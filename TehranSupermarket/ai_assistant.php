<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';
session_start();
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function ai_out(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function ai_fail(string $message, int $status=400, string $code='AI-400'): never {
    ai_out(['ok'=>false,'code'=>$code,'message'=>$message],$status);
}
function ai_norm(string $s): string {
    $s=mb_strtolower(trim($s),'UTF-8');
    $s=strtr($s,['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','‌'=>' ','ـ'=>' ']);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function ai_tokens(string $q): array {
    $stop=['میخوام','میخاهم','میخواهم','می خوام','می خواهم','می‌خوام','لطفا','لطفاً','دارید','داری','دارین','بده','بدهید','میخوامش','قیمت','موجودی','چنده','چند','هست','میخوام','میخام','از','رو','را','یه','یک','برای','من','لطفاً'];
    $n=ai_norm($q);
    $parts=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $out=[];
    foreach($parts as $p){
        if(mb_strlen($p)<2 || in_array($p,$stop,true)) continue;
        $out[$p]=true;
        if(count($out)>=6) break;
    }
    return array_keys($out);
}
function ai_img(?string $p): string {
    $p=trim((string)$p);
    if($p==='') return '';
    if(preg_match('~^https?://~i',$p)) return $p;
    return ltrim($p,'/');
}
function ai_rate_limit(): void {
    $now=time();
    $hits=$_SESSION['ai_hits']??[];
    $hits=array_values(array_filter(is_array($hits)?$hits:[],fn($t)=>(int)$t>$now-60));
    if(count($hits)>=90) ai_fail('درخواست‌ها خیلی سریع شده؛ چند لحظه بعد دوباره امتحان کنید.',429,'AI-429');
    $hits[]=$now; $_SESSION['ai_hits']=$hits;
}

$configFile=__DIR__.'/config.php';
if(!is_file($configFile)) ai_fail('فروشگاه هنوز آماده نیست.',503,'AI-503');
$c=require $configFile;
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    require_once __DIR__.'/schema.php'; ensure_product_schema($pdo);
    require_once __DIR__.'/store_lib.php';
}catch(Throwable $e){ ts_log_internal($e,'AI-501'); ai_fail('ارتباط با فروشگاه برقرار نشد.',503,'AI-501'); }

ai_rate_limit();
if(empty($_SESSION['ai_token'])) $_SESSION['ai_token']=bin2hex(random_bytes(24));
if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
$action=(string)($_REQUEST['action']??'boot');

function ai_cart(PDO $pdo): array {
    $cart=$_SESSION['cart']??[]; $items=[]; $total=0; $count=0;
    if(is_array($cart)&&$cart){
        $ids=array_values(array_filter(array_map('intval',array_keys($cart)),fn($x)=>$x>0));
        if($ids){
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $st=$pdo->prepare("SELECT id,name,brand,quantity_label,category,image_path,price,stock,active FROM products WHERE id IN ($ph)");
            $st->execute($ids);
            foreach($st->fetchAll() as $p){
                $qty=max(0,min((int)$p['stock'],50,(int)($cart[$p['id']]??0)));
                if(!$qty || !(int)$p['active'] || (int)$p['price']<1) continue;
                $line=(int)$p['price']*$qty; $total+=$line; $count+=$qty;
                $items[]=[
                    'id'=>(int)$p['id'],'name'=>$p['name'],'brand'=>$p['brand'],'quantity_label'=>$p['quantity_label'],
                    'category'=>$p['category'],'image'=>ai_img($p['image_path']),'price'=>(int)$p['price'],'stock'=>(int)$p['stock'],
                    'qty'=>$qty,'line_total'=>$line,
                ];
            }
        }
    }
    return ['items'=>$items,'count'=>$count,'total'=>$total];
}
function ai_require_token(): void {
    $token=(string)($_POST['token']??'');
    if($token==='' || !hash_equals((string)($_SESSION['ai_token']??''),$token)) ai_fail('نشست دستیار منقضی شده؛ صفحه را تازه کنید.',419,'AI-419');
}

if($action==='boot'){
    $state=store_open_state($pdo);
    $zones=active_delivery_zones($pdo);
    if(!$zones) $zones=[['id'=>0,'name'=>'محدوده اصلی','fee'=>(int)($c['delivery_fee']??0),'min_order'=>0,'eta'=>'اولین زمان ممکن']];
    $cart=ai_cart($pdo);
    ai_out(['ok'=>true,'token'=>$_SESSION['ai_token'],'csrf'=>$_SESSION['csrf'],'store'=>[
        'open'=>(bool)$state['open'],'message'=>(string)($state['message']??''),'open_time'=>(string)($state['open_time']??''),'close_time'=>(string)($state['close_time']??'')
    ],'zones'=>array_map(fn($z)=>['id'=>(int)$z['id'],'name'=>(string)$z['name'],'fee'=>(int)$z['fee'],'min_order'=>(int)$z['min_order'],'eta'=>(string)($z['eta']??'')],$zones),
    'prefill'=>['name'=>(string)($_SESSION['checkout_name']??''),'phone'=>(string)($_SESSION['checkout_phone']??''),'address'=>(string)($_SESSION['checkout_address']??'')],
    'age18'=>(bool)($_SESSION['ai_age18']??false),'cart'=>$cart]);
}

if($action==='search'){
    $q=trim((string)($_GET['q']??$_POST['q']??''));
    if(mb_strlen($q)<1 || mb_strlen($q)>120) ai_fail('اسم یا نوع کالایی که می‌خواهید را بنویسید.',422,'AI-422');
    $nq=ai_norm($q); $tokens=ai_tokens($q);
    if(!$tokens && $nq!=='') $tokens=[$nq];
    $categoryHints=[
        'شیر'=>'لبنیات و صبحانه','ماست'=>'لبنیات و صبحانه','پنیر'=>'لبنیات و صبحانه','کره'=>'لبنیات و صبحانه','خامه'=>'لبنیات و صبحانه','صبحانه'=>'لبنیات و صبحانه',
        'نوشابه'=>'نوشیدنی','آبمیوه'=>'نوشیدنی','دوغ'=>'نوشیدنی','آب معدنی'=>'نوشیدنی','انرژی زا'=>'نوشیدنی',
        'چیپس'=>'تنقلات و شیرینی','پفک'=>'تنقلات و شیرینی','شکلات'=>'تنقلات و شیرینی','بیسکویت'=>'تنقلات و شیرینی','کیک'=>'تنقلات و شیرینی','آدامس'=>'تنقلات و شیرینی',
        'برنج'=>'مواد غذایی','روغن'=>'مواد غذایی','ماکارونی'=>'مواد غذایی','قند'=>'مواد غذایی','شکر'=>'مواد غذایی','حبوبات'=>'مواد غذایی',
        'شامپو'=>'بهداشت شخصی','خمیردندان'=>'بهداشت شخصی','صابون'=>'بهداشت شخصی','پوشک'=>'کودک',
        'سیگار'=>'دخانیات','تنباکو'=>'دخانیات','ذغال'=>'دخانیات','زغال'=>'دخانیات','فندک'=>'دخانیات','کبریت'=>'دخانیات'
    ];
    $catHint=''; foreach($categoryHints as $k=>$v){if(str_contains($nq,ai_norm($k))){$catHint=$v;break;}}
    $where=[];$params=[];
    $rawTerms=array_slice(array_values(array_unique(array_filter(array_merge([$nq],$tokens)))),0,7);
    foreach($rawTerms as $term){
        $where[]='(name LIKE ? OR brand LIKE ? OR category LIKE ? OR quantity_label LIKE ?)';
        $like='%'.$term.'%'; array_push($params,$like,$like,$like,$like);
    }
    if($catHint!==''){ $where[]='category=?'; $params[]=$catHint; }
    if(!$where) ai_out(['ok'=>true,'query'=>$q,'items'=>[]]);
    $sql='SELECT id,name,brand,quantity_label,category,image_path,price,stock,active FROM products WHERE active=1 AND price>0 AND ('.implode(' OR ',$where).') LIMIT 80';
    $st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
    $ranked=[];
    foreach($rows as $p){
        $nn=ai_norm((string)$p['name']);$nb=ai_norm((string)$p['brand']);$nc=ai_norm((string)$p['category']);$score=((int)$p['stock']>0?12:-50);
        if($nq!==''&&str_contains($nn,$nq))$score+=120;
        if($nq!==''&&str_contains($nb,$nq))$score+=55;
        foreach($tokens as $t){if(str_contains($nn,$t))$score+=28;if(str_contains($nb,$t))$score+=12;if(str_contains($nc,$t))$score+=8;}
        if($catHint!==''&&(string)$p['category']===$catHint)$score+=35;
        $p['_score']=$score;$ranked[]=$p;
    }
    usort($ranked,fn($a,$b)=>($b['_score']<=>$a['_score']) ?: ((int)$b['stock']<=>(int)$a['stock']) ?: ((int)$b['id']<=>(int)$a['id']));
    $items=[];
    foreach(array_slice($ranked,0,12) as $p){$items[]=['id'=>(int)$p['id'],'name'=>(string)$p['name'],'brand'=>(string)$p['brand'],'quantity_label'=>(string)$p['quantity_label'],'category'=>(string)$p['category'],'image'=>ai_img($p['image_path']),'price'=>(int)$p['price'],'stock'=>(int)$p['stock']];}
    ai_out(['ok'=>true,'query'=>$q,'items'=>$items,'count'=>count($items)]);
}

if($action==='cart') ai_out(['ok'=>true,'cart'=>ai_cart($pdo)]);

if($action==='age_confirm'){
    ai_require_token();
    $_SESSION['ai_age18']=true;
    ai_out(['ok'=>true,'age18'=>true]);
}

if($action==='set_qty'){
    ai_require_token();
    $id=max(0,(int)($_POST['product_id']??0));$qty=max(0,min(50,(int)($_POST['qty']??0)));
    if($id<=0) ai_fail('کالا معتبر نیست.',422,'AI-422');
    $st=$pdo->prepare('SELECT id,name,category,stock,active,price FROM products WHERE id=? LIMIT 1');$st->execute([$id]);$p=$st->fetch();
    if(!$p || !(int)$p['active'] || (int)$p['price']<1) ai_fail('این کالا در حال حاضر قابل سفارش نیست.',404,'AI-404');
    if((string)$p['category']==='دخانیات' && empty($_SESSION['ai_age18'])) ai_out(['ok'=>false,'code'=>'AGE-18','age_required'=>true,'message'=>'برای سفارش دخانیات باید تأیید کنید ۱۸ سال یا بیشتر دارید.'],403);
    $qty=min($qty,(int)$p['stock']);
    if(!isset($_SESSION['cart'])||!is_array($_SESSION['cart']))$_SESSION['cart']=[];
    if($qty>0)$_SESSION['cart'][$id]=$qty;else unset($_SESSION['cart'][$id]);
    ai_out(['ok'=>true,'product'=>['id'=>$id,'name'=>(string)$p['name'],'qty'=>$qty,'stock'=>(int)$p['stock']],'cart'=>ai_cart($pdo)]);
}

ai_fail('درخواست ناشناخته است.',404,'AI-404');
