-- ============================================
-- Poultry Shop POS & Management System
-- Database Schema + Seed Data
-- ============================================

CREATE DATABASE IF NOT EXISTS poultry_form
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE poultry_form;

-- -------------------------------------------
-- 1. users
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT             AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)     NOT NULL UNIQUE,
  email         VARCHAR(100)    DEFAULT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role          ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  status        TINYINT(1)      NOT NULL DEFAULT 1,
  created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- 2. customers
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)    NOT NULL,
  phone           VARCHAR(20)     DEFAULT NULL,
  email           VARCHAR(100)    DEFAULT NULL,
  address         TEXT            DEFAULT NULL,
  opening_balance DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- 3. suppliers
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)    NOT NULL,
  phone           VARCHAR(20)     DEFAULT NULL,
  email           VARCHAR(100)    DEFAULT NULL,
  address         TEXT            DEFAULT NULL,
  opening_balance DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- 4. chicken_types
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS chicken_types (
  id          INT             AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)     NOT NULL UNIQUE,
  created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- 5. chicken_rates
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS chicken_rates (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  chicken_type_id INT             NOT NULL,
  rate_per_kg     DECIMAL(10,2)   NOT NULL,
  rate_date       DATE            NOT NULL,
  created_by      INT             DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_rate_date (rate_date),
  INDEX idx_type_date (chicken_type_id, rate_date)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 6. purchases
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  supplier_id     INT             NOT NULL,
  invoice_no      VARCHAR(50)     DEFAULT NULL,
  total_birds     INT             NOT NULL DEFAULT 0,
  total_weight    DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  purchase_rate   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  total_cost      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  purchase_date   DATE            NOT NULL,
  farm_name       VARCHAR(100)    DEFAULT NULL,
  vehicle_no      VARCHAR(50)     DEFAULT NULL,
  notes           TEXT            DEFAULT NULL,
  created_by      INT             DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_purchase_date (purchase_date)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 7. sales
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  invoice_no      VARCHAR(50)     NOT NULL UNIQUE,
  customer_id     INT             DEFAULT NULL,
  chicken_type_id INT             NOT NULL,
  birds_count     INT             NOT NULL DEFAULT 0,
  rate_per_kg     DECIMAL(10,2)   NOT NULL,
  weight          DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  amount          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  discount        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  net_total       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  paid_amount     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  balance         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  payment_method  ENUM('cash','bank','credit') NOT NULL DEFAULT 'cash',
  sale_date       DATE            NOT NULL,
  created_by      INT             DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_sale_date (sale_date),
  INDEX idx_customer (customer_id),
  INDEX idx_invoice (invoice_no)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 8. payments
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  customer_id     INT             NOT NULL,
  sale_id         INT             DEFAULT NULL,
  amount          DECIMAL(12,2)   NOT NULL,
  payment_method  ENUM('cash','bank','credit') NOT NULL DEFAULT 'cash',
  notes           TEXT            DEFAULT NULL,
  payment_date    DATE            NOT NULL,
  received_by     INT             DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_payment_date (payment_date),
  INDEX idx_payment_customer (customer_id)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 9. expenses
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
  id                INT             AUTO_INCREMENT PRIMARY KEY,
  expense_category  ENUM('labour','transport','electricity','misc') NOT NULL,
  amount            DECIMAL(12,2)   NOT NULL,
  description       TEXT            DEFAULT NULL,
  expense_date      DATE            NOT NULL,
  created_by        INT             DEFAULT NULL,
  created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_expense_date (expense_date),
  INDEX idx_expense_category (expense_category)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 10. supplier_payments
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS supplier_payments (
  id              INT             AUTO_INCREMENT PRIMARY KEY,
  supplier_id     INT             NOT NULL,
  purchase_id     INT             DEFAULT NULL,
  amount          DECIMAL(12,2)   NOT NULL,
  payment_method  ENUM('cash','bank') NOT NULL DEFAULT 'cash',
  notes           TEXT            DEFAULT NULL,
  payment_date    DATE            NOT NULL,
  created_by      INT             DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_sp_date (payment_date),
  INDEX idx_sp_supplier (supplier_id)
) ENGINE=InnoDB;

-- -------------------------------------------
-- 11. stock_ledger
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS stock_ledger (
  id                INT             AUTO_INCREMENT PRIMARY KEY,
  transaction_date  DATE            NOT NULL,
  transaction_type  ENUM('opening','purchase','sale','adjustment') NOT NULL,
  chicken_type_id   INT             NOT NULL,
  birds_count       INT             NOT NULL DEFAULT 0,
  weight_kg         DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  rate_per_kg       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  amount            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  reference_id      INT             DEFAULT NULL,
  notes             TEXT            DEFAULT NULL,
  created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_stock_date (transaction_date),
  INDEX idx_stock_type (transaction_type),
  INDEX idx_stock_chicken (chicken_type_id)
) ENGINE=InnoDB;

-- ============================================
-- SEED DATA
-- ============================================

-- Users (passwords: admin123 / cashier123)
INSERT INTO users (username, email, password_hash, role) VALUES
('admin',    'admin@poultryshop.com', 'admin123', 'admin'),
('cashier',  'cashier@poultryshop.com', 'cashier123', 'cashier');

-- Chicken types
INSERT INTO chicken_types (name) VALUES
('Broiler'),
('Layer'),
('Desi'),
('Cock');

-- Sample customer
INSERT INTO customers (name, phone, opening_balance) VALUES
('Walk-in Customer', '00000000000', 0.00);

-- ===========================================
-- Migration: Add farm_name and vehicle_no to purchases
-- ===========================================
-- If you already ran the schema before these columns were added, run:
-- ALTER TABLE purchases
--   ADD COLUMN farm_name  VARCHAR(100) DEFAULT NULL AFTER purchase_date,
--   ADD COLUMN vehicle_no VARCHAR(50)  DEFAULT NULL AFTER farm_name;

-- Sample supplier
INSERT INTO suppliers (name, phone, email, address) VALUES
('Al-Rahim Poultry Farm', '03001234567', 'alrahim@poultry.com', 'Lahore');

-- Today rate
INSERT INTO chicken_rates (chicken_type_id, rate_per_kg, rate_date, created_by)
VALUES (1, 700.00, CURDATE(), 1);

-- Opening stock
INSERT INTO stock_ledger
  (transaction_date, transaction_type, chicken_type_id, birds_count, weight_kg, rate_per_kg, amount, notes)
VALUES
   (CURDATE(), 'opening', 1, 50, 100.000, 500.00, 50000.00, 'Opening stock Broiler'),
  (CURDATE(), 'opening', 2, 30, 45.000, 600.00, 27000.00, 'Opening stock Layer');

