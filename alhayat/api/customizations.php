<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')json_response(['ok'=>false],405);
$scope=clean_slug($_GET['scope']??'site')?:'site';$locale=($_GET['lang']??'ar')==='en'?'en':'ar';$prefix='auto-'.$scope.'-%';
$statement=db()->prepare('SELECT content_key,content_value FROM content_overrides WHERE locale=:locale AND content_key LIKE :prefix');
$statement->execute(['locale'=>$locale,'prefix'=>$prefix]);
$content=[];
foreach($statement->fetchAll() as $row){
    $value=(string)$row['content_value'];
    $decoded=json_decode($value,true);
    $content[$row['content_key']]=is_array($decoded)&&($decoded['version']??null)===2?$decoded:$value;
}
json_response(['ok'=>true,'content'=>$content]);
