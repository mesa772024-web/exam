/* منتدى المدن الذكية العراقي — سلوك الواجهة */
(function () {
  'use strict';

  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Top bar ---------- */
  var topbar = $('#topbar');
  var onScroll = function () {
    if (topbar) topbar.classList.toggle('scrolled', window.scrollY > 30);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile menu ---------- */
  var burger = $('#burger');
  var mm = $('#mobileMenu');
  if (burger && mm) {
    var setMenu = function (open) {
      burger.classList.toggle('open', open);
      mm.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      mm.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.style.overflow = open ? 'hidden' : '';
      document.documentElement.style.overflow = open ? 'hidden' : '';
      if (open) mm.scrollTop = 0;
    };
    burger.addEventListener('click', function () {
      setMenu(!mm.classList.contains('open'));
    });
    $$('.mm-nav a').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var href = a.getAttribute('href') || '';
        var url;
        try { url = new URL(href, window.location.href); } catch (ignore) {}
        var samePageAnchor = url && url.hash && url.pathname === window.location.pathname;
        if (!samePageAnchor) { setMenu(false); return; }
        var target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
        if (!target) { setMenu(false); return; }
        e.preventDefault();
        setMenu(false);
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            target.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
            if (window.history && history.pushState) history.pushState(null, '', url.hash);
          });
        });
      });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1100) setMenu(false); });
  }

  /* ---------- Reveal on scroll ---------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    $$('.reveal').forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- Countdown ---------- */
  var cd = $('#countdown');
  if (cd) {
    var target = new Date((cd.getAttribute('data-target') || '').replace(' ', 'T')).getTime();
    var dEl = $('#cd-d'), hEl = $('#cd-h'), mEl = $('#cd-m'), sEl = $('#cd-s');
    var tick = function () {
      var diff = Math.max(0, target - Date.now());
      var d = Math.floor(diff / 86400000);
      var h = Math.floor(diff / 3600000) % 24;
      var m = Math.floor(diff / 60000) % 60;
      var s = Math.floor(diff / 1000) % 60;
      if (dEl) dEl.textContent = d;
      if (hEl) hEl.textContent = h;
      if (mEl) mEl.textContent = m;
      if (sEl) sEl.textContent = s;
    };
    tick();
    setInterval(tick, 1000);
  }

  /* ---------- Stat counters ---------- */
  var animateCount = function (el) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var raw = el.getAttribute('data-count') || '';
    var num = parseInt(raw.replace(/[^0-9]/g, ''), 10);
    if (!num || num < 2) return;
    var suffix = raw.replace(/[0-9]/g, '');
    var t0 = null;
    var dur = 1600;
    var step = function (ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(num * eased) + suffix;
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  if ('IntersectionObserver' in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          animateCount(en.target);
          cio.unobserve(en.target);
        }
      });
    }, { threshold: 0.6 });
    $$('.stat-num').forEach(function (el) { cio.observe(el); });
  }

  /* ---------- Image carousel ---------- */
  (function () {
    var car = $('#carousel');
    if (!car) return;
    var track = $('.carousel-track', car);
    var slides = $$('.carousel-slide', car);
    if (slides.length < 1) return;
    var dotsWrap = $('.carousel-dots', car);
    var idx = 0, timer = null;
    var rtl = document.documentElement.dir === 'rtl';
    var autoplay = parseInt(car.getAttribute('data-autoplay') || '0', 10);

    if (dotsWrap) {
      slides.forEach(function (s, i) {
        var d = document.createElement('button');
        d.className = 'carousel-dot' + (i === 0 ? ' on' : '');
        d.addEventListener('click', function () { go(i); });
        dotsWrap.appendChild(d);
      });
    }
    function render() {
      var x = idx * 100 * (rtl ? 1 : -1);
      track.style.transform = 'translateX(' + x + '%)';
      $$('.carousel-dot', car).forEach(function (d, i) { d.classList.toggle('on', i === idx); });
    }
    function go(i) { idx = (i + slides.length) % slides.length; render(); restart(); }
    function next() { go(idx + 1); }
    function prev() { go(idx - 1); }
    function restart() {
      if (timer) clearInterval(timer);
      if (autoplay > 0 && slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(next, autoplay * 1000);
    }
    var nb = $('.carousel-next', car), pb = $('.carousel-prev', car);
    if (nb) nb.addEventListener('click', next);
    if (pb) pb.addEventListener('click', prev);
    // swipe
    var sx = 0;
    car.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    car.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 40) { (dx < 0) !== rtl ? next() : prev(); }
    }, { passive: true });
    car.addEventListener('mouseenter', function () { if (timer) clearInterval(timer); });
    car.addEventListener('mouseleave', restart);
    render(); restart();
  })();

  /* ---------- Agenda tabs ---------- */
  $$('.day-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var id = tab.getAttribute('data-day');
      $$('.day-tab').forEach(function (t) {
        t.classList.toggle('active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
      $$('.day-panel').forEach(function (p) {
        p.classList.toggle('active', p.getAttribute('data-day') === id);
      });
      $$('.day-panel.active .reveal').forEach(function (el) { el.classList.add('in'); });
    });
  });

  /* ---------- Captcha refresh ---------- */
  var capImg = $('#capImg');
  var capBtn = $('#capRefresh');
  var refreshCap = function () {
    if (capImg) capImg.src = 'api/captcha.php?t=' + Date.now();
  };
  if (capBtn) capBtn.addEventListener('click', refreshCap);

  /* ---------- Registration form ---------- */
  var form = $('#regForm');
  if (form) {
    var msg = $('#formMsg');
    var btn = $('#regSubmit');
    var isOther = function (value) {
      value = String(value || '').trim().toLowerCase();
      return value === 'other' || value === 'أخرى' || value === 'اخرى' || value === 'أُخرى';
    };
    var bindOtherChoice = function (selectName, otherName) {
      var select = form.querySelector('[name="' + selectName + '"]');
      var input = form.querySelector('[name="' + otherName + '"]');
      var wrap = form.querySelector('[data-other-wrap="' + selectName + '"]');
      if (!select || !input || !wrap) return;
      var sync = function () {
        var show = isOther(select.value);
        wrap.hidden = !show;
        input.required = show;
        if (!show) { input.value = ''; input.classList.remove('err'); }
      };
      select.addEventListener('change', sync);
      sync();
    };
    bindOtherChoice('title', 'title_other');
    bindOtherChoice('sector', 'sector_other');
    $$('[data-custom-other-select]', form).forEach(function (select) {
      var key = select.getAttribute('data-custom-other-select');
      var wrap = form.querySelector('[data-custom-other-wrap="' + key + '"]');
      var input = wrap ? wrap.querySelector('[name="' + key + '_other"]') : null;
      if (!wrap || !input) return;
      var syncCustomOther = function () {
        var show = select.value === '__other__';
        wrap.hidden = !show;
        input.required = show;
        if (!show) { input.value = ''; input.classList.remove('err'); }
      };
      select.addEventListener('change', syncCustomOther);
      syncCustomOther();
    });
    var clearErrors = function () {
      $$('.ferr', form).forEach(function (e) { e.textContent = ''; });
      $$('.err', form).forEach(function (e) { e.classList.remove('err'); });
      if (msg) { msg.textContent = ''; msg.classList.remove('ok'); }
    };
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      clearErrors();
      if (btn) { btn.disabled = true; btn.textContent = btn.getAttribute('data-busy') || '...'; }
      var fd = new FormData(form);
      fetch(form.getAttribute('action'), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok && form.getAttribute('data-mode') === 'edit') {
          if (msg) { msg.textContent = j.msg || 'تم الحفظ ✓'; msg.classList.add('ok'); }
          setTimeout(function () { window.location.href = 'register.php'; }, 1100);
          return;
        }
        if (j.ok) {
          form.hidden = true;
          var box = $('#regSuccess');
          var codeEl = $('#rsCode');
          var codeBlock = $('#rsCodeBlock');
          if (j.show_code === false || !j.code) {
            if (codeBlock) codeBlock.hidden = true;
          } else {
            if (codeBlock) codeBlock.hidden = false;
            if (codeEl) codeEl.textContent = j.code;
          }
          if (box) {
            box.hidden = false;
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        } else {
          if (j.errors) {
            Object.keys(j.errors).forEach(function (k) {
              var fe = form.querySelector('.ferr[data-for="' + k + '"]');
              if (fe) fe.textContent = j.errors[k];
              var inp = form.querySelector('[name="' + k + '"]');
              if (inp && inp.type !== 'radio') inp.classList.add('err');
            });
          }
          if (j.field === 'captcha') {
            var fe2 = form.querySelector('.ferr[data-for="captcha"]');
            if (fe2) fe2.textContent = j.msg || '';
          }
          if (msg) msg.textContent = j.msg || 'خطأ، أعد المحاولة';
          refreshCap();
          var cin = $('#f_captcha');
          if (cin) cin.value = '';
        }
      }).catch(function () {
        if (msg) msg.textContent = 'تعذر الاتصال، أعد المحاولة / Connection failed, retry';
        refreshCap();
      }).then(function () {
        if (btn) { btn.disabled = false; btn.textContent = btn.getAttribute('data-label') || btn.textContent; }
      });
    });
    if (btn) {
      btn.setAttribute('data-label', btn.textContent);
      btn.setAttribute('data-busy', document.documentElement.lang === 'en' ? 'Submitting...' : 'جارٍ الإرسال...');
    }
    var copyBtn = $('#rsCopy');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var t = ($('#rsCode') || {}).textContent || '';
        if (navigator.clipboard) {
          navigator.clipboard.writeText(t).then(function () {
            copyBtn.textContent = '✓';
            setTimeout(function () { copyBtn.textContent = '⧉'; }, 1500);
          });
        }
      });
    }
  }

  /* ---------- Active nav highlight ---------- */
  var secs = $$('section[id]');
  if (secs.length && 'IntersectionObserver' in window) {
    var nio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          var id = en.target.id;
          $$('.nav-a').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            a.classList.toggle('active', href === '#' + id || href.slice(href.indexOf('#')) === '#' + id);
          });
        }
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    secs.forEach(function (s) { nio.observe(s); });
  }
})();
