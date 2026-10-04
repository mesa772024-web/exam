<?php
require_once __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/app/posts.php';
require_super();
scf_posts_ensure();

$cats = ['event' => 'النسخ والفعاليات', 'session' => 'الجلسات والعروض', 'partnership' => 'الشراكات ومذكرات التفاهم'];

/** يتحقق من قائمة الصور القادمة من المحرر */
function posts_clean_images($raw): array
{
    $list = json_decode((string)$raw, true);
    if (!is_array($list)) return [];
    $out = [];
    foreach (array_slice($list, 0, 60) as $im) {
        $file = (string)($im['file'] ?? '');
        $thumb = (string)($im['thumb'] ?? $file);
        $okPath = function ($p) {
            return (bool)preg_match('#^(uploads/posts|media/editions)/[A-Za-z0-9._/-]+\.(webp|jpg|jpeg|png)$#', $p) && strpos($p, '..') === false;
        };
        if (!$okPath($file) || !$okPath($thumb)) continue;
        $abs = strpos($file, 'uploads/') === 0 ? SCF_ROOT . '/' . $file : SCF_ROOT . '/assets/' . $file;
        if (!is_file($abs)) continue;
        $out[] = ['file' => $file, 'thumb' => $thumb, 'width' => max(1, (int)($im['width'] ?? 1800)), 'height' => max(1, (int)($im['height'] ?? 1200))];
    }
    return $out;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'upload_image') {
        if (!rate_limit('admin_post_upload', 200, 3600)) json_out(['ok' => false, 'msg' => 'تجاوزت حد الرفع'], 429);
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) json_out(['ok' => false, 'msg' => 'لم يصل أي ملف'], 400);
        if ($_FILES['file']['size'] > 15 * 1024 * 1024) json_out(['ok' => false, 'msg' => 'الحد الأقصى 15MB للصورة'], 400);
        try {
            $img = scf_image_process($_FILES['file']['tmp_name'], 'posts', 1800, 760);
        } catch (Throwable $e) {
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        json_out(['ok' => true, 'image' => $img, 'url' => scf_media_url($img['thumb'])]);
    }

    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $cat = isset($cats[$_POST['category'] ?? '']) ? $_POST['category'] : 'event';
        $f = [];
        foreach (['label_ar' => 120, 'label_en' => 120, 'title_ar' => 300, 'title_en' => 300] as $k => $max) $f[$k] = clean_text($_POST[$k] ?? '', $max);
        foreach (['ar', 'en'] as $l) {
            $head = str_replace(["\r", "\n"], ' ', clean_text($_POST['head_' . $l] ?? '', 400));
            $body = str_replace("\r", '', clean_text($_POST['body_' . $l] ?? '', 30000));
            $f['text_' . $l] = $head . "\n" . trim($body);
        }
        if ($f['title_ar'] === '' && $f['title_en'] === '') json_out(['ok' => false, 'msg' => 'اكتب عنوان المنشور'], 422);
        $images = posts_clean_images($_POST['images'] ?? '[]');
        if (!$images) json_out(['ok' => false, 'msg' => 'أضف صورة واحدة على الأقل'], 422);
        $imgJson = json_encode($images, JSON_UNESCAPED_SLASHES);
        if ($id > 0) {
            $old = q_one('SELECT images FROM posts WHERE id = ?', [$id]);
            if (!$old) json_out(['ok' => false, 'msg' => 'المنشور غير موجود'], 404);
            $keep = array_column($images, 'file');
            foreach (json_decode((string)$old['images'], true) ?: [] as $im) {
                if (!in_array($im['file'] ?? '', $keep, true)) { scf_media_delete((string)($im['file'] ?? '')); scf_media_delete((string)($im['thumb'] ?? '')); }
            }
            q('UPDATE posts SET category=?, label_ar=?, label_en=?, title_ar=?, title_en=?, text_ar=?, text_en=?, images=? WHERE id=?',
                [$cat, $f['label_ar'], $f['label_en'], $f['title_ar'], $f['title_en'], $f['text_ar'], $f['text_en'], $imgJson, $id]);
        } else {
            $slug = scf_slugify($f['title_en'] !== '' ? $f['title_en'] : 'post');
            $base = $slug; $n = 2;
            while (q_val('SELECT COUNT(*) FROM posts WHERE slug = ?', [$slug])) $slug = $base . '-' . $n++;
            $sort = (int)q_val('SELECT COALESCE(MIN(sort), 10) FROM posts') - 10;
            if (!empty($_POST['at_end'])) $sort = (int)q_val('SELECT COALESCE(MAX(sort), 0) FROM posts') + 10;
            q('INSERT INTO posts (slug, category, label_ar, label_en, title_ar, title_en, text_ar, text_en, images, sort, active) VALUES (?,?,?,?,?,?,?,?,?,?,1)',
                [$slug, $cat, $f['label_ar'], $f['label_en'], $f['title_ar'], $f['title_en'], $f['text_ar'], $f['text_en'], $imgJson, $sort]);
            $id = (int)db()->lastInsertId();
        }
        admin_audit('post_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }

    if ($act === 'reorder') {
        $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
        if (!is_array($ids)) json_out(['ok' => false], 422);
        foreach (array_values($ids) as $i => $pid) q('UPDATE posts SET sort = ? WHERE id = ?', [($i + 1) * 10, (int)$pid]);
        json_out(['ok' => true]);
    }
    if ($act === 'toggle') {
        q('UPDATE posts SET active = ? WHERE id = ?', [!empty($_POST['v']) ? 1 : 0, (int)($_POST['id'] ?? 0)]);
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = q_one('SELECT images FROM posts WHERE id = ?', [$id]);
        if ($row) {
            foreach (json_decode((string)$row['images'], true) ?: [] as $im) { scf_media_delete((string)($im['file'] ?? '')); scf_media_delete((string)($im['thumb'] ?? '')); }
            q('DELETE FROM posts WHERE id = ?', [$id]);
            admin_audit('post_delete', 'id=' . $id);
        }
        json_out(['ok' => true]);
    }
    if ($act === 'save_thanks') {
        setting_set('recap_thanks_ar', str_replace("\r", '', clean_text($_POST['ar'] ?? '', 30000)));
        setting_set('recap_thanks_en', str_replace("\r", '', clean_text($_POST['en'] ?? '', 30000)));
        admin_audit('thanks_save');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$isNew = isset($_GET['new']);
$isThanks = isset($_GET['thanks']);
$post = null;
if ($editId) {
    $row = q_one('SELECT * FROM posts WHERE id = ?', [$editId]);
    if ($row) $post = scf_post_shape($row);
}
$split = function (string $text): array {
    $lines = explode("\n", str_replace("\r", '', $text), 2);
    return [trim($lines[0]), trim($lines[1] ?? '')];
};

admin_header($post ? 'تعديل منشور' : ($isNew ? 'منشور جديد' : ($isThanks ? 'رسالة الشكر' : 'المنشورات والتغطية')), 'posts');
?>
<style>
.ps-top{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.ps-list{display:flex;flex-direction:column;gap:10px}
.ps-item{display:grid;grid-template-columns:auto 96px 1fr auto;gap:14px;align-items:center;background:#fff;border:1px solid #e3eaf2;border-radius:14px;padding:10px 14px;transition:box-shadow .2s,transform .15s}
.ps-item.dragging{opacity:.5}
.ps-item.over{box-shadow:0 0 0 2px #B08A3A}
.ps-item.off{opacity:.55}
.ps-handle{cursor:grab;color:#9aa8b8;font-size:20px;user-select:none;display:flex;flex-direction:column;gap:2px;align-items:center}
.ps-handle button{background:none;border:0;color:#7b8a9c;cursor:pointer;font-size:12px;line-height:1;padding:3px}
.ps-thumb{width:96px;height:64px;border-radius:9px;object-fit:cover;background:#eef3f7}
.ps-info b{display:block;color:#24275F;font-size:15px}
.ps-info small{display:block;color:#7b8a9c;font-size:12px;margin-top:2px}
.ps-chips{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}
.ps-chip{background:#eef4f9;color:#196598;border-radius:20px;padding:1px 9px;font-size:11px}
.ps-acts{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.sw{position:relative;width:42px;height:24px;flex:none}
.sw input{opacity:0;width:0;height:0}
.sw span{position:absolute;inset:0;background:#cfd9e3;border-radius:24px;transition:.2s;cursor:pointer}
.sw span::before{content:"";position:absolute;width:18px;height:18px;top:3px;inset-inline-start:3px;background:#fff;border-radius:50%;transition:.2s}
.sw input:checked+span{background:#22a06b}
.sw input:checked+span::before{transform:translateX(-18px)}
.ps-form{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ps-form .full{grid-column:1/-1}
.ps-form label{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:#24275F}
.ps-form small{font-weight:400;color:#7b8a9c}
.ps-form input,.ps-form select,.ps-form textarea{font:inherit;font-size:14px;padding:10px 12px;border:1px solid #d8e2ec;border-radius:10px;background:#fbfcfe}
.ps-form textarea{min-height:220px;line-height:1.8;resize:vertical}
.ps-form [dir=ltr]{font-family:Montserrat,system-ui,sans-serif}
.ps-help{background:#f4f9fd;border:1px solid #dcebf6;border-radius:12px;padding:12px 16px;font-size:13px;line-height:1.9;color:#33485a}
.im-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.im-item{position:relative;border-radius:10px;overflow:hidden;background:#eef3f7;aspect-ratio:3/2;cursor:grab;border:2px solid transparent}
.im-item.over{border-color:#B08A3A}
.im-item img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
.im-item em{position:absolute;top:6px;inset-inline-start:6px;background:#24275F;color:#fff;font-style:normal;font-size:11px;border-radius:6px;padding:1px 7px}
.im-item button{position:absolute;top:6px;inset-inline-end:6px;width:26px;height:26px;border-radius:50%;border:0;background:rgba(192,57,43,.92);color:#fff;cursor:pointer;font-size:14px;line-height:1}
.im-drop{display:grid;place-items:center;aspect-ratio:3/2;border:2px dashed #b9cfdd;border-radius:10px;color:#196598;font-weight:700;font-size:13px;cursor:pointer;text-align:center;padding:8px;background:#f7fbfe}
.im-drop.drag{background:#e6f3fb;border-color:#B08A3A}
.im-busy{opacity:.6;pointer-events:none}
.ps-save-bar{position:sticky;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border-top:1px solid #e3eaf2;padding:12px 0;display:flex;gap:10px;justify-content:flex-end;margin-top:16px;z-index:5}
@media(max-width:760px){.ps-item{grid-template-columns:auto 70px 1fr}.ps-thumb{width:70px;height:48px}.ps-acts{grid-column:1/-1;justify-content:flex-start}.ps-form{grid-template-columns:1fr}}
</style>

<?php if ($post || $isNew): $p = $post ?: ['id' => 0, 'category' => 'event', 'label' => ['ar' => '', 'en' => ''], 'title' => ['ar' => '', 'en' => ''], 'text' => ['ar' => '', 'en' => ''], 'images' => [], 'slug' => '']; [$hAr, $bAr] = $split($p['text']['ar']); [$hEn, $bEn] = $split($p['text']['en']); ?>
<div class="ps-top"><a class="act" href="posts.php">← كل المنشورات</a><?php if ($p['id']): ?><a class="act" href="../#story-<?= e($p['slug']) ?>" target="_blank">عرض في الموقع ↗</a><?php endif; ?></div>
<form class="panel" id="postForm" autocomplete="off">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="ps-form">
    <label>التصنيف<select name="category"><?php foreach ($cats as $k => $v): ?><option value="<?= e($k) ?>"<?= $p['category'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <?php if (!$p['id']): ?><label>مكان المنشور<select name="at_end"><option value="0">في البداية (الأحدث أولاً)</option><option value="1">في النهاية</option></select></label><?php else: ?><span></span><?php endif; ?>
    <label>الوسم الصغير (عربي) <small>مثال: الجلسة الأولى، مذكرة تفاهم</small><input name="label_ar" value="<?= e($p['label']['ar']) ?>" maxlength="120"></label>
    <label>Label (English)<input name="label_en" dir="ltr" value="<?= e($p['label']['en']) ?>" maxlength="120"></label>
    <label>العنوان (عربي)<input name="title_ar" value="<?= e($p['title']['ar']) ?>" maxlength="300" required></label>
    <label>Title (English)<input name="title_en" dir="ltr" value="<?= e($p['title']['en']) ?>" maxlength="300"></label>
    <label>السطر التعريفي (عربي) <small>لمذكرات التفاهم: «مذكرة تفاهم: الطرف الأول | الطرف الثاني»</small><input name="head_ar" value="<?= e($hAr) ?>"></label>
    <label>Intro line (English) <small dir="ltr">MoU: "Memorandum of understanding | Party A | Party B"</small><input name="head_en" dir="ltr" value="<?= e($hEn) ?>"></label>
    <label>النص (عربي)<textarea name="body_ar"><?= e($bAr) ?></textarea></label>
    <label>Text (English)<textarea name="body_en" dir="ltr"><?= e($bEn) ?></textarea></label>
    <div class="ps-help full"><b>طريقة كتابة النص:</b> كل سطر فقرة. ابدأ السطر بـ <b>•</b> ليصبح عنصر قائمة. للمشاركين في الجلسات اكتب: <b>• الاسم – الصفة</b> ويسبقها سطر ينتهي بنقطتين مثل «وكان المشاركون في الجلسة:» فتظهر كبطاقات أسفل المنشور.</div>
    <div class="full">
      <label style="margin-bottom:8px">الصور <small>اسحب لإعادة الترتيب — الأولى هي الغلاف. يمكن رفع عدة صور معاً.</small></label>
      <div class="im-grid" id="imGrid"></div>
    </div>
  </div>
  <div class="ps-save-bar">
    <?php if ($p['id']): ?><button type="button" class="act" id="delPost" style="color:#c0392b;margin-inline-end:auto">حذف المنشور</button><?php endif; ?>
    <a class="act" href="posts.php">إلغاء</a>
    <button class="btn-p" id="savePost"><?= icon('save') ?> حفظ ونشر</button>
  </div>
</form>
<script>
(function () {
  var images = <?= json_encode(array_map(function ($im) { return $im + ['url' => scf_media_url($im['thumb'] ?? $im['file'])]; }, $p['images']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var grid = document.getElementById('imGrid'), form = document.getElementById('postForm'), dragFrom = null;
  function send(fd) { fd.append('_csrf', window.CSRF); return fetch('posts.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json(); }); }
  function render() {
    grid.innerHTML = '';
    images.forEach(function (im, i) {
      var d = document.createElement('div'); d.className = 'im-item'; d.draggable = true;
      d.innerHTML = '<img alt=""><em>' + (i === 0 ? 'الغلاف' : (i + 1)) + '</em><button type="button" title="حذف">×</button>';
      d.querySelector('img').src = im.url;
      d.querySelector('button').addEventListener('click', function () { images.splice(i, 1); render(); });
      d.addEventListener('dragstart', function () { dragFrom = i; });
      d.addEventListener('dragover', function (e) { e.preventDefault(); d.classList.add('over'); });
      d.addEventListener('dragleave', function () { d.classList.remove('over'); });
      d.addEventListener('drop', function (e) { e.preventDefault(); if (dragFrom === null) return; var m = images.splice(dragFrom, 1)[0]; images.splice(i, 0, m); dragFrom = null; render(); });
      grid.appendChild(d);
    });
    var drop = document.createElement('label'); drop.className = 'im-drop';
    drop.innerHTML = '<span>＋ إضافة صور<br><small>أو اسحبها هنا</small></span><input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>';
    var input = drop.querySelector('input');
    input.addEventListener('change', function () { upload(input.files); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('drag'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('drag'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('drag'); if (e.dataTransfer.files.length) upload(e.dataTransfer.files); });
    grid.appendChild(drop);
  }
  function upload(files) {
    var list = Array.prototype.slice.call(files), done = 0;
    if (!list.length) return;
    grid.classList.add('im-busy'); window.toast('جارٍ رفع ' + list.length + ' صورة…');
    (function next() {
      if (!list.length) { grid.classList.remove('im-busy'); render(); window.toast('تم رفع ' + done + ' صورة ✓'); return; }
      var fd = new FormData(); fd.append('act', 'upload_image'); fd.append('file', list.shift());
      send(fd).then(function (j) {
        if (j.ok) { j.image.url = j.url; images.push(j.image); done++; } else window.toast(j.msg || 'تعذر رفع صورة', 1);
      }).catch(function () { window.toast('تعذر الاتصال', 1); }).then(next);
    })();
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form); fd.append('act', 'save');
    fd.append('images', JSON.stringify(images.map(function (im) { return {file: im.file, thumb: im.thumb, width: im.width, height: im.height}; })));
    var b = document.getElementById('savePost'); b.disabled = true;
    send(fd).then(function (j) {
      window.toast(j.ok ? 'تم الحفظ والنشر ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1);
      if (j.ok && !<?= (int)$p['id'] ?>) setTimeout(function () { location.href = 'posts.php?edit=' + j.id; }, 700);
    }).catch(function () { window.toast('تعذر الاتصال', 1); }).then(function () { b.disabled = false; });
  });
  var del = document.getElementById('delPost');
  if (del) del.addEventListener('click', function () {
    if (!confirm('حذف هذا المنشور وصوره نهائياً؟')) return;
    var fd = new FormData(); fd.append('act', 'delete'); fd.append('id', '<?= (int)$p['id'] ?>');
    send(fd).then(function (j) { if (j.ok) location.href = 'posts.php'; });
  });
  render();
})();
</script>

<?php elseif ($isThanks): $th = scf_thanks_text(); ?>
<div class="ps-top"><a class="act" href="posts.php">← كل المنشورات</a><a class="act" href="../edition2.php?post=thanks" target="_blank">عرض الرسالة ↗</a></div>
<form class="panel ps-form" id="thanksForm">
  <label>رسالة الشكر (عربي)<textarea name="ar" style="min-height:420px"><?= e($th['ar']) ?></textarea></label>
  <label>Thank-you message (English)<textarea name="en" dir="ltr" style="min-height:420px"><?= e($th['en']) ?></textarea></label>
  <div class="ps-help full">كل سطر فقرة، والسطر الذي يبدأ بـ • يصبح عنصر قائمة.</div>
  <div class="full" style="display:flex;justify-content:flex-end"><button class="btn-p"><?= icon('save') ?> حفظ</button></div>
</form>
<script>
document.getElementById('thanksForm').addEventListener('submit', function (e) {
  e.preventDefault(); var fd = new FormData(this); fd.append('act', 'save_thanks'); fd.append('_csrf', window.CSRF);
  fetch('posts.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json(); })
    .then(function (j) { window.toast(j.ok ? 'تم الحفظ ✓' : 'خطأ', j.ok ? 0 : 1); });
});
</script>

<?php else: $posts = scf_posts_list(false); ?>
<div class="ps-top">
  <a class="btn-p" href="posts.php?new=1"><?= icon('plus') ?> منشور جديد</a>
  <a class="act" href="posts.php?thanks=1">رسالة الشكر</a>
  <a class="act" href="../?edit=1#coverage" target="_blank">تعديل مباشر في الموقع ↗</a>
</div>
<div class="panel">
  <div class="panel-head"><h2>منشورات التغطية (<?= count($posts) ?>)</h2></div>
  <p class="hint">اسحب المنشور من المقبض (⋮⋮) أو استخدم الأسهم لتقديمه أو تأخيره — يُحفظ الترتيب فوراً. زر التفعيل يُظهر المنشور أو يخفيه من الموقع.</p>
  <div class="ps-list" id="psList">
    <?php foreach ($posts as $i => $p): $cover = $p['images'][0] ?? null; ?>
    <div class="ps-item<?= $p['active'] ? '' : ' off' ?>" data-id="<?= (int)$p['id'] ?>" draggable="true">
      <div class="ps-handle"><button type="button" data-mv="-1" title="تقديم">▲</button><span>⋮⋮</span><button type="button" data-mv="1" title="تأخير">▼</button></div>
      <?php if ($cover): ?><img class="ps-thumb" src="<?= e(scf_media_url($cover['thumb'] ?? $cover['file'])) ?>" alt="" loading="lazy"><?php else: ?><span class="ps-thumb"></span><?php endif; ?>
      <div class="ps-info"><b><?= e($p['title']['ar']) ?></b><small dir="ltr"><?= e($p['title']['en']) ?></small>
        <div class="ps-chips"><span class="ps-chip"><?= e($cats[$p['category']] ?? $p['category']) ?></span><span class="ps-chip"><?= count($p['images']) ?> صور</span><?php if ($p['label']['ar']): ?><span class="ps-chip"><?= e($p['label']['ar']) ?></span><?php endif; ?></div></div>
      <div class="ps-acts">
        <label class="sw" title="إظهار/إخفاء"><input type="checkbox" data-toggle<?= $p['active'] ? ' checked' : '' ?>><span></span></label>
        <a class="btn-p btn-s" href="posts.php?edit=<?= (int)$p['id'] ?>"><?= icon('edit') ?> تعديل</a>
        <button type="button" class="act" data-del style="color:#c0392b">حذف</button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$posts): ?><p class="empty">لا توجد منشورات بعد.</p><?php endif; ?>
  </div>
</div>
<script>
(function () {
  var list = document.getElementById('psList'), drag = null;
  function send(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); fd.append('_csrf', window.CSRF); return fetch('posts.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json(); }); }
  function saveOrder() {
    var ids = Array.prototype.map.call(list.querySelectorAll('.ps-item'), function (x) { return x.dataset.id; });
    send({act: 'reorder', ids: JSON.stringify(ids)}).then(function (j) { window.toast(j.ok ? 'حُفظ الترتيب ✓' : 'خطأ', j.ok ? 0 : 1); });
  }
  list.querySelectorAll('.ps-item').forEach(function (it) {
    it.addEventListener('dragstart', function () { drag = it; it.classList.add('dragging'); });
    it.addEventListener('dragend', function () { it.classList.remove('dragging'); list.querySelectorAll('.over').forEach(function (x) { x.classList.remove('over'); }); });
    it.addEventListener('dragover', function (e) { e.preventDefault(); if (drag && drag !== it) it.classList.add('over'); });
    it.addEventListener('dragleave', function () { it.classList.remove('over'); });
    it.addEventListener('drop', function (e) {
      e.preventDefault(); it.classList.remove('over');
      if (!drag || drag === it) return;
      var items = Array.prototype.slice.call(list.children);
      list.insertBefore(drag, items.indexOf(drag) < items.indexOf(it) ? it.nextSibling : it);
      saveOrder();
    });
    it.querySelectorAll('[data-mv]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.dataset.mv === '-1' && it.previousElementSibling) list.insertBefore(it, it.previousElementSibling);
        else if (b.dataset.mv === '1' && it.nextElementSibling) list.insertBefore(it.nextElementSibling, it);
        else return;
        saveOrder();
      });
    });
    it.querySelector('[data-toggle]').addEventListener('change', function () {
      var on = this.checked; it.classList.toggle('off', !on);
      send({act: 'toggle', id: it.dataset.id, v: on ? 1 : 0}).then(function (j) { window.toast(j.ok ? (on ? 'ظاهر في الموقع' : 'مخفي من الموقع') : 'خطأ', j.ok ? 0 : 1); });
    });
    it.querySelector('[data-del]').addEventListener('click', function () {
      if (!confirm('حذف هذا المنشور وصوره نهائياً؟')) return;
      send({act: 'delete', id: it.dataset.id}).then(function (j) { if (j.ok) { it.remove(); window.toast('تم الحذف'); } });
    });
  });
})();
</script>
<?php endif; ?>
<?php admin_footer(); ?>
