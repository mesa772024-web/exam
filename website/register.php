<?php
require_once __DIR__ . '/app/guard.php';
guard_boot();
require_once __DIR__ . '/app/layout.php';

$L = lang();
$regOpen = setting('reg_open', '1') === '1';
$captchaOn = setting('reg_captcha_on', '1') === '1';
$fields  = registration_fields();
$sectors = json_decode(setting('reg_sectors_' . lang(), '[]'), true) ?: [];
$titles = $L === 'ar'
    ? ['dr' => 'الدكتور', 'eng' => 'المهندس', 'mr' => 'السيد', 'mrs' => 'السيدة', 'prof' => 'الأستاذ', 'other' => 'أخرى']
    : ['dr' => 'Dr.', 'eng' => 'Engineer', 'mr' => 'Mr.', 'mrs' => 'Mrs.', 'prof' => 'Professor', 'other' => 'Other'];
$showCode = setting('reg_show_code', '1') === '1';

/** تسمية الحقل ثنائية اللغة */
function fl(array $f): string { return trim((string)($f[lang()] ?? ($f['ar'] ?? $f['key']))); }

/* هل المستخدم مسجّل مسبقاً؟ (كوكي بالرمز) */
$already = null;
$myCode = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)($_COOKIE['scf_reg'] ?? '')));
if ($myCode !== '' && strlen($myCode) <= 20) {
    $already = q_one('SELECT * FROM registrants WHERE code = ?', [$myCode]);
}
$isEdit  = isset($_GET['edit']) && $already !== null;
$prefill = $isEdit ? $already : [];
$prefillExtra = registration_extra_decode($prefill['extra'] ?? '');
$pv = function (string $k) use ($prefill) { return e($prefill[$k] ?? ''); };
$savedTitle = (string)($prefill['title'] ?? '');
$titleChoice = array_key_exists($savedTitle, $titles) && $savedTitle !== 'other' ? $savedTitle : ($savedTitle !== '' ? 'other' : '');
$titleOther = $titleChoice === 'other' && !is_other_choice($savedTitle) ? $savedTitle : '';
$savedSector = (string)($prefill['sector'] ?? '');
$sectorOtherOption = '';
foreach ($sectors as $sectorOption) {
    if (is_other_choice((string)$sectorOption)) { $sectorOtherOption = (string)$sectorOption; break; }
}
if ($sectorOtherOption === '') $sectorOtherOption = $L === 'ar' ? 'أخرى' : 'Other';
$sectorIsPreset = in_array($savedSector, $sectors, true) && !is_other_choice($savedSector);
$sectorChoice = $sectorIsPreset ? $savedSector : ($savedSector !== '' ? $sectorOtherOption : '');
$sectorOther = $savedSector !== '' && !$sectorIsPreset && !is_other_choice($savedSector) ? $savedSector : '';

site_header($isEdit ? ($L === 'ar' ? 'تعديل بياناتي' : 'Edit my data') : ($L === 'ar' ? 'التسجيل' : 'Registration'));
?>
<section class="reg-page">
  <div class="reg-page-bg" aria-hidden="true"></div>
  <div class="wrap reg-page-in">
    <div class="reg-head reveal">
      <img class="reg-logo ix-reg-logo" src="<?= e(slot_url('stage_logo')) ?>" alt="">
      <span class="eyebrow"><?= icon('ticket') ?><?= e(tr('sec_register')) ?></span>
      <h1 class="reg-title"><?= e(setting_l('site_name')) ?></h1>
      <p class="reg-sub"><?= e(setting_l('edition')) ?> · <?= e(setting_l('reg_dates', conference_event_dates($L))) ?> · <?= e(setting_l('venue')) ?></p>
      <p class="reg-intro"><?= e(setting_l('reg_intro')) ?></p>
    </div>

    <?php if ($already && !$isEdit): ?>
      <?php
        $stMap = ['pending' => $L === 'ar' ? 'قيد المراجعة' : 'Under review', 'approved' => $L === 'ar' ? 'مقبول' : 'Approved', 'rejected' => $L === 'ar' ? 'مرفوض' : 'Rejected'];
        $stCls = ['pending' => 'wait', 'approved' => 'ok', 'rejected' => 'bad'];
      ?>
      <div class="already-panel reveal">
        <span class="ap-ico"><?= icon('success') ?></span>
        <h3><?= $L === 'ar' ? 'أنت مسجّل بالفعل' : 'You are already registered' ?></h3>
        <div class="ap-data">
          <div class="ap-row"><span><?= e(tr('f_name')) ?></span><b><?= e($already['full_name']) ?></b></div>
          <?php if ($already['org']): ?><div class="ap-row"><span><?= e(tr('f_org')) ?></span><b><?= e($already['org']) ?></b></div><?php endif; ?>
          <?php if ($already['phone']): ?><div class="ap-row"><span><?= e(tr('f_phone')) ?></span><b dir="ltr">+<?= e($already['phone']) ?></b></div><?php endif; ?>
          <div class="ap-row"><span><?= $L === 'ar' ? 'الحالة' : 'Status' ?></span><b class="ap-badge ap-<?= $stCls[$already['status']] ?? 'wait' ?>"><?= e($stMap[$already['status']] ?? $already['status']) ?></b></div>
          <?php if ($showCode): ?>
          <div class="ap-row ap-code-row"><span><?= e(tr('reg_success_d')) ?></span><b class="ap-code" dir="ltr"><?= e($already['code']) ?></b></div>
          <?php endif; ?>
        </div>
        <p class="ap-note"><?= $L === 'ar' ? 'إذا أخطأت في بياناتك يمكنك تعديلها، أو التسجيل بحساب جديد.' : 'You can edit your data if you made a mistake, or start a new registration.' ?></p>
        <div class="ap-actions">
          <a class="btn btn-cta btn-lg" href="?edit=1"><?= icon('edit') ?><?= $L === 'ar' ? 'تعديل بياناتي' : 'Edit my data' ?></a>
          <button class="btn btn-ghost btn-lg" id="regAgain" type="button" style="color:var(--steel);border-color:var(--line)"><?= $L === 'ar' ? 'تسجيل جديد' : 'New registration' ?></button>
        </div>
        <a class="ap-home" href="<?= e(base_url()) ?>/"><?= e(tr('back_home')) ?></a>
      </div>
    <?php elseif (!$regOpen): ?>
      <div class="soon-panel reveal" style="max-width:620px;margin:0 auto"><span class="soon-ico"><?= icon('lock') ?></span><p><?= e(tr('reg_closed')) ?></p></div>
    <?php else: ?>
    <?php if ($isEdit): ?><div class="edit-note reveal"><?= icon('info') ?> <?= $L === 'ar' ? 'أنت تعدّل بياناتك المسجّلة. الرمز والحالة لا يتغيّران.' : 'You are editing your registered data. Code and status stay the same.' ?></div><?php endif; ?>
    <form id="regForm" class="reg-form reg-form-page reveal" method="post" action="<?= $isEdit ? 'api/register-edit.php' : 'api/register.php' ?>" data-mode="<?= $isEdit ? 'edit' : 'new' ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="_ft" value="<?= time() ?>">
      <div class="hp-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="fgrid">
        <?php foreach ($fields as $f): if (empty($f['on'])) continue;
          $k = $f['key']; $req = !empty($f['req']); $star = $req ? ' *' : '';
          $type = $f['type'] ?? 'text';
          $wide = in_array($type, ['sector', 'textarea', 'select'], true) || in_array($k, ['org', 'job'], true);
        ?>
          <?php if (registration_field_is_custom($f)):
            $customRaw = registration_custom_raw_value($prefillExtra, $k);
            $storedCustom = $prefillExtra[$k] ?? '';
            $customOther = is_array($storedCustom) && ($storedCustom['value'] ?? '') === '__other__' ? clean_text($storedCustom['label'] ?? '', 500) : '';
          ?>
            <?php if ($type === 'textarea'): ?>
              <div class="fg fg-wide">
                <label for="f_<?= e($k) ?>"><?= e(fl($f)) . $star ?></label>
                <textarea id="f_<?= e($k) ?>" name="<?= e($k) ?>" rows="4" maxlength="2000" <?= $req ? 'required' : '' ?>><?= e($customRaw) ?></textarea>
                <span class="ferr" data-for="<?= e($k) ?>"></span>
              </div>
            <?php elseif ($type === 'yesno'): ?>
              <div class="fg">
                <label><?= e(fl($f)) . $star ?></label>
                <div class="seg" role="radiogroup">
                  <label class="seg-opt"><input type="radio" name="<?= e($k) ?>" value="yes" <?= $customRaw === 'yes' ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>><span><?= $L === 'ar' ? 'نعم' : 'Yes' ?></span></label>
                  <label class="seg-opt"><input type="radio" name="<?= e($k) ?>" value="no" <?= $customRaw === 'no' ? 'checked' : '' ?>><span><?= $L === 'ar' ? 'لا' : 'No' ?></span></label>
                </div>
                <span class="ferr" data-for="<?= e($k) ?>"></span>
              </div>
            <?php elseif ($type === 'select'): ?>
              <div class="fg fg-wide">
                <label for="f_<?= e($k) ?>"><?= e(fl($f)) . $star ?></label>
                <select id="f_<?= e($k) ?>" name="<?= e($k) ?>" <?= $req ? 'required' : '' ?> data-custom-other-select="<?= e($k) ?>">
                  <option value=""><?= e(tr('f_choose')) ?></option>
                  <?php foreach (registration_field_options($f) as $option): ?><option value="<?= e($option['id']) ?>" <?= $customRaw === $option['id'] ? 'selected' : '' ?>><?= e(registration_option_label($f, $option['id'], $L)) ?></option><?php endforeach; ?>
                  <?php if (!empty($f['allow_other'])): ?><option value="__other__" <?= $customRaw === '__other__' ? 'selected' : '' ?>><?= $L === 'ar' ? 'أخرى' : 'Other' ?></option><?php endif; ?>
                </select>
                <?php if (!empty($f['allow_other'])): ?>
                <div class="choice-other" data-custom-other-wrap="<?= e($k) ?>" <?= $customRaw === '__other__' ? '' : 'hidden' ?>>
                  <label for="f_<?= e($k) ?>_other"><?= $L === 'ar' ? 'اكتب الإجابة' : 'Enter your answer' ?> *</label>
                  <textarea id="f_<?= e($k) ?>_other" name="<?= e($k) ?>_other" rows="3" maxlength="500" <?= $customRaw === '__other__' ? 'required' : '' ?>><?= e($customOther) ?></textarea>
                  <span class="ferr" data-for="<?= e($k) ?>_other"></span>
                </div>
                <?php endif; ?>
                <span class="ferr" data-for="<?= e($k) ?>"></span>
              </div>
            <?php else: ?>
              <div class="fg<?= $wide ? ' fg-wide' : '' ?>">
                <label for="f_<?= e($k) ?>"><?= e(fl($f)) . $star ?></label>
                <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="text" maxlength="500" value="<?= e($customRaw) ?>" <?= $req ? 'required' : '' ?>>
                <span class="ferr" data-for="<?= e($k) ?>"></span>
              </div>
            <?php endif; ?>
          <?php elseif ($type === 'title'): ?>
            <div class="fg">
              <label for="f_title"><?= e(fl($f)) . $star ?></label>
              <select id="f_title" name="title" <?= $req ? 'required' : '' ?>>
                <option value=""><?= e(tr('f_choose')) ?></option>
                <?php foreach ($titles as $tv => $tl): ?><option value="<?= e($tv) ?>" <?= $titleChoice === $tv ? 'selected' : '' ?>><?= e($tl) ?></option><?php endforeach; ?>
              </select>
              <div class="choice-other" data-other-wrap="title" <?= $titleChoice === 'other' ? '' : 'hidden' ?>>
                <label for="f_title_other"><?= $L === 'ar' ? 'اكتب اللقب' : 'Enter your title' ?> *</label>
                <textarea id="f_title_other" name="title_other" rows="2" maxlength="120" <?= $titleChoice === 'other' ? 'required' : '' ?>><?= e($titleOther) ?></textarea>
                <span class="ferr" data-for="title_other"></span>
              </div>
              <span class="ferr" data-for="title"></span>
            </div>
          <?php elseif ($type === 'gender'): ?>
            <div class="fg">
              <label><?= e(fl($f)) . $star ?></label>
              <div class="seg" role="radiogroup">
                <label class="seg-opt"><input type="radio" name="gender" value="male" <?= ($prefill['gender'] ?? '') === 'male' ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>><span><?= e(tr('f_male')) ?></span></label>
                <label class="seg-opt"><input type="radio" name="gender" value="female" <?= ($prefill['gender'] ?? '') === 'female' ? 'checked' : '' ?>><span><?= e(tr('f_female')) ?></span></label>
              </div>
              <span class="ferr" data-for="gender"></span>
            </div>
          <?php elseif ($type === 'sector'): ?>
            <div class="fg fg-wide">
              <label for="f_<?= e($k) ?>"><?= e(fl($f)) . $star ?></label>
              <select id="f_<?= e($k) ?>" name="sector" <?= $req ? 'required' : '' ?>>
                <option value=""><?= e(tr('f_choose')) ?></option>
                <?php foreach ($sectors as $s): ?><option value="<?= e($s) ?>" <?= $sectorChoice === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
              </select>
              <div class="choice-other" data-other-wrap="sector" <?= is_other_choice($sectorChoice) ? '' : 'hidden' ?>>
                <label for="f_sector_other"><?= $L === 'ar' ? 'اكتب قطاع العمل' : 'Enter your sector' ?> *</label>
                <textarea id="f_sector_other" name="sector_other" rows="3" maxlength="120" <?= is_other_choice($sectorChoice) ? 'required' : '' ?>><?= e($sectorOther) ?></textarea>
                <span class="ferr" data-for="sector_other"></span>
              </div>
              <span class="ferr" data-for="sector"></span>
            </div>
          <?php else:
            $inputType = $type === 'email' ? 'email' : ($type === 'number' ? 'number' : ($type === 'phone' ? 'tel' : 'text'));
            $dir = in_array($type, ['email', 'phone'], true) ? ' dir="ltr"' : '';
            $ph = $type === 'phone' ? ' placeholder="07XX XXX XXXX"' : '';
            $extra = $type === 'number' ? ' min="10" max="99" inputmode="numeric"' : ($type === 'phone' ? ' inputmode="tel" maxlength="20"' : ' maxlength="200"');
          ?>
            <div class="fg<?= $wide ? ' fg-wide' : '' ?>">
              <label for="f_<?= e($k) ?>"><?= e(fl($f)) . $star ?></label>
              <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $inputType ?>"<?= $dir . $ph . $extra ?> value="<?= $pv($k) ?>" <?= $req ? 'required' : '' ?>>
              <span class="ferr" data-for="<?= e($k) ?>"></span>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($captchaOn && !$isEdit): ?>
        <div class="fg fg-wide captcha-row">
          <label for="f_captcha"><?= e(tr('f_captcha')) ?> *</label>
          <div class="cap-line">
            <img id="capImg" src="api/captcha.php" alt="captcha" width="190" height="62">
            <button type="button" class="cap-refresh" id="capRefresh" title="<?= e(tr('f_refresh')) ?>"><?= icon('refresh') ?></button>
            <input id="f_captcha" name="captcha" required maxlength="5" autocomplete="off" placeholder="<?= e(tr('f_captcha_ph')) ?>" dir="ltr">
          </div>
          <span class="ferr" data-for="captcha"></span>
        </div>
        <?php endif; ?>
      </div>
      <button class="btn btn-cta btn-lg btn-submit" id="regSubmit" type="submit"><?= $isEdit ? ($L === 'ar' ? 'حفظ التعديلات' : 'Save changes') : e(tr('f_submit')) ?><?= icon($L === 'ar' ? 'arrow-l' : 'arrow-r') ?></button>
      <p class="form-msg" id="formMsg" role="alert"></p>
      <?php if ($isEdit): ?><a class="edit-cancel" href="register.php"><?= $L === 'ar' ? 'إلغاء والعودة' : 'Cancel' ?></a><?php endif; ?>
    </form>

    <div class="reg-success" id="regSuccess" hidden>
      <span class="rs-ico"><?= icon('success') ?></span>
      <h3><?= e(tr('reg_success_t')) ?></h3>
      <div class="rs-code-block" id="rsCodeBlock">
        <p><?= e(tr('reg_success_d')) ?></p>
        <div class="rs-code-line"><b id="rsCode" dir="ltr">—</b><button type="button" class="rs-copy" id="rsCopy" title="Copy"><?= icon('copy') ?></button></div>
        <p class="rs-note"><?= e(tr('reg_success_n')) ?></p>
      </div>
      <a class="btn btn-ghost btn-lg" href="<?= e(base_url()) ?>/" style="margin-top:18px;color:var(--steel);border-color:var(--line)"><?= icon($L === 'ar' ? 'arrow-r' : 'arrow-l') ?><?= e(tr('back_home')) ?></a>
    </div>
    <?php endif; ?>
  </div>
</section>
<script>
// تسجيل جديد: امسح الكوكي وأظهر النموذج
var regAgainBtn = document.getElementById('regAgain');
if (regAgainBtn) regAgainBtn.addEventListener('click', function () {
  document.cookie = 'scf_reg=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  location.href = location.pathname + '?fresh=1';
});
</script>
<?php site_footer(); ?>
