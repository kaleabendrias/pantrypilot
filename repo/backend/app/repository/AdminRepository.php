<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class AdminRepository
{
    public function addAudit(array $data): int
    {
        return (int) Db::transaction(function () use ($data): int {
            $prevRow = Db::name('audit_logs')->order('id', 'desc')->lock(true)->field('hash_current')->find();
            $prevHash = (string) ($prevRow['hash_current'] ?? 'GENESIS');
            $payload = json_encode($data['metadata'] ?? [], JSON_UNESCAPED_UNICODE);
            $now = date('c');
            $currentHash = hash('sha256', implode('|', [
                $prevHash,
                (string) ($data['actor_id'] ?? 0),
                (string) $data['action'],
                (string) $data['target_type'],
                (string) $data['target_id'],
                $payload,
                $now,
            ]));

            return (int) Db::name('audit_logs')->insertGetId([
                'actor_id' => $data['actor_id'] ?? null,
                'action' => $data['action'],
                'target_type' => $data['target_type'],
                'target_id' => $data['target_id'],
                'metadata' => $payload,
                'prev_hash' => $prevHash,
                'hash_current' => $currentHash,
                'ip_address' => $data['ip_address'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function listAuditLogs(int $page = 1, int $perPage = 20, array $scopes = [], array $authUser = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $query = Db::name('audit_logs')->alias('al');

        if (!\app\service\ScopeHelper::isGlobalAdmin($authUser)) {
            $actorIds = Db::name('users');
            $store = $scopes['store'] ?? [];
            $warehouse = $scopes['warehouse'] ?? [];
            $department = $scopes['department'] ?? [];
            if ($store !== []) { $actorIds->whereIn('store_id', $store); }
            elseif (!empty($authUser['store_id'])) { $actorIds->where('store_id', (string) $authUser['store_id']); }
            if ($warehouse !== []) { $actorIds->whereIn('warehouse_id', $warehouse); }
            elseif (!empty($authUser['warehouse_id'])) { $actorIds->where('warehouse_id', (string) $authUser['warehouse_id']); }
            if ($department !== []) { $actorIds->whereIn('department_id', $department); }
            elseif (!empty($authUser['department_id'])) { $actorIds->where('department_id', (string) $authUser['department_id']); }
            $scopedUserIds = $actorIds->column('id');
            if (!empty($scopedUserIds)) {
                $query->whereIn('al.actor_id', $scopedUserIds);
            } else {
                $query->where('al.actor_id', (int) ($authUser['id'] ?? 0));
            }
        }

        $total = (int) (clone $query)->count();
        $items = $query->order('al.id', 'desc')->page($page, $perPage)->select()->toArray();
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function issueReauthToken(int $userId, string $tokenHash, string $expireAt): int
    {
        return (int) Db::name('critical_reauth_tokens')->insertGetId([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expire_at' => $expireAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function consumeReauthToken(int $userId, string $tokenHash): bool
    {
        return Db::name('critical_reauth_tokens')
            ->where('user_id', $userId)
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expire_at', '>', date('Y-m-d H:i:s'))
            ->update(['consumed_at' => date('Y-m-d H:i:s')]) > 0;
    }
}
