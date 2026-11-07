// qr-viewer.js
// The GCash QR viewer (account.php): enlarged window with view larger and close.
//   Click “View Larger QR” to see the QR code at a larger fixed size.
//   Click “Close” to close the window.

(function () {
  var dialog = document.getElementById('qr-dialog');
  var stage = document.getElementById('qr-stage');
  var image = document.getElementById('qr-big');
  if (dialog === null) { return; }

  function open() {
    image.style.width = '';  // reset to natural size
    dialog.showModal();
    stage.focus();
  }
  function close() {
    dialog.close();
  }
  dialog.addEventListener('close', function () {
    if (document.activeElement === dialog) { document.activeElement.focus(); }
  });

  document.getElementById('qr-open').addEventListener('click', open);
  document.getElementById('qr-open-2').addEventListener('click', open);
  document.getElementById('qr-close').addEventListener('click', close);
  document.getElementById('qr-close-2').addEventListener('click', close);

  // Click on the dark area around the window closes it.
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) { close(); }
  });
})();