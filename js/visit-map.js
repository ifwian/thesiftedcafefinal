// visit-map.js - the Visit us map. Data comes from js/branches.js.
//  * Both branches have lat/lng -> one Leaflet map (OpenStreetMap) with a labelled pin per branch;
//    clicking a pin opens a pop-up with the branch details and a Google Maps directions link.
//  * Otherwise (default) -> a Google Maps view of the branch's street address, with one pin button per branch.
(function () {
  'use strict';
  var branches = window.SIFTED_BRANCHES || [];
  var mapEl = document.getElementById('map');
  var tabs = document.querySelector('.map-tabs');
  var caption = document.getElementById('map-caption');
  if (!mapEl || !branches.length) { return; }

  function enc(s) { return encodeURIComponent(s); }
  function directions(b) {
    var dest = (typeof b.lat === 'number' && typeof b.lng === 'number') ? b.lat + ',' + b.lng : b.address;
    return 'https://www.google.com/maps/dir/?api=1&destination=' + enc(dest);
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function byId(id) { return branches.filter(function (b) { return b.id === id; })[0]; }

  // "Get directions" links on the branch cards.
  document.querySelectorAll('[data-directions]').forEach(function (a) {
    var b = byId(a.dataset.directions); if (b) { a.href = directions(b); }
  });

  var haveCoords = branches.every(function (b) { return typeof b.lat === 'number' && typeof b.lng === 'number'; });
  var current = branches[0].id;
  var select;   // set below: select(id) shows that branch on the map

  function setActive(id) {
    current = id;
    document.querySelectorAll('.loc-card').forEach(function (c) { c.classList.toggle('is-active', c.dataset.branch === id); });
    if (tabs) { tabs.querySelectorAll('button').forEach(function (t) { var on = t.dataset.id === id; t.classList.toggle('is-active', on); t.setAttribute('aria-pressed', String(on)); }); }
  }

  // Pin buttons above the map (one per branch)
  if (tabs) {
    branches.forEach(function (b) {
      var t = document.createElement('button');
      t.type = 'button'; t.dataset.id = b.id; t.setAttribute('aria-pressed', 'false');
      t.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 22s7-6.3 7-12a7 7 0 1 0-14 0c0 5.7 7 12 7 12zm0-9.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"/></svg>' + esc(b.name);
      t.addEventListener('click', function () { select(b.id); });
      tabs.append(t);
    });
  }
  document.querySelectorAll('[data-show]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      select(btn.dataset.show);
      if (window.matchMedia('(max-width: 900px)').matches) { mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
  });

  function words(b) { return b.name + ' branch · ' + b.address; }

  if (haveCoords) {
    // ---------- Interactive map with two pins (needs internet for the Leaflet files and map tiles) ----------
    var css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.append(css);
    var js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = function () {
      var L = window.L, markers = {};
      var map = L.map(mapEl, { scrollWheelZoom: false, tap: true });
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
      var bounds = [];
      branches.forEach(function (b) {
        var icon = L.divIcon({ className: 'sifted-pin', html: '<span>' + esc(b.name) + '</span>', iconSize: [0, 0], iconAnchor: [0, 0] });
        var m = L.marker([b.lat, b.lng], { icon: icon, title: b.name + ' branch', keyboard: true }).addTo(map);
        m.bindPopup('<strong>' + esc(b.name) + ' branch</strong><br>' + esc(b.address) + '<br>' + esc(b.hours) +
          (b.phone ? '<br>' + esc(b.phone) : '') + '<br><a href="' + directions(b) + '" target="_blank" rel="noopener">Get directions</a>');
        m.on('click', function () { setActive(b.id); if (caption) { caption.textContent = words(b); } });
        markers[b.id] = m; bounds.push([b.lat, b.lng]);
      });
      map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
      map.on('focus', function () { map.scrollWheelZoom.enable(); });
      map.on('blur', function () { map.scrollWheelZoom.disable(); });
      select = function (id) {
        var b = byId(id); setActive(id);
        map.flyTo([b.lat, b.lng], 16, { duration: 0.8 });
        markers[id].openPopup();
        if (caption) { caption.textContent = words(b); }
      };
      setActive(current);
      if (caption) { caption.textContent = 'Click a pin for details.'; }
    };
    js.onerror = function () { useAddressMap(); };
    document.body.append(js);
    select = function (id) { setActive(id); };   // until Leaflet has loaded
  } else {
    useAddressMap();
  }

  // ---------- Default: Google Maps by street address (no guessed coordinates) ----------
  function useAddressMap() {
    var frame = document.createElement('iframe');
    frame.width = 600; frame.height = 450; frame.loading = 'lazy';
    frame.referrerPolicy = 'no-referrer-when-downgrade';
    frame.setAttribute('allowfullscreen', '');
    mapEl.textContent = ''; mapEl.className += ' is-embed'; mapEl.append(frame);
    select = function (id) {
      var b = byId(id); setActive(id);
      frame.title = 'Map: ' + words(b);
      frame.src = 'https://www.google.com/maps?q=' + enc(b.address) + '&z=17&output=embed';
      if (caption) { caption.textContent = words(b); }
    };
    select(current);
  }
})();
