/* Local-only enhancements; the complete coverage remains readable without JavaScript. */
(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var ar = document.documentElement.lang === 'ar';
  var all = function (q, scope) { return Array.from((scope || document).querySelectorAll(q)); };

  if ('IntersectionObserver' in window && !reduced.matches) {
    document.documentElement.classList.add('recap-motion');
    var reveal = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); reveal.unobserve(entry.target); } });
    }, { threshold: 0.06 });
    all('.recap-reveal').forEach(function (el) { reveal.observe(el); });
  }

  /* ---------- Interactive extras: cascading reveals, pixel parallax, scroll progress, back-to-top ---------- */
  // تتابع ظهور العناصر المتجاورة (بطاقات، شعارات، فقرات الأجندة…)
  var groups = new Map();
  all('.reveal, .recap-reveal').forEach(function (el) {
    var p = el.parentElement; if (!p || el.classList.contains('story')) return;
    if (!groups.has(p)) groups.set(p, []);
    groups.get(p).push(el);
  });
  var clearDelay = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      var el = en.target, d = parseInt(el.style.transitionDelay, 10) || 0;
      clearDelay.unobserve(el);
      setTimeout(function () { el.style.transitionDelay = ''; }, d + 1100); // لا نؤخر تأثيرات المرور بعد الظهور
    });
  }, { threshold: 0.05 }) : null;
  groups.forEach(function (els) {
    if (els.length < 2) return;
    els.forEach(function (el, i) {
      if (el.style.transitionDelay) return;
      el.style.transitionDelay = Math.min(i * 80, 480) + 'ms';
      if (clearDelay) clearDelay.observe(el);
    });
  });
  all('.story-extra .recap-people').forEach(function (ul) { all('li', ul).forEach(function (li, i) { li.style.setProperty('--i', i); }); });

  // مربعات الديجيتال تتبع المؤشر بلطف
  var stage = document.querySelector('.stage'), clusters = all('.stage-pixels');
  if (stage && clusters.length && !reduced.matches && window.matchMedia('(pointer:fine)').matches) {
    var raf = 0, mx = 0, my = 0;
    stage.addEventListener('pointermove', function (e) {
      var r = stage.getBoundingClientRect();
      mx = (e.clientX - r.left) / r.width - 0.5; my = (e.clientY - r.top) / r.height - 0.5;
      if (raf) return;
      raf = requestAnimationFrame(function () {
        raf = 0;
        clusters.forEach(function (c, i) { var k = i ? -1 : 1; c.style.setProperty('--px', (mx * 14 * k) + 'px'); c.style.setProperty('--py', (my * 10) + 'px'); });
      });
    });
    stage.addEventListener('pointerleave', function () { clusters.forEach(function (c) { c.style.setProperty('--px', '0px'); c.style.setProperty('--py', '0px'); }); });
  }

  // شريط تقدّم القراءة + زر العودة للأعلى
  var bar = document.createElement('div'); bar.className = 'scroll-progress'; bar.innerHTML = '<i></i>';
  var top = document.createElement('button'); top.type = 'button'; top.className = 'to-top';
  top.setAttribute('aria-label', ar ? 'العودة إلى الأعلى' : 'Back to top');
  top.innerHTML = '<svg class="ring" viewBox="0 0 50 50" aria-hidden="true"><circle cx="25" cy="25" r="22"/></svg><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
  document.body.appendChild(bar); document.body.appendChild(top);
  var barI = bar.firstChild, ticking = false;
  function onScroll() {
    ticking = false;
    var h = document.documentElement.scrollHeight - innerHeight, p = h > 0 ? Math.min(1, scrollY / h) : 0;
    barI.style.transform = 'scaleX(' + p + ')';
    top.style.setProperty('--p', p);
    top.classList.toggle('show', scrollY > 700);
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduced.matches ? 'auto' : 'smooth' }); });
  onScroll();

  /* ---------- Motion slider: crossfade + Ken Burns; the progress animation drives timing ---------- */
  function loadSlide(slide) {
    if (!slide) return;
    var img = slide.querySelector('img[data-src]');
    if (!img) return;
    if (img.dataset.srcset) { img.srcset = img.dataset.srcset; img.removeAttribute('data-srcset'); }
    img.src = img.dataset.src; img.removeAttribute('data-src');
  }
  all('[data-rs]').forEach(function (root) {
    var slides = all('[data-slide]', root), dots = all('[data-slide-to]', root);
    if (slides.length < 2) return;
    var index = 0, userPaused = false, hover = false, focused = false, inView = false, touchX = null, touchY = null, leaveTimer = null;
    var toggle = root.querySelector('[data-slide-toggle]'), counter = root.querySelector('[data-slide-counter]');

    function sync() {
      var stop = userPaused || hover || focused || !inView || document.hidden || reduced.matches;
      root.classList.toggle('is-paused', stop);
      root.classList.toggle('is-static', reduced.matches);
    }
    function show(next) {
      var prev = index;
      index = (next + slides.length) % slides.length;
      if (prev === index) return;
      loadSlide(slides[index]); loadSlide(slides[(index + 1) % slides.length]);
      clearTimeout(leaveTimer);
      slides.forEach(function (s) { s.classList.remove('is-leaving'); });
      slides[prev].classList.remove('is-active');
      slides[prev].classList.add('is-leaving');
      slides[prev].setAttribute('aria-hidden', 'true');
      slides[index].classList.add('is-active');
      slides[index].removeAttribute('aria-hidden');
      leaveTimer = setTimeout(function () { slides[prev].classList.remove('is-leaving'); }, 1300);
      dots.forEach(function (dot, i) {
        dot.setAttribute('aria-current', i === index ? 'true' : 'false');
        dot.classList.toggle('is-done', i < index);
        var bar = dot.querySelector('i');
        if (bar) { bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; }
      });
      if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
    }
    function updateToggle() {
      if (!toggle) return;
      toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute(userPaused ? 'data-play' : 'data-pause'));
    }
    dots.forEach(function (dot) {
      var bar = dot.querySelector('i');
      if (bar) bar.addEventListener('animationend', function () { if (dot.getAttribute('aria-current') === 'true' && !reduced.matches) show(index + 1); });
      dot.addEventListener('click', function () { show(Number(dot.dataset.slideTo)); });
    });
    root.querySelector('[data-slide-prev]').addEventListener('click', function () { show(index - 1); });
    root.querySelector('[data-slide-next]').addEventListener('click', function () { show(index + 1); });
    if (toggle) toggle.addEventListener('click', function () { userPaused = !userPaused; updateToggle(); sync(); });
    if (reduced.matches && toggle) toggle.hidden = true;
    root.addEventListener('mouseenter', function () { hover = true; sync(); });
    root.addEventListener('mouseleave', function () { hover = false; sync(); });
    root.addEventListener('focusin', function (e) { focused = e.target !== root; sync(); });
    root.addEventListener('focusout', function (event) { focused = root.contains(event.relatedTarget) && event.relatedTarget !== root; sync(); });
    root.addEventListener('keydown', function (event) {
      if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) < 0) return;
      event.preventDefault();
      if (event.key === 'Home') show(0);
      else if (event.key === 'End') show(slides.length - 1);
      else show(index + ((event.key === 'ArrowRight') !== ar ? 1 : -1));
    });
    root.addEventListener('touchstart', function (event) { touchX = event.changedTouches[0].clientX; touchY = event.changedTouches[0].clientY; }, { passive: true });
    root.addEventListener('touchend', function (event) {
      if (touchX === null) return;
      var dx = event.changedTouches[0].clientX - touchX, dy = event.changedTouches[0].clientY - touchY;
      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.4) show(index + ((dx < 0) !== ar ? 1 : -1));
      touchX = touchY = null;
    }, { passive: true });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        inView = entries[0].isIntersecting;
        if (inView) loadSlide(slides[(index + 1) % slides.length]);
        sync();
      }, { rootMargin: '120px 0px' }).observe(root);
    } else { inView = true; }
    document.addEventListener('visibilitychange', sync);
    if (reduced.addEventListener) reduced.addEventListener('change', function () { if (toggle) toggle.hidden = reduced.matches; sync(); });
    updateToggle(); sync();
  });

  /* ---------- Stage film: plays WITH sound on first load ----------
     Browsers block audible autoplay until the visitor interacts with the page. We try with sound first;
     if the browser refuses, the film keeps playing muted and the sound turns on at the visitor's first
     click / tap / key press anywhere on the page (unless they chose to mute it). */
  var stageVideo = document.querySelector('[data-stage-video]'), soundBtn = document.querySelector('[data-stage-sound]');
  var dialog = document.getElementById('filmDialog');
  if (stageVideo && stageVideo.dataset.hd && !stageVideo.getAttribute('src')) {
    // نسخة خفيفة للموبايل والاتصال البطيء، وعالية الدقة للحاسوب
    var conn = navigator.connection || {};
    var light = window.matchMedia('(max-width: 760px)').matches || conn.saveData || /(^|slow-)2g|3g/.test(conn.effectiveType || '');
    stageVideo.src = light && stageVideo.dataset.sd ? stageVideo.dataset.sd : stageVideo.dataset.hd;
  }
  var silent = !!(stageVideo && stageVideo.hasAttribute('data-silent'));
  if (stageVideo) {
    var saveData = navigator.connection && navigator.connection.saveData;
    var userMuted = false, inViewStage = true;
    var setSoundUI = function () {
      if (!soundBtn) return;
      var on = !stageVideo.muted;
      soundBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      soundBtn.setAttribute('aria-label', soundBtn.getAttribute(on ? 'data-on' : 'data-off'));
    };
    var quietPlay = function () { var p = stageVideo.play(); if (p && p.catch) p.catch(function () {}); };
    var gestureEvents = ['pointerdown', 'keydown', 'touchend'];
    var onGesture = function (event) {
      var t = event.target;
      if (t && t.closest && (t.closest('[data-film-open]') || t.closest('[data-stage-sound]') || t.closest('dialog'))) return;
      gestureEvents.forEach(function (n) { document.removeEventListener(n, onGesture, true); });
      if (userMuted || (dialog && dialog.open)) return;
      stageVideo.muted = false; setSoundUI();
      if (inViewStage) quietPlay();
    };
    var armGesture = function () { gestureEvents.forEach(function (n) { document.addEventListener(n, onGesture, true); }); };
    var playWithSound = function () {
      if (silent) { stageVideo.muted = true; quietPlay(); return; }
      stageVideo.muted = false;
      var p = stageVideo.play();
      if (p && p.then) {
        p.then(setSoundUI).catch(function () { stageVideo.muted = true; setSoundUI(); quietPlay(); armGesture(); });
      } else setSoundUI();
    };

    if (reduced.matches || (saveData && !silent)) { stageVideo.removeAttribute('autoplay'); stageVideo.pause(); }
    else {
      playWithSound();
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
          inViewStage = entries[0].isIntersecting;
          if (inViewStage) { if (!(dialog && dialog.open)) quietPlay(); } else stageVideo.pause();
        }, { threshold: 0.15 }).observe(stageVideo);
      }
    }
    if (soundBtn) soundBtn.addEventListener('click', function () {
      var turnOn = stageVideo.muted;
      stageVideo.muted = !turnOn;
      userMuted = !turnOn;
      if (turnOn) quietPlay();
      setSoundUI();
    });
    setSoundUI();
  }

  /* ---------- Film dialog: full showreel with sound ---------- */
  if (dialog && typeof dialog.showModal === 'function') {
    var film = dialog.querySelector('[data-film-video]');
    var close = function () { if (dialog.open) dialog.close(); };
    all('[data-film-open]').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        if (stageVideo) stageVideo.pause();
        dialog.showModal();
        film.currentTime = 0;
        var p = film.play(); if (p && p.catch) p.catch(function () {});
      });
    });
    dialog.querySelector('[data-film-close]').addEventListener('click', close);
    dialog.addEventListener('click', function (event) { if (event.target === dialog) close(); });
    dialog.addEventListener('close', function () {
      film.pause();
      if (stageVideo && !reduced.matches) { var p = stageVideo.play(); if (p && p.catch) p.catch(function () {}); }
    });
  }

  /* ---------- Speak Up form ---------- */
  var sp = document.querySelector('[data-speakup]');
  if (sp && window.fetch && window.FormData) {
    var msg = sp.querySelector('textarea[name="message"]'), count = sp.querySelector('.sp-count');
    var status = sp.querySelector('.sp-status'), submit = sp.querySelector('.sp-submit');
    var fields = sp.querySelector('.sp-fields'), done = sp.querySelector('.sp-done');
    var updateCount = function () { if (count) count.textContent = msg.value.length + ' / 2000'; };
    msg.addEventListener('input', updateCount);
    var addError = function (field, text) {
      field.classList.add('has-error');
      var er = document.createElement('span'); er.className = 'sp-err'; er.textContent = text;
      field.appendChild(er);
    };
    var clearErrors = function () {
      all('.sp-field', sp).forEach(function (f) { f.classList.remove('has-error'); var er = f.querySelector('.sp-err'); if (er) er.remove(); });
      status.textContent = ''; status.classList.remove('is-error');
    };
    sp.addEventListener('submit', function (event) {
      event.preventDefault();
      clearErrors();
      if (msg.value.trim().length < 5) {
        addError(msg.closest('.sp-field'), ar ? 'اكتب رسالتك (5 أحرف على الأقل)' : 'Please write your message (at least 5 characters)');
        msg.focus(); return;
      }
      submit.disabled = true; sp.classList.add('is-busy');
      fetch(sp.action, { method: 'POST', body: new FormData(sp), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.status = r.status; return j; }); })
        .then(function (j) {
          if (j.ok) {
            fields.hidden = true; done.hidden = false;
            done.querySelector('[data-speakup-again]').focus();
            return;
          }
          if (j.errors) {
            Object.keys(j.errors).forEach(function (k) {
              var input = sp.querySelector('[name="' + k + '"]');
              if (input) addError(input.closest('.sp-field'), j.errors[k]);
            });
          }
          status.classList.add('is-error');
          status.textContent = j.status === 419 ? (ar ? 'انتهت صلاحية الصفحة، حدّثها ثم أعد الإرسال.' : 'This page has expired. Please refresh and send again.') : (j.msg || (ar ? 'تعذّر الإرسال، حاول مجدداً.' : 'Could not send. Please try again.'));
        })
        .catch(function () { status.classList.add('is-error'); status.textContent = ar ? 'تعذّر الاتصال، تحقق من الإنترنت.' : 'Connection failed. Please check your internet.'; })
        .then(function () { submit.disabled = false; sp.classList.remove('is-busy'); });
    });
    done.querySelector('[data-speakup-again]').addEventListener('click', function () {
      sp.reset(); updateCount(); clearErrors();
      sp.querySelector('[name="_ft"]').value = Math.floor(Date.now() / 1000) - 5;
      done.hidden = true; fields.hidden = false; msg.focus();
    });
  }

  /* ---------- Lightbox: photos open in a centred popup ---------- */
  var lb = null, lbItems = [], lbIndex = 0;
  function chevron() { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>'; }
  function buildLightbox() {
    if (lb || typeof HTMLDialogElement !== 'function') return lb;
    lb = document.createElement('dialog');
    lb.className = 'lightbox';
    lb.setAttribute('aria-label', ar ? 'عرض الصورة' : 'Photo viewer');
    lb.innerHTML = '<span class="lb-count" aria-live="polite"></span>' +
      '<button type="button" class="lb-btn lb-close" aria-label="' + (ar ? 'إغلاق' : 'Close') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
      '<button type="button" class="lb-btn lb-prev" aria-label="' + (ar ? 'الصورة السابقة' : 'Previous photograph') + '">' + chevron() + '</button>' +
      '<figure class="lb-figure"><img class="lb-img" alt=""><figcaption class="lb-cap"></figcaption></figure>' +
      '<button type="button" class="lb-btn lb-next" aria-label="' + (ar ? 'الصورة التالية' : 'Next photograph') + '">' + chevron().replace('m15 18-6-6 6-6', 'm9 18 6-6-6-6') + '</button>';
    document.body.appendChild(lb);
    lb.querySelector('.lb-close').addEventListener('click', function () { lb.close(); });
    lb.querySelector('.lb-prev').addEventListener('click', function () { lbShow(lbIndex - 1); });
    lb.querySelector('.lb-next').addEventListener('click', function () { lbShow(lbIndex + 1); });
    lb.addEventListener('click', function (event) { if (event.target === lb) lb.close(); });
    lb.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') lbShow(lbIndex + (ar ? 1 : -1));
      else if (event.key === 'ArrowRight') lbShow(lbIndex + (ar ? -1 : 1));
    });
    var sx = null;
    lb.addEventListener('touchstart', function (e) { sx = e.changedTouches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (sx === null) return;
      var dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) lbShow(lbIndex + ((dx < 0) !== ar ? 1 : -1));
      sx = null;
    }, { passive: true });
    return lb;
  }
  function lbShow(i) {
    lbIndex = (i + lbItems.length) % lbItems.length;
    var img = lb.querySelector('.lb-img'), item = lbItems[lbIndex];
    img.classList.remove('is-swap'); void img.offsetWidth; img.classList.add('is-swap');
    img.src = item.src; img.alt = item.alt || '';
    lb.querySelector('.lb-cap').textContent = item.alt || '';
    lb.querySelector('.lb-count').textContent = (lbIndex + 1) + ' / ' + lbItems.length;
    var multi = lbItems.length > 1;
    lb.querySelector('.lb-prev').hidden = !multi; lb.querySelector('.lb-next').hidden = !multi;
    new Image().src = lbItems[(lbIndex + 1) % lbItems.length].src;
  }
  function openLightbox(items, start) {
    if (!items.length || !buildLightbox()) return false;
    lbItems = items;
    lbShow(start || 0);
    lb.showModal();
    return true;
  }
  all('[data-rs] .rs-stage').forEach(function (stage) {
    stage.addEventListener('click', function () {
      var slides = all('[data-slide]', stage), start = 0;
      var items = slides.map(function (s, i) {
        if (s.classList.contains('is-active')) start = i;
        var img = s.querySelector('img');
        return { src: img.dataset.full || img.dataset.src || img.currentSrc || img.src, alt: img.alt };
      });
      openLightbox(items, start);
    });
  });
  var tiles = all('.intl-rotate .intl-tile');
  if (tiles.length) {
    var tileItems = [];
    tiles.forEach(function (t) { all('img', t).forEach(function (img) { tileItems.push({ src: img.currentSrc || img.src, alt: img.alt || (tiles[0].querySelector('img').alt) }); }); });
    tiles.forEach(function (t, i) { t.addEventListener('click', function () { openLightbox(tileItems, i * 2); }); });
  }
  var gridLinks = all('.recap-photo-grid a');
  if (gridLinks.length) {
    var gridItems = gridLinks.map(function (a) { var img = a.querySelector('img'); return { src: a.href, alt: img ? img.alt : '' }; });
    gridLinks.forEach(function (a, i) { a.addEventListener('click', function (e) { if (openLightbox(gridItems, i)) e.preventDefault(); }); });
  }

  /* ---------- Coverage filters ---------- */
  var filters = document.querySelector('[data-recap-filters]');
  if (filters) {
    filters.hidden = false;
    all('[data-filter]', filters).forEach(function (button) {
      button.addEventListener('click', function () {
        all('[data-filter]', filters).forEach(function (b) { b.setAttribute('aria-pressed', b === button ? 'true' : 'false'); });
        var visible = 0;
        all('.story[data-category]').forEach(function (card) {
          card.hidden = button.dataset.filter !== 'all' && card.dataset.category !== button.dataset.filter;
          if (!card.hidden) { visible++; card.classList.add('is-visible'); }
        });
        document.querySelector('[data-filter-status]').textContent = ar ? 'عدد المنشورات المعروضة: ' + visible : visible + ' stories shown';
      });
    });
  }

  // Keep keyboard focus within the open mobile navigation and return it on Escape.
  var burger = document.getElementById('burger'), menu = document.getElementById('mobileMenu');
  if (burger && menu) {
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Tab' || !menu.classList.contains('open')) return;
      var links = all('a[href],button:not([disabled])', menu).filter(function (el) { return el.getClientRects().length; });
      var focusables = [burger].concat(links), first = focusables[0], last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    menu.addEventListener('keydown', function (event) { if (event.key === 'Escape') burger.focus(); });
  }
})();
