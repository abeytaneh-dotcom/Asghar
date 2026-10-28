<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';
require_once __DIR__.'/nz-chat-controls.php';
require_once __DIR__.'/nz-create-pending.php';
require_once __DIR__.'/nz-submit-server.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);throw new RuntimeException('درخواست نامعتبر است.');}
    $db=nzc_db_read();$user=nzc_current_user($db);if(!$user){http_response_code(401);throw new RuntimeException('برای ثبت آگهی وارد حساب شوید.');}
    if(!nzc_csrf_valid()){http_response_code(403);throw new RuntimeException('صفحه را تازه‌سازی کنید.');}
    if(nzc_user_blocked($db,(string)$user['id'])){http_response_code(403);throw new RuntimeException('این حساب اجازه ثبت آگهی ندارد.');}
    $uid=(string)$user['id'];$rootDir=__DIR__.'/.marketplace-data/ai-uploads';$dir=$rootDir.'/'.nzcp_owner_prefix($uid);if(!is_dir($dir))mkdir($dir,0700,true);
    foreach(array_slice(glob($rootDir.'/*/*.part')?:[],0,1000) as $file)if(filemtime($file)<time()-7200)@unlink($file);
    foreach($_SESSION['nzai_uploads']??[] as $id=>$upload)if(($upload['expires']??0)<time()||!is_file($rootDir.'/'.nzcp_owner_prefix((string)$upload['uid']).'/'.$id.'.part'))unset($_SESSION['nzai_uploads'][$id]);
    $action=(string)($_POST['action']??'');
    if($action==='start'){
        nzh_rate_hit('ai-media-start|'.$uid,60,3600,'کمی بعد دوباره تلاش کنید.');
        $size=filter_var($_POST['size']??null,FILTER_VALIDATE_INT);$kind=(string)($_POST['kind']??'');
        $total=array_sum(array_column($_SESSION['nzai_uploads']??[],'size'));
        if(!$size||$size<1||$size+$total>512*1024*1024||count($_SESSION['nzai_uploads']??[])>=12)throw new RuntimeException('فضای آماده‌سازی رسانه موقتاً کافی نیست. دوباره تلاش کنید.');
        if(!in_array($kind,['image','video'],true))throw new RuntimeException('نوع فایل معتبر نیست.');nzcp_assert_capacity($size,$uid);
        $id=bin2hex(random_bytes(16));$h=fopen($dir.'/'.$id.'.part','x');fclose($h);
        $_SESSION['nzai_uploads'][$id]=['uid'=>$uid,'size'=>$size,'kind'=>$kind,'expires'=>time()+7200];$out=['id'=>$id];
    }elseif($action==='status'){
        $id=(string)($_POST['id']??'');$u=$_SESSION['nzai_uploads'][$id]??null;if(!$u||$u['uid']!==$uid){http_response_code(403);throw new RuntimeException('رسانه متعلق به این گفتگو نیست.');}clearstatcache(true,$dir.'/'.$id.'.part');$out=['offset'=>(int)filesize($dir.'/'.$id.'.part')];
    }elseif($action==='chunk'){
        $id=(string)($_POST['id']??'');$u=$_SESSION['nzai_uploads'][$id]??null;
        if(!$u||$u['uid']!==$uid){http_response_code(403);throw new RuntimeException('رسانه متعلق به این گفتگو نیست.');}
        $chunk=$_FILES['chunk']??[];if(($chunk['error']??1)!==UPLOAD_ERR_OK||($chunk['size']??0)<1||$chunk['size']>1024*1024)throw new RuntimeException('ارسال فایل کامل نشد؛ دوباره تلاش کنید.');
        $path=$dir.'/'.$id.'.part';clearstatcache(true,$path);$offset=(int)filesize($path);
        $stagedBytes=0;foreach(glob($dir.'/*.part')?:[] as $f)$stagedBytes+=(int)filesize($f);if($stagedBytes+$chunk['size']>512*1024*1024)throw new RuntimeException('فضای آماده‌سازی رسانه موقتاً کافی نیست.');nzcp_assert_capacity((int)$chunk['size'],$uid);
        if((int)($_POST['offset']??-1)!==$offset||$offset+$chunk['size']>$u['size'])throw new RuntimeException('ترتیب ارسال فایل معتبر نیست.');
        $in=fopen($chunk['tmp_name'],'rb');$dst=fopen($path,'ab');$written=stream_copy_to_stream($in,$dst);fclose($in);fclose($dst);if($written!==$chunk['size'])throw new RuntimeException('ارسال فایل کامل نشد.');$out=['offset'=>$offset+$written];
    }elseif($action==='submit'){
        $key=(string)($_POST['request_id']??'');if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))throw new RuntimeException('شناسه ثبت معتبر نیست.');
        $previous=$_SESSION['nzai_submissions'][$key]??null;
        if($previous&&$previous['uid']===$uid){echo json_encode(['ok'=>true,'id'=>$previous['id'],'url'=>'/nz?page=myposts']);exit;}
        $session=$_SESSION['nzai_create']??[];$p=$session['draft']??[];
        if(($session['uid']??'')!==$uid||($session['expires']??0)<time()||empty($p['ready']))throw new RuntimeException('ابتدا مشخصات آگهی را در گفتگو کامل کن.');
        nzh_rate_hit('ai-chat-submit|'.$uid,20,3600,'کمی بعد دوباره تلاش کنید.');
        $ids=json_decode((string)($_POST['media']??'[]'),true);if(!is_array($ids)||count($ids)>6||array_filter($ids,fn($id)=>!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))||count(array_unique($ids))!==count($ids))throw new RuntimeException('رسانه‌ها معتبر نیستند.');
        $images=['name'=>[],'tmp_name'=>[],'size'=>[],'error'=>[]];$video=[];$paths=[];
        foreach($ids as $id){$u=$_SESSION['nzai_uploads'][$id]??null;if(!$u||$u['uid']!==$uid)throw new RuntimeException('رسانه متعلق به این حساب نیست.');$path=$dir.'/'.$id.'.part';clearstatcache(true,$path);if(!is_file($path)||filesize($path)!==$u['size'])throw new RuntimeException('ارسال رسانه هنوز کامل نشده است.');$paths[]=$path;
            if($u['kind']==='image'){if(count($images['name'])>=5)throw new RuntimeException('حداکثر پنج عکس انتخاب کن.');foreach(['name'=>'photo','tmp_name'=>$path,'size'=>$u['size'],'error'=>UPLOAD_ERR_OK] as $k=>$v)$images[$k][]=$v;}
            else{if($video)throw new RuntimeException('یک ویدیو کافی است.');$video=['name'=>'video','tmp_name'=>$path,'size'=>$u['size'],'error'=>UPLOAD_ERR_OK];}
        }
        if(!$images['name'])throw new RuntimeException('فقط یک عکس لازم است؛ عکس آگهی را بفرست.');
        $lat=$_POST['latitude']??'';$lng=$_POST['longitude']??'';$chat=($_POST['chat_enabled']??'')==='1';$phone=($_POST['phone_visible']??'')==='1';$csrf=$_POST['csrf'];
        $title=$p['title'];if(mb_strlen($title)<5)$title.=' '.($p['attributes']['مدل']??$p['subcategory']);
        $_POST=['csrf'=>$csrf,'title'=>$title,'description'=>$p['description'],'category'=>$p['category'],'subcategory'=>$p['subcategory'],'province'=>$p['province'],'city'=>$p['city'],'district'=>$p['district'],'street'=>$p['street'],'price'=>$p['price'],'attributes'=>$p['attributes'],'latitude'=>$lat,'longitude'=>$lng,'location_mode'=>'approx'];
        if($chat)$_POST['chat_enabled']='1';if($phone)$_POST['phone_visible']='1';$_FILES=['images'=>$images,'video'=>$video];
        $post=nzss_create_post(true);$_SESSION['nzai_submissions'][$key]=['uid'=>$uid,'id'=>$post['id']];$_SESSION['nzai_submissions']=array_slice($_SESSION['nzai_submissions'],-10,null,true);
        foreach($ids as $id)unset($_SESSION['nzai_uploads'][$id]);foreach($paths as $path)@unlink($path);unset($_SESSION['nzai_create']);$out=['id'=>$post['id'],'url'=>'/nz?page=myposts'];
    }else throw new RuntimeException('درخواست نامعتبر است.');
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
