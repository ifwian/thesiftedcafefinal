# HANDOFF — The Sifted Cafe & Bakery

**Objective:** beginner-friendly PHP/MySQL reservation site that installs with XAMPP + phpMyAdmin only and can be explained at a school defense. Design/UI unchanged.

## State: COMPLETE (see README.md, PRESENTATION.md)
**Setup:** start Apache + MySQL -> copy folder to `C:\xampp\htdocs\thesifted` -> import `database/thesifted_db.sql` in phpMyAdmin -> open `http://localhost/thesifted/`.
Nothing else is required: the SQL file already creates the 4 tables, the audit-log triggers, the demo staff (`staff@example.com` / `Staff123!`) and the demo owner (`owner@example.com` / `Owner123!`).

## Latest change (owner seed + simplification)
- `database/thesifted_db.sql`: owner account seeded with `INSERT IGNORE` (password hash from PHP `password_hash`), table/column comments added.
- Removed `database/create_owner.php`. `database/migrations/` kept only as optional legacy upgrade files (`README.txt` explains).
- Removed the `archive_ready()` / `audit_ready()` checks and the "run migration" warning banners (a fresh import always has these tables). Files touched: `includes/reservations.php`, `includes/audit.php`, `includes/owner.php`, `staff.php`, `owner.php`.
- README / PRESENTATION / CHANGELOG updated to one setup process.
