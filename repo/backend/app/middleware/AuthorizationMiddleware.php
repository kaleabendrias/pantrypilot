<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\JsonResponse;
use app\service\AuthorizationService;
use Closure;
use think\facade\Config;
use think\Request;

final class AuthorizationMiddleware
{
    public function __construct(private readonly AuthorizationService $authorizationService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $routeKey = strtoupper($request->method()) . ':' . trim($request->pathinfo(), '/');
        if ($routeKey === 'GET:') {
            $routeKey = 'GET:/';
        }

        $publicRoutes = Config::get('acl.public_routes', []);
        if (in_array($routeKey, $publicRoutes, true)) {
            return $next($request);
        }

        $rules = Config::get('acl.permissions', []);
        $matchedKey = $this->matchRoute($routeKey, array_keys($rules));

        $authUser = $request->middleware('auth_user');
        if (!$authUser) {
            return JsonResponse::error('Unauthorized', 401);
        }

        if ($matchedKey === null) {
            return JsonResponse::error('Forbidden', 403);
        }

        $required = $rules[$matchedKey];
        $can = $this->authorizationService->can(
            (int) $authUser['id'],
            (string) $required['resource'],
            (string) $required['permission']
        );

        if (!$can) {
            return JsonResponse::error('Forbidden', 403);
        }

        $request->withMiddleware([
            'data_scopes' => $this->authorizationService->dataScopes((int) $authUser['id']),
        ]);

        return $next($request);
    }

    private function matchRoute(string $routeKey, array $patterns): ?string
    {
        if (in_array($routeKey, $patterns, true)) {
            return $routeKey;
        }

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*') && fnmatch($pattern, $routeKey)) {
                return $pattern;
            }
        }

        return null;
    }
}
