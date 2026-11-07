# 5-Minute Presentation Guide — The Sifted Cafe & Bakery

**One sentence:** "Customers register and reserve a table with a GCash payment proof; staff check the proof and confirm the reservation."

## 1. HTML (30 sec) — the structure
`index.html`, `login.html`, `signup.html` are the pages and forms. `account.php` and `staff.php` are also HTML, but PHP fills in the data first.

## 2. CSS (20 sec) — the looks
`css/style.css` sets colors, fonts and layout. It was not changed for this project.

## 3. JavaScript (30 sec) — the interactions
`js/` checks the forms in the browser (for example "password too short") and sends them to PHP using `fetch`, **without reloading the page**.
- `js/auth.js` sign up / log in · `js/reserve.js` reservation form · `js/account.js` history list · `js/staff.js` pop-up windows.

## 4. PHP (2 min) — the brain
| File | Job |
|---|---|
| `config/database.php` | Connects PHP to MySQL (user `root`, database `thesifted_db`). Every page uses it. |
| `api/index.php` | Receives what JavaScript sends and answers: sign up, log in, save reservation. |
| `includes/reservations.php` | Rules and database work for reservations and staff actions. |
| `staff.php` / `actions/staff-process.php` | Staff page and its buttons. |

**How PHP connects to MySQL:** `db()` in `config/database.php` opens one connection with the host, database name, username and password. We use *prepared statements* (SQL with `?` and the values sent separately) so typed text can never change the SQL.

**How a customer registers:** form → JS sends it to `api/index.php` → PHP checks the fields → saves a row in `users` with role `customer` and a **hashed** password (`password_hash`) → logs them in.

**How login works:** PHP finds the user by email, then `password_verify()` compares the typed password with the saved hash. If correct, `$_SESSION['user_id']` is saved. The session is how the server remembers who is logged in.

**How customer, staff and owner are told apart:** the `role` column in `users` (`customer`, `staff` or `owner`). Each page begins with a guard: `staff.php` lets in only `role = staff`, `owner.php` only `role = owner`; everyone else gets a 403. The owner dashboard only reads data (SELECT), so the owner can watch but not change anything.

**What the audit log is:** the `audit_logs` table records who did what and when (login, payment verified, reservation confirmed...). PHP only inserts into it, and two database triggers refuse any UPDATE or DELETE, so the history cannot be edited.

**How a reservation is saved:** PHP validates the form (date, time, guests 2–10, seats left), then saves one row in `reservations` (status `pending`) and one row in `payments`.

**How payment proof is uploaded:** the file is checked (real PNG/JPG/PDF, under 3 MB), saved with a random name in `storage/proofs/`, and only the file name is saved in `payments`. Only staff can view it (`staff-proof.php`).

**How staff confirm:** staff click a button → `actions/staff-process.php` checks "is this a staff user?" → updates the `status` column.
Status path: `pending → confirmed → completed` (or `cancelled`). Payment path: `unpaid → proof_submitted → verified`.

## 5. MySQL (1 min) — the storage (phpMyAdmin, database `thesifted_db`)
- **users** — id, name, email, phone, password_hash, **role**
- **reservations** — who (`user_id`), branch, date, time, guests, **status**, staff notes
- **payments** — which reservation, amount, proof file name, **status**, which staff verified
- **audit_logs** — append-only diary of important events
One user has many reservations; one reservation has one payment.

## Live demo order (about 1 minute of the 5)
Sign up → reserve with a proof → log out → log in as `staff@example.com` / `Staff123!` → View → Verify payment → Confirm → log in as the customer again and show status **Confirmed** → finally log in as `owner@example.com` / `Owner123!` and show the read-only Overview, Payments and Audit Log.

## Likely questions
- **Where are passwords?** Only hashes, never the real password.
- **Where is the owner account?** It is inserted by `database/thesifted_db.sql` (`INSERT IGNORE`, so importing twice never duplicates it). Only the password hash is stored.
- **Why root with no password?** XAMPP's local default for a school demo; for a real server you would create a limited user and a password.
- **Why PDO?** It is PHP's standard database tool and gives us prepared statements.
