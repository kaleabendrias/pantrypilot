<?php
declare(strict_types=1);

namespace app\domain\identity;

final class LoginLockPolicy
{
    public function assertNotLocked(?string $lockedUntil): void
    {
        if ($lockedUntil && strtotime($lockedUntil) > time()) {
            throw new \RuntimeException('Account temporarily locked. Try again later.');
        }
    }

    public function nextAttempts(int $currentAttempts): int
    {
        return $currentAttempts + 1;
    }

    public function shouldLock(int $attempts): bool
    {
        return $attempts >= 5;
    }

    public function lockedUntil(): string
    {
        return date('Y-m-d H:i:s', time() + (15 * 60));
    }
}
