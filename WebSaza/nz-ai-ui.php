<?php
declare(strict_types=1);
require_once __DIR__.'/nz-ai-personal.php';
function nzai_enhance(string $html): string {
    if(!headers_sent())header('Cache-Control: private, no-store, max-age=0');
    $page=(string)($_GET['page']??'home');$enabled=!in_array($page,['admin','login','account-data','account-delete'],true);
    $version=fn($f)=>substr(hash_file('sha256',__DIR__.'/'.$f),0,12);
    $db=nzc_db_read();$user=nzc_current_user($db);
    if($user&&in_array($page,['home','search'],true)&&empty($_GET['id'])&&nzaiexp_enabled($db,(string)$user['id'])&&($filters=nzaiexp_manual_filters($_GET,$db['ai_examples']??[]))){
        try{nzc_db_write(fn(&$db)=>nzaiexp_record_search($db,(string)$user['id'],$filters+['ready'=>true]));}catch(Throwable $e){error_log('Niazestan personal history unavailable');}
    }
    $cfg=['csrf'=>(string)($_SESSION['csrf']??''),'page'=>$page,'logged_in'=>(bool)$user,'taxonomy'=>nzt_categories(),'open_requested'=>(string)($_GET['assistant']??'')==='1'];
    $assets='<link rel="stylesheet" href="/nz-ai.css?v='.$version('nz-ai.css').'">';
    $scripts='<script type="application/json" id="nz-ai-config">'.json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS|JSON_UNESCAPED_UNICODE).'</script><script src="/nz-ai-media.js?v='.$version('nz-ai-media.js').'" defer></script><script src="/nz-ai.js?v='.$version('nz-ai.js').'" defer></script><script src="/nz-notify.js?v='.$version('nz-notify.js').'" defer></script>';
    if($page==='admin')$scripts.='<script src="/nz-ai-learning.js?v='.$version('nz-ai-learning.js').'" defer></script>';
    if($page==='admin'&&$user&&nzauth_is_elevated($user))$html=str_replace('<main class="page nzc-admin">','<main class="page nzc-admin">'.nzailearn_admin_html($db,$user),$html);
    if($enabled){
        $avatar='/nz-ai-avatar.webp?v='.$version('nz-ai-avatar.webp');
        $mode=$page==='new'?'create':'search';
        $hero='<section class="nzai nzai-chat" id="nz-ai" dir="rtl" data-mode="'.$mode.'"><nav class="nzai-task-links" aria-label="کار دستیار"><button type="button" data-task="search">جستجوی آگهی</button><button type="button" data-task="create">ثبت آگهی</button></nav><div class="nzai-log" id="nzai-log" role="log" aria-label="گفتگو با دستیار" aria-live="polite"></div><form id="nzai-compose"><label class="nzai-sr" for="nzai-message">پیام شما به دستیار</label><textarea id="nzai-message" rows="1" maxlength="2000" required placeholder="پیامت را بنویس…"></textarea><button type="submit" class="nzai-send" aria-label="ارسال پیام">↑</button></form><div class="nzai-status" id="nzai-status" role="status"></div></section>';
        $dialog='<dialog class="nzai-dialog" id="nzai-dialog" aria-labelledby="nzai-dialog-title" dir="rtl"><header class="nzai-dialog-head"><div class="nzai-chat-title"><img class="nzai-avatar" src="'.$avatar.'" alt="" width="36" height="36"><h2 id="nzai-dialog-title">دستیار نیازستان</h2></div><button type="button" id="nzai-close" aria-label="بستن دستیار هوشمند">×</button></header><div class="nzai-dialog-body">'.$hero.'</div></dialog>';
        $launcher='<button type="button" class="nzai-launcher" id="nzai-launcher" aria-label="بازکردن دستیار هوشمند نیازستان" aria-controls="nzai-dialog" aria-haspopup="dialog" aria-expanded="false" hidden><img src="'.$avatar.'" alt="" width="64" height="64"></button>';
        $html=str_replace('</body>',$dialog.$launcher.'</body>',$html);
    }
    if($cfg['logged_in'])$scripts.='<aside class="nznt" id="nznt"><button type="button" id="nznt-enable">فعال‌کردن اعلان پیام‌ها</button><a href="/nz?page=chats" id="nznt-count">گفتگوها</a><span id="nznt-status" role="status"></span></aside>';
    return str_replace('</body>',$scripts.'</body>',str_replace('</head>',$assets.'</head>',$html));
}
