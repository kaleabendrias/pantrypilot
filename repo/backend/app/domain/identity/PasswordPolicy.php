<?php
declare(strict_types=1);

namespace app\domain\identity;

final class PasswordPolicy
{
    public function assertValid(string $password): void
    {
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('Password must be at least 10 characters');
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one letter');
        }

        if (!preg_match('/\d/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one number');
        }
    }
}
