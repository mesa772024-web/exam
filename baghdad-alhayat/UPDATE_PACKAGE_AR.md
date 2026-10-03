# صيغة حزمة التحديث

كل حزمة تحديث ZIP يجب أن تحتوي `update.json` في الجذر والملفات المراد إضافتها أو استبدالها. لا تحذف آلية التحديث ملفات قديمة تلقائياً.

مثال:

```json
{
  "version": "2.1.0",
  "files": {
    "index.php": "SHA256_LOWERCASE_HEX",
    "assets/css/v2.css": "SHA256_LOWERCASE_HEX"
  }
}
```

القواعد:

- `version` بصيغة semantic version.
- المفتاح داخل `files` هو المسار النسبي داخل ZIP والمشروع.
- القيمة هي SHA-256 الحقيقي للملف.
- لا يمكن للحزمة استبدال `storage`, `uploads`, `source-materials`, `.env`, أو `update.json`.
- تُرفض المسارات المطلقة و`..` والحزم الكبيرة أو التي تفشل بصماتها.
- قبل النسخ ينشئ النظام نسخة ZIP تلقائية من ملفات الموقع.

لإنشاء البصمة في PowerShell:

`(Get-FileHash -Algorithm SHA256 .\index.php).Hash.ToLowerInvariant()`
