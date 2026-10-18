<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tehran');
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
const D=__DIR__.'/_data';const S=D.'/settings.json';
require_once __DIR__.'/platform-v3.php';
$r=ws_sms_process_queue(S,25);echo json_encode(['ok'=>true]+$r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
