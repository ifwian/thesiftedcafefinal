<?php
/*
 * Handles the buttons on the staff dashboard: confirm, cancel, complete, verify payment,
 * save notes, archive, restore, delete (archived records only). Only a logged-in STAFF user may use it (the Owner is read-only and gets a 403 here), and only with POST.
 * (The hidden "csrf" value proves the click came from our own page, not another website.)
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = current_user();
if ($user === null || $user['role'] !== 'staff') {
    http_response_code(403);
    exit('Staff only.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid request. Go back, reload the page and try again.');
}

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$error = '';
$okText = '';

switch ($action) {
    case 'confirmed':
    case 'completed':
    case 'cancelled':
        $error = staff_set_status($id, $action, $user);
        $okText = 'Reservation ' . reference_for($id) . ' is now ' . $action . '.';
        break;
    case 'archive':
        $error = staff_archive_reservation($id, (int) $user['id'], $user);
        $okText = 'Reservation ' . reference_for($id) . ' was moved to the Archive.';
        break;
    case 'restore':
        $error = staff_restore_reservation($id, $user);
        $okText = 'Reservation ' . reference_for($id) . ' was restored to the active list.';
        break;
    case 'delete':
        $ref = reference_for($id);   // read the reference before the row is gone
        $error = staff_delete_archived_reservation($id, $user);
        $okText = 'Reservation ' . $ref . ' was permanently deleted.';
        break;
    case 'verify':
        $error = staff_verify_payment($id, (int) $user['id'], $user);
        $okText = 'Payment for ' . reference_for($id) . ' was marked as verified.';
        break;
    case 'notes':
        $error = staff_set_notes($id, (string) ($_POST['notes'] ?? ''), $user);
        $okText = 'Notes saved.';
        break;
    default:
        $error = 'Unknown action.';
}

$_SESSION['flash'] = $error === '' ? ['type' => 'ok', 'text' => $okText] : ['type' => 'error', 'text' => $error];

// only keep known filter keys when going back (no open redirect: always staff.php)
parse_str((string) ($_POST['return'] ?? ''), $back);
$keep = array_intersect_key($back, array_flip(['id', 'view', 'status', 'branch', 'payment', 'date', 'q']));
header('Location: ../staff.php' . ($keep ? '?' . http_build_query($keep) : '') . '#detail');
exit;
