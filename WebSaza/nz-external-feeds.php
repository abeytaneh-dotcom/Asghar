<?php
declare(strict_types=1);

require_once __DIR__.'/nz-community.php';

const NZEXT_SYNC_INTERVAL = 14400; // 4 hours; Jobicy allows hourly or less often.
const NZEXT_SOURCE_USER = 'source_jobicy';
const NZEXT_SOURCE_NAME = 'Jobicy';
const NZEXT_FEED_URL = 'https://jobicy.com/api/v2/remote-jobs?count=200';
const NZEXT_STATUS_URL = 'https://jobicy.com/api/v2/remote-jobs/status?ids=';

function nzext_state_file(): string { return nzstore_dir().'/external-feeds-state.json'; }
function nzext_lock_file(): string { return nzstore_dir().'/external-feeds.lock'; }
function nzext_read_state(): array {
    $raw=@file_get_contents(nzext_state_file());
    $data=json_decode($raw?:'',true);
    return is_array($data)?$data:[];
}
function nzext_write_state(array $state): void {
    @file_put_contents(nzext_state_file(),json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)?:'{}',LOCK_EX);
    @chmod(nzext_state_file(),0640);
}
function nzext_http_json(string $url): array {
    $raw='';$status=0;
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'Niyazestan/1.0 (+https://websaza.ir/nz)']);
        $result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=(string)curl_error($ch);curl_close($ch);
        if($result===false||$err!=='')throw new RuntimeException('خطا در دریافت فید مجاز: '.$err);
        $raw=(string)$result;
    }else{
        $ctx=stream_context_create(['http'=>['timeout'=>20,'ignore_errors'=>true,'header'=>"Accept: application/json\r\nUser-Agent: Niyazestan/1.0 (+https://websaza.ir/nz)\r\n"]]);
        $result=@file_get_contents($url,false,$ctx);if($result===false)throw new RuntimeException('امکان دریافت فید مجاز وجود ندارد.');$raw=(string)$result;
        foreach($http_response_header??[]as$line)if(preg_match('/^HTTP\/\S+\s+(\d{3})/',$line,$m))$status=(int)$m[1];
    }
    if($status!==0&&($status<200||$status>=300))throw new RuntimeException('فید مجاز پاسخ HTTP '.$status.' داد.');
    $json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('پاسخ فید مجاز JSON معتبر نیست.');
    return$json;
}
function nzext_plain(string $html,int $limit=5000): string {
    $html=preg_replace('~<\s*br\s*/?\s*>~i',"\n",$html)??$html;
    $html=preg_replace('~</\s*(p|li|div|h[1-6])\s*>~i',"\n",$html)??$html;
    $html=preg_replace('~<\s*li[^>]*>~i','• ',$html)??$html;
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=preg_replace('/[ \t]+/u',' ',$text)??$text;
    $text=preg_replace('/\n{3,}/u',"\n\n",$text)??$text;
    return mb_substr(trim($text),0,$limit);
}
function nzext_subcategory(array $job): string {
    $parts=[];foreach((array)($job['jobIndustry']??[])as$v)$parts[]=(string)$v;$parts[]=(string)($job['jobTitle']??'');$hay=mb_strtolower(implode(' ',$parts));
    $has=function(array $words)use($hay):bool{foreach($words as$w)if(str_contains($hay,$w))return true;return false;};
    if($has(['sales','marketing','growth','business development','account executive']))return'فروش و بازاریابی';
    if($has(['finance','accounting','accountant','payroll','bookkeep']))return'مالی و حسابداری';
    if($has(['education','teacher','teaching','instructor','tutor','learning']))return'آموزش';
    if($has(['engineer','engineering','developer','software','data','devops','security','cyber','qa','testing','technical','product','design','designer']))return'فنی و مهندسی';
    return'اداری';
}
function nzext_salary(array $job): string {
    $min=$job['salaryMin']??null;$max=$job['salaryMax']??null;$cur=trim((string)($job['salaryCurrency']??''));$period=trim((string)($job['salaryPeriod']??''));
    if(!is_numeric($min)&&!is_numeric($max))return'';$parts=[];if(is_numeric($min))$parts[]=number_format((float)$min,0,'.',',');if(is_numeric($max))$parts[]=number_format((float)$max,0,'.',',');$range=implode(' تا ',$parts);return trim($range.' '.$cur.($period!==''?' / '.$period:''));
}
function nzext_valid_job_url(string $url): bool {
    if(filter_var($url,FILTER_VALIDATE_URL)===false)return false;$host=strtolower((string)parse_url($url,PHP_URL_HOST));return $host==='jobicy.com'||$host==='www.jobicy.com';
}
function nzext_map_job(array $job): ?array {
    $sid=trim((string)($job['id']??''));$title=trim((string)($job['jobTitle']??''));$url=trim((string)($job['url']??''));if($sid===''||$title===''||!nzext_valid_job_url($url))return null;
    $company=mb_substr(trim((string)($job['companyName']??'شرکت')),0,160);$geo=mb_substr(trim((string)($job['jobGeo']??'دورکاری')),0,160);if($geo==='')$geo='دورکاری';
    $desc=nzext_plain((string)($job['jobDescription']??''));if(mb_strlen($desc)<15)$desc=nzext_plain((string)($job['jobExcerpt']??''));if(mb_strlen($desc)<15)$desc='جزئیات کامل این فرصت شغلی در صفحه منبع Jobicy در دسترس است.';
    $types=array_values(array_filter(array_map('strval',(array)($job['jobType']??[]))));$industries=array_values(array_filter(array_map('strval',(array)($job['jobIndustry']??[]))));$level=trim((string)($job['jobLevel']??''));$salary=nzext_salary($job);
    $attrs=['شرکت'=>$company,'محدوده استخدام'=>$geo,'منبع اصلی'=>NZEXT_SOURCE_NAME];if($types)$attrs['نوع همکاری']=implode('، ',$types);if($industries)$attrs['حوزه']=implode('، ',$industries);if($level!=='')$attrs['سطح شغلی']=$level;if($salary!=='')$attrs['حقوق اعلام‌شده']=$salary;
    $pub=trim((string)($job['pubDate']??''));$ts=$pub!==''?strtotime($pub):false;$created=$ts!==false?date('c',$ts):nzc_now();
    return[
        'id'=>'ext_jobicy_'.$sid,'user_id'=>NZEXT_SOURCE_USER,'title'=>mb_substr($title.' — Jobicy',0,140),'description'=>$desc,
        'category'=>'استخدام و کاریابی','subcategory'=>nzext_subcategory($job),'province'=>'دورکاری / بین‌المللی','city'=>$geo,'district'=>'','street'=>'',
        'price'=>0,'images'=>[],'video'=>null,'video_duration'=>null,'attributes'=>$attrs,'chat_enabled'=>false,'phone_visible'=>false,
        'status'=>'published','review_status'=>'approved','review_priority'=>'normal','featured'=>false,'views'=>0,
        'created_at'=>$created,'published_at'=>$created,'updated_at'=>nzc_now(),
        'source_kind'=>'licensed_public_api','source_name'=>NZEXT_SOURCE_NAME,'source_id'=>$sid,'source_url'=>$url,'source_attribution_required'=>true,'external'=>true,'external_last_seen_at'=>nzc_now(),'external_status'=>'active'
    ];
}
function nzext_existing_jobicy_ids(array $db): array {
    $ids=[];foreach($db['posts']??[]as$p)if(($p['source_name']??'')===NZEXT_SOURCE_NAME&&($p['source_id']??'')!=='')$ids[]=(string)$p['source_id'];return array_values(array_unique($ids));
}
function nzext_status_map(array $ids): array {
    $map=[];foreach(array_chunk($ids,100)as$chunk){try{$json=nzext_http_json(NZEXT_STATUS_URL.rawurlencode(implode(',',$chunk)));if(($json['success']??true)===false)continue;foreach((array)($json['jobs']??[])as$row){$id=(string)($row['id']??'');$status=(string)($row['status']??'');if($id!==''&&in_array($status,['active','closed','unknown'],true))$map[$id]=$status;}}catch(Throwable $e){continue;}}return$map;
}
function nzext_sync_jobicy(): array {
    $json=nzext_http_json(NZEXT_FEED_URL);if(($json['success']??true)===false)throw new RuntimeException('فید Jobicy درخواست را نپذیرفت.');$rows=(array)($json['jobs']??[]);if(!$rows)throw new RuntimeException('فید Jobicy آگهی فعالی برنگرداند.');
    $mapped=[];foreach($rows as$row){if(!is_array($row))continue;$p=nzext_map_job($row);if($p)$mapped[$p['source_id']]=$p;}if(!$mapped)throw new RuntimeException('هیچ آگهی معتبر Jobicy قابل تبدیل نبود.');
    $before=nzc_db_read();$statusMap=nzext_status_map(nzext_existing_jobicy_ids($before));
    return(array)nzc_db_write(function(&$db)use($mapped,$statusMap){
        $hasSourceUser=false;foreach($db['users']??[]as&$u)if(($u['id']??'')===NZEXT_SOURCE_USER){$hasSourceUser=true;$u['name']='Jobicy · منبع آگهی';$u['role']='source';$u['source']=true;break;}unset($u);if(!$hasSourceUser)$db['users'][]=['id'=>NZEXT_SOURCE_USER,'name'=>'Jobicy · منبع آگهی','role'=>'source','source'=>true,'created_at'=>nzc_now()];
        $index=[];foreach($db['posts']??[]as$i=>$p)if(($p['source_name']??'')===NZEXT_SOURCE_NAME&&($p['source_id']??'')!=='')$index[(string)$p['source_id']]=$i;
        $inserted=0;$updated=0;$expired=0;
        foreach($mapped as$sid=>$new){if(isset($index[$sid])){$i=$index[$sid];$views=(int)($db['posts'][$i]['views']??0);$db['posts'][$i]=array_merge($db['posts'][$i],$new,['views'=>$views]);$updated++;}else{$db['posts'][]=$new;$inserted++;}}
        foreach($db['posts']as&$p){if(($p['source_name']??'')!==NZEXT_SOURCE_NAME)continue;$sid=(string)($p['source_id']??'');if($sid===''||isset($mapped[$sid]))continue;$remote=$statusMap[$sid]??'';if($remote==='closed'||$remote==='unknown'){$p['status']='expired';$p['external_status']=$remote;$p['updated_at']=nzc_now();$expired++;}elseif($remote==='active'){$p['status']='published';$p['external_status']='active';$p['updated_at']=nzc_now();}}
        unset($p);return['inserted'=>$inserted,'updated'=>$updated,'expired'=>$expired,'active_feed'=>count($mapped)];
    });
}
function nzext_maybe_sync(): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')return;$state=nzext_read_state();$last=(int)($state['last_success_ts']??0);if($last>0&&time()-$last<NZEXT_SYNC_INTERVAL)return;
    $fh=@fopen(nzext_lock_file(),'c+');if(!$fh||!@flock($fh,LOCK_EX|LOCK_NB)){if($fh)@fclose($fh);return;}
    try{
        $state=nzext_read_state();$last=(int)($state['last_success_ts']??0);if($last>0&&time()-$last<NZEXT_SYNC_INTERVAL)return;$state['last_attempt_ts']=time();$state['last_attempt_at']=nzc_now();nzext_write_state($state);
        $stats=nzext_sync_jobicy();$state=array_merge($state,$stats,['last_success_ts'=>time(),'last_success_at'=>nzc_now(),'last_error'=>'']);nzext_write_state($state);
    }catch(Throwable $e){$state=nzext_read_state();$state['last_error_at']=nzc_now();$state['last_error']=mb_substr($e->getMessage(),0,500);nzext_write_state($state);}finally{@flock($fh,LOCK_UN);@fclose($fh);}
}
function nzext_output_filter(string $html): string {
    $id=trim((string)($_GET['id']??''));if($id===''||$html==='')return$html;$db=nzc_db_read();$post=null;foreach($db['posts']??[]as$p)if(($p['id']??'')===$id){$post=$p;break;}if(!$post||($post['source_name']??'')!==NZEXT_SOURCE_NAME)return$html;$url=(string)($post['source_url']??'');if(!nzext_valid_job_url($url))return$html;
    $card='<div class="surface nzext-source" style="margin-top:14px;border:1px solid #dfe7ff;background:#f8faff"><h3 style="margin-top:0">منبع آگهی</h3><div class="subtle" style="line-height:2;margin-bottom:10px">این فرصت شغلی از فید عمومی Jobicy نمایش داده شده است. نیازستان منتشرکننده اصلی این آگهی نیست و وضعیت نهایی را باید در صفحه منبع بررسی کنید.</div><a class="btn primary" href="'.nzc_e($url).'" target="_blank" rel="noopener noreferrer nofollow">مشاهده آگهی اصلی در Jobicy</a></div>';
    if(str_contains($html,'<div class="surface nzd-seller"'))return str_replace('<div class="surface nzd-seller"',$card.'<div class="surface nzd-seller"',$html);
    return str_replace('</section></main>',$card.'</section></main>',$html);
}
