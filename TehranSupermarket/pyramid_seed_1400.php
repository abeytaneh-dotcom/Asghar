<?php
declare(strict_types=1);
// One-time catalog seed; deployment trigger 2026-10-26.

function ts_seed_pyramid_1400(): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    $dataFile = __DIR__.'/pyramid_seed_1400_data.php';
    if (!is_file($dataFile) || !is_file(__DIR__.'/config.php')) return;

    $pdo = null;
    try {
        require_once __DIR__.'/error_bootstrap.php';
        $c = require __DIR__.'/config.php';
        $pdo = new PDO(
            'mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
        );

        require_once __DIR__.'/schema.php';
        ensure_product_schema($pdo);

        $markerKey = 'pyramid_seed_1400_v1';
        $marker = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=? LIMIT 1');
        $marker->execute([$markerKey]);
        if ((string)($marker->fetchColumn() ?: '') !== '') return;

        if (!defined('TS_INTERNAL_SEED')) define('TS_INTERNAL_SEED', true);
        $encoded = require $dataFile;
        if (!is_string($encoded) || $encoded === '') throw new RuntimeException('seed_data_missing');
        $compressed = base64_decode($encoded, true);
        if ($compressed === false) throw new RuntimeException('seed_base64_invalid');
        $payload = gzdecode($compressed);
        if ($payload === false) throw new RuntimeException('seed_gzip_invalid');

        $lines = preg_split('/\r?\n/', trim($payload));
        if (!is_array($lines) || count($lines) < 9000) throw new RuntimeException('seed_row_count_invalid');

        $backupTable = 'products_backup_pre_pyramid1400';
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE products");
        $backupCount = (int)$pdo->query("SELECT COUNT(*) FROM `$backupTable`")->fetchColumn();
        if ($backupCount === 0) $pdo->exec("INSERT INTO `$backupTable` SELECT * FROM products");

        $findBarcode = $pdo->prepare("SELECT id FROM products WHERE barcode=? LIMIT 1");
        $findCode = $pdo->prepare("SELECT id FROM products WHERE pyramid_code2=? LIMIT 1");
        $findName = $pdo->prepare("SELECT id FROM products WHERE name=? AND active=1 AND (pyramid_barcode_generated=1 OR pyramid_initialized=0) ORDER BY id LIMIT 2");
        $update = $pdo->prepare("UPDATE products SET barcode=?,pyramid_code2=?,pyramid_barcode_generated=0,pyramid_initialized=1,name=?,image_path=NULL,image_source=NULL,price=?,original_price=NULL,stock=10,active=1,updated_at=NOW() WHERE id=?");
        $insert = $pdo->prepare("INSERT INTO products(barcode,pyramid_code2,pyramid_barcode_generated,pyramid_initialized,name,brand,quantity_label,category,image_path,image_source,price,original_price,stock,active,created_at,updated_at) VALUES(?,?,0,1,?,NULL,NULL,'سایر',NULL,NULL,?,NULL,10,1,NOW(),NOW())");

        $inserted = 0;
        $updated = 0;
        $processed = 0;
        $realBarcodes = 0;
        $pdo->beginTransaction();
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $r = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
            $code2 = trim((string)($r['code2'] ?? ''));
            $barcode = trim((string)($r['barcode'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $price = max(0, (int)($r['price'] ?? 0));
            if ($code2 === '' || $name === '') throw new RuntimeException('seed_record_invalid');
            if ($barcode !== '') $realBarcodes++;

            $id = 0;
            if ($barcode !== '') {
                $findBarcode->execute([$barcode]);
                $id = (int)($findBarcode->fetchColumn() ?: 0);
            }
            if ($id === 0) {
                $findCode->execute([$code2]);
                $id = (int)($findCode->fetchColumn() ?: 0);
            }
            if ($id === 0) {
                $findName->execute([$name]);
                $nameMatches = $findName->fetchAll(PDO::FETCH_COLUMN);
                if (count($nameMatches) === 1) $id = (int)$nameMatches[0];
            }

            $dbBarcode = $barcode !== '' ? $barcode : null;
            if ($id > 0) {
                $update->execute([$dbBarcode, $code2, $name, $price, $id]);
                $updated++;
            } else {
                $insert->execute([$dbBarcode, $code2, $name, $price]);
                $inserted++;
            }
            $processed++;
        }
        if ($processed < 9000) throw new RuntimeException('seed_processed_count_invalid');

        $summary = json_encode([
            'done'=>true,
            'processed'=>$processed,
            'inserted'=>$inserted,
            'updated'=>$updated,
            'real_barcodes'=>$realBarcodes,
            'stock'=>10,
            'images'=>'blank',
            'price_unit'=>'toman',
            'finished_at'=>date(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $save = $pdo->prepare('INSERT INTO store_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
        $save->execute([$markerKey, $summary]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (function_exists('ts_log_internal')) ts_log_internal($e, 'CAT-1400');
        else error_log('CAT-1400 '.$e->getMessage());
    }
}
