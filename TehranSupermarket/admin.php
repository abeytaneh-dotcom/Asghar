<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';
ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Strict');if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');session_start();date_default_timezone_set('Asia/Tehran');
if(!file_exists(__DIR__.'/config.php')){header('Location: install.php');exit;}
$c=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);require_once __DIR__.'/admin_auth.php';ts_admin_schema($pdo);require_once __DIR__.'/backup_lib.php';ts_schedule_auto_backup($pdo);}catch(Throwable $e){ts_log_internal($e,'SYS-101');ts_html_fail('SYS-101',503,'index.php');}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function token(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function admin(){return !empty($_SESSION['admin_id']);}
function public_ip_host(string $host): bool {
    if($host==='')return false;
    $ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
    return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false;
}
function store_image_bytes(string $bytes,string $hint='jpg'): ?string {
    if(strlen($bytes)<100 || strlen($bytes)>7*1024*1024)return null;
    $info=@getimagesizefromstring($bytes);if(!$info)return null;
    $mime=$info['mime']??'';$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;if(!$ext)return null;
    $dir=__DIR__.'/uploads/products';if(!is_dir($dir)&&!mkdir($dir,0755,true))return null;
    $name=date('Ymd').'_'.bin2hex(random_bytes(10)).'.'.$ext;
    if(file_put_contents($dir.'/'.$name,$bytes,LOCK_EX)===false)return null;
    @chmod($dir.'/'.$name,0644);return 'uploads/products/'.$name;
}
function store_uploaded_image(array $f): ?string {
    if(($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($f['error']??1)!==UPLOAD_ERR_OK || (int)($f['size']??0)>7*1024*1024)return null;
    $bytes=@file_get_contents((string)$f['tmp_name']);return $bytes===false?null:store_image_bytes($bytes,(string)($f['name']??''));
}
function store_remote_image(string $url): ?string {
    if(!filter_var($url,FILTER_VALIDATE_URL))return null;$p=parse_url($url);if(($p['scheme']??'')!=='https'||!public_ip_host((string)($p['host']??'')))return null;
    if(!function_exists('curl_init'))return null;
    $data='';$tooBig=false;$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; TehranSupermarket/1.0)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$data,&$tooBig){$data.=$chunk;if(strlen($data)>7*1024*1024){$tooBig=true;return 0;}return strlen($chunk);}]);
    $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($ok===false||$tooBig||$code<200||$code>=300)return null;return store_image_bytes($data);
}
$cats=['میوه و سبزی','لبنیات و صبحانه','نوشیدنی','تنقلات و شیرینی','خانه و شوینده','گوشت و مرغ','مواد غذایی','کنسرو و غذای آماده','بهداشت شخصی','کودک','سایر'];$err='';$msg='';
if(isset($_POST['logout'])){ts_audit($pdo,'logout');session_destroy();header('Location: admin.php');exit;}
$currentAdmin=null;if(admin()){$currentAdmin=ts_admin_current($pdo);if(!$currentAdmin){header('Location: admin.php');exit;}if(($currentAdmin['role']??'')==='staff'){if($_SERVER['REQUEST_METHOD']==='POST'){http_response_code(403);exit('دسترسی این حساب فقط به سفارش‌ها محدود است.');}header('Location: admin_orders.php');exit;}}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){ts_html_fail('REQ-102',419,'index.php');}
 if(($_POST['action']??'')==='login'){
  [$ok,$loginErr,$role]=ts_login($pdo,trim((string)($_POST['username']??'')),(string)($_POST['password']??''));
  if($ok){header('Location: '.($role==='staff'?'admin_orders.php':'admin.php'));exit;}else{$err=$loginErr;}
 }
 if(admin()&&($_POST['action']??'')==='save_product'){
  $id=max(0,(int)($_POST['product_id']??0));$name=trim($_POST['name']??'');$barcode=trim($_POST['barcode']??'');$brand=trim($_POST['brand']??'');$qty=trim($_POST['quantity_label']??'');
  $price=filter_var($_POST['price']??null,FILTER_VALIDATE_INT);$stock=filter_var($_POST['stock']??null,FILTER_VALIDATE_INT);$category=$_POST['category']??'سایر';$selected=trim($_POST['selected_image_url']??'');$source=trim($_POST['selected_image_source']??'');
  if($name===''||$price===false||$price<0||$stock===false||$stock<0||!in_array($category,$cats,true)){$err='نام، قیمت، موجودی و دسته‌بندی را درست وارد کنید.';}
  else {
   try{
    if($barcode!==''){$dup=$pdo->prepare('SELECT id FROM products WHERE barcode=? LIMIT 1');$dup->execute([$barcode]);$dupId=(int)($dup->fetchColumn()?:0);if($dupId>0)$id=$dupId;}
    $imagePath=null;
    if(isset($_FILES['image_file']))$imagePath=store_uploaded_image($_FILES['image_file']);
    if(!$imagePath&&$selected!==''){$imagePath=store_remote_image($selected);if(!$imagePath&&filter_var($selected,FILTER_VALIDATE_URL)&&str_starts_with($selected,'https://'))$imagePath=$selected;}
    if($id>0){
      $old=$pdo->prepare('SELECT image_path,image_source FROM products WHERE id=?');$old->execute([$id]);$oldRow=$old->fetch();if(!$oldRow)throw new RuntimeException('محصول پیدا نشد.');
      if(!$imagePath)$imagePath=$oldRow['image_path']??null;if($source==='')$source=(string)($oldRow['image_source']??'');
      $st=$pdo->prepare('UPDATE products SET name=?,barcode=?,brand=?,quantity_label=?,category=?,image_path=?,image_source=?,price=?,stock=?,active=1,updated_at=NOW() WHERE id=?');
      $st->execute([$name,$barcode!==''?$barcode:null,$brand!==''?$brand:null,$qty!==''?$qty:null,$category,$imagePath,$source!==''?$source:null,$price,$stock,$id]);$msg='محصول ویرایش و ذخیره شد.';
    }else{
      $st=$pdo->prepare('INSERT INTO products(name,barcode,brand,quantity_label,category,image_path,image_source,price,stock,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
      $st->execute([$name,$barcode!==''?$barcode:null,$brand!==''?$brand:null,$qty!==''?$qty:null,$category,$imagePath,$source!==''?$source:null,$price,$stock]);$msg='محصول جدید ثبت شد.';
    }
   }catch(Throwable $x){$err=ts_error_text('SYS-100');ts_log_internal($x,'SYS-100');}
  }
 }
 if(admin()&&($_POST['action']??'')==='stock'){$id=(int)($_POST['id']??0);$price=max(0,(int)($_POST['price']??0));$stock=max(0,(int)($_POST['stock']??0));$st=$pdo->prepare('UPDATE products SET price=?,stock=?,updated_at=NOW() WHERE id=? AND active=1');$st->execute([$price,$stock,$id]);$msg='قیمت و موجودی ذخیره شد.';}
 if(admin()&&($_POST['action']??'')==='delete_product'){
   $id=max(0,(int)($_POST['id']??0));
   if($id>0){$st=$pdo->prepare('UPDATE products SET active=0,stock=0,updated_at=NOW() WHERE id=?');$st->execute([$id]);}
   header('Location: admin.php?deleted=1#products-list');exit;
 }
 if(admin()&&($_POST['action']??'')==='status'){
 $id=(int)($_POST['id']??0);$status=$_POST['status']??'new';
 if(in_array($status,['new','confirmed','preparing','ready','out','delivered','cancelled'],true)){
  try{$pdo->beginTransaction();$q=$pdo->prepare('SELECT status,stock_restored_at FROM orders WHERE id=? FOR UPDATE');$q->execute([$id]);$o=$q->fetch();if(!$o)throw new RuntimeException('سفارش پیدا نشد.');
  if($status==='cancelled'&&$o['status']!=='cancelled'&&empty($o['stock_restored_at'])){$it=$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');$it->execute([$id]);$up=$pdo->prepare('UPDATE products SET stock=stock+?,updated_at=NOW() WHERE id=?');foreach($it->fetchAll() as $row)$up->execute([(int)$row['quantity'],(int)$row['product_id']]);$pdo->prepare('UPDATE orders SET status=?,stock_restored_at=NOW(),status_updated_at=NOW() WHERE id=?')->execute([$status,$id]);}
  elseif($o['status']==='cancelled'&&$status!=='cancelled')throw new RuntimeException('سفارش لغوشده را دوباره فعال نکنید.');
  else{$pdo->prepare('UPDATE orders SET status=?,status_updated_at=NOW() WHERE id=?')->execute([$status,$id]);}
  $pdo->commit();$msg='وضعیت سفارش تغییر کرد.';}catch(Throwable $x){if($pdo->inTransaction())$pdo->rollBack();$err=ts_error_text('SYS-100');ts_log_internal($x,'SYS-100');}
 }
}
}
if(!admin()):
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود مدیر | سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><div class="logo big">🛒</div><h1>پنل مدیریت سوپرمارکت طهران</h1><p class="muted">ورود مدیر فروشگاه</p><?php if($err):?><div class="notice"><?=e($err)?></div><?php endif;?><form method="post" class="checkout"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="login"><label>نام کاربری<input name="username" required autocomplete="username"></label><label>رمز عبور<input type="password" name="password" required autocomplete="current-password"></label><button class="btn wide">ورود</button></form><a href="index.php">بازگشت به فروشگاه</a></main></body></html><?php exit; endif;
if(isset($_GET['deleted']))$msg='محصول از لیست فعال فروشگاه حذف شد؛ سابقه سفارش‌های قبلی حفظ شده است.';
$products=$pdo->query('SELECT * FROM products WHERE active=1 ORDER BY id DESC LIMIT 500')->fetchAll();
$editProduct=null;
$editId=max(0,(int)($_GET['edit']??0));
if($editId>0){$st=$pdo->prepare('SELECT * FROM products WHERE id=? AND active=1 LIMIT 1');$st->execute([$editId]);$editProduct=$st->fetch()?:null;if(!$editProduct)$err='محصول موردنظر برای ویرایش پیدا نشد.';}
$orders=$pdo->query('SELECT o.*,c.name customer_name,c.phone,c.address FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 200')->fetchAll();
$orderItems=[];
if($orders){
  $orderIds=array_map(fn($o)=>(int)$o['id'],$orders);
  $ph=implode(',',array_fill(0,count($orderIds),'?'));
  $st=$pdo->prepare("SELECT order_id,product_id,product_name,unit_price,quantity FROM order_items WHERE order_id IN ($ph) ORDER BY id ASC");
  $st->execute($orderIds);
  foreach($st->fetchAll() as $it)$orderItems[(int)$it['order_id']][]=$it;
}
$revenue=(int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled' AND payment_status='paid'")->fetchColumn();
$labels=['new'=>'جدید','confirmed'=>'تأیید شده','preparing'=>'در حال آماده‌سازی','ready'=>'آماده پیک','out'=>'در مسیر','delivered'=>'تحویل شده','cancelled'=>'لغو شده'];
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پنل مدیریت | سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css?v=20261024-admin-v2"></head><body><header class="top"><a class="brand" href="index.php"><span class="logo">🛒</span><span><b>مدیریت سوپرمارکت طهران</b><small>ثبت سریع با دوربین و انتخاب تصویر</small></span></a><form method="post"><input type="hidden" name="csrf" value="<?=e(token())?>"><button class="btn pale" name="logout" value="1">خروج</button></form></header><main><nav class="nav"><a href="index.php">مشاهده فروشگاه</a><a href="#add">📷 ثبت محصول</a><a href="admin_products.php">📦 کالاها</a><a href="admin_catalog.php">🔎 مدیریت سریع کالاها</a><a href="admin_orders.php">🧺 جمع‌آوری سفارش</a><a href="admin_store.php">⚙️ فروشگاه و ارسال</a><a href="admin_discounts.php">💸 تخفیف‌ها</a><a href="admin_coupons.php">🎁 کد تخفیف</a><a href="admin_couriers.php">🛵 پیک‌ها</a><?php if(($currentAdmin['role']??'')==='owner'):?><a href="pyramid.php">🔄 پیرامید</a><a href="admin_integrations.php">🔌 اتصال سرویس‌ها</a><a href="admin_users.php">🔐 امنیت و کاربران</a><?php endif;?></nav><?php if($err):?><div class="notice"><?=e($err)?></div><?php endif;?><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><div class="stats"><div class="stat"><b><?=number_format(count($products))?></b><span>محصولات فعال اخیر</span></div><div class="stat"><b><?=number_format(count($orders))?></b><span>سفارش‌های اخیر</span></div><div class="stat"><b><?=number_format($revenue)?></b><span>تومان فروش پرداخت‌شده</span></div></div>
<section id="add" class="panel product-entry"><div class="sectionhead"><div><h2><?=$editProduct?'✏️ ویرایش محصول':'📷 اسکن و ثبت سریع محصول'?></h2><p><?=$editProduct?'اطلاعات محصول را تغییر بده و ذخیره کن.':'بارکد را بگیر، اسم ساده بنویس، عکس را انتخاب کن و قیمت را ثبت کن.'?></p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn" id="scan">اسکن زنده</button><label for="barcode_photo" class="btn pale" style="cursor:pointer">📷 عکس بارکد</label><input type="file" id="barcode_photo" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none"></div></div>
<?php if($editProduct):?><div class="edit-banner">در حال ویرایش: <b><?=e($editProduct['name'])?></b> <a href="admin.php#add">لغو ویرایش</a></div><?php endif;?>
<video id="video" playsinline></video><div id="scanmsg" class="scan-status">دو روش دارید: اسکن زنده یا عکس بارکد.</div>
<form method="post" enctype="multipart/form-data" class="product-form" id="productForm"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="product_id" id="product_id" value="<?=(int)($editProduct['id']??0)?>"><input type="hidden" name="selected_image_url" id="selected_image_url"><input type="hidden" name="selected_image_source" id="selected_image_source" value="<?=e($editProduct['image_source']??'')?>">
<label>بارکد کالا<div class="input-action"><input name="barcode" id="barcode" inputmode="numeric" autocomplete="off" placeholder="اسکن کنید یا دستی وارد کنید" value="<?=e($editProduct['barcode']??'')?>"><button class="btn pale" type="button" id="lookupBarcode">بررسی</button></div></label>
<label class="span2">اسم محصول برای جستجوی عکس<div class="input-action"><input name="name" id="name" required placeholder="مثلاً پفک، چیپس، شیر میهن..." value="<?=e($editProduct['name']??'')?>"><button class="btn image-search-btn" type="button" id="searchImages">🔎 نمایش عکس‌ها</button></div><small class="muted">لازم نیست اسم کامل باشد؛ مثلاً فقط «پفک» بنویسید.</small></label>
<div class="span2 image-picker"><div class="picker-head"><b>عکس محصول</b><span id="imageSearchStatus" class="muted">بعد از نوشتن نام، «نمایش عکس‌ها» را بزنید.</span></div><div id="selectedPreview" class="selected-preview"><?php if($editProduct&&!empty($editProduct['image_path'])):?><div class="chosen"><img src="<?=e($editProduct['image_path'])?>" alt=""><span>عکس فعلی محصول</span></div><?php endif;?></div><div id="imageResults" class="image-results"></div><label class="upload-own">یا عکس خودتان را بگیرید/انتخاب کنید<input type="file" name="image_file" id="image_file" accept="image/*"></label></div>
<label>برند<input name="brand" id="brand" placeholder="مثلاً چی‌توز" value="<?=e($editProduct['brand']??'')?>"></label><label>وزن / حجم<input name="quantity_label" id="quantity_label" placeholder="مثلاً ۶۰ گرم" value="<?=e($editProduct['quantity_label']??'')?>"></label>
<label>قیمت فروش (تومان)<input type="number" name="price" id="price" min="0" required inputmode="numeric" value="<?=isset($editProduct['price'])?(int)$editProduct['price']:''?>"></label><label>موجودی<input type="number" name="stock" id="stock" min="0" required inputmode="numeric" value="<?=isset($editProduct['stock'])?(int)$editProduct['stock']:1?>"></label>
<label class="span2">دسته‌بندی<select name="category" id="category"><?php foreach($cats as $cat):?><option <?=$editProduct&&$editProduct['category']===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach;?></select></label>
<button class="btn wide span2 save-product" id="saveProduct"><?=$editProduct?'ذخیره تغییرات محصول':'ثبت محصول'?></button><button type="button" class="btn pale wide span2" id="newProduct">پاک کردن فرم و ثبت کالای بعدی</button></form></section>
<section id="products-list" class="panel"><div class="sectionhead"><div><h2>محصولات فعال</h2><p>ویرایش، حذف، قیمت و موجودی از همین‌جا در دسترس است.</p></div></div><div class="tablewrap"><table><thead><tr><th>عکس</th><th>نام</th><th>بارکد</th><th>دسته</th><th>قیمت / موجودی</th><th>عملیات</th></tr></thead><tbody><?php foreach($products as $p):?><tr><td><?php if(!empty($p['image_path'])):?><img class="thumb" src="<?=e($p['image_path'])?>" alt=""><?php else:?><span class="no-thumb">🛒</span><?php endif;?></td><td><b><?=e($p['name'])?></b><?php if(!empty($p['brand'])||!empty($p['quantity_label'])):?><small class="muted block"><?=e(trim(($p['brand']??'').' '.($p['quantity_label']??'')))?></small><?php endif;?></td><td><?=e($p['barcode']??'—')?></td><td><?=e($p['category'])?></td><td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="stock"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><input aria-label="قیمت" type="number" name="price" min="0" value="<?=(int)$p['price']?>"><input aria-label="موجودی" type="number" name="stock" min="0" value="<?=(int)$p['stock']?>"><button class="btn">ذخیره</button></form></td><td><div class="product-actions"><a class="btn pale small-btn" href="admin.php?edit=<?=(int)$p['id']?>#add">✏️ ویرایش</a><form method="post" onsubmit="return confirm('این محصول از فروشگاه حذف شود؟ سابقه سفارش‌های قبلی حفظ می‌شود.');"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button class="btn danger small-btn" type="submit">🗑 حذف</button></form></div></td></tr><?php endforeach;?></tbody></table></div></section>
<section id="orders" class="panel"><div class="sectionhead"><div><h2>مدیریت سفارش‌ها</h2><p>ریز کالاهای هر سفارش، تعداد و قیمت در همین جدول نمایش داده می‌شود.</p></div></div><div class="tablewrap"><table class="orders-table"><thead><tr><th>شماره</th><th>مشتری</th><th>محصولات سفارش</th><th>موبایل</th><th>آدرس</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت و ذخیره</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><b>#<?=(int)$o['id']?></b><small class="muted block"><?=e($o['created_at']??'')?></small></td><td><?=e($o['customer_name'])?></td><td><div class="order-items"><?php $items=$orderItems[(int)$o['id']]??[]; if($items): foreach($items as $it):?><div class="order-item"><b><?=e($it['product_name'])?></b><span><?=number_format((int)$it['quantity'])?> عدد × <?=number_format((int)$it['unit_price'])?> تومان</span><strong><?=number_format((int)$it['quantity']*(int)$it['unit_price'])?> تومان</strong></div><?php endforeach; else:?><span class="muted">جزئیات کالا ثبت نشده است.</span><?php endif;?></div></td><td><?=e($o['phone'])?></td><td class="order-address"><?=e($o['address'])?></td><td><b><?=number_format((int)$o['total'])?> تومان</b><?php if(isset($o['delivery_fee'])):?><small class="muted block">ارسال: <?=number_format((int)$o['delivery_fee'])?> تومان</small><?php endif;?></td><td><?=e($o['payment_method']==='cod'?'در محل':'آنلاین')?><br><?=e($o['payment_status'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><select name="status"><?php foreach($labels as $k=>$v):?><option value="<?=e($k)?>" <?=$o['status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select><button class="btn status-save">ذخیره</button></form></td></tr><?php endforeach;?></tbody></table></div></section></main>
<script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script><script src="assets/admin.js?v=20261024-camera-v6"></script><script src="assets/order-alert.js?v=20261024"></script><script src="assets/admin_push.js?v=20261024-bgpush"></script></body></html>