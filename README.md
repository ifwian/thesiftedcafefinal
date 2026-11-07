# The Sifted Cafe & Bakery

A cafe reservation website. Customers register and reserve a table with a GCash payment proof, staff check the proof and confirm the reservation, and the owner watches everything on a read-only dashboard.

Built with plain HTML, CSS, JavaScript, PHP and MySQL (MariaDB). No frameworks, no extra libraries.

---

## 1. Install (XAMPP on Windows)

1. Install **XAMPP** (PHP 8.x) and open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**.
3. Copy this whole folder to `C:\xampp\htdocs\thesifted`.
4. Open `http://localhost/phpmyadmin` in your browser.
5. Click the **Import** tab, choose `database/thesifted_db.sql`, then click **Import** at the bottom.
6. Open `http://localhost/thesifted/` — the website is ready.
7. Log in with one of the demo accounts below.

That is the whole setup. **No ALTER TABLE, no migration files, no scripts to run.**
The one SQL file creates the database `thesifted_db`, the 4 tables, the audit-log protection, and the demo staff and owner accounts.

### Demo accounts (for the school demonstration only)

| Role | Email | Password | Opens |
|---|---|---|---|
| **Owner** | `owner@example.com` | `Owner123!` | `owner.php` (Owner Dashboard) |
| **Staff** | `staff@example.com` | `Staff123!` | `staff.php` (Staff Dashboard) |

These are demonstration credentials. **Change or delete them before any real use** (phpMyAdmin > `thesifted_db` > `users` table). Only a password *hash* is stored in the database, never the password itself.

Importing the SQL file a second time is safe: it adds nothing twice and does **not** reset an existing account's password or role.

### Customer account
Open `http://localhost/thesifted/`, click **Sign Up**, fill in the form, and you are logged in as a customer. Customers land on `account.php`, where they make reservations, upload the GCash proof and see their reservation history and status.

### How each role gets to its dashboard
Everyone logs in on the same login form. The `role` column in the `users` table decides where you land:
- customer → `account.php`
- staff → `staff.php`
- owner → `owner.php`

The server checks the role on every page, so typing another role's address in the browser shows "403 – not allowed".

---

## 2. Database connection settings

The defaults are XAMPP's defaults, so normally you change nothing: host `127.0.0.1`, port `3306`, database `thesifted_db`, user `root`, empty password.

If your MySQL uses a different port or you set a root password, edit the first lines of `config/database.php`
(or copy `config/database.local.example.php` to `config/database.local.php` and edit that one; it is ignored by Git).

### If the website says the database is not working
Open `http://localhost/thesifted/api/index.php/health`. It checks the setup and tells you what is wrong:

| Message | What to do |
|---|---|
| "The database is not running" | Start **MySQL** in the XAMPP Control Panel. If it will not start, another program may use port 3306: change the port in `config/database.php`. |
| "The database was not found" | Import `database/thesifted_db.sql` in phpMyAdmin (step 5 above). |
| "The database login was rejected" | The username or password in `config/database.php` is wrong. |
| "PHP cannot use MySQL" | Enable `extension=pdo_mysql` in `php.ini` (XAMPP: Apache > Config) and restart Apache. |

---

## 3. What each role can do

| Customer | Staff (`staff.php`) | Owner (`owner.php`) — **read-only** |
|---|---|---|
| Register and log in | Log in | Log in |
| Make a reservation and choose Downpayment or Full payment | View, search and filter reservations | Overview, Analytics, Payments, Reservations |
| Upload the GCash proof | Open the payment proof image | Sees payment amounts and statuses (not the proof image) |
| See reservation history and status | Verify payment, confirm, complete or cancel | Cannot confirm, cancel, verify, archive or edit anything |
| Cancel their own reservation (24 hours before) | Staff notes | Sees notes, cannot change them |
| | Archive, restore, permanently delete archived records | Views archived records |
| | | Audit Log (who did what, and when) |

Only **verified** payments (checked by staff) count as recorded money on the Owner Dashboard. A screenshot that is not verified yet is shown as "expected", never as income.

### What the Audit Log records
Staff/owner login, logout and failed login; reservation created, confirmed, completed, cancelled, archived, restored, deleted; payment verified; staff notes updated; and signed-in users trying to open owner-only pages.

---

## 4. Security (kept on purpose)

- Passwords: `password_hash()` to save, `password_verify()` to check.
- Database: PDO **prepared statements** everywhere (typed text can never change the SQL).
- Server-side validation for every form (the browser checks are only for convenience).
- Sessions with a new session ID after login; a small brake after 5 wrong passwords.
- Role checks on the server for every protected page and API call.
- Payment proof upload: the real file content is checked (PNG, JPG or PDF only, max 3 MB), the file gets a random name, and it is saved in `storage/proofs/`, which the browser cannot open directly. Only staff can view proofs.
- Audit log: the code only ever INSERTs into it, and two database **triggers** refuse any UPDATE or DELETE on `audit_logs`.
  Limit: someone with full admin rights in phpMyAdmin (`root`) can still drop the triggers. A real deployment should use a limited MySQL user.
- `.htaccess` files block the browser from opening `config/`, `includes/`, `database/` and `storage/`.

---

## 5. Folder map

| Folder / file | What it is |
|---|---|
| `*.html` | Public pages (home, menu, about, visit, login, sign up) |
| `account.php` · `staff.php` · `owner.php` | Customer, Staff and Owner pages (PHP fills in the data) |
| `staff-proof.php` | Shows an uploaded proof to staff only |
| `api/index.php` | The API the JavaScript talks to (register, login, reservations, owner data) |
| `actions/` | `staff-process.php` handles the staff buttons, `logout.php` signs out |
| `includes/` | Reusable PHP: `bootstrap.php` (start of every page), `functions.php` (login, roles, helpers), `reservations.php` (reservation and payment rules), `audit.php` (audit log writer), `owner.php` (read-only owner queries), `owner_ui.php`, `owner_detail.php`, `staff_detail.php` |
| `config/` | `database.php` (connection) and `app.php` (rules such as guests 2–10, branch hours) |
| `database/` | `thesifted_db.sql` — the **only** file you need. `migrations/` is optional and only for upgrading an old database (see `database/migrations/README.txt`) |
| `js/`, `css/`, `assets/` | JavaScript, stylesheet, images |
| `storage/` | Uploaded proofs and the error log (private; not in Git) |

Presentation help for the defense is in `PRESENTATION.md`.

---

## 6. Older databases

The SQL file is made for a **fresh** install. If you already have an old `thesifted_db`, importing the file again does **not** change its existing tables. For the school demo, the simplest fix is: phpMyAdmin > `thesifted_db` > **Operations** > **Drop the database**, then import `database/thesifted_db.sql` again. To keep old data, see `database/migrations/README.txt`.
