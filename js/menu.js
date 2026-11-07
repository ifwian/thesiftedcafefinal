// menu.js - builds the tabbed "full menu" on menu.html from window.SIFTED_MENU (js/menu-data.js).
(function () {
  'use strict';
  var data = window.SIFTED_MENU || [];
  var tabs = document.getElementById('menu-tabs');
  var panel = document.getElementById('menu-panel');
  if (!tabs || !panel || !data.length) { return; }

  function peso(n) { return '\u20B1' + n; }

  // Draws one category: a heading, a note and one row per item (name, description, prices by size).
  function showCategory(cat) {
    panel.innerHTML = '';
    var head = document.createElement('div');
    head.className = 'menu-panel-head';
    var h3 = document.createElement('h3'); h3.textContent = cat.name;
    var note = document.createElement('p'); note.textContent = cat.note;
    head.append(h3, note);
    panel.append(head);

    var list = document.createElement('ul');
    list.className = 'price-list';
    cat.items.forEach(function (item) {
      var li = document.createElement('li');
      var text = document.createElement('div');
      var name = document.createElement('strong'); name.textContent = item.name;
      var desc = document.createElement('span'); desc.textContent = item.desc;
      text.append(name, desc);
      var prices = document.createElement('div'); prices.className = 'price-cols';
      item.prices.forEach(function (p, i) {
        var cell = document.createElement('span');
        if (p === null) { cell.textContent = '\u2014'; cell.className = 'price-none'; cell.setAttribute('aria-label', 'not offered'); }
        else {
          cell.textContent = peso(p);
          if (cat.sizes.length > 1 || cat.sizes[0] !== 'Each') { var sz = document.createElement('small'); sz.textContent = cat.sizes[i]; cell.append(sz); }
        }
        prices.append(cell);
      });
      li.append(text, prices);
      list.append(li);
    });
    panel.append(list);
  }

  function select(index) {
    var buttons = tabs.querySelectorAll('button');
    buttons.forEach(function (b, i) {
      var on = i === index;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', String(on));
    });
    showCategory(data[index]);
  }

  data.forEach(function (cat, i) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'chip'; b.setAttribute('role', 'tab');
    b.textContent = cat.name;
    b.addEventListener('click', function () { select(i); });
    tabs.append(b);
  });
  select(0);
})();
