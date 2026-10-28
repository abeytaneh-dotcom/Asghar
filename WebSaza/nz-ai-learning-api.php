<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-admin-stepup.php';
require_once __DIR__.'/nz-ai-learning.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
try{
    $db=nzc_db_read();$user=nzc_current_user($db);if(!$user||nzc_user_blocked($db,(string)$user['id'])){http_response_code(401);throw new RuntimeException('ابتدا وارد حساب مجاز شوید.');}
    $uid=(string)$user['id'];$method=$_SERVER['REQUEST_METHOD']??'GET';$out=[];
    if($method==='GET'){$out=['remember'=>nzailearn_profile($db,$uid)!==null];}
    elseif($method==='POST'){
        if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4000){http_response_code(413);throw new RuntimeException('درخواست طولانی است.');}
        $action=(string)($_POST['action']??'');
        if($action==='review'){
            if(!nzauth_is_elevated($user)){http_response_code(403);throw new RuntimeException('ورود تأییدشدهٔ مدیریت لازم است.');}
            nzc_db_write(function(&$db)use($user){$live=nzc_current_user($db);if(!$live)throw new RuntimeException('حساب معتبر نیست.');nzailearn_review($db,$live,(string)($_POST['id']??''),(string)($_POST['decision']??''));});nzc_audit($user,'ai_review','ai_example',(string)($_POST['id']??''),['decision'=>(string)($_POST['decision']??'')]);
        }elseif($action==='feedback'){
            $turn=$_SESSION['nzai_turns'][(string)($_POST['turn']??'')]??null;
            if(!is_array($turn)||($turn['uid']??'')!==$uid||(int)($turn['expires']??0)<time()){http_response_code(403);throw new RuntimeException('ابتدا یک گفت‌وگوی تازه با دستیار انجام دهید.');}
            nzh_rate_hit('ai-learn|'.$uid,10,86400,'سقف ارسال بازخورد امروز رسیده است.');$out['id']=nzc_db_write(fn(&$db)=>nzailearn_submit($db,$user,$_POST));
        }elseif($action==='remember'){
            if(($_POST['enabled']??'')==='1'){$draft=$_SESSION['nzai_search']['draft']??[];nzc_db_write(fn(&$db)=>nzailearn_save_memory($db,$uid,$draft,true));}
            else nzc_db_write(function(&$db)use($uid){$db['ai_profiles']=array_values(array_filter($db['ai_profiles']??[],fn($p)=>($p['user_id']??'')!==$uid));});
        }elseif($action==='forget'){
            nzc_db_write(fn(&$db)=>nzailearn_forget($db,$uid));unset($_SESSION['nzai_create'],$_SESSION['nzai_search'],$_SESSION['nzai_results'],$_SESSION['nzai_turns']);
        }else{http_response_code(400);throw new RuntimeException('عملیات یادگیری معتبر نیست.');}
    }else{http_response_code(405);header('Allow: GET, POST');throw new RuntimeException('روش درخواست معتبر نیست.');}
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
