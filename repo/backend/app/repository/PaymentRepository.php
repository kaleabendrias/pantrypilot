<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class PaymentRepository
{
    public function listPayments(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array
    {
        $query = Db::name('payments')->alias('p')
            ->leftJoin('bookings b', 'b.id=p.booking_id')
            ->field('p.*,b.booking_code');

        $this->applyDataScopes($query, $scopes, $authUser);
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $total = (int) (clone $query)->count();
        $items = $query->order('p.id', 'desc')->page($page, $perPage)->select()->toArray();
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

    public function createPayment(array $data): int
    {
        return (int) Db::name('payments')->insertGetId([
            'payment_ref' => $data['payment_ref'],
            'booking_id' => (int) $data['booking_id'],
            'amount' => (float) $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'status' => $data['status'] ?? 'captured',
            'paid_at' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
            'payer_name_enc' => $data['payer_name_enc'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function reconcile(array $data): int
    {
        return (int) Db::name('reconciliation')->insertGetId([
            'batch_ref' => $data['batch_ref'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'expected_total' => $data['expected_total'],
            'actual_total' => $data['actual_total'],
            'variance' => $data['variance'],
            'status' => $data['status'] ?? 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function createGatewayOrder(array $data): int
    {
        return (int) Db::name('gateway_orders')->insertGetId([
            'order_ref' => $data['order_ref'],
            'booking_id' => (int) $data['booking_id'],
            'amount' => (float) $data['amount'],
            'status' => 'pending',
            'provider' => 'wechat_local',
            'expire_at' => $data['expire_at'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function gatewayOrderByRef(string $orderRef): ?array
    {
        $row = Db::name('gateway_orders')->where('order_ref', $orderRef)->find();
        return $row ?: null;
    }

    public function markGatewayOrderPaid(string $orderRef, string $transactionRef, array $payload, bool $verified): void
    {
        Db::name('gateway_orders')->where('order_ref', $orderRef)->update([
            'status' => 'paid',
            'transaction_ref' => $transactionRef,
            'callback_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'callback_verified' => $verified ? 1 : 0,
            'callback_processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function callbackExists(string $transactionRef): bool
    {
        return Db::name('gateway_callbacks')
            ->where('transaction_ref', $transactionRef)
            ->count() > 0;
    }

    public function saveCallback(string $transactionRef, array $payload): bool
    {
        try {
            Db::name('gateway_callbacks')->insert([
                'transaction_ref' => $transactionRef,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'processed' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return false;
            }
            throw $e;
        }
    }

    public function autoCancelExpiredGatewayOrders(): int
    {
        return Db::name('gateway_orders')
            ->where('status', 'pending')
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->update([
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function paidGatewayOrdersByDate(string $date): array
    {
        return Db::name('gateway_orders')->where('status', 'paid')->whereDay('updated_at', $date)->select()->toArray();
    }

    public function paymentsByDate(string $date): array
    {
        return Db::name('payments')->whereDay('created_at', $date)->select()->toArray();
    }

    public function addReconciliationIssue(array $data): int
    {
        return (int) Db::name('finance_reconciliation_items')->insertGetId([
            'batch_ref' => $data['batch_ref'],
            'gateway_order_ref' => $data['gateway_order_ref'],
            'issue_type' => $data['issue_type'],
            'repaired' => (int) ($data['repaired'] ?? 0),
            'repaired_note' => $data['repaired_note'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function issuesByBatch(string $batchRef): array
    {
        return Db::name('finance_reconciliation_items')->where('batch_ref', $batchRef)->select()->toArray();
    }

    public function issueExists(int $issueId): bool
    {
        return Db::name('finance_reconciliation_items')->where('id', $issueId)->count() > 0;
    }

    public function repairIssue(int $issueId, string $note): bool
    {
        return Db::name('finance_reconciliation_items')->where('id', $issueId)->update([
            'repaired' => 1,
            'repaired_note' => $note,
        ]) > 0;
    }

    public function closeReconciliation(string $batchRef): bool
    {
        return Db::name('reconciliation')->where('batch_ref', $batchRef)->update([
            'status' => 'closed',
        ]) > 0;
    }

    public function batchExists(string $batchRef): bool
    {
        return Db::name('reconciliation')->where('batch_ref', $batchRef)->count() > 0;
    }

    public function batchInScope(string $batchRef, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('reconciliation')->alias('r')
            ->leftJoin('finance_reconciliation_items i', 'i.batch_ref = r.batch_ref')
            ->leftJoin('gateway_orders g', 'g.order_ref = i.gateway_order_ref')
            ->leftJoin('bookings b', 'b.id = g.booking_id')
            ->where('r.batch_ref', $batchRef)
            ->field('r.id');

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

        return $query->find() !== null;
    }

    public function paymentByRef(string $paymentRef): ?array
    {
        $row = Db::name('payments')->where('payment_ref', $paymentRef)->find();
        return $row ?: null;
    }

    public function paymentInScopeByRef(string $paymentRef, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('payments')->alias('p')->where('p.payment_ref', $paymentRef);
        $this->applyDataScopes($query, $scopes, $authUser);
        return $query->count() > 0;
    }

    public function issueInScope(int $issueId, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('finance_reconciliation_items')->alias('i')
            ->leftJoin('gateway_orders g', 'g.order_ref = i.gateway_order_ref')
            ->leftJoin('bookings b', 'b.id = g.booking_id')
            ->leftJoin('payments p', 'p.booking_id = b.id')
            ->where('i.id', $issueId)
            ->field('i.id');

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

        return $query->find() !== null;
    }

    public function markRefunded(int $paymentId): bool
    {
        return Db::name('payments')->where('id', $paymentId)->update([
            'status' => 'refunded',
        ]) > 0;
    }

    public function addAdjustment(int $paymentId, float $amount, string $reason, ?int $createdBy): int
    {
        return (int) Db::name('finance_adjustments')->insertGetId([
            'payment_id' => $paymentId,
            'adjust_amount' => $amount,
            'reason' => $reason,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function applyDataScopes($query, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn('p.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('p.store_id', $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn('p.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('p.warehouse_id', $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn('p.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('p.department_id', $authUser['department_id']);
        }
    }
}
