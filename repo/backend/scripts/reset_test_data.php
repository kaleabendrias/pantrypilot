<?php
declare(strict_types=1);

$dsn = 'mysql:host=mysql;port=3306;dbname=pantrypilot;charset=utf8mb4';
$user = 'pantry';
$pass = 'pantrypass';

$pdo = null;
for ($i = 0; $i < 30; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (Throwable $e) {
        usleep(500000);
    }
}

if (!$pdo) {
    fwrite(STDERR, "Unable to connect to mysql\n");
    exit(1);
}

$bootstrapSql = '/workspace/db_init/001_schema.sql';
if (is_file($bootstrapSql)) {
    $sql = file_get_contents($bootstrapSql);
    if ($sql !== false) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            $parts = preg_split('/;\s*\n/', $sql) ?: [];
            foreach ($parts as $part) {
                $stmt = trim($part);
                if ($stmt === '') {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                } catch (Throwable) {
                }
            }
        }
    }
}

$ensure = [
    "CREATE TABLE IF NOT EXISTS stores (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS warehouses (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS departments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS address_regions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, region_code VARCHAR(20) NOT NULL UNIQUE, region_name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS zip4_reference (zip4_code VARCHAR(10) PRIMARY KEY, region_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, state_code VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_zip4_region(region_code))",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS store_id BIGINT UNSIGNED NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS account_enabled TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_enc TEXT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS address_enc TEXT NULL",
    "CREATE TABLE IF NOT EXISTS roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS resources (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS user_roles (user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, role_id))",
    "CREATE TABLE IF NOT EXISTS role_permission_resources (role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, resource_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(role_id, permission_id, resource_id))",
    "CREATE TABLE IF NOT EXISTS user_data_scopes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, scope_type ENUM('store','warehouse','department') NOT NULL, scope_value VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_user_scope(user_id,scope_type,scope_value))",
    "CREATE TABLE IF NOT EXISTS auth_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS step_count INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy'",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS calories INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS popularity_score INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS store_id VARCHAR(60) NULL",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS warehouse_id VARCHAR(60) NULL",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS department_id VARCHAR(60) NULL",
    "CREATE TABLE IF NOT EXISTS ingredients (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, display_name VARCHAR(120) NOT NULL, normalized_name VARCHAR(120) NOT NULL UNIQUE, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS recipe_ingredients (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipe_id BIGINT UNSIGNED NOT NULL, ingredient_name VARCHAR(120) NOT NULL, ingredient_name_norm VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS recipe_cookware (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipe_id BIGINT UNSIGNED NOT NULL, cookware_norm VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS recipe_allergens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipe_id BIGINT UNSIGNED NOT NULL, allergen_norm VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS search_synonyms (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, synonym VARCHAR(80) NOT NULL UNIQUE, canonical_term VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL UNIQUE, color VARCHAR(20) NOT NULL DEFAULT '#3a7afe', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS recipe_tags (recipe_id BIGINT UNSIGNED NOT NULL, tag_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(recipe_id, tag_id))",
    "ALTER TABLE pickup_points ADD COLUMN IF NOT EXISTS region_code VARCHAR(20) NULL",
    "ALTER TABLE pickup_points ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL",
    "ALTER TABLE pickup_points ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL",
    "ALTER TABLE pickup_points ADD COLUMN IF NOT EXISTS service_radius_km DECIMAL(6,2) NOT NULL DEFAULT 10.00",
    "CREATE TABLE IF NOT EXISTS pickup_slots (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pickup_point_id BIGINT UNSIGNED NOT NULL, slot_start DATETIME NOT NULL, slot_end DATETIME NOT NULL, capacity INT UNSIGNED NOT NULL, reserved_count INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_slot(pickup_point_id,slot_start,slot_end))",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS slot_start DATETIME NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS slot_end DATETIME NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS arrived_at DATETIME NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checked_in_by BIGINT UNSIGNED NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS no_show_marked_at DATETIME NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_zip4 VARCHAR(10) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_region_code VARCHAR(20) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_latitude DECIMAL(10,7) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_longitude DECIMAL(10,7) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS distance_km DECIMAL(8,3) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS store_id VARCHAR(60) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS warehouse_id VARCHAR(60) NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS department_id VARCHAR(60) NULL",
    "CREATE TABLE IF NOT EXISTS booking_blacklist (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(255) NOT NULL, blocked_until DATETIME NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS payer_name_enc TEXT NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS store_id VARCHAR(60) NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS warehouse_id VARCHAR(60) NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS department_id VARCHAR(60) NULL",
    "CREATE TABLE IF NOT EXISTS homepage_modules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, module_key VARCHAR(50) NOT NULL, payload JSON NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, updated_by BIGINT UNSIGNED NULL, store_id VARCHAR(60) NULL, warehouse_id VARCHAR(60) NULL, department_id VARCHAR(60) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_homepage_module_scope(module_key,store_id,warehouse_id,department_id))",
    "CREATE TABLE IF NOT EXISTS message_templates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, template_code VARCHAR(60) NOT NULL, title VARCHAR(120) NOT NULL, content TEXT NOT NULL, category VARCHAR(30) NOT NULL DEFAULT 'system', active TINYINT(1) NOT NULL DEFAULT 1, store_id VARCHAR(60) NULL, warehouse_id VARCHAR(60) NULL, department_id VARCHAR(60) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_message_template_scope(template_code,store_id,warehouse_id,department_id))",
    "CREATE TABLE IF NOT EXISTS user_message_preferences (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, marketing_opt_out TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_msg_pref_user(user_id))",
    "CREATE TABLE IF NOT EXISTS message_center (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, template_id BIGINT UNSIGNED NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, is_marketing TINYINT(1) NOT NULL DEFAULT 0, sent_at DATETIME NOT NULL, read_at DATETIME NULL, clicked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS gateway_orders (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_ref VARCHAR(60) NOT NULL UNIQUE, booking_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(10,2) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', provider VARCHAR(30) NOT NULL DEFAULT 'wechat_local', transaction_ref VARCHAR(80) NULL, callback_payload JSON NULL, callback_verified TINYINT(1) NOT NULL DEFAULT 0, callback_processed_at DATETIME NULL, expire_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_gateway_tx_ref(transaction_ref))",
    "CREATE TABLE IF NOT EXISTS gateway_callbacks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, transaction_ref VARCHAR(80) NOT NULL, payload JSON NOT NULL, processed TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_callback_transaction(transaction_ref))",
    "ALTER TABLE gateway_callbacks DROP COLUMN IF EXISTS callback_hash",
    "ALTER TABLE gateway_callbacks ADD UNIQUE KEY uk_callback_transaction(transaction_ref)",
    "CREATE TABLE IF NOT EXISTS finance_reconciliation_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_ref VARCHAR(60) NOT NULL, gateway_order_ref VARCHAR(60) NOT NULL, issue_type VARCHAR(30) NOT NULL, repaired TINYINT(1) NOT NULL DEFAULT 0, repaired_note VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS finance_adjustments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payment_id BIGINT UNSIGNED NOT NULL, adjust_amount DECIMAL(10,2) NOT NULL, reason VARCHAR(255) NOT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS critical_reauth_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expire_at DATETIME NOT NULL, consumed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "ALTER TABLE attachments ADD COLUMN IF NOT EXISTS sha256 CHAR(64) NULL",
    "ALTER TABLE attachments ADD COLUMN IF NOT EXISTS magic_verified TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE attachments ADD COLUMN IF NOT EXISTS watermarked TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE attachments ADD COLUMN IF NOT EXISTS hotlink_token CHAR(64) NULL",
    "ALTER TABLE attachments ADD COLUMN IF NOT EXISTS signed_url_expire_at DATETIME NULL",
    "ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS prev_hash CHAR(64) NULL",
    "ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS hash_current CHAR(64) NULL",
    "ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL",
    "CREATE TABLE IF NOT EXISTS stock_snapshots (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(60) NOT NULL, qty INT NOT NULL, snapshot_date DATE NOT NULL, store_id VARCHAR(60) NULL, warehouse_id VARCHAR(60) NULL, department_id VARCHAR(60) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_stock_scope(store_id,warehouse_id,department_id))",
    "CREATE TABLE IF NOT EXISTS anomaly_alerts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alert_type VARCHAR(60) NOT NULL, severity VARCHAR(20) NOT NULL, payload JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS dispatch_notes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT UNSIGNED NOT NULL, note_text TEXT NOT NULL, printable_payload JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
];

foreach ($ensure as $stmt) {
    try {
        $pdo->exec($stmt);
    } catch (Throwable) {
    }
}

try {
    $pdo->exec('ALTER TABLE homepage_modules DROP INDEX module_key');
} catch (Throwable) {
}
try {
    $pdo->exec('ALTER TABLE message_templates DROP INDEX template_code');
} catch (Throwable) {
}
try {
    $pdo->exec('ALTER TABLE homepage_modules ADD UNIQUE KEY uk_homepage_module_scope(module_key,store_id,warehouse_id,department_id)');
} catch (Throwable) {
}
try {
    $pdo->exec('ALTER TABLE message_templates ADD UNIQUE KEY uk_message_template_scope(template_code,store_id,warehouse_id,department_id)');
} catch (Throwable) {
}

$tableExists = static function (PDO $pdoRef, string $table): bool {
    $q = $pdoRef->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $q->execute([$table]);
    return (int) $q->fetchColumn() > 0;
};

$columnExists = static function (PDO $pdoRef, string $table, string $column): bool {
    $q = $pdoRef->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $q->execute([$table, $column]);
    return (int) $q->fetchColumn() > 0;
};

$ensureColumn = static function (PDO $pdoRef, string $table, string $column, string $definition) use ($tableExists, $columnExists): void {
    if (!$tableExists($pdoRef, $table)) {
        return;
    }
    if ($columnExists($pdoRef, $table, $column)) {
        return;
    }
    $pdoRef->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
};

if ($tableExists($pdo, 'gateway_callbacks') && $columnExists($pdo, 'gateway_callbacks', 'callback_hash')) {
    try {
        $pdo->exec('ALTER TABLE gateway_callbacks DROP COLUMN callback_hash');
    } catch (Throwable) {
    }
}

$ensureColumn($pdo, 'users', 'store_id', 'BIGINT UNSIGNED NULL');
$ensureColumn($pdo, 'users', 'warehouse_id', 'BIGINT UNSIGNED NULL');
$ensureColumn($pdo, 'users', 'department_id', 'BIGINT UNSIGNED NULL');
$ensureColumn($pdo, 'users', 'failed_login_attempts', 'INT UNSIGNED NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'users', 'last_failed_login_at', 'DATETIME NULL');
$ensureColumn($pdo, 'users', 'locked_until', 'DATETIME NULL');
$ensureColumn($pdo, 'users', 'account_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
$ensureColumn($pdo, 'users', 'phone_enc', 'TEXT NULL');
$ensureColumn($pdo, 'users', 'address_enc', 'TEXT NULL');

$ensureColumn($pdo, 'recipes', 'step_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'recipes', 'difficulty', "ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy'");
$ensureColumn($pdo, 'recipes', 'calories', 'INT UNSIGNED NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'recipes', 'estimated_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
$ensureColumn($pdo, 'recipes', 'popularity_score', 'INT UNSIGNED NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'recipes', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'recipes', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'recipes', 'department_id', 'VARCHAR(60) NULL');

$ensureColumn($pdo, 'pickup_points', 'region_code', 'VARCHAR(20) NULL');
$ensureColumn($pdo, 'pickup_points', 'latitude', 'DECIMAL(10,7) NULL');
$ensureColumn($pdo, 'pickup_points', 'longitude', 'DECIMAL(10,7) NULL');
$ensureColumn($pdo, 'pickup_points', 'service_radius_km', 'DECIMAL(6,2) NOT NULL DEFAULT 10.00');

$ensureColumn($pdo, 'bookings', 'slot_start', 'DATETIME NULL');
$ensureColumn($pdo, 'bookings', 'slot_end', 'DATETIME NULL');
$ensureColumn($pdo, 'bookings', 'arrived_at', 'DATETIME NULL');
$ensureColumn($pdo, 'bookings', 'checked_in_by', 'BIGINT UNSIGNED NULL');
$ensureColumn($pdo, 'bookings', 'no_show_marked_at', 'DATETIME NULL');
$ensureColumn($pdo, 'bookings', 'customer_zip4', 'VARCHAR(10) NULL');
$ensureColumn($pdo, 'bookings', 'customer_region_code', 'VARCHAR(20) NULL');
$ensureColumn($pdo, 'bookings', 'customer_latitude', 'DECIMAL(10,7) NULL');
$ensureColumn($pdo, 'bookings', 'customer_longitude', 'DECIMAL(10,7) NULL');
$ensureColumn($pdo, 'bookings', 'distance_km', 'DECIMAL(8,3) NULL');
$ensureColumn($pdo, 'bookings', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'bookings', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'bookings', 'department_id', 'VARCHAR(60) NULL');

$ensureColumn($pdo, 'payments', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'payments', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'payments', 'department_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'payments', 'payer_name_enc', 'TEXT NULL');
$ensureColumn($pdo, 'stock_snapshots', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'stock_snapshots', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'stock_snapshots', 'department_id', 'VARCHAR(60) NULL');

$ensureColumn($pdo, 'campaigns', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'campaigns', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'campaigns', 'department_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'homepage_modules', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'homepage_modules', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'homepage_modules', 'department_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'message_templates', 'store_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'message_templates', 'warehouse_id', 'VARCHAR(60) NULL');
$ensureColumn($pdo, 'message_templates', 'department_id', 'VARCHAR(60) NULL');

$ensureColumn($pdo, 'attachments', 'sha256', 'CHAR(64) NULL');
$ensureColumn($pdo, 'attachments', 'magic_verified', 'TINYINT(1) NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'attachments', 'watermarked', 'TINYINT(1) NOT NULL DEFAULT 0');
$ensureColumn($pdo, 'attachments', 'hotlink_token', 'CHAR(64) NULL');
$ensureColumn($pdo, 'attachments', 'signed_url_expire_at', 'DATETIME NULL');

$ensureColumn($pdo, 'audit_logs', 'prev_hash', 'CHAR(64) NULL');
$ensureColumn($pdo, 'audit_logs', 'hash_current', 'CHAR(64) NULL');
$ensureColumn($pdo, 'audit_logs', 'ip_address', 'VARCHAR(45) NULL');

$tables = [
    'critical_reauth_tokens', 'finance_adjustments', 'finance_reconciliation_items',
    'gateway_callbacks', 'gateway_orders', 'dispatch_notes', 'anomaly_alerts',
    'stock_snapshots', 'message_center', 'user_message_preferences', 'message_templates',
    'homepage_modules', 'booking_blacklist', 'bookings', 'pickup_slots', 'payments',
    'reconciliation', 'attachments', 'audit_logs', 'recipe_allergens', 'recipe_cookware',
    'recipe_tags', 'tags', 'recipe_ingredients', 'recipes', 'ingredients', 'search_synonyms', 'user_data_scopes',
    'user_roles', 'role_permission_resources', 'roles', 'permissions', 'resources', 'users',
    'pickup_points', 'zip4_reference', 'address_regions', 'stores', 'warehouses', 'departments'
];

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $t) {
    try {
        $pdo->exec("TRUNCATE TABLE {$t}");
    } catch (Throwable) {
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$now = date('Y-m-d H:i:s');

$pdo->exec("INSERT INTO stores(code,name,created_at) VALUES('S001','Main Store','{$now}')");
$pdo->exec("INSERT INTO warehouses(code,name,created_at) VALUES('W001','Main Warehouse','{$now}')");
$pdo->exec("INSERT INTO departments(code,name,created_at) VALUES('D001','General Department','{$now}')");

$adminHash = password_hash('admin12345', PASSWORD_BCRYPT);
$scopedHash = password_hash('scope123456', PASSWORD_BCRYPT);
$lockHash = password_hash('lock123456', PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,store_id,warehouse_id,department_id,failed_login_attempts,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
$stmt->execute(['admin', $adminHash, 'System Admin', 'admin', 1, 1, 1, 0, $now, $now]);
$stmt->execute(['scoped_user', $scopedHash, 'Scoped User', 'staff', 1, 1, 1, 0, $now, $now]);
$stmt->execute(['lock_user', $lockHash, 'Lock User', 'staff', 1, 1, 1, 0, $now, $now]);

$roles = [['admin','Administrator'],['ops_staff','Operations Staff']];
$stmtRole = $pdo->prepare('INSERT INTO roles(code,name,created_at) VALUES(?,?,?)');
foreach ($roles as $r) $stmtRole->execute([$r[0], $r[1], $now]);

$perms = [['read','Read'],['write','Write'],['approve','Approve']];
$stmtPerm = $pdo->prepare('INSERT INTO permissions(code,name,created_at) VALUES(?,?,?)');
foreach ($perms as $p) $stmtPerm->execute([$p[0], $p[1], $now]);

$resources = ['recipe','booking','operations','payment','notification','file','reporting','admin'];
$stmtRes = $pdo->prepare('INSERT INTO resources(code,name,created_at) VALUES(?,?,?)');
foreach ($resources as $r) $stmtRes->execute([$r, ucfirst($r), $now]);

$roleMap = $pdo->query('SELECT code,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$permMap = $pdo->query('SELECT code,id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
$resMap = $pdo->query('SELECT code,id FROM resources')->fetchAll(PDO::FETCH_KEY_PAIR);
$userMap = $pdo->query('SELECT username,id FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);

$pdo->prepare('INSERT INTO user_roles(user_id,role_id,created_at) VALUES(?,?,?)')->execute([$userMap['admin'], $roleMap['admin'], $now]);
$pdo->prepare('INSERT INTO user_roles(user_id,role_id,created_at) VALUES(?,?,?)')->execute([$userMap['scoped_user'], $roleMap['ops_staff'], $now]);

$stmtRpr = $pdo->prepare('INSERT INTO role_permission_resources(role_id,permission_id,resource_id,created_at) VALUES(?,?,?,?)');
foreach ($permMap as $permCode => $permId) {
    foreach ($resMap as $resCode => $resId) {
        $stmtRpr->execute([$roleMap['admin'], $permId, $resId, $now]);
    }
}
$stmtRpr->execute([$roleMap['ops_staff'], $permMap['read'], $resMap['recipe'], $now]);
$stmtRpr->execute([$roleMap['ops_staff'], $permMap['read'], $resMap['booking'], $now]);
$stmtRpr->execute([$roleMap['ops_staff'], $permMap['write'], $resMap['booking'], $now]);
$stmtRpr->execute([$roleMap['ops_staff'], $permMap['read'], $resMap['notification'], $now]);

$stmtScope = $pdo->prepare('INSERT INTO user_data_scopes(user_id,scope_type,scope_value,created_at) VALUES(?,?,?,?)');
foreach (['store','warehouse','department'] as $scopeType) {
    $stmtScope->execute([$userMap['admin'], $scopeType, '1', $now]);
    $stmtScope->execute([$userMap['scoped_user'], $scopeType, '1', $now]);
}

$pdo->exec("INSERT INTO address_regions(region_code,region_name,created_at) VALUES
('REG-001','Central Region','{$now}'),
('REG-002','North Region','{$now}')");
$pdo->exec("INSERT INTO zip4_reference(zip4_code,region_code,city,state_code,created_at) VALUES
('12345-6789','REG-001','Central City','CC','{$now}'),
('12345-6790','REG-001','Central City','CC','{$now}'),
('22345-6789','REG-002','North City','NC','{$now}')");

$pdo->exec("INSERT INTO pickup_points(name,address,slot_size,active,created_at,region_code,latitude,longitude,service_radius_km) VALUES
('Central Pantry','100 Main St',1,1,'{$now}','REG-001',40.7128,-74.0060,12.87),
('North Community Hub','22 Pine Ave',5,1,'{$now}','REG-001',40.7306,-73.9352,12.87)");

$pdo->exec("INSERT INTO search_synonyms(synonym,canonical_term,created_at) VALUES
('garbanzo','chickpea','{$now}'),('chickpeas','chickpea','{$now}'),('tomatos','tomato','{$now}')");

$pdo->exec("INSERT INTO tags(name,color,created_at) VALUES
('vegan','#2e7d32','{$now}'),
('comfort','#f57c00','{$now}')");

$pdo->exec("INSERT INTO ingredients(display_name,normalized_name,created_at) VALUES
('Chickpea','chickpea','{$now}'),('Tomato','tomato','{$now}'),('Potato','potato','{$now}')");

$stmtRecipe = $pdo->prepare('INSERT INTO recipes(code,name,description,prep_minutes,step_count,servings,difficulty,calories,estimated_cost,popularity_score,status,created_by,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$stmtRecipe->execute(['RCP-CHICK', 'Chickpea Stew', 'Warm chickpea tomato stew', 25, 6, 2, 'easy', 430, 12.00, 90, 'published', $userMap['admin'], '1', '1', '1', $now, $now]);
$stmtRecipe->execute(['RCP-POTATO', 'Potato Soup', 'Creamy potato soup', 35, 8, 3, 'medium', 520, 16.00, 75, 'published', $userMap['admin'], '1', '1', '1', $now, $now]);
$recipeMap = $pdo->query('SELECT code,id FROM recipes')->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtRi = $pdo->prepare('INSERT INTO recipe_ingredients(recipe_id,ingredient_name,ingredient_name_norm,created_at) VALUES(?,?,?,?)');
$stmtRi->execute([$recipeMap['RCP-CHICK'], 'Chickpea', 'chickpea', $now]);
$stmtRi->execute([$recipeMap['RCP-CHICK'], 'Tomato', 'tomato', $now]);
$stmtRi->execute([$recipeMap['RCP-POTATO'], 'Potato', 'potato', $now]);

$stmtRc = $pdo->prepare('INSERT INTO recipe_cookware(recipe_id,cookware_norm,created_at) VALUES(?,?,?)');
$stmtRc->execute([$recipeMap['RCP-CHICK'], 'pot', $now]);
$stmtRc->execute([$recipeMap['RCP-POTATO'], 'pan', $now]);

$stmtRa = $pdo->prepare('INSERT INTO recipe_allergens(recipe_id,allergen_norm,created_at) VALUES(?,?,?)');
$stmtRa->execute([$recipeMap['RCP-CHICK'], 'none', $now]);
$stmtRa->execute([$recipeMap['RCP-POTATO'], 'dairy', $now]);

$tagMap = $pdo->query('SELECT name,id FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
$pdo->prepare('INSERT INTO recipe_tags(recipe_id,tag_id) VALUES(?,?)')->execute([$recipeMap['RCP-CHICK'], (int) $tagMap['vegan']]);
$pdo->prepare('INSERT INTO recipe_tags(recipe_id,tag_id) VALUES(?,?)')->execute([$recipeMap['RCP-POTATO'], (int) $tagMap['comfort']]);

$slotStart = date('Y-m-d H:00:00', strtotime('+1 day 10:00'));
$slotEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800);
$pdo->prepare('INSERT INTO pickup_slots(pickup_point_id,slot_start,slot_end,capacity,reserved_count,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')
    ->execute([1, $slotStart, $slotEnd, 1, 0, $now, $now]);

$old1 = date('Y-m-d H:i:s', strtotime('-2 days 10:00'));
$old2 = date('Y-m-d H:i:s', strtotime('-1 days 10:00'));
$old3 = date('Y-m-d H:i:s', strtotime('-1 hour'));
$stmtBook = $pdo->prepare('INSERT INTO bookings(booking_code,recipe_id,user_id,pickup_point_id,pickup_at,slot_start,slot_end,quantity,status,note,store_id,warehouse_id,department_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$stmtBook->execute(['BKG-NS1', $recipeMap['RCP-CHICK'], $userMap['scoped_user'], 1, $old1, $old1, date('Y-m-d H:i:s', strtotime($old1)+1800), 1, 'no_show', 'seed', '1', '1', '1', $now, $now]);
$stmtBook->execute(['BKG-NS2', $recipeMap['RCP-CHICK'], $userMap['scoped_user'], 1, $old2, $old2, date('Y-m-d H:i:s', strtotime($old2)+1800), 1, 'no_show', 'seed', '1', '1', '1', $now, $now]);
$stmtBook->execute(['BKG-PENDING-NS', $recipeMap['RCP-CHICK'], $userMap['scoped_user'], 1, $old3, $old3, date('Y-m-d H:i:s', strtotime($old3)+1800), 1, 'pending', 'seed', '1', '1', '1', $now, $now]);

$todayOrderRef = 'GW-MISS-001';
$pdo->prepare('INSERT INTO gateway_orders(order_ref,booking_id,amount,status,provider,transaction_ref,expire_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')
    ->execute([$todayOrderRef, 3, 10.00, 'paid', 'wechat_local', 'TX-MISS-001', date('Y-m-d H:i:s', strtotime('+5 min')), $now, $now]);

$pdo->prepare('INSERT INTO stock_snapshots(sku,qty,snapshot_date,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?)')->execute(['SKU-1', 0, date('Y-m-d'), '1', '1', '1', $now]);
$pdo->prepare('INSERT INTO stock_snapshots(sku,qty,snapshot_date,store_id,warehouse_id,department_id,created_at) VALUES(?,?,?,?,?,?,?)')->execute(['SKU-2', 10, date('Y-m-d'), '1', '1', '1', $now]);

echo "reset_test_data complete\n";
