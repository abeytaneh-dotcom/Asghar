<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$c=require __DIR__.'/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$rows=$pdo->query("SELECT id,barcode,name,brand,quantity_label,category,price,stock,active,image_path FROM products WHERE active=1 ORDER BY id ASC")->fetchAll();
@unlink(__FILE__);
echo json_encode(['products'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
