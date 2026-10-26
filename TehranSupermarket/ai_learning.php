<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';
session_start();
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function al_out(array $d,int $status=200): never { http_response_code($status); echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST')al_out(['ok'=>false],405);
$site=(string)($_SERVER['HTTP_SEC_FETCH_SITE']??'');
if($site!==''&&!in_array($site,['same-origin','same-site'],true))al_out(['ok'=>false],403);

$config=__DIR__.'/config.php';if(!is_file($config))al_out(['ok'=>false],503);$c=require $config;
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    require_once __DIR__.'/ai_learning_lib.php';ts_ai_learning_schema($pdo);
}catch(Throwable $e){ts_log_internal($e,'AI-LEARN-501');al_out(['ok'=>false],503);}

$type=trim((string)($_POST['event_type']??''));
$q=trim((string)($_POST['q']??''));
$corrected=trim((string)($_POST['corrected']??''));
$pid=max(0,(int)($_POST['product_id']??0));$qty=max(0,min(50,(int)($_POST['quantity']??0)));
$fulfillment=trim((string)($_POST['fulfillment_method']??''));
$allowed=['search','product_click','add_to_cart','spell_accept','checkout_start','fulfillment_choice'];
if(!in_array($type,$allowed,true))al_out(['ok'=>false],422);
if($pid>0){$st=$pdo->prepare('SELECT id FROM products WHERE id=? LIMIT 1');$st->execute([$pid]);if(!$st->fetchColumn())$pid=0;}
if($type==='spell_accept'&&$q!==''&&$corrected!=='')ts_ai_learn_alias($pdo,$q,$corrected);
if(in_array($type,['product_click','add_to_cart'],true)&&$pid>0)ts_ai_signal($pdo,$pid,$type);
ts_ai_log_event($pdo,$type,$q!==''?$q:null,$corrected!==''?$corrected:null,$pid?:null,$qty,$fulfillment);
al_out(['ok'=>true]);
