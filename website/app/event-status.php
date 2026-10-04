<?php
/** النسخة الثالثة لم تنعقد بعد — التسجيل مفتوح ويُدار من لوحة التحكم (نموذج التسجيل ← «التسجيل مفتوح»). */
function scf_event_concluded(): bool { return false; }

/** ميزة «شاركنا تجربتك · Speak Up» — متوقفة افتراضياً وتُفعَّل من لوحة التحكم. */
function scf_speakup_on(): bool { return setting('speakup_on', '0') === '1'; }
