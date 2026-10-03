<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/maintenance.php';
$admin=require_admin('super_admin');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
try{
 verify_csrf($_POST['csrf_token']??null);enforce_rate_limit('maintenance',4,3600,(string)$admin['id']);
 if(!verify_admin_password($admin,(string)($_POST['password']??'')))throw new RuntimeException('كلمة المرور الحالية غير صحيحة.');
 $action=clean_text($_POST['action']??'',40);
 if($action==='restore_database'){if(($_POST['confirmation']??'')!=='RESTORE')throw new RuntimeException('عبارة التأكيد غير صحيحة.');create_database_backup();verify_and_stage_database_restore($_FILES['database']??[]);write_audit((int)$admin['id'],$admin['display_name'],'database.restore.completed','database','mysql');clear_admin_session();header('Location: '.admin_url('login.php?restored=1'));exit;}
 if($action==='apply_update'){if(($_POST['confirmation']??'')!=='UPDATE')throw new RuntimeException('عبارة التأكيد غير صحيحة.');$version=apply_update_package($_FILES['update']??[],$admin);write_audit((int)$admin['id'],$admin['display_name'],'update.applied','application',$version);header('Location: '.admin_url('maintenance.php?ok=1'));exit;}
 throw new RuntimeException('الإجراء غير معروف.');
}catch(Throwable $error){header('Location: '.admin_url('maintenance.php?error='.rawurlencode($error->getMessage())));exit;}
