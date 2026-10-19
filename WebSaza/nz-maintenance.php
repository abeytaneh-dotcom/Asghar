<?php
declare(strict_types=1);

function nzmaint_referenced_media(array $db): array {
    $keep=[];
    foreach($db['posts']??[] as$p){
        foreach((array)($p['images']??[])as$f){$b=basename((string)$f);if($b!=='')$keep[$b]=true;}
        $v=basename((string)($p['video']??''));if($v!=='')$keep[$v]=true;
    }
    return $keep;
}
function nzmaint_cleanup_media(array $db): array {
    $dir=__DIR__.'/marketplace-uploads';if(!is_dir($dir))return ['deleted'=>0,'bytes'=>0];$keep=nzmaint_referenced_media($db);$deleted=0;$bytes=0;$cut=time()-48*3600;
    foreach(glob($dir.'/*')?:[] as$f){if(!is_file($f))continue;$base=basename($f);if(isset($keep[$base]))continue;if(filemtime($f)>$cut)continue;$size=(int)filesize($f);if(@unlink($f)){$deleted++;$bytes+=$size;}}
    return ['deleted'=>$deleted,'bytes'=>$bytes];
}
function nzmaint_prune_data(): array {
    $stats=['notifications'=>0,'messages'=>0];$now=time();
    nzc_db_write(function(&$db)use(&$stats,$now){
        $before=count($db['notifications']??[]);$db['notifications']=array_values(array_filter($db['notifications']??[],function($n)use($now){$ts=strtotime((string)($n['created_at']??''))?:$now;return empty($n['read'])||$ts>$now-180*86400;}));$stats['notifications']=$before-count($db['notifications']);
        $activeConvs=[];foreach($db['conversations']??[]as$c)if(!empty($c['id']))$activeConvs[(string)$c['id']]=true;
        $before=count($db['messages']??[]);$db['messages']=array_values(array_filter($db['messages']??[],function($m)use($activeConvs,$now){$cid=(string)($m['conversation_id']??'');$ts=strtotime((string)($m['created_at']??''))?:$now;return isset($activeConvs[$cid])||$ts>$now-30*86400;}));$stats['messages']=$before-count($db['messages']);
    });
    return $stats;
}
function nzmaint_rate_cleanup(): int {
    $file=__DIR__.'/.marketplace-data/rate-limit.json';if(!is_file($file))return 0;$raw=@file_get_contents($file);$data=json_decode($raw?:'',true);if(!is_array($data))return 0;$now=time();$before=count($data);foreach($data as$k=>$vals){$keep=array_values(array_filter((array)$vals,fn($t)=>is_int($t)&&$t>$now-86400));if($keep)$data[$k]=$keep;else unset($data[$k]);}nzstore_atomic_json($file,$data);return $before-count($data);
}
function nzmaint_run(): array {
    $life=function_exists('nzl_tick')?nzl_tick(true):[];$db=nzc_db_read();$media=nzmaint_cleanup_media($db);$pruned=nzmaint_prune_data();$backup=nzstore_backup(nzc_default_db());$rate=nzmaint_rate_cleanup();
    nzstore_audit('system-cron','maintenance','system','',['lifecycle'=>$life,'media'=>$media,'pruned'=>$pruned,'backup'=>$backup,'rate_keys_removed'=>$rate]);
    return ['lifecycle'=>$life,'media'=>$media,'pruned'=>$pruned,'backup'=>$backup,'rate_keys_removed'=>$rate,'backend'=>nzstore_backend(),'checked_at'=>date('c')];
}
