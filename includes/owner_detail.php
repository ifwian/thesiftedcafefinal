<?php /** READ-ONLY details of one reservation ($s) for the Owner. No buttons, no forms: nothing here can change a record. */
$isArch = !empty($s['archived_at']); ?>
<div class="st-detail-body">
  <div class="st-card-head">
    <h2><?= e($s['reference']) ?></h2>
    <span class="st-head-badges"><?php if ($isArch): ?><span class="badge badge-archived">Archived</span><?php endif; ?><span class="badge badge-<?= e($s['status']) ?>"><?= e(STATUS_TEXT[$s['status']] ?? $s['status']) ?></span></span>
  </div>
  <div class="st-person st-person-lg"><span class="st-avatar" aria-hidden="true"><?= e(ow_initials($s['customer_name'])) ?></span><span><strong><?= e($s['customer_name']) ?></strong><span class="cell-sub"><?= e($s['phone']) ?> &middot; <?= e($s['email']) ?></span></span></div>
  <dl class="st-facts">
    <div><dt>Branch</dt><dd><?= e($s['branch_name']) ?></dd></div>
    <div><dt>Date</dt><dd><?= e(date('M j, Y', strtotime($s['reservation_date']))) ?></dd></div>
    <div><dt>Time</dt><dd><?= e(date('g:i A', strtotime($s['reservation_time']))) ?></dd></div>
    <div><dt>Guests</dt><dd><?= (int) $s['guests'] ?></dd></div>
    <div><dt>Occasion</dt><dd><?= e($s['occasion'] ?: 'None') ?></dd></div>
    <div><dt>Special requests</dt><dd><?= e($s['special_requests'] ?: 'None') ?></dd></div>
    <div><dt>Staff notes</dt><dd><?= e($s['admin_notes'] ?: 'None') ?></dd></div>
    <div><dt>Requested on</dt><dd><?= e(ow_dt((string) $s['created_at'])) ?></dd></div>
  </dl>
  <div class="st-pay">
    <div class="st-pay-head"><strong>GCash payment</strong><span class="badge badge-<?= e($s['payment_status']) ?>"><?= e(PAY_TEXT[$s['payment_status']] ?? $s['payment_status']) ?></span></div>
    <dl class="st-facts st-facts-tight">
      <div><dt>Payment option</dt><dd><?= e(($s['payment_option'] ?? '') === 'full' ? 'Full payment' : (($s['payment_option'] ?? '') === 'downpayment' ? 'Downpayment' : '—')) ?></dd></div>
      <div><dt>Total estimated (expected)</dt><dd><?= e(ow_money($s['total_amount'])) ?></dd></div>
      <div><dt><?= $s['payment_status'] === 'verified' ? 'Verified amount' : 'Amount due now (not yet verified)' ?></dt><dd><?= e(ow_money($s['amount_due'])) ?></dd></div>
      <div><dt>Remaining balance (expected)</dt><dd><?= e(ow_money($s['balance'])) ?></dd></div>
      <div><dt>GCash reference</dt><dd><?= e($s['payment_reference'] ?: 'Not given') ?></dd></div>
      <div><dt>Proof of payment</dt><dd><?= $s['has_proof'] ? 'Uploaded (staff only)' : 'None uploaded' ?></dd></div>
      <?php if ($s['payment_status'] === 'verified'): ?><div><dt>Verified by</dt><dd><?= e($s['verified_by_name'] ?: 'Staff') ?>, <?= e(ow_dt((string) $s['verified_at'])) ?></dd></div><?php endif; ?>
    </dl>
  </div>
  <div class="st-history">
    <strong>Activity history</strong>
    <?php if (!$history): ?><p class="cell-sub">No audit entries for this reservation (it may pre-date the audit log).</p>
    <?php else: ?><ol>
      <?php foreach ($history as $h): ?>
      <li><span><?= e(audit_action_label($h['action'])) ?><?= $h['old_value'] !== null && $h['new_value'] !== null && $h['old_value'] !== $h['new_value'] ? ' (' . e($h['old_value']) . ' → ' . e($h['new_value']) . ')' : '' ?> · <?= e($h['actor_name'] ?: 'System') ?></span><time><?= e(ow_dt($h['created_at'])) ?></time></li>
      <?php endforeach; ?></ol><?php endif; ?>
  </div>
  <p class="cell-sub ow-readonly">Read-only view. Reservation and payment changes are made by staff.</p>
</div>
