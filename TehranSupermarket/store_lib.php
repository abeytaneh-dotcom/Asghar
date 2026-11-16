<?php
declare(strict_types=1);

function store_setting(PDO $pdo,string $key,string $default=''): string {
    static $cache=[];
    if(array_key_exists($key,$cache)) return $cache[$key];
    $st=$pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=? LIMIT 1');
    $st->execute([$key]);
    $v=$st->fetchColumn();
    return $cache[$key]=$v===false?$default:(string)$v;
}

function set_store_setting(PDO $pdo,string $key,string $value): void {
    $st=$pdo->prepare('INSERT INTO store_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
    $st->execute([$key,$value]);
}

function store_open_state(PDO $pdo): array {
    $mode=store_setting($pdo,'manual_mode','auto');
    $open=store_setting($pdo,'open_time','08:00');
    $close=store_setting($pdo,'close_time','23:00');
    $message=store_setting($pdo,'closed_message','سفارش‌گیری فعلاً بسته است.');
    $now=date('H:i');
    $isOpen=true;
    if($mode==='closed') $isOpen=false;
    elseif($mode==='open') $isOpen=true;
    else {
        if($open===$close) $isOpen=true;
        elseif($open<$close) $isOpen=($now>=$open&&$now<$close);
        else $isOpen=($now>=$open||$now<$close);
    }
    return ['open'=>$isOpen,'mode'=>$mode,'open_time'=>$open,'close_time'=>$close,'message'=>$message];
}

function active_delivery_zones(PDO $pdo): array {
    return $pdo->query('SELECT id,name,fee,min_order,eta FROM delivery_zones WHERE active=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
}

function delivery_zone(PDO $pdo,int $id): ?array {
    $st=$pdo->prepare('SELECT id,name,fee,min_order,eta FROM delivery_zones WHERE id=? AND active=1 LIMIT 1');
    $st->execute([$id]);
    $r=$st->fetch(PDO::FETCH_ASSOC);
    return $r?:null;
}

/**
 * Close checkout on a stale ERP snapshot only when a protected catalog-sync
 * switch is explicitly enabled. Ordinary legacy checkout stays unchanged.
 */
function ts_bridge_checkout_fresh(PDO $pdo): bool {
    $switch=__DIR__.'/storage/pyramid_bridge_writes.php';
    if(!is_file($switch)) return true;
    $flags=require $switch;
    if(!is_array($flags) || ($flags['catalog']??false)!==true) return true;
    try {
        $st=$pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=? LIMIT 1');
        $st->execute(['pyramid_bridge_last_cycle_utc']);
        $value=$st->fetchColumn();
        if(!is_string($value) || $value==='') return false;
        $moment=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if($moment===false) return false;
        $age=time()-$moment->getTimestamp();
        return $age>=0 && $age<=180;
    } catch(Throwable $error) {
        return false; // fail closed if the protected POS sync was enabled
    }
}
