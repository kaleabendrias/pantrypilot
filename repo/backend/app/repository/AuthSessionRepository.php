<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class AuthSessionRepository
{
    public function create(int $userId, string $tokenHash, string $ip, string $userAgent, string $expiresAt): int
    {
        return (int) Db::name('auth_sessions')->insertGetId([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ip_address' => $ip,
            'user_agent' => substr($userAgent, 0, 255),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $session = Db::name('auth_sessions')
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();

        return $session ?: null;
    }

    public function touch(int $sessionId): void
    {
        Db::name('auth_sessions')->where('id', $sessionId)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
