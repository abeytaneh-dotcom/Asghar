<?php
declare(strict_types=1);

const NZAUTH_RESEND_SECONDS=60;
const NZAUTH_CODE_TTL=300;
const NZAUTH_ELEVATED_SECONDS=1800;
const NZAUTH_MAX_ATTEMPTS=5;

function nzauth_fp(): string { return hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'').'|niyazestan-admin'); }
function nzauth_phone_normalize(string $raw): string {
    $phone=function_exists('nzotp_phone')?nzotp_phone($raw):(preg_replace('/\D+/','',$raw)??'');
    return $phone;
}
function nzauth_phone_valid(string $phone): bool { return preg_match('/^09\d{9}$/',$phone)===1; }
function nzauth_is_admin_user(?array $user): bool {
    if(!$user)return false;
    return in_array((string)($user['role']??''),['admin','superadmin'],true)||!empty($user['is_admin']);
}
function nzauth_is_superadmin(?array $user): bool { return $user!==null&&(string)($user['role']??'')==='superadmin'; }
function nzauth_find_user_by_phone(array $db,string $phone): ?array {
    foreach($db['users']??[]as$u){
        if(nzauth_phone_normalize((string)($u['phone']??''))===$phone)return$u;
    }
    return null;
}
function nzauth_admin_count(array $db): int {
    $n=0;foreach($db['users']??[]as$u)if(nzauth_is_admin_user($u))$n++;return$n;
}
function nzauth_is_elevated(array $user): bool {
    if(!nzauth_is_admin_user($user))return false;
    nzc_session_open();$row=$_SESSION['nz_admin_stepup']??null;
    return is_array($row)&&($row['uid']??'')===($user['id']??'')&&(int)($row['until']??0)>time()&&hash_equals((string)($row['fp']??''),nzauth_fp());
}
function nzauth_admin_user(): array {
    nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);
    if(!$u||!nzauth_is_admin_user($u))throw new RuntimeException('دسترسی مدیریت فعال نیست.');
    return$u;
}

function nzauth_rate_file(): string {return nzstore_dir().'/admin-stepup-rate.json';}
function nzauth_rate_key(string $phone): string {return hash('sha256','admin-otp|'.$phone);}
function nzauth_rate_data(): array {
    $raw=@file_get_contents(nzauth_rate_file());$data=json_decode($raw?:'',true);return is_array($data)?$data:[];
}
function nzauth_rate_remaining(string $phone): int {
    if(!nzauth_phone_valid($phone))return 0;$data=nzauth_rate_data();$sent=(int)($data[nzauth_rate_key($phone)]??0);return max(0,NZAUTH_RESEND_SECONDS-(time()-$sent));
}
function nzauth_send_code(string $phone,string $code): void {
    $file=nzauth_rate_file();$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('امکان کنترل فاصله ارسال کد مدیریت وجود ندارد.');
    try{
        if(!@flock($fh,LOCK_EX))throw new RuntimeException('امکان کنترل فاصله ارسال کد مدیریت وجود ندارد.');
        rewind($fh);$raw=stream_get_contents($fh);$data=json_decode($raw?:'',true);if(!is_array($data))$data=[];$key=nzauth_rate_key($phone);$sent=(int)($data[$key]??0);$wait=NZAUTH_RESEND_SECONDS-(time()-$sent);
        if($wait>0)throw new RuntimeException('برای ارسال مجدد کد مدیریت '.$wait.' ثانیه صبر کنید.');
        if(!function_exists('nzotp_ready')||!nzotp_ready())throw new RuntimeException('سرویس پیامک ورود هنوز روی سرور پیکربندی نشده است.');
        nzotp_send_pattern($phone,$code);
        $now=time();foreach($data as$k=>$v)if($now-(int)$v>3600)unset($data[$k]);$data[$key]=$now;
        $payload=json_encode($data,JSON_UNESCAPED_SLASHES)?:'{}';rewind($fh);ftruncate($fh,0);fwrite($fh,$payload);fflush($fh);@chmod($file,0640);
    }finally{@flock($fh,LOCK_UN);@fclose($fh);}
}
function nzauth_request(): never {
    nzc_session_open();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    $phone=nzauth_phone_normalize((string)($_POST['phone']??($_SESSION['nz_admin_stepup_pending']['phone']??'')));
    if(!nzauth_phone_valid($phone))throw new RuntimeException('شماره موبایل معتبر وارد کنید.');
    $db=nzc_db_read();$admins=nzauth_admin_count($db);$u=nzauth_find_user_by_phone($db,$phone);$bootstrap=$admins===0;
    if(!$bootstrap&&(!$u||!nzauth_is_admin_user($u)))throw new RuntimeException('این شماره مجوز ورود به مدیریت را ندارد.');
    $code=(string)random_int(10000,99999);nzauth_send_code($phone,$code);
    $_SESSION['nz_admin_stepup_pending']=['uid'=>(string)($u['id']??''),'phone'=>$phone,'bootstrap'=>$bootstrap,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+NZAUTH_CODE_TTL,'attempts'=>0,'sent_at'=>time(),'fp'=>nzauth_fp()];
    nzc_flash('کد ورود مدیریت پیامک شد.','ok');nzc_redirect('/nz?page=admin&step=verify');
}
function nzauth_claim_first_admin(string $phone): string {
    return (string)nzc_db_write(function(&$db)use($phone){
        $existingAdmins=[];foreach($db['users']??[]as$u)if(nzauth_is_admin_user($u))$existingAdmins[]=$u;
        if($existingAdmins){$existing=nzauth_find_user_by_phone($db,$phone);if(!$existing||!nzauth_is_admin_user($existing))throw new RuntimeException('مدیرکل قبلاً ثبت شده است.');return(string)$existing['id'];}
        $uid='';foreach($db['users']??[]as&$row){if(nzauth_phone_normalize((string)($row['phone']??''))!==$phone)continue;$uid=(string)($row['id']??'');$row['phone']=$phone;$row['role']='superadmin';$row['is_admin']=true;$row['admin_granted_at']=nzc_now();$row['admin_grant_source']='first_sms_claim';$row['last_login']=nzc_now();break;}unset($row);
        if($uid===''){$uid=nzc_uid('u');$db['users'][]=['id'=>$uid,'phone'=>$phone,'name'=>'مدیر نیازستان','role'=>'superadmin','is_admin'=>true,'created_at'=>nzc_now(),'last_login'=>nzc_now(),'admin_granted_at'=>nzc_now(),'admin_grant_source'=>'first_sms_claim'];}
        return$uid;
    });
}
function nzauth_verify(): never {
    nzc_session_open();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    $pending=$_SESSION['nz_admin_stepup_pending']??null;$code=trim((string)($_POST['code']??''));
    if(!is_array($pending)||(int)($pending['expires']??0)<time()||!hash_equals((string)($pending['fp']??''),nzauth_fp())){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('کد مدیریت منقضی شده است. دوباره کد بگیرید.');}
    $attempts=(int)($pending['attempts']??0)+1;$_SESSION['nz_admin_stepup_pending']['attempts']=$attempts;if($attempts>NZAUTH_MAX_ATTEMPTS){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('تعداد تلاش برای کد مدیریت بیش از حد مجاز بود.');}
    if(!preg_match('/^\d{5}$/',$code)||!password_verify($code,(string)$pending['hash']))throw new RuntimeException('کد مدیریت اشتباه است.');
    $phone=nzauth_phone_normalize((string)($pending['phone']??''));if(!nzauth_phone_valid($phone))throw new RuntimeException('هویت مدیریت معتبر نیست.');
    $uid='';if(!empty($pending['bootstrap'])){$uid=nzauth_claim_first_admin($phone);}else{$db=nzc_db_read();$u=nzauth_find_user_by_phone($db,$phone);if(!$u||!nzauth_is_admin_user($u))throw new RuntimeException('مجوز مدیریت این شماره لغو شده است.');$uid=(string)$u['id'];}
    session_regenerate_id(true);$_SESSION['uid']=$uid;$_SESSION['nz_admin_stepup']=['uid'=>$uid,'until'=>time()+NZAUTH_ELEVATED_SECONDS,'fp'=>nzauth_fp()];unset($_SESSION['nz_admin_stepup_pending']);
    nzc_audit($uid,'admin_stepup_verified','admin',$uid);nzc_flash('ورود امن مدیریت انجام شد.','ok');nzc_redirect('/nz?page=admin');
}
function nzauth_change_admin_access(string $action): never {
    $admin=nzauth_admin_user();if(!nzauth_is_elevated($admin))throw new RuntimeException('ابتدا ورود امن مدیریت را تأیید کنید.');if(!nzauth_is_superadmin($admin))throw new RuntimeException('فقط مدیرکل می‌تواند دسترسی مدیران را تغییر دهد.');if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');
    $uid=trim((string)($_POST['user_id']??''));if($uid===''||$uid===(string)($admin['id']??''))throw new RuntimeException('کاربر انتخاب‌شده معتبر نیست.');
    nzc_db_write(function(&$db)use($uid,$action,$admin){$found=false;foreach($db['users']??[]as&$row){if((string)($row['id']??'')!==$uid)continue;$found=true;if($action==='admin_grant_access'){$row['role']='admin';$row['is_admin']=true;$row['admin_granted_at']=nzc_now();$row['admin_granted_by']=$admin['id'];}else{if((string)($row['role']??'')==='superadmin')throw new RuntimeException('دسترسی مدیرکل قابل حذف نیست.');$row['role']='user';$row['is_admin']=false;$row['admin_revoked_at']=nzc_now();$row['admin_revoked_by']=$admin['id'];}break;}unset($row);if(!$found)throw new RuntimeException('کاربر پیدا نشد.');});
    nzc_audit($admin,$action,'user',$uid);nzc_flash($action==='admin_grant_access'?'دسترسی مدیریت کاربر فعال شد.':'دسترسی مدیریت کاربر لغو شد.','ok');nzc_redirect('/nz?page=admin&tab=users');
}
function nzauth_logout_stepup(): never {
    $accessAction=(string)($_POST['admin_access_action']??'');if(in_array($accessAction,['admin_grant_access','admin_revoke_access'],true))nzauth_change_admin_access($accessAction);
    $u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');unset($_SESSION['nz_admin_stepup'],$_SESSION['nz_admin_stepup_pending']);nzc_audit($u,'admin_stepup_locked','admin',(string)$u['id']);nzc_redirect('/nz?page=admin');
}
function nzauth_handle_post(string $action): never {
    if($action==='admin_stepup_request')nzauth_request();if($action==='admin_stepup_verify')nzauth_verify();if($action==='admin_stepup_lock')nzauth_logout_stepup();throw new RuntimeException('درخواست امنیت مدیریت نامعتبر است.');
}
function nzauth_require_for_action(string $action): void {
    if(!in_array($action,['approve_post','reject_post','resolve_report','admin_block_user','admin_unblock_user','admin_send_notice','admin_set_post_status','admin_toggle_featured','admin_resolve_report','admin_reopen_report','approve_service_payment','reject_service_payment'],true))return;
    $u=nzauth_admin_user();if(!nzauth_is_elevated($u)){nzc_flash('برای عملیات مدیریت ابتدا کد پیامکی را وارد کنید.','warn');nzc_redirect('/nz?page=admin');}
}
function nzauth_challenge_html(): string {
    nzc_session_open();$csrf=nzc_e($_SESSION['csrf']??'');$pending=$_SESSION['nz_admin_stepup_pending']??null;$valid=is_array($pending)&&(int)($pending['expires']??0)>time()&&!empty($pending['phone'])&&hash_equals((string)($pending['fp']??''),nzauth_fp());$db=nzc_db_read();$bootstrap=nzauth_admin_count($db)===0;
    $h='<main class="page page-narrow"><div class="surface"><h1>ورود مدیریت نیازستان</h1><p class="lead">'.($bootstrap?'برای راه‌اندازی مدیریت، اولین شماره‌ای که با کد پیامکی تأیید شود مدیرکل خواهد شد.':'شماره موبایل دارای دسترسی مدیریت را وارد کنید.').'</p>';
    if($valid){$masked=substr((string)$pending['phone'],0,4).'***'.substr((string)$pending['phone'],-4);$h.='<p class="subtle">کد برای '.nzc_e($masked).' ارسال شده است.</p><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_verify"><div class="field"><label>کد ۵ رقمی</label><input name="code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required autocomplete="one-time-code" autofocus></div><button class="btn primary" style="width:100%">تأیید کد و ورود</button></form>';$phone=(string)$pending['phone'];$wait=nzauth_rate_remaining($phone);$label=$wait>0?'ارسال مجدد کد ('.$wait.' ثانیه)':'ارسال مجدد کد';$disabled=$wait>0?' disabled':'';$h.='<form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_request"><input type="hidden" name="phone" value="'.nzc_e($phone).'"><button id="admin-resend" class="btn ghost" style="width:100%" data-wait="'.$wait.'"'.$disabled.'>'.$label.'</button></form>';}else{$h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_request"><div class="field"><label>شماره موبایل</label><input name="phone" inputmode="tel" autocomplete="tel" placeholder="09xxxxxxxxx" pattern="09[0-9]{9}" maxlength="11" required autofocus></div><button class="btn primary" style="width:100%">ارسال کد ورود مدیریت</button></form>';}
    $h.='<p class="subtle" style="margin-top:10px">ارسال کد حداکثر هر ۶۰ ثانیه یک‌بار است و هر کد ۵ دقیقه اعتبار دارد.</p><script>(()=>{const b=document.getElementById("admin-resend");if(!b)return;let s=Number(b.dataset.wait||0);if(s<=0)return;const t=setInterval(()=>{s--;if(s<=0){clearInterval(t);b.disabled=false;b.textContent="ارسال مجدد کد";}else b.textContent="ارسال مجدد کد ("+s+" ثانیه)";},1000);})();</script>';
    return$h.'</div></main>';
}
function nzauth_admin_access_html(array $db,array $admin): string {
    if(!nzauth_is_superadmin($admin))return'';$csrf=nzc_e($_SESSION['csrf']??'');$options='';$current='';
    foreach($db['users']??[]as$u){$uid=(string)($u['id']??'');if($uid===''||$uid===(string)($admin['id']??''))continue;$label=trim((string)($u['name']??''));$phone=(string)($u['phone']??'');$label=($label!==''?$label.' - ':'').$phone;if(nzauth_is_admin_user($u)){$current.='<div class="nzadmin-row"><div class="nzadmin-grow"><b>'.nzc_e($label).'</b><small>مدیر مجاز</small></div>'.((string)($u['role']??'')==='superadmin'?'':'<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_lock"><input type="hidden" name="admin_access_action" value="admin_revoke_access"><input type="hidden" name="user_id" value="'.nzc_e($uid).'"><button class="btn ghost">لغو دسترسی</button></form>').'</div>';}else{$options.='<option value="'.nzc_e($uid).'">'.nzc_e($label).'</option>';}}
    $grant=$options!==''?'<form method="post" class="nzadmin-search"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_lock"><input type="hidden" name="admin_access_action" value="admin_grant_access"><select name="user_id" required>'.$options.'</select><button class="btn primary">اعطای دسترسی مدیر</button></form>':'<p class="subtle">کاربر دیگری برای اعطای دسترسی وجود ندارد.</p>';
    return'<section class="surface" style="margin-bottom:14px"><h3>دسترسی مدیران</h3><p class="subtle">فقط مدیرکل می‌تواند مدیر جدید اضافه یا دسترسی او را لغو کند.</p>'.$grant.$current.'</section>';
}
function nzauth_replace_admin_main(string $html,string $replacement): string {
    foreach(['/<main class="page nzc-admin">.*?<\/main>/su','/<main class="page page-narrow">.*?<\/main>/su','/<main class="main">.*?<\/main>/su']as$pattern)if(preg_match($pattern,$html))return preg_replace($pattern,$replacement,$html,1)??$html;return str_replace('<nav class="mobile-nav">',$replacement.'<nav class="mobile-nav">',$html);
}
function nzauth_enhance(string $html): string {
    if(($_GET['page']??'')!=='admin')return$html;nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);if(!$u||!nzauth_is_admin_user($u)||!nzauth_is_elevated($u))return nzauth_replace_admin_main($html,nzauth_challenge_html());$csrf=nzc_e($_SESSION['csrf']??'');$lock='<form method="post" style="margin:0 0 12px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_lock"><button class="btn ghost">قفل‌کردن مدیریت</button></form>';$access=nzauth_admin_access_html($db,$u);return str_replace('<main class="page nzc-admin">','<main class="page nzc-admin">'.$lock.$access,$html);
}
