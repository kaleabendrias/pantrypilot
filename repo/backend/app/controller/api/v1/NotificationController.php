<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\NotificationService;

final class NotificationController extends BaseController
{
    public function __construct(\think\App $app, private readonly NotificationService $notificationService)
    {
        parent::__construct($app);
    }

    public function index()
    {
        $authUser = $this->request->middleware('auth_user', []);
        $scopes = $this->request->middleware('data_scopes', []);
        return JsonResponse::success(['items' => $this->notificationService->events($scopes, $authUser)]);
    }

    public function create()
    {
        $payload = $this->request->post();
        $authUser = $this->request->middleware('auth_user', []);
        $payload['store_id'] = $authUser['store_id'] ?? null;
        $payload['warehouse_id'] = $authUser['warehouse_id'] ?? null;
        $payload['department_id'] = $authUser['department_id'] ?? null;
        return JsonResponse::success($this->notificationService->enqueue($payload), 'Event queued', 201);
    }

    public function setOptOut()
    {
        $auth = $this->request->middleware('auth_user', []);
        $optOut = (bool) $this->request->post('opt_out', false);
        return JsonResponse::success($this->notificationService->setOptOut((int) ($auth['id'] ?? 0), $optOut));
    }

    public function sendMessage()
    {
        try {
            return JsonResponse::success($this->notificationService->sendMessage(
                $this->request->post(),
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', [])
            ), 'Message queued', 201);
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function inbox()
    {
        $auth = $this->request->middleware('auth_user', []);
        return JsonResponse::success(['items' => $this->notificationService->inbox((int) ($auth['id'] ?? 0))]);
    }

    public function markRead(int $id)
    {
        try {
            $auth = $this->request->middleware('auth_user', []);
            return JsonResponse::success($this->notificationService->markReadForUser($id, (int) ($auth['id'] ?? 0)));
        } catch (\RuntimeException $e) {
            return JsonResponse::error($e->getMessage(), 403);
        }
    }

    public function markClick(int $id)
    {
        try {
            $auth = $this->request->middleware('auth_user', []);
            return JsonResponse::success($this->notificationService->markClickForUser($id, (int) ($auth['id'] ?? 0)));
        } catch (\RuntimeException $e) {
            return JsonResponse::error($e->getMessage(), 403);
        }
    }

    public function analytics()
    {
        return JsonResponse::success($this->notificationService->analytics(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ));
    }
}
