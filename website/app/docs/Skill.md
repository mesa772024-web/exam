---
name: csr-update-package
description: How to write a correct, installable update package (ZIP) for the Iraqi CSR & Business Integrity Forum website (PHP 7.4+ / MySQL, Hostinger). Read this before changing any file of the site.
---

# Skill: كتابة تحديث لموقع منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي
# Skill: Writing an update for the Iraqi CSR & Business Integrity Forum website

> **لمن؟** لأي مطوّر أو مساعد ذكاء اصطناعي (Claude / Codex / ChatGPT) يُطلب منه تعديل الموقع.
> **Who is this for?** Any developer or AI assistant asked to change the site.
> التحديث يُرفع من: لوحة التحكم ← «التحديثات والتنزيلات» ← رفع ZIP ← مراجعة الملفات ← تثبيت.

---

## 1. شكل الحزمة · Package format

حزمة التحديث = **ملف ZIP** يحتوي **الملفات المعدّلة أو الجديدة فقط** بمساراتها النسبية من **جذر الموقع** (المجلد الذي فيه `index.php`).

```
my-update.zip
├── update.json            ← اختياري لكن مستحسن
├── index.php              ← يستبدل /index.php
├── app/helpers.php        ← يستبدل /app/helpers.php
├── assets/css/recap.css
└── csr-admin/posts.php   ← مجلد لوحة التحكم (csr-admin)
```

- يُسمح بمجلد واحد يغلّف كل شيء (مثل `update-2026-09/app/…`) — يُزال تلقائياً.
- الملفات المطابقة تماماً للموجود تُتجاهل (تظهر «بدون تغيير»).
- `update.json` و `Skill.md` و `README.txt` في الجذر لا تُنسخ إلى الموقع.

### update.json
```json
{
  "version": "2026.09.20-r1",
  "title": "إضافة قسم الرعاة الجدد",
  "notes": "ماذا تغيّر ولماذا — يظهر في سجل التحديثات",
  "delete": ["assets/js/old-file.js"],
  "migrate": false
}
```
| الحقل | المعنى |
|---|---|
| `version` | رقم الإصدار، يُفضَّل `YYYY.MM.DD-rN`. إن غاب يُولَّد تلقائياً من التاريخ. |
| `title` / `notes` | وصف يظهر في السجل. |
| `delete` | ملفات تُحذف من الموقع (تُحفظ نسخة منها للرجوع). |
| `migrate` | `true` يشغّل `scf_migrate()` بعد نسخ الملفات (لتغييرات قاعدة البيانات) مع نسخة SQL احتياطية. |

---

## 2. الممنوع · Hard rules (the installer rejects these)

1. **لا** ملفات داخل `storage/` أو `uploads/` (بيانات الاتصال، النسخ، ملفات المستخدمين).
2. أسماء الملفات: حروف لاتينية وأرقام و `. _ - @` فقط. لا مسافات ولا عربي في أسماء الملفات.
3. الامتدادات المسموحة: `php js css json html svg png jpg jpeg webp gif ico woff woff2 ttf otf mp4 webm txt md xml map pdf sql .htaccess`.
4. **كل ملف PHP يُفحص صياغياً** قبل التثبيت؛ أي خطأ صياغة يوقف التحديث بالكامل ولا يُلمس الموقع.
5. الحد: 60MB للملف، 200MB للحزمة بعد فك الضغط، 3000 ملف.

## 3. قواعد كتابة الشيفرة · Coding conventions

- **PHP 7.4 متوافق**: ممنوع `match()`, `str_contains()`, `?->`, الوسائط المسمّاة، `enum`، `readonly`. (المثبّت يحذّر منها.)
- **قاعدة البيانات**: PDO فقط عبر `q()`, `q_one()`, `q_all()`, `q_val()` مع **استعلامات مُعدّة** (placeholders `?`). لا تُدمج مدخلات المستخدم في SQL أبداً.
- **المخرجات**: كل نص ديناميكي يُطبع عبر `e()` (htmlspecialchars).
- **النماذج**: CSRF إلزامي — `csrf_field()` في النموذج و `csrf_require()` في المعالج. طلبات AJAX ترسل `_csrf`.
- **لوحة التحكم**: كل صفحة تبدأ بـ `require_once __DIR__ . '/inc/layout.php'; require_super();` ثم `admin_header('العنوان', 'key')` … `admin_footer()`. أضف الصفحة إلى القائمة في `csr-admin/inc/layout.php`.
- **النصوص الثنائية اللغة في الواجهة**: استخدم **`tt('النص العربي', 'English text')`** دائماً (لا تكتب `$L === 'ar' ? … : …`). هكذا يصبح النص قابلاً للتعديل من «وضع التحرير» ومن صفحة «نصوص الواجهة» تلقائياً. النص الذي يحوي وسوماً (`<em>`, `<br>`) يُطبع بدون `e()`، وغيره داخل `e()`.
- **الإعدادات**: `setting('key')` / `setting_l('base')` (ثنائي اللغة `base_ar`/`base_en`) / `setting_set()`.
- **الصور القابلة للاستبدال**: `slot_url('key')` + `slot_attr('key')` (عرّف الخانة في `app/slots.php`).
- **المنشورات**: جدول `posts` عبر `app/posts.php` (`scf_posts_list()`), والصور تُعالَج بـ `scf_image_process()`.
- **رفع الملفات**: لا تحفظ ملفاً مرفوعاً كما هو؛ الصور تُعاد ترميزها عبر GD (`scf_image_process`) وتُحفظ داخل `uploads/`.
- **CSS/JS**: عند تعديل `assets/css/*.css` أو `assets/js/*.js` **ارفع رقم الإصدار** في رابطه داخل `app/layout.php` (مثل `?v=20260920`) حتى لا يبقى الملف القديم في ذاكرة المتصفح.
- **الاتجاه**: الموقع RTL للعربي و LTR للإنكليزي — استخدم خصائص CSS المنطقية (`inset-inline-start`, `margin-inline-end`, `padding-inline`).
- **الحركة**: احترم `prefers-reduced-motion`، ولا تستخدم مكتبات خارجية (CSP يمنع أي نطاق خارجي).

## 4. تغييرات قاعدة البيانات · Database changes

- أضف التغيير في `app/migrate.php` داخل `scf_migrate()` بصيغة **آمنة للتكرار** (idempotent):
  `CREATE TABLE IF NOT EXISTS …`، أو تحقّق من وجود العمود قبل `ALTER TABLE … ADD COLUMN`.
- ضع `"migrate": true` في `update.json`.
- لا تحذف جداول أو أعمدة فيها بيانات المسجلين أبداً.

## 5. قائمة الفحص قبل التسليم · Checklist

- [ ] `php -l` لكل ملف PHP معدّل (على PHP 7.4 و 8.x).
- [ ] فتح الصفحات المتأثرة بالعربي والإنكليزي، والموبايل (360px) والحاسوب.
- [ ] لا أخطاء في Console المتصفح.
- [ ] رفع رقم إصدار CSS/JS المعدّلة في `app/layout.php`.
- [ ] الحزمة تحوي فقط الملفات المعدّلة + `update.json`.
- [ ] اسم الحزمة واضح: `csr-update-2026.10.04-r1.zip`.

## 6. الرجوع · Rollback

كل تثبيت يحفظ نسخة من الملفات المستبدلة والمحذوفة (ونسخة SQL) في `storage/update-backups/`.
من «سجل التحديثات» اضغط **«رجوع»** لإعادة الملفات كما كانت. (الرجوع يعيد الملفات فقط؛ قاعدة البيانات تُستعاد يدوياً من ملف SQL المحفوظ عند الحاجة.)

## 7. خريطة المشروع · Project map

| المسار | الوظيفة |
|---|---|
| `index.php` | الرئيسية (الأقسام تُرتَّب من `sections_order`) |
| `register.php`, `page.php` | صفحات عامة |
| `app/edition2.php` | الواجهة (الكرة الأرضية والعد التنازلي)، الشرائح، النسخ السابقة، التوصيات، Speak Up |
| `app/texts.php` | طبقة النصوص القابلة للتعديل `tt()` ووضع التحرير |
| `app/posts.php`, `app/slots.php` | المنشورات، خانات الصور |
| `app/layout.php` | الرأس والتذييل وشريط التحرير |
| `app/helpers.php`, `app/db.php`, `app/guard.php`, `app/csrf.php` | الأساسيات والأمان (WAF، تقييد المعدل) |
| `app/migrate.php` | ترقيات قاعدة البيانات |
| `api/*.php` | نقاط استقبال عامة (Speak Up …) |
| `assets/css/csr.css`, `assets/js/csr.js` | هوية المنتدى وتصميم الواجهة وطبقة الحركة |
| `assets/css/recap.css`, `assets/js/recap.js` | مكونات المحرك (الشرائح، النوافذ، الفيديو) |
| `app/recommendations.json` | توصيات النسخ السابقة (عربي/إنكليزي) |
| `assets/css/site-editor.css`, `assets/js/site-editor.js` | محرر الواجهة المباشر |
| `csr-admin/` | لوحة التحكم |
| `storage/`, `uploads/` | بيانات — **لا تُلمس في التحديثات** |
