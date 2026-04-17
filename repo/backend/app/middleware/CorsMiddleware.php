<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;

final class CorsMiddleware
{
    /** @var string[] */
    private array $allowedOrigins;

    public function __construct()
    {
        $raw = (string) (getenv('PANTRYPILOT_ALLOWED_ORIGINS') ?: '');
        $this->allowedOrigins = $raw !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $raw))))
            : [];
    }

    public function handle(Request $request, Closure $next)
    {
        $origin  = (string) ($request->header('Origin') ?? '');
        $headers = [
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization',
        ];

        if ($this->isAllowedOrigin($origin)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary']                        = 'Origin';
        }

        if ($request->isOptions()) {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }

    private function isAllowedOrigin(string $origin): bool
    {
        return $origin !== '' && in_array($origin, $this->allowedOrigins, true);
    }
}
