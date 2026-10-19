<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/nz-session-bootstrap.php';
require_once dirname(__DIR__).'/nz-community.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'");

const NZ_ADMIN_CLAIM_HASH = 'bcfddda02c61cd007f39eeb77702f7a6c52a3a0a2abfc15eae2f59550356661c';
const NZ_ADMIN_CLAIM_EXPIRES_AT = 1792400856;

function nzclaim_page(string $title,string $message,int $status=200): never {
    http_response_code($status);
    $t=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
    $m=htmlspecialchars($message,ENT_QUOTES,'UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$t.'</title><style>body{margin:0;background:#f5f6f8;color:#17191d;font-family:Tahoma,Arial,sans-serif}.box{max-width:520px;margin:12vh auto;background:#fff;border:1px solid #e4e7eb;border-radius:20px;padding:24px;box-shadow:0 12px 38px rgba(0,0,0,.08)}h1{font-size:20px;margin:0 0 12px}p{line-height:2;margin:0}.muted{color:#68707a;font-size:13px;margin-top:10px}</style><div class="box"><h1>'.$t.'</h1><p>'.$m.'</p><p class="muted">می‌توانید به نیازستان برگردید.</p></div></html>';
    exit;
}

$db=nzc_db_read();
$user=nzc_current_user($db);
if(!$user){
    nzc_flash('برای فعال‌سازی مدیریت ابتدا وارد همان حساب نیازستان شوید.','warn');
    nzc_redirect('/nz?page=login');
}
if(nzc_is_admin($user)) nzc_redirect('/nz?page=admin');
if(time()>NZ_ADMIN_CLAIM_EXPIRES_AT) nzclaim_page('لینک منقضی شده','زمان استفاده از لینک فعال‌سازی مدیریت به پایان رسیده است.',410);

$usedFile=nzstore_dir().'/admin-claim-used.json';
if(is_file($usedFile)) nzclaim_page('لینک استفاده شده','این لینک یک‌بارمصرف قبلاً استفاده شده است.',410);

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>فعال‌سازی مدیریت نیازستان</title><style>body{margin:0;background:#f5f6f8;color:#17191d;font-family:Tahoma,Arial,sans-serif}.box{max-width:520px;margin:12vh auto;background:#fff;border:1px solid #e4e7eb;border-radius:20px;padding:24px;box-shadow:0 12px 38px rgba(0,0,0,.08)}h1{font-size:20px;margin:0 0 12px}p{line-height:2;margin:0}.muted{color:#68707a;font-size:13px;margin-top:10px}</style><div class="box"><h1>فعال‌سازی مدیریت نیازستان</h1><p id="msg">در حال بررسی حساب واردشده…</p><p class="muted">این عملیات فقط روی حسابی که همین حالا در این مرورگر وارد شده انجام می‌شود.</p></div><form id="claim" method="post"><input type="hidden" name="token" id="token"></form><script>(()=>{const h=location.hash.startsWith("#token=")?location.hash.slice(7):"";history.replaceState(null,"",location.pathname);if(!h){document.getElementById("msg").textContent="لینک فعال‌سازی معتبر نیست.";return;}document.getElementById("token").value=decodeURIComponent(h);document.getElementById("claim").submit();})();</script></html>';
    exit;
}

$token=trim((string)($_POST['token']??''));
if($token===''||!hash_equals(NZ_ADMIN_CLAIM_HASH,hash('sha256',$token))) nzclaim_page('دسترسی نامعتبر','توکن فعال‌سازی معتبر نیست.',403);

$lock=@fopen($usedFile,'x');
if(!$lock) nzclaim_page('لینک استفاده شده','این لینک یک‌بارمصرف دیگر قابل استفاده نیست.',410);
@chmod($usedFile,0640);
$uid=(string)($user['id']??'');
try{
    $updated=nzc_db_write(function(&$db)use($uid){
        foreach($db['users']??[]as&$u){
            if((string)($u['id']??'')!==$uid)continue;
            $u['role']='superadmin';
            $u['is_admin']=true;
            $u['admin_granted_at']=nzc_now();
            $u['admin_grant_source']='one_time_session_claim';
            return true;
        }
        unset($u);
        return false;
    });
    if(!$updated)throw new RuntimeException('حساب واردشده در پایگاه داده پیدا نشد.');
    $marker=json_encode(['used_at'=>nzc_now(),'user_id'=>$uid],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}';
    @ftruncate($lock,0);@fwrite($lock,$marker);@fflush($lock);@fclose($lock);
    nzc_audit($uid,'grant_superadmin_self','user',$uid,['source'=>'one_time_session_claim']);
    nzc_flash('دسترسی مدیریت برای حساب شما فعال شد. برای عملیات حساس همچنان تأیید امنیتی لازم است.','ok');
    nzc_redirect('/nz?page=admin');
}catch(Throwable $e){
    @fclose($lock);@unlink($usedFile);
    nzclaim_page('فعال‌سازی انجام نشد',$e->getMessage(),500);
}
