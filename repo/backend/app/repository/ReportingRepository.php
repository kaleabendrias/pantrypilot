<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class ReportingRepository
{
    public function kpis(array $scopes = [], array $authUser = []): array
    {
        $recipeQ = Db::name('recipes')->alias('r');
        $bookingQ = Db::name('bookings')->alias('b');
        $pendingQ = Db::name('bookings')->alias('b')->where('status', 'pending');
        $paymentQ = Db::name('payments')->alias('p')->where('status', 'captured');

        $this->applyDataScopes($recipeQ, 'r', $scopes, $authUser);
        $this->applyDataScopes($bookingQ, 'b', $scopes, $authUser);
        $this->applyDataScopes($pendingQ, 'b', $scopes, $authUser);
        $this->applyDataScopes($paymentQ, 'p', $scopes, $authUser);

        $recipeCount = (int) $recipeQ->count();
        $bookingCount = (int) $bookingQ->count();
        $pendingBookings = (int) $pendingQ->count();
        $capturedPayments = (float) $paymentQ->sum('amount');

        return [
            'recipes' => $recipeCount,
            'bookings' => $bookingCount,
            'pending_bookings' => $pendingBookings,
            'captured_payments' => $capturedPayments,
        ];
    }

    public function anomalyMetrics(array $scopes = [], array $authUser = []): array
    {
        $recentQ = Db::name('bookings')->alias('b')->whereTime('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')));
        $this->applyDataScopes($recentQ, 'b', $scopes, $authUser);
        $recentBookings = (int) $recentQ->count();
        $oversellQuery = Db::name('pickup_slots')->alias('ps')
            ->join('bookings b', 'b.pickup_point_id = ps.pickup_point_id AND b.slot_start = ps.slot_start AND b.slot_end = ps.slot_end')
            ->whereRaw('ps.reserved_count > ps.capacity');
        $this->applyDataScopes($oversellQuery, 'b', $scopes, $authUser);
        $oversell = (int) $oversellQuery->count('DISTINCT ps.id');

        $refundQ = Db::name('payments')->alias('p')->where('status', 'refunded')->whereTime('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')));
        $paymentQ = Db::name('payments')->alias('p')->whereTime('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')));
        $this->applyDataScopes($refundQ, 'p', $scopes, $authUser);
        $this->applyDataScopes($paymentQ, 'p', $scopes, $authUser);
        $refunds = (int) $refundQ->count();
        $paymentTotal = (int) $paymentQ->count();
        $refundRate = $paymentTotal > 0 ? round(($refunds / $paymentTotal) * 100, 2) : 0.0;

        $stockoutsQ = Db::name('stock_snapshots')->alias('s')->where('s.qty', '<=', 0)->whereDay('s.snapshot_date', date('Y-m-d'));
        $stockTotalQ = Db::name('stock_snapshots')->alias('s')->whereDay('s.snapshot_date', date('Y-m-d'));
        $this->applyDataScopes($stockoutsQ, 's', $scopes, $authUser);
        $this->applyDataScopes($stockTotalQ, 's', $scopes, $authUser);
        $stockouts = (int) $stockoutsQ->count();
        $stockTotal = (int) $stockTotalQ->count();
        $stockoutRate = $stockTotal > 0 ? round(($stockouts / $stockTotal) * 100, 2) : 0.0;

        $alerts = [];
        if ($oversell > 0) {
            $alerts[] = ['type' => 'oversell', 'severity' => 'high', 'count' => $oversell];
        }
        if ($refundRate > 20) {
            $alerts[] = ['type' => 'refund_rate_spike', 'severity' => 'high', 'rate' => $refundRate];
        }
        if ($stockoutRate > 15) {
            $alerts[] = ['type' => 'stockout_rate', 'severity' => 'medium', 'rate' => $stockoutRate];
        }

        return [
            'recent_bookings_7d' => $recentBookings,
            'oversell_count' => $oversell,
            'refund_rate_7d' => $refundRate,
            'stockout_rate_today' => $stockoutRate,
            'alerts' => $alerts,
        ];
    }

    public function persistAlerts(array $alerts): int
    {
        $today = date('Y-m-d');
        $persisted = 0;
        foreach ($alerts as $alert) {
            $existing = Db::name('anomaly_alerts')
                ->where('alert_type', $alert['type'])
                ->whereDay('created_at', $today)
                ->count();
            if ($existing === 0) {
                Db::name('anomaly_alerts')->insert([
                    'alert_type' => $alert['type'],
                    'severity' => $alert['severity'],
                    'payload' => json_encode($alert, JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $persisted++;
            }
        }
        return $persisted;
    }

    public function exportBookingsCsv(array $scopes = [], array $authUser = []): string
    {
        $query = Db::name('bookings')->alias('b');
        $this->applyDataScopes($query, 'b', $scopes, $authUser);
        $rows = $query->field('b.*')->order('b.id', 'desc')->limit(1000)->select()->toArray();
        $headers = ['id', 'booking_code', 'user_id', 'recipe_id', 'pickup_at', 'status', 'quantity'];
        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = '"' . str_replace('"', '""', (string) ($row[$h] ?? '')) . '"';
            }
            $lines[] = implode(',', $line);
        }
        return implode("\n", $lines);
    }

    private function applyDataScopes($query, string $alias, array $scopes, array $authUser): void
    {
        \app\service\ScopeHelper::applyStandardScopes($query, $alias, $scopes, $authUser);
    }
}
