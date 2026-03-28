<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class AdminRepository
{
    public function addAudit(array $data): int
    {
        $prevHash = (string) (Db::name('audit_logs')->order('id', 'desc')->value('hash_current') ?? 'GENESIS');
        $payload = json_encode($data['metadata'] ?? [], JSON_UNESCAPED_UNICODE);
        $currentHash = hash('sha256', implode('|', [
            $prevHash,
            (string) ($data['actor_id'] ?? 0),
            (string) $data['action'],
            (string) $data['target_type'],
            (string) $data['target_id'],
            $payload,
            date('c'),
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
    }

    public function listAuditLogs(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $query = Db::name('audit_logs');
        $total = (int) (clone $query)->count();
        $items = $query->order('id', 'desc')->page($page, $perPage)->select()->toArray();
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
