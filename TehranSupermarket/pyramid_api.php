<?php
declare(strict_types=1);date_default_timezone_set('Asia/Tehran');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function out(array $d,int $s=200):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!file_exists(__DIR__.'/config.php'))out(['ok'=>false,'error'=>'not_installed'],503);
$c=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);}catch(Throwable $e){out(['ok'=>false,'error'=>'db'],500);}
$keyFile=__DIR__.'/storage/pyramid_sync_key.php';if(!file_exists($keyFile))out(['ok'=>false,'error'=>'sync_not_initialized'],503);$expected=require $keyFile;$given=(string)($_SERVER['HTTP_X_SYNC_KEY']??($_GET['key']??''));if(!is_string($expected)||$given===''||!hash_equals($expected,$given))out(['ok'=>false,'error'=>'unauthorized'],401);
$action=(string)($_GET['action']??'status');
if($action==='status'){
 $counts=$pdo->query("SELECT COUNT(*) total,SUM(active=1) active,SUM(active=1 AND pyramid_initialized=1) mapped FROM products")->fetch();
 $pending=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status<>'cancelled' AND pyramid_synced_at IS NULL")->fetchColumn();
 out(['ok'=>true,'server_time'=>date(DATE_ATOM),'products'=>$counts,'pending_orders'=>$pending]);
}
if($action==='products'){
 $since=trim((string)($_GET['since']??''));$sql="SELECT id,barcode,pyramid_code2,name,brand,quantity_label,category,price,stock,updated_at FROM products WHERE active=1";$params=[];
 if($since!==''){$sql.=" AND COALESCE(updated_at,created_at)>=?";$params[]=$since;}$sql.=" ORDER BY id";$st=$pdo->prepare($sql);$st->execute($params);out(['ok'=>true,'products'=>$st->fetchAll()]);
}
if($action==='orders'){
 $st=$pdo->query("SELECT o.id,o.status,o.payment_method,o.payment_status,o.delivery_slot,o.subtotal,o.delivery_fee,o.total,o.created_at,o.pyramid_synced_at,o.pyramid_invoice_no,c.name customer_name,c.phone,c.address FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.status<>'cancelled' AND o.pyramid_synced_at IS NULL ORDER BY o.id ASC LIMIT 200");$orders=$st->fetchAll();
 if($orders){$ids=array_column($orders,'id');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT oi.order_id,oi.product_id,COALESCE(oi.barcode,p.barcode) barcode,p.pyramid_code2,oi.product_name,oi.unit_price,oi.quantity FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ph) ORDER BY oi.id");$q->execute($ids);$items=[];foreach($q->fetchAll() as $it)$items[(int)$it['order_id']][]=$it;foreach($orders as &$o)$o['items']=$items[(int)$o['id']]??[];unset($o);}
 out(['ok'=>true,'orders'=>$orders]);
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='product_update'){
 $d=json_decode(file_get_contents('php://input'),true);if(!is_array($d))out(['ok'=>false,'error'=>'bad_json'],422);$barcode=trim((string)($d['barcode']??''));$code2=trim((string)($d['code2']??''));$stock=filter_var($d['stock']??null,FILTER_VALIDATE_INT);$price=filter_var($d['price']??null,FILTER_VALIDATE_INT);if($barcode===''&&$code2==='')out(['ok'=>false,'error'=>'identifier_required'],422);if($stock===false||$stock<0||$price===false||$price<0)out(['ok'=>false,'error'=>'bad_values'],422);
 $where=$barcode!==''?'barcode=?':'pyramid_code2=?';$idv=$barcode!==''?$barcode:$code2;$st=$pdo->prepare("UPDATE products SET stock=?,price=?,updated_at=NOW() WHERE active=1 AND $where");$st->execute([$stock,$price,$idv]);out(['ok'=>true,'updated'=>$st->rowCount()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='order_ack'){
 $d=json_decode(file_get_contents('php://input'),true);if(!is_array($d))out(['ok'=>false,'error'=>'bad_json'],422);$id=max(0,(int)($d['order_id']??0));$invoice=trim((string)($d['invoice_no']??''));if($id<1)out(['ok'=>false,'error'=>'order_id'],422);$st=$pdo->prepare('UPDATE orders SET pyramid_synced_at=NOW(),pyramid_invoice_no=? WHERE id=?');$st->execute([$invoice!==''?$invoice:null,$id]);out(['ok'=>true,'updated'=>$st->rowCount()]);
}
out(['ok'=>false,'error'=>'not_found'],404);