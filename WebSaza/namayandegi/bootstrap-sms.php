<?php
declare(strict_types=1);
/* ONE_TIME_SMS_BOOTSTRAP_V1 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){http_response_code(403);exit('HTTPS required');}

const D=__DIR__.'/_data';
const S=D.'/settings.json';
const AD=D.'/admin.json';
const TOKEN_HASH='2320a639f8fdfe17e0e2f1a53e87815ae80be514fa83cb68ecbb0290e122fffa';
const DONE=D.'/sms_bootstrap_done';

if(!is_dir(D))@mkdir(D,0750,true);
if(is_file(DONE)){http_response_code(410);exit('Setup already completed.');}
$t=(string)($_GET['t']??$_POST['t']??'');
if($t===''||!hash_equals(TOKEN_HASH,hash('sha256',$t))){http_response_code(404);exit('Not found');}

require_once __DIR__.'/_security.php';
function rd(string $f):array{$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}
function wr(string $f,array $x):bool{return sec_write($f,$x);}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
$msg='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $key=trim((string)($_POST['api_key']??''));
  $mobile=sec_mobile((string)($_POST['mobile']??''));
  $sender=trim((string)($_POST['sender']??'90008361'));
  if(strlen($key)<24||!preg_match('/^09\d{9}$/',$mobile)||!preg_match('/^[0-9A-Za-z+_-]{3,32}$/',$sender)){
    $msg='اطلاعات واردشده معتبر نیست.';
  }else{
    $cfg=rd(S);
    $cfg['sms_enabled']=true;
    $cfg['sms_api_key']=$key;
    $cfg['ippanel_api_key']=$key;
    $cfg['sms_sender']=$sender;
    $cfg['sms_api_endpoint']='https://edge.ippanel.com/v1/api/send';
    $cfg['sms_manager_mobile']=$mobile;
    $cfg['sms_configured_at']=date('Y-m-d H:i:s');
    if(!wr(S,$cfg)){
      $msg='ذخیره تنظیمات پیامک ناموفق بود.';
    }else{
      $adm=rd(AD);
      if(!$adm)$adm=['username'=>'admin'];
      $adm['mobile']=$mobile;
      $adm['must_change']=true;
      wr(AD,$adm);
      sec_unlock(D,'admin','root');
      $ok=sec_sms(S,$mobile,"وب ساز\nاتصال پیامک با موفقیت فعال شد.\nاکنون می‌توانید بازیابی ورود مدیر را انجام دهید.");
      if($ok){
        @file_put_contents(DONE,date('c'),LOCK_EX);@chmod(DONE,0600);
        $msg='تنظیمات ذخیره شد و پیامک آزمایشی با موفقیت ارسال شد. این صفحه از همین لحظه غیرفعال شد.';
      }else{
        $msg='تنظیمات ذخیره شد، اما پیامک آزمایشی ارسال نشد. خط فرستنده یا API را بررسی کنید و دوباره تلاش کنید.';
      }
    }
  }
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی امن پیامک وب ساز</title><style>body{font-family:Tahoma,Arial,sans-serif;background:#fafaf8;color:#222;margin:0}.wrap{max-width:520px;margin:8vh auto;padding:20px}.card{background:#fff;border:1px solid #ecece8;border-radius:18px;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,.05)}h1{font-size:20px;margin:0 0 10px}p{line-height:1.9;color:#666}label{display:block;margin:14px 0 6px;font-weight:700}input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #ddd;border-radius:12px;font-size:15px;direction:ltr}button{width:100%;margin-top:20px;padding:14px;border:0;border-radius:12px;background:#a62626;color:#fff;font-weight:700;font-size:15px}.msg{padding:12px;border-radius:12px;background:#f7f7f4;margin-bottom:16px;line-height:1.8}.done{background:#eef8f0;color:#256436}</style></head><body><div class="wrap"><div class="card"><h1>راه‌اندازی امن پیامک وب ساز</h1><p>این صفحه فقط یک‌بار برای ثبت API پیامک و شماره مدیر قابل استفاده است و پس از ارسال موفق پیامک آزمایشی غیرفعال می‌شود.</p><?php if($msg!==''):?><div class="msg <?=$ok?'done':''?>"><?=h($msg)?></div><?php endif;?><?php if(!$ok):?><form method="post" autocomplete="off"><input type="hidden" name="t" value="<?=h($t)?>"><label>API Key</label><input type="password" name="api_key" required autocomplete="off"><label>شماره موبایل مدیر</label><input name="mobile" inputmode="numeric" placeholder="09xxxxxxxxx" required><label>خط فرستنده</label><input name="sender" value="90008361" required><button type="submit">ذخیره و ارسال پیامک آزمایشی</button></form><?php endif;?></div></div></body></html>