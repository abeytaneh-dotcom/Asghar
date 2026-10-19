<?php
declare(strict_types=1);

function nzauth_fp(): string { return hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'').'|niyazestan-admin'); }
function nzauth_is_elevated(array $user): bool {
    nzc_session_open();$row=$_SESSION['nz_admin_stepup']??null;
    return is_array($row)
        && ($row['uid']??'')===($user['id']??'')
        && (int)($row['until']??0)>time()
        && hash_equals((string)($row['fp']??''),nzauth_fp());
}
function nzauth_admin_user(): array {
    nzc_session_open();$db=nzc_db_read();$u=nzc_require_user($db);if(!nzc_is_admin($u))throw new RuntimeException('دسترسی مدیریت برای این حساب فعال نیست.');return$u;
}
function nzauth_request(): never {
    $u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');$phone=(string)($u['phone']??'');if(!preg_match('/^09\d{9}$/',$phone))throw new RuntimeException('شماره مدیر برای تأیید امنیتی معتبر نیست.');
    $prev=$_SESSION['nz_admin_stepup_pending']??[];$sent=(int)(is_array($prev)?($prev['sent_at']??0):0);if($sent>0&&time()-$sent<60)throw new RuntimeException('برای ارسال مجدد کد امنیتی ۶۰ ثانیه صبر کنید.');
    $code=(string)random_int(10000,99999);nzotp_send_pattern($phone,$code);$_SESSION['nz_admin_stepup_pending']=['uid'=>$u['id'],'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0,'sent_at'=>time(),'fp'=>nzauth_fp()];nzc_audit($u,'admin_stepup_request','admin',(string)$u['id']);nzc_flash('کد تأیید امنیتی مدیریت پیامک شد.','ok');nzc_redirect('/nz?page=admin&step=verify');
}
function nzauth_verify(): never {
    $u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');$pending=$_SESSION['nz_admin_stepup_pending']??null;$code=trim((string)($_POST['code']??''));if(!is_array($pending)||($pending['uid']??'')!==$u['id']||(int)($pending['expires']??0)<time()||!hash_equals((string)($pending['fp']??''),nzauth_fp())){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('کد امنیتی منقضی شده است. دوباره کد بگیرید.');}$attempts=(int)($pending['attempts']??0)+1;$_SESSION['nz_admin_stepup_pending']['attempts']=$attempts;if($attempts>5){unset($_SESSION['nz_admin_stepup_pending']);throw new RuntimeException('تعداد تلاش امنیتی بیش از حد مجاز بود.');}if(!preg_match('/^\d{5}$/',$code)||!password_verify($code,(string)$pending['hash']))throw new RuntimeException('کد امنیتی مدیریت اشتباه است.');$_SESSION['nz_admin_stepup']=['uid'=>$u['id'],'until'=>time()+12*3600,'fp'=>nzauth_fp()];unset($_SESSION['nz_admin_stepup_pending']);nzc_audit($u,'admin_stepup_verified','admin',(string)$u['id']);nzc_flash('تأیید امنیتی مدیریت انجام شد.','ok');nzc_redirect('/nz?page=admin');
}
function nzauth_logout_stepup(): never {$u=nzauth_admin_user();if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');unset($_SESSION['nz_admin_stepup'],$_SESSION['nz_admin_stepup_pending']);nzc_audit($u,'admin_stepup_locked','admin',(string)$u['id']);nzc_redirect('/nz?page=admin');}
function nzauth_handle_post(string $action): never {if($action==='admin_stepup_request')nzauth_request();if($action==='admin_stepup_verify')nzauth_verify();if($action==='admin_stepup_lock')nzauth_logout_stepup();throw new RuntimeException('درخواست امنیت مدیریت نامعتبر است.');}
function nzauth_require_for_action(string $action): void {
    if(!in_array($action,['approve_post','reject_post','resolve_report','admin_block_user','admin_unblock_user','approve_service_payment','reject_service_payment'],true))return;$u=nzauth_admin_user();if(!nzauth_is_elevated($u)){nzc_flash('برای عملیات حساس مدیریت ابتدا کد تأیید امنیتی را وارد کنید.','warn');nzc_redirect('/nz?page=admin');}
}
function nzauth_challenge_html(array $u): string {
    $csrf=nzc_e($_SESSION['csrf']??'');$pending=$_SESSION['nz_admin_stepup_pending']??null;$valid=is_array($pending)&&(int)($pending['expires']??0)>time()&&($pending['uid']??'')===($u['id']??'');$phone=(string)($u['phone']??'');$masked=mb_strlen($phone)>=7?mb_substr($phone,0,4).'***'.mb_substr($phone,-4):'شماره مدیر';
    $h='<main class="page page-narrow"><div class="surface"><h1>تأیید امنیتی مدیریت</h1><p class="lead">برای مشاهده اطلاعات حساس کاربران و انجام عملیات مدیریتی، یک تأیید مجدد لازم است.</p><div class="nzauth-phone">کد به '.nzc_e($masked).' ارسال می‌شود.</div>';
    if($valid)$h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_verify"><div class="field"><label>کد ۵ رقمی</label><input name="code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required autocomplete="one-time-code"></div><button class="btn primary" style="width:100%">تأیید و ورود به مدیریت</button></form><form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_request"><button class="btn ghost" style="width:100%">ارسال مجدد کد</button></form>';
    else $h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_request"><button class="btn primary" style="width:100%">دریافت کد امنیتی مدیریت</button></form>';
    return$h.'</div></main>';
}
function nzauth_enhance(string $html): string {
    if(($_GET['page']??'')!=='admin')return$html;nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);if(!$u||!nzc_is_admin($u))return$html;if(!nzauth_is_elevated($u)&&function_exists('nzd_replace_main'))return nzd_replace_main($html,nzauth_challenge_html($u));$csrf=nzc_e($_SESSION['csrf']??'');$lock='<form method="post" style="margin:0 0 12px"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="admin_stepup_lock"><button class="btn ghost">قفل‌کردن دسترسی حساس مدیریت</button></form>';$html=str_replace('<main class="page nzc-admin">','<main class="page nzc-admin">'.$lock,$html);return$html;
}
