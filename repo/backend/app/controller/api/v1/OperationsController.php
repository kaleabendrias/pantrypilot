<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\OperationsService;

final class OperationsController extends BaseController
{
    public function __construct(\think\App $app, private readonly OperationsService $operationsService)
    {
        parent::__construct($app);
    }

    public function campaigns()
    {
        return JsonResponse::success(['items' => $this->operationsService->campaigns(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        )]);
    }

    public function createCampaign()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $payload['store_id'] = (string) ($authUser['store_id'] ?? '');
            $payload['warehouse_id'] = (string) ($authUser['warehouse_id'] ?? '');
            $payload['department_id'] = (string) ($authUser['department_id'] ?? '');
            return JsonResponse::success($this->operationsService->createCampaign($payload), 'Campaign created', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function homepageModules()
    {
        return JsonResponse::success(['items' => $this->operationsService->homepageModules(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        )]);
    }

    public function updateHomepageModule()
    {
        try {
            $payload = $this->request->post();
            $moduleKey = (string) ($payload['module_key'] ?? '');
            $authUser = $this->request->middleware('auth_user', []);
            $payload['store_id'] = (string) ($authUser['store_id'] ?? '');
            $payload['warehouse_id'] = (string) ($authUser['warehouse_id'] ?? '');
            $payload['department_id'] = (string) ($authUser['department_id'] ?? '');
            return JsonResponse::success(
                $this->operationsService->updateHomepageModule($moduleKey, $payload, (int) ($authUser['id'] ?? 0)),
                'Homepage module updated'
            );
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function messageTemplates()
    {
        return JsonResponse::success(['items' => $this->operationsService->messageTemplates(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        )]);
    }

    public function saveMessageTemplate()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $payload['store_id'] = (string) ($authUser['store_id'] ?? '');
            $payload['warehouse_id'] = (string) ($authUser['warehouse_id'] ?? '');
            $payload['department_id'] = (string) ($authUser['department_id'] ?? '');
            return JsonResponse::success($this->operationsService->saveMessageTemplate($payload), 'Template saved', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function managerDashboard()
    {
        return JsonResponse::success($this->operationsService->dashboardMetrics(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ));
    }
}
