// shared.js
// Small helpers used by more than one page (index, account, reserve).
// Loaded before the page's own script.

// Sends JSON to the server and gives back { status, data }.
// status 0 means the server could not be reached at all.
// Our PHP API is the file api/index.php. A call such as '/api/customer/login' is turned into
// '<folder>/api/index.php/customer/login'. The site may live in a subfolder (XAMPP:
// http://localhost/thesifted/), and all our pages sit in the main folder, so the folder is the page's own folder.
function apiUrl(url) {
  var folder = window.location.pathname.replace(/[^/]*$/, '');   // '/thesifted/' or '/'
  return url.indexOf('/api/') === 0 ? folder + 'api/index.php/' + url.slice(5) : url;
}

// Every failure becomes { status: 0, data: { error: '<what really went wrong>' } }.
// Before, ANY problem (page not served by PHP, PHP crash, blank 500...) showed the same
// "could not reach the server" text, which hid the real cause. Now the message says what happened,
// and the details are written to the browser console (F12).
function apiFailure(message, httpStatus) {
  return { status: 0, httpStatus: httpStatus || 0, data: { error: message, code: 'bad_reply' } };
}

function explainBadReply(httpStatus, text) {
  if (text.indexOf('<?php') !== -1) {
    return 'The server sent back PHP source code instead of running it. Open the site through Apache (XAMPP), for example http://localhost/thesifted/, not through another web server.';
  }
  if (httpStatus === 404 || httpStatus === 405 || httpStatus === 501 || httpStatus === 403) {
    return 'The server could not run the PHP backend (HTTP ' + httpStatus + '). The site must be opened through Apache (XAMPP), for example http://localhost/thesifted/, with the api folder next to this page.';
  }
  if (httpStatus >= 500) {
    return 'The server had a problem (HTTP ' + httpStatus + '). Please try again. If it keeps happening, see storage/logs/app.log or open api/index.php/health on this site.';
  }
  return 'The server sent an unexpected answer (HTTP ' + httpStatus + '). Please try again.';
}

// Does the request and turns the answer into { status, data }.
async function callApi(url, options) {
  if (window.location.protocol === 'file:') {
    return apiFailure('This page was opened as a file. Start Apache in XAMPP and open the site at http://localhost/thesifted/ instead.');
  }
  var response;
  try {
    response = await fetch(url, options);
  } catch (error) {
    console.error('Network error while calling ' + url, error);
    return apiFailure('We could not reach the server. Please check that Apache and MySQL are running (XAMPP) and your connection, then try again.');
  }
  var text = '';
  try { text = await response.text(); } catch (error) { text = ''; }
  var data = null;
  try { data = JSON.parse(text); } catch (error) { data = null; }
  if (data !== null && typeof data === 'object') {
    return { status: response.status, data: data };
  }
  console.error('The server did not answer with JSON. URL: ' + url + ', HTTP ' + response.status + ', start of the answer:', text.slice(0, 300));
  return apiFailure(explainBadReply(response.status, text), response.status);
}

async function sendJson(method, url, body) {
  url = apiUrl(url);
  var options = { method: method, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
  if (body !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }
  return callApi(url, options);
}

// Sends a FormData (text fields + the proof file) to the server. Same { status, data } answer as sendJson.
// Do NOT set Content-Type: the browser adds the multipart boundary itself.
async function sendForm(url, formData) {
  url = apiUrl(url);
  return callApi(url, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin', body: formData });
}

function pad2(number) {
  return (number < 10 ? '0' : '') + number;
}

// A date becomes text like '2026-10-24' (the format of the date picker)
function dateToText(date) {
  return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
}

// '2026-10-24' becomes 'Saturday, October 24, 2026'
function formatDateLong(dateText) {
  var parts = dateText.split('-');
  var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
  return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
}

// '2026-10-24' becomes 'Oct 24, 2026'
function formatDateShort(dateText) {
  var parts = dateText.split('-');
  var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
}

// '19:00' becomes '7:00 PM'
function formatTime12(timeText) {
  var hour = Number(timeText.slice(0, 2));
  var minute = timeText.slice(3, 5);
  var suffix = hour >= 12 ? 'PM' : 'AM';
  var hour12 = hour % 12;
  if (hour12 === 0) {
    hour12 = 12;
  }
  return hour12 + ':' + minute + ' ' + suffix;
}

var STATUS_LABELS = { pending: 'Pending', confirmed: 'Confirmed', cancelled: 'Cancelled', completed: 'Completed' };

// Shows a message box (kind is 'error' or 'success') inside a container. textContent = no HTML injection.
function showMessage(container, kind, message) {
  container.replaceChildren();
  if (message === '') {
    return;
  }
  var box = document.createElement('div');
  box.className = 'alert alert-' + kind;
  box.textContent = message;
  container.append(box);
}

// Shows (or clears) the error under a field. The field's id is prefix + name with '_' changed to '-',
// the error paragraph's id is 'err-' + prefix + name.
function fieldId(prefix, name) {
  return prefix + name.replace(/_/g, '-');
}
function setFieldError(prefix, name, message) {
  var input = document.getElementById(fieldId(prefix, name));
  var errorBox = document.getElementById('err-' + fieldId(prefix, name));
  if (input === null || errorBox === null) {
    return;
  }
  errorBox.textContent = message;
  if (message !== '') {
    input.closest('.field').classList.add('has-error');
    input.setAttribute('aria-invalid', 'true');
  } else {
    input.closest('.field').classList.remove('has-error');
    input.removeAttribute('aria-invalid');
  }
}
// Shows the errors of all listed fields and puts the cursor in the first wrong one.
function showFieldErrors(prefix, names, errors) {
  var first = null;
  for (var i = 0; i < names.length; i++) {
    setFieldError(prefix, names[i], errors[names[i]] || '');
    if (errors[names[i]] && first === null) {
      first = names[i];
    }
  }
  if (first !== null) {
    document.getElementById(fieldId(prefix, first)).focus();
  }
}

// Sends the visitor to the Log in page. 'next' is the page to open after signing in.
function goToSignIn(code, next) {
  var where = 'login.html?next=' + encodeURIComponent(next || 'account.php');
  if (code === 'session_expired') {
    where += '&expired=1';
  }
  window.location.href = where;
}

// Only these destinations may be opened after signing in (stops redirects to other places).
function safeNext(value) {
  if (value === 'account.php' || value === 'account.php#reserve' || value === 'staff.php' || value === 'owner.php') {
    return value;
  }
  return 'account.php';
}

async function signOut() {
  await sendJson('POST', '/api/customer/logout');
  window.location.href = 'index.html';
}

// Shared checks for the sign-in and sign-up forms (the same rules the server uses)
function checkEmail(email) {
  if (email === '') {
    return 'Please enter your email address.';
  }
  if (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email) === false) {
    return 'Please enter a valid email address.';
  }
  return '';
}
function checkPhone(phone) {
  var digits = phone.replace(/\D/g, '');
  if (phone === '') {
    return 'Please enter a contact number.';
  }
  if (/^\+?[0-9\s\-()]+$/.test(phone) === false) {
    return 'Contact number can only contain digits, spaces, + - ( ).';
  }
  if (digits.length < 7 || digits.length > 15) {
    return 'Contact number must have between 7 and 15 digits.';
  }
  return '';
}
function checkName(name) {
  if (name === '') {
    return 'Please enter your name.';
  }
  if (name.length < 2) {
    return 'Name must be at least 2 characters.';
  }
  if (/^[\p{L}\p{M}][\p{L}\p{M}\s.'’-]*$/u.test(name) === false) {
    return 'Name can only contain letters, spaces, apostrophes, periods and hyphens.';
  }
  return '';
}

// 1200 becomes '₱1,200.00'
function formatMoney(amount) {
  return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Payment status is SEPARATE from the reservation status. Only staff can mark a payment 'verified'.
var PAYMENT_LABELS = { unpaid: 'Unpaid', proof_submitted: 'Proof received, awaiting verification', verified: 'Payment verified' };
var PAYMENT_OPTION_LABELS = { downpayment: 'Downpayment', full: 'Full payment' };
var PAYMENT_METHOD_LABELS = { gcash: 'GCash' };   // GCash is the only payment method
