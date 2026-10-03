<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

try {
    enforce_rate_limit('contact', 8, 600);
    $data = request_data();
    verify_csrf(clean_text($data['csrf_token'] ?? '', 128));
    if (clean_text($data['website'] ?? '', 200) !== '') {
        json_response(['message' => 'تم استلام الرسالة.'], 201);
    }
    $fullName = clean_text($data['fullName'] ?? '', 100);
    $email = mb_strtolower(clean_text($data['email'] ?? '', 160), 'UTF-8');
    $phone = clean_text($data['phone'] ?? '', 40);
    $subject = clean_text($data['subject'] ?? '', 120) ?: 'General inquiry';
    $message = clean_text($data['message'] ?? '', 3000);
    if ($fullName === '' || $message === '') {
        throw new RuntimeException('يرجى إدخال الاسم ونص الرسالة.');
    }
    // Email is optional — validate only when provided.
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('صيغة البريد الإلكتروني غير صحيحة، أو اتركه فارغاً.');
    }
    $id = uuid_v4();
    $statement = db()->prepare('INSERT INTO contact_messages(id, full_name, email, phone, subject, message) VALUES(:id, :full_name, :email, :phone, :subject, :message)');
    $statement->execute(['id' => $id, 'full_name' => $fullName, 'email' => $email, 'phone' => $phone !== '' ? $phone : null, 'subject' => $subject, 'message' => $message]);
    json_response(['id' => $id, 'message' => 'تم حفظ رسالتكم.'], 201);
} catch (RuntimeException $error) {
    json_response(['error' => $error->getMessage()], 400);
} catch (Throwable) {
    json_response(['error' => 'الخدمة غير متاحة مؤقتاً. حاول لاحقاً.'], 503);
}
