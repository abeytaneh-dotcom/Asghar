<?php
declare(strict_types=1);

/**
 * Atomic catalog snapshots from Pyramid -> website.
 * These helpers NEVER provision tables on HTTP requests.
 * No guessed barcode mappings are accepted.
 */

function ts_snapshot_validate_id(string $id): bool {
    return (bool)preg_match('/^[a-f0-9]{64}$/D',$id);
}

function ts_snapshot_provision_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pyramid_catalog_snapshots (
        snapshot_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        source_count INT UNSIGNED NULL,
        matched_count INT UNSIGNED NULL,
        site_count INT UNSIGNED NULL,
        INDEX(completed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pyramid_catalog_stage (
        snapshot_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        barcode VARCHAR(128) NOT NULL,
        code2 VARCHAR(128) NOT NULL,
        price BIGINT UNSIGNED NOT NULL,
        stock INT UNSIGNED NOT NULL,
        PRIMARY KEY(snapshot_id,barcode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ts_snapshot_batch(PDO $pdo,array $payload): array {
    $id=strtolower(trim((string)($payload['snapshot_id']??'')));
    $items=$payload['products']??null;
    if(!ts_snapshot_validate_id($id) || !is_array($items) || count($items)<1 || count($items)>250) {
        throw new InvalidArgumentException('invalid_snapshot_batch');
    }
    $seen=[];
    $validated=[];
    foreach($items as $item){
        if(!is_array($item)) throw new InvalidArgumentException('invalid_snapshot_line');
        $barcode=trim((string)($item['barcode']??''));
        $code=trim((string)($item['code2']??''));
        $raw=filter_var($item['stock']??null,FILTER_VALIDATE_INT);
        $price=price_to_toman($item['price']??null,(string)($item['price_unit']??'toman'));
        if($barcode==='' || strlen($barcode)>128 || strlen($code)>128 ||
           $raw===false || $raw<0 || $raw>100000000 || $price===null || $price<1 || $price>10000000000 ||
           isset($seen[$barcode])) {
            throw new InvalidArgumentException('invalid_snapshot_line');
        }
        $seen[$barcode]=true;
        $validated[]=[$id,$barcode,$code,$price,(int)$raw];
    }
    $pdo->beginTransaction();
    try{
        $pdo->prepare('INSERT IGNORE INTO pyramid_catalog_snapshots(snapshot_id) VALUES(?)')->execute([$id]);
        $st=$pdo->prepare('SELECT completed_at FROM pyramid_catalog_snapshots WHERE snapshot_id=? FOR UPDATE');
        $st->execute([$id]);
        $record=$st->fetch(PDO::FETCH_ASSOC);
        if(!$record || $record['completed_at']!==null) throw new LogicException('snapshot_already_finalized');
        $ins=$pdo->prepare('INSERT INTO pyramid_catalog_stage(snapshot_id,barcode,code2,price,stock)
         VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE
         code2=VALUES(code2),price=VALUES(price),stock=VALUES(stock)');
        foreach($validated as $row)$ins->execute($row);
        $pdo->commit();
        return ['ok'=>true,'snapshot_id'=>$id,'batch_count'=>count($validated)];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function ts_snapshot_finalize(PDO $pdo,array $payload): array {
    $id=strtolower(trim((string)($payload['snapshot_id']??'')));
    $expected=filter_var($payload['expected_rows']??null,FILTER_VALIDATE_INT);
    if(!ts_snapshot_validate_id($id) || $expected===false || $expected<9000 || $expected>100000){
        throw new InvalidArgumentException('invalid_snapshot_finalization');
    }
    $pdo->beginTransaction();
    try{
        $st=$pdo->prepare('SELECT completed_at,source_count,matched_count,site_count FROM pyramid_catalog_snapshots WHERE snapshot_id=? FOR UPDATE');
        $st->execute([$id]);
        $record=$st->fetch(PDO::FETCH_ASSOC);
        if(!$record)throw new LogicException('snapshot_missing');
        if($record['completed_at']!==null) {
            $pdo->commit();
            return ['ok'=>true,'snapshot_id'=>$id,'already_finalized'=>true,
                'source_count'=>(int)$record['source_count'],'matched_count'=>(int)$record['matched_count'],
                'site_count'=>(int)$record['site_count']];
        }
        $st=$pdo->prepare('SELECT COUNT(*) FROM pyramid_catalog_stage WHERE snapshot_id=?');
        $st->execute([$id]);$actual=(int)$st->fetchColumn();
        if($actual!==$expected)throw new LogicException('snapshot_incomplete');
        $site=(int)$pdo->query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn();
        $st=$pdo->prepare("SELECT COUNT(*)
          FROM products p JOIN pyramid_catalog_stage s
            ON s.snapshot_id=? AND s.barcode=p.barcode
           AND (COALESCE(p.pyramid_code2,'')='' OR p.pyramid_code2=s.code2)
          WHERE p.active=1");
        $st->execute([$id]);$matched=(int)$st->fetchColumn();
        $minMatch=max(9000,(int)floor($site*0.97));
        if($matched<$minMatch)throw new LogicException('snapshot_match_rate_too_low');
        // Never mutate price for unmatched records; make them unsellable until mapped.
        // Subtract website orders not yet acknowledged by POS.
        $update=$pdo->prepare("UPDATE products p
          LEFT JOIN pyramid_catalog_stage s
            ON s.snapshot_id=? AND s.barcode=p.barcode
           AND (COALESCE(p.pyramid_code2,'')='' OR p.pyramid_code2=s.code2)
          LEFT JOIN (
            SELECT oi.product_id, SUM(oi.quantity) pending_qty
            FROM order_items oi JOIN orders o ON o.id=oi.order_id
            WHERE o.pyramid_synced_at IS NULL AND o.status<>'cancelled'
            GROUP BY oi.product_id
          ) outstanding ON outstanding.product_id=p.id
          SET p.stock=CASE WHEN s.barcode IS NOT NULL
              THEN GREATEST(0,s.stock-COALESCE(outstanding.pending_qty,0))
              ELSE 0 END,
              p.price=CASE WHEN s.barcode IS NOT NULL THEN s.price ELSE p.price END,
              p.updated_at=NOW()
          WHERE p.active=1");
        $update->execute([$id]);
        $pdo->prepare('UPDATE pyramid_catalog_snapshots
          SET completed_at=NOW(), source_count=?,matched_count=?,site_count=?
          WHERE snapshot_id=?')->execute([$actual,$matched,$site,$id]);
        $pdo->commit();
        // Best-effort bounded storage cleanup; never affect the newly completed snapshot.
        try{
            $pdo->prepare('DELETE FROM pyramid_catalog_stage WHERE snapshot_id<>?')->execute([$id]);
            $pdo->prepare('DELETE FROM pyramid_catalog_snapshots WHERE snapshot_id<>? AND completed_at IS NOT NULL')->execute([$id]);
        }catch(Throwable $ignored){}
        return ['ok'=>true,'snapshot_id'=>$id,'source_count'=>$actual,'matched_count'=>$matched,
          'site_count'=>$site,'unmatched_site'=>max(0,$site-$matched),'committed'=>true];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function ts_snapshot_fresh(PDO $pdo,int $ttlSeconds=180): bool {
    $config=__DIR__.'/storage/pyramid_bridge_writes.php';
    if(!is_file($config))return true;
    $options=require $config;
    if(!is_array($options) || ($options['enforce_freshness']??false)!==true)return true;
    try {
        $latest=$pdo->query('SELECT MAX(completed_at) FROM pyramid_catalog_snapshots')->fetchColumn();
        if(!$latest)return false;
        return (time()-strtotime((string)$latest))<=max(30,$ttlSeconds);
    }catch(Throwable $e){return false;}
}
