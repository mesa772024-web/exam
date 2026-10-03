/* Baghdad Al Hayat — home intro (same sequence as the brand intro)
   0.0s a pen draws the leaf: stem → outline → veins (vector, 60fps)
   2.3s leaf blooms + shine sweeps across it
   0.9s Arabic wordmark writes right→left, 1.7s English left→right
   3.4s shine sweeps across the wordmark
   4.0s divider → 4.4s tagline chip types → headline, text and buttons rise in
   then a leaf+text shine every 7s. Click the logo to replay. */
(function () {
  var hero = document.getElementById('hero');
  if (!hero) return;
  var body = document.body;
  var root = document.documentElement;
  var leaf = document.getElementById('leaf');
  var spark = document.getElementById('spark');   // template, cloned per stroke
  var typed = document.getElementById('typed');
  var ghost = document.getElementById('typed-ghost');
  var fx = hero.querySelector('.fx');
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var timers = [], raf = 0, introDone = false;

  function tagline() {
    return root.getAttribute('data-lang') === 'en' ? typed.getAttribute('data-en') : typed.getAttribute('data-ar');
  }

  /* ---- wordmark: stroke lengths for the "writing" effect ---- */
  var inks = hero.querySelectorAll('.wordmark .ink path');
  for (var i = 0; i < inks.length; i++) {
    var len = Math.ceil(inks[i].getTotalLength()) + 2;
    inks[i].style.strokeDasharray = len + ' ' + len;
    inks[i].style.strokeDashoffset = len;
  }

  /* ---- leaf: each part is revealed by a thick mask stroke running along its centreline ---- */
  var EASE = {
    io: function (t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; },
    o:  function (t) { return 1 - Math.pow(1 - t, 3); },
    s:  function (t) { return (1 - Math.cos(Math.PI * t)) / 2; }
  };
  var parts = [].map.call(hero.querySelectorAll('.leaf .lp'), function (el) {
    var mask = document.getElementById(el.id.replace('lf', 'lm'));
    var mp = mask.querySelector('path');
    var L = mp.getTotalLength();
    mp.style.strokeDasharray = L + ' ' + (L + 10);
    mp.style.strokeDashoffset = reduced ? 0 : L;   // hidden until the pen reaches it
    var sp = spark.cloneNode(false);               // every stroke gets its own glowing pen tip
    sp.removeAttribute('id');
    spark.parentNode.appendChild(sp);
    return { el: el, mp: mp, L: L, sp: sp, mask: 'url(#' + mask.id + ')',
             t: +el.dataset.t, d: +el.dataset.d, e: EASE[el.dataset.e] || EASE.o };
  });
  var LEAF_END = parts.reduce(function (m, p) { return Math.max(m, p.t + p.d); }, 0);
  spark.parentNode.removeChild(spark);

  function setLeaf(progressFn, now) {
    parts.forEach(function (p) {
      var k = progressFn(p);
      p.mp.style.strokeDashoffset = p.L * (1 - k);
      var a = k > 0 && k < 1 ? Math.min(1, Math.sin(Math.PI * k) * 2.2) : 0;
      p.sp.style.opacity = a;
      if (a > 0) {
        var pt = p.mp.getPointAtLength(p.L * k);
        p.sp.setAttribute('cx', pt.x); p.sp.setAttribute('cy', pt.y);
        p.sp.setAttribute('r', 7 + Math.sin((now || 0) / 90) * 1.5);
      }
    });
  }
  function drawLeaf() {
    cancelAnimationFrame(raf);
    leaf.classList.remove('bloom', 'sway');
    parts.forEach(function (p) { p.el.setAttribute('mask', p.mask); });
    setLeaf(function () { return 0; });
    var t0 = performance.now();
    (function frame(now) {
      var s = Math.max(0, now - t0) / 1000;
      setLeaf(function (p) { return p.e(Math.min(1, Math.max(0, (s - p.t) / p.d))); }, now);
      if (s < LEAF_END + .05) raf = requestAnimationFrame(frame);
      else finishLeaf();
    })(t0);
  }
  function finishLeaf() {
    // drop the masks once drawn so the leaf renders as crisp, plain vector
    parts.forEach(function (p) { p.el.removeAttribute('mask'); p.sp.style.opacity = 0; });
  }

  /* ---- typing ---- */
  var typeTimer = 0;
  function typeTag(instant) {
    clearTimeout(typeTimer);
    var text = tagline();
    ghost.textContent = text;
    typed.classList.remove('done');
    if (instant || reduced) { typed.textContent = text; typed.classList.add('done'); return; }
    var chars = Array.from ? Array.from(text) : text.split('');
    var n = 0;
    typed.textContent = '';
    (function step() {
      typed.textContent = chars.slice(0, ++n).join('');
      if (n < chars.length) typeTimer = setTimeout(step, 42 + Math.random() * 46);
      else typed.classList.add('done');
    })();
  }
  document.addEventListener('langchange', function () {
    ghost.textContent = tagline();
    if (introDone) typeTag(false); else typed.textContent = '';
  });

  function shine(cls) {
    hero.classList.remove('shine', 'shine-text', 'shine-leaf');
    void hero.offsetWidth;
    hero.classList.add(cls);
  }

  /* ---- skip: the visitor scrolls or presses a key during the intro ---- */
  function skip() {
    if (introDone) return;
    hero.classList.add('skip');
    body.classList.add('nav-ready');
    introDone = true;
    typeTag(true);
  }
  ['wheel', 'touchmove', 'keydown'].forEach(function (ev) {
    window.addEventListener(ev, skip, { passive: true, once: true });
  });

  /* ---- sequence ---- */
  function start() {
    timers.forEach(clearTimeout); timers = [];
    hero.classList.remove('play', 'shine', 'shine-text', 'shine-leaf');
    void hero.offsetWidth;
    hero.classList.add('on', 'play');
    if (fx) fx.classList.add('on');

    if (reduced) {
      hero.classList.add('instant');
      body.classList.add('nav-ready');
      finishLeaf();
      introDone = true;
      typeTag(true);
      return;
    }
    drawLeaf();
    timers.push(setTimeout(function () { body.classList.add('nav-ready'); }, 900));
    timers.push(setTimeout(function () {
      leaf.classList.add('bloom');
      shine('shine-leaf');
    }, (LEAF_END + .05) * 1000));
    timers.push(setTimeout(function () { leaf.classList.remove('bloom'); leaf.classList.add('sway'); }, (LEAF_END + 1.9) * 1000));
    timers.push(setTimeout(function () { shine('shine-text'); }, 3400));
    if (!introDone) {
      typed.textContent = '';
      timers.push(setTimeout(function () { introDone = true; typeTag(false); }, 4750));
    }
    var loop = function () { shine('shine'); timers.push(setTimeout(loop, 7000)); };
    timers.push(setTimeout(loop, 10000));
  }

  ghost.textContent = tagline();
  var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  Promise.race([fontsReady, new Promise(function (r) { setTimeout(r, 1500); })]).then(function () {
    requestAnimationFrame(start);
  });

  /* click the logo to replay the drawing */
  document.getElementById('lockup').addEventListener('click', function () {
    hero.classList.add('skip');
    start();
  });
})();
