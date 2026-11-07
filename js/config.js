// config.js
// The cafe's reservation, payment and policy settings, in ONE place.
//
// Values marked PLACEHOLDER are NOT confirmed by the cafe. They only exist so the form can
// calculate and show amounts. Replace them with the cafe's real rules before real use.
// (Later, the real server will own these numbers. The browser never decides the amounts.)

window.SIFTED_CONFIG = {
  reservationFee: 0,            // PLACEHOLDER: fixed fee per reservation, in pesos (0 = no fee)
  minimumSpendPerGuest: 200,    // PLACEHOLDER: minimum spend per guest, in pesos
  downpaymentPercent: 30,       // PLACEHOLDER: required downpayment, % of the total
  durationHours: 2,             // PLACEHOLDER: how long a table is held for one reservation
  requireProof: true,           // customers must attach a proof of payment to send the request
  maxProofMB: 3,                // biggest proof file allowed

  // GCash is the ONLY payment method.
  // The QR image is assets/gcash-qr.png (cropped from the cafe's own GCash screen, nothing invented).
  // To change it: replace that file (square PNG, at least 600 x 600 px, keep a white border around the code).
  gcash: {
    label: 'GCash',
    qrImage: 'assets/gcash-qr.png',
    qrAlt: 'GCash QR code of The Sifted Cafe. Scan it with the GCash app to pay.',
    // Only what GCash itself shows on the cafe's QR screen (partly hidden by GCash for privacy).
    recipientName: 'RO****O N.',
    // GCash adds this note on the cafe's QR screen.
    note: 'GCash shows that transfer fees may apply when you pay with this QR.'
  },

  // Menu photos you have added to assets/images/menu/ (file names without .jpg). Empty = no photos.
  // Example: menuPhotos: ['spanish-latte', 'classic-croissant']
  menuPhotos: ['spanish-latte', 'iced-caramel-macchiato', 'matcha-latte', 'truffle-mushroom-pasta', 'classic-croissant', 'basque-burnt-cheesecake'],

  // Cancellation rules. KEEP IN SYNC with 'cancel_hours_before' and 'grace_minutes' in config/app.php
  // (the server enforces them; these numbers are only for display).
  cancelHoursBefore: 24,
  graceMinutes: 15,

  // Reservation terms shown before the customer sends the request: a few short groups, each a list of one-line rules.
  policies: [
    { title: 'Confirmation and availability', items: [
      'Sending the form is a request, not a booking. Your table is reserved only when the status says Confirmed.',
      'Online bookings are for 2 to 10 guests, 1 to 60 days ahead, on the hour. Calamba is open 10 AM to 10 PM and Cabuyao 9 AM to 9 PM. For larger groups, please call or message us.',
      'A full time slot cannot be booked. If a request cannot be accommodated, staff may suggest another time or cancel it.',
      'Reservations cannot be edited. To change anything, cancel it in My account and send a new request.'
    ] },
    { title: 'Downpayment and payment', items: [
      'Pay by GCash only, using the QR code and the exact amount shown: a 30% downpayment or the full amount.',
      'Pay before you send the request. It cannot be sent without your proof of payment (PNG, JPG or PDF, up to 3 MB).',
      'Write your full name and reservation date in the GCash note, and add the reference number if you have it.',
      'A proof is not yet a verified payment. Staff check it against the cafe\'s GCash records.'
    ] },
    { title: 'Cancellation and refunds', items: [
      'You can cancel for free in My account up to 24 hours before your reservation time. After that, please contact the cafe.',
      'Refunds are not automatic. Staff will contact you about your payment. Late cancellations and no-shows may forfeit the downpayment.'
    ] },
    { title: 'Arrival', items: [
      'Please arrive on time. We hold your table for 15 minutes, then it may be given to other guests and the reservation cancelled.',
      'Seating and celebration requests are best effort and not guaranteed.'
    ] }
  ],
  privacyNote: 'We use your details only to handle your reservation and keep records for 6 months.'
};
