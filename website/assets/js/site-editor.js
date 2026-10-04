/* محرر الواجهة المباشر — يعمل للمدير الكامل في وضع التحرير فقط. */
(function () {
  'use strict';
  var CFG = window.SCF_EDIT || {};
  var all = function (q, s) { return Array.prototype.slice.call((s || document).querySelectorAll(q)); };
  var changes = {};          // key -> value
  var originals = {};        // key -> original html
  var active = null;
  var countEl = document.getElementById('seCount'), saveBtn = document.getElementById('seSave'), undoBtn = document.getElementById('seUndo');

  function toast(msg, bad) {
    var t = document.getElementById('seToast');
    if (!t) { t = document.createElement('div'); t.id = 'seToast'; t.className = 'se-toast'; document.body.appendChild(t); }
    t.textContent = msg; t.className = 'se-toast show' + (bad ? ' bad' : '');
    clearTimeout(t._h); t._h = setTimeout(function () { t.className = 'se-toast'; }, 2600);
  }
  function post(url, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('_csrf', CFG.csrf);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'استجابة غير متوقعة (' + r.status + ')' }; }); });
  }
  function refreshBar() {
    var n = Object.keys(changes).length;
    countEl.textContent = n ? n + (n === 1 ? ' تعديل غير محفوظ' : ' تعديلات غير محفوظة') : 'لا تغييرات';
    countEl.classList.toggle('dirty', n > 0);
    saveBtn.disabled = undoBtn.disabled = n === 0;
  }
  function valueOf(el) {
    if (el.dataset.rich) return el.innerHTML;
    return el.innerText.replace(/ /g, ' ');
  }

  /* ---------- النصوص ---------- */
  var spans = all('.tk');
  spans.forEach(function (el) {
    var key = el.dataset.tk;
    if (!(key in originals)) originals[key] = el.innerHTML;
    el.setAttribute('title', 'اضغط للتعديل');
  });
  function sameKey(key) { return all('.tk[data-tk="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]'); }

  function begin(el) {
    if (active === el) return;
    finish();
    active = el;
    el.contentEditable = 'true';
    el.spellcheck = false;
    el.classList.add('is-editing');
    el.focus();
    var sel = window.getSelection(), range = document.createRange();
    range.selectNodeContents(el); range.collapse(false); sel.removeAllRanges(); sel.addRange(range);
  }
  function finish() {
    if (!active) return;
    var el = active, key = el.dataset.tk;
    active = null;
    el.contentEditable = 'false';
    el.classList.remove('is-editing');
    if (el.innerHTML !== originals[key]) {
      changes[key] = valueOf(el);
      el.classList.add('is-changed');
      sameKey(key).forEach(function (o) { if (o !== el) { o.innerHTML = el.innerHTML; o.classList.add('is-changed'); } });
    } else {
      delete changes[key];
      sameKey(key).forEach(function (o) { o.classList.remove('is-changed'); });
    }
    refreshBar();
  }
  function revert(el) {
    var key = el.dataset.tk;
    sameKey(key).forEach(function (o) { o.innerHTML = originals[key]; o.classList.remove('is-changed'); });
    delete changes[key];
    active = null; el.contentEditable = 'false'; el.classList.remove('is-editing');
    refreshBar();
  }

  // الضغط على نص: تعديل (ومنع فتح الروابط/الشرائح/النوافذ)
  document.addEventListener('click', function (e) {
    var tk = e.target.closest && e.target.closest('.tk');
    if (tk) {
      e.preventDefault(); e.stopPropagation();
      begin(tk);
      return;
    }
    if (active && !e.target.closest('.se-bar')) finish();
  }, true);
  document.addEventListener('keydown', function (e) {
    if (!active) return;
    if (e.key === 'Escape') { e.preventDefault(); revert(active); return; }
    if (e.key === 'Enter') {
      e.preventDefault();
      if (active.dataset.rich && e.shiftKey) { document.execCommand('insertLineBreak'); return; }
      finish();
    }
  }, true);
  document.addEventListener('paste', function (e) {
    if (!active) return;
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  }, true);
  // لا نسمح بسحب الروابط أثناء التحرير
  document.addEventListener('dragstart', function (e) { if (e.target.closest && e.target.closest('.tk')) e.preventDefault(); }, true);

  saveBtn.addEventListener('click', function () {
    finish();
    var keys = Object.keys(changes);
    if (!keys.length) return;
    saveBtn.disabled = true; saveBtn.classList.add('busy');
    var items = keys.map(function (k) { return { k: k, v: changes[k] }; });
    post(CFG.admin + '/texts.php', { act: 'inline_save', lang: CFG.lang, items: JSON.stringify(items) }).then(function (j) {
      saveBtn.classList.remove('busy');
      if (!j.ok) { toast(j.msg || 'تعذر الحفظ', true); refreshBar(); return; }
      (j.saved || []).forEach(function (k) {
        sameKey(k).forEach(function (o) { o.classList.remove('is-changed'); o.classList.add('is-saved'); setTimeout(function () { o.classList.remove('is-saved'); }, 1600); });
        originals[k] = sameKey(k)[0] ? sameKey(k)[0].innerHTML : originals[k];
        delete changes[k];
      });
      refreshBar();
      toast(j.failed && j.failed.length ? ('حُفظ ' + j.saved.length + ' وتعذّر ' + j.failed.length) : ('حُفظت التعديلات ✓ (' + j.saved.length + ')'), j.failed && j.failed.length);
    }).catch(function () { saveBtn.classList.remove('busy'); refreshBar(); toast('تعذر الاتصال', true); });
  });
  undoBtn.addEventListener('click', function () {
    finish();
    Object.keys(changes).forEach(function (k) { sameKey(k).forEach(function (o) { o.innerHTML = originals[k]; o.classList.remove('is-changed'); }); });
    changes = {}; refreshBar();
  });
  all('[data-se-nav]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      finish();
      if (Object.keys(changes).length && !confirm('لديك تعديلات غير محفوظة. المتابعة بدون حفظ؟')) e.preventDefault();
    });
  });
  window.addEventListener('beforeunload', function (e) {
    if (Object.keys(changes).length) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- استبدال الصور والفيديو ---------- */
  var slotBtns = document.createElement('div');
  slotBtns.className = 'se-slot'; slotBtns.hidden = true;
  document.body.appendChild(slotBtns);
  var fileIn = document.createElement('input');
  fileIn.type = 'file'; fileIn.hidden = true; document.body.appendChild(fileIn);
  var pendingSlot = null, hoverTarget = null;

  function slotHost(el) { return el.tagName === 'VIDEO' ? (el.closest('.stage-film') || el) : el; }
  function showSlots(el) {
    hoverTarget = el;
    var slots = [el.dataset.slot];
    if (el.tagName === 'VIDEO') slots.push('poster');
    slotBtns.innerHTML = '';
    slots.forEach(function (s) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = s === 'video' ? '🎬 استبدال الفيديو' : (s === 'poster' ? '🖼 استبدال الغلاف' : '🖼 استبدال الصورة');
      b.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        pendingSlot = { key: s, el: el };
        fileIn.accept = s === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp';
        fileIn.value = ''; fileIn.click();
      });
      slotBtns.appendChild(b);
    });
    var r = slotHost(el).getBoundingClientRect();
    slotBtns.style.top = Math.max(8, r.top + 12) + 'px';
    slotBtns.style.left = Math.max(8, r.left + 12) + 'px';
    slotBtns.hidden = false;
  }
  all('[data-slot]').forEach(function (el) {
    slotHost(el).addEventListener('mouseenter', function () { showSlots(el); });
  });
  document.addEventListener('mousemove', function (e) {
    if (slotBtns.hidden || !hoverTarget) return;
    var r = slotHost(hoverTarget).getBoundingClientRect();
    var inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
    if (!inside && !slotBtns.contains(e.target)) slotBtns.hidden = true;
  });
  window.addEventListener('scroll', function () { slotBtns.hidden = true; }, { passive: true });
  fileIn.addEventListener('change', function () {
    if (!fileIn.files.length || !pendingSlot) return;
    var slot = pendingSlot, f = fileIn.files[0];
    toast('جارٍ رفع الملف…');
    var fd = new FormData();
    fd.append('act', 'upload'); fd.append('slot', slot.key); fd.append('file', f); fd.append('_csrf', CFG.csrf);
    fetch(CFG.admin + '/media.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { toast(j.msg || 'تعذر الرفع', true); return; }
        if (slot.key === 'poster') {
          var film = slot.el.closest('.stage-film');
          slot.el.poster = j.url; if (film) film.style.backgroundImage = 'url(' + j.url + ')';
        } else if (slot.el.tagName === 'VIDEO') {
          slot.el.src = j.url; slot.el.load(); slot.el.play().catch(function () {});
        } else {
          all('[data-slot="' + slot.key + '"]').forEach(function (img) { img.removeAttribute('srcset'); img.src = j.url; });
        }
        toast('تم الاستبدال ✓');
      })
      .catch(function () { toast('تعذر الاتصال', true); });
  });

  /* ---------- ترتيب الأقسام ---------- */
  var secBtn = document.getElementById('seSections'), secMode = false, saveTimer = null;
  var secs = function () { return all('.edit-sec'); };
  function saveOrder() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      var order = secs().map(function (s) { return { key: s.dataset.sec, on: s.classList.contains('is-off') ? 0 : 1 }; });
      post(CFG.admin + '/sections.php', { act: 'save', order: JSON.stringify(order) })
        .then(function (j) { toast(j.ok ? 'حُفظ ترتيب الأقسام ✓' : (j.msg || 'تعذر حفظ الترتيب'), !j.ok); })
        .catch(function () { toast('تعذر الاتصال', true); });
    }, 500);
  }
  secs().forEach(function (s) {
    var bar = document.createElement('div');
    bar.className = 'se-secbar';
    bar.innerHTML = '<b>' + (s.dataset.label || s.dataset.sec) + '</b>' +
      '<button type="button" data-a="up" title="تقديم">▲</button><button type="button" data-a="down" title="تأخير">▼</button>' +
      '<button type="button" data-a="toggle"></button>';
    var tg = bar.querySelector('[data-a="toggle"]');
    var sync = function () { tg.textContent = s.classList.contains('is-off') ? 'إظهار' : 'إخفاء'; };
    sync();
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      e.preventDefault(); e.stopPropagation();
      if (b.dataset.a === 'up' && s.previousElementSibling && s.previousElementSibling.classList.contains('edit-sec')) s.parentNode.insertBefore(s, s.previousElementSibling);
      else if (b.dataset.a === 'down' && s.nextElementSibling && s.nextElementSibling.classList.contains('edit-sec')) s.parentNode.insertBefore(s.nextElementSibling, s);
      else if (b.dataset.a === 'toggle') { s.classList.toggle('is-off'); sync(); }
      else return;
      s.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      saveOrder();
    });
    s.insertBefore(bar, s.firstChild);
  });
  secBtn.addEventListener('click', function () {
    secMode = !secMode;
    document.body.classList.toggle('se-sections-on', secMode);
    secBtn.setAttribute('aria-pressed', secMode ? 'true' : 'false');
  });

  /* ---------- مساعدة ---------- */
  var help = document.getElementById('seHelp'), helpBox = document.getElementById('seHelpBox');
  help.addEventListener('click', function () { helpBox.hidden = !helpBox.hidden; });
  try { if (!localStorage.getItem('scf_se_help')) { helpBox.hidden = false; localStorage.setItem('scf_se_help', '1'); } } catch (e) {}

  refreshBar();
})();
