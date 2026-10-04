/* إدارة أقسام الموقع + الأقسام المخصّصة */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var CS = window.CUSTOM_SECTIONS || {};
  var list = $('#secList');

  /* ===== الترتيب ===== */
  var dragEl = null;
  $$('.sec-row', list).forEach(function (row) {
    row.setAttribute('draggable', 'true');
    row.addEventListener('dragstart', function () { dragEl = row; row.classList.add('dragging'); });
    row.addEventListener('dragend', function () { row.classList.remove('dragging'); });
    row.addEventListener('dragover', function (e) {
      e.preventDefault();
      var after = $$('.sec-row:not(.dragging)', list).find(function (r) {
        var box = r.getBoundingClientRect(); return e.clientY < box.top + box.height / 2;
      });
      if (after) list.insertBefore(dragEl, after); else list.appendChild(dragEl);
    });
  });
  $('#saveSecs').addEventListener('click', function () {
    var order = $$('.sec-row', list).map(function (r) {
      return { key: r.getAttribute('data-key'), on: r.querySelector('.sec-on').checked ? 1 : 0 };
    });
    window.apost('sections.php', { act: 'save', order: JSON.stringify(order) }, function (j) {
      window.toast(j.ok ? 'تم الحفظ ✓ — حدّث الموقع لرؤية الترتيب' : 'خطأ', j.ok ? 0 : 1);
    });
  });

  /* ===== الأقسام المخصّصة ===== */
  var modal = $('#csModal'), form = $('#csForm'), typeSel = $('#csType');
  var carImgs = [];

  function applyType(t) {
    $$('.cs-type').forEach(function (el) { el.hidden = !el.classList.contains('cs-type-' + t); });
  }
  typeSel.addEventListener('change', function () { applyType(typeSel.value); });

  function renderCarImgs() {
    var box = $('#csImgs');
    box.innerHTML = carImgs.map(function (im, i) {
      return '<div class="cs-img"><img src="../uploads/' + im.src + '"><button type="button" data-rm="' + i + '">✕</button></div>';
    }).join('');
    $$('[data-rm]', box).forEach(function (b) {
      b.addEventListener('click', function () { carImgs.splice(+b.getAttribute('data-rm'), 1); renderCarImgs(); });
    });
  }

  function openCs(data) {
    form.reset();
    carImgs = [];
    form.id.value = data ? data.id : 0;
    typeSel.value = data ? data.type : 'text';
    form.title_ar.value = data ? data.title_ar : '';
    form.title_en.value = data ? data.title_en : '';
    form.body_ar.value = data ? (data.body_ar || '') : '';
    form.body_en.value = data ? (data.body_en || '') : '';
    form.raw_code.value = data ? (data.raw_code || '') : '';
    form.bg.value = data ? data.bg : 'light';
    form.active.checked = data ? !!data.active : true;
    // بيانات
    var d = {};
    try { d = data && data.data ? JSON.parse(data.data) : {}; } catch (e) { d = {}; }
    form.cs_height.value = (d && d.height) ? d.height : 560;
    if (data && data.type === 'carousel') { carImgs = (d.images || []); }
    renderCarImgs();
    applyType(typeSel.value);
    $('#csTitle').textContent = data ? 'تعديل القسم' : 'قسم مخصّص جديد';
    modal.hidden = false;
  }

  $('#newCs').addEventListener('click', function () { openCs(null); });
  $$('[data-cs-edit]').forEach(function (b) {
    b.addEventListener('click', function () { openCs(CS[b.getAttribute('data-cs-edit')]); });
  });
  $$('[data-cs-del]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('حذف هذا القسم نهائياً؟')) return;
      window.apost('sections.php', { act: 'cs_del', id: b.getAttribute('data-cs-del') }, function (j) {
        if (j.ok) location.reload();
      });
    });
  });
  modal.addEventListener('click', function (e) { if (e.target === modal || e.target.hasAttribute('data-close')) modal.hidden = true; });

  /* رفع صور الكاروسيل */
  var imgFile = $('#csImgFile');
  $('#csAddImg').addEventListener('click', function () { imgFile.click(); });
  imgFile.addEventListener('change', function () {
    if (!imgFile.files.length) return;
    var fd = new FormData();
    fd.append('file', imgFile.files[0]);
    fd.append('dir', 'pages');
    window.apost('upload.php', fd, function (j) {
      if (!j.ok) { window.toast(j.msg || 'فشل الرفع', 1); return; }
      carImgs.push({ src: j.path, cap: '' });
      renderCarImgs();
    });
    imgFile.value = '';
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(form);
    fd.append('act', 'cs_save');
    // بناء حقل data حسب النوع
    var dataObj = {};
    if (typeSel.value === 'html') dataObj = { height: +form.cs_height.value || 560 };
    if (typeSel.value === 'carousel') dataObj = { images: carImgs };
    fd.set('data', JSON.stringify(dataObj));
    window.apost('sections.php', fd, function (j) {
      if (j.ok) { window.toast('تم الحفظ ✓'); setTimeout(function () { location.reload(); }, 800); }
      else window.toast(j.msg || 'خطأ', 1);
    });
  });
})();
