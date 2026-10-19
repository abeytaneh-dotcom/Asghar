<?php
declare(strict_types=1);

function nzn_rows(array $db,string $uid): array {
    $rows=array_values(array_filter($db['notifications']??[],fn($n)=>($n['user_id']??'')===$uid));
    usort($rows,fn($a,$b)=>strtotime($b['created_at']??'now')<=>strtotime($a['created_at']??'now'));
    return$rows;
}
function nzn_unread(array $db,string $uid): int { $n=0;foreach($db['notifications']??[]as$x)if(($x['user_id']??'')===$uid&&empty($x['read']))$n++;return$n; }
function nzn_handle_post(string $action): never {
    nzc_session_open();$db=nzc_db_read();$u=nzc_require_user($db);if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');
    if($action==='notifications_read_all'){
        nzc_db_write(function(&$db)use($u){foreach($db['notifications']as&$n)if(($n['user_id']??'')===$u['id']){$n['read']=true;$n['read_at']=nzc_now();}unset($n);});nzc_redirect('/nz?page=notifications');
    }
    if($action==='notification_read'){
        $id=(string)($_POST['notification_id']??'');nzc_db_write(function(&$db)use($u,$id){foreach($db['notifications']as&$n)if(($n['id']??'')===$id&&($n['user_id']??'')===$u['id']){$n['read']=true;$n['read_at']=nzc_now();break;}unset($n);});nzc_redirect('/nz?page=notifications');
    }
    throw new RuntimeException('عملیات اعلان معتبر نیست.');
}
function nzn_html(array $db,array $u): string {
    $rows=nzn_rows($db,(string)$u['id']);$csrf=nzc_e($_SESSION['csrf']??'');$unread=nzn_unread($db,(string)$u['id']);$h='<main class="page page-narrow"><div class="section-head"><div><h1>اعلان‌ها</h1><p>'.$unread.' اعلان خوانده‌نشده</p></div>';
    if($unread)$h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="notifications_read_all"><button class="btn ghost">همه خوانده شد</button></form>';$h.='</div><div class="nzn-list">';if(!$rows)$h.='<div class="empty">هنوز اعلانی ندارید.</div>';
    foreach(array_slice($rows,0,150)as$n){$read=!empty($n['read']);$priority=(string)($n['priority']??'normal');$h.='<article class="nzn-item '.($read?'read':'unread').' '.($priority==='high'?'high':'').'"><div class="nzn-dot"></div><div class="nzn-body"><div>'.nzc_e($n['text']??'اعلان نیازستان').'</div><small>'.nzc_e(date('Y/m/d H:i',strtotime($n['created_at']??'now'))).'</small></div>';if(!$read)$h.='<form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="notification_read"><input type="hidden" name="notification_id" value="'.nzc_e($n['id']??'').'"><button class="btn ghost">خواندم</button></form>';$h.='</article>';}
    return$h.'</div></main>';
}
function nzn_enhance(string $html): string {
    nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);if(!$u)return$html;$page=(string)($_GET['page']??'home');if($page==='notifications'&&function_exists('nzd_replace_main'))$html=nzd_replace_main($html,nzn_html($db,$u));$count=nzn_unread($db,(string)$u['id']);
    if($page==='profile')$html=str_replace('</div><form method="post" style="margin-top:18px">','<a class="menu-item" href="/nz?page=notifications"><span>اعلان‌ها'.($count?' <b class="nzn-count">'.$count.'</b>':'').'</span><span>‹</span></a></div><form method="post" style="margin-top:18px">',$html);
    if($count>0){$badge='<b class="nzn-nav-badge">'.min(99,$count).'</b>';$html=str_replace('<span>حساب من</span>',$badge.'<span>حساب من</span>',$html);}
    $css='<style id="nzn-style">.nzn-list{display:grid;gap:8px}.nzn-item{display:grid;grid-template-columns:8px 1fr auto;gap:10px;align-items:center;border:1px solid #e9ebee;border-radius:14px;padding:12px;background:#fff}.nzn-item.unread{background:#f8fbff;border-color:#d9e8ff}.nzn-item.high{background:#fff8f7;border-color:#efc8c3}.nzn-dot{width:8px;height:8px;border-radius:50%;background:#d1d5db}.nzn-item.unread .nzn-dot{background:#2563eb}.nzn-item.high .nzn-dot{background:#d92d20}.nzn-body small{display:block;color:#747b84;margin-top:5px}.nzn-count,.nzn-nav-badge{background:#d92d20;color:#fff;border-radius:999px;padding:2px 6px;font-size:10px}.nzn-nav-badge{position:absolute;top:1px;right:50%;transform:translateX(17px)}@media(max-width:600px){.nzn-item{grid-template-columns:8px 1fr}.nzn-item form{grid-column:2}.nzn-item form .btn{width:100%}}</style>';
    return str_replace('</head>',$css.'</head>',$html);
}
