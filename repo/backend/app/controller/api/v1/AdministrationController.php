<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\AdministrationService;

final class AdministrationController extends BaseController
{
    public function __construct(\think\App $app, private readonly AdministrationService $administrationService)
    {
        parent::__construct($app);
    }

    public function users()
    {
        $q = (string) ($this->request->get('q') ?? '');
        $authUser = $this->request->middleware('auth_user', []);
        $scopes = $this->request->middleware('data_scopes', []);
        return JsonResponse::success(['items' => $this->administrationService->users($q, $scopes, $authUser)]);
    }

    public function auditLogs()
    {
        $page = (int) $this->request->get('page', 1);
        $perPage = (int) $this->request->get('per_page', 20);
        $authUser = $this->request->middleware('auth_user', []);
        $scopes = $this->request->middleware('data_scopes', []);
        return JsonResponse::success($this->administrationService->auditLogs($page, $perPage, $scopes, $authUser));
    }

    public function issueReauthToken()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $password = (string) $this->request->post('password', '');
            return JsonResponse::success($this->administrationService->issueCriticalReauthToken((int) ($authUser['id'] ?? 0), $password));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 401);
        }
    }

    public function enableUser(int $userId)
    {
        try {
            return JsonResponse::success($this->administrationService->setAccountEnabled(
                $userId,
                true,
                $this->request->middleware('auth_user', []),
                $this->request->middleware('data_scopes', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function disableUser(int $userId)
    {
        try {
            return JsonResponse::success($this->administrationService->setAccountEnabled(
                $userId,
                false,
                $this->request->middleware('auth_user', []),
                $this->request->middleware('data_scopes', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function resetUserPassword(int $userId)
    {
        try {
            $newPassword = (string) $this->request->post('new_password', '');
            return JsonResponse::success($this->administrationService->adminResetPassword(
                $userId,
                $newPassword,
                $this->request->middleware('auth_user', []),
                $this->request->middleware('data_scopes', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function updateUserScopes(int $userId)
    {
        try {
            return JsonResponse::success($this->administrationService->updateUserDataScopes(
                $userId,
                $this->request->post(),
                $this->request->middleware('auth_user', []),
                $this->request->middleware('data_scopes', [])
            ));
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function roles()
    {
        return JsonResponse::success(['items' => $this->administrationService->roles()]);
    }

    public function permissions()
    {
        return JsonResponse::success(['items' => $this->administrationService->permissions()]);
    }

    public function resources()
    {
        return JsonResponse::success(['items' => $this->administrationService->resources()]);
    }

    public function createRole()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            return JsonResponse::success($this->administrationService->createRole($this->request->post(), $authUser), 'Role created', 201);
        } catch (\app\exception\ForbiddenException $e) {
            return JsonResponse::error($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function grantRolePermissionResource()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            return JsonResponse::success($this->administrationService->grantRolePermissionResource($this->request->post(), $authUser), 'Granted', 201);
        } catch (\app\exception\ForbiddenException $e) {
            return JsonResponse::error($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function assignRoleToUser()
    {
        try {
            $authUser = $this->request->middleware('auth_user', []);
            $scopes = $this->request->middleware('data_scopes', []);
            return JsonResponse::success($this->administrationService->assignRoleToUser($this->request->post(), $authUser, $scopes), 'Assigned', 201);
        } catch (\app\exception\ForbiddenException $e) {
            return JsonResponse::error($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }
}
