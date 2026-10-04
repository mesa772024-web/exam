/* مصمّم الباج — معاينة حية + سحب + حفظ */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var tpl = window.BADGE;
  var sample = window.SAMPLE;
  var FIELD_OPTS = window.FIELD_OPTS;
  var card = $('#badgeCard');
  var stage = $('#stage');
  var elList = $('#elList');
  var PXMM = 1; // px per mm in preview (computed)

  function sampleVal(el) {
    var f = el.field;
    if (f === 'custom') return el.text || 'نص';
    if (f === 'site') return 'منتدى الاقتصاد الرقمي العراقي';
    if (f === 'edition') return 'النسخة الثانية';
    if (f === 'dates') return '1 – 3 أيلول 2026';
    if (f === 'gender') return 'ذكر';
    if (f === 'phone') return '+' + (sample.phone || '');
    return sample[f] != null ? String(sample[f]) : (FIELD_OPTS[f] || f);
  }

  function layout() {
    // fit card into stage
    var maxW = stage.clientWidth - 40;
    var maxH = stage.clientHeight - 40;
    var pxmmW = maxW / tpl.w, pxmmH = maxH / tpl.h;
    PXMM = Math.max(2, Math.min(pxmmW, pxmmH));
    card.style.width = (tpl.w * PXMM) + 'px';
    card.style.height = (tpl.h * PXMM) + 'px';
    card.style.background = tpl.bg ? ('center/cover no-repeat url(../uploads/' + tpl.bg + ')') : tpl.bg_color;
  }

  function renderCard() {
    layout();
    card.innerHTML = '';
    (tpl.elements || []).forEach(function (el, i) {
      var node = document.createElement('div');
      node.className = 'b-el';
      node.setAttribute('data-i', i);
      node.style.left = el.x + '%';
      node.style.top = el.y + '%';
      node.style.color = el.color;
      node.style.textAlign = el.align;
      node.style.fontWeight = el.bold ? '800' : '500';
      if (el.type === 'qr') {
        var box = document.createElement('div');
        node.appendChild(box);
        var px = Math.round(el.size * PXMM);
        try {
          new QRCode(box, { text: sample.code || 'IDEF26', width: px, height: px, colorDark: el.color, colorLight: '#ffffff00', correctLevel: QRCode.CorrectLevel.M });
        } catch (e) {}
        node.style.width = px + 'px';
      } else {
        node.style.fontSize = (el.size * PXMM) + 'px';
        node.style.whiteSpace = 'nowrap';
        node.textContent = sampleVal(el);
      }
      makeDraggable(node, i);
      card.appendChild(node);
    });
  }

  function makeDraggable(node, i) {
    node.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      node.setPointerCapture(e.pointerId);
      node.classList.add('drag');
      selectEl(i);
      function move(ev) {
        var r = card.getBoundingClientRect();
        var x = ((ev.clientX - r.left) / r.width) * 100;
        var y = ((ev.clientY - r.top) / r.height) * 100;
        tpl.elements[i].x = Math.max(0, Math.min(100, Math.round(x * 10) / 10));
        tpl.elements[i].y = Math.max(0, Math.min(100, Math.round(y * 10) / 10));
        node.style.left = tpl.elements[i].x + '%';
        node.style.top = tpl.elements[i].y + '%';
        syncRow(i);
      }
      function up(ev) {
        node.classList.remove('drag');
        node.releasePointerCapture(e.pointerId);
        document.removeEventListener('pointermove', move);
        document.removeEventListener('pointerup', up);
      }
      document.addEventListener('pointermove', move);
      document.addEventListener('pointerup', up);
    });
  }

  var selected = -1;
  function selectEl(i) {
    selected = i;
    $$('.el-row').forEach(function (r) { r.classList.toggle('sel', +r.getAttribute('data-i') === i); });
    $$('.b-el').forEach(function (n) { n.classList.toggle('sel', +n.getAttribute('data-i') === i); });
  }

  function fieldSelect(el, i) {
    var opts = Object.keys(FIELD_OPTS).map(function (k) {
      return '<option value="' + k + '"' + (el.field === k ? ' selected' : '') + '>' + FIELD_OPTS[k] + '</option>';
    }).join('');
    return '<select data-k="field" data-i="' + i + '">' + opts + '</select>';
  }

  function renderList() {
    elList.innerHTML = '';
    (tpl.elements || []).forEach(function (el, i) {
      var row = document.createElement('div');
      row.className = 'el-row';
      row.setAttribute('data-i', i);
      row.innerHTML =
        '<div class="el-row-top">' +
          '<select data-k="type" data-i="' + i + '"><option value="text"' + (el.type === 'text' ? ' selected' : '') + '>نص</option><option value="qr"' + (el.type === 'qr' ? ' selected' : '') + '>باركود QR</option></select>' +
          fieldSelect(el, i) +
          '<button class="mini-del" data-del="' + i + '" title="حذف">✕</button>' +
        '</div>' +
        (el.field === 'custom' ? '<input data-k="text" data-i="' + i + '" placeholder="النص الثابt" value="' + (el.text || '').replace(/"/g, '&quot;') + '">' : '') +
        '<div class="el-row-grid">' +
          '<label>الحجم<input type="number" step="0.5" data-k="size" data-i="' + i + '" value="' + el.size + '"></label>' +
          '<label>اللون<input type="color" data-k="color" data-i="' + i + '" value="' + el.color + '"></label>' +
          '<label>محاذاة<select data-k="align" data-i="' + i + '"><option value="center"' + (el.align === 'center' ? ' selected' : '') + '>وسط</option><option value="start"' + (el.align === 'start' ? ' selected' : '') + '>بداية</option><option value="end"' + (el.align === 'end' ? ' selected' : '') + '>نهاية</option></select></label>' +
          '<label class="el-bold"><input type="checkbox" data-k="bold" data-i="' + i + '"' + (el.bold ? ' checked' : '') + '> عريض</label>' +
        '</div>';
      row.addEventListener('click', function () { selectEl(i); });
      elList.appendChild(row);
    });
  }

  function syncRow(i) {
    var row = elList.querySelector('.el-row[data-i="' + i + '"]');
    if (!row) return;
  }

  elList.addEventListener('input', function (e) {
    var t = e.target;
    var i = +t.getAttribute('data-i');
    var k = t.getAttribute('data-k');
    if (isNaN(i) || !k) return;
    var el = tpl.elements[i];
    if (k === 'bold') el.bold = t.checked ? 1 : 0;
    else if (k === 'size') el.size = parseFloat(t.value) || 6;
    else el[k] = t.value;
    if (k === 'type' || k === 'field') renderList();
    renderCard();
  });
  elList.addEventListener('click', function (e) {
    var del = e.target.closest('[data-del]');
    if (del) {
      tpl.elements.splice(+del.getAttribute('data-del'), 1);
      renderList(); renderCard();
    }
  });

  $('#addEl').addEventListener('click', function () {
    tpl.elements.push({ type: 'text', field: 'full_name', text: '', x: 50, y: 40, size: 6, color: '#0b0e26', align: 'center', bold: 1 });
    renderList(); renderCard();
  });

  // dimensions & colors
  $('#bW').addEventListener('input', function () { tpl.w = +this.value || 90; renderCard(); });
  $('#bH').addEventListener('input', function () { tpl.h = +this.value || 130; renderCard(); });
  $('#bBgColor').addEventListener('input', function () { tpl.bg_color = this.value; renderCard(); });
  $('#bAccent').addEventListener('input', function () { tpl.accent = this.value; });

  // background upload
  var bgFile = $('#bgFile');
  $('#bgDrop').addEventListener('click', function () { bgFile.click(); });
  bgFile.addEventListener('change', function () {
    if (!bgFile.files.length) return;
    var fd = new FormData(); fd.append('file', bgFile.files[0]); fd.append('dir', 'badges');
    window.apost('upload.php', fd, function (j) {
      if (!j.ok) { window.toast(j.msg || 'فشل الرفع', 1); return; }
      tpl.bg = j.path; $('#bBg').value = j.path;
      var p = $('#bgPrev'); p.src = j.url; p.hidden = false; $('#bgHint').hidden = true;
      renderCard();
    });
    bgFile.value = '';
  });
  if ($('#bgClear')) $('#bgClear').addEventListener('click', function () { tpl.bg = ''; $('#bgPrev').hidden = true; $('#bgHint').hidden = false; renderCard(); });

  // barcode mode
  $$('input[name="bcmode"]').forEach(function (r) {
    r.addEventListener('change', function () { $('#bcFields').hidden = this.value !== 'info'; });
  });

  // save
  $('#saveBadge').addEventListener('click', function () {
    var bcFields = $$('.bcf:checked').map(function (c) { return c.value; });
    window.apost('badges.php', {
      act: 'save',
      template: JSON.stringify(tpl),
      barcode_mode: ($('input[name="bcmode"]:checked') || {}).value || 'code',
      barcode_fields: JSON.stringify(bcFields)
    }, function (j) { window.toast(j.ok ? 'تم حفظ التصميم ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1); });
  });

  // تحميل قالب جاهز
  $$('.preset-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      var key = b.getAttribute('data-preset');
      var p = (window.PRESETS || {})[key];
      if (!p) return;
      if (!confirm('تحميل هذا القالب سيستبدل التصميم الحالي. متابعة؟')) return;
      tpl = JSON.parse(JSON.stringify(p));
      $('#bW').value = tpl.w; $('#bH').value = tpl.h;
      $('#bBgColor').value = tpl.bg_color; $('#bAccent').value = tpl.accent;
      tpl.bg = ''; if ($('#bBg')) $('#bBg').value = '';
      if ($('#bgPrev')) { $('#bgPrev').hidden = true; }
      if ($('#bgHint')) { $('#bgHint').hidden = false; }
      renderList(); renderCard();
      window.toast('حُمّل القالب — اضغط حفظ للاعتماد');
    });
  });

  window.addEventListener('resize', renderCard);
  renderList();
  renderCard();
})();
