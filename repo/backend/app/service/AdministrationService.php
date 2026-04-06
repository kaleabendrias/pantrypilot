<?php
declare(strict_types=1);

namespace app\service;

use app\domain\identity\PasswordPolicy;
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
        private readonly CryptoService $cryptoService,
        private readonly PasswordPolicy $passwordPolicy = new PasswordPolicy()
    ) {
    }

    public function users(string $usernameFilter = '', array $scopes = [], array $authUser = []): array
    {
        $fields = ['id', 'username', 'display_name', 'role', 'store_id', 'warehouse_id', 'department_id', 'account_enabled', 'created_at', 'phone_enc', 'address_enc'];
        $query = Db::name('users')->field($fields)->order('id', 'desc');

        if ($usernameFilter !== '') {
            $query->whereLike('username', "%{$usernameFilter}%");
        }

        if (!$this->isGlobalActor($authUser)) {
            $this->applyScopeFilter($query, $scopes, $authUser);
        }

        $rows = $query->select()->toArray();
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

    public function auditLogs(int $page = 1, int $perPage = 20, array $scopes = [], array $authUser = []): array
    {
        return $this->adminRepository->listAuditLogs($page, $perPage, $scopes, $authUser);
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

    public function createRole(array $payload, array $actorUser = []): array
    {
        if (!$this->isGlobalActor($actorUser)) {
            throw new ForbiddenException('Only global administrators can create roles');
        }
        if (empty($payload['code']) || empty($payload['name'])) {
            throw new \InvalidArgumentException('code and name are required');
        }

        $id = (int) Db::name('roles')->insertGetId([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit('create_role', 'role', (string) $id, (int) ($actorUser['id'] ?? 0), ['code' => $payload['code']]);

        return ['id' => $id];
    }

    public function grantRolePermissionResource(array $payload, array $actorUser = []): array
    {
        if (!$this->isGlobalActor($actorUser)) {
            throw new ForbiddenException('Only global administrators can grant permissions');
        }

        Db::name('role_permission_resources')->insert([
            'role_id' => (int) $payload['role_id'],
            'permission_id' => (int) $payload['permission_id'],
            'resource_id' => (int) $payload['resource_id'],
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $this->audit('grant_permission', 'role_permission_resource', (string) ($payload['role_id'] ?? 0), (int) ($actorUser['id'] ?? 0), [
            'permission_id' => (int) $payload['permission_id'],
            'resource_id' => (int) $payload['resource_id'],
        ]);

        return ['granted' => true];
    }

    private const PRIVILEGED_ROLE_CODES = ['admin'];

    public function assignRoleToUser(array $payload, array $actorUser = [], array $actorScopes = []): array
    {
        $roleId = (int) $payload['role_id'];
        $targetRole = Db::name('roles')->where('id', $roleId)->find();
        if (!$targetRole) {
            throw new NotFoundException('Role not found');
        }

        $isPrivileged = in_array((string) ($targetRole['code'] ?? ''), self::PRIVILEGED_ROLE_CODES, true);

        if (!$this->isGlobalActor($actorUser)) {
            if ($isPrivileged) {
                throw new ForbiddenException('Only global administrators can assign privileged roles');
            }
            $targetUser = $this->identityRepository->findById((int) $payload['user_id']);
            if (!$targetUser) {
                throw new NotFoundException('User not found');
            }
            $this->assertManageableTarget($targetUser, $actorUser, $actorScopes);
        }

        Db::name('user_roles')->insert([
            'user_id' => (int) $payload['user_id'],
            'role_id' => $roleId,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $this->audit('assign_role', 'user_role', (string) ($payload['user_id'] ?? 0), (int) ($actorUser['id'] ?? 0), [
            'role_id' => $roleId,
            'role_code' => (string) ($targetRole['code'] ?? ''),
        ]);

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
        $this->passwordPolicy->assertValid($password);
        $this->identityRepository->updatePasswordHash($targetUserId, password_hash($password, PASSWORD_BCRYPT));
        $this->identityRepository->clearPasswordResetRequired($targetUserId);
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
                $explicit = array_map('strval', $actorScopes[$scopeType] ?? []);
                $fallback = (string) ($actorUser[$scopeType . '_id'] ?? '');
                $allowed = $explicit !== [] ? $explicit : ($fallback !== '' ? [$fallback] : []);
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
        return ScopeHelper::isGlobalAdmin($actorUser);
    }

    private function applyScopeFilter($query, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn('store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('store_id', (string) $authUser['store_id']);
        }
        if ($warehouse !== []) {
            $query->whereIn('warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('warehouse_id', (string) $authUser['warehouse_id']);
        }
        if ($department !== []) {
            $query->whereIn('department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('department_id', (string) $authUser['department_id']);
        }
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
            $explicitScopes = array_map('strval', $actorScopes[$scopeType] ?? []);
            $fallbackField = $scopeType . '_id';
            $fallbackValue = (string) ($actorUser[$fallbackField] ?? '');

            $allowed = $explicitScopes;
            if ($allowed === [] && $fallbackValue !== '') {
                $allowed = [$fallbackValue];
            }

            if ($allowed === []) {
                throw new ForbiddenException('Forbidden');
            }

            $targetValue = (string) ($targetUser[$fallbackField] ?? '');
            if ($targetValue !== '' && !in_array($targetValue, $allowed, true)) {
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
