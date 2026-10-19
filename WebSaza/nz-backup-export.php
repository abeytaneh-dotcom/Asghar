<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$expected=trim((string)@file_get_contents(__DIR__.'/.marketplace-data/cron.key'));
$provided=trim((string)($_SERVER['HTTP_X_NIYAZESTAN_CRON']??''));
if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;}
try{
    $db=nzc_db_read();
    echo json_encode(['ok'=>true,'version'=>2,'generated_at'=>date('c'),'backend'=>nzstore_backend(),'data'=>$db],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'backup_failed']);}
