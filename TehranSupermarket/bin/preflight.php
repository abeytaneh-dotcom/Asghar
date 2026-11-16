<?php
declare(strict_types=1);

/**
 * Read-only staging database preflight for Tehran Market.
 * Never runs migrations, SQL writes or demo seeds.
 * Requires TEHRAN_STAGING=1 and an explicit --check invocation.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

if (in_array('--help', $argv, true)) {
    fwrite(STDOUT, "Usage: TEHRAN_STAGING=1 php bin/preflight.php --check\n");
    exit(0);
}
if (!in_array('--check', $argv, true) || getenv('TEHRAN_STAGING') !== '1') {
    fwrite(STDERR, "Refused: explicit --check and staging-only environment required.\n");
    exit(2);
}

$conf = dirname(__DIR__) . '/config.php';
if (!is_file($conf)) {
    fwrite(STDERR, "Staging DB configuration is not present.\n");
    exit(3);
}

try {
    $c = require $conf; // Never print or log configuration values.
    if (!is_array($c)) {
        throw new RuntimeException('invalid_configuration');
    }
    $pdo = new PDO(
        'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
        $c['db_user'], $c['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false,
         PDO::ATTR_TIMEOUT => 5]
    );
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $schemaRows = $pdo->query(
        "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ('products','orders','order_items','admins','store_settings','sync_devices')
         ORDER BY TABLE_NAME,COLUMN_NAME"
    )->fetchAll();

    $tables = [];
    foreach ($schemaRows as $row) {
        $tables[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }

    $required = [
        'products' => ['id','barcode','pyramid_code2','name','price','stock','active'],
        'orders' => ['id','status','payment_status'],
        'order_items' => ['id','order_id','product_id','quantity','unit_price'],
    ];

    $missing = [];
    foreach ($required as $table => $columns) {
        foreach ($columns as $column) {
            if (!in_array($column, $tables[$table] ?? [], true)) {
                $missing[] = $table . '.' . $column;
            }
        }
    }
    $versions = $pdo->query('SELECT VERSION() version')->fetch();
    $count = isset($tables['products'])
        ? (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : null;

    $report = [
        'ok' => count($missing) === 0,
        'mode' => 'staging_read_only',
        'database_engine_version' => (string)($versions['version'] ?? 'unknown'),
        'product_count' => $count,
        'required_fields_missing' => $missing,
        'checked_table_names' => array_keys($tables),
        'database_changed' => false
    ];
    fwrite(STDOUT, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit($report['ok'] ? 0 : 4);
} catch (Throwable $exception) {
    // Do not disclose connection, filesystem or query details.
    fwrite(STDERR, "Staging read-only preflight failed; details withheld.\n");
    exit(5);
}
