<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';require_once __DIR__.'/nz-community.php';require_once __DIR__.'/nz-hardening.php';require_once __DIR__.'/nz-chat-controls.php';require_once __DIR__.'/nz-ai-personal.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
try{
    $db=nzc_db_read();$u=nzc_current_user($db);if(!$u||nzc_user_blocked($db,(string)$u['id'])){http_response_code(401);throw new RuntimeException('برای پیشنهادهای شخصی وارد حساب شوید.');}$uid=(string)$u['id'];
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='POST'){
        if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>2000){http_response_code(413);throw new RuntimeException('درخواست طولانی است.');}
        nzh_rate_hit('ai-personal|'.$uid,100,3600);$action=(string)($_POST['action']??'');
        if($action==='consent')nzc_db_write(fn(&$db)=>nzaiexp_enable($db,$uid,($_POST['enabled']??'')==='1'));
        elseif($action==='reaction'){
            $allowed=$_SESSION['nzai_recommendations']??[];$pid=(string)($_POST['id']??'');
            if(($allowed['uid']??'')!==$uid||(int)($allowed['expires']??0)<time()||!in_array($pid,$allowed['ids']??[],true)){http_response_code(403);throw new RuntimeException('ابتدا پیشنهادهای خودتان را باز کنید.');}
            nzc_db_write(fn(&$db)=>nzaiexp_react($db,$uid,$pid,(string)($_POST['value']??'')));
        }else{http_response_code(400);throw new RuntimeException('عملیات معتبر نیست.');}
        $db=nzc_db_read();
    }elseif($method!=='GET'){http_response_code(405);header('Allow: GET, POST');throw new RuntimeException('روش درخواست معتبر نیست.');}
    $out=nzaiexp_suggestions($db,$uid);$old=$_SESSION['nzai_recommendations']??[];$ids=($old['uid']??'')===$uid&&(int)($old['expires']??0)>time()?($old['ids']??[]):[];
    $_SESSION['nzai_recommendations']=['uid'=>$uid,'ids'=>array_slice(array_unique(array_merge($ids,array_column($out['cards'],'id'))),-40),'expires'=>time()+1800];
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
