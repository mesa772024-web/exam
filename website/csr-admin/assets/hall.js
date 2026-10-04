/* نظام قاعة المؤتمر التفاعلي */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var H = window.HALL;
  var seatGrid = $('#seatGrid');
  var waitingZone = $('#waitingZone');
  var queueList = $('#queueList');
  var scanInput = $('#scanInput');
  var printFrame = $('#printFrame');

  var seats = [];               // عناصر المقاعد
  var occMap = H.occMap || {};  // seat_no → {name, org, id}
  var entered = H.entered;
  var waiting = {};             // id → {name, code, dotEl, itemEl}
  var waitingCount = 0;
  var zones = H.zones || {};
  var zoneColor = '#f5b301';
  var zoneScope = 'seat';
  var draggedSeat = 0;
  var selectedSeat = 0;

  /* بناء المقاعد */
  function buildSeats() {
    seatGrid.innerHTML = '';
    seats = [];
    for (var i = 1; i <= H.cap; i++) {
      var s = document.createElement('div');
      var occ = occMap[i];
      s.className = 'seat' + (occ ? ' occupied' : '');
      s.setAttribute('data-seat', i);
      if (zones[i]) { s.style.setProperty('--zone-color', zones[i]); s.classList.add('zoned'); }
      if (occ) s.title = 'مقعد ' + i + ' · ' + occ.name; else s.title = 'مقعد ' + i + ' (فارغ)';
      if (occ) {
        s.draggable = true;
        s.addEventListener('dragstart', seatDragStart);
        s.addEventListener('dragend', seatDragEnd);
      }
      s.addEventListener('dragover', seatDragOver);
      s.addEventListener('dragleave', seatDragLeave);
      s.addEventListener('drop', seatDrop);
      s.addEventListener('click', zoneSeatClick);
      s.addEventListener('click', seatMoveClick);
      if (selectedSeat === i) s.classList.add('move-selected');
      seatGrid.appendChild(s);
      seats[i] = s;
    }
  }

  function updateStats() {
    $('#stTotal').textContent = H.cap;
    $('#stRemain').textContent = H.cap - entered;
    $('#stIn').textContent = entered;
    $('#stWait').textContent = waitingCount;
    var qc = $('#qCount'); if (qc) qc.textContent = waitingCount;
    var qb = $('#queueBlock'); if (qb) qb.classList.toggle('has-waiting', waitingCount > 0);
  }

  function msg(text, type) {
    var m = $('#scanMsg');
    m.textContent = text || '';
    m.className = 'hp-hint' + (type ? ' hp-' + type : '');
  }

  /* مسح الباركود */
  function scan() {
    var code = scanInput.value.trim();
    if (code.length < 3) return;
    window.apost('registrant-api.php', { action: 'checkin', code: code }, function (j) {
      scanInput.value = '';
      scanInput.focus();
      if (!j.ok) { msg(j.msg || 'الرمز غير موجود', 'bad'); return; }
      if (j.status !== 'approved') { msg(j.name + ' — الحالة: غير مقبول', 'bad'); return; }
      if (j.attended) { msg(j.name + ' — دخل القاعة مسبقاً', 'warn'); return; }
      if (waiting[j.id]) { msg(j.name + ' — موجود في الانتظار بالفعل', 'warn'); return; }
      enqueuePerson(j, true);
    });
  }

  function enqueuePerson(p, announce) {
    window.apost('registrant-api.php', { action: 'queue_add', id: p.id }, function(j) {
      if (!j.ok) { msg(j.msg || 'تعذر الإضافة لقائمة الانتظار', 'bad'); return; }
      addToWaiting(j.row || p, announce);
      if (announce) msg('أُضيف ' + (j.row ? j.row.name : p.name) + ' لقائمة الانتظار المشتركة', 'ok');
    });
  }

  function addToWaiting(p, announce) {
    if (waiting[p.id]) return;
    waitingCount++;
    // نقطة صفراء في منطقة الانتظار
    var dot = document.createElement('div');
    dot.className = 'person-wait';
    dot.title = p.name;
    dot.setAttribute('data-id', p.id);
    waitingZone.appendChild(dot);
    // عنصر في القائمة
    var empty = $('#queueEmpty'); if (empty) empty.style.display = 'none';
    var item = document.createElement('div');
    item.className = 'queue-item';
    item.setAttribute('data-id', p.id);
    item.innerHTML =
      '<div class="qi-info"><span class="qi-dot"></span><div><b>' + esc(p.name) + '</b><small dir="ltr">' + esc(p.code) + '</small></div></div>' +
      '<button class="btn-p qi-admit">طباعة وإدخال</button>';
    item.querySelector('.qi-admit').addEventListener('click', function () { admit(p.id); });
    queueList.appendChild(item);
    waiting[p.id] = { name: p.name, code: p.code, org: p.org || '', dotEl: dot, itemEl: item };
    updateStats();
    var qb = $('#queueBlock');
    if (qb && announce) {
      qb.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      qb.classList.remove('queue-pulse'); void qb.offsetWidth; qb.classList.add('queue-pulse');
    }
  }

  function removeWaiting(id) {
    var w = waiting[id];
    if (!w) return;
    if (w.dotEl) w.dotEl.remove();
    if (w.itemEl) w.itemEl.remove();
    delete waiting[id]; waitingCount = Math.max(0, waitingCount - 1);
    if (waitingCount === 0 && $('#queueEmpty')) $('#queueEmpty').style.display = '';
  }

  /* الإدخال للقاعة + الطباعة + الأنيميشن */
  function admit(id) {
    var w = waiting[id];
    if (!w) return;
    var btn = w.itemEl.querySelector('.qi-admit');
    btn.disabled = true; btn.textContent = '...';
    window.apost('registrant-api.php', { action: 'admit', id: id }, function (j) {
      if (!j.ok) { msg(j.msg || 'تعذّر الإدخال', 'bad'); btn.disabled = false; btn.textContent = 'طباعة وإدخال'; return; }
      // طباعة الباج (إن مفعّل)
      if (H.printOnAdmit && !j.already) printBadge(id);
      if (j.already) {
        removeWaiting(id); entered = +j.entered || entered; updateStats(); buildSeats();
        msg(w.name + ' — أُدخل من جهاز آخر إلى المقعد ' + j.seat, 'warn');
        return;
      }
      // سجّل المقعد بالاسم (للتلميح والجدول)
      occMap[j.seat] = { name: w.name, org: w.org || '', id: +id };
      // أنيميشن الطيران للمقعد
      flyToSeat(w.dotEl, j.seat, function () {
        entered = j.entered || (entered + 1);
        removeWaiting(id);
        updateStats();
      });
      msg(w.name + ' → مقعد رقم ' + j.seat, 'ok');
    });
  }

  function flyToSeat(dot, seatNo, done) {
    var seat = seats[seatNo];
    if (!seat) { if (dot) dot.remove(); done(); return; }
    var s = dot.getBoundingClientRect(), e = seat.getBoundingClientRect();
    dot.style.visibility = 'hidden';
    var fly = document.createElement('div');
    fly.className = 'fly-person';
    fly.style.top = s.top + 'px'; fly.style.left = s.left + 'px';
    document.body.appendChild(fly);
    requestAnimationFrame(function () {
      fly.style.top = e.top + 'px'; fly.style.left = e.left + 'px'; fly.style.transform = 'scale(1.15)';
    });
    setTimeout(function () {
      fly.remove(); dot.remove();
      seat.classList.add('occupied', 'just-seated');
      var occ = occMap[seatNo];
      if (occ) seat.title = 'مقعد ' + seatNo + ' · ' + occ.name;
      setTimeout(function () { seat.classList.remove('just-seated'); }, 500);
      done();
    }, 950);
  }

  /* طباعة الباج عبر إطار مخفي (بلا فتح تبويب) */
  function printBadge(id) {
    printFrame.src = 'badge-one.php?id=' + id + '&auto=1&t=' + Date.now();
  }

  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }

  function seatDragStart(e) {
    if ($('#zoneMode') && $('#zoneMode').checked) { e.preventDefault(); return; }
    draggedSeat = +this.getAttribute('data-seat');
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(draggedSeat));
  }
  function seatDragEnd() { this.classList.remove('dragging'); draggedSeat = 0; $$('.seat.drop-target').forEach(function(s){s.classList.remove('drop-target');}); }
  function seatDragOver(e) {
    if (!draggedSeat || this.classList.contains('occupied')) return;
    e.preventDefault(); this.classList.add('drop-target'); e.dataTransfer.dropEffect = 'move';
  }
  function seatDragLeave() { this.classList.remove('drop-target'); }
  function seatDrop(e) {
    e.preventDefault(); this.classList.remove('drop-target');
    var from = draggedSeat || +(e.dataTransfer.getData('text/plain') || 0);
    var to = +this.getAttribute('data-seat');
    if (!from || !to || from === to || occMap[to] || !occMap[from]) return;
    var person = occMap[from];
    window.apost('registrant-api.php', { action: 'move_seat', id: person.id, to: to }, function(j) {
      if (!j.ok) { window.toast(j.msg || 'تعذر نقل المقعد', 1); return; }
      occMap[to] = person; delete occMap[from]; buildSeats();
      window.toast(person.name + ' ← المقعد ' + to);
    });
  }

  /* نقل باللمس: اختر المقعد المشغول ثم اضغط المقعد الفارغ */
  function seatMoveClick() {
    if ($('#zoneMode') && $('#zoneMode').checked) return;
    var to = +this.getAttribute('data-seat');
    if (occMap[to]) {
      selectedSeat = selectedSeat === to ? 0 : to;
      buildSeats();
      if (selectedSeat) window.toast('اختر الآن مقعداً فارغاً لنقل ' + occMap[to].name);
      return;
    }
    if (!selectedSeat || !occMap[selectedSeat]) return;
    var from = selectedSeat, person = occMap[from];
    window.apost('registrant-api.php', { action: 'move_seat', id: person.id, to: to }, function(j) {
      if (!j.ok) { window.toast(j.msg || 'تعذر نقل المقعد', 1); return; }
      occMap[to] = person; delete occMap[from]; selectedSeat = 0; buildSeats();
      window.toast(person.name + ' ← المقعد ' + to);
    });
  }

  function zoneSeatClick(e) {
    var mode = $('#zoneMode'); if (!mode || !mode.checked) return;
    e.preventDefault();
    var seatNo = +this.getAttribute('data-seat');
    var targets = [seatNo];
    var cols = Math.max(1, getComputedStyle(seatGrid).gridTemplateColumns.split(' ').length);
    var zero = seatNo - 1;
    if (zoneScope === 'row' || e.shiftKey) {
      var row = Math.floor(zero / cols);
      targets = seats.filter(function(s){ return s && Math.floor((+s.getAttribute('data-seat') - 1) / cols) === row; })
        .map(function(s){ return +s.getAttribute('data-seat'); });
    } else if (zoneScope === 'column') {
      var col = zero % cols;
      targets = seats.filter(function(s){ return s && ((+s.getAttribute('data-seat') - 1) % cols) === col; })
        .map(function(s){ return +s.getAttribute('data-seat'); });
    }
    targets.forEach(function(n){ if (zoneColor) zones[n] = zoneColor; else delete zones[n]; });
    buildSeats(); saveZones();
  }
  function saveZones() {
    window.apost('registrant-api.php', { action: 'save_zones', zones: JSON.stringify(zones) }, function(j) {
      if (!j.ok) window.toast(j.msg || 'تعذر حفظ ألوان المقاعد', 1);
    });
  }

  var zoneMode = $('#zoneMode'), zonePalette = $('#zonePalette'), zoneScopeBox = $('#zoneScope');
  if (zoneMode) zoneMode.addEventListener('change', function(){
    zonePalette.hidden = !zoneMode.checked;
    if (zoneScopeBox) zoneScopeBox.hidden = !zoneMode.checked;
    seatGrid.classList.toggle('zone-mode', zoneMode.checked);
    buildSeats();
  });
  $$('.zc').forEach(function(b){ b.addEventListener('click', function(){
    zoneColor = b.getAttribute('data-color') || '';
    $$('.zc').forEach(function(x){ x.classList.toggle('selected', x === b); });
  }); });
  $$('.zs').forEach(function(b){ b.addEventListener('click', function(){
    var scope = b.getAttribute('data-scope');
    if (scope === 'all-clear') {
      if (!confirm('إلغاء جميع ألوان المقاعد؟')) return;
      zones = {}; buildSeats(); saveZones();
      window.toast('تم إلغاء جميع ألوان المقاعد');
      return;
    }
    zoneScope = scope || 'seat';
    $$('.zs').forEach(function(x){ x.classList.toggle('selected', x === b); });
  }); });
  if ($('.zc[data-color="#f5b301"]')) $('.zc[data-color="#f5b301"]').classList.add('selected');

  $('#scanBtn').addEventListener('click', scan);
  scanInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); scan(); } });

  /* كاميرا الهاتف؛ قارئ USB يبقى عاملاً دائماً عبر حقل الإدخال */
  var cameraBtn = $('#cameraBtn'), cameraModal = $('#cameraModal'), cameraVideo = $('#cameraVideo');
  var cameraMsg = $('#cameraMsg'), cameraStream = null, cameraDetector = null, cameraReading = false;
  function closeCamera() {
    cameraReading = false;
    if (cameraStream) cameraStream.getTracks().forEach(function(t){ t.stop(); });
    cameraStream = null;
    if (cameraVideo) cameraVideo.srcObject = null;
    if (cameraModal) cameraModal.hidden = true;
    scanInput.focus();
  }
  async function cameraLoop() {
    if (!cameraReading || !cameraDetector || !cameraVideo) return;
    try {
      var codes = await cameraDetector.detect(cameraVideo);
      if (codes && codes[0] && codes[0].rawValue) {
        scanInput.value = codes[0].rawValue.trim();
        closeCamera(); scan(); return;
      }
    } catch (ignore) {}
    if (cameraReading) requestAnimationFrame(cameraLoop);
  }
  async function openCamera() {
    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      window.toast('الكاميرا تحتاج رابط HTTPS الآمن. فعّل SSL ثم افتح الموقع بـ https://', 1); return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      window.toast('هذا المتصفح لا يتيح كاميرا الويب. افتح اللوحة بمتصفح Chrome محدّث.', 1); return;
    }
    cameraModal.hidden = false;
    cameraMsg.textContent = 'بانتظار موافقتك على إذن الكاميرا…';
    try {
      /* هذا الاستدعاء من زر المستخدم هو الذي يُظهر نافذة إذن الكاميرا في الهاتف. */
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video:{facingMode:{ideal:'environment'}, width:{ideal:1280}, height:{ideal:720}}, audio:false
      });
      cameraVideo.srcObject = cameraStream;
      await cameraVideo.play();
      if (!('BarcodeDetector' in window)) {
        cameraMsg.textContent = 'الكاميرا تعمل، لكن القراءة التلقائية غير متاحة في هذا المتصفح. استخدم Chrome محدّثاً أو قارئ USB.';
        return;
      }
      var wanted = ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8'];
      var supported = BarcodeDetector.getSupportedFormats ? await BarcodeDetector.getSupportedFormats() : wanted;
      var usable = wanted.filter(function(f){ return supported.indexOf(f) !== -1; });
      cameraDetector = usable.length ? new BarcodeDetector({formats: usable}) : new BarcodeDetector();
      cameraReading = true; cameraMsg.textContent = 'وجّه الكاميرا نحو الباركود أو QR.'; cameraLoop();
    } catch (err) {
      var denied = err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError');
      closeCamera();
      window.toast(denied ? 'تم رفض إذن الكاميرا. افتح إعدادات الموقع وغيّر الكاميرا إلى «سماح».' : 'تعذر تشغيل الكاميرا. تأكد أن لا تطبيق آخر يستخدمها ثم أعد المحاولة.', 1);
    }
  }
  if (cameraBtn) cameraBtn.addEventListener('click', openCamera);
  $$('[data-camera-close]').forEach(function(b){ b.addEventListener('click', closeCamera); });
  if (cameraModal) cameraModal.addEventListener('click', function(e){ if (e.target === cameraModal) closeCamera(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && cameraReading) closeCamera(); });

  /* البحث بالاسم (منفصل عن الباركود) */
  var nameInput = $('#nameInput'), nameResults = $('#nameResults');
  function nameSearch() {
    var q = nameInput.value.trim();
    if (q.length < 2) { nameResults.innerHTML = ''; return; }
    window.apost('registrant-api.php', { action: 'hall_search', q: q }, function (j) {
      if (!j.ok) return;
      if (!j.rows.length) { nameResults.innerHTML = '<p class="nr-empty">لا نتائج</p>'; return; }
      nameResults.innerHTML = j.rows.map(function (r) {
        var st = r.status !== 'approved' ? '<span class="nr-bad">غير مقبول</span>'
               : (+r.attended ? '<span class="nr-in">دخل — مقعد ' + (r.seat_no || '') + '</span>' : '<span class="nr-ok">جاهز</span>');
        var btn = (r.status === 'approved' && !+r.attended) ? '<button class="btn-p nr-add" data-id="' + r.id + '" data-code="' + esc(r.code) + '" data-name="' + esc(r.full_name) + '">إضافة للانتظار</button>' : '';
        return '<div class="nr-row"><div class="nr-info"><b>' + esc(r.full_name) + '</b><small>' + esc(r.org || '') + ' ' + st + '</small></div>' + btn + '</div>';
      }).join('');
      $$('.nr-add', nameResults).forEach(function (b) {
        b.addEventListener('click', function () {
          var p = { id: b.getAttribute('data-id'), code: b.getAttribute('data-code'), name: b.getAttribute('data-name'), status: 'approved', attended: 0 };
          if (waiting[p.id]) { msg(p.name + ' — موجود بالفعل', 'warn'); return; }
          enqueuePerson(p, true);
          b.disabled = true; b.textContent = '✓ أُضيف';
        });
      });
    });
  }
  $('#nameBtn').addEventListener('click', nameSearch);
  nameInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); nameSearch(); } });
  var nameTimer;
  nameInput.addEventListener('input', function () { clearTimeout(nameTimer); nameTimer = setTimeout(nameSearch, 400); });

  /* بدء جلسة جديدة (إخلاء القاعة) */
  var nsBtn = $('#newSession');
  if (nsBtn) nsBtn.addEventListener('click', function () {
    if (!confirm('سيتم إخلاء جميع المقاعد وإلغاء حضور الجميع لبدء جلسة جديدة. هل أنت متأكد؟')) return;
    if (!confirm('تأكيد أخير: هذا الإجراء لا يمكن التراجع عنه. متابعة؟')) return;
    window.apost('registrant-api.php', { action: 'hall_reset' }, function (j) {
      if (j.ok) { window.toast('بدأت جلسة جديدة — أُخلي ' + j.cleared + ' مقعداً'); setTimeout(function () { location.reload(); }, 900); }
      else window.toast(j.msg || 'خطأ', 1);
    });
  });

  /* حفظ السعة (المدير الكامل) */
  var capSave = $('#capSave');
  if (capSave) capSave.addEventListener('click', function () {
    var fd = new FormData();
    fd.append('act', 'set_capacity');
    fd.append('capacity', $('#capInput').value);
    if ($('#printChk').checked) fd.append('print_on_admit', '1');
    window.apost('checkin.php', fd, function (j) {
      if (j.ok) { H.cap = parseInt($('#capInput').value, 10) || H.cap; H.printOnAdmit = $('#printChk').checked; buildSeats(); updateStats(); window.toast('تم الحفظ ✓ — أعد بناء المقاعد'); }
      else window.toast(j.msg || 'خطأ', 1);
    });
  });

  /* جدول الحاضرين والمنتظرين (منبثق) */
  var listModal = $('#listModal');
  var showListBtn = $('#showListBtn');
  if (showListBtn) showListBtn.addEventListener('click', function () {
    buildLists();
    listModal.hidden = false;
  });
  function buildLists() {
    var inBody = $('#inTable tbody'), waitBody = $('#waitTable tbody');
    var inRows = Object.keys(occMap).map(function (s) { return { seat: +s, o: occMap[s] }; }).sort(function (a, b) { return a.seat - b.seat; });
    inBody.innerHTML = inRows.length ? inRows.map(function (r) {
      return '<tr><td class="mono">' + r.seat + '</td><td><b>' + esc(r.o.name) + '</b></td><td class="sub">' + esc(r.o.org || '') + '</td></tr>';
    }).join('') : '<tr><td colspan="3" class="empty">لا حضور بعد</td></tr>';
    var wRows = Object.keys(waiting);
    waitBody.innerHTML = wRows.length ? wRows.map(function (id, i) {
      var w = waiting[id];
      return '<tr><td class="mono">' + (i + 1) + '</td><td><b>' + esc(w.name) + '</b></td><td class="mono" dir="ltr">' + esc(w.code) + '</td></tr>';
    }).join('') : '<tr><td colspan="3" class="empty">لا أحد بالانتظار</td></tr>';
    $('#cIn').textContent = inRows.length;
    $('#cWait').textContent = wRows.length;
  }
  if (listModal) {
    listModal.addEventListener('click', function (e) { if (e.target === listModal || e.target.hasAttribute('data-close')) listModal.hidden = true; });
    document.querySelectorAll('.lt').forEach(function (t) {
      t.addEventListener('click', function () {
        document.querySelectorAll('.lt').forEach(function (x) { x.classList.toggle('on', x === t); });
        var lt = t.getAttribute('data-lt');
        $('#inTable').hidden = lt !== 'in';
        $('#waitTable').hidden = lt !== 'wait';
      });
    });
  }

  /* مزامنة المقاعد وقائمة الانتظار بين جميع الهواتف والحواسيب. */
  function syncHallState() {
    window.apost('registrant-api.php', { action: 'hall_state' }, function(j) {
      if (!j.ok) return;
      H.cap = +j.capacity || H.cap; entered = +j.entered || 0;
      var nextOcc = {};
      (j.seats || []).forEach(function(r){ nextOcc[+r.seat_no] = {id:+r.id, name:r.full_name, org:r.org || ''}; });
      if (JSON.stringify(nextOcc) !== JSON.stringify(occMap)) { occMap = nextOcc; buildSeats(); }
      var serverIds = {};
      (j.waiting || []).forEach(function(r){ serverIds[r.id] = true; addToWaiting(r, false); });
      Object.keys(waiting).forEach(function(id){ if (!serverIds[id]) removeWaiting(id); });
      updateStats();
    });
  }

  buildSeats();
  (H.waiting || []).forEach(function(r){ addToWaiting(r, false); });
  updateStats();
  setInterval(syncHallState, 3500);
  scanInput.focus();
})();
