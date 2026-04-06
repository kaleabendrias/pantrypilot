<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class IdentityRepository
{
    public function findByUsername(string $username): ?array
    {
        $user = Db::name('users')->where('username', $username)->find();
        return $user ?: null;
    }

    public function createUser(array $data): int
    {
        return (int) Db::name('users')->insertGetId([
            'username' => $data['username'],
            'password_hash' => $data['password_hash'],
            'display_name' => $data['display_name'],
            'role' => $data['role'] ?? 'staff',
            'phone_enc' => $data['phone_enc'] ?? null,
            'address_enc' => $data['address_enc'] ?? null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'account_enabled' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function assignRoleByCode(int $userId, string $roleCode): void
    {
        $role = Db::name('roles')->where('code', $roleCode)->find();
        if ($role) {
            Db::name('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => (int) $role['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function incrementFailedLogin(int $userId, int $attempts, ?string $lockedUntil): void
    {
        Db::name('users')->where('id', $userId)->update([
            'failed_login_attempts' => $attempts,
            'last_failed_login_at' => date('Y-m-d H:i:s'),
            'locked_until' => $lockedUntil,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function clearFailedLogins(int $userId): void
    {
        Db::name('users')->where('id', $userId)->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_failed_login_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findById(int $id): ?array
    {
        $user = Db::name('users')->where('id', $id)->find();
        return $user ?: null;
    }

    public function setAccountEnabled(int $userId, bool $enabled): bool
    {
        return Db::name('users')->where('id', $userId)->update([
            'account_enabled' => $enabled ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): bool
    {
        return Db::name('users')->where('id', $userId)->update([
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function clearPasswordResetRequired(int $userId): void
    {
        Db::name('users')->where('id', $userId)->update([
            'password_reset_required' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function replaceDataScopes(int $userId, array $scopeMap): void
    {
        Db::name('user_data_scopes')->where('user_id', $userId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach (['store', 'warehouse', 'department'] as $type) {
            $values = $scopeMap[$type] ?? [];
            foreach ($values as $value) {
                Db::name('user_data_scopes')->insert([
                    'user_id' => $userId,
                    'scope_type' => $type,
                    'scope_value' => (string) $value,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
