<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Tehran');
const D=__DIR__.'/_data';
require_once __DIR__.'/platform-v3.php';
$out=ws_health();
$out['php']=PHP_VERSION;
$out['cron_expected']='smart-actions.php every minute';
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
