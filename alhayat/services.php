<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
$locale = current_locale();
$isAr = $locale === 'ar';
$services = content_data('services');
render_header($locale, $isAr ? 'خدماتنا' : 'Our services', 'services');
?>
<style>
  .svc-section{padding-block:clamp(56px,7vw,104px)}
  .svc-section:nth-child(even){background:linear-gradient(180deg,#f5faf6,#ffffff)}
  .svc-detail-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:clamp(36px,6vw,90px);align-items:stretch}
  .svc-detail-grid.rev .feature-image{order:2}
  .svc-media{position:relative;border-radius:var(--card-radius,26px);overflow:hidden;min-height:clamp(320px,34vw,500px);box-shadow:0 28px 70px rgba(12,65,42,.15);background:#dfe9e2}
  .svc-media img,.svc-media video{width:100%;height:100%;position:absolute;inset:0;object-fit:cover;transform:scale(1.04);transition:transform .9s cubic-bezier(.22,.8,.2,1)}
  .svc-media:hover img,.svc-media:hover video{transform:scale(1.1)}
  .svc-index{position:absolute;z-index:2;top:18px;inset-inline-start:18px;display:inline-grid;place-items:center;min-width:46px;height:46px;padding-inline:10px;border-radius:14px;background:rgba(255,255,255,.92);color:var(--green-700,#12693f);font:800 1.05rem/1 var(--latin,'Inter',sans-serif);box-shadow:0 8px 20px rgba(12,65,42,.18)}
  .svc-copy{display:flex;flex-direction:column;justify-content:center}
  .svc-copy .eyebrow{color:var(--green-700,#12693f)}
  .svc-copy h2{font-size:clamp(1.7rem,3.2vw,2.9rem);line-height:1.25;margin:6px 0 16px;color:var(--ink,#0e241b);letter-spacing:-.01em}
  .svc-lead{font-size:clamp(1rem,1.3vw,1.15rem);color:var(--ink-2,#20362c);line-height:1.8;margin:0 0 14px;font-weight:600}
  .svc-copy>p{color:var(--muted,#5c7268);line-height:2;font-size:clamp(.9rem,1vw,1rem);margin:0 0 22px}
  .svc-points{display:flex;flex-direction:column;gap:10px;margin:0 0 26px}
  .svc-points div{position:relative;padding-inline-start:26px;color:var(--ink-2,#20362c);font-size:.9rem;line-height:1.6}
  .svc-points div::before{content:"";position:absolute;inset-inline-start:0;top:.45em;width:12px;height:12px;border-radius:50%;background:radial-gradient(circle at 30% 30%,#28b46b,#12693f)}
  .svc-cta{padding:clamp(48px,7vw,90px) 0 clamp(60px,8vw,110px);text-align:center}
  .svc-cta h2{font-size:clamp(1.5rem,3vw,2.4rem);color:var(--ink,#0e241b);margin:0 0 24px;font-weight:800;letter-spacing:-.01em}
  .svc-cta-btn{display:inline-flex;align-items:center;gap:12px;background:linear-gradient(135deg,#1e9e5a,#12693f);color:#fff!important;font-weight:800;padding:15px 34px;border-radius:999px;text-decoration:none;box-shadow:0 16px 34px rgba(18,105,63,.3);transition:transform .25s var(--ease,ease),box-shadow .25s}
  .svc-cta-btn:hover{transform:translateY(-3px);box-shadow:0 20px 42px rgba(18,105,63,.36);color:#fff!important}
  @media(max-width:820px){
    .svc-detail-grid{grid-template-columns:1fr;gap:26px}
    .svc-detail-grid.rev .feature-image{order:0}
    .svc-media{min-height:0;aspect-ratio:16/10}
  }
</style>
<main>
  <section class="inner-hero"><div class="section-shell">
    <p class="eyebrow"><span></span><?= $isAr ? 'خدماتنا' : 'Our services' ?></p>
    <h1><?= $isAr ? 'منظومة دوائية متكاملة تحت سقف واحد' : 'A complete pharmaceutical system under one roof' ?></h1>
    <p><?= $isAr ? 'من التسجيل والتخزين المراقب إلى التوزيع الوطني واليقظة الدوائية — خبرة تنظيمية ولوجستية وتجارية تعمل كنظام واحد.' : 'From registration and monitored storage to nationwide distribution and pharmacovigilance — regulatory, logistics and commercial expertise working as one system.' ?></p>
  </div></section>

  <?php $i = 0; foreach ($services as $slug => $service): $rev = ($i % 2 === 1); ?>
  <section class="svc-section" id="<?= h($slug) ?>"><div class="section-shell svc-detail-grid<?= $rev ? ' rev' : '' ?>">
    <div class="feature-image svc-media">
      <span class="svc-index"><?= h($service['index']) ?></span>
      <?php if (preg_match('/\.(mp4|webm)$/i', (string) $service['image'])): ?>
        <video src="<?= h(site_url($service['image'])) ?>" autoplay muted loop playsinline preload="metadata" aria-label="<?= h(localized($service['title'], $locale)) ?>"></video>
      <?php else: ?>
        <img src="<?= h(site_url($service['image'])) ?>" alt="<?= h(localized($service['title'], $locale)) ?>" loading="lazy">
      <?php endif; ?>
    </div>
    <div class="prose svc-copy">
      <p class="eyebrow"><span></span><?= $isAr ? 'خدمة متكاملة' : 'Integrated service' ?></p>
      <h2><?= h(localized($service['title'], $locale)) ?></h2>
      <p class="svc-lead"><?= h(localized($service['summary'], $locale)) ?></p>
      <p><?= h(localized($service['detail'], $locale)) ?></p>
    </div>
  </div></section>
  <?php $i++; endforeach; ?>

  <section class="svc-cta"><div class="section-shell">
    <h2><?= $isAr ? 'لديك استفسار أو طلب؟' : 'Have a question or request?' ?></h2>
    <a class="v2-btn svc-cta-btn" href="<?= h(site_url('messages.php?lang=' . $locale)) ?>"><?= $isAr ? 'أرسل رسالة' : 'Send a message' ?><span aria-hidden="true">↗</span></a>
  </div></section>
</main>
<?php render_footer($locale); ?>
