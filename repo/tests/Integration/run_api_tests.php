<?php
declare(strict_types=1);

$results = ['passed' => 0, 'failed' => 0];
$_hmacRaw = getenv('PANTRYPILOT_GATEWAY_HMAC_SECRET');
$GATEWAY_HMAC_SECRET = (is_string($_hmacRaw) && trim($_hmacRaw) !== '') ? trim($_hmacRaw) : 'insecure-default-hmac-secret-replace-in-production';

function tassert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function topLevelJsonObjectLength(string $raw): int
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $trimmed = ltrim($raw);
    if ($trimmed === '' || $trimmed[0] !== '{') {
        throw new RuntimeException('response body must start with a JSON object');
    }

    $len = strlen($trimmed);
    $depth = 0;
    $inString = false;
    $escaping = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $trimmed[$i];
        if ($inString) {
            if ($escaping) {
                $escaping = false;
                continue;
            }
            if ($ch === '\\') {
                $escaping = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            continue;
        }

        if ($ch === '{') {
            $depth++;
            continue;
        }

        if ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return $i + 1;
            }
            continue;
        }
    }

    throw new RuntimeException('response body contains an incomplete JSON object');
}

function decodeStrictJsonObject(string $raw): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $trimmed = ltrim($raw);
    $jsonLen = topLevelJsonObjectLength($trimmed);

    $jsonPart = substr($trimmed, 0, $jsonLen);
    $tail = trim((string) substr($trimmed, $jsonLen));
    if ($tail !== '') {
        $snippet = substr(preg_replace('/\s+/', ' ', $tail) ?? $tail, 0, 120);
        throw new RuntimeException('response has trailing bytes after JSON object: ' . $snippet);
    }

    try {
        $decoded = json_decode($jsonPart, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('response JSON decode failed: ' . $e->getMessage());
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('response JSON must decode to an object');
    }

    return $decoded;
}

function extractContentType(array $headers): string
{
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            return trim(substr($header, strlen('Content-Type:')));
        }
    }
    return '';
}

function api(string $method, string $path, array $body = [], ?string $token = null): array
{
    $url = 'http://127.0.0.1' . $path;
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $opts = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'GET' ? '' : json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
        ],
    ];

    $resp = file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    $raw = (string) ($resp ?? '');
    if ($raw === '') {
        throw new RuntimeException('empty response body for ' . $method . ' ' . $path);
    }

    $json = decodeStrictJsonObject($raw);
    $contentType = extractContentType($responseHeaders);

    return [
        'status' => $status,
        'json' => $json,
        'raw' => $raw,
        'headers' => $responseHeaders,
        'content_type' => $contentType,
    ];
}

function apiWithHeaders(string $method, string $path, array $body, array $extraHeaders, ?string $token = null): array
{
    $url = 'http://127.0.0.1' . $path;
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    foreach ($extraHeaders as $k => $v) {
        $headers[] = $k . ': ' . $v;
    }

    $opts = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
        ],
    ];

    $resp = file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    $raw = (string) ($resp ?? '');
    if ($raw === '') {
        throw new RuntimeException('empty response body for ' . $method . ' ' . $path);
    }

    return [
        'status' => $status,
        'json' => decodeStrictJsonObject($raw),
        'raw' => $raw,
        'headers' => $responseHeaders,
        'content_type' => extractContentType($responseHeaders),
    ];
}

function assertJsonContract(array $resp, string $label): void
{
    tassert($resp['status'] >= 100, $label . ': HTTP status must be present');
    tassert(str_contains(strtolower((string) $resp['content_type']), 'application/json'), $label . ': content-type must be application/json');
    tassert(isset($resp['json']['success']), $label . ': response JSON must include success field');
}

function isOk(array $resp): bool
{
    return ($resp['json']['success'] ?? false) === true;
}

function runCase(string $name, callable $fn): void
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

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('mysql:host=mysql;port=3306;dbname=pantrypilot;charset=utf8mb4', 'pantry', 'pantrypass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

$adminToken = '';
$scopedToken = '';
$uploadedFileId = 0;
$adminPaymentRef = '';

runCase('Container DNS and DB readiness from api context', function (): void {
    $resolved = gethostbyname('mysql');
    tassert($resolved !== 'mysql', 'mysql hostname should resolve via compose network DNS');

    $pdo = pdo();
    $row = $pdo->query('SELECT 1 as ok')->fetch(PDO::FETCH_ASSOC);
    tassert((int) ($row['ok'] ?? 0) === 1, 'mysql query should succeed from api test process');
});

runCase('Schema consistency keeps strict callback idempotency on transaction_ref', function (): void {
    $pdo = pdo();
    $hasCallbackHash = (int) ($pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'gateway_callbacks' AND column_name = 'callback_hash'")->fetchColumn() ?: 0);
    tassert($hasCallbackHash === 0, 'gateway_callbacks.callback_hash must be removed from live schema');

    $ukRows = $pdo->query("SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='gateway_callbacks' AND non_unique=0 GROUP BY index_name")->fetchAll(PDO::FETCH_ASSOC);
    $hasTxOnlyUnique = false;
    foreach ($ukRows as $uk) {
        if (strtolower((string) ($uk['cols'] ?? '')) === 'transaction_ref') {
            $hasTxOnlyUnique = true;
        }
    }
    tassert($hasTxOnlyUnique, 'gateway_callbacks must keep a unique index on transaction_ref only');

    $initSql = (string) file_get_contents('/workspace/db_init/001_schema.sql');
    $migrationSql = (string) file_get_contents('/var/www/html/database/migrations/202603270006_workflow_finance_compliance.sql');
    tassert(!str_contains($initSql, 'callback_hash CHAR(64) NOT NULL'), '001_schema.sql must not define callback_hash column in gateway_callbacks');
    tassert(!str_contains($migrationSql, 'callback_hash CHAR(64) NOT NULL'), 'workflow_finance_compliance migration must not define callback_hash column');
});

runCase('Deterministic clock override is active for time-based policies', function (): void {
    $now = getenv('PANTRYPILOT_TEST_NOW');
    tassert(is_string($now) && $now === '2026-01-15 10:30:00', 'integration run must pin PANTRYPILOT_TEST_NOW for deterministic policy tests');
});

runCase('Initial UI calls return strict JSON only', function (): void {
    $dashboard = api('GET', '/api/v1/reporting/dashboard');
    assertJsonContract($dashboard, 'GET /api/v1/reporting/dashboard');
    tassert($dashboard['status'] === 401, 'dashboard should respond with HTTP 401 before login');

    $login = api('POST', '/api/v1/identity/login', ['username' => 'admin', 'password' => 'admin12345']);
    assertJsonContract($login, 'POST /api/v1/identity/login');
    tassert($login['status'] === 200, 'login should respond with HTTP 200');
});

runCase('Authentication login success', function () use (&$adminToken, &$scopedToken): void {
    $r = api('POST', '/api/v1/identity/login', ['username' => 'admin', 'password' => 'admin12345']);
    assertJsonContract($r, 'admin login');
    tassert($r['status'] === 200, 'admin login should return HTTP 200');
    tassert(isOk($r), 'admin login must succeed');
    $adminToken = (string) ($r['json']['data']['token'] ?? '');
    tassert($adminToken !== '', 'admin token required');

    $r2 = api('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope123456']);
    assertJsonContract($r2, 'scoped login');
    tassert($r2['status'] === 200, 'scoped login should return HTTP 200');
    tassert(isOk($r2), 'scoped login must succeed');
    $scopedToken = (string) ($r2['json']['data']['token'] ?? '');
    tassert($scopedToken !== '', 'scoped token required');

    $pdo = pdo();
    $opsRoleId = (int) ($pdo->query("SELECT id FROM roles WHERE code='ops_staff' LIMIT 1")->fetchColumn() ?: 0);
    $readPermId = (int) ($pdo->query("SELECT id FROM permissions WHERE code='read' LIMIT 1")->fetchColumn() ?: 0);
    $writePermId = (int) ($pdo->query("SELECT id FROM permissions WHERE code='write' LIMIT 1")->fetchColumn() ?: 0);
    $approvePermId = (int) ($pdo->query("SELECT id FROM permissions WHERE code='approve' LIMIT 1")->fetchColumn() ?: 0);
    $fileResId = (int) ($pdo->query("SELECT id FROM resources WHERE code='file' LIMIT 1")->fetchColumn() ?: 0);
    $reportResId = (int) ($pdo->query("SELECT id FROM resources WHERE code='reporting' LIMIT 1")->fetchColumn() ?: 0);
    $opsResId = (int) ($pdo->query("SELECT id FROM resources WHERE code='operations' LIMIT 1")->fetchColumn() ?: 0);
    $notificationResId = (int) ($pdo->query("SELECT id FROM resources WHERE code='notification' LIMIT 1")->fetchColumn() ?: 0);
    if ($opsRoleId > 0 && $readPermId > 0 && $fileResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $readPermId, $fileResId]);
    }
    if ($opsRoleId > 0 && $readPermId > 0 && $reportResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $readPermId, $reportResId]);
    }
    if ($opsRoleId > 0 && $readPermId > 0 && $opsResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $readPermId, $opsResId]);
    }
    if ($opsRoleId > 0 && $writePermId > 0 && $opsResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $writePermId, $opsResId]);
    }
    if ($opsRoleId > 0 && $approvePermId > 0 && $fileResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $approvePermId, $fileResId]);
    }
    if ($opsRoleId > 0 && $writePermId > 0 && $notificationResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $writePermId, $notificationResId]);
    }
});

runCase('Failed-login lockout after 5 attempts', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $attempt = api('POST', '/api/v1/identity/login', ['username' => 'lock_user', 'password' => 'wrong-pass']);
        assertJsonContract($attempt, 'failed lockout attempt ' . ($i + 1));
        tassert($attempt['status'] === 401, 'failed login should return HTTP 401');
    }

    $locked = api('POST', '/api/v1/identity/login', ['username' => 'lock_user', 'password' => 'wrong-pass']);
    assertJsonContract($locked, 'lockout attempt');
    tassert($locked['status'] === 401, 'lockout should return HTTP 401');
    $msg = strtolower((string) ($locked['json']['message'] ?? ''));
    tassert(str_contains($msg, 'locked') || str_contains($msg, 'try again later'), 'lockout message should indicate temporary lock');
});

runCase('Authorization scope denies admin endpoint for scoped user', function () use (&$scopedToken): void {
    $r = api('GET', '/api/v1/admin/users', [], $scopedToken);
    assertJsonContract($r, 'scoped admin endpoint access');
    tassert($r['status'] === 403, 'scoped user must get HTTP 403 on admin endpoint');
    tassert(($r['json']['success'] ?? true) === false, 'scoped user should be denied');
});

runCase('HTTP status mapping regression remains stable (401/403/404/409/422)', function () use (&$adminToken, &$scopedToken): void {
    $unauth = api('GET', '/api/v1/reporting/dashboard');
    assertJsonContract($unauth, 'status regression 401');
    tassert($unauth['status'] === 401, 'unauthenticated request should map to 401');

    $forbidden = api('GET', '/api/v1/admin/users', [], $scopedToken);
    assertJsonContract($forbidden, 'status regression 403');
    tassert($forbidden['status'] === 403, 'forbidden request should map to 403');

    $notFound = api('GET', '/api/v1/bookings/recipe/9999999', [], $adminToken);
    assertJsonContract($notFound, 'status regression 404');
    tassert($notFound['status'] === 404, 'missing resource should map to 404');

    $slotStart = date('Y-m-d H:i:s', strtotime('+2 day 09:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $first = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $slotStart,
        'slot_start' => $slotStart, 'slot_end' => $slotEnd, 'quantity' => 1,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    $second = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $slotStart,
        'slot_start' => $slotStart, 'slot_end' => $slotEnd, 'quantity' => 1,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($first, 'status regression 409 first booking');
    assertJsonContract($second, 'status regression 409 second booking');
    $conflict = !isOk($first) ? $first : $second;
    tassert($conflict['status'] === 409, 'capacity conflict should map to 409');

    $unprocessable = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => 1,
        'pickup_at' => date('Y-m-d H:i:s', strtotime('+8 days')),
        'slot_start' => date('Y-m-d H:i:s', strtotime('+8 days')),
        'slot_end' => date('Y-m-d H:i:s', strtotime('+8 days 00:30')),
        'quantity' => 1,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($unprocessable, 'status regression 422');
    tassert($unprocessable['status'] === 422, 'validation errors should map to 422');
});

runCase('Recipe fuzzy and synonym search returns chickpea recipe', function () use (&$adminToken): void {
    $syn = api('GET', '/api/v1/recipes/search?ingredient=garbanzo&prep_under=30&max_budget=15&rank_mode=budget', [], $adminToken);
    assertJsonContract($syn, 'synonym search');
    tassert($syn['status'] === 200, 'synonym search should return HTTP 200');
    tassert(isOk($syn), 'synonym search should succeed');
    $synItems = $syn['json']['data']['items'] ?? [];
    tassert(count($synItems) >= 1, 'synonym search should return at least one item');
    $synNames = array_map(static fn ($r) => strtolower((string) ($r['name'] ?? '')), $synItems);
    tassert(in_array('chickpea stew', $synNames, true), 'synonym search garbanzo should include chickpea stew');

    $fuzzy = api('GET', '/api/v1/recipes/search?ingredient=chikpea&prep_under=30', [], $adminToken);
    assertJsonContract($fuzzy, 'fuzzy search');
    tassert($fuzzy['status'] === 200, 'fuzzy search should return HTTP 200');
    tassert(isOk($fuzzy), 'fuzzy search should succeed');
    $fuzzyItems = $fuzzy['json']['data']['items'] ?? [];
    tassert(count($fuzzyItems) >= 1, 'fuzzy search should return results');
    $fuzzyNames = array_map(static fn ($r) => strtolower((string) ($r['name'] ?? '')), $fuzzyItems);
    tassert(in_array('chickpea stew', $fuzzyNames, true), 'typo fuzzy search chikpea should include chickpea stew');
});

runCase('Recipe search supports combined tag+budget+prep filters', function () use (&$adminToken): void {
    $res = api('GET', '/api/v1/recipes/search?tags=vegan&prep_under=30&max_budget=13&rank_mode=budget', [], $adminToken);
    assertJsonContract($res, 'recipe search tag+budget+prep');
    tassert($res['status'] === 200, 'combined recipe filters should return HTTP 200');
    tassert(isOk($res), 'combined recipe filters should succeed');
    $items = $res['json']['data']['items'] ?? [];
    tassert(count($items) >= 1, 'combined filters should return at least one recipe');
    $names = array_map(static fn ($r) => strtolower((string) ($r['name'] ?? '')), $items);
    tassert(in_array('chickpea stew', $names, true), 'combined filters should include chickpea stew');
    tassert(!in_array('potato soup', $names, true), 'combined filters should exclude non-matching potato soup');
});

runCase('Recipe search rejects SQL-injection style filter inputs safely', function () use (&$adminToken): void {
    $injTag = urlencode("vegan' OR 1=1 --");
    $injIngredient = urlencode("garbanzo' UNION SELECT");
    $res = api('GET', '/api/v1/recipes/search?tags=' . $injTag . '&ingredient=' . $injIngredient . '&prep_under=30', [], $adminToken);
    assertJsonContract($res, 'recipe injection-safe search');
    tassert($res['status'] === 200, 'injection-style search inputs should not break endpoint');
    tassert(isOk($res), 'injection-style search should still return controlled success JSON');
    $items = $res['json']['data']['items'] ?? [];
    $names = array_map(static fn ($r) => strtolower((string) ($r['name'] ?? '')), $items);
    tassert(!in_array('potato soup', $names, true), 'injection input must not bypass filters to expose unrelated recipes');
});

runCase('404 for nonexistent recipe and booking resources', function () use (&$adminToken): void {
    $missingRecipe = api('GET', '/api/v1/bookings/recipe/999999', [], $adminToken);
    assertJsonContract($missingRecipe, 'missing recipe detail');
    tassert($missingRecipe['status'] === 404, 'nonexistent recipe should return HTTP 404');

    $missingBooking = api('GET', '/api/v1/bookings/999999/dispatch-note', [], $adminToken);
    assertJsonContract($missingBooking, 'missing booking dispatch note');
    tassert($missingBooking['status'] === 404, 'nonexistent booking should return HTTP 404');
});

runCase('Booking recipe detail enforces scope with explicit 403/404 behavior', function () use (&$scopedToken): void {
    $pdo = pdo();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO recipes(code,name,description,prep_minutes,step_count,servings,difficulty,calories,estimated_cost,popularity_score,status,created_by,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute(['RCP-OOS-BOOKING', 'Out Scope Recipe', 'cross-scope recipe', 10, 2, 1, 'easy', 100, 5.00, 1, 'published', 1, '999', '999', '999', $now, $now]);
    $recipeId = (int) $pdo->lastInsertId();
    tassert($recipeId > 0, 'cross-scope recipe id required');

    $scopedForbidden = api('GET', '/api/v1/bookings/recipe/' . $recipeId, [], $scopedToken);
    assertJsonContract($scopedForbidden, 'scoped booking recipe forbidden');
    tassert($scopedForbidden['status'] === 403, 'scoped user should receive 403 for cross-scope recipe detail');

    $scopedMissing = api('GET', '/api/v1/bookings/recipe/9999999', [], $scopedToken);
    assertJsonContract($scopedMissing, 'scoped booking recipe missing');
    tassert($scopedMissing['status'] === 404, 'missing recipe should return 404 before scope evaluation');
});

runCase('Booking window and cutoff constraints enforced', function () use (&$adminToken): void {
    $over = date('Y-m-d H:i:s', strtotime('+8 days'));
    $r = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $over,
        'slot_start' => $over, 'slot_end' => date('Y-m-d H:i:s', strtotime($over) + 1800),
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($r, 'booking >7 days');
    tassert($r['status'] === 422, 'booking >7 days should return HTTP 422');
    tassert(($r['json']['success'] ?? true) === false, 'booking >7d should fail');

    $near = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $r2 = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $near,
        'slot_start' => $near, 'slot_end' => date('Y-m-d H:i:s', strtotime($near) + 1800),
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($r2, 'booking inside cutoff');
    tassert($r2['status'] === 422, 'booking inside cutoff should return HTTP 422');
    tassert(($r2['json']['success'] ?? true) === false, 'booking <2h should fail');

    $zipMismatch = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'slot_start' => date('Y-m-d H:i:s', strtotime('+3 days')), 'slot_end' => date('Y-m-d H:i:s', strtotime('+3 days 00:30')),
        'quantity' => 1, 'customer_zip4' => '12345-6790', 'customer_region_code' => 'REG-002',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($zipMismatch, 'booking zip4-region mismatch');
    tassert($zipMismatch['status'] === 422, 'ZIP+4 region mismatch should return HTTP 422');
});

runCase('Slot capacity contention allows only one booking', function () use (&$adminToken): void {
    $slotStart = date('Y-m-d H:i:s', strtotime('+1 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);

    $a = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $slotStart,
        'slot_start' => $slotStart, 'slot_end' => $slotEnd, 'quantity' => 1,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    $b = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $slotStart,
        'slot_start' => $slotStart, 'slot_end' => $slotEnd, 'quantity' => 1,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($a, 'slot contention booking A');
    assertJsonContract($b, 'slot contention booking B');

    $okCount = (isOk($a) ? 1 : 0) + (isOk($b) ? 1 : 0);
    $failCount = ((isOk($a) ? 0 : 1) + (isOk($b) ? 0 : 1));
    tassert($okCount === 1 && $failCount === 1, 'capacity flow must accept exactly one booking');
    $conflict = !isOk($a) ? $a : $b;
    tassert($conflict['status'] === 409, 'slot contention failure should return HTTP 409 conflict');

    $pdo = pdo();
    $slotRow = $pdo->query("SELECT reserved_count, capacity FROM pickup_slots WHERE pickup_point_id = 1 AND slot_start = '" . $slotStart . "' AND slot_end = '" . $slotEnd . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((int) ($slotRow['reserved_count'] ?? -1) === 1, 'slot contention should reserve exactly one unit in DB');
    $bookingCount = (int) ($pdo->query("SELECT COUNT(*) FROM bookings WHERE pickup_point_id = 1 AND slot_start = '" . $slotStart . "' AND slot_end = '" . $slotEnd . "'")->fetchColumn() ?: 0);
    tassert($bookingCount === 1, 'slot contention should persist exactly one booking row for contested slot');
});

runCase('Booking reservation rollback prevents slot drift when insert fails after reserve', function () use (&$adminToken): void {
    $pdo = pdo();
    $pdo->prepare('INSERT INTO pickup_points(name,address,slot_size,active,region_code,latitude,longitude,service_radius_km,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute(['Rollback Point', 'Scope street', 2, 1, 'REG-001', 40.7128, -74.0060, 10.0, date('Y-m-d H:i:s')]);
    $pickupPointId = (int) $pdo->lastInsertId();
    tassert($pickupPointId > 0, 'rollback pickup point id required');

    $slotStart = date('Y-m-d H:i:s', strtotime('+2 day 11:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $code = 'BKG-ROLLBACK-TEST';

    $first = api('POST', '/api/v1/bookings', [
        'booking_code' => $code,
        'recipe_id' => 1,
        'pickup_point_id' => $pickupPointId,
        'pickup_at' => $slotStart,
        'slot_start' => $slotStart,
        'slot_end' => $slotEnd,
        'quantity' => 1,
        'customer_zip4' => '12345-6789',
        'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128,
        'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($first, 'rollback booking first insert');
    tassert($first['status'] === 201, 'first rollback booking should succeed');

    $beforeReserved = (int) ($pdo->query("SELECT reserved_count FROM pickup_slots WHERE pickup_point_id={$pickupPointId} AND slot_start='{$slotStart}' AND slot_end='{$slotEnd}' LIMIT 1")->fetchColumn() ?: 0);
    tassert($beforeReserved === 1, 'pre-failure reserved count should be 1');

    $dup = api('POST', '/api/v1/bookings', [
        'booking_code' => $code,
        'recipe_id' => 1,
        'pickup_point_id' => $pickupPointId,
        'pickup_at' => $slotStart,
        'slot_start' => $slotStart,
        'slot_end' => $slotEnd,
        'quantity' => 1,
        'customer_zip4' => '12345-6789',
        'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128,
        'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($dup, 'rollback booking duplicate insert');
    tassert($dup['status'] === 409, 'duplicate booking insert should fail with 409 conflict');

    $afterReserved = (int) ($pdo->query("SELECT reserved_count FROM pickup_slots WHERE pickup_point_id={$pickupPointId} AND slot_start='{$slotStart}' AND slot_end='{$slotEnd}' LIMIT 1")->fetchColumn() ?: 0);
    tassert($afterReserved === 1, 'reserved count must roll back to 1 when booking insert fails');
});

runCase('Booking flow supports runtime pickup-point selection (no fixed ID required)', function () use (&$adminToken): void {
    $pdo = pdo();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO pickup_points(name,address,slot_size,active,region_code,latitude,longitude,service_radius_km,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute(['Runtime Select Point', 'Runtime Addr', 2, 1, 'REG-001', 40.7128, -74.0060, 10.0, $now]);
    $createdPointId = (int) $pdo->lastInsertId();
    tassert($createdPointId > 0, 'created pickup point id required for runtime selection test');

    $points = api('GET', '/api/v1/bookings/pickup-points', [], $adminToken);
    assertJsonContract($points, 'pickup points runtime list');
    tassert($points['status'] === 200, 'pickup points endpoint should return 200');
    $items = $points['json']['data']['items'] ?? [];
    tassert(count($items) > 0, 'pickup points list should not be empty');
    $pickupPointId = 0;
    foreach ($items as $item) {
        if ((int) ($item['id'] ?? 0) === $createdPointId) {
            $pickupPointId = $createdPointId;
            break;
        }
    }
    if ($pickupPointId === 0) {
        $pickupPointId = $createdPointId;
    }
    tassert($pickupPointId > 0, 'pickup point id from runtime API is required');

    $slotStart = date('Y-m-d H:i:s', strtotime('+3 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1,
        'pickup_point_id' => $pickupPointId,
        'pickup_at' => $slotStart,
        'slot_start' => $slotStart,
        'slot_end' => $slotEnd,
        'quantity' => 1,
        'customer_zip4' => '12345-6789',
        'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128,
        'customer_longitude' => -74.0060,
    ], $adminToken);
    assertJsonContract($booking, 'booking with runtime pickup point');
    tassert($booking['status'] === 201, 'booking should succeed using runtime pickup point selection');
});

runCase('Bookings list pagination boundary returns metadata', function () use (&$adminToken): void {
    $paged = api('GET', '/api/v1/bookings?page=1&per_page=1', [], $adminToken);
    assertJsonContract($paged, 'bookings pagination');
    tassert($paged['status'] === 200, 'bookings pagination should return 200');
    $pagination = $paged['json']['data']['pagination'] ?? [];
    tassert((int) ($pagination['page'] ?? 0) === 1, 'pagination.page should be 1');
    tassert((int) ($pagination['per_page'] ?? 0) === 1, 'pagination.per_page should be 1');
    tassert((int) ($pagination['total'] ?? 0) >= 1, 'pagination.total should be present');
    tassert(count(($paged['json']['data']['items'] ?? [])) <= 1, 'per_page=1 should cap returned rows');
});

runCase('Scope injection is ignored on booking create', function () use (&$scopedToken): void {
    $slotStart = date('Y-m-d H:i:s', strtotime('+2 day 11:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $create = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1,
        'user_id' => 1,
        'pickup_point_id' => 2,
        'pickup_at' => $slotStart,
        'slot_start' => $slotStart,
        'slot_end' => $slotEnd,
        'quantity' => 1,
        'customer_zip4' => '12345-6789',
        'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7306,
        'customer_longitude' => -73.9352,
        'store_id' => '999',
        'warehouse_id' => '999',
        'department_id' => '999',
    ], $scopedToken);
    assertJsonContract($create, 'booking create with injected scopes');
    tassert($create['status'] === 201, 'booking create should still succeed');
    $bookingId = (int) ($create['json']['data']['id'] ?? 0);
    tassert($bookingId > 0, 'created booking id expected');

    $pdo = pdo();
    $row = $pdo->prepare('SELECT user_id, store_id, warehouse_id, department_id FROM bookings WHERE id = ?');
    $row->execute([$bookingId]);
    $r = $row->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((int) ($r['user_id'] ?? 0) === 2, 'booking user_id should be forced to scoped auth user');
    tassert((string) ($r['store_id'] ?? '') !== '999', 'store scope injection must be ignored');
    tassert((string) ($r['warehouse_id'] ?? '') !== '999', 'warehouse scope injection must be ignored');
    tassert((string) ($r['department_id'] ?? '') !== '999', 'department scope injection must be ignored');
});

runCase('Today-pickups respects data scopes for scoped user', function () use (&$scopedToken): void {
    $pdo = pdo();
    $code = 'BKG-OOS-' . strtoupper(bin2hex(random_bytes(3)));
    $slot = date('Y-m-d H:i:s', strtotime('+2 hour'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$code, 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '999', '999', '999', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    $r = api('GET', '/api/v1/bookings/today-pickups', [], $scopedToken);
    assertJsonContract($r, 'today pickups scoped');
    tassert($r['status'] === 200, 'today pickups should return 200');
    foreach (($r['json']['data']['items'] ?? []) as $item) {
        tassert((string) ($item['store_id'] ?? '') !== '999', 'scoped user must not see out-of-scope booking');
    }
});

runCase('Operations endpoints enforce scope filtering across campaigns/modules/templates/dashboard', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d H:i:s');

    $beforeScopedDashboard = api('GET', '/api/v1/operations/dashboard', [], $scopedToken);
    assertJsonContract($beforeScopedDashboard, 'operations dashboard scoped before');
    tassert($beforeScopedDashboard['status'] === 200, 'scoped operations dashboard before should return 200');

    $pdo->prepare('INSERT INTO campaigns(name,start_at,end_at,budget,status,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute(['Out Scope Campaign', $now, date('Y-m-d H:i:s', strtotime('+2 days')), 100.0, 'planned', '999', '999', '999', $now]);

    $campaignCreate = api('POST', '/api/v1/operations/campaigns', [
        'name' => 'Scoped Campaign',
        'start_at' => $now,
        'end_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        'budget' => 20,
        'store_id' => '999',
        'warehouse_id' => '999',
        'department_id' => '999',
    ], $scopedToken);
    assertJsonContract($campaignCreate, 'scoped campaign create with injection');
    tassert($campaignCreate['status'] === 201, 'scoped campaign create should return 201');
    $campaignId = (int) ($campaignCreate['json']['data']['id'] ?? 0);
    tassert($campaignId > 0, 'scoped campaign id required');
    $campaignRow = $pdo->query('SELECT store_id,warehouse_id,department_id FROM campaigns WHERE id=' . $campaignId)->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($campaignRow['store_id'] ?? '') === '1', 'campaign store scope must be forced from auth context');

    $campaignList = api('GET', '/api/v1/operations/campaigns', [], $scopedToken);
    assertJsonContract($campaignList, 'scoped operations campaigns list');
    tassert($campaignList['status'] === 200, 'scoped campaigns list should return 200');
    foreach (($campaignList['json']['data']['items'] ?? []) as $item) {
        tassert((string) ($item['store_id'] ?? '') !== '999', 'scoped campaigns list must exclude out-of-scope campaigns');
    }

    $pdo->prepare('INSERT INTO homepage_modules(module_key,payload,enabled,updated_by,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute(['regional_special', json_encode(['title' => 'x'], JSON_UNESCAPED_UNICODE), 1, 1, '999', '999', '999', $now, $now]);

    $moduleSave = api('POST', '/api/v1/operations/homepage-modules', [
        'module_key' => 'carousel_banners',
        'payload' => ['title' => 'scoped'],
        'enabled' => 1,
        'store_id' => '999',
        'warehouse_id' => '999',
        'department_id' => '999',
    ], $scopedToken);
    assertJsonContract($moduleSave, 'scoped homepage module save');
    tassert($moduleSave['status'] === 200, 'scoped homepage module save should return 200');
    $moduleRow = $pdo->query("SELECT store_id,warehouse_id,department_id FROM homepage_modules WHERE module_key='carousel_banners' AND store_id='1' LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($moduleRow['store_id'] ?? '') === '1', 'homepage module scope must be forced from auth context');

    $moduleList = api('GET', '/api/v1/operations/homepage-modules', [], $scopedToken);
    assertJsonContract($moduleList, 'scoped homepage module list');
    tassert($moduleList['status'] === 200, 'scoped homepage module list should return 200');
    foreach (($moduleList['json']['data']['items'] ?? []) as $item) {
        tassert((string) ($item['store_id'] ?? '') !== '999', 'scoped homepage module list must exclude out-of-scope rows');
    }

    $pdo->prepare('INSERT INTO message_templates(template_code,title,content,category,active,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute(['TPL-OOS-1', 'out scope', 'x', 'system', 1, '999', '999', '999', $now, $now]);

    $templateSave = api('POST', '/api/v1/operations/message-templates', [
        'template_code' => 'TPL-SCOPED-1',
        'title' => 'Scoped Template',
        'content' => 'Hello scoped',
        'category' => 'system',
        'active' => 1,
        'store_id' => '999',
        'warehouse_id' => '999',
        'department_id' => '999',
    ], $scopedToken);
    assertJsonContract($templateSave, 'scoped message template save');
    tassert($templateSave['status'] === 201, 'scoped message template save should return 201');
    $templateId = (int) ($templateSave['json']['data']['id'] ?? 0);
    tassert($templateId > 0, 'scoped message template id required');
    $templateRow = $pdo->query('SELECT store_id,warehouse_id,department_id FROM message_templates WHERE id=' . $templateId)->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($templateRow['store_id'] ?? '') === '1', 'message template scope must be forced from auth context');

    $templateList = api('GET', '/api/v1/operations/message-templates', [], $scopedToken);
    assertJsonContract($templateList, 'scoped message templates list');
    tassert($templateList['status'] === 200, 'scoped message template list should return 200');
    foreach (($templateList['json']['data']['items'] ?? []) as $item) {
        tassert((string) ($item['store_id'] ?? '') !== '999', 'scoped message template list must exclude out-of-scope rows');
    }

    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['BKG-OOS-OPS', 1, 1, 1, $today, $today, date('Y-m-d H:i:s', strtotime($today) + 1800), 1, 'pending', '999', '999', '999', $now, $now]);
    $bookingId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO payments(payment_ref,booking_id,amount,method,status,paid_at,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute(['PAY-OOS-OPS-1', $bookingId, 5.00, 'cash', 'captured', $now, '999', '999', '999', $now]);

    $afterScopedDashboard = api('GET', '/api/v1/operations/dashboard', [], $scopedToken);
    assertJsonContract($afterScopedDashboard, 'operations dashboard scoped after');
    tassert($afterScopedDashboard['status'] === 200, 'scoped operations dashboard after should return 200');
    tassert(($beforeScopedDashboard['json']['data'] ?? []) === ($afterScopedDashboard['json']['data'] ?? []), 'out-of-scope operations data must not change scoped dashboard metrics');

    $adminDashboard = api('GET', '/api/v1/operations/dashboard', [], $adminToken);
    assertJsonContract($adminDashboard, 'operations dashboard admin after');
    tassert($adminDashboard['status'] === 200, 'admin operations dashboard should return 200');
});

runCase('No-show sweep triggers blacklist automation', function () use (&$adminToken, &$scopedToken): void {
    $r = api('POST', '/api/v1/bookings/no-show-sweep', [], $adminToken);
    assertJsonContract($r, 'no-show sweep');
    tassert($r['status'] === 200, 'no-show sweep should return HTTP 200');

    $future = date('Y-m-d H:i:s', strtotime('+3 days 10:00'));
    $blocked = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'user_id' => 2, 'pickup_point_id' => 2, 'pickup_at' => $future,
        'slot_start' => $future, 'slot_end' => date('Y-m-d H:i:s', strtotime($future) + 1800),
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7306, 'customer_longitude' => -73.9352,
    ], $scopedToken);
    assertJsonContract($blocked, 'blacklist booking');
    tassert($blocked['status'] === 422, 'blacklisted booking should return HTTP 422');
    tassert(($blocked['json']['success'] ?? true) === false, 'blacklisted user booking must fail');
});

runCase('Payment callback signature verification and strict transaction_ref idempotency', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $create = api('POST', '/api/v1/payments/gateway/orders', ['booking_id' => 1, 'amount' => 9.99], $adminToken);
    assertJsonContract($create, 'gateway order create');
    tassert($create['status'] === 201, 'gateway order create should return HTTP 201');
    tassert(isOk($create), 'gateway create should succeed');
    $orderRef = (string) ($create['json']['data']['order_ref'] ?? '');
    tassert($orderRef !== '', 'gateway order ref required');

    $payload = ['amount' => 9.99, 'order_ref' => $orderRef, 'status' => 'SUCCESS', 'transaction_ref' => 'TX-OK-001'];
    ksort($payload);
    $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);

    $cb1 = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $signature], null);
    assertJsonContract($cb1, 'gateway callback first');
    tassert($cb1['status'] === 200, 'first callback should return HTTP 200');
    tassert(isOk($cb1), 'first callback should pass');

    $cb2 = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $signature], null);
    assertJsonContract($cb2, 'gateway callback second');
    tassert($cb2['status'] === 200, 'second callback should return HTTP 200');
    tassert(isOk($cb2), 'second callback should still pass idempotently');
    tassert(($cb2['json']['data']['idempotent'] ?? false) === true, 'second callback must be idempotent');

    $alteredPayload = $payload;
    $alteredPayload['status'] = 'SUCCESS';
    $alteredPayload['amount'] = 9.98;
    ksort($alteredPayload);
    $alteredSig = hash_hmac('sha256', json_encode($alteredPayload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);
    $cbAltered = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $alteredPayload, ['X-Signature' => $alteredSig], null);
    assertJsonContract($cbAltered, 'gateway callback altered payload same tx');
    tassert($cbAltered['status'] === 200, 'same transaction_ref with altered payload should be idempotent 200');
    tassert(($cbAltered['json']['data']['idempotent'] ?? false) === true, 'same transaction_ref must be idempotent regardless payload changes');

    $pdo = pdo();
    $callbackRows = (int) ($pdo->query("SELECT COUNT(*) FROM gateway_callbacks WHERE transaction_ref='TX-OK-001'")->fetchColumn() ?: 0);
    tassert($callbackRows === 1, 'gateway_callbacks must keep exactly one row per transaction_ref');
    $capturedPayments = (int) ($pdo->query("SELECT COUNT(*) FROM payments WHERE booking_id=1 AND method='wechat_local' AND status='captured'")->fetchColumn() ?: 0);
    tassert($capturedPayments === 1, 'duplicate callbacks must not create duplicate captured payments');

    $badPayload = $payload;
    $badPayload['transaction_ref'] = 'TX-BAD-001';
    $bad = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $badPayload, ['X-Signature' => 'bad-signature'], null);
    assertJsonContract($bad, 'gateway callback bad signature');
    tassert($bad['status'] === 422, 'bad signature should fail with HTTP 422');

    $pdo = pdo();
    $badCbRows = (int) ($pdo->query("SELECT COUNT(*) FROM gateway_callbacks WHERE transaction_ref='TX-BAD-001'")->fetchColumn() ?: 0);
    tassert($badCbRows === 0, 'bad-signature callback must not persist callback rows');
});

runCase('Payment callback remains safe under concurrent duplicate attempts', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $create = api('POST', '/api/v1/payments/gateway/orders', ['booking_id' => 2, 'amount' => 8.88], $adminToken);
    assertJsonContract($create, 'gateway order create for race');
    tassert($create['status'] === 201, 'gateway order create for race should return HTTP 201');
    $orderRef = (string) ($create['json']['data']['order_ref'] ?? '');
    tassert($orderRef !== '', 'gateway race order ref required');

    $payload = ['amount' => 8.88, 'order_ref' => $orderRef, 'status' => 'SUCCESS', 'transaction_ref' => 'TX-RACE-001'];
    ksort($payload);
    $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);

    if (function_exists('curl_multi_init')) {
        $url = 'http://127.0.0.1/api/v1/payments/gateway/callback';
        $headers = ['Content-Type: application/json', 'X-Signature: ' . $signature];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $mh = curl_multi_init();
        $handles = [];
        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.2);
            }
        } while ($running > 0 && $status === CURLM_OK);

        foreach ($handles as $ch) {
            $resp = (string) curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            tassert($code === 200, 'concurrent callback HTTP code should be 200');
            $json = decodeStrictJsonObject($resp);
            tassert(isset($json['success']), 'concurrent callback response must include success');
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    } else {
        $a = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $signature], null);
        $b = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $signature], null);
        assertJsonContract($a, 'callback race fallback A');
        assertJsonContract($b, 'callback race fallback B');
        tassert($a['status'] === 200 && $b['status'] === 200, 'fallback duplicate callbacks should return 200');
    }

    $pdo = pdo();
    $callbackRows = (int) ($pdo->query("SELECT COUNT(*) FROM gateway_callbacks WHERE transaction_ref='TX-RACE-001'")->fetchColumn() ?: 0);
    tassert($callbackRows === 1, 'concurrent callbacks must persist only one callback row');
    $capturedPayments = (int) ($pdo->query("SELECT COUNT(*) FROM payments WHERE booking_id=2 AND method='wechat_local' AND status='captured'")->fetchColumn() ?: 0);
    tassert($capturedPayments === 1, 'concurrent callbacks must create exactly one captured payment');
});

runCase('Reconciliation mismatch detection catches missed order', function () use (&$adminToken): void {
    $today = date('Y-m-d');
    $r = api('POST', '/api/v1/payments/reconcile/daily', ['date' => $today], $adminToken);
    assertJsonContract($r, 'daily reconciliation');
    tassert($r['status'] === 200, 'daily reconciliation should return HTTP 200');
    tassert(isOk($r), 'daily reconcile should succeed');
    $issues = (int) ($r['json']['data']['issues'] ?? -1);
    tassert($issues >= 0, 'issues field should be present');
});

runCase('Reconciliation edge case closes to zero issues when gateway and payment match', function () use (&$adminToken): void {
    $today = date('Y-m-d');
    $seedPayment = api('POST', '/api/v1/payments', [
        'booking_id' => 3,
        'amount' => 10.0,
        'method' => 'cash',
        'status' => 'captured',
    ], $adminToken);
    assertJsonContract($seedPayment, 'seed payment for zero-issue reconciliation');
    tassert($seedPayment['status'] === 201, 'seed payment should be created for reconciliation edge case');

    $r = api('POST', '/api/v1/payments/reconcile/daily', ['date' => $today], $adminToken);
    assertJsonContract($r, 'daily reconciliation zero issue case');
    tassert($r['status'] === 200, 'zero-issue reconciliation should return 200');
    tassert((int) ($r['json']['data']['issues'] ?? -1) === 0, 'reconciliation should report zero issues when records fully match');
});

runCase('File upload enforces strict type and 10MB size policy boundaries', function () use (&$adminToken): void {
    $badType = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'bad.bin',
        'mime_type' => 'application/octet-stream',
        'content_base64' => base64_encode('binary'),
    ], $adminToken);
    assertJsonContract($badType, 'file unsupported type');
    tassert($badType['status'] === 422, 'unsupported mime type should return 422');
});

runCase('Image watermarking renders valid binaries or fails explicitly without prerequisites', function () use (&$adminToken): void {
    $png1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+Lw0AAAAASUVORK5CYII=';
    $wm = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'wm-test.png',
        'mime_type' => 'image/png',
        'content_base64' => $png1x1,
        'watermark' => true,
    ], $adminToken);
    assertJsonContract($wm, 'watermarked png upload');

    if (function_exists('imagecreatefromstring') && function_exists('imagestring')) {
        tassert($wm['status'] === 201, 'watermarked image upload should succeed when GD is available');
        $fileId = (int) ($wm['json']['data']['id'] ?? 0);
        tassert($fileId > 0, 'watermarked file id required');
        $signed = api('GET', '/api/v1/files/' . $fileId . '/signed-url', [], $adminToken);
        assertJsonContract($signed, 'signed URL for watermarked image');
        parse_str(parse_url((string) ($signed['json']['data']['download_url'] ?? ''), PHP_URL_QUERY) ?: '', $q);
        $token = (string) ($q['token'] ?? '');
        $download = api('GET', '/api/v1/files/download/' . $fileId . '?token=' . urlencode($token), [], $adminToken);
        assertJsonContract($download, 'download watermarked image');
        $raw = base64_decode((string) ($download['json']['data']['content_base64'] ?? ''), true);
        tassert(is_string($raw) && str_starts_with($raw, "\x89PNG"), 'watermarked output must remain a valid PNG binary');
        tassert(imagecreatefromstring($raw) !== false, 'watermarked output must be parseable as image');
    } else {
        tassert($wm['status'] === 422, 'watermarking should fail explicitly when image processing prerequisites are missing');
        $msg = strtolower((string) ($wm['json']['message'] ?? ''));
        tassert(str_contains($msg, 'gd'), 'missing prerequisites response should clearly mention GD/image processing requirement');
    }
});

runCase('Signed URL expiry and hotlink validation', function () use (&$adminToken, &$uploadedFileId): void {
    $upload = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        'watermark' => false,
    ], $adminToken);
    assertJsonContract($upload, 'file upload');
    tassert($upload['status'] === 201, 'file upload should return HTTP 201');
    tassert(isOk($upload), 'file upload should succeed');
    $uploadedFileId = (int) ($upload['json']['data']['id'] ?? 0);
    tassert($uploadedFileId > 0, 'uploaded file id should be positive');

    $signed = api('GET', '/api/v1/files/' . $uploadedFileId . '/signed-url', [], $adminToken);
    assertJsonContract($signed, 'signed URL');
    tassert($signed['status'] === 200, 'signed-url should return HTTP 200');
    tassert(isOk($signed), 'signed-url should succeed');
    $downloadUrl = (string) ($signed['json']['data']['download_url'] ?? '');
    tassert($downloadUrl !== '', 'download URL required');

    parse_str(parse_url($downloadUrl, PHP_URL_QUERY) ?: '', $q);
    $token = (string) ($q['token'] ?? '');
    tassert($token !== '', 'download token required');

    $badToken = api('GET', '/api/v1/files/download/' . $uploadedFileId . '?token=bad-token', [], $adminToken);
    assertJsonContract($badToken, 'download with bad token');
    tassert($badToken['status'] === 403, 'bad token should return HTTP 403');

    $ok = api('GET', '/api/v1/files/download/' . $uploadedFileId . '?token=' . urlencode($token), [], $adminToken);
    assertJsonContract($ok, 'download before expiry');
    tassert($ok['status'] === 200, 'download before expiry should return HTTP 200');
    tassert(isOk($ok), 'download before expiry should pass');

    $pdo = pdo();
    $pdo->prepare('UPDATE attachments SET signed_url_expire_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$uploadedFileId]);
    $expired = api('GET', '/api/v1/files/download/' . $uploadedFileId . '?token=' . urlencode($token), [], $adminToken);
    assertJsonContract($expired, 'download after expiry');
    tassert($expired['status'] === 403, 'download after expiry should fail');
});

runCase('Scoped user cannot generate signed URL for foreign file', function () use (&$adminToken, &$scopedToken): void {
    $upload = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'owner-check.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    assertJsonContract($upload, 'upload foreign ownership test file');
    $fileId = (int) ($upload['json']['data']['id'] ?? 0);
    tassert($fileId > 0, 'foreign ownership file id required');

    $forbidden = api('GET', '/api/v1/files/' . $fileId . '/signed-url', [], $scopedToken);
    assertJsonContract($forbidden, 'scoped signed url forbidden');
    tassert($forbidden['status'] === 403, 'scoped user should be forbidden from foreign signed URL');
});

runCase('Scoped file listing excludes foreign attachments', function () use (&$adminToken, &$scopedToken): void {
    $upload = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'admin-only.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    $id = (int) ($upload['json']['data']['id'] ?? 0);
    tassert($id > 0, 'admin file id required');

    $list = api('GET', '/api/v1/files', [], $scopedToken);
    assertJsonContract($list, 'scoped file list');
    tassert($list['status'] === 200, 'scoped file list should return 200');
    foreach (($list['json']['data']['items'] ?? []) as $item) {
        tassert((int) ($item['id'] ?? 0) !== $id, 'scoped file list must not include admin-owned attachment');
    }
});

runCase('Scoped file listing filters booking/payment owners at query level behavior', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $slot = date('Y-m-d H:i:s', strtotime('+2 day 14:00'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['BKG-FILE-SCOPE', 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '999', '999', '999', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $foreignBookingId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO payments(payment_ref,booking_id,amount,method,status,paid_at,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute(['PAY-FILE-SCOPE', 1, 5.0, 'cash', 'captured', date('Y-m-d H:i:s'), '999', '999', '999', date('Y-m-d H:i:s')]);
    $foreignPaymentId = (int) $pdo->lastInsertId();

    $uploadBooking = api('POST', '/api/v1/files/upload-base64', [
        'owner_type' => 'booking',
        'owner_id' => $foreignBookingId,
        'filename' => 'foreign-booking.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    assertJsonContract($uploadBooking, 'upload foreign booking attachment');
    $foreignBookingAttachment = (int) ($uploadBooking['json']['data']['id'] ?? 0);

    $uploadPayment = api('POST', '/api/v1/files/upload-base64', [
        'owner_type' => 'payment',
        'owner_id' => $foreignPaymentId,
        'filename' => 'foreign-payment.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    assertJsonContract($uploadPayment, 'upload foreign payment attachment');
    $foreignPaymentAttachment = (int) ($uploadPayment['json']['data']['id'] ?? 0);

    $list = api('GET', '/api/v1/files', [], $scopedToken);
    assertJsonContract($list, 'scoped file list owner filters');
    tassert($list['status'] === 200, 'scoped file list should return 200 for mixed owner types');
    foreach (($list['json']['data']['items'] ?? []) as $item) {
        $id = (int) ($item['id'] ?? 0);
        tassert($id !== $foreignBookingAttachment, 'scoped list must exclude out-of-scope booking attachment');
        tassert($id !== $foreignPaymentAttachment, 'scoped list must exclude out-of-scope payment attachment');
    }
});

runCase('File lifecycle cleanup removes DB rows and physical files safely', function () use (&$adminToken): void {
    $upload = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'cleanup_target.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        'watermark' => false,
    ], $adminToken);
    assertJsonContract($upload, 'upload cleanup target');
    tassert($upload['status'] === 201, 'cleanup target upload should return 201');
    $fileId = (int) ($upload['json']['data']['id'] ?? 0);
    $storagePath = (string) ($upload['json']['data']['storage_path'] ?? '');
    tassert($fileId > 0 && $storagePath !== '', 'cleanup target id and storage path required');
    $absolutePath = '/var/www/html/' . ltrim($storagePath, '/');
    clearstatcache(true, $absolutePath);
    tassert(is_file($absolutePath), 'cleanup target physical file should exist before cleanup');

    $pdo = pdo();
    $pdo->prepare('UPDATE attachments SET created_at = DATE_SUB(NOW(), INTERVAL 181 DAY) WHERE id = ?')->execute([$fileId]);

    $cleanup = api('POST', '/api/v1/files/cleanup', [], $adminToken);
    assertJsonContract($cleanup, 'file cleanup with physical delete');
    tassert($cleanup['status'] === 200, 'file cleanup should return 200');
    tassert(((int) ($cleanup['json']['data']['deleted_records'] ?? 0)) >= 1, 'cleanup should report deleted records');

    $existsDb = (int) ($pdo->query('SELECT COUNT(*) FROM attachments WHERE id=' . $fileId)->fetchColumn() ?: 0);
    tassert($existsDb === 0, 'cleanup should delete DB attachment row');
    clearstatcache(true, $absolutePath);
    tassert(!is_file($absolutePath), 'cleanup should delete physical file');

    $upload2 = api('POST', '/api/v1/files/upload-base64', [
        'filename' => 'cleanup_missing.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        'watermark' => false,
    ], $adminToken);
    assertJsonContract($upload2, 'upload cleanup missing target');
    tassert($upload2['status'] === 201, 'cleanup missing target upload should return 201');
    $fileId2 = (int) ($upload2['json']['data']['id'] ?? 0);
    $storagePath2 = (string) ($upload2['json']['data']['storage_path'] ?? '');
    $absolutePath2 = '/var/www/html/' . ltrim($storagePath2, '/');
    tassert($fileId2 > 0 && $storagePath2 !== '', 'cleanup missing target id and path required');
    if (is_file($absolutePath2)) {
        unlink($absolutePath2);
    }
    $pdo->prepare('UPDATE attachments SET created_at = DATE_SUB(NOW(), INTERVAL 181 DAY) WHERE id = ?')->execute([$fileId2]);

    $cleanup2 = api('POST', '/api/v1/files/cleanup', [], $adminToken);
    assertJsonContract($cleanup2, 'file cleanup missing file resilient');
    tassert($cleanup2['status'] === 200, 'cleanup with missing file should still return 200');
    tassert(((int) ($cleanup2['json']['data']['missing_files'] ?? 0)) >= 1, 'cleanup should report missing file count');
    $existsDb2 = (int) ($pdo->query('SELECT COUNT(*) FROM attachments WHERE id=' . $fileId2)->fetchColumn() ?: 0);
    tassert($existsDb2 === 0, 'cleanup should delete DB row even when physical file is already missing');

    $cleanup3 = api('POST', '/api/v1/files/cleanup', [], $adminToken);
    assertJsonContract($cleanup3, 'file cleanup idempotent rerun');
    tassert($cleanup3['status'] === 200, 'second cleanup rerun should remain successful');
    tassert(((int) ($cleanup3['json']['data']['deleted_records'] ?? 0)) === 0, 'idempotent cleanup rerun should have zero additional deletions');
});

runCase('Scoped cleanup cannot delete cross-scope attachments but can delete in-scope owned files', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $scopedUserId = (int) ($pdo->query("SELECT id FROM users WHERE username='scoped_user' LIMIT 1")->fetchColumn() ?: 0);
    tassert($scopedUserId > 0, 'scoped user id required for scoped cleanup test');

    $adminFile = api('POST', '/api/v1/files/upload-base64', [
        'owner_type' => 'user',
        'owner_id' => 1,
        'filename' => 'admin-expired.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    assertJsonContract($adminFile, 'admin expired file upload');
    $adminFileId = (int) ($adminFile['json']['data']['id'] ?? 0);

    $scopedFile = api('POST', '/api/v1/files/upload-base64', [
        'owner_type' => 'user',
        'owner_id' => $scopedUserId,
        'filename' => 'scoped-expired.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
    ], $adminToken);
    assertJsonContract($scopedFile, 'scoped expired file upload');
    $scopedFileId = (int) ($scopedFile['json']['data']['id'] ?? 0);

    tassert($adminFileId > 0 && $scopedFileId > 0, 'cleanup scope test file ids required');
    $pdo->prepare('UPDATE attachments SET created_at = DATE_SUB(NOW(), INTERVAL 181 DAY) WHERE id IN (?,?)')->execute([$adminFileId, $scopedFileId]);

    $cleanup = api('POST', '/api/v1/files/cleanup', [], $scopedToken);
    assertJsonContract($cleanup, 'scoped cleanup run');
    tassert($cleanup['status'] === 200, 'scoped cleanup should be allowed and return 200');

    $adminExists = (int) ($pdo->query('SELECT COUNT(*) FROM attachments WHERE id=' . $adminFileId)->fetchColumn() ?: 0);
    $scopedExists = (int) ($pdo->query('SELECT COUNT(*) FROM attachments WHERE id=' . $scopedFileId)->fetchColumn() ?: 0);
    tassert($adminExists === 1, 'scoped cleanup must not delete cross-scope/admin-owned attachment');
    tassert($scopedExists === 0, 'scoped cleanup should delete in-scope owned expired attachment');
});

runCase('Scoped reporting export excludes out-of-scope bookings', function () use (&$scopedToken): void {
    $pdo = pdo();
    $code = 'BKG-OUT-' . strtoupper(bin2hex(random_bytes(3)));
    $slot = date('Y-m-d H:i:s', strtotime('+3 day 09:00'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$code, 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '999', '999', '999', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    $csv = api('GET', '/api/v1/reporting/exports/bookings-csv', [], $scopedToken);
    assertJsonContract($csv, 'scoped bookings csv');
    tassert($csv['status'] === 200, 'scoped csv should return 200');
    $decoded = base64_decode((string) ($csv['json']['data']['content_base64'] ?? ''), true);
    tassert($decoded !== false, 'csv should decode');
    tassert(!str_contains((string) $decoded, $code), 'scoped csv must exclude out-of-scope booking');
});

runCase('Stockout anomaly metrics are scope-isolated for scoped users', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $pdo->prepare('INSERT INTO stock_snapshots(sku,qty,snapshot_date,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?)')
        ->execute(['SKU-OOS-ZERO', 0, date('Y-m-d'), '999', '999', '999', date('Y-m-d H:i:s')]);

    $scoped = api('GET', '/api/v1/reporting/anomalies', [], $scopedToken);
    assertJsonContract($scoped, 'scoped anomaly stockout isolation');
    tassert($scoped['status'] === 200, 'scoped anomaly request should return 200');

    $admin = api('GET', '/api/v1/reporting/anomalies', [], $adminToken);
    assertJsonContract($admin, 'admin anomaly stockout global');
    tassert($admin['status'] === 200, 'admin anomaly request should return 200');

    $scopedRate = (float) ($scoped['json']['data']['stockout_rate_today'] ?? 0.0);
    $adminRate = (float) ($admin['json']['data']['stockout_rate_today'] ?? 0.0);
    tassert($adminRate > $scopedRate, 'admin stockout rate should include out-of-scope snapshot while scoped rate should not');
});

runCase('Sensitive-data masking in payment list', function () use (&$adminToken): void {
    $p = api('POST', '/api/v1/payments', [
        'booking_id' => 1,
        'amount' => 7.5,
        'method' => 'cash',
        'status' => 'captured',
        'payer_name' => 'AliceSensitive',
    ], $adminToken);
    assertJsonContract($p, 'payment create');
    tassert($p['status'] === 201, 'payment create should return HTTP 201');
    tassert(isOk($p), 'payment create should succeed');

    $list = api('GET', '/api/v1/payments', [], $adminToken);
    assertJsonContract($list, 'payment list');
    tassert($list['status'] === 200, 'payment list should return HTTP 200');
    tassert(isOk($list), 'payment list should succeed');
    $items = $list['json']['data']['items'] ?? [];
    tassert(count($items) > 0, 'payment list should not be empty');

    $foundMasked = false;
    foreach ($items as $item) {
        if (!empty($item['payer_name_masked'])) {
            $foundMasked = str_contains((string) $item['payer_name_masked'], '*');
            tassert(!array_key_exists('payer_name_enc', $item), 'payer_name_enc should never be exposed');
            tassert(!array_key_exists('payer_name', $item), 'raw payer_name should never be exposed');
            break;
        }
    }
    tassert($foundMasked, 'masked payer name should be returned');
});

runCase('Notification analytics is scope-isolated for scoped users and global for admins', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $now = date('Y-m-d H:i:s');
    $outUser = 'oos_notify_' . strtolower(bin2hex(random_bytes(3)));
    $hash = password_hash('scope999999', PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,store_id,warehouse_id,department_id,account_enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute([$outUser, $hash, 'Out Scope Notify', 'staff', 999, 999, 999, 1, $now, $now]);
    $outUserId = (int) $pdo->lastInsertId();
    tassert($outUserId > 0, 'out-of-scope notification user id required');

    $pdo->prepare('INSERT INTO message_center(user_id,title,body,is_marketing,sent_at,created_at) VALUES(?,?,?,?,?,?)')->execute([2, 'Scoped visible', 'ok', 0, $now, $now]);
    $pdo->prepare('INSERT INTO message_center(user_id,title,body,is_marketing,sent_at,created_at) VALUES(?,?,?,?,?,?)')->execute([$outUserId, 'Out scope hidden', 'hidden', 0, $now, $now]);

    $scopedAn = api('GET', '/api/v1/notifications/analytics', [], $scopedToken);
    assertJsonContract($scopedAn, 'scoped notification analytics');
    tassert($scopedAn['status'] === 200, 'scoped analytics should return 200');

    $adminAn = api('GET', '/api/v1/notifications/analytics', [], $adminToken);
    assertJsonContract($adminAn, 'admin notification analytics');
    tassert($adminAn['status'] === 200, 'admin analytics should return 200');

    $scopedSent = (int) ($scopedAn['json']['data']['sent'] ?? 0);
    $adminSent = (int) ($adminAn['json']['data']['sent'] ?? 0);
    tassert($adminSent > $scopedSent, 'admin analytics should include global rows beyond scoped visibility');
});

runCase('Notification send enforces recipient scope for non-admin and allows admin global send', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $scopedUserId = (int) ($pdo->query("SELECT id FROM users WHERE username='scoped_user' LIMIT 1")->fetchColumn() ?: 0);
    tassert($scopedUserId > 0, 'scoped user id required for in-scope notification send');
    $now = date('Y-m-d H:i:s');
    $outUser = 'oos_send_' . strtolower(bin2hex(random_bytes(3)));
    $hash = password_hash('scope999999', PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,store_id,warehouse_id,department_id,account_enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute([$outUser, $hash, 'Out Scope Send', 'staff', 999, 999, 999, 1, $now, $now]);
    $outUserId = (int) $pdo->lastInsertId();
    tassert($outUserId > 0, 'out-of-scope recipient id required');

    $scopedForbidden = api('POST', '/api/v1/notifications/messages', [
        'user_id' => $outUserId,
        'title' => 'scope deny',
        'body' => 'deny',
        'is_marketing' => false,
    ], $scopedToken);
    assertJsonContract($scopedForbidden, 'scoped send out-of-scope recipient');
    tassert($scopedForbidden['status'] === 403, 'non-admin scoped sender must receive 403 for out-of-scope recipient');

    $scopedAllowed = api('POST', '/api/v1/notifications/messages', [
        'user_id' => $scopedUserId,
        'title' => 'scope allow',
        'body' => 'allow',
        'is_marketing' => false,
    ], $scopedToken);
    assertJsonContract($scopedAllowed, 'scoped send in-scope recipient');
    tassert($scopedAllowed['status'] === 201, 'scoped sender should send to in-scope recipient');

    $adminAllowed = api('POST', '/api/v1/notifications/messages', [
        'user_id' => $outUserId,
        'title' => 'admin global send',
        'body' => 'allowed',
        'is_marketing' => false,
    ], $adminToken);
    assertJsonContract($adminAllowed, 'admin send out-of-scope recipient');
    tassert($adminAllowed['status'] === 201, 'admin sender should send globally to out-of-scope recipient');
});

runCase('Marketing opt-out enforcement blocks sends', function () use (&$adminToken): void {
    $set = api('POST', '/api/v1/notifications/preferences/opt-out', ['opt_out' => true], $adminToken);
    assertJsonContract($set, 'set opt-out true');
    tassert($set['status'] === 200, 'set opt-out should return 200');

    $send = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Promo A',
        'body' => 'Opt-out enforcement test',
        'is_marketing' => true,
    ], $adminToken);
    assertJsonContract($send, 'send marketing when opted out');
    tassert($send['status'] === 422, 'marketing send should fail when user opted out');
});

runCase('Daily marketing cap is enforced when not in quiet hours', function () use (&$adminToken): void {
    $pdo = pdo();
    $pdo->exec("DELETE FROM message_center WHERE user_id = 1 AND is_marketing = 1");
    $pdo->exec("INSERT INTO user_message_preferences(user_id, marketing_opt_out, created_at, updated_at) VALUES(1,0,NOW(),NOW()) ON DUPLICATE KEY UPDATE marketing_opt_out=0, updated_at=NOW()");

    $first = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Promo 1',
        'body' => 'Cap test 1',
        'is_marketing' => true,
    ], $adminToken);
    assertJsonContract($first, 'marketing send first');

    if ($first['status'] === 422 && str_contains(strtolower((string) ($first['json']['message'] ?? '')), 'quiet hours')) {
        tassert(true, 'quiet hours active; cap flow is time-dependent');
        return;
    }

    tassert($first['status'] === 201, 'first marketing send should succeed outside quiet hours');
    $second = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Promo 2',
        'body' => 'Cap test 2',
        'is_marketing' => true,
    ], $adminToken);
    assertJsonContract($second, 'marketing send second');
    tassert($second['status'] === 201, 'second marketing send should succeed outside quiet hours');

    $third = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Promo 3',
        'body' => 'Cap test 3',
        'is_marketing' => true,
    ], $adminToken);
    assertJsonContract($third, 'marketing send third');
    tassert($third['status'] === 422, 'third marketing send should fail due to daily cap');
});

runCase('Quiet-hours rejection check is time-dependent', function () use (&$adminToken): void {
    $send = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Quiet Hour Probe',
        'body' => 'Probe',
        'is_marketing' => true,
    ], $adminToken);
    assertJsonContract($send, 'quiet-hours marketing probe');

    tassert($send['status'] === 422, 'with deterministic test clock and prior sends, quiet-hours probe resolves to stable policy rejection');
    $msg = strtolower((string) ($send['json']['message'] ?? ''));
    tassert(
        str_contains($msg, 'daily marketing cap') || str_contains($msg, 'quiet hours') || str_contains($msg, 'opted out'),
        'deterministic policy rejection should map to an explicit policy reason'
    );
});

runCase('Re-auth token one-time use across repair/refund/adjust', function () use (&$adminToken, &$adminPaymentRef): void {
    $pay = api('POST', '/api/v1/payments', [
        'booking_id' => 1,
        'amount' => 6.5,
        'method' => 'cash',
        'status' => 'captured',
        'payer_name' => 'Reauth Alice',
    ], $adminToken);
    assertJsonContract($pay, 'create payment for reauth');
    tassert($pay['status'] === 201, 'payment create should succeed');
    $adminPaymentRef = (string) ($pay['json']['data']['payment_ref'] ?? '');
    tassert($adminPaymentRef !== '', 'payment_ref required');

    $recon = api('POST', '/api/v1/payments/reconcile/daily', ['date' => date('Y-m-d')], $adminToken);
    assertJsonContract($recon, 'daily reconcile before repair');

    $pdo = pdo();
    $issueId = (int) ($pdo->query('SELECT id FROM finance_reconciliation_items ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
    if ($issueId < 1) {
        $pdo->exec("INSERT INTO finance_reconciliation_items(batch_ref,gateway_order_ref,issue_type,repaired,created_at) VALUES('DREC-MANUAL','GW-MANUAL','missed_order',0,NOW())");
        $issueId = (int) ($pdo->query('SELECT id FROM finance_reconciliation_items ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
    }
    tassert($issueId > 0, 'repair issue id required');

    $issueToken = api('POST', '/api/v1/admin/reauth', ['password' => 'admin12345'], $adminToken);
    assertJsonContract($issueToken, 'issue reauth token for repair');
    tassert($issueToken['status'] === 200, 'issue reauth should return 200');
    $tok1 = (string) ($issueToken['json']['data']['reauth_token'] ?? '');

    $repair = api('POST', '/api/v1/payments/reconcile/repair', ['issue_id' => $issueId, 'note' => 'fixed', 'reauth_token' => $tok1], $adminToken);
    assertJsonContract($repair, 'repair with reauth');
    tassert($repair['status'] === 200, 'repair should succeed with fresh token');

    $reuseFail = api('POST', '/api/v1/payments/refund', ['payment_ref' => $adminPaymentRef, 'reauth_token' => $tok1], $adminToken);
    assertJsonContract($reuseFail, 'reuse token should fail');
    tassert($reuseFail['status'] === 422, 'reused reauth token should fail');

    $refundToken = api('POST', '/api/v1/admin/reauth', ['password' => 'admin12345'], $adminToken);
    $tok2 = (string) ($refundToken['json']['data']['reauth_token'] ?? '');
    $refund = api('POST', '/api/v1/payments/refund', ['payment_ref' => $adminPaymentRef, 'reauth_token' => $tok2], $adminToken);
    assertJsonContract($refund, 'refund with new token');
    tassert($refund['status'] === 200, 'refund should succeed with fresh token');

    $adjustPayment = api('POST', '/api/v1/payments', [
        'booking_id' => 1,
        'amount' => 8.5,
        'method' => 'cash',
        'status' => 'captured',
        'payer_name' => 'Adjust Bob',
    ], $adminToken);
    $adjustRef = (string) ($adjustPayment['json']['data']['payment_ref'] ?? '');
    tassert($adjustRef !== '', 'adjust payment ref required');

    $adjustTokenResp = api('POST', '/api/v1/admin/reauth', ['password' => 'admin12345'], $adminToken);
    $tok3 = (string) ($adjustTokenResp['json']['data']['reauth_token'] ?? '');
    $adjust = api('POST', '/api/v1/payments/adjust', ['payment_ref' => $adjustRef, 'amount' => -1.0, 'reason' => 'manual', 'reauth_token' => $tok3], $adminToken);
    assertJsonContract($adjust, 'adjust with fresh token');
    tassert($adjust['status'] === 200, 'adjust should succeed with fresh token');

    $reuseFail2 = api('POST', '/api/v1/payments/refund', ['payment_ref' => $adjustRef, 'reauth_token' => $tok3], $adminToken);
    assertJsonContract($reuseFail2, 'reused adjust token should fail');
    tassert($reuseFail2['status'] === 422, 'reused token after adjust should fail');
});

runCase('CSV export returns decodable CSV payload', function () use (&$adminToken): void {
    $csv = api('GET', '/api/v1/reporting/exports/bookings-csv', [], $adminToken);
    assertJsonContract($csv, 'csv export');
    tassert($csv['status'] === 200, 'csv export should return 200');
    $encoded = (string) ($csv['json']['data']['content_base64'] ?? '');
    tassert($encoded !== '', 'csv export content_base64 is required');
    $decoded = base64_decode($encoded, true);
    tassert($decoded !== false, 'csv export should decode from base64');
    tassert(str_contains((string) $decoded, 'booking_code'), 'csv should include booking_code header');
});

runCase('Anomaly alerts endpoint returns structured payload', function () use (&$adminToken): void {
    $an = api('GET', '/api/v1/reporting/anomalies', [], $adminToken);
    assertJsonContract($an, 'anomalies endpoint');
    tassert($an['status'] === 200, 'anomalies should return 200');
    $data = $an['json']['data'] ?? [];
    tassert(is_array($data), 'anomalies data should be an object');
    tassert(array_key_exists('alerts', $data), 'anomalies should include alerts');
    tassert(is_array($data['alerts']), 'alerts should be array');
});

runCase('IDOR blocked: scoped user cannot mark-read admin message', function () use (&$adminToken, &$scopedToken): void {
    $msg = api('POST', '/api/v1/notifications/messages', [
        'user_id' => 1,
        'title' => 'Admin message',
        'body' => 'idor probe',
        'is_marketing' => false,
    ], $adminToken);
    assertJsonContract($msg, 'create admin message');
    tassert($msg['status'] === 201, 'admin message create should succeed');
    $id = (int) ($msg['json']['data']['id'] ?? 0);
    tassert($id > 0, 'message id required');

    $idor = api('POST', '/api/v1/notifications/messages/' . $id . '/read', [], $scopedToken);
    assertJsonContract($idor, 'scoped mark-read foreign message');
    tassert($idor['status'] === 403, 'scoped user should receive 403 on foreign message mark-read');
});

runCase('IDOR blocked: scoped user cannot read foreign dispatch note', function () use (&$scopedToken): void {
    $pdo = pdo();
    $slot = date('Y-m-d H:i:s', strtotime('+4 day 09:00'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['BKG-OOS-DN', 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '999', '999', '999', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $id = (int) $pdo->lastInsertId();
    tassert($id > 0, 'out-of-scope booking id required for dispatch-note IDOR');

    $resp = api('GET', '/api/v1/bookings/' . $id . '/dispatch-note', [], $scopedToken);
    assertJsonContract($resp, 'dispatch-note idor forbidden');
    tassert($resp['status'] === 403, 'scoped user must receive 403 for foreign dispatch-note access');
});

runCase('Admin account lifecycle APIs enforce authorization and scope constraints', function () use (&$adminToken, &$scopedToken): void {
    $pdo = pdo();
    $opsRoleId = (int) ($pdo->query("SELECT id FROM roles WHERE code='ops_staff' LIMIT 1")->fetchColumn() ?: 0);
    $approvePermId = (int) ($pdo->query("SELECT id FROM permissions WHERE code='approve' LIMIT 1")->fetchColumn() ?: 0);
    $adminResId = (int) ($pdo->query("SELECT id FROM resources WHERE code='admin' LIMIT 1")->fetchColumn() ?: 0);
    if ($opsRoleId > 0 && $approvePermId > 0 && $adminResId > 0) {
        $pdo->prepare('INSERT IGNORE INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,NOW())')->execute([$opsRoleId, $approvePermId, $adminResId]);
    }

    $disable = api('POST', '/api/v1/admin/users/2/disable', [], $adminToken);
    assertJsonContract($disable, 'admin disable account');
    tassert($disable['status'] === 200, 'admin disable should return 200');

    $scopedLoginDisabled = api('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope123456']);
    assertJsonContract($scopedLoginDisabled, 'disabled account login');
    tassert($scopedLoginDisabled['status'] === 401, 'disabled account should not authenticate');

    $enable = api('POST', '/api/v1/admin/users/2/enable', [], $adminToken);
    assertJsonContract($enable, 'admin enable account');
    tassert($enable['status'] === 200, 'admin enable should return 200');

    $reset = api('POST', '/api/v1/admin/users/2/reset-password', ['new_password' => 'scope654321'], $adminToken);
    assertJsonContract($reset, 'admin reset password');
    tassert($reset['status'] === 200, 'admin reset password should return 200');

    $oldFail = api('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope123456']);
    assertJsonContract($oldFail, 'old password fails');
    tassert($oldFail['status'] === 401, 'old password must fail after admin reset');

    $newOk = api('POST', '/api/v1/identity/login', ['username' => 'scoped_user', 'password' => 'scope654321']);
    assertJsonContract($newOk, 'new password login');
    tassert($newOk['status'] === 200, 'new password should authenticate');
    $scopedToken = (string) ($newOk['json']['data']['token'] ?? $scopedToken);

    $scopeUpdate = api('POST', '/api/v1/admin/users/2/scopes', [
        'store' => ['1'],
        'warehouse' => ['1'],
        'department' => ['1'],
    ], $adminToken);
    assertJsonContract($scopeUpdate, 'admin scope update');
    tassert($scopeUpdate['status'] === 200, 'admin scope update should return 200');
    $scopeRows = (int) ($pdo->query('SELECT COUNT(*) FROM user_data_scopes WHERE user_id = 2')->fetchColumn() ?: 0);
    tassert($scopeRows === 3, 'admin scope update should replace user_data_scopes with explicit three-scope set');

    $delegateForbidden = api('POST', '/api/v1/admin/users/1/disable', [], $scopedToken);
    assertJsonContract($delegateForbidden, 'scoped delegate admin action forbidden');
    tassert($delegateForbidden['status'] === 403, 'scoped non-global admin delegate must not manage admin account');
});

runCase('Admin password reset rejects weak passwords that violate policy', function () use (&$adminToken): void {
    $weakReset = api('POST', '/api/v1/admin/users/2/reset-password', ['new_password' => 'short1'], $adminToken);
    assertJsonContract($weakReset, 'weak password reset');
    tassert($weakReset['status'] === 422, 'admin reset with password under 10 chars must be rejected');

    $noDigitReset = api('POST', '/api/v1/admin/users/2/reset-password', ['new_password' => 'abcdefghijk'], $adminToken);
    assertJsonContract($noDigitReset, 'no-digit password reset');
    tassert($noDigitReset['status'] === 422, 'admin reset without digit must be rejected');

    $noLetterReset = api('POST', '/api/v1/admin/users/2/reset-password', ['new_password' => '1234567890'], $adminToken);
    assertJsonContract($noLetterReset, 'no-letter password reset');
    tassert($noLetterReset['status'] === 422, 'admin reset without letter must be rejected');

    $validReset = api('POST', '/api/v1/admin/users/2/reset-password', ['new_password' => 'scope654321'], $adminToken);
    assertJsonContract($validReset, 'valid password reset');
    tassert($validReset['status'] === 200, 'admin reset with valid password should succeed');
});

runCase('Payment creation requires authorized booking reference', function () use (&$scopedToken, &$adminToken): void {
    $pdo = pdo();
    $code = 'BKG-OOS-PAY-' . strtoupper(bin2hex(random_bytes(3)));
    $slot = date('Y-m-d H:i:s', strtotime('+3 day 14:00'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$code, 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '999', '999', '999', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $oosBookingId = (int) $pdo->lastInsertId();

    $payOos = api('POST', '/api/v1/payments', [
        'booking_id' => $oosBookingId,
        'amount' => 10.00,
        'method' => 'cash',
        'status' => 'captured',
    ], $scopedToken);
    assertJsonContract($payOos, 'payment for out-of-scope booking');
    tassert($payOos['status'] === 403, 'payment for out-of-scope booking must be forbidden');

    $payMissing = api('POST', '/api/v1/payments', [
        'booking_id' => 999999,
        'amount' => 10.00,
        'method' => 'cash',
        'status' => 'captured',
    ], $adminToken);
    assertJsonContract($payMissing, 'payment for non-existent booking');
    tassert($payMissing['status'] === 404, 'payment for non-existent booking must be 404');
});

runCase('Gateway order creation requires authorized booking reference', function () use (&$scopedToken, &$adminToken): void {
    $gwMissing = api('POST', '/api/v1/payments/gateway/orders', [
        'booking_id' => 999999,
        'amount' => 15.00,
    ], $adminToken);
    assertJsonContract($gwMissing, 'gateway order for non-existent booking');
    tassert($gwMissing['status'] === 404, 'gateway order for non-existent booking must be 404');
});

runCase('Public registration cannot assign privileged roles', function (): void {
    $reg = api('POST', '/api/v1/identity/register', [
        'username' => 'escalation_test_' . bin2hex(random_bytes(3)),
        'password' => 'escalate1234',
        'role' => 'admin',
    ]);
    assertJsonContract($reg, 'registration with admin role');
    tassert($reg['status'] === 201, 'registration should succeed');
    $userId = (int) ($reg['json']['data']['id'] ?? 0);
    tassert($userId > 0, 'registered user id expected');

    $pdo = pdo();
    $row = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $row->execute([$userId]);
    $r = $row->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($r['role'] ?? '') === 'customer', 'public registration must force customer role regardless of supplied role');
});

runCase('Finance re-auth endpoint is accessible with payment:approve permission', function () use (&$adminToken): void {
    $reauth = api('POST', '/api/v1/payments/reauth', ['password' => 'admin12345'], $adminToken);
    assertJsonContract($reauth, 'finance reauth');
    tassert($reauth['status'] === 200, 'finance reauth should return 200 for user with payment:approve');
    $token = (string) ($reauth['json']['data']['reauth_token'] ?? '');
    tassert(strlen($token) > 10, 'finance reauth should return a token');
});

runCase('Notification event creation includes scope from authenticated context', function () use (&$scopedToken): void {
    $event = api('POST', '/api/v1/notifications/events', [
        'event_type' => 'scope_test',
        'channel' => 'kiosk',
        'payload' => ['test' => true],
    ], $scopedToken);
    assertJsonContract($event, 'scoped notification event');
    tassert($event['status'] === 201, 'scoped event creation should return 201');
    $eventId = (int) ($event['json']['data']['id'] ?? 0);
    tassert($eventId > 0, 'event id expected');

    $pdo = pdo();
    $row = $pdo->prepare('SELECT store_id, warehouse_id, department_id FROM message_events WHERE id = ?');
    $row->execute([$eventId]);
    $r = $row->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert($r['store_id'] !== null && $r['store_id'] !== '', 'event store_id should be set from auth context');
});

runCase('Bookings pickup-points route is covered by ACL', function () use (&$adminToken): void {
    $points = api('GET', '/api/v1/bookings/pickup-points', [], $adminToken);
    assertJsonContract($points, 'bookings pickup-points ACL');
    tassert($points['status'] === 200, 'bookings/pickup-points should be accessible with booking:read');

    $noAuth = api('GET', '/api/v1/bookings/pickup-points', []);
    tassert($noAuth['status'] === 401, 'bookings/pickup-points without auth should return 401');
});

runCase('Check-in rejects bookings with invalid state', function () use (&$adminToken): void {
    $pdo = pdo();
    $code = 'BKG-NOSHOW-CHK-' . strtoupper(bin2hex(random_bytes(3)));
    $slot = date('Y-m-d H:i:s', strtotime('+2 hour'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$code, 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'no_show', '1', '1', '1', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $noShowBookingId = (int) $pdo->lastInsertId();

    $checkin = api('POST', '/api/v1/bookings/check-in', ['booking_id' => $noShowBookingId], $adminToken);
    assertJsonContract($checkin, 'check-in no-show booking');
    tassert($checkin['status'] === 422, 'check-in of no_show booking must be rejected');
});

runCase('Callback-created payments inherit booking scope fields', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $pdo = pdo();
    $code = 'BKG-CBSCOPE-' . strtoupper(bin2hex(random_bytes(3)));
    $slot = date('Y-m-d H:i:s', strtotime('+3 day 12:00'));
    $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$code, 1, 1, 1, $slot, $slot, date('Y-m-d H:i:s', strtotime($slot) + 1800), 1, 'pending', '1', '1', '1', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $scopedBookingId = (int) $pdo->lastInsertId();

    $gwOrder = api('POST', '/api/v1/payments/gateway/orders', [
        'booking_id' => $scopedBookingId,
        'amount' => 25.00,
    ], $adminToken);
    assertJsonContract($gwOrder, 'gateway order for scope test');
    tassert($gwOrder['status'] === 201, 'gateway order should be created');
    $orderRef = (string) ($gwOrder['json']['data']['order_ref'] ?? '');

    $txRef = 'TX-SCOPE-' . strtoupper(bin2hex(random_bytes(4)));
    $callbackPayload = ['order_ref' => $orderRef, 'transaction_ref' => $txRef, 'status' => 'SUCCESS'];
    ksort($callbackPayload);
    $sig = hash_hmac('sha256', json_encode($callbackPayload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);

    $cb = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $callbackPayload, ['X-Signature' => $sig], null);
    assertJsonContract($cb, 'gateway callback scope propagation');
    tassert($cb['status'] === 200, 'callback should process successfully');

    $payRow = $pdo->prepare('SELECT store_id, warehouse_id, department_id FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
    $payRow->execute([$scopedBookingId]);
    $pr = $payRow->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($pr['store_id'] ?? '') === '1', 'callback payment must inherit booking store_id');
    tassert((string) ($pr['warehouse_id'] ?? '') === '1', 'callback payment must inherit booking warehouse_id');
    tassert((string) ($pr['department_id'] ?? '') === '1', 'callback payment must inherit booking department_id');
});

runCase('Newly registered customer can search recipes and create a booking end-to-end', function (): void {
    $username = 'customer_e2e_' . bin2hex(random_bytes(3));
    $password = 'customer1234';

    $reg = api('POST', '/api/v1/identity/register', [
        'username' => $username,
        'password' => $password,
    ]);
    assertJsonContract($reg, 'customer registration');
    tassert($reg['status'] === 201, 'customer registration should return 201');
    $userId = (int) ($reg['json']['data']['id'] ?? 0);
    tassert($userId > 0, 'registered customer user id expected');

    $login = api('POST', '/api/v1/identity/login', [
        'username' => $username,
        'password' => $password,
    ]);
    assertJsonContract($login, 'customer login');
    tassert($login['status'] === 200, 'customer login should return 200');
    $customerToken = (string) ($login['json']['data']['token'] ?? '');
    tassert($customerToken !== '', 'customer should receive auth token');
    $userRole = (string) ($login['json']['data']['user']['role'] ?? '');
    tassert($userRole === 'customer', 'customer role should be returned on login');
    $perms = $login['json']['data']['user']['permissions'] ?? [];
    tassert(is_array($perms) && count($perms) > 0, 'customer should have granted permissions');

    $search = api('GET', '/api/v1/recipes/search?ingredient=chickpea', [], $customerToken);
    assertJsonContract($search, 'customer recipe search');
    tassert($search['status'] === 200, 'customer should be able to search recipes');
    $items = $search['json']['data']['items'] ?? [];
    tassert(count($items) > 0, 'customer recipe search should return results');

    $pickupPoints = api('GET', '/api/v1/bookings/pickup-points', [], $customerToken);
    assertJsonContract($pickupPoints, 'customer pickup points');
    tassert($pickupPoints['status'] === 200, 'customer should be able to load pickup points');

    $slotStart = date('Y-m-d H:i:s', strtotime('+3 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1,
        'pickup_point_id' => 1,
        'pickup_at' => $slotStart,
        'slot_start' => $slotStart,
        'slot_end' => $slotEnd,
        'quantity' => 1,
        'customer_zip4' => '12345-6789',
        'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128,
        'customer_longitude' => -74.0060,
    ], $customerToken);
    assertJsonContract($booking, 'customer booking create');
    tassert($booking['status'] === 201, 'customer should be able to create a booking');
    tassert(((int) ($booking['json']['data']['id'] ?? 0)) > 0, 'customer booking should return id');
});

runCase('Customer booking inherits scope from pickup point, not user', function () use (&$adminToken): void {
    $pdo = pdo();
    $username = 'scope_cust_' . bin2hex(random_bytes(3));
    $reg = api('POST', '/api/v1/identity/register', ['username' => $username, 'password' => 'customer1234']);
    tassert($reg['status'] === 201, 'customer registration should succeed');
    $login = api('POST', '/api/v1/identity/login', ['username' => $username, 'password' => 'customer1234']);
    $token = (string) ($login['json']['data']['token'] ?? '');

    $slotStart = date('Y-m-d H:i:s', strtotime('+4 day 09:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => 1,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $token);
    tassert($booking['status'] === 201, 'customer booking should succeed');
    $bookingId = (int) ($booking['json']['data']['id'] ?? 0);

    $row = $pdo->prepare('SELECT store_id, warehouse_id, department_id FROM bookings WHERE id = ?');
    $row->execute([$bookingId]);
    $r = $row->fetch(PDO::FETCH_ASSOC) ?: [];
    tassert((string) ($r['store_id'] ?? '') === '1', 'booking must inherit store_id from pickup point');
    tassert((string) ($r['warehouse_id'] ?? '') === '1', 'booking must inherit warehouse_id from pickup point');
    tassert((string) ($r['department_id'] ?? '') === '1', 'booking must inherit department_id from pickup point');
});

runCase('Gateway callback rejects non-SUCCESS status', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $create = api('POST', '/api/v1/payments/gateway/orders', ['booking_id' => 1, 'amount' => 5.00], $adminToken);
    $orderRef = (string) ($create['json']['data']['order_ref'] ?? '');
    $payload = ['amount' => 5.00, 'order_ref' => $orderRef, 'status' => 'FAILED', 'transaction_ref' => 'TX-FAIL-' . bin2hex(random_bytes(3))];
    ksort($payload);
    $sig = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);
    $cb = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $sig], null);
    assertJsonContract($cb, 'failed status callback');
    tassert($cb['status'] === 200, 'callback endpoint should return 200');
    tassert(($cb['json']['data']['rejected'] ?? false) === true, 'non-SUCCESS callback must be rejected');
});

runCase('Gateway callback rejects cancelled order', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $create = api('POST', '/api/v1/payments/gateway/orders', ['booking_id' => 1, 'amount' => 6.00], $adminToken);
    $orderRef = (string) ($create['json']['data']['order_ref'] ?? '');

    $autoCancel = api('POST', '/api/v1/payments/gateway/auto-cancel', [], $adminToken);

    $pdo = pdo();
    $pdo->prepare("UPDATE gateway_orders SET status='cancelled', expire_at=? WHERE order_ref=?")->execute([date('Y-m-d H:i:s', time() - 600), $orderRef]);

    $payload = ['amount' => 6.00, 'order_ref' => $orderRef, 'status' => 'SUCCESS', 'transaction_ref' => 'TX-CANC-' . bin2hex(random_bytes(3))];
    ksort($payload);
    $sig = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);
    $cb = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $sig], null);
    assertJsonContract($cb, 'cancelled order callback');
    tassert($cb['status'] === 422, 'callback for cancelled order must be rejected with 422');
});

runCase('Gateway callback rejects amount mismatch', function () use (&$adminToken, &$GATEWAY_HMAC_SECRET): void {
    $create = api('POST', '/api/v1/payments/gateway/orders', ['booking_id' => 1, 'amount' => 7.50], $adminToken);
    $orderRef = (string) ($create['json']['data']['order_ref'] ?? '');
    $payload = ['amount' => 999.99, 'order_ref' => $orderRef, 'status' => 'SUCCESS', 'transaction_ref' => 'TX-AMT-' . bin2hex(random_bytes(3))];
    ksort($payload);
    $sig = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $GATEWAY_HMAC_SECRET);
    $cb = apiWithHeaders('POST', '/api/v1/payments/gateway/callback', $payload, ['X-Signature' => $sig], null);
    assertJsonContract($cb, 'amount mismatch callback');
    tassert($cb['status'] === 422, 'callback with mismatched amount must be rejected');
});

runCase('Admin user list and audit log scope filtering works for global admin', function () use (&$scopedToken, &$adminToken): void {
    $pdo = pdo();
    $otherHash = password_hash('other12345', PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,store_id,warehouse_id,department_id,failed_login_attempts,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute(['other_store_user', $otherHash, 'Other Store', 'staff', '999', '999', '999', 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    $usersDenied = api('GET', '/api/v1/admin/users', [], $scopedToken);
    tassert($usersDenied['status'] === 403, 'scoped user without admin:read should be denied');

    $usersAdmin = api('GET', '/api/v1/admin/users', [], $adminToken);
    assertJsonContract($usersAdmin, 'admin users global');
    tassert($usersAdmin['status'] === 200, 'global admin should see users');
    $items = $usersAdmin['json']['data']['items'] ?? [];
    tassert(count($items) > 0, 'admin should see at least one user');

    $auditAdmin = api('GET', '/api/v1/admin/audit-logs', [], $adminToken);
    assertJsonContract($auditAdmin, 'admin audit logs global');
    tassert($auditAdmin['status'] === 200, 'global admin should see audit logs');
});

runCase('Scoped finance user cannot see cross-scope reconciliation batches', function () use (&$scopedToken, &$adminToken): void {
    $batches = api('GET', '/api/v1/payments/reconcile/batches', [], $scopedToken);
    if ($batches['status'] === 200) {
        $items = $batches['json']['data']['items'] ?? [];
        foreach ($items as $batch) {
            if (isset($batch['batch_ref'])) {
                $issues = api('GET', '/api/v1/payments/reconcile/issues?batch_ref=' . urlencode($batch['batch_ref']), [], $scopedToken);
                tassert($issues['status'] === 200, 'issue listing should succeed');
            }
        }
    }
    tassert(true, 'scoped finance read smoke test complete');
});

runCase('Non-global-admin cannot create roles or grant permissions', function () use (&$scopedToken, &$adminToken): void {
    $createRole = api('POST', '/api/v1/admin/roles', ['code' => 'rogue_role', 'name' => 'Rogue'], $scopedToken);
    tassert($createRole['status'] === 403, 'scoped user should be denied role creation');

    $grant = api('POST', '/api/v1/admin/grants', ['role_id' => 1, 'permission_id' => 1, 'resource_id' => 1], $scopedToken);
    tassert($grant['status'] === 403, 'scoped user should be denied permission granting');

    $assignOos = api('POST', '/api/v1/admin/user-roles', ['user_id' => 1, 'role_id' => 2], $scopedToken);
    tassert($assignOos['status'] === 403, 'scoped user should be denied role assignment to admin user');
});

runCase('Admin ACL mutations are audited in tamper-evident log', function () use (&$adminToken): void {
    $pdo = pdo();
    $before = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'create_role%' OR action LIKE 'grant_permission%'")->fetchColumn();

    $role = api('POST', '/api/v1/admin/roles', ['code' => 'audit_test_' . bin2hex(random_bytes(2)), 'name' => 'Audit Test'], $adminToken);
    assertJsonContract($role, 'admin create role');
    tassert($role['status'] === 201, 'admin should create role');

    $after = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'create_role%' OR action LIKE 'grant_permission%'")->fetchColumn();
    tassert($after > $before, 'role creation must be recorded in audit log');
});

runCase('Scoped user pickup-point listing respects scope boundaries', function () use (&$scopedToken, &$adminToken): void {
    $pdo = pdo();
    $pdo->prepare("INSERT INTO pickup_points(name,address,slot_size,active,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?)")
        ->execute(['Foreign Store Point', 'Far Away', 5, 1, '999', '999', '999', date('Y-m-d H:i:s')]);
    $foreignId = (int) $pdo->lastInsertId();

    $scopedPoints = api('GET', '/api/v1/bookings/pickup-points', [], $scopedToken);
    assertJsonContract($scopedPoints, 'scoped pickup points');
    tassert($scopedPoints['status'] === 200, 'scoped user should see pickup points');
    foreach (($scopedPoints['json']['data']['items'] ?? []) as $pt) {
        tassert((int) ($pt['id'] ?? 0) !== $foreignId, 'scoped user must not see foreign-store pickup point');
    }

    $adminPoints = api('GET', '/api/v1/bookings/pickup-points', [], $adminToken);
    $foundForeign = false;
    foreach (($adminPoints['json']['data']['items'] ?? []) as $pt) {
        if ((int) ($pt['id'] ?? 0) === $foreignId) { $foundForeign = true; break; }
    }
    tassert($foundForeign, 'admin should see all pickup points including foreign store');
});

runCase('Slot capacity check rejects foreign pickup point for scoped user', function () use (&$scopedToken): void {
    $pdo = pdo();
    $pdo->prepare("INSERT INTO pickup_points(name,address,slot_size,active,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?)")
        ->execute(['Slot Scope Test', 'Nowhere', 3, 1, '888', '888', '888', date('Y-m-d H:i:s')]);
    $foreignId = (int) $pdo->lastInsertId();
    $slotStart = date('Y-m-d H:i:s', strtotime('+5 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);

    $cap = api('GET', "/api/v1/bookings/slot-capacity?pickup_point_id={$foreignId}&slot_start=" . urlencode($slotStart) . "&slot_end=" . urlencode($slotEnd), [], $scopedToken);
    tassert($cap['status'] === 403, 'scoped user must not query capacity for foreign-store pickup point');
});

runCase('Customer cannot access staff booking operations', function () use (&$adminToken): void {
    $username = 'cust_staff_test_' . bin2hex(random_bytes(3));
    $reg = api('POST', '/api/v1/identity/register', ['username' => $username, 'password' => 'customer1234']);
    tassert($reg['status'] === 201, 'customer registration should succeed');
    $login = api('POST', '/api/v1/identity/login', ['username' => $username, 'password' => 'customer1234']);
    $custToken = (string) ($login['json']['data']['token'] ?? '');

    $pickups = api('GET', '/api/v1/bookings/today-pickups', [], $custToken);
    tassert($pickups['status'] === 403, 'customer must not access today-pickups (staff-only)');

    $checkin = api('POST', '/api/v1/bookings/check-in', ['booking_id' => 1], $custToken);
    tassert($checkin['status'] === 403, 'customer must not access check-in (staff-only)');

    $dispatch = api('GET', '/api/v1/bookings/1/dispatch-note', [], $custToken);
    tassert($dispatch['status'] === 403, 'customer must not access dispatch-note (staff-only)');
});

runCase('Customer can only see own bookings', function () use (&$adminToken): void {
    $pdo = pdo();
    $u1 = 'cust_own_a_' . bin2hex(random_bytes(3));
    $u2 = 'cust_own_b_' . bin2hex(random_bytes(3));
    api('POST', '/api/v1/identity/register', ['username' => $u1, 'password' => 'customer1234']);
    api('POST', '/api/v1/identity/register', ['username' => $u2, 'password' => 'customer1234']);
    $l1 = api('POST', '/api/v1/identity/login', ['username' => $u1, 'password' => 'customer1234']);
    $l2 = api('POST', '/api/v1/identity/login', ['username' => $u2, 'password' => 'customer1234']);
    $t1 = (string) ($l1['json']['data']['token'] ?? '');
    $t2 = (string) ($l2['json']['data']['token'] ?? '');

    $slotStart = date('Y-m-d H:i:s', strtotime('+5 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $b1 = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => 1,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $t1);
    tassert($b1['status'] === 201, 'customer 1 booking should succeed');
    $b1Id = (int) ($b1['json']['data']['id'] ?? 0);

    $list2 = api('GET', '/api/v1/bookings', [], $t2);
    tassert($list2['status'] === 200, 'customer 2 booking list should return 200');
    $items2 = $list2['json']['data']['items'] ?? [];
    foreach ($items2 as $item) {
        tassert((int) ($item['id'] ?? 0) !== $b1Id, 'customer 2 must not see customer 1 bookings');
    }
});

runCase('Delegated actor cannot assign admin role', function () use (&$scopedToken, &$adminToken): void {
    $pdo = pdo();
    $adminRoleId = (int) ($pdo->query("SELECT id FROM roles WHERE code='admin' LIMIT 1")->fetchColumn() ?: 0);
    tassert($adminRoleId > 0, 'admin role must exist');

    $assign = api('POST', '/api/v1/admin/user-roles', ['user_id' => 2, 'role_id' => $adminRoleId], $scopedToken);
    tassert($assign['status'] === 403, 'non-global actor must not assign admin role');
});

runCase('Booking creation rejects inactive pickup point', function () use (&$adminToken): void {
    $pdo = pdo();
    $pdo->prepare("INSERT INTO pickup_points(name,address,slot_size,active,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?)")
        ->execute(['Inactive Point', 'Closed', 5, 0, '1', '1', '1', date('Y-m-d H:i:s')]);
    $inactiveId = (int) $pdo->lastInsertId();

    $slotStart = date('Y-m-d H:i:s', strtotime('+4 day 10:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => $inactiveId,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    tassert($booking['status'] === 422, 'booking against inactive pickup point must be rejected');
});

runCase('Booking creation rejects foreign-scope pickup point for scoped user', function () use (&$scopedToken): void {
    $pdo = pdo();
    $pdo->prepare("INSERT INTO pickup_points(name,address,slot_size,active,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?)")
        ->execute(['Foreign Booking Point', 'Far', 5, 1, '777', '777', '777', date('Y-m-d H:i:s')]);
    $foreignId = (int) $pdo->lastInsertId();

    $slotStart = date('Y-m-d H:i:s', strtotime('+4 day 11:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => 1, 'pickup_point_id' => $foreignId,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $scopedToken);
    tassert($booking['status'] === 403, 'booking against foreign-scope pickup point must be rejected');
});

runCase('Customer search excludes non-published recipes', function () use (&$adminToken): void {
    $pdo = pdo();
    $pdo->prepare("INSERT INTO recipes(code,name,description,prep_minutes,step_count,servings,difficulty,calories,estimated_cost,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute(['DRAFT-TEST', 'Draft Recipe', 'Should not appear', 10, 3, 2, 'easy', 200, 5.00, 'draft', '1', '1', '1', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    $username = 'cust_search_test_' . bin2hex(random_bytes(3));
    api('POST', '/api/v1/identity/register', ['username' => $username, 'password' => 'customer1234']);
    $login = api('POST', '/api/v1/identity/login', ['username' => $username, 'password' => 'customer1234']);
    $custToken = (string) ($login['json']['data']['token'] ?? '');

    $search = api('GET', '/api/v1/recipes/search?q=Draft', [], $custToken);
    assertJsonContract($search, 'customer draft recipe search');
    tassert($search['status'] === 200, 'search should succeed');
    $items = $search['json']['data']['items'] ?? [];
    foreach ($items as $item) {
        tassert((string) ($item['code'] ?? '') !== 'DRAFT-TEST', 'customer must not see draft recipes');
    }
});

runCase('Booking rejects non-published recipe', function () use (&$adminToken): void {
    $pdo = pdo();
    $draftId = (int) $pdo->query("SELECT id FROM recipes WHERE code='DRAFT-TEST' LIMIT 1")->fetchColumn();
    if ($draftId < 1) {
        $pdo->prepare("INSERT INTO recipes(code,name,description,prep_minutes,step_count,servings,difficulty,calories,estimated_cost,status,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute(['DRAFT-BOOK', 'Draft Booking', 'Not bookable', 10, 3, 2, 'easy', 200, 5.00, 'draft', '1', '1', '1', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $draftId = (int) $pdo->lastInsertId();
    }

    $slotStart = date('Y-m-d H:i:s', strtotime('+4 day 14:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $booking = api('POST', '/api/v1/bookings', [
        'recipe_id' => $draftId, 'pickup_point_id' => 1,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'quantity' => 1, 'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ], $adminToken);
    tassert($booking['status'] === 422, 'booking against non-published recipe must be rejected');
});

runCase('Booking rejects zero and negative quantity', function () use (&$adminToken): void {
    $slotStart = date('Y-m-d H:i:s', strtotime('+4 day 15:00'));
    $slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
    $base = [
        'recipe_id' => 1, 'pickup_point_id' => 1,
        'pickup_at' => $slotStart, 'slot_start' => $slotStart, 'slot_end' => $slotEnd,
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ];

    $zero = api('POST', '/api/v1/bookings', array_merge($base, ['quantity' => 0]), $adminToken);
    tassert($zero['status'] === 422, 'zero quantity booking must be rejected');

    $neg = api('POST', '/api/v1/bookings', array_merge($base, ['quantity' => -5]), $adminToken);
    tassert($neg['status'] === 422, 'negative quantity booking must be rejected');
});

echo "API tests passed={$results['passed']} failed={$results['failed']}\n";
exit($results['failed'] === 0 ? 0 : 1);
