# دعوة افتتاح مقر مجلس الأعمال العراقي–الأمريكي (A5 عرضي)

- `IUSBC_Invitation.pdf` — النسخة النهائية (210×148 مم). النص حقيقي (غير محوَّل لمنحنيات) والخط IBM Plex Sans Arabic مضمّن.
  زر «افتح الموقع على الخريطة» ورمز QR كلاهما رابط ينقل مباشرة إلى الخريطة.
- `IUSBC_Invitation.png` — معاينة/مشاركة رقمية (الروابط لا تعمل في الصور).
- `IUSBC_Invitation.idml` — ملف InDesign (DOM 20 = InDesign 2025): الشعاران مضمّنان، النصوص قابلة للتعديل، Bleed 3 مم.
- `Fonts/` — IBM Plex Sans Arabic (SIL OFL 1.1) — ثبّته قبل فتح IDML.
- `src/` — سكربت التوليد (`python3 build.py` ثم `node render.js`).

الرابط: https://maps.app.goo.gl/6UkNFgEYdHkM9V8R8

ملاحظات IDML:
- الروابط لا تُكتب داخل IDML بصيغة مضمونة، لذلك الشكلان المسميان «Map link (Button / QR)» (الزر ومربع QR الأبيض)
  يحتاجان ربطاً يدوياً: حدّد الشكل ← Window ▸ Interactive ▸ Hyperlinks ← New Hyperlink ← URL، وكرّر لنص الزر
  (إطار `btn`) ثم صدّر PDF بخيار Hyperlinks.
- النص عربي RTL؛ إن ظهرت الحروف منفصلة اضبط Paragraph Composer على Adobe World-Ready Paragraph Composer.
- الألوان RGB (Navy 0B2A55 / Gold CDA449) — حوّلها إلى CMYK عند الطباعة.

## بطاقات الأسماء
ضع الأسماء (اسم في كل سطر) في `src/names.txt` ثم `python3 build.py && node render.js` — تُنتج `named_cards/NN_الاسم.pdf`
(سطر المخاطَبة يتحول إلى الاسم + سطر «يرجى تأكيد الحضور» في الأسفل).
