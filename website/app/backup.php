<?php
/**
 * النسخ الاحتياطي — تصدير واستيراد محتوى الموقع وقاعدة البيانات.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const BACKUP_CONTENT_TABLES = ['settings', 'speakers', 'orgs', 'agenda_days', 'agenda_items', 'pages'];
const BACKUP_ALL_TABLES = ['settings', 'admins', 'speakers', 'orgs', 'agenda_days', 'agenda_items', 'pages', 'registrants', 'banned_ips', 'security_log'];

/** حزمة JSON لمحتوى الموقع (بدون بيانات المسجلين الحساسة) */
function backup_content_json(): string
{
    $out = ['_meta' => ['site' => 'scforum', 'kind' => 'content', 'version' => 1, 'created' => date('c')]];
    foreach (BACKUP_CONTENT_TABLES as $t) {
        $out[$t] = q_all("SELECT * FROM `$t`");
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

/** حزمة JSON كاملة تشمل المسجلين */
function backup_full_json(): string
{
    $out = ['_meta' => ['site' => 'scforum', 'kind' => 'full', 'version' => 1, 'created' => date('c')]];
    foreach (BACKUP_ALL_TABLES as $t) {
        if ($t === 'security_log') {
            $out[$t] = q_all("SELECT * FROM `$t` ORDER BY id DESC LIMIT 2000");
        } else {
            $out[$t] = q_all("SELECT * FROM `$t`");
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

/** استيراد نسخة محتوى: يستبدل جداول المحتوى ضمن معاملة واحدة */
function backup_restore_content(array $data): array
{
    $pdo = db();
    $restored = [];
    $pdo->beginTransaction();
    try {
        foreach (BACKUP_CONTENT_TABLES as $t) {
            if (!isset($data[$t]) || !is_array($data[$t])) continue;
            $rows = $data[$t];
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec("DELETE FROM `$t`");
            if ($rows) {
                $cols = array_keys($rows[0]);
                $colSql = '`' . implode('`,`', array_map(function ($c) {
                    return preg_replace('/[^a-z0-9_]/i', '', $c);
                }, $cols)) . '`';
                $ph = '(' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
                $st = $pdo->prepare("INSERT INTO `$t` ($colSql) VALUES $ph");
                foreach ($rows as $r) {
                    $st->execute(array_values($r));
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $restored[] = $t . ' (' . count($rows) . ')';
        }
        $pdo->commit();
        return ['ok' => true, 'restored' => $restored];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'فشل الاستيراد: بنية الملف غير متوافقة'];
    }
}

/** تفريغ SQL كامل (بنية + بيانات) */
function backup_sql_dump(): string
{
    $pdo = db();
    $sql = "-- Iraqi CSR & Business Integrity Forum — full backup\n-- " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$t)) continue;
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values($r));
            $cols = '`' . implode('`,`', array_keys($r)) . '`';
            $sql .= "INSERT INTO `$t` ($cols) VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

/** نسخة تلقائية يومية إلى storage/backups مع الاحتفاظ بآخر 30 */
function auto_backup_if_due(): void
{
    $dir = SCF_STORAGE . '/backups';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $last = @file_get_contents($dir . '/.last');
    if ($last !== false && (time() - (int)$last) < 86400) return;
    $file = $dir . '/auto-' . date('Ymd-His') . '.json';
    @file_put_contents($file, backup_full_json(), LOCK_EX);
    @file_put_contents($dir . '/.last', (string)time());
    /* الاحتفاظ بآخر 30 نسخة */
    $files = glob($dir . '/auto-*.json') ?: [];
    sort($files);
    while (count($files) > 30) {
        @unlink(array_shift($files));
    }
}

/** CSV للمسجلين (UTF-8 BOM ليفتح في Excel مباشرة) */
function registrants_csv(array $filter = []): string
{
    $w = '1=1';
    $params = [];
    if (!empty($filter['status'])) {
        $w .= ' AND status = ?';
        $params[] = $filter['status'];
    }
    $rows = q_all("SELECT code, title, full_name, gender, country, phone, email, sector, org, job, extra, status, wa_sent, attended, notes, created_at
                   FROM registrants WHERE $w ORDER BY id DESC", $params);
    $head = ['الرمز', 'اللقب', 'الاسم الكامل', 'الجنس', 'الدولة', 'رقم واتساب', 'البريد الإلكتروني', 'القطاع', 'الجهة / المؤسسة', 'المسمى الوظيفي', 'الحالة', 'أُرسل واتساب', 'حضر', 'ملاحظات', 'تاريخ التسجيل'];
    $customFields = registration_custom_fields();
    foreach ($customFields as $field) $head[] = $field['ar'] ?: ($field['en'] ?? $field['key']);
    $map = ['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض'];
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $head);
    foreach ($rows as $r) {
        $line = [
            $r['code'], registrant_title_label((string)$r['title'], 'ar'), $r['full_name'],
            $r['gender'] === 'male' ? 'ذكر' : ($r['gender'] === 'female' ? 'أنثى' : ''),
            $r['country'], '+' . $r['phone'], $r['email'], $r['sector'], $r['org'], $r['job'],
            $map[$r['status']] ?? $r['status'],
            $r['wa_sent'] ? 'نعم' : 'لا',
            $r['attended'] ? 'نعم' : 'لا',
            $r['notes'], $r['created_at'],
        ];
        $extra = registration_extra_decode($r['extra'] ?? '');
        foreach ($customFields as $field) $line[] = registration_custom_value_label($field, $extra[$field['key']] ?? '', 'ar');
        fputcsv($fh, $line);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return "\xEF\xBB\xBF" . $csv;
}
