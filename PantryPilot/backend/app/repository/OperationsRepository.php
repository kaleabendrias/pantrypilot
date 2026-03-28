<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class OperationsRepository
{
    public function listCampaigns(array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('campaigns')->alias('c')->order('c.id', 'desc');
        $this->applyDataScopes($query, 'c', $scopes, $authUser);
        return $query->select()->toArray();
    }

    public function createCampaign(array $data): int
    {
        return (int) Db::name('campaigns')->insertGetId([
            'name' => $data['name'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'budget' => $data['budget'] ?? 0,
            'status' => $data['status'] ?? 'planned',
            'store_id' => $data['store_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function upsertHomepageModule(string $moduleKey, array $payload, int $updatedBy): void
    {
        Db::name('homepage_modules')->insert([
            'module_key' => $moduleKey,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'enabled' => (int) ($payload['enabled'] ?? 1),
            'updated_by' => $updatedBy,
            'store_id' => $payload['store_id'] ?? null,
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'department_id' => $payload['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], true);
    }

    public function homepageModules(array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('homepage_modules')->alias('m')->order('m.module_key');
        $this->applyDataScopes($query, 'm', $scopes, $authUser);
        return $query->select()->toArray();
    }

    public function upsertMessageTemplate(array $data): int
    {
        $scopeQuery = Db::name('message_templates')->where('template_code', $data['template_code']);
        if (array_key_exists('store_id', $data)) {
            $scopeQuery->where('store_id', $data['store_id']);
        }
        if (array_key_exists('warehouse_id', $data)) {
            $scopeQuery->where('warehouse_id', $data['warehouse_id']);
        }
        if (array_key_exists('department_id', $data)) {
            $scopeQuery->where('department_id', $data['department_id']);
        }
        $id = $scopeQuery->value('id');
        if ($id) {
            Db::name('message_templates')->where('id', $id)->update([
                'title' => $data['title'],
                'content' => $data['content'],
                'category' => $data['category'] ?? 'system',
                'active' => (int) ($data['active'] ?? 1),
                'store_id' => $data['store_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return (int) $id;
        }

        return (int) Db::name('message_templates')->insertGetId([
            'template_code' => $data['template_code'],
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? 'system',
            'active' => (int) ($data['active'] ?? 1),
            'store_id' => $data['store_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function listMessageTemplates(array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('message_templates')->alias('t')->order('t.template_code');
        $this->applyDataScopes($query, 't', $scopes, $authUser);
        return $query->select()->toArray();
    }

    public function dashboardMetrics(array $scopes = [], array $authUser = []): array
    {
        $today = date('Y-m-d');
        $bookingsQ = Db::name('bookings')->alias('b')->whereDay('b.created_at', $today);
        $paymentsSuccessQ = Db::name('payments')->alias('p')->whereDay('p.created_at', $today)->where('p.status', 'captured');
        $this->applyDataScopes($bookingsQ, 'b', $scopes, $authUser);
        $this->applyDataScopes($paymentsSuccessQ, 'p', $scopes, $authUser);
        $bookings = (int) $bookingsQ->count();
        $paymentsSuccess = (int) $paymentsSuccessQ->count();
        $conversion = $bookings > 0 ? round(($paymentsSuccess / $bookings) * 100, 2) : 0.0;

        $slotIdsQuery = Db::name('bookings')->alias('b')
            ->join('pickup_slots ps', 'ps.pickup_point_id = b.pickup_point_id AND ps.slot_start = b.slot_start AND ps.slot_end = b.slot_end')
            ->whereDay('b.slot_start', $today)
            ->group('ps.id')
            ->field('ps.id');
        $this->applyDataScopes($slotIdsQuery, 'b', $scopes, $authUser);
        $slotIds = $slotIdsQuery->column('ps.id');
        $slotTotal = 0;
        $slotReserved = 0;
        if ($slotIds !== []) {
            $slotTotal = (int) Db::name('pickup_slots')->whereIn('id', $slotIds)->sum('capacity');
            $slotReserved = (int) Db::name('pickup_slots')->whereIn('id', $slotIds)->sum('reserved_count');
        }
        $slotUtil = $slotTotal > 0 ? round(($slotReserved / $slotTotal) * 100, 2) : 0.0;

        $todayNoShowQ = Db::name('bookings')->alias('b')->whereDay('b.slot_start', $today)->where('b.status', 'no_show');
        $todayBookingTotalQ = Db::name('bookings')->alias('b')->whereDay('b.slot_start', $today);
        $this->applyDataScopes($todayNoShowQ, 'b', $scopes, $authUser);
        $this->applyDataScopes($todayBookingTotalQ, 'b', $scopes, $authUser);
        $todayNoShow = (int) $todayNoShowQ->count();
        $todayBookingTotal = (int) $todayBookingTotalQ->count();
        $noShowRate = $todayBookingTotal > 0 ? round(($todayNoShow / $todayBookingTotal) * 100, 2) : 0.0;

        $paymentTotalQ = Db::name('payments')->alias('p')->whereDay('p.created_at', $today);
        $this->applyDataScopes($paymentTotalQ, 'p', $scopes, $authUser);
        $paymentTotal = (int) $paymentTotalQ->count();
        $paymentSuccessRate = $paymentTotal > 0 ? round(($paymentsSuccess / $paymentTotal) * 100, 2) : 0.0;

        return [
            'conversion_rate' => $conversion,
            'slot_utilization_rate' => $slotUtil,
            'no_show_rate' => $noShowRate,
            'payment_success_rate' => $paymentSuccessRate,
        ];
    }

    private function applyDataScopes($query, string $alias, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn($alias . '.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where($alias . '.store_id', (string) $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn($alias . '.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where($alias . '.warehouse_id', (string) $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn($alias . '.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where($alias . '.department_id', (string) $authUser['department_id']);
        }
    }
}
