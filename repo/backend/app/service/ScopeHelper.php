<?php
declare(strict_types=1);

namespace app\service;

final class ScopeHelper
{
    public static function isGlobalAdmin(array $authUser): bool
    {
        return (string) ($authUser['role'] ?? '') === 'admin';
    }

    public static function applyStandardScopes($query, string $alias, array $scopes, array $authUser): void
    {
        if (self::isGlobalAdmin($authUser)) {
            return;
        }

        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn($alias . '.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where($alias . '.store_id', (string) $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn($alias . '.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where($alias . '.warehouse_id', (string) $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn($alias . '.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where($alias . '.department_id', (string) $authUser['department_id']);
        }
    }

    public static function applyNullableScopeFilter($query, string $alias, array $scopes, array $authUser): void
    {
        if (self::isGlobalAdmin($authUser)) {
            return;
        }

        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->where(function ($q) use ($alias, $store) { $q->whereIn($alias . '.store_id', $store)->whereOr($alias . '.store_id', null); });
        } elseif (!empty($authUser['store_id'])) {
            $query->where(function ($q) use ($alias, $authUser) { $q->where($alias . '.store_id', (string) $authUser['store_id'])->whereOr($alias . '.store_id', null); });
        }
        if ($warehouse !== []) {
            $query->where(function ($q) use ($alias, $warehouse) { $q->whereIn($alias . '.warehouse_id', $warehouse)->whereOr($alias . '.warehouse_id', null); });
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where(function ($q) use ($alias, $authUser) { $q->where($alias . '.warehouse_id', (string) $authUser['warehouse_id'])->whereOr($alias . '.warehouse_id', null); });
        }
        if ($department !== []) {
            $query->where(function ($q) use ($alias, $department) { $q->whereIn($alias . '.department_id', $department)->whereOr($alias . '.department_id', null); });
        } elseif (!empty($authUser['department_id'])) {
            $query->where(function ($q) use ($alias, $authUser) { $q->where($alias . '.department_id', (string) $authUser['department_id'])->whereOr($alias . '.department_id', null); });
        }
    }
}
