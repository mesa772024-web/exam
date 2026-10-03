/* Baghdad Al Hayat — shared behaviour (all pages)
   language toggle (AR/EN) · navigation · scroll reveal · counters · particles · contact form */
(function () {
  var root = document.documentElement;
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- language ---------- */
  function store(key, val) { try { localStorage.setItem(key, val); } catch (e) {} }
  function applyLang(lang) {
    root.setAttribute('data-lang', lang);
    root.setAttribute('lang', lang);
    root.setAttribute('dir', lang === 'en' ? 'ltr' : 'rtl');
    var t = document.querySelector('meta[name="title-' + lang + '"]');
    if (t) document.title = t.content;
    [].forEach.call(document.querySelectorAll('[data-ph-ar]'), function (el) {
      el.setAttribute('placeholder', el.getAttribute('data-ph-' + lang) || '');
    });
    [].forEach.call(document.querySelectorAll('[data-alt-ar]'), function (el) {
      el.setAttribute('alt', el.getAttribute('data-alt-' + lang) || '');
    });
    [].forEach.call(document.querySelectorAll('[data-label-ar]'), function (el) {
      el.setAttribute('aria-label', el.getAttribute('data-label-' + lang) || '');
    });
    document.dispatchEvent(new CustomEvent('langchange', { detail: lang }));
  }
  applyLang(root.getAttribute('data-lang') === 'en' ? 'en' : 'ar');
  [].forEach.call(document.querySelectorAll('[data-lang-toggle]'), function (btn) {
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-lang') === 'en' ? 'ar' : 'en';
      store('bh-lang', next);
      applyLang(next);
    });
  });

  /* ---------- year ---------- */
  [].forEach.call(document.querySelectorAll('[data-year]'), function (el) { el.textContent = new Date().getFullYear(); });

  /* ---------- nav ---------- */
  var nav = document.querySelector('.nav');
  function onScroll() { if (nav) nav.classList.toggle('scrolled', window.scrollY > 24); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  var body = document.body;
  function setMenu(open) {
    body.classList.toggle('menu-open', open);
    var mm = document.querySelector('.mobile-menu');
    if (mm) mm.setAttribute('aria-hidden', open ? 'false' : 'true');
    [].forEach.call(document.querySelectorAll('.menu-btn'), function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }
  [].forEach.call(document.querySelectorAll('.menu-btn'), function (b) {
    b.addEventListener('click', function () { setMenu(!body.classList.contains('menu-open')); });
  });
  [].forEach.call(document.querySelectorAll('.mobile-menu a'), function (a) {
    a.addEventListener('click', function () { setMenu(false); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

  /* ---------- counters ---------- */
  function countUp(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
    var suffix = el.querySelector('i') ? el.querySelector('i').outerHTML : '';
    if (reduced || isNaN(target)) return;
    var t0 = performance.now(), dur = 1600;
    (function frame(now) {
      var k = Math.min(1, (now - t0) / dur);
      var e = 1 - Math.pow(1 - k, 3);
      var v = (target * e).toFixed(decimals);
      if (!decimals) v = Number(v).toLocaleString('en-US');
      el.innerHTML = v + suffix;
      if (k < 1) requestAnimationFrame(frame);
    })(t0);
  }

  /* ---------- scroll reveal ---------- */
  var revealables = document.querySelectorAll('.reveal, .rule:not(.hero-rule), [data-count]');
  if ('IntersectionObserver' in window && !reduced) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        el.classList.add('in');
        if (el.hasAttribute('data-count')) countUp(el);
        io.unobserve(el);
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -6% 0px' });
    [].forEach.call(revealables, function (el) {
      if (el.hasAttribute('data-count')) {
        var sfx = el.querySelector('i');
        el.innerHTML = '0' + (sfx ? sfx.outerHTML : '');
      }
      io.observe(el);
    });
  } else {
    [].forEach.call(revealables, function (el) { el.classList.add('in'); });
  }

  /* ---------- floating particles (same as the intro) ---------- */
  if (!reduced) {
    [].forEach.call(document.querySelectorAll('.particles'), function (box) {
      var count = +(box.getAttribute('data-count-p') || (window.innerWidth < 720 ? 10 : 18));
      for (var k = 0; k < count; k++) {
        var d = document.createElement('i');
        var s = 2 + Math.random() * 4;
        d.style.cssText = 'left:' + (Math.random() * 100) + '%;width:' + s + 'px;height:' + s + 'px;' +
          'animation-duration:' + (14 + Math.random() * 16) + 's;animation-delay:' + (-Math.random() * 25) + 's;' +
          '--dx:' + (Math.random() * 80 - 40) + 'px;--o:' + (0.08 + Math.random() * 0.16);
        box.appendChild(d);
      }
    });
  }
  requestAnimationFrame(function () {
    [].forEach.call(document.querySelectorAll('.fx[data-auto]'), function (fx) { fx.classList.add('on'); });
  });

  /* ---------- network steps: auto-advance ---------- */
  var steps = document.querySelectorAll('.steps .step');
  if (steps.length && !reduced) {
    var si = 0, paused = false;
    [].forEach.call(steps, function (s, i) {
      s.addEventListener('mouseenter', function () { paused = true; activate(i); });
      s.addEventListener('focus', function () { paused = true; activate(i); });
      s.addEventListener('mouseleave', function () { paused = false; });
    });
    function activate(i) { si = i; [].forEach.call(steps, function (s, j) { s.classList.toggle('active', j === i); }); }
    setInterval(function () { if (!paused) activate((si + 1) % steps.length); }, 2600);
  }

  /* ---------- contact form ---------- */
  var form = document.getElementById('contact-form');
  if (form) {
    var status = document.getElementById('form-status');
    function lang() { return root.getAttribute('data-lang') === 'en' ? 'en' : 'ar'; }
    function say(kind, ar, en) {
      status.className = 'form-status ' + kind;
      status.textContent = lang() === 'en' ? en : ar;
    }
    function mailtoFallback(data) {
      var to = form.getAttribute('data-mail');
      var subject = data.get('subject') || (lang() === 'en' ? 'Message from the website' : 'رسالة من الموقع');
      var bodyTxt = (lang() === 'en' ? 'Name: ' : 'الاسم: ') + data.get('name') + '\n' +
        (data.get('email') ? 'Email: ' + data.get('email') + '\n' : '') +
        (data.get('phone') ? (lang() === 'en' ? 'Phone: ' : 'الهاتف: ') + data.get('phone') + '\n' : '') +
        '\n' + data.get('message');
      window.location.href = 'mailto:' + to + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(bodyTxt);
      say('ok', 'تم فتح برنامج البريد لديك لإرسال الرسالة.', 'Your email app was opened to send the message.');
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = new FormData(form);
      if (!String(data.get('name') || '').trim() || !String(data.get('message') || '').trim()) {
        say('err', 'يرجى إدخال الاسم ونص الرسالة.', 'Please enter your name and a message.');
        return;
      }
      var email = String(data.get('email') || '').trim();
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        say('err', 'صيغة البريد الإلكتروني غير صحيحة، أو اتركه فارغاً.', 'The email format is invalid — or leave it empty.');
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      data.append('lang', lang());
      fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (res) {
          if (res && res.ok) {
            form.reset();
            say('ok', 'تم استلام رسالتك. شكراً لتواصلك — سيطّلع عليها فريق المكتب.', 'Your message was received. Thank you — the office team will review it.');
          } else if (res && res.error) {
            say('err', res.error_ar || res.error, res.error);
          } else { mailtoFallback(data); }
        })
        .catch(function () { mailtoFallback(data); })
        .then(function () { btn.disabled = false; });
    });
  }
})();
