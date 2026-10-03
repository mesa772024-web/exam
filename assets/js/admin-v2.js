(() => {
  'use strict';
  const menu = document.querySelector('[data-admin-menu]');
  const side = document.querySelector('.admin-side');
  const sideOverlay = document.querySelector('[data-admin-overlay]');
  const setAdminMenu = (open) => {
    side?.classList.toggle('open', open);
    sideOverlay?.classList.toggle('open', open);
    document.body.classList.toggle('admin-menu-open', open);
    menu?.setAttribute('aria-expanded', String(open));
  };
  menu?.addEventListener('click', () => setAdminMenu(!side?.classList.contains('open')));
  sideOverlay?.addEventListener('click', () => setAdminMenu(false));
  side?.querySelectorAll('nav a').forEach((link) => link.addEventListener('click', () => setAdminMenu(false)));
  addEventListener('keydown', (event) => { if (event.key === 'Escape') setAdminMenu(false); });
  addEventListener('resize', () => { if (innerWidth > 980) setAdminMenu(false); }, { passive: true });
  // V3 polish — sticky top-bar shadow, gentle number count-up, chart stagger, mobile table labels.
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const top = document.querySelector('.admin-top');
  const syncTop = () => top?.classList.toggle('is-scrolled', scrollY > 8);
  addEventListener('scroll', syncTop, { passive: true });
  syncTop();
  document.querySelectorAll('.chart').forEach((chart) => {
    chart.querySelectorAll(':scope > div > i').forEach((bar, index) => bar.style.setProperty('--i', index));
  });
  document.querySelectorAll('.metric-card strong, .status-tile b').forEach((node) => {
    const target = Number.parseInt(node.textContent.trim(), 10);
    if (reduceMotion || !Number.isFinite(target) || String(target) !== node.textContent.trim() || target < 1) return;
    const duration = Math.min(1100, 500 + target * 20);
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      node.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
      if (progress < 1) requestAnimationFrame(step);
    };
    node.textContent = '0';
    requestAnimationFrame(step);
  });
  document.querySelectorAll('.admin-table').forEach((table) => {
    const labels = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach((row) => {
      [...row.children].forEach((cell, index) => { if (labels[index] && !cell.hasAttribute('data-label')) cell.dataset.label = labels[index]; });
    });
  });
  document.querySelectorAll('[data-confirm]').forEach((element) => element.addEventListener('click', (event) => {
    if (!confirm(element.dataset.confirm || 'هل أنت متأكد؟')) event.preventDefault();
  }));

  const blockType = document.querySelector('[data-block-type]');
  const syncBlockFields = () => {
    const type = blockType?.value || '';
    document.querySelectorAll('[data-for-types]').forEach((field) => {
      const visible = field.dataset.forTypes.split(',').includes(type);
      field.hidden = !visible;
      field.querySelectorAll('input,textarea').forEach((input) => { input.disabled = !visible; });
    });
  };
  blockType?.addEventListener('change', syncBlockFields);
  syncBlockFields();

  const iframe = document.querySelector('[data-visual-frame]');
  const visualForm = document.querySelector('[data-visual-form]');
  const empty = document.querySelector('[data-visual-empty]');
  const rgbToHex = (value, fallback = '#231018') => {
    const parts = String(value || '').match(/[\d.]+/g);
    if (!parts || parts.length < 3) return fallback;
    return `#${parts.slice(0, 3).map((part) => Math.max(0, Math.min(255, Math.round(Number(part)))).toString(16).padStart(2, '0')).join('')}`;
  };
  const hasOpaqueColor = (value) => {
    const parts = String(value || '').match(/[\d.]+/g);
    return Boolean(parts && parts.length >= 3 && (parts.length < 4 || Number(parts[3]) > 0));
  };
  const numberFromCss = (value, fallback = '') => {
    const parsed = Number.parseFloat(String(value || ''));
    return Number.isFinite(parsed) ? parsed : fallback;
  };
  const toggleVisualField = (selector, visible) => {
    const field = visualForm?.querySelector(selector);
    if (!field) return;
    field.hidden = !visible;
    field.querySelectorAll('input,textarea,select').forEach((control) => { control.disabled = !visible; });
  };
  addEventListener('message', (event) => {
    if (event.origin !== location.origin || event.data?.type !== 'hayat-element-selected' || !visualForm) return;
    visualForm.hidden = false;
    if (empty) empty.hidden = true;
    const tag = String(event.data.tag || 'div').toLowerCase();
    const styles = event.data.styles || {};
    visualForm.elements.element_key.value = event.data.key || '';
    visualForm.elements.element_tag.value = tag;
    visualForm.elements.content_value.value = event.data.text || '';
    visualForm.elements.link_url.value = event.data.href || '';
    visualForm.elements.media_url.value = event.data.src || '';
    visualForm.elements.alt_text.value = event.data.alt || '';
    const selection = visualForm.querySelector('[data-visual-selection]');
    if (selection) selection.textContent = `العنصر المحدد: <${tag}> — ${event.data.label || event.data.key || ''}`;
    toggleVisualField('[data-visual-text-field]', Boolean(event.data.canEditText));
    toggleVisualField('[data-visual-link-field]', tag === 'a');
    toggleVisualField('[data-visual-media-field]', ['img', 'video', 'iframe'].includes(tag));
    toggleVisualField('[data-visual-alt-field]', tag === 'img');
    visualForm.elements.color.value = rgbToHex(styles.color);
    visualForm.elements.background_color.value = rgbToHex(styles.backgroundColor, '#ffffff');
    visualForm.elements.use_background.checked = hasOpaqueColor(styles.backgroundColor);
    visualForm.elements.font_size.value = Math.max(8, Math.min(160, Math.round(numberFromCss(styles.fontSize, 16))));
    const fontWeight = Number.parseInt(styles.fontWeight, 10);
    visualForm.elements.font_weight.value = ['300', '400', '500', '600', '700', '800'].includes(String(fontWeight)) ? String(fontWeight) : '400';
    const lineHeight = numberFromCss(styles.lineHeight, 1.5);
    visualForm.elements.line_height.value = lineHeight > 3 ? Math.max(0.8, Math.min(3, lineHeight / Math.max(8, numberFromCss(styles.fontSize, 16)))).toFixed(1) : Math.max(0.8, Math.min(3, lineHeight)).toFixed(1);
    visualForm.elements.text_align.value = ['left', 'right', 'center', 'start', 'end'].includes(styles.textAlign) ? styles.textAlign : 'start';
    ['width', 'max_width', 'height', 'padding', 'margin_top', 'margin_bottom', 'border_radius', 'border_width', 'opacity', 'translate_x', 'translate_y'].forEach((name) => { visualForm.elements[name].value = ''; });
    visualForm.elements.width.placeholder = `الحالي: ${styles.width || 'auto'}`;
    visualForm.elements.max_width.placeholder = `الحالي: ${styles.maxWidth || 'none'}`;
    visualForm.elements.height.placeholder = `الحالي: ${styles.height || 'auto'}`;
    visualForm.elements.padding.placeholder = `الحالي: ${styles.padding || '0px'}`;
    visualForm.elements.margin_top.placeholder = `الحالي: ${styles.marginTop || '0px'}`;
    visualForm.elements.margin_bottom.placeholder = `الحالي: ${styles.marginBottom || '0px'}`;
    visualForm.elements.border_radius.placeholder = `الحالي: ${styles.borderRadius || '0px'}`;
    visualForm.elements.border_width.placeholder = `الحالي: ${styles.borderWidth || '0px'}`;
    visualForm.elements.opacity.placeholder = `الحالي: ${styles.opacity || '1'}`;
    visualForm.elements.border_color.value = rgbToHex(styles.borderColor, '#e7dce1');
    visualForm.elements.object_fit.value = ['cover', 'contain', 'fill', 'scale-down'].includes(styles.objectFit) && ['img', 'video'].includes(tag) ? styles.objectFit : '';
    visualForm.elements.display.value = '';
  });
  visualForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const status = visualForm.querySelector('[data-visual-status]');
    const submitButton = visualForm.querySelector('button[type="submit"]');
    try {
      if (submitButton) submitButton.disabled = true;
      if (status) status.textContent = 'جارٍ الحفظ…';
      const endpoint = visualForm.getAttribute('action') || location.href;
      const response = await fetch(endpoint, {
        method: 'POST',
        body: new FormData(visualForm),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      const raw = await response.text();
      let result;
      try {
        result = JSON.parse(raw);
      } catch {
        const sessionEnded = response.redirected || /<!doctype|<html/i.test(raw);
        throw new Error(sessionEnded ? 'انتهت جلسة الإدارة. سجّل الدخول مجدداً ثم أعد المحاولة.' : 'تعذر قراءة استجابة الحفظ. حدّث الصفحة وحاول مجدداً.');
      }
      if (!response.ok || !result.ok) throw new Error(result.message || 'تعذر الحفظ');
      if (status) status.textContent = result.message;
      iframe?.contentWindow.location.reload();
    } catch (error) {
      if (status) status.textContent = error.message;
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
})();
