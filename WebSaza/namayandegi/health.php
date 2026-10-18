<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Tehran');
session_name('WEBSAZA_NAMAYANDEGI');
if(PHP_SAPI!=='cli')@session_start();
const D=__DIR__.'/_data';
if(!is_dir(D))@mkdir(D,0750,true);
require_once __DIR__.'/platform-v3.php';
$core=ws_health();
$backup=ws_raw_read(D.'/_backup_status.json');
$ops=ws_raw_read(D.'/_ops_status.json');
$backupTs=strtotime((string)($backup['completed_at']??''))?:0;
$opsTs=strtotime((string)($ops['checked_at']??''))?:0;
$backupOk=!empty($backup['ok'])&&$backupTs>time()-129600;
$free=@disk_free_space(dirname(__DIR__,2));
$total=@disk_total_space(dirname(__DIR__,2));
$diskOk=is_numeric($free)&&is_numeric($total)&&$total>0?($free/$total)>=0.08:true;
$q=ws_store_read(D.'/sms_queue.json');$oldPending=0;foreach($q as $r){if(!is_array($r)||!in_array((string)($r['status']??''),['queued','retry'],true))continue;$ts=strtotime((string)($r['created_at']??''))?:time();if($ts<time()-7200)$oldPending++;}
$opsCurrent=!empty($core['ok'])&&$backupOk&&$diskOk&&(int)($core['sms_dead']??0)<20&&$oldPending<50;
$opsFresh=!empty($ops['ok'])&&$opsTs>time()-3600;
$public=['ok'=>!empty($core['ok']),'version'=>'platform-v4','backup_ok'=>$backupOk,'ops_ok'=>$opsCurrent];
$full=PHP_SAPI==='cli'||(!empty($_SESSION['adm'])&&isset($_GET['full']));
if($full){$public['details']=$core;$public['backup']=$backup;$public['ops']=$ops;$public['ops_fresh']=$opsFresh;$public['php']=PHP_VERSION;$public['cron_expected']=['smart-actions.php'=>'every minute','sms-worker.php'=>'every minute','ops-worker.php'=>'every 15 minutes','backup-worker.php'=>'guarded every 5 minutes'];}
echo json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
