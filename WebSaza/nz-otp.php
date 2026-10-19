<?php
declare(strict_types=1);

$nzotpLegacyPlatform = __DIR__.'/namayandegi/platform-v3.php';
if (is_file($nzotpLegacyPlatform)) require_once $nzotpLegacyPlatform;

function nzotp_env(string $key): string { return trim((string)getenv($key)); }
function nzotp_legacy_settings_file(): string { return __DIR__.'/namayandegi/_data/settings.json'; }
function nzotp_legacy_settings(): array {
    $file=nzotp_legacy_settings_file();
    if (function_exists('ws_raw_read')) return ws_raw_read($file);
    $raw=@file_get_contents($file); $data=json_decode($raw?:'',true); return is_array($data)?$data:[];
}
function nzotp_legacy_pattern(): string {
    $env=nzotp_env('NIAZESTAN_OTP_PATTERN'); if($env!=='')return$env;
    $c=nzotp_legacy_settings();
    foreach(['niazestan_otp_pattern','sms_login_pattern','sms_recovery_pattern'] as$k){$v=trim((string)($c[$k]??''));if($v!=='')return$v;}
    return'';
}
function nzotp_legacy_ready(): bool {
    if(!function_exists('ws_sms_send_now')||!function_exists('ws_sms_key'))return false;
    $c=nzotp_legacy_settings(); return nzotp_legacy_pattern()!=='' && ws_sms_key($c)!=='';
}
function nzotp_direct_ready(): bool {
    return nzotp_env('IPPANEL_API_KEY')!=='' && nzotp_env('IPPANEL_PATTERN_CODE')!=='' && nzotp_env('IPPANEL_FROM_NUMBER')!=='';
}
function nzotp_ready(): bool { return nzotp_legacy_ready() || nzotp_direct_ready(); }
function nzotp_phone(string $phone): string {
    $p=preg_replace('/\D+/','',$phone)??'';
    if(str_starts_with($p,'98')&&strlen($p)===12)$p='0'.substr($p,2);
    if(str_starts_with($p,'9')&&strlen($p)===10)$p='0'.$p;
    return $p;
}
function nzotp_e164(string $phone): string { return '+98'.substr($phone,1); }

function nzotp_send_pattern(string $phone, string $code): void {
    if (nzotp_legacy_ready()) {
        $result=ws_sms_send_now(nzotp_legacy_settings_file(),$phone,nzotp_legacy_pattern(),['code'=>$code]);
        if(!empty($result['ok']))return;
        throw new RuntimeException('ارسال کد ورود انجام نشد. کمی بعد دوباره تلاش کنید.');
    }
    if (!nzotp_direct_ready() || !function_exists('curl_init')) throw new RuntimeException('سرویس پیامک ورود هنوز روی سرور پیکربندی نشده است.');
    $paramKey=nzotp_env('IPPANEL_PATTERN_PARAM'); if($paramKey==='')$paramKey='code';
    $payload=[
        'sending_type'=>'pattern',
        'from_number'=>nzotp_env('IPPANEL_FROM_NUMBER'),
        'code'=>nzotp_env('IPPANEL_PATTERN_CODE'),
        'recipients'=>[nzotp_e164($phone)],
        'params'=>[$paramKey=>$code],
    ];
    $ch=curl_init('https://edge.ippanel.com/v1/api/send');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: '.nzotp_env('IPPANEL_API_KEY')],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw===false || $status<200 || $status>=300) throw new RuntimeException('ارسال کد ورود انجام نشد. کمی بعد دوباره تلاش کنید.');
    $json=json_decode((string)$raw,true);
    if (is_array($json) && isset($json['meta']['status']) && !$json['meta']['status']) throw new RuntimeException('سرویس پیامک ارسال کد را نپذیرفت.');
}

function nzotp_request(): never {
    nzc_session_open();
    if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    $phone=nzotp_phone((string)($_POST['phone']??''));
    if(!preg_match('/^09\d{9}$/',$phone)) throw new RuntimeException('شماره موبایل معتبر وارد کنید.');
    $code=(string)random_int(10000,99999);
    nzotp_send_pattern($phone,$code);
    $_SESSION['otp']=['phone'=>$phone,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0,'sent_at'=>time()];
    nzc_flash('کد ورود برای شما پیامک شد.','ok');
    nzc_redirect('/nz?page=login&step=verify');
}

function nzotp_verify(): never {
    nzc_session_open();
    if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    $otp=$_SESSION['otp']??[]; $code=trim((string)($_POST['code']??''));
    if (!is_array($otp) || empty($otp['hash']) || time()>(int)($otp['expires']??0)) { unset($_SESSION['otp']); throw new RuntimeException('کد ورود منقضی شده است. دوباره کد بگیرید.'); }
    $attempts=(int)($otp['attempts']??0)+1; $_SESSION['otp']['attempts']=$attempts;
    if ($attempts>6) { unset($_SESSION['otp']); throw new RuntimeException('تعداد تلاش ناموفق زیاد بود. دوباره کد ورود بگیرید.'); }
    if (!preg_match('/^\d{5}$/',$code) || !password_verify($code,(string)$otp['hash'])) throw new RuntimeException('کد ورود اشتباه است.');
    $phone=(string)$otp['phone'];
    $uid=nzc_db_write(function(&$db)use($phone){
        foreach($db['users'] as &$u){if(($u['phone']??'')===$phone){$u['last_login']=date('c');return(string)$u['id'];}} unset($u);
        $id=nzc_uid('u'); $db['users'][]=['id'=>$id,'phone'=>$phone,'name'=>'کاربر نیازستان','role'=>'user','created_at'=>date('c'),'last_login'=>date('c')]; return$id;
    });
    session_regenerate_id(true); $_SESSION['uid']=$uid; unset($_SESSION['otp']); nzc_flash('خوش آمدید 👋','ok'); nzc_redirect('/nz');
}
