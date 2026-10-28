<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-chat-controls.php';
require_once __DIR__.'/nz-ai-core.php';
require_once __DIR__.'/nz-ai-learning.php';
require_once __DIR__.'/nz-ai-personal.php';
require_once __DIR__.'/nz-ai-browse.php';
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
        foreach(nzab_posts($db,$user,true) as $post)if((string)$post['id']===(string)$_POST['id']&&nzab_demo($post)){http_response_code(409);throw new RuntimeException('این آگهی نمایشی است و فروشنده یا اطلاعات تماس واقعی ندارد.');}
        $out=nzai_contact($db,(string)$_POST['id'],$user);
        if(nzaiexp_enabled($db,(string)$user['id']))try{nzc_db_write(fn(&$db)=>nzaiexp_react($db,(string)$user['id'],(string)$_POST['id'],'contact'));}catch(Throwable $e){error_log('Niazestan interaction history unavailable');}
    }elseif(($_POST['action']??'')==='reset'){unset($_SESSION['nzai_'.$mode],$_SESSION['nzai_results']);$out=[];
    }elseif(($_POST['action']??'')==='resume'){
        $s=$_SESSION['nzai_'.$mode]??[];$out=[];
        if(($s['uid']??null)===($user['id']??'anonymous')&&(int)($s['expires']??0)>time()){
            $plan=$s['draft'];if($mode==='search')$plan=nzab_plan(nzab_posts($db,$user,true),'',$plan,$db['ai_examples']??[]);elseif(($plan['engine']??'')==='native')$plan=nznative_guidance($plan,$mode);$out=['plan'=>$plan,'history'=>[]];foreach($s['history']??[] as $h){$value=$h['content'];if($h['role']==='assistant')$value=json_decode($value,true)['reply']??'';$out['history'][]=['role'=>$h['role'],'text'=>$value];}
            if($mode==='search'){$out['browse']=nzab_view(nzab_posts($db,$user,true),$plan);$out['results']=$out['browse']['results'];$_SESSION['nzai_results']=array_column($out['results'],'id');$_SESSION['nzai_search']['draft']=$plan;$out['history']=array_values(array_filter($out['history'],fn($h)=>$h['role']==='user'||!preg_match('/کدام دسته|کدام نوع از این دسته|کدام شهر جستجو|بازه.*بودجه/u',$h['text'])));}
        }
    }else{
        if($mode==='create'&&!$user){http_response_code(401);throw new RuntimeException('برای ثبت آگهی ابتدا وارد حساب شوید.');}
        $recall=($_POST['action']??'')==='recall';$recalled=null;
        if($recall){if(!$user||$mode!=='search'){http_response_code(403);throw new RuntimeException('جستجوی قبلی فقط در حساب خودتان در دسترس است.');}try{$recalled=nzaiexp_recall($db,(string)$user['id'],(string)($_POST['history_id']??''));}catch(RuntimeException $e){http_response_code(404);throw $e;}}
        $facetAction=$mode==='search'&&in_array($_POST['action']??'',['refine','more'],true);
        $message=$facetAction?(($_POST['action']==='more')?'آگهی‌های بیشتر':(string)($_POST['value']??'حذف انتخاب')):($recall?'ادامهٔ جستجوی قبلی':trim((string)($_POST['message']??'')));if($facetAction&&$message==='')$message='حذف انتخاب';if($message===''||mb_strlen($message)>2000){http_response_code(400);throw new RuntimeException('پیام باید بین ۱ تا ۲۰۰۰ حرف باشد.');}
        $s=$_SESSION['nzai_'.$mode]??['history'=>[],'draft'=>[]];if(($s['expires']??0)<time()||($s['uid']??null)!==($user['id']??'anonymous'))$s=['history'=>[],'draft'=>$user?nzailearn_seed($db,(string)$user['id']):[]];
        $history=array_slice($s['history'],-18);$history[]=['role'=>'user','content'=>$message];
        if($mode==='search'){
            $posts=nzab_posts($db,$user,true);$draft=$recalled?nzaiexp_plan($recalled):$s['draft'];
            if(($draft['engine']??'')!=='browse'&&$recall)$draft=nzab_base($draft);
            if(($_POST['action']??'')==='more'){$plan=nzab_plan($posts,'',$draft,$db['ai_examples']??[]);$plan['_browse']['offset']=min(49992,(int)($draft['_browse']['offset']??0)+12);}
            else $plan=nzab_plan($posts,$recall||$facetAction?'':$message,$draft,$db['ai_examples']??[],($_POST['action']??'')==='refine'?['key'=>(string)($_POST['facet']??''),'value'=>(string)($_POST['value']??'')]:null);
        }else $plan=nzai_plan($history,$mode,$s['draft'],$db['ai_examples']??[]);$history[]=['role'=>'assistant','content'=>json_encode($plan,JSON_UNESCAPED_UNICODE)];
        $_SESSION['nzai_'.$mode]=['history'=>$history,'draft'=>$plan,'expires'=>time()+3600,'uid'=>$user['id']??'anonymous'];
        $token=bin2hex(random_bytes(16));$_SESSION['nzai_turns']=array_filter($_SESSION['nzai_turns']??[],fn($t)=>(int)($t['expires']??0)>time());$_SESSION['nzai_turns']=array_slice($_SESSION['nzai_turns'],-19,null,true);$_SESSION['nzai_turns'][$token]=['uid'=>$user['id']??'','expires'=>time()+1800];
        $out=['plan'=>$plan,'turn'=>$token];if($mode==='search'){$out['browse']=nzab_view(nzab_posts($db,$user,true),$plan);$out['results']=$out['browse']['results'];$ids=array_column($out['results'],'id');$_SESSION['nzai_results']=($_POST['action']??'')==='more'?array_slice(array_values(array_unique(array_merge($_SESSION['nzai_results']??[],$ids))),-120):$ids;if($user&&nzailearn_profile($db,(string)$user['id']))nzc_db_write(fn(&$db)=>nzailearn_save_memory($db,(string)$user['id'],$plan));if($user&&nzaiexp_enabled($db,(string)$user['id']))try{nzc_db_write(fn(&$db)=>nzaiexp_record_search($db,(string)$user['id'],$plan));}catch(Throwable $e){error_log('Niazestan personal history unavailable');}}
    }
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(503);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
