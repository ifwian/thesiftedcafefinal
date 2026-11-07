<?php
/*
 * SMALL HELPERS used by every page: escaping text, JSON answers, login/logout,
 * who is signed in, and the rules for the sign-up form.
 */

function cfg(string $key)
{
    return $GLOBALS['sifted_config'][$key];
}

/** Escape text before printing it in HTML (stops XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_text(): string
{
    return date('Y-m-d H:i:s');
}

/** Send a JSON answer and stop. */
function json_out(int $status, array $data): void
{
    while (ob_get_level() > 0) {   // drop anything printed earlier so the answer is pure JSON
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Trim and shorten text. Empty text becomes null if $nullIfEmpty. */
function clean($value, int $max = 255, bool $nullIfEmpty = false): ?string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if (mb_strlen($text) > $max) {
        $text = mb_substr($text, 0, $max);
    }
    return ($text === '' && $nullIfEmpty) ? null : $text;
}

/** The request body as an array: JSON body, or form fields (multipart / urlencoded). */
function request_data(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== false) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function truthy($value): bool
{
    return $value === true || $value === 1 || in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------

/**
 * JSON API protection. Our own pages always send the header "X-Requested-With: fetch".
 * Another website cannot add that header to a form post, and the browser blocks
 * cross-site fetch calls with custom headers. We also compare the Origin header.
 */
function api_csrf_check(): void
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
        json_out(403, ['error' => 'Request blocked (missing security header).']);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $originHost = (string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
        // Behind a proxy or tunnel (ngrok, Codespaces...) the public host arrives in X-Forwarded-Host.
        $forwarded = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
        $sameHost = strcasecmp($originHost, (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0
            || ($forwarded !== '' && strcasecmp($originHost, $forwarded) === 0);
        if (!$sameHost) {
            json_out(403, ['error' => 'Request blocked (wrong origin).']);
        }
    }
}

/** Token for the HTML forms on the staff page. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ---------------------------------------------------------------------
// Users and sessions
// ---------------------------------------------------------------------

/** The signed-in user, read fresh from the database (so the role cannot be faked). */
function current_user(): ?array
{
    static $cache = [];
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id === 0) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $stmt = db()->prepare('SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $cache[$id] = $stmt->fetch() ?: null;
    }
    return $cache[$id];
}

function sign_in(int $userId): void
{
    session_regenerate_id(true);   // new session ID after login (stops session fixation)
    $_SESSION = ['user_id' => $userId];
}

function sign_out(): void
{
    $who = current_user();   // read BEFORE the session is cleared, so the audit log knows who left
    if ($who !== null && in_array($who['role'], ['staff', 'owner'], true)) {
        audit_log('logout', $who);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function public_user(array $u): array
{
    return [
        'full_name'    => $u['full_name'],
        'email'        => $u['email'],
        'phone'        => $u['phone'],
        'role'         => $u['role'],
        'member_since' => substr((string) $u['created_at'], 0, 10),
    ];
}

/** Page guard: staff only. Customers and guests are sent away. */
function require_staff_page(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.html?next=staff.php');
        exit;
    }
    if ($user['role'] !== 'staff') {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Staff only</title><p>This page is for cafe staff only.</p><p><a href="index.html">Back to the home page</a></p>';
        exit;
    }
    return $user;
}

/**
 * Page guard: OWNER only. Staff and customers get a 403 even if they type the address by hand.
 * (Hiding the menu link is never the protection: this check runs on the server for every request.)
 */
function require_owner_page(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.html?next=owner.php');
        exit;
    }
    if ($user['role'] !== 'owner') {
        owner_access_denied($user);
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Owner only</title><p>This page is for the cafe owner only.</p><p><a href="index.html">Back to the home page</a></p>';
        exit;
    }
    return $user;
}

/** Same guard for JSON endpoints under /api/owner/. Gives back the owner, or answers 401 / 403 and stops. */
function require_owner_api(?array $user): array
{
    if ($user === null) {
        json_out(401, ['error' => 'Please sign in first.', 'code' => 'not_signed_in']);
    }
    if ($user['role'] !== 'owner') {
        owner_access_denied($user);
        json_out(403, ['error' => 'Owner access only.', 'code' => 'forbidden']);
    }
    return $user;
}

/** A signed-in staff member or customer tried to open an owner-only address: write it down. */
function owner_access_denied(array $user): void
{
    audit_log('access_denied', $user, ['details' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 200)]);
}

/** Where each role lands after signing in. */
function home_page_for(string $role): string
{
    return ['owner' => 'owner.php', 'staff' => 'staff.php'][$role] ?? 'account.php';
}

/** Page guard: any signed-in user. */
function require_login_page(string $next): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.html?next=' . rawurlencode($next));
        exit;
    }
    return $user;
}

/** Same rules the pages check in the browser. The server always checks again. */
function registration_errors(array $d, string $normalizedEmail): array
{
    $errors = [];
    $name = clean($d['full_name'] ?? '', 100);
    $phone = clean($d['phone'] ?? '', 30);
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    $password = (string) ($d['password'] ?? '');
    if (mb_strlen($name) < 2) {
        $errors['full_name'] = 'Please enter your full name (at least 2 characters).';
    }
    if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($normalizedEmail) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^\+?[0-9\s\-()]+$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
        $errors['phone'] = 'Contact number must have 7 to 15 digits.';
    }
    if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be 8 to 72 characters with a letter and a number.';
    }
    if (($d['confirm_password'] ?? null) !== ($d['password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    return $errors;
}

/** Very small brake on password guessing: 5 wrong tries, then wait 5 minutes (per browser session). */
function login_throttled(): bool
{
    $t = $_SESSION['login_fail'] ?? ['n' => 0, 'since' => time()];
    if (time() - $t['since'] > 300) {
        unset($_SESSION['login_fail']);
        return false;
    }
    return $t['n'] >= 5;
}

function login_failed(): void
{
    $t = $_SESSION['login_fail'] ?? ['n' => 0, 'since' => time()];
    $t['n']++;
    $_SESSION['login_fail'] = $t;
}

/** Used by api/index.php/health: checks the setup piece by piece WITHOUT stopping at the first problem. */
function health_report(): array
{
    $checks = [];
    $checks['php_7_4_or_newer'] = PHP_VERSION_ID >= 70400;
    foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'json', 'session'] as $ext) {
        $checks['php_extension_' . $ext] = extension_loaded($ext);
    }
    $checks['folder_storage_proofs_writable'] = is_dir(cfg('proof_dir')) && is_writable(cfg('proof_dir'));
    $checks['folder_storage_logs_writable'] = is_dir(cfg('log_dir')) && is_writable(cfg('log_dir'));

    $database = 'ok';
    $pdo = null;
    try {
        $pdo = db_open();
    } catch (PDOException $ex) {
        $database = db_error_kind($ex);   // db_down | db_auth | db_missing | db_driver
    }
    $checks['database_connection'] = $database;

    if ($pdo !== null) {
        $need = ['users' => ['id', 'email', 'password_hash', 'role'],
                 'reservations' => ['id', 'user_id', 'status', 'archived_at', 'archived_by'],
                 'payments' => ['reservation_id', 'status', 'proof_file'],
                 'audit_logs' => ['id', 'created_at', 'actor_id', 'action']];
        foreach ($need as $table => $columns) {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $have = array_column($stmt->fetchAll(), 'COLUMN_NAME');
            $checks['table_' . $table] = $have === [] ? 'missing' : (array_diff($columns, array_map('strtolower', $have)) ? 'columns_missing' : 'ok');
        }
    }
    $good = true;
    foreach ($checks as $value) {
        if ($value !== true && $value !== 'ok') {
            $good = false;
        }
    }
    return ['ok' => $good, 'checks' => $checks];
}
