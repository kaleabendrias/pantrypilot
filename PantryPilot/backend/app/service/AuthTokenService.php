<?php
declare(strict_types=1);

namespace app\service;

final class AuthTokenService
{
    public function issueToken(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function expirationDateTime(): string
    {
        return date('Y-m-d H:i:s', time() + (8 * 3600));
    }
}
