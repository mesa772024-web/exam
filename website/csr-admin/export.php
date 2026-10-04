<?php
/** تصدير بيانات المسجّلين — CSV بترميز UTF-8 يفتح في Excel مباشرة */
require_once __DIR__ . '/inc/auth.php';
require_once dirname(__DIR__) . '/app/backup.php';

require_super();

$type = (string)($_GET['type'] ?? 'csv');
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';

if ($type === 'csv') {
    admin_audit('export_csv', 'status=' . ($status ?: 'all'));
    $csv = registrants_csv($status ? ['status' => $status] : []);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrants-' . ($status ?: 'all') . '-' . date('Ymd-Hi') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

http_response_code(400);
exit('bad type');
