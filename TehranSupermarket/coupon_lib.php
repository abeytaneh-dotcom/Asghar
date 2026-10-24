<?php
declare(strict_types=1);
function ts_coupon_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons(
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(40) NOT NULL UNIQUE,
      type VARCHAR(20) NOT NULL DEFAULT 'percent',
      value BIGINT UNSIGNED NOT NULL DEFAULT 0,
      min_order BIGINT UNSIGNED NOT NULL DEFAULT 0,
      max_discount BIGINT UNSIGNED NOT NULL DEFAULT 0,
      usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
      used_count INT UNSIGNED NOT NULL DEFAULT 0,
      active TINYINT(1) NOT NULL DEFAULT 1,
      starts_at DATETIME NULL,
      ends_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $cols=[];foreach($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $r)$cols[$r['Field']]=true;$a=[];
    if(!isset($cols['coupon_code']))$a[]="ADD COLUMN coupon_code VARCHAR(40) NULL";
    if(!isset($cols['discount_total']))$a[]="ADD COLUMN discount_total BIGINT UNSIGNED NOT NULL DEFAULT 0";
    if($a)$pdo->exec('ALTER TABLE orders '.implode(', ',$a));
}
function ts_coupon_apply(PDO $pdo,string $code,int $subtotal,int $deliveryFee,bool $lock=false): array {
    $code=mb_strtoupper(trim($code));if($code==='')return ['ok'=>true,'code'=>'','discount'=>0,'delivery_fee'=>$deliveryFee,'coupon_id'=>0];
    $sql="SELECT * FROM coupons WHERE code=? AND active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1".($lock?' FOR UPDATE':'');
    $st=$pdo->prepare($sql);$st->execute([$code]);$c=$st->fetch();if(!$c)return ['ok'=>false,'error'=>'کد تخفیف معتبر نیست.'];
    if((int)$c['usage_limit']>0&&(int)$c['used_count']>=(int)$c['usage_limit'])return ['ok'=>false,'error'=>'ظرفیت استفاده از این کد تمام شده است.'];
    if($subtotal<(int)$c['min_order'])return ['ok'=>false,'error'=>'حداقل خرید برای این کد '.number_format((int)$c['min_order']).' تومان است.'];
    $discount=0;$fee=$deliveryFee;$type=(string)$c['type'];
    if($type==='percent'){$discount=(int)floor($subtotal*min(100,(int)$c['value'])/100);if((int)$c['max_discount']>0)$discount=min($discount,(int)$c['max_discount']);}
    elseif($type==='fixed')$discount=min($subtotal,(int)$c['value']);
    elseif($type==='free_shipping')$fee=0;
    else return ['ok'=>false,'error'=>'نوع کد تخفیف نامعتبر است.'];
    return ['ok'=>true,'code'=>$code,'discount'=>$discount,'delivery_fee'=>$fee,'coupon_id'=>(int)$c['id']];
}
function ts_coupon_use(PDO $pdo,int $couponId): void {if($couponId>0)$pdo->prepare('UPDATE coupons SET used_count=used_count+1,updated_at=NOW() WHERE id=?')->execute([$couponId]);}
