-- LEGACY / OPTIONAL: only for upgrading an OLD database. NOT needed for a fresh install (import database/thesifted_db.sql instead).
-- =====================================================================
-- MIGRATION 003: Owner role + persistent Audit Log.
-- Run ONCE on an EXISTING thesifted_db (phpMyAdmin: select thesifted_db > SQL tab > paste > Go).
-- It only ADDS things. No customer, reservation or payment row is changed or deleted.
-- Safe to run twice. Works on XAMPP MariaDB and MySQL 8.
-- (A brand-new install does NOT need this file: database/thesifted_db.sql already contains it.)
-- =====================================================================
USE thesifted_db;

-- 1) The users.role column gets a third value, 'owner'. Existing 'customer' and 'staff' rows stay as they are.
ALTER TABLE users
  MODIFY role ENUM('customer','staff','owner') NOT NULL DEFAULT 'customer';

-- 2) The audit log: one row per important event, written by the PHP backend (includes/audit.php).
--    actor_name / entity_ref are COPIES of the name and reference at that moment, so the history stays
--    readable even if a user or a reservation is later removed.
CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at  DATETIME NOT NULL,
  actor_id    INT UNSIGNED NULL,            -- users.id of who did it (NULL = system)
  actor_name  VARCHAR(100) NULL,
  actor_role  VARCHAR(10)  NULL,            -- customer | staff | owner
  action      VARCHAR(40)  NOT NULL,        -- e.g. reservation_confirmed, payment_verified, login
  entity_type VARCHAR(20)  NULL,            -- reservation | payment | user
  entity_id   INT UNSIGNED NULL,
  entity_ref  VARCHAR(40)  NULL,            -- e.g. SC-00012
  old_value   VARCHAR(40)  NULL,            -- previous status, when applicable
  new_value   VARCHAR(40)  NULL,            -- new status, when applicable
  details     VARCHAR(500) NULL,
  ip_address  VARCHAR(45)  NULL,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_action (action),
  KEY idx_audit_actor (actor_id),
  KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Tamper protection INSIDE the database: audit rows can be added, never changed or removed.
--    (Even a bug or a stray query in the website code cannot edit or delete them.)
DROP TRIGGER IF EXISTS trg_audit_logs_no_update;
CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be changed';

DROP TRIGGER IF EXISTS trg_audit_logs_no_delete;
CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be deleted';
