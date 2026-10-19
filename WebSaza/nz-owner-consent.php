<?php
declare(strict_types=1);

const NZOC_TTL_SECONDS = 604800;
const NZOC_RESEND_WINDOW = 86400;

function nzoc_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function nzoc_hash_token(string $token): string { return hash('sha256', 'nz-owner-consent|'.$token); }
function nzoc_mask_phone(string $phone): string { return strlen($phone) >= 8 ? substr($phone,0,4).'***'.substr($phone,-4) : '***'; }
function nzoc_source_host(string $url): string { $h=parse_url($url,PHP_URL_HOST); return is_string($h)?strtolower($h):''; }
function nzoc_valid_source_url(string $url): bool { return filter_var($url,FILTER_VALIDATE_URL)!==false && in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true); }
function nzoc_consent_pattern(): string { return trim((string)getenv('NIAZESTAN_CONSENT_PATTERN_CODE')); }
function nzoc_consent_param(): string { $v=trim((string)getenv('NIAZESTAN_CONSENT_PATTERN_PARAM')); return $v!==''?$v:'link'; }

function nzoc_send_invite_sms(string $phone,string $url): bool {
    $pattern=nzoc_consent_pattern();
    if($pattern==='') return false;
    if(function_exists('nzotp_direct_ready') && nzotp_direct_ready()){
        $payload=['sending_type'=>'pattern','from_number'=>nzotp_direct_sender(),'code'=>$pattern,'recipients'=>[nzotp_e164($phone)],'params'=>[nzoc_consent_param()=>$url]];
        $ch=curl_init('https://edge.ippanel.com/v1/api/send');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: '.nzotp_direct_key()],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw===false||$status<200||$status>=300)return false;
        $json=json_decode((string)$raw,true);return !(is_array($json)&&isset($json['meta']['status'])&&!$json['meta']['status']);
    }
    if(function_exists('ws_sms_send_now')){
        $settings=function_exists('nzotp_legacy_settings_file')?nzotp_legacy_settings_file():'';
        if($settings==='')return false;
        $result=ws_sms_send_now($settings,$phone,$pattern,[nzoc_consent_param()=>$url]);
        return !empty($result['ok']);
    }
    return false;
}

function nzoc_find_invite(array $db,string $token): ?array {
    $hash=nzoc_hash_token($token);
    foreach($db['owner_consent_invites']??[] as $row){
        if(($row['token_hash']??'')!==$hash)continue;
        return $row;
    }
    return null;
}

function nzoc_find_user_by_phone(array $db,string $phone): ?array {
    foreach($db['users']??[] as $u){
        $candidate=function_exists('nzotp_phone')?nzotp_phone((string)($u['phone']??'')):(preg_replace('/\D+/','',(string)($u['phone']??''))??'');
        if($candidate===$phone)return $u;
    }
    return null;
}

function nzoc_admin_create(): never {
    nzc_session_open();$db=nzc_db_read();$admin=nzc_require_admin($db);
    if(function_exists('nzauth_is_elevated')&&!nzauth_is_elevated($admin))throw new RuntimeException('برای ساخت دعوت انتشار ابتدا ورود امن مدیریت را تأیید کنید.');
    if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    if(empty($_POST['contact_permission']))throw new RuntimeException('قبل از ارسال دعوت باید مجاز بودن تماس با این شماره را تأیید کنید.');
    $phone=function_exists('nzotp_phone')?nzotp_phone((string)($_POST['owner_phone']??'')):(preg_replace('/\D+/','',(string)($_POST['owner_phone']??''))??'');
    if(!preg_match('/^09\d{9}$/',$phone))throw new RuntimeException('شماره موبایل معتبر وارد کنید.');
    $source=trim((string)($_POST['source_url']??''));if(!nzoc_valid_source_url($source))throw new RuntimeException('لینک منبع معتبر وارد کنید.');
    $title=mb_substr(trim((string)($_POST['title']??'')),0,120);$description=mb_substr(trim((string)($_POST['description']??'')),0,5000);
    if(mb_strlen($title)<5)throw new RuntimeException('عنوان پیش‌نویس حداقل ۵ حرف باشد.');if(mb_strlen($description)<15)throw new RuntimeException('توضیحات بازنویسی‌شده را کامل‌تر وارد کنید.');
    $now=time();foreach($db['owner_consent_invites']??[] as $r){if(($r['phone_hash']??'')===hash('sha256','consent-phone|'.$phone)&&($r['status']??'')==='pending'&&$now-(int)($r['created_ts']??0)<NZOC_RESEND_WINDOW)throw new RuntimeException('برای این شماره در ۲۴ ساعت گذشته دعوت فعال ساخته شده است.');}
    $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$inviteId=nzc_uid('oc');$expires=$now+NZOC_TTL_SECONDS;
    $draft=['title'=>$title,'description'=>$description,'price'=>max(0,(int)($_POST['price']??0)),'category'=>mb_substr(trim((string)($_POST['category']??'')),0,80),'subcategory'=>mb_substr(trim((string)($_POST['subcategory']??'')),0,100),'province'=>mb_substr(trim((string)($_POST['province']??'')),0,100),'city'=>mb_substr(trim((string)($_POST['city']??'')),0,100)];
    $row=['id'=>$inviteId,'status'=>'pending','token_hash'=>nzoc_hash_token($token),'phone'=>$phone,'phone_hash'=>hash('sha256','consent-phone|'.$phone),'phone_masked'=>nzoc_mask_phone($phone),'source_url'=>$source,'source_host'=>nzoc_source_host($source),'draft'=>$draft,'created_by'=>(string)($admin['id']??''),'created_at'=>nzc_now(),'created_ts'=>$now,'expires_at'=>date('c',$expires),'expires_ts'=>$expires];
    nzc_db_write(function(&$db)use($row){if(!isset($db['owner_consent_invites'])||!is_array($db['owner_consent_invites']))$db['owner_consent_invites']=[];$db['owner_consent_invites'][]=$row;});
    $link='https://websaza.ir/nz/consent.php?t='.rawurlencode($token);$sent=nzoc_send_invite_sms($phone,$link);
    nzc_audit($admin,'owner_consent_invite_created','owner_consent',$inviteId,['source_host'=>$row['source_host'],'sms_sent'=>$sent]);
    $_SESSION['nzoc_last_invite_link']=$link;$_SESSION['nzoc_last_invite_sent']=$sent;$_SESSION['nzoc_last_invite_mask']=$row['phone_masked'];
    nzc_flash($sent?'دعوت تأیید انتشار برای مالک پیامک شد.':'دعوت ساخته شد؛ پترن پیامک رضایت تنظیم نیست، لینک آماده ارسال دستی است.',$sent?'ok':'warn');
    nzc_redirect('/nz?page=admin#owner-consent');
}

function nzoc_handle_admin_post(string $action): never {
    if($action==='owner_consent_create')nzoc_admin_create();
    throw new RuntimeException('عملیات رضایت مالک معتبر نیست.');
}

function nzoc_admin_html(string $html): string {
    if(($_GET['page']??'')!=='admin')return $html;
    nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);if(!$u||!nzc_is_admin($u))return $html;
    $csrf=nzoc_e((string)($_SESSION['csrf']??''));$last='';
    if(!empty($_SESSION['nzoc_last_invite_link'])){$url=nzoc_e((string)$_SESSION['nzoc_last_invite_link']);$mask=nzoc_e((string)($_SESSION['nzoc_last_invite_mask']??''));$sent=!empty($_SESSION['nzoc_last_invite_sent']);unset($_SESSION['nzoc_last_invite_link'],$_SESSION['nzoc_last_invite_sent'],$_SESSION['nzoc_last_invite_mask']);$last='<div style="padding:12px;border-radius:12px;background:#f4f7fb;margin-bottom:12px"><b>'.($sent?'پیامک ارسال شد':'لینک آماده ارسال دستی').'</b><div style="font-size:12px;margin:6px 0">گیرنده: '.$mask.'</div><input readonly value="'.$url.'" style="width:100%;direction:ltr"></div>';}
    $card='<section id="owner-consent" class="surface" style="margin-top:18px"><h2 style="margin-top:0">دعوت مالک برای انتشار آگهی</h2><p class="subtle">یک پیش‌نویس غیرعمومی بسازید. متن باید بازنویسی‌شده باشد و تصاویر منبع کپی نمی‌شوند؛ مالک می‌تواند تصویر خودش را هنگام تأیید اضافه کند.</p>'.$last.'<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="owner_consent_create"><div class="field"><label>شماره مالک</label><input name="owner_phone" inputmode="tel" pattern="09[0-9]{9}" maxlength="11" required placeholder="09xxxxxxxxx"></div><div class="field"><label>لینک آگهی منبع</label><input name="source_url" type="url" required placeholder="https://..."></div><div class="field"><label>عنوان آماده</label><input name="title" maxlength="120" required></div><div class="field"><label>توضیحات بازنویسی‌شده</label><textarea name="description" rows="5" maxlength="5000" required></textarea></div><div class="field"><label>قیمت (تومان)</label><input name="price" inputmode="numeric" value="0"></div><div class="field"><label>دسته</label><input name="category"></div><div class="field"><label>زیر‌دسته</label><input name="subcategory"></div><div class="field"><label>استان</label><input name="province"></div><div class="field"><label>شهر</label><input name="city"></div><label style="display:flex;gap:8px;align-items:flex-start;margin:12px 0"><input type="checkbox" name="contact_permission" value="1" required><span>تأیید می‌کنم برای ارسال این دعوت، مجاز به تماس با این شماره هستم.</span></label><button class="btn primary">ساخت دعوت و ارسال لینک تأیید</button></form></section>';
    if(str_contains($html,'</main>'))return preg_replace('/<\/main>/', $card.'</main>', $html, 1)??$html;
    return $html.$card;
}

function nzoc_page(string $title,string $body,int $status=200): never {
    http_response_code($status);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('Pragma: no-cache');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.nzoc_e($title).'</title><style>body{margin:0;background:#f5f6f8;color:#17191d;font-family:Tahoma,Arial,sans-serif}.wrap{max-width:720px;margin:32px auto;padding:16px}.card{background:#fff;border:1px solid #e5e8ec;border-radius:20px;padding:20px;box-shadow:0 12px 38px rgba(0,0,0,.06)}h1{font-size:22px;margin:0 0 10px}.muted{color:#6b7280;line-height:1.9}.field{margin:13px 0}.field label{display:block;font-size:13px;margin-bottom:6px}.field input,.field textarea{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d7dce2;border-radius:12px;font:inherit}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn{border:0;border-radius:12px;padding:12px 16px;font:inherit;cursor:pointer}.primary{background:#17191d;color:#fff}.danger{background:#fff0f0;color:#9b1c1c;border:1px solid #ffd2d2}.note{background:#f6f8fb;border-radius:12px;padding:12px;line-height:1.9;font-size:13px}</style><div class="wrap"><div class="card"><h1>'.nzoc_e($title).'</h1>'.$body.'</div></div></html>';exit;
}

function nzoc_public_handler(): never {
    $token=trim((string)($_POST['token']??$_GET['t']??''));if($token==='')nzoc_page('لینک نامعتبر','<p class="muted">لینک تأیید کامل نیست.</p>',400);
    $db=nzc_db_read();$invite=nzoc_find_invite($db,$token);if(!$invite||($invite['status']??'')!=='pending')nzoc_page('لینک نامعتبر','<p class="muted">این لینک معتبر نیست یا قبلاً استفاده شده است.</p>',410);if((int)($invite['expires_ts']??0)<time())nzoc_page('لینک منقضی شده','<p class="muted">مهلت این دعوت به پایان رسیده است.</p>',410);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $decision=(string)($_POST['decision']??'');$id=(string)$invite['id'];
        if($decision==='reject'){
            nzc_db_write(function(&$db)use($id){$db['owner_consent_invites']=array_values(array_filter($db['owner_consent_invites']??[],fn($r)=>(string)($r['id']??'')!==$id));});
            nzc_audit('consent-owner','owner_consent_rejected','owner_consent',$id,['source_host'=>(string)($invite['source_host']??'')]);
            nzoc_page('درخواست حذف شد','<p class="muted">آگهی منتشر نشد و پیش‌نویس دعوت حذف شد.</p>');
        }
        if($decision!=='approve')nzoc_page('درخواست نامعتبر','<p class="muted">انتخاب انجام‌شده معتبر نیست.</p>',400);
        $title=mb_substr(trim((string)($_POST['title']??'')),0,120);$desc=mb_substr(trim((string)($_POST['description']??'')),0,5000);if(mb_strlen($title)<5||mb_strlen($desc)<15)nzoc_page('اطلاعات ناقص','<p class="muted">عنوان یا توضیحات آگهی خیلی کوتاه است. به صفحه قبل برگردید و کامل‌تر کنید.</p>',422);
        $phone=(string)($invite['phone']??'');$images=[];try{if(function_exists('nzcp_process_images'))$images=nzcp_process_images($_FILES['images']??[],hash('sha256',$phone));}catch(Throwable $e){nzoc_page('خطای تصویر','<p class="muted">'.nzoc_e($e->getMessage()).'</p>',422);}
        $postId=nzc_uid('p');$now=nzc_now();$price=max(0,(int)($_POST['price']??0));$category=mb_substr(trim((string)($_POST['category']??'')),0,80);$subcategory=mb_substr(trim((string)($_POST['subcategory']??'')),0,100);$province=mb_substr(trim((string)($_POST['province']??'')),0,100);$city=mb_substr(trim((string)($_POST['city']??'')),0,100);
        nzc_db_write(function(&$db)use($invite,$id,$phone,$title,$desc,$images,$postId,$now,$price,$category,$subcategory,$province,$city){$u=nzoc_find_user_by_phone($db,$phone);if($u){$uid=(string)$u['id'];}else{$uid=nzc_uid('u');$db['users'][]=['id'=>$uid,'phone'=>$phone,'name'=>'مالک آگهی','role'=>'user','created_at'=>$now,'last_login'=>null];}$db['posts'][]=['id'=>$postId,'user_id'=>$uid,'title'=>$title,'description'=>$desc,'category'=>$category,'subcategory'=>$subcategory,'province'=>$province,'city'=>$city,'price'=>$price,'images'=>$images,'video'=>null,'chat_enabled'=>true,'phone_visible'=>false,'status'=>'pending','review_status'=>'pending','review_priority'=>'normal','moderation'=>['flagged'=>false,'reasons'=>[]],'featured'=>false,'views'=>0,'created_at'=>$now,'submitted_at'=>$now,'updated_at'=>$now,'owner_consent_verified'=>true,'owner_consent_verified_at'=>$now,'source_origin'=>'owner_consent_invite','source_host'=>(string)($invite['source_host']??'')];$db['owner_consent_invites']=array_values(array_filter($db['owner_consent_invites']??[],fn($r)=>(string)($r['id']??'')!==$id));nzc_support_message($db,$uid,'آگهی «'.$title.'» با تأیید شما دریافت شد و برای بررسی انتشار ارسال شد.');nzc_notify($db,$uid,'آگهی «'.$title.'» در انتظار بررسی انتشار است.');});
        nzc_audit('consent-owner','owner_consent_approved','post',$postId,['invite_id'=>$id,'source_host'=>(string)($invite['source_host']??'')]);
        nzoc_page('تأیید ثبت شد','<p class="muted">نسخه‌ای که تأیید کردید برای بررسی انتشار به نیازستان ارسال شد. بعد از تأیید مدیر، آگهی عمومی می‌شود.</p>');
    }
    $d=(array)($invite['draft']??[]);$tokenEsc=nzoc_e($token);$source=nzoc_e((string)($invite['source_url']??''));$body='<p class="muted">این پیش‌نویس برای راحتی شما آماده شده است. می‌توانید همین نسخه را تأیید کنید، هر بخش را ویرایش کنید، عکس‌های خودتان را اضافه کنید، یا کل درخواست را رد و حذف کنید.</p><div class="note">منبع اولیه: <a href="'.$source.'" target="_blank" rel="noopener noreferrer">مشاهده آگهی منبع</a><br>برای حفظ حقوق محتوا، تصاویر منبع به نیازستان کپی نشده‌اند؛ در صورت تمایل عکس‌های خودتان را همین‌جا اضافه کنید.</div><form method="post" enctype="multipart/form-data"><input type="hidden" name="token" value="'.$tokenEsc.'"><div class="field"><label>عنوان</label><input name="title" maxlength="120" required value="'.nzoc_e((string)($d['title']??'')).'"></div><div class="field"><label>توضیحات</label><textarea name="description" rows="7" maxlength="5000" required>'.nzoc_e((string)($d['description']??'')).'</textarea></div><div class="field"><label>قیمت (تومان)</label><input name="price" inputmode="numeric" value="'.(int)($d['price']??0).'"></div><div class="field"><label>دسته</label><input name="category" value="'.nzoc_e((string)($d['category']??'')).'"></div><div class="field"><label>زیر‌دسته</label><input name="subcategory" value="'.nzoc_e((string)($d['subcategory']??'')).'"></div><div class="field"><label>استان</label><input name="province" value="'.nzoc_e((string)($d['province']??'')).'"></div><div class="field"><label>شهر</label><input name="city" value="'.nzoc_e((string)($d['city']??'')).'"></div><div class="field"><label>عکس‌های خودتان (اختیاری، حداکثر ۵ عکس)</label><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></div><div class="note">با انتخاب «تأیید و ارسال برای انتشار» تأیید می‌کنید که مجاز به انتشار اطلاعات و تصاویر واردشده در نیازستان هستید.</div><div class="actions"><button class="btn primary" name="decision" value="approve">تأیید و ارسال برای انتشار</button><button class="btn danger" name="decision" value="reject" formnovalidate>رد و حذف درخواست</button></div></form>';
    nzoc_page('تأیید آگهی در نیازستان',$body);
}
