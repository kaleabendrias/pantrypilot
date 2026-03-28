<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\JsonResponse;
use app\service\IdentityService;
use Closure;
use think\facade\Config;
use think\Request;

final class AuthenticationMiddleware
{
    public function __construct(private readonly IdentityService $identityService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $routeKey = strtoupper($request->method()) . ':' . trim($request->pathinfo(), '/');
        if ($routeKey === 'GET:') {
            $routeKey = 'GET:/';
        }

        $publicRoutes = Config::get('acl.public_routes', []);
        if ($this->matchRoute($routeKey, $publicRoutes) !== null) {
            return $next($request);
        }

        $header = (string) $request->header('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return JsonResponse::error('Unauthorized', 401);
        }

        $token = trim(substr($header, 7));
        $authUser = $this->identityService->userByToken($token);
        if (!$authUser) {
            return JsonResponse::error('Invalid or expired token', 401);
        }

        $request->withMiddleware(['auth_user' => $authUser]);
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
