<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

$kinds = ['organizer' => 'الجهات المنظمة', 'partner' => 'الجهات الداعمة وضيوف المنتدى', 'support' => 'بدعم من', 'sponsor' => 'الرعاة'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $kind = isset($kinds[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'organizer';
        $url = clean_text($_POST['url'] ?? '', 255);
        if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
        $logo = (string)($_POST['logo'] ?? '');
        if (strpos($logo, 'assets:') !== 0) {
            $logo = preg_replace('/[^a-zA-Z0-9\/\._-]/', '', $logo);
        }
        $data = [
            $kind,
            clean_text($_POST['name_ar'] ?? '', 200),
            clean_text($_POST['name_en'] ?? '', 200),
            clean_text($_POST['desc_ar'] ?? '', 2000),
            clean_text($_POST['desc_en'] ?? '', 2000),
            clean_text($_POST['tier'] ?? '', 30),
            $url,
            $logo,
            (int)($_POST['sort'] ?? 0),
            !empty($_POST['active']) ? 1 : 0,
        ];
        if ($data[1] === '' && $data[2] === '') json_out(['ok' => false, 'msg' => 'أدخل الاسم'], 422);
        if ($id > 0) {
            q('UPDATE orgs SET kind=?, name_ar=?, name_en=?, desc_ar=?, desc_en=?, tier=?, url=?, logo=?, sort=?, active=? WHERE id=?',
              array_merge($data, [$id]));
        } else {
            q('INSERT INTO orgs (kind,name_ar,name_en,desc_ar,desc_en,tier,url,logo,sort,active) VALUES (?,?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        admin_audit('org_save', 'id=' . $id);
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM orgs WHERE id = ?', [$id]);
        admin_audit('org_del', 'id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$kind = isset($kinds[$_GET['kind'] ?? '']) ? $_GET['kind'] : 'organizer';
$rows = q_all('SELECT * FROM orgs WHERE kind = ? ORDER BY sort, id', [$kind]);

function org_logo_admin(array $o): string
{
    $l = $o['logo'];
    if (strpos($l, 'assets:') === 0) return asset('img/' . substr($l, 7));
    if ($l !== '') return upload_url($l);
    return '';
}

admin_header('الشركاء والرعاة', 'orgs');
?>
<div class="filters-bar">
  <div class="ftabs">
    <?php foreach ($kinds as $k => $lbl): ?>
      <a class="ftab <?= $k === $kind ? 'on' : '' ?>" href="?kind=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
  <div class="fbtns"><button class="btn-p" id="newBtn">+ إضافة <?= e(mb_substr($kinds[$kind], 0, 20)) ?></button></div>
</div>

<div class="panel">
  <div class="ent-grid" id="entGrid">
    <?php foreach ($rows as $r): ?>
    <div class="ent-card <?= $r['active'] ? '' : 'ent-off' ?>" data-ent='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'>
      <div class="ent-photo ent-logo">
        <?php $lg = org_logo_admin($r); ?>
        <?php if ($lg): ?><img src="<?= e($lg) ?>" alt=""><?php else: ?><span><?= icon('building') ?></span><?php endif; ?>
      </div>
      <b><?= e($r['name_ar'] ?: $r['name_en']) ?></b>
      <?php if ($r['tier']): ?><small><?= e($r['tier']) ?></small><?php endif; ?>
      <div class="ent-actions">
        <button class="btn-s" data-edit>تعديل</button>
        <button class="btn-s btn-danger" data-del>حذف</button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="empty">القائمة فارغة — أضف أول عنصر</p><?php endif; ?>
  </div>
</div>

<div class="modal" id="entModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3 id="entTitle"><?= e($kinds[$kind]) ?></h3>
    <form id="entForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <input type="hidden" name="kind" value="<?= e($kind) ?>">
      <input type="hidden" name="photo" value="">
      <div class="photo-drop" id="photoDrop">
        <img id="photoPrev" hidden alt="">
        <span id="photoHint"><?= icon('image') ?> اضغط لرفع الشعار</span>
        <input type="file" id="photoFile" accept="image/*" hidden>
      </div>
      <div class="grid2">
        <label>الاسم (عربي)<input name="name_ar" maxlength="200"></label>
        <label>Name (EN)<input name="name_en" maxlength="200" dir="ltr"></label>
        <?php if ($kind === 'sponsor'): ?>
        <label>فئة الرعاية
          <select name="tier">
            <option value="">—</option>
            <option value="بلاتيني">بلاتيني</option>
            <option value="ذهبي">ذهبي</option>
            <option value="فضي">فضي</option>
            <option value="برونزي">برونزي</option>
            <option value="إعلامي">شريك إعلامي</option>
          </select>
        </label>
        <?php else: ?>
        <input type="hidden" name="tier" value="">
        <?php endif; ?>
        <label>رابط الموقع<input name="url" dir="ltr" maxlength="255" placeholder="https://"></label>
        <label>الترتيب<input name="sort" type="number" value="0"></label>
        <label class="chkline"><input type="checkbox" name="active" checked> ظاهر في الموقع</label>
      </div>
      <label>نبذة (عربي)<textarea name="desc_ar" rows="2" maxlength="2000"></textarea></label>
      <label>Description (EN)<textarea name="desc_en" rows="2" maxlength="2000" dir="ltr"></textarea></label>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>
<script>window.ENT = {api: 'orgs.php', uploadDir: 'orgs', logoField: 'logo'};</script>
<?php admin_footer(); ?>
