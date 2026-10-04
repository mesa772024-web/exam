# Agenda draft – لقاء وفد الإستثمار والأعمال العراقي

مسودة تصميم أجندة (A4، مع 3 مم bleed) بناءً على ملف الوورد المرسل.

| File | Use |
|---|---|
| `out/Agenda_Draft.idml` | يُفتح في InDesign (File › Open) ويحفظ كـ `.indd`. كل العناصر قابلة للتعديل، والأنماط معرّفة كـ Paragraph Styles. |
| `out/Agenda_Draft.pdf` | معاينة / مراجعة |
| `out/Agenda_Draft.png` | معاينة سريعة |

- **الخط:** IBM Plex Sans Arabic (مجاني، موجود في `assets/fonts`). ثبّته قبل فتح ملف IDML.
- **الشعارات:** علما العراق وقطر مرسومان كـ vector. شعارا غرفة قطر واتحاد الغرف التجارية العراقية مكانهما إطارات مؤقتة (placeholder) لازم تستبدلها بالملفات الرسمية.
- **الوقت:** 11:30 حسب البريف. وقت الغداء 12:00 كما في ملف الوورد.
- **المسودة:** فيه شارة "مسودة" وعلامة مائية؛ احذفهما (*Draft badge*, *Draft watermark*) في النسخة النهائية.

Rebuild: `python3 build.py && node render.mjs` (needs `fonttools` and Playwright).
