<?php
require_once __DIR__ . '/inc/layout.php';
require_super();
require_once dirname(__DIR__) . '/app/whatsapp.php';
$titles = ['dr' => 'الدكتور / Dr.', 'eng' => 'المهندس / Engineer', 'mr' => 'السيد / Mr.', 'mrs' => 'السيدة / Mrs.', 'prof' => 'الأستاذ / Professor', 'other' => 'أخرى / Other'];
$regSectors = json_decode(setting('reg_sectors_ar', '[]'), true) ?: [];
$customFields = registration_custom_fields(true);
$customFieldsHistory = registration_custom_fields(false);
$configuredFields = [];
foreach (registration_fields() as $configuredField) $configuredFields[$configuredField['key']] = $configuredField;
$fieldOn = function (string $key) use ($configuredFields): bool { return !empty($configuredFields[$key]['on']); };
$fieldReq = function (string $key) use ($configuredFields): bool { return !empty($configuredFields[$key]['on']) && !empty($configuredFields[$key]['req']); };
$fieldLabel = function (string $key, string $fallback) use ($configuredFields): string { return trim((string)($configuredFields[$key]['ar'] ?? '')) ?: $fallback; };
$titleFieldOn = $fieldOn('title');

function render_admin_custom_fields(array $fields): void
{
    foreach ($fields as $field) {
        $key = (string)$field['key'];
        $label = trim((string)($field['ar'] ?? '')) ?: trim((string)($field['en'] ?? '')) ?: $key;
        $required = !empty($field['req']);
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'textarea') {
            echo '<label class="admin-custom-wide">' . e($label) . ($required ? ' *' : '') . '<textarea name="' . e($key) . '" rows="3" maxlength="2000"' . ($required ? ' required' : '') . '></textarea></label>';
        } elseif ($type === 'yesno') {
            echo '<label>' . e($label) . ($required ? ' *' : '') . '<select name="' . e($key) . '"' . ($required ? ' required' : '') . '><option value="">—</option><option value="yes">نعم</option><option value="no">لا</option></select></label>';
        } elseif ($type === 'select') {
            echo '<label>' . e($label) . ($required ? ' *' : '') . '<select name="' . e($key) . '" data-custom-other-select="' . e($key) . '"' . ($required ? ' required' : '') . '><option value="">—</option>';
            foreach (registration_field_options($field) as $option) echo '<option value="' . e($option['id']) . '">' . e(registration_option_label($field, $option['id'], 'ar')) . '</option>';
            if (!empty($field['allow_other'])) echo '<option value="__other__">أخرى</option>';
            echo '</select></label>';
            if (!empty($field['allow_other'])) echo '<label class="admin-other-field admin-custom-wide" data-custom-other-wrap="' . e($key) . '" hidden>اكتب الإجابة *<textarea name="' . e($key) . '_other" maxlength="500" rows="2"></textarea></label>';
        } else {
            echo '<label>' . e($label) . ($required ? ' *' : '') . '<input name="' . e($key) . '" maxlength="500"' . ($required ? ' required' : '') . '></label>';
        }
    }
}

/* ---------- الفلاتر ---------- */
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$wa     = ($_GET['wa'] ?? '') !== '' ? (int)$_GET['wa'] : null;
$att    = ($_GET['att'] ?? '') !== '' ? (int)$_GET['att'] : null;
$search = clean_text($_GET['q'] ?? '', 80);
$pg     = max(1, (int)($_GET['pg'] ?? 1));
$per    = 50;

$w = '1=1';
$params = [];
if ($status !== '') { $w .= ' AND status = ?';   $params[] = $status; }
if ($wa !== null)   { $w .= ' AND wa_sent = ?';  $params[] = $wa; }
if ($att !== null)  { $w .= ' AND attended = ?'; $params[] = $att; }
if ($search !== '') {
    $w .= ' AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR code LIKE ? OR org LIKE ? OR title LIKE ? OR sector LIKE ? OR extra LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

$total = (int)q_val("SELECT COUNT(*) FROM registrants WHERE $w", $params);
$pages = max(1, (int)ceil($total / $per));
$pg = min($pg, $pages);
$off = ($pg - 1) * $per;
$rows = q_all("SELECT * FROM registrants WHERE $w ORDER BY id DESC LIMIT $per OFFSET $off", $params);

$counts = [
    ''         => (int)q_val('SELECT COUNT(*) FROM registrants'),
    'pending'  => (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='pending'"),
    'approved' => (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='approved'"),
    'rejected' => (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='rejected'"),
];

/* رسالة واتساب جاهزة لكل مسجّل */
function wa_message(array $r): string
{
    $tpl = setting('wa_template_' . ($r['lang'] === 'en' ? 'en' : 'ar'), setting('wa_template_ar', ''));
    return str_replace(['{name}', '{code}'], [$r['full_name'], $r['code']], $tpl);
}

function qs_keep(array $over = []): string
{
    $qs = array_merge($_GET, $over);
    unset($qs['pg']);
    if (isset($over['pg'])) $qs['pg'] = $over['pg'];
    return '?' . http_build_query(array_filter($qs, function ($v) { return $v !== '' && $v !== null; }));
}

$stMap = ['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض'];

admin_header('المسجّلون', 'registrants');
?>
<div class="filters-bar">
  <div class="ftabs">
    <a class="ftab <?= $status === '' ? 'on' : '' ?>" href="<?= e(qs_keep(['status' => ''])) ?>">الكل <i><?= $counts[''] ?></i></a>
    <a class="ftab <?= $status === 'pending' ? 'on' : '' ?>" href="<?= e(qs_keep(['status' => 'pending'])) ?>">قيد المراجعة <i><?= $counts['pending'] ?></i></a>
    <a class="ftab <?= $status === 'approved' ? 'on' : '' ?>" href="<?= e(qs_keep(['status' => 'approved'])) ?>">المقبولون <i><?= $counts['approved'] ?></i></a>
    <a class="ftab <?= $status === 'rejected' ? 'on' : '' ?>" href="<?= e(qs_keep(['status' => 'rejected'])) ?>">المرفوضون <i><?= $counts['rejected'] ?></i></a>
  </div>
  <form class="fsearch" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input name="q" value="<?= e($search) ?>" placeholder="بحث: اسم / هاتف / بريد / رمز / جهة">
    <button class="btn-s">بحث</button>
  </form>
  <div class="fbtns">
    <a class="fchip <?= $wa === 0 && $status === 'approved' ? 'on' : '' ?>" href="<?= e(qs_keep(['status' => 'approved', 'wa' => $wa === 0 ? '' : '0'])) ?>"><?= icon('whatsapp') ?> لم تُرسل لهم رسالة</a>
    <a class="fchip <?= $att === 1 ? 'on' : '' ?>" href="<?= e(qs_keep(['att' => $att === 1 ? '' : '1'])) ?>"><?= icon('ticket') ?> الحاضرون</a>
  </div>
  <div class="fbtns top-tools" aria-label="أدوات المسجّلين">
    <button class="btn-s tool-btn" id="addReg" title="إضافة يدوية" aria-label="إضافة يدوية"><?= icon('plus') ?><span class="btn-label">إضافة يدوية</span></button>
    <a class="btn-s tool-btn" href="export.php?type=csv<?= $status ? '&status=' . e($status) : '' ?>" title="تنزيل CSV" aria-label="تنزيل CSV"><?= icon('download') ?><span class="btn-label">تنزيل CSV</span></a>
    <a class="btn-s tool-btn" href="checkin.php" title="قاعة المؤتمر" aria-label="قاعة المؤتمر"><?= icon('ticket') ?><span class="btn-label">قاعة المؤتمر</span></a>
  </div>
</div>

<div class="bulk-bar" id="bulkBar" hidden>
  <span id="bulkCount">0</span> محدد
  <button class="btn-s" data-bulk="approve"><?= icon('check') ?> قبول المحدد</button>
  <button class="btn-s" data-bulk="wa_sent"><?= icon('whatsapp') ?> تحديد كمُرسَل</button>
  <button class="btn-s btn-danger" data-bulk="delete"><?= icon('trash') ?> حذف المحدد</button>
</div>

<div class="panel reg-panel">
<div class="tbl-scroll">
<table class="tbl tbl-reg">
  <thead>
    <tr>
      <th class="chk"><input type="checkbox" id="chkAll"></th>
      <th>الرمز</th>
      <th>الاسم</th>
      <th>الهاتف</th>
      <th>الجهة</th>
      <th>الحالة</th>
      <th>واتساب</th>
      <th>التاريخ</th>
      <th class="actions-col">الإجراءات</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr data-id="<?= (int)$r['id'] ?>" data-code="<?= e($r['code']) ?>" data-phone="<?= e($r['phone']) ?>"
        data-name="<?= e($r['full_name']) ?>" data-msg="<?= e(wa_message($r)) ?>">
      <td class="chk" data-label=""><input type="checkbox" class="rowChk" value="<?= (int)$r['id'] ?>"></td>
      <td dir="ltr" class="mono code-cell" data-label="الرمز"><?= e($r['code']) ?></td>
      <td data-label="الاسم">
        <b><?= e($r['full_name']) ?></b>
        <small class="sub"><?= e(trim(($r['title'] ? ($titles[$r['title']] ?? $r['title']) . ' · ' : '') . ($r['gender'] === 'male' ? 'ذكر' : ($r['gender'] === 'female' ? 'أنثى' : '')) . ($r['country'] ? ' · ' . $r['country'] : ''))) ?></small>
        <?php if ($r['email']): ?><small class="sub" dir="ltr"><?= e($r['email']) ?></small><?php endif; ?>
        <?php $extraValues = registration_extra_decode($r['extra'] ?? ''); if ($customFieldsHistory && $extraValues): ?>
          <details class="reg-extra-details"><summary>الحقول الإضافية</summary><div><?php foreach ($customFieldsHistory as $cf): $answer = registration_custom_value_label($cf, $extraValues[$cf['key']] ?? '', 'ar'); if ($answer === '') continue; ?><span><b><?= e($cf['ar'] ?: ($cf['en'] ?? $cf['key'])) ?>:</b> <?= e($answer) ?></span><?php endforeach; ?></div></details>
        <?php endif; ?>
      </td>
      <td dir="ltr" class="mono" data-label="الهاتف">+<?= e($r['phone']) ?></td>
      <td data-label="الجهة">
        <?= e($r['org'] ?: '—') ?>
        <?php if ($r['job']): ?><small class="sub"><?= e($r['job']) ?></small><?php endif; ?>
        <?php if ($r['sector']): ?><small class="sub"><?= e($r['sector']) ?></small><?php endif; ?>
      </td>
      <td data-label="الحالة">
        <span class="badge b-<?= e($r['status']) ?>"><?= e($stMap[$r['status']] ?? $r['status']) ?></span>
        <?php if ($r['attended']): ?><span class="badge b-att">حضر</span><?php endif; ?>
      </td>
      <td data-label="واتساب"><span class="badge <?= $r['wa_sent'] ? 'b-wa-yes' : 'b-wa-no' ?>"><?= $r['wa_sent'] ? 'أُرسلت' : 'لم تُرسل' ?></span></td>
      <td class="mono" data-label="التاريخ"><?= e(date('m/d H:i', strtotime($r['created_at']))) ?></td>
      <td class="row-actions ico-actions" data-label="الإجراءات">
        <?php if ($r['status'] !== 'approved'): ?>
          <button class="act act-ok" data-act="approve" title="قبول"><?= icon('check') ?></button>
        <?php endif; ?>
        <?php if ($r['status'] === 'approved'): ?>
          <button class="act act-wa act-wa-meta" data-act="wa_meta" title="إرسال مباشر عبر Meta WhatsApp API"><?= icon('whatsapp') ?><span class="act-badge">META</span></button>
          <button class="act act-wa act-wa-manual" data-act="wa_open" title="واتساب يدوي: فتح المحادثة مباشرة"><?= icon('whatsapp') ?><span class="act-badge">يدوي</span></button>
          <button class="act" data-act="copy_msg" title="نسخ نص الرسالة"><?= icon('copy') ?></button>
          <button class="act" data-act="qr" title="عرض الباركود"><?= icon('qr') ?></button>
          <button class="act" data-act="badge" title="طباعة/تنزيل الباج"><?= icon('printer') ?></button>
          <button class="act <?= $r['wa_sent'] ? 'act-dim' : '' ?>" data-act="wa_toggle" title="<?= $r['wa_sent'] ? 'وضع كغير مُرسَل' : 'وضع كمُرسَل' ?>"><?= icon('refresh') ?></button>
        <?php endif; ?>
        <?php if ($r['status'] !== 'rejected'): ?>
          <button class="act act-no" data-act="reject" title="رفض"><?= icon('close') ?></button>
        <?php else: ?>
          <button class="act" data-act="pending" title="إعادة للمراجعة"><?= icon('refresh') ?></button>
        <?php endif; ?>
        <button class="act act-edit" data-act="edit" title="تعديل البيانات"><?= icon('edit') ?></button>
        <button class="act" data-act="notes" title="ملاحظات"><?= icon('doc') ?><?php if ($r['notes']): ?><span class="act-dot"></span><?php endif; ?></button>
        <button class="act act-del" data-act="delete" title="حذف"><?= icon('trash') ?></button>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">لا توجد نتائج</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $pg): ?><span class="pg on"><?= $i ?></span>
    <?php else: ?><a class="pg" href="<?= e(qs_keep(['pg' => $i])) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>
</div>

<!-- نافذة الباركود -->
<div class="modal" id="qrModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3 id="qrName">—</h3>
    <p class="mono" dir="ltr" id="qrCodeTxt">—</p>
    <div class="qr-holder"><div id="qrBox"></div></div>
    <div class="modal-actions">
      <button class="btn-p" id="qrCopyImg">⧉ نسخ الباركود</button>
      <button class="btn-s" id="qrDownload"><?= icon('download') ?> تنزيل PNG</button>
      <button class="btn-s" id="qrCopyCode">⧉ نسخ الرمز</button>
    </div>
  </div>
</div>

<!-- نافذة الإضافة اليدوية -->
<div class="modal" id="addModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>إضافة مسجّل يدوياً</h3>
    <form id="addForm" class="modal-form">
      <div class="grid2">
        <?php if ($titleFieldOn): ?>
          <label><?= e($fieldLabel('title', 'اللقب')) ?><?= $fieldReq('title') ? ' *' : '' ?><select name="title" <?= $fieldReq('title') ? 'required' : '' ?> data-other-select="title"><option value="">—</option><?php foreach ($titles as $tv => $tl): ?><option value="<?= e($tv) ?>"><?= e($tl) ?></option><?php endforeach; ?></select></label>
          <label class="admin-other-field" data-other-wrap="title" hidden>اكتب اللقب *<textarea name="title_other" maxlength="120" rows="2"></textarea></label>
        <?php endif; ?>
        <?php if ($fieldOn('full_name')): ?><label><?= e($fieldLabel('full_name', 'الاسم الكامل')) ?><?= $fieldReq('full_name') ? ' *' : '' ?><input name="full_name" <?= $fieldReq('full_name') ? 'required' : '' ?> maxlength="160"></label><?php endif; ?>
        <?php if ($fieldOn('gender')): ?><label><?= e($fieldLabel('gender', 'الجنس')) ?><?= $fieldReq('gender') ? ' *' : '' ?><select name="gender" <?= $fieldReq('gender') ? 'required' : '' ?>><option value="">—</option><option value="male">ذكر</option><option value="female">أنثى</option></select></label><?php endif; ?>
        <?php if ($fieldOn('country')): ?><label><?= e($fieldLabel('country', 'الدولة')) ?><?= $fieldReq('country') ? ' *' : '' ?><input name="country" <?= $fieldReq('country') ? 'required' : '' ?> maxlength="100"></label><?php endif; ?>
        <?php if ($fieldOn('phone')): ?><label><?= e($fieldLabel('phone', 'رقم الواتساب')) ?><?= $fieldReq('phone') ? ' *' : '' ?><input name="phone" <?= $fieldReq('phone') ? 'required' : '' ?> dir="ltr" placeholder="07XXXXXXXXX" maxlength="20"></label><?php endif; ?>
        <?php if ($fieldOn('email')): ?><label><?= e($fieldLabel('email', 'البريد')) ?><?= $fieldReq('email') ? ' *' : '' ?><input name="email" <?= $fieldReq('email') ? 'required' : '' ?> type="email" dir="ltr" maxlength="160"></label><?php endif; ?>
        <?php if ($fieldOn('sector')): ?><label><?= e($fieldLabel('sector', 'القطاع')) ?><?= $fieldReq('sector') ? ' *' : '' ?><select name="sector" <?= $fieldReq('sector') ? 'required' : '' ?> data-other-select="sector"><option value="">—</option><?php foreach ($regSectors as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select></label>
        <label class="admin-other-field" data-other-wrap="sector" hidden>اكتب قطاع العمل *<textarea name="sector_other" maxlength="120" rows="3"></textarea></label><?php endif; ?>
        <?php if ($fieldOn('org')): ?><label><?= e($fieldLabel('org', 'الجهة / المؤسسة')) ?><?= $fieldReq('org') ? ' *' : '' ?><input name="org" <?= $fieldReq('org') ? 'required' : '' ?> maxlength="200"></label><?php endif; ?>
        <?php if ($fieldOn('job')): ?><label><?= e($fieldLabel('job', 'المسمى الوظيفي')) ?><?= $fieldReq('job') ? ' *' : '' ?><input name="job" <?= $fieldReq('job') ? 'required' : '' ?> maxlength="200"></label><?php endif; ?>
        <?php render_admin_custom_fields($customFields); ?>
      </div>
      <label class="chkline"><input type="checkbox" name="approve" value="1" checked> قبول مباشر</label>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>

<!-- نافذة الملاحظات -->
<div class="modal" id="notesModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>ملاحظات</h3>
    <form id="notesForm" class="modal-form">
      <textarea name="notes" rows="5" maxlength="2000"></textarea>
      <button class="btn-p btn-block">حفظ</button>
    </form>
  </div>
</div>

<!-- نافذة تعديل بيانات المسجّل -->
<div class="modal" id="editModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3><?= icon('edit') ?> تعديل بيانات المسجّل</h3>
    <form id="editForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <div class="grid2">
        <?php if ($titleFieldOn): ?>
          <label><?= e($fieldLabel('title', 'اللقب')) ?><?= $fieldReq('title') ? ' *' : '' ?><select name="title" <?= $fieldReq('title') ? 'required' : '' ?> data-other-select="title"><option value="">—</option><?php foreach ($titles as $tv => $tl): ?><option value="<?= e($tv) ?>"><?= e($tl) ?></option><?php endforeach; ?></select></label>
          <label class="admin-other-field" data-other-wrap="title" hidden>اكتب اللقب *<textarea name="title_other" maxlength="120" rows="2"></textarea></label>
        <?php endif; ?>
        <?php if ($fieldOn('full_name')): ?><label><?= e($fieldLabel('full_name', 'الاسم الكامل')) ?><?= $fieldReq('full_name') ? ' *' : '' ?><input name="full_name" <?= $fieldReq('full_name') ? 'required' : '' ?> maxlength="160"></label><?php endif; ?>
        <?php if ($fieldOn('phone')): ?><label><?= e($fieldLabel('phone', 'رقم الواتساب')) ?><?= $fieldReq('phone') ? ' *' : '' ?><input name="phone" <?= $fieldReq('phone') ? 'required' : '' ?> dir="ltr" maxlength="20"></label><?php endif; ?>
        <?php if ($fieldOn('email')): ?><label><?= e($fieldLabel('email', 'البريد')) ?><?= $fieldReq('email') ? ' *' : '' ?><input name="email" <?= $fieldReq('email') ? 'required' : '' ?> type="email" dir="ltr" maxlength="160"></label><?php endif; ?>
        <?php if ($fieldOn('gender')): ?><label><?= e($fieldLabel('gender', 'الجنس')) ?><?= $fieldReq('gender') ? ' *' : '' ?><select name="gender" <?= $fieldReq('gender') ? 'required' : '' ?>><option value="">—</option><option value="male">ذكر</option><option value="female">أنثى</option></select></label><?php endif; ?>
        <?php if ($fieldOn('country')): ?><label><?= e($fieldLabel('country', 'الدولة')) ?><?= $fieldReq('country') ? ' *' : '' ?><input name="country" <?= $fieldReq('country') ? 'required' : '' ?> maxlength="100"></label><?php endif; ?>
        <?php if ($fieldOn('sector')): ?><label><?= e($fieldLabel('sector', 'القطاع')) ?><?= $fieldReq('sector') ? ' *' : '' ?><select name="sector" <?= $fieldReq('sector') ? 'required' : '' ?> data-other-select="sector"><option value="">—</option><?php foreach ($regSectors as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select></label>
        <label class="admin-other-field" data-other-wrap="sector" hidden>اكتب قطاع العمل *<textarea name="sector_other" maxlength="120" rows="3"></textarea></label><?php endif; ?>
        <?php if ($fieldOn('org')): ?><label><?= e($fieldLabel('org', 'الجهة / المؤسسة')) ?><?= $fieldReq('org') ? ' *' : '' ?><input name="org" <?= $fieldReq('org') ? 'required' : '' ?> maxlength="200"></label><?php endif; ?>
        <?php if ($fieldOn('job')): ?><label><?= e($fieldLabel('job', 'المسمى الوظيفي')) ?><?= $fieldReq('job') ? ' *' : '' ?><input name="job" <?= $fieldReq('job') ? 'required' : '' ?> maxlength="200"></label><?php endif; ?>
        <?php render_admin_custom_fields($customFields); ?>
      </div>
      <button class="btn-p btn-block"><?= icon('save') ?> حفظ التعديلات</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
