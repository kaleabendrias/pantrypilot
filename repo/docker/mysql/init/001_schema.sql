CREATE TABLE IF NOT EXISTS stores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'staff',
  phone_enc TEXT NULL,
  address_enc TEXT NULL,
  store_id BIGINT UNSIGNED NULL,
  warehouse_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_failed_login_at DATETIME NULL,
  locked_until DATETIME NULL,
  account_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permission_resources (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(role_id, permission_id, resource_id),
  CONSTRAINT fk_rpr_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rpr_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rpr_res FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_data_scopes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  scope_type ENUM('store','warehouse','department') NOT NULL,
  scope_value VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_scope (user_id, scope_type, scope_value),
  INDEX idx_scope_lookup (scope_type, scope_value),
  CONSTRAINT fk_user_scope_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_auth_sessions_active (expires_at, revoked_at),
  CONSTRAINT fk_auth_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  prep_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  step_count INT UNSIGNED NOT NULL DEFAULT 0,
  servings INT UNSIGNED NOT NULL DEFAULT 1,
  difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
  calories INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  popularity_score INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_recipes_status (status),
  INDEX idx_recipe_speed (prep_minutes, step_count),
  INDEX idx_recipe_budget (estimated_cost),
  INDEX idx_recipe_calories (calories),
  INDEX idx_recipe_scope (store_id, warehouse_id, department_id),
  FULLTEXT KEY ft_recipe_name_desc (name, description),
  CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ingredients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(120) NOT NULL,
  normalized_name VARCHAR(120) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ingredient_norm (normalized_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id BIGINT UNSIGNED NOT NULL,
  ingredient_name VARCHAR(120) NOT NULL,
  ingredient_name_norm VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipe_ingredient_norm (ingredient_name_norm, recipe_id),
  CONSTRAINT fk_recipe_ingredient_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_cookware (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id BIGINT UNSIGNED NOT NULL,
  cookware_norm VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipe_cookware (cookware_norm, recipe_id),
  CONSTRAINT fk_recipe_cookware_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_allergens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id BIGINT UNSIGNED NOT NULL,
  allergen_norm VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipe_allergen (allergen_norm, recipe_id),
  CONSTRAINT fk_recipe_allergen_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS search_synonyms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  synonym VARCHAR(80) NOT NULL UNIQUE,
  canonical_term VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_syn_canonical (canonical_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT '#3a7afe',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_tags (
  recipe_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(recipe_id, tag_id),
  CONSTRAINT fk_recipe_tags_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pickup_points (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  slot_size INT UNSIGNED NOT NULL DEFAULT 10,
  active TINYINT(1) NOT NULL DEFAULT 1,
  region_code VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  service_radius_km DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pickup_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(40) NOT NULL UNIQUE,
  recipe_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  pickup_point_id BIGINT UNSIGNED NOT NULL,
  pickup_at DATETIME NOT NULL,
  slot_start DATETIME NULL,
  slot_end DATETIME NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  note VARCHAR(255) NULL,
  arrived_at DATETIME NULL,
  checked_in_by BIGINT UNSIGNED NULL,
  no_show_marked_at DATETIME NULL,
  customer_zip4 VARCHAR(10) NULL,
  customer_region_code VARCHAR(20) NULL,
  customer_latitude DECIMAL(10,7) NULL,
  customer_longitude DECIMAL(10,7) NULL,
  distance_km DECIMAL(8,3) NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bookings_pickup (pickup_at),
  INDEX idx_bookings_status (status),
  INDEX idx_bookings_scope (store_id, warehouse_id, department_id),
  CONSTRAINT fk_bookings_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_pickup FOREIGN KEY (pickup_point_id) REFERENCES pickup_points(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  budget DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'planned',
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  INDEX idx_campaign_scope (store_id, warehouse_id, department_id),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  payload JSON NOT NULL,
  state VARCHAR(20) NOT NULL DEFAULT 'queued',
  dispatched_at DATETIME NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_state (state),
  INDEX idx_message_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_ref VARCHAR(60) NOT NULL UNIQUE,
  booking_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'cash',
  status VARCHAR(20) NOT NULL DEFAULT 'initiated',
  paid_at DATETIME NULL,
  payer_name_enc TEXT NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payments_status (status),
  INDEX idx_payments_scope (store_id, warehouse_id, department_id),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reconciliation (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_ref VARCHAR(60) NOT NULL UNIQUE,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  expected_total DECIMAL(10,2) NOT NULL,
  actual_total DECIMAL(10,2) NOT NULL,
  variance DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reconciliation_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(50) NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  filename VARCHAR(180) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NULL,
  magic_verified TINYINT(1) NOT NULL DEFAULT 0,
  watermarked TINYINT(1) NOT NULL DEFAULT 0,
  hotlink_token CHAR(64) NULL,
  signed_url_expire_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attachments_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NOT NULL,
  target_id VARCHAR(60) NOT NULL,
  metadata JSON NULL,
  prev_hash CHAR(64) NULL,
  hash_current CHAR(64) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_target (target_type, target_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO stores (code, name) VALUES ('S001', 'Main Store') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO warehouses (code, name) VALUES ('W001', 'Main Warehouse') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (code, name) VALUES ('D001', 'General Department') ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Default admin seed: password MUST be changed on first login.
-- The hash below corresponds to a temporary bootstrap password that must be rotated.
-- Set PANTRYPILOT_ADMIN_PASSWORD_HASH env var to override at deploy time.
INSERT INTO users (username, password_hash, display_name, role, store_id, warehouse_id, department_id)
VALUES ('admin', '$2y$10$coP6EPopPcPJgzllcZkj9uuCPeokmqbzz4Wse8bqktO0l8c4zQey.', 'System Admin', 'admin', 1, 1, 1)
ON DUPLICATE KEY UPDATE username = username;
-- WARNING: This is a bootstrap-only seed credential. Rotate immediately after first boot.

INSERT INTO roles (code, name) VALUES
('admin', 'Administrator'),
('ops_staff', 'Operations Staff'),
('manager', 'Operations Manager'),
('finance', 'Finance Admin'),
('customer', 'Customer')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO permissions (code, name) VALUES
('read', 'Read Access'),
('write', 'Write Access'),
('approve', 'Approval Access')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO resources (code, name) VALUES
('recipe', 'Recipes'),
('booking', 'Bookings'),
('booking_ops', 'Booking Operations'),
('operations', 'Operations'),
('payment', 'Payments'),
('notification', 'Notifications'),
('file', 'Files'),
('reporting', 'Reporting'),
('admin', 'Administration')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'admin' AND r.code = 'admin';

INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r
JOIN permissions p
JOIN resources rs
WHERE r.code = 'admin';

-- Customer: read recipes, read/write bookings, read notifications
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'customer' AND p.code = 'read' AND rs.code = 'recipe';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'customer' AND p.code = 'read' AND rs.code = 'booking';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'customer' AND p.code = 'write' AND rs.code = 'booking';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'customer' AND p.code = 'read' AND rs.code = 'notification';

-- Operations Staff: read recipes, read/write bookings, read notifications
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'read' AND rs.code = 'recipe';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'read' AND rs.code = 'booking';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'write' AND rs.code = 'booking';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'approve' AND rs.code = 'booking';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'read' AND rs.code = 'notification';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code IN ('read','write','approve') AND rs.code = 'booking_ops';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'read' AND rs.code = 'operations';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'ops_staff' AND p.code = 'write' AND rs.code = 'operations';

-- Manager: read/write operations, recipes, bookings, notifications, files, reporting + approve bookings
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'manager' AND p.code IN ('read','write') AND rs.code IN ('recipe','booking','operations','notification','file','reporting');
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'manager' AND p.code = 'approve' AND rs.code = 'booking';

-- Finance: read/write/approve payments, read reporting and bookings
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'finance' AND p.code IN ('read','write','approve') AND rs.code = 'payment';
INSERT IGNORE INTO role_permission_resources (role_id, permission_id, resource_id)
SELECT r.id, p.id, rs.id FROM roles r, permissions p, resources rs
WHERE r.code = 'finance' AND p.code = 'read' AND rs.code IN ('reporting','booking');

INSERT IGNORE INTO user_data_scopes (user_id, scope_type, scope_value)
SELECT id, 'store', '1' FROM users WHERE username='admin';
INSERT IGNORE INTO user_data_scopes (user_id, scope_type, scope_value)
SELECT id, 'warehouse', '1' FROM users WHERE username='admin';
INSERT IGNORE INTO user_data_scopes (user_id, scope_type, scope_value)
SELECT id, 'department', '1' FROM users WHERE username='admin';

INSERT INTO search_synonyms (synonym, canonical_term) VALUES
('garbanzo', 'chickpea'),
('chickpeas', 'chickpea'),
('tomatos', 'tomato'),
('potatos', 'potato')
ON DUPLICATE KEY UPDATE canonical_term = VALUES(canonical_term);

INSERT INTO ingredients (display_name, normalized_name) VALUES
('Chickpea', 'chickpea'),
('Tomato', 'tomato'),
('Potato', 'potato'),
('Onion', 'onion')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

INSERT INTO pickup_points (name, address, slot_size, active, store_id, warehouse_id, department_id)
VALUES
('Central Pantry', '100 Main St', 20, 1, '1', '1', '1'),
('North Community Hub', '22 Pine Ave', 12, 1, '1', '1', '1')
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE pickup_points SET region_code='REG-001', latitude=40.7128, longitude=-74.0060, service_radius_km=12.87 WHERE name='Central Pantry';
UPDATE pickup_points SET region_code='REG-001', latitude=40.7306, longitude=-73.9352, service_radius_km=12.87 WHERE name='North Community Hub';

CREATE TABLE IF NOT EXISTS pickup_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pickup_point_id BIGINT UNSIGNED NOT NULL,
  slot_start DATETIME NOT NULL,
  slot_end DATETIME NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  reserved_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_slot (pickup_point_id, slot_start, slot_end),
  INDEX idx_slot_lookup (pickup_point_id, slot_start),
  CONSTRAINT fk_pickup_slot_point FOREIGN KEY (pickup_point_id) REFERENCES pickup_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_blacklist (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  blocked_until DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_blacklist_user (user_id, active),
  CONSTRAINT fk_blacklist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS address_regions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  region_code VARCHAR(20) NOT NULL UNIQUE,
  region_name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zip4_reference (
  zip4_code VARCHAR(10) PRIMARY KEY,
  region_code VARCHAR(20) NOT NULL,
  city VARCHAR(120) NOT NULL,
  state_code VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_zip4_region (region_code),
  CONSTRAINT fk_zip4_region FOREIGN KEY (region_code) REFERENCES address_regions(region_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_modules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(50) NOT NULL,
  payload JSON NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_homepage_module_scope (module_key, store_id, warehouse_id, department_id),
  INDEX idx_homepage_module_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_code VARCHAR(60) NOT NULL,
  title VARCHAR(120) NOT NULL,
  content TEXT NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'system',
  active TINYINT(1) NOT NULL DEFAULT 1,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_message_template_scope (template_code, store_id, warehouse_id, department_id),
  INDEX idx_message_template_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_message_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  marketing_opt_out TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_msg_pref_user (user_id),
  CONSTRAINT fk_msg_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_center (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  is_marketing TINYINT(1) NOT NULL DEFAULT 0,
  sent_at DATETIME NOT NULL,
  read_at DATETIME NULL,
  clicked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_center_user (user_id, sent_at),
  CONSTRAINT fk_msg_center_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_center_template FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gateway_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(60) NOT NULL UNIQUE,
  booking_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  provider VARCHAR(30) NOT NULL DEFAULT 'wechat_local',
  transaction_ref VARCHAR(80) NULL,
  callback_payload JSON NULL,
  callback_verified TINYINT(1) NOT NULL DEFAULT 0,
  callback_processed_at DATETIME NULL,
  expire_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_gateway_pending (status, expire_at),
  UNIQUE KEY uk_gateway_tx_ref (transaction_ref),
  CONSTRAINT fk_gateway_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gateway_callbacks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_ref VARCHAR(80) NOT NULL,
  payload JSON NOT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_callback_transaction (transaction_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_reconciliation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_ref VARCHAR(60) NOT NULL,
  gateway_order_ref VARCHAR(60) NOT NULL,
  issue_type VARCHAR(30) NOT NULL,
  repaired TINYINT(1) NOT NULL DEFAULT 0,
  repaired_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recon_batch (batch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_adjustments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  adjust_amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_adjustment_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS critical_reauth_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expire_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reauth_user (user_id, expire_at),
  CONSTRAINT fk_reauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(60) NOT NULL,
  qty INT NOT NULL,
  snapshot_date DATE NOT NULL,
  store_id VARCHAR(60) NULL,
  warehouse_id VARCHAR(60) NULL,
  department_id VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stock_date (snapshot_date),
  INDEX idx_stock_scope (store_id, warehouse_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anomaly_alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_type VARCHAR(60) NOT NULL,
  severity VARCHAR(20) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alert_type (alert_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  note_text TEXT NOT NULL,
  printable_payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dispatch_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO address_regions (region_code, region_name)
VALUES
('REG-001', 'Central Region'),
('REG-002', 'North Region')
ON DUPLICATE KEY UPDATE region_name = VALUES(region_name);

INSERT INTO zip4_reference (zip4_code, region_code, city, state_code)
VALUES
('12345-6789', 'REG-001', 'Central City', 'CC'),
('12345-6790', 'REG-001', 'Central City', 'CC'),
('22345-6789', 'REG-002', 'North City', 'NC')
ON DUPLICATE KEY UPDATE
  region_code = VALUES(region_code),
  city = VALUES(city),
  state_code = VALUES(state_code);

