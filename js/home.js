// home.js - fills the home page "menu" and "branches" sections from the same data the
// Menu and Visit pages use (menu-data.js and branches.js), so nothing is typed twice.
// Colours come from CSS classes (f-* = fill, s-* = stroke) that read the CSS variables.
(function () {
  'use strict';

  var SPARK = 'M12 .8C13 8 16 11 23.2 12 16 13 13 16 12 23.2 11 16 8 13 .8 12 8 11 11 8 12 .8Z';

  // One small hand-drawn icon per menu category id. Unknown ids fall back to "coffee".
  var ICONS = {
    'coffee': '<ellipse class="f-yellow" cx="46" cy="85" rx="32" ry="6"/><path class="f-cream" d="M20 42H70V62Q70 82 45 82Q20 82 20 62Z"/><path d="M70 48Q90 46 87 62Q84 74 67 70"/><path class="f-latte" d="M20 52H70V62Q70 82 45 82Q20 82 20 62Z"/><path class="s-red" d="M34 34q-7-8 0-15M48 34q-7-8 0-15M62 34q-7-8 0-15"/>',
    'non-coffee': '<path class="f-cream" d="M28 34H68L63 88H33Z"/><path class="f-green" d="M31 56H65L63 88H33Z"/><path class="s-red" stroke-width="6" d="M52 34L64 8"/><rect class="f-white" fill-opacity=".8" x="40" y="42" width="11" height="11" rx="3" transform="rotate(-12 45 47)"/><rect class="f-white" fill-opacity=".6" x="54" y="62" width="10" height="10" rx="3" transform="rotate(10 59 67)"/>',
    'specialty': '<path class="f-cream" d="M28 40H68L63 88H33Z"/><path class="f-latte" d="M30 58H66L63 88H33Z"/><path class="f-cream" d="M30 58H66L65 66H31Z"/><path class="f-white" fill-opacity=".85" d="M26 40Q48 18 70 40Z"/><path class="s-red" stroke-width="5" d="M50 26L56 6"/><path class="f-yellow" stroke-width="2" transform="translate(60 4) scale(1.6)" d="' + SPARK + '"/>',
    'pastries': '<ellipse class="f-cream" cx="50" cy="84" rx="38" ry="6"/><path class="f-gold" d="M12 72Q16 34 50 30Q84 34 88 72Q68 58 50 60Q32 58 12 72Z"/><path stroke-width="3" d="M34 44Q40 58 36 64M50 36V58M66 44Q60 58 64 64"/>',
    'desserts': '<path class="f-cream" d="M14 62L84 48V84H14Z"/><path class="f-pink" d="M14 62L84 48L70 30L26 40Z"/><path stroke-width="3" d="M14 72L84 60"/><circle class="f-red" cx="52" cy="30" r="8"/><path d="M52 22q4-8 10-10"/>',
    'savory': '<path class="f-gold" d="M18 48Q50 8 82 48Z"/><path class="f-green" d="M16 54q8 9 17 0t17 0t17 0t17 0V60H16Z"/><rect class="f-coffee" x="18" y="60" width="64" height="12" rx="6"/><rect class="f-gold" x="20" y="74" width="60" height="12" rx="6"/>'
  };
  var TONES = ['red', 'green', 'blue', 'yellow', 'blue', 'red'];

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text) n.textContent = text;
    return n;
  }

  function icon(id) {
    var wrap = el('span', 'dish-disc');
    wrap.innerHTML = '<svg class="dish-ic" viewBox="0 0 100 100" aria-hidden="true"><g filter="url(#wob)" class="s-ink" stroke-width="3.6" stroke-linejoin="round" stroke-linecap="round" fill="none">' + (ICONS[id] || ICONS.coffee) + '</g></svg>';
    return wrap;
  }

  function renderMenu() {
    var grid = document.getElementById('dish-grid');
    var menu = window.SIFTED_MENU;
    if (!grid || !menu || !menu.length) { if (grid) grid.closest('section').hidden = true; return; }
    menu.forEach(function (cat, i) {
      var li = el('li'); li.setAttribute('data-reveal', '');
      var a = el('a', 'dish dish-' + TONES[i % TONES.length]);
      a.href = 'menu.html';
      a.appendChild(icon(cat.id));
      a.appendChild(el('strong', '', cat.name));
      if (cat.note) a.appendChild(el('span', 'dish-sub', cat.note));
      li.appendChild(a); grid.appendChild(li);
    });
  }

  function renderBranches() {
    var grid = document.getElementById('door-grid');
    var list = window.SIFTED_BRANCHES;
    if (!grid || !list || !list.length) { if (grid) grid.closest('section').hidden = true; return; }
    list.forEach(function (b, i) {
      var art = el('article', 'door ' + (i % 2 ? 'door-yellow' : 'door-blue')); art.setAttribute('data-reveal', '');
      art.appendChild(el('p', 'door-no', ('0' + (i + 1)).slice(-2))).setAttribute('aria-hidden', 'true');
      art.appendChild(el('h3', '', b.name));
      art.appendChild(el('address', '', String(b.address || '').replace(/,\s*Philippines$/, '')));
      var hours = el('p', 'door-hours'), parts = String(b.hours || '').split(/,\s*/);
      if (parts.length > 1) { hours.appendChild(el('strong', '', parts.shift())); hours.appendChild(el('span', '', parts.join(', '))); }
      else hours.appendChild(el('span', '', parts[0]));
      art.appendChild(hours);
      var link = el('a', 'link-arrow', 'Directions & map '); link.href = 'visit.html#' + b.id + '-info';
      var arrow = el('span', '', '→'); arrow.setAttribute('aria-hidden', 'true'); link.appendChild(arrow);
      art.appendChild(link); grid.appendChild(art);
    });
  }

  renderMenu();
  renderBranches();
})();
