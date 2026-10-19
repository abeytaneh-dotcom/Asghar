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
        'request_otp' => nzh_rate_limit('otp_request', 5, 600),
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
    if (preg_match('/<meta\s+name="'.preg_quote($name,'/').'"[^>]*>/iu',$html)) return preg_replace('/<meta\s+name="'.preg_quote($name,'/').'"[^>]*>/iu','<meta name="'.$name.'" content="'.$safe.'">',$html,1) ?? $html;
    return str_replace('</head>','<meta name="'.$name.'" content="'.$safe.'">'."\n</head>",$html);
}

function nzh_seo(string $html): string {
    $page=(string)($_GET['page']??'home'); $id=(string)($_GET['id']??'');
    $private=in_array($page,['login','new','profile','myposts','chat','admin','support'],true);
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
            if ((int)($p['price']??0)>0) $schema['offers']=['@type'=>'Offer','price'=>(int)$p['price'],'priceCurrency'=>'IRR','availability'=>'https://schema.org/InStock','url'=>$canonical];
            break;
        }
    }
    $html=preg_replace('/<title>.*?<\/title>/isu','<title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</title>',$html,1)??$html;
    $html=nzh_meta_replace($html,'description',$desc);
    $og='\n<link rel="canonical" href="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'">\n<meta property="og:type" content="website"><meta property="og:site_name" content="نیازستان"><meta property="og:title" content="'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'"><meta property="og:description" content="'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'"><meta property="og:url" content="'.htmlspecialchars($canonical,ENT_QUOTES,'UTF-8').'"><meta property="og:image" content="'.htmlspecialchars($image,ENT_QUOTES,'UTF-8').'">\n<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>\n';
    return str_replace('</head>',$og.'</head>',$html);
}
