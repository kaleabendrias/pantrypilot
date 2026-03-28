<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class BookingRepository
{
    public function list(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array
    {
        $query = Db::name('bookings')->alias('b')
            ->leftJoin('recipes r', 'r.id=b.recipe_id')
            ->leftJoin('users u', 'u.id=b.user_id')
            ->leftJoin('pickup_points p', 'p.id=b.pickup_point_id')
            ->field('b.*,r.name recipe_name,u.display_name user_name,p.name pickup_point_name');

        $this->applyDataScopes($query, $scopes, $authUser);
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $total = (int) (clone $query)->count();
        $items = $query->order('b.id', 'desc')->page($page, $perPage)->select()->toArray();

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

    public function create(array $data): int
    {
        $bookingId = (int) Db::name('bookings')->insertGetId([
            'booking_code' => $data['booking_code'],
            'recipe_id' => (int) $data['recipe_id'],
            'user_id' => (int) $data['user_id'],
            'pickup_point_id' => (int) $data['pickup_point_id'],
            'pickup_at' => $data['pickup_at'],
            'slot_start' => $data['slot_start'] ?? $data['pickup_at'],
            'slot_end' => $data['slot_end'] ?? date('Y-m-d H:i:s', strtotime($data['pickup_at']) + 1800),
            'quantity' => (int) ($data['quantity'] ?? 1),
            'status' => $data['status'] ?? 'pending',
            'note' => $data['note'] ?? null,
            'customer_zip4' => $data['customer_zip4'] ?? null,
            'customer_region_code' => $data['customer_region_code'] ?? null,
            'customer_latitude' => $data['customer_latitude'] ?? null,
            'customer_longitude' => $data['customer_longitude'] ?? null,
            'distance_km' => $data['distance_km'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $bookingId;
    }

    public function pickupPoints(): array
    {
        return Db::name('pickup_points')->where('active', 1)->order('name')->select()->toArray();
    }

    public function recipeDetail(int $recipeId): ?array
    {
        $recipe = Db::name('recipes')->where('id', $recipeId)->find();
        if (!$recipe) {
            return null;
        }

        $recipe['ingredients'] = Db::name('recipe_ingredients')->where('recipe_id', $recipeId)->column('ingredient_name');
        $recipe['cookware'] = Db::name('recipe_cookware')->where('recipe_id', $recipeId)->column('cookware_norm');
        $recipe['allergens'] = Db::name('recipe_allergens')->where('recipe_id', $recipeId)->column('allergen_norm');

        return $recipe;
    }

    public function recipeExists(int $recipeId): bool
    {
        return Db::name('recipes')->where('id', $recipeId)->count() > 0;
    }

    public function recipeInScope(int $recipeId, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('recipes')->alias('r')->where('r.id', $recipeId);

        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn('r.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('r.store_id', (string) $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn('r.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('r.warehouse_id', (string) $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn('r.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('r.department_id', (string) $authUser['department_id']);
        }

        return $query->count() > 0;
    }

    public function slotCapacity(int $pickupPointId, string $slotStart, string $slotEnd): array
    {
        $slot = Db::name('pickup_slots')
            ->where('pickup_point_id', $pickupPointId)
            ->where('slot_start', $slotStart)
            ->where('slot_end', $slotEnd)
            ->find();

        if (!$slot) {
            $point = Db::name('pickup_points')->where('id', $pickupPointId)->find();
            $capacity = (int) ($point['slot_size'] ?? 0);
            return ['capacity' => $capacity, 'reserved' => 0, 'remaining' => $capacity];
        }

        $capacity = (int) $slot['capacity'];
        $reserved = (int) $slot['reserved_count'];
        return ['capacity' => $capacity, 'reserved' => $reserved, 'remaining' => max(0, $capacity - $reserved)];
    }

    public function ensureSlot(int $pickupPointId, string $slotStart, string $slotEnd, int $capacity): void
    {
        try {
            Db::name('pickup_slots')->insert([
                'pickup_point_id' => $pickupPointId,
                'slot_start' => $slotStart,
                'slot_end' => $slotEnd,
                'capacity' => $capacity,
                'reserved_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
        }
    }

    public function reserveSlotAtomic(int $pickupPointId, string $slotStart, string $slotEnd, int $quantity): array
    {
        $slot = Db::name('pickup_slots')
            ->where('pickup_point_id', $pickupPointId)
            ->where('slot_start', $slotStart)
            ->where('slot_end', $slotEnd)
            ->lock(true)
            ->find();

        if (!$slot) {
            throw new \RuntimeException('Slot not found');
        }

        $capacity = (int) $slot['capacity'];
        $reserved = (int) $slot['reserved_count'];
        $remaining = $capacity - $reserved;
        if ($remaining < $quantity) {
            throw new \RuntimeException('Slot capacity is not enough');
        }

        Db::name('pickup_slots')->where('id', $slot['id'])->update([
            'reserved_count' => $reserved + $quantity,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'capacity' => $capacity,
            'reserved' => $reserved + $quantity,
            'remaining' => $capacity - ($reserved + $quantity),
        ];
    }

    public function pickupPointById(int $pickupPointId): ?array
    {
        $point = Db::name('pickup_points')->where('id', $pickupPointId)->find();
        return $point ?: null;
    }

    public function regionExists(string $regionCode): bool
    {
        return Db::name('address_regions')->where('region_code', $regionCode)->count() > 0;
    }

    public function zip4InRegion(string $zip4, string $regionCode): bool
    {
        return Db::name('zip4_reference')
            ->where('zip4_code', $zip4)
            ->where('region_code', $regionCode)
            ->count() > 0;
    }

    public function activeBlacklist(int $userId): ?array
    {
        $row = Db::name('booking_blacklist')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->where(function ($q) {
                $q->whereNull('blocked_until')->whereOr('blocked_until', '>', date('Y-m-d H:i:s'));
            })->find();
        return $row ?: null;
    }

    public function todaysPickups(string $today, array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('bookings')->alias('b')
            ->leftJoin('users u', 'u.id=b.user_id')
            ->leftJoin('recipes r', 'r.id=b.recipe_id')
            ->leftJoin('pickup_points p', 'p.id=b.pickup_point_id')
            ->field('b.*,u.display_name user_name,r.name recipe_name,p.name pickup_point_name')
            ->whereDay('b.slot_start', $today)
            ->order('b.slot_start', 'asc');

        $this->applyDataScopes($query, $scopes, $authUser);
        return $query->select()->toArray();
    }

    public function checkIn(int $bookingId, int $staffId): bool
    {
        return Db::name('bookings')->where('id', $bookingId)->update([
            'status' => 'arrived',
            'arrived_at' => date('Y-m-d H:i:s'),
            'checked_in_by' => $staffId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function classifyNoShows(string $cutoffTime): array
    {
        $targets = Db::name('bookings')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('slot_start', '<', $cutoffTime)
            ->whereNull('arrived_at')
            ->select()->toArray();

        $updated = 0;
        foreach ($targets as $row) {
            Db::name('bookings')->where('id', $row['id'])->update([
                'status' => 'no_show',
                'no_show_marked_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $updated++;

            $count = Db::name('bookings')
                ->where('user_id', $row['user_id'])
                ->where('status', 'no_show')
                ->whereTime('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->count();

            if ($count >= 3) {
                Db::name('booking_blacklist')->insert([
                    'user_id' => $row['user_id'],
                    'reason' => 'Repeated no-show in 30 days',
                    'blocked_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return ['classified' => $updated];
    }

    public function dispatchNote(int $bookingId): array
    {
        $booking = Db::name('bookings')->alias('b')
            ->leftJoin('users u', 'u.id=b.user_id')
            ->leftJoin('recipes r', 'r.id=b.recipe_id')
            ->leftJoin('pickup_points p', 'p.id=b.pickup_point_id')
            ->field('b.*,u.display_name user_name,r.name recipe_name,p.name pickup_point_name,p.address pickup_address')
            ->where('b.id', $bookingId)
            ->find();

        if (!$booking) {
            throw new \RuntimeException('Booking not found');
        }

        $payload = [
            'booking_code' => $booking['booking_code'],
            'customer' => $booking['user_name'],
            'recipe' => $booking['recipe_name'],
            'pickup' => $booking['pickup_point_name'],
            'address' => $booking['pickup_address'],
            'slot_start' => $booking['slot_start'] ?: $booking['pickup_at'],
            'qty' => $booking['quantity'],
        ];

        Db::name('dispatch_notes')->insert([
            'booking_id' => $bookingId,
            'note_text' => 'Dispatch note generated',
            'printable_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $payload;
    }

    public function bookingInScope(int $bookingId, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('bookings')->alias('b')->where('b.id', $bookingId);
        $this->applyDataScopes($query, $scopes, $authUser);
        $row = $query->field('b.id')->find();
        return $row !== null;
    }

    public function bookingExists(int $bookingId): bool
    {
        return Db::name('bookings')->where('id', $bookingId)->count() > 0;
    }

    private function applyDataScopes($query, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn('b.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('b.store_id', $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn('b.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('b.warehouse_id', $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn('b.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('b.department_id', $authUser['department_id']);
        }
    }
}
