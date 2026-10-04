<?php
require_once __DIR__ . '/app/guard.php';
guard_boot();
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/edition2.php';

$speakers   = get_speakers();
$organizers = get_orgs('organizer');
$partners   = get_orgs('partner');
$support    = get_orgs('support');
$sponsors   = get_orgs('sponsor');
$agenda     = get_agenda();
$gallery    = q_all('SELECT * FROM gallery WHERE active = 1 ORDER BY sort, id');
$stats      = json_decode(setting('stats', '[]'), true) ?: [];
$order      = idef_sections_order(json_decode(setting('sections_order', '[]'), true) ?: []);
$regOpen    = setting('reg_open', '1') === '1';
$editing    = scf_edit_mode();
$L = lang();
$home = base_url() . '/';

function org_logo(array $o): string
{
    $l = $o['logo'];
    if (strpos($l, 'assets:') === 0) return asset('img/' . substr($l, 7));
    if ($l !== '') return upload_url($l);
    return slot_url('brand_logo');
}

/**
 * قائمة ثنائية اللغة مخزّنة كسطور «جزء | جزء | …» في setting(base_ar/base_en).
 * كل جزء يُمرَّر عبر tt() على حدة ليبقى قابلاً للتعديل المباشر.
 */
function csr_rows(string $base): array
{
    $ar = json_decode(setting($base . '_ar', '[]'), true) ?: [];
    $en = json_decode(setting($base . '_en', '[]'), true) ?: [];
    $rows = [];
    foreach ($ar as $i => $line) {
        $pa = array_map('trim', explode('|', (string)$line));
        $pe = array_map('trim', explode('|', (string)($en[$i] ?? $line)));
        $row = [];
        foreach ($pa as $k => $p) $row[] = [$p, $pe[$k] ?? $p];
        $rows[] = $row;
    }
    return $rows;
}

/** يقسم جزءاً إلى عناصر (، أو ؛ أو ,) مع إبقاء المقابل الإنكليزي */
function csr_items(array $pair, string $sepAr = '،', string $sepEn = ','): array
{
    $a = array_values(array_filter(array_map('trim', explode($sepAr, $pair[0]))));
    $e = array_values(array_filter(array_map('trim', explode($sepEn, $pair[1]))));
    $out = [];
    foreach ($a as $i => $x) $out[] = tt($x, $e[$i] ?? $x);
    return $out;
}

/** عنوان قسم موحّد: سطر صغير + عنوان مقسّم لسطور متحركة */
function csr_head(string $kicker, string $title, string $extra = ''): string
{
    return '<header class="c-sec-head' . ($extra ? ' ' . $extra : '') . '"><span class="c-kicker" data-reveal>' . e($kicker) . '</span>'
        . '<h2 class="c-h2" data-split>' . e($title) . '</h2></header>';
}

$S = [];

/* ===== الأرقام ===== */
ob_start(); ?>
<section class="c-stats" id="stats" aria-label="<?= e(tt('المنتدى بالأرقام', 'The forum in numbers')) ?>">
  <div class="c-wrap c-stats-in">
    <?php foreach ($stats as $i => $s): ?>
      <div class="c-stat" data-reveal style="--d:<?= $i * 120 ?>ms">
        <b class="c-stat-num" data-count="<?= e(preg_replace('/\D/', '', (string)$s['num'])) ?>" data-digits="<?= $L ?>"><?= e(num_l((string)$s['num'])) ?></b>
        <span><?= e(tt((string)$s['ar'], (string)$s['en'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php $S['stats'] = ob_get_clean();

/* ===== نبذة عامة ===== */
$about = setting_l('about');
$paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $about))));
ob_start(); ?>
<section class="c-about c-sec" id="about">
  <div class="c-wrap c-about-grid">
    <div class="c-about-side">
      <?= csr_head(tt('عن المنتدى', 'About the forum'), tt('نبذة عامة', 'Overview')) ?>
      <figure class="c-about-fig" data-reveal="clip">
        <img src="<?= e(slot_url('about_photo')) ?>"<?= slot_attr('about_photo') ?> alt="" loading="lazy" data-parallax="-0.08">
      </figure>
    </div>
    <div class="c-about-txt">
      <?php foreach ($paras as $i => $p): ?>
        <p class="<?= $i === 0 ? 'c-lead' : 'c-body' ?>"<?= $editing ? '' : ' data-scrub' ?>><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $S['about'] = ob_get_clean();

/* ===== الأهداف الاستراتيجية (مقطع أفقي مثبّت) ===== */
$goals = csr_rows('objectives');
ob_start(); ?>
<section class="c-goals" id="objectives" data-hscroll>
  <div class="c-goals-pin">
    <div class="c-goals-track" data-htrack>
      <div class="c-goals-intro">
        <?= csr_head(tt('أهداف المنتدى', 'Forum objectives'), tt('الأهداف الاستراتيجية', 'Strategic objectives')) ?>
        <figure class="c-goals-fig"><img src="<?= e(slot_url('goals_photo')) ?>"<?= slot_attr('goals_photo') ?> alt="" loading="lazy"></figure>
      </div>
      <?php foreach ($goals as $i => $g): ?>
        <article class="c-goal" style="--i:<?= $i ?>">
          <span class="c-goal-num" aria-hidden="true"><?= num_l((string)($i + 1)) ?></span>
          <?= csr_star('c-goal-star', false) ?>
          <h3><?= e(tt($g[0][0], $g[0][1])) ?></h3>
          <?php if (isset($g[1])): ?><p><?= e(tt($g[1][0], $g[1][1])) ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="c-goals-bar" aria-hidden="true"><i data-hbar></i></div>
  </div>
</section>
<?php $S['objectives'] = ob_get_clean();

/* ===== الفئات المشاركة ===== */
$groups = csr_rows('sectors');
ob_start(); ?>
<section class="c-groups c-sec" id="participants">
  <div class="c-wrap">
    <div class="c-groups-head">
      <?= csr_head(tt('من سيشارك؟', 'Who takes part?'), tt('الفئات المشاركة', 'Participant groups')) ?>
      <figure class="c-groups-fig" data-reveal="clip"><img src="<?= e(slot_url('groups_photo')) ?>"<?= slot_attr('groups_photo') ?> alt="" loading="lazy" data-parallax="0.06"></figure>
    </div>
    <div class="c-groups-row" data-line>
      <svg class="c-groups-line" preserveAspectRatio="none" viewBox="0 0 100 2" aria-hidden="true"><line x1="0" y1="1" x2="100" y2="1" data-draw/></svg>
      <?php foreach ($groups as $i => $g): ?>
        <article class="c-group" data-reveal style="--d:<?= $i * 110 ?>ms">
          <div class="c-group-ph">
            <?= csr_star('c-group-star', false) ?>
            <img src="<?= e(slot_url('group_' . ($i + 1))) ?>"<?= slot_attr('group_' . ($i + 1)) ?> alt="" loading="lazy">
          </div>
          <span class="c-group-no"><?= num_l((string)($i + 1)) ?></span>
          <h3><?= e(tt($g[0][0], $g[0][1])) ?></h3>
          <?php if (isset($g[1])): ?><ul><?php foreach (csr_items($g[1]) as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $S['participants'] = ob_get_clean();

/* ===== برنامج المنتدى (خط زمني) ===== */
ob_start(); ?>
<section class="c-agenda c-sec" id="agenda">
  <div class="c-wrap c-agenda-grid">
    <aside class="c-agenda-side">
      <?= csr_head(tt('جدول الأعمال', 'Agenda'), tt('برنامج المنتدى', 'Forum programme')) ?>
      <figure class="c-agenda-fig" data-reveal="clip">
        <img src="<?= e(slot_url('agenda_photo')) ?>"<?= slot_attr('agenda_photo') ?> alt="" loading="lazy">
        <?php foreach ($agenda as $d): ?><figcaption><?= csr_star('c-cap-star', false) ?><span><?= e(ble('agenda_days', $d, 'title')) ?></span></figcaption><?php break; endforeach; ?>
      </figure>
    </aside>
    <div class="c-tl" data-timeline>
      <span class="c-tl-rail" aria-hidden="true"><i data-tl-fill></i></span>
      <?php foreach ($agenda as $d): foreach ($d['items'] as $n => $it):
        $lines = array_values(array_filter(array_map('trim', explode("\n", bl($it, 'desc')))));
        $last = $n === count($d['items']) - 1;
        $kind = count($lines) > 1 ? 'pills' : ((count($lines) === 1 && mb_strlen($lines[0]) < 40) ? 'label' : ($lines ? 'text' : 'plain'));
      ?>
        <article class="c-tl-item<?= $last ? ' is-final' : '' ?> is-<?= $kind ?>" data-tl-item>
          <span class="c-tl-node" aria-hidden="true"><i></i></span>
          <?php if ($it['time_txt'] !== ''): ?><time dir="ltr"><?= e($it['time_txt']) ?></time><?php endif; ?>
          <div class="c-tl-card" data-reveal>
            <?php if ($kind === 'label'): ?><span class="c-tl-label"><?= e($lines[0]) ?></span><?php endif; ?>
            <h3><?= e(ble('agenda_items', $it, 'title')) ?></h3>
            <?php if ($kind === 'pills'): ?><ul class="c-pills"><?php foreach ($lines as $ln): ?><li><?= e($ln) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($kind === 'text'): ?><p><?= e($lines[0]) ?></p><?php endif; ?>
            <?php if ($last): ?><?= csr_star('c-tl-star') ?><?php endif; ?>
          </div>
        </article>
      <?php endforeach; endforeach; ?>
    </div>
  </div>
</section>
<?php $S['agenda'] = ob_get_clean();

/* ===== المتحدثون ===== */
ob_start(); ?>
<section class="c-speakers c-sec" id="speakers">
  <div class="c-wrap">
    <?= csr_head(tt('المتحدثون', 'Speakers'), tt('نخبة المتحدثين', 'Featured speakers')) ?>
    <div class="c-spk-grid">
      <?php foreach ($speakers as $i => $sp): ?>
        <article class="c-spk" data-reveal style="--d:<?= ($i % 4) * 90 ?>ms">
          <div class="c-spk-ph"><?php if ($sp['photo']): ?><img src="<?= e(upload_url($sp['photo'])) ?>" alt="<?= e(bl($sp, 'name')) ?>" loading="lazy"><?php else: ?><?= csr_star('c-spk-star') ?><?php endif; ?></div>
          <b><?= e(ble('speakers', $sp, 'name')) ?></b><span><?= e(ble('speakers', $sp, 'title')) ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $S['speakers'] = ob_get_clean();

/* ===== الشراكات والرعاية ===== */
$tiers = csr_rows('why');
ob_start(); ?>
<section class="c-tiers c-sec" id="partnership">
  <div class="c-wrap">
    <div class="c-tiers-head">
      <?= csr_head(tt('كن شريكاً في الأثر', 'Be a partner in impact'), tt('الشراكات والرعاية', 'Partnership & sponsorship')) ?>
      <p class="c-tiers-intro" data-reveal><?= e(setting_l('importance')) ?></p>
    </div>
    <div class="c-tiers-row">
      <?php foreach ($tiers as $i => $t): ?>
        <article class="c-tier c-tier--<?= $i + 1 ?>" data-reveal style="--d:<?= $i * 120 ?>ms">
          <span class="c-tier-lab"><?= e(tt($t[0][0], $t[0][1])) ?></span>
          <h3><?= isset($t[1]) ? e(tt($t[1][0], $t[1][1])) : '' ?></h3>
          <?php if (isset($t[2])): ?>
            <span class="c-tier-perks"><?= e(tt('المزايا', 'Benefits')) ?></span>
            <ul><?php foreach (csr_items($t[2], '؛', ';') as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?= csr_star('c-tier-star', false) ?>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="c-tiers-note" data-reveal>
      <p><?= e(setting_l('sponsor_why')) ?></p>
      <?php $mail = setting('contact_email'); ?>
      <a class="c-btn c-btn--line" href="<?= e($mail ? 'mailto:' . $mail : '#contact') ?>" data-magnetic><span><?= e(tt('تواصل مع اللجنة المنظمة', 'Contact the organising committee')) ?></span></a>
    </div>
  </div>
</section>
<?php $S['partnership'] = ob_get_clean();

/* ===== الجهات المنظمة والداعمة ===== */
$allOrgs = array_merge($organizers, $partners, $support);
ob_start(); ?>
<section class="c-orgs c-sec" id="partners">
  <div class="c-wrap">
    <?= csr_head(tt('بالتنسيق والتعاون مع', 'In coordination and cooperation with'), tt('الجهات المنظمة والداعمة وضيوف المنتدى', 'Organisers, supporters & guests'), 'is-center') ?>
  </div>
  <?php if ($allOrgs): ?>
  <div class="c-marquee" aria-hidden="true">
    <div class="c-marquee-track">
      <?php for ($k = 0; $k < 2; $k++): foreach ($allOrgs as $o): ?><span class="c-marquee-item"><img src="<?= e(org_logo($o)) ?>" alt="" loading="lazy"></span><?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="c-wrap">
    <?php foreach ([[$organizers, tt('الجهات المنظمة', 'Organisers')], [$partners, tt('الجهات الداعمة وضيوف المنتدى', 'Supporters & guests')], [$sponsors, tt('الرعاة', 'Sponsors')]] as $grp): if (!$grp[0]) continue; ?>
      <h3 class="c-orgs-h" data-reveal><?= e($grp[1]) ?></h3>
      <div class="c-orgs-grid">
        <?php foreach ($grp[0] as $i => $o): ?>
          <a class="c-org" data-reveal style="--d:<?= ($i % 4) * 80 ?>ms"<?= $o['url'] ? ' href="' . e($o['url']) . '" target="_blank" rel="noopener"' : '' ?>>
            <span class="c-org-logo"><img src="<?= e(org_logo($o)) ?>" alt="" loading="lazy"></span>
            <span class="c-org-name"><?= e(ble('orgs', $o, 'name')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach ($support as $o): ?>
      <div class="c-support" data-reveal>
        <img src="<?= e(org_logo($o)) ?>" alt="" loading="lazy">
        <b><?= e(ble('orgs', $o, 'name')) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php $S['partners'] = ob_get_clean();

/* ===== معرض الصور ===== */
if ($gallery) {
    ob_start(); ?>
<section class="c-gallery c-sec" id="gallery">
  <div class="c-wrap">
    <?= csr_head(tt('من المنتدى', 'From the forum'), setting_l('gallery_title')) ?>
    <div class="c-gal-grid">
      <?php foreach ($gallery as $i => $g): ?><figure data-reveal="clip" style="--d:<?= ($i % 3) * 90 ?>ms"><img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e(bl($g, 'caption')) ?>" loading="lazy"></figure><?php endforeach; ?>
    </div>
  </div>
</section>
<?php $S['gallery'] = ob_get_clean();
}

/* ===== الختام ===== */
ob_start(); ?>
<section class="c-final" id="final">
  <div class="c-final-bg" aria-hidden="true"><img src="<?= e(slot_url('final_photo')) ?>"<?= slot_attr('final_photo') ?> alt="" loading="lazy" data-parallax="0.12"></div>
  <div class="c-final-pattern" aria-hidden="true"></div>
  <div class="c-wrap c-final-in">
    <?= csr_star('c-final-star') ?>
    <p class="c-final-quote" data-split><?= e(setting_l('hero_text')) ?></p>
    <p class="c-final-meta" data-reveal><span><?= e(setting_l('event_dates')) ?></span><i aria-hidden="true"></i><span><?= e(setting_l('venue')) ?></span></p>
    <?php if ($regOpen): ?><a class="c-btn c-btn--gold c-btn--lg" href="<?= e($home . 'register.php') ?>" data-reveal data-magnetic><span><?= e(tt('سجّل حضورك الآن', 'Register now')) ?></span></a><?php endif; ?>
  </div>
</section>
<?php $S['final'] = ob_get_clean();

/* الأقسام المخصّصة (نص / HTML / كاروسيل) */
require_once __DIR__ . '/app/sections_render.php';
foreach (q_all('SELECT * FROM custom_sections WHERE active = 1') as $cs) {
    $S['custom:' . $cs['id']] = render_custom_section($cs);
}

site_header();
?>
<section class="c-hero" id="top">
  <div class="c-hero-pattern" aria-hidden="true" data-pattern></div>
  <div class="c-wrap c-hero-grid">
    <div class="c-hero-txt">
      <span class="c-hero-eyebrow" data-reveal><?= csr_star('c-eyebrow-star', false) ?><?= e(setting_l('edition')) ?></span>
      <h1 class="c-h1" data-split="hero"><?= e(setting_l('site_name')) ?></h1>
      <?php if ($L === 'ar'): ?><p class="c-hero-en" data-reveal lang="en" dir="ltr"><?= e(setting('site_name_en')) ?></p><?php endif; ?>
      <p class="c-hero-tag" data-reveal><?= e(setting_l('tagline')) ?></p>
      <div class="c-hero-meta" data-reveal>
        <span><b><?= e(tt('الموعد', 'Date')) ?></b><?= e(setting_l('event_dates')) ?></span>
        <span><b><?= e(tt('المكان', 'Venue')) ?></b><?= e(setting_l('venue')) ?></span>
      </div>
      <div class="c-count" data-countdown="<?= e(setting('countdown_target')) ?>" data-reveal aria-label="<?= e(tt('العد التنازلي لانطلاق المنتدى', 'Countdown to the forum')) ?>">
        <?php foreach (['d' => tt('يوم', 'Days'), 'h' => tt('ساعة', 'Hours'), 'm' => tt('دقيقة', 'Min'), 's' => tt('ثانية', 'Sec')] as $k => $lbl): ?>
          <div><b data-cd="<?= $k ?>">00</b><span><?= $lbl ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="c-hero-cta" data-reveal>
        <?php if ($regOpen): ?><a class="c-btn c-btn--gold c-btn--lg" href="<?= e($home . 'register.php') ?>" data-magnetic><span><?= e(tt('سجّل حضورك', 'Register now')) ?></span></a><?php endif; ?>
        <a class="c-btn c-btn--ghost c-btn--lg" href="#agenda" data-magnetic><span><?= e(tt('اكتشف البرنامج', 'Explore the programme')) ?></span></a>
      </div>
    </div>
    <figure class="c-hero-fig" data-reveal="clip-up">
      <img src="<?= e(slot_url('hero_photo')) ?>"<?= slot_attr('hero_photo') ?> alt="<?= e(tt('نهر دجلة في بغداد', 'The Tigris in Baghdad')) ?>" fetchpriority="high" data-parallax="0.1">
      <?= csr_star('c-hero-badge') ?>
    </figure>
  </div>
  <a class="c-scroll" href="#about" aria-label="<?= e(tt('انتقل إلى المحتوى', 'Scroll to content')) ?>"><span></span></a>
</section>
<div class="c-ticker" aria-hidden="true">
  <div class="c-ticker-track"><?php for ($k = 0; $k < 6; $k++): ?><span><?= e(setting_l('hero_text')) ?></span><?= csr_star('c-ticker-star', false) ?><span><?= e(setting_l('tagline')) ?></span><?= csr_star('c-ticker-star', false) ?><?php endfor; ?></div>
</div>
<?php
foreach ($order as $sec) {
    $k = $sec['key'] ?? '';
    if (!isset($S[$k]) || trim($S[$k]) === '') continue;
    if ($k === 'speakers' && !$speakers) continue;
    if (!$editing) {
        if (!empty($sec['on'])) echo $S[$k];
        continue;
    }
    echo '<div class="edit-sec' . (empty($sec['on']) ? ' is-off' : '') . '" data-sec="' . e($k) . '" data-label="' . e($sec['ar'] ?? $k) . '">' . $S[$k] . '</div>';
}
site_footer();
