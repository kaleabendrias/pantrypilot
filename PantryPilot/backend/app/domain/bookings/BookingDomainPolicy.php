<?php
declare(strict_types=1);

namespace app\domain\bookings;

final class BookingDomainPolicy
{
    public function assertFuturePickup(string $pickupAt): void
    {
        if (strtotime($pickupAt) <= time()) {
            throw new \DomainException('Pickup time must be in the future');
        }
    }
}
