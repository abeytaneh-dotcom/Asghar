<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';
session_start();
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function fs_out(array $d,int $status=200): never { http_response_code($status); echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fs_norm(string $s): string {
    $s=mb_strtolower(trim($s),'UTF-8');
    $s=strtr($s,['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','‌'=>' ','ـ'=>' ']);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    $s=trim(preg_replace('/\s+/u',' ',$s)??$s);
    $s=preg_replace('/\bذغال\b/u','زغال',$s)??$s;
    $s=preg_replace('/آب\s*میوه/u','آبمیوه',$s)??$s;
    return trim($s);
}
function fs_tokens(string $s): array {
    $stop=['میخوام','میخاهم','میخواهم','می خوام','می خواهم','می‌خوام','لطفا','لطفاً','دارید','داری','دارین','بده','بدهید','میخوامش','قیمت','موجودی','چنده','چند','هست','میخام','از','رو','را','یه','یک','برای','من','لطفاً','لطفا','چی','می'];
    $p=preg_split('/\s+/u',fs_norm($s),-1,PREG_SPLIT_NO_EMPTY)?:[];$o=[];
    foreach($p as $w){ if(mb_strlen($w,'UTF-8')<2||in_array($w,$stop,true))continue; $o[$w]=true; if(count($o)>=7)break; }
    return array_keys($o);
}
function fs_chars(string $s): array { return preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[]; }
function fs_dist(string $a,string $b): int {
    $x=fs_chars($a);$y=fs_chars($b);$n=count($x);$m=count($y);
    if(!$n)return $m;if(!$m)return $n;
    $d=array_fill(0,$n+1,array_fill(0,$m+1,0));
    for($i=0;$i<=$n;$i++)$d[$i][0]=$i;for($j=0;$j<=$m;$j++)$d[0][$j]=$j;
    for($i=1;$i<=$n;$i++){
        for($j=1;$j<=$m;$j++){
            $cost=$x[$i-1]===$y[$j-1]?0:1;
            $v=min($d[$i-1][$j]+1,$d[$i][$j-1]+1,$d[$i-1][$j-1]+$cost);
            if($i>1&&$j>1&&$x[$i-1]===$y[$j-2]&&$x[$i-2]===$y[$j-1])$v=min($v,$d[$i-2][$j-2]+1);
            $d[$i][$j]=$v;
        }
    }
    return $d[$n][$m];
}
function fs_limit(string $w): int { $l=mb_strlen($w,'UTF-8'); return $l<=5?1:($l<=9?2:3); }
function fs_img(?string $p): string { $p=trim((string)$p); if($p===''||preg_match('~^https?://~i',$p))return $p; return ltrim($p,'/'); }
function fs_words(string $s): array { return preg_split('/\s+/u',fs_norm($s),-1,PREG_SPLIT_NO_EMPTY)?:[]; }

$q=trim((string)($_GET['q']??$_POST['q']??''));
if($q===''||mb_strlen($q,'UTF-8')>120)fs_out(['ok'=>false,'message'=>'اسم یا نوع کالا را بنویس.'],422);
$config=__DIR__.'/config.php';if(!is_file($config))fs_out(['ok'=>false,'message'=>'فروشگاه آماده نیست.'],503);
$c=require $config;
try{
    $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    require_once __DIR__.'/schema.php';ensure_product_schema($pdo);
}catch(Throwable $e){ts_log_internal($e,'AI-FUZZY-501');fs_out(['ok'=>false,'message'=>'ارتباط با فروشگاه برقرار نشد.'],503);}

$rows=$pdo->query("SELECT id,name,brand,quantity_label,category,image_path,price,stock FROM products WHERE active=1 AND price>0 ORDER BY id DESC LIMIT 12000")->fetchAll();

$curated=['شیر','ماست','کشک','پنیر','کره','خامه','دوغ','نوشیدنی','آبمیوه','نوشابه','آب','آب معدنی','دلستر','چیپس','پفک','شکلات','بیسکویت','کیک','آدامس','روغن','برنج','ماکارونی','قند','شکر','نمک','حبوبات','عدس','لوبیا','نخود','رب','کنسرو','سس','چای','قهوه','شامپو','صابون','خمیردندان','پوشک','دستمال','مایع','پودر','زغال','سیگار','تنباکو','فندک','کبریت','میوه','سبزی','گوشت','مرغ','تخم مرغ'];
$vocab=[];
foreach($curated as $w){$w=fs_norm($w);foreach(fs_words($w) as $x)$vocab[$x]=($vocab[$x]??0)+1000;}
foreach($rows as $p){
    $nw=fs_words((string)$p['name']);
    foreach($nw as $i=>$w){if(mb_strlen($w,'UTF-8')>=2&&!preg_match('/^\d+$/u',$w))$vocab[$w]=($vocab[$w]??0)+($i===0?30:12);}
    foreach(fs_words((string)$p['brand']) as $w){if(mb_strlen($w,'UTF-8')>=2)$vocab[$w]=($vocab[$w]??0)+4;}
}

$direct=['ماشت'=>'ماست','پیر'=>'پنیر','چیبس'=>'چیپس','نویدنی'=>'نوشیدنی','ذغال'=>'زغال','زغال'=>'زغال'];
$origTokens=fs_tokens($q);if(!$origTokens)$origTokens=[fs_norm($q)];
$effective=[];$corrections=[];$suggestions=[];
foreach($origTokens as $tok){
    $tok=fs_norm($tok);$best=$tok;$alts=[];
    if(isset($direct[$tok])){$best=$direct[$tok];$alts[]=['w'=>$best,'d'=>0,'wt'=>2000];}
    elseif(isset($vocab[$tok])){$best=$tok;}
    else{
        $lim=fs_limit($tok);$tl=mb_strlen($tok,'UTF-8');
        foreach($vocab as $w=>$wt){
            $wl=mb_strlen($w,'UTF-8');if(abs($wl-$tl)>$lim)continue;
            $d=fs_dist($tok,$w);if($d>$lim)continue;
            $alts[]=['w'=>$w,'d'=>$d,'wt'=>$wt];
        }
        usort($alts,function($a,$b){return ($a['d']<=>$b['d']) ?: ($b['wt']<=>$a['wt']) ?: (mb_strlen($a['w'],'UTF-8')<=>mb_strlen($b['w'],'UTF-8'));});
        if($alts)$best=$alts[0]['w'];
    }
    if($best!==$tok)$corrections[]=['from'=>$tok,'to'=>$best];
    $effective[]=$best;
    foreach(array_slice($alts,0,3) as $a){if($a['w']!==$tok)$suggestions[$a['w']]=true;}
}
$effective=array_values(array_unique(array_filter($effective)));
$correctedQuery=implode(' ',$effective);

$generic=[
 'لبنیات'=>'لبنیات و صبحانه','صبحانه'=>'لبنیات و صبحانه','نوشیدنی'=>'نوشیدنی','تنقلات'=>'تنقلات و شیرینی','شیرینی'=>'تنقلات و شیرینی','شوینده'=>'خانه و شوینده','مواد غذایی'=>'مواد غذایی','کنسرو'=>'کنسرو و غذای آماده','بهداشت'=>'بهداشت شخصی','کودک'=>'کودک','میوه'=>'میوه و سبزی','سبزی'=>'میوه و سبزی','گوشت'=>'گوشت و مرغ','مرغ'=>'گوشت و مرغ','دخانیات'=>'دخانیات'
];
$genericCat=(count($effective)===1&&isset($generic[$effective[0]]))?$generic[$effective[0]]:'';
$ranked=[];
foreach($rows as $p){
    $nn=fs_norm((string)$p['name']);$nb=fs_norm((string)$p['brand']);$nqty=fs_norm((string)$p['quantity_label']);$nc=fs_norm((string)$p['category']);
    $score=((int)$p['stock']>0?25:-150);$matched=0;
    if($genericCat!==''){
        if((string)$p['category']===$genericCat)$score+=240;else continue;
    }else{
        foreach($effective as $t){
            $wordName=(bool)preg_match('/(^|\s)'.preg_quote($t,'/').'($|\s)/u',$nn);
            $wordBrand=(bool)preg_match('/(^|\s)'.preg_quote($t,'/').'($|\s)/u',$nb);
            if($wordName){$score+=150;$matched++;}
            elseif(str_contains($nn,$t)){$score+=80;$matched++;}
            elseif($wordBrand){$score+=45;$matched++;}
            elseif(str_contains($nb,$t)){$score+=25;$matched++;}
            elseif(str_contains($nqty,$t)){$score+=18;$matched++;}
            elseif(str_contains($nc,$t)){$score+=8;$matched++;}
        }
        $need=count($effective)<=3?count($effective):(int)ceil(count($effective)*.7);
        if($matched<max(1,$need))continue;
        if($correctedQuery!==''&&str_contains($nn,$correctedQuery))$score+=230;
        if($effective&&($nn===$effective[0]||str_starts_with($nn,$effective[0].' ')))$score+=120;
    }
    $p['_score']=$score;$ranked[]=$p;
}
usort($ranked,fn($a,$b)=>($b['_score']<=>$a['_score']) ?: ((int)$b['stock']<=>(int)$a['stock']) ?: ((int)$b['id']<=>(int)$a['id']));
$items=[];foreach(array_slice($ranked,0,12) as $p){$items[]=['id'=>(int)$p['id'],'name'=>(string)$p['name'],'brand'=>(string)$p['brand'],'quantity_label'=>(string)$p['quantity_label'],'category'=>(string)$p['category'],'image'=>fs_img($p['image_path']),'price'=>(int)$p['price'],'stock'=>(int)$p['stock']];}

$sug=array_keys($suggestions);$sug=array_values(array_filter($sug,fn($w)=>$w!==$correctedQuery));$sug=array_slice($sug,0,4);
fs_out(['ok'=>true,'query'=>$q,'corrected_query'=>$corrections?$correctedQuery:null,'corrections'=>$corrections,'suggestions'=>$sug,'items'=>$items,'count'=>count($items)]);
