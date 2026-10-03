<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin('viewer');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (request_expects_json()) json_response(['ok' => false, 'message' => 'طريقة الطلب غير مسموحة.'], 405);
    http_response_code(405);
    exit;
}

function admin_result(string $path, string $message = 'تم الحفظ.'): never
{
    if (request_expects_json()) json_response(['ok' => true, 'message' => $message]);
    [$path, $hash] = array_pad(explode('#', $path, 2), 2, '');
    header('Location: ' . admin_url($path . (str_contains($path, '?') ? '&' : '?') . 'ok=1' . ($hash !== '' ? '#' . $hash : '')));
    exit;
}

function admin_failure(Throwable $error, string $fallback): never
{
    if (request_expects_json()) json_response(['ok' => false, 'message' => $error->getMessage()], 422);
    [$fallback, $hash] = array_pad(explode('#', $fallback, 2), 2, '');
    header('Location: ' . admin_url($fallback . (str_contains($fallback, '?') ? '&' : '?') . 'error=' . rawurlencode($error->getMessage()) . ($hash !== '' ? '#' . $hash : '')));
    exit;
}

function normalize_uploaded_files(array $input): array
{
    if (!is_array($input['name'] ?? null)) return $input ? [$input] : [];
    $files=[];
    foreach($input['name'] as $index=>$name)$files[]=['name'=>$name,'type'=>$input['type'][$index]??'','tmp_name'=>$input['tmp_name'][$index]??'','error'=>$input['error'][$index]??UPLOAD_ERR_NO_FILE,'size'=>$input['size'][$index]??0];
    return $files;
}

$action = clean_text($_POST['action'] ?? '', 50);
$fallback = clean_text($_POST['fallback'] ?? 'index.php', 200);
try {
    verify_csrf($_POST['csrf_token'] ?? null);
    $pdo = db();

    if ($action === 'upload_cms_media') {
        if(!admin_can($admin,'editor'))throw new RuntimeException('تحتاج صلاحية محرر.');
        $files=normalize_uploaded_files($_FILES['media_files']??[]);if(!$files||count($files)>12)throw new RuntimeException('اختر من صورة واحدة إلى 12 صورة.');
        $ids=[];foreach($files as $file){$stored=store_public_image($file,'cms');$id=uuid_v4();$pdo->prepare('INSERT INTO media_library(id,file_path,original_name,title,mime_type,file_size,width,height,sha256,uploaded_by)VALUES(:id,:path,:name,:title,:mime,:size,:width,:height,:sha,:admin)')->execute(['id'=>$id,'path'=>$stored['path'],'name'=>$stored['original_name'],'title'=>clean_text($_POST['media_title']??'',160),'mime'=>$stored['mime'],'size'=>$stored['size'],'width'=>$stored['width'],'height'=>$stored['height'],'sha'=>$stored['sha256'],'admin'=>$admin['id']]);$ids[]=$id;}
        write_audit((int)$admin['id'],$admin['display_name'],'media.upload','media_library',implode(',',$ids),['count'=>count($ids)]);admin_result('content.php'.(!empty($_POST['page_id'])?'?id='.(int)$_POST['page_id']:''),'تم رفع الصور إلى المكتبة.');
    }

    if ($action === 'save_page') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $id = (int) ($_POST['page_id'] ?? 0);
        $slug = clean_slug($_POST['slug'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $template = in_array($_POST['template'] ?? '', ['editorial','clinical','cinematic'], true) ? $_POST['template'] : 'editorial';
        $isBlog = !empty($_POST['is_blog']) ? 1 : 0;
        $titleAr = clean_text($_POST['title_ar'] ?? '', 180);
        $titleEn = clean_text($_POST['title_en'] ?? '', 180);
        if ($slug === '' || $titleAr === '' || $titleEn === '') throw new RuntimeException('العناوين والرابط المختصر مطلوبة.');
        if (in_array($slug, ['index','admin','api','install','messages','media','page','blog',ADMIN_SLUG], true)) throw new RuntimeException('الرابط المختصر محجوز.');
        $pdo->beginTransaction();
        if ($id > 0) {
            $statement = $pdo->prepare("UPDATE pages SET slug=:slug, template=:template, status=:status_value, is_blog=:blog, published_at=CASE WHEN :status_publish='published' THEN COALESCE(published_at,CURRENT_TIMESTAMP) ELSE published_at END, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $statement->execute(['slug'=>$slug,'template'=>$template,'status_value'=>$status,'status_publish'=>$status,'blog'=>$isBlog,'id'=>$id]);
            if ($statement->rowCount() < 1) {
                $exists = $pdo->prepare('SELECT 1 FROM pages WHERE id=:id'); $exists->execute(['id'=>$id]); if (!$exists->fetchColumn()) throw new RuntimeException('الصفحة غير موجودة.');
            }
        } else {
            $statement = $pdo->prepare("INSERT INTO pages(slug,template,status,is_blog,created_by,published_at) VALUES(:slug,:template,:status_value,:blog,:admin,CASE WHEN :status_publish='published' THEN CURRENT_TIMESTAMP ELSE NULL END)");
            $statement->execute(['slug'=>$slug,'template'=>$template,'status_value'=>$status,'status_publish'=>$status,'blog'=>$isBlog,'admin'=>$admin['id']]);
            $id = (int) $pdo->lastInsertId();
        }
        $translation = $pdo->prepare('INSERT INTO page_translations(page_id,locale,title,excerpt,seo_title,seo_description) VALUES(:page,:locale,:title,:excerpt,:seo_title,:seo_description) ON DUPLICATE KEY UPDATE title=VALUES(title),excerpt=VALUES(excerpt),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description)');
        foreach (['ar','en'] as $locale) $translation->execute(['page'=>$id,'locale'=>$locale,'title'=>clean_text($_POST['title_'.$locale] ?? '',180),'excerpt'=>clean_multiline($_POST['excerpt_'.$locale] ?? '',600),'seo_title'=>clean_text($_POST['seo_title_'.$locale] ?? '',180),'seo_description'=>clean_text($_POST['seo_description_'.$locale] ?? '',320)]);
        $pdo->commit();
        write_audit((int)$admin['id'],$admin['display_name'],'cms.page.save','page',(string)$id,['status'=>$status]);
        admin_result('content.php?id='.$id);
    }

    if ($action === 'archive_page') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $id=(int)($_POST['page_id']??0); $pdo->prepare("UPDATE pages SET status='archived',updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>$id]);
        write_audit((int)$admin['id'],$admin['display_name'],'cms.page.archive','page',(string)$id); admin_result('content.php');
    }

    if ($action === 'save_block') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $blockId=(int)($_POST['block_id']??0); $pageId=(int)($_POST['page_id']??0);
        $type=in_array($_POST['block_type']??'',CMS_BLOCK_TYPES,true)?$_POST['block_type']:'paragraph';
        if ($type==='html_element'&&!admin_can($admin,'super_admin')) throw new RuntimeException('عناصر HTML/CSS/JS متاحة للمدير الأعلى فقط.');
        $pageCheck=$pdo->prepare('SELECT 1 FROM pages WHERE id=:id');$pageCheck->execute(['id'=>$pageId]);if(!$pageCheck->fetchColumn())throw new RuntimeException('الصفحة غير موجودة.');
        $tone=in_array($_POST['tone']??'', ['light','soft','dark','green'],true)?$_POST['tone']:'light';
        $contentByLocale=[];
        foreach(['ar','en'] as $locale){
            $title=clean_text($_POST['title_'.$locale]??'',240);$body=clean_multiline($_POST['body_'.$locale]??'',20000);$eyebrow=clean_text($_POST['eyebrow_'.$locale]??'',100);
            $content=['eyebrow'=>$eyebrow,'title'=>$title,'body'=>$body];
            if($type==='rich_text')$content=['html'=>mb_substr((string)($_POST['rich_'.$locale]??$body),0,50000)];
            elseif($type==='quote')$content=['quote'=>$body,'author'=>$title];
            elseif($type==='image')$content=['src'=>clean_text($_POST['media_urls']??'',500),'alt'=>$title,'caption'=>$body];
            elseif(in_array($type,['gallery','slideshow'],true)){$urls=preg_split('/\R/u',(string)($_POST['media_urls']??''))?:[];$content=['images'=>array_values(array_map(static fn($url)=>['src'=>clean_text($url,500),'alt'=>$title,'caption'=>''],array_filter(array_slice($urls,0,20),static fn($v)=>trim($v)!=='')))];}
            elseif($type==='flowchart'){$nodes=json_decode((string)($_POST['nodes_json_'.$locale]??'[]'),true);$edges=json_decode((string)($_POST['edges_json']??'[]'),true);$content=['nodes'=>is_array($nodes)?array_slice($nodes,0,40):[],'edges'=>is_array($edges)?array_slice($edges,0,80):[]];}
            elseif($type==='html_element')$content=['title'=>$title,'html'=>mb_substr((string)($_POST['custom_html']??''),0,100000),'css'=>mb_substr((string)($_POST['custom_css']??''),0,50000),'js'=>mb_substr((string)($_POST['custom_js']??''),0,50000)];
            elseif(in_array($type,['hero','cta'],true)){$content['button_label']=clean_text($_POST['button_label_'.$locale]??'',100);$content['button_url']=cms_safe_url(clean_text($_POST['button_url']??'#',500));}
            $contentByLocale[$locale]=$content;
        }
        $pdo->beginTransaction();
        if($blockId>0){$statement=$pdo->prepare('UPDATE content_blocks SET block_type=:type,settings_json=:settings,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND page_id=:page');$statement->execute(['type'=>$type,'settings'=>json_encode(['tone'=>$tone]),'id'=>$blockId,'page'=>$pageId]);}
        else{$orderStatement=$pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM content_blocks WHERE page_id=:page');$orderStatement->execute(['page'=>$pageId]);$order=(int)$orderStatement->fetchColumn();$pdo->prepare('INSERT INTO content_blocks(page_id,block_type,sort_order,settings_json)VALUES(:page,:type,:sort,:settings)')->execute(['page'=>$pageId,'type'=>$type,'sort'=>$order,'settings'=>json_encode(['tone'=>$tone])]);$blockId=(int)$pdo->lastInsertId();}
        $translation=$pdo->prepare('INSERT INTO content_block_translations(block_id,locale,content_json)VALUES(:block,:locale,:content)ON DUPLICATE KEY UPDATE content_json=VALUES(content_json)');foreach($contentByLocale as $locale=>$content)$translation->execute(['block'=>$blockId,'locale'=>$locale,'content'=>json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();write_audit((int)$admin['id'],$admin['display_name'],'cms.block.save','content_block',(string)$blockId,['type'=>$type]);admin_result('content.php?id='.$pageId.'#blocks');
    }

    if ($action === 'delete_block') {
        if(!admin_can($admin,'editor'))throw new RuntimeException('تحتاج صلاحية محرر.');$id=(int)($_POST['block_id']??0);$page=(int)($_POST['page_id']??0);$pdo->prepare('DELETE FROM content_blocks WHERE id=:id AND page_id=:page')->execute(['id'=>$id,'page'=>$page]);write_audit((int)$admin['id'],$admin['display_name'],'cms.block.delete','content_block',(string)$id);admin_result('content.php?id='.$page.'#blocks');
    }

    if ($action === 'move_block') {
        if(!admin_can($admin,'editor'))throw new RuntimeException('تحتاج صلاحية محرر.');$id=(int)($_POST['block_id']??0);$page=(int)($_POST['page_id']??0);$direction=(($_POST['direction']??$_GET['direction']??'up')==='down')?'down':'up';$current=$pdo->prepare('SELECT id,sort_order FROM content_blocks WHERE id=:id AND page_id=:page');$current->execute(['id'=>$id,'page'=>$page]);$a=$current->fetch();if(!$a)throw new RuntimeException('العنصر غير موجود.');$operator=$direction==='up'?'<': '>';$order=$direction==='up'?'DESC':'ASC';$other=$pdo->prepare("SELECT id,sort_order FROM content_blocks WHERE page_id=:page AND sort_order $operator :sort ORDER BY sort_order $order LIMIT 1");$other->execute(['page'=>$page,'sort'=>$a['sort_order']]);$b=$other->fetch();if($b){$pdo->beginTransaction();$pdo->prepare('UPDATE content_blocks SET sort_order=:sort WHERE id=:id')->execute(['sort'=>$b['sort_order'],'id'=>$a['id']]);$pdo->prepare('UPDATE content_blocks SET sort_order=:sort WHERE id=:id')->execute(['sort'=>$a['sort_order'],'id'=>$b['id']]);$pdo->commit();}admin_result('content.php?id='.$page.'#blocks');
    }

    if ($action === 'save_visual') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $scope = clean_slug($_POST['page_scope'] ?? 'home') ?: 'home';
        $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($_POST['element_key'] ?? '')) ?? '';
        $locale = ($_POST['locale'] ?? 'ar') === 'en' ? 'en' : 'ar';
        $value = clean_multiline($_POST['content_value'] ?? '', 5000);
        $tag = strtolower(preg_replace('/[^a-z0-9-]/i', '', (string) ($_POST['element_tag'] ?? 'div')) ?? 'div');
        if ($key === '') throw new RuntimeException('اختر عنصراً من المعاينة أولاً.');

        if (preg_match('/^page-title-(\d+)$/', $key, $match)) {
            $statement = $pdo->prepare('UPDATE page_translations SET title=:value WHERE page_id=:id AND locale=:locale');
            $statement->execute(['value' => $value, 'id' => (int) $match[1], 'locale' => $locale]);
        } elseif (preg_match('/^block-(\d+)-(title|body)$/', $key, $match)) {
            $statement = $pdo->prepare('SELECT content_json FROM content_block_translations WHERE block_id=:id AND locale=:locale');
            $statement->execute(['id' => (int) $match[1], 'locale' => $locale]);
            $content = cms_decode_json($statement->fetchColumn());
            $field = $match[2] === 'title' ? (array_key_exists('title', $content) ? 'title' : (array_key_exists('author', $content) ? 'author' : 'alt')) : (array_key_exists('body', $content) ? 'body' : (array_key_exists('quote', $content) ? 'quote' : 'caption'));
            $content[$field] = $value;
            $pdo->prepare('INSERT INTO content_block_translations(block_id,locale,content_json)VALUES(:id,:locale,:content)ON DUPLICATE KEY UPDATE content_json=VALUES(content_json)')->execute(['id' => (int) $match[1], 'locale' => $locale, 'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }

        if (str_starts_with($key, 'auto-')) {
            $payload = ['version' => 2, 'tag' => $tag, 'text' => $value];
            $linkUrl = visual_safe_url(clean_text($_POST['link_url'] ?? '', 500));
            $mediaUrl = visual_safe_url(clean_text($_POST['media_url'] ?? '', 500), true);
            $altText = clean_text($_POST['alt_text'] ?? '', 300);
            if ($linkUrl !== '') $payload['href'] = $linkUrl;
            if ($mediaUrl !== '') $payload['src'] = $mediaUrl;
            if ($altText !== '') $payload['alt'] = $altText;
            save_editable_content($key, $locale, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $admin['id']);
        }

        $styles = [
            'color' => clean_text($_POST['color'] ?? '', 20),
            'backgroundColor' => !empty($_POST['use_background']) ? clean_text($_POST['background_color'] ?? '', 20) : '',
            'fontSize' => ($_POST['font_size'] ?? '') !== '' ? (int) $_POST['font_size'] . 'px' : '',
            'fontWeight' => clean_text($_POST['font_weight'] ?? '', 10),
            'lineHeight' => clean_text($_POST['line_height'] ?? '', 10),
            'width' => clean_text($_POST['width'] ?? '', 20),
            'maxWidth' => clean_text($_POST['max_width'] ?? '', 20),
            'height' => clean_text($_POST['height'] ?? '', 20),
            'padding' => ($_POST['padding'] ?? '') !== '' ? (int) $_POST['padding'] . 'px' : '',
            'marginTop' => ($_POST['margin_top'] ?? '') !== '' ? (int) $_POST['margin_top'] . 'px' : '',
            'marginBottom' => ($_POST['margin_bottom'] ?? '') !== '' ? (int) $_POST['margin_bottom'] . 'px' : '',
            'textAlign' => clean_text($_POST['text_align'] ?? '', 20),
            'borderRadius' => ($_POST['border_radius'] ?? '') !== '' ? (int) $_POST['border_radius'] . 'px' : '',
            'borderWidth' => ($_POST['border_width'] ?? '') !== '' ? (int) $_POST['border_width'] . 'px' : '',
            'borderColor' => clean_text($_POST['border_color'] ?? '', 20),
            'borderStyle' => ($_POST['border_width'] ?? '') !== '' && (int) $_POST['border_width'] > 0 ? 'solid' : '',
            'opacity' => clean_text($_POST['opacity'] ?? '', 10),
            'objectFit' => clean_text($_POST['object_fit'] ?? '', 20),
            'display' => ($_POST['display'] ?? '') === 'initial' ? '' : clean_text($_POST['display'] ?? '', 20),
            'translateX' => ($_POST['translate_x'] ?? '') !== '' ? (int) $_POST['translate_x'] . 'px' : '',
            'translateY' => ($_POST['translate_y'] ?? '') !== '' ? (int) $_POST['translate_y'] . 'px' : '',
        ];
        save_element_style($scope, $key, $styles, (int) $admin['id']);
        write_audit((int) $admin['id'], $admin['display_name'], 'visual.element.save', 'element', $key, ['scope' => $scope, 'locale' => $locale, 'tag' => $tag]);
        admin_result('editor.php?scope=' . $scope . '&lang=' . $locale, 'تم تحديث العنصر.');
    }

    if ($action === 'save_appearance') {
        if(!admin_can($admin,'admin'))throw new RuntimeException('تحتاج صلاحية مدير.');foreach(['primaryColor','accentColor','surfaceColor'] as $key){$value=clean_text($_POST[$key]??'',20);if(!preg_match('/^#[0-9a-f]{6}$/i',$value))throw new RuntimeException('قيمة اللون غير صحيحة.');save_setting($key,$value,(int)$admin['id']);}foreach(['siteNameAr','siteNameEn'] as $key)save_setting($key,clean_text($_POST[$key]??'',100),(int)$admin['id']);$font=in_array($_POST['fontFamily']??'',['Hayat Sans','IBM Plex Sans','System'],true)?$_POST['fontFamily']:'Hayat Sans';save_setting('fontFamily',$font,(int)$admin['id']);$siteLanguages=in_array($_POST['siteLanguages']??'',['both','en'],true)?$_POST['siteLanguages']:'both';save_setting('siteLanguages',$siteLanguages,(int)$admin['id']);foreach(['chat_audio_enabled','chat_files_enabled','booking_enabled'] as $key)save_setting($key,!empty($_POST[$key])?'1':'0',(int)$admin['id']);write_audit((int)$admin['id'],$admin['display_name'],'settings.appearance','settings','site',['languages'=>$siteLanguages]);admin_result('appearance.php');
    }

    if ($action === 'upload_logo') {
        if(!admin_can($admin,'admin'))throw new RuntimeException('تحتاج صلاحية مدير.');$file=$_FILES['logo']??[];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new RuntimeException('لم يكتمل رفع الشعار.');if((int)$file['size']>4_000_000)throw new RuntimeException('حجم الشعار أكبر من 4MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];$size=@getimagesize($file['tmp_name']);if(!isset($allowed[$mime])||!is_array($size)||($size['mime']??'')!==$mime)throw new RuntimeException('ملف الشعار ليس صورة صالحة.');$name='brand-'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];$directory=APP_ROOT.DIRECTORY_SEPARATOR.'uploads';if(!is_dir($directory))mkdir($directory,0755,true);if(!move_uploaded_file($file['tmp_name'],$directory.DIRECTORY_SEPARATOR.$name))throw new RuntimeException('تعذر حفظ الشعار.');save_setting('logoPath','uploads/'.$name,(int)$admin['id']);write_audit((int)$admin['id'],$admin['display_name'],'brand.logo.upload','media',$name,['mime'=>$mime]);admin_result('appearance.php');
    }

    if ($action === 'conversation_reply') {
        if(!admin_can($admin,'reviewer'))throw new RuntimeException('تحتاج صلاحية مراجع.');$conversationId=clean_text($_POST['conversation_id']??'',50);$body=clean_multiline($_POST['body']??'',4000);$conv=$pdo->prepare('SELECT visitor_id FROM conversations WHERE id=:id');$conv->execute(['id'=>$conversationId]);$visitorId=$conv->fetchColumn();if(!$visitorId)throw new RuntimeException('المحادثة غير موجودة.');$stored=null;$type='text';if(!empty($_FILES['attachment']['name'])){$purpose=clean_text($_POST['purpose']??'file',10);if(!in_array($purpose,['image','audio','file'],true))$purpose='file';$stored=store_private_upload($_FILES['attachment'],$purpose);$type=$purpose;}if($body===''&&!$stored)throw new RuntimeException('اكتب الرد أو اختر ملفاً.');$messageId=uuid_v4();$pdo->beginTransaction();$pdo->prepare("INSERT INTO conversation_messages(id,conversation_id,sender_type,sender_id,body,message_type)VALUES(:id,:conversation,'admin',:admin,:body,:type)")->execute(['id'=>$messageId,'conversation'=>$conversationId,'admin'=>(string)$admin['id'],'body'=>$body,'type'=>$type]);if($stored)$pdo->prepare('INSERT INTO attachments(id,message_id,conversation_id,visitor_id,file_path,original_name,mime_type,file_size,sha256,width,height)VALUES(:id,:message,:conversation,:visitor,:path,:name,:mime,:size,:sha,:width,:height)')->execute(['id'=>uuid_v4(),'message'=>$messageId,'conversation'=>$conversationId,'visitor'=>$visitorId,'path'=>$stored['path'],'name'=>$stored['original_name'],'mime'=>$stored['mime'],'size'=>$stored['size'],'sha'=>$stored['sha256'],'width'=>$stored['width'],'height'=>$stored['height']]);$pdo->prepare("UPDATE conversations SET status='pending',assigned_admin_id=:admin,last_message_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['admin'=>$admin['id'],'id'=>$conversationId]);$pdo->commit();write_audit((int)$admin['id'],$admin['display_name'],'conversation.reply','conversation',$conversationId);admin_result('conversations.php?id='.$conversationId);
    }

    if ($action === 'conversation_status') {
        if(!admin_can($admin,'reviewer'))throw new RuntimeException('تحتاج صلاحية مراجع.');$id=clean_text($_POST['conversation_id']??'',50);$status=in_array($_POST['status']??'', ['open','pending','closed'],true)?$_POST['status']:'open';$pdo->prepare('UPDATE conversations SET status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);write_audit((int)$admin['id'],$admin['display_name'],'conversation.status','conversation',$id,['status'=>$status]);admin_result('conversations.php?id='.$id);
    }

    if ($action === 'booking_status') {
        if(!admin_can($admin,'reviewer'))throw new RuntimeException('تحتاج صلاحية مراجع.');$id=clean_text($_POST['booking_id']??'',50);$status=in_array($_POST['status']??'', ['requested','confirmed','completed','cancelled'],true)?$_POST['status']:'requested';$pdo->prepare('UPDATE bookings SET status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);write_audit((int)$admin['id'],$admin['display_name'],'booking.status','booking',$id,['status'=>$status]);admin_result('calendar.php?booking='.$id);
    }

    if ($action === 'create_admin') {
        if(!admin_can($admin,'super_admin'))throw new RuntimeException('متاح للمدير الأعلى فقط.');$username=mb_strtolower(clean_text($_POST['username']??'',40),'UTF-8');$display=clean_text($_POST['display_name']??'',100);$password=(string)($_POST['password']??'');$role=in_array($_POST['role']??'',array_keys(ROLE_LEVELS),true)?$_POST['role']:'viewer';if(!preg_match('/^[a-z0-9._-]{3,40}$/',$username)||$display===''||strlen($password)<8)throw new RuntimeException('بيانات المستخدم أو كلمة المرور غير مكتملة؛ الحد الأدنى 8 خانات.');$pdo->prepare('INSERT INTO admin_users(username,display_name,password_hash,role)VALUES(:username,:display,:password,:role)')->execute(['username'=>$username,'display'=>$display,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);$id=(string)$pdo->lastInsertId();write_audit((int)$admin['id'],$admin['display_name'],'admin.create','admin_user',$id,['role'=>$role]);admin_result('appearance.php#users');
    }

    if ($action === 'save_event') {
        if (!admin_can($admin, 'reviewer')) throw new RuntimeException('تحتاج صلاحية مراجع.');
        $title = clean_text($_POST['title'] ?? '', 255);
        $date = clean_text($_POST['event_date'] ?? '', 10);
        $desc = clean_multiline($_POST['description'] ?? '', 2000);
        $color = in_array($_POST['color'] ?? '', ['green', 'maroon', 'blue', 'amber'], true) ? $_POST['color'] : 'green';
        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('أدخل عنوان الفعالية وتاريخاً صحيحاً.');
        $pdo->prepare('INSERT INTO calendar_events(id, event_date, title, description, color, created_by) VALUES(:id, :date, :title, :desc, :color, :admin)')
            ->execute(['id' => uuid_v4(), 'date' => $date, 'title' => $title, 'desc' => $desc, 'color' => $color, 'admin' => (int) $admin['id']]);
        write_audit((int) $admin['id'], $admin['display_name'], 'event.create', 'calendar_event', $date, ['title' => $title]);
        admin_result('calendar.php?month=' . substr($date, 0, 7), 'تمت إضافة الفعالية.');
    }

    if ($action === 'delete_event') {
        if (!admin_can($admin, 'reviewer')) throw new RuntimeException('تحتاج صلاحية مراجع.');
        $id = clean_text($_POST['event_id'] ?? '', 50);
        $pdo->prepare('DELETE FROM calendar_events WHERE id = :id')->execute(['id' => $id]);
        write_audit((int) $admin['id'], $admin['display_name'], 'event.delete', 'calendar_event', $id);
        admin_result($fallback, 'تم حذف الفعالية.');
    }

    if ($action === 'speakup_status') {
        if (!admin_can($admin, 'reviewer')) throw new RuntimeException('تحتاج صلاحية مراجع.');
        $id = clean_text($_POST['message_id'] ?? '', 50);
        $status = in_array($_POST['status'] ?? '', ['new', 'read', 'archived'], true) ? $_POST['status'] : 'read';
        $pdo->prepare('UPDATE contact_messages SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
        write_audit((int) $admin['id'], $admin['display_name'], 'speakup.status', 'contact_message', $id, ['status' => $status]);
        admin_result($fallback, 'تم تحديث حالة الرسالة.');
    }

    if ($action === 'save_landing') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $fields = landing_text_fields();
        $saved = 0;
        foreach (array_keys($fields) as $key) {
            foreach (['ar', 'en'] as $locale) {
                $field = 'text_' . $key . '_' . $locale;
                if (!array_key_exists($field, $_POST)) continue;
                $value = clean_multiline($_POST[$field] ?? '', 2000);
                save_editable_content('home.' . $key, $locale, $value, (int) $admin['id']);
                $saved++;
            }
        }
        write_audit((int) $admin['id'], $admin['display_name'], 'landing.text.save', 'landing', 'home', ['fields' => $saved]);
        admin_result('landing.php', 'تم حفظ نصوص الصفحة الرئيسية.');
    }

    if ($action === 'save_landing_media') {
        if (!admin_can($admin, 'editor')) throw new RuntimeException('تحتاج صلاحية محرر.');
        $mediaFields = landing_media_fields();
        $key = clean_text($_POST['media_key'] ?? '', 40);
        if (!isset($mediaFields[$key])) throw new RuntimeException('عنصر الوسائط غير معروف.');
        $type = $mediaFields[$key][2];
        $file = $_FILES['media_file'] ?? [];
        $path = '';
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($type === 'image') {
                $stored = store_public_image($file, 'landing');
                $path = $stored['path'];
            } else {
                if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new RuntimeException('لم يكتمل رفع الملف.');
                if ((int) ($file['size'] ?? 0) > 40_000_000) throw new RuntimeException('حجم الفيديو أكبر من 40MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: '';
                $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
                if (!isset($allowed[$mime])) throw new RuntimeException('صيغة الفيديو غير مدعومة (MP4 أو WebM فقط).');
                $relative = 'uploads/landing/' . date('Y/m');
                $directory = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!is_dir($directory)) mkdir($directory, 0755, true);
                $name = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                if (!move_uploaded_file((string) $file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('تعذر حفظ الفيديو.');
                @chmod($directory . DIRECTORY_SEPARATOR . $name, 0644);
                $path = $relative . '/' . $name;
            }
        } else {
            $path = visual_safe_url(clean_text($_POST['media_path'] ?? '', 500), true);
            if ($path === '') throw new RuntimeException('اختر ملفاً أو أدخل مساراً صحيحاً.');
        }
        save_setting('home.' . $key, $path, (int) $admin['id']);
        write_audit((int) $admin['id'], $admin['display_name'], 'landing.media.save', 'landing', $key, ['path' => $path]);
        admin_result('landing.php', 'تم تحديث الوسائط.');
    }

    if (in_array($action, ['save_partner', 'partner_status', 'partner_move', 'partner_delete'], true)) {
        if (!admin_can($admin, 'admin')) throw new RuntimeException('تحتاج صلاحية مدير لإدارة الشركاء.');
        $records = partner_records();
        $partnerId = clean_text($_POST['partner_id'] ?? '', 40);
        $index = null;
        foreach ($records as $position => $record) if ($record['id'] === $partnerId) $index = $position;
        if ($action !== 'save_partner' || $partnerId !== '') {
            if ($index === null) throw new RuntimeException('الشريك غير موجود.');
        }
        $deleteLogoFile = static function (?string $path): void {
            if (!$path || !str_starts_with($path, 'uploads/partners/')) return;
            $full = realpath(APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
            $root = realpath(APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'partners');
            if ($full && $root && str_starts_with($full, $root) && is_file($full)) @unlink($full);
        };
        $message = 'تم الحفظ.';
        if ($action === 'save_partner') {
            $name = clean_text($_POST['name'] ?? '', 80);
            $since = clean_text($_POST['since'] ?? '', 20);
            if ($name === '') throw new RuntimeException('اكتب اسم الشريك.');
            $record = $index === null ? ['id' => 'p' . bin2hex(random_bytes(5)), 'name' => '', 'since' => '', 'logo' => null, 'status' => 'visible'] : $records[$index];
            $record['name'] = $name;
            $record['since'] = $since;
            $file = $_FILES['logo'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $stored = store_public_image($file, 'partners');
                $deleteLogoFile($record['logo']);
                $record['logo'] = $stored['path'];
            } elseif (!empty($_POST['remove_logo'])) {
                $deleteLogoFile($record['logo']);
                $record['logo'] = null;
            }
            if ($index === null) { $records[] = $record; $message = 'تمت إضافة الشريك.'; } else { $records[$index] = $record; $message = 'تم تحديث الشريك.'; }
        } elseif ($action === 'partner_status') {
            $records[$index]['status'] = $records[$index]['status'] === 'archived' ? 'visible' : 'archived';
            $message = $records[$index]['status'] === 'archived' ? 'تمت أرشفة الشريك وإخفاؤه من الموقع.' : 'عاد الشريك للظهور على الموقع.';
        } elseif ($action === 'partner_move') {
            $target = ($_POST['direction'] ?? '') === 'up' ? $index - 1 : $index + 1;
            if (isset($records[$target])) [$records[$index], $records[$target]] = [$records[$target], $records[$index]];
            $message = 'تم تغيير الترتيب.';
        } else {
            $deleteLogoFile($records[$index]['logo']);
            array_splice($records, $index, 1);
            $message = 'تم حذف الشريك.';
        }
        save_partner_records($records, (int) $admin['id']);
        write_audit((int) $admin['id'], $admin['display_name'], 'partners.' . $action, 'partner', $partnerId !== '' ? $partnerId : 'new', []);
        admin_result('appearance.php#partners', $message);
    }

    throw new RuntimeException('الإجراء غير معروف.');
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    admin_failure($error, $fallback);
}
