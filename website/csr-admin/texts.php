<?php
require_once __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/app/i18n.php';
require_super();
scf_texts_ensure_table();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');

    /* حفظ دفعة تعديلات من محرر الواجهة */
    if ($act === 'inline_save') {
        $items = json_decode((string)($_POST['items'] ?? '[]'), true);
        $lang = ($_POST['lang'] ?? 'ar') === 'en' ? 'en' : 'ar';
        if (!is_array($items) || count($items) > 300) json_out(['ok' => false, 'msg' => 'بيانات غير صالحة'], 422);
        $saved = []; $failed = [];
        foreach ($items as $it) {
            $k = (string)($it['k'] ?? '');
            try {
                scf_tk_save($k, (string)($it['v'] ?? ''), $lang);
                $saved[] = $k;
            } catch (Throwable $e) {
                $failed[] = ['k' => $k, 'msg' => $e->getMessage()];
            }
        }
        admin_audit('inline_edit', $lang . ' saved=' . count($saved) . ' failed=' . count($failed));
        json_out(['ok' => true, 'saved' => $saved, 'failed' => $failed]);
    }

    /* حفظ صف من هذه الصفحة (العربي والإنكليزي معاً) */
    if ($act === 'save_row') {
        $key = (string)($_POST['key'] ?? '');
        $rich = !empty($_POST['rich']);
        try {
            foreach (['ar', 'en'] as $l) {
                $v = (string)($_POST['val_' . $l] ?? '');
                if (trim($v) === '') { scf_tk_reset($key, $l); continue; }
                scf_tk_save($key . ($rich ? '*' : ''), $v, $l);
            }
        } catch (Throwable $e) {
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        json_out(['ok' => true]);
    }

    if ($act === 'reset') {
        $key = (string)($_POST['key'] ?? '');
        if (!preg_match('/^(t:[a-f0-9]{12}|i18n:[a-z0-9_]{1,60})$/', $key)) json_out(['ok' => false], 422);
        scf_tk_reset($key);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

/* الفهرس: من الشيفرة + ما سُجّل من الصفحات */
$catalog = scf_text_catalog();
$rows = [];
foreach (q_all('SELECT * FROM site_texts') as $r) $rows[$r['tkey']] = $r;
$list = [];
foreach ($catalog as $k => $c) {
    $r = $rows[$k] ?? null;
    $list[$k] = ['key' => $k, 'def_ar' => $c['ar'], 'def_en' => $c['en'], 'val_ar' => $r['val_ar'] ?? null, 'val_en' => $r['val_en'] ?? null, 'file' => $c['file']];
}
foreach ($rows as $k => $r) {
    if (isset($list[$k])) continue;
    if ($r['def_ar'] === null && $r['val_ar'] === null && $r['val_en'] === null) continue;
    $list[$k] = ['key' => $k, 'def_ar' => (string)$r['def_ar'], 'def_en' => (string)$r['def_en'], 'val_ar' => $r['val_ar'], 'val_en' => $r['val_en'], 'file' => 'أُخرى'];
}
$edited = count(array_filter($list, function ($x) { return $x['val_ar'] !== null || $x['val_en'] !== null; }));
$fileNames = [
    'index.php' => 'الرئيسية', 'app/edition2.php' => 'الواجهة والتغطية', 'app/layout.php' => 'الرأس والتذييل',
    'edition2.php' => 'صفحة التغطية', 'register.php' => 'صفحة التسجيل', 'edition1.php' => 'النسخة الأولى',
    'page.php' => 'الصفحات', 'app/sections_render.php' => 'الأقسام المخصصة', 'app/i18n.php' => 'قاموس الواجهة',
];

admin_header('نصوص الواجهة', 'texts');
?>
<style>
.tx-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px}
.tx-top input[type=search]{flex:1;min-width:220px}
.tx-live{display:flex;gap:14px;align-items:center;flex-wrap:wrap;background:linear-gradient(120deg,#24275F,#196598);color:#fff;border-radius:16px;padding:18px 20px;margin-bottom:16px}
.tx-live b{font-size:17px}.tx-live p{margin:0;color:#cfe3f3;font-size:13.5px;flex:1;min-width:220px}
.tx-live a{background:#fff;color:#24275F;border-radius:999px;padding:10px 18px;font-weight:700;display:inline-flex;gap:8px;align-items:center}
.tx-list{display:flex;flex-direction:column;gap:10px}
.tx-row{border:1px solid #e3eaf2;border-radius:12px;padding:12px 14px;background:#fff}
.tx-row.is-edited{border-inline-start:4px solid #f0a020}
.tx-meta{display:flex;gap:8px;align-items:center;font-size:11.5px;color:#7b8a9c;margin-bottom:8px;flex-wrap:wrap}
.tx-chip{background:#eef4f9;color:#196598;border-radius:20px;padding:1px 9px}
.tx-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tx-grid label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:#5b6b80}
.tx-grid textarea{min-height:44px;resize:vertical;font:inherit;font-size:14px;padding:8px 10px;border:1px solid #d8e2ec;border-radius:9px;line-height:1.7}
.tx-grid textarea[dir=ltr]{font-family:Montserrat,system-ui,sans-serif}
.tx-def{font-size:11.5px;color:#9aa8b8}
.tx-actions{display:flex;gap:8px;margin-top:8px;justify-content:flex-end}
@media(max-width:720px){.tx-grid{grid-template-columns:1fr}}
</style>

<div class="tx-live">
  <div><b>✏️ التعديل المباشر على الموقع</b></div>
  <p>أسرع طريقة: افتح الموقع في وضع التحرير واضغط على أي نص لتعديله مكانه (عربي وإنكليزي)، واستبدل الصور، ورتّب الأقسام.</p>
  <a href="../?edit=1" target="_blank"><?= icon('edit') ?> فتح الموقع للتعديل</a>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>كل نصوص الواجهة (<?= count($list) ?>) · المعدّلة: <?= $edited ?></h2>
    <div class="ftabs">
      <button class="ftab on" type="button" data-f="all">الكل</button>
      <button class="ftab" type="button" data-f="edited">المعدّلة فقط</button>
    </div>
  </div>
  <p class="hint">اترك الحقل فارغاً لاستخدام النص الأصلي. النصوص التي تحوي تنسيقاً (&lt;em&gt; و&lt;br&gt;) تقبل هذه الوسوم فقط. نصوص «محتوى الموقع» والأجندة والشركاء تُعدّل من صفحاتها أو من الواجهة مباشرة.</p>
  <div class="tx-top"><input type="search" id="txSearch" placeholder="ابحث في النصوص (عربي أو إنكليزي)…"></div>
  <div class="tx-list" id="txList">
    <?php foreach ($list as $x): $isEd = $x['val_ar'] !== null || $x['val_en'] !== null; $rich = strpos($x['def_ar'] . $x['def_en'], '<') !== false; ?>
    <div class="tx-row<?= $isEd ? ' is-edited' : '' ?>" data-key="<?= e($x['key']) ?>" data-rich="<?= $rich ? 1 : 0 ?>" data-search="<?= e(mb_strtolower($x['def_ar'] . ' ' . $x['def_en'] . ' ' . $x['val_ar'] . ' ' . $x['val_en'])) ?>">
      <div class="tx-meta"><span class="tx-chip"><?= e($fileNames[$x['file']] ?? $x['file']) ?></span><?php if ($rich): ?><span class="tx-chip">تنسيق</span><?php endif; ?><span class="mono" dir="ltr"><?= e($x['key']) ?></span><?php if ($isEd): ?><span class="tx-chip" style="background:#fff3dc;color:#8a5a00">معدّل</span><?php endif; ?></div>
      <div class="tx-grid">
        <label>العربي<textarea name="val_ar" rows="1" placeholder="<?= e($x['def_ar']) ?>"><?= e((string)$x['val_ar']) ?></textarea><span class="tx-def">الأصل: <?= e(mb_strimwidth($x['def_ar'], 0, 140, '…')) ?></span></label>
        <label>English<textarea name="val_en" rows="1" dir="ltr" placeholder="<?= e($x['def_en']) ?>"><?= e((string)$x['val_en']) ?></textarea><span class="tx-def" dir="ltr">Default: <?= e(mb_strimwidth($x['def_en'], 0, 140, '…')) ?></span></label>
      </div>
      <div class="tx-actions"><?php if ($isEd): ?><button class="act" type="button" data-reset>استعادة الأصل</button><?php endif; ?><button class="btn-p btn-s" type="button" data-save>حفظ</button></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  function post(data) {
    var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); fd.append('_csrf', window.CSRF);
    return fetch('texts.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json(); });
  }
  var rows = Array.prototype.slice.call(document.querySelectorAll('.tx-row')), filter = 'all', q = '';
  function apply() {
    rows.forEach(function (r) {
      var show = (!q || r.dataset.search.indexOf(q) >= 0) && (filter === 'all' || r.classList.contains('is-edited'));
      r.hidden = !show;
    });
  }
  document.getElementById('txSearch').addEventListener('input', function () { q = this.value.trim().toLowerCase(); apply(); });
  document.querySelectorAll('[data-f]').forEach(function (b) {
    b.addEventListener('click', function () { document.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('on', x === b); }); filter = b.dataset.f; apply(); });
  });
  document.querySelectorAll('.tx-grid textarea').forEach(function (t) {
    var fit = function () { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 300) + 'px'; };
    t.addEventListener('input', fit); fit();
  });
  document.getElementById('txList').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var row = b.closest('.tx-row');
    if (b.hasAttribute('data-save')) {
      b.disabled = true;
      post({act: 'save_row', key: row.dataset.key, rich: row.dataset.rich, val_ar: row.querySelector('[name=val_ar]').value, val_en: row.querySelector('[name=val_en]').value})
        .then(function (j) { window.toast(j.ok ? 'تم الحفظ ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1); if (j.ok) row.classList.add('is-edited'); })
        .catch(function () { window.toast('تعذر الاتصال', 1); }).then(function () { b.disabled = false; });
    }
    if (b.hasAttribute('data-reset')) {
      if (!confirm('استعادة النص الأصلي بالعربي والإنكليزي؟')) return;
      post({act: 'reset', key: row.dataset.key}).then(function (j) { if (j.ok) { row.querySelectorAll('textarea').forEach(function (t) { t.value = ''; }); row.classList.remove('is-edited'); b.remove(); window.toast('تمت الاستعادة'); } });
    }
  });
})();
</script>
<?php admin_footer(); ?>
