<?php
/*
 * CUSTOMER ACCOUNT PAGE (reservation form + reservation history).
 * This is a PHP page because it must check the login BEFORE showing anything:
 *   not logged in -> sent to login.html      staff -> sent to staff.php
 * The reservation list itself is loaded by js/account.js from api/index.php.
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login_page('account.php');   // not signed in -> login page
if ($me['role'] === 'staff' || $me['role'] === 'owner') { header('Location: ' . home_page_for($me['role'])); exit; }
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My account | The Sifted Cafe</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#f5eee0">
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&family=Geist:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="customer-page">
  <div class="dash-shell">

    <!-- ================= SIDEBAR (dark, sticky) ================= -->
    <aside class="dash-sidebar" id="dash-sidebar" aria-label="Account menu">
      <a class="dash-brand" href="index.html">
        <span class="dash-brand-name">the sifted</span>
        <span class="dash-brand-sub">cafe &amp; bakery &middot; my account</span>
      </a>

      <nav class="dash-nav" aria-label="Dashboard sections">
        <a href="index.html">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
          <span>Home</span>
        </a>
        <a href="#reserve" id="nav-reserve" data-view-link="reserve">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M12 14v4M10 16h4"/></svg>
          <span>Make a reservation</span>
        </a>
        <a href="#history" id="nav-dashboard" data-view-link="dashboard">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span>Reservation history</span>
        </a>
      </nav>

      <div class="dash-foot">
        <div class="dash-user">
          <span class="dash-user-avatar" id="dash-user-initials" aria-hidden="true"></span>
          <span class="dash-user-meta">
            <strong id="dash-user-name"></strong>
            <span id="dash-user-email"></span>
          </span>
        </div>
        <button type="button" class="dash-signout" id="signout-button">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
          <span>Sign out</span>
        </button>
      </div>
    </aside>

    <!-- Dark film behind the open drawer (small screens) -->
    <div class="dash-scrim" id="dash-scrim" hidden></div>

    <!-- Mobile top bar: menu button + brand (hidden on desktop, where the sidebar is always visible) -->
    <div class="dash-topbar">
      <button type="button" class="dash-toggle" id="dash-toggle"
              aria-expanded="false" aria-controls="dash-sidebar"
              aria-label="Open the navigation menu">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <a class="dash-topbar-brand" href="index.html">the sifted</a>
      <span class="dash-user-avatar dash-topbar-avatar" id="dash-topbar-initials" aria-hidden="true"></span>
    </div>

    <!-- ================= MAIN CONTENT (two views, no page reload) ================= -->
    <main id="main" class="dash-content">

      <!-- VIEW 1: the dashboard -->
      <div id="view-dashboard" class="dash-view" tabindex="-1">
        <div class="page-title">
          <span class="eyebrow">My account</span>
          <h1 id="greeting">Reservation history</h1>
          <p class="muted">Your current and previous reservations, with their status and payment.</p>
        </div>

        <div id="load-error" class="alert alert-error" hidden>
          <span id="load-error-text">Something went wrong.</span>
          <button type="button" class="btn btn-outline btn-small" id="retry-button">Try again</button>
        </div>

        <section class="card" aria-labelledby="upcoming-title">
          <h2 id="upcoming-title">Current reservations</h2>
          <p id="upcoming-loading" class="muted">Loading your reservations...</p>
          <ul class="res-list" id="upcoming-list"></ul>
          <p id="upcoming-empty" class="muted" hidden>You have no current reservations. <a href="#reserve" data-view-link="reserve">Make a reservation</a></p>
        </section>
        <section class="card" id="history" aria-labelledby="history-title">
          <h2 id="history-title">Previous reservations</h2>
          <p id="history-loading" class="muted">Loading your reservations...</p>
          <ul class="res-list" id="history-list"></ul>
          <p id="history-empty" class="muted" hidden>Your past reservations will appear here.</p>
        </section>
      </div>
      <!-- VIEW 2: the reservation form, embedded in the same layout (js/reserve.js runs it). -->
      <section id="view-reserve" class="dash-view" tabindex="-1" hidden>
        <div class="page-title">
          <span class="eyebrow">Reservations</span>
          <h1>Make a reservation</h1>
          <p class="muted">Step 1: read the reservation terms. Step 2: fill in the form. Our team reviews every request and confirms it separately.</p>
        </div>

        <!-- TERMS FIRST: the form below stays hidden until the terms are accepted -->
        <section class="card terms-card" id="terms-card" aria-labelledby="terms-title">
          <h2 id="terms-title"><span class="section-num">1</span> Reservation terms</h2>

          <div class="terms-quick-summary" aria-label="Quick summary">
            <h3>Quick summary</h3>
            <ul class="terms-facts">
              <li><span>Guests</span><strong>2&ndash;10</strong></li>
              <li><span>Book ahead</span><strong>1+ day</strong></li>
              <li><span>Table held</span><strong>15 min</strong></li>
              <li><span>Free cancel</span><strong>24 h before</strong></li>
              <li><span>Payment</span><strong>GCash only</strong></li>
            </ul>
          </div>

          <div id="policy-list" class="terms-grid"></div>
          <p class="terms-privacy" id="terms-privacy"></p>
          <div class="field terms-accept">
            <label class="check-row"><input type="checkbox" id="res-accept" aria-describedby="err-res-accept"> I have read and accept the reservation terms.</label>
            <p class="field-error" id="err-res-accept" aria-live="polite"></p>
          </div>
        </section>

        <section class="card" id="form-card" hidden>
          <h2 class="form-card-title"><span class="section-num">2</span> Reservation form</h2>
          <form id="reservation-form" novalidate>

            <!-- A. Reservation details -->
            <fieldset class="form-section">
              <legend><span class="section-num">A</span> Reservation details</legend>
              <p class="form-note">Fields marked <span class="req" aria-hidden="true">*</span> are required.</p>
              <div class="form-grid">
                <h3 class="form-subtitle span-2">Your contact details</h3>
                <div class="field">
                  <label for="res-name">Name <span class="req" aria-hidden="true">*</span></label>
                  <input id="res-name" type="text" placeholder="Full name" required aria-required="true" autocomplete="name" maxlength="100">
                  <p class="field-error" id="err-res-name" aria-live="polite"></p>
                </div>
                <div class="field">
                  <label for="res-phone">Contact number <span class="req" aria-hidden="true">*</span></label>
                  <input id="res-phone" type="tel" placeholder="e.g. 0917 123 4567" required aria-required="true" autocomplete="tel" maxlength="30">
                  <p class="field-error" id="err-res-phone" aria-live="polite"></p>
                </div>
                <div class="field span-2">
                  <label for="res-email">Email <span class="req" aria-hidden="true">*</span></label>
                  <input id="res-email" type="email" placeholder="name@example.com" required aria-required="true" autocomplete="email" maxlength="150">
                  <p class="field-error" id="err-res-email" aria-live="polite"></p>
                </div>
                <h3 class="form-subtitle span-2">Table and time</h3>
                <div class="field">
                  <label for="res-branch">Branch <span class="req" aria-hidden="true">*</span></label>
                  <select id="res-branch">
                    <option value="">Select a branch</option>
                    <option value="calamba">Calamba</option>
                    <option value="cabuyao">Cabuyao</option>
                  </select>
                  <p class="field-error" id="err-res-branch" aria-live="polite"></p>
                </div>
                <div class="field">
                  <label for="res-guests">Number of guests <span class="req" aria-hidden="true">*</span></label>
                  <div class="stepper"><button type="button" class="stepper-btn" data-step="-1" aria-label="One guest less">&minus;</button><input id="res-guests" type="number" min="2" max="10" inputmode="numeric" value="2"><button type="button" class="stepper-btn" data-step="1" aria-label="One guest more">+</button></div>
                  <p class="field-error" id="err-res-guests" aria-live="polite"></p>
                </div>
                <div class="field">
                  <label for="res-date">Date <span class="req" aria-hidden="true">*</span></label>
                  <input id="res-date" type="date">
                  <p class="field-error" id="err-res-date" aria-live="polite"></p>
                </div>
                <div class="field">
                  <label for="res-time">Time slot <span class="req" aria-hidden="true">*</span></label>
                  <select id="res-time"></select>
                  <p class="field-error" id="err-res-time" aria-live="polite"></p>
                </div>
                <p class="field-hint span-2" id="time-hint">Choose a branch to see the time slots.</p>
                <p class="field-hint span-2" id="duration-hint"></p>
                <h3 class="form-subtitle span-2">Additional information</h3>
                <div class="field span-2">
                  <label for="res-occasion">Occasion <span class="optional">(optional)</span></label>
                  <input id="res-occasion" type="text" placeholder="e.g. Birthday, anniversary" maxlength="100">
                  <p class="field-error" id="err-res-occasion" aria-live="polite"></p>
                </div>
                <div class="field span-2">
                  <label for="res-requests">Special requests / additional notes <span class="optional">(optional)</span></label>
                  <textarea id="res-requests" rows="3" maxlength="500" placeholder="Let us know if you have any special requests for your reservation."></textarea>
                  <p class="field-hint">For example a celebration, a preferred seating area, or a specific table if available.</p>
                  <p class="field-error" id="err-res-requests" aria-live="polite"></p>
                </div>
                <!-- Hidden bot trap (honeypot). Real people never see or fill it. -->
                <div class="hp" aria-hidden="true">
                  <label for="res-website">Leave this empty</label>
                  <input id="res-website" type="text" tabindex="-1" autocomplete="off">
                </div>
              </div>
            </fieldset>

            <!-- C. Payment option -->
            <fieldset class="form-section">
              <legend><span class="section-num">B</span> Payment option</legend>
              <div class="field" id="res-payment-option" tabindex="-1">
                <div class="option-grid">
                  <label class="option-card"><input type="radio" name="payment_option" value="downpayment">
                    <span class="option-title">Downpayment</span>
                    <span class="option-amount" id="opt-downpayment-amount">Choose guests first</span>
                    <span class="option-note" id="opt-downpayment-note">Pay the required deposit now.</span></label>
                  <label class="option-card"><input type="radio" name="payment_option" value="full">
                    <span class="option-title">Full payment</span>
                    <span class="option-amount" id="opt-full-amount">Choose guests first</span>
                    <span class="option-note" id="opt-full-note">Pay the whole amount now.</span></label>
                </div>
                <p class="field-error" id="err-res-payment-option" aria-live="polite"></p>
              </div>
            </fieldset>

            <!-- D. Pay with GCash (the only payment method) -->
            <fieldset class="form-section">
              <legend><span class="section-num">C</span> Pay with GCash</legend>
              <div class="gcash-card">
                <div class="gcash-qr-col">
                  <button type="button" class="qr-open" id="qr-open" aria-label="Open the GCash QR code in a larger viewer">
                    <img id="pay-qr" class="pay-qr" src="assets/gcash-qr.png" width="240" height="240" alt="GCash QR code of The Sifted Cafe">
                  </button>
                  <button type="button" class="btn btn-outline btn-small" id="qr-open-2">View larger QR</button>
                </div>
                <div class="gcash-info">
                  <span class="gcash-badge">GCash only</span>
                  <dl class="info-list" id="pay-lines">
                    <dt>Recipient</dt><dd id="pay-recipient">-</dd>
                    <dt>Amount to pay now</dt><dd><strong class="pay-amount-value" id="pay-amount">Choose a payment option (section B)</strong></dd>
                  </dl>
                  <ol class="gcash-steps">
                    <li>Choose your payment option in section B.</li>
                    <li>Open the GCash app and scan this QR code (or tap it to enlarge).</li>
                    <li>Type the exact amount shown above. In the note, write your <strong>full name</strong> and the <strong>reservation date</strong>.</li>
                    <li>Screenshot the receipt, then attach it below.</li>
                  </ol>
                  <p class="field-hint" id="pay-note"></p>
                </div>
              </div>

              <div class="field">
                <label for="res-payment-reference">GCash reference number <span class="optional">(optional)</span></label>
                <input id="res-payment-reference" type="text" placeholder="Reference number from your GCash receipt" inputmode="numeric" maxlength="20" autocomplete="off" aria-describedby="err-res-payment-reference">
                <p class="field-error" id="err-res-payment-reference" aria-live="polite"></p>
              </div>
              <div class="field">
                <label for="res-payment-proof">Proof of payment <span class="optional" id="proof-required-note">(PNG, JPG or PDF)</span></label>
                <input id="res-payment-proof" type="file" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" aria-describedby="proof-hint err-res-payment-proof">
                <p class="field-hint" id="proof-hint">Sending a proof does not verify your payment. Our staff checks it separately.</p>
                <p class="field-error" id="err-res-payment-proof" aria-live="polite"></p>
              </div>
            </fieldset>

            <!-- E. Review, terms and send -->
            <fieldset class="form-section">
              <legend><span class="section-num">D</span> Booking summary</legend>
                            <dl class="info-list summary-compact" id="review-list" aria-live="polite"></dl>
              <div id="form-status" class="form-status" aria-live="polite"></div>
              <button type="submit" class="btn btn-primary btn-block" id="reservation-submit">Send reservation request</button>
              <p class="field-hint center">This sends a request. Your table is not reserved until staff confirm it.</p>
            </fieldset>
          </form>
        </section>

        <section id="reservation-success" class="card reservation-success" tabindex="-1" hidden>
          <h2>Request sent: waiting for staff confirmation</h2>
          <p id="success-message"></p>
          <dl class="info-list" id="success-details"></dl>
          <div class="hero-actions">
            <a class="btn btn-primary" href="account.php#history">View my reservations</a>
            <button type="button" class="btn btn-outline" id="new-request">Make another request</button>
          </div>
        </section>
      </section>
    </main>
  </div>

  <dialog id="qr-dialog" class="qr-dialog" aria-labelledby="qr-title">
    <div class="qr-head">
      <h2 id="qr-title">GCash QR code</h2>
      <button type="button" class="auth-close" id="qr-close" aria-label="Close the QR viewer">&times;</button>
    </div>
    <div class="qr-stage" id="qr-stage" tabindex="0" aria-label="GCash QR code. Press Close to close the viewer.">
      <img id="qr-big" src="assets/gcash-qr.png" width="900" height="900" alt="GCash QR code of The Sifted Cafe. Scan it with the GCash app." draggable="false">
    </div>
    <div class="qr-controls" style="justify-content: center; gap: var(--sp-3);">
      <button type="button" class="btn btn-primary btn-small" id="qr-close-2">Close</button>
    </div>
    <p class="field-hint center">Tip: pinch to close or tap the close button.</p>
  </dialog>

  <dialog id="details-dialog" class="details-dialog" aria-labelledby="details-title">
    <h2 id="details-title">Reservation details</h2>
    <dl class="info-list" id="details-list"></dl>
    <p class="muted" id="details-note"></p>
    <div class="dialog-actions">
      <button type="button" class="btn btn-warn btn-small" id="details-cancel" hidden>Cancel this reservation</button>
      <button type="button" class="btn btn-primary btn-small" id="details-close">Close</button>
    </div>
  </dialog>

  <!-- Confirmation window for cancelling a reservation -->
  <dialog id="cancel-dialog" class="details-dialog" aria-labelledby="cancel-title">
    <h2 id="cancel-title">Cancel this reservation?</h2>
    <p id="cancel-text"></p>
    <p class="field-error" id="cancel-error" aria-live="polite"></p>
    <div class="dialog-actions">
      <button type="button" class="btn btn-outline btn-small" id="cancel-keep">Keep it</button>
      <button type="button" class="btn btn-danger btn-small" id="cancel-confirm">Yes, cancel it</button>
    </div>
  </dialog>

  <script src="js/config.js"></script>
  <script src="js/shared.js"></script>
  <script src="js/account.js"></script>
  <script src="js/reserve.js"></script>
  <script src="js/qr-viewer.js"></script>
</body>
</html>
