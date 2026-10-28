<?php
declare(strict_types=1);

function nzprivacy_handle(string $action): never {
    nzc_session_open();$db=nzc_db_read();$user=nzc_require_user($db);if(!nzc_csrf_valid())throw new RuntimeException('درخواست نامعتبر است.');$uid=(string)$user['id'];
    if($action==='request_account_export'){
        $out=['generated_at'=>date('c'),'account'=>$user,'posts'=>[],'notifications'=>[],'saved_searches'=>[],'messages_sent'=>[]];
        foreach($db['posts']??[]as$r)if(($r['user_id']??'')===$uid)$out['posts'][]=$r;
        foreach($db['notifications']??[]as$r)if(($r['user_id']??'')===$uid)$out['notifications'][]=$r;
        foreach($db['saved_searches']??[]as$r)if(($r['user_id']??'')===$uid)$out['saved_searches'][]=$r;
        foreach($db['messages']??[]as$r)if(($r['sender_id']??'')===$uid)$out['messages_sent'][]=$r;
        foreach(['ai_profiles','ai_examples','ai_personalization','ai_search_history','ai_interactions'] as $bucket)$out[$bucket]=array_values(array_filter($db[$bucket]??[],fn($r)=>($r['user_id']??'')===$uid));
        nzc_audit($user,'account_export','user',$uid);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="niazestan-my-data-'.date('Ymd').'.json"');header('Cache-Control: no-store');echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;
    }
    if($action==='deactivate_account'){
        if(trim((string)($_POST['confirm']??''))!=='حذف')throw new RuntimeException('برای تأیید، کلمه «حذف» را وارد کنید.');
        require_once __DIR__.'/nz-ai-learning.php';nzc_db_write(function(&$db)use($uid){nzailearn_forget($db,$uid);foreach($db['users']??[]as&$u){if(($u['id']??'')!==$uid)continue;$u['phone']='';$u['name']='کاربر حذف‌شده';$u['status']='deleted';$u['deleted_at']=date('c');unset($u['last_ip'],$u['last_user_agent'],$u['last_device']);}unset($u);foreach($db['posts']??[]as&$p)if(($p['user_id']??'')===$uid){$p['status']='archived';$p['review_status']='account_deleted';$p['updated_at']=date('c');}unset($p);$db['notifications']=array_values(array_filter($db['notifications']??[],fn($n)=>($n['user_id']??'')!==$uid));$db['saved_searches']=array_values(array_filter($db['saved_searches']??[],fn($n)=>($n['user_id']??'')!==$uid));});
        require_once __DIR__.'/nz-create-pending.php';$staging=__DIR__.'/.marketplace-data/ai-uploads/'.nzcp_owner_prefix($uid);foreach(glob($staging.'/*.part')?:[] as $file)@unlink($file);if(is_dir($staging))@rmdir($staging);nzc_audit($user,'account_deactivated','user',$uid);$_SESSION=[];session_destroy();header('Location: /nz',true,302);exit;
    }
    throw new RuntimeException('درخواست حریم خصوصی نامعتبر است.');
}
