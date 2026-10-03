(() => {
  'use strict';

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
  const clamp = (value, min = 0, max = 1) => Math.max(min, Math.min(max, value));

  const nav = qs('[data-main-nav]');
  const menuButtons = qsa('[data-menu-button]');
  const menuButton = menuButtons[0];
  const mobileMenu = qs('[data-mobile-menu]');
  const setMobileMenu = (open) => {
    if (!menuButton || !mobileMenu) return;
    const wasOpen = mobileMenu.classList.contains('is-open');
    menuButton.setAttribute('aria-expanded', String(open));
    mobileMenu.classList.toggle('is-open', open);
    mobileMenu.setAttribute('aria-hidden', String(!open));
    document.body.classList.toggle('menu-open', open);
    if (!open && wasOpen) menuButton.focus({ preventScroll: true });
  };
  const syncNav = () => nav?.classList.toggle('is-scrolled', window.scrollY > 30);
  syncNav();
  addEventListener('scroll', syncNav, { passive: true });
  menuButtons.forEach((button) => button.addEventListener('click', () => {
    setMobileMenu(button === menuButton && menuButton.getAttribute('aria-expanded') !== 'true');
  }));
  qsa('a', mobileMenu || document.createElement('div')).forEach((link) => link.addEventListener('click', () => setMobileMenu(false)));
  addEventListener('keydown', (event) => { if (event.key === 'Escape') setMobileMenu(false); });
  addEventListener('resize', () => { if (innerWidth > 980) setMobileMenu(false); }, { passive: true });

  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const scope = document.body.dataset.pageScope || 'site';
  const excludedEditTags = new Set(['script', 'style', 'svg', 'path', 'use', 'meta', 'link', 'br', 'source', 'track', 'template', 'noscript']);
  const mediaEditTags = new Set(['img', 'video', 'iframe', 'canvas']);
  const formEditTags = new Set(['input', 'textarea', 'select', 'option']);
  const canEditText = (element) => !mediaEditTags.has(element.tagName.toLowerCase())
    && !formEditTags.has(element.tagName.toLowerCase())
    && element.children.length === 0;
  const hashPath = (element) => {
    const parts = [];
    let current = element;
    while (current && current !== document.body) {
      let index = 1;
      let sibling = current.previousElementSibling;
      while (sibling) { if (sibling.tagName === current.tagName) index += 1; sibling = sibling.previousElementSibling; }
      parts.push(`${current.tagName.toLowerCase()}:${index}`);
      current = current.parentElement;
    }
    const input = parts.reverse().join('/');
    let hash = 2166136261;
    for (let index = 0; index < input.length; index += 1) { hash ^= input.charCodeAt(index); hash = Math.imul(hash, 16777619); }
    return (hash >>> 0).toString(36);
  };
  qsa('body *').forEach((element) => {
    const tag = element.tagName.toLowerCase();
    if (excludedEditTags.has(tag) || element.closest('svg') || element.matches('[data-no-visual-edit], [data-no-visual-edit] *')) return;
    if (!element.dataset.editKey) element.dataset.editKey = `auto-${scope}-${hashPath(element)}`;
  });
  const customizationApi = document.body.dataset.customizationApi;
  if (customizationApi) {
    const url = new URL(customizationApi, location.origin);
    url.searchParams.set('scope', scope);
    url.searchParams.set('lang', document.documentElement.lang || 'ar');
    fetch(url, { credentials: 'same-origin' }).then((response) => response.ok ? response.json() : null).then((result) => {
      if (!result?.content) return;
      Object.entries(result.content).forEach(([key, value]) => {
        const element = document.querySelector(`[data-edit-key="${CSS.escape(key)}"]`);
        if (!element) return;
        if (typeof value === 'string') {
          if (canEditText(element)) element.textContent = value;
          return;
        }
        if (!value || typeof value !== 'object') return;
        const tag = element.tagName.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(value, 'text') && canEditText(element)) element.textContent = String(value.text ?? '');
        if (tag === 'a' && typeof value.href === 'string') element.setAttribute('href', value.href);
        if (['img', 'video', 'iframe'].includes(tag) && typeof value.src === 'string') element.setAttribute('src', value.src);
        if (tag === 'img' && typeof value.alt === 'string') element.setAttribute('alt', value.alt);
      });
    }).catch(() => {});
  }
  const cinematic = qs('[data-cinematic]');
  if (cinematic && !reducedMotion) {
    const scenes = qsa('[data-cine-scene]', cinematic);
    const buttons = qsa('[data-cine-go]', cinematic);
    const progressBar = qs('[data-cine-progress]', cinematic);
    const count = qs('[data-cine-count]', cinematic);
    let activeScene = 0;
    let scheduled = false;

    const canvasPlayers = qsa('[data-cine-canvas]', cinematic).map((canvas) => {
      const context = canvas.getContext('2d', { alpha: false });
      const video = document.createElement('video');
      video.src = canvas.dataset.video || '';
      video.muted = true;
      video.playsInline = true;
      video.preload = 'metadata';
      canvas.style.backgroundImage = `url("${String(canvas.dataset.poster || '').replace(/"/g, '')}")`;
      const resize = () => {
        const box = canvas.getBoundingClientRect();
        const scale = Math.min(devicePixelRatio || 1, 1.6);
        canvas.width = Math.max(2, Math.round(box.width * scale));
        canvas.height = Math.max(2, Math.round(box.height * scale));
      };
      const draw = () => {
        if (video.readyState < 2 || !context) return;
        const cw = canvas.width;
        const ch = canvas.height;
        const videoRatio = video.videoWidth / video.videoHeight;
        const canvasRatio = cw / ch;
        let width = cw;
        let height = ch;
        let x = 0;
        let y = 0;
        if (videoRatio > canvasRatio) {
          width = ch * videoRatio;
          x = (cw - width) / 2;
        } else {
          height = cw / videoRatio;
          y = (ch - height) / 2;
        }
        context.drawImage(video, x, y, width, height);
      };
      video.addEventListener('loadeddata', draw);
      video.addEventListener('seeked', draw);
      resize();
      addEventListener('resize', () => { resize(); draw(); }, { passive: true });
      return { video, draw };
    });

    const setProgress = (forcedScene = null) => {
      const rect = cinematic.getBoundingClientRect();
      const distance = Math.max(1, cinematic.offsetHeight - innerHeight);
      let progress = clamp(-rect.top / distance);
      if (forcedScene !== null) {
        progress = forcedScene / Math.max(1, scenes.length - 1);
        scrollTo({ top: cinematic.offsetTop + progress * distance, behavior: 'smooth' });
      }
      const scaled = progress * scenes.length;
      const index = clamp(Math.floor(scaled), 0, scenes.length - 1);
      const local = index === scenes.length - 1 ? 1 : scaled - index;
      if (activeScene !== index) activeScene = index;
      scenes.forEach((scene, sceneIndex) => scene.classList.toggle('is-active', sceneIndex === index));
      buttons.forEach((button, buttonIndex) => button.classList.toggle('is-active', buttonIndex === index));
      if (progressBar) progressBar.style.width = `${progress * 100}%`;
      if (count) count.textContent = String(index + 1).padStart(2, '0');
      const player = canvasPlayers[index];
      if (player?.video.duration && Number.isFinite(player.video.duration)) {
        const target = clamp(local, 0.02, 0.98) * player.video.duration;
        if (Math.abs(player.video.currentTime - target) > 0.035) player.video.currentTime = target;
      }
      scheduled = false;
    };
    const schedule = () => {
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(() => setProgress());
    };
    addEventListener('scroll', schedule, { passive: true });
    addEventListener('resize', schedule, { passive: true });
    buttons.forEach((button) => button.addEventListener('click', () => setProgress(Number(button.dataset.cineGo))));
    setProgress();
  }

  const revealTargets = qsa('.landing-inner-page main .inner-hero .section-shell > *, .landing-inner-page main .page-hero .v2-shell > *, .landing-inner-page main .content-grid > *, .landing-inner-page main .section-heading, .landing-inner-page main .commitment-grid > *, .landing-inner-page main .pv-grid > *, .landing-inner-page main .sop-grid > *, .landing-inner-page main .partner-list-large > *, .landing-inner-page main .services-grid > *, .landing-inner-page main .metrics-grid > *, .landing-inner-page main .highlight-grid > *, .landing-inner-page main .leadership-grid > *, .landing-inner-page main .blog-grid > *, .landing-inner-page main .messaging-shell > *');
  revealTargets.forEach((element, index) => {
    if (!element.hasAttribute('data-reveal')) element.dataset.reveal = index % 3 === 1 ? 'side' : 'up';
    element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 70}ms`);
  });
  const motionMedia = qsa('.landing-inner-page .feature-image, .landing-inner-page .certificate-wrap, .landing-inner-page .leader-photo, .landing-inner-page .partnership-photo');
  motionMedia.forEach((element) => element.classList.add('media-motion'));
  if (motionMedia.length && !reducedMotion) {
    let mediaScheduled = false;
    const updateMedia = () => {
      motionMedia.forEach((element) => {
        const rect = element.getBoundingClientRect();
        const progress = clamp((innerHeight - rect.top) / Math.max(1, innerHeight + rect.height));
        element.style.setProperty('--media-shift', `${(progress - 0.5) * 28}px`);
      });
      mediaScheduled = false;
    };
    const scheduleMedia = () => {
      if (mediaScheduled) return;
      mediaScheduled = true;
      requestAnimationFrame(updateMedia);
    };
    addEventListener('scroll', scheduleMedia, { passive: true });
    addEventListener('resize', scheduleMedia, { passive: true });
    updateMedia();
  }

  if ('IntersectionObserver' in window && !reducedMotion) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    }), { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });
    qsa('[data-reveal]').forEach((element) => observer.observe(element));
  } else {
    qsa('[data-reveal]').forEach((element) => element.classList.add('is-visible'));
  }

  qsa('[data-flow-board]').forEach((board) => {
    const nodes = qsa('[data-node]', board);
    const lines = qsa('line[data-from][data-to]', board);
    const drawLines = () => {
      const boardBox = board.getBoundingClientRect();
      lines.forEach((line) => {
        const from = qs(`[data-node="${CSS.escape(line.dataset.from || '')}"]`, board);
        const to = qs(`[data-node="${CSS.escape(line.dataset.to || '')}"]`, board);
        if (!from || !to) return;
        const a = from.getBoundingClientRect();
        const b = to.getBoundingClientRect();
        line.setAttribute('x1', String(a.left - boardBox.left + a.width / 2));
        line.setAttribute('y1', String(a.top - boardBox.top + a.height / 2));
        line.setAttribute('x2', String(b.left - boardBox.left + b.width / 2));
        line.setAttribute('y2', String(b.top - boardBox.top + b.height / 2));
      });
    };
    nodes.forEach((node) => {
      let pointerId = null;
      node.addEventListener('pointerdown', (event) => {
        pointerId = event.pointerId;
        node.setPointerCapture(pointerId);
      });
      node.addEventListener('pointermove', (event) => {
        if (event.pointerId !== pointerId) return;
        const box = board.getBoundingClientRect();
        node.style.setProperty('--x', `${clamp((event.clientX - box.left) / box.width) * 100}%`);
        node.style.setProperty('--y', `${clamp((event.clientY - box.top) / box.height) * 100}%`);
        drawLines();
      });
      node.addEventListener('pointerup', () => { pointerId = null; });
      node.addEventListener('pointercancel', () => { pointerId = null; });
    });
    drawLines();
    addEventListener('resize', drawLines, { passive: true });
  });

  const url = new URL(location.href);
  if (url.searchParams.get('hyt_edit') === '1' && window.parent !== window) {
    document.body.classList.add('hyt-edit-mode');
    qsa('[data-edit-key]').forEach((element) => element.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      qsa('[data-edit-key].hyt-selected').forEach((selected) => selected.classList.remove('hyt-selected'));
      element.classList.add('hyt-selected');
      const style = getComputedStyle(element);
      const tag = element.tagName.toLowerCase();
      const textEditable = canEditText(element);
      const label = (textEditable ? element.textContent : '')?.trim()
        || element.getAttribute('alt')
        || element.getAttribute('aria-label')
        || element.id
        || element.className?.toString().split(' ').filter(Boolean).slice(0, 2).join('.')
        || tag;
      parent.postMessage({
        type: 'hayat-element-selected',
        key: element.dataset.editKey,
        tag,
        label: String(label).slice(0, 90),
        canEditText: textEditable,
        text: textEditable ? element.textContent.trim() : '',
        href: tag === 'a' ? (element.getAttribute('href') || '') : '',
        src: ['img', 'video', 'iframe'].includes(tag) ? (element.getAttribute('src') || '') : '',
        alt: tag === 'img' ? (element.getAttribute('alt') || '') : '',
        styles: {
          color: style.color,
          backgroundColor: style.backgroundColor,
          fontSize: style.fontSize,
          fontWeight: style.fontWeight,
          lineHeight: style.lineHeight,
          width: style.width,
          maxWidth: style.maxWidth,
          height: style.height,
          padding: style.padding,
          marginTop: style.marginTop,
          marginBottom: style.marginBottom,
          textAlign: style.textAlign,
          borderRadius: style.borderRadius,
          borderWidth: style.borderWidth,
          borderColor: style.borderColor,
          opacity: style.opacity,
          objectFit: style.objectFit,
          display: style.display
        }
      }, location.origin);
    }));
  }
})();

/* V3 (2026-09-28) — restrained motion: numbers count up once when seen, and a thin reading-progress line. Text stays identical at the end. */
(() => {
  'use strict';
  if (new URLSearchParams(location.search).has('hyt_edit') || window.self !== window.top) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const bar = document.createElement('div');
  bar.setAttribute('aria-hidden', 'true');
  Object.assign(bar.style, { position: 'fixed', insetInlineStart: '0', top: '0', height: '2px', width: '100%', zIndex: '9999', pointerEvents: 'none', transformOrigin: document.dir === 'rtl' || document.documentElement.dir === 'rtl' ? 'right' : 'left', transform: 'scaleX(0)', background: 'linear-gradient(90deg,#0f6b48,#5fd59a)', opacity: '.85' });
  document.body.appendChild(bar);
  let ticking = false;
  const paint = () => {
    const max = document.documentElement.scrollHeight - innerHeight;
    bar.style.transform = `scaleX(${max > 0 ? Math.min(1, scrollY / max) : 0})`;
    ticking = false;
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(paint); } }, { passive: true });
  paint();

  if (reduce || !('IntersectionObserver' in window)) return;
  const nodes = [...document.querySelectorAll('.stats .stat strong, .home-kpis strong, .metrics-band .metric strong')];
  const items = nodes.map((node) => {
    const textNode = [...node.childNodes].find((child) => child.nodeType === 3 && child.textContent.trim() !== '');
    const match = textNode && textNode.textContent.match(/^(\s*[+]?)([\d,]+)(.*)$/s);
    if (!match) return null;
    const target = Number(match[2].replace(/,/g, ''));
    if (!Number.isFinite(target) || target < 2) return null;
    return { node, textNode, prefix: match[1], suffix: match[3], target, commas: match[2].includes(','), original: textNode.textContent };
  }).filter(Boolean);
  const format = (value, commas) => commas ? value.toLocaleString('en-US') : String(value);
  const run = (item) => {
    const duration = 1400;
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      item.textNode.textContent = progress < 1 ? item.prefix + format(Math.round(item.target * eased), item.commas) + item.suffix : item.original;
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    observer.unobserve(entry.target);
    const item = items.find((candidate) => candidate.node === entry.target);
    if (item) run(item);
  }), { threshold: 0.6 });
  items.forEach((item) => observer.observe(item.node));
})();
