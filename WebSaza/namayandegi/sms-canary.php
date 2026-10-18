<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tehran');
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
const D=__DIR__.'/_data';const S=D.'/settings.json';
require_once __DIR__.'/platform-v3.php';
$c=ws_raw_read(S);$mobile=ws_phone((string)($c['sms_manager_mobile']??''));$pattern=trim((string)($c['sms_recovery_pattern']??'4RXte6aAmq'));$statusFile=D.'/_sms_canary_status.json';
$old=ws_raw_read($statusFile);$last=strtotime((string)($old['completed_at']??''))?:0;$force=in_array('--force',$argv??[],true);if(!$force&&$last>time()-2505600){echo "CANARY_NOT_DUE\n";exit(0);}if($mobile===''||$pattern===''){ws_raw_write($statusFile,['ok'=>false,'status'=>'not_configured','checked_at'=>ws_now()]);fwrite(STDERR,"CANARY_NOT_CONFIGURED\n");exit(2);} 
$code=(string)random_int(100000,999999);$r=ws_sms_send_now(S,$mobile,$pattern,['code'=>$code]);$ok=!empty($r['ok']);ws_raw_write($statusFile,['ok'=>$ok,'status'=>(string)($r['status']??''),'http'=>(int)($r['http']??0),'completed_at'=>ws_now()]);echo $ok?"CANARY_ACCEPTED\n":"CANARY_FAILED\n";exit($ok?0:1);
