<?php
/*
 * RESERVATION LOGIC: checking the form, working out the amounts, saving a reservation
 * and its payment, listing reservations, and the staff actions (confirm, cancel, ...).
 */

function branch_name(string $branch): string
{
    return cfg('branches')[$branch]['name'] ?? $branch;
}

function reference_for(int $id): string
{
    return 'SC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** Amounts are always worked out HERE. The browser never sends amounts we trust. */
function compute_amounts(int $guests): array
{
    $fee = (int) cfg('reservation_fee');
    $perGuest = (int) cfg('minimum_spend_per_guest');
    $pct = (int) cfg('downpayment_percent');
    $minimumSpend = $guests * $perGuest;
    $total = $fee + $minimumSpend;
    $deposit = min($total, (int) ceil($total * $pct / 100));
    return [
        'guests' => $guests, 'reservation_fee' => $fee, 'minimum_spend_per_guest' => $perGuest,
        'minimum_spend' => $minimumSpend, 'total_amount' => $total, 'downpayment_percent' => $pct,
        'duration_hours' => (int) cfg('duration_hours'),
        'options' => [
            'downpayment' => ['amount_due' => $deposit, 'balance' => $total - $deposit],
            'full'        => ['amount_due' => $total,   'balance' => 0],
        ],
    ];
}

/** Checks the reservation fields. Gives back [cleanValues, errors]. */
function validate_reservation(array $d): array
{
    $errors = [];
    $v = [
        'customer_name'    => clean($d['customer_name'] ?? '', 100),
        'phone'            => clean($d['phone'] ?? '', 30),
        'email'            => strtolower(clean($d['email'] ?? '', 150)),
        'branch'           => (string) ($d['branch'] ?? ''),
        'reservation_date' => (string) ($d['reservation_date'] ?? ''),
        'reservation_time' => substr((string) ($d['reservation_time'] ?? ''), 0, 5),
        'guests'           => $d['guests'] ?? '',
        'occasion'         => clean($d['occasion'] ?? '', 100, true),
        'special_requests' => clean($d['special_requests'] ?? '', 500, true),
    ];
    $branches = cfg('branches');

    if (mb_strlen($v['customer_name']) < 2) { $errors['customer_name'] = 'Please enter your name.'; }
    $digits = preg_replace('/\D/', '', $v['phone']) ?? '';
    if (!preg_match('/^\+?[0-9\s\-()]+$/', $v['phone']) || strlen($digits) < 7 || strlen($digits) > 15) {
        $errors['phone'] = 'Contact number must have 7 to 15 digits.';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Please enter a valid email address.'; }
    if (!isset($branches[$v['branch']])) { $errors['branch'] = 'Please choose a branch.'; }

    if (!is_numeric($v['guests']) || (int) $v['guests'] != $v['guests'] || (int) $v['guests'] < cfg('min_guests') || (int) $v['guests'] > cfg('max_guests')) {
        $errors['guests'] = 'Online reservations are for ' . cfg('min_guests') . ' to ' . cfg('max_guests') . ' guests. For larger groups, please call or message the cafe.';
    } else {
        $v['guests'] = (int) $v['guests'];
    }

    $date = DateTime::createFromFormat('!Y-m-d', $v['reservation_date']);
    if (!$date || $date->format('Y-m-d') !== $v['reservation_date']) {
        $errors['reservation_date'] = 'Please choose a date.';
    } else {
        $today = new DateTime('today');
        $days = (int) $today->diff($date)->format('%r%a');
        if ($days < cfg('min_days_ahead')) {
            $errors['reservation_date'] = 'We need at least ' . cfg('min_days_ahead') . ' day notice. Please choose tomorrow or a later date.';
        } elseif ($days > cfg('max_days_ahead')) {
            $errors['reservation_date'] = 'Please choose a date within the next ' . cfg('max_days_ahead') . ' days.';
        }
    }

    if (!isset($errors['branch'])) {
        $hour = (int) substr($v['reservation_time'], 0, 2);
        $okFormat = preg_match('/^\d{2}:00$/', $v['reservation_time']) === 1;
        if (!$okFormat || $hour < $branches[$v['branch']]['first_hour'] || $hour > $branches[$v['branch']]['last_hour']) {
            $errors['reservation_time'] = 'Please pick one of the listed times for this branch (every hour while open).';
        }
    }
    return [$v, $errors];
}

/** Checks the payment part, including the uploaded proof file. Gives back [values, errors, fileInfo|null]. */
function validate_payment(array $d, int $guests, ?array $file): array
{
    $errors = [];
    $option = (string) ($d['payment_option'] ?? '');
    if (!in_array($option, ['downpayment', 'full'], true)) {
        $errors['payment_option'] = 'Please choose Downpayment or Full payment.';
    }
    if (($d['payment_method'] ?? '') !== 'gcash') {
        $errors['payment_method'] = 'GCash is the only payment method.';
    }
    $ref = preg_replace('/\s/', '', (string) ($d['payment_reference'] ?? '')) ?? '';
    if ($ref !== '' && !preg_match('/^[0-9]{6,20}$/', $ref)) {
        $errors['payment_reference'] = 'The GCash reference number can only have digits (6 to 20).';
    }
    if (!truthy($d['accept_policies'] ?? false)) {
        $errors['accept_policies'] = 'Please confirm that you read the policies.';
    }

    $info = null;
    if ($option !== '' && !isset($errors['payment_option']) && $guests > 0) {
        $due = compute_amounts($guests)['options'][$option]['amount_due'];
        $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasFile) {
            [$info, $problem] = inspect_proof($file);
            if ($problem !== null) {
                $errors['payment_proof'] = $problem;
            }
        } elseif (cfg('require_proof') && $due > 0) {
            $errors['payment_proof'] = 'Please attach your proof of payment.';
        }
    }
    return [['payment_option' => $option, 'payment_reference' => $ref === '' ? null : $ref], $errors, $info];
}

/** Looks at an uploaded file. We trust the file CONTENT (finfo), not the name or the browser's claim. */
function inspect_proof(array $file): array
{
    $max = cfg('max_proof_bytes');
    $mb = $max / 1048576;
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [null, 'Proof of payment must be smaller than ' . $mb . ' MB.'];
        default:
            return [null, 'The proof of payment could not be uploaded. Please try again.'];
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > $max) {
        return [null, 'Proof of payment must be smaller than ' . $mb . ' MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $types = cfg('proof_types');
    if (!isset($types[$mime])) {
        return [null, 'Proof of payment must be a PNG, JPG or PDF file.'];
    }
    $original = clean(basename((string) $file['name']), 150);
    return [[
        'tmp' => $file['tmp_name'], 'mime' => $mime, 'ext' => $types[$mime],
        'size' => (int) $file['size'], 'original' => $original === '' ? 'proof.' . $types[$mime] : $original,
    ], null];
}

/** The moment a reservation starts, as a DateTime. */
function reservation_start(string $date, string $time): DateTime
{
    return new DateTime($date . ' ' . substr($time, 0, 5) . ':00');
}

/** Last moment a customer may cancel online: cancel_hours_before hours before the reservation time. */
function cancel_deadline(string $date, string $time): string
{
    $d = reservation_start($date, $time);
    $d->modify('-' . (int) cfg('cancel_hours_before') . ' hours');
    return $d->format('Y-m-d H:i');
}

/** Can the customer still cancel this reservation online? (open status AND the reservation time has not started).
 *  Cancelling AFTER the free-cancel deadline is allowed, but is "late": the downpayment may be forfeited (see the terms). */
function customer_can_cancel(string $status, string $date, string $time): bool
{
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        return false;
    }
    return date('Y-m-d H:i') < reservation_start($date, $time)->format('Y-m-d H:i');
}

/** True when cancelling now is after the free-cancel deadline. */
function cancel_is_late(string $date, string $time): bool
{
    return date('Y-m-d H:i') > cancel_deadline($date, $time);
}

/**
 * A customer cancels THEIR OWN reservation. Returns '' on success or an error message.
 * Rules (the same ones the Terms show): only your own reservation, only pending/confirmed,
 * only until cancel_hours_before hours before the time. Refunds are never automatic: staff handle them.
 */
function customer_cancel_reservation(int $userId, int $reservationId): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status, reservation_date, reservation_time, admin_notes FROM reservations WHERE id = ? AND user_id = ?');
    $stmt->execute([$reservationId, $userId]);
    $r = $stmt->fetch();
    if (!$r) { return 'That reservation was not found in your account.'; }
    if (!in_array($r['status'], ['pending', 'confirmed'], true)) {
        return 'This reservation is already ' . $r['status'] . ' and cannot be cancelled.';
    }
    if (!customer_can_cancel($r['status'], $r['reservation_date'], $r['reservation_time'])) {
        return 'This reservation has already started and can no longer be cancelled online. Please contact the cafe directly.';
    }
    // The WHERE ... status IN (...) makes the change safe even if staff changed the status a moment ago.
    $note = trim((string) $r['admin_notes'] . ' [Cancelled by customer ' . now_text() . ']');
    $upd = $pdo->prepare("UPDATE reservations SET status = 'cancelled', admin_notes = ?, updated_at = ? WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')");
    $upd->execute([mb_substr($note, -500), now_text(), $reservationId, $userId]);
    if ($upd->rowCount() === 0) { return 'This reservation could not be cancelled. Please refresh the page.'; }
    audit_reservation('reservation_cancelled', current_user(), $reservationId, (string) $r['status'], 'cancelled', 'Cancelled by the customer online');
    return '';
}

/** Guests already booked in a time slot (pending + confirmed reservations count). */
function slot_guest_total(PDO $pdo, string $branch, string $date, string $time): int
{
    $sql = "SELECT COALESCE(SUM(guests), 0) FROM reservations
            WHERE branch = ? AND reservation_date = ? AND reservation_time = ? AND status IN ('pending','confirmed')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch, $date, $time . ':00']);
    return (int) $stmt->fetchColumn();
}

/**
 * Saves a reservation + its payment (and the proof file) together.
 * Returns ['ok'=>true,'id'=>..] or ['ok'=>false,'errors'=>[...]].
 */
function create_reservation(int $userId, array $v, array $pay, ?array $proof): array
{
    $pdo = db();
    $amounts = compute_amounts($v['guests']);
    $due = $amounts['options'][$pay['payment_option']]['amount_due'];
    $balance = $amounts['options'][$pay['payment_option']]['balance'];

    $storedName = null;
    $target = null;
    if ($proof !== null) {
        $storedName = bin2hex(random_bytes(16)) . '.' . $proof['ext'];
        $target = cfg('proof_dir') . '/' . $storedName;
        if (!is_dir(cfg('proof_dir'))) { @mkdir(cfg('proof_dir'), 0775, true); }
        if (!move_uploaded_file($proof['tmp'], $target)) {
            return ['ok' => false, 'errors' => ['payment_proof' => 'The proof of payment could not be saved. Please try again.']];
        }
    }

    try {
        $pdo->beginTransaction();
        $used = slot_guest_total($pdo, $v['branch'], $v['reservation_date'], $v['reservation_time']);
        if ($used + $v['guests'] > cfg('capacity_per_slot')) {
            $pdo->rollBack();
            if ($target) { @unlink($target); }
            return ['ok' => false, 'full' => true, 'errors' => ['reservation_time' => 'Sorry, that time is fully booked for this branch. Please choose another time or date.']];
        }
        // Same customer, same branch/date/time, still open: almost certainly a double click or a repeat.
        $dup = $pdo->prepare("SELECT 1 FROM reservations WHERE user_id = ? AND branch = ? AND reservation_date = ? AND reservation_time = ? AND status IN ('pending','confirmed')");
        $dup->execute([$userId, $v['branch'], $v['reservation_date'], $v['reservation_time'] . ':00']);
        if ($dup->fetchColumn()) {
            $pdo->rollBack();
            if ($target) { @unlink($target); }
            return ['ok' => false, 'errors' => ['reservation_time' => 'You already have an open request for this branch, date and time. Check My account before sending another one.']];
        }
        $now = now_text();
        $pdo->prepare('INSERT INTO reservations
            (user_id, customer_name, phone, email, branch, reservation_date, reservation_time, guests, occasion, allergies, special_requests, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId, $v['customer_name'], $v['phone'], $v['email'], $v['branch'], $v['reservation_date'],
                $v['reservation_time'] . ':00', $v['guests'], $v['occasion'], null, $v['special_requests'], 'pending', $now, $now]);
        $id = (int) $pdo->lastInsertId();
        // A proof only means "proof_submitted". It is NEVER "verified" until staff check the cafe's GCash records.
        $payStatus = $proof !== null ? 'proof_submitted' : 'unpaid';
        $pdo->prepare('INSERT INTO payments
            (reservation_id, method, payment_option, total_amount, amount_due, balance, reference_number, proof_original_name, proof_file, proof_mime, proof_size, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, 'gcash', $pay['payment_option'], $amounts['total_amount'], $due, $balance, $pay['payment_reference'],
                $proof['original'] ?? null, $storedName, $proof['mime'] ?? null, $proof['size'] ?? null, $payStatus, $now]);
        audit_reservation('reservation_created', current_user(), $id, null, 'pending',
            $v['branch'] . ', ' . $v['reservation_date'] . ' ' . $v['reservation_time'] . ', ' . $v['guests'] . ' guests; payment ' . $pay['payment_option'] . ' (' . $payStatus . ')');
        $pdo->commit();
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($target) { @unlink($target); }
        throw $ex;
    }
}

const RES_SELECT = "SELECT r.*, p.payment_option, p.method AS payment_method, p.total_amount, p.amount_due, p.balance,
        p.reference_number AS payment_reference, p.proof_original_name AS proof_name, p.proof_file, p.status AS payment_status,
        p.verified_at, u.full_name AS verified_by_name
    FROM reservations r
    LEFT JOIN payments p ON p.reservation_id = r.id
    LEFT JOIN users u ON u.id = p.verified_by";

/** Adds the fields the pages expect. */
function shape_reservation(array $r, bool $forStaff = false): array
{
    $r['id'] = (int) $r['id'];
    $r['guests'] = (int) $r['guests'];
    $r['reference'] = reference_for($r['id']);
    $r['branch_name'] = branch_name($r['branch']);
    $r['reservation_time'] = substr((string) $r['reservation_time'], 0, 5);
    foreach (['total_amount', 'amount_due', 'balance'] as $k) {
        $r[$k] = $r[$k] === null ? 0 : (float) $r[$k] + 0;
    }
    $r['payment_method'] = $r['payment_method'] ?? 'gcash';
    $r['payment_status'] = $r['payment_status'] ?? 'unpaid';
    $r['has_proof'] = !empty($r['proof_file']);
    // Tells the customer page whether the Cancel button may be shown (the server checks again on cancel).
    $r['can_cancel'] = customer_can_cancel($r['status'], $r['reservation_date'], $r['reservation_time']);
    $r['cancel_late'] = $r['can_cancel'] && cancel_is_late($r['reservation_date'], $r['reservation_time']);
    $r['cancel_deadline'] = cancel_deadline($r['reservation_date'], $r['reservation_time']);
    if (!$forStaff) {
        unset($r['admin_notes'], $r['user_id'], $r['archived_at'], $r['archived_by'], $r['proof_file'], $r['verified_by_name'], $r['verified_at']);
    }
    return $r;
}

function my_reservations(int $userId): array
{
    $stmt = db()->prepare(RES_SELECT . ' WHERE r.user_id = ? ORDER BY r.reservation_date DESC, r.reservation_time DESC');
    $stmt->execute([$userId]);
    $today = date('Y-m-d');
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = shape_reservation($row);
        $open = in_array($item['status'], ['pending', 'confirmed'], true);
        $item['phase'] = ($open && $item['reservation_date'] >= $today) ? 'upcoming' : 'history';
        $out[] = $item;
    }
    return $out;
}

/** $archived = false: the active list. $archived = true: only archived reservations. */
function staff_reservations(array $filters, bool $archived = false): array
{
    $where = [];
    $args = [];
    if ($archived) {
        $where[] = 'r.archived_at IS NOT NULL';
    } else {
        $where[] = 'r.archived_at IS NULL';
    }
    if (in_array($filters['status'] ?? '', ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
        $where[] = 'r.status = ?'; $args[] = $filters['status'];
    }
    if (isset(cfg('branches')[$filters['branch'] ?? ''])) {
        $where[] = 'r.branch = ?'; $args[] = $filters['branch'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['date'] ?? ''))) {
        $where[] = 'r.reservation_date = ?'; $args[] = $filters['date'];
    }
    if (in_array($filters['payment'] ?? '', ['unpaid', 'proof_submitted', 'verified'], true)) {
        $where[] = 'p.status = ?'; $args[] = $filters['payment'];
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';   // '!' is the escape character (a backslash would be a syntax error in MySQL)
        $where[] = "(r.customer_name LIKE ? ESCAPE '!' OR r.phone LIKE ? ESCAPE '!' OR r.email LIKE ? ESCAPE '!')";
        array_push($args, $like, $like, $like);
    }
    $sql = RES_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ($archived ? ' ORDER BY r.archived_at DESC, r.id DESC LIMIT 300'
                      : ' ORDER BY r.reservation_date ASC, r.reservation_time ASC, r.id ASC LIMIT 300');
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return array_map(fn($r) => shape_reservation($r, true), $stmt->fetchAll());
}

function staff_counts(): array
{
    $c = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0, 'today' => 0, 'proofs_to_check' => 0, 'archived' => 0];
    // Archived records are not counted as active.
    $c['archived'] = (int) db()->query('SELECT COUNT(*) FROM reservations WHERE archived_at IS NOT NULL')->fetchColumn();
    foreach (db()->query('SELECT status, COUNT(*) n FROM reservations WHERE archived_at IS NULL GROUP BY status')->fetchAll() as $row) {
        $c[$row['status']] = (int) $row['n'];
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_date = ? AND status IN ('pending','confirmed')");
    $stmt->execute([date('Y-m-d')]);
    $c['today'] = (int) $stmt->fetchColumn();
    $c['proofs_to_check'] = (int) db()->query("SELECT COUNT(*) FROM payments p JOIN reservations r ON r.id = p.reservation_id WHERE p.status = 'proof_submitted' AND r.status IN ('pending','confirmed')")->fetchColumn();
    return $c;
}

/**
 * Allowed status moves (staff only):
 *   pending   -> confirmed | cancelled
 *   confirmed -> completed | cancelled
 * completed and cancelled are final.
 */
function allowed_next_statuses(string $status): array
{
    return ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled']][$status] ?? [];
}

function staff_set_status(int $reservationId, string $newStatus, ?array $actor = null): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $current = $stmt->fetchColumn();
    if ($current === false) { return 'That reservation was not found.'; }
    if (!in_array($newStatus, allowed_next_statuses((string) $current), true)) {
        return 'A ' . $current . ' reservation cannot be changed to ' . $newStatus . '.';
    }
    $upd = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = ? WHERE id = ? AND status = ?');
    $upd->execute([$newStatus, now_text(), $reservationId, $current]);   // "AND status = old" stops two staff changing it at the same moment
    if ($upd->rowCount() === 0) { return 'This reservation was just changed by someone else. Please reload the page.'; }
    audit_reservation('reservation_' . $newStatus, $actor, $reservationId, (string) $current, $newStatus);
    return '';
}

function staff_set_notes(int $reservationId, string $notes, ?array $actor = null): string
{
    $stmt = db()->prepare('UPDATE reservations SET admin_notes = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([clean($notes, 500, true), now_text(), $reservationId]);
    if ($stmt->rowCount() > 0) { audit_reservation('notes_updated', $actor, $reservationId); }   // the note text itself is not copied into the log
    return '';
}

/**
 * Archive (staff only): hides a FINISHED reservation (completed or cancelled) from the active list.
 * Nothing is deleted: the reservation, its payment row and its proof file all stay in the database.
 * Pending/confirmed ones cannot be archived because they still hold a table.
 */
function staff_archive_reservation(int $reservationId, int $staffId, ?array $actor = null): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status, archived_at FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch();
    if (!$row) { return 'That reservation was not found.'; }
    if ($row['archived_at'] !== null) { return 'That reservation is already archived.'; }
    if (!in_array($row['status'], ['completed', 'cancelled'], true)) {
        return 'Only completed or cancelled reservations can be archived. Complete or cancel it first.';
    }
    $pdo->prepare('UPDATE reservations SET archived_at = ?, archived_by = ? WHERE id = ? AND archived_at IS NULL')
        ->execute([now_text(), $staffId, $reservationId]);
    audit_reservation('reservation_archived', $actor, $reservationId, (string) $row['status'], (string) $row['status'], 'Moved to the Archive');
    return '';
}

/** Restore (staff only): puts an archived reservation back in the active list, exactly as it was. */
function staff_restore_reservation(int $reservationId, ?array $actor = null): string
{
    $stmt = db()->prepare('UPDATE reservations SET archived_at = NULL, archived_by = NULL WHERE id = ? AND archived_at IS NOT NULL');
    $stmt->execute([$reservationId]);
    if ($stmt->rowCount() === 0) { return 'That reservation is not in the archive.'; }
    audit_reservation('reservation_restored', $actor, $reservationId, null, null, 'Returned to the active list');
    return '';
}

/**
 * Permanent delete (staff only). ONLY an archived reservation can be deleted; active ones are refused here
 * on the server, whatever the page sends. The payment row is removed with it (and its uploaded proof file),
 * inside one transaction, so no orphaned payment or proof is left behind.
 */
function staff_delete_archived_reservation(int $reservationId, ?array $actor = null): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT r.archived_at, r.status, r.customer_name, r.branch, r.reservation_date, p.status AS pay_status, p.proof_file FROM reservations r LEFT JOIN payments p ON p.reservation_id = r.id WHERE r.id = ?');
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch();
    if (!$row) { return 'That reservation was not found.'; }
    if ($row['archived_at'] === null) { return 'Only archived reservations can be deleted. Archive it first.'; }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM payments WHERE reservation_id = ?')->execute([$reservationId]);
        $del = $pdo->prepare('DELETE FROM reservations WHERE id = ? AND archived_at IS NOT NULL');
        $del->execute([$reservationId]);
        if ($del->rowCount() !== 1) { $pdo->rollBack(); return 'That reservation could not be deleted.'; }
        // Written in the SAME transaction: the deletion and its audit entry stand or fall together.
        audit_reservation('reservation_deleted', $actor, $reservationId, (string) $row['status'], null,
            'Permanently deleted: ' . $row['customer_name'] . ', ' . $row['branch'] . ' ' . $row['reservation_date'] . ', payment ' . ($row['pay_status'] ?? 'none'));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return 'That reservation could not be deleted. Nothing was changed.';
    }
    // The database rows are gone; now remove the uploaded proof file (basename() keeps it inside storage/proofs).
    if (!empty($row['proof_file'])) {
        $file = cfg('proof_dir') . '/' . basename((string) $row['proof_file']);
        if (is_file($file)) { @unlink($file); }
    }
    return '';
}

/** Staff marks a proof as checked against the cafe's real GCash records. */
function staff_verify_payment(int $reservationId, int $staffId, ?array $actor = null): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status FROM payments WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    $current = $stmt->fetchColumn();
    if ($current !== 'proof_submitted') {
        return 'Only a payment with an uploaded proof can be marked as verified.';
    }
    $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = ? WHERE reservation_id = ?")
        ->execute([$staffId, now_text(), $reservationId]);
    audit_log('payment_verified', $actor, ['entity_type' => 'payment', 'entity_id' => $reservationId, 'ref' => reference_for($reservationId),
        'old' => 'proof_submitted', 'new' => 'verified']);
    return '';
}


/** Seats left for every hourly slot of a branch on a date (pending + confirmed count as taken). */
function slot_availability(string $branch, string $date): array
{
    $b = cfg('branches')[$branch];
    $stmt = db()->prepare("SELECT reservation_time, SUM(guests) AS taken FROM reservations
        WHERE branch = ? AND reservation_date = ? AND status IN ('pending','confirmed') GROUP BY reservation_time");
    $stmt->execute([$branch, $date]);
    $taken = [];
    foreach ($stmt->fetchAll() as $row) {
        $taken[substr((string) $row['reservation_time'], 0, 5)] = (int) $row['taken'];
    }
    $slots = [];
    for ($h = $b['first_hour']; $h <= $b['last_hour']; $h++) {
        $key = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
        $slots[$key] = max(0, cfg('capacity_per_slot') - ($taken[$key] ?? 0));
    }
    return ['capacity' => cfg('capacity_per_slot'), 'seats_left' => $slots];
}
