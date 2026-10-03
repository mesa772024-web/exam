# -*- coding: utf-8 -*-
"""Generates the Baghdad Al Hayat static site pages (shared header/footer)."""
import os, html

OUT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SC = os.path.dirname(os.path.abspath(__file__))
LOCKUP = open(os.path.join(SC, 'lockup.html'), encoding='utf8').read()

EMAIL = 'info@baghdadalhayat.com'
SITE = 'https://www.baghdadalhayat.com'
NAME_AR = 'مكتب بغداد الحياة العلمي'
NAME_EN = 'Baghdad Al Hayat Scientific Office'


def T(ar, en, tag='span', cls=''):
    c = (' ' + cls) if cls else ''
    return f'<{tag} class="ar{c}">{ar}</{tag}><{tag} class="en{c}">{en}</{tag}>'


NOCLS = ''
CUR = ' aria-current="page"'
ARR = '<span class="arr" aria-hidden="true">↗</span>'

NAV = [
    ('index.html', 'الرئيسية', 'Home'),
    ('about.html', 'من نحن', 'About'),
    ('services.html', 'خدماتنا', 'Services'),
    ('quality.html', 'الجودة', 'Quality'),
    ('partners.html', 'الشركاء', 'Partners'),
]

SERVICES = [
    dict(slug='regulatory-affairs', idx='01', ic='reg',
         t=('التسجيل والشؤون التنظيمية', 'Registration & regulatory affairs'),
         short=('التسجيل الدوائي', 'Regulatory registration'),
         card=('ملفات مكتملة ومسارات واضحة تواكب المتطلبات المحلية من التقديم حتى الموافقة.', 'Complete dossiers and clear pathways through every local requirement, from submission to approval.'),
         summary=('خبرة دقيقة في ملفات التسجيل ومتطلبات وزارة الصحة، مع 53 موقع تصنيع و148 منتجاً دوائياً مسجلاً.', 'Precise dossier management and Ministry of Health expertise, with 53 manufacturing sites and 148 registered products.'),
         detail=('يتولى فريقنا إعداد الملفات ومتابعتها حتى الموافقة النهائية، ويضمن توافق كل منتج مع أعلى معايير السلامة والفعالية والجودة. تساعد خبرتنا وعلاقاتنا التنظيمية شركاءنا العالميين على إيصال العلاجات الأساسية إلى السوق العراقي بكفاءة.', 'Our team manages dossiers from preparation to final approval, ensuring every product meets rigorous safety, efficacy and quality standards. Established regulatory relationships help global partners bring essential therapies to Iraq efficiently.'),
         facts=[('53', 'موقع تصنيع', 'manufacturing sites'), ('148', 'منتجاً مسجلاً', 'registered products')],
         media=('video', 'assets/video/products.mp4', 'assets/img/photos/products-poster.jpg')),
    dict(slug='warehousing', idx='02', ic='cold',
         t=('المخازن وسلسلة التبريد', 'Warehousing & cold chain'),
         short=('التخزين وسلسلة التبريد', 'Warehousing & cold chain'),
         card=('بيئات مراقبة ومؤرشفة تحفظ استقرار المنتج في كل لحظة.', 'Monitored, documented environments that preserve product integrity at every moment.'),
         summary=('مرافق متوافقة مع ممارسات التخزين والتوزيع الجيد ومراقبة حرارية لحظية تحقق التزاماً بنسبة 99.7%.', 'GSDP-compliant, temperature-controlled facilities with real-time monitoring and 99.7% cold-chain compliance.'),
         detail=('تعمل مخازن بغداد وأربيل والبصرة بأنظمة متقدمة لإدارة المخزون والتتبع، مع غرف تبريد وعمليات نقل مؤمنة تحافظ على سلامة المنتجات من الاستلام حتى التسليم النهائي.', 'Facilities in Baghdad, Erbil and Basra use advanced inventory and traceability systems, cold rooms and secure logistics to preserve product integrity from receipt to final delivery.'),
         facts=[('99.7%', 'التزام سلسلة التبريد', 'cold-chain compliance'), ('3', 'مخازن: بغداد وأربيل والبصرة', 'warehouses: Baghdad, Erbil, Basra')],
         media=('video', 'assets/video/warehouse.mp4', 'assets/img/photos/warehouse-poster.jpg')),
    dict(slug='distribution', idx='03', ic='truck',
         t=('التوزيع الوطني', 'Nationwide distribution'),
         short=('التوزيع الوطني', 'Nationwide distribution'),
         card=('مسارات مدروسة تربط بغداد بالمستشفيات والصيدليات في أنحاء العراق.', 'Planned routes connecting Baghdad with hospitals and pharmacies throughout Iraq.'),
         summary=('شبكة تغطي 439,000 كم² وتصل إلى أكثر من 7,000 صيدلية ومذخر ومؤسسة صحية عبر ستة مراكز و46+ ناقلاً مبرداً.', 'A 439,000 km² network reaching 7,000+ pharmacies, drugstores and healthcare institutions through six centres and 46+ cold-chain couriers.'),
         detail=('تخدم كياناتنا في بغداد وأربيل (أومنيا كردستان) والبصرة المؤسسات الصحية والصيدليات مباشرة، وتربط الإمداد بالطلب الفعلي لتقليل الانقطاع وتسريع التسليم في جميع مناطق العراق.', 'Our Baghdad, Erbil (Omnia Kurdistan) and Basra entities serve healthcare institutions and pharmacies directly, aligning supply with live demand to reduce stockouts and accelerate delivery nationwide.'),
         facts=[('439,000', 'كم² ضمن الشبكة', 'km² network'), ('7,000+', 'صيدلية ومذخر ومؤسسة', 'pharmacies & institutions'), ('46+', 'ناقلاً مبرداً', 'cold-chain couriers')],
         media=('img', 'assets/img/photos/warehouse-racks.jpg', None)),
    dict(slug='customer-relations', idx='04', ic='users',
         t=('علاقات العملاء والمبيعات', 'Customer relations & sales'),
         short=('علاقات الشركاء', 'Partner relations'),
         card=('فريق يسمع، يستجيب، ويبقي شركاءنا على اطلاع في كل نقطة اتصال.', 'A team that listens, responds, and keeps partners informed at every touchpoint.'),
         summary=('100 ممثل ومشرف ميداني مدعومون بأنظمة CRM وERP لتقديم استجابة دقيقة ورؤية سوقية متقدمة.', 'A 100-person field force supported by CRM and ERP for responsive service and data-driven market insight.'),
         detail=('نبني شراكات طويلة الأمد مع الصيدليات والمستشفيات والموزعين عبر الزيارات والمتابعة المستمرة وإدارة المخزون والطلبات والمرتجعات والتحصيل إلكترونياً.', 'We build long-term relationships with pharmacies, hospitals and distributors through regular visits, continuous follow-up, stock checks, order fulfilment, quality-managed returns and electronic collection.'),
         facts=[('100', 'ممثل ومشرف ميداني', 'field reps & supervisors'), ('CRM · ERP', 'أنظمة متكاملة', 'integrated systems')],
         media=('img', 'assets/img/photos/corridor.jpg', None)),
    dict(slug='pharmacovigilance', idx='05', ic='shield',
         t=('اليقظة الدوائية', 'Pharmacovigilance'),
         short=('اليقظة الدوائية', 'Pharmacovigilance'),
         card=('مراقبة سلامة مستمرة وإبلاغ مسؤول يحمي المريض بعد وصول الدواء.', 'Continuous safety monitoring and responsible reporting beyond delivery.'),
         summary=('نظام متكامل لرصد وتقييم سلامة الدواء طوال دورة حياة المنتج وفق معايير وزارة الصحة وFDA وEMA.', 'An integrated medicine-safety framework throughout the product lifecycle, aligned with MOH, FDA and EMA standards.'),
         detail=('يشمل النظام الإبلاغ عن الأحداث العكسية واكتشاف الإشارات ومراجعات السلامة الدورية PSURs والدمج الكامل مع ضمان الجودة والإجراءات التشغيلية القياسية.', 'The system covers adverse-event reporting, signal detection, periodic safety update reports (PSURs), continuous monitoring and full QA/SOP integration.'),
         facts=[('MOH · FDA · EMA', 'معايير معتمدة', 'aligned standards'), ('PSURs', 'مراجعات سلامة دورية', 'periodic safety reports')],
         media=('img', 'assets/img/photos/products.jpg', None)),
    dict(slug='compliance', idx='06', ic='award',
         t=('الامتثال وضمان الجودة', 'Compliance & quality assurance'),
         short=('الجودة والامتثال', 'Quality & compliance'),
         card=('أنظمة مدققة وإجراءات موثقة تحوّل المعايير إلى ممارسة يومية.', 'Audited systems and documented procedures that turn standards into daily practice.'),
         summary=('حوكمة وإجراءات تشغيلية وتدقيق وتدريب مستمر مدعوم بأنظمة ISO 9001 و22301 و27001 و45001 و14001.', 'Governance, SOPs, audits and continuous training supported by ISO 9001, 22301, 27001, 45001 and 14001 systems.'),
         detail=('يمثل الامتثال قيمة تشغيلية أساسية في التسجيل والتوزيع واليقظة الدوائية وحماية المعلومات واستمرارية الأعمال والصحة والسلامة والبيئة.', 'Compliance is embedded across registration, distribution, pharmacovigilance, information security, business continuity, occupational safety and environmental management.'),
         facts=[('5', 'أنظمة ISO', 'ISO systems'), ('GSDP', 'ممارسات التخزين والتوزيع الجيد', 'good storage & distribution')],
         media=('img', 'assets/img/photos/warehouse-boxes.jpg', None)),
]
# order used on the home grid (same as the original landing page)
HOME_ORDER = ['regulatory-affairs', 'warehousing', 'distribution', 'pharmacovigilance', 'compliance', 'customer-relations']

ICONS = {
    'reg': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="m9 14.5 2 2 4-4.5"/>',
    'cold': '<path d="M12 2.5v19M4 7l16 10M20 7 4 17"/><path d="m9.5 4 2.5 2 2.5-2M9.5 20l2.5-2 2.5 2"/>',
    'truck': '<path d="M2.5 6.5h11.5v10H2.5z"/><path d="M14 10h4.2l3.3 3.4v3.1H14"/><circle cx="6.5" cy="17.5" r="1.9"/><circle cx="17.5" cy="17.5" r="1.9"/>',
    'users': '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0"/><path d="M15.5 5a3 3 0 0 1 0 6M17.5 14.3a5.5 5.5 0 0 1 3 5.2"/>',
    'shield': '<path d="M12 2.8 4.8 5.8v5.4c0 4.6 3.1 8.5 7.2 10 4.1-1.5 7.2-5.4 7.2-10V5.8z"/><path d="M8 12.2h2.2l1.2-2.4 2 4.6 1.2-2.2H16"/>',
    'award': '<circle cx="12" cy="9" r="5.5"/><path d="m8.8 13.6-1.6 7.4L12 18.4l4.8 2.6-1.6-7.4"/><path d="m10 9 1.4 1.4L14.2 7.6"/>',
}


def icon(k):
    return f'<svg viewBox="0 0 24 24" aria-hidden="true">{ICONS[k]}</svg>'


PARTNERS = [
    ('AstraZeneca', '1996', 'astrazeneca.png'), ('Servier', '2012', 'servier.png'), ('MSD', '2017', 'msd.png'),
    ('Pfizer', '2018', 'pfizer.png'), ('Biogaran', '2020', 'biogaran.png'), ('Pharmanovia', '2022', 'pharmanovia.png'),
    ('AbbVie', '2024', 'abbvie.png'), ('Cheplapharm', '2024', 'cheplapharm.png'), ('Amgen', '2026', None),
]

LOCATIONS = [
    (('بغداد', 'Baghdad'), ('حي بابل، محلة 929، شارع 19، بناية مكتب بغداد الحياة العلمي', 'Hay Babel, District 929, St. 19, Baghdad Al Hayat Scientific Office Building'), '+964 782 3360 920'),
    (('أربيل', 'Erbil'), ('أومنيا كردستان، برج العدالة، الطابق 23', 'Omnia Kurdistan, Justice Tower, 23rd floor'), '+964 782 7208 470'),
    (('البصرة', 'Basra'), ('البراضعية، شارع السراجي', 'Al Bradiyyah, Sarraji Street'), '+964 783 3084 690'),
]


def tel(n):
    return 'tel:' + n.replace(' ', '')


def head(ar_title, en_title, desc_ar, desc_en, page):
    full_ar = f'{ar_title} | {NAME_AR}' if ar_title else f'{NAME_AR} | {NAME_EN}'
    full_en = f'{en_title} | {NAME_EN}' if en_title else f'{NAME_EN} | {NAME_AR}'
    url = SITE + '/' + ('' if page == 'index.html' else page)
    return f'''<!DOCTYPE html>
<html lang="ar" dir="rtl" data-lang="ar" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{html.escape(full_ar)}</title>
<meta name="title-ar" content="{html.escape(full_ar)}">
<meta name="title-en" content="{html.escape(full_en)}">
<meta name="description" content="{html.escape(desc_ar + ' ' + desc_en)}">
<meta name="theme-color" content="#ffffff">
<link rel="canonical" href="{url}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{NAME_AR} | {NAME_EN}">
<meta property="og:title" content="{html.escape(full_ar)}">
<meta property="og:description" content="{html.escape(desc_ar)}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE}/assets/img/brand/og.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="assets/img/brand/leaf.svg">
<link rel="alternate icon" type="image/png" href="assets/img/brand/leaf.png">
<link rel="apple-touch-icon" href="assets/img/brand/leaf.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script>(function(){{var d=document.documentElement,l=null;d.className=d.className.replace('no-js','js');try{{var q=new URLSearchParams(location.search).get('lang');if(q==='en'||q==='ar'){{l=q;localStorage.setItem('bh-lang',q);}}else{{l=localStorage.getItem('bh-lang');}}}}catch(e){{}}if(l==='en'){{d.setAttribute('data-lang','en');d.lang='en';d.dir='ltr';}}}})();</script>
</head>'''


def brand(cls=''):
    return (f'<a class="brand{cls}" href="index.html" data-label-ar="{NAME_AR} — الرئيسية" data-label-en="{NAME_EN} — Home" aria-label="{NAME_AR} — الرئيسية">'
            '<img class="b-leaf" src="assets/img/brand/leaf.svg" alt="" width="194" height="368">'
            '<img class="b-word" src="assets/img/brand/wordmark.svg" alt="" width="748" height="191"></a>')


def nav(page):
    links = ''.join(
        f'<a href="{h}"{CUR if h == page else ""}>{T(a, e)}</a>' for h, a, e in NAV)
    mlinks = ''.join(
        f'<a class="m-link" href="{h}"{CUR if h == page else ""}>{T(a, e)}<small>0{i + 1}</small></a>'
        for i, (h, a, e) in enumerate(NAV + [('contact.html', 'تواصل معنا', 'Contact us')]))
    return f'''<a class="skip-link" href="#main">{T('تخطَّ إلى المحتوى', 'Skip to content')}</a>
<nav class="nav" aria-label="Main">
  <div class="wrap nav-inner">
    {brand()}
    <div class="nav-links">{links}</div>
    <div class="nav-actions">
      <button class="lang-btn" type="button" data-lang-toggle data-label-ar="English" data-label-en="العربية" aria-label="English"><span class="ar">EN</span><span class="en">ع</span></button>
      <a class="btn btn-primary nav-cta" href="contact.html"{' aria-current="page"' if page == 'contact.html' else ''}>{T('تواصل معنا', 'Contact us')}</a>
      <button class="menu-btn" type="button" aria-expanded="false" aria-controls="mobile-menu" data-label-ar="القائمة" data-label-en="Menu" aria-label="القائمة"><span></span></button>
    </div>
  </div>
</nav>
<div class="mobile-menu" id="mobile-menu" aria-hidden="true">
  {mlinks}
  <div class="m-foot">
    <span>{T('بغداد · العراق · منذ 1996', 'Baghdad · Iraq · Since 1996')}</span>
    <a href="mailto:{EMAIL}">{EMAIL}</a>
    <a href="{tel(LOCATIONS[0][2])}" dir="ltr">{LOCATIONS[0][2]}</a>
  </div>
</div>'''


def footer():
    svc = ''.join(f'<a href="services.html#{s["slug"]}">{T(*s["short"])}</a>' for s in SERVICES)
    return f'''<footer class="footer">
  <img class="leaf-mark" src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">
  <div class="wrap">
    <div class="f-top">
      <div class="f-brand">
        {brand()}
        <p>{T('حي بابل، محلة 929، شارع 19، بناية مكتب بغداد الحياة العلمي — بغداد، العراق.', 'Hay Babel, District 929, St. 19, Baghdad Al Hayat Scientific Office Building — Baghdad, Iraq.')}</p>
      </div>
      <div class="f-col">
        <h3>{T('استكشف', 'Explore')}</h3>
        <a href="about.html">{T('من نحن', 'About us')}</a>
        <a href="about.html#leadership">{T('القيادة', 'Leadership')}</a>
        <a href="quality.html">{T('الجودة والامتثال', 'Quality & compliance')}</a>
        <a href="partners.html">{T('الشركاء', 'Partners')}</a>
        <a href="contact.html">{T('تواصل معنا', 'Contact us')}</a>
      </div>
      <div class="f-col">
        <h3>{T('خدماتنا', 'Services')}</h3>
        {svc}
      </div>
      <div class="f-col">
        <h3>{T('تواصل', 'Connect')}</h3>
        <a class="ltr" href="mailto:{EMAIL}">{EMAIL}</a>
        {''.join(f'<a class="ltr" href="{tel(p)}">{p}</a>' for _, _, p in LOCATIONS)}
      </div>
    </div>
    <div class="f-bottom">
      <span class="latin">© <span data-year>2026</span> {NAME_EN.upper()}</span>
      <span>{T('حيث المريض أولويتنا · بغداد، العراق', 'Where the patient is our priority · Baghdad, Iraq')}</span>
    </div>
  </div>
</footer>'''


def page(name, ar_title, en_title, desc, body_cls, main, scripts=''):
    return f'''{head(ar_title, en_title, desc[0], desc[1], name)}
<body class="{body_cls}">
{nav(name)}
<main id="main">
{main}
</main>
{footer()}
<script src="assets/js/main.js"></script>{scripts}
</body>
</html>
'''


def page_hero(eyebrow, h1, p):
    return f'''<header class="page-hero">
  <div class="fx" data-auto aria-hidden="true"><div class="glow g1"></div><div class="glow g2"></div><img class="watermark" src="assets/img/brand/leaf.svg" alt=""><div class="particles" data-count-p="12"></div></div>
  <div class="wrap">
    <img class="ph-leaf" src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">
    <p class="eyebrow">{T(*eyebrow)}</p>
    <h1>{T(*h1)}</h1>
    <div class="rule hero-rule" aria-hidden="true"><span></span><i></i><span></span></div>
    <p>{T(*p)}</p>
  </div>
</header>'''


def media_html(m, alt, cls='media'):
    kind, src, poster = m
    if kind == 'video':
        return f'<figure class="{cls}"><video src="{src}" poster="{poster}" autoplay muted loop playsinline preload="metadata" aria-label="{alt}"></video>'
    return f'<figure class="{cls}"><img src="{src}" alt="{alt}" loading="lazy">'


def cta_band(eyebrow, title, btn):
    return f'''<section class="block tight">
  <div class="wrap">
    <div class="cta-band reveal">
      <div>
        <p class="eyebrow">{T(*eyebrow)}</p>
        <h2>{T(*title)}</h2>
      </div>
      <a class="btn btn-light" href="contact.html">{T(*btn)}{ARR}</a>
    </div>
  </div>
</section>'''


# =========================================================================
# HOME
# =========================================================================
def home():
    svc_by = {s['slug']: s for s in SERVICES}
    cards = ''.join(f'''
      <a class="card svc reveal" style="--i:{i}" href="services.html#{s['slug']}">
        <span class="idx">{s['idx']}</span>
        <span class="ic">{icon(s['ic'])}</span>
        <h3>{T(*s['short'])}</h3>
        <p>{T(*s['card'])}</p>
        <span class="more">{T('اعرف المزيد', 'Learn more')} <span class="arr" aria-hidden="true">↗</span></span>
      </a>''' for i, s in enumerate(svc_by[k] for k in HOME_ORDER))
    wall = ''.join(
        f'<a class="card partner-tile" href="partners.html">' + (f'<img src="assets/img/partners/{lg}" alt="{n}" loading="lazy">' if lg else f'<span class="partner-name">{n}</span>') + '</a>'
        for n, _, lg in PARTNERS)
    leaf_icon = '<img src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">'
    main = f'''<header class="hero" id="hero">
  <div class="fx" aria-hidden="true">
    <div class="glow g1"></div><div class="glow g2"></div>
    <img class="watermark" src="assets/img/brand/leaf.svg" alt="">
    <div class="particles"></div>
  </div>
  <div class="hero-stage">
    <p class="sr-only">{NAME_AR} — {NAME_EN}</p>
    {LOCKUP.replace('<div class="lockup" id="lockup" title="Replay">', '<div class="lockup" id="lockup" title="Replay" role="img" aria-label="' + NAME_AR + ' — ' + NAME_EN + '">')}
    <div class="rule hero-rule" aria-hidden="true"><span></span><i></i><span></span></div>
    <p class="chip hero-in" style="--at:4.4s">{leaf_icon}<span class="typed-box"><span class="ghost" id="typed-ghost">حيث المريض أولويتنا</span><span class="typed" id="typed" data-ar="حيث المريض أولويتنا" data-en="Where the patient is our priority"></span></span></p>
    <h1 class="hero-title hero-in" style="--at:4.85s">{T('نعمل من أجل وصول الرعاية الصحية إلى كل مريض', 'We work to bring healthcare to every patient')}</h1>
    <p class="hero-sub hero-in" style="--at:5.1s">{T('منظومة دوائية عراقية متكاملة: تسجيل، تخزين مراقب الحرارة، وتوزيع وطني.', 'An integrated Iraqi pharma system: registration, cold-chain storage, and nationwide distribution.')}</p>
    <div class="hero-cta hero-in" style="--at:5.35s">
      <a class="btn btn-primary" href="#services">{T('اكتشف خدماتنا', 'Explore services')}{ARR}</a>
      <a class="btn" href="contact.html">{T('تواصل معنا', 'Contact us')}</a>
    </div>
  </div>
  <a class="scroll-cue hero-in" style="--at:5.8s" href="#story" data-label-ar="انتقل للأسفل" data-label-en="Scroll down" aria-label="انتقل للأسفل"></a>
</header>

<div class="trust">
  <div class="wrap trust-inner">
    <span>{T('معايير عالمية نلتزم بها', 'Global standards we uphold')}</span>
    <div class="badges"><b>GDP</b><b>GSP</b><b>ISO 9001</b><b>{T('وزارة الصحة', 'MINISTRY OF HEALTH')}</b></div>
  </div>
</div>

<section class="block tight blush" id="stats">
  <div class="wrap">
    <h2 class="stats-title reveal">{T('منظومة متكاملة تربط جميع مراحل رحلة المنتج الدوائي', 'An integrated system connecting every stage of the pharmaceutical journey')}</h2>
    <div class="stats">
      <div class="stat reveal" style="--i:0"><strong data-count="30">30<i>+</i></strong><small>{T('عامًا من الخبرة', 'Years of experience')}</small></div>
      <div class="stat reveal" style="--i:1"><strong data-count="18">18</strong><small>{T('محافظة ضمن شبكة التوزيع', 'Governorates in the distribution network')}</small></div>
      <div class="stat reveal" style="--i:2"><strong>24<i>/7</i></strong><small>{T('مراقبة سلسلة التبريد', 'Cold-chain monitoring')}</small></div>
      <div class="stat reveal" style="--i:3"><strong>GDP</strong><small>{T('التزام بمعايير الجودة', 'GDP-standard compliance')}</small></div>
    </div>
  </div>
</section>

<section class="block" id="story">
  <div class="wrap story-grid">
    <figure class="media story-media reveal">
      <img src="assets/img/photos/building-1.jpg" alt="مبنى مكتب بغداد الحياة العلمي في بغداد" data-alt-ar="مبنى مكتب بغداد الحياة العلمي في بغداد" data-alt-en="Baghdad Al Hayat Scientific Office building in Baghdad" loading="lazy">
      <figcaption class="stamp"><strong>1996</strong><span>{T('من بغداد بدأت قصة ثقة تمتد اليوم إلى كل العراق.', 'A story of trust that began in Baghdad and now reaches all of Iraq.')}</span></figcaption>
    </figure>
    <div class="story-copy reveal" style="--i:1">
      <p class="eyebrow">{T('قصة بغداد الحياة', 'Our story')}</p>
      <h2 class="sec-title">{T('خبرة محلية عميقة، <span class="hl">بمعايير عالمية.</span>', 'Deep local expertise, <span class="hl">global standards.</span>')}</h2>
      <p class="lead">{T('منذ عام 1996، يعمل مكتب بغداد الحياة العلمي شريكاً متكاملاً للشركات الدوائية في العراق. نجمع التسجيل والتنظيم والخزن المراقب والتوزيع واليقظة الدوائية ضمن منظومة واحدة يقودها فريق يعرف السوق واحتياجات المريض.', 'Since 1996, Baghdad Al Hayat Scientific Office has served as an integrated partner for pharmaceutical companies in Iraq, combining regulatory affairs, monitored storage, distribution and pharmacovigilance in one locally experienced system.')}</p>
      <div class="kpis">
        <div><strong data-count="525">525<i>+</i></strong><span>{T('متخصصاً ضمن فريق بغداد الحياة', 'Specialists across Baghdad Al Hayat')}</span></div>
        <div><strong data-count="3">3</strong><span>{T('مراكز رئيسية: بغداد وأربيل والبصرة', 'Main hubs: Baghdad, Erbil and Basra')}</span></div>
        <div><strong data-count="13">13</strong><span>{T('قسماً يعمل كنظام موحّد', 'Departments working as one system')}</span></div>
        <div><strong data-count="30">30<i>+</i></strong><span>{T('عاماً من الخبرة في السوق العراقي', 'Years of Iraqi market experience')}</span></div>
      </div>
      <a class="btn btn-primary" href="about.html">{T('اقرأ قصة بغداد الحياة', 'Discover our story')}{ARR}</a>
    </div>
  </div>
</section>

<section class="block tight" id="vision">
  <div class="wrap vision">
    <div class="reveal">
      <h2 class="sec-title">{T('شريك واحد <span class="hl">لكل المراحل.</span>', 'One partner for <span class="hl">every stage.</span>')}</h2>
      <p class="lead">{T('التسجيل والتخزين والتوزيع تحت سقف واحد، مع أنظمة جودة مدققة وشبكة تغطية وطنية — لأن المريض في نهاية كل خطوة.', 'Registration, storage, and distribution under one roof, backed by audited quality systems and nationwide reach — because the patient is at the end of every step.')}</p>
    </div>
    <div class="vision-aside reveal" style="--i:1">
      <div class="big">06</div>
      <p>{T('خدمات متكاملة تعمل كنظام واحد، من الموافقة التنظيمية حتى التسليم الموثّق.', 'Six integrated services working as one system, from regulatory approval to documented handover.')}</p>
    </div>
  </div>
</section>

<section class="block blush" id="services">
  <div class="wrap">
    <div class="sec-head reveal">
      <div>
        <p class="eyebrow">{T('خدماتنا', 'Our services')}</p>
        <h2 class="sec-title">{T('منظومة دوائية <span class="hl">متكاملة.</span>', 'A complete <span class="hl">pharma system.</span>')}</h2>
      </div>
      <p class="lead">{T('خبرة تنظيمية ولوجستية وتجارية تعمل كنظام واحد، لتصل منتجات شركائنا إلى السوق العراقي بثقة.', 'Regulatory, logistics, and commercial expertise working as one system, bringing our partners’ products to the Iraqi market with confidence.')}</p>
    </div>
    <div class="svc-grid">{cards}
    </div>
  </div>
</section>

<section class="block" id="quality-proof">
  <div class="wrap">
    <div class="sec-head reveal">
      <div>
        <p class="eyebrow">{T('الجودة في كل خطوة', 'Quality in every step')}</p>
        <h2 class="sec-title">{T('من المخزن إلى المريض، <span class="hl">كل تفصيل موثّق.</span>', 'From warehouse to patient, <span class="hl">every detail is documented.</span>')}</h2>
      </div>
      <p class="lead">{T('نطبّق إجراءات التشغيل القياسية ومراقبة الحرارة والتتبع المستمر، لتبقى سلامة المنتج جزءاً من العمل اليومي.', 'Standard operating procedures, temperature monitoring and continuous traceability make product integrity a daily practice.')}</p>
    </div>
    <div class="proof-grid">
      <figure class="media reveal"><img src="assets/img/photos/warehouse.jpg" alt="المخزن الدوائي لمكتب بغداد الحياة العلمي" data-alt-ar="المخزن الدوائي لمكتب بغداد الحياة العلمي" data-alt-en="Baghdad Al Hayat pharmaceutical warehouse" loading="lazy"></figure>
      <div class="proof-cards">
        <article class="card proof-card reveal" style="--i:0"><strong data-count="99.7">99.7<i>%</i></strong><div><b>{T('مطابقة حرارية', 'Temperature compliance')}</b><p>{T('مراقبة مستمرة لسلامة المنتجات الحساسة.', 'Continuous oversight for sensitive products.')}</p></div></article>
        <article class="card proof-card reveal" style="--i:1"><strong data-count="46">46<i>+</i></strong><div><b>{T('مركبة مبردة', 'Cold-chain vehicles')}</b><p>{T('تتبع GPS ومسارات توزيع موثقة.', 'GPS tracking and documented routes.')}</p></div></article>
        <article class="card proof-card reveal" style="--i:2"><strong data-count="6">6</strong><div><b>{T('مراكز توزيع', 'Distribution centres')}</b><p>{T('تغطية عملية تربط المناطق الحيوية.', 'Operational coverage across key regions.')}</p></div></article>
        <article class="card proof-card reveal" style="--i:3"><strong data-count="7000">7,000<i>+</i></strong><div><b>{T('نقطة رعاية صحية', 'Healthcare points')}</b><p>{T('صيدليات ومذاخر ومؤسسات صحية ضمن الشبكة.', 'Pharmacies, drugstores and health institutions.')}</p></div></article>
      </div>
    </div>
  </div>
</section>

<section class="block tight blush" id="partners">
  <div class="wrap">
    <div class="sec-head reveal">
      <div>
        <p class="eyebrow">{T('شركاؤنا', 'Our partners')}</p>
        <h2 class="sec-title">{T('علاقات تُبنى على <span class="hl">الثقة والنتائج.</span>', 'Relationships built on <span class="hl">trust and results.</span>')}</h2>
      </div>
      <p class="lead">{T('نعمل مع شركات دوائية عالمية لنحوّل الخبرة الدولية إلى وصول مسؤول وفعّال داخل السوق العراقي.', 'We help global pharmaceutical companies turn international expertise into responsible, effective access across Iraq.')}</p>
    </div>
    <div class="partner-wall reveal">{wall}</div>
  </div>
</section>

<section class="block dark" id="network">
  <img class="leaf-mark" src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">
  <div class="wrap">
    <div class="sec-head reveal">
      <div>
        <p class="eyebrow">{T('شبكة التوزيع', 'Distribution network')}</p>
        <h2 class="sec-title">{T('من بغداد إلى <span class="hl">محافظات العراق.</span>', 'From Baghdad across <span class="hl">Iraq’s provinces.</span>')}</h2>
      </div>
      <p class="lead">{T('منظومة تتعقب كل خطوة وتحمي كل درجة حرارة وتوثق كل تسليم.', 'A network that tracks every step, safeguards every temperature, and documents every handover.')}</p>
    </div>
    <div class="net-grid">
      <figure class="media net-media reveal">
        <img src="assets/img/photos/warehouse-racks.jpg" alt="" loading="lazy">
        <figcaption class="cap"><b>BAGHDAD AL HAYAT · NETWORK</b><span>{T('تغطية وطنية موثّقة', 'Documented nationwide reach')}</span></figcaption>
      </figure>
      <div class="steps">
        <div class="step active reveal" style="--i:0" tabindex="0"><div class="n">01</div><div><h3>{T('الانطلاق الموثّق', 'Documented dispatch')}</h3><p>{T('تحقق من المنتج والدفعة ودرجة الحرارة قبل المغادرة.', 'Product, batch and temperature verified before departure.')}</p></div></div>
        <div class="step reveal" style="--i:1" tabindex="0"><div class="n">02</div><div><h3>{T('التتبع الحراري', 'Temperature tracking')}</h3><p>{T('مراقبة مستمرة أثناء النقل ضمن المسارات اليومية.', 'Continuous monitoring in transit along daily routes.')}</p></div></div>
        <div class="step reveal" style="--i:2" tabindex="0"><div class="n">03</div><div><h3>{T('التغطية الوطنية', 'Nationwide reach')}</h3><p>{T('وصول إلى المستشفيات والصيدليات في أنحاء العراق.', 'Reaching hospitals and pharmacies throughout Iraq.')}</p></div></div>
        <div class="step reveal" style="--i:3" tabindex="0"><div class="n">04</div><div><h3>{T('التسليم الموثّق', 'Documented handover')}</h3><p>{T('توثيق واضح في كل مرحلة من سلسلة العهدة.', 'Clear documentation at every stage of the chain of custody.')}</p></div></div>
      </div>
    </div>
  </div>
</section>

{cta_band(('تواصل معنا', 'Contact us'), ('أرسل رسالتك إلى مكتب بغداد الحياة العلمي', 'Send a message to Baghdad Al Hayat Scientific Office'), ('أرسِل رسالتك', 'Send message'))}'''
    return page('index.html', '', '', (
        'مكتب بغداد الحياة العلمي — منظومة متكاملة لتسجيل وتخزين وتوزيع الدواء في العراق منذ 1996.',
        'Baghdad Al Hayat Scientific Office — an integrated system for pharmaceutical registration, storage and distribution in Iraq since 1996.'),
        'home', main, '\n<script src="assets/js/home.js"></script>')


# =========================================================================
# ABOUT
# =========================================================================
DEPARTMENTS = [
    ('مبيعات القطاع الخاص', 'Private sales'), ('المناقصات', 'Tender management'),
    ('ضمان الجودة والسلامة', 'Quality assurance & safety'), ('الموارد البشرية', 'Human resources'),
    ('اللوجستيات وسلسلة الإمداد', 'Logistics & supply chain'), ('تقنية المعلومات وحماية البيانات', 'IT & data protection'),
    ('المحاسبة والمالية', 'Accounting & finance'), ('التدقيق', 'Audit'),
    ('النقل', 'Transportation'), ('إدارة المخازن', 'Warehouse management'),
    ('الشؤون التنظيمية', 'Regulatory affairs'), ('التسويق', 'Marketing'),
    ('اليقظة الدوائية', 'Pharmacovigilance'),
]
TIMELINE = [
    ('1996', ('التأسيس', 'Foundation'), ('انطلاق العمل بالتركيز على مناقصات وزارة الصحة.', 'Established with a focus on Ministry of Health tenders.')),
    ('2007', ('التوسع في القطاع الخاص', 'Private-sector expansion'), ('تنويع استراتيجي وبناء شبكة صيدليات التجزئة.', 'Strategic diversification into private healthcare and retail pharmacy networks.')),
    ('2010', ('عمليات كردستان', 'Kurdistan operations'), ('تأسيس أومنيا كردستان وحضور إقليمي مرخص.', 'Omnia Kurdistan established with a direct retail licence and regional presence.')),
    ('2012', ('التوسع جنوباً', 'Southern expansion'), ('إطلاق مركز البصرة واستكمال مثلث التوزيع الاستراتيجي.', 'Basra hub launched, completing the strategic triangle of distribution centres.')),
    ('2026', ('تغطية وطنية أوسع', 'Nationwide coverage'), ('توسع مخطط إلى الموصل وكركوك وكربلاء للوصول الوطني المتكامل.', 'Planned expansion to Mosul, Kirkuk and Karbala for complete national reach.')),
]


def about():
    deps = ''.join(f'<article class="card reveal" style="--i:{i % 4}"><span>{i + 1:02d}</span><p>{T(a, e)}</p></article>' for i, (a, e) in enumerate(DEPARTMENTS))
    tl = ''.join(f'<article class="tl reveal" style="--i:{i}"><div class="dot">{y}</div><h3>{T(*t)}</h3><p>{T(*x)}</p></article>' for i, (y, t, x) in enumerate(TIMELINE))
    main = f'''{page_hero(('قصتنا', 'Our story'), ('من مكتب علمي محلي إلى شريك وطني متكامل', 'From scientific office to integrated national partner'), ('منذ 1996 نطوّر القدرات التنظيمية والتشغيلية والتجارية التي تربط الشركات الدوائية العالمية بملايين المرضى في العراق.', 'Since 1996, we have built the regulatory, operational and commercial capabilities that connect global pharmaceutical companies with millions of patients across Iraq.'))}

<section class="block" id="who">
  <div class="wrap split">
    <div class="prose reveal">
      <p class="eyebrow">{T('من نحن', 'Who we are')}</p>
      <h2 class="sec-title">{T('خبرة محلية عميقة <span class="hl">وطموح يتجاوز التوزيع</span>', 'Deep local expertise with an <span class="hl">ambition beyond distribution</span>')}</h2>
      <p class="lead">{T('مكتب بغداد الحياة العلمي إحدى أكثر الوكالات الدوائية خبرة وثقة في العراق. نمثل شركات مصنعة رائدة ونوفر وصولاً مستمراً إلى المنتجات عالية الجودة عبر فهم دقيق للأنظمة المحلية وشبكات توزيع ولوجستيات متقدمة.', 'Baghdad Al Hayat Scientific Office is one of Iraq’s most experienced and trusted pharmaceutical agencies. We represent leading manufacturers and ensure consistent access to high-quality products through deep regulatory understanding and advanced distribution logistics.')}</p>
      <p>{T('يعمل أكثر من 525 متخصصاً من بغداد وأربيل والبصرة، بعد أن سجل الملف التعريفي السابق أكثر من 300 متخصص، لخدمة الطلب المتنامي على الرعاية الصحية.', 'More than 525 professionals work from Baghdad, Erbil and Basra, growing from the 300+ professionals recorded in the earlier profile.')}</p>
      <div class="mv">
        <div class="card"><span class="ar">المهمة</span><span class="en">Mission</span><strong>{T('تحسين جودة الحياة', 'Improve quality of life')}</strong><p>{T('منتجات دوائية عالية الجودة وسياسات صحية محدثة ومعايير دولية.', 'High-quality medicines, evolved health policy and international standards.')}</p></div>
        <div class="card"><span class="ar">الرؤية</span><span class="en">Vision</span><strong>{T('تطوير قطاع الصحة', 'Develop healthcare')}</strong><p>{T('تعليم وتحول رقمي وحلول مبتكرة تتمحور حول المريض.', 'Education, digital transformation and patient-centred innovation.')}</p></div>
      </div>
    </div>
    <figure class="media reveal" style="--i:1"><img src="assets/img/photos/building-2.jpg" alt="مبنى مكتب بغداد الحياة العلمي" data-alt-ar="مبنى مكتب بغداد الحياة العلمي" data-alt-en="Baghdad Al Hayat Scientific Office building" loading="lazy"></figure>
  </div>
</section>

<section class="block tight dark" id="scale">
  <img class="leaf-mark" src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">
  <div class="wrap metrics">
    <div class="intro reveal"><p class="eyebrow">{T('قدراتنا اليوم', 'Our scale today')}</p><b>{T('بنية وطنية تُدار بمعايير عالمية', 'National infrastructure managed to global standards')}</b></div>
    <div class="metric reveal" style="--i:1"><strong data-count="10000">10,000<i>+</i></strong><span>{T('طلب شهرياً', 'Monthly orders')}</span></div>
    <div class="metric reveal" style="--i:2"><strong data-count="5835">5,835<i>+</i></strong><span>{T('عميلاً', 'Clients')}</span></div>
    <div class="metric reveal" style="--i:3"><strong data-count="525">525<i>+</i></strong><span>{T('موظفاً متخصصاً', 'Dedicated employees')}</span></div>
    <div class="metric reveal" style="--i:4"><strong data-count="148">148<i>+</i></strong><span>{T('منتجاً مسجلاً', 'Registered products')}</span></div>
  </div>
</section>

<section class="block" id="organisation">
  <div class="wrap">
    <div class="sec-head center reveal">
      <p class="eyebrow">{T('الهيكل التنظيمي', 'Organisation structure')}</p>
      <h2 class="sec-title">{T('13 إدارة متكاملة <span class="hl">تحت قيادة المدير العام</span>', 'Thirteen integrated functions <span class="hl">reporting to the General Manager</span>')}</h2>
    </div>
    <div class="num-grid">{deps}</div>
  </div>
</section>

<section class="block blush" id="leadership">
  <div class="wrap">
    <div class="sec-head center reveal">
      <p class="eyebrow">{T('القيادة', 'Leadership')}</p>
      <h2 class="sec-title">{T('رؤية تقود <span class="hl">ثلاثة عقود من الثقة</span>', 'Leadership behind <span class="hl">three decades of trust</span>')}</h2>
    </div>
    <div class="leaders">
      <figure class="media leader-photo reveal"><img src="assets/img/photos/ceo.png" alt="Ali Mosawi — Founder &amp; CEO" loading="lazy"></figure>
      <blockquote class="card quote reveal" style="--i:1">
        <p class="eyebrow">{T('رسالة المؤسس والرئيس التنفيذي', 'A message from our founder & CEO')}</p>
        <span class="qmark" aria-hidden="true">“</span>
        <p>{T('مهمتنا هي تسهيل الصلة بين قطاع الرعاية الصحية العراقي وأحدث ما توصل إليه الطب العالمي. يمنحنا الأثر الحقيقي لهذه الابتكارات دافعاً يومياً لإيصال العلاجات التي تجدد القوة والأمل.', 'Our mission is to facilitate the connection between Iraqi healthcare and the latest advancements in global medicine. The tangible impact of these innovations is the daily motivation behind our work.')}</p>
        <footer><strong>Ali Mosawi</strong><span>{T('المؤسس والرئيس التنفيذي', 'Founder & CEO')}</span></footer>
      </blockquote>
      <blockquote class="card quote reveal" style="--i:2">
        <p class="eyebrow">{T('رسالة المدير العام', 'A message from our GM')}</p>
        <span class="qmark" aria-hidden="true">“</span>
        <p>{T('نجمع الخبرة الموثوقة مع التزام عميق بصحة المريض؛ من الامتثال التنظيمي إلى التوزيع الوطني نحرص على وصول العلاجات المنقذة للحياة بفاعلية ونزاهة.', 'We combine trusted expertise with a deep commitment to patient wellbeing, ensuring life-saving treatments are accessible, effective and delivered with integrity.')}</p>
        <footer><strong>Dr. Zaid Haitham</strong><span>{T('المدير العام', 'General Manager')}</span></footer>
      </blockquote>
    </div>
  </div>
</section>

<section class="block" id="why">
  <div class="wrap split rev">
    <div class="prose reveal">
      <p class="eyebrow">{T('لماذا بغداد الحياة؟', 'Why Baghdad Al Hayat?')}</p>
      <h2 class="sec-title">{T('استقرار وشفافية <span class="hl">والتزام طويل الأمد</span>', 'Stability, transparency and <span class="hl">long-term commitment</span>')}</h2>
      <p class="lead">{T('خبرة تتجاوز 30 عاماً وعمليات مستدامة وفريق ماهر وثقافة عمل عائلية، مع استمرار الاستثمار في القدرات حتى خلال فترات الأزمات.', 'More than 30 years of experience, sustainable operations, a skilled team and a family-oriented culture, with continued investment even through periods of crisis.')}</p>
      <div class="checks">
        <div>{T('استقرار مالي وشفافية مع تدقيق داخلي وخارجي', 'Financial stability and transparency with internal and external audit')}</div>
        <div>{T('امتثال لمتطلبات البنك المركزي العراقي', 'Compliance with Central Bank of Iraq requirements')}</div>
        <div>{T('أنظمة متقدمة للتدفق النقدي والكفاءة التشغيلية', 'Advanced cash-flow and operating-efficiency systems')}</div>
      </div>
    </div>
    <figure class="media reveal" style="--i:1"><img src="assets/img/photos/warehouse.jpg" alt="" loading="lazy"></figure>
  </div>
</section>

<section class="block blush" id="milestones">
  <div class="wrap">
    <div class="sec-head center reveal">
      <p class="eyebrow">{T('محطاتنا', 'Our milestones')}</p>
      <h2 class="sec-title">{T('أسس نمو بُنيت <span class="hl">على مدى ثلاثة عقود</span>', 'Foundations built <span class="hl">across three decades</span>')}</h2>
    </div>
    <div class="timeline">{tl}</div>
  </div>
</section>

<section class="block" id="team">
  <div class="wrap split">
    <figure class="media reveal"><video src="assets/video/products.mp4" poster="assets/img/photos/products-poster.jpg" autoplay muted loop playsinline preload="metadata" aria-label="Baghdad Al Hayat"></video></figure>
    <div class="prose reveal" style="--i:1">
      <p class="eyebrow">{T('أبطال بغداد الحياة', 'The champs of Baghdad Al Hayat')}</p>
      <h2 class="sec-title">{T('قوة ميدانية <span class="hl">مدعومة بالتقنية</span>', 'A field force <span class="hl">powered by technology</span>')}</h2>
      <p class="lead">{T('90 ممثلاً ميدانياً و10 مشرفين يقودون التنفيذ التجاري مدعومين بالتكامل بين أنظمة CRM وERP والرؤية القائمة على البيانات.', 'Ninety field representatives and ten supervisors drive market execution, supported by integrated CRM and ERP systems and data-driven insight.')}</p>
      <div class="checks">
        <div>{T('إدارة المخزون والطلبات والمرتجعات والتحصيل إلكترونياً', 'Electronic stock, order, returns and collection management')}</div>
        <div>{T('زيارات ميدانية ومتابعة مستمرة', 'Field engagement and continuous customer follow-up')}</div>
        <div>{T('معلومات سوق وقرارات مدعومة بالبيانات', 'Market intelligence and data-supported decisions')}</div>
      </div>
    </div>
  </div>
</section>

{cta_band(('ابدأ شراكة', 'Start a partnership'), ('لنبنِ معاً حضوراً مستداماً في السوق العراقي', 'Let’s build a sustainable presence in Iraq together'), ('تواصل معنا', 'Contact us'))}'''
    return page('about.html', 'من نحن', 'About us', (
        'تعرّف على مكتب بغداد الحياة العلمي: قصتنا منذ 1996، القيادة، الهيكل التنظيمي ومحطات النمو.',
        'About Baghdad Al Hayat Scientific Office: our story since 1996, leadership, organisation and milestones.'),
        'about', main)


# =========================================================================
# SERVICES
# =========================================================================
def services():
    tabs = ''.join(f'<a href="#{s["slug"]}">{T(*s["short"])}</a>' for s in SERVICES)
    secs = ''
    for i, s in enumerate(SERVICES):
        rev = ' rev' if i % 2 else ''
        SM = ' class="sm"'
        facts = ''.join(f'<div><strong{SM if len(v) > 8 else NOCLS}>{v}</strong><span>{T(a, e)}</span></div>' for v, a, e in s['facts'])
        m = media_html(s['media'], s['t'][1], 'media reveal')
        secs += f'''
<section class="block svc-sec svc-detail" id="{s['slug']}">
  <div class="wrap split{rev}">
    {m}<span class="badge-idx">{s['idx']}</span></figure>
    <div class="prose reveal" style="--i:1">
      <p class="eyebrow">{T('خدمة متكاملة', 'Integrated service')}</p>
      <h2 class="sec-title">{T(*s['t'])}</h2>
      <p class="svc-lead">{T(*s['summary'])}</p>
      <p>{T(*s['detail'])}</p>
      <div class="kpis">{facts}</div>
    </div>
  </div>
</section>'''
    hero = page_hero(('خدماتنا', 'Our services'), ('منظومة دوائية متكاملة تحت سقف واحد', 'A complete pharmaceutical system under one roof'), ('من التسجيل والتخزين المراقب إلى التوزيع الوطني واليقظة الدوائية — خبرة تنظيمية ولوجستية وتجارية تعمل كنظام واحد.', 'From registration and monitored storage to nationwide distribution and pharmacovigilance — regulatory, logistics and commercial expertise working as one system.'))
    hero = hero.replace('  </div>\n</header>', f'    <nav class="svc-tabs" aria-label="Services">{tabs}</nav>\n  </div>\n</header>')
    main = hero + '\n<div class="svc-list">' + secs + '\n</div>\n\n' + cta_band(('لديك استفسار؟', 'Have a question?'), ('لديك استفسار أو طلب؟ فريق مكتب بغداد الحياة جاهز للإجابة', 'Have a question or request? The Baghdad Al Hayat team is ready to help'), ('أرسل رسالة', 'Send a message'))
    return page('services.html', 'خدماتنا', 'Our services', (
        'خدمات مكتب بغداد الحياة العلمي: التسجيل الدوائي، المخازن وسلسلة التبريد، التوزيع الوطني، علاقات العملاء، اليقظة الدوائية، والامتثال.',
        'Baghdad Al Hayat services: regulatory registration, warehousing & cold chain, nationwide distribution, customer relations, pharmacovigilance and compliance.'),
        'services', main)


# =========================================================================
# QUALITY
# =========================================================================
ISO = [
    ('ISO 9001:2015', ('إدارة الجودة', 'Quality management')), ('ISO 22301', ('استمرارية الأعمال', 'Business continuity')),
    ('ISO 27001', ('أمن المعلومات', 'Information security')), ('ISO 45001', ('الصحة والسلامة المهنية', 'Occupational health & safety')),
    ('ISO 14001', ('الإدارة البيئية', 'Environmental management')), ('GSDP', ('ممارسات التخزين والتوزيع الجيد', 'Good storage & distribution practices')),
]
FACILITIES = [
    (('بغداد', 'Baghdad'), ('مخزن 3,200م² بغرفتي تبريد، مبنى مكاتب من 4 طوابق بمساحة 1,100م² لكل طابق، ومذخر 700م².', '3,200 m² warehouse with two cold rooms, four office floors of 1,100 m² each, and a 700 m² drugstore.')),
    (('أربيل', 'Erbil'), ('مخزن 1,300م² بغرفة تبريد، مكاتب 1,700م²، ومذخر أومنيا كردستان للمبيعات المباشرة للصيدليات.', '1,300 m² warehouse with one cold room, 1,700 m² offices, and Omnia Kurdistan drugstore for direct pharmacy sales.')),
    (('البصرة', 'Basra'), ('موقع بمساحة 900م² مع غرفة تبريد.', '900 m² site with one cold room.')),
    (('الموصل — توسع 2026', 'Mosul — 2026 expansion'), ('مذخر دوائي مخطط بمساحة 700م².', 'Planned 700 m² drugstore.')),
    (('كركوك — توسع 2026', 'Kirkuk — 2026 expansion'), ('مذخر دوائي مخطط بمساحة 300م².', 'Planned 300 m² drugstore.')),
    (('كربلاء — توسع 2026', 'Karbala — 2026 expansion'), ('مذخر مخطط بمساحة 300م² مع غرفة تبريد.', 'Planned 300 m² drugstore with a cold room.')),
    (('السليمانية', 'Sulaymaniyah'), ('مكاتب بغداد الحياة بمساحة 100م².', 'Baghdad Al Hayat offices covering 100 m².')),
    (('الناصرية', 'Nasiriyah'), ('مكتب بمساحة 200م².', '200 m² office.')),
]


def quality():
    iso = ''.join(f'<div><strong>{c}</strong><span>{T(*t)}</span></div>' for c, t in ISO)
    fac = ''.join(f'<article class="card reveal" style="--i:{i % 4}"><span>{i + 1:02d}</span><p><strong>{T(*c)}</strong>{T(*d)}</p></article>' for i, (c, d) in enumerate(FACILITIES))
    main = f'''{page_hero(('الجودة والامتثال', 'Quality & compliance'), ('سلامة الدواء تبدأ قبل وصوله إلى المريض بوقت طويل', 'Medicine safety starts long before it reaches the patient'), ('أنظمة جودة وحوكمة وسلسلة تبريد ويقظة دوائية تحمي سلامة المنتج وبيانات الشركاء واستمرارية الأعمال.', 'Quality systems, governance, cold-chain control and pharmacovigilance protect product integrity, partner data and business continuity.'))}

<section class="block" id="assurance">
  <div class="wrap split">
    <div class="prose reveal">
      <p class="eyebrow">{T('ضمان الجودة', 'Quality assurance')}</p>
      <h2 class="sec-title">{T('نظام متكامل، <span class="hl">لا قائمة فحص</span>', 'An integrated system, <span class="hl">not a checklist</span>')}</h2>
      <p class="lead">{T('نتبع ممارسات التخزين والتوزيع الجيد GSDP ونطبق رقابة صارمة مع التدقيق الداخلي والخارجي والتدريب المستمر وإدارة المخاطر.', 'We follow GSDP-compliant practices and rigorous controls, including internal and external audits, continuous training and risk management.')}</p>
      <div class="iso-grid">{iso}</div>
    </div>
    <div class="card cert-card reveal" style="--i:1">
      <div class="seal">GSDP</div>
      <h3>{T('شهادة ممارسات التخزين والتوزيع الجيد', 'Good Storage & Distribution Practice certificate')}</h3>
      <div class="cert-rows">
        <div><span>{T('مرجع الشهادة', 'Certificate reference')}</span><b>103/25</b></div>
        <div><span>{T('تاريخ الإصدار', 'Issue date')}</span><b>25/06/2025</b></div>
        <div><span>{T('الصلاحية', 'Valid until')}</span><b>{T('سبتمبر 2026', 'September 2026')}</b></div>
      </div>
    </div>
  </div>
</section>

<section class="block tight dark" id="cold-chain">
  <img class="leaf-mark" src="assets/img/brand/leaf.svg" alt="" aria-hidden="true">
  <div class="wrap">
    <div class="highlights">
      <div class="reveal" style="--i:0"><strong data-count="99.7">99.7<i>%</i></strong><span>{T('التزام بمتطلبات درجات الحرارة', 'temperature compliance')}</span></div>
      <div class="reveal" style="--i:1"><strong data-count="46">46<i>+</i></strong><span>{T('ناقلاً مبرداً مزوداً بنظام GPS', 'GPS-enabled cold-chain couriers')}</span></div>
      <div class="reveal" style="--i:2"><strong data-count="6">6</strong><span>{T('مراكز توزيع تغطي العراق', 'distribution centres across Iraq')}</span></div>
    </div>
  </div>
</section>

<section class="block" id="facilities">
  <div class="wrap">
    <div class="sec-head reveal">
      <div>
        <p class="eyebrow">{T('المرافق والتوسع', 'Facilities & expansion')}</p>
        <h2 class="sec-title">{T('تفاصيل البنية التشغيلية <span class="hl">في جميع المواقع</span>', 'The complete operating footprint <span class="hl">across Iraq</span>')}</h2>
      </div>
      <figure class="media reveal" style="aspect-ratio:16/8"><img src="assets/img/photos/warehouse-boxes.jpg" alt="" loading="lazy"></figure>
    </div>
    <div class="num-grid">{fac}</div>
  </div>
</section>

<section class="block blush" id="pharmacovigilance">
  <div class="wrap">
    <div class="sec-head center reveal">
      <p class="eyebrow">{T('نظام اليقظة الدوائية', 'Pharmacovigilance system')}</p>
      <h2 class="sec-title">{T('سلامة مستمرة <span class="hl">طوال دورة حياة المنتج</span>', 'Continuous safety <span class="hl">across the product lifecycle</span>')}</h2>
    </div>
    <div class="num-grid">
      <article class="card reveal" style="--i:0"><span>01</span><p>{T('سلامة المريض: اكتشاف وتقييم ومنع الآثار العكسية', 'Patient safety: detect, assess and prevent adverse effects')}</p></article>
      <article class="card reveal" style="--i:1"><span>02</span><p>{T('الامتثال لمعايير وزارة الصحة وFDA وEMA', 'Compliance with MOH, FDA and EMA standards')}</p></article>
      <article class="card reveal" style="--i:2"><span>03</span><p>{T('تكامل ضمان الجودة والتوثيق والإجراءات', 'QA integration, documentation and SOP adherence')}</p></article>
      <article class="card reveal" style="--i:3"><span>04</span><p>{T('المراقبة المستمرة واكتشاف الإشارات', 'Continuous monitoring and signal detection')}</p></article>
    </div>
    <div class="sop">
      <div class="reveal" style="--i:0"><strong>{T('الإبلاغ عن الأحداث العكسية', 'Adverse event reporting')}</strong><span>{T('جمع معلومات السلامة بدقة وفي الوقت المناسب', 'Timely and accurate safety reporting')}</span></div>
      <div class="reveal" style="--i:1"><strong>{T('اكتشاف الإشارات', 'Signal detection')}</strong><span>{T('تحديد المخاوف الجديدة استباقياً', 'Proactive identification of new concerns')}</span></div>
      <div class="reveal" style="--i:2"><strong>PSURs</strong><span>{T('مراجعات دورية تُرفع للجهات المختصة', 'Periodic reviews submitted to authorities')}</span></div>
    </div>
  </div>
</section>

{cta_band(('الجودة والامتثال', 'Quality & compliance'), ('هل تحتاج تفاصيل إضافية عن أنظمة الجودة لدينا؟', 'Need more detail on our quality systems?'), ('تواصل معنا', 'Contact us'))}'''
    return page('quality.html', 'الجودة والامتثال', 'Quality & compliance', (
        'الجودة والامتثال في مكتب بغداد الحياة العلمي: GSDP وأنظمة ISO وسلسلة التبريد واليقظة الدوائية.',
        'Quality & compliance at Baghdad Al Hayat: GSDP, ISO systems, cold chain and pharmacovigilance.'),
        'quality', main)


# =========================================================================
# PARTNERS
# =========================================================================
COMMITMENTS = [
    ('01', ('شراكة استراتيجية', 'Strategic partnership'), ('نبني علاقات طويلة الأمد ونستثمر في الكفاءات والبنية التحتية والقدرات لتحقيق نمو مستدام.', 'Long-term partnerships backed by investment in people, infrastructure and capabilities for sustainable growth.')),
    ('02', ('الامتثال والحوكمة', 'Compliance & governance'), ('نعمل وفق الأنظمة العراقية وبما يتوافق مع المعايير الدولية للشفافية والنزاهة واستمرارية الأعمال.', 'Iraqi regulatory expertise aligned with international standards for transparency, integrity and continuity.')),
    ('03', ('خبرة علاجية', 'Therapeutic expertise'), ('خبرة في الأورام والجهاز التنفسي والقلب والأوعية والغدد واللقاحات والرعاية التخصصية.', 'Experience across oncology, respiratory, cardiovascular, endocrinology, vaccines and specialty care.')),
    ('04', ('ريادة السوق', 'Market leadership'), ('ثلاثة عقود من فهم المناقصات العامة والمسارات التنظيمية وديناميكيات السوق الخاص في العراق.', 'Three decades navigating public tenders, regulatory pathways and private-market dynamics in Iraq.')),
    ('05', ('استثمار مخصص', 'Dedicated investment'), ('فرق متخصصة ومبادرات نفاذ للسوق وأنظمة امتثال وبنية تجارية مكرسة للشركات التي نمثلها.', 'Dedicated teams, market-access initiatives, compliance systems and commercial infrastructure.')),
]


def partners():
    cards = ''.join(f'''<article class="card p-card reveal" style="--i:{i % 3}">
        <div class="p-top"><span>{i + 1:02d}</span><span>{since}</span></div>
        <div class="p-logo">{f'<img src="assets/img/partners/{lg}" alt="{n}" loading="lazy">' if lg else f'<strong>{n}</strong>'}</div>
        <div class="p-meta"><strong>{n}</strong><small>{T('بداية الشراكة', 'Partnership established')} <b>{since}</b></small></div>
      </article>''' for i, (n, since, lg) in enumerate(PARTNERS))
    com = ''.join(f'<article class="card reveal" style="--i:{i % 3}"><span>{n}</span><h3>{T(*t)}</h3><p>{T(*b)}</p></article>' for i, (n, t, b) in enumerate(COMMITMENTS))
    main = f'''{page_hero(('شراكات عالمية', 'Global partnerships'), ('نمثل الابتكار العالمي باستثمار محلي مخصص', 'Global innovation, backed by dedicated local investment'), ('نبني لكل شريك بنية تنظيمية وتجارية وتشغيلية طويلة الأمد مصممة خصيصاً لديناميكيات السوق العراقي.', 'For every partner, we build a long-term regulatory, commercial and operational platform designed specifically for Iraq’s market dynamics.'))}

<section class="block" id="list">
  <div class="wrap">
    <div class="partner-list">
      {cards}
    </div>
  </div>
</section>

<section class="block blush" id="promise">
  <div class="wrap">
    <div class="sec-head center reveal">
      <p class="eyebrow">{T('التزامنا', 'Our promise')}</p>
      <h2 class="sec-title">{T('ما الذي يحصل عليه <span class="hl">شريك بغداد الحياة؟</span>', 'What a Baghdad Al Hayat <span class="hl">partner can expect</span>')}</h2>
    </div>
    <div class="num-grid c3">{com}</div>
  </div>
</section>

<section class="block" id="market-entry">
  <div class="wrap split">
    <figure class="media reveal"><img src="assets/img/photos/building-1.jpg" alt="" loading="lazy"></figure>
    <div class="prose reveal" style="--i:1">
      <p class="eyebrow">{T('دخول السوق', 'Market entry')}</p>
      <h2 class="sec-title">{T('مسار واحد <span class="hl">من الاستراتيجية إلى التنفيذ</span>', 'One path <span class="hl">from strategy to execution</span>')}</h2>
      <p class="lead">{T('يعمل فريق الشراكات مع التسجيل والنفاذ للسوق والمبيعات وسلسلة الإمداد والجودة واليقظة الدوائية كوحدة واحدة لتقليل التعقيد وتحويل الخطة إلى حضور مستدام.', 'Our partnerships team works as one unit with regulatory, market access, sales, supply chain, quality and pharmacovigilance functions to turn strategy into sustainable presence.')}</p>
      <div class="checks">
        <div>{T('تحليل السوق والمسار التنظيمي', 'Market and regulatory pathway assessment')}</div>
        <div>{T('فريق ومنصة تجارية مخصصة', 'Dedicated team and commercial platform')}</div>
        <div>{T('تقارير أداء وحوكمة وامتثال مستمرة', 'Ongoing performance, governance and compliance reporting')}</div>
      </div>
    </div>
  </div>
</section>

{cta_band(('ابدأ شراكة', 'Start a partnership'), ('هل تبحث عن شريك موثوق في السوق العراقي؟', 'Looking for a trusted partner in Iraq?'), ('تحدّث مع فريقنا', 'Talk to our team'))}'''
    return page('partners.html', 'الشركاء', 'Global partners', (
        'شركاء مكتب بغداد الحياة العلمي العالميون: AstraZeneca وServier وMSD وPfizer وغيرها.',
        'Baghdad Al Hayat global partners: AstraZeneca, Servier, MSD, Pfizer and more.'),
        'partners', main)


# =========================================================================
# CONTACT
# =========================================================================
def contact():
    locs = ''.join(f'<article class="card loc reveal" style="--i:{i}"><h3>{T(*c)}</h3><p>{T(*d)}</p><a href="{tel(p)}">{p}</a></article>' for i, (c, d, p) in enumerate(LOCATIONS))
    main = f'''{page_hero(('تواصل معنا', 'Contact us'), ('أرسل رسالتك إلى مكتب بغداد الحياة العلمي', 'Send a message to Baghdad Al Hayat Scientific Office'), ('اكتب رسالتك وستصل مباشرة إلى فريق مكتب بغداد الحياة. البريد الإلكتروني اختياري.', 'Write your message and it goes straight to the Baghdad Al Hayat team. Email is optional.'))}

<section class="block">
  <div class="wrap contact-grid">
    <div class="card form-card reveal">
      <p class="eyebrow">{T('أسمِعنا صوتك', 'Speak up')}</p>
      <h2 class="sec-title" style="font-size:clamp(24px,2.4vw,32px);margin-bottom:22px">{T('نحن هنا <span class="hl">لنسمعك</span>', 'We are here <span class="hl">to listen</span>')}</h2>
      <form id="contact-form" action="contact.php" method="post" data-mail="{EMAIL}" novalidate>
        <div class="field"><label for="f-name">{T('الاسم', 'Name')}</label><input id="f-name" name="name" type="text" autocomplete="name" required maxlength="120" placeholder="اسمك الكامل" data-ph-ar="اسمك الكامل" data-ph-en="Your full name"></div>
        <div class="field-row">
          <div class="field"><label for="f-email">{T('البريد الإلكتروني', 'Email')} <small>{T('(اختياري)', '(optional)')}</small></label><input id="f-email" name="email" type="email" autocomplete="email" maxlength="160" dir="ltr" placeholder="name@example.com" data-ph-ar="name@example.com" data-ph-en="name@example.com"></div>
          <div class="field"><label for="f-phone">{T('الهاتف', 'Phone')} <small>{T('(اختياري)', '(optional)')}</small></label><input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" dir="ltr" placeholder="+964 …" data-ph-ar="+964 …" data-ph-en="+964 …"></div>
        </div>
        <div class="field"><label for="f-subject">{T('الموضوع', 'Subject')} <small>{T('(اختياري)', '(optional)')}</small></label><input id="f-subject" name="subject" type="text" maxlength="160" placeholder="موضوع الرسالة" data-ph-ar="موضوع الرسالة" data-ph-en="Message subject"></div>
        <div class="field"><label for="f-message">{T('رسالتك', 'Your message')}</label><textarea id="f-message" name="message" required maxlength="5000" placeholder="اكتب رسالتك هنا…" data-ph-ar="اكتب رسالتك هنا…" data-ph-en="Write your message here…"></textarea></div>
        <div class="hp" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <button class="btn btn-primary" type="submit">{T('أرسِل رسالتك', 'Send message')}{ARR}</button>
        <p class="form-note">{T('نحترم خصوصيتك — تُستخدم بياناتك للرد على رسالتك فقط.', 'We respect your privacy — your details are used only to reply to your message.')}</p>
        <div class="form-status" id="form-status" role="status" aria-live="polite"></div>
      </form>
    </div>
    <div class="locs">
      <div class="card mail-card reveal"><span>{T('البريد الإلكتروني', 'Email')}</span><a href="mailto:{EMAIL}">{EMAIL}</a></div>
      {locs}
    </div>
  </div>
</section>'''
    return page('contact.html', 'تواصل معنا', 'Contact us', (
        'تواصل مع مكتب بغداد الحياة العلمي — بغداد وأربيل والبصرة.',
        'Contact Baghdad Al Hayat Scientific Office — Baghdad, Erbil and Basra.'),
        'contact', main)


for name, fn in [('index.html', home), ('about.html', about), ('services.html', services), ('quality.html', quality), ('partners.html', partners), ('contact.html', contact)]:
    with open(os.path.join(OUT, name), 'w', encoding='utf8') as f:
        f.write(fn())
    print('wrote', name)
