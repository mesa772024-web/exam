<?php
/** عرض الصفحات المخصصة المبنية من محرر الصفحات */
require_once __DIR__ . '/app/guard.php';
guard_boot();
require_once __DIR__ . '/app/layout.php';

$slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower((string)($_GET['s'] ?? '')));
$page = $slug !== '' ? q_one('SELECT * FROM pages WHERE slug = ? AND published = 1', [$slug]) : null;

if ($page === null) {
    http_response_code(404);
    site_header('404');
    echo '<section class="page-hero"><div class="wrap"><h1>404</h1></div></section>';
    echo '<div class="wrap" style="padding:60px 0;text-align:center"><a class="btn btn-cta btn-lg" href="' . e(base_url()) . '/">' . e(tr('back_home')) . '</a></div>';
    site_footer();
    exit;
}

$L = lang();

/* ===== وضع HTML الكامل: إطار معزول يغطي الصفحة دون تداخل ===== */
if (($page['mode'] ?? 'blocks') === 'full') {
    site_header(bl($page, 'title'));
    ?>
    <div class="fullpage-embed">
      <iframe class="fullpage-frame"
        sandbox="allow-scripts allow-forms allow-popups allow-modals allow-downloads allow-popups-to-escape-sandbox"
        srcdoc="<?= e((string)$page['raw_code']) ?>"
        title="<?= e(bl($page, 'title')) ?>"></iframe>
    </div>
    <?php
    site_footer();
    exit;
}

$blocks = json_decode((string)$page['blocks'], true) ?: [];

/** قيمة ثنائية اللغة من بيانات البلوك */
function bv(array $d, string $key): string
{
    $v = trim((string)($d[$key . '_' . lang()] ?? ''));
    if ($v === '') $v = trim((string)($d[$key . '_' . (lang() === 'ar' ? 'en' : 'ar')] ?? ''));
    return $v;
}

function blk_img_src(string $src): string
{
    if ($src === '') return '';
    if (strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) return $src;
    if (strpos($src, 'assets:') === 0) return asset('img/' . substr($src, 7));
    return upload_url($src);
}

site_header(bl($page, 'title'));
?>
<section class="page-hero">
  <div class="wrap"><h1><?= e(bl($page, 'title')) ?></h1></div>
</section>
<div class="page-body">
  <div class="wrap">
  <?php foreach ($blocks as $b):
      $t = $b['type'] ?? '';
      $d = $b['data'] ?? [];
  ?>
    <?php if ($t === 'heading'): ?>
      <div class="blk blk-heading"><h2><?= e(bv($d, 'text')) ?></h2></div>

    <?php elseif ($t === 'text'): ?>
      <div class="blk blk-text"><p><?= e(bv($d, 'text')) ?></p></div>

    <?php elseif ($t === 'image'): ?>
      <figure class="blk blk-image">
        <img src="<?= e(blk_img_src((string)($d['src'] ?? ''))) ?>" alt="<?= e(bv($d, 'caption')) ?>" loading="lazy">
        <?php if (bv($d, 'caption')): ?><figcaption><?= e(bv($d, 'caption')) ?></figcaption><?php endif; ?>
      </figure>

    <?php elseif ($t === 'imgtext'): ?>
      <div class="blk blk-imgtext">
        <img src="<?= e(blk_img_src((string)($d['src'] ?? ''))) ?>" alt="" loading="lazy">
        <div>
          <h3><?= e(bv($d, 'title')) ?></h3>
          <p><?= e(bv($d, 'text')) ?></p>
        </div>
      </div>

    <?php elseif ($t === 'cards'): ?>
      <div class="blk blk-cards">
        <?php foreach (($d['items'] ?? []) as $it): ?>
          <div class="blk-card">
            <b><?= e(trim((string)($it['title_' . $L] ?? ($it['title_ar'] ?? '')))) ?></b>
            <p><?= e(trim((string)($it['text_' . $L] ?? ($it['text_ar'] ?? '')))) ?></p>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($t === 'list'): ?>
      <div class="blk blk-list"><ul>
        <?php foreach (preg_split('/\r?\n/', bv($d, 'items')) as $li): if (trim($li) === '') continue; ?>
          <li><?= e(trim($li)) ?></li>
        <?php endforeach; ?>
      </ul></div>

    <?php elseif ($t === 'video'): ?>
      <div class="blk blk-video">
        <?php $src = (string)($d['src'] ?? ''); ?>
        <?php if (preg_match('~youtube\.com|youtu\.be~', $src)):
            preg_match('~(?:v=|youtu\.be/|embed/)([\w-]{6,})~', $src, $m);
            $vid = $m[1] ?? '';
        ?>
          <iframe src="https://www.youtube.com/embed/<?= e($vid) ?>" title="video" loading="lazy" allowfullscreen></iframe>
        <?php elseif ($src !== ''): ?>
          <video controls preload="metadata" src="<?= e(blk_img_src($src)) ?>"></video>
        <?php endif; ?>
      </div>

    <?php elseif ($t === 'gallery'): ?>
      <div class="blk blk-gallery">
        <?php foreach (preg_split('/\r?\n/', (string)($d['srcs'] ?? '')) as $g): if (trim($g) === '') continue; ?>
          <img src="<?= e(blk_img_src(trim($g))) ?>" alt="" loading="lazy">
        <?php endforeach; ?>
      </div>

    <?php elseif ($t === 'cta'): ?>
      <div class="blk blk-cta">
        <h3><?= e(bv($d, 'title')) ?></h3>
        <a class="btn btn-cta btn-lg" href="<?= e((string)($d['url'] ?? '#')) ?>"><?= e(bv($d, 'label')) ?></a>
      </div>

    <?php elseif ($t === 'pdf'): ?>
      <?php $psrc = (string)($d['src'] ?? ''); if ($psrc !== ''): ?>
      <div class="blk blk-pdf">
        <div class="pdf-bar">
          <b><?= icon('file-pdf') ?><?= e(bv($d, 'title') ?: 'ملف PDF') ?></b>
          <a class="pdf-dl" href="<?= e(upload_url($psrc)) ?>" download><?= icon('download') ?><?= tt('تنزيل', 'Download') ?></a>
        </div>
        <iframe src="<?= e(upload_url($psrc)) ?>#view=FitH" title="PDF" loading="lazy"></iframe>
      </div>
      <?php endif; ?>

    <?php elseif ($t === 'html'): ?>
      <?php $rawHtml = (string)($d['html'] ?? ''); if (trim($rawHtml) !== ''): ?>
      <div class="blk blk-embed">
        <iframe sandbox="allow-scripts allow-popups allow-forms" loading="lazy"
                style="height:<?= max(120, min(3000, (int)($d['height'] ?? 500))) ?>px"
                srcdoc="<?= e($rawHtml) ?>" title="embed"></iframe>
      </div>
      <?php endif; ?>

    <?php elseif ($t === 'spacer'): ?>
      <div class="blk" style="height:<?= (int)($d['h'] ?? 40) ?>px"></div>
    <?php endif; ?>
  <?php endforeach; ?>
  </div>
</div>
<?php site_footer(); ?>
