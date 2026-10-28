<?php
declare(strict_types=1);
require_once __DIR__.'/nz-ai-core.php';

function nzaiexp_enabled(array $db,string $uid): bool {
    foreach($db['ai_personalization']??[] as $r)if(($r['user_id']??'')===$uid&&!empty($r['enabled']))return true;return false;
}
function nzaiexp_forget(array &$db,string $uid): void {
    foreach(['ai_personalization','ai_search_history','ai_interactions'] as $bucket)$db[$bucket]=array_values(array_filter($db[$bucket]??[],fn($r)=>($r['user_id']??'')!==$uid));
}
function nzaiexp_enable(array &$db,string $uid,bool $enabled): void {
    if(!$enabled){nzaiexp_forget($db,$uid);return;}
    if(nzaiexp_enabled($db,$uid))return;
    $db['ai_personalization'][]=['id'=>'ai-personal-'.$uid,'user_id'=>$uid,'enabled'=>true,'consent_at'=>time()];
}
/** Structured item filters only; never conversation, phone, street or coordinates. */
function nzaiexp_filters(array $plan): ?array {
    if(($plan['ready']??true)!==true)return null;
    $cat=(string)($plan['category']??'');$sub=(string)($plan['subcategory']??'');if(!isset(nzt_categories()[$cat])||($sub!==''&&!in_array($sub,nzt_categories()[$cat],true)))return null;
    $q=trim((string)($plan['query']??''));if(mb_strlen($q)>80||preg_match('/(?:[0-9][\s-]*){7}|(?:خیابان|کوچه|پلاک|آدرس|شماره|تلفن)|[@<>:\/]/u',nznative_norm($q)))return null;
    $province=(string)($plan['province']??'');$city=(string)($plan['city']??'');if($province!==''&&!isset(nzt_provinces()[$province]))return null;if($city!==''&&!in_array($city,nzt_provinces()[$province]??[],true))return null;
    $out=['query'=>$q,'category'=>$cat,'subcategory'=>$sub,'province'=>$province,'city'=>$city];
    foreach(['min_price','max_price'] as $k){$v=$plan[$k]??null;if($v!==null&&(!is_int($v)||$v<0||$v>999999999999))return null;$out[$k]=$v;}
    if($out['min_price']!==null&&$out['max_price']!==null&&$out['min_price']>$out['max_price'])return null;
    $out['attributes']=[];
    foreach($plan['attributes']??[] as $label=>$value){
        if(!in_array($label,['برند','مدل','وضعیت','نوع معامله','تعداد اتاق','متراژ','سال ساخت','کارکرد','وضعیت بدنه','نوع همکاری','رنگ'],true)||!is_string($value)||mb_strlen($value)>80||preg_match('/(?:[0-9][\s-]*){7}|(?:خیابان|کوچه|پلاک|آدرس|شماره|تلفن)|[@<>:\/]/u',nznative_norm($value)))return null;
        if($value!=='')$out['attributes'][$label]=$value;
    }
    return $out;
}
function nzaiexp_prune(array &$db): void {
    foreach(['ai_search_history','ai_interactions'] as $bucket)$db[$bucket]=array_values(array_filter($db[$bucket]??[],fn($r)=>(int)($r['expires']??0)>time()));
}
function nzaiexp_record_search(array &$db,string $uid,array $plan): void {
    if(!nzaiexp_enabled($db,$uid)||!($filters=nzaiexp_filters($plan)))return;
    nzaiexp_prune($db);$id='search-'.substr(hash('sha256',$uid.'|'.json_encode($filters,JSON_UNESCAPED_UNICODE)),0,24);
    $db['ai_search_history']=array_values(array_filter($db['ai_search_history']??[],fn($r)=>($r['id']??'')!==$id));
    $mine=array_values(array_filter($db['ai_search_history'],fn($r)=>($r['user_id']??'')===$uid));$keep=array_column(array_slice($mine,-29),'id');
    $db['ai_search_history']=array_values(array_filter($db['ai_search_history'],fn($r)=>($r['user_id']??'')!==$uid||in_array($r['id'],$keep,true)));
    $db['ai_search_history'][]=['id'=>$id,'user_id'=>$uid,'filters'=>$filters,'used_at'=>microtime(true),'expires'=>time()+30*86400];
}
function nzaiexp_manual_filters(array $get,array $examples=[]): ?array {
    foreach(['district','brand','model','year_min','year_max','mileage_max','area_min','area_max','rooms','radius','lat','lng'] as $key)if(trim((string)($get[$key]??''))!=='')return null;
    $f=['query'=>trim((string)($get['q']??'')),'category'=>(string)($get['category']??''),'subcategory'=>(string)($get['subcategory']??''),'province'=>(string)($get['province']??''),'city'=>(string)($get['city']??''),'ready'=>true];
    if($f['category']===''&&$f['query']!==''){$c=nznative_classify($f['query'],$examples);if($c){$f['category']=$c['category'];$f['subcategory']=$c['subcategory'];}}
    if($f['city']!==''&&$f['province']==='')foreach(nzt_provinces() as $province=>$cities)if(in_array($f['city'],$cities,true)){$f['province']=$province;break;}
    foreach(['price_min'=>'min_price','price_max'=>'max_price'] as $input=>$output){$raw=(string)($get[$input]??'');if($raw!==''&&!preg_match('/^[0-9]+$/D',$raw))return null;$f[$output]=$raw!==''?(int)$raw:null;if($input==='price_max'&&$f[$output]===0)$f[$output]=null;}
    return nzaiexp_filters($f);
}
function nzaiexp_recall(array $db,string $uid,string $id): array {
    if(nzaiexp_enabled($db,$uid))foreach($db['ai_search_history']??[] as $r)if(($r['id']??'')===$id&&($r['user_id']??'')===$uid&&(int)($r['expires']??0)>time()){
        $filters=nzaiexp_filters($r['filters']+['ready'=>true]);if($filters)return $filters;
    }
    throw new RuntimeException('این جستجوی ذخیره‌شده در دسترس نیست.');
}
function nzaiexp_label(array $f): string {
    $label=trim(($f['query']?:($f['subcategory']?:$f['category'])).($f['city']!==''?' در '.$f['city']:''));
    if($f['min_price']!==null)$label.=' از '.number_format($f['min_price']).' تومان';if($f['max_price']!==null)$label.=' تا '.number_format($f['max_price']).' تومان';return $label;
}
function nzaiexp_react(array &$db,string $uid,string $pid,string $value): void {
    if(!nzaiexp_enabled($db,$uid))throw new RuntimeException('ابتدا پیشنهادهای شخصی را با اجازهٔ خودتان فعال کنید.');
    if(!in_array($value,['like','dismiss','clear','contact'],true))throw new RuntimeException('بازخورد معتبر نیست.');
    $post=null;foreach($db['posts']??[] as $p)if(($p['id']??'')===$pid&&nzai_public($p)){$post=$p;break;}
    if(!$post)throw new RuntimeException('این آگهی دیگر در دسترس نیست.');
    if(nzc_user_blocked($db,(string)($post['user_id']??''))||(function_exists('nzcc_blocked')&&nzcc_blocked($db,$uid,(string)($post['user_id']??''))))throw new RuntimeException('این آگهی در دسترس نیست.');
    nzaiexp_prune($db);$kind=$value==='contact'?'contact':'reaction';$id='interaction-'.substr(hash('sha256',$uid.'|'.$pid.'|'.$kind),0,24);
    $db['ai_interactions']=array_values(array_filter($db['ai_interactions']??[],fn($r)=>($r['id']??'')!==$id));
    if($value==='clear')return;
    $mine=array_values(array_filter($db['ai_interactions'],fn($r)=>($r['user_id']??'')===$uid));$keep=array_column(array_slice($mine,-99),'id');
    $db['ai_interactions']=array_values(array_filter($db['ai_interactions'],fn($r)=>($r['user_id']??'')!==$uid||in_array($r['id'],$keep,true)));
    $db['ai_interactions'][]=['id'=>$id,'user_id'=>$uid,'post_id'=>$pid,'value'=>$value,'category'=>(string)($post['category']??''),'subcategory'=>(string)($post['subcategory']??''),'created_at'=>time(),'expires'=>time()+30*86400];
}
function nzaiexp_suggestions(array $db,string $uid): array {
    $out=['enabled'=>nzaiexp_enabled($db,$uid),'history'=>[],'cards'=>[]];if(!$out['enabled'])return $out;
    $history=array_values(array_filter($db['ai_search_history']??[],fn($r)=>($r['user_id']??'')===$uid&&(int)($r['expires']??0)>time()));usort($history,fn($a,$b)=>$b['used_at']<=>$a['used_at']);$history=array_slice($history,0,3);
    $events=array_values(array_filter($db['ai_interactions']??[],fn($r)=>($r['user_id']??'')===$uid&&(int)($r['expires']??0)>time()));$dismissed=[];foreach($events as $e)if($e['value']==='dismiss')$dismissed[]=$e['post_id'];
    $eligible=[];foreach($db['posts']??[] as $p){$owner=(string)($p['user_id']??'');if($owner===$uid||nzc_user_blocked($db,$owner)||(function_exists('nzcc_blocked')&&nzcc_blocked($db,$uid,$owner)))continue;$eligible[]=$p;}
    $candidates=[];foreach($history as $index=>$h){$f=nzaiexp_filters($h['filters']+['ready'=>true]);if(!$f)continue;$label=nzaiexp_label($f);$out['history'][]=['id'=>$h['id'],'label'=>$label];
        foreach(nzai_search($eligible,$f) as $p){if(in_array($p['id'],$dismissed,true))continue;$score=30-$index*4;$liked=false;
            foreach($events as $e){if($e['post_id']===$p['id']){$score+=match($e['value']){'like'=>16,'contact'=>10,default=>0};if($e['value']==='like')$liked=true;}elseif($e['category']===$f['category']&&$e['subcategory']===$f['subcategory'])$score+=match($e['value']){'like'=>2,'contact'=>1,'dismiss'=>-1,default=>0};}
            if(!isset($candidates[$p['id']])||$score>$candidates[$p['id']]['_score'])$candidates[$p['id']]=$p+['_score'=>$score,'reason'=>'بر اساس جستجوی «'.$label.'»','liked'=>$liked];
        }
    }
    usort($candidates,fn($a,$b)=>($b['_score']<=>$a['_score'])?:strcmp($b['created_at'],$a['created_at'])?:strcmp($a['id'],$b['id']));
    foreach(array_slice($candidates,0,4) as $p){unset($p['_score']);$out['cards'][]=$p;}
    return $out;
}
function nzaiexp_plan(array $filters): array {
    $p=nzai_clean([]);$p=array_replace($p,$filters);$p['ready']=true;$p['stage']='results';$p['engine']='native';$p['choices']=[];$p['reply']='جستجوی قبلی را با همان شرایط ادامه دادم؛ آگهی‌های فعلی سایت در ادامه نمایش داده می‌شوند.';
    $p['_native']=['subject'=>$filters['query']?:$filters['subcategory'],'matched'=>$filters['subcategory'],'all_cities'=>$filters['city']==='','any_budget'=>$filters['min_price']===null&&$filters['max_price']===null];return $p;
}
