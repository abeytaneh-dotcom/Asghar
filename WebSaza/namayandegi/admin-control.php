<?php
declare(strict_types=1);
session_name('WEBSAZA_NAMAYANDEGI');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/namayandegi/']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Tehran');

const D=__DIR__.'/_data';
const B=D.'/branches.json';
const L=D.'/leads.json';
const A=D.'/assignments.json';
const S=D.'/settings.json';
const SMSL=D.'/sms_log.json';
const MR=D.'/manager_reports.json';

if(!is_dir(D))@mkdir(D,0750,true);
foreach([B,L,A,SMSL,MR] as $f)if(!is_file($f))@file_put_contents($f,"[]\n");
if(!is_file(S))@file_put_contents(S,"{}\n");

function out(array $x,int $code=200):never{http_response_code($code);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function j(string $f):array{$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}
function sj(string $f,array $x):bool{$l=fopen($f.'.lock','c');if(!$l||!flock($l,LOCK_EX))return false;try{$tmp=$f.'.tmp.'.bin2hex(random_bytes(4));$q=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);if($q===false||file_put_contents($tmp,$q,LOCK_EX)===false)return false;@chmod($tmp,0640);return rename($tmp,$f);}finally{flock($l,LOCK_UN);fclose($l);}}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return (string)$_SESSION['csrf'];}
function csrfOk():bool{return isset($_POST['csrf'])&&hash_equals(csrf(),(string)$_POST['csrf']);}
function n(string $k,int $default,int $min,int $max):int{$v=(int)($_POST[$k]??$default);return max($min,min($max,$v));}
function s(string $k,string $default=''):string{return trim((string)($_POST[$k]??$default));}
function settings():array{return array_merge([
  'company_name'=>'وب ساز','support_phone'=>'','welcome_text'=>'',
  'daily_quota'=>20,'commission_percent'=>30,'urgent_commission_percent'=>40,
  'normal_reassign_hours'=>24,'urgent_reassign_hours'=>2,
  'normal_reminder_hours'=>12,'urgent_reminder_hours'=>1,
  'max_order_handoffs'=>2,'default_referral_reward'=>200000,
  'sms_enabled'=>true,'sms_sender'=>'90008361','sms_manager_mobile'=>'09024342370',
  'sms_api_endpoint'=>'https://edge.ippanel.com/v1/api/send',
  'sms_new_order_pattern'=>'','sms_half_reminder_pattern'=>'','sms_manager_report_pattern'=>'',
  'manager_report_enabled'=>true
],j(S));}
function safeSettings(array $c):array{$keys=['company_name','support_phone','welcome_text','daily_quota','commission_percent','urgent_commission_percent','normal_reassign_hours','urgent_reassign_hours','normal_reminder_hours','urgent_reminder_hours','max_order_handoffs','default_referral_reward','sms_enabled','sms_sender','sms_manager_mobile','sms_api_endpoint','sms_new_order_pattern','sms_half_reminder_pattern','sms_manager_report_pattern','manager_report_enabled'];$o=[];foreach($keys as $k)$o[$k]=$c[$k]??null;$o['sms_api_configured']=!empty($c['sms_api_key'])||!empty($c['faraz_api_key'])||!empty($c['ippanel_api_key'])||!empty($c['api_key']);return $o;}
function branchPublic(array $b):array{return ['id'=>(string)($b['id']??''),'name'=>(string)($b['name']??''),'mobile'=>(string)($b['mobile']??''),'city'=>(string)($b['city']??''),'regions'=>(string)($b['regions']??''),'code'=>(string)($b['code']??''),'daily_quota'=>(int)($b['daily_quota']??0),'referral_reward_amount'=>(int)($b['referral_reward_amount']??200000),'active'=>!empty($b['active']),'last_login_at'=>(string)($b['last_login_at']??'')];}

if(empty($_SESSION['adm']))out(['ok'=>false,'message'=>'نشست مدیریت معتبر نیست.'],401);

if($_SERVER['REQUEST_METHOD']==='GET'){
  $c=settings();$branches=array_map('branchPublic',j(B));
  usort($branches,fn($a,$b)=>strcmp($b['name'],$a['name']));
  $leads=j(L);$assignments=j(A);$reports=j(MR);$sms=j(SMSL);
  $activeAssignments=count(array_filter($assignments,fn($x)=>($x['current']??true)!==false&&($x['status']??'new')!=='reassigned'));
  $openReports=count(array_filter($reports,fn($x)=>($x['status']??'open')==='open'));
  $smsSent=count(array_filter($sms,fn($x)=>in_array(($x['status']??''),['sent','sent_pattern'],true)));
  $smsFailed=count(array_filter($sms,fn($x)=>in_array(($x['status']??''),['failed','pattern_failed','not_configured'],true)));
  $recentReports=array_values(array_reverse(array_slice($reports,-50)));
  $recentSms=array_values(array_reverse(array_slice($sms,-40)));
  foreach($recentSms as &$r){unset($r['response']);$r['message']=mb_substr((string)($r['message']??''),0,180);}unset($r);
  out(['ok'=>true,'csrf'=>csrf(),'settings'=>safeSettings($c),'branches'=>$branches,'summary'=>[
    'branches_total'=>count($branches),'branches_active'=>count(array_filter($branches,fn($x)=>$x['active'])),
    'leads_total'=>count($leads),'assignments_active'=>$activeAssignments,'manager_reports_open'=>$openReports,
    'sms_sent'=>$smsSent,'sms_failed'=>$smsFailed
  ],'manager_reports'=>$recentReports,'sms_logs'=>$recentSms]);
}

if($_SERVER['REQUEST_METHOD']!=='POST')out(['ok'=>false,'message'=>'روش درخواست نامعتبر است.'],405);
if(!csrfOk())out(['ok'=>false,'message'=>'درخواست نامعتبر یا منقضی شده است.'],403);
$action=(string)($_POST['action']??'');

if($action==='save_settings'){
  $c=settings();
  $normal=n('normal_reassign_hours',(int)$c['normal_reassign_hours'],1,168);
  $urgent=n('urgent_reassign_hours',(int)$c['urgent_reassign_hours'],1,24);
  $normalReminder=n('normal_reminder_hours',(int)$c['normal_reminder_hours'],0,max(0,$normal-1));
  $urgentReminder=n('urgent_reminder_hours',(int)$c['urgent_reminder_hours'],0,max(0,$urgent-1));
  $c['company_name']=s('company_name',(string)$c['company_name']);
  $c['support_phone']=s('support_phone',(string)$c['support_phone']);
  $c['welcome_text']=s('welcome_text',(string)$c['welcome_text']);
  $c['daily_quota']=n('daily_quota',(int)$c['daily_quota'],1,100);
  $c['commission_percent']=n('commission_percent',(int)$c['commission_percent'],0,100);
  $c['urgent_commission_percent']=n('urgent_commission_percent',(int)$c['urgent_commission_percent'],0,100);
  $c['normal_reassign_hours']=$normal;$c['urgent_reassign_hours']=$urgent;
  $c['normal_reminder_hours']=$normalReminder;$c['urgent_reminder_hours']=$urgentReminder;
  $c['max_order_handoffs']=n('max_order_handoffs',(int)$c['max_order_handoffs'],1,10);
  $c['default_referral_reward']=max(0,(int)preg_replace('/\D/','',s('default_referral_reward',(string)$c['default_referral_reward'])));
  $c['sms_enabled']=(string)($_POST['sms_enabled']??'0')==='1';
  $c['manager_report_enabled']=(string)($_POST['manager_report_enabled']??'0')==='1';
  $c['sms_sender']=s('sms_sender',(string)$c['sms_sender']);
  $c['sms_manager_mobile']=s('sms_manager_mobile',(string)$c['sms_manager_mobile']);
  $c['sms_api_endpoint']=s('sms_api_endpoint',(string)$c['sms_api_endpoint']);
  $c['sms_new_order_pattern']=s('sms_new_order_pattern',(string)$c['sms_new_order_pattern']);
  $c['sms_half_reminder_pattern']=s('sms_half_reminder_pattern',(string)$c['sms_half_reminder_pattern']);
  $c['sms_manager_report_pattern']=s('sms_manager_report_pattern',(string)$c['sms_manager_report_pattern']);
  $newKey=s('sms_api_key','');if($newKey!=='')$c['sms_api_key']=$newKey;
  $c['unseen_reassign_hours']=$normal;
  if(!sj(S,$c))out(['ok'=>false,'message'=>'ذخیره تنظیمات انجام نشد.'],500);
  out(['ok'=>true,'message'=>'همه تنظیمات ذخیره شد.','settings'=>safeSettings($c)]);
}

if($action==='save_branch'){
  $id=s('id');$rows=j(B);$found=false;$updated=null;
  foreach($rows as &$r)if((string)($r['id']??'')===$id){
    $r['name']=s('name',(string)($r['name']??''));$r['mobile']=s('mobile',(string)($r['mobile']??''));
    $r['city']=s('city',(string)($r['city']??''));$r['regions']=s('regions',(string)($r['regions']??''));
    $r['daily_quota']=n('daily_quota',(int)($r['daily_quota']??0),0,100);
    $r['referral_reward_amount']=max(0,(int)preg_replace('/\D/','',s('referral_reward_amount',(string)($r['referral_reward_amount']??200000))));
    $r['active']=(string)($_POST['active']??'0')==='1';
    $newPassword=(string)($_POST['new_password']??'');if($newPassword!==''){if(strlen($newPassword)<8)out(['ok'=>false,'message'=>'رمز جدید شعبه حداقل ۸ کاراکتر باشد.'],422);$r['password_hash']=password_hash($newPassword,PASSWORD_DEFAULT);}
    $r['admin_updated_at']=date('Y-m-d H:i:s');$found=true;$updated=branchPublic($r);break;
  }unset($r);
  if(!$found)out(['ok'=>false,'message'=>'شعبه پیدا نشد.'],404);
  if(!sj(B,$rows))out(['ok'=>false,'message'=>'ذخیره شعبه انجام نشد.'],500);
  out(['ok'=>true,'message'=>'اطلاعات شعبه ذخیره شد.','branch'=>$updated]);
}

if($action==='resolve_report'){
  $id=s('id');$rows=j(MR);$found=false;
  foreach($rows as &$r)if((string)($r['id']??'')===$id){$r['status']='resolved';$r['resolved_at']=date('Y-m-d H:i:s');$found=true;break;}unset($r);
  if(!$found)out(['ok'=>false,'message'=>'گزارش پیدا نشد.'],404);
  sj(MR,$rows);out(['ok'=>true,'message'=>'گزارش بسته شد.']);
}

out(['ok'=>false,'message'=>'عملیات ناشناخته است.'],400);
