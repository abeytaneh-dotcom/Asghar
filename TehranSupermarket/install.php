<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';
session_start();
date_default_timezone_set('Asia/Tehran');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$error='';$done=file_exists(__DIR__.'/config.php');
if($_SERVER['REQUEST_METHOD']==='POST'&&!$done){
 $host=trim($_POST['db_host']??'localhost');$db=trim($_POST['db_name']??'');$user=trim($_POST['db_user']??'');$pass=$_POST['db_pass']??'';
 $admin=trim($_POST['admin_user']??'admin');$apass=$_POST['admin_pass']??'';$adminHash=trim($_POST['admin_hash']??'');$fee=max(0,(int)($_POST['delivery_fee']??0));
 $validHash=(bool)preg_match('/^\$2[ayb]\$\d{2}\$[\.\/A-Za-z0-9]{53}$/',$adminHash);
 if(!preg_match('/^[a-zA-Z0-9_]+$/',$db)||!preg_match('/^[a-zA-Z0-9_]+$/',$user)||$admin===''||(!$validHash&&strlen($apass)<12)){
  $error='نام دیتابیس/کاربر معتبر باشد و رمز مدیر حداقل ۱۲ کاراکتر داشته باشد.';
 } else try {
  $pdo=new PDO('mysql:host='.$host.';dbname='.$db.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $sqls=[
   "CREATE TABLE IF NOT EXISTS admins(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(80) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
   "CREATE TABLE IF NOT EXISTS products(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,barcode VARCHAR(100) NULL UNIQUE,name VARCHAR(190) NOT NULL,brand VARCHAR(140) NULL,quantity_label VARCHAR(120) NULL,category VARCHAR(100) NOT NULL DEFAULT 'سایر',image_path VARCHAR(500) NULL,image_source VARCHAR(500) NULL,price BIGINT UNSIGNED NOT NULL DEFAULT 0,stock INT UNSIGNED NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NULL,INDEX(name),INDEX(category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
   "CREATE TABLE IF NOT EXISTS customers(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,phone VARCHAR(20) NOT NULL,address TEXT NOT NULL,created_at DATETIME NOT NULL,INDEX(phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
   "CREATE TABLE IF NOT EXISTS orders(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,customer_id BIGINT UNSIGNED NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'new',payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',delivery_slot VARCHAR(100) NULL,subtotal BIGINT UNSIGNED NOT NULL,delivery_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,total BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,INDEX(status,created_at),CONSTRAINT fk_order_customer FOREIGN KEY(customer_id) REFERENCES customers(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
   "CREATE TABLE IF NOT EXISTS order_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,product_name VARCHAR(190) NOT NULL,unit_price BIGINT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,CONSTRAINT fk_item_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,CONSTRAINT fk_item_product FOREIGN KEY(product_id) REFERENCES products(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  ];
  foreach($sqls as $sql)$pdo->exec($sql);
  $finalHash=$validHash?$adminHash:password_hash($apass,PASSWORD_DEFAULT);
  $st=$pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');
  $st->execute([$admin,$finalHash]);
  $config="<?php\nreturn ".var_export(['db_host'=>$host,'db_name'=>$db,'db_user'=>$user,'db_pass'=>$pass,'delivery_fee'=>$fee],true).";\n";
  if(file_put_contents(__DIR__.'/config.php',$config,LOCK_EX)===false)throw new RuntimeException('امکان ساخت config.php نیست؛ مجوز نوشتن پوشه را بررسی کنید.');
  @chmod(__DIR__.'/config.php',0600);
  file_put_contents(__DIR__.'/install.lock.php',"<?php http_response_code(403); exit('Installer disabled');",LOCK_EX);
  $done=true;
 } catch(Throwable $ex){$error=ts_error_text('SYS-100');ts_log_internal($ex,'SYS-100');}
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نصب سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><div class="logo big">🛒</div><h1>نصب سوپرمارکت طهران</h1><p class="muted">Tehran Supermarket · نصب‌کننده خودکار هاست cPanel</p><?php if($done):?><div class="success">پیکربندی اولیه انجام شده یا قبلاً نصب شده است.</div><a class="btn wide" href="index.php">ورود به فروشگاه</a><a class="btn pale wide" href="admin.php">ورود به پنل مدیریت</a><?php else:?><?php if($error):?><div class="notice"><?=$error?></div><?php endif;?><div class="notice">ابتدا در cPanel یک دیتابیس MySQL و کاربر بسازید و کاربر را با ALL PRIVILEGES به دیتابیس وصل کنید.</div><form method="post" class="checkout"><h3>اتصال پایگاه داده MySQL</h3><label>هاست دیتابیس<input name="db_host" value="<?=e($_POST['db_host']??'localhost')?>" required></label><label>نام دیتابیس کامل cPanel<input name="db_name" value="<?=e($_POST['db_name']??'')?>" placeholder="account_store" required></label><label>نام کاربر دیتابیس کامل<input name="db_user" value="<?=e($_POST['db_user']??'')?>" required></label><label>رمز کاربر دیتابیس<input type="password" name="db_pass" required></label><h3>ساخت حساب مدیر</h3><label>نام کاربری مدیر<input name="admin_user" value="<?=e($_POST['admin_user']??'admin')?>" required></label><label>رمز عبور مدیر (حداقل ۱۲ کاراکتر)<input type="password" name="admin_pass" minlength="12" required></label><label>هزینه ارسال (تومان)<input type="number" name="delivery_fee" min="0" value="<?=e($_POST['delivery_fee']??'0')?>" required></label><button class="btn wide">نصب و ساخت جدول‌ها</button></form><?php endif;?></main></body></html>