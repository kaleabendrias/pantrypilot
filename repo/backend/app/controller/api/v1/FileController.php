<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\BookingService;
use app\service\FileService;
use app\service\PaymentService;

final class FileController extends BaseController
{
    public function __construct(
        \think\App $app,
        private readonly FileService $fileService,
        private readonly BookingService $bookingService,
        private readonly PaymentService $paymentService
    ) {
        parent::__construct($app);
    }

    public function index()
    {
        return JsonResponse::success([
            'items' => $this->fileService->list(
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', [])
            ),
        ]);
    }

    public function uploadBase64()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $scopes = $this->request->middleware('data_scopes', []);
            $payload['owner_type'] = $payload['owner_type'] ?? 'user';
            $payload['owner_id'] = $payload['owner_id'] ?? (int) ($authUser['id'] ?? 0);

            $ownerType = (string) $payload['owner_type'];
            $ownerId = (int) $payload['owner_id'];
            $allowedOwnerTypes = ['user', 'booking', 'payment'];
            if (!in_array($ownerType, $allowedOwnerTypes, true)) {
                return JsonResponse::error('Invalid owner_type', 422);
            }
            if ($ownerType === 'booking' && $ownerId > 0) {
                if (!$this->bookingService->bookingExists($ownerId)) {
                    return JsonResponse::error('Referenced booking not found', 404);
                }
                if (!$this->bookingService->canAccessBooking($ownerId, $scopes, $authUser)) {
                    return JsonResponse::error('Forbidden', 403);
                }
            }
            if ($ownerType === 'payment' && $ownerId > 0) {
                $payment = \think\facade\Db::name('payments')->where('id', $ownerId)->find();
                if (!$payment) {
                    return JsonResponse::error('Referenced payment not found', 404);
                }
                if (!\app\service\ScopeHelper::isGlobalAdmin($authUser)) {
                    $scopeFields = ['store_id', 'warehouse_id', 'department_id'];
                    foreach ($scopeFields as $field) {
                        $paymentVal = (string) ($payment[$field] ?? '');
                        $userVal = (string) ($authUser[$field] ?? '');
                        if ($paymentVal !== '' && $userVal !== '' && $paymentVal !== $userVal) {
                            return JsonResponse::error('Forbidden', 403);
                        }
                    }
                }
            }
            if ($ownerType === 'user' && $ownerId > 0 && !\app\service\ScopeHelper::isGlobalAdmin($authUser)) {
                if ($ownerId !== (int) ($authUser['id'] ?? 0)) {
                    return JsonResponse::error('Forbidden', 403);
                }
            }

            return JsonResponse::success($this->fileService->uploadBase64($payload), 'File stored', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function signedUrl(int $id)
    {
        try {
            return JsonResponse::success($this->fileService->createSignedDownloadUrl(
                $id,
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 404);
        }
    }

    public function download(int $id)
    {
        try {
            $token = (string) $this->request->get('token', '');
            return JsonResponse::success($this->fileService->validateDownloadToken(
                $id,
                $token,
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 403);
        }
    }

    public function cleanup()
    {
        return JsonResponse::success($this->fileService->cleanupLifecycle(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ), 'Cleanup completed');
    }
}
