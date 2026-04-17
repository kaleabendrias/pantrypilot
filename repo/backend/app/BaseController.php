<?php
declare(strict_types=1);

namespace app;

use app\common\JsonResponse;
use app\exception\ApiException;
use think\App;
use think\facade\Log;
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

        if ($this->isSafeException($e)) {
            return JsonResponse::error($e->getMessage(), $defaultStatus);
        }

        Log::error('unhandled_exception', [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return JsonResponse::error('An internal error occurred', 500);
    }

    private function isSafeException(\Throwable $e): bool
    {
        if ($e instanceof ApiException) {
            return true;
        }

        if ($e instanceof \InvalidArgumentException) {
            return true;
        }

        if ($e instanceof \DomainException) {
            return true;
        }

        if ($e instanceof \RuntimeException) {
            $msg = $e->getMessage();
            $unsafePatterns = ['SQL', 'SQLSTATE', 'PDO', 'mysql', 'table', 'column', 'syntax', 'Connection refused'];
            foreach ($unsafePatterns as $pattern) {
                if (stripos($msg, $pattern) !== false) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
