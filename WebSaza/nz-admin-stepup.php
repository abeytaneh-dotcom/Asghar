<?php
declare(strict_types=1);

const NZAUTH_ADMIN_PHONE_SHA256='048a6663bd85415947bb12a344b8d8fba3983264738f28049d7635c3abac8188';
const NZAUTH_RESEND_SECONDS=60;
const NZAUTH_CODE_TTL=300;
const NZAUTH_ELEVATED_SECONDS=1800;

function nzauth_fp(): string { return hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'').'|niyazestan-admin'); }
function nzauth_phone(array $user): string {
    $raw=(string)($user['phone']??'');
    return function_exists('nzotp_phone')?nzotp_phone($raw):(preg_replace('/\D+/','',$raw)??'');
}
function nzauth_is_fixed_admin(?array $user): bool {
    if(!$user)return false;
    $phone=nzauth_phone($user);
    return preg_match('/^09\d{9}$/',$phone)===1&&hash_equals(NZAUTH_ADMIN_PHONE_SHA256,hash('sha256',$phone));
}
function nzauth_is_elevated(array $user): bool {
    if(!nzauth_is_fixed_admin($user))return false;
    nzc_session_open();$row=$_SESSION['nz_admin_stepup']??null;
    return is_array($row)
        && ($row['uid']??'')===($user['id']??'')
        && (int)($row['until']??0)>time()
        && hash_equals((string)($row['fp']??''),nzauth_fp());
}
function nzauth_admin_user(): array {
    nzc_session_open();$db=nzc_db_read();$u=nzc_require_user($db);
    if(!nzauth_is_fixed_admin($u))throw new RuntimeException('دسترسی مدیریت برای این حساب فعال نیست.');
    return$u;
}
function nzauth_rate_file(): string {return nzstore_dir().'/admin-stepup-rate.json';}
function nzauth_rate_remaining(): int {
    $raw=@file_get_contents(nzauth_rate_file());$data=json_decode($raw?:'',true);$sent=is_array($data)?(int)($data['sent_at']??0):0;
    return max(0,NZAUTH_RESEND_SECONDS-(time()-$sent));
}
function nzauth_send_code(string $phone,string $code): void {
    $file=nzauth_rate_file();$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('امکان کنترل فاصله ارسال کد مدیریت وجود ندارد.');
    try{
        if(!@flock($fh,LOCK_EX))throw new RuntimeException('امکان کنترل فاصله ارسال کد مدیریت وجود ندارد.');
        rewind($fh);$raw=stream_get_contents($fh);$data=json_decode($raw?:'',true);$sent=is_array($data)?(int)($data['sent_at']??0):0;$wait=NZAUTH_RESEND_SECONDS-(time()-$sent);
        if($wait>0)throw new RuntimeException('برای ارسال مجدد کد مدیریت '.$wait.' ثانیه صبر کنید.');
        nzotp_send_pattern($phone,$code);
        $payload=json_encode(['sent_at'=>time()],JSON_UNESCAPED_SLASHES)?:'{}';rewind($fh);ftruncate($fh,0);fwrite($fh,$payload);fflush($fh);@chmod($file,0640);
    }finally{@flock($fh,LOCK_UN);@fclose($fh);}
}
function nzauth_make_only_fixed_admin(string $uid): void {
    nzc_db_write(function(&$db)use($uid){
        foreach($db['users']??[]as&$row){
            $isTarget=((string)($row['id']??'')===$uid)&&nzauth_is_fixed_admin($row);
            if($isTarget){$row['role']='superadmin';$row['is_admin']=true;$row['admin_granted_at']=nzc_now();$row['admin_grant_source']='fixed_phone_sms_stepup';continue;}
            if(in_array((string)($row['role']??''),['admin','superadmin'],true)||!empty($row['is_admin'])){$row['role']='user';$row['is_admin']=false;$row['admin_revoked_at']=nzc_now();}
        }unset($row);
    });
}
function nzauth_request(): never {
    $u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');$phone=nzauth_phone($u);if(!nzauth_is_fixed_admin($u))throw new RuntimeException('دسترسی مدیریت برای این حساب فعال نیست.');
    $code=(string)random_int(10000,99999);nzauth_send_code($phone,$code);$_SESSION['nz_admin_stepup_pending']=['uid'=>$u['id'],'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+NZAUTH_CODE_TTL,'attempts'=>0,'sent_at'=>time(),'fp'=>nzauth_fp()];nzc_audit($u,'admin_stepup_request','admin',(string)$u['id']);nzc_flash('کد ورود مدیریت پیامک شد.','ok');nzc_redirect('/nz?page=admin&step=verify');
}
function nzauth_verify(): never {
    $u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');$pending=$_SESSION['nz_admin_stepup_pending']??null;$code=trim((string)($_POST['code']??''));if(!is_array($pending)||($pending['uid']??'')!==$u['id']||(int)($pending['expires']??0)<time()||!hash_equals((string)($pending['fp']??''),nzauth_fp())){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('کد مدیریت منقضی شده است. دوباره کد بگیرید.');}$attempts=(int)($pending['attempts']??0)+1;$_SESSION['nz_admin_stepup_pending']['attempts']=$attempts;if($attempts>5){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('تعداد تلاش برای کد مدیریت بیش از حد مجاز بود.');}if(!preg_match('/^\d{5}$/',$code)||!password_verify($code,(string)$pending['hash']))throw new RuntimeException('کد مدیریت اشتباه است.');
    nzauth_make_only_fixed_admin((string)$u['id']);$_SESSION['nz_admin_stepup']=['uid'=>$u['id'],'until'=>time()+NZAUTH_ELEVATED_SECONDS,'fp'=>nzauth_fp()];unset($_SESSION['nz_admin_stepup_pending']);nzc_audit($u,'admin_stepup_verified','admin',(string)$u['id']);nzc_flash('ورود امن مدیریت انجام شد.','ok');nzc_redirect('/nz?page=admin');
}
function nzauth_logout_stepup(): never {$u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');unset($_SESSION['nz_admin_stepup'],$_SESSION['nz_admin_stepup_pending']);nzc_audit($u,'admin_stepup_locked','admin',(string)$u['id']);nzc_redirect('/nz?page=admin');}
function nzauth_handle_post(string $action): never {if($action==='admin_stepup_request')nzauth_request();if($action==='admin_stepup_verify')nzauth_verify();if($action==='admin_stepup_lock')nzauth_logout_stepup();throw new RuntimeException('درخواست امنیت مدیریت نامعتبر است.');}
function nzauth_require_for_action(string $action): void {
    if(!in_array($action,['approve_post','reject_post','resolve_report','admin_block_user','admin_unblock_user','admin_send_notice','admin_set_post_status','admin_toggle_featured','admin_resolve_report','admin_reopen_report','approve_service_payment','reject_service_payment'],true))return;
    $u=nzauth_admin_user();if(!nzauth_is_elevated($u)){nzc_flash('برای عملیات مدیریت ابتدا کد پیامکی را وارد کنید.','warn');nzc_redirect('/nz?page=admin');}
}
function nzauth_challenge_html(array $u): string {
    $csrf=nzc_e($_SESSION['csrf']??'');$pending=$_SESSION['nz_admin_stepup_pending']??null;$valid=is_array($pending)&&(int)($pending['expires']??0)>time()&&($pending['uid']??'')===($u['id']??'');$wait=nzauth_rate_remaining();
    $h='<main class="page page-narrow"><div class="surface"><h1>ورود مدیریت نیازستان</h1><p class="lead">ورود مدیریت فقط برای شماره مدیر ثبت‌شده فعال است و با کد پیامکی انجام می‌شود.</p><div class="nzauth-phone">شماره قابل تغییر یا واردکردن نیست.</div>';
    if($valid)$h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_verify"><div class="field"><label>کد ۵ رقمی</label><input name="code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required autocomplete="one-time-code" autofocus></div><button class="btn primary" style="width:100%">تأیید کد و ورود</button></form>';
    $label=$wait>0?'ارسال مجدد کد ('.$wait.' ثانیه)':'ارسال کد ورود مدیریت';$disabled=$wait>0?' disabled':'';
    $h.='<form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_request"><button id="admin-resend" class="btn '.($valid?'ghost':'primary').'" style="width:100%" data-wait="'.$wait.'"'.$disabled.'>'.$label.'</button></form>';
    $h.='<p class="subtle" style="margin-top:10px">حداکثر هر ۶۰ ثانیه یک کد ارسال می‌شود و هر کد ۵ دقیقه اعتبار دارد.</p><script>(()=>{const b=document.getElementById("admin-resend");if(!b)return;let s=Number(b.dataset.wait||0);if(s<=0)return;const t=setInterval(()=>{s--;if(s<=0){clearInterval(t);b.disabled=false;b.textContent="ارسال کد ورود مدیریت";}else b.textContent="ارسال مجدد کد ("+s+" ثانیه)";},1000);})();</script>';
    return$h.'</div></main>';
}
function nzauth_denied_html(bool $loggedIn): string {
    return'<main class="page page-narrow"><div class="surface"><h1>مدیریت نیازستان</h1><p class="lead">'.($loggedIn?'این حساب مجوز ورود به مدیریت را ندارد.':'برای ورود مدیریت ابتدا با حساب مدیر وارد شوید.').'</p>'.(!$loggedIn?'<a class="btn primary" style="width:100%" href="/nz?page=login">ورود به حساب</a>':'').'</div></main>';
}
function nzauth_replace_admin_main(string $html,string $replacement): string {
    foreach(['/<main class="page nzc-admin">.*?<\/main>/su','/<main class="page page-narrow">.*?<\/main>/su','/<main class="main">.*?<\/main>/su']as$pattern)if(preg_match($pattern,$html))return preg_replace($pattern,$replacement,$html,1)??$html;
    return str_replace('<nav class="mobile-nav">',$replacement.'<nav class="mobile-nav">',$html);
}
function nzauth_enhance(string $html): string {
    if(($_GET['page']??'')!=='admin')return$html;
    nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);
    if(!$u||!nzauth_is_fixed_admin($u))return nzauth_replace_admin_main($html,nzauth_denied_html((bool)$u));
    if(!nzauth_is_elevated($u))return nzauth_replace_admin_main($html,nzauth_challenge_html($u));
    $csrf=nzc_e($_SESSION['csrf']??'');$lock='<form method="post" style="margin:0 0 12px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_lock"><button class="btn ghost">قفل‌کردن مدیریت</button></form>';
    return str_replace('<main class="page nzc-admin">','<main class="page nzc-admin">'.$lock,$html);
}
