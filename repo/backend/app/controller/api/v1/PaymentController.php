<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\PaymentService;

final class PaymentController extends BaseController
{
    public function __construct(\think\App $app, private readonly PaymentService $paymentService)
    {
        parent::__construct($app);
    }

    public function index()
    {
        $page = (int) $this->request->get('page', 1);
        $perPage = (int) $this->request->get('per_page', 20);
        return JsonResponse::success([
            ...$this->paymentService->list(
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', []),
                $page,
                $perPage
            ),
        ]);
    }

    public function create()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $payload['store_id'] = $authUser['store_id'] ?? null;
            $payload['warehouse_id'] = $authUser['warehouse_id'] ?? null;
            $payload['department_id'] = $authUser['department_id'] ?? null;
            return JsonResponse::success($this->paymentService->create($payload), 'Payment created', 201);
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function reconcile()
    {
        try {
            return JsonResponse::success($this->paymentService->reconcile($this->request->post()), 'Reconciliation started', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function createGatewayOrder()
    {
        try {
            return JsonResponse::success($this->paymentService->createGatewayOrder($this->request->post()), 'Gateway order created', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function gatewayCallback()
    {
        try {
            $signature = (string) $this->request->header('X-Signature', '');
            return JsonResponse::success($this->paymentService->processGatewayCallback($this->request->post(), $signature), 'Callback processed');
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function autoCancelGatewayOrders()
    {
        return JsonResponse::success($this->paymentService->autoCancelExpiredGatewayOrders(), 'Expired gateway orders cancelled');
    }

    public function dailyReconcile()
    {
        try {
            $date = (string) $this->request->post('date', date('Y-m-d'));
            return JsonResponse::success($this->paymentService->dailyReconciliation($date), 'Daily reconciliation completed');
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function repairIssue()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $issueId = (int) $this->request->post('issue_id', 0);
            $note = (string) $this->request->post('note', '');
            $reauthToken = (string) $this->request->post('reauth_token', '');
            return JsonResponse::success(
                $this->paymentService->repairReconciliationIssue(
                    $issueId,
                    $note,
                    (int) ($authUser['id'] ?? 0),
                    $reauthToken,
                    $this->request->middleware('data_scopes', []),
                    $authUser,
                    (string) $this->request->ip()
                ),
                'Issue repaired'
            );
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function closeBatch()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $batchRef = (string) $this->request->post('batch_ref', '');
            $reauthToken = (string) $this->request->post('reauth_token', '');
            return JsonResponse::success(
                $this->paymentService->closeReconciliationBatch(
                    $batchRef,
                    (int) ($authUser['id'] ?? 0),
                    $reauthToken,
                    $this->request->middleware('data_scopes', []),
                    $authUser,
                    (string) $this->request->ip()
                ),
                'Reconciliation closed'
            );
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function refund()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $paymentRef = (string) $this->request->post('payment_ref', '');
            $reauthToken = (string) $this->request->post('reauth_token', '');
            return JsonResponse::success(
                $this->paymentService->refund(
                    $paymentRef,
                    (int) ($authUser['id'] ?? 0),
                    $reauthToken,
                    $this->request->middleware('data_scopes', []),
                    $authUser
                ),
                'Refund completed'
            );
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function adjust()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $paymentRef = (string) $this->request->post('payment_ref', '');
            $amount = (float) $this->request->post('amount', 0);
            $reason = (string) $this->request->post('reason', '');
            $reauthToken = (string) $this->request->post('reauth_token', '');
            return JsonResponse::success(
                $this->paymentService->adjust(
                    $paymentRef,
                    $amount,
                    $reason,
                    (int) ($authUser['id'] ?? 0),
                    $reauthToken,
                    $this->request->middleware('data_scopes', []),
                    $authUser
                ),
                'Adjustment completed'
            );
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }
}
