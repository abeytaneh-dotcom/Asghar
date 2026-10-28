<?php
declare(strict_types=1);
require_once __DIR__.'/nz-controlled-validation.php';

/** Bounded Persian intent/entity engine. Learned samples classify items only. */
function nznative_norm(string $s): string {
    $s=mb_strtolower(strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','ي'=>'ی','ك'=>'ک',"\u{200c}"=>' ','٫'=>'.','٬'=>'']));
    $s=str_replace(['آیفون','ایفون','سامسونگ','شیائومی','اپل','پرو'],['iphone','iphone','samsung','xiaomi','apple','pro'],$s);
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function nznative_tokens(string $s): array {
    $stop=['می','خواهم','میخواهم','میخوام','میخام','یک','یه','را','رو','من','برای','در','با','از','به','و','که','هستم','است','دارم','دنبال','بفروشم','بخرم','بدم','بدهم','آگهی','ثبت','کنم','کن','لطفا','لطفاً','قیمت','تومان','تومن','میلیون','میلیارد','تا','حداکثر','حداقل','بودجه','نیاز','دارم','باشد','باشه','نه','شهر','استان'];
    $stop=array_merge($stop,['پیدا','نشون','بده','نمایش','بگرد','میگردم','جستجو','جستوجو','قصد','فروش','خرید','کردن','کردم','شده','بخواهم','میخواستم']);
    $stop=array_merge($stop,['چی','چه','داری','دارید','دارین','دارینش','هست','هستش','هستند','موجود','موجوده','بهم','بدهید','بفرست','میخواد','میخوامش','هایی','های','ها']);
    return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u',nznative_norm($s),-1,PREG_SPLIT_NO_EMPTY)?:[],fn($x)=>mb_strlen($x)>1&&!in_array($x,$stop,true)));
}
function nznative_has(string $text,string $phrase): bool {
    return preg_match('/(?<![\p{L}\p{N}])'.preg_quote(nznative_norm($phrase),'/').'(?![\p{L}\p{N}])/u',nznative_norm($text))===1;
}
function nznative_seeds(): array {
    $aliases=[
        'آپارتمان'=>['آپارتمان','اپارتمان','آپارتمان اجاره ای'],'خانه و ویلا'=>['ویلا','خانه ویلایی'],'زمین و کلنگی'=>['زمین','کلنگی'],'دفتر و تجاری'=>['دفتر تجاری'],'مغازه'=>['مغازه'],
        'خودرو'=>['ماشین','خودرو','پژو','پراید','دنا','ساینا','تیبا','تارا'],'موتورسیکلت'=>['موتور سیکلت','موتورسیکلت'],'دوچرخه'=>['دوچرخه'],
        'موبایل'=>['گوشی','گوشیم','موبایل','iphone','samsung','xiaomi'],'لپ‌تاپ'=>['لپ تاپ','لپتاپ','مک بوک'],'تبلت'=>['تبلت','آیپد'],'کنسول بازی'=>['پلی استیشن','ps5','xbox'],
        'لوازم خانگی'=>['یخچال','لباسشویی','ظرفشویی','جاروبرقی'],'مبلمان'=>['مبل','مبلمان','کاناپه'],'فرش و گلیم'=>['فرش','گلیم'],'روشنایی'=>['لوستر','چراغ'],
        'تعمیرات'=>['تعمیرکار','تعمیر لوازم'],'نظافت'=>['نظافت','نظافتچی'],'طراحی و برنامه‌نویسی'=>['برنامه نویسی','طراحی سایت'],
        'فروش و بازاریابی'=>['استخدام فروشنده','بازاریاب'],'راننده'=>['استخدام راننده'],'مالی و حسابداری'=>['حسابدار'],
        'لباس'=>['لباس','پیراهن','مانتو'],'کیف و کفش'=>['کیف','کفش'],'لوازم کودک'=>['کالسکه','سیسمونی'],'زیورآلات'=>['گردنبند','دستبند'],
        'کتاب'=>['کتاب','رمان'],'ورزش'=>['تردمیل','دمبل'],'موسیقی'=>['گیتار','پیانو'],'بازی و اسباب‌بازی'=>['اسباب بازی'],
        'ابزارآلات'=>['دریل','آچار'],'ماشین‌آلات'=>['ماشین آلات'],'تجهیزات فروشگاهی'=>['قفسه فروشگاهی'],'مواد غذایی و خوراکی'=>['برنج','عسل','خرما'],'گل و گیاه طبیعی'=>['گل طبیعی','گلدان'],'دام و طیور'=>['گوسفند','مرغ'],
        'کلاس و دوره آموزشی'=>['دوره آموزشی','کلاس زبان'],'فروش عمده'=>['فروش عمده'],'واگذاری کسب‌وکار'=>['واگذاری کسب و کار']
    ];
    $docs=[];foreach(nzt_categories() as $cat=>$subs)foreach($subs as $sub){$phrases=array_unique(array_merge([$sub],$aliases[$sub]??[]));foreach($phrases as $phrase)$docs[]=['phrase'=>$phrase,'category'=>$cat,'subcategory'=>$sub,'learned'=>false];}
    return $docs;
}
/** TF-IDF nearest prototype classifier; only approved, valid item examples are eligible. */
function nznative_classify(string $text,array $examples=[]): ?array {
    $docs=nznative_seeds();$cats=nzt_categories();foreach(array_slice($examples,-500) as $e){if(($e['status']??'')!=='approved'||!in_array($e['subcategory']??'',$cats[$e['category']??'']??[],true))continue;$phrase=(string)($e['phrase']??'');if(mb_strlen($phrase)>80||!nznative_tokens($phrase))continue;$docs[]=['phrase'=>$phrase,'category'=>$e['category'],'subcategory'=>$e['subcategory'],'learned'=>true];}
    $df=[];foreach($docs as &$d){$d['tokens']=array_unique(nznative_tokens($d['phrase']));foreach($d['tokens'] as $t)$df[$t]=($df[$t]??0)+1;}unset($d);
    $query=array_unique(nznative_tokens($text));if(!$query)return null;$scores=[];$n=count($docs);
    foreach($docs as $d){$dot=0.;$qa=0.;$da=0.;foreach($query as $t){$w=log(1+$n/(1+($df[$t]??0)));$qa+=$w*$w;if(in_array($t,$d['tokens'],true))$dot+=$w*$w;}foreach($d['tokens'] as $t){$w=log(1+$n/(1+($df[$t]??0)));$da+=$w*$w;}
        $score=$qa&&$da?$dot/sqrt($qa*$da):0.;if(nznative_has($text,$d['phrase']))$score=max($score,.95+min(.04,mb_strlen($d['phrase'])/1000));
        $key=$d['category'].'|'.$d['subcategory'];if($score>($scores[$key]['confidence']??0))$scores[$key]=$d+['confidence'=>$score];
    }
    usort($scores,fn($a,$b)=>$b['confidence']<=>$a['confidence']);$best=$scores[0]??null;if(!$best||$best['confidence']<.38)return null;
    if(isset($scores[1])&&$best['confidence']-$scores[1]['confidence']<.015&&$best['subcategory']!==$scores[1]['subcategory'])return null;
    unset($best['tokens']);return $best;
}
function nznative_money(string $text,string $stage): array {
    $s=nznative_norm($text);$s=preg_replace('/(?<=\d),(?=\d{3}(?:\D|$))/u','',$s)??$s;
    $s=preg_replace_callback('/([0-9]+(?:\.[0-9]+)?)\s*میلیون\s*و\s*([0-9]+)\s*هزار/u',fn($m)=>((float)$m[1]+(float)$m[2]/1000).' میلیون',$s)??$s;
    if(preg_match('/-\s*[0-9]/u',$s))throw new RuntimeException('مبلغ منفی معتبر نیست؛ مبلغ را دوباره به تومان بنویسید.');
    preg_match_all('/(?:(از|حداقل|بالای|تا|حداکثر|زیر|بودجه|قیمت(?:ش)?|مبلغ)\s*)?([0-9]+(?:\.[0-9]+)?)\s*(میلیارد|میلیون|هزار)?\s*(تومان|تومن|ریال)?/u',$s,$matches,PREG_SET_ORDER);
    $out=[];foreach($matches as $i=>$m){$prefix=$m[1]??'';$unit=$m[3]??'';$currency=$m[4]??'';
        if($unit===''&&($matches[$i+1][1]??'')==='تا')$unit=$matches[$i+1][3]??'';
        if($prefix===''&&$unit===''&&$currency===''&&!in_array($stage,['price','budget'],true))continue;
        $mult=match($unit){'میلیارد'=>1000000000,'میلیون'=>1000000,'هزار'=>1000,default=>1};$v=(float)$m[2]*$mult;if($currency==='ریال')$v/=10;
        if(!is_finite($v)||$v>999999999999||$v<0)throw new RuntimeException('مبلغ خارج از محدوده است؛ مبلغ را به تومان دوباره بنویسید.');
        $out[]=['value'=>(int)round($v),'minimum'=>in_array($prefix,['از','حداقل','بالای'],true)];
    }
    return $out;
}
function nznative_fields(string $cat,string $sub): array {
    $fields=match($cat){
        'املاک'=>['نوع معامله'=>['فروش','رهن و اجاره','اجاره','پیش‌فروش'],'متراژ'=>'number','تعداد اتاق'=>['بدون اتاق','۱','۲','۳','۴','۵ و بیشتر']],
        'وسایل نقلیه'=>['برند'=>'text','مدل'=>'text','سال ساخت'=>'year','کارکرد'=>'number','وضعیت بدنه'=>['بی‌رنگ','یک لکه رنگ','دو لکه رنگ','چند لکه رنگ','دور رنگ','تصادفی']],
        'کالای دیجیتال'=>['برند'=>'text','مدل'=>'text','وضعیت'=>['نو','در حد نو','کارکرده سالم','نیازمند تعمیر']],
        'خانه و آشپزخانه','شخصی','سرگرمی و فراغت'=>['وضعیت'=>['نو','در حد نو','کارکرده سالم','نیازمند تعمیر']],
        'خدمات'=>['نوع ارائه خدمت'=>['در محل مشتری','در محل ارائه‌دهنده','آنلاین','هر دو'],'محدوده خدمت‌رسانی'=>'text'],
        'استخدام و کاریابی'=>['عنوان شغلی'=>'text','نوع همکاری'=>['تمام‌وقت','پاره‌وقت','پروژه‌ای','دورکاری','کارآموزی']],
        'تجهیزات و صنعتی'=>['نوع دستگاه/تجهیز'=>'text','وضعیت'=>['نو','کارکرده سالم','نیازمند تعمیر']],
        'کسب‌وکار و عمده‌فروشی'=>['نوع فروش'=>['عمده','خرده و عمده','واگذاری کامل']],
        'کشاورزی، حیوانات و مواد غذایی'=>['نوع محصول/خدمت'=>'text'],
        'آموزشی و اجتماعی'=>['نوع برگزاری'=>['حضوری','آنلاین','هر دو']],default=>[]};
    $catalog=nzcv_catalog($sub);if($catalog)$fields['برند']=array_keys($catalog);
    return $fields;
}
function nznative_model_norm(string $s): string {
    $s=nznative_norm($s);
    $aliases=['proمکس'=>'pro max','مکس'=>'max','پلاس'=>'plus','اولترا'=>'ultra','مینی'=>'mini','گلکسی'=>'galaxy','ردمی'=>'redmi','پوکو'=>'poco','نوت'=>'note','پیکسل'=>'pixel','ایر'=>'air'];
    return preg_replace_callback('/(?<![\p{L}\p{N}])('.implode('|',array_keys($aliases)).')(?![\p{L}\p{N}])/u',fn($m)=>$aliases[$m[0]],$s)??$s;
}
/** Accept an unambiguous catalogue model, including a brand-scoped short name. */
function nznative_model(string $text,array $models,bool $shorthand): ?string {
    $s=nznative_model_norm($text);$compact=fn($v)=>preg_replace('/[^\p{L}\p{N}]+/u','',nznative_model_norm($v));
    $candidates=[];
    foreach($models as $model){
        if($model==='سایر مدل'){if(nznative_has($s,$model))return $model;continue;}
        $m=nznative_model_norm($model);$aliases=[$m];
        if($shorthand)$aliases[]=preg_replace('/^(?:iphone|galaxy|redmi|xiaomi|honor|pixel)\s+/u','',$m)??$m;
        // Never turn an unsupported Pro/Max/Plus/Ultra variant into its base model.
        foreach(['pro','max','plus','ultra','mini','air'] as $variant)if(nznative_has($s,$variant)&&!nznative_has($m,$variant))continue 2;
        foreach($aliases as $alias)if(nznative_has($s,$alias)||$compact($s)===$compact($alias)){$candidates[$model]=mb_strlen($m);break;}
    }
    if(!$candidates)return null;arsort($candidates);$names=array_keys($candidates);$best=$names[0];
    // Multiple different models in one reply require clarification, not a guess.
    foreach(array_slice($names,1) as $other)if(!nznative_has(nznative_model_norm($best),nznative_model_norm($other)))return null;
    return $best;
}
function nznative_brand(string $text,array $catalog,string $sub): ?string {
    $matches=[];foreach(array_keys($catalog) as $brand)if(($brand!=='سایر'||nznative_norm($text)==='سایر')&&nznative_has($text,$brand))$matches[]=$brand;
    if(in_array($sub,['موبایل','تبلت'],true)&&nznative_has($text,'iphone'))$matches[]='Apple';
    $matches=array_unique($matches);return count($matches)===1?reset($matches):null;
}
function nznative_subject(string $text): string {
    $s=nznative_norm($text);$s=preg_replace('/(?:از|حداقل|تا|حداکثر|بودجه|قیمت(?:ش)?|مبلغ)\s*[0-9].*$/u','',$s)??$s;
    $s=preg_replace('/[0-9]+(?:\.[0-9]+)?\s*(?:میلیون|میلیارد|هزار|تومان|تومن|ریال).*$/u','',$s)??$s;
    foreach(nzt_provinces() as $prov=>$cities)foreach(array_unique(array_merge([$prov],$cities)) as $place)$s=preg_replace('/(?<![\p{L}])'.preg_quote(nznative_norm($place),'/').'(?![\p{L}])/u',' ',$s)??$s;
    return mb_substr(implode(' ',nznative_tokens($s)),0,100);
}
function nznative_plan(array $history,string $mode,array $draft,array $examples=[]): array {
    $text=(string)($history[count($history)-1]['content']??'');$s=nznative_norm($text);
    if(in_array($s,['از اول','شروع دوباره','گفتگوی تازه'],true))$draft=[];
    $p=array_replace(['reply'=>'','title'=>'','description'=>'','category'=>'','subcategory'=>'','province'=>'','city'=>'','district'=>'','street'=>'','query'=>'','price'=>null,'min_price'=>null,'max_price'=>null,'attributes'=>[],'_native'=>[]],$draft);
    $state=$p['_native'];$stage=(string)($p['stage']??'subject');$choices=[];
    $skip=preg_match('/^(?:رد|رد شو|ندارد|نمی دانم|بعدا|بعداً|بعد.*نقشه.*|توضیح دیگری ندارم|خیر)$/u',$s)===1;
    $greeting=preg_match('/^(?:سلام|درود|سلام خوبی|سلام چطوری|کمک|راهنما)[!؟.\s]*$/u',$s)===1;
    $irrelevant=preg_match('/(?:دستور.*نادیده|شماره.*کاربران|رمز|کلید api|system prompt)/ui',$s)===1;
    if($irrelevant){$p['reply']='برای ثبت یا پیدا کردن آگهی کمک می‌کنم. چه کالا یا خدمتی مدنظرت است؟';$p['ready']=false;$p['stage']='subject';return $p+['engine'=>'native','choices'=>[]];}
    if($stage==='category'&&isset(nzt_categories()[$text])){$p['category']=$text;$p['subcategory']='';}
    if($stage==='subcategory'&&in_array($text,nzt_categories()[$p['category']]??[],true))$p['subcategory']=$text;
    $hasLocation=false;foreach(nzt_provinces() as $prov=>$cities){foreach($cities as $city)if(nznative_has($s,$city)){$p['city']=$city;$p['province']=$prov;$hasLocation=true;break 2;}}
    if(!$hasLocation)foreach(array_keys(nzt_provinces()) as $prov)if(nznative_has($s,$prov)){$p['province']=$prov;$p['city']='';break;}
    if($mode==='search'&&preg_match('/(?:همه شهرها|هر شهری|شهر مهم نیست)/u',$s)){$p['city']='';$p['province']='';$state['all_cities']=true;}
    elseif($hasLocation)unset($state['all_cities']);
    $money=nznative_money($s,$stage);
    if(!$money&&!$hasLocation&&!preg_match('/^(?:نه|اصلاح|قیمت|شهر)/u',$s)){
        if($stage==='district'){$p['district']=$skip?'':mb_substr(trim($text),0,100);$state['district_done']=true;}
        elseif($stage==='street'){$p['street']=$skip?'':mb_substr(trim($text),0,180);$state['street_done']=true;}
        elseif($stage==='description'){$state['details']=$skip?'':mb_substr(trim($text),0,2000);$state['description_done']=true;}
    }
    if($mode==='create'){
        if(preg_match('/(?:توافقی|رایگان)/u',$s))$p['price']=0;
        elseif($money)$p['price']=$money[count($money)-1]['value'];
    }else{
        if(preg_match('/(?:بودجه مهم نیست|بدون محدودیت|قیمت مهم نیست|هر قیمتی)/u',$s)){$p['min_price']=null;$p['max_price']=null;$state['any_budget']=true;}
        elseif($money){unset($state['any_budget']);foreach($money as $m)$p[$m['minimum']?'min_price':'max_price']=$m['value'];}
        if($p['min_price']!==null&&$p['max_price']!==null&&$p['min_price']>$p['max_price'])throw new RuntimeException('حداقل بودجه از حداکثر بیشتر است؛ بازه را دوباره مشخص کنید.');
    }
    $subject=nznative_subject($text);$classification=null;
    // A clarification is still an item description, not only an exact taxonomy choice.
    if(!$greeting&&!$skip&&($stage==='subject'||$p['title']===''||in_array($stage,['category','subcategory'],true))){
        if($subject!==''){$p['title']=$subject;$state['subject']=$subject;$classification=nznative_classify($subject,$examples);}
    }elseif(preg_match('/^(?:نه[،, ]+)?(?:دنبال|آگهی جدید|به جاش)/u',$s)&&$subject!==''){
        $classification=nznative_classify($subject,$examples);if($classification&&$classification['subcategory']!==$p['subcategory']){$p['attributes']=[];$state['subject']=$subject;$p['title']=$subject;unset($state['district_done'],$state['street_done'],$state['description_done']);}
    }
    if($classification){$p['category']=$classification['category'];$p['subcategory']=$classification['subcategory'];$state['matched']=$classification['phrase'];$state['learned']=!empty($classification['learned']);}
    $fields=nznative_fields($p['category'],$p['subcategory']);$catalog=nzcv_catalog($p['subcategory']);$changedBrand=false;
    if($catalog&&!preg_match('/(?:نیست|نباشد|نباشه|ندارد|نداره)/u',$s)&&($brand=nznative_brand($s,$catalog,$p['subcategory']))){
        if(($p['attributes']['برند']??'')!==$brand){unset($p['attributes']['مدل']);$changedBrand=true;}$p['attributes']['برند']=$brand;
    }
    if($stage==='attribute'&&!empty($p['next_attribute'])){
        $label=$p['next_attribute'];$allowed=$fields[$label]??'text';if($label==='مدل'&&$catalog)$allowed=$catalog[$p['attributes']['برند']??'']??[];
        if($label==='مدل'&&$catalog){if(!preg_match('/(?:نیست|نباشد|نباشه|ندارد|نداره)/u',$s)&&($model=nznative_model($s,$allowed,true)))$p['attributes']['مدل']=$model;}
        elseif(is_array($allowed)){foreach($allowed as $v)if(nznative_norm($v)===$s){$p['attributes'][$label]=$v;break;}}
        elseif(in_array($allowed,['number','year'],true)){
            if(preg_match('/^[0-9]+$/D',$s)&&($allowed!=='year'||((int)$s>=1300&&(int)$s<=1405)))$p['attributes'][$label]=$s;
        }elseif(!$skip&&mb_strlen($text)<=150)$p['attributes'][$label]=trim($text);
    }
    // Infer only explicit values, never interpret a negated condition as a positive claim.
    if(!preg_match('/(?:نیست|نباشد|نباشه|ندارد|نداره)/u',$s)){
        foreach($fields as $label=>$allowed)if(is_array($allowed)&&!($catalog&&$label==='برند')){usort($allowed,fn($a,$b)=>mb_strlen($b)<=>mb_strlen($a));foreach($allowed as $value)if(nznative_has($s,$value)){$p['attributes'][$label]=$value;break;}}
    }
    if($catalog){$brand=$p['attributes']['برند']??'';if($brand!==''&&!isset($catalog[$brand]))unset($p['attributes']['برند']);if(isset($catalog[$brand])){
        if(isset($p['attributes']['مدل'])&&!in_array($p['attributes']['مدل'],$catalog[$brand],true))unset($p['attributes']['مدل']);
        if(!preg_match('/(?:نیست|نباشد|نباشه|ندارد|نداره)/u',$s)&&($model=nznative_model($s,$catalog[$brand],($p['next_attribute']??'')==='مدل')))$p['attributes']['مدل']=$model;
    }}
    $p['ready']=false;$p['next_attribute']='';$stage='subject';$reply='چه کالا یا خدمتی می‌خواهی '.($mode==='create'?'آگهی کنی؟':'پیدا کنی؟');
    if(!empty($state['subject'])){
        if($p['category']===''){$retry=($p['stage']??'')==='category';$stage='category';$reply=$retry?'هنوز نوع کالا یا خدمت را تشخیص ندادم. نامش را ساده بنویس، مثلاً «گوشی»، «مبل» یا «آپارتمان»؛ یا یک دسته را انتخاب کن.':'این کالا یا خدمت در کدام دسته است؟';$choices=array_keys(nzt_categories());}
        elseif($p['subcategory']===''){$stage='subcategory';$reply='کدام نوع از این دسته مدنظرت است؟';$choices=nzt_categories()[$p['category']]??[];}
        elseif($mode==='create'){
            $stage='review';foreach($fields as $label=>$allowed)if(!isset($p['attributes'][$label])||$p['attributes'][$label]===''){$stage='attribute';$p['next_attribute']=$label;$reply='«'.$label.'» را مشخص کن.';if($label==='مدل'&&$catalog)$allowed=$catalog[$p['attributes']['برند']??'']??[];$choices=is_array($allowed)?array_slice($allowed,0,80):[];break;}
            if($stage==='review'){
                if($p['price']===null){$stage='price';$reply='قیمت پیشنهادی چند تومان است؟ برای قیمت توافقی بنویس «توافقی».';}
                elseif($p['city']===''){$stage='city';$reply='آگهی مربوط به کدام شهر است؟';$choices=$p['province']!==''?(nzt_provinces()[$p['province']]??[]):[];}
                elseif(empty($state['district_done'])){$stage='district';$reply='محلهٔ آگهی کجاست؟ اگر نمی‌خواهی بنویسی، «رد» را بزن.';$choices=['رد'];}
                elseif(empty($state['street_done'])){$stage='street';$reply='آدرس یا خیابان را می‌نویسی؟ می‌توانی «بعداً» را بزنی و موقعیت را روی نقشه تأیید کنی.';$choices=['بعداً'];}
                elseif(empty($state['description_done'])){$stage='description';$reply='نکته یا توضیح دیگری دربارهٔ آگهی داری؟';$choices=['توضیح دیگری ندارم'];}
                else{$p['ready']=true;$reply='پیش‌نویس آماده است. آن را بررسی کن؛ عکس، نقشه و اجازهٔ تماس را در مرحلهٔ بعد تأیید می‌کنی.';}
            }
        }elseif($p['city']===''&&empty($state['all_cities'])){$stage='city';$reply='در کدام شهر جستجو کنم؟';$choices=['همه شهرها'];}
        elseif($p['min_price']===null&&$p['max_price']===null&&empty($state['any_budget'])){$stage='budget';$reply='بازهٔ بودجه چقدر است؟ مثلاً «تا ۲۰ میلیون تومان» یا «بودجه مهم نیست».';$choices=['بودجه مهم نیست'];}
        else{$stage='results';$p['ready']=true;$reply='شرایط جستجو مشخص شد. نتیجهٔ آگهی‌های واقعی سایت در ادامه نمایش داده می‌شود؛ می‌توانی شرایط را اصلاح کنی.';}
    }
    $p['query']='';if($mode==='search'){
        $terms=nznative_tokens((string)($state['subject']??''));$matched=nznative_tokens((string)($state['matched']??$p['subcategory']));$p['query']=implode(' ',array_values(array_diff($terms,$matched,['نو','سالم','کارکرده'])));
    }
    if($mode==='create'&&!empty($state['subject'])){
        $lines=[$state['subject']];foreach($p['attributes'] as $label=>$value)$lines[]=$label.': '.$value;if(!empty($state['details']))$lines[]=$state['details'];$p['description']='مشخصات ثبت‌شده توسط آگهی‌دهنده:'."\n".implode("\n",$lines);
    }
    $p['engine']='native';$p['stage']=$stage;$p['reply']=$reply;$p['choices']=$choices;$p['_native']=$state;
    return nznative_guidance($p,$mode,$draft,$changedBrand);
}
/** Refresh guidance without consuming a user reply, including old saved sessions. */
function nznative_guidance(array $p,string $mode,array $draft=[],bool $changedBrand=false): array {
    $stage=$p['stage']??'subject';$reply=$p['reply']??'';$choices=$p['choices']??[];
    $fields=nznative_fields($p['category']??'',$p['subcategory']??'');$catalog=nzcv_catalog($p['subcategory']??'');
    if($stage==='attribute'){$allowed=$fields[$p['next_attribute']]??'text';if($p['next_attribute']==='مدل'&&$catalog)$allowed=$catalog[$p['attributes']['برند']??'']??[];$choices=is_array($allowed)?array_slice($allowed,0,80):[];}
    $p['hint']='می‌توانی پاسخ را بنویسی یا از گزینه‌های همین مرحله انتخاب کنی.';$p['placeholder']='پاسخت را اینجا بنویس…';
    if($stage==='subject'){$p['hint']=$mode==='create'?'نام کالا یا خدمت را بگو؛ بعد مشخصات، قیمت و محل را قدم‌به‌قدم می‌پرسم.':'نام کالا، شهر و اگر خواستی بودجه‌ات را بنویس.';$p['placeholder']=$mode==='create'?'مثلاً: گوشی سامسونگ A55 برای فروش دارم':'مثلاً: مبل در شیراز تا ۲۰ میلیون تومان';}
    elseif($stage==='attribute'){
        $label=$p['next_attribute'];$example=$choices[0]??'';
        $reply='«'.$label.'» را مشخص کن.';
        if($label==='مدل'&&$catalog){$reply='مدل '.$p['subcategory'].' با برند «'.($p['attributes']['برند']??'').'» چیست؟';$p['hint']='مدل را بنویس یا از فهرست انتخاب کن؛ مثال: '.$example.'. اگر در فهرست نیست، «سایر مدل» را بزن و نام دقیق را در توضیحات آخر بنویس.';$p['placeholder']='مثلاً: '.$example;}
        elseif($choices){$p['hint']='یکی از گزینه‌ها را انتخاب کن یا نامش را بنویس؛ مثلاً «'.$example.'».';$p['placeholder']='مثلاً: '.$example;}
        else{$p['hint']=match($fields[$label]??'text'){'year'=>'سال را چهاررقمی بین ۱۳۰۰ تا ۱۴۰۵ بنویس؛ مثلاً ۱۴۰۲.','number'=>'فقط عدد را بنویس؛ مثلاً ۸۰.',default=>'مقدار «'.$label.'» را کوتاه و دقیق بنویس.'};$p['placeholder']=$label.' را بنویس…';}
        if(($draft['stage']??'')==='attribute'&&($draft['next_attribute']??'')===$label&&!$changedBrand)$reply='این پاسخ با «'.$label.'» تطبیق نداشت. '.$reply;
    }elseif($stage==='price'){$p['hint']='عدد و واحد را بنویس؛ یا برای قیمت توافقی، «توافقی» را بزن.';$p['placeholder']='مثلاً: ۲۵ میلیون تومان';$choices=['توافقی'];}
    elseif($stage==='city'){$p['hint']='نام شهر را بنویس؛ مثلاً «شیراز».'.($mode==='search'?' برای جستجوی سراسری «همه شهرها» را بزن.':'');$p['placeholder']='نام شهر، مثلاً شیراز';}
    elseif($stage==='district'){$p['hint']='نام محله را بنویس یا با «رد» از این مرحله بگذر.';$p['placeholder']='مثلاً: معالی‌آباد';}
    elseif($stage==='street'){$p['hint']='نشانی را بنویس یا «بعداً» را بزن. موقعیت نقشه را در فرم نهایی خودت تأیید می‌کنی.';$p['placeholder']='خیابان و نشانی، یا بعداً';}
    elseif($stage==='description'){$p['hint']='ویژگی‌ها، ایرادها و اگر «سایر مدل» انتخاب کردی، نام دقیق مدل را بنویس؛ یا «توضیح دیگری ندارم» را بزن.';$p['placeholder']='مثلاً: مدل دقیق و ظرفیت، لوازم همراه یا ایراد کالا';}
    elseif($stage==='review'){$p['hint']='خلاصه را بررسی کن و دکمهٔ «تأیید پیش‌نویس و تکمیل عکس و نقشه» را بزن. آگهی هنوز ثبت نشده است.';$p['placeholder']='اگر لازم است، اصلاح قیمت یا شهر را بنویس…';}
    elseif($stage==='budget'){$p['hint']='بازه را به تومان بنویس یا «بودجه مهم نیست» را انتخاب کن.';$p['placeholder']='مثلاً: تا ۲۰ میلیون تومان';}
    $p['reply']=$reply;$p['choices']=$choices;return $p;
}
