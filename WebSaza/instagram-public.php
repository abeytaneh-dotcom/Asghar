<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
function allowedImageHost($host){
    $host = strtolower($host);
    foreach (['cdninstagram.com','fbcdn.net','instagram.com'] as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.'.$suffix)) return true;
    }
    return false;
}

if (isset($_GET['img'])) {
    $url = base64_decode(strtr($_GET['img'], '-_', '+/'), true);
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) { http_response_code(400); exit('bad image url'); }
    $p = parse_url($url);
    if (($p['scheme'] ?? '') !== 'https' || !allowedImageHost($p['host'] ?? '')) { http_response_code(403); exit('blocked'); }
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'Mozilla/5.0',CURLOPT_HTTPHEADER=>['Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8']]);
    $body = curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $type=curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
    if (!$body || $status<200 || $status>=400) { http_response_code(502); exit('image fetch failed'); }
    header('Content-Type: '.($type ?: 'image/jpeg')); echo $body; exit;
}

$username = trim($_GET['u'] ?? '');
$username = ltrim($username, '@');
$username = preg_replace('/[^A-Za-z0-9._]/','',$username);
$result = null;
if ($username !== '') {
    $target = 'https://www.instagram.com/'.$username.'/';
    $ch = curl_init($target);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_MAXREDIRS=>5,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_ENCODING=>'',
        CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36',
        CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8','Accept-Language: en-US,en;q=0.9']
    ]);
    $html = curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $final=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL); curl_close($ch);
    $meta=[];
    if (is_string($html) && $html!=='') {
        libxml_use_internal_errors(true);
        $dom=new DOMDocument(); @$dom->loadHTML($html); $xp=new DOMXPath($dom);
        foreach (['og:title'=>'title','og:description'=>'description','og:image'=>'image'] as $prop=>$key) {
            $n=$xp->query('//meta[@property="'.$prop.'"]/@content');
            if ($n && $n->length) $meta[$key]=$n->item(0)->nodeValue;
        }
        $tn=$xp->query('//title'); if ((!isset($meta['title'])||!$meta['title']) && $tn && $tn->length) $meta['title']=$tn->item(0)->textContent;
    }
    $result=['status'=>$status,'final'=>$final,'error'=>$err,'meta'=>$meta,'bytes'=>is_string($html)?strlen($html):0,'target'=>$target];
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>تست نمایش پیج عمومی Instagram | وب ساز</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#12151a;font-family:Tahoma,Arial,sans-serif;padding:22px}.wrap{max-width:760px;margin:auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 14px 45px rgba(0,0,0,.07)}h1{font-size:22px;margin:0 0 10px}.muted{color:#68707a;line-height:1.9;font-size:13px}.form{display:flex;gap:10px;margin:20px 0}.form input{flex:1;border:1px solid #d7dce3;border-radius:12px;padding:13px 14px;font-size:16px;direction:ltr;text-align:left}.form button{border:0;border-radius:12px;background:#111827;color:#fff;padding:0 18px;font-weight:700}.ok{background:#eaf8ef;color:#137333;padding:13px 15px;border-radius:12px;margin:15px 0}.bad{background:#fdecec;color:#b42318;padding:13px 15px;border-radius:12px;margin:15px 0}.profile{margin-top:18px;border-top:1px solid #eceff3;padding-top:18px}.profile img{width:132px;height:132px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb;display:block;margin:0 auto 16px}.title{font-weight:800;font-size:18px;text-align:center}.desc{margin-top:10px;line-height:1.9;color:#444}.stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.box{background:#f7f8fa;border-radius:12px;padding:12px}.lab{font-size:12px;color:#6b7280}.val{margin-top:6px;direction:ltr;text-align:left;word-break:break-all;font-family:monospace}.note{margin-top:18px;font-size:13px;color:#5d6570;line-height:1.9}@media(max-width:560px){.form{flex-direction:column}.form button{padding:13px}.stats{grid-template-columns:1fr}}</style></head><body><div class="wrap"><div class="card"><h1>نمایش آزمایشی پیج عمومی Instagram از طریق وب ساز</h1><div class="muted">یک نام کاربری عمومی وارد کن. درخواست از سرور وب‌ساز به Instagram می‌رود، نه مستقیم از گوشی شما.</div><form class="form" method="get"><input name="u" value="<?=h($username)?>" placeholder="مثلاً instagram"><button>تست پیج</button></form>
<?php if($result): $good=$result['status']>=200 && $result['status']<400 && !empty($result['meta']['title']); ?>
<div class="<?=$good?'ok':'bad'?>"><?=$good?'✅ پیج از سمت سرور دریافت شد':'❌ پیج کامل دریافت نشد'?></div>
<div class="stats"><div class="box"><div class="lab">HTTP Status</div><div class="val"><?=h($result['status'])?></div></div><div class="box"><div class="lab">Downloaded bytes</div><div class="val"><?=h($result['bytes'])?></div></div></div>
<?php if($good): ?><div class="profile"><?php if(!empty($result['meta']['image'])):$enc=rtrim(strtr(base64_encode($result['meta']['image']),'+/','-_'),'=');?><img src="?img=<?=h($enc)?>" alt="profile image"><?php endif; ?><div class="title"><?=h($result['meta']['title'] ?? '')?></div><div class="desc"><?=nl2br(h($result['meta']['description'] ?? ''))?></div></div><?php endif; ?>
<?php if($result['error']):?><div class="bad"><?=h($result['error'])?></div><?php endif; ?>
<?php endif; ?>
<div class="note">این نسخه فقط پیج‌های عمومی را آزمایش می‌کند و هیچ فرم ورود، رمز عبور یا کوکی اینستاگرام را دریافت یا ذخیره نمی‌کند.</div></div></div></body></html>