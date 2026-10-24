<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';header('Cache-Control:no-store,private,max-age=0');header('Referrer-Policy:no-referrer');header('X-Robots-Tag:noindex,nofollow');date_default_timezone_set('Asia/Tehran');
$t=trim((string)($_GET['t']??''));if(!preg_match('/^[a-f0-9]{48,64}$/',$t))ts_html_fail('PAY-401',404,'track.php');
if(!file_exists(__DIR__.'/config.php'))ts_html_fail('SYS-100',503,'track.php?t='.rawurlencode($t));$c=require __DIR__.'/config.php';
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);require_once __DIR__.'/payment_lib.php';
    $st=$pdo->prepare('SELECT o.*,c.phone FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.public_token=? LIMIT 1');$st->execute([$t]);$o=$st->fetch();
    if(!$o)ts_html_fail('PAY-401',404,'track.php');
    if($o['status']==='cancelled')ts_html_fail('PAY-402',409,'track.php?t='.rawurlencode($t));
    if($o['payment_status']==='paid'){header('Location:track.php?t='.urlencode($t),true,303);exit;}
    $cfg=ts_payment_config($pdo);if(!$cfg['enabled']||$cfg['merchant']==='')ts_html_fail('PAY-403',503,'track.php?t='.rawurlencode($t));
    $callback='https://websaza.ir/tehran/payment_callback.php?t='.rawurlencode($t);$authority=ts_payment_request($pdo,$o,$callback);
    $pdo->prepare("UPDATE orders SET payment_method='online',payment_status='pending',payment_authority=?,payment_requested_at=NOW(),payment_error=NULL WHERE id=?")->execute([$authority,(int)$o['id']]);
    header('Location:https://www.zarinpal.com/pg/StartPay/'.rawurlencode($authority),true,303);exit;
}catch(Throwable $e){ts_log_internal($e,'PAY-404');ts_html_fail('PAY-404',502,'track.php?t='.rawurlencode($t));}
