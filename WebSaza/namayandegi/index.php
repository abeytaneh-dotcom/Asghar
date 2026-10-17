<?php
declare(strict_types=1);

session_name('WEBSAZA_NAMAYANDEGI');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/namayandegi/']);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

const DATA_DIR = __DIR__ . '/_data';
const BRANCHES = DATA_DIR . '/branches.json';
const LEADS = DATA_DIR . '/leads.json';
const ASSIGNMENTS = DATA_DIR . '/assignments.json';
const ADMIN = DATA_DIR . '/admin.json';

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0750, true);
if (!is_file(DATA_DIR.'/.htaccess')) @file_put_contents(DATA_DIR.'/.htaccess', "Require all denied\n");
if (!is_file(BRANCHES)) @file_put_contents(BRANCHES, "[]\n");
if (!is_file(LEADS)) @file_put_contents(LEADS, "[]\n");
if (!is_file(ASSIGNMENTS)) @file_put_contents(ASSIGNMENTS, "[]\n");
if (!is_file(ADMIN)) @file_put_contents(ADMIN, json_encode(['username'=>'admin','password_hash'=>password_hash('123456',PASSWORD_DEFAULT),'must_change'=>true], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function loadj(string $f): array { $v=json_decode((string)@file_get_contents($f),true); return is_array($v)?$v:[]; }
function savej(string $f,array $v): bool {
    $lock=fopen($f.'.lock','c'); if(!$lock||!flock($lock,LOCK_EX)) return false;
    try { $tmp=$f.'.tmp.'.bin2hex(random_bytes(4)); $ok=file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)!==false; if(!$ok)return false; chmod($tmp,0640); return rename($tmp,$f); }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function goodcsrf(): bool { return isset($_POST['csrf'],$_SESSION['csrf'])&&hash_equals($_SESSION['csrf'],(string)$_POST['csrf']); }
function go(string $u): never { header('Location: '.$u); exit; }
function branch(): ?array {
    if(empty($_SESSION['branch_id'])) return null;
    foreach(loadj(BRANCHES) as $b) if(($b['id']??'')===$_SESSION['branch_id']) return $b;
    return null;
}
function statusLabel(string $s): string { return [
    'new'=>'جدید','won'=>'سفارش قطعی شد','interested'=>'مشتری راغب','followup'=>'نیاز به پیگیری','cancelled'=>'کنسل','bad_info'=>'اطلاعات اشتباه','no_answer'=>'جواب نداد'
][$s]??$s; }
function statusClass(string $s): string { return in_array($s,['won','interested'],true)?'good':(in_array($s,['cancelled','bad_info'],true)?'bad':'mid'); }
function today(): string { return date('Y-m-d'); }
function makeCode(): string { do{$c=(string)random_int(1000,9999);$used=false;foreach(loadj(BRANCHES) as $b)if(($b['code']??'')===$c)$used=true;}while($used);return $c; }
function ensureDaily(string $branchId): void {
    $assign=loadj(ASSIGNMENTS); $leads=loadj(LEADS); $day=today();
    $todayIds=[]; foreach($assign as $a) if(($a['branch_id']??'')===$branchId&&($a['date']??'')===$day) $todayIds[]=$a['lead_id'];
    $need=max(0,20-count($todayIds)); if(!$need)return;
    $taken=[]; foreach($assign as $a) if(($a['date']??'')===$day) $taken[$a['lead_id']]=1;
    foreach($leads as $l){ if($need<=0)break; $id=$l['id']??''; if(!$id||isset($taken[$id]))continue; $assign[]=['id'=>'A'.bin2hex(random_bytes(5)),'branch_id'=>$branchId,'lead_id'=>$id,'date'=>$day,'status'=>'new','note'=>'','followup_at'=>'','updated_at'=>'']; $taken[$id]=1; $need--; }
    savej(ASSIGNMENTS,$assign);
}
function leadById(string $id): ?array { foreach(loadj(LEADS) as $l) if(($l['id']??'')===$id)return $l; return null; }

$action=(string)($_POST['action']??$_GET['action']??'');
$flash='';

if($action==='register' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');
    $name=trim((string)($_POST['name']??'')); $mobile=preg_replace('/\D+/','',(string)($_POST['mobile']??'')); $password=(string)($_POST['password']??''); $city=trim((string)($_POST['city']??''));
    if(mb_strlen($name)<3||!preg_match('/^09\d{9}$/',$mobile)||strlen($password)<8){$flash='اطلاعات ثبت‌نام کامل یا معتبر نیست.';}
    else{
        $rows=loadj(BRANCHES); foreach($rows as $r) if(($r['mobile']??'')===$mobile){$flash='این شماره قبلاً ثبت شده است.'; break 1;}
        if(!$flash){$id='B'.bin2hex(random_bytes(6));$rows[]=['id'=>$id,'code'=>makeCode(),'name'=>$name,'mobile'=>$mobile,'city'=>$city,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'active'=>true,'created_at'=>date('Y-m-d H:i:s')];savej(BRANCHES,$rows);$_SESSION['branch_id']=$id;ensureDaily($id);go('./');}
    }
}
if($action==='login' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');
    $u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');
    foreach(loadj(BRANCHES) as $b){ if((($b['mobile']??'')===$u||($b['code']??'')===$u)&&!empty($b['active'])&&password_verify($p,(string)($b['password_hash']??''))){$_SESSION['branch_id']=$b['id'];ensureDaily($b['id']);go('./');}}
    $flash='شماره/کد یا رمز عبور صحیح نیست.';
}
if($action==='logout'){unset($_SESSION['branch_id']);go('./');}

if($action==='admin_login' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');
    $a=loadj(ADMIN);if(hash_equals((string)($a['username']??''),trim((string)($_POST['username']??'')))&&password_verify((string)($_POST['password']??''),(string)($a['password_hash']??''))){$_SESSION['admin']=true;go('./?admin=1');}$flash='ورود مدیر ناموفق بود.';
}
if($action==='admin_logout'){unset($_SESSION['admin']);go('./?admin=1');}
if($action==='admin_password' && !empty($_SESSION['admin']) && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');$p=(string)($_POST['password']??'');if(strlen($p)<10)$flash='رمز جدید حداقل ۱۰ کاراکتر باشد.';else{$a=loadj(ADMIN);$a['password_hash']=password_hash($p,PASSWORD_DEFAULT);$a['must_change']=false;savej(ADMIN,$a);$flash='رمز مدیر تغییر کرد.';}
}
if($action==='add_leads' && !empty($_SESSION['admin']) && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');$raw=trim((string)($_POST['bulk']??''));$rows=loadj(LEADS);$n=0;
    foreach(preg_split('/\R/u',$raw) as $line){$line=trim($line);if($line==='')continue;$p=array_map('trim',explode('|',$line));$phone=preg_replace('/[^0-9+]/','',$p[1]??'');if(!$phone)continue;$rows[]=['id'=>'L'.bin2hex(random_bytes(6)),'name'=>$p[0]??'مشتری','phone'=>$phone,'business'=>$p[2]??'','city'=>$p[3]??'','proposal'=>$p[4]??'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>$p[5]??'سلام وقت بخیر، از وب ساز تماس می‌گیرم. سایت و حضور آنلاین کسب‌وکار شما را بررسی کردیم و یک پیشنهاد مشخص برای بهتر شدن جذب مشتری داریم. اگر دو دقیقه فرصت دارید خیلی کوتاه توضیح می‌دهم.','created_at'=>date('Y-m-d H:i:s')];$n++;}
    savej(LEADS,$rows);$flash="$n فرصت فروش اضافه شد.";
}
if($action==='update_lead' && ($b=branch()) && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!goodcsrf()) exit('درخواست نامعتبر است');$aid=(string)($_POST['assignment_id']??'');$status=(string)($_POST['status']??'new');$allowed=['new','won','interested','followup','cancelled','bad_info','no_answer'];if(!in_array($status,$allowed,true))$status='new';
    $rows=loadj(ASSIGNMENTS);foreach($rows as &$a){if(($a['id']??'')===$aid&&($a['branch_id']??'')===$b['id']){$a['status']=$status;$a['note']=trim((string)($_POST['note']??''));$a['followup_at']=trim((string)($_POST['followup_at']??''));$a['updated_at']=date('Y-m-d H:i:s');break;}}unset($a);savej(ASSIGNMENTS,$rows);go('./#lead-'.$aid);
}

$isAdmin=isset($_GET['admin']);$b=branch();if($b)ensureDaily($b['id']);
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>سامانه نمایندگی وب ساز</title><meta name="robots" content="noindex,nofollow"><style>
@font-face{font-family:y;src:url('/YekanBakhMedium.woff')}*{box-sizing:border-box}body{margin:0;background:#f5f6f8;color:#17191d;font-family:y,Tahoma,sans-serif;line-height:1.75}a{text-decoration:none;color:inherit}.wrap{width:min(1180px,calc(100% - 28px));margin:auto}.top{position:sticky;top:0;z-index:20;background:#101318;color:#fff;border-bottom:1px solid #252a31}.top .wrap{height:72px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{font-weight:900;font-size:21px}.brand small{display:block;font-size:10px;color:#9ea6b1}.btn,button{border:0;border-radius:14px;padding:11px 16px;font:inherit;font-weight:800;cursor:pointer}.btn{display:inline-flex;align-items:center;justify-content:center}.dark{background:#171b21;color:#fff}.lime{background:#c9f35b;color:#111}.ghost{background:#fff;border:1px solid #dfe3e8}.page{padding:34px 0 70px}.hero{background:#101318;color:#fff;border-radius:28px;padding:32px;margin-bottom:22px}.hero h1{font-size:clamp(30px,6vw,58px);line-height:1.15;margin:0 0 12px}.hero p{color:#b8c0ca;margin:0;max-width:760px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid #e2e5e9;border-radius:22px;padding:20px;box-shadow:0 12px 35px rgba(20,25,30,.05)}.span8{grid-column:span 8}.span4{grid-column:span 4}.span12{grid-column:1/-1}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.stat{background:#f6f7f8;border-radius:16px;padding:14px}.stat b{display:block;font-size:22px}.muted{color:#737b85;font-size:13px}.form{display:grid;gap:12px}.form input,.form textarea,.form select{width:100%;border:1px solid #d9dee4;border-radius:13px;padding:11px 12px;font:inherit;background:#fff}.form textarea{min-height:110px;resize:vertical}.lead{border:1px solid #e3e6ea;border-radius:18px;padding:16px;margin-top:12px}.lead-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.lead h3{margin:0}.phone{direction:ltr;display:inline-block;font-weight:900}.pill{display:inline-block;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:900}.good{background:#e5f8e6;color:#176b2b}.bad{background:#ffe8e8;color:#a22727}.mid{background:#fff4ce;color:#805f00}.script{background:#f5f7f8;border-right:4px solid #c9f35b;padding:13px;border-radius:12px;margin:12px 0}.proposal{background:#eef7ff;border-radius:12px;padding:13px;margin:12px 0}.row{display:flex;gap:8px;flex-wrap:wrap}.alert{padding:12px 14px;border-radius:14px;background:#fff7d7;border:1px solid #f0d978;margin-bottom:16px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.tabs a{padding:9px 12px;border-radius:12px;background:#fff;border:1px solid #e0e4e8}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:right;padding:10px;border-bottom:1px solid #eceff2;font-size:13px}.empty{text-align:center;padding:35px;color:#808792}@media(max-width:850px){.span8,.span4{grid-column:1/-1}.stats{grid-template-columns:1fr 1fr}.top .wrap{height:auto;padding:12px 0}.lead-head{display:block}.hero{padding:24px}}@media(max-width:520px){.stats{grid-template-columns:1fr}.row>*{width:100%}.table{display:block;overflow:auto}}
</style></head><body><header class="top"><div class="wrap"><a class="brand" href="/">وب ساز<small>سامانه فعالیت نمایندگی</small></a><div class="row"><?php if($b):?><span><?=e($b['name'])?> • کد <?=e($b['code'])?></span><a class="btn lime" href="?action=logout">خروج</a><?php elseif(!empty($_SESSION['admin'])):?><a class="btn lime" href="?admin=1&action=admin_logout">خروج مدیر</a><?php else:?><a class="btn ghost" href="?admin=1">ورود مدیر</a><?php endif;?></div></div></header><main class="page"><div class="wrap">
<?php if($flash):?><div class="alert"><?=e($flash)?></div><?php endif;?>
<?php if($isAdmin):?>
<?php if(empty($_SESSION['admin'])):?>
<div class="grid"><section class="card span4" style="grid-column:5/span 4"><h2>ورود مدیریت</h2><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="admin_login"><label>نام کاربری<input name="username" required></label><label>رمز عبور<input type="password" name="password" required></label><button class="dark">ورود</button></form><p class="muted">ورود اولیه: admin / 123456 — بعد از ورود حتماً رمز را تغییر دهید.</p></section></div>
<?php else:$branches=loadj(BRANCHES);$leads=loadj(LEADS);$ass=loadj(ASSIGNMENTS);$won=count(array_filter($ass,fn($x)=>($x['status']??'')==='won'));?>
<section class="hero"><h1>مدیریت نمایندگی‌ها و فرصت‌های فروش</h1><p>نمایندگی‌ها خودکار ثبت‌نام می‌کنند. بانک فرصت‌های فروش را شارژ کنید تا هر نمایندگی در هر روز تا ۲۰ مشتری دریافت کند.</p></section>
<div class="stats"><div class="stat"><b><?=count($branches)?></b><span>نمایندگی</span></div><div class="stat"><b><?=count($leads)?></b><span>فرصت فروش</span></div><div class="stat"><b><?=count($ass)?></b><span>تخصیص انجام‌شده</span></div><div class="stat"><b><?=$won?></b><span>سفارش قطعی</span></div></div><div style="height:16px"></div>
<div class="grid"><section class="card span8"><h2>افزودن گروهی فرصت‌های فروش</h2><p class="muted">هر خط با این فرمت: نام | شماره | شغل/کسب‌وکار | شهر | پیشنهاد ما | متن پیشنهادی تماس</p><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="add_leads"><textarea name="bulk" placeholder="فروشگاه نمونه | 09120000000 | فروش لوازم خانگی | شیراز | طراحی فروشگاه اینترنتی | سلام وقت بخیر..."></textarea><button class="lime">افزودن به بانک مشتری‌ها</button></form></section><aside class="card span4"><h2>امنیت مدیر</h2><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="admin_password"><input type="password" name="password" minlength="10" placeholder="رمز جدید" required><button class="dark">تغییر رمز مدیر</button></form></aside>
<section class="card span12"><h2>نمایندگی‌ها</h2><table class="table"><thead><tr><th>نام</th><th>کد</th><th>موبایل</th><th>شهر</th><th>ثبت‌نام</th></tr></thead><tbody><?php foreach(array_reverse($branches) as $r):?><tr><td><?=e($r['name'])?></td><td><?=e($r['code'])?></td><td><?=e($r['mobile'])?></td><td><?=e($r['city'])?></td><td><?=e($r['created_at'])?></td></tr><?php endforeach;?></tbody></table></section></div>
<?php endif;?>
<?php elseif($b):$ass=loadj(ASSIGNMENTS);$mine=array_values(array_filter($ass,fn($x)=>($x['branch_id']??'')===$b['id']&&($x['date']??'')===today()));$counts=[];foreach($mine as $x)$counts[$x['status']??'new']=($counts[$x['status']??'new']??0)+1;?>
<section class="hero"><h1>فرصت‌های امروز شما</h1><p><?=e($b['name'])?>، برای امروز <?=count($mine)?> فرصت فروش در صفحه شما قرار گرفته است. بعد از هر تماس نتیجه را همان لحظه ثبت کنید تا گزارش‌ها دقیق بمانند.</p></section>
<div class="stats"><div class="stat"><b><?=count($mine)?></b><span>کل امروز</span></div><div class="stat"><b><?=$counts['won']??0?></b><span>قطعی</span></div><div class="stat"><b><?=$counts['interested']??0?></b><span>راغب</span></div><div class="stat"><b><?=($counts['no_answer']??0)+($counts['followup']??0)?></b><span>پیگیری</span></div></div><div style="height:16px"></div>
<section class="card"><h2>لیست تماس امروز</h2><?php if(!$mine):?><div class="empty">بانک مشتری هنوز فرصت آزاد کافی ندارد. مدیر باید مشتری جدید اضافه کند.</div><?php endif;?><?php foreach($mine as $a):$l=leadById((string)$a['lead_id']);if(!$l)continue;?><article class="lead" id="lead-<?=e($a['id'])?>"><div class="lead-head"><div><h3><?=e($l['name'])?></h3><div class="muted"><?=e($l['business'])?><?=!empty($l['city'])?' • '.e($l['city']):''?></div></div><div><a class="phone btn lime" href="tel:<?=e($l['phone'])?>"><?=e($l['phone'])?></a> <span class="pill <?=statusClass($a['status'])?>"><?=e(statusLabel($a['status']))?></span></div></div><div class="proposal"><b>پیشنهاد مناسب برای این مشتری:</b><br><?=e($l['proposal'])?></div><div class="script"><b>متن پیشنهادی تماس:</b><br><?=nl2br(e($l['script']))?></div><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="update_lead"><input type="hidden" name="assignment_id" value="<?=e($a['id'])?>"><div class="row"><select name="status"><option value="new" <?=$a['status']==='new'?'selected':''?>>جدید</option><option value="won" <?=$a['status']==='won'?'selected':''?>>سفارش قطعی شد</option><option value="interested" <?=$a['status']==='interested'?'selected':''?>>مشتری راغب</option><option value="followup" <?=$a['status']==='followup'?'selected':''?>>نیاز به پیگیری</option><option value="no_answer" <?=$a['status']==='no_answer'?'selected':''?>>جواب نداد</option><option value="cancelled" <?=$a['status']==='cancelled'?'selected':''?>>کنسل</option><option value="bad_info" <?=$a['status']==='bad_info'?'selected':''?>>اطلاعات اشتباه</option></select><input type="datetime-local" name="followup_at" value="<?=e($a['followup_at']??'')?>" title="زمان پیگیری"><button class="dark">ثبت نتیجه</button></div><textarea name="note" placeholder="نتیجه مکالمه، درخواست مشتری، زمان مناسب تماس بعدی و..."><?=e($a['note']??'')?></textarea></form></article><?php endforeach;?></section>
<?php else:?>
<section class="hero"><h1>نمایندگی وب ساز؛ ثبت‌نام کن و شروع به فعالیت کن</h1><p>بعد از ثبت‌نام، صفحه اختصاصی شما خودکار ساخته می‌شود و هر روز تا ۲۰ فرصت فروش آماده تماس می‌گیرید؛ همراه با پیشنهاد مناسب و متن پیشنهادی برای صحبت با مشتری.</p></section><div class="grid"><section class="card span8"><h2>ثبت‌نام نمایندگی</h2><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="register"><label>نام و نام خانوادگی<input name="name" required></label><label>شماره موبایل<input name="mobile" inputmode="tel" placeholder="09123456789" required></label><label>شهر<input name="city"></label><label>رمز عبور<input type="password" name="password" minlength="8" required></label><button class="lime">ثبت‌نام و ورود به صفحه اختصاصی</button></form></section><aside class="card span4"><h2>ورود نمایندگی</h2><form class="form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="login"><label>شماره موبایل یا کد ۴ رقمی<input name="username" required></label><label>رمز عبور<input type="password" name="password" required></label><button class="dark">ورود</button></form><p class="muted">بعد از ثبت‌نام یک کد ۴ رقمی اختصاصی برای هر نمایندگی ساخته می‌شود.</p></aside></div>
<?php endif;?>
</div></main></body></html>