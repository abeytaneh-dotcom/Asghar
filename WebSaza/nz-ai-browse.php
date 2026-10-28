<?php
declare(strict_types=1);
require_once __DIR__.'/nz-ai-core.php';
require_once __DIR__.'/nz-demo-posts.php';
/** Only existing eligible ads provide facets. Catalogue guesses never become options. */
function nzab_posts(array $db,?array $viewer,bool $showcase=false): array {
    $items=[];foreach(array_merge($showcase?nzdemo_posts():[],$db['posts']??[]) as $post)$items[(string)$post['id']]=$post;
    return array_values(array_filter($items,function($p)use($db,$viewer){
        if(!nzai_public(array_replace($p,['demo'=>false])))return false;$seller=(string)($p['user_id']??'');
        if(function_exists('nzc_user_blocked')&&nzc_user_blocked($db,$seller))return false;
        return !$viewer||!function_exists('nzcc_blocked')||!nzcc_blocked($db,(string)$viewer['id'],$seller);
    }));
}
function nzab_demo(array $post): bool {return !empty($post['demo'])||str_starts_with((string)($post['id']??''),'demo_');}
function nzab_search(array $posts,array $filters,int $limit=12): array {
    $visible=array_map(fn($post)=>array_replace($post,['demo'=>false]),$posts);
    return nzai_search($visible,$filters,$limit);
}
function nzab_safe(string $key,string $value): bool {
    if($value===''||mb_strlen($value)>80||mb_strlen($key)>60)return false;
    if(preg_match('/شماره|تماس|نشانی|آدرس|پلاک|کد ملی|سریال|imei|phone|address|latitude|longitude|password|token/ui',$key))return false;
    if(preg_match('/https?:|@|(?:09[0-9]{9})/u',nznative_norm($value)))return false;
    return !preg_match('/^09[0-9]{9}$/D',preg_replace('/\D/','',nznative_norm($value))??'');
}
function nzab_groups(array $posts,array $p): array {
    $matches=nzab_search($posts,$p,50000);$ids=array_fill_keys(array_column($matches,'id'),true);$groups=[];
    foreach($posts as $post){if(!isset($ids[(string)$post['id']]))continue;
        $attrs=is_array($post['attributes']??null)?$post['attributes']:[];
        if(!isset($attrs['برند'])&&isset($attrs['برند/سازنده']))$attrs['برند']=$attrs['برند/سازنده'];unset($attrs['برند/سازنده']);
        foreach(['subcategory'=>'__type','city'=>'شهر'] as $field=>$label)if(!empty($post[$field]))$attrs[$label]=$post[$field];
        foreach($attrs as $key=>$value){if(!is_scalar($value)||!nzab_safe((string)$key,(string)$value))continue;$value=trim((string)$value);$norm=nzai_norm($value);$groups[$key][$norm]??=['value'=>$value,'count'=>0];$groups[$key][$norm]['count']++;}
    }
    foreach($groups as &$values){$values=array_values($values);usort($values,fn($a,$b)=>$b['count']<=>$a['count']?:strcmp($a['value'],$b['value']));}unset($values);
    return $groups;
}
function nzab_base(array $draft): array {
    $p=array_replace(['reply'=>'','title'=>'','description'=>'','category'=>'','subcategory'=>'','province'=>'','city'=>'','district'=>'','street'=>'','query'=>'','price'=>null,'min_price'=>null,'max_price'=>null,'attributes'=>[]],$draft);
    $p['_browse']=$p['_browse']??['subject'=>(string)($draft['_native']['subject']??$draft['title']??''),'offset'=>0];
    if(($draft['engine']??'')!=='browse')$p['query']=implode(' ',nzab_terms((string)$p['query']));unset($p['_native']);$p['engine']='browse';$p['choices']=[];$p['next_attribute']='';$p['placeholder']='بنویس یا از گزینه‌ها و آگهی‌ها انتخاب کن…';return $p;
}
function nzab_terms(string $s): array {
    $stop=['خوام','خواستم','میخواستم','میخواهم','بخوام','میخوام','میخام','ببینم','بفرست','بفرستی','نشان','بهم','فقط','اصلاح','همه','شهرها','هر','شهری','مهم','نیست','بدون','محدودیت','چیزی','لازم','دارین','هستین'];
    return array_values(array_diff(nznative_tokens($s),$stop));
}
function nzab_match(string $text,string $key,array $options): ?string {
    $values=array_column($options,'value');$s=nznative_norm($text);
    foreach($values as $v)if(nznative_norm($v)===$s)return $v;
    if($key==='مدل')return nznative_model($text,$values,true);
    $matches=[];foreach($values as $v)if(nznative_has($text,$v))$matches[]=$v;
    usort($matches,fn($a,$b)=>mb_strlen($b)<=>mb_strlen($a));if(!$matches)return null;
    foreach(array_slice($matches,1) as $v)if(!nznative_has($matches[0],$v))return null;
    return $matches[0];
}
function nzab_plan(array $posts,string $message,array $draft=[],array $examples=[],?array $selection=null): array {
    $s=nznative_norm($message);if(in_array($s,['از اول','شروع دوباره','گفتگوی تازه'],true))$draft=[];
    $p=nzab_base($draft);$p['_browse']['offset']=0;
    if(preg_match('/^(?:همه آگهی ها|همه آگهیها|همه آگهی های موجود|همه آگهی های سایت)$/u',$s)){$p=nzab_base([]);$p['_browse']['subject']='همه آگهی‌ها';$message='';}
    if($selection!==null){
        $key=(string)($selection['key']??'');$value=(string)($selection['value']??'');
        if($value===''){
            if($key==='شهر'){$p['city']='';$p['province']='';}elseif($key==='__type')$p['subcategory']='';elseif($key==='بودجه'){$p['min_price']=null;$p['max_price']=null;}else unset($p['attributes'][$key]);
            if(in_array($key,['برند','__type'],true))unset($p['attributes']['مدل']);
        }else{
            $groups=nzab_groups($posts,$p);$options=$groups[$key]??[];$chosen=null;foreach($options as $o)if($o['value']===$value)$chosen=$value;
            if($chosen===null)throw new RuntimeException('این گزینه دیگر در آگهی‌های موجود نیست؛ نتیجه‌ها را تازه کن.');
            if($key==='شهر'){$p['city']=$chosen;foreach(nzt_provinces() as $prov=>$cities)if(in_array($chosen,$cities,true)){$p['province']=$prov;break;}}
            elseif($key==='__type'){$p['subcategory']=$chosen;foreach(nzt_categories() as $cat=>$subs)if(in_array($chosen,$subs,true)){$p['category']=$cat;break;}}
            else $p['attributes'][$key]=$chosen;
            if($key==='برند')unset($p['attributes']['مدل']);
        }
    }elseif($message!==''){
        $greeting=preg_match('/^(?:سلام|درود|سلام خوبی|سلام چطوری)[!؟.\s]*$/u',$s);
        if(!$greeting){
            if(preg_match('/همه شهرها|هر شهری|شهر مهم نیست/u',$s)){$p['province']='';$p['city']='';$p['district']='';}
            else foreach(nzt_provinces() as $prov=>$cities){foreach($cities as $city)if(nznative_has($s,$city)){$p['province']=$prov;$p['city']=$city;break 2;}}
            if(preg_match('/بودجه مهم نیست|بدون محدودیت|قیمت مهم نیست|هر قیمتی/u',$s)){$p['min_price']=null;$p['max_price']=null;}
            elseif(preg_match('/میلیون|میلیارد|هزار|تومان|تومن|ریال|(?:بودجه|قیمت|حداقل|حداکثر|از|تا|زیر|بالای)\s*-?\s*[0-9]/u',$s))foreach(nznative_money($s,'results') as $m)$p[$m['minimum']?'min_price':'max_price']=$m['value'];
            if($p['min_price']!==null&&$p['max_price']!==null&&$p['min_price']>$p['max_price'])throw new RuntimeException('بازهٔ بودجه را دوباره بنویس؛ حداقل باید از حداکثر کمتر باشد.');
            $subject=nznative_subject($message);$terms=nzab_terms($subject);$classification=nznative_classify($subject,$examples);
            // Existing item facets take precedence over a brand alias classified as a different product.
            $entityScope=$p;$entityScope['attributes']=[];$groups=nzab_groups($posts,$entityScope);$hits=[];$removed=[];
            foreach($groups as $key=>$options){if(in_array($key,['__type','شهر'],true))continue;$v=nzab_match($s,$key,$options);if($v!==null){$hits[$key]=$v;$removed=array_merge($removed,nznative_tokens($v));}}
            $remainder=array_values(array_diff($terms,$removed,['برند','مدل','رنگ','وضعیت','متراژ','اتاق','سال','ساخت','نوع','معامله']));
            if($hits&&!$remainder)$classification=null;
            if($classification){
                if($p['subcategory']!==$classification['subcategory'])$p['attributes']=[];
                $p['category']=$classification['category'];$p['subcategory']=$classification['subcategory'];
                $entityScope=$p;$entityScope['attributes']=[];$entityScope['query']='';$groups=nzab_groups($posts,$entityScope);$hits=[];$removed=[];
                foreach($groups as $key=>$options){if(in_array($key,['__type','شهر'],true))continue;if(($v=nzab_match($s,$key,$options))!==null){$hits[$key]=$v;$removed=array_merge($removed,nznative_tokens($v));}}
                $removed=array_merge($removed,nznative_tokens($p['subcategory']));
                $broadAliases=['موبایل'=>['گوشی','گوشیم'],'خودرو'=>['ماشین'],'آپارتمان'=>['اپارتمان'],'خانه و ویلا'=>['ویلا','خانه ویلایی'],'لپ‌تاپ'=>['لپ تاپ','لپتاپ']];
                foreach($broadAliases[$p['subcategory']]??[] as $alias)if(nznative_has($subject,$alias))$removed=array_merge($removed,nznative_tokens($alias));
                foreach(array_filter($examples,fn($e)=>($e['status']??'')==='approved') as $seed)if(($seed['category']??'')===$p['category']&&($seed['subcategory']??'')===$p['subcategory']&&nznative_has($subject,(string)($seed['phrase']??'')))$removed=array_merge($removed,nznative_tokens($seed['phrase']));
                $p['query']=implode(' ',array_values(array_diff($terms,$removed,['نو','کارکرده','سالم'])));$p['title']=$subject;$p['_browse']['subject']=$subject;
            }elseif($remainder){
                $p['category']='';$p['subcategory']='';$p['attributes']=[];$p['query']=implode(' ',$remainder);$p['title']=$subject;$p['_browse']['subject']=$subject;
                // Unknown products are searched as keywords, not sent to a category questionnaire.
                $groups=nzab_groups($posts,$p);$hits=[];foreach($groups as $key=>$options)if(!in_array($key,['__type','شهر'],true)&&($v=nzab_match($s,$key,$options))!==null)$hits[$key]=$v;
            }
            if(!preg_match('/نیست|نباشد|نباشه|ندارد|نداره/u',$s))foreach(array_replace(array_intersect_key($hits,['برند'=>true]),$hits) as $key=>$v){if($key==='برند'&&($p['attributes'][$key]??'')!==$v)unset($p['attributes']['مدل']);$p['attributes'][$key]=$v;}
        }
    }
    $p['ready']=!empty($p['_browse']['subject'])||$p['category']!==''||$p['query']!==''||!empty($p['attributes']);if(empty($p['_browse']['subject'])&&!empty($p['attributes']))$p['_browse']['subject']=implode(' ',array_values($p['attributes']));$p['stage']='results';
    $p['reply']=$p['ready']?'این آگهی‌ها با خواسته‌ات مرتبط‌اند. می‌توانی گزینه‌ها را بزنی، آگهی انتخاب کنی یا پیام بعدی را بنویسی.':'اسم کالا یا چیزی که لازم داری را بنویس؛ از بین آگهی‌های موجود پیدایش می‌کنم.';
    return $p;
}
function nzab_view(array $posts,array $p): array {
    $matches=empty($p['ready'])?[]:nzab_search($posts,$p,50000);$count=count($matches);$offset=max(0,(int)($p['_browse']['offset']??0));$results=array_slice($matches,$offset,12);$byId=[];foreach($posts as $post)$byId[(string)$post['id']]=$post;
    foreach($results as &$result){$post=$byId[$result['id']];$result['demo']=nzab_demo($post);$result['attributes']=[];foreach($post['attributes']??[] as $k=>$v)if(is_scalar($v)&&nzab_safe((string)$k,(string)$v))$result['attributes'][$k]=(string)$v;$image=basename((string)($post['images'][0]??''));$result['image']=$image!==''&&preg_match('/\.(jpe?g|png|webp)$/i',$image)?'/marketplace-uploads/'.rawurlencode($image):'';if($result['image']===''&&$result['demo']&&parse_url((string)($post['demo_image']??''),PHP_URL_HOST)==='images.unsplash.com')$result['image']=$post['demo_image'];}unset($result);
    $groups=$count?nzab_groups($posts,$p):[];$order=['برند','مدل','__type','نوع معامله','نوع محصول/خدمت','نوع دستگاه/تجهیز','عنوان شغلی','نوع همکاری','وضعیت','تعداد اتاق','متراژ','سال ساخت','رنگ'];$keys=array_unique(array_merge($order,array_keys($groups),['شهر']));$facet=null;
    foreach($keys as $key){if(!isset($groups[$key])||isset($p['attributes'][$key]))continue;if($key==='__type'&&($p['subcategory']!==''||count($groups[$key])<2))continue;if($key==='شهر'&&$p['city']!=='')continue;$facet=['key'=>$key,'label'=>$key==='__type'?'نوع':$key,'options'=>array_slice($groups[$key],0,60)];break;}
    $selected=[];foreach($p['attributes']??[] as $key=>$value)if(nzab_safe((string)$key,(string)$value))$selected[]=['key'=>$key,'value'=>$value];if($p['city']!=='')$selected[]=['key'=>'شهر','value'=>$p['city']];if($p['min_price']!==null||$p['max_price']!==null)$selected[]=['key'=>'بودجه','value'=>($p['min_price']??0).' تا '.($p['max_price']??'∞').' تومان'];
    return ['results'=>$results,'facet'=>$facet,'selected'=>$selected,'total'=>$count,'has_more'=>$offset+12<$count,'offset'=>$offset];
}
