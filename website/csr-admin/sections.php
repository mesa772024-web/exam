<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

/* ترتيب/إظهار الأقسام + إدارة الأقسام المخصّصة */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'save') {
        $incoming = json_decode((string)($_POST['order'] ?? '[]'), true);
        if (!is_array($incoming)) json_out(['ok' => false], 422);
        $current = json_decode(setting('sections_order', '[]'), true) ?: [];
        $byKey = [];
        foreach ($current as $c) $byKey[$c['key']] = $c;
        $out = [];
        foreach ($incoming as $it) {
            $k = $it['key'] ?? '';
            $on = !empty($it['on']) ? 1 : 0;
            if (strpos($k, 'custom:') === 0) {
                $out[] = ['key' => $k, 'ar' => 'قسم مخصّص', 'en' => 'Custom', 'on' => $on];
            } elseif (isset($byKey[$k])) {
                $row = $byKey[$k];
                $row['on'] = $on;
                $out[] = $row;
            }
        }
        if ($out) setting_set('sections_order', json_encode($out, JSON_UNESCAPED_UNICODE));
        admin_audit('sections_save');
        json_out(['ok' => true]);
    }

    if ($act === 'cs_save') {
        $id = (int)($_POST['id'] ?? 0);
        $type = in_array($_POST['type'] ?? '', ['text', 'html', 'carousel'], true) ? $_POST['type'] : 'text';
        $data = [
            $type,
            clean_text($_POST['title_ar'] ?? '', 200),
            clean_text($_POST['title_en'] ?? '', 200),
            clean_text($_POST['body_ar'] ?? '', 20000),
            clean_text($_POST['body_en'] ?? '', 20000),
            mb_substr(str_replace("\x00", '', (string)($_POST['raw_code'] ?? '')), 0, 200000),
            (string)($_POST['data'] ?? ''),
            in_array($_POST['bg'] ?? '', ['light', 'white', 'dark'], true) ? $_POST['bg'] : 'light',
            !empty($_POST['active']) ? 1 : 0,
        ];
        // تحقّق من صحة JSON للبيانات
        if ($data[6] !== '' && json_decode($data[6]) === null) $data[6] = '';
        if ($id > 0) {
            q('UPDATE custom_sections SET type=?, title_ar=?, title_en=?, body_ar=?, body_en=?, raw_code=?, data=?, bg=?, active=? WHERE id=?', array_merge($data, [$id]));
        } else {
            q('INSERT INTO custom_sections (type,title_ar,title_en,body_ar,body_en,raw_code,data,bg,active) VALUES (?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
            // أضِف للترتيب
            $order = json_decode(setting('sections_order', '[]'), true) ?: [];
            $order[] = ['key' => 'custom:' . $id, 'ar' => 'قسم مخصّص', 'en' => 'Custom', 'on' => 1];
            setting_set('sections_order', json_encode($order, JSON_UNESCAPED_UNICODE));
        }
        admin_audit('custom_section_save', 'id=' . $id . ' type=' . $type);
        json_out(['ok' => true, 'id' => $id]);
    }

    if ($act === 'cs_del') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM custom_sections WHERE id = ?', [$id]);
        $order = json_decode(setting('sections_order', '[]'), true) ?: [];
        $order = array_values(array_filter($order, function ($s) use ($id) { return ($s['key'] ?? '') !== 'custom:' . $id; }));
        setting_set('sections_order', json_encode($order, JSON_UNESCAPED_UNICODE));
        admin_audit('custom_section_del', 'id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$order = json_decode(setting('sections_order', '[]'), true) ?: [];
$customRows = q_all('SELECT * FROM custom_sections ORDER BY id');
$customMap = [];
foreach ($customRows as $c) $customMap[(int)$c['id']] = $c;
$typeLabels = ['text' => 'نص', 'html' => 'HTML كامل', 'carousel' => 'كاروسيل صور'];

admin_header('أقسام الموقع', 'sections');
?>
<div class="panel">
  <div class="panel-head">
    <h2>ترتيب وإظهار الأقسام</h2>
    <div class="fbtns">
      <button class="btn-s" id="newCs"><?= icon('plus') ?> قسم مخصّص جديد</button>
      <button class="btn-p" id="saveSecs"><?= icon('save') ?> حفظ الترتيب</button>
    </div>
  </div>
  <p class="hint">اسحب لإعادة الترتيب، وأطفئ أي قسم لإخفائه. الأقسام المخصّصة (نص/HTML/كاروسيل) يمكن تعديل محتواها. القسم المدمج الفارغ يُخفى تلقائياً — لتعديل نصوصه استخدم «محتوى الموقع».</p>
  <ul class="sec-list" id="secList">
    <?php foreach ($order as $s):
      $k = $s['key'];
      $isCustom = strpos($k, 'custom:') === 0;
      $csId = $isCustom ? (int)substr($k, 7) : 0;
      if ($isCustom && !isset($customMap[$csId])) continue;
      $cs = $isCustom ? $customMap[$csId] : null;
      $label = $isCustom ? ($cs['title_ar'] ?: 'قسم مخصّص') : ($s['ar'] ?? $k);
    ?>
      <li class="sec-row<?= $isCustom ? ' sec-custom' : '' ?>" data-key="<?= e($k) ?>">
        <span class="sec-drag"><?= icon('menu') ?></span>
        <span class="sec-name">
          <?= e($label) ?>
          <?php if ($isCustom): ?><small class="cs-badge"><?= e($typeLabels[$cs['type']] ?? $cs['type']) ?></small>
          <?php else: ?><small class="sub" dir="ltr"><?= e($s['en'] ?? '') ?></small><?php endif; ?>
        </span>
        <div class="sec-ctl">
          <?php if ($isCustom): ?>
            <button class="mini" data-cs-edit="<?= $csId ?>" title="تعديل"><?= icon('edit') ?></button>
            <button class="mini mini-del" data-cs-del="<?= $csId ?>" title="حذف"><?= icon('trash') ?></button>
          <?php else: ?>
            <a class="mini" href="content.php" title="تعديل المحتوى"><?= icon('edit') ?></a>
          <?php endif; ?>
          <label class="switch"><input type="checkbox" class="sec-on" <?= !empty($s['on']) ? 'checked' : '' ?>><span></span></label>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- نافذة القسم المخصّص -->
<div class="modal" id="csModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3 id="csTitle">قسم مخصّص</h3>
    <form id="csForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <div class="grid2">
        <label>نوع القسم
          <select name="type" id="csType">
            <option value="text">نص</option>
            <option value="html">كود HTML/CSS/JS</option>
            <option value="carousel">كاروسيل صور</option>
          </select>
        </label>
        <label>خلفية القسم
          <select name="bg"><option value="light">فاتحة</option><option value="white">بيضاء</option><option value="dark">داكنة</option></select>
        </label>
        <label>العنوان (عربي)<input name="title_ar" maxlength="200"></label>
        <label>Title (EN)<input name="title_en" maxlength="200" dir="ltr"></label>
      </div>

      <div class="cs-type cs-type-text">
        <label>النص (عربي)<textarea name="body_ar" rows="5"></textarea></label>
        <label>Text (EN)<textarea name="body_en" rows="5" dir="ltr"></textarea></label>
      </div>

      <div class="cs-type cs-type-html" hidden>
        <label>ارتفاع الإطار (بكسل)<input name="cs_height" type="number" value="560" min="120" max="2000" style="max-width:180px"></label>
        <label>كود HTML / CSS / JS<textarea name="raw_code" rows="10" dir="ltr" spellcheck="false" style="font-family:Consolas,monospace"></textarea></label>
      </div>

      <div class="cs-type cs-type-carousel" hidden>
        <p class="hint">أضف صور الكاروسيل:</p>
        <div class="cs-imgs" id="csImgs"></div>
        <button type="button" class="btn-s" id="csAddImg"><?= icon('upload') ?> رفع صورة</button>
        <input type="file" id="csImgFile" accept="image/*" hidden>
      </div>

      <input type="hidden" name="data" id="csData" value="">
      <label class="chkline"><input type="checkbox" name="active" checked> ظاهر في الموقع</label>
      <button class="btn-p btn-block"><?= icon('save') ?> حفظ القسم</button>
    </form>
  </div>
</div>

<style>
.sec-list{list-style:none;display:flex;flex-direction:column;gap:8px}
.sec-row{display:flex;align-items:center;gap:14px;background:#fbfcfe;border:1px solid var(--line);border-radius:12px;padding:12px 16px}
.sec-row.sec-custom{background:#f4faff;border-color:#cfe6f7}
.sec-row.dragging{opacity:.5}
.sec-drag{color:#aab4c8;cursor:grab;display:grid;place-items:center}
.sec-name{flex:1;font-weight:800;display:flex;align-items:center;gap:8px}
.cs-badge{font-size:10.5px;background:var(--sky);color:#fff;padding:2px 9px;border-radius:999px;font-weight:800}
.sec-ctl{display:flex;align-items:center;gap:8px}
.mini{width:36px;height:36px;border-radius:9px;border:1.5px solid var(--line);background:#fff;display:grid;place-items:center;color:var(--muted)}
.mini:hover{border-color:var(--sky);color:var(--steel)}
.mini-del:hover{border-color:#e0455e;color:#c92a45}
.switch{position:relative;width:46px;height:26px;flex:none}
.switch input{position:absolute;opacity:0}
.switch span{position:absolute;inset:0;background:#cdd6e4;border-radius:999px;transition:.25s;cursor:pointer}
.switch span::after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.25s}
.switch input:checked+span{background:var(--sky)}
.switch input:checked+span::after{inset-inline-start:23px}
.cs-imgs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.cs-img{position:relative;width:90px;height:64px;border-radius:8px;overflow:hidden;border:1px solid var(--line)}
.cs-img img{width:100%;height:100%;object-fit:cover}
.cs-img button{position:absolute;top:2px;inset-inline-end:2px;width:20px;height:20px;border:0;border-radius:6px;background:rgba(201,42,69,.9);color:#fff;font-size:12px;cursor:pointer}
</style>
<script>
window.CUSTOM_SECTIONS = <?= json_encode(array_map(function ($c) {
    return [
        'id' => (int)$c['id'], 'type' => $c['type'], 'title_ar' => $c['title_ar'], 'title_en' => $c['title_en'],
        'body_ar' => $c['body_ar'], 'body_en' => $c['body_en'], 'raw_code' => $c['raw_code'],
        'data' => $c['data'], 'bg' => $c['bg'], 'active' => (int)$c['active'],
    ];
}, $customMap), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/sections.js?v=1"></script>
<?php admin_footer(); ?>
