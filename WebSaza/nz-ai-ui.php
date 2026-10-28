<?php
declare(strict_types=1);
function nzai_enhance(string $html): string {
    if(!headers_sent())header('Cache-Control: private, no-store, max-age=0');
    $page=(string)($_GET['page']??'home');$enabled=in_array($page,['home','search','new'],true)&&empty($_GET['id']);
    $version=fn($f)=>substr(hash_file('sha256',__DIR__.'/'.$f),0,12);
    $cfg=['csrf'=>(string)($_SESSION['csrf']??''),'page'=>$page,'logged_in'=>(bool)nzc_current_user(nzc_db_read())];
    $assets='<link rel="stylesheet" href="/nz-ai.css?v='.$version('nz-ai.css').'">';
    $scripts='<script type="application/json" id="nz-ai-config">'.json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS|JSON_UNESCAPED_UNICODE).'</script><script src="/nz-ai.js?v='.$version('nz-ai.js').'" defer></script><script src="/nz-notify.js?v='.$version('nz-notify.js').'" defer></script>';
    if($enabled){
        $mode=$page==='new'?'create':'search';
        $hero='<section class="nzai" id="nz-ai" dir="rtl" data-mode="'.$mode.'"><div class="nzai-heading"><span class="nzai-orb" aria-hidden="true">✦</span><div><span class="nzai-eyebrow">نیازستان، همراه هوشمند شما</span><h2>'.($mode==='create'?'بگو چی می‌خوای آگهی کنی':'چی لازم داری؟ با هم پیداش کنیم').'</h2></div></div><p class="nzai-lead">'.($mode==='create'?'قدم‌به‌قدم ازت می‌پرسم و آگهی‌ات را آماده می‌کنم.':'خواسته‌ات را راحت بنویس؛ از بین آگهی‌های سایت جستجو می‌کنم.').'</p><div class="nzai-modes" aria-label="روش استفاده"><button type="button" id="nzai-smart" aria-pressed="true">✦ با دستیار هوشمند</button><button type="button" id="nzai-manual" aria-pressed="false">'.($mode==='create'?'ثبت دستی':'جستجوی دستی').'</button></div><div id="nzai-panel"><div class="nzai-examples">'.($mode==='create'?'<button type="button" data-example="می‌خواهم گوشی‌ام را بفروشم">فروش گوشی</button><button type="button" data-example="می‌خواهم خانه‌ام را اجاره بدهم">اجاره خانه</button>':'<button type="button" data-example="دنبال یک مبل در شیراز تا ۲۰ میلیون تومان هستم">مبل تا ۲۰ میلیون در شیراز</button><button type="button" data-example="دنبال آپارتمان اجاره‌ای در تهران هستم">اجاره آپارتمان در تهران</button>').'</div><div class="nzai-log" id="nzai-log" role="log" aria-label="گفتگو با دستیار" aria-live="polite"></div><form id="nzai-compose"><label class="nzai-sr" for="nzai-message">پیام شما به دستیار</label><textarea id="nzai-message" rows="2" maxlength="2000" required placeholder="همین‌طور که به یک دوست می‌گی، برام بنویس…"></textarea><button type="submit" class="nzai-send">ارسال <span aria-hidden="true">↑</span></button></form><div class="nzai-status" id="nzai-status" role="status"></div><div id="nzai-output"></div><div class="nzai-foot"><button type="button" id="nzai-reset">گفتگوی تازه</button><small>گفتگو برای پردازش به سرویس هوش مصنوعی ارسال می‌شود.</small></div></div></section>';
        $html=preg_replace_callback('/<main\b[^>]*>/u',fn($m)=>$m[0].$hero,$html,1)??$html;
    }
    if($cfg['logged_in'])$scripts.='<aside class="nznt" id="nznt"><button type="button" id="nznt-enable">فعال‌کردن اعلان پیام‌ها</button><a href="/nz?page=chats" id="nznt-count">گفتگوها</a><span id="nznt-status" role="status"></span></aside>';
    return str_replace('</body>',$scripts.'</body>',str_replace('</head>',$assets.'</head>',$html));
}
