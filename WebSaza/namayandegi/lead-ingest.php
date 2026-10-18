<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
date_default_timezone_set('Asia/Tehran');
const D=__DIR__.'/_data';const L=D.'/leads.json';
require_once __DIR__.'/platform-v3.php';
const INGEST_KEY_HASH='e2f04feba814decd948a55bc2d4ef94a038557ee48e160519e285ce4359d1716';
function out(array $x,int $code=200):never{http_response_code($code);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$key=(string)($_SERVER['HTTP_X_WEBSAZA_KEY']??$_POST['key']??$_GET['key']??'');
if($key===''||!hash_equals(INGEST_KEY_HASH,hash('sha256',$key)))out(['ok'=>false,'message'=>'unauthorized'],401);
if($_SERVER['REQUEST_METHOD']!=='POST')out(['ok'=>true,'message'=>'ready','max_batch'=>500]);
$raw=file_get_contents('php://input');$body=json_decode((string)$raw,true);if(!is_array($body))$body=$_POST;
$items=$body['leads']??null;if(!is_array($items))$items=[$body];if(count($items)>500)out(['ok'=>false,'message'=>'batch_too_large'],413);
$rows=ws_store_read(L);ws_import_contacts($rows);$known=[];foreach($rows as $r){$p=ws_phone((string)($r['phone']??''));if($p!=='')$known[$p]=1;}
$accepted=[];$duplicates=[];$rejected=[];
foreach($items as $i=>$x){if(!is_array($x)){$rejected[]=['index'=>$i,'reason'=>'invalid'];continue;}$p=ws_phone((string)($x['phone']??''));$score=max(0,min(100,(int)($x['lead_score']??ws_score($x))));$site=mb_strtolower(trim((string)($x['website_status']??'')),'UTF-8');$noSite=!empty($x['verified_no_website'])||$site===''||str_contains($site,'ندارد')||str_contains($site,'بدون')||str_contains($site,'ضعیف')||str_contains($site,'قدیمی');if($p===''||$score<90||!$noSite){$rejected[]=['index'=>$i,'phone'=>$p,'reason'=>$p===''?'bad_phone':($score<90?'score_below_90':'website_not_verified')];continue;}if(isset($known[$p])||ws_contact_seen($p)){$duplicates[]=['index'=>$i,'phone'=>$p,'reason'=>'historical_contact'];continue;}$id='L'.bin2hex(random_bytes(6));$lead=['id'=>$id,'name'=>trim((string)($x['name']??'مشتری'))?:'مشتری','phone'=>$p,'business'=>trim((string)($x['business']??'')),'city'=>trim((string)($x['city']??'')),'region'=>trim((string)($x['region']??'')),'category'=>trim((string)($x['category']??'')),'proposal'=>trim((string)($x['proposal']??'')),'script'=>trim((string)($x['script']??'')),'source'=>trim((string)($x['source']??'جست‌وجوی روزانه')),'source_url'=>trim((string)($x['source_url']??'')),'website_status'=>trim((string)($x['website_status']??'ندارد')),'website_url'=>trim((string)($x['website_url']??'')),'social_url'=>trim((string)($x['social_url']??$x['instagram']??'')),'best_call_time'=>trim((string)($x['best_call_time']??'')),'decision_maker'=>trim((string)($x['decision_maker']??'')),'customer_profile'=>trim((string)($x['customer_profile']??'')),'system_need'=>trim((string)($x['system_need']??'')),'evidence'=>trim((string)($x['evidence']??'')),'objections'=>is_array($x['objections']??null)?array_values($x['objections']):trim((string)($x['objections']??'')),'lead_score'=>$score,'verified_no_website'=>true,'priority'=>((string)($x['priority']??'normal'))==='urgent'?'urgent':'normal','active'=>true,'created_at'=>ws_now()];if($lead['proposal']==='')$lead['proposal']='پیشنهاد اختصاصی طراحی سایت/فروشگاه/سیستم آنلاین بر اساس نیاز این کسب‌وکار';if($lead['script']==='')$lead['script']='سلام وقت بخیر، از وب ساز تماس می‌گیرم. کسب‌وکار شما را بررسی کردیم و یک پیشنهاد مشخص برای افزایش جذب مشتری و فروش آنلاین داریم.';$rows[]=$lead;$known[$p]=1;ws_contact_touch($p,['status'=>'ingested','lead_id'=>$id,'business'=>$lead['business'],'source'=>$lead['source'],'score'=>$score,'source_url'=>$lead['source_url']]);ws_audit('lead_ingested',$id,$p,['score'=>$score,'source'=>$lead['source'],'city'=>$lead['city']]);$accepted[]=['id'=>$id,'phone'=>$p,'score'=>$score];}
if($accepted&&!ws_store_write(L,$rows))out(['ok'=>false,'message'=>'storage_failed'],500);
out(['ok'=>true,'accepted'=>$accepted,'duplicates'=>$duplicates,'rejected'=>$rejected,'counts'=>['accepted'=>count($accepted),'duplicates'=>count($duplicates),'rejected'=>count($rejected)]]);
