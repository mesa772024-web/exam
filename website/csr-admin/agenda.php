<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'day_save') {
        $id = (int)($_POST['id'] ?? 0);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['day_date'] ?? '') ? $_POST['day_date'] : null;
        if ($date === null) json_out(['ok' => false, 'msg' => 'حدد تاريخ اليوم'], 422);
        if ($id === 0 && (int)q_val('SELECT COUNT(*) FROM agenda_days') >= 2) json_out(['ok' => false, 'msg' => 'المؤتمر محدد بيومين فقط'], 422);
        $data = [
            $date,
            clean_text($_POST['title_ar'] ?? '', 200),
            clean_text($_POST['title_en'] ?? '', 200),
            clean_text($_POST['sub_ar'] ?? '', 255),
            clean_text($_POST['sub_en'] ?? '', 255),
            (int)($_POST['sort'] ?? 0),
        ];
        if ($id > 0) {
            q('UPDATE agenda_days SET day_date=?, title_ar=?, title_en=?, sub_ar=?, sub_en=?, sort=? WHERE id=?', array_merge($data, [$id]));
        } else {
            q('INSERT INTO agenda_days (day_date,title_ar,title_en,sub_ar,sub_en,sort) VALUES (?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        conference_sync_countdown_target();
        admin_audit('agenda_day_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($act === 'day_del') {
        q('DELETE FROM agenda_days WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        conference_sync_countdown_target();
        admin_audit('agenda_day_del', 'id=' . (int)$_POST['id']);
        json_out(['ok' => true]);
    }
    if ($act === 'item_save') {
        $id = (int)($_POST['id'] ?? 0);
        $dayId = (int)($_POST['day_id'] ?? 0);
        if (q_one('SELECT id FROM agenda_days WHERE id = ?', [$dayId]) === null) {
            json_out(['ok' => false, 'msg' => 'اليوم غير موجود'], 422);
        }
        $data = [
            $dayId,
            clean_text($_POST['time_txt'] ?? '', 40),
            clean_text($_POST['title_ar'] ?? '', 255),
            clean_text($_POST['title_en'] ?? '', 255),
            clean_text($_POST['desc_ar'] ?? '', 2000),
            clean_text($_POST['desc_en'] ?? '', 2000),
            (int)($_POST['sort'] ?? 0),
        ];
        if ($data[2] === '' && $data[3] === '') json_out(['ok' => false, 'msg' => 'أدخل العنوان'], 422);
        if ($id > 0) {
            q('UPDATE agenda_items SET day_id=?, time_txt=?, title_ar=?, title_en=?, desc_ar=?, desc_en=?, sort=? WHERE id=?', array_merge($data, [$id]));
        } else {
            q('INSERT INTO agenda_items (day_id,time_txt,title_ar,title_en,desc_ar,desc_en,sort) VALUES (?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        admin_audit('agenda_item_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($act === 'item_del') {
        q('DELETE FROM agenda_items WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        admin_audit('agenda_item_del', 'id=' . (int)$_POST['id']);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$days = q_all('SELECT * FROM agenda_days ORDER BY sort, id');
foreach ($days as &$d) {
    $d['items'] = q_all('SELECT * FROM agenda_items WHERE day_id = ? ORDER BY sort, id', [$d['id']]);
}
unset($d);

admin_header('الأجندة', 'agenda');
?>
<div class="panel-head standalone">
  <div><h2>أيام المؤتمر: <?= count($days) ?> من 2</h2><p class="hint">التواريخ هنا تظهر تلقائياً في الصفحة الرئيسية وصفحة التسجيل، واليوم الأول يضبط العدّ التنازلي.</p></div>
  <?php if (count($days) < 2): ?><button class="btn-p" id="newDayBtn">+ إضافة يوم</button><?php endif; ?>
</div>

<?php foreach ($days as $d): ?>
<div class="panel day-panel-adm" data-day='<?= e(json_encode(array_diff_key($d, ['items' => 1]), JSON_UNESCAPED_UNICODE)) ?>'>
  <div class="panel-head">
    <h2><?= e($d['title_ar']) ?> <small class="sub"><?= e($d['sub_ar']) ?> · <?= e($d['day_date']) ?></small></h2>
    <div class="fbtns">
      <button class="btn-s" data-day-edit>تعديل اليوم</button>
      <button class="btn-s" data-item-new data-day-id="<?= (int)$d['id'] ?>">+ فقرة</button>
      <button class="btn-s btn-danger" data-day-del>حذف اليوم</button>
    </div>
  </div>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th style="width:90px">الوقت</th><th>الفقرة</th><th>الوصف</th><th style="width:80px">الترتيب</th><th style="width:150px"></th></tr></thead>
    <tbody>
      <?php foreach ($d['items'] as $it): ?>
      <tr data-item='<?= e(json_encode($it, JSON_UNESCAPED_UNICODE)) ?>'>
        <td class="mono"><?= e($it['time_txt'] ?: '—') ?></td>
        <td><b><?= e($it['title_ar'] ?: $it['title_en']) ?></b></td>
        <td class="sub"><?= e(mb_substr($it['desc_ar'] ?: $it['desc_en'], 0, 70)) ?></td>
        <td class="mono"><?= (int)$it['sort'] ?></td>
        <td class="row-actions">
          <button class="act" data-item-edit><?= icon('edit') ?> تعديل</button>
          <button class="act act-del" data-item-del><?= icon('trash') ?></button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$d['items']): ?><tr><td colspan="5" class="empty">لا فقرات بعد</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; ?>

<!-- نافذة اليوم -->
<div class="modal" id="dayModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>بيانات اليوم</h3>
    <form id="dayForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <div class="grid2">
        <label>العنوان (عربي)<input name="title_ar" maxlength="200"></label>
        <label>Title (EN)<input name="title_en" maxlength="200" dir="ltr"></label>
        <label>الوصف (عربي)<input name="sub_ar" maxlength="255"></label>
        <label>Subtitle (EN)<input name="sub_en" maxlength="255" dir="ltr"></label>
        <label>التاريخ *<input name="day_date" type="date" required></label>
        <label>الترتيب<input name="sort" type="number" value="0"></label>
      </div>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>

<!-- نافذة الفقرة -->
<div class="modal" id="itemModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>فقرة الأجندة</h3>
    <form id="itemForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <input type="hidden" name="day_id" value="0">
      <div class="grid2">
        <label>الوقت (مثال 10:30)<input name="time_txt" maxlength="40" dir="ltr"></label>
        <label>الترتيب<input name="sort" type="number" value="0"></label>
        <label>العنوان (عربي)<input name="title_ar" maxlength="255"></label>
        <label>Title (EN)<input name="title_en" maxlength="255" dir="ltr"></label>
      </div>
      <label>الوصف (عربي)<textarea name="desc_ar" rows="2" maxlength="2000"></textarea></label>
      <label>Description (EN)<textarea name="desc_en" rows="2" maxlength="2000" dir="ltr"></textarea></label>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
