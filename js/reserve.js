// reserve.js
// The reservation form. It runs inside account.php (the customer page)
// (same markup, same ids), for signed-in customers only.
//   a. The browser checks what the customer typed.
//   b. The form is sent to the server, which checks it AGAIN.
//   c. The success message is shown ONLY if the server says it saved the reservation.

// The guard stops the Sign out click from being bound twice
// (account.js and reserve.js touch the same button).
var signoutButton = document.getElementById('signout-button');
if (signoutButton !== null && signoutButton.dataset.signoutBound !== '1') {
  signoutButton.dataset.signoutBound = '1';
  signoutButton.addEventListener('click', signOut);
}

var form = document.getElementById('reservation-form');
var submitButton = document.getElementById('reservation-submit');
var statusBox = document.getElementById('form-status');
var successBox = document.getElementById('reservation-success');
var PREFIX = 'res-';

// The form fields (the ids are 'res-' + the name, with '-' instead of '_'), plus the one id that differs.
var FIELD_NAMES = ['name', 'phone', 'email', 'branch', 'guests', 'date', 'time', 'occasion', 'requests',
                   'payment_option', 'payment_reference', 'payment_proof', 'accept'];
function input(name) {
  return document.getElementById('res-' + name.replace(/_/g, '-'));
}
var CFG = window.SIFTED_CONFIG;
var PROOF_TYPES = ['image/png', 'image/jpeg', 'application/pdf'];

// The date picker can only choose from TOMORROW (1 day notice) up to 60 days ahead.
var firstDay = new Date();
firstDay.setDate(firstDay.getDate() + 1);
var lastDay = new Date();
lastDay.setDate(lastDay.getDate() + 60);
input('date').min = dateToText(firstDay);
input('date').max = dateToText(lastDay);

// Opening hours: requests are every hour, from opening until 1 hour before closing.
var BRANCH_HOURS = { calamba: { open: 10, close: 22 }, cabuyao: { open: 9, close: 21 } };
var timeHint = document.getElementById('time-hint');

// Fills the Time list with the hours that fit the chosen branch.
function updateTimeOptions() {
  var branch = input('branch').value;
  var select = input('time');
  var keep = select.value;
  select.replaceChildren();
  var first = document.createElement('option');
  first.value = '';
  first.textContent = branch === '' ? 'Choose a branch first' : 'Select a time';
  select.append(first);
  if (BRANCH_HOURS[branch] === undefined) {
    timeHint.textContent = 'Choose a branch to see the available times.';
    return;
  }
  var h = BRANCH_HOURS[branch];
  for (var hour = h.open; hour <= h.close - 1; hour++) {
    var option = document.createElement('option');
    option.value = pad2(hour) + ':00';
    option.textContent = formatTime12(option.value);
    select.append(option);
  }
  select.value = keep;   // keep the chosen time if it still exists
  timeHint.textContent = 'Times are every hour, from ' + formatTime12(pad2(h.open) + ':00') + ' to ' +
    formatTime12(pad2(h.close - 1) + ':00') + ' (we close at ' + formatTime12(pad2(h.close) + ':00') +
    '). Reserve at least 1 day ahead.';
  applyAvailability();
}
input('branch').addEventListener('change', function () { updateTimeOptions(); refreshAvailability(); });
updateTimeOptions();

// ---------------------------------------------------------------
// Seats left per time slot. The SERVER knows the bookings (GET /api/availability);
// we only show the answer next to each time. The server checks again when you send the form.
// ---------------------------------------------------------------
var availability = null;   // { '12:00': 14, ... } or null when unknown

function applyAvailability() {
  var guests = Number(input('guests').value) || 0;
  var select = input('time');
  var options = select.querySelectorAll('option');
  for (var i = 0; i < options.length; i++) {
    var option = options[i];
    if (option.value === '') { continue; }
    var text = formatTime12(option.value);
    var disable = false;
    if (availability !== null && availability[option.value] !== undefined) {
      var left = availability[option.value];
      if (left === 0) {
        text += ' - fully booked';
        disable = true;
      } else if (left < guests) {
        text += ' - only ' + left + ' seat' + (left === 1 ? '' : 's') + ' left';
        disable = true;
      } else if (left <= 6) {
        text += ' - ' + left + ' seat' + (left === 1 ? '' : 's') + ' left';
      }
    }
    option.textContent = text;
    option.disabled = disable;
    if (disable && select.value === option.value) { select.value = ''; }
  }
}

async function refreshAvailability() {
  var branch = input('branch').value;
  var date = input('date').value;
  availability = null;
  if (branch === '' || date === '') { applyAvailability(); return; }
  var result = await sendJson('GET', '/api/availability?branch=' + encodeURIComponent(branch) + '&date=' + encodeURIComponent(date));
  // ignore an old answer if the choice changed while we waited
  if (input('branch').value === branch && input('date').value === date) {
    availability = result.status === 200 ? result.data.seats_left : null;
    applyAvailability();
  }
}
input('date').addEventListener('change', refreshAvailability);
input('guests').addEventListener('input', applyAvailability);

// Guests: the - and + buttons change the number (2 to 10)
document.querySelectorAll('.stepper-btn').forEach(function (button) {
  button.addEventListener('click', function () {
    var field = input('guests');
    var next = (Number(field.value) || 2) + Number(button.dataset.step);
    field.value = String(Math.min(10, Math.max(2, next)));
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  });
});

// Reads every field. The result uses the names the server expects.
function readValues() {
  return {
    customer_name: input('name').value.trim(),
    phone: input('phone').value.trim(),
    email: input('email').value.trim(),
    branch: input('branch').value,
    guests: input('guests').value.trim(),
    reservation_date: input('date').value,
    reservation_time: input('time').value,
    occasion: input('occasion').value.trim(),
    special_requests: input('requests').value.trim(),
    payment_option: selectedOption(),
    payment_method: 'gcash',   // GCash is the only payment method
    payment_reference: input('payment_reference').value.trim(),
    accept_policies: input('accept').checked
  };
}

// Which payment option radio is chosen ('' if none)
function selectedOption() {
  var chosen = document.querySelector('input[name="payment_option"]:checked');
  return chosen ? chosen.value : '';
}

// The chosen proof file (or null)
function proofFile() {
  var files = input('payment_proof').files;
  return files.length > 0 ? files[0] : null;
}

// Server field names -> the short names used in the page ids
var SERVER_TO_PAGE = {
  customer_name: 'name', phone: 'phone', email: 'email', branch: 'branch', guests: 'guests',
  reservation_date: 'date', reservation_time: 'time', occasion: 'occasion', special_requests: 'requests',
  payment_option: 'payment_option', payment_reference: 'payment_reference', payment_proof: 'payment_proof', accept_policies: 'accept'
};

// Checks the values. Gives back an object of messages like { phone: '...' } (page names). Empty = fine.
function checkValues(values) {
  var errors = {};
  var nameMessage = checkName(values.customer_name);
  if (nameMessage !== '') { errors.name = nameMessage; }
  var phoneMessage = checkPhone(values.phone);
  if (phoneMessage !== '') { errors.phone = phoneMessage; }
  var emailMessage = checkEmail(values.email);
  if (emailMessage !== '') { errors.email = emailMessage; }

  if (values.branch === '') {
    errors.branch = 'Please choose a branch.';
  }
  var guests = Number(values.guests);
  if (values.guests === '') {
    errors.guests = 'Please enter the number of guests.';
  } else if (Number.isInteger(guests) === false) {
    errors.guests = 'Number of guests must be a whole number.';
  } else if (guests < 2 || guests > 20) {
    errors.guests = 'Online reservations are for 2 to 20 guests. For larger groups, please call or message the cafe.';
  }
  // Dates like 2026-10-24 can be compared with < and >
  if (values.reservation_date === '') {
    errors.date = 'Please choose a date.';
  } else if (values.reservation_date < dateToText(firstDay)) {
    errors.date = 'We need at least 1 day notice. Please choose tomorrow or a later date.';
  } else if (values.reservation_date > dateToText(lastDay)) {
    errors.date = 'Reservations can be requested up to 60 days ahead.';
  }
  if (values.reservation_time === '') {
    errors.time = 'Please choose a time.';
  }
  if (values.occasion.length > 100) { errors.occasion = 'Occasion must be 100 characters or fewer.'; }
  if (values.special_requests.length > 500) { errors.requests = 'Special requests must be 500 characters or fewer.'; }

  // Payment
  if (values.payment_option === '') {
    errors.payment_option = 'Please choose Downpayment or Full payment.';
  }
  if (values.payment_reference !== '' && /^[0-9 ]{6,20}$/.test(values.payment_reference) === false) {
    errors.payment_reference = 'The GCash reference number can only have digits (6 to 20).';
  }
  var file = proofFile();
  if (CFG.requireProof) {
    if (file === null) {
      errors.payment_proof = 'Please attach your proof of payment.';
    } else if (PROOF_TYPES.indexOf(file.type) === -1) {
      errors.payment_proof = 'Proof of payment must be a PNG, JPG or PDF file.';
    } else if (file.size === 0 || file.size > CFG.maxProofMB * 1024 * 1024) {
      errors.payment_proof = 'Proof of payment must be smaller than ' + CFG.maxProofMB + ' MB.';
    }
  }
  if (values.accept_policies !== true) {
    errors.accept = 'Please accept the reservation terms before sending your request.';
  }
  return errors;
}

// Shows the errors (page names) and focuses the first wrong field.
function showErrors(errors) {
  showFieldErrors(PREFIX, FIELD_NAMES, errors);
}

// Errors from the server use the server's names, so rename them first.
function renameServerErrors(serverErrors) {
  var renamed = {};
  for (var key in serverErrors) {
    renamed[SERVER_TO_PAGE[key] || key] = serverErrors[key];
  }
  return renamed;
}

// Checks one field when the customer leaves it
function connectFieldEvents(name) {
  input(name).addEventListener('blur', function (event) {
    if (event.relatedTarget === submitButton) {
      return;   // the submit step checks every field anyway
    }
    setFieldError(PREFIX, name, checkValues(readValues())[name] || '');
  });
  // 'input' fires while typing; 'change' fires for lists, the checkbox and the file picker
  var recheck = function () {
    if (input(name).closest('.field').classList.contains('has-error')) {
      setFieldError(PREFIX, name, checkValues(readValues())[name] || '');
      // when no field shows an error any more, remove the red message above the button
      if (form.querySelector('.has-error') === null) {
        showMessage(statusBox, 'error', '');
      }
    }
  };
  input(name).addEventListener('input', recheck);
  input(name).addEventListener('change', recheck);
}
for (var j = 0; j < FIELD_NAMES.length; j++) {
  if (FIELD_NAMES[j] !== 'payment_option') {   // payment_option is a group of radio buttons, handled below
    connectFieldEvents(FIELD_NAMES[j]);
  }
}

// Pre-fill name, phone and email from the account (the customer can still change them).
async function prefill() {
  var me = await sendJson('GET', '/api/customer/me');
  if (me.status === 401) {
    goToSignIn(me.data.code, 'account.php#reserve');
    return;
  }
  if (me.status === 200) {
    input('name').value = me.data.customer.full_name;
    input('phone').value = me.data.customer.phone;
    input('email').value = me.data.customer.email;
    if (typeof updatePaymentSection === 'function') { updatePaymentSection(); }   // refreshes the summary and the review list with the filled-in values
  }
}
prefill();


// ---------------------------------------------------------------
// Booking summary, payment option and payment instructions
// ---------------------------------------------------------------
// The amounts come from the server (POST /api/reservations/quote). The browser only DISPLAYS them.
var quote = null;   // the last answer from the server, or null

async function refreshQuote() {
  var guests = Number(input('guests').value);
  quote = null;
  if (Number.isInteger(guests) && guests >= 2 && guests <= 10) {
    var result = await sendJson('POST', '/api/reservations/quote', { guests: guests });
    if (result.status === 401) {
      goToSignIn(result.data.code, 'account.php#reserve');
      return;
    }
    if (result.status === 200) {
      quote = result.data.quote;
    }
  }
  updateReview();
  updatePaymentSection();
}

function addSummaryRow(list, label, value) {
  var dt = document.createElement('dt');
  var dd = document.createElement('dd');
  dt.textContent = label;
  dd.textContent = value;
  list.append(dt, dd);
}

// The time this reservation would end, like '2:00 PM'
function endTimeText(startText, hours) {
  var hour = Number(startText.slice(0, 2)) + Number(hours);
  return formatTime12(pad2(hour % 24) + startText.slice(2, 5));
}

// The short "Booking summary" at the end of the form: only what the customer is about to send.
function updateReview() {
  var list = document.getElementById('review-list');
  list.replaceChildren();
  var v = readValues();
  var branchName = { calamba: 'Calamba', cabuyao: 'Cabuyao' }[v.branch] || '';
  addSummaryRow(list, 'Name', v.customer_name || '-');
  addSummaryRow(list, 'Date', v.reservation_date ? formatDateLong(v.reservation_date) : '-');
  addSummaryRow(list, 'Time', v.reservation_time ? formatTime12(v.reservation_time) + ' to ' + endTimeText(v.reservation_time, CFG.durationHours) + (branchName ? ' (' + branchName + ')' : '') : '-');
  addSummaryRow(list, 'Guests', v.guests || '-');
  if (v.special_requests) { addSummaryRow(list, 'Special requests', v.special_requests); }
  if (quote !== null && v.payment_option !== '') {
    addSummaryRow(list, PAYMENT_OPTION_LABELS[v.payment_option], formatMoney(quote.options[v.payment_option].amount_due));
  } else if (quote !== null) {
    addSummaryRow(list, 'Downpayment (' + quote.downpayment_percent + '%)', formatMoney(quote.options.downpayment.amount_due));
  }
}
form.addEventListener('input', updateReview);
form.addEventListener('change', updateReview);

// Shows the amounts on the two option cards, and the payment instructions for the chosen method.
function updatePaymentSection() {
  var downText = 'Enter guests first';
  var fullText = 'Enter guests first';
  var downNote = 'Pay the required deposit now.';
  if (quote !== null) {
    downText = formatMoney(quote.options.downpayment.amount_due);
    fullText = formatMoney(quote.options.full.amount_due);
    downNote = 'Pay the deposit now. Remaining balance: ' + formatMoney(quote.options.downpayment.balance);
  }
  document.getElementById('opt-downpayment-amount').textContent = downText;
  document.getElementById('opt-full-amount').textContent = fullText;
  document.getElementById('opt-downpayment-note').textContent = downNote;
  document.getElementById('opt-full-note').textContent = 'Pay the whole amount now. Remaining balance: ' + formatMoney(0);

  // GCash card: recipient, QR image (from config.js) and the exact amount
  var gcash = CFG.gcash;
  var option = selectedOption();
  document.getElementById('pay-recipient').textContent = gcash.recipientName;
  document.getElementById('pay-note').textContent = gcash.note;
  var qr = document.getElementById('pay-qr');
  qr.src = gcash.qrImage;
  qr.alt = gcash.qrAlt;
  document.getElementById('qr-big').src = gcash.qrImage;
  document.getElementById('qr-big').alt = gcash.qrAlt;
  document.getElementById('pay-amount').textContent =
    (quote !== null && option !== '') ? formatMoney(quote.options[option].amount_due) : 'Choose a payment option (section B)';
  document.getElementById('proof-required-note').textContent = CFG.requireProof ? '(required: PNG, JPG or PDF, up to ' + CFG.maxProofMB + ' MB)' : '(optional: PNG, JPG or PDF)';
  updateReview();
}

// Keep the summary and payment section up to date
input('guests').addEventListener('input', refreshQuote);
input('guests').addEventListener('change', refreshQuote);
var optionRadios = document.querySelectorAll('input[name="payment_option"]');
for (var k = 0; k < optionRadios.length; k++) {
  optionRadios[k].addEventListener('change', function () {
    setFieldError(PREFIX, 'payment_option', '');
    updatePaymentSection();
  });
}

// Fill the terms from config.js: a few short groups, each a list of one-line rules, plus a privacy line
var policyList = document.getElementById('policy-list');
for (var p = 0; p < CFG.policies.length; p++) {
  // One <details> per group: native keyboard and touch support, no extra script needed.
  var group = document.createElement('details');
  group.className = 'terms-group';
  if (p === 0) { group.open = true; }   // the first group starts open
  var heading = document.createElement('summary');
  var title = document.createElement('span');
  title.textContent = CFG.policies[p].title;
  heading.append(title);
  var list = document.createElement('ul');
  for (var i = 0; i < CFG.policies[p].items.length; i++) {
    var li = document.createElement('li');
    li.textContent = CFG.policies[p].items[i];
    list.append(li);
  }
  group.append(heading, list);
  policyList.append(group);
}
document.getElementById('terms-privacy').textContent = CFG.privacyNote;
document.getElementById('duration-hint').textContent = 'Each reservation holds a table for ' + CFG.durationHours + ' hours.';
refreshQuote();

// Sends the form to the server.
form.addEventListener('submit', async function (event) {
  event.preventDefault();   // stop the browser from reloading the page
  showMessage(statusBox, 'error', '');

  var values = readValues();
  var errors = checkValues(values);
  showErrors(errors);
  if (Object.keys(errors).length > 0) {
    showMessage(statusBox, 'error', 'Please fix the highlighted fields and try again.');
    return;
  }

  values.website = document.getElementById('res-website').value;   // the hidden bot trap. It must stay empty.
  // Sent as a multipart form so the REAL proof file is uploaded. NO amounts are sent: the server works them out itself.
  var formData = new FormData();
  Object.keys(values).forEach(function (key) { formData.append(key, String(values[key])); });
  var file = proofFile();
  if (file !== null) {
    formData.append('payment_proof', file, file.name);
  }
  submitButton.disabled = true;
  submitButton.textContent = 'Sending...';
  var result = await sendForm('/api/reservations', formData);
  submitButton.disabled = false;
  submitButton.textContent = 'Send reservation request';

  if (result.status === 201 || result.status === 200) {
    showSuccess(result.data);
  } else if (result.status === 401) {
    goToSignIn(result.data.code, 'account.php#reserve');
  } else if ((result.status === 400 || result.status === 409) && result.data.errors) {
    showErrors(renameServerErrors(result.data.errors));
    showMessage(statusBox, 'error', 'Please fix the highlighted fields and try again.');
  } else if (result.status === 0) {
    showMessage(statusBox, 'error', result.data.error + ' Your request was NOT sent.');
  } else {
    showMessage(statusBox, 'error', (result.data.error || 'Something went wrong.') + ' Your request was NOT sent.');
  }
});

// Replaces the form with the "Request received" box.
// The reservation status and the payment status are shown separately on purpose.
function showSuccess(data) {
  var r = data.reservation;
  document.getElementById('success-message').textContent = data.message;
  var details = document.getElementById('success-details');
  details.replaceChildren();
  var rows = [
    ['Reference', r.reference], ['Name', r.customer_name], ['Branch', r.branch_name],
    ['Date', formatDateLong(r.reservation_date)], ['Time', formatTime12(r.reservation_time)],
    ['Guests', String(r.guests)],
    ['Reservation status', 'Pending: waiting for staff confirmation (not confirmed yet)'],
    ['Payment status', PAYMENT_LABELS[r.payment_status] || r.payment_status],
    ['Payment', (PAYMENT_OPTION_LABELS[r.payment_option] || '') + ' via GCash'],
    ['Total estimated amount', formatMoney(r.total_amount)],
    ['GCash amount sent', formatMoney(r.amount_due)],
    ['Remaining balance', formatMoney(r.balance)]
  ];
  for (var i = 0; i < rows.length; i++) {
    var label = document.createElement('dt');
    var value = document.createElement('dd');
    label.textContent = rows[i][0];
    value.textContent = rows[i][1];
    details.append(label, value);
  }
  form.hidden = true;
  successBox.hidden = false;
  successBox.focus();
  successBox.scrollIntoView({ block: 'start' });
}

// "Make another request" brings the form back (name, phone and email are filled again).
document.getElementById('new-request').addEventListener('click', function () {
  form.reset();
  updateTimeOptions();
  refreshQuote();
  for (var i = 0; i < FIELD_NAMES.length; i++) {
    setFieldError(PREFIX, FIELD_NAMES[i], '');
  }
  showMessage(statusBox, 'error', '');
  successBox.hidden = true;
  form.hidden = false;
  updatePaymentSection();
  prefill();
});


// Terms first: the form appears only after the customer accepts the terms.
(function () {
  var accept = document.getElementById('res-accept');
  var card = document.getElementById('form-card');
  if (!accept || !card) { return; }
  function sync() {
    card.hidden = !accept.checked;
    if (accept.checked) { setFieldError(PREFIX, 'accept', ''); }
  }
  accept.addEventListener('change', function () {
    sync();
    if (accept.checked) { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
  sync();
})();
