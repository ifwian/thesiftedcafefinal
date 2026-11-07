<?php

/*
 * THE API: the PHP file that the JavaScript talks to (using fetch).
 * The browser sends a request such as POST api/index.php/customer/login,
 * this file does the work (check the database, save a row, ...) and answers with JSON.
 * The part after index.php (for example /customer/login) tells us WHAT to do.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
// Keep only the part after "index.php", e.g. "/customer/login". Written as "/api/customer/login" below.
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pos = strpos($path, '/index.php');
$path = '/api' . ($pos === false ? '' : substr($path, $pos + strlen('/index.php')));

// ----- Setup check: open  http://localhost/thesifted/api/index.php/health  to see what is missing. -----
// Only answers from the same computer, and only says ok / problem (no passwords, no details).
if ($method === 'GET' && $path === '/api/health') {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        json_out(404, ['error' => 'Not found.']);
    }
    json_out(200, health_report());
}

if ($method === 'POST') {
    api_csrf_check();
}

$user = current_user();
function need_login(?array $user): array
{
    if ($user === null) {
        json_out(401, ['error' => 'Please sign in first.', 'code' => 'not_signed_in']);
    }
    return $user;
}

// ----- Sign up, log in, log out -----
if ($method === 'POST' && $path === '/api/customer/register') {
    $d = request_data();
    $email = strtolower(clean($d['email'] ?? '', 150));
    $errors = registration_errors($d, $email);
    $taken = false;
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists. Please sign in.';
            $taken = true;
        }
    }
    if ($errors) {
        json_out($taken && count($errors) === 1 ? 409 : 400, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    try {
        // The password is saved only as a hash. Every new sign-up gets the role 'customer'.
        $hash = password_hash((string) $d['password'], PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([clean($d['full_name'], 100), $email, clean($d['phone'], 30), $hash, 'customer', now_text()]);
    } catch (PDOException $ex) {
        // two people registering the same email at the same moment: the UNIQUE key stops the second one
        json_out(409, ['error' => 'Please fix the highlighted fields.', 'errors' => ['email' => 'An account with this email already exists. Please sign in.']]);
    }
    sign_in((int) db()->lastInsertId());
    json_out(201, ['customer' => public_user(current_user())]);
}

if ($method === 'POST' && $path === '/api/customer/login') {
    if (login_throttled()) {
        json_out(429, ['error' => 'Too many tries. Please wait a few minutes and try again.']);
    }
    $d = request_data();
    $email = strtolower(clean($d['email'] ?? '', 150));
    // 1. Find the user with this email.
    $stmt = db()->prepare('SELECT id, full_name, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    // 2. Compare the typed password with the saved hash. (password_verify never needs the real password.)
    $ok = $row && password_verify((string) ($d['password'] ?? ''), $row['password_hash']);
    if (!$ok) {
        login_failed();
        if ($row && in_array($row['role'], ['staff', 'owner'], true)) {   // someone guessed a staff/owner password
            audit_log('login_failed', $row, ['details' => 'Wrong password']);
        }
        // same message for "unknown email" and "wrong password"
        json_out(401, ['error' => 'Incorrect email or password.']);
    }
    // 3. Correct: remember the user in the session.
    sign_in((int) $row['id']);
    if (in_array($row['role'], ['staff', 'owner'], true)) { audit_log('login', $row); }
    json_out(200, ['customer' => public_user(current_user())]);
}

if ($method === 'POST' && $path === '/api/customer/logout') {
    sign_out();
    json_out(200, ['ok' => true]);
}

if ($method === 'GET' && $path === '/api/customer/me') {
    $u = need_login($user);
    json_out(200, ['customer' => public_user($u)]);
}

// ----- My reservations (only the signed-in user's own) -----
if ($method === 'GET' && $path === '/api/my/reservations') {
    $u = need_login($user);
    json_out(200, ['reservations' => my_reservations((int) $u['id'])]);
}

// ----- Cancel my own reservation (only before the deadline in config/app.php) -----
if ($method === 'POST' && preg_match('#^/api/reservations/(\d+)/cancel$#', $path, $m)) {
    $u = need_login($user);
    $error = customer_cancel_reservation((int) $u['id'], (int) $m[1]);
    if ($error !== '') {
        json_out(409, ['error' => $error]);
    }
    json_out(200, ['ok' => true, 'message' => 'Your reservation ' . reference_for((int) $m[1]) . ' was cancelled. If you paid a deposit, our staff will contact you about it according to the cancellation terms.']);
}

// ----- Seats left per time slot (shown next to the time choices) -----
if ($method === 'GET' && $path === '/api/availability') {
    need_login($user);
    $branch = (string) ($_GET['branch'] ?? '');
    $date = (string) ($_GET['date'] ?? '');
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!isset(cfg('branches')[$branch]) || !$d || $d->format('Y-m-d') !== $date) {
        json_out(400, ['error' => 'Choose a branch and a date.']);
    }
    json_out(200, slot_availability($branch, $date));
}

// ----- Price quote (amounts are worked out here) -----
if ($method === 'POST' && $path === '/api/reservations/quote') {
    need_login($user);
    $d = request_data();
    $g = $d['guests'] ?? '';
    if (!is_numeric($g) || (int) $g != $g || (int) $g < cfg('min_guests') || (int) $g > cfg('max_guests')) {
        $msg = 'Online reservations are for ' . cfg('min_guests') . ' to ' . cfg('max_guests') . ' guests.';
        json_out(400, ['error' => $msg, 'errors' => ['guests' => $msg]]);
    }
    json_out(200, ['quote' => compute_amounts((int) $g)]);
}

// ----- Send a reservation request (multipart form with the proof file) -----
if ($method === 'POST' && $path === '/api/reservations') {
    $u = need_login($user);
    if ($u['role'] === 'owner') {   // the Owner only watches: no booking, even through the API
        json_out(403, ['error' => 'Owner accounts are read-only and cannot make reservations.']);
    }
    // A file bigger than PHP's post_max_size arrives as an EMPTY form: say so clearly instead of a confusing error.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        json_out(413, ['error' => 'The upload is too big. Please attach a proof smaller than 3 MB.', 'errors' => ['payment_proof' => 'Proof of payment must be smaller than 3 MB.']]);
    }
    $d = request_data();
    if (clean($d['website'] ?? '') !== '') {   // hidden bot-trap field: real people leave it empty
        json_out(400, ['error' => 'Something went wrong. Please try again.']);
    }
    [$v, $errors] = validate_reservation($d);
    $pay = null; $proof = null;
    if (!isset($errors['guests'])) {
        [$pay, $payErrors, $proof] = validate_payment($d, $v['guests'], $_FILES['payment_proof'] ?? null);
        $errors += $payErrors;
    }
    if ($errors) {
        json_out(400, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    $result = create_reservation((int) $u['id'], $v, $pay, $proof);
    if (!$result['ok']) {
        json_out(409, ['error' => 'Please fix the highlighted fields.', 'errors' => $result['errors']]);
    }
    $stmt = db()->prepare(RES_SELECT . ' WHERE r.id = ?');
    $stmt->execute([$result['id']]);
    $made = shape_reservation($stmt->fetch());
    json_out(201, [
        'message' => 'Your reservation request has been sent and is waiting for staff confirmation. It is NOT confirmed yet, and your GCash payment is not verified yet. Please wait for our team to contact you.',
        'reservation' => array_intersect_key($made, array_flip([
            'reference', 'customer_name', 'branch_name', 'reservation_date', 'reservation_time', 'guests', 'status',
            'payment_status', 'payment_option', 'payment_method', 'total_amount', 'amount_due', 'balance',
        ])) + ['duration_hours' => cfg('duration_hours')],
    ]);
}

// ----- OWNER endpoints: read-only. Owner role is checked on the server for every call. -----
if (strpos($path, '/api/owner/') === 0) {
    $owner = require_owner_api($user);
    if ($method !== 'GET') {
        header('Allow: GET');
        json_out(405, ['error' => 'The owner area is read-only.']);
    }
    require_once dirname(__DIR__) . '/includes/owner_ui.php';
    [$days, $from, $to] = owner_range($_GET);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    if ($path === '/api/owner/summary') {
        $by = owner_granularity($_GET, $days);
        json_out(200, ['period' => ['days' => $days, 'from' => $from, 'to' => $to],
            'overview' => owner_overview($from, $to), 'payments' => owner_payments($from, $to, $by)]);
    }
    if ($path === '/api/owner/reservations') {
        [$total, $rows] = owner_records($_GET, $page, 20);
        $rows = array_map(fn($r) => array_intersect_key($r, array_flip(['id', 'reference', 'customer_name', 'branch', 'reservation_date', 'reservation_time',
            'guests', 'status', 'payment_status', 'payment_option', 'total_amount', 'amount_due', 'balance', 'archived_at'])), $rows);
        json_out(200, ['total' => $total, 'page' => $page, 'reservations' => $rows]);
    }
    if ($path === '/api/owner/audit') {
        [$total, $rows] = owner_audit($_GET, $page, 25);
        json_out(200, ['total' => $total, 'page' => $page, 'entries' => $rows]);
    }
}

json_out(404, ['error' => 'Not found.']);
