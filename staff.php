<?php
/*
 * STAFF DASHBOARD.
 * require_staff_page() is the guard: not logged in -> login page, logged in as a
 * customer -> "staff only" message. Only users whose role is 'staff' get past it.
 * The page lists all reservations from the database; the buttons post to
 * actions/staff-process.php (confirm, cancel, complete, verify payment, notes).
 */
require __DIR__ . '/includes/bootstrap.php';
$staff = require_staff_page();   // guests -> login page, customers -> 403

header('Cache-Control: no-store');

$filters = [
    'status'  => $_GET['status'] ?? '',
    'branch'  => $_GET['branch'] ?? '',
    'payment' => $_GET['payment'] ?? '',
    'date'    => $_GET['date'] ?? '',
    'q'       => $_GET['q'] ?? '',
];
$isArchive = (($_GET['view'] ?? '') === 'archive');   // ?view=archive shows the Archive section
$today = date('Y-m-d');
$rows = staff_reservations($filters, $isArchive);
$counts = staff_counts();
$selectedId = (int) ($_GET['id'] ?? 0);
$selected = null;
foreach ($rows as $r) {
    if ($r['id'] === $selectedId) { $selected = $r; }
}
if ($selected === null && $selectedId > 0) {   // selected one is outside the current filter: load it alone
    $stmt = db()->prepare(RES_SELECT . ' WHERE r.id = ?');
    $stmt->execute([$selectedId]);
    $row = $stmt->fetch();
    $selected = $row ? shape_reservation($row, true) : null;
}

$archivedByName = '';
if ($selected !== null && !empty($selected['archived_by'])) {
    $st = db()->prepare('SELECT full_name FROM users WHERE id = ?');
    $st->execute([(int) $selected['archived_by']]);
    $archivedByName = (string) $st->fetchColumn();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function money($n): string { return '₱' . number_format((float) $n, 2); }
function time12(string $hhmm): string { return date('g:i A', strtotime($hhmm)); }
function date_short(string $d): string { return date('M j, Y', strtotime($d)); }
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) { $s .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $s === '' ? '?' : $s;
}
const ARCHIVABLE = ['completed', 'cancelled'];   // only finished reservations can be archived
const STATUS_TEXT = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
const PAY_TEXT = ['unpaid' => 'Unpaid', 'proof_submitted' => 'Awaiting verification', 'verified' => 'Verified'];
function back_query(array $extra = []): string {
    $keep = array_filter(array_intersect_key($_GET, array_flip(['view', 'status', 'branch', 'payment', 'date', 'q'])), fn($v) => $v !== '');
    return http_build_query($extra + $keep);
}
// ?id=5&modal=1 -> only the details of that reservation (the page fetches this for the pop-up window)
if (isset($_GET['modal'])) {
    if ($selected === null) { http_response_code(404); exit('Not found.'); }
    $s = $selected;
    require __DIR__ . '/includes/staff_detail.php';
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff | The Sifted Cafe</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#121c5e">
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&family=Geist:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="st-page">
  <a class="skip-link" href="#main">Skip to main content</a>
  <div class="st-shell">

    <aside class="st-side on-dark" aria-label="Staff menu">
      <a class="st-brand" href="index.html"><strong>the sifted</strong><span>cafe &amp; bakery &middot; staff</span></a>
      <nav class="st-nav" aria-label="Staff sections">
        <?php $ico = fn(string $d) => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>'; ?>
        <p class="st-nav-label">Operations</p>
        <a href="staff.php"<?= !$isArchive ? ' aria-current="page"' : '' ?>>
          <?= $ico('<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>') ?>
          <span>Reservations</span><?php if ($counts['pending'] > 0): ?><span class="st-nav-count" title="Pending approval"><?= (int) $counts['pending'] ?></span><?php endif; ?>
        </a>
        <p class="st-nav-label">Records</p>
        <a href="staff.php?view=archive"<?= $isArchive ? ' aria-current="page"' : '' ?>>
          <?= $ico('<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>') ?>
          <span>Archive</span><?php if ($counts['archived'] > 0): ?><span class="st-nav-count"><?= (int) $counts['archived'] ?></span><?php endif; ?>
        </a>
      </nav>
      <div class="st-side-foot">
        <div class="st-user"><span class="st-avatar" aria-hidden="true"><?= e(initials($staff['full_name'])) ?></span><span class="st-user-meta"><strong><?= e($staff['full_name']) ?></strong><span>Staff</span></span></div>
        <form method="post" action="actions/logout.php">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button class="st-out" type="submit" aria-label="Log out"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>Log out</span></button>
        </form>
      </div>
    </aside>

    <div class="st-body">
      <header class="st-top">
        <div>
          <p class="eyebrow"><?= $isArchive ? 'Records' : 'Overview' ?></p>
          <h1><?= $isArchive ? 'Archive' : 'Reservations' ?></h1>
        </div>
        <form class="st-top-tools" method="get" action="staff.php" role="search">
          <?php if ($isArchive): ?><input type="hidden" name="view" value="archive"><?php endif; ?>
          <?php foreach (['status', 'branch', 'payment', 'date'] as $k): if (($filters[$k] ?? '') !== ''): ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($filters[$k]) ?>">
          <?php endif; endforeach; ?>
          <label class="st-search"><span class="visually-hidden">Search reservations</span><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, phone or email" enterkeyhint="search"></label>
        </form>
      </header>

      <main id="main" class="st-main">
        <?php if ($flash): ?>
          <div class="alert <?= $flash['type'] === 'ok' ? 'alert-success' : 'alert-error' ?>" role="status"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <?php if ($isArchive): ?>
        <p class="st-archive-intro">Archived reservations are kept safely in the database and are hidden from the active list. Use <strong>Restore</strong> to bring one back, or <strong>Delete</strong> to remove one permanently.</p>
        <?php else: ?>
        <section class="st-stats" aria-label="Summary">
          <article class="st-stat is-pending">
            <div class="st-stat-top"><span class="st-stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span></div>
            <p class="st-stat-num"><?= (int) $counts['pending'] ?></p>
            <p class="st-stat-label">Pending</p>
          </article>
          <article class="st-stat is-confirmed">
            <div class="st-stat-top"><span class="st-stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg></span><span class="st-delta"><?= (int) $counts['today'] ?> today</span></div>
            <p class="st-stat-num"><?= (int) $counts['confirmed'] ?></p>
            <p class="st-stat-label">Confirmed</p>
          </article>
          <article class="st-stat is-completed">
            <div class="st-stat-top"><span class="st-stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg></span><span class="st-delta"><?= (int) $counts['proofs_to_check'] ?> proofs to check</span></div>
            <p class="st-stat-num"><?= (int) $counts['completed'] ?></p>
            <p class="st-stat-label">Completed</p>
          </article>
          <article class="st-stat is-cancelled">
            <div class="st-stat-top"><span class="st-stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg></span></div>
            <p class="st-stat-num"><?= (int) $counts['cancelled'] ?></p>
            <p class="st-stat-label">Cancelled</p>
          </article>
        </section>
        <?php endif; ?>

        <div class="st-grid st-grid-single">
          <section class="st-card st-list" aria-labelledby="list-title">
            <div class="st-card-head">
              <h2 id="list-title"><?= $isArchive ? 'Archived reservations' : 'All requests' ?></h2>
              <div class="st-filters" role="group" aria-label="Filter by status">
                <?php foreach (['' => 'All'] + STATUS_TEXT as $key => $label): $on = ($filters['status'] === $key); ?>
                  <a class="st-chip<?= $on ? ' is-on' : '' ?>" href="staff.php?<?= e(http_build_query(array_filter(['view' => $isArchive ? 'archive' : '', 'status' => $key, 'branch' => $filters['branch'], 'payment' => $filters['payment'], 'date' => $filters['date'], 'q' => $filters['q']], fn($v) => $v !== ''))) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
              </div>
            </div>
            <form class="st-selects" method="get" action="staff.php">
              <?php if ($isArchive): ?><input type="hidden" name="view" value="archive"><?php endif; ?>
              <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
              <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
              <label>Branch <select name="branch">
                <option value="">All branches</option>
                <?php foreach (cfg('branches') as $key => $b): ?><option value="<?= e($key) ?>"<?= $filters['branch'] === $key ? ' selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
              </select></label>
              <label>Payment <select name="payment">
                <option value="">All payments</option>
                <?php foreach (PAY_TEXT as $key => $label): ?><option value="<?= e($key) ?>"<?= $filters['payment'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
              </select></label>
              <label>Date <input type="date" name="date" value="<?= e($filters['date']) ?>"></label>
              <button type="submit" class="btn btn-outline btn-small">Apply</button>
              <a class="btn btn-view btn-small" href="staff.php?<?= e(http_build_query(array_filter(['view' => $isArchive ? 'archive' : '', 'date' => $today]))) ?>">Today</a>
              <a class="btn btn-ghost btn-small" href="staff.php<?= $isArchive ? '?view=archive' : '' ?>">Clear</a>
            </form>

            <?php if (!$rows): ?>
              <div class="st-empty">
                <span class="st-empty-icon"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span>
                <?php if ($isArchive): ?>
                <h2>Nothing archived yet</h2>
                <p>Completed or cancelled reservations you archive will be kept here. Try clearing the filters if you expected some.</p>
                <?php else: ?>
                <h2>No reservations found</h2>
                <p>New customer requests will appear here as soon as they are sent. Try clearing the filters if you expected some.</p>
                <?php endif; ?>
              </div>
            <?php else: ?>
            <div class="table-wrap st-table-wrap">
              <table class="res-table st-table">
                <caption class="visually-hidden"><?= $isArchive ? 'Archived reservations' : 'Customer reservations' ?></caption>
                <thead><tr>
                  <th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Date and time</th>
                  <th scope="col">Guests</th><th scope="col">Amount due</th><th scope="col">Payment</th><th scope="col">Status</th><?php if ($isArchive): ?><th scope="col">Archived</th><?php endif; ?><th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td data-label="Reference"><strong><?= e($r['reference']) ?></strong><span class="cell-sub"><?= e($r['branch_name']) ?></span></td>
                    <td data-label="Customer"><span class="st-person"><span class="st-avatar" aria-hidden="true"><?= e(initials($r['customer_name'])) ?></span><span><strong><?= e($r['customer_name']) ?></strong><span class="cell-sub"><?= e($r['phone']) ?></span></span></span></td>
                    <td data-label="Date and time"><?= e(date_short($r['reservation_date'])) ?><span class="cell-sub"><?= e(time12($r['reservation_time'])) ?></span></td>
                    <td data-label="Guests"><?= (int) $r['guests'] ?></td>
                    <td data-label="Amount due"><?= e(money($r['amount_due'])) ?></td>
                    <td data-label="Payment"><span class="badge badge-<?= e($r['payment_status']) ?>"><?= e(PAY_TEXT[$r['payment_status']] ?? $r['payment_status']) ?></span></td>
                    <td data-label="Status"><span class="badge badge-<?= e($r['status']) ?>"><?= e(STATUS_TEXT[$r['status']] ?? $r['status']) ?></span></td>
                    <?php if ($isArchive): ?><td data-label="Archived"><?= e(date_short((string) $r['archived_at'])) ?><span class="cell-sub"><?= e(time12(substr((string) $r['archived_at'], 11, 5))) ?></span></td><?php endif; ?>
                    <td data-label="Actions"><div class="row-actions">
                      <a class="btn btn-view btn-small" href="staff.php?<?= e(back_query(['id' => $r['id']])) ?>" data-view="<?= (int) $r['id'] ?>">View<span class="visually-hidden"> <?= e($r['reference']) ?></span></a>
                      <?php if ($isArchive): ?>
                      <button type="button" class="btn btn-primary btn-small" data-act="restore" data-id="<?= (int) $r['id'] ?>" data-ref="<?= e($r['reference']) ?>" data-return="<?= e(back_query()) ?>">Restore<span class="visually-hidden"> <?= e($r['reference']) ?></span></button>
                      <button type="button" class="btn btn-danger btn-small" data-act="delete" data-id="<?= (int) $r['id'] ?>" data-ref="<?= e($r['reference']) ?>" data-return="<?= e(back_query()) ?>">Delete<span class="visually-hidden"> <?= e($r['reference']) ?></span></button>
                      <?php else: ?>
                      <?php $nx = allowed_next_statuses($r['status'], (string) $r['payment_status']); if (in_array('confirmed', $nx, true)): ?>
                      <form method="post" action="actions/staff-process.php" onsubmit="return confirm('Confirm this reservation? The customer will see it as Confirmed.');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="return" value="<?= e(back_query()) ?>"><button type="submit" name="action" value="confirmed" class="btn btn-confirm btn-small">Confirm<span class="visually-hidden"> <?= e($r['reference']) ?></span></button></form>
                      <?php endif; ?>
                      <?php if (in_array('cancelled', $nx, true)): ?>
                      <button type="button" class="btn btn-warn btn-small" data-act="cancelled" data-id="<?= (int) $r['id'] ?>" data-ref="<?= e($r['reference']) ?>" data-return="<?= e(back_query()) ?>">Cancel<span class="visually-hidden"> <?= e($r['reference']) ?></span></button>
                      <?php endif; ?>
                      <?php if (in_array($r['status'], ARCHIVABLE, true)): ?>
                      <button type="button" class="btn btn-archive btn-small" data-act="archive" data-id="<?= (int) $r['id'] ?>" data-ref="<?= e($r['reference']) ?>" data-return="<?= e(back_query()) ?>">Archive<span class="visually-hidden"> <?= e($r['reference']) ?></span></button>
                      <?php endif; ?>
                      <?php endif; ?>
                    </div></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <p class="st-foot">Showing <?= count($rows) ?> <?= $isArchive ? 'archived ' : '' ?>reservation<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) >= 300 ? ' (first 300)' : '' ?></p>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>
  </div>
  <!-- View window (details are loaded into it) -->
  <dialog id="detail-modal" class="st-modal" aria-labelledby="detail-modal-title">
    <div class="st-modal-head">
      <h2 id="detail-modal-title">Reservation details</h2>
      <button type="button" class="st-modal-x" data-close aria-label="Close">&times;</button>
    </div>
    <div id="detail-modal-body" class="st-modal-body">
      <?php if ($selected): $s = $selected; require __DIR__ . '/includes/staff_detail.php'; endif; ?>
    </div>
    <div class="st-modal-foot"><button type="button" class="btn btn-outline btn-small" data-close>Close</button></div>
  </dialog>

  <!-- Confirmation window for Cancel, Archive, Restore and permanent Delete -->
  <dialog id="confirm-modal" class="st-modal st-modal-sm" aria-labelledby="confirm-title">
    <form method="post" action="actions/staff-process.php" id="confirm-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" id="confirm-id" value="">
      <input type="hidden" name="action" id="confirm-action" value="">
      <input type="hidden" name="return" id="confirm-return" value="">
      <div class="st-modal-head"><h2 id="confirm-title">Are you sure?</h2><button type="button" class="st-modal-x" data-close aria-label="Close">&times;</button></div>
      <div class="st-modal-body">
        <p id="confirm-text"></p>
      </div>
      <div class="st-modal-foot">
        <button type="button" class="btn btn-outline btn-small" data-close>Go back</button>
        <button type="submit" class="btn btn-primary btn-small" id="confirm-submit">Yes</button>
      </div>
    </form>
  </dialog>
  <script src="js/staff.js"></script>
</body>
</html>
