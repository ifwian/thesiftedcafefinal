// app.js
// The JavaScript of the public website (index.html):
//   (used on every public page: Home, Menu, About, Visit)
//   1. The mobile menu
//   2. The year in the footer
//   3. The photo viewer (lightbox) in the gallery
//   4. The "Reserve a table" buttons and the Log in / Sign up / My account links (the forms are on login.html and signup.html)


// ---------------------------------------------------------------
// 1. Mobile menu
// ---------------------------------------------------------------
var menuButton = document.querySelector('.nav-toggle');
var menu = document.getElementById('site-nav');

// Opens or closes the menu. aria-expanded tells screen readers what state it is in.
function setMenuOpen(isOpen) {
  menuButton.setAttribute('aria-expanded', String(isOpen));
  if (isOpen) {
    menu.classList.add('is-open');
  } else {
    menu.classList.remove('is-open');
  }
}

menuButton.addEventListener('click', function () {
  var isOpenNow = menuButton.getAttribute('aria-expanded') === 'true';
  setMenuOpen(!isOpenNow);
});

// Clicking a link in the menu closes it.
menu.addEventListener('click', function (event) {
  if (event.target.closest('a')) {
    setMenuOpen(false);
  }
});

// A tap outside the open menu closes it.
document.addEventListener('click', function (event) {
  if (menu.classList.contains('is-open') && !event.target.closest('.site-nav') && !event.target.closest('.nav-toggle')) {
    setMenuOpen(false);
  }
});

// The Escape key closes it.
document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') {
    setMenuOpen(false);
  }
});


// ---------------------------------------------------------------
// 2. Year in the footer
// ---------------------------------------------------------------
var yearEl = document.getElementById('year');
if (yearEl) { yearEl.textContent = String(new Date().getFullYear()); }


// ---------------------------------------------------------------
// 3. Gallery photo viewer (lightbox)
// ---------------------------------------------------------------
var galleryItems = document.querySelectorAll('.gallery-item');
var lightbox = document.getElementById('lightbox');
var lightboxImage = document.getElementById('lightbox-img');
var lightboxCaption = document.getElementById('lightbox-caption');
var currentPhoto = 0;   // which photo is showing

// Shows the photo at a position. After the last photo it goes back to the first.
function showPhoto(index) {
  if (index < 0) {
    index = galleryItems.length - 1;
  }
  if (index >= galleryItems.length) {
    index = 0;
  }
  currentPhoto = index;

  var thumbnail = galleryItems[index].querySelector('img');
  lightboxImage.src = thumbnail.currentSrc || galleryItems[index].dataset.full;   // currentSrc: shows the placeholder until the real photo exists
  lightboxImage.alt = thumbnail.alt;
  lightboxCaption.textContent = thumbnail.alt;
}

// Clicking a small photo opens the viewer on that photo.
// (A separate function is used so each button remembers its own number.)
function connectThumbnail(button, index) {
  button.addEventListener('click', function () {
    showPhoto(index);
    lightbox.showModal();
  });
}
if (lightbox) {
for (var i = 0; i < galleryItems.length; i++) {
  connectThumbnail(galleryItems[i], i);
}

document.getElementById('lightbox-prev').addEventListener('click', function () {
  showPhoto(currentPhoto - 1);
});
document.getElementById('lightbox-next').addEventListener('click', function () {
  showPhoto(currentPhoto + 1);
});
document.getElementById('lightbox-close').addEventListener('click', function () {
  lightbox.close();
});

// Clicking the dark area around the photo closes the viewer.
lightbox.addEventListener('click', function (event) {
  if (event.target === lightbox) {
    lightbox.close();
  }
});
}   // end: only runs on pages that have the gallery


// ---------------------------------------------------------------
// 4. "Reserve a table" buttons
// ---------------------------------------------------------------
// Every button with the attribute data-reserve does the same thing:
//   - signed in  -> go to the reservation page
//   - signed out -> go to the Log in page (then back to the form)
// We ask the server who is signed in with GET /api/customer/me.

var signedIn = false;

async function checkSignedIn() {
  var result = await sendJson('GET', '/api/customer/me');
  signedIn = (result.status === 200);
  // Signed in: show "My account"; signed out: show "Log in" and "Sign up".
  document.querySelectorAll('.nav-member').forEach(function (el) { el.hidden = !signedIn; });
  document.querySelectorAll('.nav-guest').forEach(function (el) { el.hidden = signedIn; });
}

function connectReserveButton(button) {
  button.addEventListener('click', function () {
    setMenuOpen(false);
    if (signedIn) {
      window.location.href = 'account.php#reserve';   // the inline form in the dashboard
    } else {
      window.location.href = 'login.html?next=' + encodeURIComponent('account.php#reserve');   // after logging in, the reservation form opens
    }
  });
}
var reserveButtons = document.querySelectorAll('[data-reserve]');
for (var r = 0; r < reserveButtons.length; r++) {
  connectReserveButton(reserveButtons[r]);
}


// ---------------------------------------------------------------
// 6. Menu hover preview
// ---------------------------------------------------------------
// Each menu card names its local photo in data-photo (for example assets/images/menu/spanish-latte.jpg).
// Hovering (or keyboard focus) shows that photo. If the file is missing, nothing is shown and nothing breaks.
var menuPreview = document.createElement('figure');
menuPreview.className = 'menu-preview';
menuPreview.setAttribute('aria-hidden', 'true');
var menuPreviewImg = new Image();
menuPreviewImg.width = 360; menuPreviewImg.height = 270;
menuPreview.append(menuPreviewImg);
document.body.append(menuPreview);

function placePreview(x, y) {
  var w = 280, h = 210;
  var left = Math.min(x + 24, window.innerWidth - w - 12);
  var top = Math.min(Math.max(y - h / 2, 80), window.innerHeight - h - 12);
  menuPreview.style.transform = 'translate(' + Math.round(left) + 'px,' + Math.round(top) + 'px)';
}
function showPreview(card, x, y) {
  // Only photos listed in SIFTED_CONFIG.menuPhotos (js/config.js) are used, so a missing file is never requested.
  var name = (card.dataset.photo || '').split('/').pop().replace(/\.(jpg|jpeg|png|svg)$/, '');
  if ((window.SIFTED_CONFIG.menuPhotos || []).indexOf(name) === -1) { return; }
  menuPreviewImg.alt = '';
  menuPreviewImg.src = card.dataset.photo;
  placePreview(x, y);
  menuPreview.classList.add('is-on');
}
document.querySelectorAll('.menu-card[data-photo]').forEach(function (card) {
  card.tabIndex = 0;
  if (window.matchMedia('(hover: hover)').matches) {
    card.addEventListener('mouseenter', function (e) { showPreview(card, e.clientX, e.clientY); });
    card.addEventListener('mousemove', function (e) { placePreview(e.clientX, e.clientY); });
    card.addEventListener('mouseleave', function () { menuPreview.classList.remove('is-on'); });
  }
  card.addEventListener('focus', function () { var r = card.getBoundingClientRect(); showPreview(card, r.right - 120, r.top + r.height / 2); });
  card.addEventListener('blur', function () { menuPreview.classList.remove('is-on'); });
});

checkSignedIn();
