// staff.js - the pop-up windows of the staff page (View, and the Cancel / Archive / Restore confirmations).
(function () {
  'use strict';
  var detail = document.getElementById('detail-modal');
  var body = document.getElementById('detail-modal-body');
  var confirmBox = document.getElementById('confirm-modal');

  var CONFIRM_TEXT = {
    cancelled: { title: 'Cancel {ref}?', yes: 'Yes, cancel it', cls: 'btn-danger',
      body: 'The reservation will be marked as cancelled and its table will be released. The record is kept in the list.' },
    archive: { title: 'Archive {ref}?', yes: 'Yes, archive it', cls: 'btn-primary',
      body: 'The reservation moves out of the active list into the Archive. Nothing is deleted: you can view it and restore it at any time.' },
    delete: { title: 'Permanently delete {ref}?', yes: 'Yes, delete permanently', cls: 'btn-danger',
      body: 'This removes the archived reservation, its payment record and its uploaded proof from the database. It cannot be undone. Use Restore instead if you may need it again.' },
    restore: { title: 'Restore {ref}?', yes: 'Yes, restore it', cls: 'btn-primary',
      body: 'The reservation returns to the active reservation list with its status and payment unchanged.' }
  };

  var reopenDetail = false;   // was the View window open when a confirmation was opened?
  function open(d) { if (!d.open) { d.showModal(); } }

  // Close buttons, and a click on the dimmed background.
  document.addEventListener('click', function (e) {
    var closeBtn = e.target.closest('[data-close]');
    if (closeBtn) { closeBtn.closest('dialog').close(); return; }
    if (e.target.tagName === 'DIALOG') { e.target.close(); return; }

    // View: load the details of that reservation into the window (no page reload).
    var view = e.target.closest('[data-view]');
    if (view) {
      e.preventDefault();
      body.textContent = 'Loading...';
      open(detail);
      var url = new URL(view.href, location.href);
      url.searchParams.set('modal', '1');
      fetch(url.toString(), { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) { throw new Error('x'); } return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function () { body.textContent = 'Could not load the details. Please close this window and try again.'; });
      return;
    }

    // Cancel / Archive / Restore / Delete: always ask first.
    var act = e.target.closest('[data-act]');
    if (act) {
      var kind = act.dataset.act;
      var ref = act.dataset.ref;
      var text = CONFIRM_TEXT[kind];
      document.getElementById('confirm-id').value = act.dataset.id;
      document.getElementById('confirm-action').value = kind === 'cancelled' ? 'cancelled' : kind;
      document.getElementById('confirm-return').value = act.dataset.return || '';
      document.getElementById('confirm-title').textContent = text.title.replace('{ref}', ref);
      document.getElementById('confirm-text').textContent = text.body;
      var yes = document.getElementById('confirm-submit');
      yes.textContent = text.yes;
      yes.className = 'btn btn-small ' + text.cls;
      reopenDetail = detail.open;
      detail.close();
      open(confirmBox);
    }
  });

  // "Go back" / close goes back to the details window instead of leaving the screen empty.
  confirmBox.addEventListener('close', function () { if (reopenDetail) { reopenDetail = false; open(detail); } });

  // After an action the page reloads with ?id=...: show that reservation's window again.
  if (detail && body.querySelector('.st-detail-body')) { open(detail); }
})();
