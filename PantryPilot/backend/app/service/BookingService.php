<?php
declare(strict_types=1);

namespace app\service;

use app\domain\bookings\BookingDomainPolicy;
use app\exception\ConflictException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\repository\BookingRepository;
use think\facade\Db;

final class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingDomainPolicy $bookingDomainPolicy
    )
    {
    }

    public function list(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array
    {
        return $this->bookingRepository->list($scopes, $authUser, $page, $perPage);
    }

    public function create(array $payload): array
    {
        $this->bookingDomainPolicy->assertFuturePickup($payload['pickup_at']);

        $pickupAtTs = strtotime($payload['pickup_at']);
        if ($pickupAtTs === false) {
            throw new \InvalidArgumentException('Invalid pickup_at');
        }

        if ($pickupAtTs > strtotime('+7 days')) {
            throw new ValidationException('Booking can only be made up to 7 days ahead');
        }

        if ($pickupAtTs - time() < 7200) {
            throw new ValidationException('Booking cutoff is 2 hours before pickup');
        }

        $blacklist = $this->bookingRepository->activeBlacklist((int) $payload['user_id']);
        if ($blacklist) {
            throw new ValidationException('User is temporarily blacklisted due to repeated no-show');
        }

        $zip4 = (string) ($payload['customer_zip4'] ?? '');
        if ($zip4 !== '' && !preg_match('/^\d{5}-\d{4}$/', $zip4)) {
            throw new ValidationException('ZIP+4 must be in format 12345-6789');
        }

        $regionCode = (string) ($payload['customer_region_code'] ?? '');
        if ($regionCode !== '' && !$this->bookingRepository->regionExists($regionCode)) {
            throw new ValidationException('Invalid admin region');
        }

        if ($zip4 !== '' && $regionCode !== '' && !$this->bookingRepository->zip4InRegion($zip4, $regionCode)) {
            throw new ValidationException('ZIP+4 does not match admin region');
        }

        $slotStart = (string) ($payload['slot_start'] ?? $payload['pickup_at']);
        $slotEnd = (string) ($payload['slot_end'] ?? date('Y-m-d H:i:s', strtotime($slotStart) + 1800));
        $point = $this->bookingRepository->pickupPointById((int) $payload['pickup_point_id']);
        if (!$point) {
            throw new NotFoundException('Pickup point not found');
        }

        $qty = (int) ($payload['quantity'] ?? 1);

        if (!empty($payload['customer_latitude']) && !empty($payload['customer_longitude'])
            && !empty($point['latitude']) && !empty($point['longitude'])) {
            $distance = $this->haversine(
                (float) $payload['customer_latitude'],
                (float) $payload['customer_longitude'],
                (float) $point['latitude'],
                (float) $point['longitude']
            );

            if ($distance > (float) ($point['service_radius_km'] ?? 0)) {
                throw new ValidationException('Address is outside service radius');
            }
            $payload['distance_km'] = round($distance, 3);
        }

        $payload['slot_start'] = $slotStart;
        $payload['slot_end'] = $slotEnd;
        $payload['booking_code'] = $payload['booking_code'] ?? 'BKG-' . strtoupper(bin2hex(random_bytes(3)));

        try {
            $id = Db::transaction(function () use ($point, $slotStart, $slotEnd, $qty, $payload): int {
                $this->bookingRepository->ensureSlot((int) $point['id'], $slotStart, $slotEnd, (int) ($point['slot_size'] ?? 0));
                $this->bookingRepository->reserveSlotAtomic((int) $point['id'], $slotStart, $slotEnd, $qty);
                return $this->bookingRepository->create($payload);
            });
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Slot capacity is not enough') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new ConflictException(str_contains($e->getMessage(), 'Slot capacity is not enough') ? 'Slot capacity is not enough' : 'Duplicate entry', $e);
            }
            if (str_contains($e->getMessage(), 'Slot not found')) {
                throw new NotFoundException('Slot not found', $e);
            }
            throw $e;
        }
        $remaining = $this->bookingRepository->slotCapacity((int) $point['id'], $slotStart, $slotEnd);
        return ['id' => $id, 'booking_code' => $payload['booking_code'], 'slot_remaining' => $remaining['remaining']];
    }

    public function pickupPoints(): array
    {
        return $this->bookingRepository->pickupPoints();
    }

    public function recipeDetail(int $recipeId): array
    {
        $recipe = $this->bookingRepository->recipeDetail($recipeId);
        if (!$recipe) {
            throw new NotFoundException('Recipe not found');
        }
        return $recipe;
    }

    public function recipeExists(int $recipeId): bool
    {
        return $this->bookingRepository->recipeExists($recipeId);
    }

    public function canAccessRecipe(int $recipeId, array $scopes = [], array $authUser = []): bool
    {
        return $this->bookingRepository->recipeInScope($recipeId, $scopes, $authUser);
    }

    public function slotCapacity(int $pickupPointId, string $slotStart, string $slotEnd): array
    {
        return $this->bookingRepository->slotCapacity($pickupPointId, $slotStart, $slotEnd);
    }

    public function todaysPickups(array $scopes = [], array $authUser = []): array
    {
        return $this->bookingRepository->todaysPickups(date('Y-m-d'), $scopes, $authUser);
    }

    public function checkIn(int $bookingId, int $staffId): bool
    {
        return $this->bookingRepository->checkIn($bookingId, $staffId);
    }

    public function autoClassifyNoShow(): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - (15 * 60));
        return $this->bookingRepository->classifyNoShows($cutoff);
    }

    public function printableDispatchNote(int $bookingId): array
    {
        return $this->bookingRepository->dispatchNote($bookingId);
    }

    public function canAccessBooking(int $bookingId, array $scopes = [], array $authUser = []): bool
    {
        return $this->bookingRepository->bookingInScope($bookingId, $scopes, $authUser);
    }

    public function bookingExists(int $bookingId): bool
    {
        return $this->bookingRepository->bookingExists($bookingId);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
