<?php
declare(strict_types=1);

function ensure_product_schema(PDO $pdo): void {
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[$row['Field']] = true;
    }
    $alters = [];
    if (!isset($columns['brand'])) $alters[] = "ADD COLUMN brand VARCHAR(140) NULL AFTER name";
    if (!isset($columns['quantity_label'])) $alters[] = "ADD COLUMN quantity_label VARCHAR(120) NULL AFTER brand";
    if (!isset($columns['image_path'])) $alters[] = "ADD COLUMN image_path VARCHAR(500) NULL AFTER category";
    if (!isset($columns['image_source'])) $alters[] = "ADD COLUMN image_source VARCHAR(500) NULL AFTER image_path";
    if (!isset($columns['updated_at'])) $alters[] = "ADD COLUMN updated_at DATETIME NULL AFTER created_at";
    if ($alters) {
        $pdo->exec('ALTER TABLE products '.implode(', ', $alters));
    }
}
