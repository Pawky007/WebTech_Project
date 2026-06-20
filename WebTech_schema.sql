-- ============================================================
--  HaulPro (WebTech_Project) — Database schema
--  Database name expected by db.php: webtech_project
--
--  Usage:
--    mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS webtech_project CHARACTER SET utf8mb4;"
--    mysql -u root -p webtech_project < schema.sql
--
--  Notes:
--   * users, lorry_owners, drives, billing_cycles, billing_payments
--     and billing_prefs are auto-created by the app, but only AFTER a
--     user logs in. They are included here so a single import sets up
--     everything up front.
--   * truck_data, trip_history and trips are NOT auto-created by the
--     PHP code, so importing this file is required for the truck list,
--     trip history and dashboard/analytics pages to work.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL UNIQUE,
  full_name     VARCHAR(140) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Drivers ("drives")
CREATE TABLE IF NOT EXISTS drives (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id    BIGINT NOT NULL,
  full_name  VARCHAR(120) NOT NULL,
  contact    VARCHAR(50)  NOT NULL,
  license_no VARCHAR(80)  DEFAULT NULL,
  address    VARCHAR(255) DEFAULT NULL,
  status     ENUM('Active','Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lorry owners / vehicles
CREATE TABLE IF NOT EXISTS lorry_owners (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT,
  vehicle_no VARCHAR(80)  NOT NULL,
  owner_type VARCHAR(40)  NOT NULL,
  owner_name VARCHAR(120) DEFAULT NULL,
  truck_type VARCHAR(60)  NOT NULL,
  status     VARCHAR(40)  DEFAULT 'Available',
  driver_id  BIGINT DEFAULT NULL,
  contact    VARCHAR(50)  DEFAULT NULL,
  address    VARCHAR(255) DEFAULT NULL,
  capacity   DECIMAL(10,2) DEFAULT NULL,
  notes      TEXT DEFAULT NULL,
  user_id    BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id), INDEX (driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Truck fleet board (note: column names contain spaces, as used by the app)
CREATE TABLE IF NOT EXISTS truck_data (
  id                         BIGINT PRIMARY KEY AUTO_INCREMENT,
  `Reg Number`               VARCHAR(60),
  `Driver`                   VARCHAR(120),
  `Driver Phone`             VARCHAR(40),
  `Status`                   VARCHAR(40),
  `Truck Type`               VARCHAR(60),
  `Current Load Description` VARCHAR(160) DEFAULT NULL,
  `Current Location`         VARCHAR(120),
  `ETA to Depot`             VARCHAR(60)  DEFAULT NULL,
  `Notes`                    VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trip history ledger (column names contain spaces, as used by the app)
CREATE TABLE IF NOT EXISTS trip_history (
  id                     BIGINT PRIMARY KEY AUTO_INCREMENT,
  `Trip No`              VARCHAR(30),
  `Date (BD)`            VARCHAR(40),     -- store as YYYY-MM-DD for the range filter
  `Route`                VARCHAR(160),
  `Trip Type`            VARCHAR(60),
  `Distance (km)`        INT,
  `Rent / Revenue (BDT)` DECIMAL(12,2),
  `Expense (BDT)`        DECIMAL(12,2),
  `Profit (BDT)`         DECIMAL(12,2),
  user_id                BIGINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Operational trips (drives the dashboard KPIs)
CREATE TABLE IF NOT EXISTS trips (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT NULL,
  truck_id    BIGINT NULL,
  driver_id   BIGINT NULL,
  trip_status VARCHAR(30) DEFAULT 'Pending', -- Pending/Accepted/Pickup/Completed/Cancelled
  trip_date   DATE NULL,
  amount      DECIMAL(12,2) DEFAULT 0,
  revenue_bdt DECIMAL(12,2) DEFAULT 0,
  origin      VARCHAR(120),
  destination VARCHAR(120),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Monthly billing
CREATE TABLE IF NOT EXISTS billing_cycles (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT NOT NULL,
  year        SMALLINT NOT NULL,
  month       TINYINT  NOT NULL,
  amount_due  DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_payments (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT NOT NULL,
  year        SMALLINT NOT NULL,
  month       TINYINT  NOT NULL,
  paid_date   DATE NOT NULL,
  method      VARCHAR(40) NOT NULL,        -- bkash/nagad/bank/cash/etc.
  method_ref  VARCHAR(64) DEFAULT NULL,
  txn_no      VARCHAR(80) NOT NULL,
  amount_bdt  DECIMAL(12,2) NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT NULL,
  reviewed_at TIMESTAMP NULL,
  review_note VARCHAR(255) NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id), INDEX (paid_date), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_prefs (
  user_id    BIGINT PRIMARY KEY,
  currency   VARCHAR(8) DEFAULT 'BDT',
  email      VARCHAR(160) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
