<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-lifecycle.php';
require_once __DIR__.'/nz-maintenance.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$keyFile=__DIR__.'/.marketplace-data/cron.key';$expected=trim((string)@file_get_contents($keyFile));$provided=trim((string)($_SERVER['HTTP_X_NIYAZESTAN_CRON']??''));
if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;}
try{$stats=nzmaint_run();echo json_encode(['ok'=>true,'stats'=>$stats],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'maintenance_failed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
