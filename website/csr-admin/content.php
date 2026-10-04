<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

/* المفاتيح المسموح تحريرها: نص عادي */
$textKeys = [
    'site_name_ar', 'site_name_en', 'edition_ar', 'edition_en', 'tagline_ar', 'tagline_en',
    'reg_dates_ar', 'reg_dates_en', 'event_dates_ar', 'event_dates_en',
    'venue_ar', 'venue_en', 'event_time_ar', 'event_time_en',
    'countdown_target', 'site_domain',
    'about_ar', 'about_en', 'vision_ar', 'vision_en', 'hero_text_ar', 'hero_text_en', 'importance_ar', 'importance_en',
    'previous_ar', 'previous_en', 'roadmap_ar', 'roadmap_en', 'sponsor_why_ar', 'sponsor_why_en',
    'contact_email', 'contact_addr_ar', 'contact_addr_en',
    'social_facebook', 'social_instagram', 'social_linkedin',
    'footer_note_ar', 'footer_note_en',
    'wa_template_ar', 'wa_template_en', 'wa_api_url', 'wa_api_token',
    'wa_api_provider', 'wa_api_instance', 'wa_api_sender',
    'recaptcha_site', 'recaptcha_secret',
];
/* مفاتيح قوائم (سطر لكل عنصر → JSON) */
$listKeys = ['objectives_ar', 'objectives_en', 'sectors_ar', 'sectors_en', 'target_sectors_ar', 'target_sectors_en', 'why_ar', 'why_en', 'contact_phones'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $saved = 0;
    foreach ($_POST as $k => $v) {
        if (in_array($k, $textKeys, true)) {
            setting_set($k, clean_text($v, 5000));
            $saved++;
        } elseif (in_array($k, $listKeys, true)) {
            $lines = array_values(array_filter(array_map(function ($s) {
                return clean_text($s, 800);
            }, preg_split('/\r?\n/', (string)$v)), function ($s) { return $s !== ''; }));
            setting_set($k, json_encode($lines, JSON_UNESCAPED_UNICODE));
            $saved++;
        } elseif ($k === 'stats_lines') {
            $stats = [];
            foreach (preg_split('/\r?\n/', (string)$v) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 3 && $parts[0] !== '') {
                    $stats[] = ['num' => clean_text($parts[0], 12), 'ar' => clean_text($parts[1], 60), 'en' => clean_text($parts[2], 60)];
                }
            }
            setting_set('stats', json_encode($stats, JSON_UNESCAPED_UNICODE));
            $saved++;
        }
    }
    // خانة إرسال الباركود (checkbox) — تُحفظ فقط عند إرسال نموذج واتساب API
    if (isset($_POST['wa_api_provider'])) {
        setting_set('wa_send_barcode', !empty($_POST['wa_send_barcode']) ? '1' : '0');
    }
    admin_audit('content_save', 'fields=' . $saved);
    json_out(['ok' => true, 'saved' => $saved]);
}

function listval(string $key): string
{
    $arr = json_decode(setting($key, '[]'), true) ?: [];
    return implode("\n", $arr);
}
$statsLines = implode("\n", array_map(function ($s) {
    return ($s['num'] ?? '') . ' | ' . ($s['ar'] ?? '') . ' | ' . ($s['en'] ?? '');
}, json_decode(setting('stats', '[]'), true) ?: []));

admin_header('محتوى الموقع', 'content');
?>
<div class="content-grid">

<form class="panel cform">
  <div class="panel-head"><h2>الهوية والموعد</h2><button class="btn-p">حفظ</button></div>
  <div class="grid2">
    <label>اسم المنتدى (عربي)<input name="site_name_ar" value="<?= e(setting('site_name_ar')) ?>"></label>
    <label>Name (EN)<input name="site_name_en" dir="ltr" value="<?= e(setting('site_name_en')) ?>"></label>
    <label>النسخة (عربي)<input name="edition_ar" value="<?= e(setting('edition_ar')) ?>"></label>
    <label>Edition (EN)<input name="edition_en" dir="ltr" value="<?= e(setting('edition_en')) ?>"></label>
    <label>الشعار النصي (عربي)<input name="tagline_ar" value="<?= e(setting('tagline_ar')) ?>"></label>
    <label>Tagline (EN)<input name="tagline_en" dir="ltr" value="<?= e(setting('tagline_en')) ?>"></label>
    <label>موعد الانعقاد <small>فارغ = من الأجندة</small><input name="event_dates_ar" value="<?= e(setting('event_dates_ar')) ?>" placeholder="<?= e(conference_event_dates('ar')) ?>"></label>
    <label>Dates <small>empty = from Agenda</small><input name="event_dates_en" dir="ltr" value="<?= e(setting('event_dates_en')) ?>" placeholder="<?= e(conference_event_dates('en')) ?>"></label>
    <label>تاريخ صفحة التسجيل (عربي)<input name="reg_dates_ar" value="<?= e(setting('reg_dates_ar')) ?>" placeholder="5 تشرين الأول 2026"></label>
    <label>Registration date (EN)<input name="reg_dates_en" dir="ltr" value="<?= e(setting('reg_dates_en')) ?>" placeholder="October 5, 2026"></label>
    <label>المكان (عربي)<input name="venue_ar" value="<?= e(setting('venue_ar')) ?>"></label>
    <label>Venue (EN)<input name="venue_en" dir="ltr" value="<?= e(setting('venue_en')) ?>"></label>
    <label>التوقيت (عربي)<input name="event_time_ar" value="<?= e(setting('event_time_ar')) ?>"></label>
    <label>Time (EN)<input name="event_time_en" dir="ltr" value="<?= e(setting('event_time_en')) ?>"></label>
    <label>هدف العد التنازلي<input name="countdown_target" dir="ltr" value="<?= e(setting('countdown_target')) ?>" placeholder="2026-09-01 09:00:00"></label>
    <label>النطاق<input name="site_domain" dir="ltr" value="<?= e(setting('site_domain')) ?>"></label>
  </div>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>النصوص الرئيسية</h2><button class="btn-p">حفظ</button></div>
  <label>نبذة عامة (عربي) <small>سطر فارغ بين الفقرات</small><textarea name="about_ar" rows="8"><?= e(setting('about_ar')) ?></textarea></label>
  <label>Overview (EN) <small>blank line between paragraphs</small><textarea name="about_en" rows="8" dir="ltr"><?= e(setting('about_en')) ?></textarea></label>
  <label>عبارة الختام وشريط الواجهة (عربي)<textarea name="hero_text_ar" rows="2"><?= e(setting('hero_text_ar')) ?></textarea></label>
  <label>Closing statement &amp; ticker (EN)<textarea name="hero_text_en" rows="2" dir="ltr"><?= e(setting('hero_text_en')) ?></textarea></label>
  <label>مقدمة «الشراكات والرعاية» (عربي)<textarea name="importance_ar" rows="3"><?= e(setting('importance_ar')) ?></textarea></label>
  <label>Partnership intro (EN)<textarea name="importance_en" rows="3" dir="ltr"><?= e(setting('importance_en')) ?></textarea></label>
  <label>ملاحظة الباقات المخصّصة (عربي)<textarea name="sponsor_why_ar" rows="3"><?= e(setting('sponsor_why_ar')) ?></textarea></label>
  <label>Bespoke packages note (EN)<textarea name="sponsor_why_en" rows="3" dir="ltr"><?= e(setting('sponsor_why_en')) ?></textarea></label>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>القوائم <small class="sub">سطر واحد لكل عنصر</small></h2><button class="btn-p">حفظ</button></div>
  <div class="grid2">
    <label>الأهداف (عربي) <small>العنوان | الوصف</small><textarea name="objectives_ar" rows="7"><?= e(listval('objectives_ar')) ?></textarea></label>
    <label>Objectives (EN) <small>Title | description</small><textarea name="objectives_en" rows="7" dir="ltr"><?= e(listval('objectives_en')) ?></textarea></label>
    <label>الفئات المشاركة (عربي) <small>الفئة | عنصر، عنصر</small><textarea name="sectors_ar" rows="8"><?= e(listval('sectors_ar')) ?></textarea></label>
    <label>Participant groups (EN) <small>Group | item, item</small><textarea name="sectors_en" rows="8" dir="ltr"><?= e(listval('sectors_en')) ?></textarea></label>
    <label>فئات الرعاية (عربي) <small>الفئة | الاسم | ميزة؛ ميزة</small><textarea name="why_ar" rows="5"><?= e(listval('why_ar')) ?></textarea></label>
    <label>Sponsorship tiers (EN) <small>Tier | name | perk; perk</small><textarea name="why_en" rows="5" dir="ltr"><?= e(listval('why_en')) ?></textarea></label>
  </div>
  <label>أرقام النسخة الأولى <small class="sub">التنسيق: الرقم | التسمية عربي | التسمية إنكليزي</small>
    <textarea name="stats_lines" rows="5" dir="ltr"><?= e($statsLines) ?></textarea>
  </label>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>التواصل والروابط</h2><button class="btn-p">حفظ</button></div>
  <div class="grid2">
    <label>أرقام الهاتف <small class="sub">سطر لكل رقم</small><textarea name="contact_phones" rows="3" dir="ltr"><?= e(listval('contact_phones')) ?></textarea></label>
    <label>البريد الإلكتروني<input name="contact_email" dir="ltr" value="<?= e(setting('contact_email')) ?>"></label>
    <label>العنوان (عربي)<input name="contact_addr_ar" value="<?= e(setting('contact_addr_ar')) ?>"></label>
    <label>Address (EN)<input name="contact_addr_en" dir="ltr" value="<?= e(setting('contact_addr_en')) ?>"></label>
    <label>فيسبوك<input name="social_facebook" dir="ltr" value="<?= e(setting('social_facebook')) ?>"></label>
    <label>انستغرام<input name="social_instagram" dir="ltr" value="<?= e(setting('social_instagram')) ?>"></label>
    <label>لينكدإن<input name="social_linkedin" dir="ltr" value="<?= e(setting('social_linkedin')) ?>"></label>
  </div>
  <label>ملاحظة الحقوق (عربي)<textarea name="footer_note_ar" rows="2"><?= e(setting('footer_note_ar')) ?></textarea></label>
  <label>Rights note (EN)<textarea name="footer_note_en" rows="2" dir="ltr"><?= e(setting('footer_note_en')) ?></textarea></label>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>رسائل واتساب</h2><button class="btn-p">حفظ</button></div>
  <p class="hint">المتغيرات: <code>{name}</code> اسم المسجّل · <code>{code}</code> رمز الدخول</p>
  <label>قالب الرسالة (عربي)<textarea name="wa_template_ar" rows="6"><?= e(setting('wa_template_ar')) ?></textarea></label>
  <label>Template (EN)<textarea name="wa_template_en" rows="6" dir="ltr"><?= e(setting('wa_template_en')) ?></textarea></label>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>واتساب API <small class="sub">اختياري — يعمل مع الزر اليدوي في الوقت نفسه</small></h2><button class="btn-p">حفظ</button></div>
  <p class="hint">
    <b>طريقتان للإرسال:</b><br>
    ١) <b>زر سماوي يدوي</b> (دائماً متاح): يفتح محادثة الرقم مباشرة برسالة جاهزة.<br>
    ٢) <b>زر أخضر META</b> (اختياري): يرسل الرسالة <b>مع صورة الباركود</b> تلقائياً. لا تحتاج إلى تعطيل أي طريقة لاستخدام الأخرى.<br>
    لـ <b>Meta WhatsApp Cloud API</b>: ضع <b>Phone Number ID</b> في خانة المعرّف، و<b>Access Token</b> الدائم في خانة التوكن. ملاحظة: قد يتطلب Meta قالباً معتمداً للرسائل خارج نافذة 24 ساعة.
  </p>
  <div class="grid2">
    <label>المزوّد
      <select name="wa_api_provider">
        <option value="" <?= setting('wa_api_provider') === '' ? 'selected' : '' ?>>— يدوي فقط —</option>
        <option value="meta" <?= setting('wa_api_provider') === 'meta' ? 'selected' : '' ?>>Meta WhatsApp Cloud API (رسمي)</option>
        <option value="ultramsg" <?= setting('wa_api_provider') === 'ultramsg' ? 'selected' : '' ?>>UltraMsg</option>
        <option value="custom" <?= setting('wa_api_provider') === 'custom' ? 'selected' : '' ?>>مخصّص (Custom)</option>
      </select>
    </label>
    <label class="chkline" style="align-self:end"><input type="checkbox" name="wa_send_barcode" <?= setting('wa_send_barcode', '1') === '1' ? 'checked' : '' ?>> إرسال صورة الباركود مع الرسالة</label>
    <label>المعرّف <small class="sub">Meta: Phone Number ID · UltraMsg: Instance ID</small><input name="wa_api_instance" dir="ltr" value="<?= e(setting('wa_api_instance')) ?>"></label>
    <label>Token / Access Token<input name="wa_api_token" dir="ltr" value="<?= e(setting('wa_api_token')) ?>"></label>
    <label>Custom API URL <small class="sub">للمزوّد المخصّص فقط</small><input name="wa_api_url" dir="ltr" value="<?= e(setting('wa_api_url')) ?>"></label>
    <label>رقم المُرسِل (اختياري)<input name="wa_api_sender" dir="ltr" value="<?= e(setting('wa_api_sender')) ?>"></label>
  </div>
</form>

<form class="panel cform">
  <div class="panel-head"><h2>reCAPTCHA <small class="sub">اختياري — يستبدل رمز التحقق المدمج</small></h2><button class="btn-p">حفظ</button></div>
  <div class="grid2">
    <label>Site Key<input name="recaptcha_site" dir="ltr" value="<?= e(setting('recaptcha_site')) ?>"></label>
    <label>Secret Key<input name="recaptcha_secret" dir="ltr" value="<?= e(setting('recaptcha_secret')) ?>"></label>
  </div>
</form>

</div>
<script>
document.querySelectorAll('.cform').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(f);
    fd.append('_csrf', window.CSRF);
    fetch('content.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { return r.json(); })
      .then(function (j) { window.toast(j.ok ? 'تم الحفظ ✓' : 'خطأ', j.ok ? 0 : 1); })
      .catch(function () { window.toast('تعذر الاتصال', 1); });
  });
});
</script>
<?php admin_footer(); ?>
