<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use app\service\AuthTokenService;
use app\service\CryptoService;
use app\service\NotificationService;
use app\infrastructure\notification\LocalNotificationAdapter;
use app\infrastructure\time\ClockInterface;

runTest('Auth token issuance is non-empty and hashable', function (): void {
    $svc = new AuthTokenService();
    $a = $svc->issueToken();
    $b = $svc->issueToken();
    assertTrue($a !== '' && $b !== '', 'Tokens should not be empty');
    assertTrue($a !== $b, 'Tokens should be unique');
    assertEquals(64, strlen($svc->hashToken($a)), 'SHA256 hash must be 64 chars');
});

runTest('Auth token expiration in the future', function (): void {
    $svc = new AuthTokenService();
    $exp = $svc->expirationDateTime();
    assertTrue(strtotime($exp) > time(), 'Expiration should be future');
});

runTest('Crypto mask protects sensitive outputs', function (): void {
    $svc = new CryptoService();
    assertEquals('Al**********ve', $svc->mask('AliceSensitive'), 'Mask should preserve only edge characters');
    assertEquals('****', $svc->mask('1234'), 'Short values should be fully masked');
    assertEquals('', $svc->mask(''), 'Empty value should remain empty');
});

runTest('Auth token hash is deterministic for signature-safe comparisons', function (): void {
    $svc = new AuthTokenService();
    $raw = 'callback-signature-check';
    assertEquals($svc->hashToken($raw), $svc->hashToken($raw), 'Same raw token must always hash identically');
});

runTest('Security config uses env overrides and throws when secrets are empty', function (): void {
    $configPath = '/var/www/html/config/security.php';

    $origKey  = getenv('PANTRYPILOT_CRYPTO_KEY');
    $origHmac = getenv('PANTRYPILOT_GATEWAY_HMAC_SECRET');
    $origIv   = getenv('PANTRYPILOT_CRYPTO_IV');

    putenv('PANTRYPILOT_CRYPTO_KEY=override_crypto_key_for_test');
    putenv('PANTRYPILOT_GATEWAY_HMAC_SECRET=override_hmac_secret_for_test');
    putenv('PANTRYPILOT_CRYPTO_IV=override_iv_16byt');
    $cfg = require $configPath;
    assertEquals('override_crypto_key_for_test', (string) ($cfg['crypto']['key'] ?? ''), 'crypto key should be overridden by env');
    assertEquals('override_hmac_secret_for_test', (string) ($cfg['gateway']['hmac_secret'] ?? ''), 'gateway hmac secret should be overridden by env');
    assertEquals('override_iv_16byt', (string) ($cfg['crypto']['iv'] ?? ''), 'crypto iv should be overridden by env');

    // Verify that missing (empty) secrets now throw RuntimeException instead of silently falling back
    putenv('PANTRYPILOT_GATEWAY_HMAC_SECRET=');
    $threw = false;
    try {
        require $configPath;
    } catch (\RuntimeException $e) {
        $threw = true;
        assertTrue(str_contains($e->getMessage(), 'PANTRYPILOT_GATEWAY_HMAC_SECRET'), 'exception must name the missing variable');
    }
    assertTrue($threw, 'empty PANTRYPILOT_GATEWAY_HMAC_SECRET must throw RuntimeException');

    // Restore original env for subsequent tests
    if ($origKey !== false) putenv("PANTRYPILOT_CRYPTO_KEY={$origKey}");
    else putenv('PANTRYPILOT_CRYPTO_KEY=');
    if ($origHmac !== false) putenv("PANTRYPILOT_GATEWAY_HMAC_SECRET={$origHmac}");
    else putenv('PANTRYPILOT_GATEWAY_HMAC_SECRET=');
    if ($origIv !== false) putenv("PANTRYPILOT_CRYPTO_IV={$origIv}");
    else putenv('PANTRYPILOT_CRYPTO_IV=');
});

runTest('Notification recipient scope helper enforces non-admin data isolation', function (): void {
    $clock = new class implements ClockInterface {
        public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-01-15 10:30:00'); }
    };
    $svc = new NotificationService(new LocalNotificationAdapter(), $clock);

    $recipientInScope = ['id' => 2, 'store_id' => '1', 'warehouse_id' => '1', 'department_id' => '1'];
    $recipientOutScope = ['id' => 9, 'store_id' => '999', 'warehouse_id' => '999', 'department_id' => '999'];
    $scopes = ['store' => ['1'], 'warehouse' => ['1'], 'department' => ['1']];
    $authUser = ['id' => 2, 'role' => 'ops_staff', 'store_id' => '1', 'warehouse_id' => '1', 'department_id' => '1'];

    assertTrue((bool) call_user_func([$svc, 'recipientInScope'], $recipientInScope, $scopes, $authUser), 'recipient in explicit actor scope should be allowed');
    assertTrue(!(bool) call_user_func([$svc, 'recipientInScope'], $recipientOutScope, $scopes, $authUser), 'recipient outside explicit actor scope should be denied');
});

exit(finishTests());
