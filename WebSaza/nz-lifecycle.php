<?php
declare(strict_types=1);

const NZL_FREE_DAYS = 30;
const NZL_SERVICE_PRICE = 100000; // تومان
const NZL_PROMO_DAYS = 7;

function nzl_services(): array {
    return [
        'renew'=>['label'=>'تمدید','description'=>'۳۰ روز دیگر به عمر آگهی اضافه می‌کند.','priority'=>0],
        'step'=>['label'=>'پله','description'=>'برای ۷ روز آگهی را بالاتر از آگهی‌های عادی نمایش می‌دهد.','priority'=>1],
        'ladder'=>['label'=>'نردبان','description'=>'برای ۷ روز آگهی را در اولویت بالاتر قرار می‌دهد.','priority'=>2],
        'urgent'=>['label'=>'فوری','description'=>'برای ۷ روز بالاترین اولویت نمایش را فعال می‌کند.','priority'=>3],
    ];
}
function nzl_service_label(string $service): string {
    $s=nzl_services(); return (string)($s[$service]['label']??$service);
}
function nzl_ts(mixed $value): int {
    if (!is_string($value) || trim($value)==='') return 0;
    $ts=strtotime($value); return $ts===false?0:$ts;
}
function nzl_post_start(array $p): int {
    foreach (['published_at','reviewed_at','created_at'] as $k) {
        $ts=nzl_ts($p[$k]??null); if($ts>0)return$ts;
    }
    return time();
}
function nzl_expiry_ts(array $p): int {
    $ts=nzl_ts($p['expires_at']??null);
    return $ts>0?$ts:(nzl_post_start($p)+(NZL_FREE_DAYS*86400));
}
function nzl_days_left(array $p): int {
    $seconds=nzl_expiry_ts($p)-time();
    return $seconds<=0?0:(int)ceil($seconds/86400);
}
function nzl_is_promo_active(array $p,string $service): bool {
    $field=$service.'_until';
    return nzl_ts($p[$field]??null)>time();
}
function nzl_promo_priority(array $p): int {
    if(($p['status']??'')!=='published')return 0;
    if(nzl_is_promo_active($p,'urgent'))return 3;
    if(nzl_is_promo_active($p,'ladder'))return 2;
    if(nzl_is_promo_active($p,'step'))return 1;
    return 0;
}
function nzl_promo_badges(array $p): string {
    $badges=[];
    foreach(['urgent'=>'فوری','ladder'=>'نردبان','step'=>'پله'] as $k=>$label)if(nzl_is_promo_active($p,$k))$badges[]='<span class="nzl-badge nzl-'.$k.'">'.nzc_e($label).'</span>';
    return implode(' ',$badges);
}

function nzl_tick(bool $force=false): array {
    static $ran=false;
    if($ran&&!$force)return['updated'=>0,'expired'=>0,'reminders'=>0];
    $ran=true;$now=time();$stats=['updated'=>0,'expired'=>0,'reminders'=>0];
    nzc_db_write(function(&$db)use($now,&$stats){
        foreach($db['posts']??[] as &$p){
            if(($p['status']??'')!=='published')continue;
            $owner=(string)($p['user_id']??'');$title=(string)($p['title']??'آگهی');
            if(empty($p['published_at'])){$base=nzl_post_start($p);$p['published_at']=date('c',$base);$stats['updated']++;}
            if(empty($p['expires_at'])){$p['expires_at']=date('c',nzl_post_start($p)+(NZL_FREE_DAYS*86400));$stats['updated']++;}
            $exp=nzl_expiry_ts($p);$remaining=$exp-$now;
            if($remaining<=0){
                $p['status']='expired';$p['expired_at']=date('c',$now);$p['updated_at']=date('c',$now);$stats['expired']++;
                if($owner!==''&&empty($p['expiry_notice_sent_at'])){
                    $msg='مهلت ۳۰ روزه آگهی «'.$title.'» تمام شد و آگهی به‌صورت خودکار از نمایش حذف شد. هر زمان بخواهید می‌توانید با پرداخت ۱۰۰٬۰۰۰ تومان آن را ۳۰ روز تمدید کنید.';
                    nzc_support_message($db,$owner,$msg);nzc_notify($db,$owner,$msg);$p['expiry_notice_sent_at']=date('c',$now);
                }
                continue;
            }
            if($remaining<=2*86400&&$remaining>86400&&empty($p['reminder_2d_at'])&&$owner!==''){
                $msg='هشدار انقضا: آگهی «'.$title.'» حدود ۲ روز دیگر حذف می‌شود. برای ادامه نمایش می‌توانید آن را با هزینه ۱۰۰٬۰۰۰ تومان تمدید کنید.';
                nzc_support_message($db,$owner,$msg);nzc_notify($db,$owner,$msg);$p['reminder_2d_at']=date('c',$now);$stats['reminders']++;
            }
            if($remaining<=86400&&empty($p['reminder_1d_at'])&&$owner!==''){
                $msg='یادآوری نهایی: آگهی «'.$title.'» حدود ۱ روز دیگر حذف می‌شود. اگر می‌خواهید فعال بماند، تمدید ۳۰ روزه را انجام دهید.';
                nzc_support_message($db,$owner,$msg);nzc_notify($db,$owner,$msg);$p['reminder_1d_at']=date('c',$now);$stats['reminders']++;
            }
        }
        unset($p);
    });
    return $stats;
}

function nzl_find_post(array $db,string $postId): ?array {
    foreach($db['posts']??[] as $p)if(($p['id']??'')===$postId)return$p;
    return null;
}
function nzl_payment_pending(array $db,string $userId,string $postId,string $service): bool {
    foreach($db['payments']??[] as $pay)if(($pay['kind']??'')==='ad_service'&&($pay['user_id']??'')===$userId&&($pay['post_id']??'')===$postId&&($pay['service']??'')===$service&&($pay['status']??'')==='pending')return true;
    return false;
}
function nzl_apply_service(array &$db,string $postId,string $service,string $userId): void {
    $now=time();$found=false;
    foreach($db['posts']??[] as &$p){
        if(($p['id']??'')!==$postId)continue;$found=true;
        if(($p['user_id']??'')!==$userId)throw new RuntimeException('مالکیت آگهی تأیید نشد.');
        if(($p['review_status']??'')==='rejected')throw new RuntimeException('آگهی ردشده قابل ارتقا نیست.');
        if($service==='renew'){
            $base=max($now,nzl_expiry_ts($p));$p['expires_at']=date('c',$base+(NZL_FREE_DAYS*86400));
            if(($p['status']??'')==='expired'&&($p['review_status']??'')==='approved')$p['status']='published';
            $p['renewed_at']=date('c',$now);unset($p['expired_at'],$p['expiry_notice_sent_at'],$p['reminder_2d_at'],$p['reminder_1d_at']);
        }else{
            if(($p['status']??'')!=='published')throw new RuntimeException('برای این خدمت، آگهی باید فعال و منتشرشده باشد.');
            $field=$service.'_until';$base=max($now,nzl_ts($p[$field]??null));$p[$field]=date('c',$base+(NZL_PROMO_DAYS*86400));$p[$service.'_activated_at']=date('c',$now);
            if($service==='ladder')$p['bumped_at']=date('c',$now);
        }
        $p['updated_at']=date('c',$now);break;
    }
    unset($p);if(!$found)throw new RuntimeException('آگهی پیدا نشد.');
}
function nzl_request_service(): never {
    nzc_session_open();$db=nzc_db_read();$user=nzc_require_user($db);
    if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');
    $postId=trim((string)($_POST['post_id']??''));$service=trim((string)($_POST['service']??''));$services=nzl_services();
    if(!isset($services[$service]))throw new RuntimeException('خدمت انتخاب‌شده معتبر نیست.');
    $post=nzl_find_post($db,$postId);if(!$post||($post['user_id']??'')!==$user['id'])throw new RuntimeException('آگهی متعلق به این حساب نیست.');
    if(($post['review_status']??'')==='rejected'||($post['status']??'')==='deleted')throw new RuntimeException('این آگهی قابل ارتقا نیست.');
    if($service!=='renew'&&($post['status']??'')!=='published')throw new RuntimeException('آگهی باید منتشر و فعال باشد.');
    if(nzl_payment_pending($db,(string)$user['id'],$postId,$service)){nzc_flash('برای این خدمت یک درخواست پرداخت باز دارید.','warn');nzc_redirect('/nz?page=services&post_id='.rawurlencode($postId));}
    $paymentId=nzc_uid('pay');
    nzc_db_write(function(&$db)use($user,$postId,$service,$paymentId){
        $db['payments'][]=['id'=>$paymentId,'kind'=>'ad_service','user_id'=>$user['id'],'post_id'=>$postId,'service'=>$service,'amount'=>NZL_SERVICE_PRICE,'currency'=>'TOMAN','status'=>'pending','created_at'=>nzc_now()];
        nzc_support_message($db,(string)$user['id'],'درخواست «'.nzl_service_label($service).'» برای آگهی شما به مبلغ ۱۰۰٬۰۰۰ تومان ثبت شد. پس از تأیید پرداخت، خدمت فعال می‌شود.');
    });
    nzc_flash('درخواست خدمت ثبت شد. مبلغ هر خدمت ۱۰۰٬۰۰۰ تومان است و پس از تأیید پرداخت فعال می‌شود.','ok');
    nzc_redirect('/nz?page=services&post_id='.rawurlencode($postId));
}
function nzl_admin_payment_action(string $action): never {
    nzc_session_open();$db=nzc_db_read();$admin=nzc_require_admin($db);if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');
    $paymentId=trim((string)($_POST['payment_id']??''));$approve=$action==='approve_service_payment';$owner='';$postId='';$service='';
    nzc_db_write(function(&$db)use($paymentId,$approve,$admin,&$owner,&$postId,&$service){
        $found=false;
        foreach($db['payments']??[] as &$pay){
            if(($pay['id']??'')!==$paymentId)continue;$found=true;
            if(($pay['kind']??'')!=='ad_service'||($pay['status']??'')!=='pending')throw new RuntimeException('این پرداخت قابل بررسی نیست.');
            $owner=(string)($pay['user_id']??'');$postId=(string)($pay['post_id']??'');$service=(string)($pay['service']??'');
            if($approve){nzl_apply_service($db,$postId,$service,$owner);$pay['status']='paid';$pay['paid_at']=nzc_now();$pay['confirmed_by']=$admin['id'];}
            else{$pay['status']='cancelled';$pay['cancelled_at']=nzc_now();$pay['confirmed_by']=$admin['id'];}
            break;
        }
        unset($pay);if(!$found)throw new RuntimeException('درخواست پرداخت پیدا نشد.');
        if($owner!==''){
            $msg=$approve?'پرداخت خدمت «'.nzl_service_label($service).'» تأیید و روی آگهی شما فعال شد.':'درخواست پرداخت خدمت «'.nzl_service_label($service).'» تأیید نشد.';
            nzc_support_message($db,$owner,$msg);nzc_notify($db,$owner,$msg);
        }
    });
    nzc_flash($approve?'پرداخت تأیید و خدمت فعال شد.':'درخواست پرداخت لغو شد.',$approve?'ok':'warn');nzc_redirect('/nz?page=admin');
}
function nzl_prepare_request(): void {
    nzl_tick();
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;$action=(string)($_POST['action']??'');
    if($action==='request_ad_service')nzl_request_service();
    if(in_array($action,['approve_service_payment','reject_service_payment'],true))nzl_admin_payment_action($action);
}

function nzl_post_service_summary(array $p): string {
    $status=(string)($p['status']??'');$days=nzl_days_left($p);$expiry=nzl_expiry_ts($p);
    $parts=[];if($status==='published')$parts[]=$days.' روز تا انقضا';elseif($status==='expired')$parts[]='منقضی و از نمایش حذف شده';else $parts[]=$status;
    if($expiry>0)$parts[]='انقضا: '.date('Y/m/d',$expiry);
    return implode(' · ',$parts);
}
function nzl_service_cards(array $db,array $user,array $post): string {
    $csrf=nzc_e($_SESSION['csrf']??'');$pid=(string)($post['id']??'');$services=nzl_services();$h='<div class="nzl-grid">';
    foreach($services as$key=>$cfg){$pending=nzl_payment_pending($db,(string)$user['id'],$pid,$key);$disabled=($key!=='renew'&&($post['status']??'')!=='published')||($post['review_status']??'')==='rejected';
        $active='';if($key!=='renew'&&nzl_is_promo_active($post,$key))$active='<div class="nzl-active">فعال تا '.date('Y/m/d',nzl_ts($post[$key.'_until']??null)).'</div>';
        $h.='<div class="surface nzl-service"><h3>'.nzc_e((string)$cfg['label']).'</h3><p>'.nzc_e((string)$cfg['description']).'</p>'.$active.'<b class="nzl-price">۱۰۰٬۰۰۰ تومان</b><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="request_ad_service"><input type="hidden" name="post_id" value="'.nzc_e($pid).'"><input type="hidden" name="service" value="'.nzc_e($key).'">'.($pending?'<button class="btn" disabled>در انتظار تأیید پرداخت</button>':'<button class="btn primary" '.($disabled?'disabled':'').'>خرید '.nzc_e((string)$cfg['label']).'</button>').'</form></div>';
    }
    return$h.'</div>';
}
function nzl_services_page(array $db,array $user): string {
    $postId=trim((string)($_GET['post_id']??''));$own=array_values(array_filter($db['posts']??[],fn($p)=>($p['user_id']??'')===$user['id']&&($p['status']??'')!=='deleted'));
    if($postId!==''){
        $post=null;foreach($own as$p)if(($p['id']??'')===$postId){$post=$p;break;}
        if(!$post)return'<main class="page page-narrow"><div class="surface"><h1>آگهی پیدا نشد</h1><a class="btn" href="/nz?page=services">بازگشت</a></div></main>';
        return'<main class="page"><div class="section-head"><div><h1>ارتقای آگهی</h1><p>ثبت اولیه آگهی رایگان است؛ هر خدمت فقط ۱۰۰٬۰۰۰ تومان.</p></div><a class="btn ghost" href="/nz?page=services">آگهی‌های من</a></div><div class="surface" style="margin-bottom:14px"><h2 style="margin:0 0 8px">'.nzc_e($post['title']??'آگهی').'</h2><div class="subtle">'.nzc_e(nzl_post_service_summary($post)).'</div><div style="margin-top:8px">'.nzl_promo_badges($post).'</div></div>'.nzl_service_cards($db,$user,$post).'</main>';
    }
    $h='<main class="page"><div class="section-head"><div><h1>خدمات آگهی</h1><p>ثبت آگهی رایگان است؛ تمدید، پله، نردبان و فوری هر کدام ۱۰۰٬۰۰۰ تومان.</p></div></div>';
    if(!$own)return$h.'<div class="empty">هنوز آگهی قابل مدیریت ندارید.</div></main>';
    foreach(array_reverse($own)as$p)$h.='<div class="surface nzl-own"><div><b>'.nzc_e($p['title']??'آگهی').'</b><small class="subtle" style="display:block;margin-top:5px">'.nzc_e(nzl_post_service_summary($p)).'</small></div><a class="btn ghost" href="/nz?page=services&post_id='.rawurlencode((string)$p['id']).'">مدیریت و ارتقا</a></div>';
    return$h.'</main>';
}
function nzl_admin_payments_html(array $db): string {
    $rows=array_values(array_filter($db['payments']??[],fn($p)=>($p['kind']??'')==='ad_service'&&($p['status']??'')==='pending'));if(!$rows)return'';
    usort($rows,fn($a,$b)=>nzl_ts($b['created_at']??null)<=>nzl_ts($a['created_at']??null));$csrf=nzc_e($_SESSION['csrf']??'');$h='<section class="surface nzl-admin-pay"><h2>پرداخت‌های خدمات آگهی</h2>';
    foreach($rows as$pay){$phone='';foreach($db['users']??[]as$u)if(($u['id']??'')===($pay['user_id']??'')){$phone=(string)($u['phone']??$u['name']??'');break;}$post=nzl_find_post($db,(string)($pay['post_id']??''));
        $h.='<article class="nzl-pay-row"><div><b>'.nzc_e(nzl_service_label((string)($pay['service']??''))).' · ۱۰۰٬۰۰۰ تومان</b><small>'.nzc_e(($post['title']??'آگهی').' · '.$phone).'</small></div><div><form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="approve_service_payment"><input type="hidden" name="payment_id" value="'.nzc_e($pay['id']??'').'"><button class="btn primary">تأیید پرداخت</button></form> <form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="reject_service_payment"><input type="hidden" name="payment_id" value="'.nzc_e($pay['id']??'').'"><button class="btn danger">رد</button></form></div></article>';
    }
    return$h.'</section>';
}
function nzl_enhance(string $html): string {
    nzc_session_open();$db=nzc_db_read();$user=nzc_current_user($db);$page=(string)($_GET['page']??'home');
    if($page==='services'&&$user&&function_exists('nzd_replace_main'))$html=nzd_replace_main($html,nzl_services_page($db,$user));
    if($page==='profile'&&$user)$html=str_replace('</div><form method="post" style="margin-top:18px">','<a class="menu-item" href="/nz?page=services"><span>خدمات و تمدید آگهی</span><span>‹</span></a></div><form method="post" style="margin-top:18px">',$html);
    if($page==='admin'&&$user&&nzc_is_admin($user))$html=str_replace('</main>',nzl_admin_payments_html($db).'</main>',$html);
    $postId=trim((string)($_GET['id']??''));if($postId!==''&&$user){$p=nzl_find_post($db,$postId);if($p&&($p['user_id']??'')===$user['id']&&str_contains($html,'<section class="detail-info">')){$card='<div class="surface nzl-owner-card"><b>مدیریت آگهی</b><span>'.nzc_e(nzl_post_service_summary($p)).'</span><a class="btn primary" href="/nz?page=services&post_id='.rawurlencode($postId).'">تمدید / نردبان / پله / فوری</a></div>';$html=str_replace('<section class="detail-info">','<section class="detail-info">'.$card,$html);}}
    $html=str_replace('>expired</b>','>منقضی / حذف خودکار</b>',$html);
    $css='<style id="nzl-style">.nzl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.nzl-service h3{margin:0 0 7px}.nzl-service p{min-height:42px;color:#626a73;line-height:1.8}.nzl-price{display:block;margin:10px 0;font-size:16px}.nzl-active{display:inline-block;background:#eaf7ef;color:#18733e;border-radius:999px;padding:5px 9px;font-size:11px}.nzl-badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:900;margin-left:4px;background:#f0f1f3}.nzl-urgent{background:#fff0f0;color:#b42318}.nzl-ladder{background:#fff4d8;color:#8a5a00}.nzl-step{background:#edf4ff;color:#175cd3}.nzl-own,.nzl-pay-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}.nzl-pay-row{padding:12px 0;border-top:1px solid #eee}.nzl-pay-row small{display:block;color:#747b84;margin-top:4px}.nzl-owner-card{margin-bottom:12px;display:grid;gap:7px}.nzl-owner-card span{font-size:12px;color:#747b84}@media(max-width:720px){.nzl-grid{grid-template-columns:1fr}.nzl-own,.nzl-pay-row{align-items:stretch;flex-direction:column}.nzl-own .btn,.nzl-pay-row .btn{width:100%}}</style>';
    return str_replace('</head>',$css.'</head>',$html);
}
