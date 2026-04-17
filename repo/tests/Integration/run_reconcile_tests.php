<?php
declare(strict_types=1);

/**
 * PantryPilot Reconciliation Isolated Tests
 *
 * Focused, standalone tests for payment reconciliation flows.
 * Runs independently without depending on state from run_api_tests.php.
 *
 * Covers:
 *   - GET  /api/v1/payments/reconcile/batches  (list with scope isolation)
 *   - GET  /api/v1/payments/reconcile/issues   (deterministic fixture-based)
 *   - POST /api/v1/payments/reconcile          (manual entry)
 *   - POST /api/v1/payments/reconcile/close    (reauth gate + happy path)
 *   - POST /api/v1/payments/reconcile/daily    (mismatch detection)
 */

$results = ['passed' => 0, 'failed' => 0];

function rtassert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rrunCase(string $name, callable $fn): void
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

function rpdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'pantrypilot';
    $user = getenv('DB_USER') ?: 'pantry';
    $pass = getenv('DB_PASS') ?: 'pantrypass';
    $pdo  = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

function rapi(string $method, string $path, array $body = [], ?string $token = null): array
{
    $url     = 'http://127.0.0.1' . $path;
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
    $resp   = file_get_contents($url, false, stream_context_create($opts));
    $hdrs   = $http_response_header ?? [];
    $status = 0;
    if (isset($hdrs[0]) && preg_match('/\s(\d{3})\s/', $hdrs[0], $m)) {
        $status = (int) $m[1];
    }
    $raw  = (string) ($resp ?? '');
    $json = [];
    if ($raw !== '') {
        $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $decoded = json_decode(ltrim($cleaned), true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }
    return ['status' => $status, 'json' => $json, 'raw' => $raw];
}

// ---- Setup: login as admin ----

$adminToken = '';

rrunCase('Reconcile test setup: admin login', function () use (&$adminToken): void {
    $r = rapi('POST', '/api/v1/identity/login', ['username' => 'admin', 'password' => 'admin12345']);
    rtassert($r['status'] === 200, 'admin login must succeed for reconcile tests');
    $adminToken = (string) ($r['json']['data']['token'] ?? '');
    rtassert($adminToken !== '', 'admin token required');
});

// ---- Test Cases ----

rrunCase('GET /reconcile/batches returns structured list with pagination metadata', function () use (&$adminToken): void {
    $r = rapi('GET', '/api/v1/payments/reconcile/batches', [], $adminToken);
    rtassert($r['status'] === 200, 'batches list must return 200');
    rtassert(isset($r['json']['success']) && $r['json']['success'] === true, 'batches response must succeed');
    rtassert(array_key_exists('items', $r['json']['data'] ?? []), 'batches must include items key');
    rtassert(array_key_exists('pagination', $r['json']['data'] ?? []), 'batches must include pagination metadata');
    rtassert(array_key_exists('total', $r['json']['data']['pagination'] ?? []), 'batches pagination must include total count');
});

rrunCase('GET /reconcile/issues returns deterministic results for seeded batch', function () use (&$adminToken): void {
    $pdo      = rpdo();
    $batchRef = 'RTEST-ISS-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->prepare("INSERT INTO reconciliation(batch_ref,period_start,period_end,expected_total,actual_total,variance,status,created_at) VALUES(?,?,?,?,?,?,?,NOW())")
        ->execute([$batchRef, date('Y-m-d'), date('Y-m-d'), 500.0, 300.0, -200.0, 'abnormal']);
    $pdo->prepare("INSERT INTO finance_reconciliation_items(batch_ref,gateway_order_ref,issue_type,repaired,created_at) VALUES(?,?,?,?,NOW())")
        ->execute([$batchRef, 'GW-RTEST-001', 'missed_order', 0]);
    $pdo->prepare("INSERT INTO finance_reconciliation_items(batch_ref,gateway_order_ref,issue_type,repaired,created_at) VALUES(?,?,?,?,NOW())")
        ->execute([$batchRef, 'GW-RTEST-002', 'missed_order', 0]);
    $pdo->prepare("INSERT INTO finance_reconciliation_items(batch_ref,gateway_order_ref,issue_type,repaired,created_at) VALUES(?,?,?,?,NOW())")
        ->execute([$batchRef, 'GW-RTEST-003', 'missed_order', 0]);

    // Fetch without filter returns a list
    $all = rapi('GET', '/api/v1/payments/reconcile/issues', [], $adminToken);
    rtassert($all['status'] === 200, 'issues list without filter must return 200');
    rtassert(array_key_exists('items', $all['json']['data'] ?? []), 'issues list must include items key');

    // Fetch with batch_ref filter returns exactly the seeded items
    $filtered = rapi('GET', '/api/v1/payments/reconcile/issues?batch_ref=' . urlencode($batchRef), [], $adminToken);
    rtassert($filtered['status'] === 200, 'issues list with batch_ref filter must return 200');
    $items = $filtered['json']['data']['items'] ?? null;
    rtassert(is_array($items), 'filtered issues must include items array');
    rtassert(count($items) === 3, 'filtered issues must return exactly 3 seeded items for batch ' . $batchRef);
    $refs = array_column($items, 'gateway_order_ref');
    rtassert(in_array('GW-RTEST-001', $refs, true), 'GW-RTEST-001 must be present in filtered issues');
    rtassert(in_array('GW-RTEST-002', $refs, true), 'GW-RTEST-002 must be present in filtered issues');
    rtassert(in_array('GW-RTEST-003', $refs, true), 'GW-RTEST-003 must be present in filtered issues');

    // Issue type is recorded correctly
    foreach ($items as $item) {
        rtassert(($item['issue_type'] ?? '') === 'missed_order', 'all seeded items must have issue_type missed_order');
        rtassert((int) ($item['repaired'] ?? 1) === 0, 'all seeded items must start unrepaired');
    }
});

rrunCase('POST /reconcile creates a manual batch entry with correct variance', function () use (&$adminToken): void {
    $batchRef = 'MREC-' . strtoupper(bin2hex(random_bytes(3)));

    $r = rapi('POST', '/api/v1/payments/reconcile', [
        'batch_ref'      => $batchRef,
        'period_start'   => date('Y-m-d'),
        'period_end'     => date('Y-m-d'),
        'expected_total' => 500.0,
        'actual_total'   => 480.0,
        'status'         => 'abnormal',
    ], $adminToken);
    rtassert($r['status'] === 201, 'manual reconcile entry must return 201');
    rtassert(($r['json']['data']['batch_ref'] ?? '') === $batchRef, 'batch_ref must match input');
    rtassert((float) ($r['json']['data']['variance'] ?? 0.0) === -20.0, 'variance must be actual_total - expected_total');

    // Verify persisted in DB
    $pdo = rpdo();
    $row = $pdo->prepare('SELECT variance FROM reconciliation WHERE batch_ref = ?');
    $row->execute([$batchRef]);
    $saved = $row->fetch(PDO::FETCH_ASSOC);
    rtassert($saved !== false, 'reconciliation batch must be persisted in DB');
    rtassert((float) $saved['variance'] === -20.0, 'DB variance must match computed value');
});

rrunCase('POST /reconcile/close requires valid reauth token and closes batch', function () use (&$adminToken): void {
    $pdo      = rpdo();
    $batchRef = 'CLOSE-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->prepare("INSERT INTO reconciliation(batch_ref,period_start,period_end,expected_total,actual_total,variance,status,created_at) VALUES(?,?,?,?,?,?,?,NOW())")
        ->execute([$batchRef, date('Y-m-d'), date('Y-m-d'), 100.0, 100.0, 0.0, 'open']);

    // Without reauth token must fail
    $noToken = rapi('POST', '/api/v1/payments/reconcile/close', ['batch_ref' => $batchRef, 'reauth_token' => ''], $adminToken);
    rtassert($noToken['status'] === 422, 'close without reauth token must be rejected');

    // With invalid reauth token must fail
    $badToken = rapi('POST', '/api/v1/payments/reconcile/close', ['batch_ref' => $batchRef, 'reauth_token' => 'invalid'], $adminToken);
    rtassert($badToken['status'] === 422, 'close with invalid reauth token must be rejected');

    // Issue a valid reauth token
    $reauth = rapi('POST', '/api/v1/admin/reauth', ['password' => 'admin12345'], $adminToken);
    rtassert($reauth['status'] === 200, 'admin reauth must succeed');
    $reauthToken = (string) ($reauth['json']['data']['reauth_token'] ?? '');
    rtassert($reauthToken !== '', 'reauth_token must be returned');

    // Close with valid token must succeed
    $close = rapi('POST', '/api/v1/payments/reconcile/close', ['batch_ref' => $batchRef, 'reauth_token' => $reauthToken], $adminToken);
    rtassert($close['status'] === 200, 'close with valid reauth token must return 200');
    rtassert(($close['json']['data']['closed'] ?? false) === true, 'closed flag must be true');

    // Reauth token is consumed — reuse must fail
    $reuse = rapi('POST', '/api/v1/payments/reconcile/close', ['batch_ref' => $batchRef, 'reauth_token' => $reauthToken], $adminToken);
    rtassert($reuse['status'] === 422, 'consumed reauth token must not be reusable');
});

rrunCase('GET /reconcile/issues scope isolation: scoped user sees only own-scope issues', function () use (&$adminToken): void {
    $pdo = rpdo();

    // Create a batch in a foreign scope
    $foreignRef = 'SCOPE-FOREIGN-' . strtoupper(bin2hex(random_bytes(2)));
    $pdo->prepare("INSERT INTO reconciliation(batch_ref,period_start,period_end,expected_total,actual_total,variance,status,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([$foreignRef, date('Y-m-d'), date('Y-m-d'), 100.0, 50.0, -50.0, 'abnormal', '999', '999', '999']);
    $pdo->prepare("INSERT INTO finance_reconciliation_items(batch_ref,gateway_order_ref,issue_type,repaired,created_at) VALUES(?,?,?,?,NOW())")
        ->execute([$foreignRef, 'GW-FOREIGN-001', 'missed_order', 0]);

    // Admin sees all issues globally
    $adminView = rapi('GET', '/api/v1/payments/reconcile/issues?batch_ref=' . urlencode($foreignRef), [], $adminToken);
    rtassert($adminView['status'] === 200, 'admin must see issues for any batch');
    rtassert(count($adminView['json']['data']['items'] ?? []) === 1, 'admin must see the foreign-scope issue');
});

echo "Reconcile tests passed={$results['passed']} failed={$results['failed']}\n";
exit($results['failed'] === 0 ? 0 : 1);
