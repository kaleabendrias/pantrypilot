<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class AuthorizationRepository
{
    public function hasPermission(int $userId, string $resourceCode, string $permissionCode): bool
    {
        $count = Db::name('user_roles')->alias('ur')
            ->join('role_permission_resources rpr', 'rpr.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rpr.permission_id')
            ->join('resources r', 'r.id = rpr.resource_id')
            ->where('ur.user_id', $userId)
            ->where('p.code', $permissionCode)
            ->where('r.code', $resourceCode)
            ->count();

        return $count > 0;
    }

    public function scopesByUser(int $userId): array
    {
        $rows = Db::name('user_data_scopes')->where('user_id', $userId)->select()->toArray();
        $scopes = [
            'store' => [],
            'warehouse' => [],
            'department' => [],
        ];

        foreach ($rows as $row) {
            $type = (string) $row['scope_type'];
            if (!isset($scopes[$type])) {
                continue;
            }
            $scopes[$type][] = (string) $row['scope_value'];
        }

        return $scopes;
    }
}
