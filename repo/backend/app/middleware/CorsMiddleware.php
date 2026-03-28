<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;

final class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isOptions()) {
            return response('', 204)
                ->header([
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type,Authorization',
                ]);
        }

        $response = $next($request);
        return $response->header([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization',
        ]);
    }
}
