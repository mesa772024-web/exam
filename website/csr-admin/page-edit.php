<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$page = q_one('SELECT * FROM pages WHERE id = ?', [$id]);
if ($page === null) {
    header('Location: pages.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        $blocksRaw = (string)($_POST['blocks'] ?? '[]');
        $blocks = json_decode($blocksRaw, true);
        if (!is_array($blocks)) json_out(['ok' => false, 'msg' => 'بنية غير صالحة'], 422);
        /* تنقية البلوكات: أنواع معروفة */
        $allowed = ['heading', 'text', 'image', 'imgtext', 'cards', 'list', 'video', 'gallery', 'cta', 'spacer', 'pdf', 'html'];
        $rawFields = ['html' => ['html']]; // حقول تُخزَّن كما هي (تُعرض في iframe معزول)
        $cleanBlocks = [];
        foreach (array_slice($blocks, 0, 60) as $b) {
            $t = $b['type'] ?? '';
            if (!in_array($t, $allowed, true)) continue;
            $d = is_array($b['data'] ?? null) ? $b['data'] : [];
            $cd = [];
            foreach ($d as $k => $v) {
                $k = preg_replace('/[^a-z0-9_]/i', '', (string)$k);
                if ($k === 'items' && is_array($v)) {
                    $items = [];
                    foreach (array_slice($v, 0, 24) as $it) {
                        if (!is_array($it)) continue;
                        $ci = [];
                        foreach ($it as $ik => $iv) {
                            $ik = preg_replace('/[^a-z0-9_]/i', '', (string)$ik);
                            $ci[$ik] = clean_text((string)$iv, 2000);
                        }
                        $items[] = $ci;
                    }
                    $cd['items'] = $items;
                } elseif (isset($rawFields[$t]) && in_array($k, $rawFields[$t], true)) {
                    /* محتوى HTML/CSS/JS خام — يُعرض داخل iframe sandbox معزول */
                    $cd[$k] = mb_substr(str_replace("\x00", '', (string)$v), 0, 100000);
                } else {
                    $cd[$k] = clean_text((string)$v, 5000);
                }
            }
            $cleanBlocks[] = ['type' => $t, 'data' => $cd];
        }
        $titleAr = clean_text($_POST['title_ar'] ?? '', 200);
        $titleEn = clean_text($_POST['title_en'] ?? '', 200);
        $mode = ($_POST['mode'] ?? 'blocks') === 'full' ? 'full' : 'blocks';
        $rawCode = mb_substr(str_replace("\x00", '', (string)($_POST['raw_code'] ?? '')), 0, 300000);
        q('UPDATE pages SET title_ar=?, title_en=?, blocks=?, mode=?, raw_code=?, published=?, in_nav=?, sort=? WHERE id=?', [
            $titleAr, $titleEn,
            json_encode($cleanBlocks, JSON_UNESCAPED_UNICODE),
            $mode, $rawCode,
            !empty($_POST['published']) ? 1 : 0,
            !empty($_POST['in_nav']) ? 1 : 0,
            (int)($_POST['sort'] ?? 0),
            $id,
        ]);
        admin_audit('page_save', 'id=' . $id . ' mode=' . $mode);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

admin_header('محرر: ' . ($page['title_ar'] ?: $page['title_en']), 'pages');
?>
<div class="builder">
  <aside class="builder-side">
    <div class="bpanel">
      <h3>إعدادات الصفحة</h3>
      <label>العنوان (عربي)<input id="pTitleAr" value="<?= e($page['title_ar']) ?>" maxlength="200"></label>
      <label>Title (EN)<input id="pTitleEn" value="<?= e($page['title_en']) ?>" maxlength="200" dir="ltr"></label>
      <label class="chkline"><input type="checkbox" id="pPub" <?= $page['published'] ? 'checked' : '' ?>> منشورة</label>
      <label class="chkline"><input type="checkbox" id="pNav" <?= $page['in_nav'] ? 'checked' : '' ?>> تظهر في قائمة الموقع</label>
      <label>الترتيب<input id="pSort" type="number" value="<?= (int)$page['sort'] ?>"></label>
    </div>
    <div class="bpanel">
      <h3>نوع الصفحة</h3>
      <div class="mode-toggle">
        <label class="mode-opt"><input type="radio" name="pmode" value="blocks" <?= ($page['mode'] ?? 'blocks') !== 'full' ? 'checked' : '' ?>><span><?= icon('layout') ?> بلوكات جاهزة</span></label>
        <label class="mode-opt"><input type="radio" name="pmode" value="full" <?= ($page['mode'] ?? 'blocks') === 'full' ? 'checked' : '' ?>><span><?= icon('code') ?> كود HTML كامل</span></label>
      </div>
      <p class="hint" style="margin:8px 0 0">وضع «كود HTML كامل» يعرض كودك داخل إطار معزول يغطي الصفحة دون تداخل مع باقي الموقع.</p>
    </div>
    <div class="bpanel" id="blocksPanel">
      <h3>إضافة بلوك</h3>
      <div class="blk-btns">
        <button data-add="heading"><?= icon('doc') ?> عنوان</button>
        <button data-add="text"><?= icon('list') ?> نص</button>
        <button data-add="image"><?= icon('image') ?> صورة</button>
        <button data-add="imgtext"><?= icon('layout') ?> صورة + نص</button>
        <button data-add="cards"><?= icon('grid') ?> بطاقات</button>
        <button data-add="list"><?= icon('check') ?> قائمة نقاط</button>
        <button data-add="gallery"><?= icon('image') ?> معرض صور</button>
        <button data-add="video"><?= icon('play') ?> فيديو</button>
        <button data-add="pdf"><?= icon('file-pdf') ?> ملف PDF</button>
        <button data-add="html"><?= icon('code') ?> كود HTML</button>
        <button data-add="cta"><?= icon('star') ?> زر دعوة</button>
        <button data-add="spacer"><?= icon('menu') ?> فراغ</button>
      </div>
    </div>
    <div class="bpanel">
      <button class="btn-p btn-block" id="saveBtn"><?= icon('save') ?> حفظ الصفحة</button>
      <a class="btn-s btn-block" href="../page.php?s=<?= e($page['slug']) ?>" target="_blank"><?= icon('external') ?> معاينة</a>
      <a class="btn-s btn-block" href="pages.php"><?= icon('arrow-r') ?> رجوع للصفحات</a>
    </div>
  </aside>
  <div class="builder-main">
    <div class="builder-canvas" id="canvas"></div>
    <div class="full-editor" id="fullEditor" hidden>
      <div class="fe-head"><b><?= icon('code') ?> كود HTML / CSS / JS (ملف واحد كامل)</b><button type="button" class="btn-s" id="fePreview"><?= icon('eye') ?> تحديث المعاينة</button></div>
      <div class="fe-split">
        <textarea id="rawCode" spellcheck="false" placeholder="<!DOCTYPE html>&#10;<html>...</html>"><?= e((string)($page['raw_code'] ?? '')) ?></textarea>
        <iframe id="fePrevFrame" class="fe-prev" sandbox="allow-scripts allow-forms allow-popups allow-modals" title="preview"></iframe>
      </div>
    </div>
  </div>
</div>

<input type="file" id="blkFile" accept="image/*" hidden>
<input type="file" id="blkPdf" accept="application/pdf,.pdf" hidden>

<script>
window.PAGE_ID = <?= (int)$id ?>;
window.PAGE_BLOCKS = <?= json_encode(json_decode((string)$page['blocks'], true) ?: [], JSON_UNESCAPED_UNICODE) ?>;
window.PAGE_MODE = <?= json_encode($page['mode'] ?? 'blocks') ?>;
</script>
<script>
(function(){
  var blocksPanel=document.getElementById('blocksPanel');
  var canvas=document.getElementById('canvas');
  var fullEd=document.getElementById('fullEditor');
  var raw=document.getElementById('rawCode');
  var prev=document.getElementById('fePrevFrame');
  function applyMode(m){
    var full=m==='full';
    fullEd.hidden=!full; canvas.hidden=full; blocksPanel.style.display=full?'none':'';
    if(full) refreshPrev();
  }
  function refreshPrev(){ try{ prev.srcdoc=raw.value; }catch(e){} }
  document.querySelectorAll('input[name=pmode]').forEach(function(r){
    r.addEventListener('change',function(){ if(r.checked) applyMode(r.value); });
  });
  var pbtn=document.getElementById('fePreview'); if(pbtn) pbtn.addEventListener('click',refreshPrev);
  window.PAGE_GET_MODE=function(){ var c=document.querySelector('input[name=pmode]:checked'); return c?c.value:'blocks'; };
  window.PAGE_GET_RAW=function(){ return raw.value; };
  applyMode(window.PAGE_MODE);
})();
</script>
<script>
(function () {
  'use strict';
  var blocks = Array.isArray(window.PAGE_BLOCKS) ? window.PAGE_BLOCKS : [];
  var canvas = document.getElementById('canvas');
  var fileInput = document.getElementById('blkFile');
  var uploadTarget = null;

  var NAMES = {heading: 'عنوان', text: 'نص', image: 'صورة', imgtext: 'صورة + نص', cards: 'بطاقات',
    list: 'قائمة نقاط', gallery: 'معرض صور', video: 'فيديو', cta: 'زر دعوة', spacer: 'فراغ',
    pdf: 'ملف PDF', html: 'كود HTML/CSS/JS'};

  function inp(label, val, key, opts) {
    opts = opts || {};
    var dir = opts.ltr ? ' dir="ltr"' : '';
    if (opts.area) {
      return '<label>' + label + '<textarea data-k="' + key + '" rows="' + (opts.rows || 3) + '"' + dir + '>' + esc(val || '') + '</textarea></label>';
    }
    return '<label>' + label + '<input data-k="' + key + '" value="' + esc(val || '') + '"' + dir + (opts.num ? ' type="number"' : '') + '></label>';
  }
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }
  function imgBtn(val, key) {
    var prev = val ? '<img src="' + esc(imgUrl(val)) + '" class="bimg-prev">' : '';
    return '<div class="bimg" data-imgkey="' + key + '">' + prev +
      '<button type="button" class="btn-s" data-upload="' + key + '">📤 رفع صورة</button>' +
      '<input data-k="' + key + '" value="' + esc(val || '') + '" placeholder="أو رابط مباشر" dir="ltr"></div>';
  }
  function imgUrl(v) {
    if (/^https?:/.test(v)) return v;
    if (v.indexOf('assets:') === 0) return '../assets/img/' + v.slice(7);
    return '../uploads/' + v;
  }

  function blockFields(b) {
    var d = b.data || {};
    switch (b.type) {
      case 'heading':
        return inp('النص (عربي)', d.text_ar, 'text_ar') + inp('Text (EN)', d.text_en, 'text_en', {ltr: 1});
      case 'text':
        return inp('النص (عربي)', d.text_ar, 'text_ar', {area: 1, rows: 4}) + inp('Text (EN)', d.text_en, 'text_en', {area: 1, rows: 4, ltr: 1});
      case 'image':
        return imgBtn(d.src, 'src') + inp('تعليق (عربي)', d.caption_ar, 'caption_ar') + inp('Caption (EN)', d.caption_en, 'caption_en', {ltr: 1});
      case 'imgtext':
        return imgBtn(d.src, 'src') + inp('العنوان (عربي)', d.title_ar, 'title_ar') + inp('Title (EN)', d.title_en, 'title_en', {ltr: 1}) +
          inp('النص (عربي)', d.text_ar, 'text_ar', {area: 1}) + inp('Text (EN)', d.text_en, 'text_en', {area: 1, ltr: 1});
      case 'cards':
        var items = d.items || [];
        var html = '<div class="cards-list">';
        items.forEach(function (it, i) {
          html += '<div class="card-item" data-ci="' + i + '"><div class="card-item-head"><b>بطاقة ' + (i + 1) + '</b>' +
            '<button type="button" class="act act-del" data-ci-del="' + i + '">🗑</button></div>' +
            '<input data-ci-k="title_ar" data-ci-i="' + i + '" value="' + esc(it.title_ar || '') + '" placeholder="العنوان عربي">' +
            '<input data-ci-k="title_en" data-ci-i="' + i + '" value="' + esc(it.title_en || '') + '" placeholder="Title EN" dir="ltr">' +
            '<textarea data-ci-k="text_ar" data-ci-i="' + i + '" rows="2" placeholder="النص عربي">' + esc(it.text_ar || '') + '</textarea>' +
            '<textarea data-ci-k="text_en" data-ci-i="' + i + '" rows="2" placeholder="Text EN" dir="ltr">' + esc(it.text_en || '') + '</textarea></div>';
        });
        html += '</div><button type="button" class="btn-s" data-ci-add>+ بطاقة</button>';
        return html;
      case 'list':
        return inp('العناصر (عربي — سطر لكل عنصر)', d.items_ar, 'items_ar', {area: 1, rows: 5}) +
               inp('Items (EN — one per line)', d.items_en, 'items_en', {area: 1, rows: 5, ltr: 1});
      case 'gallery':
        return '<div class="bimg" data-imgkey="srcs"><button type="button" class="btn-s" data-upload-append="srcs">📤 رفع وإضافة صورة</button></div>' +
               inp('الصور (سطر لكل صورة)', d.srcs, 'srcs', {area: 1, rows: 4, ltr: 1});
      case 'video':
        return inp('رابط يوتيوب أو ملف فيديو', d.src, 'src', {ltr: 1});
      case 'cta':
        return inp('العنوان (عربي)', d.title_ar, 'title_ar') + inp('Title (EN)', d.title_en, 'title_en', {ltr: 1}) +
               inp('نص الزر (عربي)', d.label_ar, 'label_ar') + inp('Button (EN)', d.label_en, 'label_en', {ltr: 1}) +
               inp('الرابط', d.url, 'url', {ltr: 1});
      case 'spacer':
        return inp('الارتفاع بالبكسل', d.h || 40, 'h', {num: 1});
      case 'pdf':
        var pdfPrev = d.src ? '<a class="pdf-chip" href="../uploads/' + esc(d.src) + '" target="_blank">📄 ' + esc(d.label || 'ملف PDF') + '</a>' : '';
        return '<div class="bimg" data-imgkey="src">' + pdfPrev +
          '<button type="button" class="btn-s" data-upload-pdf="src">📎 رفع ملف PDF</button></div>' +
          inp('عنوان الملف (عربي)', d.title_ar, 'title_ar') + inp('Title (EN)', d.title_en, 'title_en', {ltr: 1});
      case 'html':
        return '<p class="hint" style="margin:0 0 8px">الصق كود HTML/CSS/JS كاملاً في ملف واحد. يُعرض داخل إطار معزول آمن.</p>' +
          '<label>الكود<textarea data-k="html" rows="10" dir="ltr" style="font-family:monospace;font-size:12.5px" placeholder="&lt;style&gt;...&lt;/style&gt;&#10;&lt;div&gt;...&lt;/div&gt;&#10;&lt;script&gt;...&lt;/script&gt;">' + esc(d.html || '') + '</textarea></label>' +
          inp('الارتفاع (بكسل)', d.height || 500, 'height', {num: 1});
    }
    return '';
  }

  function render() {
    canvas.innerHTML = '';
    if (!blocks.length) {
      canvas.innerHTML = '<p class="empty" style="padding:60px 0;text-align:center">أضف البلوكات من القائمة الجانبية لبناء الصفحة</p>';
      return;
    }
    blocks.forEach(function (b, i) {
      var el = document.createElement('div');
      el.className = 'bblock';
      el.setAttribute('data-i', i);
      el.innerHTML =
        '<div class="bblock-head">' +
          '<b>' + (NAMES[b.type] || b.type) + '</b>' +
          '<div class="bblock-tools">' +
            '<button type="button" data-mv="-1" title="أعلى">▲</button>' +
            '<button type="button" data-mv="1" title="أسفل">▼</button>' +
            '<button type="button" data-fold title="طي/فتح">▤</button>' +
            '<button type="button" data-rm class="rm" title="حذف">✕</button>' +
          '</div>' +
        '</div>' +
        '<div class="bblock-body">' + blockFields(b) + '</div>';
      canvas.appendChild(el);
    });
  }

  /* ربط التعديلات بالنموذج */
  canvas.addEventListener('input', function (e) {
    var t = e.target;
    var blockEl = t.closest('.bblock');
    if (!blockEl) return;
    var i = +blockEl.getAttribute('data-i');
    if (!blocks[i]) return;
    blocks[i].data = blocks[i].data || {};
    if (t.hasAttribute('data-k')) {
      blocks[i].data[t.getAttribute('data-k')] = t.value;
    } else if (t.hasAttribute('data-ci-k')) {
      var ci = +t.getAttribute('data-ci-i');
      blocks[i].data.items = blocks[i].data.items || [];
      blocks[i].data.items[ci] = blocks[i].data.items[ci] || {};
      blocks[i].data.items[ci][t.getAttribute('data-ci-k')] = t.value;
    }
  });

  canvas.addEventListener('click', function (e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    var blockEl = btn.closest('.bblock');
    var i = blockEl ? +blockEl.getAttribute('data-i') : -1;
    if (btn.hasAttribute('data-rm')) {
      if (!confirm('حذف هذا البلوك؟')) return;
      blocks.splice(i, 1);
      render();
    } else if (btn.hasAttribute('data-mv')) {
      var dir = +btn.getAttribute('data-mv');
      var j = i + dir;
      if (j < 0 || j >= blocks.length) return;
      var tmp = blocks[i]; blocks[i] = blocks[j]; blocks[j] = tmp;
      render();
    } else if (btn.hasAttribute('data-fold')) {
      blockEl.classList.toggle('folded');
    } else if (btn.hasAttribute('data-ci-add')) {
      blocks[i].data = blocks[i].data || {};
      blocks[i].data.items = blocks[i].data.items || [];
      blocks[i].data.items.push({});
      render();
    } else if (btn.hasAttribute('data-ci-del')) {
      blocks[i].data.items.splice(+btn.getAttribute('data-ci-del'), 1);
      render();
    } else if (btn.hasAttribute('data-upload') || btn.hasAttribute('data-upload-append')) {
      uploadTarget = {i: i, key: btn.getAttribute('data-upload') || btn.getAttribute('data-upload-append'), append: btn.hasAttribute('data-upload-append')};
      fileInput.click();
    } else if (btn.hasAttribute('data-upload-pdf')) {
      uploadTarget = {i: i, key: btn.getAttribute('data-upload-pdf'), pdf: true};
      pdfInput.click();
    }
  });

  var pdfInput = document.getElementById('blkPdf');
  pdfInput.addEventListener('change', function () {
    if (!pdfInput.files.length || !uploadTarget) return;
    var fd = new FormData();
    fd.append('file', pdfInput.files[0]);
    fd.append('_csrf', window.CSRF);
    fetch('upload-file.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { window.toast(j.msg || 'فشل الرفع', 1); return; }
        var b = blocks[uploadTarget.i];
        b.data = b.data || {};
        b.data.src = j.path;
        b.data.label = j.label || 'PDF';
        render();
        window.toast('تم رفع الملف');
      })
      .catch(function () { window.toast('تعذر الرفع', 1); });
    pdfInput.value = '';
  });

  fileInput.addEventListener('change', function () {
    if (!fileInput.files.length || !uploadTarget) return;
    var fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('dir', 'pages');
    fd.append('_csrf', window.CSRF);
    fetch('upload.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { window.toast(j.msg || 'فشل الرفع', 1); return; }
        var b = blocks[uploadTarget.i];
        b.data = b.data || {};
        if (uploadTarget.append) {
          b.data[uploadTarget.key] = (b.data[uploadTarget.key] ? b.data[uploadTarget.key] + '\n' : '') + j.path;
        } else {
          b.data[uploadTarget.key] = j.path;
        }
        render();
        window.toast('تم رفع الصورة');
      })
      .catch(function () { window.toast('تعذر الرفع', 1); });
    fileInput.value = '';
  });

  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      blocks.push({type: btn.getAttribute('data-add'), data: {}});
      render();
      canvas.lastElementChild && canvas.lastElementChild.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
  });

  document.getElementById('saveBtn').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('act', 'save');
    fd.append('id', window.PAGE_ID);
    fd.append('blocks', JSON.stringify(blocks));
    fd.append('mode', window.PAGE_GET_MODE ? window.PAGE_GET_MODE() : 'blocks');
    fd.append('raw_code', window.PAGE_GET_RAW ? window.PAGE_GET_RAW() : '');
    fd.append('title_ar', document.getElementById('pTitleAr').value);
    fd.append('title_en', document.getElementById('pTitleEn').value);
    if (document.getElementById('pPub').checked) fd.append('published', '1');
    if (document.getElementById('pNav').checked) fd.append('in_nav', '1');
    fd.append('sort', document.getElementById('pSort').value);
    fd.append('_csrf', window.CSRF);
    fetch('page-edit.php?id=' + window.PAGE_ID, {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { return r.json(); })
      .then(function (j) { window.toast(j.ok ? 'تم الحفظ ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1); })
      .catch(function () { window.toast('تعذر الاتصال', 1); });
  });

  render();
})();
</script>
<?php admin_footer(); ?>
