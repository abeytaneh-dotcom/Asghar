<?php
declare(strict_types=1);
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");

const APP_NAME = 'بازار وب‌ساز';
const APP_PATH = '/marketplace.php';
const MAX_IMAGES = 8;
const MAX_IMAGE_BYTES = 5242880;

$storageDir = __DIR__ . '/.marketplace-data';
$uploadDir = __DIR__ . '/marketplace-uploads';
if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
if (!file_exists($storageDir . '/.htaccess')) @file_put_contents($storageDir . '/.htaccess', "Require all denied\n");
if (!file_exists($uploadDir . '/.htaccess')) @file_put_contents($uploadDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n");
$dataFile = $storageDir . '/data.json';

$categories = [
    'املاک' => ['آپارتمان', 'خانه و ویلا', 'زمین و کلنگی', 'اجاره', 'دفتر و تجاری'],
    'وسایل نقلیه' => ['خودرو', 'موتورسیکلت', 'قطعات خودرو', 'خودرو سنگین'],
    'کالای دیجیتال' => ['موبایل', 'لپ‌تاپ', 'تبلت', 'کنسول بازی', 'لوازم جانبی'],
    'خانه و آشپزخانه' => ['لوازم خانگی', 'مبلمان', 'دکوراسیون', 'ظروف و آشپزخانه'],
    'خدمات' => ['تعمیرات', 'آموزش', 'خدمات خودرو', 'نظافت', 'طراحی و برنامه‌نویسی'],
    'استخدام و کاریابی' => ['فروش و بازاریابی', 'فنی و مهندسی', 'اداری', 'راننده', 'کارگر'],
    'شخصی' => ['لباس', 'کیف و کفش', 'زیورآلات', 'لوازم کودک'],
    'سرگرمی و فراغت' => ['ورزش', 'کتاب', 'موسیقی', 'بازی و اسباب‌بازی'],
];
$cities = ['تهران','شیراز','اصفهان','مشهد','تبریز','کرج','اهواز','قم','رشت','کرمان','بندرعباس','ارومیه','یزد','بوشهر'];

function default_db(): array {
    return ['users'=>[], 'posts'=>[], 'favorites'=>[], 'conversations'=>[], 'messages'=>[], 'reports'=>[], 'saved_searches'=>[], 'notifications'=>[], 'payments'=>[]];
}
function db_read(): array {
    global $dataFile;
    if (!is_file($dataFile)) return default_db();
    $raw = @file_get_contents($dataFile);
    $db = json_decode($raw ?: '', true);
    return is_array($db) ? array_replace(default_db(), $db) : default_db();
}
function db_write(callable $mutator): mixed {
    global $dataFile;
    $fh = @fopen($dataFile, 'c+');
    if (!$fh) throw new RuntimeException('امکان دسترسی به فضای ذخیره‌سازی وجود ندارد.');
    flock($fh, LOCK_EX);
    rewind($fh);
    $raw = stream_get_contents($fh);
    $db = json_decode($raw ?: '', true);
    if (!is_array($db)) $db = default_db();
    $db = array_replace(default_db(), $db);
    $result = $mutator($db);
    rewind($fh); ftruncate($fh, 0);
    fwrite($fh, json_encode($db, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    fflush($fh); flock($fh, LOCK_UN); fclose($fh);
    return $result;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(6)); }
function now(): string { return date('c'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $message, string $type='ok'): void { $_SESSION['flash'] = [$message,$type]; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'); }
function current_user(array $db): ?array {
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) return null;
    foreach ($db['users'] as $u) if (($u['id'] ?? '') === $uid) return $u;
    return null;
}
function require_user(array $db): array {
    $u = current_user($db);
    if (!$u) { flash('برای ادامه وارد حساب شوید.', 'warn'); redirect(APP_PATH . '?page=login'); }
    return $u;
}
function normalize_phone(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($phone, '98') && strlen($phone) === 12) $phone = '0' . substr($phone, 2);
    if (str_starts_with($phone, '9') && strlen($phone) === 10) $phone = '0' . $phone;
    return $phone;
}
function money(mixed $n): string {
    $n = (int)$n;
    return $n > 0 ? number_format($n) . ' تومان' : 'توافقی';
}
function post_image(array $p): string {
    if (!empty($p['images'][0])) return '/marketplace-uploads/' . rawurlencode(basename($p['images'][0]));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="100%" height="100%" fill="#eef2f7"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="38" fill="#64748b">'.htmlspecialchars($p['category'] ?? 'آگهی').'</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
function upload_images(): array {
    global $uploadDir;
    if (empty($_FILES['images']) || !is_array($_FILES['images']['name'])) return [];
    $out=[]; $count=min(count($_FILES['images']['name']), MAX_IMAGES);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    for($i=0;$i<$count;$i++){
        if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($_FILES['images']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $tmp=$_FILES['images']['tmp_name'][$i]; $size=(int)($_FILES['images']['size'][$i] ?? 0);
        if($size<=0 || $size>MAX_IMAGE_BYTES) continue;
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if(!isset($allowed[$mime])) continue;
        $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
        if(move_uploaded_file($tmp, $uploadDir.'/'.$name)) $out[]=$name;
    }
    return $out;
}
function notify(array &$db, string $uid, string $text): void { $db['notifications'][]=['id'=>id('n'),'user_id'=>$uid,'text'=>$text,'read'=>false,'created_at'=>now()]; }

try {
    $db = db_read();
    $user = current_user($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'request_otp') {
            $phone = normalize_phone($_POST['phone'] ?? '');
            if (!preg_match('/^09\d{9}$/', $phone)) throw new RuntimeException('شماره موبایل معتبر وارد کنید.');
            $code = (string)random_int(10000,99999);
            $_SESSION['otp']=['phone'=>$phone,'code'=>$code,'expires'=>time()+300];
            flash('کد ورود آزمایشی: '.$code.' — تا اتصال سرویس پیامک، کد روی همین صفحه نمایش داده می‌شود.', 'warn');
            redirect(APP_PATH.'?page=login&step=verify');
        }
        if ($action === 'verify_otp') {
            $otp=$_SESSION['otp']??[]; $code=trim($_POST['code']??'');
            if(empty($otp) || time()>(int)($otp['expires']??0) || !hash_equals((string)($otp['code']??''),$code)) throw new RuntimeException('کد ورود اشتباه یا منقضی شده است.');
            $phone=$otp['phone'];
            $uid=db_write(function(&$db) use($phone){
                foreach($db['users'] as &$u){ if(($u['phone']??'')===$phone){ $u['last_login']=now(); return $u['id']; } }
                $adminPhone=normalize_phone((string)getenv('MARKETPLACE_ADMIN_PHONE'));
                $u=['id'=>id('u'),'phone'=>$phone,'name'=>'کاربر '.$phone,'role'=>($adminPhone && $phone===$adminPhone)?'admin':'user','business'=>false,'created_at'=>now(),'last_login'=>now()];
                $db['users'][]=$u; return $u['id'];
            });
            $_SESSION['uid']=$uid; unset($_SESSION['otp']); flash('ورود با موفقیت انجام شد.'); redirect(APP_PATH);
        }
        $db=db_read(); $user=require_user($db);
        if ($action === 'logout') { session_regenerate_id(true); $_SESSION=[]; flash('از حساب خارج شدید.'); redirect(APP_PATH); }
        if ($action === 'create_post') {
            $title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $cat=trim($_POST['category']??''); $sub=trim($_POST['subcategory']??''); $city=trim($_POST['city']??'');
            if(mb_strlen($title)<5) throw new RuntimeException('عنوان آگهی حداقل ۵ حرف باشد.');
            if(mb_strlen($desc)<15) throw new RuntimeException('توضیحات آگهی کامل‌تر نوشته شود.');
            $images=upload_images();
            $post=['id'=>id('p'),'user_id'=>$user['id'],'title'=>$title,'description'=>$desc,'category'=>$cat,'subcategory'=>$sub,'city'=>$city,'district'=>trim($_POST['district']??''),'price'=>(int)($_POST['price']??0),'images'=>$images,'chat_enabled'=>isset($_POST['chat_enabled']),'phone_visible'=>isset($_POST['phone_visible']),'status'=>'published','featured'=>false,'views'=>0,'bumped_at'=>now(),'created_at'=>now(),'updated_at'=>now()];
            db_write(function(&$db) use($post){$db['posts'][]=$post; notify($db,$post['user_id'],'آگهی «'.$post['title'].'» منتشر شد.');});
            flash('آگهی منتشر شد.'); redirect(APP_PATH.'?id='.urlencode($post['id']));
        }
        if ($action === 'delete_post') {
            $pid=$_POST['post_id']??'';
            db_write(function(&$db) use($pid,$user){ foreach($db['posts'] as &$p) if($p['id']===$pid && ($p['user_id']===$user['id'] || $user['role']==='admin')){$p['status']='deleted';$p['updated_at']=now();} });
            flash('آگهی حذف شد.'); redirect(APP_PATH.'?page=myposts');
        }
        if ($action === 'favorite') {
            $pid=$_POST['post_id']??'';
            db_write(function(&$db) use($pid,$user){$key=$user['id'].'|'.$pid; if(in_array($key,$db['favorites'],true)) $db['favorites']=array_values(array_filter($db['favorites'],fn($x)=>$x!==$key)); else $db['favorites'][]=$key;});
            redirect($_SERVER['HTTP_REFERER'] ?? APP_PATH);
        }
        if ($action === 'report') {
            $pid=$_POST['post_id']??''; $reason=trim($_POST['reason']??'سایر');
            db_write(function(&$db) use($pid,$reason,$user){$db['reports'][]=['id'=>id('r'),'post_id'=>$pid,'user_id'=>$user['id'],'reason'=>$reason,'status'=>'open','created_at'=>now()];});
            flash('گزارش برای بررسی ثبت شد.'); redirect(APP_PATH.'?id='.urlencode($pid));
        }
        if ($action === 'start_chat') {
            $pid=$_POST['post_id']??''; $post=null; foreach($db['posts'] as $p) if($p['id']===$pid) $post=$p;
            if(!$post || $post['user_id']===$user['id']) throw new RuntimeException('امکان شروع این گفتگو وجود ندارد.');
            $cid=db_write(function(&$db) use($post,$user){ foreach($db['conversations'] as $c) if($c['post_id']===$post['id'] && in_array($user['id'],$c['members'],true)) return $c['id']; $c=['id'=>id('c'),'post_id'=>$post['id'],'members'=>[$user['id'],$post['user_id']],'created_at'=>now(),'updated_at'=>now()];$db['conversations'][]=$c;notify($db,$post['user_id'],'یک گفت‌وگوی جدید برای آگهی «'.$post['title'].'» شروع شد.');return $c['id'];});
            redirect(APP_PATH.'?page=chat&cid='.urlencode($cid));
        }
        if ($action === 'send_message') {
            $cid=$_POST['conversation_id']??''; $text=trim($_POST['text']??''); if($text==='') throw new RuntimeException('پیام خالی است.');
            $allowed=false; foreach($db['conversations'] as $c) if($c['id']===$cid && in_array($user['id'],$c['members'],true)) $allowed=true; if(!$allowed) throw new RuntimeException('دسترسی به گفتگو ندارید.');
            db_write(function(&$db) use($cid,$text,$user){$db['messages'][]=['id'=>id('m'),'conversation_id'=>$cid,'sender_id'=>$user['id'],'text'=>mb_substr($text,0,1200),'created_at'=>now()];foreach($db['conversations'] as &$c)if($c['id']===$cid){$c['updated_at']=now();foreach($c['members'] as $member)if($member!==$user['id'])notify($db,$member,'پیام جدید در گفت‌وگو دریافت کردید.');}});
            redirect(APP_PATH.'?page=chat&cid='.urlencode($cid));
        }
        if ($action === 'save_search') {
            $query=trim($_POST['query']??''); $city=trim($_POST['city']??''); $category=trim($_POST['category']??'');
            db_write(function(&$db) use($query,$city,$category,$user){$db['saved_searches'][]=['id'=>id('s'),'user_id'=>$user['id'],'query'=>$query,'city'=>$city,'category'=>$category,'created_at'=>now()];notify($db,$user['id'],'جست‌وجوی شما ذخیره شد؛ اعلان خودکار پس از اتصال کرون فعال می‌شود.');});
            flash('جست‌وجو ذخیره شد.'); redirect(APP_PATH.'?q='.urlencode($query).'&city='.urlencode($city).'&category='.urlencode($category));
        }
        if ($action === 'promote') {
            $pid=$_POST['post_id']??''; $plan=$_POST['plan']??'bump'; $prices=['bump'=>49000,'urgent'=>99000,'featured'=>149000];
            db_write(function(&$db) use($pid,$plan,$prices,$user){$db['payments'][]=['id'=>id('pay'),'user_id'=>$user['id'],'post_id'=>$pid,'plan'=>$plan,'amount'=>$prices[$plan]??49000,'status'=>'pending','created_at'=>now()];notify($db,$user['id'],'درخواست ارتقای آگهی ثبت شد؛ برای فعال‌سازی واقعی باید درگاه پرداخت متصل شود.');});
            flash('درخواست پرداخت ثبت شد. درگاه پرداخت هنوز متصل نشده است.', 'warn'); redirect(APP_PATH.'?id='.urlencode($pid));
        }
        if ($action === 'admin_post_status' && ($user['role']??'')==='admin') {
            $pid=$_POST['post_id']??''; $status=in_array($_POST['status']??'', ['published','rejected','deleted'],true)?$_POST['status']:'published';
            db_write(function(&$db) use($pid,$status){foreach($db['posts'] as &$p)if($p['id']===$pid)$p['status']=$status;}); flash('وضعیت آگهی تغییر کرد.'); redirect(APP_PATH.'?page=admin');
        }
    }
} catch (Throwable $ex) { flash($ex->getMessage(), 'error'); redirect($_SERVER['HTTP_REFERER'] ?? APP_PATH); }

$db=db_read(); $user=current_user($db); $page=$_GET['page']??'home'; $pid=$_GET['id']??'';
if($pid){ foreach($db['posts'] as &$p) if($p['id']===$pid){$p['views']=(int)($p['views']??0)+1; $post=$p; break;} if(isset($post)){db_write(function(&$d) use($pid){foreach($d['posts'] as &$p)if($p['id']===$pid)$p['views']=(int)($p['views']??0)+1;});}}
$q=trim($_GET['q']??''); $filterCity=trim($_GET['city']??''); $filterCat=trim($_GET['category']??''); $min=(int)($_GET['min']??0); $max=(int)($_GET['max']??0);
$visiblePosts=array_values(array_filter($db['posts'],function($p) use($q,$filterCity,$filterCat,$min,$max){ if(($p['status']??'')!=='published')return false; if($q!=='' && mb_stripos(($p['title']??'').' '.($p['description']??''),$q)===false)return false; if($filterCity!==''&&($p['city']??'')!==$filterCity)return false; if($filterCat!==''&&($p['category']??'')!==$filterCat)return false; $price=(int)($p['price']??0); if($min&&$price<$min)return false; if($max&&$price>$max)return false; return true;}));
usort($visiblePosts,fn($a,$b)=>strcmp(($b['featured']?'1':'0').($b['bumped_at']??''),($a['featured']?'1':'0').($a['bumped_at']??'')));
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$title = isset($post) ? e($post['title']).' | '.APP_NAME : APP_NAME.' | خرید، فروش و خدمات محلی';
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=$title?></title><meta name="description" content="بازار وب‌ساز؛ پلتفرم ثبت و جست‌وجوی آگهی، خرید و فروش، خدمات و گفت‌وگوی مستقیم."><meta name="theme-color" content="#0f766e"><link rel="canonical" href="https://websaza.ir<?=e(APP_PATH)?><?=isset($post)?'?id='.e($post['id']):''?>"><style>
*{box-sizing:border-box}body{margin:0;background:#fff;color:#172033;font-family:Tahoma,Arial,sans-serif;font-size:14px}.top{position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #e6e8ed}.bar{max-width:1320px;margin:auto;height:68px;padding:0 20px;display:flex;align-items:center;gap:14px}.brand{font-size:22px;font-weight:900;color:#0f766e;text-decoration:none;white-space:nowrap}.search{flex:1;display:flex;gap:8px}.search input,.search select,.field input,.field select,.field textarea{border:1px solid #d9dde5;border-radius:10px;padding:11px 12px;background:#fff;color:#172033;outline:none;width:100%}.search input:focus,.field input:focus,.field textarea:focus,.field select:focus{border-color:#0f766e;box-shadow:0 0 0 3px #0f766e16}.btn{border:0;border-radius:10px;padding:11px 16px;background:#f1f5f9;color:#243044;text-decoration:none;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:6px}.btn.primary{background:#0f766e;color:#fff}.btn.danger{background:#fff1f2;color:#be123c}.btn.ghost{background:#fff;border:1px solid #d9dde5}.layout{max-width:1320px;margin:24px auto;padding:0 20px;display:grid;grid-template-columns:250px 1fr;gap:28px}.side{position:sticky;top:92px;align-self:start}.side h3{font-size:14px;color:#64748b}.cats{display:flex;flex-direction:column}.cats a{padding:10px 4px;color:#475569;text-decoration:none;border-bottom:1px solid #f1f5f9}.cats a:hover{color:#0f766e}.filters{margin-top:20px;border-top:1px solid #e7e9ee;padding-top:16px}.field{margin:0 0 13px}.field label{display:block;margin:0 0 7px;font-weight:700;color:#475569}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.card{border:1px solid #e3e6eb;border-radius:14px;padding:12px;display:grid;grid-template-columns:1fr 132px;gap:12px;text-decoration:none;color:inherit;min-height:156px;transition:.15s;background:#fff}.card:hover{border-color:#c7cdd7;box-shadow:0 6px 20px #0f172a0b;transform:translateY(-1px)}.card img{width:132px;height:132px;border-radius:10px;object-fit:cover;background:#eef2f7}.card h2{font-size:16px;line-height:1.7;margin:0 0 12px}.price{font-weight:800;margin-top:auto}.muted{color:#7b8494;font-size:12px}.badge{display:inline-flex;padding:4px 7px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:11px;font-weight:800}.panel{max-width:820px;margin:26px auto;padding:0 20px}.box{border:1px solid #e3e6eb;border-radius:16px;padding:20px;background:#fff}.box h1{font-size:22px;margin-top:0}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.full{grid-column:1/-1}.flash{max-width:1320px;margin:12px auto 0;padding:0 20px}.flash div{padding:12px 14px;border-radius:10px;background:#ecfdf5;color:#065f46}.flash .warn{background:#fff7ed;color:#9a3412}.flash .error{background:#fff1f2;color:#be123c}.detail{max-width:1050px;margin:28px auto;padding:0 20px;display:grid;grid-template-columns:1fr 1fr;gap:34px}.gallery-main{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:15px;background:#eef2f7}.thumbs{display:flex;gap:8px;margin-top:8px;overflow:auto}.thumbs img{width:82px;height:64px;object-fit:cover;border-radius:9px}.detail h1{font-size:25px;line-height:1.6}.detail-price{font-size:19px;font-weight:900;margin:18px 0}.rows{border-top:1px solid #e8eaee}.row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eef0f3}.actions{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.chat{max-width:800px;margin:22px auto;padding:0 20px}.messages{height:55vh;overflow:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:15px;padding:14px;display:flex;flex-direction:column;gap:9px}.msg{max-width:78%;padding:10px 12px;border-radius:13px;background:#fff;border:1px solid #e2e8f0;line-height:1.8}.msg.me{align-self:flex-start;background:#ccfbf1;border-color:#99f6e4}.nav-mobile{display:none}.empty{padding:50px 20px;text-align:center;color:#64748b}.admin-table{width:100%;border-collapse:collapse}.admin-table th,.admin-table td{padding:10px;border-bottom:1px solid #edf0f3;text-align:right}.hero{padding:12px 0 20px}.hero h1{margin:0;font-size:20px}.hero p{color:#64748b;margin:6px 0 0}.mini{font-size:11px;color:#94a3b8}.pillbar{display:flex;gap:8px;overflow:auto;padding:0 0 14px}.pill{white-space:nowrap;padding:8px 12px;border-radius:999px;background:#f1f5f9;color:#475569;text-decoration:none}.pill.active{background:#ccfbf1;color:#0f766e;font-weight:800}.profile-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.profile-grid a{border:1px solid #e5e7eb;border-radius:13px;padding:16px;text-decoration:none;color:#334155;text-align:center}.footer{max-width:1320px;margin:40px auto 90px;padding:20px;color:#94a3b8;text-align:center;border-top:1px solid #eef0f3}
@media(max-width:1050px){.grid{grid-template-columns:repeat(2,1fr)}.layout{grid-template-columns:210px 1fr}}@media(max-width:760px){body{padding-bottom:66px}.bar{height:auto;min-height:62px;padding:9px 12px;flex-wrap:wrap}.brand{font-size:19px}.bar>.btn:not(.primary){display:none}.search{order:3;flex-basis:100%}.search select{max-width:110px}.layout{display:block;margin-top:14px;padding:0 12px}.side{position:static}.side .cats,.side h3{display:none}.filters{border:0;margin:0;padding:0}.grid{grid-template-columns:1fr}.card{grid-template-columns:1fr 116px;min-height:140px}.card img{width:116px;height:116px}.detail{grid-template-columns:1fr;padding:0 13px;gap:14px}.panel,.chat{padding:0 12px}.form-grid{grid-template-columns:1fr}.full{grid-column:1}.nav-mobile{position:fixed;bottom:0;left:0;right:0;z-index:40;background:#fff;border-top:1px solid #e3e6eb;display:grid;grid-template-columns:repeat(4,1fr);padding:7px 6px env(safe-area-inset-bottom)}.nav-mobile a{font-size:11px;text-decoration:none;text-align:center;color:#64748b;padding:5px}.nav-mobile b{display:block;font-size:18px;color:#334155}.profile-grid{grid-template-columns:1fr 1fr}.gallery-main{border-radius:10px}.box{padding:15px}.admin-table{font-size:11px}}
</style></head><body>
<header class="top"><div class="bar"><a class="brand" href="<?=APP_PATH?>">بازار وب‌ساز</a><form class="search" method="get" action="<?=APP_PATH?>"><select name="city"><option value="">همه شهرها</option><?php foreach($cities as $c):?><option <?=($filterCity===$c?'selected':'')?> value="<?=e($c)?>"><?=e($c)?></option><?php endforeach?></select><input name="q" value="<?=e($q)?>" placeholder="جست‌وجو در آگهی‌ها"><button class="btn" type="submit">جست‌وجو</button></form><a class="btn ghost" href="<?=APP_PATH?>?page=chats">چت</a><a class="btn ghost" href="<?=APP_PATH?>?page=profile"><?=$user?'حساب من':'ورود'?></a><a class="btn primary" href="<?=APP_PATH?>?page=new">ثبت آگهی</a></div></header>
<?php if($flash):?><div class="flash"><div class="<?=e($flash[1])?>"><?=e($flash[0])?></div></div><?php endif?>
<?php if(isset($post) && ($post['status']??'')==='published'): $owner=null;foreach($db['users'] as $u)if($u['id']===$post['user_id'])$owner=$u; $fav=$user?in_array($user['id'].'|'.$post['id'],$db['favorites'],true):false; ?>
<main class="detail"><section><img class="gallery-main" src="<?=e(post_image($post))?>" alt="<?=e($post['title'])?>"><div class="thumbs"><?php foreach($post['images'] as $im):?><img src="/marketplace-uploads/<?=e(rawurlencode($im))?>" alt=""><?php endforeach?></div></section><section><div class="muted"><?=e($post['city'])?><?=!empty($post['district'])?'، '.e($post['district']):''?> · <?=e(date('Y/m/d',strtotime($post['created_at'])))?></div><h1><?=e($post['title'])?></h1><div class="detail-price"><?=e(money($post['price']))?></div><div class="rows"><div class="row"><span>دسته‌بندی</span><strong><?=e($post['category'].' / '.$post['subcategory'])?></strong></div><div class="row"><span>بازدید</span><strong><?=number_format((int)$post['views'])?></strong></div></div><div class="actions"><?php if($user && $user['id']!==$post['user_id'] && $post['chat_enabled']):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="start_chat"><input type="hidden" name="post_id" value="<?=e($post['id'])?>"><button class="btn primary">چت با فروشنده</button></form><?php endif?><?php if($post['phone_visible'] && $owner):?><a class="btn ghost" href="tel:<?=e($owner['phone'])?>">نمایش شماره</a><?php endif?><?php if($user):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="favorite"><input type="hidden" name="post_id" value="<?=e($post['id'])?>"><button class="btn ghost"><?=$fav?'★ ذخیره شده':'☆ ذخیره آگهی'?></button></form><?php endif?></div><div class="box"><h3>توضیحات</h3><div style="white-space:pre-line;line-height:2"><?=e($post['description'])?></div></div><?php if($user && $user['id']===$post['user_id']):?><div class="box" style="margin-top:12px"><h3>افزایش بازدید</h3><div class="actions"><?php foreach(['bump'=>'نردبان ۴۹,۰۰۰','urgent'=>'فوری ۹۹,۰۰۰','featured'=>'ویژه ۱۴۹,۰۰۰'] as $pl=>$label):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="promote"><input type="hidden" name="post_id" value="<?=e($post['id'])?>"><input type="hidden" name="plan" value="<?=$pl?>"><button class="btn ghost"><?=$label?></button></form><?php endforeach?></div><div class="mini">پرداخت‌ها ثبت می‌شوند؛ فعال‌سازی نهایی به اتصال درگاه نیاز دارد.</div></div><?php endif?><?php if($user && $user['id']!==$post['user_id']):?><details style="margin-top:18px"><summary>گزارش آگهی</summary><form method="post" class="field" style="margin-top:10px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="report"><input type="hidden" name="post_id" value="<?=e($post['id'])?>"><select name="reason"><option>محتوای نامناسب</option><option>قیمت یا اطلاعات نادرست</option><option>کلاهبرداری یا رفتار مشکوک</option><option>آگهی تکراری</option></select><button class="btn danger" style="margin-top:8px">ثبت گزارش</button></form></details><?php endif?></section></main>
<?php elseif($page==='login'):?><main class="panel"><div class="box"><h1>ورود یا ثبت‌نام</h1><p class="muted">با شماره موبایل وارد شوید.</p><?php if(($_GET['step']??'')==='verify' && !empty($_SESSION['otp'])):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="verify_otp"><div class="field"><label>کد ۵ رقمی</label><input name="code" inputmode="numeric" maxlength="5" autocomplete="one-time-code" required></div><button class="btn primary">تأیید و ورود</button></form><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="request_otp"><div class="field"><label>شماره موبایل</label><input name="phone" inputmode="tel" placeholder="0912xxxxxxx" required></div><button class="btn primary">دریافت کد ورود</button></form><?php endif?></div></main>
<?php elseif($page==='new'): $u=require_user($db);?><main class="panel"><div class="box"><h1>ثبت آگهی جدید</h1><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create_post"><div class="field full"><label>عنوان آگهی</label><input name="title" maxlength="90" required placeholder="مثلاً پژو ۲۰۷ اتومات مدل ۱۴۰۳"></div><div class="field"><label>دسته‌بندی</label><select name="category" id="category" required><option value="">انتخاب کنید</option><?php foreach($categories as $cat=>$subs):?><option value="<?=e($cat)?>"><?=e($cat)?></option><?php endforeach?></select></div><div class="field"><label>زیر دسته</label><select name="subcategory" id="subcategory" required><option value="">ابتدا دسته را انتخاب کنید</option></select></div><div class="field"><label>شهر</label><select name="city" required><option value="">انتخاب شهر</option><?php foreach($cities as $c):?><option><?=e($c)?></option><?php endforeach?></select></div><div class="field"><label>محله</label><input name="district" placeholder="مثلاً معالی‌آباد"></div><div class="field"><label>قیمت (تومان)</label><input type="number" name="price" min="0" step="1000" placeholder="برای توافقی صفر بگذارید"></div><div class="field"><label>تصاویر (حداکثر ۸ عکس)</label><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></div><div class="field full"><label>توضیحات</label><textarea name="description" rows="7" maxlength="3000" required placeholder="توضیحات کامل و شفاف بنویسید..."></textarea></div><label><input type="checkbox" name="chat_enabled" checked> چت برای این آگهی فعال باشد</label><label><input type="checkbox" name="phone_visible" checked> شماره تماس نمایش داده شود</label><div class="full"><button class="btn primary" style="width:100%">انتشار آگهی</button></div></form></div></main><script>const cats=<?=json_encode($categories,JSON_UNESCAPED_UNICODE)?>;const c=document.getElementById('category'),s=document.getElementById('subcategory');c.addEventListener('change',()=>{s.innerHTML='<option value="">انتخاب زیر دسته</option>';(cats[c.value]||[]).forEach(x=>{const o=document.createElement('option');o.textContent=x;o.value=x;s.appendChild(o)})})</script>
<?php elseif($page==='profile'):?><main class="panel"><div class="box"><?php if(!$user):?><h1>حساب کاربری</h1><p>برای مدیریت آگهی‌ها، چت و علاقه‌مندی‌ها وارد شوید.</p><a class="btn primary" href="<?=APP_PATH?>?page=login">ورود / ثبت‌نام</a><?php else:?><h1><?=e($user['name'])?></h1><div class="muted"><?=e($user['phone'])?></div><div class="profile-grid" style="margin-top:18px"><a href="<?=APP_PATH?>?page=myposts">آگهی‌های من</a><a href="<?=APP_PATH?>?page=favorites">نشان‌شده‌ها</a><a href="<?=APP_PATH?>?page=chats">چت‌ها</a><a href="<?=APP_PATH?>?page=notifications">اعلان‌ها</a><a href="<?=APP_PATH?>?page=saved">جست‌وجوهای ذخیره</a><?php if(($user['role']??'')==='admin'):?><a href="<?=APP_PATH?>?page=admin">مدیریت</a><?php endif?></div><form method="post" style="margin-top:20px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="logout"><button class="btn danger">خروج</button></form><?php endif?></div></main>
<?php elseif($page==='myposts'): $u=require_user($db); $mine=array_filter($db['posts'],fn($p)=>$p['user_id']===$u['id']);?><main class="panel"><h1>آگهی‌های من</h1><?php foreach($mine as $p):?><div class="box" style="margin-bottom:10px;display:flex;gap:12px;align-items:center"><img src="<?=e(post_image($p))?>" style="width:90px;height:80px;object-fit:cover;border-radius:9px"><div style="flex:1"><a href="<?=APP_PATH?>?id=<?=e($p['id'])?>" style="font-weight:800;color:#172033;text-decoration:none"><?=e($p['title'])?></a><div class="muted"><?=e($p['status'])?> · <?=number_format((int)$p['views'])?> بازدید</div></div><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?=e($p['id'])?>"><button class="btn danger">حذف</button></form></div><?php endforeach?><?php if(!$mine):?><div class="empty">هنوز آگهی ثبت نکرده‌اید.</div><?php endif?></main>
<?php elseif($page==='favorites'): $u=require_user($db); $keys=array_filter($db['favorites'],fn($k)=>str_starts_with($k,$u['id'].'|')); $ids=array_map(fn($k)=>explode('|',$k,2)[1],$keys); $favPosts=array_filter($db['posts'],fn($p)=>in_array($p['id'],$ids,true)&&$p['status']==='published');?><main class="panel"><h1>نشان‌شده‌ها</h1><div class="grid"><?php foreach($favPosts as $p):?><a class="card" href="<?=APP_PATH?>?id=<?=e($p['id'])?>"><div><h2><?=e($p['title'])?></h2><div class="price"><?=e(money($p['price']))?></div><div class="muted"><?=e($p['city'])?></div></div><img src="<?=e(post_image($p))?>"></a><?php endforeach?></div></main>
<?php elseif($page==='chats'): $u=require_user($db); $convs=array_filter($db['conversations'],fn($c)=>in_array($u['id'],$c['members'],true));?><main class="panel"><h1>گفت‌وگوها</h1><?php foreach($convs as $c): $p=null;foreach($db['posts'] as $pp)if($pp['id']===$c['post_id'])$p=$pp;?><a class="box" style="display:block;text-decoration:none;color:inherit;margin-bottom:9px" href="<?=APP_PATH?>?page=chat&cid=<?=e($c['id'])?>"><strong><?=e($p['title']??'گفت‌وگو')?></strong><div class="muted"><?=e(date('Y/m/d H:i',strtotime($c['updated_at'])))?></div></a><?php endforeach?><?php if(!$convs):?><div class="empty">هنوز گفت‌وگویی ندارید.</div><?php endif?></main>
<?php elseif($page==='chat'): $u=require_user($db); $cid=$_GET['cid']??''; $conv=null;foreach($db['conversations'] as $c)if($c['id']===$cid&&in_array($u['id'],$c['members'],true))$conv=$c;if(!$conv):?><div class="empty">گفت‌وگو پیدا نشد.</div><?php else:$msgs=array_filter($db['messages'],fn($m)=>$m['conversation_id']===$cid);?><main class="chat"><div class="messages" id="messages"><?php foreach($msgs as $m):?><div class="msg <?=$m['sender_id']===$u['id']?'me':''?>"><?=nl2br(e($m['text']))?><div class="mini"><?=e(date('H:i',strtotime($m['created_at'])))?></div></div><?php endforeach?></div><form method="post" style="display:flex;gap:8px;margin-top:9px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="conversation_id" value="<?=e($cid)?>"><input name="text" maxlength="1200" required placeholder="پیام بنویسید..." style="flex:1;border:1px solid #d9dde5;border-radius:10px;padding:12px"><button class="btn primary">ارسال</button></form></main><script>const m=document.getElementById('messages');m.scrollTop=m.scrollHeight</script><?php endif?>
<?php elseif($page==='notifications'): $u=require_user($db); $notes=array_reverse(array_filter($db['notifications'],fn($n)=>$n['user_id']===$u['id']));?><main class="panel"><h1>اعلان‌ها</h1><?php foreach($notes as $n):?><div class="box" style="margin-bottom:8px"><?=e($n['text'])?><div class="mini"><?=e(date('Y/m/d H:i',strtotime($n['created_at'])))?></div></div><?php endforeach?><?php if(!$notes):?><div class="empty">اعلانی ندارید.</div><?php endif?></main>
<?php elseif($page==='saved'): $u=require_user($db); $saved=array_filter($db['saved_searches'],fn($s)=>$s['user_id']===$u['id']);?><main class="panel"><h1>جست‌وجوهای ذخیره‌شده</h1><?php foreach($saved as $s):?><a class="box" style="display:block;text-decoration:none;color:inherit;margin-bottom:8px" href="<?=APP_PATH?>?q=<?=urlencode($s['query'])?>&city=<?=urlencode($s['city'])?>&category=<?=urlencode($s['category'])?>"><?=e($s['query']?:'همه آگهی‌ها')?> · <?=e($s['city']?:'همه شهرها')?> · <?=e($s['category']?:'همه دسته‌ها')?></a><?php endforeach?></main>
<?php elseif($page==='admin' && $user && ($user['role']??'')==='admin'):?><main class="panel" style="max-width:1100px"><h1>پنل مدیریت بازار</h1><div class="profile-grid"><div class="box"><strong><?=count($db['users'])?></strong><div class="muted">کاربر</div></div><div class="box"><strong><?=count($db['posts'])?></strong><div class="muted">آگهی</div></div><div class="box"><strong><?=count($db['reports'])?></strong><div class="muted">گزارش</div></div></div><div class="box" style="margin-top:14px;overflow:auto"><table class="admin-table"><tr><th>آگهی</th><th>شهر</th><th>وضعیت</th><th>عملیات</th></tr><?php foreach(array_reverse($db['posts']) as $p):?><tr><td><?=e($p['title'])?></td><td><?=e($p['city'])?></td><td><?=e($p['status'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_post_status"><input type="hidden" name="post_id" value="<?=e($p['id'])?>"><select name="status"><option>published</option><option>rejected</option><option>deleted</option></select><button class="btn">ثبت</button></form></td></tr><?php endforeach?></table></div></main>
<?php else:?>
<main class="layout"><aside class="side"><h3>دسته‌ها</h3><div class="cats"><a href="<?=APP_PATH?>">همه آگهی‌ها</a><?php foreach($categories as $cat=>$subs):?><a href="<?=APP_PATH?>?category=<?=urlencode($cat)?>"><?=e($cat)?></a><?php endforeach?></div><form class="filters" method="get"><div class="field"><label>دسته‌بندی</label><select name="category"><option value="">همه دسته‌ها</option><?php foreach($categories as $cat=>$subs):?><option <?=$filterCat===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach?></select></div><div class="field"><label>حداقل قیمت</label><input type="number" name="min" value="<?=$min?:''?>"></div><div class="field"><label>حداکثر قیمت</label><input type="number" name="max" value="<?=$max?:''?>"></div><input type="hidden" name="q" value="<?=e($q)?>"><input type="hidden" name="city" value="<?=e($filterCity)?>"><button class="btn" style="width:100%">اعمال فیلتر</button></form></aside><section><div class="hero"><h1><?=($q||$filterCat||$filterCity)?'نتایج جست‌وجو':'آگهی‌های تازه'?></h1><p><?=number_format(count($visiblePosts))?> آگهی پیدا شد</p></div><div class="pillbar"><?php foreach(array_slice(array_keys($categories),0,6) as $cat):?><a class="pill <?=$filterCat===$cat?'active':''?>" href="<?=APP_PATH?>?category=<?=urlencode($cat)?>"><?=e($cat)?></a><?php endforeach?></div><?php if($user && ($q||$filterCity||$filterCat)):?><form method="post" style="margin-bottom:14px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="save_search"><input type="hidden" name="query" value="<?=e($q)?>"><input type="hidden" name="city" value="<?=e($filterCity)?>"><input type="hidden" name="category" value="<?=e($filterCat)?>"><button class="btn ghost">ذخیره این جست‌وجو</button></form><?php endif?><div class="grid"><?php foreach($visiblePosts as $p):?><a class="card" href="<?=APP_PATH?>?id=<?=e($p['id'])?>"><div style="display:flex;flex-direction:column"><div><?php if(!empty($p['featured'])):?><span class="badge">ویژه</span><?php endif?><h2><?=e($p['title'])?></h2></div><div class="price"><?=e(money($p['price']))?></div><div class="muted"><?=e($p['city'])?><?=!empty($p['district'])?'، '.e($p['district']):''?></div></div><img src="<?=e(post_image($p))?>" loading="lazy" alt="<?=e($p['title'])?>"></a><?php endforeach?></div><?php if(!$visiblePosts):?><div class="empty"><h3>هنوز آگهی‌ای مطابق این فیلتر نیست</h3><p>اولین آگهی را ثبت کنید.</p><a class="btn primary" href="<?=APP_PATH?>?page=new">ثبت آگهی</a></div><?php endif?></section></main>
<?php endif?>
<nav class="nav-mobile"><a href="<?=APP_PATH?>"><b>⌂</b>آگهی‌ها</a><a href="<?=APP_PATH?>?page=favorites"><b>☆</b>نشان‌ها</a><a href="<?=APP_PATH?>?page=chats"><b>◌</b>چت</a><a href="<?=APP_PATH?>?page=profile"><b>☻</b>حساب من</a></nav><footer class="footer">بازار وب‌ساز — پلتفرم مستقل آگهی و خرید و فروش · نسخه MVP</footer></body></html>