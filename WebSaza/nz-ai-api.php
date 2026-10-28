<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-chat-controls.php';
require_once __DIR__.'/nz-ai-core.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new RuntimeException('درخواست نامعتبر است.');}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>12000){http_response_code(413);throw new RuntimeException('پیام طولانی است.');}
    if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
    nzh_rate_hit('ai|'.hash('sha256',nzh_ip()),30,3600,'سقف گفت‌وگوی این ساعت رسیده است؛ کمی بعد تلاش کنید.');
    $mode=($_POST['mode']??'search')==='create'?'create':'search';$db=nzc_db_read();$user=nzc_current_user($db);
    if(($_POST['action']??'')==='contact'){
        if(!$user){http_response_code(401);throw new RuntimeException('برای مشاهده اطلاعات تماس وارد حساب شوید.');}
        if(!in_array((string)($_POST['id']??''),$_SESSION['nzai_results']??[],true)){http_response_code(403);throw new RuntimeException('ابتدا آگهی را جستجو کنید.');}
        $out=nzai_contact($db,(string)$_POST['id'],$user);
    }elseif(($_POST['action']??'')==='reset'){unset($_SESSION['nzai_'.$mode],$_SESSION['nzai_results']);$out=[];
    }else{
        if($mode==='create'&&!$user){http_response_code(401);throw new RuntimeException('برای ثبت آگهی ابتدا وارد حساب شوید.');}
        $message=trim((string)($_POST['message']??''));if($message===''||mb_strlen($message)>2000){http_response_code(400);throw new RuntimeException('پیام باید بین ۱ تا ۲۰۰۰ حرف باشد.');}
        $s=$_SESSION['nzai_'.$mode]??['history'=>[],'draft'=>[]];if(($s['expires']??0)<time())$s=['history'=>[],'draft'=>[]];
        $history=array_slice($s['history'],-18);$history[]=['role'=>'user','content'=>$message];
        $plan=nzai_plan($history,$mode,$s['draft']);$history[]=['role'=>'assistant','content'=>json_encode($plan,JSON_UNESCAPED_UNICODE)];
        $_SESSION['nzai_'.$mode]=['history'=>$history,'draft'=>$plan,'expires'=>time()+3600];
        $out=['plan'=>$plan];if($mode==='search'){$out['results']=nzai_search($db['posts'],$plan);$_SESSION['nzai_results']=array_column($out['results'],'id');}
    }
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(503);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
