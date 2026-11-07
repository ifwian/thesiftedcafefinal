# CHANGELOG — bugfix package (thesifted-bugfix-final)

## Beginner-friendly final version
- **Owner account is now created by the main SQL file** (`owner@example.com` / `Owner123!`, demo only). Safe to import twice; never overwrites an existing account.
- Removed `database/create_owner.php` and every "run migration 003" instruction. `database/migrations/` is now optional legacy upgrade material only.
- Simplified code: removed `archive_ready()` and `audit_ready()` and their warning banners (fresh install always has the tables). Fewer branches, easier to explain.
- `database/thesifted_db.sql`: added plain-language comments for every table, key and the audit log.
- README rewritten with one setup path.

## Root causes
1. **The error message hid the real problem.** `js/shared.js` showed *"We could not reach the server…"* for **every** failure: a real network failure, but also any reply that was not valid JSON (a 404/405/501 HTML page from a server without PHP, PHP source code returned as text, a blank 500 from a PHP fatal error, a warning or blank line mixed into the JSON). So Sign up, Log in and every page call looked identical, and the real cause was impossible to see.
2. **The server could still answer with non-JSON.** A PHP fatal error, or any stray warning/echo/blank line, produced an empty or contaminated response. Database connection problems all shared one message and no port setting existed (a 3307 MySQL, common when MySQL Workbench's own server is installed, could not be configured).
3. **GitHub does not run PHP.** After pushing to GitHub, opening the pages from GitHub Pages, Live Server or as a file produces exactly the status the old code mapped to that message. (This is the most likely trigger, but I could not see your machine; see "Remaining limitations".)
4. **Repo hygiene:** real customer payment screenshots (`storage/proofs/*.jpg`) were committed, there was no `.gitignore`/`.gitattributes`, and 4 PHP files had mixed CRLF/LF endings.

## What was verified
On a clean XAMPP-equivalent (Apache 2.4 + mod_php 8.3 + MariaDB 10.11, real `.htaccess` enabled) the original code worked: register, login, sessions and all pages responded correctly. So the application logic was not broken; the problem is environment-dependent and was being masked by the message.

## Fixes
- `js/shared.js`: new `callApi()`; separates *network failure*, *page opened as file*, *PHP not executed / wrong server (404/405/501/403)*, *PHP source returned*, *server error (5xx)*, *unexpected answer*; logs details to the browser console; sends `credentials: 'same-origin'`. Still returns `status: 0` for these cases so existing page code keeps working.
- `includes/bootstrap.php`: output buffering (no stray output can corrupt JSON); shutdown handler turns PHP fatal errors into a clean JSON/HTML error and logs them; error responses include a `code`.
- `includes/functions.php`: `json_out()` discards earlier output; CSRF origin check also accepts `X-Forwarded-Host` (proxies/tunnels); new `health_report()`.
- `config/database.php`: `$db_port` setting; optional `config/database.local.php` override; connection errors are classified (`db_down`, `db_auth`, `db_missing`, `db_driver`) with clear non-sensitive messages; details only in the log.
- `api/index.php`: new `GET /api/index.php/health` setup check (local computer only; booleans/short codes, no secrets).
- `config/.htaccess`, `includes/.htaccess`, `storage/.htaccess`: also work on Apache 2.2/hosts without `mod_authz_core`.

## Files
- **Modified:** `js/shared.js`, `includes/bootstrap.php`, `includes/functions.php`, `config/database.php`, `api/index.php`, `config/.htaccess`, `includes/.htaccess`, `storage/.htaccess`, `README.md` (rewritten).
- **New:** `CHANGELOG.md`, `.gitignore`, `.gitattributes`, `config/database.local.example.php`, `database/migrations/002_repair_schema_safe.sql`.
- **Removed from this package:** the 5 customer proof images in `storage/proofs/` (private data; your local copies are untouched if you copy over, do not delete that folder).
- No UI, CSS, HTML or branding changes. Existing features and file structure are unchanged.

## Database
No schema change for a fresh install. `database/migrations/002_repair_schema_safe.sql` is an optional, repeatable (idempotent) repair for older databases missing the archive columns; works on MariaDB and MySQL 8; adds only, never deletes. Tested twice in a row on an old-schema database.

## Features tested (automated, 46 checks, all passed; Apache + PHP 8.3 + MariaDB)
Register (success, duplicate 409, invalid 400), login (staff and customer, wrong password 401), `me`, logout, session persistence, protected pages (account.php, staff.php redirect guests, 403 for customers), availability, quote, reservation creation with proof upload, missing-proof rejection, reservation history, customer cancel, staff dashboard and detail view, confirm / verify payment / notes / archive / archive view / restore / complete / cancel, staff proof viewer (staff only), blocked access to `config/`, `includes/`, `storage/`, `.sql`, `.md`.
Failure scenarios tested: stray output + warnings, PHP fatal error, MySQL port wrong (down), wrong password, missing database, health endpoint; client messages tested in Node for network down, 404/405, PHP source, blank 500, `file:`, and normal JSON.

## Remaining limitations / not tested
- **I cannot see your computer**, so I could not confirm which of the causes above hit you. Open `http://localhost/thesifted/api/index.php/health` and read the new message on the page: it now names the cause.
- No real browser was available: page rendering, browser console and the JavaScript UI flows were not exercised in a browser (only the API with real HTTP and `shared.js` in Node).
- Not run on PHP 7.4 or on Windows/XAMPP itself (tested on Linux, PHP 8.3). Code avoids PHP 8-only syntax as far as I could check by reading.
- Not tested with MySQL 8 (only MariaDB); the migration is written to work on both.

## Owner dashboard + audit log
- New read-only Owner dashboard (`owner.php`), owner role, `/api/owner/*` (GET only), persistent append-only `audit_logs` (migration 003).
- Staff sidebar no longer contains filters; filters live above the reservation table ("Today" shortcut added).
- Audit hooks added to reservation/payment operations and sign-in/out. Status changes now use `WHERE status = old` to avoid double changes.

## Final review
- Staff page: Confirm, Mark completed and Verify payment now ask "are you sure?" before saving (staff.php, includes/staff_detail.php).
- Hero: removed the scroll-down mouse icon; added small hover/tap polish in css/style.css; mobile checked at 320-414px.
