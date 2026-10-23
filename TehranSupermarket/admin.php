<?php
declare(strict_types=1);
session_start();date_default_timezone_set('Asia/Tehran');
if(!file_exists(__DIR__.'/config.php')){header('Location: install.php');exit;}
$c=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);}catch(Throwable $e){exit('اتصال پایگاه داده برقرار نیست.');}
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
if(isset($_POST['logout'])){session_destroy();header('Location: admin.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('درخواست نامعتبر');}
 if(($_POST['action']??'')==='login'){
  $st=$pdo->prepare('SELECT id,password_hash FROM admins WHERE username=?');$st->execute([trim($_POST['username']??'')]);$a=$st->fetch();
  if($a&&password_verify($_POST['password']??'',$a['password_hash'])){session_regenerate_id(true);$_SESSION['admin_id']=$a['id'];header('Location: admin.php');exit;}else{$err='نام کاربری یا رمز عبور نادرست است.';}
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
   }catch(Throwable $x){$err='ذخیره محصول انجام نشد: '.$x->getMessage();}
  }
 }
 if(admin()&&($_POST['action']??'')==='stock'){$id=(int)($_POST['id']??0);$price=max(0,(int)($_POST['price']??0));$stock=max(0,(int)($_POST['stock']??0));$st=$pdo->prepare('UPDATE products SET price=?,stock=?,updated_at=NOW() WHERE id=?');$st->execute([$price,$stock,$id]);$msg='قیمت و موجودی ذخیره شد.';}
 if(admin()&&($_POST['action']??'')==='status'){$id=(int)($_POST['id']??0);$status=$_POST['status']??'new';if(in_array($status,['new','confirmed','preparing','ready','out','delivered','cancelled'],true)){$st=$pdo->prepare('UPDATE orders SET status=? WHERE id=?');$st->execute([$status,$id]);$msg='وضعیت سفارش تغییر کرد.';}}
}
if(!admin()):
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود مدیر | سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><div class="logo big">🛒</div><h1>پنل مدیریت سوپرمارکت طهران</h1><p class="muted">ورود مدیر فروشگاه</p><?php if($err):?><div class="notice"><?=e($err)?></div><?php endif;?><form method="post" class="checkout"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="login"><label>نام کاربری<input name="username" required autocomplete="username"></label><label>رمز عبور<input type="password" name="password" required autocomplete="current-password"></label><button class="btn wide">ورود</button></form><a href="index.php">بازگشت به فروشگاه</a></main></body></html><?php exit; endif;
$products=$pdo->query('SELECT * FROM products ORDER BY id DESC LIMIT 500')->fetchAll();
$orders=$pdo->query('SELECT o.*,c.name customer_name,c.phone,c.address FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 200')->fetchAll();
$revenue=(int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled' AND payment_status='paid'")->fetchColumn();
$labels=['new'=>'جدید','confirmed'=>'تأیید شده','preparing'=>'در حال آماده‌سازی','ready'=>'آماده پیک','out'=>'در مسیر','delivered'=>'تحویل شده','cancelled'=>'لغو شده'];
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پنل مدیریت | سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css"></head><body><header class="top"><a class="brand" href="index.php"><span class="logo">🛒</span><span><b>مدیریت سوپرمارکت طهران</b><small>ثبت سریع با دوربین و انتخاب تصویر</small></span></a><form method="post"><input type="hidden" name="csrf" value="<?=e(token())?>"><button class="btn pale" name="logout" value="1">خروج</button></form></header><main><nav class="nav"><a href="index.php">مشاهده فروشگاه</a><a href="#add">📷 ثبت محصول</a><a href="#products-list">کالاها</a><a href="#orders">سفارش‌ها</a></nav><?php if($err):?><div class="notice"><?=e($err)?></div><?php endif;?><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><div class="stats"><div class="stat"><b><?=number_format(count($products))?></b><span>محصولات اخیر</span></div><div class="stat"><b><?=number_format(count($orders))?></b><span>سفارش‌های اخیر</span></div><div class="stat"><b><?=number_format($revenue)?></b><span>تومان فروش پرداخت‌شده</span></div></div>
<section id="add" class="panel product-entry"><div class="sectionhead"><div><h2>📷 اسکن و ثبت سریع محصول</h2><p>بارکد را بگیر، اسم ساده بنویس، عکس را انتخاب کن و قیمت را ثبت کن.</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn" id="scan">اسکن زنده</button><label for="barcode_photo" class="btn pale" style="cursor:pointer">📷 عکس بارکد</label><input type="file" id="barcode_photo" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none"></div></div>
<video id="video" playsinline></video><div id="scanmsg" class="scan-status">دو روش دارید: اسکن زنده یا عکس بارکد.</div>
<form method="post" enctype="multipart/form-data" class="product-form" id="productForm"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="product_id" id="product_id" value="0"><input type="hidden" name="selected_image_url" id="selected_image_url"><input type="hidden" name="selected_image_source" id="selected_image_source">
<label>بارکد کالا<div class="input-action"><input name="barcode" id="barcode" inputmode="numeric" autocomplete="off" placeholder="اسکن کنید یا دستی وارد کنید"><button class="btn pale" type="button" id="lookupBarcode">بررسی</button></div></label>
<label class="span2">اسم محصول برای جستجوی عکس<div class="input-action"><input name="name" id="name" required placeholder="مثلاً پفک، چیپس، شیر میهن..."><button class="btn image-search-btn" type="button" id="searchImages">🔎 نمایش عکس‌ها</button></div><small class="muted">لازم نیست اسم کامل باشد؛ مثلاً فقط «پفک» بنویسید.</small></label>
<div class="span2 image-picker"><div class="picker-head"><b>عکس محصول</b><span id="imageSearchStatus" class="muted">بعد از نوشتن نام، «نمایش عکس‌ها» را بزنید.</span></div><div id="selectedPreview" class="selected-preview"></div><div id="imageResults" class="image-results"></div><label class="upload-own">یا عکس خودتان را بگیرید/انتخاب کنید<input type="file" name="image_file" id="image_file" accept="image/*"></label></div>
<label>برند<input name="brand" id="brand" placeholder="مثلاً چی‌توز"></label><label>وزن / حجم<input name="quantity_label" id="quantity_label" placeholder="مثلاً ۶۰ گرم"></label>
<label>قیمت فروش (تومان)<input type="number" name="price" id="price" min="0" required inputmode="numeric"></label><label>موجودی<input type="number" name="stock" id="stock" min="0" required inputmode="numeric" value="1"></label>
<label class="span2">دسته‌بندی<select name="category" id="category"><?php foreach($cats as $cat):?><option><?=e($cat)?></option><?php endforeach;?></select></label>
<button class="btn wide span2 save-product" id="saveProduct">ثبت محصول</button><button type="button" class="btn pale wide span2" id="newProduct">پاک کردن فرم و ثبت کالای بعدی</button></form></section>
<section id="products-list" class="panel"><h2>محصولات اخیر</h2><div class="tablewrap"><table><thead><tr><th>عکس</th><th>نام</th><th>بارکد</th><th>دسته</th><th>قیمت / موجودی</th><th>ذخیره</th></tr></thead><tbody><?php foreach($products as $p):?><tr><td><?php if(!empty($p['image_path'])):?><img class="thumb" src="<?=e($p['image_path'])?>" alt=""><?php else:?><span class="no-thumb">🛒</span><?php endif;?></td><td><b><?=e($p['name'])?></b><?php if(!empty($p['brand'])):?><small class="muted block"><?=e($p['brand'])?> <?=e($p['quantity_label']??'')?></small><?php endif;?></td><td><?=e($p['barcode']??'—')?></td><td><?=e($p['category'])?></td><td colspan="2"><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="stock"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><input aria-label="قیمت" type="number" name="price" min="0" value="<?=(int)$p['price']?>"><input aria-label="موجودی" type="number" name="stock" min="0" value="<?=(int)$p['stock']?>"><button class="btn">ذخیره</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<section id="orders" class="panel"><h2>مدیریت سفارش‌ها</h2><div class="tablewrap"><table><thead><tr><th>شماره</th><th>مشتری</th><th>موبایل</th><th>آدرس</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت و ذخیره</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td>#<?=(int)$o['id']?></td><td><?=e($o['customer_name'])?></td><td><?=e($o['phone'])?></td><td><?=e($o['address'])?></td><td><?=number_format((int)$o['total'])?></td><td><?=e($o['payment_method']==='cod'?'در محل':'آنلاین')?><br><?=e($o['payment_status'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><select name="status"><?php foreach($labels as $k=>$v):?><option value="<?=e($k)?>" <?=$o['status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select><button class="btn">ذخیره</button></form></td></tr><?php endforeach;?></tbody></table></div></section></main>
<script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script><script src="assets/admin.js?v=20261024-camera-v5"></script></body></html>