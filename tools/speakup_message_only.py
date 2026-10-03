# -*- coding: utf-8 -*-
"""Speak Up becomes a single message box: no name, email, phone or subject.

Edits messages.php of a package in place (idempotent).
usage: python3 tools/speakup_message_only.py alhayat baghdad-alhayat
"""
import re
import sys

for pkg in sys.argv[1:]:
    p = f'{pkg}/messages.php'
    s = open(p, encoding='utf8').read()

    # --- server side: only the message is read and required
    s = re.sub(
        r"        \$fullName = clean_text\(\$_POST\['full_name'\].*?\n        \}\n        // Email is OPTIONAL.*?\n        \}\n",
        "        $message = clean_multiline($_POST['message'] ?? '', 4000);\n"
        "        $old['message'] = $message;\n\n"
        "        if (mb_strlen($message) < 3) {\n"
        "            throw new RuntimeException($isAr ? 'يرجى كتابة رسالتك.' : 'Please write your message.');\n"
        "        }\n",
        s, flags=re.S)
    s = s.replace("                'full_name' => $fullName,\n                'email' => $email,\n"
                  "                'phone' => $phone !== '' ? $phone : null,\n"
                  "                'subject' => $subject !== '' ? $subject : ($isAr ? 'رسالة عامة' : 'General message'),",
                  "                'full_name' => $isAr ? 'مجهول' : 'Anonymous',\n                'email' => '',\n"
                  "                'phone' => null,\n"
                  "                'subject' => 'Speak Up',")

    # --- intro line no longer mentions the (removed) email field
    s = s.replace(' البريد الإلكتروني اختياري.', '').replace(' Email is optional.', '')

    # --- form: drop every field except the message
    s = re.sub(r'\n          <div class="speakup-field">\n            <label for="su-(name|email|phone|subject)">.*?\n          </div>', '', s, flags=re.S)

    assert "su-name" not in s and "$fullName" not in s, pkg
    open(p, 'w', encoding='utf8').write(s)
    print('speak up → message only:', pkg)
