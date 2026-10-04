<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $img = preg_replace('/[^a-zA-Z0-9\/\._-]/', '', (string)($_POST['image'] ?? ''));
        if ($img === '') json_out(['ok' => false, 'msg' => 'ارفع صورة أولاً'], 422);
        $data = [$img, clean_text($_POST['cap_ar'] ?? '', 255), clean_text($_POST['cap_en'] ?? '', 255), (int)($_POST['sort'] ?? 0), !empty($_POST['active']) ? 1 : 0];
        if ($id > 0) {
            q('UPDATE gallery SET image=?, cap_ar=?, cap_en=?, sort=?, active=? WHERE id=?', array_merge($data, [$id]));
        } else {
            q('INSERT INTO gallery (image, cap_ar, cap_en, sort, active) VALUES (?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        admin_audit('gallery_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($act === 'del') {
        q('DELETE FROM gallery WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        admin_audit('gallery_del', 'id=' . (int)$_POST['id']);
        json_out(['ok' => true]);
    }
    if ($act === 'settings') {
        setting_set('gallery_title_ar', clean_text($_POST['gallery_title_ar'] ?? '', 120));
        setting_set('gallery_title_en', clean_text($_POST['gallery_title_en'] ?? '', 120));
        setting_set('gallery_autoplay', (string)max(0, min(30, (int)($_POST['gallery_autoplay'] ?? 5))));
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$rows = q_all('SELECT * FROM gallery ORDER BY sort, id');
admin_header('معرض الصور', 'gallery');
?>
<form class="panel cform-inline" id="gSettings">
  <div class="panel-head"><h2>إعدادات السلايدر</h2><button class="btn-p"><?= icon('save') ?> حفظ</button></div>
  <div class="grid2">
    <label>عنوان القسم (عربي)<input name="gallery_title_ar" value="<?= e(setting('gallery_title_ar')) ?>"></label>
    <label>Section title (EN)<input name="gallery_title_en" dir="ltr" value="<?= e(setting('gallery_title_en')) ?>"></label>
    <label>مدة التبديل التلقائي (ثانية، 0 = إيقاف)<input name="gallery_autoplay" type="number" min="0" max="30" value="<?= e(setting('gallery_autoplay', '5')) ?>"></label>
  </div>
</form>

<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> صورة</h2>
    <button class="btn-p" id="newBtn"><?= icon('plus') ?> صورة جديدة</button>
  </div>
  <p class="hint">تظهر الصور في سلايدر متحرك بالصفحة الرئيسية. رتّبها بالرقم (الأصغر أولاً).</p>
  <div class="ent-grid" id="entGrid">
    <?php foreach ($rows as $r): ?>
    <div class="ent-card <?= $r['active'] ? '' : 'ent-off' ?>" data-ent='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'>
      <div class="ent-photo ent-wide"><img src="<?= e(upload_url($r['image'])) ?>" alt=""></div>
      <b><?= e($r['cap_ar'] ?: '—') ?></b>
      <small>ترتيب: <?= (int)$r['sort'] ?></small>
      <div class="ent-actions">
        <button class="btn-s" data-edit><?= icon('edit') ?> تعديل</button>
        <button class="btn-s btn-danger" data-del><?= icon('trash') ?></button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="empty">لا صور بعد — أضف أول صورة للسلايدر</p><?php endif; ?>
  </div>
</div>

<div class="modal" id="entModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>صورة السلايدر</h3>
    <form id="entForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <input type="hidden" name="image" value="">
      <div class="photo-drop photo-drop-wide" id="photoDrop">
        <img id="photoPrev" hidden alt="">
        <span id="photoHint"><?= icon('image') ?> اضغط لرفع الصورة</span>
        <input type="file" id="photoFile" accept="image/*" hidden>
      </div>
      <div class="grid2">
        <label>تعليق (عربي)<input name="cap_ar" maxlength="255"></label>
        <label>Caption (EN)<input name="cap_en" maxlength="255" dir="ltr"></label>
        <label>الترتيب<input name="sort" type="number" value="0"></label>
        <label class="chkline"><input type="checkbox" name="active" checked> ظاهرة</label>
      </div>
      <button class="btn-p btn-block"><?= icon('save') ?> حفظ</button>
    </form>
  </div>
</div>
<script>
document.getElementById('gSettings').addEventListener('submit', function(ev){
  ev.preventDefault(); var fd=new FormData(this); fd.append('act','settings');
  window.apost('gallery.php', fd, function(j){ window.toast(j.ok?'تم الحفظ ✓':'خطأ', j.ok?0:1); });
});
(function(){
  var modal=document.getElementById('entModal'), form=document.getElementById('entForm');
  function fill(d){
    form.id.value=d?d.id:0; form.image.value=d?d.image:''; form.cap_ar.value=d?d.cap_ar:''; form.cap_en.value=d?d.cap_en:'';
    form.sort.value=d?d.sort:0; form.active.checked=d?!!+d.active:true;
    var p=document.getElementById('photoPrev'), h=document.getElementById('photoHint');
    if(d&&d.image){p.src='../uploads/'+d.image;p.hidden=false;h.hidden=true;}else{p.hidden=true;h.hidden=false;}
  }
  document.getElementById('newBtn').addEventListener('click',function(){fill(null);modal.hidden=false;});
  document.querySelectorAll('.ent-card').forEach(function(c){
    var d=JSON.parse(c.getAttribute('data-ent'));
    c.querySelector('[data-edit]').addEventListener('click',function(){fill(d);modal.hidden=false;});
    c.querySelector('[data-del]').addEventListener('click',function(){
      if(!confirm('حذف الصورة؟'))return;
      window.apost('gallery.php',{act:'del',id:d.id},function(j){if(j.ok)c.remove();});
    });
  });
  form.addEventListener('submit',function(ev){
    ev.preventDefault(); var fd=new FormData(form); fd.append('act','save');
    window.apost('gallery.php',fd,function(j){if(j.ok)location.reload();else window.toast(j.msg||'خطأ',1);});
  });
  var pd=document.getElementById('photoDrop'), pf=document.getElementById('photoFile');
  pd.addEventListener('click',function(){pf.click();});
  pf.addEventListener('change',function(){
    if(!pf.files.length)return;
    var fd=new FormData(); fd.append('file',pf.files[0]); fd.append('dir','gallery');
    window.apost('upload.php',fd,function(j){
      if(!j.ok){window.toast(j.msg||'فشل الرفع',1);return;}
      form.image.value=j.path; var p=document.getElementById('photoPrev');
      p.src=j.url; p.hidden=false; document.getElementById('photoHint').hidden=true;
    });
    pf.value='';
  });
})();
</script>
<?php admin_footer(); ?>
