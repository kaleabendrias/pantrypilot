<?php
declare(strict_types=1);

namespace app\infrastructure\time;

final class EnvClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        $fixed = getenv('PANTRYPILOT_TEST_NOW');
        if (is_string($fixed) && trim($fixed) !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($fixed));
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        return new \DateTimeImmutable('now');
    }
}
