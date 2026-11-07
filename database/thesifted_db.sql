-- =====================================================================
-- THE SIFTED CAFE & BAKERY - the ONE and ONLY database setup file.
--
-- HOW TO USE: phpMyAdmin > Import tab > Choose file > Import. That is all.
-- It creates: the database thesifted_db, 4 tables, the audit-log protection
-- triggers, 1 demo STAFF account and 1 demo OWNER account.
-- No ALTER TABLE, no migration, no script is needed afterwards.
--
-- Safe to import again: nothing is deleted, nothing is duplicated, and the
-- demo accounts are NOT reset if they already exist.
-- (It is meant for a FRESH install. An older database with a different
--  structure needs database/migrations/, see database/migrations/README.txt.)
--
-- THE 4 TABLES AND HOW THEY CONNECT
--   users ----< reservations ---- payments
--     (one user has many reservations; one reservation has one payment)
--   audit_logs = a diary of important events (login, confirm, verify...)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS thesifted_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE thesifted_db;

-- ---------------------------------------------------------------------
-- TABLE 1: users  (customers, staff and the owner live in the same table)
-- The "role" column tells them apart: 'customer', 'staff' or 'owner'.
-- Passwords are NEVER stored as text, only as a scrambled hash (password_hash in PHP).
-- PRIMARY KEY (id): a unique number for every row, so other tables can point to one user.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(30)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','staff','owner') NOT NULL DEFAULT 'customer',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)          -- no two accounts with the same email
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE 2: reservations  (one row per table request)
-- FOREIGN KEY user_id -> users.id : every reservation belongs to a real customer.
-- Reservations and payments are SEPARATE tables because they are different things:
-- a reservation is "who/when/where", a payment is "how much/proof/verified".
-- Keeping them apart means the money data can be checked and counted on its own.
-- Archive: archived_at stays NULL for active rows. Staff "archive" = set a date (nothing is deleted).
-- status:  pending   -> the customer sent it, staff have not decided yet
--          confirmed -> staff accepted it
--          completed -> the customer's visit is done
--          cancelled -> staff cancelled it
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,                -- which customer made it (users.id)
  customer_name    VARCHAR(100) NOT NULL,
  phone            VARCHAR(30)  NOT NULL,
  email            VARCHAR(150) NOT NULL,
  branch           ENUM('calamba','cabuyao') NOT NULL,
  reservation_date DATE NOT NULL,
  reservation_time TIME NOT NULL,
  guests           TINYINT UNSIGNED NOT NULL,
  occasion         VARCHAR(100) NULL,
  allergies        VARCHAR(300) NULL,
  special_requests VARCHAR(500) NULL,
  status           ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  admin_notes      VARCHAR(500) NULL,                    -- staff notes (customers never see them)
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at      DATETIME NULL,                        -- NULL = active; a date = moved to the staff Archive
  archived_by      INT UNSIGNED NULL,                    -- which staff member archived it (users.id)
  PRIMARY KEY (id),
  KEY idx_res_archived (archived_at),
  KEY idx_res_user (user_id),
  KEY idx_res_slot (branch, reservation_date, reservation_time),
  KEY idx_res_status (status),
  CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_res_archived_by FOREIGN KEY (archived_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE 3: payments  (exactly one GCash payment per reservation)
-- FOREIGN KEY reservation_id -> reservations.id (UNIQUE = at most one payment per reservation).
-- FOREIGN KEY verified_by -> users.id : which staff member checked the payment.
-- status:  unpaid          -> no proof uploaded
--          proof_submitted -> the customer uploaded a screenshot (NOT checked yet)
--          verified        -> staff checked it against the cafe's GCash records
-- The screenshot itself is saved as a file in storage/proofs/.
-- The database only keeps the file's name.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id      INT UNSIGNED NOT NULL,             -- which reservation (reservations.id)
  method              ENUM('gcash') NOT NULL DEFAULT 'gcash',
  payment_option      ENUM('downpayment','full') NOT NULL,
  total_amount        DECIMAL(10,2) NOT NULL,
  amount_due          DECIMAL(10,2) NOT NULL,
  balance             DECIMAL(10,2) NOT NULL,
  reference_number    VARCHAR(20) NULL,
  proof_original_name VARCHAR(150) NULL,
  proof_file          VARCHAR(80)  NULL,
  proof_mime          VARCHAR(50)  NULL,
  proof_size          INT UNSIGNED NULL,
  status              ENUM('unpaid','proof_submitted','verified') NOT NULL DEFAULT 'unpaid',
  verified_by         INT UNSIGNED NULL,                 -- which staff member verified it (users.id)
  verified_at         DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pay_reservation (reservation_id),
  KEY idx_pay_status (status),
  CONSTRAINT fk_pay_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pay_verifier FOREIGN KEY (verified_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE 4: audit_logs  (append-only history of important events)
-- WHY IT MATTERS: it answers "who did what, and when?" (e.g. which staff member
-- verified a payment). A history is only trustworthy if nobody can change it,
-- so the two triggers below refuse every UPDATE and DELETE on this table.
-- There is no foreign key on purpose: the history must survive even if a user is removed.
-- ---------------------------------------------------------------------
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

-- Tamper protection INSIDE the database: audit rows can be added, never changed or removed.
--    (Even a bug or a stray query in the website code cannot edit or delete them.)
DROP TRIGGER IF EXISTS trg_audit_logs_no_update;
CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be changed';

DROP TRIGGER IF EXISTS trg_audit_logs_no_delete;
CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be deleted';

-- ---------------------------------------------------------------------
-- DEMO ACCOUNTS (so you can log in right after importing)
-- These are for the SCHOOL DEMONSTRATION ONLY. Change or delete them before any real use.
--
--   STAFF   email: staff@example.com   password: Staff123!   -> staff.php
--   OWNER   email: owner@example.com   password: Owner123!   -> owner.php
--
-- Only the HASH of each password is stored (made with PHP password_hash()),
-- never the password itself.
-- INSERT IGNORE + the UNIQUE email key = importing again adds nothing and never
-- overwrites an existing account's password or role.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO users (full_name, email, phone, password_hash, role)
VALUES ('Demo Staff', 'staff@example.com', '09000000001',
        '$2y$10$GtcQIZUSOhevWhsfPZokBOqU8pQFf7gQV/k24JB0iU2giRYz4aNG2', 'staff');

INSERT IGNORE INTO users (full_name, email, phone, password_hash, role)
VALUES ('Demo Owner', 'owner@example.com', '09000000002',
        '$2y$10$9LTQ4EXjGH3ZwVMDkCNrqe0DIjORNBA4YB5LSmQub5kIwkJbIzMmm', 'owner');
