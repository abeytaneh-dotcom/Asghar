<?php
declare(strict_types=1);

function nzh_norm_phone(string $v): string {
    $p=preg_replace('/\D+/','',$v)??'';
    if(str_starts_with($p,'0098'))$p=substr($p,4);
    elseif(str_starts_with($p,'98')&&strlen($p)===12)$p='0'.substr($p,2);
    if(str_starts_with($p,'9')&&strlen($p)===10)$p='0'.$p;
    return preg_match('/^09\d{9}$/',$p)?$p:'';
}
function nzh_ip(): string { $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'0.0.0.0'; }
function nzh_ua(): string { return mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,200); }
function nzh_client_key(): string { return hash('sha256',nzh_ip().'|'.nzh_ua().'|niyazestan'); }
function nzh_rate_file(): string { return __DIR__.'/.marketplace-data/rate-limit.json'; }
function nzh_rate_hit(string $key,int $limit,int $windowSeconds,string $message=''): void {
    $file=nzh_rate_file();$dir=dirname($file);if(!is_dir($dir))@mkdir($dir,0750,true);$fh=@fopen($file,'c+');if(!$fh)return;if(!flock($fh,LOCK_EX)){fclose($fh);return;}
    try{
        rewind($fh);$data=json_decode(stream_get_contents($fh)?:'',true);if(!is_array($data))$data=[];$now=time();$rows=array_values(array_filter((array)($data[$key]??[]),static fn($t)=>is_int($t)&&$t>$now-$windowSeconds));
        if(count($rows)>=$limit)throw new RuntimeException($message!==''?$message:'تعداد درخواست‌های شما زیاد است. کمی بعد دوباره تلاش کنید.');
        $rows[]=$now;$data[$key]=$rows;
        if(count($data)>3000)foreach($data as$k=>$vals)if(!array_filter((array)$vals,static fn($t)=>is_int($t)&&$t>$now-86400))unset($data[$k]);
        rewind($fh);ftruncate($fh,0);fwrite($fh,json_encode($data,JSON_UNESCAPED_SLASHES));fflush($fh);
    }finally{flock($fh,LOCK_UN);fclose($fh);}
}
function nzh_guard_request(): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;nzc_session_open();$action=(string)($_POST['action']??'');$client=nzh_client_key();$ipHash=hash('sha256','ip|'.nzh_ip());$uid=trim((string)($_SESSION['uid']??''));
    if($action==='request_otp'){
        $phone=nzh_norm_phone((string)($_POST['phone']??''));
        nzh_rate_hit('otp60|'.hash('sha256',$phone.'|'.nzh_ip()),1,60,'برای دریافت مجدد کد ورود باید ۶۰ ثانیه صبر کنید.');
        if($phone!=='')nzh_rate_hit('otphour-phone|'.hash('sha256',$phone),6,3600,'درخواست کد برای این شماره بیش از حد مجاز بوده است. کمی بعد دوباره تلاش کنید.');
        nzh_rate_hit('otphour-ip|'.$ipHash,20,3600,'تعداد درخواست کد از این اتصال بیش از حد مجاز بوده است.');return;
    }
    if($action==='verify_otp'){
        $phone=nzh_norm_phone((string)(($_SESSION['otp']['phone']??'')));
        nzh_rate_hit('otpverify|'.hash('sha256',$phone.'|'.nzh_ip()),10,600,'تعداد تلاش ورود زیاد است. چند دقیقه بعد دوباره تلاش کنید.');return;
    }
    $suffix=$uid!==''?'|u:'.$uid:'|c:'.$client;
    match($action){
        'create_post'=>nzh_rate_hit('create'.$suffix,6,3600),
        'send_message','support_send'=>nzh_rate_hit('message'.$suffix,40,60),
        'report_post'=>nzh_rate_hit('report'.$suffix,12,3600),
        'save_search'=>nzh_rate_hit('save-search'.$suffix,30,3600),
        default=>nzh_rate_hit('post'.$suffix,100,60),
    };
}
function nzh_headers(): void {
    if(headers_sent())return;
    $page=(string)($_GET['page']??'');$private=in_array($page,['login','new','profile','myposts','chat','chats','admin','support','search','saved-searches','services','edit'],true);
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-site');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; connect-src 'self' https://nominatim.openstreetmap.org https://cdn.jsdelivr.net https://*.jsdelivr.net https://storage.googleapis.com; worker-src 'self' blob: https://cdn.jsdelivr.net; font-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if($private){header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('X-Robots-Tag: noindex, nofollow, noarchive');}
}
function nzh_meta_replace(string $html,string $name,string $content): string {
    $safe=htmlspecialchars($content,ENT_QUOTES,'UTF-8');$pattern='/<meta\s+name="'.preg_quote($name,'/').'"[^>]*>/iu';$replacement='<meta name="'.$name.'" content="'.$safe.'">';if(preg_match($pattern,$html))return preg_replace($pattern,$replacement,$html,1)??$html;return str_replace('</head>',$replacement."\n</head>",$html);
}
function nzh_otp_ui(string $html): string {
    nzc_session_open();$otp=$_SESSION['otp']??[];$sentAt=is_array($otp)?(int)($otp['sent_at']??0):0;$remaining=$sentAt>0?max(0,60-(time()-$sentAt)):0;$phone=is_array($otp)?(string)($otp['phone']??''):'';$csrf=(string)($_SESSION['csrf']??'');$cfg=json_encode(['remaining'=>$remaining,'phone'=>$phone,'csrf'=>$csrf],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($cfg===false)return$html;
    $ui='<style id="nz-otp-cooldown-style">.nz-otp-resend{margin-top:14px}.nz-otp-resend button:disabled,form[data-nz-otp-request] button:disabled{opacity:.55;cursor:not-allowed}.nz-otp-countdown{display:block;text-align:center;margin-top:9px;font-size:12px;color:#70757d;min-height:19px}</style>';
    $ui.='<script id="nz-otp-cooldown-script">(()=>{const cfg='.$cfg.';const KEY="nzOtpCooldownUntil";const fa=n=>new Intl.NumberFormat("fa-IR",{useGrouping:false}).format(n);let until=0;try{until=Number(sessionStorage.getItem(KEY)||0)||0}catch(_){}if(cfg.remaining>0)until=Math.max(until,Date.now()+cfg.remaining*1000);const statusFor=btn=>{let s=btn.parentElement&&btn.parentElement.querySelector(".nz-otp-countdown");if(!s){s=document.createElement("small");s.className="nz-otp-countdown";btn.insertAdjacentElement("afterend",s)}return s};const bind=(form,btn,status)=>{const original=btn.textContent.trim()||"دریافت کد ورود";const render=()=>{const sec=Math.max(0,Math.ceil((until-Date.now())/1000));btn.disabled=sec>0;status.textContent=sec>0?"دریافت مجدد کد تا "+fa(sec)+" ثانیه دیگر":""};form.addEventListener("submit",()=>{until=Date.now()+60000;try{sessionStorage.setItem(KEY,String(until))}catch(_){}btn.disabled=true;btn.textContent="در حال ارسال کد…"});render();setInterval(()=>{btn.textContent=original;render()},250)};const req=document.querySelector("form input[name=action][value=request_otp]");if(req){const f=req.form,b=f.querySelector("button");if(b){f.dataset.nzOtpRequest="1";bind(f,b,statusFor(b))}}const verify=document.querySelector("form input[name=action][value=verify_otp]");if(verify&&cfg.phone&&!document.querySelector(".nz-otp-resend")){const vf=verify.form,f=document.createElement("form");f.method="post";f.className="nz-otp-resend";f.innerHTML=`<input type="hidden" name="csrf" value="${cfg.csrf}"><input type="hidden" name="action" value="request_otp"><input type="hidden" name="phone" value="${cfg.phone}"><button type="submit" class="btn ghost" style="width:100%">دریافت مجدد کد</button><small class="nz-otp-countdown"></small>`;vf.insertAdjacentElement("afterend",f);bind(f,f.querySelector("button"),f.querySelector("small"))}})();</script>';
    return str_replace('</body>',$ui.'</body>',$html);
}
function nzh_seo(string $html): string {
    $html=str_replace(['</style>\\n</head>','<!-- nz-community-v1 -->\\n</body>'],["</style>\n</head>","<!-- nz-community-v1 -->\n</body>"],$html);
    $page=(string)($_GET['page']??'home');$id=(string)($_GET['id']??'');$private=in_array($page,['login','new','profile','myposts','chat','chats','admin','support','search','saved-searches','services','edit'],true)||(!empty($_GET['q'])&&$id==='');$html=nzh_meta_replace($html,'robots',$private?'noindex,nofollow,noarchive':'index,follow,max-image-preview:large');
    $canonical='https://websaza.ir/nz';$title='نیازستان | آگهی خرید، فروش و خدمات';$desc='نیازستان؛ آگهی خرید، فروش و خدمات با جست‌وجوی محلی، چت و ثبت رایگان آگهی.';$image='https://websaza.ir/niazestan-icon-512.php?v=20261019-2';$schema=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'نیازستان','url'=>'https://websaza.ir/nz'];
    if($id!=='')foreach(nzc_db_read()['posts']as$p){if(($p['id']??'')!==$id||($p['status']??'')!=='published')continue;$canonical.='?id='.rawurlencode($id);$title=trim((string)($p['title']??'آگهی')).' | نیازستان';$desc=mb_substr(trim(preg_replace('/\s+/u',' ',(string)($p['description']??''))??''),0,155);if(!empty($p['images'][0]))$image='https://websaza.ir/marketplace-uploads/'.rawurlencode(basename((string)$p['images'][0]));$schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>(string)($p['title']??''),'description'=>$desc,'image'=>[$image],'category'=>trim((string)($p['category']??'').' / '.(string)($p['subcategory']??'')),'url'=>$canonical];if((int)($p['price']??0)>0)$schema['offers']=['@type'=>'Offer','price'=>(int)$p['price']*10,'priceCurrency'=>'IRR','availability'=>'https://schema.org/InStock','url'=>$canonical];break;}
    $html=preg_replace('/<title>.*?<\/title>/isu','<title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</title>',$html,1)??$html;$html=nzh_meta_replace($html,'description',$desc);
    $og="\n".'<link rel="canonical" href="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'">'."\n".'<link rel="sitemap" type="application/xml" href="https://websaza.ir/nz-sitemap.xml">'."\n".'<meta property="og:type" content="website"><meta property="og:site_name" content="نیازستان"><meta property="og:title" content="'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'"><meta property="og:description" content="'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'"><meta property="og:url" content="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'"><meta property="og:image" content="'.htmlspecialchars($image,ENT_QUOTES,'UTF-8').'">'."\n".'<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";$html=str_replace('</head>',$og.'</head>',$html);if($page==='login')$html=nzh_otp_ui($html);return$html;
}
