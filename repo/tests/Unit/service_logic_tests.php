<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!class_exists('think\\facade\\Config', false)) {
    $__envGet = function(string $k, string $fb): string { $v = getenv($k); return (is_string($v) && trim($v) !== '') ? trim($v) : $fb; };
    $__hmacSecret = $__envGet('PANTRYPILOT_GATEWAY_HMAC_SECRET', '');
    $__cryptoKey = $__envGet('PANTRYPILOT_CRYPTO_KEY', '');
    $__cryptoIv = $__envGet('PANTRYPILOT_CRYPTO_IV', '');
    eval(<<<PHP
namespace think\\facade;
final class Config {
    private static array \$map = [
        'security.gateway.merchant_id' => 'LOCAL-MERCHANT-001',
        'security.gateway.hmac_secret' => '{$__hmacSecret}',
        'security.gateway.order_auto_cancel_minutes' => 10,
        'security.files.max_size_bytes' => 10485760,
        'security.files.allowed_mime_types' => ['application/pdf', 'text/csv', 'image/png', 'image/jpeg'],
        'security.files.download_url_ttl_seconds' => 300,
        'security.files.retention_days' => 180,
        'security.crypto.cipher' => 'AES-256-CBC',
        'security.crypto.key' => '{$__cryptoKey}',
        'security.crypto.iv' => '{$__cryptoIv}',
    ];
    public static function get(string \$key, \$default = null) {
        return self::\$map[\$key] ?? \$default;
    }
}
PHP);
}

if (!class_exists('think\\facade\\Log', false)) {
    eval(<<<'PHP'
namespace think\facade;
final class Log {
    public static function info(string $message, array $context = []): void {}
}
PHP);
}

if (!class_exists('think\\facade\\Db', false)) {
    eval(<<<'PHP'
namespace think\facade;
final class Db {
    public static function transaction(callable $callback) {
        return $callback();
    }
}
PHP);
}

if (!class_exists('app\\repository\\IdentityRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class IdentityRepository {
    public array $users = [];
    public int $nextId = 10;
    public function __construct() {
        $this->users = [
            'admin' => ['id' => 1, 'username' => 'admin', 'display_name' => 'Admin', 'role' => 'admin', 'password_hash' => password_hash('admin12345', PASSWORD_BCRYPT), 'failed_login_attempts' => 0, 'locked_until' => null],
            'lock_user' => ['id' => 2, 'username' => 'lock_user', 'display_name' => 'Lock', 'role' => 'staff', 'password_hash' => password_hash('lock123456', PASSWORD_BCRYPT), 'failed_login_attempts' => 0, 'locked_until' => null],
        ];
    }
    public function findByUsername(string $username): ?array { return $this->users[$username] ?? null; }
    public function createUser(array $payload): int {
        $id = $this->nextId++;
        $this->users[$payload['username']] = [
            'id' => $id,
            'username' => $payload['username'],
            'display_name' => $payload['display_name'],
            'role' => $payload['role'],
            'password_hash' => $payload['password_hash'],
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ];
        return $id;
    }
    public function assignRoleByCode(int $userId, string $roleCode): void {}
    public function clearPasswordResetRequired(int $userId): void {}
    public function incrementFailedLogin(int $id, int $attempts, ?string $lockedUntil): void {
        foreach ($this->users as $k => $u) {
            if ((int) $u['id'] === $id) {
                $this->users[$k]['failed_login_attempts'] = $attempts;
                $this->users[$k]['locked_until'] = $lockedUntil;
            }
        }
    }
    public function clearFailedLogins(int $id): void {
        foreach ($this->users as $k => $u) {
            if ((int) $u['id'] === $id) {
                $this->users[$k]['failed_login_attempts'] = 0;
                $this->users[$k]['locked_until'] = null;
            }
        }
    }
    public function findById(int $id): ?array {
        foreach ($this->users as $u) {
            if ((int) $u['id'] === $id) { return $u; }
        }
        return null;
    }
}
PHP);
}

if (!class_exists('app\\repository\\AuthSessionRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class AuthSessionRepository {
    public array $sessions = [];
    public function create(int $userId, string $tokenHash, string $ip, string $ua, string $expireAt): void {
        $this->sessions[] = ['id' => count($this->sessions) + 1, 'user_id' => $userId, 'token_hash' => $tokenHash, 'expired_at' => $expireAt, 'active' => 1];
    }
    public function findActiveByTokenHash(string $tokenHash): ?array {
        foreach ($this->sessions as $s) {
            if ($s['token_hash'] === $tokenHash && (int) $s['active'] === 1) { return $s; }
        }
        return null;
    }
    public function touch(int $id): void {}
}
PHP);
}

if (!class_exists('app\\repository\\AuthorizationRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class AuthorizationRepository {
    public function hasPermission(int $userId, string $resourceCode, string $permissionCode): bool { return true; }
    public function scopesByUser(int $userId): array { return ['store' => [], 'warehouse' => [], 'department' => []]; }
    public function assignedRoleCode(int $userId): ?string { return 'customer'; }
    public function grantKeys(int $userId): array { return ['recipe:read', 'booking:read', 'booking:write']; }
}
PHP);
}

if (!class_exists('app\\repository\\BookingRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class BookingRepository {
    public array $slots = [];
    public array $blacklist = [];
    public bool $regionValid = true;
    public bool $zipRegionValid = true;
    public bool $pointExists = true;
    public array $point = ['id' => 1, 'slot_size' => 1, 'latitude' => 40.7128, 'longitude' => -74.0060, 'service_radius_km' => 10.0];
    public int $created = 0;
    public function list(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array { return ['items' => [], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 0]]; }
    public function activeBlacklist(int $userId): ?array { return $this->blacklist[$userId] ?? null; }
    public function regionExists(string $regionCode): bool { return $this->regionValid; }
    public function zip4InRegion(string $zip4, string $regionCode): bool { return $this->zipRegionValid; }
    public function pickupPointById(int $pickupPointId): ?array { return $this->pointExists ? $this->point : null; }
    public function ensureSlot(int $pickupPointId, string $slotStart, string $slotEnd, int $capacity): void {
        $key = $pickupPointId . '|' . $slotStart . '|' . $slotEnd;
        if (!isset($this->slots[$key])) {
            $this->slots[$key] = ['capacity' => $capacity, 'reserved' => 0];
        }
    }
    public function reserveSlotAtomic(int $pickupPointId, string $slotStart, string $slotEnd, int $quantity): array {
        $key = $pickupPointId . '|' . $slotStart . '|' . $slotEnd;
        $slot = $this->slots[$key] ?? ['capacity' => 0, 'reserved' => 0];
        if (($slot['capacity'] - $slot['reserved']) < $quantity) {
            throw new \RuntimeException('Slot capacity is not enough');
        }
        $slot['reserved'] += $quantity;
        $this->slots[$key] = $slot;
        return ['capacity' => $slot['capacity'], 'reserved' => $slot['reserved'], 'remaining' => $slot['capacity'] - $slot['reserved']];
    }
    public function create(array $data): int { $this->created++; return $this->created; }
    public function slotCapacity(int $pickupPointId, string $slotStart, string $slotEnd): array {
        $key = $pickupPointId . '|' . $slotStart . '|' . $slotEnd;
        $slot = $this->slots[$key] ?? ['capacity' => 0, 'reserved' => 0];
        return ['capacity' => $slot['capacity'], 'reserved' => $slot['reserved'], 'remaining' => $slot['capacity'] - $slot['reserved']];
    }
    public function pickupPoints(): array { return []; }
    public function recipeDetail(int $recipeId): ?array { return null; }
    public function recipeExists(int $recipeId): bool { return false; }
    public function recipeInScope(int $recipeId, array $scopes = [], array $authUser = []): bool { return false; }
    public function todaysPickups(string $today, array $scopes = [], array $authUser = []): array { return []; }
    public function checkIn(int $bookingId, int $staffId): bool { return true; }
    public function classifyNoShows(string $cutoffTime): array { return ['classified' => 0]; }
    public function dispatchNote(int $bookingId): array { return []; }
    public function bookingInScope(int $bookingId, array $scopes = [], array $authUser = []): bool { return true; }
    public function bookingExists(int $bookingId): bool { return true; }
}
PHP);
}

if (!class_exists('app\\repository\\PaymentRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class PaymentRepository {
    public array $callbacks = [];
    public array $orders = [];
    public array $payments = [];
    public array $issues = [1 => true];
    public array $issueScopes = [1 => true];
    public array $paymentScopes = [];
    public array $paymentRefs = [];
    public function listPayments(array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array { return ['items' => $this->payments, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => count($this->payments), 'total_pages' => 1]]; }
    public function createPayment(array $data): int {
        $this->payments[] = $data;
        if (!empty($data['payment_ref'])) {
            $this->paymentRefs[(string) $data['payment_ref']] = ['id' => count($this->payments)] + $data;
        }
        return count($this->payments);
    }
    public function reconcile(array $data): int { return 1; }
    public function createGatewayOrder(array $data): int { $this->orders[$data['order_ref']] = ['booking_id' => $data['booking_id'], 'amount' => $data['amount'], 'status' => 'pending', 'expire_at' => date('Y-m-d H:i:s', time() + 600)]; return 1; }
    public function gatewayOrderByRef(string $orderRef): ?array { return $this->orders[$orderRef] ?? null; }
    public function callbackExists(string $transactionRef): bool { return isset($this->callbacks[$transactionRef]); }
    public function saveCallback(string $transactionRef, array $payload): bool {
        if (isset($this->callbacks[$transactionRef])) {
            return false;
        }
        $this->callbacks[$transactionRef] = $payload;
        return true;
    }
    public function markGatewayOrderPaid(string $orderRef, string $transactionRef, array $payload, bool $verified): void {}
    public function bookingScopeById(int $bookingId): ?array { return ['store_id' => '1', 'warehouse_id' => '1', 'department_id' => '1']; }
    public function autoCancelExpiredGatewayOrders(array $scopes = [], array $authUser = []): int { return 0; }
    public function paidGatewayOrdersByDate(string $date, array $scopes = [], array $authUser = []): array { return []; }
    public function paymentsByDate(string $date, array $scopes = [], array $authUser = []): array { return []; }
    public function addReconciliationIssue(array $data): int { return 1; }
    public function issuesByBatch(string $batchRef): array { return []; }
    public function issueExists(int $issueId): bool { return isset($this->issues[$issueId]); }
    public function issueInScope(int $issueId, array $scopes = [], array $authUser = []): bool { return (bool) ($this->issueScopes[$issueId] ?? false); }
    public function repairIssue(int $issueId, string $note): bool { return true; }
    public function closeReconciliation(string $batchRef): bool { return true; }
    public function batchExists(string $batchRef): bool { return true; }
    public function batchInScope(string $batchRef, array $scopes = [], array $authUser = []): bool { return true; }
    public function paymentByRef(string $paymentRef): ?array { return $this->paymentRefs[$paymentRef] ?? null; }
    public function paymentInScopeByRef(string $paymentRef, array $scopes = [], array $authUser = []): bool {
        if (!isset($this->paymentRefs[$paymentRef])) {
            return false;
        }
        return (bool) ($this->paymentScopes[$paymentRef] ?? true);
    }
    public function markRefunded(int $paymentId): bool { return $paymentId > 0; }
    public function addAdjustment(int $paymentId, float $amount, string $reason, ?int $createdBy): int { return 1; }
}
PHP);
}

if (!class_exists('app\\repository\\FileRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class FileRepository {
    public array $rows = [];
    public array $lastExpiredArgs = [];
    public function addAttachment(array $data): int { $id = count($this->rows) + 1; $this->rows[$id] = ['id' => $id] + $data + ['hotlink_token' => null, 'signed_url_expire_at' => null]; return $id; }
    public function listAttachments(): array { return array_values($this->rows); }
    public function listAttachmentsScoped(array $scopes = [], array $authUser = []): array { return array_values($this->rows); }
    public function byId(int $id): ?array { return $this->rows[$id] ?? null; }
    public function updateSignedToken(int $id, string $token, string $expireAt): void { $this->rows[$id]['hotlink_token'] = $token; $this->rows[$id]['signed_url_expire_at'] = $expireAt; }
    public function attachmentInScope(int $id, array $scopes = [], array $authUser = []): bool {
        $row = $this->rows[$id] ?? null;
        if (!$row) {
            return false;
        }
        if (($row['owner_type'] ?? '') === 'user') {
            return (int) ($row['owner_id'] ?? 0) === (int) ($authUser['id'] ?? 0);
        }
        return true;
    }
    public function cleanupExpired(int $retentionDays): int { return 0; }
    public function expiredAttachments(int $retentionDays, array $scopes = [], array $authUser = []): array { $this->lastExpiredArgs = [$retentionDays, $scopes, $authUser]; return []; }
    public function deleteAttachment(int $id): bool { if (!isset($this->rows[$id])) { return false; } unset($this->rows[$id]); return true; }
}
PHP);
}

if (!class_exists('app\\repository\\AdminRepository', false)) {
    eval(<<<'PHP'
namespace app\repository;
final class AdminRepository {
    public function addAudit(array $payload): int { return 1; }
    public function listAuditLogs(): array { return []; }
    public function issueReauthToken(int $userId, string $tokenHash, string $expireAt): void {}
    public function consumeReauthToken(int $userId, string $tokenHash): bool { return true; }
}
PHP);
}

use app\domain\bookings\BookingDomainPolicy;
use app\domain\identity\LoginLockPolicy;
use app\domain\identity\PasswordPolicy;
use app\domain\payments\PaymentDomainPolicy;
use app\infrastructure\filesystem\LocalFileStorageAdapter;
use app\repository\AdminRepository;
use app\repository\AuthSessionRepository;
use app\repository\BookingRepository;
use app\repository\FileRepository;
use app\repository\IdentityRepository;
use app\repository\PaymentRepository;
use app\service\AdministrationService;
use app\service\AuthTokenService;
use app\service\BookingService;
use app\service\CryptoService;
use app\service\FileService;
use app\service\IdentityService;
use app\service\PaymentService;

runTest('Identity service enforces registration and lockout boundaries', function (): void {
    $repo = new IdentityRepository();
    $sessions = new AuthSessionRepository();
    $svc = new IdentityService($repo, new PasswordPolicy(), new LoginLockPolicy(), new AuthTokenService(), $sessions, new CryptoService());

    assertThrows(fn () => $svc->register(['username' => 'x', 'password' => 'abc1234567']), 'short username should fail');
    assertThrows(fn () => $svc->register(['username' => 'admin', 'password' => 'abc1234567']), 'duplicate username should fail');

    $created = $svc->register(['username' => 'new_user', 'password' => 'abc1234567', 'display_name' => 'New']);
    assertTrue((int) $created['id'] > 0, 'register should return id');

    for ($i = 0; $i < 5; $i++) {
        assertThrows(fn () => $svc->login('lock_user', 'wrong-pass'), 'invalid password should fail');
    }
    assertThrows(fn () => $svc->login('lock_user', 'lock123456'), 'locked user should be denied even with valid password');

    $ok = $svc->login('admin', 'admin12345');
    assertTrue($ok['token'] !== '', 'successful login should issue token');
    $authUser = $svc->userByToken($ok['token']);
    assertEquals('admin', $authUser['username'] ?? '', 'token should resolve auth user');
});

runTest('Booking service enforces offline booking constraints and contention', function (): void {
    $repo = new BookingRepository();
    $svc = new BookingService($repo, new BookingDomainPolicy());

    $over = date('Y-m-d H:i:s', strtotime('+8 days'));
    assertThrows(fn () => $svc->create([
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $over,
        'slot_start' => $over, 'slot_end' => date('Y-m-d H:i:s', strtotime($over) + 1800),
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
    ]), 'booking >7 days should fail');

    $near = date('Y-m-d H:i:s', strtotime('+1 hour'));
    assertThrows(fn () => $svc->create([
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $near,
        'slot_start' => $near, 'slot_end' => date('Y-m-d H:i:s', strtotime($near) + 1800),
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
    ]), 'booking inside 2h cutoff should fail');

    $future = date('Y-m-d H:i:s', strtotime('+1 day'));

    assertThrows(fn () => $svc->create([
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $future,
        'slot_start' => $future, 'slot_end' => date('Y-m-d H:i:s', strtotime($future) + 1800),
        'customer_zip4' => 'bad-zip', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ]), 'invalid ZIP+4 should fail');

    $repo->zipRegionValid = false;
    assertThrows(fn () => $svc->create([
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $future,
        'slot_start' => $future, 'slot_end' => date('Y-m-d H:i:s', strtotime($future) + 1800),
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ]), 'ZIP+4 region mismatch should fail');
    $repo->zipRegionValid = true;

    assertThrows(fn () => $svc->create([
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $future,
        'slot_start' => $future, 'slot_end' => date('Y-m-d H:i:s', strtotime($future) + 1800),
    ]), 'missing address fields should fail');

    $payload = [
        'recipe_id' => 1, 'user_id' => 1, 'pickup_point_id' => 1, 'pickup_at' => $future,
        'slot_start' => $future, 'slot_end' => date('Y-m-d H:i:s', strtotime($future) + 1800),
        'customer_zip4' => '12345-6789', 'customer_region_code' => 'REG-001',
        'customer_latitude' => 40.7128, 'customer_longitude' => -74.0060,
    ];
    $ok = $svc->create($payload);
    assertTrue((int) $ok['id'] === 1, 'first booking should succeed');
    assertThrows(fn () => $svc->create($payload), 'second booking should fail on slot contention');
});

runTest('Payment service verifies callback signature and idempotency', function (): void {
    $repo = new PaymentRepository();
    $admin = new AdministrationService(new AdminRepository(), new IdentityRepository(), new CryptoService());
    $svc = new PaymentService($repo, new PaymentDomainPolicy(), $admin, new CryptoService());

    assertThrows(fn () => $svc->create(['booking_id' => 1, 'amount' => 0]), 'non-positive payment should fail');
    $created = $svc->create(['booking_id' => 1, 'amount' => 9.5, 'payer_name' => 'AliceSensitive']);
    assertTrue((int) $created['id'] === 1, 'payment should be created');

    $order = $svc->createGatewayOrder(['booking_id' => 7, 'amount' => 9.99]);
    $payload = ['amount' => 9.99, 'order_ref' => $order['order_ref'], 'status' => 'SUCCESS', 'transaction_ref' => 'TX-100'];
    ksort($payload);
    $_hv = getenv('PANTRYPILOT_GATEWAY_HMAC_SECRET');
    $hmacSecret = (is_string($_hv) && trim($_hv) !== '') ? trim($_hv) : '';
    $sig = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $hmacSecret);

    $ok = $svc->processGatewayCallback($payload, $sig);
    assertTrue(($ok['processed'] ?? false) === true, 'first callback should process');

    $idem = $svc->processGatewayCallback($payload, $sig);
    assertTrue(($idem['idempotent'] ?? false) === true, 'repeat callback should be idempotent');

    $altered = $payload;
    $altered['amount'] = 9.88;
    $altered['status'] = 'SUCCESS';
    ksort($altered);
    $alteredSig = hash_hmac('sha256', json_encode($altered, JSON_UNESCAPED_UNICODE), $hmacSecret);
    $idemAltered = $svc->processGatewayCallback($altered, $alteredSig);
    assertTrue(($idemAltered['idempotent'] ?? false) === true, 'same transaction_ref with altered payload should remain idempotent');
    assertTrue(count($repo->callbacks) === 1, 'only one callback row should exist per transaction_ref');
    assertTrue(count($repo->payments) === 2, 'duplicate callback should not create another captured payment');

    $bad = $payload;
    $bad['transaction_ref'] = 'TX-101';
    assertThrows(fn () => $svc->processGatewayCallback($bad, 'bad-signature'), 'bad signature should fail');

    $repo->paymentRefs['PAY-OOS'] = ['id' => 99, 'payment_ref' => 'PAY-OOS'];
    $repo->paymentScopes['PAY-OOS'] = false;
    assertThrows(fn () => $svc->refund('PAY-OOS', 1, 'token', [], []), 'refund should fail when payment is out of scope');

    $list = $svc->list([], []);
    $items = $list['items'] ?? [];
    assertTrue(isset($items[0]['payer_name_masked']), 'masked payer name should be returned');
    assertTrue(!isset($items[0]['payer_name_enc']), 'encrypted payer name should not be exposed');
});

runTest('File service enforces type, magic bytes, signed URL, and expiry', function (): void {
    $repo = new FileRepository();
    $storage = new LocalFileStorageAdapter('/var/www/html/runtime/uploads/unit_tests');
    $svc = new FileService($repo, $storage);

    assertThrows(fn () => $svc->uploadBase64([
        'filename' => 'x.exe',
        'mime_type' => 'application/octet-stream',
        'content_base64' => base64_encode('bad'),
    ]), 'unsupported mime should fail');

    assertThrows(fn () => $svc->uploadBase64([
        'filename' => 'bad.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode('NOTPDF'),
    ]), 'bad PDF magic bytes should fail');

    assertThrows(fn () => $svc->uploadBase64([
        'filename' => 'huge.csv',
        'mime_type' => 'text/csv',
        'content_base64' => base64_encode(str_repeat('A', 10485761)),
    ]), 'files larger than 10MB should fail');

    $png1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+Lw0AAAAASUVORK5CYII=';
    if (function_exists('imagecreatefromstring') && function_exists('imagestring')) {
        $wm = $svc->uploadBase64([
            'filename' => 'wm.png',
            'mime_type' => 'image/png',
            'content_base64' => $png1x1,
            'watermark' => true,
        ]);
        $wmPath = '/var/www/html/' . ltrim((string) ($wm['storage_path'] ?? ''), '/');
        $wmBytes = file_get_contents($wmPath);
        assertTrue(is_string($wmBytes) && str_starts_with($wmBytes, "\x89PNG"), 'watermarked PNG should remain valid PNG binary');
    } else {
        assertThrows(fn () => $svc->uploadBase64([
            'filename' => 'wm.png',
            'mime_type' => 'image/png',
            'content_base64' => $png1x1,
            'watermark' => true,
        ]), 'watermark should fail explicitly without GD extension support');
    }

    $upload = $svc->uploadBase64([
        'filename' => 'ok.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        'watermark' => false,
    ]);
    $fileId = (int) $upload['id'];
    assertTrue($fileId > 0, 'valid upload should return id');

    $signed = $svc->createSignedDownloadUrl($fileId);
    parse_str(parse_url((string) $signed['download_url'], PHP_URL_QUERY) ?: '', $query);
    $token = (string) ($query['token'] ?? '');
    assertTrue($token !== '', 'signed URL should include token');

    assertThrows(fn () => $svc->validateDownloadToken($fileId, 'bad-token'), 'invalid hotlink token should fail');
    $ok = $svc->validateDownloadToken($fileId, $token);
    assertTrue(isset($ok['content_base64']), 'valid token should return base64 content');

    $userOwned = $svc->uploadBase64([
        'filename' => 'owned.pdf',
        'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        'owner_type' => 'user',
        'owner_id' => 44,
    ]);
    assertThrows(
        fn () => $svc->createSignedDownloadUrl((int) $userOwned['id'], [], ['id' => 45]),
        'user-owned attachment should reject cross-user signed URL access'
    );

    assertTrue(isset($signed['expire_at']) && $signed['expire_at'] !== '', 'signed URL should include expiry timestamp');

    $cleanupA = $svc->cleanupLifecycle(['store' => ['1']], ['id' => 2, 'role' => 'ops_staff', 'store_id' => '1']);
    $repoState = (array) $repo;
    $args = $repoState['lastExpiredArgs'] ?? [];
    assertTrue(isset($args[1]['store']) && $args[1]['store'][0] === '1', 'cleanup should pass scopes to repository for non-admin filtering');
    $cleanupB = $svc->cleanupLifecycle();
    assertTrue(isset($cleanupA['deleted_records']) && isset($cleanupB['deleted_records']), 'cleanup lifecycle should return structured result idempotently');
});

exit(finishTests());
