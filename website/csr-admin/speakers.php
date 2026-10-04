<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

/* حفظ / حذف */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            clean_text($_POST['name_ar'] ?? '', 160),
            clean_text($_POST['name_en'] ?? '', 160),
            clean_text($_POST['title_ar'] ?? '', 255),
            clean_text($_POST['title_en'] ?? '', 255),
            clean_text($_POST['bio_ar'] ?? '', 2000),
            clean_text($_POST['bio_en'] ?? '', 2000),
            preg_replace('/[^a-zA-Z0-9\/\._-]/', '', (string)($_POST['photo'] ?? '')),
            (int)($_POST['sort'] ?? 0),
            !empty($_POST['active']) ? 1 : 0,
        ];
        if ($data[0] === '' && $data[1] === '') {
            json_out(['ok' => false, 'msg' => 'أدخل الاسم بلغة واحدة على الأقل'], 422);
        }
        if ($id > 0) {
            q('UPDATE speakers SET name_ar=?, name_en=?, title_ar=?, title_en=?, bio_ar=?, bio_en=?, photo=?, sort=?, active=? WHERE id=?',
              array_merge($data, [$id]));
        } else {
            q('INSERT INTO speakers (name_ar,name_en,title_ar,title_en,bio_ar,bio_en,photo,sort,active) VALUES (?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        admin_audit('speaker_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM speakers WHERE id = ?', [$id]);
        admin_audit('speaker_del', 'id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$rows = q_all('SELECT * FROM speakers ORDER BY sort, id');
admin_header('المتحدثون', 'speakers');
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> متحدث</h2>
    <button class="btn-p" id="newBtn">+ متحدث جديد</button>
  </div>
  <div class="ent-grid" id="entGrid">
    <?php foreach ($rows as $r): ?>
    <div class="ent-card <?= $r['active'] ? '' : 'ent-off' ?>" data-ent='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'>
      <div class="ent-photo">
        <?php if ($r['photo']): ?><img src="<?= e(upload_url($r['photo'])) ?>" alt=""><?php else: ?><span><?= icon('mic') ?></span><?php endif; ?>
      </div>
      <b><?= e($r['name_ar'] ?: $r['name_en']) ?></b>
      <small><?= e($r['title_ar'] ?: $r['title_en']) ?></small>
      <div class="ent-actions">
        <button class="btn-s" data-edit>تعديل</button>
        <button class="btn-s btn-danger" data-del>حذف</button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="empty">لا يوجد متحدثون — أضف الأول</p><?php endif; ?>
  </div>
</div>

<div class="modal" id="entModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3 id="entTitle">متحدث</h3>
    <form id="entForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <input type="hidden" name="photo" value="">
      <div class="photo-drop" id="photoDrop">
        <img id="photoPrev" hidden alt="">
        <span id="photoHint"><?= icon('image') ?> اضغط لرفع الصورة</span>
        <input type="file" id="photoFile" accept="image/*" hidden>
      </div>
      <div class="grid2">
        <label>الاسم (عربي)<input name="name_ar" maxlength="160"></label>
        <label>Name (EN)<input name="name_en" maxlength="160" dir="ltr"></label>
        <label>الصفة (عربي)<input name="title_ar" maxlength="255"></label>
        <label>Title (EN)<input name="title_en" maxlength="255" dir="ltr"></label>
      </div>
      <label>نبذة (عربي)<textarea name="bio_ar" rows="2" maxlength="2000"></textarea></label>
      <label>Bio (EN)<textarea name="bio_en" rows="2" maxlength="2000" dir="ltr"></textarea></label>
      <div class="grid2">
        <label>الترتيب<input name="sort" type="number" value="0"></label>
        <label class="chkline"><input type="checkbox" name="active" checked> ظاهر في الموقع</label>
      </div>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>
<script>window.ENT = {api: 'speakers.php', uploadDir: 'speakers'};</script>
<?php admin_footer(); ?>
