<?php
/*
 * OWNER DASHBOARD (read-only). Sections: Overview, Analytics, Payments, Reservations, Archived records, Audit log.
 * require_owner_page() is the guard: guests -> login, staff and customers -> 403. Every query behind this page is a SELECT.
 * There are no forms that change data here: the only POST is "Log out".
 */
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/owner_ui.php';
$owner = require_owner_page();
header('Cache-Control: no-store');

const STATUS_TEXT = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
const PAY_TEXT = ['unpaid' => 'Unpaid', 'proof_submitted' => 'Awaiting verification', 'verified' => 'Verified'];
function ow_initials(string $name): string
{
    $s = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $p) { $s .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $s === '' ? '?' : $s;
}

$views = ['overview' => 'Overview', 'analytics' => 'Analytics', 'payments' => 'Payments', 'records' => 'Reservations', 'audit' => 'Audit log'];
$view = (string) ($_GET['view'] ?? 'overview');
if (!isset($views[$view])) { $view = 'overview'; }
[$days, $from, $to] = owner_range($_GET);
$by = owner_granularity($_GET, $days);
$page = max(1, (int) ($_GET['page'] ?? 1));
$scope = in_array($_GET['scope'] ?? '', ['active', 'archived', 'all'], true) ? $_GET['scope'] : 'active';

// ?view=records&id=5&modal=1 -> only the read-only details of that reservation (the page loads it into the pop-up)
if (isset($_GET['modal'])) {
    $s = owner_reservation((int) ($_GET['id'] ?? 0));
    if ($s === null) { http_response_code(404); exit('Not found.'); }
    $st = db()->prepare("SELECT * FROM audit_logs WHERE entity_id = ? AND entity_type IN ('reservation', 'payment') ORDER BY id ASC LIMIT 40");
    $st->execute([$s['id']]);
    $history = $st->fetchAll();
    require __DIR__ . '/includes/owner_detail.php';
    exit;
}

/** Link to a page of the dashboard, keeping the period. */
function ow_url(array $q = []): string { return 'owner.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null)); }
/** Chips to choose the period (and day/week/month where it matters). */
function ow_period_bar(string $view, int $days, string $by, bool $showBy): string
{
    $out = '<div class="ow-bar"><div class="st-filters" role="group" aria-label="Period">';
    foreach ([7 => 'Last 7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $d => $label) {
        $out .= '<a class="st-chip' . ($d === $days ? ' is-on' : '') . '" href="' . e(ow_url(['view' => $view, 'range' => $d])) . '"' . ($d === $days ? ' aria-current="true"' : '') . '>' . e($label) . '</a>';
    }
    $out .= '</div>';
    if ($showBy) {
        $out .= '<div class="st-filters" role="group" aria-label="Group by">';
        foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $k => $label) {
            if ($k === 'day' && $days > 90) { continue; }
            $out .= '<a class="st-chip' . ($k === $by ? ' is-on' : '') . '" href="' . e(ow_url(['view' => $view, 'range' => $days, 'by' => $k])) . '"' . ($k === $by ? ' aria-current="true"' : '') . '>' . e($label) . '</a>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}
$periodText = date('M j', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));

// ---- load only what the chosen section needs ----
$isRecords = ($view === 'records');
$filters = ['scope' => $scope, 'status' => $_GET['status'] ?? '', 'branch' => $_GET['branch'] ?? '', 'payment' => $_GET['payment'] ?? '',
            'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '', 'q' => $_GET['q'] ?? ''];
$af = ['action' => $_GET['action'] ?? '', 'actor' => $_GET['actor'] ?? '', 'from' => $_GET['afrom'] ?? '', 'to' => $_GET['ato'] ?? '', 'q' => $_GET['q'] ?? ''];
$ov = owner_overview($from, $to);

$icoNav = [
  'overview'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
  'analytics' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
  'payments'  => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/>',
  'records'   => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
  'archive'   => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>',
  'audit'     => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner | The Sifted Cafe</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#121c5e">
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&family=Geist:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="st-page ow-page">
  <a class="skip-link" href="#main">Skip to main content</a>
  <div class="st-shell">
    <aside class="st-side on-dark" aria-label="Owner menu">
      <a class="st-brand" href="index.html"><strong>the sifted</strong><span>cafe &amp; bakery &middot; owner</span></a>
      <nav class="st-nav" aria-label="Owner sections">
        <p class="st-nav-label">Business</p>
        <?php foreach (['overview', 'analytics', 'payments'] as $k): ?>
        <a href="<?= e(ow_url(['view' => $k])) ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= ow_ico($icoNav[$k]) ?><span><?= e($views[$k]) ?></span></a>
        <?php endforeach; ?>
        <p class="st-nav-label">Records</p>
        <a href="<?= e(ow_url(['view' => 'records'])) ?>"<?= ($view === 'records' && $scope !== 'archived') ? ' aria-current="page"' : '' ?>><?= ow_ico($icoNav['records']) ?><span>Reservations</span></a>
        <a href="<?= e(ow_url(['view' => 'records', 'scope' => 'archived'])) ?>"<?= ($view === 'records' && $scope === 'archived') ? ' aria-current="page"' : '' ?>><?= ow_ico($icoNav['archive']) ?><span>Archived records</span><?php if ($ov['archived'] > 0): ?><span class="st-nav-count"><?= (int) $ov['archived'] ?></span><?php endif; ?></a>
        <a href="<?= e(ow_url(['view' => 'audit'])) ?>"<?= $view === 'audit' ? ' aria-current="page"' : '' ?>><?= ow_ico($icoNav['audit']) ?><span>Audit log</span></a>
      </nav>
      <div class="st-side-foot">
        <div class="st-user"><span class="st-avatar" aria-hidden="true"><?= e(ow_initials($owner['full_name'])) ?></span><span class="st-user-meta"><strong><?= e($owner['full_name']) ?></strong><span>Owner &middot; read-only</span></span></div>
        <form method="post" action="actions/logout.php">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button class="st-out" type="submit" aria-label="Log out"><?= ow_ico('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>') ?><span>Log out</span></button>
        </form>
      </div>
    </aside>

    <div class="st-body">
      <header class="st-top">
        <div>
          <p class="eyebrow"><?= $view === 'audit' || $isRecords ? 'Records' : 'Business' ?></p>
          <h1><?= e($isRecords && $scope === 'archived' ? 'Archived records' : $views[$view]) ?></h1>
        </div>
        <span class="badge badge-completed ow-ro" title="The owner dashboard cannot change reservations, payments or the audit log.">Read-only</span>
      </header>

      <main id="main" class="st-main">

<?php if ($view === 'overview'): ?>
        <p class="ow-note">Status totals cover <strong>all reservations ever recorded</strong>. The activity row follows the period you pick.</p>
        <section class="st-stats ow-kpis" aria-label="Reservation totals">
          <?php foreach ([
              ['Total reservations', $ov['total'], '', 'Everything in the database'],
              ['Pending', $ov['pending'], 'is-pending', 'Waiting for staff'],
              ['Confirmed', $ov['confirmed'], 'is-confirmed', 'Accepted by staff'],
              ['Completed', $ov['completed'], 'is-completed', 'Visit finished'],
              ['Cancelled', $ov['cancelled'], 'is-cancelled', 'Cancelled by guest or staff'],
              ['Archived', $ov['archived'], 'is-archived', 'Already counted in Completed / Cancelled'],
          ] as [$label, $n, $tone, $sub]): ?>
          <article class="st-stat <?= $tone ?>"><p class="st-stat-num"><?= (int) $n ?></p><p class="st-stat-label"><?= e($label) ?></p><p class="st-delta"><?= e($sub) ?></p></article>
          <?php endforeach; ?>
        </section>

        <section class="st-card st-list" aria-labelledby="act-h">
          <div class="st-card-head"><h2 id="act-h">Reservation activity</h2></div>
          <?= ow_period_bar('overview', $days, $by, false) ?>
          <dl class="ow-activity">
            <div><dt>Requests received today</dt><dd><?= (int) $ov['received_today'] ?></dd></div>
            <div><dt>Reservations scheduled today</dt><dd><?= (int) $ov['scheduled_today'] ?></dd></div>
            <div><dt>Requests received<br><span class="cell-sub"><?= e($periodText) ?></span></dt><dd><?= (int) $ov['received_period'] ?></dd></div>
            <div><dt>Reservations scheduled<br><span class="cell-sub"><?= e($periodText) ?></span></dt><dd><?= (int) $ov['scheduled_period'] ?></dd></div>
          </dl>
          <p class="st-foot">"Scheduled" counts reservations whose booked date falls on the day or in the period, cancelled ones excluded.</p>
        </section>

        <section class="st-card st-list ow-gap" aria-labelledby="recent-h">
          <div class="st-card-head"><h2 id="recent-h">Latest staff activity</h2><a class="btn btn-outline btn-small" href="<?= e(ow_url(['view' => 'audit'])) ?>">Open audit log</a></div>
          <?php [$tot, $recent] = owner_audit([], 1, 6); if (!$recent): echo ow_empty('No activity recorded yet', 'Sign-ins, reservation changes and payment checks will appear here as soon as they happen.'); else: ?>
          <ul class="ow-feed">
            <?php foreach ($recent as $a): ?>
            <li><span class="ow-feed-main"><strong><?= e($a['actor_name'] ?: 'System') ?></strong> <?= e(strtolower(audit_action_label($a['action']))) ?><?= $a['entity_ref'] ? ' · ' . e($a['entity_ref']) : '' ?></span><time><?= e(ow_dt($a['created_at'])) ?></time></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </section>

<?php elseif ($view === 'analytics'):
    [$tl, $tv, $tTotal] = owner_trend($from, $to, $by);
    $mix = owner_status_mix($from, $to);
    $branches = owner_branch_compare($from, $to);
    [$wd, $hrs] = owner_peaks($from, $to);
    $col = ['pending' => 'var(--amber)', 'confirmed' => 'var(--green)', 'completed' => 'var(--blue-500)', 'cancelled' => 'var(--red)'];
    $byText = ['day' => 'day', 'week' => 'week', 'month' => 'month'][$by]; ?>
        <?= ow_period_bar('analytics', $days, $by, true) ?>
        <section class="st-card st-list" aria-labelledby="tr-h">
          <div class="st-card-head"><h2 id="tr-h">Requests received per <?= e($byText) ?></h2><span class="st-foot"><?= (int) $tTotal ?> in <?= e($periodText) ?></span></div>
          <?= $tTotal > 0 ? ow_bar_chart($tl, $tv, 'Reservation requests received per ' . $byText) : ow_empty('No requests in this period', 'Try a longer period. Charts fill in automatically as customers send requests.') ?>
          <?php if ($by === 'week'): ?><p class="st-foot">Weeks are labelled with the Monday they start on.</p><?php endif; ?>
        </section>

        <div class="ow-two ow-gap">
          <section class="st-card st-list" aria-labelledby="mix-h">
            <div class="st-card-head"><h2 id="mix-h">Status distribution</h2></div>
            <?php $donut = ow_donut($mix, $col); if ($donut === ''): echo ow_empty('Nothing to show', 'No requests were received in this period.'); else: ?>
            <div class="ow-donut-wrap"><?= $donut ?>
              <ul class="ow-legend">
                <?php foreach ($mix as $k => $n): ?><li><i style="background:<?= $col[$k] ?>"></i><span><?= e(STATUS_TEXT[$k]) ?></span><strong><?= (int) $n ?></strong><span class="cell-sub"><?= array_sum($mix) ? round($n / array_sum($mix) * 100) : 0 ?>%</span></li><?php endforeach; ?>
              </ul></div>
            <p class="st-foot">Current status of the requests received in this period.</p>
            <?php endif; ?>
          </section>

          <section class="st-card st-list" aria-labelledby="br-h">
            <div class="st-card-head"><h2 id="br-h">Branch comparison</h2></div>
            <?php $bmax = max(1, ...array_column($branches, 'total')); if (array_sum(array_column($branches, 'total')) === 0): echo ow_empty('Nothing to compare', 'No requests were received in this period.'); else: ?>
            <div class="table-wrap st-table-wrap"><table class="res-table st-table ow-table">
              <thead><tr><th scope="col">Branch</th><th scope="col">Requests</th><th scope="col">Completed</th><th scope="col">Cancelled</th><th scope="col">Guests*</th></tr></thead>
              <tbody><?php foreach ($branches as $b): ?><tr>
                <td data-label="Branch"><strong><?= e($b['name']) ?></strong><span class="ow-meter"><i style="width:<?= round($b['total'] / $bmax * 100) ?>%"></i></span></td>
                <td data-label="Requests"><?= (int) $b['total'] ?></td><td data-label="Completed"><?= (int) $b['completed'] ?></td><td data-label="Cancelled"><?= (int) $b['cancelled'] ?></td><td data-label="Guests"><?= (int) $b['guests'] ?></td></tr><?php endforeach; ?></tbody></table></div>
            <p class="st-foot">*Guests in requests that were not cancelled.</p>
            <?php endif; ?>
          </section>
        </div>

        <section class="st-card st-list ow-gap" aria-labelledby="pk-h">
          <div class="st-card-head"><h2 id="pk-h">Most active reservation periods</h2><span class="st-foot">Booked dates in <?= e($periodText) ?>, cancelled excluded</span></div>
          <?php if (array_sum($wd) === 0): echo ow_empty('No reservations scheduled in this period', 'Busy days and hours will appear once reservations fall inside the period.'); else:
              $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
              $hl = array_map(fn($h) => date('ga', strtotime(sprintf('%02d:00', $h))), array_keys($hrs)); ?>
          <div class="ow-two">
            <div><h3 class="ow-h3">By day of the week</h3><?= ow_bar_chart(array_values($names), array_values($wd), 'Reservations by weekday', false, 'var(--blue-700)') ?></div>
            <div><h3 class="ow-h3">By hour of the day</h3><?= ow_bar_chart($hl, array_values($hrs), 'Reservations by hour', false, 'var(--blue-700)') ?></div>
          </div>
          <?php endif; ?>
        </section>

<?php elseif ($view === 'payments'):
    $pay = owner_payments($from, $to, $by); $recent = owner_recent_payments(10); ?>
        <p class="ow-note"><strong>Verified</strong> = a staff member checked the payment against the cafe's GCash records: this is the only figure treated as recorded money. <strong>Awaiting verification</strong> (screenshot only) and <strong>unpaid</strong> are expected amounts, <em>not</em> revenue. Totals only cover payments still in the database: when staff permanently delete an archived reservation, its payment record goes with it (the audit log keeps a note of the deletion).</p>
        <section class="st-stats ow-kpis ow-kpis-4" aria-label="Payment summary">
          <article class="st-stat is-confirmed"><p class="st-stat-num"><?= e(ow_money0($pay['verified']['amt'])) ?></p><p class="st-stat-label">Verified payments (all time)</p><p class="st-delta"><?= (int) $pay['verified']['n'] ?> payment<?= $pay['verified']['n'] == 1 ? '' : 's' ?><?= $pay['verified_cancelled']['n'] ? ' · ' . e(ow_money0($pay['verified_cancelled']['amt'])) . ' on cancelled reservations' : '' ?></p></article>
          <article class="st-stat is-completed"><p class="st-stat-num"><?= e(ow_money0($pay['verified_period']['amt'])) ?></p><p class="st-stat-label">Verified in selected period</p><p class="st-delta"><?= (int) $pay['verified_period']['n'] ?> payment<?= $pay['verified_period']['n'] == 1 ? '' : 's' ?> · <?= e($periodText) ?></p></article>
          <article class="st-stat is-pending"><p class="st-stat-num"><?= (int) $pay['submitted']['n'] ?></p><p class="st-stat-label">Awaiting verification</p><p class="st-delta"><?= e(ow_money0($pay['submitted']['amt'])) ?> expected, not revenue yet</p></article>
          <article class="st-stat is-cancelled"><p class="st-stat-num"><?= (int) $pay['unpaid']['n'] ?></p><p class="st-stat-label">Unpaid reservations</p><p class="st-delta"><?= e(ow_money0($pay['unpaid']['amt'])) ?> expected, no payment sent</p></article>
        </section>
        <?= ow_period_bar('payments', $days, $by, true) ?>
        <section class="st-card st-list" aria-labelledby="pa-h">
          <div class="st-card-head"><h2 id="pa-h">Verified payments over time</h2><span class="st-foot">By the day staff verified them</span></div>
          <?= $pay['verified_period']['amt'] > 0 ? ow_bar_chart($pay['chart_labels'], $pay['chart_values'], 'Verified payment amounts', true, 'var(--blue-700)') : ow_empty('No verified payments in this period', 'Verified amounts appear here after staff verify a payment.') ?>
        </section>

        <div class="ow-stack ow-gap">
          <section class="st-card st-list" aria-labelledby="op-h">
            <div class="st-card-head"><h2 id="op-h">Downpayment and full-payment records</h2></div>
            <div class="table-wrap st-table-wrap"><table class="res-table st-table ow-table">
              <thead><tr><th scope="col">Option</th><th scope="col">Records</th><th scope="col">Verified</th><th scope="col">Awaiting</th><th scope="col">Unpaid</th><th scope="col">Verified ₱</th></tr></thead>
              <tbody><?php foreach (['downpayment' => 'Downpayment', 'full' => 'Full payment'] as $k => $label): $o = $pay['option'][$k]; ?><tr>
                <td data-label="Option"><strong><?= e($label) ?></strong></td><td data-label="Records"><?= (int) $o['records'] ?></td><td data-label="Verified"><?= (int) $o['verified'] ?></td><td data-label="Awaiting"><?= (int) $o['submitted'] ?></td><td data-label="Unpaid"><?= (int) $o['unpaid'] ?></td><td data-label="Verified amount"><?= e(ow_money($o['verified_amt'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <p class="st-foot">Balance still expected on verified downpayments (active reservations): <strong><?= e(ow_money($pay['balance_expected'])) ?></strong>. This is not recorded revenue: the balance is paid at the cafe.</p>
          </section>
          <section class="st-card st-list" aria-labelledby="bp-h">
            <div class="st-card-head"><h2 id="bp-h">Branch payment summary</h2></div>
            <div class="table-wrap st-table-wrap"><table class="res-table st-table ow-table">
              <thead><tr><th scope="col">Branch</th><th scope="col">Verified</th><th scope="col">Verified ₱</th><th scope="col">In period ₱</th><th scope="col">Awaiting</th><th scope="col">Unpaid</th></tr></thead>
              <tbody><?php foreach ($pay['branch'] as $b): ?><tr>
                <td data-label="Branch"><strong><?= e($b['name']) ?></strong></td><td data-label="Verified"><?= (int) $b['verified_n'] ?></td><td data-label="Verified amount"><?= e(ow_money($b['verified_amt'])) ?></td><td data-label="In period"><?= e(ow_money($b['period_amt'])) ?></td><td data-label="Awaiting"><?= (int) $b['submitted_n'] ?> (<?= e(ow_money0($b['submitted_amt'])) ?>)</td><td data-label="Unpaid"><?= (int) $b['unpaid_n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
          </section>
        </div>

        <section class="st-card st-list ow-gap" aria-labelledby="rp-h">
          <div class="st-card-head"><h2 id="rp-h">Latest payment records</h2><a class="btn btn-outline btn-small" href="<?= e(ow_url(['view' => 'records', 'scope' => 'all'])) ?>">All records</a></div>
          <?php if (!$recent): echo ow_empty('No payment records yet', 'Payments are recorded when customers send a reservation request.'); else: ?>
          <div class="table-wrap st-table-wrap"><table class="res-table st-table">
            <thead><tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Option</th><th scope="col">Amount due</th><th scope="col">Payment</th><th scope="col">Verified by</th></tr></thead>
            <tbody><?php foreach ($recent as $r): ?><tr>
              <td data-label="Reference"><strong><?= e(reference_for((int) $r['id'])) ?></strong><span class="cell-sub"><?= e(branch_name($r['branch'])) ?></span></td>
              <td data-label="Customer"><?= e($r['customer_name']) ?></td>
              <td data-label="Option"><?= $r['payment_option'] === 'full' ? 'Full' : 'Downpayment' ?></td>
              <td data-label="Amount due"><?= e(ow_money($r['amount_due'])) ?></td>
              <td data-label="Payment"><span class="badge badge-<?= e($r['pstatus']) ?>"><?= e(PAY_TEXT[$r['pstatus']] ?? $r['pstatus']) ?></span></td>
              <td data-label="Verified by"><?= $r['verified_at'] ? e($r['verified_by'] ?: 'Staff') . '<span class="cell-sub">' . e(ow_dt($r['verified_at'])) . '</span>' : '—' ?></td></tr><?php endforeach; ?></tbody></table></div>
          <?php endif; ?>
        </section>

<?php elseif ($view === 'records'):
    [$total, $rows] = owner_records($filters, $page, 20);
    $isA = $scope === 'archived'; ?>
        <section class="st-card st-list" aria-labelledby="rec-h">
          <div class="st-card-head"><h2 id="rec-h"><?= $isA ? 'Archived reservations' : 'Reservation records' ?></h2><span class="st-foot"><?= (int) $total ?> record<?= $total === 1 ? '' : 's' ?> found</span></div>
          <form class="st-selects" method="get" action="owner.php" role="search">
            <input type="hidden" name="view" value="records">
            <label>Search <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, phone, email or SC-00012"></label>
            <label>Records <select name="scope"><?php foreach (['active' => 'Active', 'archived' => 'Archived', 'all' => 'Active + archived'] as $k => $l): ?><option value="<?= $k ?>"<?= $scope === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
            <label>Status <select name="status"><option value="">All statuses</option><?php foreach (STATUS_TEXT as $k => $l): ?><option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
            <label>Branch <select name="branch"><option value="">All branches</option><?php foreach (cfg('branches') as $k => $b): ?><option value="<?= e($k) ?>"<?= $filters['branch'] === $k ? ' selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></label>
            <label>Payment <select name="payment"><option value="">All payments</option><?php foreach (PAY_TEXT as $k => $l): ?><option value="<?= $k ?>"<?= $filters['payment'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
            <label>Booked from <input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
            <label>to <input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
            <button type="submit" class="btn btn-outline btn-small">Apply</button>
            <a class="btn btn-ghost btn-small" href="<?= e(ow_url(['view' => 'records', 'scope' => $isA ? 'archived' : ''])) ?>">Clear</a>
          </form>
          <?php if (!$rows): echo ow_empty($isA ? 'No archived records' : 'No reservations found', 'Nothing matches these filters. Try clearing them.'); else: ?>
          <div class="table-wrap st-table-wrap"><table class="res-table st-table">
            <caption class="visually-hidden">Reservation records (read-only)</caption>
            <thead><tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Date and time</th><th scope="col">Guests</th><th scope="col">Amount due</th><th scope="col">Payment</th><th scope="col">Status</th><?php if ($scope !== 'active'): ?><th scope="col">Archived</th><?php endif; ?><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?><tr>
              <td data-label="Reference"><strong><?= e($r['reference']) ?></strong><span class="cell-sub"><?= e($r['branch_name']) ?></span></td>
              <td data-label="Customer"><span class="st-person"><span class="st-avatar" aria-hidden="true"><?= e(ow_initials($r['customer_name'])) ?></span><span><strong><?= e($r['customer_name']) ?></strong></span></span></td>
              <td data-label="Date and time"><?= e(date('M j, Y', strtotime($r['reservation_date']))) ?><span class="cell-sub"><?= e(date('g:i A', strtotime($r['reservation_time']))) ?></span></td>
              <td data-label="Guests"><?= (int) $r['guests'] ?></td>
              <td data-label="Amount due"><?= e(ow_money($r['amount_due'])) ?></td>
              <td data-label="Payment"><span class="badge badge-<?= e($r['payment_status']) ?>"><?= e(PAY_TEXT[$r['payment_status']] ?? $r['payment_status']) ?></span></td>
              <td data-label="Status"><span class="badge badge-<?= e($r['status']) ?>"><?= e(STATUS_TEXT[$r['status']] ?? $r['status']) ?></span></td>
              <?php if ($scope !== 'active'): ?><td data-label="Archived"><?= !empty($r['archived_at']) ? e(ow_dt((string) $r['archived_at'])) : '—' ?></td><?php endif; ?>
              <td data-label="Details"><a class="btn btn-outline btn-small" href="<?= e(ow_url(['view' => 'records', 'id' => $r['id']] + array_filter($filters))) ?>" data-view="<?= (int) $r['id'] ?>">View<span class="visually-hidden"> <?= e($r['reference']) ?></span></a></td></tr><?php endforeach; ?></tbody></table></div>
          <?= ow_pager($total, $page, 20) ?>
          <?php endif; ?>
        </section>

<?php else: /* audit */
    [$total, $rows] = owner_audit($af, $page, 25); $actors = owner_audit_actors(); ?>
        <section class="st-card st-list" aria-labelledby="au-h">
          <div class="st-card-head"><h2 id="au-h">Activity history</h2><span class="st-foot"><?= (int) $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?> · newest first</span></div>
          <p class="ow-note">Entries are written automatically by the system when something happens. They cannot be edited or deleted from this dashboard, and the database refuses to change them.</p>
          <form class="st-selects" method="get" action="owner.php" role="search">
            <input type="hidden" name="view" value="audit">
            <label>Search <input type="search" name="q" value="<?= e($af['q']) ?>" placeholder="Reference, person or detail"></label>
            <label>Action <select name="action"><option value="">All actions</option><?php foreach (AUDIT_ACTIONS as $g => $items): ?><optgroup label="<?= e($g) ?>"><?php foreach ($items as $k => $l): ?><option value="<?= e($k) ?>"<?= $af['action'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <label>User <select name="actor"><option value="">Everyone</option><?php foreach ($actors as $a): ?><option value="<?= (int) $a['actor_id'] ?>"<?= (int) $af['actor'] === (int) $a['actor_id'] ? ' selected' : '' ?>><?= e($a['actor_name']) ?> (<?= e($a['actor_role']) ?>)</option><?php endforeach; ?></select></label>
            <label>From <input type="date" name="afrom" value="<?= e($af['from']) ?>"></label>
            <label>To <input type="date" name="ato" value="<?= e($af['to']) ?>"></label>
            <button type="submit" class="btn btn-outline btn-small">Apply</button>
            <a class="btn btn-ghost btn-small" href="<?= e(ow_url(['view' => 'audit'])) ?>">Clear</a>
          </form>
          <?php if (!$rows): echo ow_empty('No audit entries', 'Nothing matches these filters, or nothing has happened yet.'); else: ?>
          <div class="table-wrap st-table-wrap"><table class="res-table st-table ow-table">
            <caption class="visually-hidden">Audit log (read-only)</caption>
            <thead><tr><th scope="col">Date and time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Change</th><th scope="col">Details</th></tr></thead>
            <tbody><?php foreach ($rows as $a): ?><tr>
              <td data-label="Date and time"><?= e(ow_dt($a['created_at'])) ?></td>
              <td data-label="User"><strong><?= e($a['actor_name'] ?: 'System') ?></strong><?php if ($a['actor_role']): ?><span class="cell-sub"><?= e(ucfirst($a['actor_role'])) ?></span><?php endif; ?></td>
              <td data-label="Action"><span class="badge ow-act ow-act-<?= e(preg_replace('/_.*/', '', $a['action'])) ?>"><?= e(audit_action_label($a['action'])) ?></span></td>
              <td data-label="Record"><?= $a['entity_ref'] ? '<strong>' . e($a['entity_ref']) . '</strong>' : '—' ?></td>
              <td data-label="Change"><?= ($a['old_value'] !== null || $a['new_value'] !== null) && $a['old_value'] !== $a['new_value'] ? e(($a['old_value'] ?? '—') . ' → ' . ($a['new_value'] ?? '—')) : '—' ?></td>
              <td data-label="Details" class="ow-detail"><?= e($a['details'] ?? '') ?: '—' ?><?php if ($a['ip_address']): ?><span class="cell-sub">IP <?= e($a['ip_address']) ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
          <?= ow_pager($total, $page, 25) ?>
          <?php endif; ?>
        </section>
<?php endif; ?>
      </main>
    </div>
  </div>

  <dialog id="detail-modal" class="st-modal" aria-labelledby="detail-modal-title">
    <div class="st-modal-head"><h2 id="detail-modal-title">Reservation details</h2><button type="button" class="st-modal-x" data-close aria-label="Close">&times;</button></div>
    <div id="detail-modal-body" class="st-modal-body"></div>
    <div class="st-modal-foot"><button type="button" class="btn btn-outline btn-small" data-close>Close</button></div>
  </dialog>
  <script src="js/owner.js"></script>
</body>
</html>
