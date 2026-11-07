// motion.js - the animations of the public website (index.html).
// Everything here is decoration: if this file fails, the page still works.
// People who ask their system for less motion get no animation at all.
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // 1. Reveal sections as they scroll into view (IntersectionObserver).
  var targets = document.querySelectorAll(
    '[data-reveal], .section-head, .menu-filters, .menu-card, .values li, .steps-grid li, .gallery-grid li, .branch-card, .visit-photo');
  targets.forEach(function (el, i) { el.classList.add('reveal'); el.style.setProperty('--r', String(i % 4)); });
  if (reduce || !('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    targets.forEach(function (el) { io.observe(el); });
  }

  // 2. Images that are still loading fade in when they arrive. Images already
  //    ready (or cached) are left alone, so nothing flashes on a normal reload.
  document.querySelectorAll('img').forEach(function (img) {
    if (img.complete) { return; }
    img.classList.add('is-loading');
    function done() { img.classList.add('is-loaded'); }
    img.addEventListener('load', done, { once: true });
    img.addEventListener('error', done, { once: true });
  });

  // 3. Header gets a soft shadow after scrolling; gentle parallax on the hero photo.
  var header = document.querySelector('.site-header');
  var heroMedia = document.querySelector('.hero-media');
  var slow = document.querySelectorAll('[data-parallax-slow]');
  var ticking = false;
  function onScroll() {
    var y = window.scrollY || 0;
    var heroEl = document.getElementById('top');
    var limit = heroEl ? Math.max(40, heroEl.offsetHeight - header.offsetHeight - 24) : 8;
    header.classList.toggle('is-scrolled', y > limit);
    if (!reduce) {
      if (heroMedia && y < window.innerHeight * 1.2) { heroMedia.style.transform = 'translate3d(0,' + Math.round(y * 0.18) + 'px,0)'; }
      slow.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.bottom > 0 && r.top < window.innerHeight) {
          el.style.transform = 'translate3d(0,' + Math.round((r.top - window.innerHeight / 2) * -0.06) + 'px,0)';
        }
      });
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  // 4. The menu link that matches the section on screen is underlined.
  var links = {};
  document.querySelectorAll('.site-nav a[href^="#"]').forEach(function (a) { links[a.getAttribute('href').slice(1)] = a; });
  if ('IntersectionObserver' in window) {
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting || !links[e.target.id]) { return; }
        Object.keys(links).forEach(function (k) { links[k].classList.remove('is-current'); links[k].removeAttribute('aria-current'); });
        links[e.target.id].classList.add('is-current');
        links[e.target.id].setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(links).forEach(function (id) { var s = document.getElementById(id); if (s) { spy.observe(s); } });
  }

  // 5. Menu filter chips. Only shows or hides the cards that are already on the page.
  var chips = document.querySelectorAll('.menu-filters .chip');
  var cards = document.querySelectorAll('.menu-grid .menu-card');
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      var want = chip.dataset.filter;
      chips.forEach(function (c) { var on = c === chip; c.classList.toggle('is-active', on); c.setAttribute('aria-pressed', String(on)); });
      cards.forEach(function (card) {
        var cat = card.querySelector('.menu-category').textContent.trim();
        card.hidden = !(want === 'all' || cat === want);
        if (!card.hidden) { card.classList.add('is-in'); }
      });
    });
  });
})();
