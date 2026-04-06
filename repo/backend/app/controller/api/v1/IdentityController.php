<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\IdentityService;

final class IdentityController extends BaseController
{
    public function __construct(\think\App $app, private readonly IdentityService $identityService)
    {
        parent::__construct($app);
    }

    public function register()
    {
        try {
            $payload = $this->request->post();
            if (!isset($payload['username'], $payload['password'])) {
                return JsonResponse::error('username and password are required', 422);
            }

            $result = $this->identityService->register($payload);
            return JsonResponse::success($result, 'User registered', 201);
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function login()
    {
        try {
            $payload = $this->request->post();
            if (!isset($payload['username'], $payload['password'])) {
                return JsonResponse::error('username and password are required', 422);
            }

            $result = $this->identityService->login(
                (string) $payload['username'],
                (string) $payload['password'],
                (string) $this->request->ip(),
                (string) $this->request->header('User-Agent', '')
            );
            return JsonResponse::success($result, 'Authenticated');
        } catch (\Throwable $e) {
            return $this->respondException($e, 401);
        }
    }

    public function rotateBootstrapPassword()
    {
        try {
            $payload = $this->request->post();
            if (!isset($payload['username'], $payload['current_password'], $payload['new_password'])) {
                return JsonResponse::error('username, current_password, and new_password are required', 422);
            }

            $result = $this->identityService->rotateBootstrapPassword(
                (string) $payload['username'],
                (string) $payload['current_password'],
                (string) $payload['new_password']
            );
            return JsonResponse::success($result, 'Password rotated successfully');
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }
}
