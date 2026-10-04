/* منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي — طبقة الحركة
 * Vanilla JS (CSP: same-origin only). Respects prefers-reduced-motion and
 * the live-edit mode (body.scf-editing), where motion is switched off.
 */
(function () {
  'use strict';
  var d = document, w = window, root = d.documentElement, body = d.body;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var clamp = function (v, a, b) { return Math.max(a, Math.min(b, v)); };
  var RTL = root.dir === 'rtl';
  var AR = root.lang === 'ar';
  var editing = body.classList.contains('scf-editing');
  var reduced = w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = w.matchMedia('(pointer: fine)').matches;
  var motion = !reduced && !editing;
  var vh = w.innerHeight, vw = w.innerWidth;

  var digits = function (s) {
    s = String(s);
    return AR ? s.replace(/[0-9]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'[c]; }) : s;
  };

  /* ---------------------------------------------------------- header -- */
  var head = $('#cHead');
  var hero = $('.c-hero');
  var lastY = w.scrollY;
  function headerTick(y) {
    if (!head) return;
    var solidAt = hero ? hero.offsetHeight - 90 : 10;
    head.classList.toggle('is-solid', !body.classList.contains('is-inner') && y > solidAt);
    var down = y > lastY + 2, up = y < lastY - 2;
    if (down && y > 420 && !body.classList.contains('menu-open')) head.classList.add('is-hidden');
    else if (up || y < 120) head.classList.remove('is-hidden');
    lastY = y;
  }

  /* active nav link */
  var navLinks = $$('.c-nav a[href^="#"]');
  if (navLinks.length && 'IntersectionObserver' in w) {
    var map = {};
    navLinks.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var nio = new IntersectionObserver(function (es) {
      es.forEach(function (en) {
        var a = map[en.target.id];
        if (a && en.isIntersecting) { navLinks.forEach(function (x) { x.classList.remove('is-active'); }); a.classList.add('is-active'); }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(map).forEach(function (id) { var s = d.getElementById(id); if (s) nio.observe(s); });
  }

  /* ------------------------------------------------------------ menu -- */
  var burger = $('#cBurger'), menu = $('#cMenu');
  function setMenu(open) {
    body.classList.toggle('menu-open', open);
    if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (menu) menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    body.style.overflow = open ? 'hidden' : '';
  }
  if (burger) burger.addEventListener('click', function () { setMenu(!body.classList.contains('menu-open')); });
  $$('.c-menu a').forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

  /* ------------------------------------------------------- countdown -- */
  $$('[data-countdown]').forEach(function (el) {
    var t = new Date((el.getAttribute('data-countdown') || '').replace(' ', 'T')).getTime();
    if (!t) return;
    var cells = {};
    $$('[data-cd]', el).forEach(function (c) { cells[c.getAttribute('data-cd')] = c; });
    function tick() {
      var s = Math.max(0, Math.floor((t - Date.now()) / 1000));
      var v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
      Object.keys(v).forEach(function (k) { if (cells[k]) cells[k].textContent = digits(String(v[k]).padStart(2, '0')); });
    }
    tick(); setInterval(tick, 1000);
  });

  /* stats counters (also correct without motion) */
  function countUp(el) {
    var n = parseInt(el.getAttribute('data-count'), 10);
    if (!n) return;
    if (!motion) { el.textContent = digits(n); return; }
    var t0 = null, dur = 1700;
    (function step(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 4);
      el.textContent = digits(Math.round(n * e));
      if (p < 1) requestAnimationFrame(step);
    })(performance.now());
  }

  if (!motion) {
    $$('.c-loader').forEach(function (l) { l.classList.add('is-gone'); });
    $$('[data-count]').forEach(countUp);
    w.addEventListener('scroll', function () { headerTick(w.scrollY); }, { passive: true });
    headerTick(w.scrollY);
    return;
  }

  root.classList.add('js-motion');

  /* ---------------------------------------------------- split lines -- */
  function wrapWords(el) {
    var words = el.textContent.trim().split(/\s+/);
    el.textContent = '';
    words.forEach(function (wd, i) {
      var s = d.createElement('span');
      s.className = 'w';
      s.textContent = wd;
      el.appendChild(s);
      if (i < words.length - 1) el.appendChild(d.createTextNode(' '));
    });
    return $$('.w', el);
  }
  function splitLines(el) {
    if (!el.dataset.src) el.dataset.src = el.textContent.trim();
    el.textContent = el.dataset.src;
    var ws = wrapWords(el), lines = [], top = null;
    ws.forEach(function (s) {
      var t = s.offsetTop;
      if (top === null || Math.abs(t - top) > 4) { lines.push([]); top = t; }
      lines[lines.length - 1].push(s.textContent);
    });
    el.textContent = '';
    lines.forEach(function (ln, i) {
      var o = d.createElement('span'); o.className = 'ln';
      var inn = d.createElement('span'); inn.className = 'ln-in'; inn.style.setProperty('--li', i);
      inn.textContent = ln.join(' ');
      o.appendChild(inn); el.appendChild(o);
    });
  }
  var splits = $$('[data-split]');
  function doSplits() { splits.forEach(splitLines); }

  /* word scrub (about text) */
  var scrubs = $$('[data-scrub]').map(function (el) { return { el: el, ws: wrapWords(el) }; });

  /* ------------------------------------------------------- reveals --- */
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (en) {
      if (!en.isIntersecting) return;
      var el = en.target;
      el.classList.add('is-in');
      $$('[data-count]', el).forEach(countUp);
      io.unobserve(el);
    });
  }, { threshold: 0.14, rootMargin: '0px 0px -6% 0px' });
  /* clip-path hides an element from IntersectionObserver, so clip reveals
     are triggered by their parent instead */
  var cio = new IntersectionObserver(function (es) {
    es.forEach(function (en) {
      if (!en.isIntersecting) return;
      $$(':scope > [data-reveal^="clip"]', en.target).forEach(function (c) { c.classList.add('is-in'); });
      cio.unobserve(en.target);
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });
  function startReveals() {
    $$('[data-reveal],[data-split],.c-stat').forEach(function (el) {
      if (/^clip/.test(el.getAttribute('data-reveal') || '')) cio.observe(el.parentElement);
      else io.observe(el);
    });
  }

  /* ---------------------------------------------------- scroll FX ---- */
  var parallax = $$('[data-parallax]').map(function (el) { return { el: el, k: parseFloat(el.getAttribute('data-parallax')) || 0, cur: 0 }; });
  var hs = $('[data-hscroll]'), htrack = hs && $('[data-htrack]', hs), hbar = hs && $('[data-hbar]', hs), hdist = 0;
  function measureH() {
    if (!hs) return;
    var pin = $('.c-goals-pin', hs);
    if (vw <= 860) { hs.style.height = ''; hs.classList.remove('is-pinned'); htrack.style.transform = ''; return; }
    hs.classList.add('is-pinned');
    hdist = Math.max(0, htrack.scrollWidth - pin.clientWidth);
    hs.style.height = (hdist + vh) + 'px';
  }
  var lines = $$('[data-draw]');
  lines.forEach(function (l) { l.setAttribute('pathLength', '1'); });
  var tl = $('[data-timeline]'), tlFill = tl && $('[data-tl-fill]', tl), tlItems = tl ? $$('[data-tl-item]', tl) : [];

  function progress(el, start, end) {
    var r = el.getBoundingClientRect();
    return clamp((vh * start - r.top) / (r.height + vh * (start - end)), 0, 1);
  }

  function frame() {
    var y = w.scrollY;
    headerTick(y);

    parallax.forEach(function (p) {
      var r = p.el.parentElement.getBoundingClientRect();
      if (r.bottom < -200 || r.top > vh + 200) return;
      var target = ((r.top + r.height / 2) - vh / 2) * p.k;
      p.cur += (target - p.cur) * 0.12;
      p.el.style.transform = 'translate3d(0,' + p.cur.toFixed(2) + 'px,0)';
    });

    scrubs.forEach(function (s) {
      var p = progress(s.el, 0.88, 0.42), n = s.ws.length, lit = p * (n + 6);
      for (var i = 0; i < n; i++) {
        var o = clamp(lit - i, 0, 6) / 6;
        s.ws[i].style.opacity = (0.18 + 0.82 * o).toFixed(3);
      }
    });

    if (hs && hdist) {
      var r = hs.getBoundingClientRect();
      var p = clamp(-r.top / hdist, 0, 1);
      htrack.style.transform = 'translate3d(' + ((RTL ? 1 : -1) * p * hdist).toFixed(1) + 'px,0,0)';
      if (hbar) hbar.style.transform = 'scaleX(' + p.toFixed(4) + ')';
    }

    lines.forEach(function (l) {
      var host = l.closest('[data-line]') || l.parentNode;
      var p = progress(host, 0.95, 0.55);
      l.style.strokeDashoffset = (1 - p).toFixed(4);
    });

    if (tl) {
      var tr = tl.getBoundingClientRect(), mark = vh * 0.62;
      var tp = clamp((mark - tr.top) / tr.height, 0, 1);
      if (tlFill) tlFill.style.transform = 'scaleY(' + tp.toFixed(4) + ')';
      tlItems.forEach(function (it) {
        var nr = it.getBoundingClientRect();
        it.classList.toggle('is-lit', nr.top + 30 < mark);
      });
    }
    requestAnimationFrame(frame);
  }

  /* --------------------------------------------- pointer: magnetic --- */
  if (fine) {
    $$('[data-magnetic]').forEach(function (b) {
      b.addEventListener('pointermove', function (e) {
        var r = b.getBoundingClientRect();
        var x = e.clientX - r.left - r.width / 2, y = e.clientY - r.top - r.height / 2;
        b.style.transform = 'translate(' + (x * 0.22).toFixed(1) + 'px,' + (y * 0.32).toFixed(1) + 'px)';
      });
      b.addEventListener('pointerleave', function () { b.style.transform = ''; });
    });

    /* soft cursor */
    var cur = d.createElement('div'); cur.className = 'c-cursor'; body.appendChild(cur);
    var cx = vw / 2, cy = vh / 2, tx = cx, ty = cy;
    d.addEventListener('pointermove', function (e) { tx = e.clientX; ty = e.clientY; cur.classList.add('is-on'); }, { passive: true });
    d.addEventListener('pointerleave', function () { cur.classList.remove('is-on'); });
    d.addEventListener('pointerover', function (e) { cur.classList.toggle('is-link', !!(e.target.closest && e.target.closest('a,button,[data-magnetic]'))); });
    (function loop() {
      cx += (tx - cx) * 0.2; cy += (ty - cy) * 0.2;
      cur.style.transform = 'translate3d(' + cx.toFixed(1) + 'px,' + cy.toFixed(1) + 'px,0)';
      requestAnimationFrame(loop);
    })();

    /* hero pattern drift */
    var pat = $('[data-pattern]');
    if (pat) d.addEventListener('pointermove', function (e) {
      var x = (e.clientX / vw - 0.5) * 18, y = (e.clientY / vh - 0.5) * 18;
      pat.style.transform = 'translate3d(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px,0)';
    }, { passive: true });
  }

  /* ------------------------------------------------------- resize ---- */
  var rt;
  w.addEventListener('resize', function () {
    clearTimeout(rt);
    rt = setTimeout(function () {
      var nw = w.innerWidth;
      vh = w.innerHeight;
      if (nw !== vw) { vw = nw; doSplits(); $$('[data-split]').forEach(function (el) { if (el.classList.contains('is-in')) el.classList.add('is-in'); }); }
      measureH();
    }, 180);
  });

  /* -------------------------------------------------------- boot ----- */
  function boot() {
    doSplits();
    measureH();
    requestAnimationFrame(frame);
    var loader = $('#cLoader');
    if (!loader) { startReveals(); return; }
    var seen = false;
    try { seen = sessionStorage.getItem('csr-intro') === '1'; sessionStorage.setItem('csr-intro', '1'); } catch (e) {}
    var wait = seen ? 350 : 1500;
    var go = function () {
      loader.classList.add('is-done');
      setTimeout(startReveals, 280);
      setTimeout(function () { loader.classList.add('is-gone'); }, 1100);
    };
    setTimeout(go, wait);
  }
  if (d.fonts && d.fonts.ready) d.fonts.ready.then(boot); else w.addEventListener('load', boot);
})();
