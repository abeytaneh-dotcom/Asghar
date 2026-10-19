<?php
declare(strict_types=1);

function nzm_normalize(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','ٱ'=>'ا',
        "\xE2\x80\x8C"=>' ', "\xE2\x80\x8F"=>'', "\xE2\x80\x8E"=>''
    ]);
    $text = preg_replace('/([\p{L}\p{N}])\1{2,}/u', '$1$1', $text) ?? $text;
    $text = preg_replace('/[^\p{L}\p{N}\s@._+-]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function nzm_contains_any(string $text, array $words): array {
    $hits = [];
    foreach ($words as $word) {
        $word = nzm_normalize((string)$word);
        if ($word !== '' && mb_stripos($text, $word, 0, 'UTF-8') !== false) $hits[] = $word;
    }
    return array_values(array_unique($hits));
}

function nzm_text_scan(string $text): array {
    $n = nzm_normalize($text);
    if ($n === '') return ['flagged'=>false,'priority'=>'normal','score'=>0,'reasons'=>[],'hits'=>[]];

    $profanity = [
        'کص','کسکش','کونی','کون','کیری','کیر','جنده','فاحشه','حرومزاده','حرامزاده','بی ناموس','بیناموس','گوه','گوه خور','آشغال','عوضی','خفه شو','لعنتی',
        'fuck','fucking','bitch','asshole','motherfucker','shit'
    ];
    $sexual = [
        'سکس','پورن','پورنو','برهنه','لخت','عریان','بدون لباس','رابطه جنسی','ماساژ جنسی','خدمات جنسی','صیغه ساعتی','روسپی','اندام جنسی',
        'nude','naked','porn','sex service','escort'
    ];
    $threat = [
        'میکشمت','می کشمت','بکشمت','میزنمت','می زنمت','تهدیدت میکنم','آدرسشو بدید','پیدات میکنم','پیدات می کنم','آبروتو میبرم','آبروت را میبرم',
        'kill you','beat you','find you'
    ];
    $targeting = ['تو','شما','ایشون','ایشان','فلانی','این شخص','این آدم','فروشنده','خریدار','مدیر','زن','مرد','دختر','پسر'];
    $hateOrDegrading = ['حیوان','کثافت','نجس','بی شرف','بیشرف','بی غیرت','بیغیرت','احمق','نفهم','روانی','آشغال'];

    $reasons = [];
    $hits = [];
    $score = 0;

    $p = nzm_contains_any($n, $profanity);
    if ($p) { $reasons[]='کلمات رکیک یا دشنام در متن'; $hits=array_merge($hits,$p); $score=max($score,85); }
    $s = nzm_contains_any($n, $sexual);
    if ($s) { $reasons[]='متن دارای محتوای جنسی/برهنگی'; $hits=array_merge($hits,$s); $score=max($score,95); }
    $t = nzm_contains_any($n, $threat);
    if ($t) { $reasons[]='تهدید یا آزار مستقیم'; $hits=array_merge($hits,$t); $score=max($score,98); }
    $d = nzm_contains_any($n, $hateOrDegrading);
    if ($d) {
        $targetHits = nzm_contains_any($n, $targeting);
        $reasons[] = $targetHits ? 'توهین یا تحقیر شخص/گروه' : 'عبارات توهین‌آمیز یا تحقیرکننده';
        $hits=array_merge($hits,$d,$targetHits); $score=max($score,88);
    }

    return [
        'flagged'=>(bool)$reasons,
        'priority'=>$reasons ? 'high' : 'normal',
        'score'=>$score,
        'reasons'=>array_values(array_unique($reasons)),
        'hits'=>array_values(array_unique(array_slice($hits,0,20))),
    ];
}

function nzm_client_scan(): array {
    $raw = (string)($_POST['moderation_client'] ?? '');
    if ($raw === '' || strlen($raw) > 30000) return ['reasons'=>[],'ocr_text'=>'','details'=>[]];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['reasons'=>[],'ocr_text'=>'','details'=>[]];

    $reasons = [];
    $details = [];
    $ocrParts = [];
    foreach (array_slice((array)($data['images'] ?? []), 0, 5) as $index => $img) {
        if (!is_array($img)) continue;
        $num = $index + 1;
        $ocr = mb_substr(trim((string)($img['ocr'] ?? '')), 0, 1200);
        if ($ocr !== '') {
            $ocrParts[] = $ocr;
            $reasons[] = 'متن داخل تصویر شناسایی شد';
            $details[] = 'تصویر '.$num.': متن OCR شناسایی شد';
        }
        if (!empty($img['face'])) {
            $reasons[] = 'چهره انسانی در تصویر شناسایی شد';
            $details[] = 'تصویر '.$num.': چهره انسانی';
        }
        $porn = max(0, min(1, (float)($img['porn'] ?? 0)));
        $sexy = max(0, min(1, (float)($img['sexy'] ?? 0)));
        $hentai = max(0, min(1, (float)($img['hentai'] ?? 0)));
        if ($porn >= .20 || $hentai >= .25 || ($porn + $sexy) >= .55 || $sexy >= .62) {
            $reasons[] = 'احتمال برهنگی یا محتوای جنسی در تصویر';
            $details[] = 'تصویر '.$num.': امتیاز تصویری مشکوک '.number_format(max($porn,$sexy,$hentai),2);
        }
    }
    return [
        'reasons'=>array_values(array_unique($reasons)),
        'ocr_text'=>mb_substr(implode("\n", $ocrParts),0,4000),
        'details'=>$details,
    ];
}

function nzm_scan_submission(string $title, string $description, array $attributes=[]): array {
    $attrText = '';
    foreach ($attributes as $k=>$v) {
        if (is_array($v)) continue;
        $attrText .= ' '.(string)$k.' '.(string)$v;
    }
    $client = nzm_client_scan();
    $text = nzm_text_scan($title.' '.$description.' '.$attrText.' '.$client['ocr_text']);
    $reasons = array_values(array_unique(array_merge($text['reasons'], $client['reasons'])));
    $score = (int)$text['score'];
    if ($client['reasons']) $score = max($score, 90);
    return [
        'flagged'=>(bool)$reasons,
        'priority'=>$reasons ? 'high' : 'normal',
        'score'=>$score,
        'reasons'=>$reasons,
        'hits'=>$text['hits'],
        'image_details'=>$client['details'],
        'ocr_excerpt'=>mb_substr($client['ocr_text'],0,1200),
        'checked_at'=>date('c'),
        'engine'=>'nzm-1',
    ];
}

function nzm_add_system_report(array &$db, array $post): void {
    $mod = $post['moderation'] ?? [];
    if (empty($mod['flagged'])) return;
    $postId = (string)($post['id'] ?? '');
    foreach ($db['reports'] ?? [] as $r) {
        if (($r['post_id'] ?? '') === $postId && ($r['reporter_id'] ?? '') === 'system-moderation' && ($r['status'] ?? 'open') === 'open') return;
    }
    $reasons = array_values(array_filter(array_map('strval', (array)($mod['reasons'] ?? []))));
    $details = 'هشدار خودکار با اولویت بالا';
    if ($reasons) $details .= ': '.implode(' | ', $reasons);
    if (!empty($mod['image_details'])) $details .= ' — '.implode(' | ', array_map('strval',(array)$mod['image_details']));
    $db['reports'][] = [
        'id'=>function_exists('nzc_uid') ? nzc_uid('r') : ('r_'.bin2hex(random_bytes(6))),
        'post_id'=>$postId,
        'post_title'=>(string)($post['title'] ?? 'آگهی'),
        'reporter_id'=>'system-moderation',
        'reason'=>'پایش خودکار محتوای مشکوک',
        'details'=>mb_substr($details,0,1800),
        'priority'=>'high',
        'status'=>'open',
        'created_at'=>date('c'),
    ];
}

function nzm_admin_urgent_html(array $db): string {
    $flagged = array_values(array_filter($db['posts'] ?? [], static fn($p)=>($p['status']??'')==='pending' && !empty($p['moderation']['flagged'])));
    usort($flagged, static fn($a,$b)=>(int)($b['moderation']['score']??0) <=> (int)($a['moderation']['score']??0));
    if (!$flagged) return '';
    $out = '<section class="surface nzm-urgent" style="border:2px solid #d92d20;background:#fff8f7;margin-bottom:16px"><h2 style="color:#b42318">⚠ بررسی فوری سیستم ('.count($flagged).')</h2><p class="subtle">این آگهی‌ها خودکار منتشر نشده‌اند و به دلیل محتوای مشکوک با اولویت بالا برای تصمیم مدیر نگه داشته شده‌اند.</p>';
    foreach ($flagged as $p) {
        $reasons = implode('، ', array_map('strval',(array)($p['moderation']['reasons']??[])));
        $out .= '<article style="padding:12px 0;border-top:1px solid #f0c6c1"><b>'.nzc_e($p['title']??'آگهی').'</b><div style="color:#b42318;font-weight:800;margin-top:5px">'.nzc_e($reasons).'</div><div class="subtle" style="margin-top:5px">امتیاز ریسک: '.(int)($p['moderation']['score']??0).'</div><a class="btn ghost" style="margin-top:8px" href="/nz?id='.rawurlencode((string)($p['id']??'')).'">مشاهده آگهی</a></article>';
    }
    return $out.'</section>';
}

function nzm_enhance_admin_html(string $html): string {
    if (($_GET['page'] ?? '') !== 'admin') return $html;
    $urgent = nzm_admin_urgent_html(nzc_db_read());
    if ($urgent === '') return $html;
    return str_replace('<div class="nzc-stats">', $urgent.'<div class="nzc-stats">', $html);
}
