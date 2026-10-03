/* Ambient motion layer — animated backgrounds (canvas + CSS), card spotlights.
   Purely decorative: it adds layers behind existing content and never edits text.
   Colours come from the site palette (--amb-* in ambient.css), so the visual
   identity of each site stays exactly as it is.
   Performance: draws only sections that are on screen, pauses in background
   tabs, caps device-pixel-ratio, fewer particles on phones, and shows a still
   frame when the visitor prefers reduced motion. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  if (!doc.body || doc.body.dataset.ambient === 'off') return;

  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;
  var phone = Math.min(window.innerWidth, window.innerHeight) < 700;

  /* ---------- palette ---------- */
  function cssVar(name, fallback) {
    var v = getComputedStyle(root).getPropertyValue(name).trim();
    return v || fallback;
  }
  function hexToRgb(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex.split('').map(function (c) { return c + c; }).join('');
    var n = parseInt(hex, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  var PAL = {
    a: hexToRgb(cssVar('--amb-a', '#28b46b')),   // main brand tone
    b: hexToRgb(cssVar('--amb-b', '#12693f')),   // deep brand tone
    c: hexToRgb(cssVar('--amb-c', '#8ff0bb'))    // light brand tone (on dark)
  };
  function rgba(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }

  /* ---------- where the motion lives ----------
     mode: light = soft network on white, dark = glowing network on dark,
           brand = light particles on a brand-coloured block */
  var HOSTS = [
    ['.hero', 'light', 1], ['.services-wrap', 'light', .7], ['.proof-section', 'dark', 1],
    ['.home-partners', 'light', .6], ['#network', 'light', .7], ['#contact .contact', 'brand', 1],
    ['body > footer', 'dark', .8],
    ['.inner-hero', 'light', 1], ['.metrics-band', 'brand', 1], ['.quality-highlight', 'brand', 1],
    ['.speakup', 'light', 1], ['.landing-footer', 'dark', .8], ['.svc-cta', 'light', .8],
    ['.partners-promise', 'light', .55], ['.pv-section', 'light', .55], ['.why-section', 'light', .55]
  ];

  var scenes = [];
  HOSTS.forEach(function (h) {
    [].forEach.call(doc.querySelectorAll(h[0]), function (el) {
      if (el.closest('.intro') || el.querySelector(':scope > .amb-layer')) return;
      scenes.push(makeScene(el, h[1], h[2]));
    });
  });

  function makeScene(host, mode, density) {
    host.classList.add('amb-host', 'amb-' + mode);
    var layer = doc.createElement('div');
    layer.className = 'amb-layer';
    layer.setAttribute('aria-hidden', 'true');
    layer.innerHTML = '<span class="amb-blob b1"></span><span class="amb-blob b2"></span><span class="amb-blob b3"></span><canvas class="amb-canvas"></canvas>';
    host.insertBefore(layer, host.firstChild);
    var canvas = layer.querySelector('canvas');
    var s = { host: host, layer: layer, canvas: canvas, ctx: canvas.getContext('2d'), mode: mode,
              density: density, w: 0, h: 0, dpr: 1, parts: [], visible: false, mouse: null, t: 0 };
    resize(s);
    return s;
  }

  /* ---------- particles ---------- */
  function seed(s) {
    var area = s.w * s.h;
    var per = phone ? 26000 : 15000;
    var n = Math.round(Math.min(phone ? 34 : 78, Math.max(10, area / per)) * s.density);
    s.parts = [];
    for (var i = 0; i < n; i++) {
      s.parts.push({
        x: Math.random() * s.w, y: Math.random() * s.h,
        vx: (Math.random() - .5) * .22, vy: (Math.random() - .5) * .22,
        r: .8 + Math.random() * (s.mode === 'dark' ? 2.1 : 1.7),
        p: Math.random() * Math.PI * 2,            // pulse phase
        tone: Math.random() < .3 ? 'c' : (Math.random() < .5 ? 'a' : 'b')
      });
    }
  }

  function resize(s) {
    var r = s.host.getBoundingClientRect();
    var w = Math.max(1, Math.round(r.width)), h = Math.max(1, Math.round(r.height));
    if (w === s.w && h === s.h) return;
    s.w = w; s.h = h;
    s.dpr = Math.min(phone ? 1.25 : 1.5, window.devicePixelRatio || 1);
    s.canvas.width = Math.round(w * s.dpr);
    s.canvas.height = Math.round(h * s.dpr);
    s.canvas.style.width = w + 'px';
    s.canvas.style.height = h + 'px';
    s.ctx.setTransform(s.dpr, 0, 0, s.dpr, 0, 0);
    seed(s);
    draw(s, 0);
  }

  function draw(s, dt) {
    var ctx = s.ctx, P = s.parts, w = s.w, h = s.h;
    var link = phone ? 96 : 132, link2 = link * link;
    var dark = s.mode !== 'light';
    ctx.clearRect(0, 0, w, h);
    s.t += dt;

    for (var i = 0; i < P.length; i++) {
      var p = P[i];
      if (dt) {
        // gentle drift with a slow swirl so the field never looks mechanical
        var ang = Math.sin((p.y + s.t * .02) * .004) + Math.cos((p.x - s.t * .015) * .003);
        p.vx += Math.cos(ang) * .0035 * dt / 16;
        p.vy += Math.sin(ang) * .0035 * dt / 16;
        if (s.mouse) {
          var mx = p.x - s.mouse.x, my = p.y - s.mouse.y, md = mx * mx + my * my;
          if (md < 22000) { var f = (1 - md / 22000) * .05; p.vx += mx * f / 60; p.vy += my * f / 60; }
        }
        p.vx *= .985; p.vy *= .985;
        var sp = Math.hypot(p.vx, p.vy), max = .45;
        if (sp > max) { p.vx *= max / sp; p.vy *= max / sp; }
        if (sp < .05) { p.vx += (Math.random() - .5) * .02; p.vy += (Math.random() - .5) * .02; }
        p.x += p.vx * dt / 16; p.y += p.vy * dt / 16;
        if (p.x < -10) p.x = w + 10; else if (p.x > w + 10) p.x = -10;
        if (p.y < -10) p.y = h + 10; else if (p.y > h + 10) p.y = -10;
      }
    }

    // links — the "molecular network"
    ctx.lineWidth = 1;
    for (var a = 0; a < P.length; a++) {
      for (var b = a + 1; b < P.length; b++) {
        var dx = P[a].x - P[b].x, dy = P[a].y - P[b].y, d2 = dx * dx + dy * dy;
        if (d2 < link2) {
          var k = 1 - d2 / link2;
          ctx.strokeStyle = dark ? rgba(PAL.c, k * .22) : rgba(PAL.b, k * .17);
          ctx.beginPath(); ctx.moveTo(P[a].x, P[a].y); ctx.lineTo(P[b].x, P[b].y); ctx.stroke();
        }
      }
    }
    // nodes
    for (var j = 0; j < P.length; j++) {
      var q = P[j], pulse = .65 + Math.sin(s.t * .0016 + q.p) * .35;
      var col = dark ? PAL.c : PAL[q.tone === 'c' ? 'a' : q.tone];
      if (dark) {
        ctx.fillStyle = rgba(col, .10 * pulse);
        ctx.beginPath(); ctx.arc(q.x, q.y, q.r * 4.2, 0, 6.2832); ctx.fill();
      }
      ctx.fillStyle = rgba(col, (dark ? .75 : .5) * pulse);
      ctx.beginPath(); ctx.arc(q.x, q.y, q.r, 0, 6.2832); ctx.fill();
    }
  }

  /* ---------- loop: only visible scenes, only while the tab is shown ---------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var s = scenes.find(function (x) { return x.host === e.target; });
        if (s) { s.visible = e.isIntersecting; s.layer.classList.toggle('amb-on', e.isIntersecting); }
      });
      kick();
    }, { rootMargin: '120px 0px' });
    scenes.forEach(function (s) { io.observe(s.host); });
  } else {
    scenes.forEach(function (s) { s.visible = true; s.layer.classList.add('amb-on'); });
  }
  if ('ResizeObserver' in window) {
    var ro = new ResizeObserver(function (entries) {
      entries.forEach(function (e) {
        var s = scenes.find(function (x) { return x.host === e.target; });
        if (s) resize(s);
      });
    });
    scenes.forEach(function (s) { ro.observe(s.host); });
  }

  var raf = 0, last = 0;
  function frame(now) {
    raf = 0;
    var dt = last ? Math.min(48, now - last) : 16;
    last = now;
    var any = false;
    for (var i = 0; i < scenes.length; i++) if (scenes[i].visible) { draw(scenes[i], dt); any = true; }
    if (any && !doc.hidden) raf = requestAnimationFrame(frame); else last = 0;
  }
  function kick() { if (!reduced && !raf && !doc.hidden) raf = requestAnimationFrame(frame); }
  doc.addEventListener('visibilitychange', function () { if (!doc.hidden) kick(); });
  if (reduced) root.classList.add('amb-still');
  kick();

  /* ---------- pointer: the network leans away from the cursor (desktop) ---------- */
  if (finePointer && !reduced) {
    scenes.forEach(function (s) {
      s.host.addEventListener('pointermove', function (e) {
        var r = s.host.getBoundingClientRect();
        s.mouse = { x: e.clientX - r.left, y: e.clientY - r.top };
      }, { passive: true });
      s.host.addEventListener('pointerleave', function () { s.mouse = null; }, { passive: true });
    });
  }

  /* ---------- card spotlight: a soft glow follows the cursor ---------- */
  var CARDS = '.service,.proof-card,.partner-tile,.step,.home-kpis>div,.featured,.vision-aside,' +
    '.pv-grid article,.commitment-grid article,.partner-list-large article,.sop-grid>div,' +
    '.leadership-grid blockquote,.mission-vision>div,.quality-systems>div,.speakup-card,.blog-card';
  if (finePointer && !reduced) {
    [].forEach.call(doc.querySelectorAll(CARDS), function (card) {
      if (card.querySelector(':scope > .amb-spot')) return;
      card.classList.add('amb-card');
      var spot = doc.createElement('i');
      spot.className = 'amb-spot';
      spot.setAttribute('aria-hidden', 'true');
      // second-to-last: keeps the site's own :first-child / :last-child / nth-child rules intact
      var last = card.lastElementChild;
      if (last && card.children.length > 1) card.insertBefore(spot, last); else card.appendChild(spot);
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--amb-x', (e.clientX - r.left) + 'px');
        card.style.setProperty('--amb-y', (e.clientY - r.top) + 'px');
      }, { passive: true });
    });
  }

  /* ---------- scroll progress hairline (inner pages) ---------- */
  if (doc.body.classList.contains('landing-inner-page')) {
    var bar = doc.createElement('div');
    bar.className = 'amb-progress';
    bar.setAttribute('aria-hidden', 'true');
    doc.body.appendChild(bar);
    var ticking = false;
    var upd = function () {
      ticking = false;
      var max = root.scrollHeight - innerHeight;
      bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, scrollY / max) : 0) + ')';
    };
    addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
    upd();
  }
})();
