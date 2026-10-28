<?php
declare(strict_types=1);
require __DIR__.'/../WebSaza/nz-community.php';require __DIR__.'/../WebSaza/nz-chat-controls.php';require __DIR__.'/../WebSaza/nz-ai-browse.php';
function test(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$base=['user_id'=>'seller','status'=>'published','description'=>'','province'=>'فارس','city'=>'شیراز','price'=>20000000,'created_at'=>date('c'),'category'=>'کالای دیجیتال','subcategory'=>'موبایل'];
$phone=fn($id,$brand,$model)=>$base+['id'=>$id,'title'=>'گوشی '.$brand.' '.$model,'attributes'=>['برند'=>$brand,'مدل'=>$model,'وضعیت'=>'کارکرده سالم']];
$posts=[$phone('s55','Samsung','Galaxy A55'),$phone('s56','Samsung','Galaxy A56'),$phone('apple','Apple','iPhone 13 Pro')];
$posts[]=array_replace($phone('hidden','Secret','Hidden'),['status'=>'pending']);$posts[]=array_replace($phone('demo','Demo','Demo'),['demo'=>true]);$posts[]=array_replace($phone('expired','Expired','Expired'),['expires_at'=>'2000-01-01']);
$p=nzab_plan($posts,'گوشی می خوام');$v=nzab_view($posts,$p);test($p['ready']&&$p['stage']==='results'&&count($v['results'])===3,'bare phone intent immediately returns real ads without city/budget/category questions');
test($v['facet']['key']==='برند'&&array_column($v['facet']['options'],'value')===['Samsung','Apple'],'brand row comes only from currently eligible ads');
test(count(nzab_view($posts,nzab_plan($posts,'سامسونگ'))['results'])===2,'brand-only first message also returns inventory');
$s=nzab_plan($posts,'',$p,[],['key'=>'برند','value'=>'Samsung']);$v=nzab_view($posts,$s);test(count($v['results'])===2&&$v['facet']['key']==='مدل'&&count($v['facet']['options'])===2,'brand chip narrows ads and offers existing models');
$m=nzab_plan($posts,'',$s,[],['key'=>'مدل','value'=>'Galaxy A55']);$v=nzab_view($posts,$m);test(array_column($v['results'],'id')===['s55'],'model chip narrows exact real results');
$typed=nzab_plan($posts,'سامسونگ',$p);$typed=nzab_plan($posts,'A55',$typed);test(array_column(nzab_view($posts,$typed)['results'],'id')===['s55'],'typing and chips refine the same search state');
$changed=nzab_plan($posts,'اپل',$m);test(($changed['attributes']['برند']??'')==='Apple'&&!isset($changed['attributes']['مدل']),'typed brand correction clears incompatible model');
$back=nzab_plan($posts,'',$m,[],['key'=>'برند','value'=>'']);test(count(nzab_view($posts,$back)['results'])===3,'removing brand clears downstream model and restores results');
try{nzab_plan($posts,'',$p,[],['key'=>'برند','value'=>'Invented']);throw new LogicException('forged facet accepted');}catch(RuntimeException $e){test(true,'invented and unavailable facet values are rejected');}
$local=nzab_plan($posts,'گوشی در تهران تا ۲۵ میلیون تومان');test(nzab_view($posts,$local)['results']===[],'explicit city and price remain strict even without matching results');
$unknown=$base;$unknown['id']='unknown';$unknown['title']='سیگار آزمایشی';$unknown['category']='شخصی';$unknown['subcategory']='سایر';$unknown['attributes']=['برند'=>'نام موجود'];$all=array_merge($posts,[$unknown]);$u=nzab_plan($all,'سیگار می خوام');test(array_column(nzab_view($all,$u)['results'],'id')===['unknown']&&!str_contains($u['reply'],'کدام دسته'),'unclassified product searches ad text instead of requesting taxonomy');
$chair=$base;$chair['id']='chair';$chair['title']='صندلی سفارشی';$chair['category']='خانه و آشپزخانه';$chair['subcategory']='مبلمان';$chair['attributes']=['جنس'=>'چوب','رنگ'=>'سفید'];$cp=nzab_plan([$chair],'صندلی');$cv=nzab_view([$chair],$cp);test($cv['facet']['key']==='رنگ'||$cv['facet']['key']==='جنس','generic products receive actual attributes instead of phone-specific catalogues');
$house=$base;$house['id']='house';$house['title']='آپارتمان دو خوابه';$house['category']='املاک';$house['subcategory']='آپارتمان';$house['attributes']=['نوع معامله'=>'فروش','تعداد اتاق'=>'۲'];$hv=nzab_view([$house],nzab_plan([$house],'آپارتمان'));test($hv['facet']['key']==='نوع معامله','property search uses transaction facets from ads');
$private=$phone('pii','Samsung','Galaxy A55');$private['attributes']['شماره تماس']='09120000000';$private['attributes']['کد خصوصی']='09121111111';$pv=nzab_view([$private],nzab_plan([$private],'گوشی'));test(!str_contains(json_encode($pv),'0912'),'facets and cards never leak private contact attributes');
$db=['posts'=>$posts,'users'=>[['id'=>'seller'],['id'=>'buyer']],'blocks'=>[['blocker_id'=>'buyer','blocked_id'=>'seller','status'=>'active']]];test(nzab_posts($db,['id'=>'buyer'])===[],'blocked seller ads never provide results or facet options');
$many=[];for($i=0;$i<15;$i++)$many[]=$phone('many'.$i,$i===14?'Rare':'Samsung','Galaxy A55');$manyPlan=nzab_plan($many,'گوشی');$manyView=nzab_view($many,$manyPlan);test(count($manyView['results'])===12&&$manyView['has_more']&&in_array('Rare',array_column($manyView['facet']['options'],'value'),true),'facets include all matching inventory beyond the first page');
$manyPlan['_browse']['offset']=12;test(count(nzab_view($many,$manyPlan)['results'])===3,'pagination continues existing filtered results');
$pending=['phrase'=>'آرمینو','category'=>'خانه و آشپزخانه','subcategory'=>'مبلمان','status'=>'pending'];test(nzab_plan([$chair],'آرمینو',[],[$pending])['category']==='','unapproved examples cannot influence browsing');
$hyphen=$phone('hyphen','Other','X-200');test(count(nzab_view([$hyphen],nzab_plan([$hyphen],'X-200'))['results'])===1,'hyphenated model names are not mistaken for negative prices');
echo "All progressive browsing checks passed.\n";
