<?php
declare(strict_types=1);

/**
 * Shared helpers for Tehran Supermarket desktop/Pyramid sync.
 * The desktop app always connects outbound over HTTPS to pyramid_api.php.
 */

function ts_sync_key_path(): string {
    return __DIR__.'/storage/pyramid_sync_key.php';
}

function ts_sync_key(): string {
    $dir=__DIR__.'/storage';
    if(!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('sync_storage');
    $file=ts_sync_key_path();
    if(is_file($file)){
        $k=require $file;
        if(is_string($k) && strlen($k)>=32) return $k;
    }
    $k=bin2hex(random_bytes(24));
    $php="<?php return '".$k."';\n";
    if(@file_put_contents($file,$php,LOCK_EX)===false) throw new RuntimeException('sync_key_write');
    @chmod($file,0600);
    return $k;
}

function ts_sync_ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sync_devices (
        device_id VARCHAR(128) NOT NULL PRIMARY KEY,
        device_name VARCHAR(160) NULL,
        app_version VARCHAR(60) NULL,
        platform VARCHAR(80) NULL,
        capabilities_json TEXT NULL,
        first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NULL,
        last_ip_hash CHAR(64) NULL,
        last_action VARCHAR(60) NULL,
        updated_at DATETIME NULL,
        INDEX(last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sync_idempotency (
        request_id VARCHAR(128) NOT NULL PRIMARY KEY,
        action_name VARCHAR(60) NOT NULL,
        response_json MEDIUMTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ts_sync_valid_device_id(string $id): bool {
    return $id!=='' && strlen($id)<=128 && (bool)preg_match('/^[A-Za-z0-9._:@-]+$/',$id);
}

function ts_sync_ip_hash(): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'');
    return hash('sha256',$ip);
}

function ts_sync_touch_device(PDO $pdo,string $deviceId,string $action,string $name='',string $version='',string $platform='',array $capabilities=[]): void {
    if(!ts_sync_valid_device_id($deviceId)) return;
    $caps=$capabilities ? json_encode(array_values(array_unique(array_map('strval',$capabilities))),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
    $st=$pdo->prepare("INSERT INTO sync_devices(device_id,device_name,app_version,platform,capabilities_json,last_seen_at,last_ip_hash,last_action,updated_at)
      VALUES(?,?,?,?,?,NOW(),?,?,NOW())
      ON DUPLICATE KEY UPDATE
        device_name=IF(VALUES(device_name) IS NULL OR VALUES(device_name)='',device_name,VALUES(device_name)),
        app_version=IF(VALUES(app_version) IS NULL OR VALUES(app_version)='',app_version,VALUES(app_version)),
        platform=IF(VALUES(platform) IS NULL OR VALUES(platform)='',platform,VALUES(platform)),
        capabilities_json=IF(VALUES(capabilities_json) IS NULL,capabilities_json,VALUES(capabilities_json)),
        last_seen_at=NOW(),last_ip_hash=VALUES(last_ip_hash),last_action=VALUES(last_action),updated_at=NOW()");
    $st->execute([$deviceId,$name!==''?$name:null,$version!==''?$version:null,$platform!==''?$platform:null,$caps,ts_sync_ip_hash(),substr($action,0,60)]);
}

function ts_sync_idempotency_load(PDO $pdo,string $requestId,string $action): ?array {
    if($requestId==='' || strlen($requestId)>128) return null;
    $st=$pdo->prepare('SELECT action_name,response_json FROM sync_idempotency WHERE request_id=? LIMIT 1');
    $st->execute([$requestId]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if(!$row) return null;
    if(!hash_equals((string)$row['action_name'],$action)) return ['ok'=>false,'error'=>'request_id_reused','code'=>'SYNC-704'];
    $d=json_decode((string)$row['response_json'],true);
    return is_array($d)?$d:null;
}

function ts_sync_idempotency_store(PDO $pdo,string $requestId,string $action,array $response): void {
    if($requestId==='' || strlen($requestId)>128) return;
    $json=json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json)) return;
    $st=$pdo->prepare('INSERT IGNORE INTO sync_idempotency(request_id,action_name,response_json,created_at) VALUES(?,?,?,NOW())');
    $st->execute([$requestId,$action,$json]);
    // Keep the replay table bounded without needing a cron job.
    if(random_int(1,100)===1) $pdo->exec("DELETE FROM sync_idempotency WHERE created_at < (NOW() - INTERVAL 14 DAY)");
}
