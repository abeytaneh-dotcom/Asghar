<?php
declare(strict_types=1);
require_once __DIR__.'/nz-moderation.php';

function nzchat_conversation(array $db,string $cid):?array{foreach($db['conversations'] as $c)if(($c['id']??'')===$cid)return$c;return null;}
function nzchat_member(array $conv,string $uid):bool{return in_array($uid,(array)($conv['members']??[]),true);}

function nzchat_handle_post(string $action): never {
    nzc_session_open(); $db=nzc_db_read(); $user=nzc_require_user($db);
    if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    if($action==='start_chat'){
        $pid=trim((string)($_POST['post_id']??''));$post=null;foreach($db['posts'] as $p)if(($p['id']??'')===$pid){$post=$p;break;}
        if(!$post||($post['status']??'')!=='published'||($post['user_id']??'')===$user['id']||empty($post['chat_enabled']))throw new RuntimeException('امکان شروع این گفتگو وجود ندارد.');
        $cid=nzc_db_write(function(&$db)use($post,$user){foreach($db['conversations'] as $c)if(($c['post_id']??'')===($post['id']??'')&&nzchat_member($c,(string)$user['id']))return(string)$c['id'];$id=nzc_uid('c');$db['conversations'][]=['id'=>$id,'post_id'=>$post['id'],'members'=>[$user['id'],$post['user_id']],'created_at'=>nzc_now(),'updated_at'=>nzc_now()];return$id;});
        nzc_redirect('/nz?page=chat&cid='.rawurlencode($cid));
    }
    if($action==='send_message'){
        $cid=trim((string)($_POST['conversation_id']??''));$text=mb_substr(trim((string)($_POST['text']??'')),0,1200);if($text==='')throw new RuntimeException('پیام خالی است.');
        $conv=nzchat_conversation($db,$cid);if(!$conv||!nzchat_member($conv,(string)$user['id']))throw new RuntimeException('دسترسی به گفتگو ندارید.');
        $scan=nzm_text_scan($text);
        nzc_db_write(function(&$db)use($cid,$text,$user,$conv,$scan){
            $mid=nzc_uid('m');$db['messages'][]=['id'=>$mid,'conversation_id'=>$cid,'sender_id'=>$user['id'],'text'=>$text,'read_by'=>[$user['id']],'moderation'=>$scan,'created_at'=>nzc_now()];
            foreach($db['conversations'] as &$c)if(($c['id']??'')===$cid)$c['updated_at']=nzc_now();unset($c);
            foreach((array)($conv['members']??[]) as $member)if($member!==$user['id'])nzc_notify($db,(string)$member,'پیام جدید درباره «'.nzc_post_title($db,(string)($conv['post_id']??'')).'» دارید.');
            if(!empty($scan['flagged'])){
                $db['reports'][]=['id'=>nzc_uid('r'),'post_id'=>(string)($conv['post_id']??''),'post_title'=>nzc_post_title($db,(string)($conv['post_id']??'')),'reporter_id'=>'system-moderation','reason'=>'پایش خودکار پیام مشکوک','details'=>'پیام گفتگو نیازمند بررسی: '.implode(' | ',(array)$scan['reasons']),'priority'=>'high','conversation_id'=>$cid,'message_id'=>$mid,'status'=>'open','created_at'=>nzc_now()];
            }
        });
        nzc_redirect('/nz?page=chat&cid='.rawurlencode($cid));
    }
    throw new RuntimeException('درخواست گفتگو نامعتبر است.');
}

function nzchat_mark_read():void{
    if(($_GET['page']??'')!=='chat'||empty($_GET['cid']))return;nzc_session_open();$db=nzc_db_read();$user=nzc_current_user($db);if(!$user)return;$cid=(string)$_GET['cid'];$conv=nzchat_conversation($db,$cid);if(!$conv||(!nzc_is_admin($user)&&!nzchat_member($conv,(string)$user['id'])))return;
    nzc_db_write(function(&$db)use($cid,$user){foreach($db['messages'] as &$m){if(($m['conversation_id']??'')!==$cid||($m['sender_id']??'')===$user['id'])continue;$read=(array)($m['read_by']??[]);if(!in_array($user['id'],$read,true)){$read[]=$user['id'];$m['read_by']=$read;}}unset($m);});
}
function nzchat_unread_count(array $db,array $user):int{$count=0;foreach($db['messages'] as $m){if(($m['sender_id']??'')===$user['id'])continue;$conv=nzchat_conversation($db,(string)($m['conversation_id']??''));if(!$conv||!nzchat_member($conv,(string)$user['id']))continue;if(!in_array($user['id'],(array)($m['read_by']??[]),true))$count++;}return$count;}
function nzchat_enhance_html(string $html):string{
    nzc_session_open();$db=nzc_db_read();$user=nzc_current_user($db);if(!$user)return$html;$count=nzchat_unread_count($db,$user);if($count>0){$badge='<b style="position:absolute;top:0;right:50%;transform:translateX(15px);min-width:17px;height:17px;padding:0 4px;border-radius:10px;background:#d92d20;color:#fff;font-size:10px;display:grid;place-items:center">'.$count.'</b>';$html=str_replace('<span>چت</span>',$badge.'<span>چت</span>',$html);}
    if(($_GET['page']??'')==='chat'&&!empty($_GET['cid']))$html=str_replace('</body>','<script src="/nz-chat-live.js?v=20261019-1" defer></script></body>',$html);
    return$html;
}
