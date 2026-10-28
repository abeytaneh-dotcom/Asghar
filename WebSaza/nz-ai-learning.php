<?php
declare(strict_types=1);
require_once __DIR__.'/nz-taxonomy.php';
require_once __DIR__.'/nz-ai-native.php';

function nzailearn_phrase(string $raw): string {
    $phrase=trim(preg_replace('/\s+/u',' ',$raw)??'');$norm=nznative_norm($phrase);
    if(mb_strlen($phrase)<2||mb_strlen($phrase)>80||!preg_match('/^[\p{L}\p{N}\s\-‌]+$/u',$phrase)||preg_match('/[0-9]{7,}|(?:خیابان|کوچه|پلاک|آدرس|محله|شماره|موبایلم|تلفن)/u',$norm))throw new RuntimeException('فقط نام کوتاه کالا یا خدمت را بدون شماره، آدرس یا اطلاعات شخصی بنویسید.');
    return $phrase;
}
function nzailearn_profile(array $db,string $uid): ?array {
    foreach($db['ai_profiles']??[] as $row)if(($row['user_id']??'')===$uid&&!empty($row['enabled'])&&(int)($row['expires']??0)>time())return $row;
    return null;
}
function nzailearn_seed(array $db,string $uid): array {
    $row=nzailearn_profile($db,$uid);if(!$row)return [];$seed=[];foreach(['city','province','min_price','max_price'] as $key)$seed[$key]=$row[$key]??(str_contains($key,'price')?null:'');
    if($seed['city']!==''&&!in_array($seed['city'],nzt_provinces()[$seed['province']]??[],true)){$seed['city']='';$seed['province']='';}
    return $seed;
}
function nzailearn_save_memory(array &$db,string $uid,array $plan,bool $enable=false): void {
    if(!$enable&&!nzailearn_profile($db,$uid))return;
    $row=['id'=>'ai-memory-'.$uid,'user_id'=>$uid,'enabled'=>true,'expires'=>time()+30*86400,'updated_at'=>date('c')];
    foreach(['city','province'] as $key)$row[$key]=is_string($plan[$key]??null)?$plan[$key]:'';
    foreach(['min_price','max_price'] as $key)$row[$key]=is_int($plan[$key]??null)&&$plan[$key]>=0&&$plan[$key]<=999999999999?$plan[$key]:null;
    if($row['city']!==''&&!in_array($row['city'],nzt_provinces()[$row['province']]??[],true)){$row['city']='';$row['province']='';}
    $db['ai_profiles']=array_values(array_filter($db['ai_profiles']??[],fn($p)=>($p['user_id']??'')!==$uid));$db['ai_profiles'][]=$row;
}
function nzailearn_forget(array &$db,string $uid): void {
    foreach(['ai_profiles','ai_examples','ai_personalization','ai_search_history','ai_interactions'] as $bucket)$db[$bucket]=array_values(array_filter($db[$bucket]??[],fn($row)=>($row['user_id']??'')!==$uid));
}
function nzailearn_submit(array &$db,array $user,array $input): string {
    if(($input['consent']??'')!=='1')throw new RuntimeException('ارسال نمونه برای بهبود دستیار نیازمند رضایت شماست.');
    $phrase=nzailearn_phrase((string)($input['phrase']??''));$cat=(string)($input['category']??'');$sub=(string)($input['subcategory']??'');
    if(!in_array($sub,nzt_categories()[$cat]??[],true))throw new RuntimeException('دسته و زیر‌دستهٔ درست را انتخاب کنید.');
    $uid=(string)$user['id'];$id='learn-'.substr(hash('sha256',$uid.'|'.nznative_norm($phrase).'|'.$cat.'|'.$sub),0,24);
    foreach($db['ai_examples']??[] as $row)if(($row['id']??'')===$id)return $id;
    $rows=array_values(array_filter($db['ai_examples']??[],fn($e)=>(int)($e['created_ts']??0)>time()-90*86400||($e['status']??'')==='approved'));
    if(count($rows)>=1000)throw new RuntimeException('صف بررسی نمونه‌ها فعلاً پر است.');
    $rows[]=['id'=>$id,'user_id'=>$uid,'phrase'=>$phrase,'category'=>$cat,'subcategory'=>$sub,'status'=>'pending','created_ts'=>time(),'created_at'=>date('c')];$db['ai_examples']=$rows;return $id;
}
function nzailearn_review(array &$db,array $admin,string $id,string $decision): void {
    if(!function_exists('nzauth_is_elevated')||!nzauth_is_elevated($admin))throw new RuntimeException('برای بررسی یادگیری، ورود تأییدشدهٔ مدیریت لازم است.');
    $status=match($decision){'approve'=>'approved','reject'=>'rejected','disable'=>'disabled',default=>throw new RuntimeException('تصمیم بررسی معتبر نیست.')};
    $db['ai_examples']??=[];
    foreach($db['ai_examples'] as &$row){if(($row['id']??'')!==$id)continue;
        if($status==='approved'){
            nzailearn_phrase((string)$row['phrase']);if(!in_array($row['subcategory'],nzt_categories()[$row['category']]??[],true))throw new RuntimeException('نمونه با دسته‌های فعلی سازگار نیست.');
            $approved=0;foreach($db['ai_examples'] as $other)if(($other['status']??'')==='approved'&&($other['id']??'')!==$id){$approved++;if(nznative_norm($other['phrase'])===nznative_norm($row['phrase'])&&($other['category']!==$row['category']||$other['subcategory']!==$row['subcategory']))throw new RuntimeException('برای این عبارت دستهٔ دیگری فعال است؛ ابتدا آن نمونه را لغو کنید.');}
            if($approved>=500)throw new RuntimeException('سقف نمونه‌های فعال رسیده است؛ ابتدا نمونه‌های قدیمی را لغو کنید.');
        }
        $row['status']=$status;$row['reviewed_at']=date('c');$row['reviewed_by']=$admin['id'];return;
    }unset($row);
    throw new RuntimeException('نمونهٔ آموزشی پیدا نشد.');
}
function nzailearn_admin_html(array $db,array $user): string {
    if(!nzauth_is_elevated($user))return '';
    $rows=array_values(array_filter($db['ai_examples']??[],fn($r)=>in_array($r['status']??'',['pending','approved'],true)));$pending=count(array_filter($rows,fn($r)=>$r['status']==='pending'));$active=count($rows)-$pending;
    $h='<section class="surface nzai-admin"><h2>یادگیری دستیار نیازستان</h2><p>نمونه‌های در انتظار: '.$pending.' · نمونه‌های فعال: '.$active.'</p><p class="subtle">فقط نام کالا و دستهٔ اصلاح‌شده بررسی می‌شود؛ گفت‌وگو و آدرس وارد آموزش مشترک نمی‌شوند. تأیید، روی تشخیص‌های بعدی اثر می‌گذارد و قابل لغو است.</p>';
    usort($rows,fn($a,$b)=>strcmp($a['status'],$b['status']));foreach(array_slice($rows,0,100) as $r){$h.='<article class="nzai-learning-row"><b>'.nzc_e($r['phrase']).'</b><p>'.nzc_e($r['category'].' / '.$r['subcategory']).'</p>';
        foreach($r['status']==='pending'?['approve'=>'تأیید یادگیری','reject'=>'رد']:['disable'=>'لغو یادگیری'] as $decision=>$label)$h.='<button type="button" data-ai-review="'.nzc_e($r['id']).'" data-decision="'.$decision.'">'.$label.'</button>';
        $h.='</article>';}
    return $h.'<p id="nzai-admin-status" role="status"></p></section>';
}
