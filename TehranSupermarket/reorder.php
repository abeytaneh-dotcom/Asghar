<?php
declare(strict_types=1);session_start();date_default_timezone_set('Asia/Tehran');
header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');
if(!file_exists(__DIR__.'/config.php')){header('Location:install.php');exit;}$c=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);}catch(Throwable $e){exit('اتصال برقرار نیست.');}
$token=trim((string)($_GET['t']??''));$orderId=0;
if($token!==''&&preg_match('/^[a-f0-9]{32,64}$/',$token)){$st=$pdo->prepare('SELECT id FROM orders WHERE public_token=? LIMIT 1');$st->execute([$token]);$orderId=(int)($st->fetchColumn()?:0);}
if($orderId<1){$_SESSION['flash']='برای سفارش مجدد، ابتدا سفارش قبلی را از صفحه پیگیری باز کنید.';header('Location:track.php');exit;}
$q=$pdo->prepare('SELECT oi.product_id,oi.quantity,p.stock,p.price,p.active FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');$q->execute([$orderId]);$added=0;$skipped=0;
foreach($q->fetchAll() as $r){if(!(int)$r['active']||(int)$r['stock']<1||(int)$r['price']<1){$skipped++;continue;}$qty=max(1,min((int)$r['quantity'],(int)$r['stock'],50));$_SESSION['cart'][(int)$r['product_id']]=$qty;$added++;}
$_SESSION['flash']=$added.' قلم از سفارش قبلی به سبد اضافه شد'.($skipped?' و '.$skipped.' قلم فعلاً قابل سفارش نبود.':'.');header('Location:index.php#cart');
