<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Tehran');

$configFile = __DIR__ . '/config.php';
$installed = file_exists($configFile);
if (!$installed) {
    header('Location: install.php');
    exit;
}
$config = require $configFile;
try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
} catch (Throwable $e) {
    http_response_code(500); exit('اتصال پایگاه داده برقرار نشد. تنظیمات config.php را بررسی کنید.');
}
require_once __DIR__.'/schema.php';
ensure_product_schema($pdo);
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function toman(int $n): string { return number_format($n) . ' تومان'; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'); } }
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
$categories = ['میوه و سبزی','لبنیات و صبحانه','نوشیدنی','تنقلات و شیرینی','خانه و شوینده','گوشت و مرغ','مواد غذایی','کنسرو و غذای آماده','بهداشت شخصی','کودک','سایر'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action==='add_cart') {
        $id=(int)($_POST['product_id']??0); $qty=max(1,min(50,(int)($_POST['qty']??1)));
        $st=$pdo->prepare('SELECT id,stock,active FROM products WHERE id=?');$st->execute([$id]);$p=$st->fetch();
        if ($p && $p['active']) { $_SESSION['cart'][$id]=min((int)$p['stock'],(int)($_SESSION['cart'][$id]??0)+$qty); }
        header('Location: index.php#products'); exit;
    }
    if ($action==='remove_cart') { $id=(int)($_POST['product_id']??0); unset($_SESSION['cart'][$id]); header('Location: index.php#cart');exit; }
    if ($action==='checkout') {
        $name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$address=trim($_POST['address']??'');$slot=trim($_POST['slot']??'اولین زمان ممکن');$payment=$_POST['payment']??'cod';
        if (mb_strlen($name)<2 || !preg_match('/^09\d{9}$/',$phone) || mb_strlen($address)<5) { $_SESSION['flash']='نام، شماره موبایل و آدرس را درست وارد کنید.'; header('Location: index.php#checkout');exit; }
        if (!in_array($payment,['cod','online'],true)) $payment='cod';
        $cart=$_SESSION['cart']??[];
        if (!$cart) { $_SESSION['flash']='سبد خرید شما خالی است.';header('Location: index.php#products');exit; }
        try {
            $pdo->beginTransaction(); $items=[]; $subtotal=0;
            foreach($cart as $id=>$qty){
                $st=$pdo->prepare('SELECT id,name,price,stock,active FROM products WHERE id=? FOR UPDATE');$st->execute([(int)$id]);$p=$st->fetch();
                if(!$p || !$p['active'] || (int)$p['stock']<(int)$qty) throw new RuntimeException('موجودی یکی از کالاها کافی نیست؛ سبد خرید را بررسی کنید.');
                $items[]=['id'=>(int)$p['id'],'name'=>$p['name'],'price'=>(int)$p['price'],'qty'=>(int)$qty];$subtotal+=(int)$p['price']*(int)$qty;
            }
            $st=$pdo->prepare('INSERT INTO customers(name,phone,address,created_at) VALUES(?,?,?,NOW())');$st->execute([$name,$phone,$address]);$customerId=(int)$pdo->lastInsertId();
            $st=$pdo->prepare('INSERT INTO orders(customer_id,status,payment_method,payment_status,delivery_slot,subtotal,delivery_fee,total,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');
            $fee=(int)$config['delivery_fee'];$st->execute([$customerId,'new',$payment,'unpaid',$slot,$subtotal,$fee,$subtotal+$fee]);$orderId=(int)$pdo->lastInsertId();
            $ins=$pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity) VALUES(?,?,?,?,?)');
            $upd=$pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?');
            foreach($items as $it){$ins->execute([$orderId,$it['id'],$it['name'],$it['price'],$it['qty']]);$upd->execute([$it['qty'],$it['id']]);}
            $pdo->commit();unset($_SESSION['cart']);$_SESSION['flash']='سفارش شما با شماره '.$orderId.' ثبت شد. برای پیگیری با فروشگاه تماس بگیرید.';header('Location: index.php#products');exit;
        } catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['flash']=$ex instanceof RuntimeException?$ex->getMessage():'ثبت سفارش انجام نشد. لطفاً دوباره تلاش کنید.';header('Location: index.php#checkout');exit;}
    }
}
$search=trim($_GET['q']??'');$cat=trim($_GET['cat']??'');
$sql='SELECT * FROM products WHERE active=1';$params=[];
if($search!==''){$sql.=' AND (name LIKE ? OR barcode LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
if($cat!==''){$sql.=' AND category=?';$params[]=$cat;}
$sql.=' ORDER BY id DESC LIMIT 120';$st=$pdo->prepare($sql);$st->execute($params);$products=$st->fetchAll();
$cart=$_SESSION['cart']??[];$cartRows=[];$cartTotal=0;
if($cart){$ids=array_map('intval',array_keys($cart));$in=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT id,name,price,stock FROM products WHERE id IN ($in)");$st->execute($ids);foreach($st->fetchAll() as $p){$q=min((int)$p['stock'],(int)$cart[$p['id']]);$cartRows[]=$p+['qty'=>$q];$cartTotal+=(int)$p['price']*$q;}}
$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#11823b"><title>سوپرمارکت طهران</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="top"><a class="brand" href="index.php"><span class="logo">🛒</span><span><b>سوپرمارکت طهران</b><small>Tehran Supermarket · تازه، سالم، همیشه در دسترس</small></span></a><span class="location">📍 بهارستان اصفهان</span></header>
<section class="hero"><div><span class="pill">خرید آسان محلی</span><h1>خرید روزانه،<br><em>سریع و راحت</em></h1><p>محصولات موردنیازت رو انتخاب کن؛ ما در بهارستان برات آماده می‌کنیم.</p><a class="btn" href="#products">شروع خرید ←</a></div><div class="heroart">🥬 🥛 🍎</div></section>
<main>
<?php if($flash): ?><div class="notice"><?=e($flash)?></div><?php endif; ?>
<section id="products"><div class="sectionhead"><div><h2>محصولات فروشگاه</h2><p>محصولت رو جستجو کن و به سبد اضافه کن.</p></div><a class="btn pale" href="admin.php">پنل مدیریت</a></div>
<form class="search" method="get"><input name="q" value="<?=e($search)?>" placeholder="🔎 جستجوی نام یا بارکد محصول"><select name="cat"><option value="">همه دسته‌ها</option><?php foreach($categories as $c): ?><option value="<?=e($c)?>" <?=$cat===$c?'selected':''?>><?=e($c)?></option><?php endforeach; ?></select><button class="btn">جستجو</button></form>
<div class="grid"><?php foreach($products as $p): ?><article class="card"><?php if(!empty($p['image_path'])):?><div class="product-photo"><img loading="lazy" src="<?=e($p['image_path'])?>" alt="<?=e($p['name'])?>"></div><?php else:?><div class="emoji"><?=['میوه و سبزی'=>'🥬','لبنیات و صبحانه'=>'🥛','نوشیدنی'=>'🧃','تنقلات و شیرینی'=>'🍪','خانه و شوینده'=>'🧼','گوشت و مرغ'=>'🍗','مواد غذایی'=>'🛍️','کنسرو و غذای آماده'=>'🥫','بهداشت شخصی'=>'🧴','کودک'=>'🍼','سایر'=>'🛒'][$p['category']]??'🛒'?></div><?php endif;?><h3><?=e($p['name'])?></h3><?php if(!empty($p['brand'])||!empty($p['quantity_label'])):?><p class="product-meta"><?=e(trim(($p['brand']??'').' '.($p['quantity_label']??'')))?></p><?php endif;?><p class="muted"><?=e($p['category'])?> · موجودی <?=number_format((int)$p['stock'])?></p><div class="price-row"><b><?=toman((int)$p['price'])?></b><?php if((int)$p['stock']>0): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><button class="add">+ افزودن</button></form><?php else: ?><span class="muted">ناموجود</span><?php endif; ?></div></article><?php endforeach; if(!$products): ?><p class="notice">محصولی پیدا نشد. مدیر فروشگاه می‌تواند محصولات را از پنل مدیریت ثبت کند.</p><?php endif; ?></div></section>
<section id="cart" class="cartbox"><div class="sectionhead"><div><h2>سبد خرید</h2><p><?=number_format(count($cartRows))?> نوع کالا</p></div><strong><?=toman($cartTotal)?></strong></div>
<?php if($cartRows): foreach($cartRows as $r): ?><div class="cartline"><span><?=e($r['name'])?> × <?=number_format((int)$r['qty'])?></span><span><?=toman((int)$r['price']*(int)$r['qty'])?></span><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="remove_cart"><input type="hidden" name="product_id" value="<?=(int)$r['id']?>"><button class="remove">حذف</button></form></div><?php endforeach; ?>
<h3 id="checkout">اطلاعات تحویل</h3><form method="post" class="checkout"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="checkout"><label>نام و نام خانوادگی<input name="name" required minlength="2" placeholder="نام شما"></label><label>شماره موبایل<input name="phone" required pattern="09[0-9]{9}" placeholder="09123456789"></label><label class="wide">آدرس دقیق تحویل<textarea name="address" required minlength="5" placeholder="شهر، خیابان، کوچه، پلاک"></textarea></label><label>زمان تحویل<select name="slot"><option>اولین زمان ممکن</option><option>۱۰ تا ۱۲</option><option>۱۲ تا ۱۴</option><option>۱۶ تا ۱۸</option><option>۱۸ تا ۲۰</option><option>۲۰ تا ۲۲</option></select></label><label>روش پرداخت<select name="payment"><option value="cod">پرداخت در محل</option><option value="online">پرداخت آنلاین (تا اتصال درگاه فعال نیست)</option></select></label><p class="muted wide">هزینه ارسال: <?=toman((int)$config['delivery_fee'])?> · خرید بدون ساخت حساب کاربری</p><button class="btn wide" type="submit">ثبت سفارش · <?=toman($cartTotal+(int)$config['delivery_fee'])?></button></form>
<?php else: ?><p class="muted">سبد خرید خالی است؛ از محصولات بالا کالا اضافه کنید.</p><?php endif; ?></section></main><footer>سوپرمارکت طهران · بهارستان اصفهان <a href="admin.php">مدیریت فروشگاه</a></footer></body></html>