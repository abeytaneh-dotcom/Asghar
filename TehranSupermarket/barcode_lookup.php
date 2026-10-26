<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if($_SERVER['REQUEST_METHOD'] !== 'GET'){
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed'],JSON_UNESCAPED_UNICODE);
    exit;
}

$barcode = preg_replace('/\D+/', '', (string)($_GET['barcode'] ?? '')) ?? '';
if(strlen($barcode) < 4 || strlen($barcode) > 32){
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_barcode'],JSON_UNESCAPED_UNICODE);
    exit;
}

try{
    $c = require __DIR__.'/config.php';
    $pdo = new PDO(
        'mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',
        $c['db_user'],
        $c['db_pass'],
        [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]
    );

    $sql = 'SELECT id,name,barcode,price,stock,category,image_path FROM products WHERE active=1 AND barcode=? LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$barcode]);
    $p = $st->fetch();

    if(!$p && ltrim($barcode,'0') !== ''){
        $normalized = ltrim($barcode,'0');
        $st = $pdo->prepare("SELECT id,name,barcode,price,stock,category,image_path FROM products WHERE active=1 AND TRIM(LEADING '0' FROM barcode)=? LIMIT 1");
        $st->execute([$normalized]);
        $p = $st->fetch();
    }

    if(!$p){
        echo json_encode(['ok'=>false,'found'=>false,'barcode'=>$barcode],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stock = (float)$p['stock'];
    $stockDisplay = abs($stock - round($stock)) < 0.0001 ? (int)round($stock) : $stock;
    echo json_encode([
        'ok'=>true,
        'found'=>true,
        'product'=>[
            'id'=>(int)$p['id'],
            'name'=>(string)$p['name'],
            'barcode'=>(string)$p['barcode'],
            'price'=>(int)$p['price'],
            'price_text'=>number_format((int)$p['price']).' تومان',
            'stock'=>$stockDisplay,
            'stock_text'=>(string)$stockDisplay.' عدد',
            'category'=>(string)($p['category'] ?? ''),
            'image_path'=>(string)($p['image_path'] ?? ''),
        ],
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'lookup_failed'],JSON_UNESCAPED_UNICODE);
}
