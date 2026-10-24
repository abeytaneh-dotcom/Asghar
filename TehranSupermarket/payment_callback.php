<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';header('Cache-Control:no-store,private,max-age=0');header('Referrer-Policy:no-referrer');header('X-Robots-Tag:noindex,nofollow');date_default_timezone_set('Asia/Tehran');
$t=trim((string)($_GET['t']??''));$authority=trim((string)($_GET['Authority']??''));$status=strtoupper(trim((string)($_GET['Status']??'')));if(!preg_match('/^[a-f0-9]{48,64}$/',$t))ts_html_fail('PAY-401',404,'track.php');
if(!file_exists(__DIR__.'/config.php'))ts_html_fail('SYS-100',503,'track.php?t='.rawurlencode($t));$c=require __DIR__.'/config.php';$ok=false;$code='PAY-402';$message=ts_error_text($code);
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);require_once __DIR__.'/payment_lib.php';
    $st=$pdo->prepare('SELECT * FROM orders WHERE public_token=? LIMIT 1');$st->execute([$t]);$o=$st->fetch();if(!$o)ts_html_fail('PAY-401',404,'track.php');
    if($o['payment_status']==='paid'){$ok=true;$message='پرداخت قبلاً تأیید شده است.';}
    elseif($status!=='OK'||$authority===''||!hash_equals((string)($o['payment_authority']??''),$authority)){
        $pdo->prepare("UPDATE orders SET payment_status='failed',payment_error=? WHERE id=?")->execute(['PAY-402',(int)$o['id']]);$code='PAY-402';$message=ts_error_text($code);
    }else{
        $v=ts_payment_verify($pdo,$o,$authority);$pdo->prepare("UPDATE orders SET payment_status='paid',payment_ref_id=?,payment_error=NULL WHERE id=?")->execute([$v['ref_id'],(int)$o['id']]);$ok=true;$message='پرداخت با موفقیت تأیید شد.';
    }
}catch(Throwable $e){ts_log_internal($e,'PAY-405');$code='PAY-405';$message=ts_error_text($code);try{if(isset($pdo,$o['id']))$pdo->prepare("UPDATE orders SET payment_status='failed',payment_error=? WHERE id=?")->execute([$code,(int)$o['id']]);}catch(Throwable $x){ts_log_internal($x,'PAY-405');}}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نتیجه پرداخت</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><div class="<?=$ok?'success':'notice'?>"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><a class="btn wide" href="track.php?t=<?=htmlspecialchars($t,ENT_QUOTES,'UTF-8')?>">مشاهده سفارش</a></main></body></html>
