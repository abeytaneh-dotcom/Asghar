<?php
declare(strict_types=1);
ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Strict');if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');session_start();date_default_timezone_set('Asia/Tehran');
if(!file_exists(__DIR__.'/config.php')){header('Location: install.php');exit;}
$c=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);require_once __DIR__.'/admin_auth.php';ts_admin_schema($pdo);require_once __DIR__.'/backup_lib.php';ts_schedule_auto_backup($pdo);}catch(Throwable $e){exit('اتصال پایگاه داده برقرار نیست.');}
$currentAdmin=ts_require_roles($pdo,['owner']);
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function x($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');}
function sync_key(): string {
  $dir=__DIR__.'/storage';if(!is_dir($dir))@mkdir($dir,0755,true);
  $file=$dir.'/pyramid_sync_key.php';
  if(file_exists($file)){ $k=require $file; if(is_string($k)&&strlen($k)>=32)return $k; }
  $k=bin2hex(random_bytes(24));file_put_contents($file,"<?php return '".$k."';",LOCK_EX);@chmod($file,0600);return $k;
}
$key=sync_key();
$products=$pdo->query("SELECT id,barcode,pyramid_code2,pyramid_barcode_generated,name,brand,quantity_label,category,price,stock,updated_at FROM products WHERE active=1 ORDER BY id")->fetchAll();
if(isset($_GET['download'])&&$_GET['download']==='excel'){
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="tehran_pyramid_live.xls"');
  header('Cache-Control: no-store');
  echo '<?xml version="1.0" encoding="UTF-8"?>';
  echo '<?mso-application progid="Excel.Sheet"?>';
  echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
  echo '<Worksheet ss:Name="Pyramid_Kala"><Table>';
  echo '<Row><Cell><Data ss:Type="String">Barcode1</Data></Cell><Cell><Data ss:Type="String">Code2</Data></Cell><Cell><Data ss:Type="String">Name1</Data></Cell><Cell><Data ss:Type="String">Name2</Data></Cell></Row>';
  foreach($products as $p){$name2=trim(((string)($p['brand']??'')).' '.((string)($p['quantity_label']??'')));
    echo '<Row><Cell><Data ss:Type="String">'.x($p['barcode']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['pyramid_code2']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['name']).'</Data></Cell><Cell><Data ss:Type="String">'.x($name2).'</Data></Cell></Row>';
  }
  echo '</Table></Worksheet>';
  echo '<Worksheet ss:Name="Mojoodi_10"><Table><Row><Cell><Data ss:Type="String">Barcode1</Data></Cell><Cell><Data ss:Type="String">Name1</Data></Cell><Cell><Data ss:Type="String">InitialStock</Data></Cell></Row>';
  foreach($products as $p) echo '<Row><Cell><Data ss:Type="String">'.x($p['barcode']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['name']).'</Data></Cell><Cell><Data ss:Type="Number">'.(int)$p['stock'].'</Data></Cell></Row>';
  echo '</Table></Worksheet>';
  echo '<Worksheet ss:Name="Reference"><Table><Row><Cell><Data ss:Type="String">Barcode</Data></Cell><Cell><Data ss:Type="String">Code2</Data></Cell><Cell><Data ss:Type="String">Name</Data></Cell><Cell><Data ss:Type="String">Category</Data></Cell><Cell><Data ss:Type="String">Price_Toman</Data></Cell><Cell><Data ss:Type="String">Stock</Data></Cell></Row>';
  foreach($products as $p) echo '<Row><Cell><Data ss:Type="String">'.x($p['barcode']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['pyramid_code2']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['name']).'</Data></Cell><Cell><Data ss:Type="String">'.x($p['category']).'</Data></Cell><Cell><Data ss:Type="Number">'.(int)$p['price'].'</Data></Cell><Cell><Data ss:Type="Number">'.(int)$p['stock'].'</Data></Cell></Row>';
  echo '</Table></Worksheet></Workbook>';exit;
}
$total=count($products);$generated=0;$real=0;$stock10=0;foreach($products as $p){if((int)$p['pyramid_barcode_generated'])$generated++;else $real++;if((int)$p['stock']===10)$stock10++;}
$pending=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status<>'cancelled' AND pyramid_synced_at IS NULL")->fetchColumn();
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>اتصال پیرامید | سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css?v=20261024-pyramid"></head><body>
<header class="top"><a class="brand" href="admin.php"><span class="logo">🔄</span><span><b>اتصال حسابداری پیرامید</b><small>Pyramid ↔ Tehran Supermarket</small></span></a><a class="btn pale" href="admin.php">بازگشت به مدیریت</a></header>
<main>
<section class="panel"><div class="sectionhead"><div><h2>وضعیت تطبیق کالاها</h2><p>بارکد و Code2 سایت با فایل ورود پیرامید یکسان نگه داشته می‌شود.</p></div><span class="pill">آماده اتصال</span></div>
<div class="stats"><div class="stat"><b><?=number_format($total)?></b><span>کالای فعال و تطبیق‌شده</span></div><div class="stat"><b><?=number_format($real)?></b><span>بارکد واقعی</span></div><div class="stat"><b><?=number_format($generated)?></b><span>بارکد داخلی EAN-13</span></div></div>
<div class="stats"><div class="stat"><b><?=number_format($stock10)?></b><span>کالا با موجودی ۱۰</span></div><div class="stat"><b><?=number_format($pending)?></b><span>سفارش منتظر انتقال به پیرامید</span></div><div class="stat"><b>SQL Server</b><span>سمت حسابداری تأیید شده</span></div></div>
<a class="btn" href="pyramid.php?download=excel">⬇️ دانلود Excel زنده برای پیرامید</a>
<p class="muted">این فایل هر بار مستقیم از دیتابیس فعلی سایت ساخته می‌شود؛ بنابراین اگر کالا یا بارکد تغییر کند، فایل جدید هم همان تغییر را دارد.</p></section>
<section class="panel"><h2>آمادگی Sync خودکار</h2><p>سمت سایت آماده است. برنامه Tehran Sync روی کامپیوتر فروشگاه می‌تواند با این API قیمت، موجودی و سفارش‌ها را هماهنگ کند.</p><label>کلید اتصال این فروشگاه<input readonly value="<?=e($key)?>" onclick="this.select()"></label><p class="notice">این کلید را فقط داخل برنامه Sync روی کامپیوتر فروشگاه وارد کنید و عمومی منتشر نکنید. چون کامپیوتر حسابداری فعلاً اینترنت ندارد، انتقال آنلاین تا زمان اتصال آن کامپیوتر یا یک دستگاه واسط به اینترنت انجام نمی‌شود؛ فایل Excel بالا برای ورود اولیه آفلاین آماده است.</p></section>
<section class="panel"><div class="sectionhead"><div><h2>تطبیق فعلی محصولات</h2><p>شناسه‌های زیر مبنای مشترک سایت و پیرامید هستند.</p></div></div><div class="tablewrap"><table><thead><tr><th>کالا</th><th>Barcode1</th><th>Code2</th><th>نوع بارکد</th><th>موجودی</th><th>قیمت</th></tr></thead><tbody><?php foreach($products as $p):?><tr><td><?=e($p['name'])?></td><td><code><?=e($p['barcode'])?></code></td><td><?=e($p['pyramid_code2'])?></td><td><?=$p['pyramid_barcode_generated']?'داخلی':'واقعی'?></td><td><?=number_format((int)$p['stock'])?></td><td><?=number_format((int)$p['price'])?></td></tr><?php endforeach;?></tbody></table></div></section>
</main></body></html>