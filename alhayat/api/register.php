<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

try {
    enforce_rate_limit('registration', 8, 600);
    $data = request_data();
    verify_csrf(clean_text($data['csrf_token'] ?? '', 128));
    if (clean_text($data['website'] ?? '', 200) !== '') {
        json_response(['message' => 'تم استلام الطلب.'], 201);
    }
    $fullName = clean_text($data['fullName'] ?? '', 100);
    $email = mb_strtolower(clean_text($data['email'] ?? '', 160), 'UTF-8');
    $phone = clean_text($data['phone'] ?? '', 40);
    $organization = clean_text($data['organization'] ?? '', 140);
    $interest = clean_text($data['interestType'] ?? '', 40);
    $message = clean_text($data['message'] ?? '', 1500);
    $consent = ($data['consent'] ?? '') === 'yes' || ($data['consent'] ?? false) === true;
    $allowed = ['partnership', 'distribution', 'regulatory', 'general'];
    if ($fullName === '' || $organization === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($interest, $allowed, true) || !$consent) {
        throw new RuntimeException('يرجى إكمال جميع الحقول المطلوبة بصورة صحيحة.');
    }
    $id = uuid_v4();
    $statement = db()->prepare('INSERT INTO registrations(id, full_name, email, phone, organization, interest_type, message, consent_at) VALUES(:id, :full_name, :email, :phone, :organization, :interest, :message, :consent_at)');
    $statement->execute(['id' => $id, 'full_name' => $fullName, 'email' => $email, 'phone' => $phone !== '' ? $phone : null, 'organization' => $organization, 'interest' => $interest, 'message' => $message, 'consent_at' => gmdate('Y-m-d H:i:s')]);
    json_response(['id' => $id, 'message' => 'تم حفظ طلبكم وسيتواصل معكم فريقنا.'], 201);
} catch (RuntimeException $error) {
    json_response(['error' => $error->getMessage()], 400);
} catch (Throwable) {
    json_response(['error' => 'الخدمة غير متاحة مؤقتاً. حاول لاحقاً.'], 503);
}
