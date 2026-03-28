<?php
declare(strict_types=1);

namespace app;

use app\common\JsonResponse;
use app\exception\ApiException;
use think\App;
use think\Request;

abstract class BaseController
{
    protected App $app;
    protected Request $request;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    protected function initialize(): void
    {
    }

    protected function respondException(\Throwable $e, int $defaultStatus = 422)
    {
        if ($e instanceof ApiException) {
            return JsonResponse::error($e->getMessage(), $e->statusCode());
        }

        return JsonResponse::error($e->getMessage(), $defaultStatus);
    }
}
