<?php
declare(strict_types=1);

function nzh_client_key(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,160);
    return hash('sha256', $ip.'|'.$ua.'|niyazestan');
}

function nzh_rate_file(): string { return __DIR__.'/.marketplace-data/rate-limit.json'; }

function nzh_rate_limit(string $bucket, int $limit, int $windowSeconds): void {
    $file = nzh_rate_file(); $dir = dirname($file); if (!is_dir($dir)) @mkdir($dir,0755,true);
    $fh = @fopen($file,'c+'); if (!$fh) return;
    if (!flock($fh, LOCK_EX)) { fclose($fh); return; }
    rewind($fh); $data = json_decode(stream_get_contents($fh) ?: '', true); if (!is_array($data)) $data=[];
    $now=time(); $key=nzh_client_key().'|'.$bucket; $rows=array_values(array_filter((array)($data[$key]??[]), static fn($t)=>is_int($t)&&$t>$now-$windowSeconds));
    if (count($rows) >= $limit) {
        flock($fh,LOCK_UN); fclose($fh);
        if ($bucket === 'otp_request') throw new RuntimeException('برای دریافت مجدد کد ورود باید ۶۰ ثانیه صبر کنید.');
        throw new RuntimeException('تعداد درخواست‌های شما زیاد است. چند دقیقه بعد دوباره تلاش کنید.');
    }
    $rows[]=$now; $data[$key]=$rows;
    if (count($data)>1500) {
        foreach ($data as $k=>$vals) if (!array_filter((array)$vals, static fn($t)=>is_int($t)&&$t>$now-86400)) unset($data[$k]);
    }
    rewind($fh); ftruncate($fh,0); fwrite($fh,json_encode($data,JSON_UNESCAPED_SLASHES)); fflush($fh); flock($fh,LOCK_UN); fclose($fh);
}

function nzh_guard_request(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $action=(string)($_POST['action']??'');
    match($action) {
        'create_post' => nzh_rate_limit('create_post', 6, 3600),
        'request_otp' => nzh_rate_limit('otp_request', 1, 60),
        'verify_otp' => nzh_rate_limit('otp_verify', 10, 600),
        'send_message','support_send' => nzh_rate_limit('message', 35, 60),
        'report_post' => nzh_rate_limit('report', 12, 3600),
        default => nzh_rate_limit('generic_post', 80, 60),
    };
}

function nzh_headers(): void {
    if (headers_sent()) return;
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-site');
    header("Content-Security-Policy-Report-Only: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; connect-src 'self' https://nominatim.openstreetmap.org https://cdn.jsdelivr.net https://*.jsdelivr.net https://storage.googleapis.com; worker-src 'self' blob: https://cdn.jsdelivr.net; font-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

function nzh_meta_replace(string $html, string $name, string $content): string {
    $safe = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $pattern = '/<meta\s+name="'.preg_quote($name,'/').'"[^>]*>/iu';
    $replacement = '<meta name="'.$name.'" content="'.$safe.'">';
    if (preg_match($pattern,$html)) return preg_replace($pattern,$replacement,$html,1) ?? $html;
    return str_replace('</head>', $replacement."\n</head>", $html);
}

function nzh_otp_ui(string $html): string {
    nzc_session_open();
    $otp = $_SESSION['otp'] ?? [];
    $sentAt = is_array($otp) ? (int)($otp['sent_at'] ?? 0) : 0;
    $remaining = $sentAt > 0 ? max(0, 60 - (time() - $sentAt)) : 0;
    $phone = is_array($otp) ? (string)($otp['phone'] ?? '') : '';
    $csrf = (string)($_SESSION['csrf'] ?? '');
    $cfg = json_encode(['remaining'=>$remaining,'phone'=>$phone,'csrf'=>$csrf], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if ($cfg === false) return $html;

    $ui = '<style id="nz-otp-cooldown-style">.nz-otp-resend{margin-top:14px}.nz-otp-resend button:disabled,form[data-nz-otp-request] button:disabled{opacity:.55;cursor:not-allowed}.nz-otp-countdown{display:block;text-align:center;margin-top:9px;font-size:12px;color:#70757d;min-height:19px}</style>';
    $ui .= '<script id="nz-otp-cooldown-script">(()=>{const cfg='.$cfg.';const KEY="nzOtpCooldownUntil";const PHONE="nzOtpPhone";const fa=n=>new Intl.NumberFormat("fa-IR",{useGrouping:false}).format(n);let until=0;try{until=Number(sessionStorage.getItem(KEY)||0)||0}catch(_){}if(cfg.remaining>0)until=Math.max(until,Date.now()+cfg.remaining*1000);const statusFor=btn=>{let s=btn.parentElement&&btn.parentElement.querySelector(".nz-otp-countdown");if(!s){s=document.createElement("small");s.className="nz-otp-countdown";btn.insertAdjacentElement("afterend",s)}return s};const bindButton=(form,btn,status)=>{const original=btn.dataset.nzOriginal||btn.textContent.trim()||"دریافت کد ورود";btn.dataset.nzOriginal=original;const render=()=>{const sec=Math.max(0,Math.ceil((until-Date.now())/1000));if(sec>0){btn.disabled=true;if(!btn.dataset.nzSending)btn.textContent=original;status.textContent="دریافت مجدد کد تا "+fa(sec)+" ثانیه دیگر"}else{btn.disabled=false;btn.dataset.nzSending="";btn.textContent=original;status.textContent="";try{sessionStorage.removeItem(KEY)}catch(_){}}};form.addEventListener("submit",()=>{until=Date.now()+60000;try{sessionStorage.setItem(KEY,String(until));const p=form.querySelector("input[name=phone]");if(p&&p.value)sessionStorage.setItem(PHONE,p.value)}catch(_){}btn.disabled=true;btn.dataset.nzSending="1";btn.textContent="در حال ارسال کد…";render()});render();setInterval(render,250)};const requestInput=document.querySelector("form input[name=action][value=request_otp]");if(requestInput){const form=requestInput.form;form.dataset.nzOtpRequest="1";const btn=form.querySelector("button");if(btn)bindButton(form,btn,statusFor(btn))}const verifyInput=document.querySelector("form input[name=action][value=verify_otp]");if(verifyInput&&cfg.phone){const verifyForm=verifyInput.form;if(!document.querySelector(".nz-otp-resend")){const form=document.createElement("form");form.method="post";form.className="nz-otp-resend";form.dataset.nzOtpRequest="1";const add=(name,value)=>{const i=document.createElement("input");i.type="hidden";i.name=name;i.value=value;form.appendChild(i)};add("csrf",cfg.csrf);add("action","request_otp");add("phone",cfg.phone);const btn=document.createElement("button");btn.type="submit";btn.className="btn ghost";btn.style.width="100%";btn.textContent="دریافت مجدد کد";form.appendChild(btn);const s=document.createElement("small");s.className="nz-otp-countdown";form.appendChild(s);verifyForm.insertAdjacentElement("afterend",form);bindButton(form,btn,s)}}})();</script>';
    return str_replace('</body>', $ui.'</body>', $html);
}

function nzh_seo(string $html): string {
    $html = str_replace(
        ['</style>\\n</head>', '<!-- nz-community-v1 -->\\n</body>'],
        ["</style>\n</head>", "<!-- nz-community-v1 -->\n</body>"],
        $html
    );

    $page=(string)($_GET['page']??'home'); $id=(string)($_GET['id']??'');
    $private=in_array($page,['login','new','profile','myposts','chat','admin','support','search','saved-searches'],true) || (!empty($_GET['q']) && $id==='');
    $robots=$private ? 'noindex,nofollow,noarchive' : 'index,follow,max-image-preview:large';
    $html=nzh_meta_replace($html,'robots',$robots);

    $canonical='https://websaza.ir/nz'; $title='نیازستان | آگهی خرید، فروش و خدمات'; $desc='نیازستان؛ آگهی خرید، فروش و خدمات با جست‌وجوی محلی، چت و ثبت آگهی.'; $image='https://websaza.ir/niazestan-icon-512.php?v=20261019-1';
    $schema=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'نیازستان','url'=>'https://websaza.ir/nz'];
    if ($id!=='') {
        foreach (nzc_db_read()['posts'] as $p) {
            if (($p['id']??'')!==$id || ($p['status']??'')!=='published') continue;
            $canonical.='?id='.rawurlencode($id); $title=trim((string)($p['title']??'آگهی')).' | نیازستان';
            $desc=mb_substr(trim(preg_replace('/\s+/u',' ',(string)($p['description']??''))??''),0,155);
            if (!empty($p['images'][0])) $image='https://websaza.ir/marketplace-uploads/'.rawurlencode(basename((string)$p['images'][0]));
            $schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>(string)($p['title']??''),'description'=>$desc,'image'=>[$image],'category'=>trim((string)($p['category']??'').' / '.(string)($p['subcategory']??'')),'url'=>$canonical];
            if ((int)($p['price']??0)>0) $schema['offers']=['@type'=>'Offer','price'=>(int)$p['price']*10,'priceCurrency'=>'IRR','availability'=>'https://schema.org/InStock','url'=>$canonical];
            break;
        }
    }
    $html=preg_replace('/<title>.*?<\/title>/isu','<title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</title>',$html,1)??$html;
    $html=nzh_meta_replace($html,'description',$desc);
    $og = "\n".
        '<link rel="canonical" href="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'">'."\n".
        '<meta property="og:type" content="website">'.
        '<meta property="og:site_name" content="نیازستان">'.
        '<meta property="og:title" content="'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'">'.
        '<meta property="og:description" content="'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'">'.
        '<meta property="og:url" content="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'">'.
        '<meta property="og:image" content="'.htmlspecialchars($image,ENT_QUOTES,'UTF-8').'">'."\n".
        '<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";
    $html = str_replace('</head>',$og.'</head>',$html);
    if ($page === 'login') $html = nzh_otp_ui($html);
    return $html;
}
