<?php
declare(strict_types=1);

function nzu_prepare_approval_watch(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || (string)($_POST['action'] ?? '') !== 'approve_post') return;
    $postId=trim((string)($_POST['post_id']??'')); if($postId==='')return;
    register_shutdown_function(function()use($postId){
        try{
            $db=nzc_db_read();$post=null;foreach($db['posts'] as$p)if(($p['id']??'')===$postId){$post=$p;break;}
            if(!$post||($post['status']??'')!=='published'||!empty($post['saved_search_notified_at']))return;
            nzc_db_write(function(&$db)use($postId,$post){
                foreach($db['saved_searches']??[] as$s){
                    if(($s['user_id']??'')===($post['user_id']??''))continue;
                    parse_str((string)($s['query']??''),$f);if(!is_array($f)||!$f)continue;
                    if(nzs_filter([$post],$f))nzc_notify($db,(string)$s['user_id'],'آگهی جدید مطابق جستجوی ذخیره‌شده «'.(string)($s['label']??'جستجوی شما').'» منتشر شد: '.(string)($post['title']??'آگهی'));
                }
                foreach($db['posts'] as&$p)if(($p['id']??'')===$postId){$p['saved_search_notified_at']=nzc_now();break;}unset($p);
            });
        }catch(Throwable $e){}
    });
}

function nzu_post_stats(array$db,array$p):array{
    $pid=(string)($p['id']??'');$favorites=0;foreach($db['favorites']??[] as$k)if(str_ends_with((string)$k,'|'.$pid))$favorites++;
    $convs=array_values(array_filter($db['conversations']??[],fn($c)=>($c['post_id']??'')===$pid));$convIds=array_map(fn($c)=>(string)($c['id']??''),$convs);$messages=0;foreach($db['messages']??[] as$m)if(in_array((string)($m['conversation_id']??''),$convIds,true))$messages++;
    return['views'=>(int)($p['views']??0),'favorites'=>$favorites,'chats'=>count($convs),'messages'=>$messages];
}
function nzu_analytics_html(array$db,array$user):string{
    $posts=array_values(array_filter($db['posts']??[],fn($p)=>($p['user_id']??'')===$user['id']&&($p['status']??'')!=='deleted'));
    usort($posts,fn($a,$b)=>strtotime($b['created_at']??'now')<=>strtotime($a['created_at']??'now'));
    $h='<main class="page"><div class="section-head"><div><h1>آمار آگهی‌های من</h1><p>بازدید، ذخیره و گفتگو</p></div></div>';
    if(!$posts)return$h.'<div class="empty">هنوز آگهی ثبت نکرده‌اید.</div></main>';
    foreach($posts as$p){$s=nzu_post_stats($db,$p);$h.='<div class="surface" style="margin-bottom:10px"><a href="/nz?id='.rawurlencode((string)$p['id']).'" style="font-weight:900;text-decoration:none">'.nzc_e($p['title']??'آگهی').'</a><div class="subtle" style="margin:6px 0">'.nzc_e(nzc_status_fa((string)($p['status']??''))).'</div><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:7px;text-align:center"><div><b>'.$s['views'].'</b><small class="subtle" style="display:block">بازدید</small></div><div><b>'.$s['favorites'].'</b><small class="subtle" style="display:block">ذخیره</small></div><div><b>'.$s['chats'].'</b><small class="subtle" style="display:block">گفتگو</small></div><div><b>'.$s['messages'].'</b><small class="subtle" style="display:block">پیام</small></div></div></div>';}
    return$h.'</main>';
}
function nzu_enhance(string$html):string{
    nzc_session_open();$db=nzc_db_read();$u=nzc_current_user($db);$page=(string)($_GET['page']??'home');if(!$u)return$html;
    if($page==='analytics')return nzd_replace_main($html,nzu_analytics_html($db,$u));
    if($page==='profile')$html=str_replace('</div><form method="post" style="margin-top:18px">','<a class="menu-item" href="/nz?page=analytics"><span>آمار آگهی‌های من</span><span>‹</span></a></div><form method="post" style="margin-top:18px">',$html);
    return$html;
}
