<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\FileService;

final class FileController extends BaseController
{
    public function __construct(\think\App $app, private readonly FileService $fileService)
    {
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
            $payload['owner_type'] = $payload['owner_type'] ?? 'user';
            $payload['owner_id'] = $payload['owner_id'] ?? (int) ($authUser['id'] ?? 0);
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
