-- LEGACY / OPTIONAL: only for upgrading an OLD database. NOT needed for a fresh install (import database/thesifted_db.sql instead).
-- =====================================================================
-- OPTIONAL SAFE REPAIR for an EXISTING thesifted_db that was created from an OLDER schema.
-- Only needed if http://localhost/thesifted/api/index.php/health reports
-- "columns_missing" for reservations. Works on XAMPP MariaDB AND MySQL 8 (Workbench).
-- It only ADDS what is missing. It never deletes or changes existing data.
-- Run in phpMyAdmin: select thesifted_db > SQL tab > paste > Go. Safe to run more than once.
-- =====================================================================
USE thesifted_db;

-- archived_at
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD COLUMN archived_at DATETIME NULL AFTER updated_at',
  'SELECT "archived_at already exists" AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'archived_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- archived_by
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD COLUMN archived_by INT UNSIGNED NULL AFTER archived_at',
  'SELECT "archived_by already exists" AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'archived_by');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- index on archived_at
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD KEY idx_res_archived (archived_at)',
  'SELECT "idx_res_archived already exists" AS info')
  FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_res_archived');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- foreign key archived_by -> users.id
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD CONSTRAINT fk_res_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT "fk_res_archived_by already exists" AS info')
  FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND CONSTRAINT_NAME = 'fk_res_archived_by');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
