// owner.js - opens the read-only reservation details window (no page reload). It has no way to send changes.
(function () {
  'use strict';
  var dlg = document.getElementById('detail-modal');
  var body = document.getElementById('detail-modal-body');
  if (!dlg) { return; }
  document.addEventListener('click', function (e) {
    var close = e.target.closest('[data-close]');
    if (close) { dlg.close(); return; }
    if (e.target === dlg) { dlg.close(); return; }
    var view = e.target.closest('[data-view]');
    if (!view) { return; }
    e.preventDefault();
    body.textContent = 'Loading...';
    dlg.showModal();
    var url = new URL(view.href, location.href);
    url.searchParams.set('modal', '1');
    fetch(url.toString(), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error('x'); } return r.text(); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () { body.textContent = 'Could not load the details. Please close this window and try again.'; });
  });
})();
