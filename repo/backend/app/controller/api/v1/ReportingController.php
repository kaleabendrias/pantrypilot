<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\ReportingService;

final class ReportingController extends BaseController
{
    public function __construct(\think\App $app, private readonly ReportingService $reportingService)
    {
        parent::__construct($app);
    }

    public function dashboard()
    {
        return JsonResponse::success($this->reportingService->dashboard(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ));
    }

    public function anomalies()
    {
        return JsonResponse::success($this->reportingService->anomalies(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ));
    }

    public function generateAlerts()
    {
        $result = $this->reportingService->generateAlerts(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        );
        return JsonResponse::success($result, 'Alerts generated');
    }

    public function exportBookingsCsv()
    {
        return JsonResponse::success($this->reportingService->exportBookingsCsv(
            $this->request->middleware('data_scopes', []),
            $this->request->middleware('auth_user', [])
        ));
    }
}
