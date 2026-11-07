OPTIONAL LEGACY UPGRADE FILES - NOT PART OF THE NORMAL INSTALLATION
====================================================================

For a NEW setup you do NOT need anything in this folder.
Just import database/thesifted_db.sql (it already contains the owner role,
the archive columns, the audit log, the triggers and the demo accounts).

These files exist only for someone who already has an OLD thesifted_db
(made before the owner role / archive / audit log existed) and wants to keep
its data instead of starting fresh:

  001_add_reservation_archive.sql    adds the archive columns to reservations
  002_repair_schema_safe.sql         optional repair if archive columns are missing
  003_owner_role_and_audit_log.sql   adds the 'owner' role, audit_logs table and triggers

Important: importing thesifted_db.sql into an old database does NOT change its
existing tables (CREATE TABLE IF NOT EXISTS skips tables that already exist).
For the school demonstration the simplest way is a fresh start:
  phpMyAdmin > select thesifted_db > Operations > Drop the database,
  then import database/thesifted_db.sql again.

An old database also has no demo owner. The owner demo account is added by
the main SQL file; on an old database, after running the files above, run
the owner INSERT at the bottom of thesifted_db.sql once.
