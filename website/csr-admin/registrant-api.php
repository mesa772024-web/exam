<?php
/** نقاط إجراءات المسجّلين — POST + CSRF + جلسة إدارية */
require_once __DIR__ . '/inc/auth.php';

admin_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false], 405);
}
csrf_require();

$action = (string)($_POST['action'] ?? '');

/* موظف التسجيل (desk) يُسمح له فقط بإجراءات القاعة */
$deskAllowed = ['checkin', 'queue_add', 'checkin_mark', 'admit', 'hall_state', 'hall_reset_seat', 'hall_search', 'hall_reset', 'move_seat'];
if (!is_super() && !in_array($action, $deskAllowed, true)) {
    json_out(['ok' => false, 'msg' => 'صلاحية غير كافية'], 403);
}

$id = (int)($_POST['id'] ?? 0);
$ids = [];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_slice(array_map('intval', $_POST['ids']), 0, 500);
} elseif ($id > 0) {
    $ids = [$id];
}

function reg_row(int $id): ?array
{
    return q_one('SELECT * FROM registrants WHERE id = ?', [$id]);
}

function admin_sector_options(): array
{
    return array_values(array_unique(array_merge(
        json_decode(setting('reg_sectors_ar', '[]'), true) ?: [],
        json_decode(setting('reg_sectors_en', '[]'), true) ?: []
    )));
}

function admin_other_sector_option(): string
{
    foreach (admin_sector_options() as $option) {
        if (is_other_choice((string)$option)) return (string)$option;
    }
    return 'أخرى';
}

function admin_resolve_title(): string
{
    $choice = (string)($_POST['title'] ?? '');
    if (in_array($choice, ['dr', 'eng', 'mr', 'mrs', 'prof'], true)) return $choice;
    return $choice === 'other' ? clean_text($_POST['title_other'] ?? '', 120) : '';
}

function admin_reg_field_enabled(string $key): bool
{
    foreach (registration_fields() as $field) {
        if (($field['key'] ?? '') === $key) return !empty($field['on']);
    }
    return false;
}

function admin_reg_field_required(string $key): bool
{
    foreach (registration_fields() as $field) {
        if (($field['key'] ?? '') === $key) return !empty($field['on']) && !empty($field['req']);
    }
    return false;
}

function admin_resolve_sector(): string
{
    $choice = clean_text($_POST['sector'] ?? '', 120);
    if (!in_array($choice, admin_sector_options(), true)) return '';
    return is_other_choice($choice) ? clean_text($_POST['sector_other'] ?? '', 120) : $choice;
}

function admin_collect_registration_extra(array $existing = []): array
{
    $fields = registration_fields();
    foreach ($fields as $field) {
        if (registration_field_is_custom($field) && !empty($field['on'])) unset($existing[$field['key']]);
    }
    $errors = [];
    $new = registration_collect_custom_values($fields, $_POST, $errors, false);
    if ($errors) json_out(['ok' => false, 'msg' => reset($errors), 'errors' => $errors], 422);
    return array_merge($existing, $new);
}

/** يمنع تضارب اختيار المقعد عند عمل أكثر من موظف في اللحظة نفسها. */
function hall_locked(callable $work): array
{
    $locked = (int)q_val("SELECT GET_LOCK('scforum_hall_seats', 5)");
    if ($locked !== 1) return ['ok' => false, 'msg' => 'القاعة مشغولة للحظة، أعد المحاولة'];
    try {
        return $work();
    } finally {
        q_val("SELECT RELEASE_LOCK('scforum_hall_seats')");
    }
}

switch ($action) {
    case 'approve':
    case 'reject':
    case 'pending':
        if (!$ids) json_out(['ok' => false, 'msg' => 'لا يوجد تحديد'], 400);
        $st = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
        $in = rtrim(str_repeat('?,', count($ids)), ',');
        q("UPDATE registrants SET status = '$st' WHERE id IN ($in)", $ids);
        admin_audit('reg_' . $action, 'ids=' . implode(',', $ids));
        json_out(['ok' => true, 'status' => $st, 'count' => count($ids)]);

    case 'wa_toggle':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        $new = $r['wa_sent'] ? 0 : 1;
        q('UPDATE registrants SET wa_sent = ?, wa_sent_at = ? WHERE id = ?', [$new, $new ? date('Y-m-d H:i:s') : null, $id]);
        admin_audit('reg_wa_toggle', 'id=' . $id . ' sent=' . $new);
        json_out(['ok' => true, 'wa_sent' => $new]);

    case 'wa_sent':
        if (!$ids) json_out(['ok' => false, 'msg' => 'لا يوجد تحديد'], 400);
        $in = rtrim(str_repeat('?,', count($ids)), ',');
        q("UPDATE registrants SET wa_sent = 1, wa_sent_at = NOW() WHERE id IN ($in)", $ids);
        admin_audit('reg_wa_bulk', 'ids=' . implode(',', $ids));
        json_out(['ok' => true, 'count' => count($ids)]);

    case 'wa_send_api':
        require_once dirname(__DIR__) . '/app/whatsapp.php';
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        $res = wa_send_api($r);
        if ($res['ok']) {
            q('UPDATE registrants SET wa_sent = 1, wa_sent_at = NOW() WHERE id = ?', [$id]);
            admin_audit('reg_wa_api', 'id=' . $id);
        }
        json_out($res);

    case 'wa_send_meta':
        require_once dirname(__DIR__) . '/app/whatsapp.php';
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        $res = wa_send_meta($r);
        if ($res['ok']) {
            q('UPDATE registrants SET wa_sent = 1, wa_sent_at = NOW() WHERE id = ?', [$id]);
            admin_audit('reg_wa_meta', 'id=' . $id);
        }
        json_out($res);

    case 'notes':
        if (reg_row($id) === null) json_out(['ok' => false], 404);
        $notes = clean_text($_POST['notes'] ?? '', 2000);
        q('UPDATE registrants SET notes = ? WHERE id = ?', [$notes, $id]);
        admin_audit('reg_notes', 'id=' . $id);
        json_out(['ok' => true]);

    case 'get_notes':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        json_out(['ok' => true, 'notes' => (string)$r['notes']]);

    case 'get':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        $titleRaw = (string)$r['title'];
        $titleKnown = in_array($titleRaw, ['dr', 'eng', 'mr', 'mrs', 'prof'], true);
        $sectorRaw = (string)$r['sector'];
        $sectorKnown = in_array($sectorRaw, admin_sector_options(), true) && !is_other_choice($sectorRaw);
        $responseRow = [
            'id' => (int)$r['id'],
            'title' => $titleKnown ? $titleRaw : ($titleRaw !== '' ? 'other' : ''),
            'title_other' => (!$titleKnown && !is_other_choice($titleRaw)) ? $titleRaw : '',
            'full_name' => $r['full_name'], 'gender' => $r['gender'],
            'age' => $r['age'], 'phone' => $r['phone'], 'email' => $r['email'],
            'org' => $r['org'], 'job' => $r['job'],
            'sector' => $sectorKnown ? $sectorRaw : ($sectorRaw !== '' ? admin_other_sector_option() : ''),
            'sector_other' => (!$sectorKnown && !is_other_choice($sectorRaw)) ? $sectorRaw : '',
            'city' => $r['city'], 'country' => $r['country'],
        ];
        $extra = registration_extra_decode($r['extra'] ?? '');
        foreach (registration_custom_fields(true) as $field) {
            $key = (string)$field['key'];
            $responseRow[$key] = registration_custom_raw_value($extra, $key);
            $stored = $extra[$key] ?? '';
            if (is_array($stored) && ($stored['value'] ?? '') === '__other__') $responseRow[$key . '_other'] = clean_text($stored['label'] ?? '', 500);
        }
        json_out(['ok' => true, 'row' => $responseRow]);

    case 'edit':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        $name  = admin_reg_field_enabled('full_name') ? clean_text($_POST['full_name'] ?? '', 160) : (string)$r['full_name'];
        $titleOn = admin_reg_field_enabled('title');
        $title = $titleOn ? admin_resolve_title() : (string)$r['title'];
        $phone = admin_reg_field_enabled('phone') ? normalize_phone((string)($_POST['phone'] ?? '')) : (string)$r['phone'];
        $email = admin_reg_field_enabled('email') ? strtolower(clean_text($_POST['email'] ?? '', 160)) : (string)$r['email'];
        $country = admin_reg_field_enabled('country') ? clean_text($_POST['country'] ?? '', 100) : (string)$r['country'];
        $sector = admin_reg_field_enabled('sector') ? admin_resolve_sector() : (string)$r['sector'];
        $org = admin_reg_field_enabled('org') ? clean_text($_POST['org'] ?? '', 200) : (string)$r['org'];
        $job = admin_reg_field_enabled('job') ? clean_text($_POST['job'] ?? '', 200) : (string)$r['job'];
        $gender = admin_reg_field_enabled('gender') ? (in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : '') : (string)$r['gender'];
        $extra = admin_collect_registration_extra(registration_extra_decode($r['extra'] ?? ''));
        if ($titleOn && admin_reg_field_required('title') && $title === '') json_out(['ok' => false, 'msg' => 'أدخل اللقب'], 422);
        if (admin_reg_field_enabled('full_name') && $name !== '' && mb_strlen($name) < 3) json_out(['ok' => false, 'msg' => 'أدخل الاسم'], 422);
        if (admin_reg_field_enabled('phone') && $phone !== '' && !preg_match('/^\d{10,15}$/', $phone)) json_out(['ok' => false, 'msg' => 'رقم غير صحيح'], 422);
        if (admin_reg_field_enabled('email') && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'msg' => 'بريد غير صحيح'], 422);
        foreach (['full_name'=>$name,'gender'=>$gender,'country'=>$country,'phone'=>$phone,'email'=>$email,'sector'=>$sector,'org'=>$org,'job'=>$job] as $fieldKey=>$fieldValue) {
            if (admin_reg_field_required($fieldKey) && trim((string)$fieldValue) === '') json_out(['ok' => false, 'msg' => 'أكمل الحقول المطلوبة'], 422);
        }
        // تفادي تكرار الهاتف مع شخص آخر
        $dup = null;
        if ($phone !== '') $dup = q_one('SELECT id FROM registrants WHERE phone = ? AND id <> ?', [$phone, $id]);
        if ($dup === null && $email !== '') $dup = q_one('SELECT id FROM registrants WHERE email = ? AND id <> ?', [$email, $id]);
        if ($dup !== null) json_out(['ok' => false, 'msg' => 'الرقم مستخدم لمسجّل آخر'], 409);
        q('UPDATE registrants SET title=?, full_name=?, gender=?, age=NULL, phone=?, email=?, org=?, job=?, sector=?, city=?, country=?, extra=? WHERE id=?', [
            $title, $name, $gender, $phone, $email, $org, $job, $sector, '', $country, json_encode($extra, JSON_UNESCAPED_UNICODE), $id,
        ]);
        admin_audit('reg_edit', 'id=' . $id);
        json_out(['ok' => true]);

    case 'delete':
        if (!$ids) json_out(['ok' => false, 'msg' => 'لا يوجد تحديد'], 400);
        $in = rtrim(str_repeat('?,', count($ids)), ',');
        q("DELETE FROM registrants WHERE id IN ($in)", $ids);
        admin_audit('reg_delete', 'ids=' . implode(',', $ids));
        json_out(['ok' => true, 'count' => count($ids)]);

    case 'add':
        $name  = admin_reg_field_enabled('full_name') ? clean_text($_POST['full_name'] ?? '', 160) : '';
        $titleOn = admin_reg_field_enabled('title');
        $title = $titleOn ? admin_resolve_title() : '';
        $phone = admin_reg_field_enabled('phone') ? normalize_phone((string)($_POST['phone'] ?? '')) : '';
        $email = admin_reg_field_enabled('email') ? strtolower(clean_text($_POST['email'] ?? '', 160)) : '';
        $gender= admin_reg_field_enabled('gender') && in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : '';
        $org   = admin_reg_field_enabled('org') ? clean_text($_POST['org'] ?? '', 200) : '';
        $job   = admin_reg_field_enabled('job') ? clean_text($_POST['job'] ?? '', 200) : '';
        $country = admin_reg_field_enabled('country') ? clean_text($_POST['country'] ?? '', 100) : '';
        $sector = admin_reg_field_enabled('sector') ? admin_resolve_sector() : '';
        $extra = admin_collect_registration_extra();
        if ($titleOn && admin_reg_field_required('title') && $title === '') json_out(['ok' => false, 'msg' => 'أدخل اللقب'], 422);
        if ($name !== '' && mb_strlen($name) < 3) json_out(['ok' => false, 'msg' => 'أدخل الاسم'], 422);
        if ($phone !== '' && !preg_match('/^\d{10,15}$/', $phone)) json_out(['ok' => false, 'msg' => 'رقم الهاتف غير صحيح'], 422);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'msg' => 'البريد غير صحيح'], 422);
        foreach (['full_name'=>$name,'gender'=>$gender,'country'=>$country,'phone'=>$phone,'email'=>$email,'sector'=>$sector,'org'=>$org,'job'=>$job] as $fieldKey=>$fieldValue) {
            if (admin_reg_field_required($fieldKey) && trim((string)$fieldValue) === '') json_out(['ok' => false, 'msg' => 'أكمل الحقول المطلوبة'], 422);
        }
        $dup = $phone !== '' ? q_one('SELECT id FROM registrants WHERE phone = ?', [$phone]) : null;
        if ($dup === null && $email !== '') $dup = q_one('SELECT id FROM registrants WHERE email = ?', [$email]);
        if ($dup !== null) json_out(['ok' => false, 'msg' => 'الرقم مسجّل مسبقاً'], 409);
        $code = gen_reg_code();
        $st = !empty($_POST['approve']) ? 'approved' : 'pending';
        q('INSERT INTO registrants (code, title, full_name, gender, phone, email, org, job, sector, country, extra, status, lang, ip)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$code, $title, $name, $gender, $phone, $email, $org, $job, $sector, $country, json_encode($extra, JSON_UNESCAPED_UNICODE), $st, 'ar', client_ip()]);
        admin_audit('reg_add', 'code=' . $code);
        json_out(['ok' => true, 'code' => $code]);

    case 'toggle_reg':
        $new = setting('reg_open', '1') === '1' ? '0' : '1';
        setting_set('reg_open', $new);
        admin_audit('reg_toggle_open', 'open=' . $new);
        json_out(['ok' => true, 'open' => $new === '1']);

    case 'checkin':
        $code = strtoupper(clean_text($_POST['code'] ?? '', 20));
        $r = q_one('SELECT * FROM registrants WHERE code = ?', [$code]);
        if ($r === null) json_out(['ok' => false, 'msg' => 'الرمز غير موجود']);
        $payload = [
            'ok'       => true,
            'id'       => (int)$r['id'],
            'code'     => $r['code'],
            'name'     => $r['full_name'],
            'org'      => $r['org'],
            'job'     => $r['job'],
            'phone'    => '+' . $r['phone'],
            'status'   => $r['status'],
            'attended' => (int)$r['attended'],
            'attended_at' => $r['attended_at'],
        ];
        json_out($payload);

    case 'queue_add':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false, 'msg' => 'المسجّل غير موجود'], 404);
        if ($r['status'] !== 'approved') json_out(['ok' => false, 'msg' => 'المسجّل غير مقبول']);
        if ((int)$r['attended'] === 1) json_out(['ok' => false, 'msg' => 'دخل القاعة مسبقاً']);
        $adminId = (int)(admin_user()['id'] ?? 0);
        q('INSERT IGNORE INTO hall_queue (registrant_id, added_by) VALUES (?,?)', [$id, $adminId ?: null]);
        json_out(['ok' => true, 'row' => ['id' => (int)$r['id'], 'code' => $r['code'], 'name' => $r['full_name'], 'org' => $r['org']]]);

    case 'checkin_mark':
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        if ($r['status'] !== 'approved') json_out(['ok' => false, 'msg' => 'المسجّل غير مقبول']);
        q('UPDATE registrants SET attended = 1, attended_at = IFNULL(attended_at, NOW()) WHERE id = ?', [$id]);
        admin_audit('reg_checkin', 'id=' . $id);
        json_out(['ok' => true]);

    /* ===== نظام القاعة التفاعلي ===== */
    case 'hall_state':
        $cap = (int)setting('hall_capacity', '150');
        $entered = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
        $seats = q_all('SELECT id, full_name, org, seat_no FROM registrants WHERE attended = 1 AND seat_no IS NOT NULL ORDER BY seat_no');
        q("DELETE hq FROM hall_queue hq LEFT JOIN registrants r ON r.id=hq.registrant_id WHERE r.id IS NULL OR r.attended=1 OR r.status<>'approved'");
        $queue = q_all("SELECT r.id, r.code, r.full_name name, r.org FROM hall_queue hq JOIN registrants r ON r.id=hq.registrant_id ORDER BY hq.created_at, r.id");
        json_out(['ok' => true, 'capacity' => $cap, 'entered' => $entered, 'seats' => $seats,
                  'waiting' => $queue, 'print_on_admit' => setting('print_on_admit', '1') === '1']);

    case 'admit':
        $result = hall_locked(function () use ($id) {
            $r = reg_row($id);
            if ($r === null) return ['ok' => false, 'msg' => 'غير موجود'];
            if ($r['status'] !== 'approved') return ['ok' => false, 'msg' => 'المسجّل غير مقبول'];
            $cap = (int)setting('hall_capacity', '150');
            if ((int)$r['attended'] === 1 && $r['seat_no'] !== null) {
                q('DELETE FROM hall_queue WHERE registrant_id = ?', [$id]);
                $entered = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
                return ['ok' => true, 'already' => true, 'seat' => (int)$r['seat_no'], 'name' => $r['full_name'], 'entered' => $entered, 'capacity' => $cap];
            }
            $entered = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
            if ($entered >= $cap) return ['ok' => false, 'msg' => 'القاعة ممتلئة! (' . $cap . ' مقعد)'];
            $used = array_map('intval', array_column(q_all('SELECT seat_no FROM registrants WHERE seat_no IS NOT NULL'), 'seat_no'));
            $seat = 1; $usedSet = array_flip($used);
            while (isset($usedSet[$seat]) && $seat <= $cap) $seat++;
            q('UPDATE registrants SET attended = 1, attended_at = IFNULL(attended_at, NOW()), seat_no = ?, badge_printed = 1 WHERE id = ?', [$seat, $id]);
            q('DELETE FROM hall_queue WHERE registrant_id = ?', [$id]);
            admin_audit('hall_admit', 'id=' . $id . ' seat=' . $seat);
            return ['ok' => true, 'seat' => $seat, 'name' => $r['full_name'], 'entered' => $entered + 1, 'capacity' => $cap];
        });
        json_out($result);

    case 'hall_reset_seat':
        // إلغاء دخول شخص (إخلاء مقعده) — للطوارئ
        $r = reg_row($id);
        if ($r === null) json_out(['ok' => false], 404);
        q('UPDATE registrants SET attended = 0, seat_no = NULL WHERE id = ?', [$id]);
        admin_audit('hall_reset_seat', 'id=' . $id);
        json_out(['ok' => true]);

    case 'hall_search':
        // بحث بالاسم للإدخال اليدوي (منفصل عن الباركود)
        $qstr = clean_text($_POST['q'] ?? '', 60);
        if (mb_strlen($qstr) < 2) json_out(['ok' => true, 'rows' => []]);
        $like = '%' . $qstr . '%';
        $rows = q_all("SELECT id, code, full_name, org, status, attended, seat_no FROM registrants
                       WHERE full_name LIKE ? ORDER BY (status='approved') DESC, attended ASC, full_name LIMIT 15", [$like]);
        json_out(['ok' => true, 'rows' => $rows]);

    case 'hall_reset':
        // بدء جلسة جديدة: إخلاء كل الحضور والمقاعد
        $n = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
        q('UPDATE registrants SET attended = 0, attended_at = NULL, seat_no = NULL, badge_printed = 0 WHERE attended = 1');
        q('DELETE FROM hall_queue');
        admin_audit('hall_new_session', 'cleared=' . $n, 'medium');
        json_out(['ok' => true, 'cleared' => $n]);

    case 'move_seat':
        // نقل جالس إلى مقعد آخر (فارغ)
        $to = (int)($_POST['to'] ?? 0);
        $cap = (int)setting('hall_capacity', '150');
        if ($to < 1 || $to > $cap) json_out(['ok' => false, 'msg' => 'مقعد غير صالح'], 400);
        $result = hall_locked(function () use ($id, $to) {
            $r = reg_row($id);
            if ($r === null || (int)$r['attended'] !== 1) return ['ok' => false, 'msg' => 'غير موجود'];
            $taken = q_one('SELECT id FROM registrants WHERE seat_no = ? AND id <> ?', [$to, $id]);
            if ($taken !== null) return ['ok' => false, 'msg' => 'المقعد مشغول'];
            q('UPDATE registrants SET seat_no = ? WHERE id = ?', [$to, $id]);
            admin_audit('hall_move_seat', 'id=' . $id . ' -> ' . $to);
            return ['ok' => true, 'seat' => $to];
        });
        json_out($result);

    case 'save_zones':
        if (!is_super()) json_out(['ok' => false, 'msg' => 'صلاحية غير كافية'], 403);
        $z = json_decode((string)($_POST['zones'] ?? '{}'), true);
        if (!is_array($z)) $z = [];
        $clean = [];
        $cap = (int)setting('hall_capacity', '150');
        foreach ($z as $seat => $color) {
            $s = (int)$seat;
            if ($s < 1 || $s > $cap) continue;
            if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$color)) $clean[$s] = $color;
        }
        setting_set('hall_zones', json_encode($clean, JSON_UNESCAPED_UNICODE));
        admin_audit('hall_zones_save', 'count=' . count($clean));
        json_out(['ok' => true]);
}

json_out(['ok' => false, 'msg' => 'إجراء غير معروف'], 400);
