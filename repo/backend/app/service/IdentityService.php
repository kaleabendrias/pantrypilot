<?php
declare(strict_types=1);

namespace app\service;

use app\domain\identity\LoginLockPolicy;
use app\domain\identity\PasswordPolicy;
use app\repository\AuthorizationRepository;
use app\repository\AuthSessionRepository;
use app\repository\IdentityRepository;
use think\facade\Log;

final class IdentityService
{
    public function __construct(
        private readonly IdentityRepository $identityRepository,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly LoginLockPolicy $loginLockPolicy,
        private readonly AuthTokenService $authTokenService,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly CryptoService $cryptoService,
        private readonly AuthorizationRepository $authorizationRepository = new AuthorizationRepository()
    )
    {
    }

    public function register(array $payload): array
    {
        $username = (string) ($payload['username'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            throw new \InvalidArgumentException('Username must be 3-50 chars, letters/numbers/underscore only');
        }

        $exists = $this->identityRepository->findByUsername($payload['username']);
        if ($exists) {
            throw new \InvalidArgumentException('Username already exists');
        }

        $this->passwordPolicy->assertValid((string) ($payload['password'] ?? ''));

        $id = $this->identityRepository->createUser([
            'username' => $payload['username'],
            'display_name' => $payload['display_name'] ?? $payload['username'],
            'password_hash' => password_hash($payload['password'], PASSWORD_BCRYPT),
            'role' => 'customer',
            'phone_enc' => $this->cryptoService->encrypt((string) ($payload['phone'] ?? '')),
            'address_enc' => $this->cryptoService->encrypt((string) ($payload['address'] ?? '')),
        ]);

        $this->identityRepository->assignRoleByCode($id, 'customer');

        return ['id' => $id];
    }

    public function login(string $username, string $password, string $ip = '', string $userAgent = ''): array
    {
        $user = $this->identityRepository->findByUsername($username);

        if (!$user) {
            Log::info('identity.login.failed', ['username' => $username, 'reason' => 'user_not_found', 'ip' => $ip]);
            throw new \RuntimeException('Invalid credentials');
        }

        if ((int) ($user['account_enabled'] ?? 1) !== 1) {
            Log::info('identity.login.failed', ['user_id' => (int) $user['id'], 'username' => $username, 'reason' => 'account_disabled', 'ip' => $ip]);
            throw new \RuntimeException('Account disabled');
        }

        try {
            $this->loginLockPolicy->assertNotLocked($user['locked_until'] ?? null);
        } catch (\RuntimeException $e) {
            Log::info('identity.login.failed', ['user_id' => (int) $user['id'], 'username' => $username, 'reason' => 'locked', 'locked_until' => $user['locked_until'] ?? null, 'ip' => $ip]);
            throw $e;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $this->loginLockPolicy->nextAttempts((int) ($user['failed_login_attempts'] ?? 0));
            $lockedUntil = $this->loginLockPolicy->shouldLock($attempts) ? $this->loginLockPolicy->lockedUntil() : null;
            $this->identityRepository->incrementFailedLogin((int) $user['id'], $attempts, $lockedUntil);
            Log::info('identity.login.failed', ['user_id' => (int) $user['id'], 'username' => $username, 'reason' => 'bad_password', 'attempts' => $attempts, 'locked_until' => $lockedUntil, 'ip' => $ip]);
            throw new \RuntimeException('Invalid credentials');
        }

        $this->identityRepository->clearFailedLogins((int) $user['id']);

        $token = $this->authTokenService->issueToken();
        $this->authSessionRepository->create(
            (int) $user['id'],
            $this->authTokenService->hashToken($token),
            $ip,
            $userAgent,
            $this->authTokenService->expirationDateTime()
        );

        Log::info('identity.login.success', ['user_id' => (int) $user['id'], 'username' => $username, 'ip' => $ip]);

        $assignedRole = $this->authorizationRepository->assignedRoleCode((int) $user['id']);
        $effectiveRole = $assignedRole ?: (string) $user['role'];
        $grants = $this->authorizationRepository->grantKeys((int) $user['id']);

        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'role' => $effectiveRole,
                'permissions' => $grants,
            ],
        ];
    }

    public function rotatePassword(string $username, string $currentPassword, string $newPassword): array
    {
        $user = $this->identityRepository->findByUsername($username);
        if (!$user) {
            throw new \RuntimeException('Invalid credentials');
        }

        try {
            $this->loginLockPolicy->assertNotLocked($user['locked_until'] ?? null);
        } catch (\RuntimeException $e) {
            Log::info('identity.rotate_password.failed', ['username' => $username, 'reason' => 'locked']);
            throw $e;
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $attempts = $this->loginLockPolicy->nextAttempts((int) ($user['failed_login_attempts'] ?? 0));
            $lockedUntil = $this->loginLockPolicy->shouldLock($attempts) ? $this->loginLockPolicy->lockedUntil() : null;
            $this->identityRepository->incrementFailedLogin((int) $user['id'], $attempts, $lockedUntil);
            throw new \RuntimeException('Invalid credentials');
        }

        $this->passwordPolicy->assertValid($newPassword);

        $this->identityRepository->updatePasswordHash((int) $user['id'], password_hash($newPassword, PASSWORD_BCRYPT));
        $this->identityRepository->clearPasswordResetRequired((int) $user['id']);
        $this->identityRepository->clearFailedLogins((int) $user['id']);

        Log::info('identity.password_rotated', ['user_id' => (int) $user['id'], 'username' => $username]);

        return ['user_id' => (int) $user['id'], 'password_rotated' => true];
    }

    public function userByToken(string $rawToken): ?array
    {
        if ($rawToken === '') {
            return null;
        }

        $session = $this->authSessionRepository->findActiveByTokenHash($this->authTokenService->hashToken($rawToken));
        if (!$session) {
            return null;
        }

        $this->authSessionRepository->touch((int) $session['id']);
        $user = $this->identityRepository->findById((int) $session['user_id']);
        if (!$user) {
            return null;
        }
        if ((int) ($user['account_enabled'] ?? 1) !== 1) {
            return null;
        }

        $assignedRole = $this->authorizationRepository->assignedRoleCode((int) $user['id']);
        $effectiveRole = $assignedRole ?: (string) $user['role'];

        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
            'role' => $effectiveRole,
            'store_id' => (string) ($user['store_id'] ?? ''),
            'warehouse_id' => (string) ($user['warehouse_id'] ?? ''),
            'department_id' => (string) ($user['department_id'] ?? ''),
        ];
    }
}
