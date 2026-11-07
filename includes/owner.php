<?php
/*
 * OWNER DASHBOARD: the numbers. Every function here only READS (SELECT). There is no INSERT,
 * UPDATE or DELETE on reservations or payments in this file, so the Owner cannot change records.
 * All figures come straight from the database: nothing is hardcoded.
 */

/** Period chosen in the page: 7, 30, 90 or 365 days ending today. Gives [days, fromDate, toDate]. */
function owner_range(array $q): array
{
    $days = (int) ($q['range'] ?? 30);
    if (!in_array($days, [7, 30, 90, 365], true)) { $days = 30; }
    return [$days, date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d')];
}

/** Day / week / month grouping for charts. "day" is only allowed up to 90 days. */
function owner_granularity(array $q, int $days): string
{
    $by = (string) ($q['by'] ?? '');
    if (!in_array($by, ['day', 'week', 'month'], true)) { $by = $days <= 30 ? 'day' : ($days <= 90 ? 'week' : 'month'); }
    if ($by === 'day' && $days > 90) { $by = 'week'; }
    return $by;
}

function bucket_key(string $date, string $by): string
{
    $ts = strtotime($date);
    if ($by === 'month') { return date('Y-m', $ts); }
    if ($by === 'week') { return date('Y-m-d', strtotime('monday this week', $ts)); }
    return date('Y-m-d', $ts);
}

function bucket_label(string $key, string $by): string
{
    if ($by === 'month') { return date('M Y', strtotime($key . '-01')); }
    return date('M j', strtotime($key));
}

/** Empty list of buckets covering the period, so days without data show as 0 instead of being skipped. */
function empty_buckets(string $from, string $to, string $by): array
{
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
        $out[bucket_key(date('Y-m-d', $t), $by)] = 0;
    }
    return $out;
}

function one_value(string $sql, array $args = [])
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

/** A. Business overview: all-time status counts + activity for today and the chosen period. */
function owner_overview(string $from, string $to): array
{
    $o = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0, 'archived' => 0];
    foreach (db()->query('SELECT status, COUNT(*) n FROM reservations GROUP BY status')->fetchAll() as $row) {
        $o[$row['status']] = (int) $row['n'];
        $o['total'] += (int) $row['n'];
    }
    $o['archived'] = (int) db()->query('SELECT COUNT(*) FROM reservations WHERE archived_at IS NOT NULL')->fetchColumn();
    $today = date('Y-m-d');
    $o['received_today']  = (int) one_value('SELECT COUNT(*) FROM reservations WHERE created_at BETWEEN ? AND ?', [$today . ' 00:00:00', $today . ' 23:59:59']);
    $o['scheduled_today'] = (int) one_value("SELECT COUNT(*) FROM reservations WHERE reservation_date = ? AND status <> 'cancelled'", [$today]);
    $o['received_period'] = (int) one_value('SELECT COUNT(*) FROM reservations WHERE created_at BETWEEN ? AND ?', [$from . ' 00:00:00', $to . ' 23:59:59']);
    $o['scheduled_period'] = (int) one_value("SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN ? AND ? AND status <> 'cancelled'", [$from, $to]);
    return $o;
}

/** B1. Requests received per day/week/month (by the date the customer sent them). Gives [labels, values, total]. */
function owner_trend(string $from, string $to, string $by): array
{
    $st = db()->prepare('SELECT DATE(created_at) d, COUNT(*) n FROM reservations WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)');
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    $b = empty_buckets($from, $to, $by);
    foreach ($st->fetchAll() as $r) { $b[bucket_key($r['d'], $by)] += (int) $r['n']; }
    $labels = [];
    foreach (array_keys($b) as $k) { $labels[] = bucket_label($k, $by); }
    return [$labels, array_values($b), array_sum($b)];
}

/** B2. Status of the requests received in the period. */
function owner_status_mix(string $from, string $to): array
{
    $mix = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
    $st = db()->prepare('SELECT status, COUNT(*) n FROM reservations WHERE created_at BETWEEN ? AND ? GROUP BY status');
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    foreach ($st->fetchAll() as $r) { $mix[$r['status']] = (int) $r['n']; }
    return $mix;
}

/** B3. Calamba vs Cabuyao for requests received in the period (+ verified money, see payments). */
function owner_branch_compare(string $from, string $to): array
{
    $out = [];
    foreach (cfg('branches') as $key => $b) {
        $out[$key] = ['name' => $b['name'], 'total' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0, 'pending' => 0, 'guests' => 0];
    }
    $st = db()->prepare('SELECT branch, status, COUNT(*) n, SUM(guests) g FROM reservations WHERE created_at BETWEEN ? AND ? GROUP BY branch, status');
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    foreach ($st->fetchAll() as $r) {
        if (!isset($out[$r['branch']])) { continue; }
        $out[$r['branch']]['total'] += (int) $r['n'];
        $out[$r['branch']][$r['status']] += (int) $r['n'];
        if ($r['status'] !== 'cancelled') { $out[$r['branch']]['guests'] += (int) $r['g']; }
    }
    return $out;
}

/** B4. Busiest times: reservations (not cancelled) SCHEDULED inside the period, by weekday and by hour. */
function owner_peaks(string $from, string $to): array
{
    $days = array_fill(1, 7, 0);   // 1 = Monday ... 7 = Sunday
    $hours = [];
    $st = db()->prepare("SELECT reservation_date d, reservation_time t, COUNT(*) n FROM reservations
        WHERE reservation_date BETWEEN ? AND ? AND status <> 'cancelled' GROUP BY reservation_date, reservation_time");
    $st->execute([$from, $to]);
    foreach ($st->fetchAll() as $r) {
        $days[(int) date('N', strtotime($r['d']))] += (int) $r['n'];
        $h = (int) substr($r['t'], 0, 2);
        $hours[$h] = ($hours[$h] ?? 0) + (int) $r['n'];
    }
    ksort($hours);
    return [$days, $hours];
}

/**
 * C. Payments. Only status 'verified' (a staff member checked it) counts as RECORDED money.
 * 'proof_submitted' = only a screenshot, 'unpaid' = nothing sent: those are shown as pending / expected, never as revenue.
 */
function owner_payments(string $from, string $to, string $by): array
{
    $new = fn() => ['n' => 0, 'amt' => 0.0];
    $p = [
        'verified' => $new(), 'verified_cancelled' => $new(), 'verified_period' => $new(),
        'submitted' => $new(), 'unpaid' => $new(), 'balance_expected' => 0.0,
        'option' => ['downpayment' => ['records' => 0, 'verified' => 0, 'submitted' => 0, 'unpaid' => 0, 'verified_amt' => 0.0],
                     'full'        => ['records' => 0, 'verified' => 0, 'submitted' => 0, 'unpaid' => 0, 'verified_amt' => 0.0]],
        'branch' => [],
    ];
    foreach (cfg('branches') as $key => $b) {
        $p['branch'][$key] = ['name' => $b['name'], 'verified_n' => 0, 'verified_amt' => 0.0, 'period_amt' => 0.0, 'submitted_n' => 0, 'submitted_amt' => 0.0, 'unpaid_n' => 0];
    }

    $sql = "SELECT p.status ps, p.payment_option po, r.branch br, (r.status = 'cancelled') canc, COUNT(*) n, SUM(p.amount_due) due, SUM(p.balance) bal
            FROM payments p JOIN reservations r ON r.id = p.reservation_id GROUP BY p.status, p.payment_option, r.branch, (r.status = 'cancelled')";
    foreach (db()->query($sql)->fetchAll() as $r) {
        $n = (int) $r['n']; $amt = (float) $r['due']; $po = $r['po']; $br = $r['br']; $canc = (bool) (int) $r['canc'];
        $p['option'][$po]['records'] += $n;
        if ($r['ps'] === 'verified') {
            $p['verified']['n'] += $n; $p['verified']['amt'] += $amt;
            $p['option'][$po]['verified'] += $n; $p['option'][$po]['verified_amt'] += $amt;
            $p['branch'][$br]['verified_n'] += $n; $p['branch'][$br]['verified_amt'] += $amt;
            if ($canc) { $p['verified_cancelled']['n'] += $n; $p['verified_cancelled']['amt'] += $amt; }
            if ($po === 'downpayment' && !$canc) { $p['balance_expected'] += (float) $r['bal']; }
        } elseif ($r['ps'] === 'proof_submitted') {
            $p['option'][$po]['submitted'] += $n;
            if (!$canc) { $p['submitted']['n'] += $n; $p['submitted']['amt'] += $amt; $p['branch'][$br]['submitted_n'] += $n; $p['branch'][$br]['submitted_amt'] += $amt; }
        } else {
            $p['option'][$po]['unpaid'] += $n;
            if (!$canc) { $p['unpaid']['n'] += $n; $p['unpaid']['amt'] += $amt; $p['branch'][$br]['unpaid_n'] += $n; }
        }
    }

    // verified inside the chosen period, grouped by the day staff verified it
    $st = db()->prepare("SELECT DATE(p.verified_at) d, r.branch br, COUNT(*) n, SUM(p.amount_due) amt FROM payments p JOIN reservations r ON r.id = p.reservation_id
        WHERE p.status = 'verified' AND p.verified_at BETWEEN ? AND ? GROUP BY DATE(p.verified_at), r.branch");
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    $b = empty_buckets($from, $to, $by);
    foreach ($st->fetchAll() as $r) {
        $b[bucket_key($r['d'], $by)] += (float) $r['amt'];
        $p['verified_period']['n'] += (int) $r['n']; $p['verified_period']['amt'] += (float) $r['amt'];
        $p['branch'][$r['br']]['period_amt'] += (float) $r['amt'];
    }
    $p['chart_labels'] = array_map(fn($k) => bucket_label($k, $by), array_keys($b));
    $p['chart_values'] = array_values($b);
    return $p;
}

/** Latest payment records (read-only list on the Payments page). */
function owner_recent_payments(int $limit = 10): array
{
    $st = db()->prepare("SELECT r.id, r.customer_name, r.branch, r.status rstatus, p.payment_option, p.amount_due, p.status pstatus, p.reference_number, p.verified_at, u.full_name verified_by
        FROM payments p JOIN reservations r ON r.id = p.reservation_id LEFT JOIN users u ON u.id = p.verified_by ORDER BY p.created_at DESC, p.id DESC LIMIT " . (int) $limit);
    $st->execute();
    return $st->fetchAll();
}

/** D. Reservation records for the owner (read-only). Gives [totalRows, rows]. */
function owner_records(array $f, int $page, int $per = 20): array
{
    $where = []; $args = [];
    $scope = $f['scope'] ?? 'active';
    if ($scope === 'archived') {
        $where[] = 'r.archived_at IS NOT NULL';
    } elseif ($scope !== 'all') {
        $where[] = 'r.archived_at IS NULL';   // default: active reservations only
    }
    if (in_array($f['status'] ?? '', ['pending', 'confirmed', 'completed', 'cancelled'], true)) { $where[] = 'r.status = ?'; $args[] = $f['status']; }
    if (isset(cfg('branches')[$f['branch'] ?? ''])) { $where[] = 'r.branch = ?'; $args[] = $f['branch']; }
    if (in_array($f['payment'] ?? '', ['unpaid', 'proof_submitted', 'verified'], true)) { $where[] = 'p.status = ?'; $args[] = $f['payment']; }
    foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f[$k] ?? ''))) { $where[] = "r.reservation_date $op ?"; $args[] = $f[$k]; }
    }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $or = "r.customer_name LIKE ? ESCAPE '!' OR r.phone LIKE ? ESCAPE '!' OR r.email LIKE ? ESCAPE '!'";
        array_push($args, $like, $like, $like);
        if (preg_match('/^(?:sc-?)?0*(\d{1,9})$/i', $q, $m)) { $or .= ' OR r.id = ?'; $args[] = (int) $m[1]; }   // search by reference such as SC-00012
        $where[] = "($or)";
    }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) one_value('SELECT COUNT(*) FROM reservations r LEFT JOIN payments p ON p.reservation_id = r.id' . $w, $args);
    $order = $scope === 'archived' ? 'r.archived_at DESC, r.id DESC' : 'r.reservation_date DESC, r.reservation_time DESC, r.id DESC';
    $st = db()->prepare(RES_SELECT . $w . ' ORDER BY ' . $order . ' LIMIT ' . (int) $per . ' OFFSET ' . (int) (max(1, $page) - 1) * (int) $per);
    $st->execute($args);
    return [$total, array_map(fn($r) => shape_reservation($r, true), $st->fetchAll())];
}

/** E. Audit log entries (read-only). Gives [totalRows, rows]. */
function owner_audit(array $f, int $page, int $per = 25): array
{
    $where = []; $args = [];
    if (($f['action'] ?? '') !== '' && audit_action_label($f['action']) !== $f['action']) { $where[] = 'action = ?'; $args[] = $f['action']; }
    if ((int) ($f['actor'] ?? 0) > 0) { $where[] = 'actor_id = ?'; $args[] = (int) $f['actor']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? ''))) { $where[] = 'created_at >= ?'; $args[] = $f['from'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? '')))   { $where[] = 'created_at <= ?'; $args[] = $f['to'] . ' 23:59:59'; }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $where[] = "(entity_ref LIKE ? ESCAPE '!' OR actor_name LIKE ? ESCAPE '!' OR details LIKE ? ESCAPE '!')";
        array_push($args, $like, $like, $like);
    }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) one_value('SELECT COUNT(*) FROM audit_logs' . $w, $args);
    $st = db()->prepare('SELECT * FROM audit_logs' . $w . ' ORDER BY id DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) (max(1, $page) - 1) * (int) $per);
    $st->execute($args);
    return [$total, $st->fetchAll()];
}

/** People who appear in the audit log (for the "User" filter). */
function owner_audit_actors(): array
{
    return db()->query('SELECT actor_id, MAX(actor_name) actor_name, MAX(actor_role) actor_role FROM audit_logs WHERE actor_id IS NOT NULL GROUP BY actor_id ORDER BY actor_name')->fetchAll();
}

/** One reservation exactly as the owner sees it (read-only), or null. */
function owner_reservation(int $id): ?array
{
    $st = db()->prepare(RES_SELECT . ' WHERE r.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? shape_reservation($row, true) : null;
}
