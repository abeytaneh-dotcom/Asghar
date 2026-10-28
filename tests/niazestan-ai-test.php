<?php
declare(strict_types=1);
require __DIR__.'/../WebSaza/nz-community.php';
require __DIR__.'/../WebSaza/nz-chat-controls.php';
require __DIR__.'/../WebSaza/nz-ai-core.php';
require __DIR__.'/../WebSaza/nz-push.php';
function check(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
function rejects(callable $fn,string $name): void {try{$fn();}catch(Throwable $e){check(true,$name);return;}check(false,$name);}
$p=['id'=>'real','user_id'=>'seller','status'=>'published','title'=>'مبل راحتی سالم','description'=>'مبل سه نفره سالم','category'=>'خانه و آشپزخانه','subcategory'=>'مبلمان','province'=>'فارس','city'=>'شیراز','district'=>'معالی‌آباد','price'=>19000000,'created_at'=>date('c'),'phone_visible'=>false,'chat_enabled'=>true,'attributes'=>['وضعیت'=>'کارکرده سالم']];
$f=['query'=>'مبل','city'=>'شیراز','max_price'=>20000000];
$posts=[$p,array_replace($p,['id'=>'pending','status'=>'pending']),array_replace($p,['id'=>'expired','expires_at'=>'2000-01-01']),array_replace($p,['id'=>'demo','demo'=>true]),array_replace($p,['id'=>'sold','status'=>'sold']),array_replace($p,['id'=>'unknown-price','price'=>0]),array_replace($p,['id'=>'expensive','price'=>30000000]),array_replace($p,['id'=>'other-city','city'=>'تهران'])];
check(array_column(nzai_search($posts,$f),'id')===['real'],'only real published unexpired in-budget local listings');
check(nzai_search([$p],['query'=>'یخچال'])===[],'no invented matches');
check(nzai_search([$p],$f+['attributes'=>['وضعیت'=>'نو']])===[],'category details filter results');
check(nzai_search([$p],['min_price'=>20000000])===[],'minimum budget enforced');
$db=['posts'=>[$p],'users'=>[['id'=>'seller','phone'=>'09120000000'],['id'=>'buyer']],'blocks'=>[]];
rejects(fn()=>nzai_contact($db,'real',null),'anonymous contact denied');
check(nzai_contact($db,'real',['id'=>'buyer'])['phone']==='','seller privacy preserved');
$db['posts'][0]['phone_visible']=true;check(nzai_contact($db,'real',['id'=>'buyer'])['phone']==='09120000000','explicit contact allowed');
$db['blocks'][]=['blocker_id'=>'buyer','blocked_id'=>'seller','status'=>'active'];rejects(fn()=>nzai_contact($db,'real',['id'=>'buyer']),'blocked contact denied');
rejects(fn()=>nzai_contact($db,'missing',['id'=>'buyer']),'unknown listing denied');
rejects(fn()=>nzai_clean(['category'=>'invented']),'invalid taxonomy fails closed');
rejects(fn()=>nzai_clean(['city'=>'invented']),'invalid city fails closed');
rejects(fn()=>nzai_clean(['min_price'=>200,'max_price'=>100]),'inverted budget rejected');
$c=nzai_clean(['city'=>'شیراز','title'=>'<script>test</script>','attributes'=>[['label'=>'رنگ','value'=>'سفید']]]);check($c['province']==='فارس'&&$c['attributes']['رنگ']==='سفید','known city resolved and structured attributes accepted');
putenv('NZ_AI_API_KEY');putenv('NZ_AI_ENGINE=cloud');rejects(fn()=>nzai_plan([],'search',[]),'explicit cloud mode requires provider');putenv('NZ_AI_ENGINE');
foreach(['http://fcm.googleapis.com/a','https://127.0.0.1/x','https://fcm.googleapis.com.evil.test/x','https://fcm.googleapis.com:444/x','https://user@fcm.googleapis.com/x'] as $url)rejects(fn()=>nzpush_host($url),'reject unsafe push endpoint '.$url);
check(nzpush_host('https://fcm.googleapis.com/abc')==='fcm.googleapis.com','FCM allowed');
check(nzpush_host('https://web.push.apple.com/abc')==='web.push.apple.com','Apple push allowed');
$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);openssl_pkey_export($key,$pem);$d=openssl_pkey_get_details($key);$keys=['private'=>$pem,'public'=>nzpush_b64("\x04".$d['ec']['x'].$d['ec']['y'])];
[$head,$claims,$sig]=explode('.',nzpush_jwt('fcm.googleapis.com',$keys));$decoded=json_decode(base64_decode(strtr($claims,'-_','+/')),true);$raw=base64_decode(strtr($sig,'-_','+/'));check(strlen($raw)===64&&$decoded['aud']==='https://fcm.googleapis.com'&&$decoded['exp']>time(),'VAPID claims and raw signature');
$ints='';foreach([substr($raw,0,32),substr($raw,32)] as $v){$v=ltrim($v,"\0");if(ord($v[0])>127)$v="\0".$v;$ints.="\x02".chr(strlen($v)).$v;}$der="\x30".chr(strlen($ints)).$ints;check(openssl_verify($head.'.'.$claims,$der,$d['key'],OPENSSL_ALGO_SHA256)===1,'VAPID signature independently verifies');
echo "All AI domain and push protocol checks passed.\n";
