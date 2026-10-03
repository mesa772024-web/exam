<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

function chat_uploaded_files(array $input): array
{
    if (!$input) return [];
    if (!is_array($input['name'] ?? null)) return !empty($input['name']) ? [$input] : [];
    $files=[];
    foreach($input['name'] as $index=>$name)if($name!=='')$files[]=['name'=>$name,'type'=>$input['type'][$index]??'','tmp_name'=>$input['tmp_name'][$index]??'','error'=>$input['error'][$index]??UPLOAD_ERR_NO_FILE,'size'=>$input['size'][$index]??0];
    return $files;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
$data = request_data();
try {
    verify_csrf($data['csrf_token'] ?? null);
    $action = clean_text($data['action'] ?? '', 30);
    if ($action === 'start') {
        enforce_rate_limit('chat_start', 5, 3600);
        $name = clean_text($data['full_name'] ?? '', 100);
        $email = clean_email($data['email'] ?? '');
        $phone = clean_text($data['phone'] ?? '', 40);
        $company = clean_text($data['company'] ?? '', 140);
        $details = clean_multiline($data['details'] ?? '', 2000);
        if (mb_strlen($name) < 2 || $email === '' || mb_strlen($details) < 3) throw new RuntimeException('يرجى إدخال الاسم والبريد وتفاصيل الطلب بشكل صحيح.');
        $visitorId = current_visitor_id() ?: uuid_v4();
        $pdo = db();
        $pdo->beginTransaction();
        $statement = $pdo->prepare('INSERT INTO visitors(id, full_name, email, phone, company, details) VALUES(:id, :name, :email, :phone, :company, :details) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email), phone = VALUES(phone), company = VALUES(company), details = VALUES(details), updated_at = CURRENT_TIMESTAMP');
        $statement->execute(['id' => $visitorId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'company' => $company, 'details' => $details]);
        $conversationId = uuid_v4();
        $pdo->prepare('INSERT INTO conversations(id, visitor_id, subject) VALUES(:id, :visitor, :subject)')->execute(['id' => $conversationId, 'visitor' => $visitorId, 'subject' => mb_substr($details, 0, 120)]);
        $pdo->prepare("INSERT INTO conversation_messages(id, conversation_id, sender_type, sender_id, body, message_type) VALUES(:id, :conversation, 'visitor', :visitor, :body, 'text')")->execute(['id' => uuid_v4(), 'conversation' => $conversationId, 'visitor' => $visitorId, 'body' => $details]);
        $pdo->commit();
        issue_visitor_cookie($visitorId);
        json_response(['ok' => true, 'message' => 'تم إنشاء المحادثة.', 'reload' => true]);
    }

    $visitorId = current_visitor_id();
    if (!$visitorId) throw new RuntimeException('انتهت هوية المحادثة. ابدأ حساباً سريعاً جديداً.');
    $conversationId = clean_text($data['conversation_id'] ?? '', 50);
    $statement = db()->prepare('SELECT * FROM conversations WHERE id = :id AND visitor_id = :visitor');
    $statement->execute(['id' => $conversationId, 'visitor' => $visitorId]);
    $conversation = $statement->fetch();
    if (!$conversation) throw new RuntimeException('المحادثة غير موجودة.');

    if ($action === 'send') {
        enforce_rate_limit('chat_send', 30, 300, $visitorId);
        if ($conversation['status'] === 'closed') throw new RuntimeException('هذه المحادثة مغلقة.');
        $body = clean_multiline($data['body'] ?? '', 4000);
        $files=chat_uploaded_files($_FILES['attachments']??[]);
        if(!empty($_FILES['attachment']['name']))$files=array_merge($files,chat_uploaded_files($_FILES['attachment']));
        if(count($files)>8)throw new RuntimeException('يمكن إرسال ثمانية ملفات أو صور في المرة الواحدة.');
        $storedFiles=[];
        foreach($files as $file){$requested=clean_text($data['purpose']??'file',10);$detected=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name'])?:'';$purpose=$requested==='audio'?'audio':(str_starts_with($detected,'image/')?'image':'file');if($purpose==='audio'&&setting('chat_audio_enabled','1')!=='1')throw new RuntimeException('الرسائل الصوتية معطلة حالياً.');if($purpose==='file'&&setting('chat_files_enabled','1')!=='1')throw new RuntimeException('رفع الملفات معطل حالياً.');$storedFiles[]=['purpose'=>$purpose,'file'=>store_private_upload($file,$purpose)];}
        if ($body === '' && !$storedFiles) throw new RuntimeException('اكتب رسالة أو اختر ملفاً.');
        $pdo = db();
        $pdo->beginTransaction();
        $messageInsert=$pdo->prepare("INSERT INTO conversation_messages(id,conversation_id,sender_type,sender_id,body,message_type)VALUES(:id,:conversation,'visitor',:visitor,:body,:type)");
        $attachmentInsert=$pdo->prepare('INSERT INTO attachments(id,message_id,conversation_id,visitor_id,file_path,original_name,mime_type,file_size,sha256,width,height)VALUES(:id,:message,:conversation,:visitor,:path,:name,:mime,:size,:sha,:width,:height)');
        if(!$storedFiles){$messageInsert->execute(['id'=>uuid_v4(),'conversation'=>$conversationId,'visitor'=>$visitorId,'body'=>$body,'type'=>'text']);}
        else{foreach($storedFiles as $index=>$item){$messageId=uuid_v4();$stored=$item['file'];$messageInsert->execute(['id'=>$messageId,'conversation'=>$conversationId,'visitor'=>$visitorId,'body'=>$index===0?$body:'','type'=>$item['purpose']]);$attachmentInsert->execute(['id'=>uuid_v4(),'message'=>$messageId,'conversation'=>$conversationId,'visitor'=>$visitorId,'path'=>$stored['path'],'name'=>$stored['original_name'],'mime'=>$stored['mime'],'size'=>$stored['size'],'sha'=>$stored['sha256'],'width'=>$stored['width'],'height'=>$stored['height']]);}}
        $pdo->prepare('UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $conversationId]);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'تم إرسال الرسالة.', 'reload' => true]);
    }

    if ($action === 'book') {
        enforce_rate_limit('booking', 6, 3600, $visitorId);
        if (setting('booking_enabled', '1') !== '1') throw new RuntimeException('الحجوزات معطلة حالياً.');
        $title = clean_text($data['title'] ?? '', 160);
        $location = clean_text($data['location'] ?? '', 300);
        $notes = clean_multiline($data['notes'] ?? '', 1500);
        $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', clean_text($data['starts_at'] ?? '', 30));
        $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', clean_text($data['ends_at'] ?? '', 30));
        if ($title === '' || !$start || !$end || $end <= $start || $end->getTimestamp() - $start->getTimestamp() > 28800 || $start->getTimestamp() < time() - 300) throw new RuntimeException('تفاصيل الموعد أو مدته غير صحيحة.');
        $bookingId = uuid_v4();
        $messageId = uuid_v4();
        $stored = !empty($_FILES['meeting_image']['name']) ? store_private_upload($_FILES['meeting_image'], 'image') : null;
        $attachmentId = $stored ? uuid_v4() : null;
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO bookings(id, conversation_id, visitor_id, title, starts_at, ends_at, location, notes, image_attachment_id) VALUES(:id, :conversation, :visitor, :title, :starts, :ends, :location, :notes, :image)')->execute(['id' => $bookingId, 'conversation' => $conversationId, 'visitor' => $visitorId, 'title' => $title, 'starts' => $start->format('Y-m-d H:i:s'), 'ends' => $end->format('Y-m-d H:i:s'), 'location' => $location, 'notes' => $notes, 'image' => $attachmentId]);
        $body = 'Booking: ' . $title . ' — ' . $start->format('Y-m-d H:i');
        $pdo->prepare("INSERT INTO conversation_messages(id, conversation_id, sender_type, sender_id, body, message_type, booking_id) VALUES(:id, :conversation, 'visitor', :visitor, :body, 'booking', :booking)")->execute(['id' => $messageId, 'conversation' => $conversationId, 'visitor' => $visitorId, 'body' => $body, 'booking' => $bookingId]);
        if ($stored && $attachmentId) $pdo->prepare('INSERT INTO attachments(id, message_id, conversation_id, visitor_id, file_path, original_name, mime_type, file_size, sha256, width, height) VALUES(:id, :message, :conversation, :visitor, :path, :name, :mime, :size, :sha, :width, :height)')->execute(['id' => $attachmentId, 'message' => $messageId, 'conversation' => $conversationId, 'visitor' => $visitorId, 'path' => $stored['path'], 'name' => $stored['original_name'], 'mime' => $stored['mime'], 'size' => $stored['size'], 'sha' => $stored['sha256'], 'width' => $stored['width'], 'height' => $stored['height']]);
        $pdo->prepare('UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $conversationId]);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'تم إرسال طلب الحجز.', 'reload' => true]);
    }
    throw new RuntimeException('الإجراء غير معروف.');
} catch (Throwable $error) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['ok' => false, 'message' => $error->getMessage()], 422);
}
