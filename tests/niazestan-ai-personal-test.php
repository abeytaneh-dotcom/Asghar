<?php
declare(strict_types=1);
require __DIR__.'/../WebSaza/nz-community.php';require __DIR__.'/../WebSaza/nz-chat-controls.php';require __DIR__.'/../WebSaza/nz-ai-core.php';require __DIR__.'/../WebSaza/nz-ai-personal.php';
function expect(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$f=['query'=>'مبل','category'=>'خانه و آشپزخانه','subcategory'=>'مبلمان','province'=>'فارس','city'=>'شیراز','min_price'=>null,'max_price'=>20000000,'ready'=>true];
$p=['id'=>'a','user_id'=>'seller','title'=>'مبل راحتی','description'=>'مبل سالم','status'=>'published','category'=>$f['category'],'subcategory'=>$f['subcategory'],'province'=>'فارس','city'=>'شیراز','price'=>15000000,'created_at'=>date('c')];
$db=nzc_default_db();$db['posts']=[$p,array_replace($p,['id'=>'b']),array_replace($p,['id'=>'expensive','price'=>25000000]),array_replace($p,['id'=>'private','status'=>'pending']),array_replace($p,['id'=>'demo','demo'=>true]),array_replace($p,['id'=>'other-city','city'=>'تهران'])];
nzaiexp_record_search($db,'one',$f);expect(empty($db['ai_search_history']),'history is not recorded before separate consent');
nzaiexp_enable($db,'one',true);nzaiexp_record_search($db,'one',$f);expect(count($db['ai_search_history'])===1,'completed search recorded after consent');
nzaiexp_record_search($db,'one',$f);expect(count($db['ai_search_history'])===1,'repeated search is deduplicated');
expect(nzaiexp_suggestions($db,'two')['cards']===[],'another account receives no personal data');
$r=nzaiexp_suggestions($db,'one');expect(array_column($r['cards'],'id')===['a','b'],'suggestions respect price, city, moderation and demo exclusion');
nzaiexp_react($db,'one','b','like');$r=nzaiexp_suggestions($db,'one');expect($r['cards'][0]['id']==='b','positive interaction improves future ranking');
nzaiexp_react($db,'one','b','dismiss');expect(array_column(nzaiexp_suggestions($db,'one')['cards'],'id')===['a'],'negative feedback hides unwanted listing');
nzaiexp_react($db,'one','b','clear');expect(count(nzaiexp_suggestions($db,'one')['cards'])===2,'feedback can be undone');
$history=nzaiexp_suggestions($db,'one')['history'];expect(count($history)===1,'previous search is available to resume');
expect(nzaiexp_recall($db,'one',$history[0]['id'])['max_price']===20000000,'resume preserves saved constraints');
try{nzaiexp_recall($db,'two',$history[0]['id']);throw new LogicException('cross-user recall accepted');}catch(RuntimeException $e){expect(true,'cross-account history lookup denied');}
nzaiexp_record_search($db,'one',array_replace($f,['ready'=>false]));expect(count($db['ai_search_history'])===1,'incomplete dialogue is not learned as a completed search');
$n=count($db['ai_search_history']);nzaiexp_record_search($db,'one',array_replace($f,['query'=>'خیابان آزمایشی پلاک ۲۳']));expect(count($db['ai_search_history'])===$n,'precise address excluded from search memory');
$db['blocks'][]=['blocker_id'=>'one','blocked_id'=>'seller','status'=>'active'];expect(nzaiexp_suggestions($db,'one')['cards']===[],'blocked seller excluded from recommendations');$db['blocks']=[];
$db['posts'][0]['expires_at']='2000-01-01';expect(!in_array('a',array_column(nzaiexp_suggestions($db,'one')['cards'],'id'),true),'expired listing disappears');
nzaiexp_enable($db,'one',false);expect(nzaiexp_suggestions($db,'one')['history']===[]&&empty($db['ai_search_history'])&&empty($db['ai_interactions']),'opt-out erases personal history and interactions');
$manual=nzaiexp_manual_filters(['q'=>'مبل','city'=>'شیراز','price_max'=>'20000000']);expect($manual['subcategory']==='مبلمان'&&$manual['max_price']===20000000,'basic manual searches use controlled item and location filters');
expect(nzaiexp_manual_filters(['q'=>'مبل','city'=>'شیراز','district'=>'معالی‌آباد'])===null,'unsupported private manual filters are not silently broadened');
nzaiexp_enable($db,'one',true);$db['posts'][1]['attributes']=['وضعیت'=>'کارکرده سالم'];nzaiexp_record_search($db,'one',$f+['attributes'=>['وضعیت'=>'نو']]);expect(nzaiexp_suggestions($db,'one')['cards']===[],'condition constraints are preserved in personal recommendations');

echo "All personalized recommendation checks passed.\n";
