<?php
require_once __DIR__ . '/helpers.php';

/** يعرض قسماً مخصّصاً في الصفحة الرئيسية */
function render_custom_section(array $cs): string
{
    $L = lang();
    $title = trim((string)($cs['title_' . $L] ?? '')) ?: trim((string)($cs['title_' . ($L === 'ar' ? 'en' : 'ar')] ?? ''));
    $bgClass = $cs['bg'] === 'dark' ? 'cs-dark' : ($cs['bg'] === 'white' ? '' : 'cs-light');
    ob_start();
    ?>
    <section class="sec custom-sec <?= $bgClass ?>" id="custom-<?= (int)$cs['id'] ?>">
      <div class="wrap">
        <?php if ($title !== ''): ?><h2 class="sec-title reveal" style="text-align:center;margin-inline:auto"><?= e($title) ?></h2><?php endif; ?>
        <?php if ($cs['type'] === 'html'):
            $data = json_decode((string)($cs['data'] ?? '{}'), true) ?: [];
            $h = max(120, min(2000, (int)($data['height'] ?? 560)));
        ?>
          <div class="cs-embed reveal">
            <iframe class="cs-frame" style="height:<?= $h ?>px"
              sandbox="allow-scripts allow-forms allow-popups allow-modals allow-downloads"
              srcdoc="<?= e((string)$cs['raw_code']) ?>" title="<?= e($title) ?>"></iframe>
          </div>
        <?php elseif ($cs['type'] === 'carousel'):
            $imgs = json_decode((string)($cs['data'] ?? '[]'), true) ?: [];
            $imgs = is_array($imgs) ? ($imgs['images'] ?? $imgs) : [];
        ?>
          <div class="carousel reveal" data-autoplay="5" style="margin-top:20px">
            <div class="carousel-track">
              <?php foreach ($imgs as $im): $src = is_array($im) ? ($im['src'] ?? '') : $im; $cap = is_array($im) ? ($im['cap'] ?? '') : ''; if ($src === '') continue; ?>
                <div class="carousel-slide">
                  <img src="<?= e(strpos($src, 'http') === 0 ? $src : upload_url($src)) ?>" alt="" loading="lazy">
                  <?php if ($cap): ?><div class="carousel-cap"><b><?= e($cap) ?></b></div><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (count($imgs) > 1): ?>
            <button class="carousel-btn carousel-prev" aria-label="prev"><?= icon($L === 'ar' ? 'arrow-r' : 'arrow-l') ?></button>
            <button class="carousel-btn carousel-next" aria-label="next"><?= icon($L === 'ar' ? 'arrow-l' : 'arrow-r') ?></button>
            <div class="carousel-dots"></div>
            <?php endif; ?>
          </div>
        <?php else: /* text */
            $body = trim((string)($cs['body_' . $L] ?? '')) ?: trim((string)($cs['body_' . ($L === 'ar' ? 'en' : 'ar')] ?? ''));
        ?>
          <div class="cs-text reveal"><?= nl2br(e($body)) ?></div>
        <?php endif; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
}
