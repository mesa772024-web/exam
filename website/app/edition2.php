<?php
/** واجهة منتدى الاقتصاد الرقمي العراقي: الواجهة، الشرائح، النسخ السابقة، التوصيات. */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/posts.php';
require_once __DIR__ . '/slots.php';
require_once __DIR__ . '/sections_catalog.php';

function scf_archive_text(string $ar, string $en): string { return tt($ar, $en); }
function scf_archive_data(): array {
    static $data = null;
    if ($data === null) {
        try {
            $data = ['posts' => scf_posts_list(true), 'thanks' => scf_thanks_text()];
        } catch (Throwable $e) {
            $data = scf_posts_json();
        }
    }
    return $data;
}
function scf_archive_url(string $slug = ''): string {
    return base_url() . '/editions.php?' . http_build_query(array_filter(['post' => $slug, 'lang' => lang()]));
}
function scf_mark(string $text, string $extra = ''): string {
    return '<span class="iscf-marked ' . e($extra) . '"><span class="iscf-marked-text">' . e($text) . '</span></span>';
}
function scf_archive_copy(string $text, bool $people = false): void {
    $inList = false;
    foreach (explode("\n", trim($text)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, '•') === 0) {
            if (!$inList) { echo $people ? '<ul class="recap-people">' : '<ul class="recap-list">'; $inList = true; }
            $line = trim(mb_substr($line, 1));
            $parts = preg_split('/\s+[–—]\s+/u', $line, 2);
            if ($people && count($parts) === 2) {
                echo '<li><b>' . e($parts[0]) . '</b>' . scf_mark($parts[1], 'recap-role') . '</li>';
            } else { echo '<li>' . e($line) . '</li>'; }
        } else {
            if ($inList) { echo '</ul>'; $inList = false; }
            echo '<p>' . e($line) . '</p>';
        }
    }
    if ($inList) echo '</ul>';
}

/** نص المنشور دون سطر العنوان الأول ودون سطور التاريخ/المكان المكررة. */
function scf_story_body(array $post): string {
    $lines = explode("\n", trim($post['text'][lang()]));
    array_shift($lines);
    $keep = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if (preg_match('/^\d{1,2}\s+\S+\s+20\d\d$/u', $t)) continue;
        if (mb_strlen($t) < 40 && preg_match('/^(بغداد|Baghdad)\s*[–—-]/u', $t)) continue;
        $keep[] = $line;
    }
    return implode("\n", $keep);
}
/** يفصل النص القصير (بجانب الصور) عن قائمة المشاركين والخاتمة (بعرض كامل تحتها). */
function scf_story_split(array $post): array {
    $lines = explode("\n", scf_story_body($post));
    $first = null;
    foreach ($lines as $i => $line) { if (strpos(trim($line), '•') === 0) { $first = $i; break; } }
    if ($first === null) return [implode("\n", $lines), '', ''];
    $head = '';
    $cut = $first;
    if ($first > 0 && preg_match('/[:：]\s*$/u', trim($lines[$first - 1]))) { $head = preg_replace('/\s*[:：]\s*$/u', '', trim($lines[$first - 1])); $cut = $first - 1; }
    return [implode("\n", array_slice($lines, 0, $cut)), $head, implode("\n", array_slice($lines, $first))];
}
/** طرفا مذكرة التفاهم من سطر العنوان. */
function scf_story_parties(array $post): array {
    if ($post['category'] !== 'partnership') return [];
    $first = trim(explode("\n", trim($post['text'][lang()]))[0]);
    $pos = mb_strpos($first, ':');
    $rest = $pos !== false ? mb_substr($first, $pos + 1) : (mb_strpos($first, '|') !== false ? mb_substr($first, mb_strpos($first, '|') + 1) : '');
    $parts = preg_split('/\s*\|\s*|\s+and\s+/u', trim($rest));
    return array_values(array_filter(array_map('trim', $parts)));
}

function scf_icon_sound(bool $on): string {
    $wave = $on ? '<path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.4 5.6a9 9 0 0 1 0 12.8"/>' : '<path d="m16 9 5 5"/><path d="m21 9-5 5"/>';
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/>' . $wave . '</svg>';
}

/**
 * عرض شرائح احترافي: تلاشٍ متقاطع + حركة Ken Burns + شريط تقدّم.
 * الصور غير الأولى تُحمَّل عند اقتراب دورها فقط.
 */
function scf_archive_slider(array $slides, string $id, string $variant = 'story', int $interval = 6000): void {
    $L = lang();
    $total = count($slides);
    $sizes = $variant === 'hero' ? '(max-width: 900px) 94vw, 58vw' : ($variant === 'gallery' ? '(max-width: 900px) 94vw, 1100px' : '(max-width: 900px) 94vw, 50vw');
    $origins = ['50% 50%', '30% 40%', '70% 35%', '40% 65%', '65% 60%'];
    ?>
    <div class="rs rs--<?= e($variant) ?>" id="<?= e($id) ?>" data-rs style="--rs-interval:<?= $interval ?>ms" role="region" aria-roledescription="<?= e(scf_archive_text('عرض شرائح', 'carousel')) ?>" aria-label="<?= e(scf_archive_text('صور المنتدى', 'Forum photographs')) ?>" tabindex="0">
      <div class="rs-stage">
      <?php foreach ($slides as $i => $slide):
          $src = scf_media_url($slide['file']);
          $srcset = isset($slide['thumb']) && $slide['thumb'] !== $slide['file'] ? scf_media_url($slide['thumb']) . ' 760w, ' . $src . ' ' . (int)($slide['width'] ?? 1800) . 'w' : '';
          $first = $i === 0; ?>
        <figure class="rs-slide<?= $first ? ' is-active' : '' ?>" data-slide style="--kb-origin:<?= $origins[$i % count($origins)] ?>" role="group" aria-roledescription="<?= e(scf_archive_text('شريحة', 'slide')) ?>" aria-label="<?= ($i + 1) . ' / ' . $total ?>"<?= $first ? '' : ' aria-hidden="true"' ?>>
          <img <?= $first ? 'src' : 'data-src' ?>="<?= e($src) ?>" <?php if ($srcset): ?><?= $first ? 'srcset' : 'data-srcset' ?>="<?= e($srcset) ?>" sizes="<?= e($sizes) ?>"<?php endif; ?> width="<?= (int)($slide['width'] ?? 1800) ?>" height="<?= (int)($slide['height'] ?? 1200) ?>" data-full="<?= e($src) ?>" alt="<?= e($slide['caption']) ?>" <?= $first && $variant === 'hero' ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
          <?php if (!empty($slide['label'])): ?><figcaption><span><?= e($slide['label']) ?></span><b><?= e($slide['caption']) ?></b></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
      </div>
      <?php if ($total > 1): ?>
      <div class="rs-bar">
        <div class="rs-progress" aria-label="<?= e(scf_archive_text('اختيار صورة', 'Choose a photograph')) ?>">
          <?php foreach ($slides as $i => $slide): ?><button type="button" data-slide-to="<?= $i ?>" aria-label="<?= e(scf_archive_text('الصورة ', 'Photograph ') . ($i + 1)) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"><i></i></button><?php endforeach; ?>
        </div>
        <div class="rs-ctrl">
          <span class="rs-counter" data-slide-counter dir="ltr">1 / <?= $total ?></span>
          <button type="button" data-slide-prev aria-label="<?= e(scf_archive_text('الصورة السابقة', 'Previous photograph')) ?>"><?= icon($L === 'ar' ? 'arrow-r' : 'arrow-l') ?></button>
          <button type="button" data-slide-toggle data-pause="<?= e(scf_archive_text('إيقاف الحركة', 'Pause slideshow')) ?>" data-play="<?= e(scf_archive_text('تشغيل الحركة', 'Play slideshow')) ?>" aria-label="<?= e(scf_archive_text('إيقاف الحركة', 'Pause slideshow')) ?>" aria-pressed="false"><span class="rs-ico-pause"><?= icon('pause') ?></span><span class="rs-ico-play"><?= icon('play') ?></span></button>
          <button type="button" data-slide-next aria-label="<?= e(scf_archive_text('الصورة التالية', 'Next photograph')) ?>"><?= icon($L === 'ar' ? 'arrow-l' : 'arrow-r') ?></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php
}

/** أيقونة مناسبة لكل قطاع رقمي */
function idef_sector_icon(string $text, int $i): string
{
    $map = [
        'بنية|infrastructure|اتصالات|telecom' => 's-telecom', 'برمج|software|معلومات|information' => 's-it', 'ذكاء|intelligence|ناشئة|emerging' => 'bolt',
        'بيانات|data|سحاب|cloud' => 'database', 'تجارة|commerce' => 's-invest', 'مالي|fin|مدفوع|pay|مصرف|bank' => 's-fintech', 'سيبراني|cyber|ثقة|trust' => 'shield',
        'حكوم|government' => 's-egov', 'مدن|cit' => 's-urban', 'صناع|industr|زراع|agric' => 's-build', 'صحة|health' => 's-health', 'تعليم|education' => 's-univ',
        'إعلام|media|منصات|platform' => 'mic', 'ناشئة|startup|ريادة|entrepreneur' => 'trending', 'استثمار|invest' => 's-invest', 'نفط|oil|طاقة|energy' => 's-energy',
        'نقل|transport|لوجست|logist' => 's-transport', 'عقار|real estate|بناء|construction' => 's-housing', 'غذائي|food' => 's-water', 'سياحة|touris' => 'globe',
    ];
    foreach ($map as $re => $ic) if (preg_match('/' . $re . '/iu', $text)) return $ic;
    return sector_icon($i);
}

/** عنصران ثنائيا اللغة من إعدادين (مصفوفتين متقابلتين) — كل عنصر قابل للتعديل المباشر */
function idef_pairs(string $base): array
{
    $ar = json_decode(setting($base . '_ar', '[]'), true) ?: [];
    $en = json_decode(setting($base . '_en', '[]'), true) ?: [];
    $out = [];
    foreach ($ar as $i => $a) $out[] = tt((string)$a, (string)($en[$i] ?? $a));
    if (!$ar) foreach ($en as $e) $out[] = tt((string)$e, (string)$e);
    return $out;
}

/** العد التنازلي */
function idef_countdown(string $variant = ''): void
{
    $target = setting('countdown_target', '2026-10-05 09:00:00');
    ?>
    <div class="ix-count<?= $variant ? ' ix-count--' . e($variant) : '' ?>" data-countdown="<?= e($target) ?>" aria-label="<?= e(tr('countdown_to')) ?>">
      <?php foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $k => $lbl): ?>
        <div class="ix-cd"><b data-cd="<?= $k ?>">00</b><span><?= e(tr($lbl)) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php
}

/** الواجهة: شعار + نص + عد تنازلي + الكرة الأرضية (فيديو خفيف بخلفية تندمج مع الصفحة) */
function scf_home_stage(): void {
    $L = lang();
    $stats = json_decode(setting('stats', '[]'), true) ?: [];
    $regOpen = setting('reg_open', '1') === '1';
    $sectors = idef_pairs('sectors');
    ?>
    <section class="ix-hero" id="top">
      <div class="ix-grid-bg" aria-hidden="true"></div>
      <svg class="ix-lines" viewBox="0 0 1200 400" preserveAspectRatio="none" aria-hidden="true"><polyline points="0,330 180,300 300,318 430,250 560,270 700,190 820,215 960,120 1080,140 1200,60"/><polyline class="l2" points="0,370 200,350 340,360 470,300 610,320 760,250 900,265 1040,190 1200,150"/></svg>
      <div class="wrap ix-hero-top">
        <h1 class="ix-logo"><img src="<?= e(slot_url('stage_logo')) ?>"<?= slot_attr('stage_logo') ?> width="1600" height="462" alt="<?= e(strip_tags(setting_l('site_name'))) ?>" fetchpriority="high"></h1>
      </div>
      <div class="wrap ix-hero-in">
        <div class="ix-copy">
          <p class="ix-lead"><?= e(setting_l('hero_text')) ?></p>
          <ul class="ix-meta">
            <li><?= icon('calendar') ?><span><?= e(setting_l('event_dates')) ?></span></li>
            <li><?= icon('pin') ?><span><?= e(setting_l('venue')) ?></span></li>
            <li><?= icon('clock') ?><span dir="auto"><?= e(setting_l('event_time')) ?></span></li>
          </ul>
          <?php idef_countdown(); ?>
          <div class="ix-actions">
            <?php if ($regOpen): ?><a class="ix-btn ix-btn-red" href="<?= e(base_url()) ?>/register.php"><?= icon('ticket') ?><?= e(tr('hero_cta')) ?></a><?php endif; ?>
            <a class="ix-btn ix-btn-line" href="#agenda"><?= icon('calendar') ?><?= tt('برنامج المنتدى', 'Forum program') ?></a>
          </div>
        </div>
        <div class="ix-visual">
          <div class="ix-globe" id="film">
            <span class="ix-halo" aria-hidden="true"></span>
            <video class="ix-video" data-stage-video data-silent<?= slot_attr('video') ?> data-hd="<?= e(slot_url('video')) ?>" data-sd="<?= e(slot_url('video_sd')) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= e(slot_url('poster')) ?>" aria-label="<?= e(tt('الاقتصاد الرقمي العراقي — العراق على خارطة العالم الرقمي', 'Iraq on the global digital map')) ?>"></video>
            <noscript><video class="ix-video" src="<?= e(slot_url('video')) ?>" autoplay muted loop playsinline></video></noscript>
            <button type="button" class="ix-globe-hit" data-film-open aria-label="<?= e(tt('عرض الفيديو بحجم أكبر', 'Watch the video larger')) ?>"></button>
            <?php foreach (array_slice($stats, 0, 3) as $i => $st): ?>
              <div class="ix-float ix-float--<?= $i ?>"><b class="stat-num" data-count="<?= e($st['num']) ?>"><?= e($st['num']) ?></b><span><?= e(tt((string)$st['ar'], (string)$st['en'])) ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php if ($sectors): ?>
      <div class="ix-ticker" aria-hidden="true"><div class="ix-ticker-track">
        <?php for ($r = 0; $r < 2; $r++): ?><div class="ix-ticker-set"><?php foreach ($sectors as $sec): ?><span><?= e(strip_tags($sec)) ?></span><?php endforeach; ?></div><?php endfor; ?>
      </div></div>
      <?php endif; ?>
    </section>
    <dialog class="film-dialog" id="filmDialog" aria-label="<?= e(tt('فيديو المنتدى', 'Forum video')) ?>">
      <button type="button" class="film-dialog-close" data-film-close aria-label="<?= e(tt('إغلاق', 'Close')) ?>"><?= icon('close') ?></button>
      <video class="film-dialog-video" controls muted loop playsinline preload="none" poster="<?= e(slot_url('poster')) ?>" data-film-video><source src="<?= e(slot_url('video')) ?>" type="video/mp4"></video>
    </dialog>
    <?php
}

/** النسخ السابقة (المنشورات): شرائح + النص كاملاً */
function scf_archive_cards(bool $filters = false, string $exclude = ''): void {
    $posts = scf_archive_data()['posts'];
    if (!$posts) return;
    ?>
    <section class="recap-section recap-coverage ix-editions" id="coverage">
      <div class="wrap">
        <div class="ix-head recap-reveal"><span class="eyebrow"><?= icon('image') ?><?= e(tr('sec_edition1')) ?></span><h2 class="sec-title"><?= tt('محطات من <em>المنتدى</em>', 'Forum <em>milestones</em>') ?></h2><p class="ix-head-lead"><?= e(setting_l('previous')) ?></p></div>
        <div class="story-list">
          <?php $n = 0; foreach ($posts as $p): if ($p['slug'] === $exclude) continue; $n++;
              $slides = [];
              foreach ($p['images'] as $i => $im) { $im['caption'] = $p['title'][lang()] . tt(' — الصورة ', ' — Photograph ') . ($i + 1); $slides[] = $im; }
              [$lead, $extraHead, $extra] = scf_story_split($p);
              $first = trim(explode("\n", trim($p['text'][lang()]))[0]); ?>
            <article class="story recap-reveal" id="story-<?= e($p['slug']) ?>" data-category="<?= e($p['category']) ?>">
              <div class="story-row">
              <div class="story-media"><?php scf_archive_slider($slides, 'story-' . $p['slug'], 'story', 5200 + ($n % 3) * 700); ?></div>
              <div class="story-copy">
                <p class="story-kicker"><span class="story-num" dir="ltr"><?= str_pad((string)$n, 2, '0', STR_PAD_LEFT) ?></span><?= scf_mark(isset($p['id']) ? scf_tk_wrap('post:' . $p['id'] . ':label', $p['label'][lang()]) : $p['label'][lang()]) ?></p>
                <h3 class="story-title"><?= e(isset($p['id']) ? scf_tk_wrap('post:' . $p['id'] . ':title', $p['title'][lang()]) : $p['title'][lang()]) ?></h3>
                <?php if (strpos($first, '|') !== false): ?><p class="ix-story-place"><?= icon('pin') ?><?= e(trim(substr($first, strpos($first, '|') + 1))) ?></p><?php endif; ?>
                <?php if (isset($p['id']) && scf_edit_mode()): ?><a class="edit-post-btn" href="<?= e(base_url() . '/' . ADMIN_DIR . '/posts.php?edit=' . $p['id']) ?>" target="_blank" rel="noopener"><?= icon('edit') ?> تعديل النص الكامل والصور</a><?php endif; ?>
                <div class="story-text" lang="<?= e(lang()) ?>"><?php scf_archive_copy($lead); ?><?php if ($extra !== ''): ?><?php if ($extraHead !== ''): ?><p><b><?= e($extraHead) ?></b></p><?php endif; ?><?php scf_archive_copy($extra); ?><?php endif; ?></div>
              </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
<?php }

/** توصيات النسخ السابقة (تبويب لكل نسخة + بطاقات قابلة للفتح) */
function idef_recommendations_data(): array
{
    static $d = null;
    if ($d === null) {
        $d = json_decode((string)@file_get_contents(__DIR__ . '/recommendations.json'), true);
        if (!is_array($d) || empty($d['editions'])) $d = ['editions' => []];
    }
    return $d['editions'];
}

function idef_recommendations(bool $full = false): void
{
    $eds = idef_recommendations_data();
    if (!$eds) return;
    $eds = array_reverse($eds, true); // الأحدث أولاً
    ?>
    <section class="sec ix-recs" id="recommendations">
      <div class="wrap">
        <?php if (!$full): ?><div class="ix-head reveal"><span class="eyebrow"><?= icon('check-circle') ?><?= e(tr('nav_recs')) ?></span><h2 class="sec-title"><?= tt('توصيات تتحول إلى <em>خارطة طريق</em>', 'Recommendations that become <em>a roadmap</em>') ?></h2></div><?php endif; ?>
        <div class="ix-tabs" role="tablist" data-tabs>
          <?php $first = true; foreach ($eds as $k => $ed): $count = 0; foreach ($ed['groups'] as $g) $count += count($g['items']); ?>
            <button type="button" role="tab" data-tab="rec<?= (int)$k ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= e(tt($ed['eyebrow']['ar'], $ed['eyebrow']['en'])) ?><em><?= $count ?></em></button>
          <?php $first = false; endforeach; ?>
        </div>
        <?php $first = true; foreach ($eds as $k => $ed): $shown = 0; ?>
          <div class="ix-tabpanel" data-panel="rec<?= (int)$k ?>" role="tabpanel"<?= $first ? '' : ' hidden' ?>>
            <div class="ix-rec-intro"><h3><?= e(tt($ed['title']['ar'], $ed['title']['en'])) ?></h3><p><?= e(tt($ed['intro']['ar'], $ed['intro']['en'])) ?></p></div>
            <?php foreach ($ed['groups'] as $g): if (!$full && $shown >= 6) break; ?>
              <?php if (count($ed['groups']) > 1): ?><h4 class="ix-rec-group"><?= e(tt($g['title']['ar'], $g['title']['en'])) ?></h4><?php endif; ?>
              <div class="ix-rec-grid">
                <?php foreach ($g['items'] as $it): if (!$full && $shown >= 6) break; $shown++; ?>
                  <details class="ix-rec reveal"<?= $full ? '' : '' ?>>
                    <summary><span class="ix-rec-no" dir="ltr"><?= str_pad((string)$it['id'], 2, '0', STR_PAD_LEFT) ?></span><b><?= e(tt($it['title']['ar'], $it['title']['en'])) ?></b><i aria-hidden="true"></i></summary>
                    <p><?= e(tt($it['text']['ar'], $it['text']['en'])) ?></p>
                  </details>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php $first = false; endforeach; ?>
        <?php if (!$full): ?><div class="ix-center reveal"><a class="ix-btn ix-btn-navy" href="<?= e(base_url()) ?>/recommendations.php"><?= tt('جميع التوصيات (32 توصية)', 'All recommendations (32)') ?><?= icon(lang() === 'ar' ? 'arrow-l' : 'arrow-r') ?></a></div><?php endif; ?>
      </div>
    </section>
    <?php
}

/** Speak Up: نموذج يترك فيه الزائر تجربته (الرسالة فقط إلزامية) — يظهر فقط عند تفعيله من لوحة التحكم. */
function scf_speakup_section(): void {
    if (!scf_speakup_on()) return;
    $opt = tt('اختياري', 'optional');
    ?>
    <section class="speakup" id="speakup">
      <div class="wrap speakup-grid">
        <div class="speakup-copy reveal">
          <span class="eyebrow"><?= icon('edit') ?>SPEAK UP</span>
          <h2><?= tt('شاركنا <em>رأيك</em>', 'Share <em>your view</em>') ?></h2>
          <p class="speakup-lead"><?= tt('رأيك في منتدى الاقتصاد الرقمي العراقي يهمّنا: فكرة، اقتراح، أو تجربة.', 'Your view on the Iraqi Digital Economy Forum matters: an idea, a suggestion or an experience.') ?></p>
        </div>
        <form class="speakup-form reveal" data-speakup action="<?= e(base_url()) ?>/api/speakup.php?lang=<?= e(lang()) ?>" method="post" novalidate>
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="_ft" value="<?= time() ?>">
          <div class="sp-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <div class="sp-fields">
            <div class="sp-row">
              <label class="sp-field"><span><?= tt('الاسم', 'Name') ?> <small><?= $opt ?></small></span><input type="text" name="name" maxlength="80" autocomplete="name"></label>
              <label class="sp-field"><span><?= tt('رقم الهاتف', 'Phone number') ?> <small><?= $opt ?></small></span><input type="tel" name="phone" maxlength="24" dir="ltr" inputmode="tel" autocomplete="tel" placeholder="+964"></label>
            </div>
            <label class="sp-field"><span><?= tt('البريد الإلكتروني', 'Email') ?> <small><?= $opt ?></small></span><input type="email" name="email" maxlength="160" dir="ltr" autocomplete="email" placeholder="name@example.com"></label>
            <label class="sp-field"><span><?= tt('رسالتك', 'Your message') ?> <b aria-hidden="true">*</b></span><textarea name="message" rows="6" maxlength="2000" required></textarea><em class="sp-count" dir="ltr">0 / 2000</em></label>
            <div class="sp-foot">
              <button type="submit" class="ix-btn ix-btn-navy sp-submit"><?= tt('إرسال الرسالة', 'Send message') ?></button>
              <p class="sp-status" role="status" aria-live="polite"></p>
            </div>
          </div>
          <div class="sp-done" hidden>
            <span class="sp-done-ico"><?= icon('check') ?></span>
            <b><?= tt('شكراً لك! وصلتنا رسالتك.', 'Thank you! Your message has been received.') ?></b>
            <button type="button" class="recap-text-link" data-speakup-again><?= tt('إرسال رسالة أخرى', 'Send another message') ?></button>
          </div>
        </form>
      </div>
    </section>
    <?php
}
