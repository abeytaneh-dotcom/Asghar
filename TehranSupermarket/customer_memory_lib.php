<?php
declare(strict_types=1);

function tm_memory_schema(PDO $pdo): void {
    static $done=false;if($done)return;$done=true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_saved_profiles (
      device_hash CHAR(64) NOT NULL PRIMARY KEY,
      customer_id INT UNSIGNED NULL,
      last_order_id BIGINT UNSIGNED NULL,
      full_name VARCHAR(190) NOT NULL DEFAULT '',
      phone VARCHAR(20) NOT NULL DEFAULT '',
      delivery_address TEXT NULL,
      delivery_zone_id INT UNSIGNED NULL,
      delivery_zone_name VARCHAR(120) NULL,
      delivery_lat DECIMAL(10,7) NULL,
      delivery_lng DECIMAL(10,7) NULL,
      delivery_accuracy INT UNSIGNED NULL,
      last_fulfillment_method VARCHAR(20) NOT NULL DEFAULT 'delivery',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      KEY idx_saved_profiles_phone(phone,updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function tm_memory_hash_token(string $token): string {
    $token=trim($token);
    if(!preg_match('/^[A-Za-z0-9_-]{32,128}$/',$token))return '';
    return hash('sha256',$token);
}
function tm_memory_profile(PDO $pdo,string $token): ?array {
    tm_memory_schema($pdo);$h=tm_memory_hash_token($token);if($h==='')return null;
    $st=$pdo->prepare('SELECT full_name,phone,delivery_address,delivery_zone_id,delivery_zone_name,delivery_lat,delivery_lng,delivery_accuracy,last_fulfillment_method FROM customer_saved_profiles WHERE device_hash=? LIMIT 1');
    $st->execute([$h]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
    return [
      'name'=>(string)$r['full_name'],'phone'=>(string)$r['phone'],'address'=>(string)($r['delivery_address']??''),
      'zone_id'=>(int)($r['delivery_zone_id']??0),'zone_name'=>(string)($r['delivery_zone_name']??''),
      'lat'=>$r['delivery_lat']===null?'':(string)$r['delivery_lat'],'lng'=>$r['delivery_lng']===null?'':(string)$r['delivery_lng'],
      'accuracy'=>$r['delivery_accuracy']===null?'':(string)$r['delivery_accuracy'],
      'fulfillment_method'=>in_array((string)$r['last_fulfillment_method'],['pickup','delivery'],true)?(string)$r['last_fulfillment_method']:'delivery'
    ];
}
function tm_memory_forget(PDO $pdo,string $token): void {
    tm_memory_schema($pdo);$h=tm_memory_hash_token($token);if($h==='')return;
    $pdo->prepare('DELETE FROM customer_saved_profiles WHERE device_hash=?')->execute([$h]);
}
function tm_memory_save_order(PDO $pdo,int $orderId,array $post): void {
    if($orderId<1||empty($post['remember_customer']))return;
    $token=(string)($post['device_token']??'');$h=tm_memory_hash_token($token);if($h==='')return;
    tm_memory_schema($pdo);
    $name=mb_substr(trim((string)($post['name']??'')),0,190,'UTF-8');$phone=trim((string)($post['phone']??''));
    if(!preg_match('/^09\d{9}$/',$phone))return;
    $method=in_array((string)($post['fulfillment_method']??'delivery'),['pickup','delivery'],true)?(string)$post['fulfillment_method']:'delivery';
    $st=$pdo->prepare('SELECT customer_id,delivery_zone_name FROM orders WHERE id=? LIMIT 1');$st->execute([$orderId]);$ord=$st->fetch(PDO::FETCH_ASSOC);if(!$ord)return;
    $cid=(int)($ord['customer_id']??0);
    $oldSt=$pdo->prepare('SELECT delivery_address,delivery_zone_id,delivery_zone_name,delivery_lat,delivery_lng,delivery_accuracy FROM customer_saved_profiles WHERE device_hash=? LIMIT 1');$oldSt->execute([$h]);$old=$oldSt->fetch(PDO::FETCH_ASSOC)?:[];
    if($method==='delivery'){
        $address=mb_substr(trim((string)($post['address']??'')),0,2000,'UTF-8');$zoneId=max(0,(int)($post['zone_id']??0));$zoneName=(string)($ord['delivery_zone_name']??'');
        $latRaw=trim((string)($post['delivery_lat']??''));$lngRaw=trim((string)($post['delivery_lng']??''));$lat=$latRaw===''?null:filter_var($latRaw,FILTER_VALIDATE_FLOAT);$lng=$lngRaw===''?null:filter_var($lngRaw,FILTER_VALIDATE_FLOAT);
        if($lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){$lat=null;$lng=null;}$acc=($lat!==null&&$lng!==null)?max(0,min(100000,(int)($post['delivery_accuracy']??0))):null;
    }else{
        $address=(string)($old['delivery_address']??'');$zoneId=(int)($old['delivery_zone_id']??0);$zoneName=(string)($old['delivery_zone_name']??'');$lat=$old['delivery_lat']??null;$lng=$old['delivery_lng']??null;$acc=$old['delivery_accuracy']??null;
    }
    $sql="INSERT INTO customer_saved_profiles(device_hash,customer_id,last_order_id,full_name,phone,delivery_address,delivery_zone_id,delivery_zone_name,delivery_lat,delivery_lng,delivery_accuracy,last_fulfillment_method,created_at,updated_at)
          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
          ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id),last_order_id=VALUES(last_order_id),full_name=VALUES(full_name),phone=VALUES(phone),delivery_address=VALUES(delivery_address),delivery_zone_id=VALUES(delivery_zone_id),delivery_zone_name=VALUES(delivery_zone_name),delivery_lat=VALUES(delivery_lat),delivery_lng=VALUES(delivery_lng),delivery_accuracy=VALUES(delivery_accuracy),last_fulfillment_method=VALUES(last_fulfillment_method),updated_at=NOW()";
    $pdo->prepare($sql)->execute([$h,$cid?:null,$orderId,$name,$phone,$address?:null,$zoneId?:null,$zoneName?:null,$lat,$lng,$acc,$method]);
}
