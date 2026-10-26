<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$expected='__TOKEN__';
if(!hash_equals($expected,(string)($_SERVER['HTTP_X_REPORT_TOKEN']??''))){http_response_code(403);exit;}
try{
  $c=require __DIR__.'/config.php';
  $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $sql="SELECT p.id,p.name,p.brand,p.quantity_label,p.barcode,p.pyramid_code2,p.category,p.stock,
        COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END),0) sold_qty,
        COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN oi.order_id END) order_count
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id=p.id
        LEFT JOIN orders o ON o.id=oi.order_id AND (o.status IS NULL OR o.status NOT IN ('cancelled','canceled'))
        WHERE p.active=1 AND (p.image_path IS NULL OR p.image_path='')
        GROUP BY p.id,p.name,p.brand,p.quantity_label,p.barcode,p.pyramid_code2,p.category,p.stock
        ORDER BY (p.stock>0) DESC, sold_qty DESC, order_count DESC, p.stock DESC, p.id ASC
        LIMIT 1600";
  $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'count'=>count($rows),'rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
@unlink(__FILE__);
