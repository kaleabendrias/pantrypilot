-- Migration: complete_schema_alignment
-- Adds reference tables (stores, warehouses, departments) and missing columns
-- that the reset_test_data.php script ensures but no prior migration covers.

CREATE TABLE IF NOT EXISTS stores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS warehouses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone_enc TEXT NULL,
  ADD COLUMN IF NOT EXISTS address_enc TEXT NULL;

-- Add scope columns to message_events for tenant isolation
ALTER TABLE message_events
  ADD COLUMN IF NOT EXISTS store_id VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS warehouse_id VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS department_id VARCHAR(60) NULL;

CREATE INDEX IF NOT EXISTS idx_message_scope ON message_events (store_id, warehouse_id, department_id);
