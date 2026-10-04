<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

function regform_option_lines($value): array
{
    $lines = preg_split('/\R/u', (string)$value) ?: [];
    return array_slice(array_map(function ($line) { return clean_text($line, 160); }, $lines), 0, 50);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save_fields') {
        $incoming = json_decode((string)($_POST['fields'] ?? '[]'), true);
        if (!is_array($incoming)) json_out(['ok' => false], 422);
        $current = json_decode(setting('reg_fields', '[]'), true) ?: [];
        $byKey = [];
        foreach ($current as $c) $byKey[$c['key']] = $c;
        $out = [];
        foreach ($incoming as $it) {
            $k = $it['key'] ?? '';
            if (!isset($byKey[$k])) continue;
            $row = $byKey[$k];
            $row['lock'] = 0;
            $row['on']  = !empty($it['on']) ? 1 : 0;
            $row['req'] = !empty($it['req']) ? 1 : 0;
            if ($row['on'] === 0) $row['req'] = 0;
            $out[] = $row;
        }
        foreach ($current as $row) {
            if (registration_field_is_custom($row) && !empty($row['deleted'])) $out[] = $row;
        }
        if ($out) setting_set('reg_fields', json_encode($out, JSON_UNESCAPED_UNICODE));
        admin_audit('regform_fields_save');
        json_out(['ok' => true]);
    }
    if ($act === 'field_save') {
        $fields = registration_fields();
        $key = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['key'] ?? ''));
        $found = null;
        foreach ($fields as $index => $field) {
            if (($field['key'] ?? '') === $key) { $found = $index; break; }
        }
        $isBuiltin = $found !== null && !registration_field_is_custom($fields[$found]);
        $activeCustomCount = count(array_filter(registration_custom_fields(), function ($field) { return empty($field['deleted']); }));
        if ($found === null && $activeCustomCount >= 30) json_out(['ok' => false, 'msg' => 'الحد الأعلى 30 حقلاً إضافياً'], 422);
        $ar = clean_text($_POST['ar'] ?? '', 160);
        $en = clean_text($_POST['en'] ?? '', 160);
        if ($ar === '' && $en === '') json_out(['ok' => false, 'msg' => 'أدخل اسم الحقل'], 422);
        if ($isBuiltin) {
            $fields[$found]['ar'] = $ar;
            $fields[$found]['en'] = $en;
            $fields[$found]['lock'] = 0;
            setting_set('reg_fields', json_encode(array_values($fields), JSON_UNESCAPED_UNICODE));
            admin_audit('regform_builtin_save', 'key=' . $key);
            json_out(['ok' => true, 'key' => $key]);
        }
        $type = in_array($_POST['type'] ?? '', ['text', 'textarea', 'yesno', 'select'], true) ? $_POST['type'] : 'text';
        $options = [];
        if ($type === 'select') {
            $arLines = regform_option_lines($_POST['options_ar'] ?? '');
            $enLines = regform_option_lines($_POST['options_en'] ?? '');
            $oldIds = json_decode((string)($_POST['option_ids'] ?? '[]'), true);
            if (!is_array($oldIds)) $oldIds = [];
            $count = max(count($arLines), count($enLines));
            for ($i = 0; $i < $count; $i++) {
                $oa = trim((string)($arLines[$i] ?? ''));
                $oe = trim((string)($enLines[$i] ?? ''));
                if ($oa === '' && $oe === '') continue;
                $oid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($oldIds[$i] ?? ''));
                if ($oid === '') $oid = 'opt_' . bin2hex(random_bytes(4));
                $options[] = ['id' => substr($oid, 0, 32), 'ar' => $oa, 'en' => $oe];
            }
            if (!$options) json_out(['ok' => false, 'msg' => 'أدخل خيارات القائمة، كل خيار في سطر'], 422);
        }
        $previous = $found !== null ? $fields[$found] : [];
        $row = [
            'key' => $found !== null ? $key : 'custom_' . bin2hex(random_bytes(6)),
            'ar' => $ar, 'en' => $en, 'type' => $type,
            'on' => array_key_exists('on', $previous) ? (int)!empty($previous['on']) : 1,
            'req' => array_key_exists('req', $previous) ? (int)!empty($previous['req']) : 0,
            'lock' => 0, 'custom' => 1,
            'allow_other' => $type === 'select' && !empty($_POST['allow_other']) ? 1 : 0,
            'options' => $options,
        ];
        if ($found !== null) $fields[$found] = $row; else $fields[] = $row;
        setting_set('reg_fields', json_encode(array_values($fields), JSON_UNESCAPED_UNICODE));
        admin_audit('regform_custom_save', 'key=' . $row['key']);
        json_out(['ok' => true, 'key' => $row['key']]);
    }
    if ($act === 'field_delete') {
        $key = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['key'] ?? ''));
        $fields = registration_fields();
        $changed = false;
        foreach ($fields as &$field) {
            if (registration_field_is_custom($field) && ($field['key'] ?? '') === $key) {
                $field['deleted'] = 1; $field['on'] = 0; $field['req'] = 0; $changed = true;
            }
        }
        unset($field);
        if (!$changed) json_out(['ok' => false, 'msg' => 'الحقل غير موجود'], 404);
        setting_set('reg_fields', json_encode(array_values($fields), JSON_UNESCAPED_UNICODE));
        admin_audit('regform_custom_delete', 'key=' . $key);
        json_out(['ok' => true]);
    }
    if ($act === 'save_opts') {
        setting_set('reg_show_code', !empty($_POST['reg_show_code']) ? '1' : '0');
        setting_set('reg_open', !scf_event_concluded() && !empty($_POST['reg_open']) ? '1' : '0');
        setting_set('reg_captcha_on', !empty($_POST['reg_captcha_on']) ? '1' : '0');
        setting_set('reg_intro_ar', clean_text($_POST['reg_intro_ar'] ?? '', 1000));
        setting_set('reg_intro_en', clean_text($_POST['reg_intro_en'] ?? '', 1000));
        admin_audit('regform_opts_save');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$fields = array_values(array_filter(registration_fields(), function ($field) { return empty($field['deleted']); }));
$typeNames = ['title' => 'لقب', 'gender' => 'اختيار', 'sector' => 'قائمة القطاعات', 'phone' => 'هاتف', 'email' => 'بريد', 'number' => 'رقم', 'text' => 'نص قصير', 'textarea' => 'نص طويل', 'yesno' => 'نعم / لا', 'select' => 'قائمة منسدلة'];
admin_header('نموذج التسجيل', 'regform');
?>
<?php if (scf_event_concluded()): ?><div class="panel" style="padding:18px;margin-bottom:20px"><strong>اختُتمت النسخة الثانية — التسجيل مغلق.</strong><p>أُوقف التسجيل والتعديل الذاتي لبيانات التسجيل في هذه النسخة. تبقى سجلات المشاركين وإدارتها متاحة من لوحة التحكم.</p></div><?php endif; ?>
<div class="content-grid">

<form class="panel" id="optsForm">
  <div class="panel-head"><h2>خيارات النموذج</h2><button class="btn-p"><?= icon('save') ?> حفظ</button></div>
  <label class="chkline big"><input type="checkbox" name="reg_open" <?= scf_event_concluded() ? 'disabled' : '' ?> <?= setting('reg_open', '1') === '1' ? 'checked' : '' ?>> <b>التسجيل مفتوح</b> — عند إيقافه تظهر رسالة "التسجيل مغلق"</label>
  <label class="chkline big"><input type="checkbox" name="reg_show_code" <?= setting('reg_show_code', '1') === '1' ? 'checked' : '' ?>> <b>إظهار رمز التسجيل للمستخدم</b> — عند إيقافه يرى المستخدم رسالة "تم استلام طلبك" فقط دون رمز</label>
  <label class="chkline big"><input type="checkbox" name="reg_captcha_on" <?= setting('reg_captcha_on', '1') === '1' ? 'checked' : '' ?>> <b>تفعيل رمز التحقق (Captcha)</b> — عند إيقافه يُحذف حقل التحقق من النموذج (تبقى حماية الفخّ وتقييد المعدل)</label>
  <div class="grid2" style="margin-top:14px">
    <label>مقدمة الصفحة (عربي)<textarea name="reg_intro_ar" rows="3"><?= e(setting('reg_intro_ar')) ?></textarea></label>
    <label>Intro (EN)<textarea name="reg_intro_en" rows="3" dir="ltr"><?= e(setting('reg_intro_en')) ?></textarea></label>
  </div>
</form>

<div class="panel">
  <div class="panel-head"><h2>حقول التسجيل</h2><div class="fbtns"><button class="btn-s" id="newCustomField" type="button"><?= icon('plus') ?> حقل جديد</button><button class="btn-p" id="saveFields" type="button"><?= icon('save') ?> حفظ الترتيب والحالة</button></div></div>
  <p class="hint">يمكن إيقاف أي حقل أصلي أو تعديل اسمه العربي والإنجليزي، إضافةً إلى إنشاء الحقول الجديدة وتحديد الإلزام والترتيب.</p>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>الحقل</th><th>النوع</th><th style="width:90px">ظاهر</th><th style="width:90px">إلزامي</th><th style="width:160px">الترتيب</th></tr></thead>
    <tbody id="fieldsBody">
      <?php foreach ($fields as $f): $locked = false; ?>
      <tr data-key="<?= e($f['key']) ?>" data-lock="<?= $locked ? 1 : 0 ?>" data-custom="<?= registration_field_is_custom($f) ? 1 : 0 ?>" data-field='<?= e(json_encode($f, JSON_UNESCAPED_UNICODE)) ?>'>
        <td><div class="fld-cell"><b><?= e($f['ar'] ?: ($f['en'] ?? $f['key'])) ?></b><small dir="ltr"><?= e($f['en'] ?? '') ?></small></div></td>
        <td><span class="type-chip"><?= e($typeNames[$f['type'] ?? 'text'] ?? ($f['type'] ?? 'text')) ?></span></td>
        <td><label class="switch"><input type="checkbox" class="f-on" <?= !empty($f['on']) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>><span></span></label></td>
        <td><label class="switch"><input type="checkbox" class="f-req" <?= !empty($f['req']) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>><span></span></label></td>
        <td><div class="field-actions"><button type="button" class="act" data-move="up" title="للأعلى">↑</button><button type="button" class="act" data-move="down" title="للأسفل">↓</button><button type="button" class="act act-edit" data-field-edit title="تعديل الاسم"><?= icon('edit') ?></button><?php if (registration_field_is_custom($f)): ?><button type="button" class="act act-del" data-field-delete title="حذف"><?= icon('trash') ?></button><?php endif; ?></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal" id="customFieldModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3 id="customFieldTitle">حقل تسجيل جديد</h3>
    <form id="customFieldForm" class="modal-form">
      <input type="hidden" name="key" value="">
      <input type="hidden" name="option_ids" value="[]">
      <div class="grid2">
        <label>اسم الحقل بالعربي<input name="ar" maxlength="160"></label>
        <label>Field label in English<input name="en" maxlength="160" dir="ltr"></label>
        <label>نوع الحقل<select name="type"><option value="text">نص قصير</option><option value="textarea">نص طويل</option><option value="yesno">نعم / لا</option><option value="select">قائمة منسدلة</option></select></label>
      </div>
      <div id="fieldOptions" hidden>
        <div class="grid2">
          <label>خيارات العربي <small>كل خيار في سطر</small><textarea name="options_ar" rows="6"></textarea></label>
          <label>English options <small>one option per line</small><textarea name="options_en" rows="6" dir="ltr"></textarea></label>
        </div>
        <label class="chkline"><input type="checkbox" name="allow_other" value="1"> السماح باختيار «أخرى» وكتابة إجابة مخصصة</label>
      </div>
      <button class="btn-p btn-block"><?= icon('save') ?> حفظ الحقل</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>معاينة سريعة</h2><a class="btn-s" href="../register.php" target="_blank"><?= icon('external') ?> فتح صفحة التسجيل</a></div>
</div>
</div>

<style>
.switch{position:relative;width:46px;height:26px;display:inline-block}
.switch input{position:absolute;opacity:0}
.switch span{position:absolute;inset:0;background:#cdd6e4;border-radius:999px;transition:.25s;cursor:pointer}
.switch span::after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.25s}
.switch input:checked+span{background:var(--sky)}
.switch input:checked+span::after{inset-inline-start:23px}
.switch input:disabled+span{opacity:.55;cursor:not-allowed}
.chkline.big{padding:12px 0;border-bottom:1px solid var(--line);font-size:14.5px}
.chkline.big b{font-weight:900}
.fld-cell{display:flex;flex-direction:column;gap:2px}
.fld-cell b{font-weight:800;font-size:14.5px;display:flex;align-items:center;gap:8px}
.fld-cell small{color:var(--muted);font-size:12px;font-weight:600}
.lock-tag{font-size:10.5px;background:rgba(40,163,219,.12);color:var(--steel);padding:2px 8px;border-radius:999px;font-weight:800}
.type-chip{display:inline-block;background:#eef2f8;color:#54617c;font-size:12px;font-weight:800;padding:4px 12px;border-radius:999px;font-family:Montserrat,monospace}
.field-actions{display:flex;gap:5px;align-items:center}.field-actions .act{width:32px;height:32px;padding:0;display:grid;place-items:center}.field-actions .ic{width:15px;height:15px}
#fieldOptions{padding:12px;border:1px solid var(--line);border-radius:13px;background:#f8fbff}#fieldOptions small{display:block;color:var(--muted);font-size:11px}
@media(max-width:700px){.field-actions{min-width:145px}.panel-head .fbtns{width:100%;display:grid;grid-template-columns:1fr 1fr}.panel-head .fbtns button{justify-content:center}}
</style>
<script>
document.getElementById('optsForm').addEventListener('submit',function(ev){
  ev.preventDefault(); var fd=new FormData(this); fd.append('act','save_opts');
  window.apost('regform.php',fd,function(j){window.toast(j.ok?'تم الحفظ ✓':'خطأ',j.ok?0:1);});
});
document.getElementById('saveFields').addEventListener('click',function(){
  var fields=[...document.querySelectorAll('#fieldsBody tr')].map(function(r){
    return {key:r.getAttribute('data-key'), on:r.querySelector('.f-on').checked?1:0, req:r.querySelector('.f-req').checked?1:0};
  });
  window.apost('regform.php',{act:'save_fields',fields:JSON.stringify(fields)},function(j){
    window.toast(j.ok?'تم حفظ الحقول ✓':'خطأ',j.ok?0:1);
  });
});
// عند إطفاء الظاهر، عطّل الإلزامي
document.querySelectorAll('.f-on').forEach(function(c){
  c.addEventListener('change',function(){
    var req=c.closest('tr').querySelector('.f-req');
    if(!c.checked&&!req.disabled){req.checked=false;}
  });
});
var fieldsBody=document.getElementById('fieldsBody');
fieldsBody.addEventListener('click',function(e){
  var move=e.target.closest('[data-move]');
  if(move){var row=move.closest('tr');if(move.dataset.move==='up'&&row.previousElementSibling)fieldsBody.insertBefore(row,row.previousElementSibling);if(move.dataset.move==='down'&&row.nextElementSibling)fieldsBody.insertBefore(row.nextElementSibling,row);return;}
  var edit=e.target.closest('[data-field-edit]');
  if(edit){openField(JSON.parse(edit.closest('tr').getAttribute('data-field')));return;}
  var del=e.target.closest('[data-field-delete]');
  if(del&&confirm('حذف هذا الحقل من النموذج؟ تبقى الإجابات القديمة محفوظة.')){var row=del.closest('tr');window.apost('regform.php',{act:'field_delete',key:row.dataset.key},function(j){if(j.ok)row.remove();else window.toast(j.msg||'خطأ',1);});}
});
var fieldModal=document.getElementById('customFieldModal'),fieldForm=document.getElementById('customFieldForm'),fieldOptions=document.getElementById('fieldOptions');
function syncFieldType(){fieldOptions.hidden=fieldForm.type.value!=='select';}
function openField(data){
  fieldForm.reset(); fieldForm.key.value=data&&data.key?data.key:'';
  fieldForm.ar.value=data&&data.ar?data.ar:'';fieldForm.en.value=data&&data.en?data.en:'';fieldForm.type.value=data&&data.type?data.type:'text';
  var opts=data&&Array.isArray(data.options)?data.options:[];
  fieldForm.options_ar.value=opts.map(function(o){return o.ar||'';}).join('\n');fieldForm.options_en.value=opts.map(function(o){return o.en||'';}).join('\n');
  fieldForm.option_ids.value=JSON.stringify(opts.map(function(o){return o.id||'';}));fieldForm.allow_other.checked=!!(data&&data.allow_other);
  var builtin=!!(data&&!data.custom);fieldForm.type.disabled=builtin;
  document.getElementById('customFieldTitle').textContent=builtin?'تعديل اسم الحقل':(data?'تعديل الحقل':'حقل تسجيل جديد');syncFieldType();fieldModal.hidden=false;
}
document.getElementById('newCustomField').addEventListener('click',function(){openField(null);});
fieldForm.type.addEventListener('change',syncFieldType);
fieldForm.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(fieldForm);fd.append('act','field_save');window.apost('regform.php',fd,function(j){if(j.ok)location.reload();else window.toast(j.msg||'خطأ',1);});});
</script>
<?php admin_footer(); ?>
