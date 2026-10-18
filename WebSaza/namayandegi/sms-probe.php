<?php
declare(strict_types=1);
/* SMS_PROVIDER_PROBE_V1 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'https_required']);exit;}
const D=__DIR__.'/_data';
const S=D.'/settings.json';
const TOKEN_HASH='c27a47efe584559a1e92931481a0b8376c7c70b4cc09d1ec3f9023a24f827a31';
$t=(string)($_GET['t']??'');
if($t===''||!hash_equals(TOKEN_HASH,hash('sha256',$t))){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
$c=json_decode((string)@file_get_contents(S),true);if(!is_array($c))$c=[];
$key='';foreach(['sms_api_key','faraz_api_key','ippanel_api_key','api_key'] as $k){$v=trim((string)($c[$k]??''));if($v!==''){$key=$v;break;}}
$mobile=preg_replace('/\D/','',(string)($c['sms_manager_mobile']??''))??'';if(str_starts_with($mobile,'98'))$mobile='0'.substr($mobile,2);if(strlen($mobile)===10&&str_starts_with($mobile,'9'))$mobile='0'.$mobile;
$pattern=trim((string)($c['sms_recovery_pattern']??'xa2lAXeEHs'));
$from=trim((string)($c['sms_sender']??'90008361'));
if($key===''||!preg_match('/^09\d{9}$/',$mobile)||$pattern===''){echo json_encode(['ok'=>false,'error'=>'config_missing','has_key'=>$key!=='','has_mobile'=>preg_match('/^09\d{9}$/',$mobile)===1,'pattern'=>$pattern!==''?'configured':'missing']);exit;}
$code=(string)random_int(100000,999999);
$payload=['sending_type'=>'pattern','from_number'=>$from,'code'=>$pattern,'recipients'=>['+98'.substr($mobile,1)],'params'=>['code'=>$code]];
$ch=curl_init(trim((string)($c['sms_api_endpoint']??'https://edge.ippanel.com/v1/api/send')));
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: '.$key],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20]);
$body=(string)curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=(string)curl_error($ch);curl_close($ch);
$j=json_decode($body,true);$safeBody=is_array($j)?$j:$body;
@file_put_contents(D.'/sms_probe_last.json',json_encode(['at'=>date('c'),'http'=>$http,'response'=>$safeBody,'curl_error'=>$err],JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod(D.'/sms_probe_last.json',0600);
echo json_encode(['ok'=>$http>=200&&$http<300,'http'=>$http,'sender'=>$from,'pattern'=>$pattern,'provider'=>$safeBody,'curl_error'=>$err],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
