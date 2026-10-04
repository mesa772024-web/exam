/* لوحة التحكم — السلوك العام */
(function () {
  'use strict';

  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Toast ---------- */
  window.toast = function (msg, isErr) {
    var t = $('#toast');
    if (!t) return alert(msg);
    t.textContent = msg;
    t.className = 'toast show' + (isErr ? ' err' : '');
    clearTimeout(t._h);
    t._h = setTimeout(function () { t.className = 'toast'; }, 2600);
  };

  /* ---------- POST helper ---------- */
  window.apost = function (url, data, cb) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) {
      Object.keys(data || {}).forEach(function (k) {
        if (Array.isArray(data[k])) {
          data[k].forEach(function (v) { fd.append(k + '[]', v); });
        } else {
          fd.append(k, data[k]);
        }
      });
    }
    fd.append('_csrf', window.CSRF);
    fetch(url, {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.auth === false) { location.href = 'index.php'; return; }
        cb(j);
      })
      .catch(function () { window.toast('تعذر الاتصال', 1); });
  };

  /* ---------- Sidebar collapse (desktop) ---------- */
  var shell = $('.admin-shell');
  if (shell && localStorage.getItem('scf_side_collapsed') === '1') shell.classList.add('collapsed');
  var sc = $('#sideCollapse');
  if (sc) sc.addEventListener('click', function () {
    var on = shell.classList.toggle('collapsed');
    localStorage.setItem('scf_side_collapsed', on ? '1' : '0');
  });

  /* ---------- Sidebar (mobile) ---------- */
  var st = $('#sideToggle');
  var side = $('#side');
  var sideBackdrop = $('#sideBackdrop');
  var setMobileSide = function (open) {
    if (!side) return;
    side.classList.toggle('open', !!open);
    document.body.classList.toggle('side-open', !!open);
    if (sideBackdrop) sideBackdrop.hidden = !open;
    if (st) st.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  if (st) {
    st.setAttribute('aria-expanded', 'false');
    st.addEventListener('click', function (e) {
      e.stopPropagation();
      setMobileSide(!side.classList.contains('open'));
    });
    if (sideBackdrop) sideBackdrop.addEventListener('click', function () { setMobileSide(false); });
    $$('.side-nav a', side).forEach(function (link) {
      link.addEventListener('click', function () { setMobileSide(false); });
    });
    document.addEventListener('click', function (e) {
      if (side && side.classList.contains('open') && !side.contains(e.target) && !st.contains(e.target)) {
        setMobileSide(false);
      }
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 860) setMobileSide(false); });
  }

  /* ---------- Modals ---------- */
  $$('.modal').forEach(function (m) {
    m.addEventListener('click', function (e) {
      if (e.target === m || e.target.hasAttribute('data-close')) {
        m.hidden = true;
      }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      $$('.modal').forEach(function (m) { m.hidden = true; });
      setMobileSide(false);
    }
  });
  function openModal(id) {
    var m = $(id);
    if (m) m.hidden = false;
    return m;
  }

  /* ---------- Generic data-post buttons ---------- */
  $$('[data-post]').forEach(function (b) {
    b.addEventListener('click', function () {
      window.apost(b.getAttribute('data-post'), {action: b.getAttribute('data-action')}, function (j) {
        if (j.ok) location.reload();
        else window.toast(j.msg || 'خطأ', 1);
      });
    });
  });

  /* =========================================================
     المسجّلون
     ======================================================= */
  var regTable = $('.tbl-reg');
  if (regTable) {
    var currentQR = null;
    var otherChoice = function (value) {
      value = String(value || '').trim().toLowerCase();
      return value === 'other' || value === 'أخرى' || value === 'اخرى' || value === 'أُخرى';
    };
    var syncOtherFields = function (formEl) {
      if (!formEl) return;
      $$('[data-other-select]', formEl).forEach(function (select) {
        var key = select.getAttribute('data-other-select');
        var wrap = formEl.querySelector('[data-other-wrap="' + key + '"]');
        var input = wrap ? wrap.querySelector('textarea') : null;
        if (!wrap || !input) return;
        var show = otherChoice(select.value);
        wrap.hidden = !show;
        input.required = show;
        if (!show) input.value = '';
      });
      $$('[data-custom-other-select]', formEl).forEach(function (select) {
        var key = select.getAttribute('data-custom-other-select');
        var wrap = formEl.querySelector('[data-custom-other-wrap="' + key + '"]');
        var input = wrap ? wrap.querySelector('textarea') : null;
        if (!wrap || !input) return;
        var show = select.value === '__other__';
        wrap.hidden = !show;
        input.required = show;
        if (!show) input.value = '';
      });
    };
    var bindOtherFields = function (formEl) {
      if (!formEl) return;
      $$('[data-other-select]', formEl).forEach(function (select) {
        select.addEventListener('change', function () { syncOtherFields(formEl); });
      });
      $$('[data-custom-other-select]', formEl).forEach(function (select) {
        select.addEventListener('change', function () { syncOtherFields(formEl); });
      });
      syncOtherFields(formEl);
    };

    regTable.addEventListener('click', function (e) {
      var btn = e.target.closest('.act');
      if (!btn) return;
      var tr = btn.closest('tr');
      var id = tr.getAttribute('data-id');
      var act = btn.getAttribute('data-act');

      if (act === 'approve' || act === 'reject' || act === 'pending') {
        if (act === 'reject' && !confirm('رفض هذا الطلب؟')) return;
        window.apost('registrant-api.php', {action: act, id: id}, function (j) {
          if (j.ok) location.reload(); else window.toast(j.msg || 'خطأ', 1);
        });
      } else if (act === 'delete') {
        if (!confirm('حذف نهائي لهذا المسجّل؟')) return;
        window.apost('registrant-api.php', {action: 'delete', id: id}, function (j) {
          if (j.ok) { tr.remove(); window.toast('تم الحذف'); } else window.toast('خطأ', 1);
        });
      } else if (act === 'wa_toggle') {
        window.apost('registrant-api.php', {action: 'wa_toggle', id: id}, function (j) {
          if (j.ok) location.reload();
        });
      } else if (act === 'wa_open') {
        var phone = tr.getAttribute('data-phone');
        var msg = tr.getAttribute('data-msg');
        window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
      } else if (act === 'wa_api') {
        if (!confirm('إرسال الرسالة والباركود آلياً عبر واتساب API؟')) return;
        btn.disabled = true; btn.textContent = '...';
        window.apost('registrant-api.php', {action: 'wa_send_api', id: id}, function (j) {
          window.toast(j.msg || (j.ok ? 'أُرسلت ✓' : 'فشل'), j.ok ? 0 : 1);
          if (j.ok) location.reload();
          else { btn.disabled = false; }
        });
      } else if (act === 'wa_meta') {
        if (!confirm('إرسال الرسالة والباركود عبر Meta WhatsApp API؟')) return;
        btn.disabled = true; btn.textContent = '...';
        window.apost('registrant-api.php', {action: 'wa_send_meta', id: id}, function (j) {
          window.toast(j.msg || (j.ok ? 'أُرسلت عبر Meta ✓' : 'فشل'), j.ok ? 0 : 1);
          if (j.ok) location.reload();
          else { btn.disabled = false; btn.innerHTML = 'Meta'; }
        });
      } else if (act === 'copy_msg') {
        copyText(tr.getAttribute('data-msg'), 'نُسخت الرسالة ✓');
      } else if (act === 'qr') {
        showQR(tr);
      } else if (act === 'badge') {
        window.open('badge-one.php?id=' + id, '_blank');
      } else if (act === 'notes') {
        var nm = openModal('#notesModal');
        nm.setAttribute('data-id', id);
        var ta = $('#notesForm textarea');
        ta.value = '';
        window.apost('registrant-api.php', {action: 'get_notes', id: id}, function (j) {
          if (j.ok) ta.value = j.notes || '';
        });
      } else if (act === 'edit') {
        var em = openModal('#editModal');
        window.apost('registrant-api.php', {action: 'get', id: id}, function (j) {
          if (!j.ok) { window.toast('تعذر الجلب', 1); return; }
          var f = $('#editForm');
          Object.keys(j.row).forEach(function (k) {
            var inp = f.querySelector('[name="' + k + '"]');
            if (inp) inp.value = j.row[k] == null ? '' : j.row[k];
          });
          syncOtherFields(f);
        });
      }
    });

    function copyText(txt, okMsg) {
      if (navigator.clipboard && window.isSecureContext !== false) {
        navigator.clipboard.writeText(txt).then(function () { window.toast(okMsg); }, function () { fallbackCopy(txt, okMsg); });
      } else {
        fallbackCopy(txt, okMsg);
      }
    }
    function fallbackCopy(txt, okMsg) {
      var ta = document.createElement('textarea');
      ta.value = txt;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); window.toast(okMsg); } catch (e) { window.toast('انسخ يدوياً', 1); }
      document.body.removeChild(ta);
    }

    function showQR(tr) {
      var code = tr.getAttribute('data-code');
      var name = tr.getAttribute('data-name');
      openModal('#qrModal');
      $('#qrName').textContent = name;
      $('#qrCodeTxt').textContent = code;
      var box = $('#qrBox');
      box.innerHTML = '';
      currentQR = new QRCode(box, {
        text: code,
        width: 240,
        height: 240,
        colorDark: '#24275F',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
      $('#qrModal').setAttribute('data-code', code);
    }

    function qrCanvas() {
      return $('#qrBox canvas') || null;
    }
    var qrDl = $('#qrDownload');
    if (qrDl) qrDl.addEventListener('click', function () {
      var cv = qrCanvas();
      if (!cv) return;
      /* إطار أبيض + الرمز أسفل الباركود */
      var pad = 26;
      var out = document.createElement('canvas');
      out.width = cv.width + pad * 2;
      out.height = cv.height + pad * 2 + 40;
      var cx = out.getContext('2d');
      cx.fillStyle = '#fff';
      cx.fillRect(0, 0, out.width, out.height);
      cx.drawImage(cv, pad, pad);
      cx.fillStyle = '#24275F';
      cx.font = 'bold 22px Montserrat, monospace';
      cx.textAlign = 'center';
      cx.fillText($('#qrModal').getAttribute('data-code'), out.width / 2, out.height - 18);
      var a = document.createElement('a');
      a.download = $('#qrModal').getAttribute('data-code') + '.png';
      a.href = out.toDataURL('image/png');
      a.click();
    });
    var qrCp = $('#qrCopyImg');
    if (qrCp) qrCp.addEventListener('click', function () {
      var cv = qrCanvas();
      if (!cv) return;
      if (navigator.clipboard && window.ClipboardItem) {
        cv.toBlob(function (blob) {
          navigator.clipboard.write([new ClipboardItem({'image/png': blob})])
            .then(function () { window.toast('نُسخ الباركود ✓ — الصقه في واتساب'); },
                  function () { window.toast('المتصفح لا يدعم نسخ الصور — استخدم التنزيل', 1); });
        });
      } else {
        window.toast('المتصفح لا يدعم نسخ الصور — استخدم التنزيل', 1);
      }
    });
    var qrCc = $('#qrCopyCode');
    if (qrCc) qrCc.addEventListener('click', function () {
      copyText($('#qrModal').getAttribute('data-code'), 'نُسخ الرمز ✓');
    });

    /* الملاحظات */
    var notesForm = $('#notesForm');
    if (notesForm) notesForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var id = $('#notesModal').getAttribute('data-id');
      window.apost('registrant-api.php', {action: 'notes', id: id, notes: notesForm.notes.value}, function (j) {
        if (j.ok) { $('#notesModal').hidden = true; window.toast('حُفظت الملاحظات ✓'); }
      });
    });

    /* تعديل بيانات المسجّل */
    var editForm = $('#editForm');
    bindOtherFields(editForm);
    if (editForm) editForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(editForm);
      fd.append('action', 'edit');
      window.apost('registrant-api.php', fd, function (j) {
        if (j.ok) { window.toast('حُفظت التعديلات ✓'); setTimeout(function () { location.reload(); }, 800); }
        else window.toast(j.msg || 'خطأ', 1);
      });
    });

    /* التحديد الجماعي */
    var chkAll = $('#chkAll');
    var bulkBar = $('#bulkBar');
    function selIds() {
      return $$('.rowChk:checked').map(function (c) { return c.value; });
    }
    function updBulk() {
      var n = selIds().length;
      if (bulkBar) {
        bulkBar.hidden = n === 0;
        $('#bulkCount').textContent = n;
      }
    }
    if (chkAll) chkAll.addEventListener('change', function () {
      $$('.rowChk').forEach(function (c) { c.checked = chkAll.checked; });
      updBulk();
    });
    $$('.rowChk').forEach(function (c) { c.addEventListener('change', updBulk); });
    $$('[data-bulk]').forEach(function (b) {
      b.addEventListener('click', function () {
        var ids = selIds();
        if (!ids.length) return;
        var act = b.getAttribute('data-bulk');
        if (act === 'delete' && !confirm('حذف ' + ids.length + ' مسجّلاً نهائياً؟')) return;
        window.apost('registrant-api.php', {action: act === 'wa_sent' ? 'wa_sent' : act, ids: ids}, function (j) {
          if (j.ok) location.reload(); else window.toast(j.msg || 'خطأ', 1);
        });
      });
    });
    var bulkBadge = $('[data-bulk-badge]');
    if (bulkBadge) bulkBadge.addEventListener('click', function () {
      var ids = selIds();
      if (!ids.length) return;
      window.open('badge-print.php?ids=' + ids.join(','), '_blank');
    });

    /* الإضافة اليدوية */
    var addBtn = $('#addReg');
    if (addBtn) addBtn.addEventListener('click', function () {
      if (addForm) { addForm.reset(); syncOtherFields(addForm); }
      openModal('#addModal');
    });
    var addForm = $('#addForm');
    bindOtherFields(addForm);
    if (addForm) addForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(addForm);
      fd.append('action', 'add');
      window.apost('registrant-api.php', fd, function (j) {
        if (j.ok) { window.toast('أُضيف بالرمز ' + j.code); setTimeout(function () { location.reload(); }, 900); }
        else window.toast(j.msg || 'خطأ', 1);
      });
    });
  }

  /* =========================================================
     تسجيل الدخول للقاعة
     ======================================================= */
  var ciCode = $('#ciCode');
  if (ciCode) {
    var lookup = function () {
      var code = ciCode.value.trim().toUpperCase();
      if (code.length < 4) return;
      window.apost('registrant-api.php', {action: 'checkin', code: code}, function (j) {
        var res = $('#ciResult');
        res.hidden = false;
        if (!j.ok) {
          $('#ciStatus').textContent = '✗ غير موجود';
          $('#ciStatus').className = 'ci-status ci-bad';
          $('#ciName').textContent = code;
          $('#ciOrg').textContent = '';
          $('#ciPhone').textContent = '';
          $('#ciCodeTxt').textContent = '';
          $('#ciMark').hidden = true;
          $('#ciNote').textContent = j.msg || '';
          return;
        }
        var stMap = {approved: '✓ مقبول', pending: '⏳ قيد المراجعة', rejected: '✗ مرفوض'};
        $('#ciStatus').textContent = (j.attended ? '🎫 مسجّل حضور مسبقاً — ' : '') + (stMap[j.status] || j.status);
        $('#ciStatus').className = 'ci-status ' + (j.status === 'approved' ? (j.attended ? 'ci-warn' : 'ci-ok') : 'ci-bad');
        $('#ciName').textContent = j.name;
        $('#ciOrg').textContent = [j.org, j.job].filter(Boolean).join(' · ');
        $('#ciPhone').textContent = j.phone;
        $('#ciCodeTxt').textContent = j.code;
        $('#ciMark').hidden = !(j.status === 'approved' && !j.attended);
        $('#ciMark').setAttribute('data-id', j.id);
        $('#ciNote').textContent = j.attended && j.attended_at ? 'وقت الحضور: ' + j.attended_at : '';
      });
    };
    $('#ciGo').addEventListener('click', lookup);
    ciCode.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); lookup(); }
    });
    $('#ciMark').addEventListener('click', function () {
      window.apost('registrant-api.php', {action: 'checkin_mark', id: $('#ciMark').getAttribute('data-id')}, function (j) {
        if (j.ok) {
          window.toast('سُجّل الحضور ✓');
          $('#ciMark').hidden = true;
          $('#ciStatus').textContent = '🎫 حاضر';
          $('#ciStatus').className = 'ci-status ci-ok';
          ciCode.value = '';
          ciCode.focus();
        } else window.toast(j.msg || 'خطأ', 1);
      });
    });
  }

  /* =========================================================
     المتحدثون / الشركاء (CRUD موحد)
     ======================================================= */
  if (window.ENT) {
    var entModal = $('#entModal');
    var entForm = $('#entForm');
    var photoField = window.ENT.logoField || 'photo';

    var fillForm = function (data) {
      $$('#entForm [name]').forEach(function (inp) {
        var k = inp.getAttribute('name');
        if (inp.type === 'checkbox') {
          inp.checked = data ? !!+(data[k] || 0) : (k === 'active');
        } else if (k === 'photo') {
          inp.value = data ? (data[photoField] || '') : '';
        } else {
          inp.value = data && data[k] !== undefined && data[k] !== null ? data[k] : (k === 'sort' ? '0' : (k === 'id' ? '0' : (k === 'kind' ? inp.value : '')));
        }
      });
      var prev = $('#photoPrev'), hint = $('#photoHint');
      var ph = data ? (data[photoField] || '') : '';
      if (ph && ph.indexOf('assets:') === 0) {
        prev.src = '../assets/img/' + ph.slice(7);
        prev.hidden = false; hint.hidden = true;
      } else if (ph) {
        prev.src = '../uploads/' + ph;
        prev.hidden = false; hint.hidden = true;
      } else {
        prev.hidden = true; hint.hidden = false;
      }
      /* الشعارات المدمجة تبقى كما هي إذا لم تُستبدل */
      entForm.photo.value = ph;
    };

    var newBtn = $('#newBtn');
    if (newBtn) newBtn.addEventListener('click', function () {
      fillForm(null);
      entModal.hidden = false;
    });
    if (location.search.indexOf('new=1') > -1 && newBtn) newBtn.click();

    $$('.ent-card').forEach(function (card) {
      var data = JSON.parse(card.getAttribute('data-ent'));
      card.querySelector('[data-edit]').addEventListener('click', function () {
        fillForm(data);
        entModal.hidden = false;
      });
      card.querySelector('[data-del]').addEventListener('click', function () {
        if (!confirm('حذف "' + (data.name_ar || data.name_en) + '"؟')) return;
        window.apost(window.ENT.api, {act: 'del', id: data.id}, function (j) {
          if (j.ok) card.remove();
        });
      });
    });

    entForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(entForm);
      fd.append('act', 'save');
      /* orgs يستخدم حقل logo */
      if (photoField === 'logo') fd.append('logo', entForm.photo.value);
      window.apost(window.ENT.api, fd, function (j) {
        if (j.ok) location.reload();
        else window.toast(j.msg || 'خطأ', 1);
      });
    });

    var pd = $('#photoDrop');
    var pf = $('#photoFile');
    if (pd) pd.addEventListener('click', function () { pf.click(); });
    if (pf) pf.addEventListener('change', function () {
      if (!pf.files.length) return;
      var fd = new FormData();
      fd.append('file', pf.files[0]);
      fd.append('dir', window.ENT.uploadDir);
      window.apost('upload.php', fd, function (j) {
        if (!j.ok) { window.toast(j.msg || 'فشل الرفع', 1); return; }
        entForm.photo.value = j.path;
        $('#photoPrev').src = j.url;
        $('#photoPrev').hidden = false;
        $('#photoHint').hidden = true;
        window.toast('رُفعت الصورة ✓');
      });
      pf.value = '';
    });
  }

  /* =========================================================
     الأجندة
     ======================================================= */
  var dayModal = $('#dayModal');
  if (dayModal) {
    var dayForm = $('#dayForm');
    var itemModal = $('#itemModal');
    var itemForm = $('#itemForm');

    var fill = function (form, data) {
      $$('[name]', form).forEach(function (inp) {
        var k = inp.getAttribute('name');
        inp.value = data && data[k] !== undefined && data[k] !== null ? data[k] : (k === 'sort' || k === 'id' || k === 'day_id' ? '0' : '');
      });
    };

    var newAgendaDay = $('#newDayBtn');
    if (newAgendaDay) newAgendaDay.addEventListener('click', function () {
      fill(dayForm, null);
      dayModal.hidden = false;
    });
    $$('.day-panel-adm').forEach(function (panel) {
      var dData = JSON.parse(panel.getAttribute('data-day'));
      panel.querySelector('[data-day-edit]').addEventListener('click', function () {
        fill(dayForm, dData);
        dayModal.hidden = false;
      });
      panel.querySelector('[data-day-del]').addEventListener('click', function () {
        if (!confirm('حذف اليوم وكل فقراته؟')) return;
        window.apost('agenda.php', {act: 'day_del', id: dData.id}, function (j) { if (j.ok) location.reload(); });
      });
      panel.querySelector('[data-item-new]').addEventListener('click', function () {
        fill(itemForm, null);
        itemForm.day_id.value = dData.id;
        itemModal.hidden = false;
      });
      $$('[data-item]', panel).forEach(function (trEl) {
        var iData = JSON.parse(trEl.getAttribute('data-item'));
        trEl.querySelector('[data-item-edit]').addEventListener('click', function () {
          fill(itemForm, iData);
          itemModal.hidden = false;
        });
        trEl.querySelector('[data-item-del]').addEventListener('click', function () {
          if (!confirm('حذف الفقرة؟')) return;
          window.apost('agenda.php', {act: 'item_del', id: iData.id}, function (j) { if (j.ok) trEl.remove(); });
        });
      });
    });
    dayForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(dayForm);
      fd.append('act', 'day_save');
      window.apost('agenda.php', fd, function (j) { if (j.ok) location.reload(); else window.toast(j.msg || 'خطأ', 1); });
    });
    itemForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(itemForm);
      fd.append('act', 'item_save');
      window.apost('agenda.php', fd, function (j) { if (j.ok) location.reload(); else window.toast(j.msg || 'خطأ', 1); });
    });
  }

  /* =========================================================
     الصفحات
     ======================================================= */
  var pageModal = $('#pageModal');
  if (pageModal) {
    $('#newPageBtn').addEventListener('click', function () { pageModal.hidden = false; });
    $('#pageForm').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(ev.target);
      fd.append('act', 'create');
      window.apost('pages.php', fd, function (j) {
        if (j.ok) location.href = 'page-edit.php?id=' + j.id;
        else window.toast(j.msg || 'خطأ', 1);
      });
    });
    $$('[data-page-del]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!confirm('حذف الصفحة نهائياً؟')) return;
        var tr = b.closest('tr');
        window.apost('pages.php', {act: 'del', id: tr.getAttribute('data-id')}, function (j) {
          if (j.ok) tr.remove();
        });
      });
    });
    $$('[data-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var tr = b.closest('tr');
        window.apost('pages.php', {act: 'toggle', id: tr.getAttribute('data-id'), field: b.getAttribute('data-toggle')}, function (j) {
          if (j.ok) location.reload();
        });
      });
    });
  }
})();
