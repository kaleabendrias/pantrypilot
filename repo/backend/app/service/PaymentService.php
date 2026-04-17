<?php
declare(strict_types=1);

namespace app\service;

use app\domain\payments\PaymentDomainPolicy;
use app\exception\ConflictException;
use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\repository\PaymentRepository;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentDomainPolicy $paymentDomainPolicy,
        private readonly AdministrationService $administrationService,
        private readonly CryptoService $cryptoService
    )
    {
    }

    public function list(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array
    {
        $result = $this->paymentRepository->listPayments($scopes, $authUser, $page, $perPage);
        $items = $result['items'] ?? [];
        foreach ($items as &$item) {
            if (!empty($item['payer_name_enc'])) {
                $plain = $this->cryptoService->decrypt((string) $item['payer_name_enc']);
                $item['payer_name_masked'] = $this->cryptoService->mask($plain);
                unset($item['payer_name_enc']);
            }
        }
        $result['items'] = $items;
        return $result;
    }

    public function resolveBookingScope(int $bookingId): array
    {
        return $this->paymentRepository->bookingScopeById($bookingId) ?? ['store_id' => null, 'warehouse_id' => null, 'department_id' => null];
    }

    public function create(array $payload): array
    {
        $this->paymentDomainPolicy->assertPositiveAmount((float) ($payload['amount'] ?? 0));
        $payload['payment_ref'] = $payload['payment_ref'] ?? 'PAY-' . strtoupper(bin2hex(random_bytes(3)));
        if (!empty($payload['payer_name'])) {
            $payload['payer_name_enc'] = $this->cryptoService->encrypt((string) $payload['payer_name']);
        }
        try {
            $id = $this->paymentRepository->createPayment($payload);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new ConflictException('Duplicate entry', $e);
            }
            throw $e;
        }
        return ['id' => $id, 'payment_ref' => $payload['payment_ref']];
    }

    public function listBatches(int $page = 1, int $perPage = 20, array $scopes = [], array $authUser = []): array
    {
        return $this->paymentRepository->listBatches($page, $perPage, $scopes, $authUser);
    }

    public function listIssues(string $batchRef = '', int $page = 1, int $perPage = 50, array $scopes = [], array $authUser = []): array
    {
        return $this->paymentRepository->listIssues($batchRef, $page, $perPage, $scopes, $authUser);
    }

    public function reconcile(array $payload): array
    {
        $payload['batch_ref'] = $payload['batch_ref'] ?? 'REC-' . strtoupper(bin2hex(random_bytes(3)));
        $payload['variance'] = (float) $payload['actual_total'] - (float) $payload['expected_total'];
        $id = $this->paymentRepository->reconcile($payload);
        return ['id' => $id, 'batch_ref' => $payload['batch_ref'], 'variance' => $payload['variance']];
    }

    public function createGatewayOrder(array $payload): array
    {
        $this->paymentDomainPolicy->assertPositiveAmount((float) ($payload['amount'] ?? 0));
        $orderRef = (string) ($payload['order_ref'] ?? ('GW-' . strtoupper(bin2hex(random_bytes(4)))));
        $expireAt = date('Y-m-d H:i:s', time() + ((int) Config::get('security.gateway.order_auto_cancel_minutes', 10) * 60));

        $this->paymentRepository->createGatewayOrder([
            'order_ref' => $orderRef,
            'booking_id' => (int) $payload['booking_id'],
            'amount' => (float) $payload['amount'],
            'expire_at' => $expireAt,
        ]);

        return [
            'order_ref' => $orderRef,
            'status' => 'pending',
            'expire_at' => $expireAt,
            'merchant_id' => Config::get('security.gateway.merchant_id'),
        ];
    }

    public function autoCancelExpiredGatewayOrders(array $scopes = [], array $authUser = []): array
    {
        $count = $this->paymentRepository->autoCancelExpiredGatewayOrders($scopes, $authUser);
        return ['auto_cancelled' => $count];
    }

    public function processGatewayCallback(array $payload, string $signature): array
    {
        $orderRef = (string) ($payload['order_ref'] ?? '');
        $transactionRef = (string) ($payload['transaction_ref'] ?? '');
        if ($orderRef === '' || $transactionRef === '') {
            throw new ValidationException('order_ref and transaction_ref are required');
        }

        $verified = $this->verifyGatewaySignature($payload, $signature);
        if (!$verified) {
            throw new ValidationException('Invalid callback signature');
        }

        $callbackStatus = strtoupper((string) ($payload['status'] ?? ''));
        if ($callbackStatus !== 'SUCCESS') {
            Log::info('payments.gateway_callback.rejected', ['order_ref' => $orderRef, 'reason' => 'non_success_status', 'status' => $callbackStatus]);
            return ['processed' => false, 'rejected' => true, 'reason' => 'callback status is not SUCCESS'];
        }

        $result = Db::transaction(function () use ($orderRef, $transactionRef, $payload): array {
            $inserted = $this->paymentRepository->saveCallback($transactionRef, $payload);
            if (!$inserted) {
                return ['idempotent' => true, 'processed' => true];
            }

            $order = $this->paymentRepository->gatewayOrderByRef($orderRef);
            if (!$order) {
                throw new NotFoundException('Gateway order not found');
            }

            $orderStatus = (string) ($order['status'] ?? '');
            if ($orderStatus === 'paid') {
                return ['idempotent' => true, 'processed' => true, 'booking_id' => (int) ($order['booking_id'] ?? 0)];
            }
            if ($orderStatus === 'cancelled') {
                throw new ValidationException('Cannot capture payment for cancelled order');
            }
            if ($orderStatus !== 'pending') {
                throw new ValidationException('Order is not in a capturable state: ' . $orderStatus);
            }
            if (strtotime((string) ($order['expire_at'] ?? '')) <= time()) {
                throw new ValidationException('Order has expired');
            }

            $callbackAmount = (float) ($payload['amount'] ?? 0);
            $orderAmount = (float) ($order['amount'] ?? 0);
            if ($callbackAmount > 0 && abs($callbackAmount - $orderAmount) > 0.01) {
                throw new ValidationException('Callback amount does not match order amount');
            }

            $this->paymentRepository->markGatewayOrderPaid($orderRef, $transactionRef, $payload, true);

            $bookingScope = $this->paymentRepository->bookingScopeById((int) $order['booking_id']);

            $this->create([
                'booking_id' => (int) $order['booking_id'],
                'amount' => (float) $order['amount'],
                'method' => 'wechat_local',
                'status' => 'captured',
                'store_id' => $bookingScope['store_id'] ?? null,
                'warehouse_id' => $bookingScope['warehouse_id'] ?? null,
                'department_id' => $bookingScope['department_id'] ?? null,
            ]);

            return ['processed' => true, 'verified' => true, 'booking_id' => (int) $order['booking_id']];
        });

        if (($result['idempotent'] ?? false) === true) {
            Log::info('payments.gateway_callback.idempotent', ['order_ref' => $orderRef, 'transaction_ref' => $transactionRef]);
            return $result;
        }

        Log::info('payments.gateway_callback.processed', ['order_ref' => $orderRef, 'transaction_ref' => $transactionRef, 'booking_id' => (int) ($result['booking_id'] ?? 0)]);

        return $result;
    }

    public function dailyReconciliation(string $date, array $scopes = [], array $authUser = []): array
    {
        $gatewayOrders = $this->paymentRepository->paidGatewayOrdersByDate($date, $scopes, $authUser);
        $payments = $this->paymentRepository->paymentsByDate($date, $scopes, $authUser);
        $paymentMap = [];
        foreach ($payments as $p) {
            $paymentMap[(int) $p['booking_id']] = true;
        }

        $batchRef = 'DREC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $issues = 0;
        $expected = 0.0;
        $actual = 0.0;

        foreach ($gatewayOrders as $order) {
            $expected += (float) $order['amount'];
            if (isset($paymentMap[(int) $order['booking_id']])) {
                $actual += (float) $order['amount'];
            } else {
                $issues++;
                $this->paymentRepository->addReconciliationIssue([
                    'batch_ref' => $batchRef,
                    'gateway_order_ref' => $order['order_ref'],
                    'issue_type' => 'missed_order',
                ]);
            }
        }

        $rec = $this->reconcile([
            'batch_ref' => $batchRef,
            'period_start' => $date,
            'period_end' => $date,
            'expected_total' => $expected,
            'actual_total' => $actual,
            'status' => $issues > 0 ? 'abnormal' : 'open',
            'store_id' => $authUser['store_id'] ?? null,
            'warehouse_id' => $authUser['warehouse_id'] ?? null,
            'department_id' => $authUser['department_id'] ?? null,
        ]);

        Log::info('payments.daily_reconciliation.completed', ['date' => $date, 'batch_ref' => $rec['batch_ref'], 'issues' => $issues, 'variance' => $rec['variance']]);

        return ['batch_ref' => $rec['batch_ref'], 'issues' => $issues, 'variance' => $rec['variance']];
    }

    public function repairReconciliationIssue(int $issueId, string $note, int $actorId, string $reauthToken, array $scopes = [], array $authUser = [], string $ip = '', string $userAgent = '', string $requestId = ''): array
    {
        if (!$this->administrationService->consumeCriticalReauthToken($actorId, $reauthToken)) {
            throw new ValidationException('Critical operation requires valid re-auth token');
        }
        if (!$this->paymentRepository->issueExists($issueId)) {
            throw new NotFoundException('Issue not found');
        }
        if (!$this->paymentRepository->issueInScope($issueId, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $ok = $this->paymentRepository->repairIssue($issueId, $note);
        $this->administrationService->audit('finance.repair_issue', 'finance_reconciliation_items', (string) $issueId, $actorId, ['note' => $note], $ip, $userAgent, $requestId);
        return ['repaired' => $ok];
    }

    public function closeReconciliationBatch(string $batchRef, int $actorId, string $reauthToken, array $scopes = [], array $authUser = [], string $ip = '', string $userAgent = '', string $requestId = ''): array
    {
        if (!$this->administrationService->consumeCriticalReauthToken($actorId, $reauthToken)) {
            throw new ValidationException('Critical operation requires valid re-auth token');
        }
        if (!$this->paymentRepository->batchExists($batchRef)) {
            throw new NotFoundException('Batch not found');
        }
        if (!$this->paymentRepository->batchInScope($batchRef, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $ok = $this->paymentRepository->closeReconciliation($batchRef);
        $this->administrationService->audit('finance.close_reconciliation', 'reconciliation', $batchRef, $actorId, ['status' => 'closed'], $ip, $userAgent, $requestId);
        return ['closed' => $ok];
    }

    public function refund(string $paymentRef, int $actorId, string $reauthToken, array $scopes = [], array $authUser = [], string $ip = '', string $userAgent = '', string $requestId = ''): array
    {
        if (!$this->administrationService->consumeCriticalReauthToken($actorId, $reauthToken)) {
            throw new ValidationException('Critical operation requires valid re-auth token');
        }

        $payment = $this->paymentRepository->paymentByRef($paymentRef);
        if (!$payment) {
            throw new NotFoundException('Payment not found');
        }
        if (!$this->paymentRepository->paymentInScopeByRef($paymentRef, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $ok = $this->paymentRepository->markRefunded((int) $payment['id']);
        $this->administrationService->audit('finance.refund', 'payments', $paymentRef, $actorId, ['result' => $ok], $ip, $userAgent, $requestId);
        Log::info('payments.refund.completed', ['payment_ref' => $paymentRef, 'actor_id' => $actorId, 'result' => $ok]);
        return ['refunded' => $ok, 'payment_ref' => $paymentRef];
    }

    public function adjust(string $paymentRef, float $amount, string $reason, int $actorId, string $reauthToken, array $scopes = [], array $authUser = [], string $ip = '', string $userAgent = '', string $requestId = ''): array
    {
        if (!$this->administrationService->consumeCriticalReauthToken($actorId, $reauthToken)) {
            throw new ValidationException('Critical operation requires valid re-auth token');
        }

        $payment = $this->paymentRepository->paymentByRef($paymentRef);
        if (!$payment) {
            throw new NotFoundException('Payment not found');
        }
        if (!$this->paymentRepository->paymentInScopeByRef($paymentRef, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $id = $this->paymentRepository->addAdjustment((int) $payment['id'], $amount, $reason, $actorId);
        $this->administrationService->audit('finance.adjustment', 'payments', $paymentRef, $actorId, ['amount' => $amount, 'reason' => $reason], $ip, $userAgent, $requestId);
        Log::info('payments.adjustment.completed', ['payment_ref' => $paymentRef, 'actor_id' => $actorId, 'adjustment_id' => $id, 'amount' => $amount]);
        return ['adjustment_id' => $id, 'payment_ref' => $paymentRef];
    }

    private function verifyGatewaySignature(array $payload, string $signature): bool
    {
        ksort($payload);
        $message = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $secret = (string) Config::get('security.gateway.hmac_secret');
        $expected = hash_hmac('sha256', (string) $message, $secret);
        return hash_equals($expected, $signature);
    }
}
