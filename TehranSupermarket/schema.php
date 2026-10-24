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
    $rows = $pdo->query("SELECT id,barcode,stock,pyramid_code2,pyramid_barcode_generated,pyramid_initialized FROM products WHERE active=1 AND (pyramid_initialized=0 OR barcode IS NULL OR barcode='' OR pyramid_code2 IS NULL OR pyramid_code2='') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return;
    $st = $pdo->prepare('UPDATE products SET barcode=?, pyramid_code2=?, pyramid_barcode_generated=?, pyramid_initialized=1, stock=?, updated_at=NOW() WHERE id=?');
    foreach ($rows as $r) {
        $barcode = trim((string)($r['barcode'] ?? ''));
        $generated = (int)($r['pyramid_barcode_generated'] ?? 0);
        if ($barcode === '') {$barcode = pyramid_internal_ean13((int)$r['id']);$generated = 1;}
        $code2 = trim((string)($r['pyramid_code2'] ?? ''));
        if ($code2 === '') $code2 = (string)(900000 + (int)$r['id']);
        $stock = (int)$r['stock'];
        if (!(int)($r['pyramid_initialized'] ?? 0)) $stock = 10;
        $st->execute([$barcode, $code2, $generated, $stock, (int)$r['id']]);
    }
}

function ensure_order_public_tokens(PDO $pdo): void {
    try {
        $rows=$pdo->query("SELECT id FROM orders WHERE public_token IS NULL OR public_token='' LIMIT 1000")->fetchAll(PDO::FETCH_COLUMN);
        if($rows){$st=$pdo->prepare("UPDATE orders SET public_token=? WHERE id=? AND (public_token IS NULL OR public_token='')");foreach($rows as $id)$st->execute([bin2hex(random_bytes(24)),(int)$id]);}
    } catch(Throwable $e) {}
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
    if (!isset($columns['original_price'])) $alters[] = "ADD COLUMN original_price BIGINT UNSIGNED NULL AFTER price";
    if ($alters) $pdo->exec('ALTER TABLE products '.implode(', ', $alters));

    $orderCols=[];
    foreach ($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $row) $orderCols[$row['Field']]=true;
    $oa=[];
    if(!isset($orderCols['pyramid_synced_at'])) $oa[]="ADD COLUMN pyramid_synced_at DATETIME NULL";
    if(!isset($orderCols['pyramid_invoice_no'])) $oa[]="ADD COLUMN pyramid_invoice_no VARCHAR(100) NULL";
    if(!isset($orderCols['stock_restored_at'])) $oa[]="ADD COLUMN stock_restored_at DATETIME NULL";
    if(!isset($orderCols['status_updated_at'])) $oa[]="ADD COLUMN status_updated_at DATETIME NULL";
    if(!isset($orderCols['delivery_zone_id'])) $oa[]="ADD COLUMN delivery_zone_id INT UNSIGNED NULL";
    if(!isset($orderCols['delivery_zone_name'])) $oa[]="ADD COLUMN delivery_zone_name VARCHAR(120) NULL";
    if(!isset($orderCols['delivery_eta'])) $oa[]="ADD COLUMN delivery_eta VARCHAR(120) NULL";
    if(!isset($orderCols['public_token'])) $oa[]="ADD COLUMN public_token VARCHAR(64) NULL";
    if(!isset($orderCols['courier_id'])) $oa[]="ADD COLUMN courier_id INT UNSIGNED NULL";
    if(!isset($orderCols['courier_assigned_at'])) $oa[]="ADD COLUMN courier_assigned_at DATETIME NULL";
    if(!isset($orderCols['delivered_at'])) $oa[]="ADD COLUMN delivered_at DATETIME NULL";
    if(!isset($orderCols['payment_authority'])) $oa[]="ADD COLUMN payment_authority VARCHAR(100) NULL";
    if(!isset($orderCols['payment_ref_id'])) $oa[]="ADD COLUMN payment_ref_id VARCHAR(100) NULL";
    if(!isset($orderCols['payment_requested_at'])) $oa[]="ADD COLUMN payment_requested_at DATETIME NULL";
    if(!isset($orderCols['payment_error'])) $oa[]="ADD COLUMN payment_error VARCHAR(500) NULL";
    if($oa) $pdo->exec('ALTER TABLE orders '.implode(', ', $oa));
    try{
        $idx=[];foreach($pdo->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $r)$idx[$r['Key_name']]=true;
        if(!isset($idx['uq_orders_public_token']))$pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_public_token(public_token)');
        if(!isset($idx['idx_orders_courier']))$pdo->exec('ALTER TABLE orders ADD KEY idx_orders_courier(courier_id,status,created_at)');
        if(!isset($idx['idx_orders_payment']))$pdo->exec('ALTER TABLE orders ADD KEY idx_orders_payment(payment_status,payment_method,created_at)');
    }catch(Throwable $e){}

    $itemCols=[];
    foreach ($pdo->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_ASSOC) as $row) $itemCols[$row['Field']]=true;
    $ia=[];
    if(!isset($itemCols['barcode'])) $ia[]="ADD COLUMN barcode VARCHAR(100) NULL AFTER product_id";
    if(!isset($itemCols['picked'])) $ia[]="ADD COLUMN picked TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity";
    if(!isset($itemCols['picked_at'])) $ia[]="ADD COLUMN picked_at DATETIME NULL AFTER picked";
    if(!isset($itemCols['original_product_id'])) $ia[]="ADD COLUMN original_product_id INT UNSIGNED NULL AFTER product_id";
    if(!isset($itemCols['original_product_name'])) $ia[]="ADD COLUMN original_product_name VARCHAR(190) NULL AFTER product_name";
    if(!isset($itemCols['adjustment_note'])) $ia[]="ADD COLUMN adjustment_note VARCHAR(255) NULL AFTER picked_at";
    if($ia) $pdo->exec('ALTER TABLE order_items '.implode(', ', $ia));

    $pdo->exec("CREATE TABLE IF NOT EXISTS store_settings (setting_key VARCHAR(80) NOT NULL PRIMARY KEY,setting_value TEXT NULL,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_zones (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,fee BIGINT UNSIGNED NOT NULL DEFAULT 0,min_order BIGINT UNSIGNED NOT NULL DEFAULT 0,eta VARCHAR(120) NULL,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS couriers (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,phone VARCHAR(20) NULL,access_token VARCHAR(64) NOT NULL UNIQUE,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,last_seen_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_outbox (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NULL,phone VARCHAR(20) NOT NULL,event_key VARCHAR(60) NOT NULL,message_text VARCHAR(500) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'queued',provider_response TEXT NULL,attempts INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,sent_at DATETIME NULL,INDEX(status,created_at),INDEX(order_id,event_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,endpoint_hash CHAR(64) NOT NULL UNIQUE,endpoint TEXT NOT NULL,admin_id INT UNSIGNED NULL,user_agent VARCHAR(250) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,last_success_at DATETIME NULL,last_error_at DATETIME NULL,INDEX(admin_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults=[
      'manual_mode'=>'auto','open_time'=>'08:00','close_time'=>'23:00',
      'closed_message'=>'سفارش‌گیری فعلاً بسته است. می‌توانید محصولات را ببینید و بعداً سفارش را ثبت کنید.',
      'sms_enabled'=>'0','sms_provider'=>'ippanel','sms_api_token'=>'','sms_from_number'=>'','sms_webhook_url'=>'','sms_webhook_token'=>'',
      'payment_enabled'=>'0','payment_provider'=>'zarinpal','payment_merchant_id'=>'','payment_multiplier'=>'10',
      'push_enabled'=>'1','push_provider'=>'native'
    ];
    $ins=$pdo->prepare('INSERT IGNORE INTO store_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW())');foreach($defaults as $k=>$v)$ins->execute([$k,$v]);

    try{$pdo->exec("UPDATE order_items oi JOIN products p ON p.id=oi.product_id SET oi.barcode=p.barcode WHERE (oi.barcode IS NULL OR oi.barcode='')");}catch(Throwable $e){}
    try{$pdo->exec("UPDATE order_items SET original_product_id=product_id WHERE original_product_id IS NULL");}catch(Throwable $e){}
    try{$pdo->exec("UPDATE order_items SET original_product_name=product_name WHERE original_product_name IS NULL OR original_product_name=''");}catch(Throwable $e){}
    ensure_order_public_tokens($pdo);
    ensure_pyramid_mapping($pdo);
}
