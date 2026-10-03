<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin('reviewer');
$period = (int) ($_GET['period'] ?? 30);
if (!in_array($period, [7, 30, 90, 365], true)) $period = 30;
$format = ($_GET['format'] ?? 'csv') === 'pdf' ? 'pdf' : 'csv';
$from = gmdate('Y-m-d', strtotime('-' . ($period - 1) . ' days'));
$pdo = db();
$summaryStatement = $pdo->prepare("SELECT
 (SELECT COUNT(*) FROM visit_events WHERE created_at >= :from_visits) AS visits,
 (SELECT COUNT(DISTINCT session_hash) FROM visit_events WHERE created_at >= :from_unique) AS unique_visitors,
 (SELECT COUNT(*) FROM conversations WHERE created_at >= :from_conversations) AS conversations,
 (SELECT COUNT(*) FROM conversation_messages WHERE created_at >= :from_messages) AS messages,
 (SELECT COUNT(*) FROM conversation_messages WHERE created_at >= :from_replies AND sender_type = 'admin') AS replies,
 (SELECT COUNT(*) FROM bookings WHERE created_at >= :from_bookings) AS bookings");
$fromTimestamp = $from . ' 00:00:00';
$summaryStatement->execute(['from_visits' => $fromTimestamp, 'from_unique' => $fromTimestamp, 'from_conversations' => $fromTimestamp, 'from_messages' => $fromTimestamp, 'from_replies' => $fromTimestamp, 'from_bookings' => $fromTimestamp]);
$summary = $summaryStatement->fetch();
$daily = $pdo->prepare("SELECT d.day,
 (SELECT COUNT(*) FROM visit_events WHERE date(created_at)=d.day) visits,
 (SELECT COUNT(DISTINCT session_hash) FROM visit_events WHERE date(created_at)=d.day) unique_visitors,
 (SELECT COUNT(*) FROM conversations WHERE date(created_at)=d.day) conversations,
 (SELECT COUNT(*) FROM conversation_messages WHERE date(created_at)=d.day) messages,
 (SELECT COUNT(*) FROM bookings WHERE date(created_at)=d.day) bookings
 FROM (SELECT date(created_at) day FROM visit_events WHERE created_at >= :from_daily_visits UNION SELECT date(created_at) FROM conversations WHERE created_at >= :from_daily_conversations UNION SELECT date(created_at) FROM bookings WHERE created_at >= :from_daily_bookings) d ORDER BY d.day");
$daily->execute(['from_daily_visits' => $fromTimestamp, 'from_daily_conversations' => $fromTimestamp, 'from_daily_bookings' => $fromTimestamp]);
$rows = $daily->fetchAll();
write_audit((int) $admin['id'], $admin['display_name'], 'report.export', 'report', $format, ['period' => $period]);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="al-hayat-report-' . gmdate('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $stream = fopen('php://output', 'wb');
    fputcsv($stream, ['الفترة', $from, gmdate('Y-m-d')]);
    fputcsv($stream, ['إجمالي الزيارات', $summary['visits'], 'الزوار المميزون', $summary['unique_visitors'], 'المحادثات', $summary['conversations'], 'الرسائل', $summary['messages'], 'ردود الفريق', $summary['replies'], 'الحجوزات', $summary['bookings']]);
    fputcsv($stream, []);
    fputcsv($stream, ['التاريخ', 'الزيارات', 'الزوار المميزون', 'المحادثات', 'الرسائل', 'الحجوزات']);
    foreach ($rows as $row) fputcsv($stream, [$row['day'], $row['visits'], $row['unique_visitors'], $row['conversations'], $row['messages'], $row['bookings']]);
    fclose($stream);
    exit;
}

$lines = [
    'AL HAYAT SCIENTIFIC OFFICE - EXECUTIVE REPORT',
    'Period: ' . $from . ' to ' . gmdate('Y-m-d'),
    '',
    'Page views: ' . $summary['visits'],
    'Unique visitors: ' . $summary['unique_visitors'],
    'New conversations: ' . $summary['conversations'],
    'Messages: ' . $summary['messages'],
    'Team replies: ' . $summary['replies'],
    'Bookings: ' . $summary['bookings'],
    '',
    'Generated: ' . gmdate('Y-m-d H:i') . ' UTC',
];
$pdf = admin_simple_pdf($lines);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: attachment; filename="al-hayat-report-' . gmdate('Ymd') . '.pdf"');
echo $pdf;

function admin_simple_pdf(array $lines): string
{
    $escape = static fn(string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = "BT\n/F1 18 Tf\n50 790 Td\n";
    foreach ($lines as $index => $line) {
        if ($index > 0) $content .= "0 -28 Td\n";
        $content .= '(' . $escape($line) . ") Tj\n";
    }
    $content .= "ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}
