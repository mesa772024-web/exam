<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$blockId=(int)($_GET['block']??0);$locale=($_GET['lang']??'ar')==='en'?'en':'ar';
$statement=db()->prepare("SELECT t.content_json,p.status FROM content_blocks b JOIN pages p ON p.id=b.page_id JOIN content_block_translations t ON t.block_id=b.id AND t.locale=:locale WHERE b.id=:id AND b.block_type='html_element'");$statement->execute(['locale'=>$locale,'id'=>$blockId]);$row=$statement->fetch();
if(!$row||($row['status']!=='published'&&!current_admin())){http_response_code(404);exit;}
$content=cms_decode_json($row['content_json']);
header_remove('Content-Security-Policy');
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'self' data:; font-src 'self'; media-src 'none'; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'");
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html lang="<?= h($locale) ?>" dir="<?= $locale==='ar'?'rtl':'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;font-family:system-ui;color:#231018}*{box-sizing:border-box}<?= (string)($content['css']??'') ?></style></head><body><?= (string)($content['html']??'') ?><script nonce="<?= h(csp_nonce()) ?>"><?= (string)($content['js']??'') ?></script></body></html>
