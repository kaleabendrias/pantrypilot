<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\repository\AdminRepository;
use app\repository\IdentityRepository;
use think\facade\Config;
use think\facade\Db;

final class AdministrationService
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly IdentityRepository $identityRepository,
        private readonly CryptoService $cryptoService
    ) {
    }

    public function users(string $usernameFilter = ''): array
    {
        $fields = ['id', 'username', 'display_name', 'role', 'store_id', 'warehouse_id', 'department_id', 'account_enabled', 'created_at', 'phone_enc', 'address_enc'];
        if ($usernameFilter === '') {
            $rows = \think\facade\Db::name('users')->field($fields)->order('id', 'desc')->select()->toArray();
            return $this->maskSensitiveContacts($rows);
        }

        $rows = \think\facade\Db::name('users')->field($fields)->whereLike('username', "%{$usernameFilter}%")->order('id', 'desc')->select()->toArray();
        return $this->maskSensitiveContacts($rows);
    }

    public function audit(string $action, string $targetType, string $targetId, ?int $actorId = null, array $meta = []): int
    {
        return $this->adminRepository->addAudit([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $meta,
        ]);
    }

    public function auditLogs(int $page = 1, int $perPage = 20): array
    {
        return $this->adminRepository->listAuditLogs($page, $perPage);
    }

    public function issueCriticalReauthToken(int $userId, string $password): array
    {
        $user = $this->identityRepository->findById($userId);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new \RuntimeException('Re-authentication failed');
        }

        $rawToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $rawToken);
        $ttl = (int) Config::get('security.auth.critical_reauth_ttl_seconds', 300);
        $expireAt = date('Y-m-d H:i:s', time() + $ttl);
        $this->adminRepository->issueReauthToken($userId, $tokenHash, $expireAt);

        return ['reauth_token' => $rawToken, 'expire_at' => $expireAt];
    }

    public function consumeCriticalReauthToken(int $userId, string $rawToken): bool
    {
        return $this->adminRepository->consumeReauthToken($userId, hash('sha256', $rawToken));
    }

    public function roles(): array
    {
        return Db::name('roles')->order('code')->select()->toArray();
    }

    public function permissions(): array
    {
        return Db::name('permissions')->order('code')->select()->toArray();
    }

    public function resources(): array
    {
        return Db::name('resources')->order('code')->select()->toArray();
    }

    public function createRole(array $payload): array
    {
        if (empty($payload['code']) || empty($payload['name'])) {
            throw new \InvalidArgumentException('code and name are required');
        }

        $id = (int) Db::name('roles')->insertGetId([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $id];
    }

    public function grantRolePermissionResource(array $payload): array
    {
        Db::name('role_permission_resources')->insert([
            'role_id' => (int) $payload['role_id'],
            'permission_id' => (int) $payload['permission_id'],
            'resource_id' => (int) $payload['resource_id'],
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        return ['granted' => true];
    }

    public function assignRoleToUser(array $payload): array
    {
        Db::name('user_roles')->insert([
            'user_id' => (int) $payload['user_id'],
            'role_id' => (int) $payload['role_id'],
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        return ['assigned' => true];
    }

    public function setAccountEnabled(int $targetUserId, bool $enabled, array $actorUser, array $actorScopes): array
    {
        $targetUser = $this->identityRepository->findById($targetUserId);
        if (!$targetUser) {
            throw new NotFoundException('User not found');
        }
        $this->assertManageableTarget($targetUser, $actorUser, $actorScopes);
        $this->identityRepository->setAccountEnabled($targetUserId, $enabled);
        $this->audit($enabled ? 'enable_account' : 'disable_account', 'user', (string) $targetUserId, (int) ($actorUser['id'] ?? 0));
        return ['user_id' => $targetUserId, 'account_enabled' => $enabled];
    }

    public function adminResetPassword(int $targetUserId, string $newPassword, array $actorUser, array $actorScopes): array
    {
        $targetUser = $this->identityRepository->findById($targetUserId);
        if (!$targetUser) {
            throw new NotFoundException('User not found');
        }
        $this->assertManageableTarget($targetUser, $actorUser, $actorScopes);

        $password = trim($newPassword);
        if ($password === '') {
            throw new ValidationException('new_password is required');
        }
        $this->identityRepository->updatePasswordHash($targetUserId, password_hash($password, PASSWORD_BCRYPT));
        $this->audit('reset_password', 'user', (string) $targetUserId, (int) ($actorUser['id'] ?? 0));

        return ['user_id' => $targetUserId, 'password_reset' => true];
    }

    public function updateUserDataScopes(int $targetUserId, array $scopePayload, array $actorUser, array $actorScopes): array
    {
        $targetUser = $this->identityRepository->findById($targetUserId);
        if (!$targetUser) {
            throw new NotFoundException('User not found');
        }
        $this->assertManageableTarget($targetUser, $actorUser, $actorScopes);

        $scopeMap = [
            'store' => $this->normalizeScopeValues($scopePayload['store'] ?? []),
            'warehouse' => $this->normalizeScopeValues($scopePayload['warehouse'] ?? []),
            'department' => $this->normalizeScopeValues($scopePayload['department'] ?? []),
        ];

        $this->assertScopeReferencesExist($scopeMap);

        if (!$this->isGlobalActor($actorUser)) {
            foreach (['store', 'warehouse', 'department'] as $scopeType) {
                $allowed = array_map('strval', $actorScopes[$scopeType] ?? []);
                foreach ($scopeMap[$scopeType] as $value) {
                    if (!in_array((string) $value, $allowed, true)) {
                        throw new ForbiddenException('Forbidden');
                    }
                }
            }
        }

        Db::transaction(function () use ($targetUserId, $scopeMap, $actorUser): void {
            $this->identityRepository->replaceDataScopes($targetUserId, $scopeMap);
            Db::name('users')->where('id', $targetUserId)->update([
                'store_id' => $scopeMap['store'][0] ?? null,
                'warehouse_id' => $scopeMap['warehouse'][0] ?? null,
                'department_id' => $scopeMap['department'][0] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->audit('update_user_scopes', 'user', (string) $targetUserId, (int) ($actorUser['id'] ?? 0), ['scopes' => $scopeMap]);
        });

        return ['user_id' => $targetUserId, 'scopes' => $scopeMap];
    }

    private function maskSensitiveContacts(array $rows): array
    {
        foreach ($rows as &$row) {
            $phone = $this->cryptoService->decrypt((string) ($row['phone_enc'] ?? ''));
            $address = $this->cryptoService->decrypt((string) ($row['address_enc'] ?? ''));
            $row['phone_masked'] = $this->cryptoService->mask($phone);
            $row['address_masked'] = $this->cryptoService->mask($address);
            unset($row['phone_enc'], $row['address_enc']);
        }
        return $rows;
    }

    private function isGlobalActor(array $actorUser): bool
    {
        return (string) ($actorUser['role'] ?? '') === 'admin';
    }

    private function assertManageableTarget(array $targetUser, array $actorUser, array $actorScopes): void
    {
        $actorId = (int) ($actorUser['id'] ?? 0);
        $targetId = (int) ($targetUser['id'] ?? 0);
        if ($actorId < 1 || $targetId < 1) {
            throw new ForbiddenException('Forbidden');
        }
        if ($targetId === $actorId) {
            throw new ValidationException('Self-management is not allowed for this action');
        }
        if ($this->isGlobalActor($actorUser)) {
            return;
        }

        if ((string) ($targetUser['role'] ?? '') === 'admin') {
            throw new ForbiddenException('Forbidden');
        }

        foreach (['store', 'warehouse', 'department'] as $scopeType) {
            $allowed = array_map('strval', $actorScopes[$scopeType] ?? []);
            $field = $scopeType . '_id';
            $targetValue = (string) ($targetUser[$field] ?? '');
            if ($targetValue !== '' && $allowed !== [] && !in_array($targetValue, $allowed, true)) {
                throw new ForbiddenException('Forbidden');
            }
        }
    }

    private function normalizeScopeValues(array|string $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/[\s,]+/', trim($values)) ?: [];
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }
        return array_values(array_unique($normalized));
    }

    private function assertScopeReferencesExist(array $scopeMap): void
    {
        $ref = [
            'store' => ['table' => 'stores', 'column' => 'id'],
            'warehouse' => ['table' => 'warehouses', 'column' => 'id'],
            'department' => ['table' => 'departments', 'column' => 'id'],
        ];

        foreach ($ref as $scopeType => $meta) {
            $values = $scopeMap[$scopeType] ?? [];
            if ($values === []) {
                continue;
            }
            $existing = Db::name($meta['table'])->whereIn($meta['column'], $values)->column($meta['column']);
            $existingSet = array_map('strval', $existing);
            foreach ($values as $value) {
                if (!in_array((string) $value, $existingSet, true)) {
                    throw new ValidationException($scopeType . ' scope value not found: ' . $value);
                }
            }
        }
    }
}
