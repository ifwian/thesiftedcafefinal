<?php /** The details of ONE reservation ($s). Used inside the staff modal. */ $isArch = !empty($s['archived_at']); $next = $isArch ? [] : allowed_next_statuses($s['status'], (string) $s['payment_status']); ?>
<div class="st-detail-body">
            <div class="st-card-head">
              <h2 id="detail-title"><?= e($s['reference']) ?></h2>
              <span class="st-head-badges"><?php if ($isArch): ?><span class="badge badge-archived">Archived</span><?php endif; ?><span class="badge badge-<?= e($s['status']) ?>"><?= e(STATUS_TEXT[$s['status']] ?? $s['status']) ?></span></span>
            </div>
            <div class="st-person st-person-lg"><span class="st-avatar" aria-hidden="true"><?= e(initials($s['customer_name'])) ?></span><span><strong><?= e($s['customer_name']) ?></strong><span class="cell-sub"><?= e($s['phone']) ?> &middot; <?= e($s['email']) ?></span></span></div>
            <dl class="st-facts">
              <div><dt>Branch</dt><dd><?= e($s['branch_name']) ?></dd></div>
              <div><dt>Date</dt><dd><?= e(date_short($s['reservation_date'])) ?></dd></div>
              <div><dt>Time</dt><dd><?= e(time12($s['reservation_time'])) ?> to <?= e(date('g:i A', strtotime($s['reservation_time']) + cfg('duration_hours') * 3600)) ?></dd></div>
              <div><dt>Guests</dt><dd><?= (int) $s['guests'] ?></dd></div>
              <div><dt>Occasion</dt><dd><?= e($s['occasion'] ?: 'None') ?></dd></div>
              <?php if (!empty($s['allergies'])): ?><div><dt>Allergies (older booking)</dt><dd><?= e($s['allergies']) ?></dd></div><?php endif; ?>
              <div><dt>Special requests / notes</dt><dd><?= e($s['special_requests'] ?: 'None') ?></dd></div>
            </dl>
            <div class="st-pay">
              <div class="st-pay-head"><strong>GCash payment</strong><span class="badge badge-<?= e($s['payment_status']) ?>"><?= e(PAY_TEXT[$s['payment_status']] ?? $s['payment_status']) ?></span></div>
              <div class="st-proof"><span class="st-proof-thumb" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg></span>
                <span>
                <?php if ($s['has_proof']): ?>
                  <strong><?= e($s['proof_name']) ?></strong><span class="cell-sub"><a href="staff-proof.php?id=<?= (int) $s['id'] ?>" target="_blank" rel="noopener">Open proof of payment</a></span>
                <?php else: ?>
                  <strong>No proof uploaded</strong><span class="cell-sub">Nothing to check</span>
                <?php endif; ?>
                </span></div>
              <dl class="st-facts st-facts-tight">
                <div><dt>Total estimated</dt><dd><?= e(money($s['total_amount'])) ?></dd></div>
                <div><dt>Amount paid now</dt><dd><?= e(money($s['amount_due'])) ?></dd></div>
                <div><dt>Remaining balance</dt><dd><?= e(money($s['balance'])) ?></dd></div>
                <div><dt>GCash reference</dt><dd><?= e($s['payment_reference'] ?: 'Not given') ?></dd></div>
                <?php if ($s['payment_status'] === 'verified'): ?>
                <div><dt>Verified</dt><dd><?= e($s['verified_by_name'] ?: 'Staff') ?>, <?= e((string) $s['verified_at']) ?></dd></div>
                <?php endif; ?>
              </dl>
              <p class="cell-sub">A proof is only a screenshot. Check the cafe's own GCash records before marking it verified.</p>
            </div>

            <div class="st-history">
              <strong>History</strong>
              <ol>
                <li><span>Requested</span><time><?= e(date_short((string) $s['created_at'])) ?>, <?= e(time12(substr((string) $s['created_at'], 11, 5))) ?></time></li>
                <?php if (($s['payment_status'] ?? '') === 'verified' && !empty($s['verified_at'])): ?>
                <li><span>Payment verified by <?= e($s['verified_by_name'] ?: 'staff') ?></span><time><?= e(date_short((string) $s['verified_at'])) ?></time></li>
                <?php endif; ?>
                <?php if (!empty($s['updated_at']) && $s['updated_at'] !== $s['created_at']): ?>
                <li><span>Last updated (now <?= e(strtolower(STATUS_TEXT[$s['status']] ?? $s['status'])) ?>)</span><time><?= e(date_short((string) $s['updated_at'])) ?></time></li>
                <?php endif; ?>
                <?php if ($isArch): ?>
                <li><span>Archived<?= !empty($archivedByName) ? ' by ' . e($archivedByName) : '' ?></span><time><?= e(date_short((string) $s['archived_at'])) ?>, <?= e(time12(substr((string) $s['archived_at'], 11, 5))) ?></time></li>
                <?php endif; ?>
              </ol>
            </div>

            <form method="post" action="actions/staff-process.php" class="st-notes">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="return" value="<?= e(back_query(['id' => $s['id']])) ?>">
              <label>Staff notes
                <textarea name="notes" rows="3" maxlength="500" placeholder="Visible to staff only"><?= e($s['admin_notes'] ?? '') ?></textarea></label>
              <button type="submit" name="action" value="notes" class="btn btn-outline btn-small">Save notes</button>
            </form>

            <?php $ret = back_query(['id' => $s['id']]); $retList = back_query(); ?>
            <div class="dialog-actions st-actions">
              <?php if (in_array('confirmed', $next, true)): ?>
              <form method="post" action="actions/staff-process.php" onsubmit="return confirm('Confirm this reservation? The customer will see it as Confirmed.');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button type="submit" name="action" value="confirmed" class="btn btn-confirm btn-small">Confirm</button></form>
              <?php endif; ?>
              <?php if (in_array('completed', $next, true)): ?>
              <form method="post" action="actions/staff-process.php" onsubmit="return confirm('Mark this reservation as completed? This cannot be changed afterwards.');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button type="submit" name="action" value="completed" class="btn btn-primary btn-small">Mark completed</button></form>
              <?php endif; ?>
              <?php if (!$isArch && $s['payment_status'] === 'proof_submitted' && in_array($s['status'], ['pending', 'confirmed'], true)): ?>
              <form method="post" action="actions/staff-process.php" onsubmit="return confirm('Verify this payment? Please check the proof against the GCash records first.');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button type="submit" name="action" value="verify" class="btn btn-verify btn-small">Verify payment</button></form>
              <?php endif; ?>
              <?php if (in_array('cancelled', $next, true)): ?>
              <button type="button" class="btn btn-warn btn-small" data-act="cancelled" data-id="<?= (int) $s['id'] ?>" data-ref="<?= e($s['reference']) ?>" data-return="<?= e($ret) ?>">Cancel reservation</button>
              <?php endif; ?>
              <?php if ($isArch): ?>
              <button type="button" class="btn btn-primary btn-small" data-act="restore" data-id="<?= (int) $s['id'] ?>" data-ref="<?= e($s['reference']) ?>" data-return="<?= e($retList) ?>">Restore to active list</button>
              <button type="button" class="btn btn-danger btn-small" data-act="delete" data-id="<?= (int) $s['id'] ?>" data-ref="<?= e($s['reference']) ?>" data-return="<?= e($retList) ?>">Delete permanently</button>
              <?php elseif (in_array($s['status'], ARCHIVABLE, true)): ?>
              <button type="button" class="btn btn-archive btn-small" data-act="archive" data-id="<?= (int) $s['id'] ?>" data-ref="<?= e($s['reference']) ?>" data-return="<?= e($retList) ?>">Archive</button>
              <?php endif; ?>
            </div>
            <?php if (!$isArch && $s['status'] === 'pending' && $s['payment_status'] !== 'verified'): ?><p class="cell-sub">Verify the payment first. The Confirm button appears once the payment is verified.</p><?php endif; ?>
            <?php if ($isArch): ?><p class="cell-sub">This reservation is archived. Restore it to work on it again, or delete it permanently.</p>
            <?php elseif (!$next): ?><p class="cell-sub">This reservation is <?= e($s['status']) ?>, so its status can no longer be changed.</p><?php endif; ?>
          </div>
