<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control:no-store');
$expected='__TOKEN__';if(!hash_equals($expected,(string)($_SERVER['HTTP_X_REPORT_TOKEN']??''))){http_response_code(403);exit;}
try{$c=require __DIR__.'/config.php';$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$rows=$pdo->query("SELECT id,name,brand,quantity_label,barcode,pyramid_code2,category,stock,image_source FROM products WHERE active=1 AND image_source LIKE 'generated-fallback|%' ORDER BY id")->fetchAll();echo json_encode(['ok'=>true,'count'=>count($rows),'rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}@unlink(__FILE__);
