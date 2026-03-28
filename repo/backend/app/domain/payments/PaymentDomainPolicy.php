<?php
declare(strict_types=1);

namespace app\domain\payments;

final class PaymentDomainPolicy
{
    public function assertPositiveAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be positive');
        }
    }
}
