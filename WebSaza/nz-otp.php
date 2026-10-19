<?php
declare(strict_types=1);

$nzotpLegacyPlatform = __DIR__.'/namayandegi/platform-v3.php';
if (is_file($nzotpLegacyPlatform)) require_once $nzotpLegacyPlatform;

function nzotp_env(string $key): string { return trim((string)getenv($key)); }
function nzotp_env_first(array $keys): string {foreach($keys as$k){$v=nzotp_env($k);if($v!=='')return$v;}return'';}
function nzotp_direct_key(): string {return nzotp_env_first(['NIAZESTAN_SMS_API_KEY','IPPANEL_API_KEY']);}
function nzotp_direct_pattern(): string {return nzotp_env_first(['NIAZESTAN_SMS_PATTERN_CODE','IPPANEL_PATTERN_CODE']);}
function nzotp_direct_sender(): string {return nzotp_env_first(['NIAZESTAN_SMS_FROM_NUMBER','IPPANEL_FROM_NUMBER']);}
function nzotp_direct_param(): string {$v=nzotp_env_first(['NIAZESTAN_SMS_PATTERN_PARAM','IPPANEL_PATTERN_PARAM']);return$v!==''?$v:'code';}
function nzotp_legacy_settings_file(): string { return __DIR__.'/namayandegi/_data/settings.json'; }
function nzotp_legacy_settings(): array {
    $file=nzotp_legacy_settings_file();
    if (function_exists('ws_raw_read')) return ws_raw_read($file);
    $raw=@file_get_contents($file); $data=json_decode($raw?:'',true); return is_array($data)?$data:[];
}
function nzotp_legacy_key(array $c): string {
    if (function_exists('ws_sms_key')) return trim((string)ws_sms_key($c));
    foreach(['sms_api_key','faraz_api_key','ippanel_api_key','api_key'] as $k){$v=trim((string)($c[$k]??''));if($v!=='')return$v;}
    foreach(['FARAZ_SMS_API_KEY','IPPANEL_API_KEY','SMS_API_KEY'] as $k){$v=nzotp_env($k);if($v!=='')return$v;}
    return'';
}
function nzotp_legacy_pattern(): string {
    $env=nzotp_env('NIAZESTAN_OTP_PATTERN'); if($env!=='')return$env;
    $c=nzotp_legacy_settings();
    foreach(['niazestan_otp_pattern','sms_login_pattern','sms_recovery_pattern'] as$k){$v=trim((string)($c[$k]??''));if($v!=='')return$v;}
    return '4RXte6aAmq';
}
function nzotp_legacy_sender(array $c): string {
    $v=trim((string)($c['sms_sender']??$c['sms_from_number']??''));if($v!=='')return$v;
    $env=nzotp_env('SMS_FROM_NUMBER'); if($env!=='')return$env;return '90008361';
}
function nzotp_legacy_ready(): bool {
    $c=nzotp_legacy_settings();if(array_key_exists('sms_enabled',$c) && empty($c['sms_enabled'])) return false;
    return nzotp_legacy_pattern()!=='' && nzotp_legacy_key($c)!=='' && function_exists('curl_init');
}
function nzotp_direct_ready(): bool {return nzotp_direct_key()!==''&&nzotp_direct_pattern()!==''&&nzotp_direct_sender()!==''&&function_exists('curl_init');}
function nzotp_ready(): bool { return nzotp_direct_ready() || nzotp_legacy_ready(); }
function nzotp_phone(string $phone): string {
    $p=preg_replace('/\D+/','',$phone)??'';
    if(str_starts_with($p,'0098'))$p=substr($p,4);elseif(str_starts_with($p,'98')&&strlen($p)===12)$p='0'.substr($p,2);if(str_starts_with($p,'9')&&strlen($p)===10)$p='0'.$p;return $p;
}
function nzotp_e164(string $phone): string { return '+98'.substr($phone,1); }

function nzotp_send_legacy(string $phone,string $code): bool {
    $settings=nzotp_legacy_settings_file();
    if(function_exists('ws_sms_send_now')){$result=ws_sms_send_now($settings,$phone,nzotp_legacy_pattern(),['code'=>$code]);return !empty($result['ok']);}
    $c=nzotp_legacy_settings(); $key=nzotp_legacy_key($c);if($key===''||!function_exists('curl_init'))return false;
    $payload=['code'=>nzotp_legacy_pattern(),'attributes'=>(object)['code'=>$code],'recipient'=>nzotp_phone($phone),'line_number'=>nzotp_legacy_sender($c),'number_format'=>'english'];
    $ch=curl_init('https://api.iranpayamak.com/ws/v1/sms/pattern');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Api-Key: '.$key],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1]);
    $raw=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=(string)curl_error($ch); curl_close($ch);if($raw===false||$err!==''||$http<200||$http>=300)return false;$j=json_decode((string)$raw,true);if(is_array($j)&&isset($j['status'])&&strtolower((string)$j['status'])!=='success')return false;return true;
}
function nzotp_send_direct(string $phone,string $code): bool {
    if(!nzotp_direct_ready())return false;
    $payload=['sending_type'=>'pattern','from_number'=>nzotp_direct_sender(),'code'=>nzotp_direct_pattern(),'recipients'=>[nzotp_e164($phone)],'params'=>[nzotp_direct_param()=>$code]];
    $ch=curl_init('https://edge.ippanel.com/v1/api/send');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: '.nzotp_direct_key()],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$status<200||$status>=300)return false;$json=json_decode((string)$raw,true);return !(is_array($json)&&isset($json['meta']['status'])&&!$json['meta']['status']);
}
function nzotp_send_pattern(string $phone,string $code): void {
    if(nzotp_direct_ready()){if(nzotp_send_direct($phone,$code))return;throw new RuntimeException('سرویس پیامک نیازستان در دسترس است اما ارسال کد پذیرفته نشد.');}
    if(nzotp_legacy_ready()){if(nzotp_send_legacy($phone,$code))return;throw new RuntimeException('سرویس پیامک در دسترس است اما ارسال کد پذیرفته نشد. تنظیمات پترن یا خط ارسال را بررسی کنید.');}
    throw new RuntimeException('سرویس پیامک ورود هنوز روی سرور پیکربندی نشده است.');
}
function nzotp_request(): never {
    nzc_session_open();if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');$phone=nzotp_phone((string)($_POST['phone']??''));if(!preg_match('/^09\d{9}$/',$phone)) throw new RuntimeException('شماره موبایل معتبر وارد کنید.');$code=(string)random_int(10000,99999);nzotp_send_pattern($phone,$code);$_SESSION['otp']=['phone'=>$phone,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0,'sent_at'=>time()];nzc_flash('کد ورود برای شما پیامک شد.','ok');nzc_redirect('/nz?page=login&step=verify');
}
function nzotp_verify(): never {
    nzc_session_open();if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');$otp=$_SESSION['otp']??[]; $code=trim((string)($_POST['code']??''));
    if (!is_array($otp) || empty($otp['hash']) || time()>(int)($otp['expires']??0)) { unset($_SESSION['otp']); throw new RuntimeException('کد ورود منقضی شده است. دوباره کد بگیرید.'); }
    $attempts=(int)($otp['attempts']??0)+1; $_SESSION['otp']['attempts']=$attempts;if ($attempts>6) { unset($_SESSION['otp']); throw new RuntimeException('تعداد تلاش ناموفق زیاد بود. دوباره کد ورود بگیرید.'); }
    if (!preg_match('/^\d{5}$/',$code) || !password_verify($code,(string)$otp['hash'])) throw new RuntimeException('کد ورود اشتباه است.');$phone=(string)$otp['phone'];
    $uid=nzc_db_write(function(&$db)use($phone){foreach($db['users'] as &$u){if(($u['phone']??'')===$phone){$u['last_login']=date('c');return(string)$u['id'];}} unset($u);$id=nzc_uid('u');$db['users'][]=['id'=>$id,'phone'=>$phone,'name'=>'کاربر نیازستان','role'=>'user','created_at'=>date('c'),'last_login'=>date('c')]; return$id;});
    session_regenerate_id(true); $_SESSION['uid']=$uid; unset($_SESSION['otp']); nzc_flash('خوش آمدید 👋','ok'); nzc_redirect('/nz');
}
