<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-push.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
try{
    $db=nzc_db_read();$user=nzc_current_user($db);if(!$user){http_response_code(401);throw new RuntimeException('ابتدا وارد حساب شوید.');}
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
        nzh_rate_hit('push-sub|'.$user['id'],20,3600);$endpoint=(string)($_POST['endpoint']??'');nzpush_host($endpoint);$remove=($_POST['action']??'')==='unsubscribe';
        nzc_db_write(function(&$db)use($endpoint,$user,$remove){$db['push_subscriptions']=array_values(array_filter($db['push_subscriptions']??[],fn($s)=>($s['endpoint']??'')!==$endpoint||($remove&&($s['user_id']??'')!==$user['id'])));if(!$remove){$owned=array_values(array_filter($db['push_subscriptions'],fn($s)=>($s['user_id']??'')===$user['id']));if(count($owned)>=6)throw new RuntimeException('حداکثر ۶ دستگاه قابل اتصال است.');$db['push_subscriptions'][]=['id'=>hash('sha256',$endpoint),'user_id'=>$user['id'],'endpoint'=>$endpoint,'created_at'=>nzc_now()];}});$out=[];
    }else{
        $rows=[];foreach($db['notifications']??[] as $n)if(($n['user_id']??'')===$user['id']&&empty($n['read']))$rows[]=['id'=>$n['id'],'text'=>$n['text'],'created_at'=>$n['created_at']];
        $out=['count'=>count($rows),'items'=>array_slice($rows,-20),'public_key'=>nzpush_keys()['public'],'user_id'=>$user['id']];
    }
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
