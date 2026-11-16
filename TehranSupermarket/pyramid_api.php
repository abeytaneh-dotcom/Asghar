<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Tehran-Sync-Version: 2');

function out(array $d,int $s=200): never {
    http_response_code($s);
    echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function body_json(): array {
    $raw=file_get_contents('php://input');
    if($raw===false || trim($raw)==='') return [];
    if(strlen($raw)>8*1024*1024) out(['ok'=>false,'error'=>'payload_too_large','code'=>'SYNC-702'],413);
    $d=json_decode($raw,true);
    if(!is_array($d)) out(['ok'=>false,'error'=>'invalid_json','code'=>'SYNC-702'],422);
    return $d;
}
function price_to_toman($value,string $unit): ?int {
    $v=filter_var($value,FILTER_VALIDATE_INT);
    if($v===false || $v<0) return null;
    $unit=strtolower(trim($unit));
    if($unit==='rial') return (int)round($v/10);
    if($unit==='toman' || $unit==='') return (int)$v;
    return null;
}
function get_orders(PDO $pdo,int $limit=200): array {
    $limit=max(1,min(500,$limit));
    $sql="SELECT o.id,o.status,o.payment_method,o.payment_status,o.delivery_slot,o.subtotal,o.delivery_fee,o.total,o.created_at,o.pyramid_synced_at,o.pyramid_invoice_no,c.name customer_name,c.phone,c.address FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.status<>'cancelled' AND o.pyramid_synced_at IS NULL ORDER BY o.id ASC LIMIT ".$limit;
    $orders=$pdo->query($sql)->fetchAll();
    if($orders){
        $ids=array_column($orders,'id');
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $q=$pdo->prepare("SELECT oi.order_id,oi.product_id,COALESCE(oi.barcode,p.barcode) barcode,p.pyramid_code2,oi.product_name,oi.unit_price,oi.quantity FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ph) ORDER BY oi.id");
        $q->execute($ids);$items=[];
        foreach($q->fetchAll() as $it)$items[(int)$it['order_id']][]=$it;
        foreach($orders as &$o)$o['items']=$items[(int)$o['id']]??[];
        unset($o);
    }
    return $orders;
}
function get_products(PDO $pdo,string $since=''): array {
    $sql="SELECT id,barcode,pyramid_code2,name,brand,quantity_label,category,price,stock,updated_at FROM products WHERE active=1";$params=[];
    if($since!==''){$sql.=" AND COALESCE(updated_at,created_at)>=?";$params[]=$since;}
    $sql.=' ORDER BY id';$st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll();
}

if(!file_exists(__DIR__.'/config.php')) ts_json_fail('SYNC-703',503);
$c=require __DIR__.'/config.php';
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    // Database schema must be prepared by an explicit reviewed migration.
    require_once __DIR__.'/pyramid_sync_lib.php';
    $expected=ts_sync_key();
}catch(Throwable $e){ts_log_internal($e,'SYNC-703');ts_json_fail('SYNC-703',503);}

$given=trim((string)($_SERVER['HTTP_X_SYNC_KEY']??''));
if($given==='' || !hash_equals($expected,$given)) ts_json_fail('SYNC-701',401);

$action=(string)($_GET['action']??'status');
// Backward-compatible Phase 1 safety: no API write until a separate protected
// server-side opt-in file is explicitly provisioned after end-to-end validation.
function ts_bridge_write_enabled(string $action): bool {
    $file=__DIR__.'/storage/pyramid_bridge_writes.php';
    if(!is_file($file)) return false;
    $config=require $file;
    if(!is_array($config)) return false;
    if($action==='order_ack') return ($config['order_ack']??false)===true;
    if(in_array($action,['product_update','products_batch'],true)) return ($config['catalog']??false)===true;
    return false;
}
if (in_array($action, ['product_update', 'products_batch', 'order_ack'], true) && !ts_bridge_write_enabled($action)) {
    out(['ok'=>false,'error'=>'sync_read_only','code'=>'SYNC-READONLY'],403);
}
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$deviceId=trim((string)($_SERVER['HTTP_X_DEVICE_ID']??''));
if($deviceId!=='' && !ts_sync_valid_device_id($deviceId)) out(['ok'=>false,'error'=>'bad_device_id','code'=>'SYNC-702'],422);
try{
    if($deviceId!=='') ts_sync_touch_device($pdo,$deviceId,$action);

    if($action==='status'){
        $counts=$pdo->query("SELECT COUNT(*) total,SUM(active=1) active,SUM(active=1 AND pyramid_initialized=1) mapped FROM products")->fetch();
        $pending=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status<>'cancelled' AND pyramid_synced_at IS NULL")->fetchColumn();
        $devices=(int)$pdo->query('SELECT COUNT(*) FROM sync_devices')->fetchColumn();
        out([
            'ok'=>true,'api_version'=>2,'protocol'=>'TEHRAN-SYNC-2','server_time'=>date(DATE_ATOM),'price_unit'=>'toman',
            'products'=>$counts,'pending_orders'=>$pending,'registered_devices'=>$devices,
            'capabilities'=>['products','products_batch','orders','order_ack','device_register','heartbeat','sync_pull']
        ]);
    }

    if($action==='device_register' && $method==='POST'){
        $d=body_json();
        $id=trim((string)($d['device_id']??$deviceId));
        if(!ts_sync_valid_device_id($id)) out(['ok'=>false,'error'=>'bad_device_id','code'=>'SYNC-702'],422);
        $name=mb_substr(trim((string)($d['device_name']??'')),0,160);
        $version=mb_substr(trim((string)($d['app_version']??'')),0,60);
        $platform=mb_substr(trim((string)($d['platform']??'Windows')),0,80);
        $caps=is_array($d['capabilities']??null)?array_slice($d['capabilities'],0,40):[];
        ts_sync_touch_device($pdo,$id,'device_register',$name,$version,$platform,$caps);
        out(['ok'=>true,'device_id'=>$id,'server_time'=>date(DATE_ATOM),'api_version'=>2]);
    }

    if($action==='heartbeat' && $method==='POST'){
        $d=body_json();
        $id=trim((string)($d['device_id']??$deviceId));
        if(!ts_sync_valid_device_id($id)) out(['ok'=>false,'error'=>'bad_device_id','code'=>'SYNC-702'],422);
        ts_sync_touch_device($pdo,$id,'heartbeat',mb_substr(trim((string)($d['device_name']??'')),0,160),mb_substr(trim((string)($d['app_version']??'')),0,60),mb_substr(trim((string)($d['platform']??'')),0,80),is_array($d['capabilities']??null)?array_slice($d['capabilities'],0,40):[]);
        $pending=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status<>'cancelled' AND pyramid_synced_at IS NULL")->fetchColumn();
        out(['ok'=>true,'server_time'=>date(DATE_ATOM),'pending_orders'=>$pending]);
    }

    if($action==='devices'){
        $rows=$pdo->query("SELECT device_id,device_name,app_version,platform,capabilities_json,first_seen_at,last_seen_at,last_action FROM sync_devices ORDER BY COALESCE(last_seen_at,first_seen_at) DESC LIMIT 100")->fetchAll();
        foreach($rows as &$r){$caps=json_decode((string)($r['capabilities_json']??''),true);$r['capabilities']=is_array($caps)?$caps:[];unset($r['capabilities_json']);}unset($r);
        out(['ok'=>true,'devices'=>$rows]);
    }

    if($action==='products'){
        $since=trim((string)($_GET['since']??''));
        out(['ok'=>true,'price_unit'=>'toman','server_time'=>date(DATE_ATOM),'products'=>get_products($pdo,$since)]);
    }

    if($action==='orders'){
        $limit=(int)($_GET['limit']??200);
        out(['ok'=>true,'price_unit'=>'toman','server_time'=>date(DATE_ATOM),'orders'=>get_orders($pdo,$limit)]);
    }

    if($action==='sync_pull'){
        $since=trim((string)($_GET['since']??''));
        $limit=(int)($_GET['order_limit']??200);
        $serverTime=date(DATE_ATOM);
        out(['ok'=>true,'api_version'=>2,'server_time'=>$serverTime,'price_unit'=>'toman','products'=>get_products($pdo,$since),'orders'=>get_orders($pdo,$limit)]);
    }

    if($action==='product_update' && $method==='POST'){
        $d=body_json();
        $requestId=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??($d['request_id']??'')));
        if($requestId!==''){
            $old=ts_sync_idempotency_load($pdo,$requestId,$action);
            if($old!==null) out($old,($old['ok']??false)?200:409);
        }
        $barcode=trim((string)($d['barcode']??''));$code2=trim((string)($d['code2']??''));
        $stock=filter_var($d['stock']??null,FILTER_VALIDATE_INT);
        $price=price_to_toman($d['price']??null,(string)($d['price_unit']??'toman'));
        if(($barcode===''&&$code2==='') || $stock===false || $stock<0 || $price===null) out(['ok'=>false,'error'=>'bad_product_update','code'=>'SYNC-702'],422);
        $where=$barcode!==''?'barcode=?':'pyramid_code2=?';$idv=$barcode!==''?$barcode:$code2;
        $st=$pdo->prepare("UPDATE products SET stock=?,price=?,updated_at=NOW() WHERE active=1 AND $where");$st->execute([(int)$stock,$price,$idv]);
        $resp=['ok'=>true,'updated'=>$st->rowCount(),'price_toman'=>$price];
        ts_sync_idempotency_store($pdo,$requestId,$action,$resp);out($resp);
    }

    if($action==='products_batch' && $method==='POST'){
        $d=body_json();
        $requestId=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??($d['request_id']??'')));
        if($requestId!==''){
            $old=ts_sync_idempotency_load($pdo,$requestId,$action);
            if($old!==null) out($old,($old['ok']??false)?200:409);
        }
        $items=$d['products']??null;
        if(!is_array($items) || count($items)>1000) out(['ok'=>false,'error'=>'bad_products_batch','code'=>'SYNC-702'],422);
        // Apply an entire batch under row locks, and subtract orders not yet
        // finalized in the POS. This prevents re-stocking a website reservation
        // when the raw POS quantity has not been decremented yet.
        $updated=0;$notFound=[];$invalid=[];
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->beginTransaction();
        try{
            $valid=[];$barcodes=[];
            foreach($items as $idx=>$item){
                if(!is_array($item)){$invalid[]=$idx;continue;}
                $barcode=trim((string)($item['barcode']??''));
                $stock=filter_var($item['stock']??null,FILTER_VALIDATE_INT);
                $price=price_to_toman($item['price']??null,(string)($item['price_unit']??($d['price_unit']??'toman')));
                if($barcode==='' || strlen($barcode)>100 || $stock===false || $stock<0 || $stock>100000000 || $price===null || $price<1 || $price>10000000000){$invalid[]=$idx;continue;}
                $valid[$idx]=['barcode'=>$barcode,'stock'=>(int)$stock,'price'=>$price,'code2'=>trim((string)($item['code2']??''))];
                $barcodes[$barcode]=$barcode;
            }
            $map=[];
            if($barcodes){
                $list=array_values($barcodes);
                $ph=implode(',',array_fill(0,count($list),'?'));
                $st=$pdo->prepare("SELECT id,barcode,pyramid_code2 FROM products WHERE active=1 AND barcode IN ($ph) ORDER BY id FOR UPDATE");
                $st->execute($list);
                foreach($st->fetchAll(PDO::FETCH_ASSOC) as $p)$map[(string)$p['barcode']][]=$p;
            }
            $ids=[];
            foreach($valid as $idx=>$it){
                $matches=$map[$it['barcode']]??[];
                if(count($matches)!==1){$notFound[]=['index'=>$idx,'barcode'=>$it['barcode'],'reason'=>count($matches)>1?'ambiguous':'not_found'];continue;}
                $p=$matches[0];
                // Never accept a contradictory secondary key if the site has already mapped one.
                $siteCode=trim((string)($p['pyramid_code2']??''));
                if($siteCode!=='' && $it['code2']!=='' && $siteCode!==$it['code2']){
                    $notFound[]=['index'=>$idx,'barcode'=>$it['barcode'],'reason'=>'code_mismatch'];continue;
                }
                $valid[$idx]['product_id']=(int)$p['id'];
                $ids[(int)$p['id']]=(int)$p['id'];
            }
            $reserved=[];
            if($ids){
                $list=array_values($ids);
                $ph=implode(',',array_fill(0,count($list),'?'));
                $st=$pdo->prepare("SELECT oi.product_id,COALESCE(SUM(oi.quantity),0) qty
                  FROM order_items oi JOIN orders o ON o.id=oi.order_id
                  WHERE oi.product_id IN ($ph) AND o.pyramid_synced_at IS NULL AND o.status<>'cancelled'
                  GROUP BY oi.product_id");
                $st->execute($list);
                foreach($st->fetchAll(PDO::FETCH_ASSOC) as $p)$reserved[(int)$p['product_id']]=(int)$p['qty'];
            }
            $upd=$pdo->prepare('UPDATE products SET stock=?,price=?,updated_at=NOW() WHERE id=? AND active=1');
            foreach($valid as $idx=>$it){
                if(!isset($it['product_id']))continue;
                $net=max(0,$it['stock']-max(0,$reserved[$it['product_id']]??0));
                $upd->execute([$net,$it['price'],$it['product_id']]);
                $updated++;
            }
            $resp=['ok'=>true,'received'=>count($items),'updated'=>$updated,'not_found'=>$notFound,'invalid_indexes'=>$invalid,'server_time'=>date(DATE_ATOM)];
            ts_sync_idempotency_store($pdo,$requestId,$action,$resp);
            $pdo->commit();
        }catch(Throwable $x){if($pdo->inTransaction())$pdo->rollBack();throw $x;}
        out($resp);
    }

    if($action==='order_ack' && $method==='POST'){
        $d=body_json();
        $requestId=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??($d['request_id']??'')));
        if($requestId!==''){
            $old=ts_sync_idempotency_load($pdo,$requestId,$action);
            if($old!==null) out($old,($old['ok']??false)?200:409);
        }
        $id=max(0,(int)($d['order_id']??0));$invoice=mb_substr(trim((string)($d['invoice_no']??'')),0,100);
        if($id<1 || !preg_match('/^[0-9]{1,20}$/',$invoice)) out(['ok'=>false,'error'=>'invalid_invoice_ack','code'=>'SYNC-702'],422);
        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT id,pyramid_synced_at,pyramid_invoice_no,status,payment_method,payment_status FROM orders WHERE id=? FOR UPDATE');
            $st->execute([$id]);$order=$st->fetch(PDO::FETCH_ASSOC);
            if(!$order || $order['status']==='cancelled') out(['ok'=>false,'error'=>'order_not_eligible'],409);
            if($order['payment_method']!=='cod' && $order['payment_status']!=='paid') out(['ok'=>false,'error'=>'payment_not_verified'],409);
            if($order['pyramid_synced_at']!==null && (string)$order['pyramid_invoice_no']!==$invoice) out(['ok'=>false,'error'=>'invoice_conflict'],409);
            if($order['pyramid_synced_at']===null){
                $pdo->prepare('UPDATE orders SET pyramid_synced_at=NOW(),pyramid_invoice_no=? WHERE id=?')->execute([$invoice,$id]);
            }
            $resp=['ok'=>true,'order_id'=>$id,'invoice_no'=>$invoice,'acknowledged'=>true];
            ts_sync_idempotency_store($pdo,$requestId,$action,$resp);
            $pdo->commit();
        }catch(Throwable $x){if($pdo->inTransaction())$pdo->rollBack();throw $x;}
        out($resp);
    }

    out(['ok'=>false,'error'=>'not_found','code'=>'REQ-101'],404);
}catch(Throwable $e){
    ts_log_internal($e,'SYNC-703');
    ts_json_fail('SYNC-703',500);
}
