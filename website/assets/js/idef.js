/* منتدى الاقتصاد الرقمي العراقي — تفاعلات الواجهة */
(function () {
  'use strict';
  var all = function (q, s) { return Array.prototype.slice.call((s || document).querySelectorAll(q)); };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };

  /* ---------- العد التنازلي (يدعم أكثر من عدّاد في الصفحة) ---------- */
  var counters = all('[data-countdown]').map(function (el) {
    var t = new Date((el.getAttribute('data-countdown') || '').replace(' ', 'T') + '+03:00').getTime();
    return { el: el, t: t, cells: { d: el.querySelector('[data-cd="d"]'), h: el.querySelector('[data-cd="h"]'), m: el.querySelector('[data-cd="m"]'), s: el.querySelector('[data-cd="s"]') } };
  }).filter(function (c) { return !isNaN(c.t); });
  function tick() {
    var now = Date.now();
    counters.forEach(function (c) {
      var diff = Math.max(0, c.t - now);
      var v = { d: Math.floor(diff / 86400000), h: Math.floor(diff / 3600000) % 24, m: Math.floor(diff / 60000) % 60, s: Math.floor(diff / 1000) % 60 };
      Object.keys(v).forEach(function (k) {
        var cell = c.cells[k]; if (!cell) return;
        var txt = k === 'd' ? String(v.d) : pad(v[k]);
        if (cell.textContent !== txt) {
          cell.textContent = txt;
          cell.classList.remove('tick'); void cell.offsetWidth; cell.classList.add('tick');
        }
      });
    });
  }
  if (counters.length) { tick(); setInterval(tick, 1000); }

  /* ---------- التبويبات ---------- */
  all('[data-tabs]').forEach(function (tabs) {
    var scope = tabs.parentElement;
    var btns = all('[data-tab]', tabs);
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        btns.forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        all('[data-panel]', scope).forEach(function (p) {
          if (p.closest('[data-tabs]')) return;
          var on = p.getAttribute('data-panel') === b.getAttribute('data-tab');
          p.hidden = !on;
          if (on) all('.reveal', p).forEach(function (r) { r.classList.add('in'); });
        });
      });
    });
  });

  /* ---------- البحث في التوصيات ---------- */
  var search = document.getElementById('recSearch');
  if (search) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      all('.ix-tabpanel').forEach(function (panel) {
        var hits = 0;
        all('.ix-rec', panel).forEach(function (r) {
          var match = !q || r.textContent.toLowerCase().indexOf(q) >= 0;
          r.hidden = !match;
          r.classList.toggle('is-hit', !!q && match);
          if (q && match) { r.open = true; hits++; }
          if (!q) r.open = false;
        });
        if (q && hits) { panel.hidden = false; }
      });
      if (!q) {
        var sel = document.querySelector('[data-tabs] [aria-selected="true"]');
        all('.ix-tabpanel').forEach(function (p) { p.hidden = sel && p.getAttribute('data-panel') !== sel.getAttribute('data-tab'); });
      }
    });
  }

  /* ---------- قسم الرؤية: رسم الخط عند الظهور ---------- */
  var vision = document.querySelector('.ix-vision');
  if (vision && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en, obs) { if (en[0].isIntersecting) { vision.classList.add('in'); obs.disconnect(); } }, { threshold: .3 }).observe(vision);
  } else if (vision) vision.classList.add('in');
})();
