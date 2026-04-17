<?php
declare(strict_types=1);

/**
 * PantryPilot FE-BE End-to-End Tests
 *
 * These tests run inside the api container and exercise the full integration path:
 *   Browser → nginx (web service) → api container → MySQL
 *
 * All requests go through the nginx proxy at http://web:80, verifying that:
 *   - nginx correctly serves the frontend SPA
 *   - nginx correctly proxies /api/* requests to the backend
 *   - complete user journeys work end-to-end through the real network stack
 */

$results = ['passed' => 0, 'failed' => 0];

function etassert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function erunCase(string $name, callable $fn): void
{
    global $results;
    try {
        $fn();
        $results['passed']++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $results['failed']++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

function webRaw(string $method, string $path, array $body = [], ?string $token = null): array
{
    $url = 'http://web' . $path;
    $headers = ['Content-Type: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $opts = [
        'http' => [
            'method'        => $method,
            'ignore_errors' => true,
            'header'        => implode("\r\n", $headers),
            'content'       => $method === 'GET' ? '' : json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout'       => 20,
        ],
    ];
    $resp = file_get_contents($url, false, stream_context_create($opts));
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'body' => (string) ($resp ?? ''), 'headers' => $responseHeaders];
}

function webApi(string $method, string $path, array $body = [], ?string $token = null): array
{
    $r = webRaw($method, $path, $body, $token);
    if ($r['body'] === '') {
        throw new RuntimeException('empty response body from nginx for ' . $method . ' ' . $path);
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $r['body']) ?? $r['body'];
    $json = json_decode(ltrim($raw), true);
    if (!is_array($json)) {
        throw new RuntimeException('response is not valid JSON for ' . $method . ' ' . $path . ': ' . substr($r['body'], 0, 120));
    }
    return ['status' => $r['status'], 'json' => $json, 'headers' => $r['headers'], 'body' => $r['body']];
}

function webContentType(array $headers): string
{
    foreach ($headers as $h) {
        if (stripos((string) $h, 'Content-Type:') === 0) {
            return strtolower(trim(substr((string) $h, strlen('Content-Type:'))));
        }
    }
    return '';
}

// ----- Connectivity pre-check -----

$webReach = @file_get_contents('http://web/', false, stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 5]]));
if ($webReach === false) {
    echo "[SKIP] Web service at http://web/ is not reachable from api container. Ensure 'web' service is running.\n";
    echo "E2E tests skipped=all\n";
    exit(0);
}

// ----- E2E Test Cases -----

erunCase('Nginx serves frontend SPA HTML at root path', function (): void {
    $r = webRaw('GET', '/');
    etassert($r['status'] === 200, 'nginx should serve frontend with HTTP 200, got: ' . $r['status']);
    $ct = webContentType($r['headers']);
    etassert(str_contains($ct, 'text/html'), 'nginx root must return text/html content-type');
    etassert(
        str_contains($r['body'], '<html') || str_contains($r['body'], '<!DOCTYPE'),
        'nginx root must serve HTML document'
    );
    etassert(
        str_contains($r['body'], 'PantryPilot') || str_contains($r['body'], 'layui') || str_contains($r['body'], 'app.js'),
        'frontend HTML must reference PantryPilot application identifiers'
    );
});

erunCase('Nginx SPA fallback returns HTML for unknown frontend routes', function (): void {
    $r = webRaw('GET', '/some/spa/route/that/does/not/exist');
    etassert($r['status'] === 200, 'nginx SPA fallback should return 200 for unknown routes');
    $ct = webContentType($r['headers']);
    etassert(str_contains($ct, 'text/html'), 'nginx SPA fallback must return text/html');
    etassert(
        str_contains($r['body'], '<html') || str_contains($r['body'], '<!DOCTYPE'),
        'nginx SPA fallback must return HTML'
    );
});

erunCase('Nginx proxies unauthenticated API requests and returns JSON with correct status', function (): void {
    $r = webApi('GET', '/api/v1/reporting/dashboard');
    etassert($r['status'] === 401, 'unauthenticated API call through nginx must return 401');
    etassert(str_contains(webContentType($r['headers']), 'application/json'), 'API response via nginx must be application/json');
    etassert(isset($r['json']['success']), 'API error response through nginx must include success field');
    etassert($r['json']['success'] === false, 'unauthenticated response success must be false');
});

erunCase('Complete login-to-dashboard user journey through nginx proxy', function (): void {
    // Step 1: login
    $login = webApi('POST', '/api/v1/identity/login', ['username' => 'admin', 'password' => 'admin12345']);
    etassert($login['status'] === 200, 'admin login through nginx should return 200');
    etassert(($login['json']['success'] ?? false) === true, 'login response success must be true');
    $token = (string) ($login['json']['data']['token'] ?? '');
    etassert($token !== '', 'login must return a bearer token');

    // Step 2: load reporting dashboard (simulates first authenticated page load)
    $dashboard = webApi('GET', '/api/v1/reporting/dashboard', [], $token);
    etassert($dashboard['status'] === 200, 'reporting dashboard through nginx should return 200');
    etassert(($dashboard['json']['success'] ?? false) === true, 'dashboard response must succeed');

    // Step 3: load recipes tab
    $recipes = webApi('GET', '/api/v1/recipes', [], $token);
    etassert($recipes['status'] === 200, 'recipe list through nginx should return 200');
    etassert(array_key_exists('items', $recipes['json']['data'] ?? []), 'recipe list must include items array');

    // Step 4: load bookings tab
    $bookings = webApi('GET', '/api/v1/bookings', [], $token);
    etassert($bookings['status'] === 200, 'bookings list through nginx should return 200');

    // Step 5: load admin metadata (simulates admin tab)
    $roles = webApi('GET', '/api/v1/admin/roles', [], $token);
    etassert($roles['status'] === 200, 'admin roles through nginx should return 200');
});

erunCase('User registration, login, and self-service workflow through nginx proxy', function (): void {
    $username = 'e2e_' . bin2hex(random_bytes(3));
    $password = 'e2epassword99';

    // Register
    $reg = webApi('POST', '/api/v1/identity/register', ['username' => $username, 'password' => $password]);
    etassert($reg['status'] === 201, 'user registration through nginx should return 201');

    // Login
    $login = webApi('POST', '/api/v1/identity/login', ['username' => $username, 'password' => $password]);
    etassert($login['status'] === 200, 'login after registration through nginx should return 200');
    $token = (string) ($login['json']['data']['token'] ?? '');
    etassert($token !== '', 'login must return token');

    // View notification inbox (customer self-service)
    $inbox = webApi('GET', '/api/v1/notifications/inbox', [], $token);
    etassert($inbox['status'] === 200, 'user inbox through nginx should return 200');

    // Attempt to access admin endpoint (must be denied)
    $denied = webApi('GET', '/api/v1/admin/users', [], $token);
    etassert($denied['status'] === 403, 'non-admin user must be denied admin access through nginx');
});

erunCase('Operations staff workflow through nginx proxy', function (): void {
    // Login as ops_staff (scoped_user)
    $login = webApi('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope123456']);
    if ($login['status'] !== 200) {
        // scoped_user password may have been reset by other tests; re-login with new password
        $login = webApi('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope654321']);
    }
    etassert($login['status'] === 200, 'ops_staff login through nginx must succeed');
    $token = (string) ($login['json']['data']['token'] ?? '');
    etassert($token !== '', 'ops_staff token required');

    // Load pickup points (booking:read)
    $pp = webApi('GET', '/api/v1/bookings/pickup-points', [], $token);
    etassert($pp['status'] === 200, 'ops_staff pickup-points through nginx should return 200');

    // Load notification events (notification:read)
    $events = webApi('GET', '/api/v1/notifications/events', [], $token);
    etassert($events['status'] === 200, 'ops_staff notification events through nginx should return 200');

    // Must be denied payment operations (no payment permission)
    $payments = webApi('GET', '/api/v1/payments', [], $token);
    etassert($payments['status'] === 403, 'ops_staff must be denied payment list through nginx');
});

echo "E2E tests passed={$results['passed']} failed={$results['failed']}\n";
exit($results['failed'] === 0 ? 0 : 1);
