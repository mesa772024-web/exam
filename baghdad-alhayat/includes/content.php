<?php
declare(strict_types=1);

function localized(array $value, string $locale): string
{
    return (string) ($value[$locale] ?? $value['ar'] ?? '');
}

$SITE_CONTENT = [
    'metrics' => [
        ['value' => '10,000+', 'label' => ['ar' => 'طلب شهرياً', 'en' => 'Monthly orders']],
        ['value' => '5,835+', 'label' => ['ar' => 'عميلاً', 'en' => 'Clients']],
        ['value' => '525+', 'label' => ['ar' => 'موظفاً متخصصاً', 'en' => 'Dedicated employees']],
        ['value' => '148+', 'label' => ['ar' => 'منتجاً مسجلاً', 'en' => 'Registered products']],
    ],
    'commitments' => [
        ['number' => '01', 'title' => ['ar' => 'شراكة استراتيجية', 'en' => 'Strategic partnership'], 'body' => ['ar' => 'نبني علاقات طويلة الأمد ونستثمر في الكفاءات والبنية التحتية والقدرات لتحقيق نمو مستدام.', 'en' => 'Long-term partnerships backed by investment in people, infrastructure and capabilities for sustainable growth.']],
        ['number' => '02', 'title' => ['ar' => 'الامتثال والحوكمة', 'en' => 'Compliance & governance'], 'body' => ['ar' => 'نعمل وفق الأنظمة العراقية وبما يتوافق مع المعايير الدولية للشفافية والنزاهة واستمرارية الأعمال.', 'en' => 'Iraqi regulatory expertise aligned with international standards for transparency, integrity and continuity.']],
        ['number' => '03', 'title' => ['ar' => 'خبرة علاجية', 'en' => 'Therapeutic expertise'], 'body' => ['ar' => 'خبرة في الأورام والجهاز التنفسي والقلب والأوعية والغدد واللقاحات والرعاية التخصصية.', 'en' => 'Experience across oncology, respiratory, cardiovascular, endocrinology, vaccines and specialty care.']],
        ['number' => '04', 'title' => ['ar' => 'ريادة السوق', 'en' => 'Market leadership'], 'body' => ['ar' => 'ثلاثة عقود من فهم المناقصات العامة والمسارات التنظيمية وديناميكيات السوق الخاص في العراق.', 'en' => 'Three decades navigating public tenders, regulatory pathways and private-market dynamics in Iraq.']],
        ['number' => '05', 'title' => ['ar' => 'استثمار مخصص', 'en' => 'Dedicated investment'], 'body' => ['ar' => 'فرق متخصصة ومبادرات نفاذ للسوق وأنظمة امتثال وبنية تجارية مكرسة للشركات التي نمثلها.', 'en' => 'Dedicated teams, market-access initiatives, compliance systems and commercial infrastructure.']],
    ],
    'services' => [
        'regulatory-affairs' => [
            'index' => '01', 'title' => ['ar' => 'التسجيل والشؤون التنظيمية', 'en' => 'Registration & regulatory affairs'],
            'summary' => ['ar' => 'خبرة دقيقة في ملفات التسجيل ومتطلبات وزارة الصحة، مع 53 موقع تصنيع و148 منتجاً دوائياً مسجلاً.', 'en' => 'Precise dossier management and Ministry of Health expertise, with 53 manufacturing sites and 148 registered products.'],
            'detail' => ['ar' => 'يتولى فريقنا إعداد الملفات ومتابعتها حتى الموافقة النهائية، ويضمن توافق كل منتج مع أعلى معايير السلامة والفعالية والجودة. تساعد خبرتنا وعلاقاتنا التنظيمية شركاءنا العالميين على إيصال العلاجات الأساسية إلى السوق العراقي بكفاءة.', 'en' => 'Our team manages dossiers from preparation to final approval, ensuring every product meets rigorous safety, efficacy and quality standards. Established regulatory relationships help global partners bring essential therapies to Iraq efficiently.'],
            'image' => 'assets/vid/products.mp4',
        ],
        'warehousing' => [
            'index' => '02', 'title' => ['ar' => 'المخازن وسلسلة التبريد', 'en' => 'Warehousing & cold chain'],
            'summary' => ['ar' => 'مرافق متوافقة مع ممارسات التخزين والتوزيع الجيد ومراقبة حرارية لحظية تحقق التزاماً بنسبة 99.7%.', 'en' => 'GSDP-compliant, temperature-controlled facilities with real-time monitoring and 99.7% cold-chain compliance.'],
            'detail' => ['ar' => 'تعمل مخازن بغداد وأربيل والبصرة بأنظمة متقدمة لإدارة المخزون والتتبع، مع غرف تبريد وعمليات نقل مؤمنة تحافظ على سلامة المنتجات من الاستلام حتى التسليم النهائي.', 'en' => 'Facilities in Baghdad, Erbil and Basra use advanced inventory and traceability systems, cold rooms and secure logistics to preserve product integrity from receipt to final delivery.'],
            'image' => 'assets/vid/warehouse.mp4',
        ],
        'distribution' => [
            'index' => '03', 'title' => ['ar' => 'التوزيع الوطني', 'en' => 'Nationwide distribution'],
            'summary' => ['ar' => 'شبكة تغطي 439,000 كم² وتصل إلى أكثر من 7,000 صيدلية ومذخر ومؤسسة صحية عبر ستة مراكز و46+ ناقلاً مبرداً.', 'en' => 'A 439,000 km² network reaching 7,000+ pharmacies, drugstores and healthcare institutions through six centres and 46+ cold-chain couriers.'],
            'detail' => ['ar' => 'تخدم كياناتنا في بغداد وأربيل (أومنيا كردستان) والبصرة المؤسسات الصحية والصيدليات مباشرة، وتربط الإمداد بالطلب الفعلي لتقليل الانقطاع وتسريع التسليم في جميع مناطق العراق.', 'en' => 'Our Baghdad, Erbil (Omnia Kurdistan) and Basra entities serve healthcare institutions and pharmacies directly, aligning supply with live demand to reduce stockouts and accelerate delivery nationwide.'],
            'image' => 'assets/images/distribution-map.jpg',
        ],
        'customer-relations' => [
            'index' => '04', 'title' => ['ar' => 'علاقات العملاء والمبيعات', 'en' => 'Customer relations & sales'],
            'summary' => ['ar' => '100 ممثل ومشرف ميداني مدعومون بأنظمة CRM وERP لتقديم استجابة دقيقة ورؤية سوقية متقدمة.', 'en' => 'A 100-person field force supported by CRM and ERP for responsive service and data-driven market insight.'],
            'detail' => ['ar' => 'نبني شراكات طويلة الأمد مع الصيدليات والمستشفيات والموزعين عبر الزيارات والمتابعة المستمرة وإدارة المخزون والطلبات والمرتجعات والتحصيل إلكترونياً.', 'en' => 'We build long-term relationships with pharmacies, hospitals and distributors through regular visits, continuous follow-up, stock checks, order fulfilment, quality-managed returns and electronic collection.'],
            'image' => 'assets/images/sales-activity.jpg',
        ],
        'pharmacovigilance' => [
            'index' => '05', 'title' => ['ar' => 'اليقظة الدوائية', 'en' => 'Pharmacovigilance'],
            'summary' => ['ar' => 'نظام متكامل لرصد وتقييم سلامة الدواء طوال دورة حياة المنتج وفق معايير وزارة الصحة وFDA وEMA.', 'en' => 'An integrated medicine-safety framework throughout the product lifecycle, aligned with MOH, FDA and EMA standards.'],
            'detail' => ['ar' => 'يشمل النظام الإبلاغ عن الأحداث العكسية واكتشاف الإشارات ومراجعات السلامة الدورية PSURs والدمج الكامل مع ضمان الجودة والإجراءات التشغيلية القياسية.', 'en' => 'The system covers adverse-event reporting, signal detection, periodic safety update reports (PSURs), continuous monitoring and full QA/SOP integration.'],
            'image' => 'assets/images/products.jpg',
        ],
        'compliance' => [
            'index' => '06', 'title' => ['ar' => 'الامتثال وضمان الجودة', 'en' => 'Compliance & quality assurance'],
            'summary' => ['ar' => 'حوكمة وإجراءات تشغيلية وتدقيق وتدريب مستمر مدعوم بأنظمة ISO 9001 و22301 و27001 و45001 و14001.', 'en' => 'Governance, SOPs, audits and continuous training supported by ISO 9001, 22301, 27001, 45001 and 14001 systems.'],
            'detail' => ['ar' => 'يمثل الامتثال قيمة تشغيلية أساسية في التسجيل والتوزيع واليقظة الدوائية وحماية المعلومات واستمرارية الأعمال والصحة والسلامة والبيئة.', 'en' => 'Compliance is embedded across registration, distribution, pharmacovigilance, information security, business continuity, occupational safety and environmental management.'],
            'image' => 'assets/images/gsdp-certificate.jpg',
        ],
    ],
    'timeline' => [
        ['year' => '1996', 'title' => ['ar' => 'التأسيس', 'en' => 'Foundation'], 'text' => ['ar' => 'انطلاق العمل بالتركيز على مناقصات وزارة الصحة.', 'en' => 'Established with a focus on Ministry of Health tenders.']],
        ['year' => '2007', 'title' => ['ar' => 'التوسع في القطاع الخاص', 'en' => 'Private-sector expansion'], 'text' => ['ar' => 'تنويع استراتيجي وبناء شبكة صيدليات التجزئة.', 'en' => 'Strategic diversification into private healthcare and retail pharmacy networks.']],
        ['year' => '2010', 'title' => ['ar' => 'عمليات كردستان', 'en' => 'Kurdistan operations'], 'text' => ['ar' => 'تأسيس أومنيا كردستان وحضور إقليمي مرخص.', 'en' => 'Omnia Kurdistan established with a direct retail licence and regional presence.']],
        ['year' => '2012', 'title' => ['ar' => 'التوسع جنوباً', 'en' => 'Southern expansion'], 'text' => ['ar' => 'إطلاق مركز البصرة واستكمال مثلث التوزيع الاستراتيجي.', 'en' => 'Basra hub launched, completing the strategic triangle of distribution centres.']],
        ['year' => '2026', 'title' => ['ar' => 'تغطية وطنية أوسع', 'en' => 'Nationwide coverage'], 'text' => ['ar' => 'توسع مخطط إلى الموصل وكركوك وكربلاء للوصول الوطني المتكامل.', 'en' => 'Planned expansion to Mosul, Kirkuk and Karbala for complete national reach.']],
    ],
    'quality_systems' => [
        ['code' => 'ISO 9001:2015', 'title' => ['ar' => 'إدارة الجودة', 'en' => 'Quality management']],
        ['code' => 'ISO 22301', 'title' => ['ar' => 'استمرارية الأعمال', 'en' => 'Business continuity']],
        ['code' => 'ISO 27001', 'title' => ['ar' => 'أمن المعلومات', 'en' => 'Information security']],
        ['code' => 'ISO 45001', 'title' => ['ar' => 'الصحة والسلامة المهنية', 'en' => 'Occupational health & safety']],
        ['code' => 'ISO 14001', 'title' => ['ar' => 'الإدارة البيئية', 'en' => 'Environmental management']],
        ['code' => 'GSDP', 'title' => ['ar' => 'ممارسات التخزين والتوزيع الجيد', 'en' => 'Good storage & distribution practices']],
    ],
    'partners' => [
        ['name' => 'AstraZeneca', 'since' => '1996', 'logo' => 'assets/partners/astrazeneca.png'],
        ['name' => 'Servier', 'since' => '2012', 'logo' => 'assets/partners/servier.png'],
        ['name' => 'MSD', 'since' => '2017', 'logo' => 'assets/partners/msd.png'],
        ['name' => 'Pfizer', 'since' => '2018', 'logo' => 'assets/partners/pfizer.png'],
        ['name' => 'Biogaran', 'since' => '2020', 'logo' => 'assets/partners/biogaran.png'],
        ['name' => 'Pharmanovia', 'since' => '2022', 'logo' => 'assets/partners/pharmanovia.png'],
        ['name' => 'AbbVie', 'since' => '2024', 'logo' => 'assets/partners/abbvie.png'],
        ['name' => 'Cheplapharm', 'since' => '2024', 'logo' => 'assets/partners/cheplapharm.png'],
        ['name' => 'Amgen', 'since' => '2026', 'logo' => null],
    ],
    'locations' => [
        ['city' => ['ar' => 'بغداد', 'en' => 'Baghdad'], 'detail' => ['ar' => 'حي بابل، محلة 929، شارع 19، مبنى مكتب بغداد الحياة العلمي', 'en' => 'Hay Babel, District 929-ST, Building 19, Baghdad Al Hayat Scientific Office Building'], 'phone' => '+964 782 3360 920'],
        ['city' => ['ar' => 'أربيل', 'en' => 'Erbil'], 'detail' => ['ar' => 'أومنيا كردستان، برج العدالة، الطابق 23', 'en' => 'Omnia Kurdistan, Justice Tower, 23rd floor'], 'phone' => '+964 782 7208 470'],
        ['city' => ['ar' => 'البصرة', 'en' => 'Basra'], 'detail' => ['ar' => 'البراضعية، شارع السراجي', 'en' => 'Al Bradiyyah, Sarraji Street'], 'phone' => '+964 783 3084 690'],
    ],
    'departments' => [
        ['ar' => 'مبيعات القطاع الخاص', 'en' => 'Private sales'], ['ar' => 'المناقصات', 'en' => 'Tender management'],
        ['ar' => 'ضمان الجودة والسلامة', 'en' => 'Quality assurance & safety'], ['ar' => 'الموارد البشرية', 'en' => 'Human resources'],
        ['ar' => 'اللوجستيات وسلسلة الإمداد', 'en' => 'Logistics & supply chain'], ['ar' => 'تقنية المعلومات وحماية البيانات', 'en' => 'IT & data protection'],
        ['ar' => 'المحاسبة والمالية', 'en' => 'Accounting & finance'], ['ar' => 'التدقيق', 'en' => 'Audit'],
        ['ar' => 'النقل', 'en' => 'Transportation'], ['ar' => 'إدارة المخازن', 'en' => 'Warehouse management'],
        ['ar' => 'الشؤون التنظيمية', 'en' => 'Regulatory affairs'], ['ar' => 'التسويق', 'en' => 'Marketing'],
        ['ar' => 'اليقظة الدوائية', 'en' => 'Pharmacovigilance'],
    ],
    'facilities' => [
        ['city' => ['ar' => 'بغداد', 'en' => 'Baghdad'], 'detail' => ['ar' => 'مخزن 3,200م² بغرفتي تبريد، مبنى مكاتب من 4 طوابق بمساحة 1,100م² لكل طابق، ومذخر 700م².', 'en' => '3,200 m² warehouse with two cold rooms, four office floors of 1,100 m² each, and a 700 m² drugstore.']],
        ['city' => ['ar' => 'أربيل', 'en' => 'Erbil'], 'detail' => ['ar' => 'مخزن 1,300م² بغرفة تبريد، مكاتب 1,700م²، ومذخر أومنيا كردستان للمبيعات المباشرة للصيدليات.', 'en' => '1,300 m² warehouse with one cold room, 1,700 m² offices, and Omnia Kurdistan drugstore for direct pharmacy sales.']],
        ['city' => ['ar' => 'البصرة', 'en' => 'Basra'], 'detail' => ['ar' => 'موقع بمساحة 900م² مع غرفة تبريد.', 'en' => '900 m² site with one cold room.']],
        ['city' => ['ar' => 'الموصل — توسع 2026', 'en' => 'Mosul — 2026 expansion'], 'detail' => ['ar' => 'مذخر دوائي مخطط بمساحة 700م².', 'en' => 'Planned 700 m² drugstore.']],
        ['city' => ['ar' => 'كركوك — توسع 2026', 'en' => 'Kirkuk — 2026 expansion'], 'detail' => ['ar' => 'مذخر دوائي مخطط بمساحة 300م².', 'en' => 'Planned 300 m² drugstore.']],
        ['city' => ['ar' => 'كربلاء — توسع 2026', 'en' => 'Karbala — 2026 expansion'], 'detail' => ['ar' => 'مذخر مخطط بمساحة 300م² مع غرفة تبريد.', 'en' => 'Planned 300 m² drugstore with a cold room.']],
        ['city' => ['ar' => 'السليمانية', 'en' => 'Sulaymaniyah'], 'detail' => ['ar' => 'مكاتب بغداد الحياة بمساحة 100م².', 'en' => 'Baghdad Al Hayat offices covering 100 m².']],
        ['city' => ['ar' => 'الناصرية', 'en' => 'Nasiriyah'], 'detail' => ['ar' => 'مكتب بمساحة 200م².', 'en' => '200 m² office.']],
    ],
];

function content_data(string $key): array
{
    global $SITE_CONTENT;
    if ($key === 'partners') {
        return array_values(array_filter(partner_records(), static fn(array $partner): bool => ($partner['status'] ?? 'visible') === 'visible'));
    }
    return $SITE_CONTENT[$key] ?? [];
}

/**
 * Partner logos are managed from the admin (الهوية والإعدادات › شعارات الشركاء) and stored as JSON
 * in settings.partners_json. Until the first admin save, the built-in list above is used.
 * Each record: id, name, since, logo (path or null), status (visible|archived).
 */
function partner_records(): array
{
    global $SITE_CONTENT;
    static $records = null;
    if ($records !== null) return $records;
    $stored = null;
    try {
        $raw = setting('partners_json');
        if ($raw !== '') $stored = json_decode($raw, true);
    } catch (Throwable) {
        $stored = null;
    }
    if (!is_array($stored)) {
        $stored = [];
        foreach ($SITE_CONTENT['partners'] ?? [] as $index => $partner) {
            $stored[] = ['id' => 'p' . ($index + 1), 'name' => $partner['name'], 'since' => $partner['since'], 'logo' => $partner['logo'], 'status' => 'visible'];
        }
    }
    $records = [];
    foreach ($stored as $partner) {
        if (!is_array($partner) || trim((string) ($partner['name'] ?? '')) === '') continue;
        $records[] = [
            'id' => (string) ($partner['id'] ?? bin2hex(random_bytes(4))),
            'name' => (string) $partner['name'],
            'since' => (string) ($partner['since'] ?? ''),
            'logo' => ($partner['logo'] ?? '') !== '' ? (string) $partner['logo'] : null,
            'status' => ($partner['status'] ?? 'visible') === 'archived' ? 'archived' : 'visible',
        ];
    }
    return $records;
}

function save_partner_records(array $records, ?int $adminId = null): void
{
    save_setting('partners_json', json_encode(array_values($records), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $adminId);
}
