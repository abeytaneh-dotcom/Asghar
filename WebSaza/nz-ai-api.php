<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-chat-controls.php';
require_once __DIR__.'/nz-ai-core.php';
require_once __DIR__.'/nz-ai-learning.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new RuntimeException('درخواست نامعتبر است.');}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>12000){http_response_code(413);throw new RuntimeException('پیام طولانی است.');}
    if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
    nzh_rate_hit('ai|'.hash('sha256',nzh_ip()),(getenv('NZ_AI_ENGINE')?:'native')==='cloud'?30:120,3600,'سقف گفت‌وگوی این ساعت رسیده است؛ کمی بعد تلاش کنید.');
    $mode=($_POST['mode']??'search')==='create'?'create':'search';$db=nzc_db_read();$user=nzc_current_user($db);
    if(($_POST['action']??'')==='contact'){
        if(!$user){http_response_code(401);throw new RuntimeException('برای مشاهده اطلاعات تماس وارد حساب شوید.');}
        if(!in_array((string)($_POST['id']??''),$_SESSION['nzai_results']??[],true)){http_response_code(403);throw new RuntimeException('ابتدا آگهی را جستجو کنید.');}
        $out=nzai_contact($db,(string)$_POST['id'],$user);
    }elseif(($_POST['action']??'')==='reset'){unset($_SESSION['nzai_'.$mode],$_SESSION['nzai_results']);$out=[];
    }elseif(($_POST['action']??'')==='resume'){
        $s=$_SESSION['nzai_'.$mode]??[];$out=[];
        if(($s['uid']??null)===($user['id']??'anonymous')&&(int)($s['expires']??0)>time()){
            $plan=$s['draft'];$out=['plan'=>$plan,'history'=>[]];foreach($s['history']??[] as $h){$value=$h['content'];if($h['role']==='assistant')$value=json_decode($value,true)['reply']??'';$out['history'][]=['role'=>$h['role'],'text'=>$value];}
            if($mode==='search'){$out['results']=($plan['ready']??true)?nzai_search($db['posts'],$plan):[];$_SESSION['nzai_results']=array_column($out['results'],'id');}
        }
    }else{
        if($mode==='create'&&!$user){http_response_code(401);throw new RuntimeException('برای ثبت آگهی ابتدا وارد حساب شوید.');}
        $message=trim((string)($_POST['message']??''));if($message===''||mb_strlen($message)>2000){http_response_code(400);throw new RuntimeException('پیام باید بین ۱ تا ۲۰۰۰ حرف باشد.');}
        $s=$_SESSION['nzai_'.$mode]??['history'=>[],'draft'=>[]];if(($s['expires']??0)<time()||($s['uid']??null)!==($user['id']??'anonymous'))$s=['history'=>[],'draft'=>$user?nzailearn_seed($db,(string)$user['id']):[]];
        $history=array_slice($s['history'],-18);$history[]=['role'=>'user','content'=>$message];
        $plan=nzai_plan($history,$mode,$s['draft'],$db['ai_examples']??[]);$history[]=['role'=>'assistant','content'=>json_encode($plan,JSON_UNESCAPED_UNICODE)];
        $_SESSION['nzai_'.$mode]=['history'=>$history,'draft'=>$plan,'expires'=>time()+3600,'uid'=>$user['id']??'anonymous'];
        $token=bin2hex(random_bytes(16));$_SESSION['nzai_turns']=array_filter($_SESSION['nzai_turns']??[],fn($t)=>(int)($t['expires']??0)>time());$_SESSION['nzai_turns']=array_slice($_SESSION['nzai_turns'],-19,null,true);$_SESSION['nzai_turns'][$token]=['uid'=>$user['id']??'','expires'=>time()+1800];
        $out=['plan'=>$plan,'turn'=>$token];if($mode==='search'){$out['results']=($plan['ready']??true)?nzai_search($db['posts'],$plan):[];$_SESSION['nzai_results']=array_column($out['results'],'id');if($user&&nzailearn_profile($db,(string)$user['id']))nzc_db_write(fn(&$db)=>nzailearn_save_memory($db,(string)$user['id'],$plan));}
    }
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(503);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
