<?php
require_once __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/app/slots.php';
require_once dirname(__DIR__) . '/app/posts.php';
require_super();

$slots = scf_slots();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    $key = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$key])) json_out(['ok' => false, 'msg' => 'خانة غير معروفة'], 422);
    $slot = $slots[$key];
    $old = setting('slot_' . $key, '');

    if ($act === 'upload') {
        if (!rate_limit('admin_media_upload', 60, 3600)) json_out(['ok' => false, 'msg' => 'تجاوزت حد الرفع، انتظر قليلاً'], 429);
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) json_out(['ok' => false, 'msg' => 'لم يصل أي ملف (قد يكون أكبر من حد الاستضافة)'], 400);
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'msg' => 'فشل الرفع (' . (int)$f['error'] . ')'], 400);
        try {
            if ($slot['type'] === 'video') {
                if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'mp4') throw new RuntimeException('يجب أن يكون الفيديو بصيغة MP4');
                $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 16);
                if (strpos($head, 'ftyp') === false) throw new RuntimeException('الملف ليس فيديو MP4 صالحاً');
                $dir = SCF_UPLOADS . '/site';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $rel = 'uploads/site/video-' . date('Ymd') . '-' . bin2hex(random_bytes(5)) . '.mp4';
                if (!move_uploaded_file($f['tmp_name'], SCF_ROOT . '/' . $rel)) throw new RuntimeException('تعذر حفظ الفيديو');
                @chmod(SCF_ROOT . '/' . $rel, 0644);
            } else {
                if ($f['size'] > 12 * 1024 * 1024) throw new RuntimeException('الحد الأقصى للصورة 12MB');
                $max = $key === 'brand_logo' ? 600 : ($key === 'stage_logo' ? 1400 : 2000);
                $img = scf_image_process($f['tmp_name'], 'site', $max, 0, !empty($slot['png']));
                $rel = $img['file'];
            }
        } catch (Throwable $e) {
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        if ($old !== '') scf_media_delete($old);
        setting_set('slot_' . $key, $rel);
        admin_audit('media_replace', $key . ' -> ' . $rel);
        json_out(['ok' => true, 'url' => base_url() . '/' . $rel]);
    }

    if ($act === 'reset') {
        if ($old !== '') scf_media_delete($old);
        setting_set('slot_' . $key, '');
        admin_audit('media_reset', $key);
        json_out(['ok' => true, 'url' => slot_url($key)]);
    }
    json_out(['ok' => false], 400);
}

$maxUpload = ini_get('upload_max_filesize');
admin_header('الصور والفيديو', 'media');
?>
<style>
.md-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.md-card{border:1px solid #e3eaf2;border-radius:14px;overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s}
.md-card:hover{box-shadow:0 14px 34px -18px rgba(36,39,95,.45);transform:translateY(-2px)}
.md-prev{aspect-ratio:16/10;background:#eef3f7 repeating-conic-gradient(#f4f7fa 0 25%,#e9eef3 0 50%) 50%/18px 18px;display:grid;place-items:center;overflow:hidden;position:relative}
.md-prev img,.md-prev video{max-width:100%;max-height:100%;object-fit:contain}
.md-card.busy .md-prev::after{content:"جارٍ الرفع…";position:absolute;inset:0;display:grid;place-items:center;background:rgba(255,255,255,.8);font-weight:700;color:#196598}
.md-body{padding:12px 14px;display:flex;flex-direction:column;gap:8px;flex:1}
.md-body b{font-size:14px;color:#24275F}
.md-tag{font-size:11px;color:#7b8a9c}
.md-tag.over{color:#1b8a5b}
.md-actions{display:flex;gap:8px;margin-top:auto;flex-wrap:wrap}
.md-actions label{cursor:pointer}
</style>
<div class="panel">
  <div class="panel-head"><h2>الصور والوسائط الأساسية</h2><a class="ftab" href="../?edit=1" target="_blank">أو استبدلها من الموقع مباشرة ↗</a></div>
  <p class="hint">اختر «استبدال» لرفع صورة جديدة (JPG / PNG / WebP) أو فيديو MP4. تُعاد معالجة الصور تلقائياً لتصبح خفيفة وآمنة. «استعادة الأصل» يرجع الملف الأصلي. الحد الأقصى للرفع في الاستضافة: <b dir="ltr"><?= e((string)$maxUpload) ?></b>. صور المنشورات تُدار من صفحة «المنشورات»، وشعارات الشركاء من «الشركاء والرعاة».</p>
  <div class="md-grid">
    <?php foreach ($slots as $key => $s): $over = setting('slot_' . $key, ''); $url = slot_url($key); ?>
    <div class="md-card" data-slot="<?= e($key) ?>">
      <div class="md-prev"><?php if ($s['type'] === 'video'): ?><video src="<?= e($url) ?>" muted playsinline preload="metadata" controls></video><?php else: ?><img src="<?= e($url) ?>" alt="" loading="lazy"><?php endif; ?></div>
      <div class="md-body">
        <b><?= e($s['label']) ?></b>
        <span class="md-tag<?= $over !== '' ? ' over' : '' ?>"><?= $over !== '' ? '✓ مستبدلة' : 'الملف الأصلي' ?></span>
        <div class="md-actions">
          <label class="btn-p btn-s"><?= icon('upload') ?> استبدال<input type="file" hidden accept="<?= $s['type'] === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' ?>"></label>
          <?php if ($over !== ''): ?><button type="button" class="act" data-reset>استعادة الأصل</button><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  function send(fd) {
    fd.append('_csrf', window.CSRF);
    return fetch('media.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json(); });
  }
  document.querySelectorAll('.md-card').forEach(function (card) {
    var input = card.querySelector('input[type=file]');
    input.addEventListener('change', function () {
      if (!input.files.length) return;
      var fd = new FormData(); fd.append('act', 'upload'); fd.append('slot', card.dataset.slot); fd.append('file', input.files[0]);
      card.classList.add('busy');
      send(fd).then(function (j) {
        window.toast(j.ok ? 'تم الاستبدال ✓' : (j.msg || 'تعذر الرفع'), j.ok ? 0 : 1);
        if (j.ok) setTimeout(function () { location.reload(); }, 600);
      }).catch(function () { window.toast('تعذر الاتصال (قد يكون الملف أكبر من حد الاستضافة)', 1); })
        .then(function () { card.classList.remove('busy'); input.value = ''; });
    });
    var reset = card.querySelector('[data-reset]');
    if (reset) reset.addEventListener('click', function () {
      if (!confirm('استعادة الملف الأصلي؟')) return;
      var fd = new FormData(); fd.append('act', 'reset'); fd.append('slot', card.dataset.slot);
      send(fd).then(function (j) { if (j.ok) location.reload(); });
    });
  });
})();
</script>
<?php admin_footer(); ?>
