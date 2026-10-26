<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$expected='__TOKEN__';
if(!hash_equals($expected,(string)($_SERVER['HTTP_X_VERIFY_TOKEN']??''))){http_response_code(403);exit;}
@unlink(__FILE__);
$c=require __DIR__.'/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$row=$pdo->query("SELECT COUNT(*) total,SUM(image_path IS NOT NULL AND image_path<>'') with_image,SUM(image_path LIKE 'uploads/products/auto/%') auto_images,SUM(image_path IS NULL OR image_path='') no_image FROM products WHERE active=1")->fetch();
echo json_encode(['ok'=>true,'products'=>$row],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
