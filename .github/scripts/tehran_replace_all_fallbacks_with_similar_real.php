<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control:no-store');
$expected='__TOKEN__';if(!hash_equals($expected,(string)($_SERVER['HTTP_X_IMPORT_TOKEN']??''))){http_response_code(403);exit;}
@set_time_limit(0);@ini_set('memory_limit','512M');
function nrm(string $s): string {
  $s=strtr($s,['ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ۀ'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','آ'=>'ا','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
  $s=mb_strtolower($s,'UTF-8');$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??'';return trim(preg_replace('/\s+/u',' ',$s)??'');
}
function toks(string $s): array {
  static $stop=['محصول'=>1,'بسته'=>1,'عددی'=>1,'عدد'=>1,'گرمی'=>1,'گرم'=>1,'کیلوگرم'=>1,'کیلو'=>1,'میلی'=>1,'لیتر'=>1,'مدل'=>1,'نوع'=>1,'با'=>1,'بدون'=>1,'حجم'=>1,'وزن'=>1,'قوطی'=>1,'بطری'=>1,'پاکت'=>1,'شیشه'=>1,'جعبه'=>1,'کارتن'=>1,'اصل'=>1,'اصلی'=>1];
  $out=[];foreach(preg_split('/\s+/u',nrm($s))?:[] as $x){if($x===''||isset($stop[$x])||preg_match('/^\d+$/',$x))continue;$out[$x]=1;}return array_keys($out);
}
function groupOf(string $s): string {
  $n=' '.nrm($s).' ';
  $groups=[
   'milk'=>[' شیر ',' شیرکاکائو ',' شیرقهوه '],'yogurt'=>[' ماست '],'cheese'=>[' پنیر '],'dough'=>[' دوغ '],'cream'=>[' خامه '],'butter'=>[' کره '],
   'soda'=>[' نوشابه ',' نوشیدنی گازدار '],'juice'=>[' آبمیوه ',' آب میوه ',' نکتار '],'water'=>[' آب معدنی ',' آب آشامیدنی '],
   'chips'=>[' چیپس '],'puff'=>[' پفک ',' اسنک '],'biscuit'=>[' بیسکویت ',' ویفر '],'cake'=>[' کیک ',' کلوچه '],'chocolate'=>[' شکلات ',' کاکائو '],
   'tea'=>[' چای '],'coffee'=>[' قهوه ',' کافی میکس ',' نسکافه '],'sugar'=>[' شکر ',' قند '],'rice'=>[' برنج '],'pasta'=>[' ماکارونی ',' پاستا ',' رشته '],
   'oil'=>[' روغن '],'tomato'=>[' رب '],'canned'=>[' کنسرو ',' تن ماهی ',' کمپوت '],'sauce'=>[' سس '],'spice'=>[' ادویه ',' فلفل ',' زردچوبه ',' دارچین '],
   'legume'=>[' عدس ',' نخود ',' لوبیا ',' لپه '],'nuts'=>[' آجیل ',' پسته ',' بادام ',' کشمش ',' خرما '],'honey'=>[' عسل '],'jam'=>[' مربا '],
   'tissue'=>[' دستمال ',' دستمال کاغذی ',' حوله کاغذی '],'shampoo'=>[' شامپو '],'soap'=>[' صابون '],'detergent'=>[' مایع ظرفشویی ',' مایع لباسشویی ',' پودر لباسشویی ',' شوینده '],
   'diaper'=>[' پوشک '],'tooth'=>[' خمیر دندان ',' مسواک '],'gum'=>[' آدامس '],'icecream'=>[' بستنی ']
  ];
  foreach($groups as $g=>$words)foreach($words as $w)if(strpos($n,$w)!==false)return $g;return '';
}
function simScore(array $a,array $b): float {
  $s=0.0;$ca=nrm((string)($a['category']??''));$cb=nrm((string)($b['category']??''));if($ca!==''&&$cb!==''&&$ca===$cb)$s+=70;
  $ba=nrm((string)($a['brand']??''));$bb=nrm((string)($b['brand']??''));if($ba!==''&&$bb!==''){if($ba===$bb)$s+=55;else{similar_text($ba,$bb,$bp);if($bp>=80)$s+=25;}}
  $ga=groupOf((string)$a['name']);$gb=groupOf((string)$b['name']);if($ga!==''&&$ga===$gb)$s+=80;
  $ta=toks((string)$a['name']);$tb=toks((string)$b['name']);$ia=array_intersect($ta,$tb);if($ia)$s+=65*(count($ia)/max(1,count(array_unique(array_merge($ta,$tb)))));
  similar_text(nrm((string)$a['name']),nrm((string)$b['name']),$np);$s+=0.75*$np;
  return $s;
}
try{
 $c=require __DIR__.'/config.php';$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $targets=$pdo->query("SELECT id,name,brand,category,pyramid_code2 FROM products WHERE active=1 AND image_source LIKE 'generated-fallback|%' ORDER BY id")->fetchAll();
 $pool=$pdo->query("SELECT id,name,brand,category,pyramid_code2,image_path,image_source FROM products WHERE active=1 AND image_path IS NOT NULL AND image_path<>'' AND (image_source IS NULL OR (image_source NOT LIKE 'generated-fallback|%' AND image_source NOT LIKE 'similar-real|%')) ORDER BY id")->fetchAll();
 if(!$pool)throw new RuntimeException('empty real image pool');
 $byCat=[];$byBrand=[];$byGroup=[];$byToken=[];
 foreach($pool as $i=>$p){$cat=nrm((string)($p['category']??''));if($cat!=='')$byCat[$cat][]=$i;$br=nrm((string)($p['brand']??''));if($br!=='')$byBrand[$br][]=$i;$g=groupOf((string)$p['name']);if($g!=='')$byGroup[$g][]=$i;foreach(array_slice(toks((string)$p['name']),0,5) as $t)$byToken[$t][]=$i;}
 $up=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE id=? AND image_source LIKE 'generated-fallback|%'");
 $updated=0;$exactGroup=0;$sameCat=0;$sameBrand=0;$tokenMatch=0;$globalFallback=0;
 $pdo->beginTransaction();
 foreach($targets as $t){$cand=[];$cat=nrm((string)($t['category']??''));$br=nrm((string)($t['brand']??''));$g=groupOf((string)$t['name']);
   if($g!==''&&isset($byGroup[$g])){foreach($byGroup[$g] as $i)$cand[$i]=1;$exactGroup++;}
   if($cat!==''&&isset($byCat[$cat])){foreach($byCat[$cat] as $i)$cand[$i]=1;$sameCat++;}
   if($br!==''&&isset($byBrand[$br])){foreach($byBrand[$br] as $i)$cand[$i]=1;$sameBrand++;}
   foreach(array_slice(toks((string)$t['name']),0,4) as $tk)if(isset($byToken[$tk])){foreach($byToken[$tk] as $i)$cand[$i]=1;$tokenMatch++;}
   if(!$cand){$globalFallback++;$step=max(1,(int)floor(count($pool)/250));for($i=0;$i<count($pool);$i+=$step)$cand[$i]=1;}
   if(count($cand)>600){$cand=array_slice($cand,0,600,true);}
   $best=null;$bs=-1.0;foreach(array_keys($cand) as $i){$sc=simScore($t,$pool[$i]);if($sc>$bs){$bs=$sc;$best=$pool[$i];}}
   if(!$best)$best=$pool[0];
   $src='similar-real|'.(string)$best['id'].'|'.substr((string)($best['image_source']??'real'),0,180);
   $up->execute([(string)$best['image_path'],$src,(int)$t['id']]);$updated+=$up->rowCount();
 }
 $pdo->commit();
 $remain=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1 AND image_source LIKE 'generated-fallback|%'")->fetchColumn();
 $similar=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1 AND image_source LIKE 'similar-real|%'")->fetchColumn();
 $total=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
 @unlink(__FILE__);
 echo json_encode(['ok'=>true,'targets'=>count($targets),'real_pool'=>count($pool),'updated'=>$updated,'remaining_generated'=>$remain,'similar_real'=>$similar,'active_total'=>$total,'signals'=>['group'=>$exactGroup,'category'=>$sameCat,'brand'=>$sameBrand,'token'=>$tokenMatch,'global'=>$globalFallback]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();http_response_code(500);echo json_encode(['ok'=>false,'error'=>'SIM-500']);}
