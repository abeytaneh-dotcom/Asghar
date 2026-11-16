<?php
declare(strict_types=1);
/**
 * Explicit staging-only bridge schema provisioning.
 * Never used by an HTTP request. Do not run against production.
 */
if (PHP_SAPI !== 'cli' || getenv('TEHRAN_STAGING') !== '1'
    || !in_array('--apply', $argv, true)) {
    fwrite(STDERR, "STAGING_ONLY: explicit CLI --apply and TEHRAN_STAGING=1 required\n");
    exit(2);
}
$cfgFile=dirname(__DIR__).'/config.php';
if (!is_file($cfgFile)) {
    fwrite(STDERR,"CONFIG_MISSING\n");exit(2);
}
$c=require $cfgFile;
if (!is_array($c) || !isset($c['db_host'],$c['db_name'],$c['db_user'],$c['db_pass'])) {
    fwrite(STDERR,"CONFIG_INVALID\n");exit(2);
}
if (!preg_match('/(?:stage|staging|ci_restoration)/i',(string)$c['db_name'])) {
    fwrite(STDERR,"NOT_A_STAGING_DATABASE\n");exit(2);
}
try {
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',
        $c['db_user'],$c['db_pass'],[
          PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES=>false
        ]);
    require_once dirname(__DIR__).'/pyramid_sync_lib.php';
    ts_sync_ensure_schema($pdo);
    require_once dirname(__DIR__).'/pyramid_snapshot_lib.php';
    ts_snapshot_provision_schema($pdo);
    foreach (['sync_devices','sync_idempotency','pyramid_catalog_snapshots','pyramid_catalog_stage'] as $table) {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('missing '.$table);
    }
    echo "BRIDGE_STAGING_SCHEMA=PASS\n";
} catch(Throwable $e) {
    $code=preg_replace('/[^A-Z0-9]/i','', (string)$e->getCode());
    fwrite(STDERR,"BRIDGE_STAGING_SCHEMA=FAIL CLASS=".get_class($e)." CODE=".$code."\n");
    exit(1);
}
