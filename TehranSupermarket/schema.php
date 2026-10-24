<?php
declare(strict_types=1);

function pyramid_internal_ean13(int $id): string {
    $body = '29'.str_pad((string)$id, 10, '0', STR_PAD_LEFT);
    $sum = 0;
    for ($i=0; $i<12; $i++) $sum += ((($i+1) % 2) === 0 ? 3 : 1) * (int)$body[$i];
    $check = (10 - ($sum % 10)) % 10;
    return $body.(string)$check;
}

function ensure_pyramid_mapping(PDO $pdo): void {
    $rows = $pdo->query("SELECT id,barcode,stock,pyramid_code2,pyramid_barcode_generated,pyramid_initialized FROM products WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('UPDATE products SET barcode=?, pyramid_code2=?, pyramid_barcode_generated=?, pyramid_initialized=1, stock=?, updated_at=NOW() WHERE id=?');
    foreach ($rows as $r) {
        $barcode = trim((string)($r['barcode'] ?? ''));
        $generated = (int)($r['pyramid_barcode_generated'] ?? 0);
        if ($barcode === '') {
            $barcode = pyramid_internal_ean13((int)$r['id']);
            $generated = 1;
        }
        $code2 = trim((string)($r['pyramid_code2'] ?? ''));
        if ($code2 === '') $code2 = (string)(900000 + (int)$r['id']);
        $stock = (int)$r['stock'];
        if (!(int)($r['pyramid_initialized'] ?? 0)) $stock = 10;
        $st->execute([$barcode, $code2, $generated, $stock, (int)$r['id']]);
    }
}

function ensure_product_schema(PDO $pdo): void {
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC) as $row) $columns[$row['Field']] = true;
    $alters = [];
    if (!isset($columns['brand'])) $alters[] = "ADD COLUMN brand VARCHAR(140) NULL AFTER name";
    if (!isset($columns['quantity_label'])) $alters[] = "ADD COLUMN quantity_label VARCHAR(120) NULL AFTER brand";
    if (!isset($columns['image_path'])) $alters[] = "ADD COLUMN image_path VARCHAR(500) NULL AFTER category";
    if (!isset($columns['image_source'])) $alters[] = "ADD COLUMN image_source VARCHAR(500) NULL AFTER image_path";
    if (!isset($columns['updated_at'])) $alters[] = "ADD COLUMN updated_at DATETIME NULL AFTER created_at";
    if (!isset($columns['pyramid_code2'])) $alters[] = "ADD COLUMN pyramid_code2 VARCHAR(40) NULL AFTER barcode";
    if (!isset($columns['pyramid_barcode_generated'])) $alters[] = "ADD COLUMN pyramid_barcode_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER pyramid_code2";
    if (!isset($columns['pyramid_initialized'])) $alters[] = "ADD COLUMN pyramid_initialized TINYINT(1) NOT NULL DEFAULT 0 AFTER pyramid_barcode_generated";
    if ($alters) $pdo->exec('ALTER TABLE products '.implode(', ', $alters));

    $orderCols=[];
    foreach ($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $row) $orderCols[$row['Field']]=true;
    $oa=[];
    if(!isset($orderCols['pyramid_synced_at'])) $oa[]="ADD COLUMN pyramid_synced_at DATETIME NULL";
    if(!isset($orderCols['pyramid_invoice_no'])) $oa[]="ADD COLUMN pyramid_invoice_no VARCHAR(100) NULL";
    if($oa) $pdo->exec('ALTER TABLE orders '.implode(', ', $oa));

    $itemCols=[];
    foreach ($pdo->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_ASSOC) as $row) $itemCols[$row['Field']]=true;
    if(!isset($itemCols['barcode'])) $pdo->exec("ALTER TABLE order_items ADD COLUMN barcode VARCHAR(100) NULL AFTER product_id");

    ensure_pyramid_mapping($pdo);
}
