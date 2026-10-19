<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$db=nzc_db_read();
$active=0;$total=0;$expired=0;
foreach($db['posts']??[]as$p){
    if(($p['source_name']??'')!=='Jobicy')continue;
    $total++;
    $status=(string)($p['status']??'');
    if($status==='published')$active++;
    if(in_array($status,['expired','deleted'],true))$expired++;
}
$stateFile=nzstore_dir().'/external-feeds-state.json';
$state=[];
if(is_file($stateFile)){$raw=@file_get_contents($stateFile);$decoded=json_decode($raw?:'',true);if(is_array($decoded))$state=$decoded;}
$last=trim((string)($state['last_success_at']??''));
$ok=$active>0&&$last!=='';
echo json_encode([
    'ok'=>$ok,
    'jobicy'=>[
        'active'=>$active,
        'total'=>$total,
        'expired'=>$expired,
        'last_success_at'=>$last!==''?$last:null,
        'last_error_present'=>trim((string)($state['last_error']??''))!=='',
    ],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
