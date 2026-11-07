-- LEGACY / OPTIONAL: only for upgrading an OLD database. NOT needed for a fresh install (import database/thesifted_db.sql instead).
-- Adds the Archive feature to an EXISTING thesifted_db.
-- Run it ONCE in phpMyAdmin: select thesifted_db > SQL tab > paste > Go.
-- No reservation, payment or user data is changed. All current reservations stay active (archived_at = NULL).
-- (If you get "Duplicate column name 'archived_at'" the migration was already applied.)

USE thesifted_db;

ALTER TABLE reservations
  ADD COLUMN archived_at DATETIME NULL AFTER updated_at,
  ADD COLUMN archived_by INT UNSIGNED NULL AFTER archived_at,
  ADD KEY idx_res_archived (archived_at),
  ADD CONSTRAINT fk_res_archived_by FOREIGN KEY (archived_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE;
