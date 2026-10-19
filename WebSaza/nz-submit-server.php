<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-controlled-validation.php';
require_once __DIR__ . '/nz-moderation.php';

function nzss_clean_attributes(mixed $raw): array {
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $key => $value) {
        if (count($out) >= 30) break;
        $label = mb_substr(trim((string)$key), 0, 80);
        if ($label === '' || is_array($value)) continue;
        $val = mb_substr(trim((string)$value), 0, 300);
        if ($val !== '') $out[$label] = $val;
    }
    return $out;
}
function nzss_location_id(string $type, string $value): string {
    $normalized = nzm_normalize($value);
    return $normalized === '' ? '' : $type.'_'.substr(hash('sha256', $normalized),0,12);
}

function nzss_dup_normalize(string $text): string {
    $text = strtr($text, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $text = preg_replace('/(?:\+?98|0)?9\d{9}/u', ' phone ', $text) ?? $text;
    $text = preg_replace('~https?://\S+|www\.\S+~iu', ' ', $text) ?? $text;
    return nzm_normalize($text);
}
function nzss_dup_tokens(string $text): array {
    $stop = array_fill_keys(['این','آن','برای','با','از','به','در','و','یا','که','یک','را','است','هست','شود','شده','می','خیلی','کاملا','کاملاً','فقط','تومان','قیمت','فروش','آگهی','phone'], true);
    $out=[];
    foreach (preg_split('/\s+/u', nzss_dup_normalize($text)) ?: [] as $token) {
        if ($token==='' || mb_strlen($token,'UTF-8')<2 || isset($stop[$token])) continue;
        $out[$token]=true;
    }
    return array_keys($out);
}
function nzss_jaccard(array $a, array $b): float {
    if (!$a || !$b) return 0.0;
    $aa=array_fill_keys($a,true); $bb=array_fill_keys($b,true);
    $intersection=count(array_intersect_key($aa,$bb));
    $union=count($aa+$bb);
    return $union>0 ? ($intersection/$union)*100.0 : 0.0;
}
function nzss_similar_percent(string $a, string $b): float {
    $a=nzss_dup_normalize($a); $b=nzss_dup_normalize($b);
    if ($a==='' || $b==='') return 0.0;
    similar_text(mb_substr($a,0,4500), mb_substr($b,0,4500), $pct);
    return (float)$pct;
}
function nzss_attr_value(array $attrs, array $names): string {
    foreach ($attrs as $k=>$v) if (in_array(nzm_normalize((string)$k), array_map('nzm_normalize',$names), true)) return trim((string)$v);
    return '';
}
function nzss_attrs_text(array $attrs): string {
    $clean=[];
    foreach ($attrs as $k=>$v) {
        if (is_array($v) || trim((string)$v)==='') continue;
        $clean[nzm_normalize((string)$k)] = nzss_dup_normalize((string)$v);
    }
    ksort($clean,SORT_STRING);
    $parts=[]; foreach($clean as $k=>$v)$parts[]=$k.' '.$v;
    return implode(' ', $parts);
}
function nzss_duplicate_fingerprint(string $category,string $subcategory,string $title,string $description,array $attributes,int $price): string {
    $payload=implode('|',[
        nzss_dup_normalize($category), nzss_dup_normalize($subcategory),
        nzss_dup_normalize($title), nzss_dup_normalize($description),
        nzss_attrs_text($attributes), (string)$price
    ]);
    return hash('sha256',$payload);
}
function nzss_price_close(int $a,int $b): bool {
    if ($a<=0 && $b<=0) return true;
    if ($a<=0 || $b<=0) return false;
    $max=max($a,$b); return abs($a-$b)/$max <= 0.12;
}
function nzss_image_resource_for_hash(string $path) {
    $info=@getimagesize($path); $mime=(string)($info['mime']??'');
    if ($mime==='image/jpeg' && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($path);
    if ($mime==='image/png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($path);
    if ($mime==='image/webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
    return false;
}
function nzss_image_dhash(string $path): string {
    if (!is_file($path) || !function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) return '';
    $src=nzss_image_resource_for_hash($path); if(!$src)return'';
    $w=imagesx($src);$h=imagesy($src);if($w<1||$h<1){imagedestroy($src);return'';}
    $dst=imagecreatetruecolor(9,8); imagecopyresampled($dst,$src,0,0,0,0,9,8,$w,$h); imagedestroy($src);
    $bits='';
    for($y=0;$y<8;$y++){
        $gray=[];
        for($x=0;$x<9;$x++){$rgb=imagecolorat($dst,$x,$y);$r=($rgb>>16)&255;$g=($rgb>>8)&255;$b=$rgb&255;$gray[]=(int)round(($r*0.299)+($g*0.587)+($b*0.114));}
        for($x=0;$x<8;$x++)$bits.=$gray[$x]>$gray[$x+1]?'1':'0';
    }
    imagedestroy($dst);
    $hex='';for($i=0;$i<64;$i+=4)$hex.=dechex(bindec(substr($bits,$i,4)));
    return $hex;
}
function nzss_file_fingerprint(string $path): array {
    if (!is_file($path)) return ['sha'=>'','dhash'=>''];
    return ['sha'=>(string)@hash_file('sha256',$path),'dhash'=>nzss_image_dhash($path)];
}
function nzss_uploaded_fingerprints(array $files): array {
    $out=[]; if(empty($files['tmp_name'])||!is_array($files['tmp_name']))return$out;
    $count=min(5,count($files['tmp_name']));
    for($i=0;$i<$count;$i++){
        if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;
        $fp=nzss_file_fingerprint((string)$files['tmp_name'][$i]); if($fp['sha']!==''||$fp['dhash']!=='')$out[]=$fp;
    }
    return$out;
}
function nzss_stored_fingerprints(array $post): array {
    $saved=$post['image_fingerprints']??[];
    if(is_array($saved)&&$saved){$out=[];foreach($saved as$fp)if(is_array($fp))$out[]=['sha'=>(string)($fp['sha']??''),'dhash'=>(string)($fp['dhash']??'')];if($out)return$out;}
    $out=[];foreach(array_slice((array)($post['images']??[]),0,5)as$name){$fp=nzss_file_fingerprint(__DIR__.'/marketplace-uploads/'.basename((string)$name));if($fp['sha']!==''||$fp['dhash']!=='')$out[]=$fp;}return$out;
}
function nzss_hex_hamming(string $a,string $b): int {
    if(strlen($a)!==16||strlen($b)!==16)return 64;
    static $bits=['0'=>0,'1'=>1,'2'=>1,'3'=>2,'4'=>1,'5'=>2,'6'=>2,'7'=>3,'8'=>1,'9'=>2,'a'=>2,'b'=>3,'c'=>2,'d'=>3,'e'=>3,'f'=>4];
    $d=0;for($i=0;$i<16;$i++){$x=dechex(hexdec($a[$i])^hexdec($b[$i]));$d+=$bits[$x]??4;}return$d;
}
function nzss_image_similarity(array $new,array $old): float {
    $best=0.0;
    foreach($new as$a)foreach($old as$b){
        if(!empty($a['sha'])&&!empty($b['sha'])&&hash_equals((string)$a['sha'],(string)$b['sha']))return100.0;
        $da=(string)($a['dhash']??'');$db=(string)($b['dhash']??'');if($da===''||$db==='')continue;
        $dist=nzss_hex_hamming($da,$db);$score=$dist<=4?100:($dist<=8?94:($dist<=12?82:0));if($score>$best)$best=$score;
    }
    return$best;
}
function nzss_duplicate_match(array $db,string $category,string $subcategory,string $title,string $description,array $attributes,int $price,array $imageFingerprints): ?array {
    $newFull=$title.' '.$description.' '.nzss_attrs_text($attributes);
    $newTokens=nzss_dup_tokens($newFull);$newAttrTokens=nzss_dup_tokens(nzss_attrs_text($attributes));
    $newBrand=nzss_dup_normalize(nzss_attr_value($attributes,['برند','برند/سازنده']));
    $newModel=nzss_dup_normalize(nzss_attr_value($attributes,['مدل']));
    $newFp=nzss_duplicate_fingerprint($category,$subcategory,$title,$description,$attributes,$price);
    foreach(array_reverse($db['posts']??[])as$p){
        if(($p['status']??'')==='deleted')continue;
        $oldAttrs=is_array($p['attributes']??null)?$p['attributes']:[];
        $oldFp=(string)($p['duplicate_fingerprint']??'');
        if($oldFp==='' )$oldFp=nzss_duplicate_fingerprint((string)($p['category']??''),(string)($p['subcategory']??''),(string)($p['title']??''),(string)($p['description']??''),$oldAttrs,(int)($p['price']??0));
        if(hash_equals($newFp,$oldFp))return['reason'=>'این آگهی قبلاً با همین محتوا ثبت شده است.','post_id'=>(string)($p['id']??''),'score'=>100];

        $oldTitle=(string)($p['title']??'');$oldFull=$oldTitle.' '.(string)($p['description']??'').' '.nzss_attrs_text($oldAttrs);
        $titleSim=nzss_similar_percent($title,$oldTitle);
        $fullTextSim=nzss_similar_percent($newFull,$oldFull);
        $tokenSim=nzss_jaccard($newTokens,nzss_dup_tokens($oldFull));
        $contentSim=max($fullTextSim,$tokenSim);
        $attrSim=nzss_jaccard($newAttrTokens,nzss_dup_tokens(nzss_attrs_text($oldAttrs)));
        $sameSub=nzss_dup_normalize($subcategory)!==''&&nzss_dup_normalize($subcategory)===nzss_dup_normalize((string)($p['subcategory']??''));
        $sameCategory=nzss_dup_normalize($category)!==''&&nzss_dup_normalize($category)===nzss_dup_normalize((string)($p['category']??''));
        $oldBrand=nzss_dup_normalize(nzss_attr_value($oldAttrs,['برند','برند/سازنده']));
        $oldModel=nzss_dup_normalize(nzss_attr_value($oldAttrs,['مدل']));
        $keyMatch=$newBrand!==''&&$newModel!==''&&$newBrand===$oldBrand&&$newModel===$oldModel;
        $priceClose=nzss_price_close($price,(int)($p['price']??0));
        $imageSim=0.0;
        if($imageFingerprints && ($sameCategory||$sameSub||$titleSim>=55||$contentSim>=55||$keyMatch))$imageSim=nzss_image_similarity($imageFingerprints,nzss_stored_fingerprints($p));

        $duplicate=false;$reason='';$score=(int)round(max($titleSim,$contentSim,$imageSim));
        if($titleSim>=94&&$contentSim>=78){$duplicate=true;$reason='عنوان و محتوای آگهی با آگهی قبلی تقریباً یکسان است.';}
        elseif($contentSim>=92&&$tokenSim>=70){$duplicate=true;$reason='متن و مشخصات آگهی با آگهی قبلی بیش از حد مشابه است.';}
        elseif($sameSub&&$titleSim>=86&&$contentSim>=72&&$attrSim>=50&&$priceClose){$duplicate=true;$reason='عنوان، مشخصات و قیمت با آگهی قبلی تطابق بسیار زیادی دارد.';}
        elseif($imageSim>=94&&($sameSub||$sameCategory)&&($titleSim>=55||$contentSim>=55||$keyMatch)){$duplicate=true;$reason='تصاویر و محتوای آگهی با آگهی قبلی بسیار مشابه است.';}
        elseif($imageSim>=100&&($titleSim>=45||$contentSim>=45||$keyMatch)){$duplicate=true;$reason='تصویر آگهی قبلاً برای همین محتوای مشابه استفاده شده است.';}
        elseif($keyMatch&&$sameSub&&$titleSim>=82&&$attrSim>=70&&$priceClose){$duplicate=true;$reason='برند، مدل، مشخصات و قیمت با آگهی قبلی تطابق غیرعادی دارد.';}
        if($duplicate)return['reason'=>$reason,'post_id'=>(string)($p['id']??''),'score'=>$score,'title_similarity'=>round($titleSim,1),'content_similarity'=>round($contentSim,1),'attribute_similarity'=>round($attrSim,1),'image_similarity'=>round($imageSim,1)];
    }
    return null;
}
function nzss_reject_duplicate(array $user,array $match,string $title): never {
    $reason=(string)($match['reason']??'این آگهی تکراری تشخیص داده شد.');
    nzc_db_write(function(&$db)use($user,$title,$reason,$match){
        $msg='ثبت آگهی «'.$title.'» به دلیل تکراری بودن رد شد. '.$reason.' تغییر شهر، شماره تماس یا حساب کاربری مجوز ثبت دوباره همان آگهی نیست.';
        nzc_support_message($db,(string)$user['id'],$msg);
        nzc_notify($db,(string)$user['id'],'آگهی «'.$title.'» به دلیل تکراری بودن رد شد.');
        $db['reports'][]=['id'=>nzc_uid('r'),'post_id'=>(string)($match['post_id']??''),'post_title'=>$title,'reporter_id'=>'system-duplicate','reason'=>'تلاش برای ثبت آگهی تکراری','details'=>mb_substr($reason.' | امتیاز شباهت: '.(int)($match['score']??0),0,800),'priority'=>'high','status'=>'open','created_at'=>nzc_now()];
    });
    nzc_flash('این آگهی به دلیل تکراری یا بسیار مشابه بودن با آگهی قبلی رد شد. تغییر شهر یا شماره موبایل باعث مجاز شدن ثبت مجدد نمی‌شود.','error');
    nzc_redirect('/nz?page=new');
}

function nzss_handle_create(): never {
    nzc_session_open();
    $db = nzc_db_read();
    $user = nzc_require_user($db);
    if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');

    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $subcategory = trim((string)($_POST['subcategory'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $district = mb_substr(trim((string)($_POST['district'] ?? '')), 0, 100);
    $street = mb_substr(trim((string)($_POST['street'] ?? '')), 0, 180);
    $mode = (string)($_POST['location_mode'] ?? 'exact');
    if (!in_array($mode, ['exact','approx'], true)) $mode = 'exact';

    if (mb_strlen($title) < 5) throw new RuntimeException('عنوان آگهی حداقل ۵ حرف باشد.');
    if (mb_strlen($desc) < 15) throw new RuntimeException('توضیحات آگهی را کامل‌تر بنویسید.');
    if ($category === '' || $subcategory === '') throw new RuntimeException('دسته و زیر‌دسته آگهی را انتخاب کنید.');
    if ($province === '' || $city === '') throw new RuntimeException('استان و شهر آگهی را انتخاب کنید.');

    $latRaw = $_POST['latitude'] ?? null;
    $lngRaw = $_POST['longitude'] ?? null;
    if (!is_numeric($latRaw) || !is_numeric($lngRaw)) throw new RuntimeException('موقعیت آگهی را روی نقشه مشخص کنید.');
    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if ($lat < 24 || $lat > 40.5 || $lng < 43 || $lng > 64) throw new RuntimeException('موقعیت انتخاب‌شده خارج از محدوده قابل قبول است.');
    if ($mode === 'approx') { $lat = round($lat, 3); $lng = round($lng, 3); }

    $attributes = nzss_clean_attributes($_POST['attributes'] ?? []);
    nzcv_validate($category, $subcategory, $attributes);
    $price=max(0,(int)($_POST['price']??0));

    $imageFingerprints=nzss_uploaded_fingerprints($_FILES['images']??[]);
    $duplicate=nzss_duplicate_match($db,$category,$subcategory,$title,$desc,$attributes,$price,$imageFingerprints);
    if($duplicate!==null)nzss_reject_duplicate($user,$duplicate,$title);

    $moderation = nzm_scan_submission($title, $desc, $attributes);

    $images = nzcp_process_images($_FILES['images'] ?? []);
    if (!$images) throw new RuntimeException('حداقل یک عکس برای آگهی انتخاب کنید.');
    [$video, $videoDuration] = nzcp_process_video($_FILES['video'] ?? [], (float)($_POST['video_duration'] ?? 0));

    $provinceId=nzss_location_id('province',$province);
    $cityId=nzss_location_id('city',$province.'|'.$city);
    $districtId=$district!==''?nzss_location_id('district',$province.'|'.$city.'|'.$district):'';
    $post = [
        'id' => nzc_uid('p'),
        'user_id' => $user['id'],
        'title' => $title,
        'description' => $desc,
        'category' => $category,
        'subcategory' => $subcategory,
        'province' => $province,
        'province_id' => $provinceId,
        'city' => $city,
        'city_id' => $cityId,
        'district' => $district,
        'district_id' => $districtId,
        'street' => $street,
        'latitude' => $lat,
        'longitude' => $lng,
        'location_mode' => $mode,
        'location' => ['province'=>$province,'province_id'=>$provinceId,'city'=>$city,'city_id'=>$cityId,'district'=>$district,'district_id'=>$districtId,'street'=>$street,'lat'=>$lat,'lng'=>$lng,'mode'=>$mode],
        'attributes' => $attributes,
        'price' => $price,
        'images' => $images,
        'image_fingerprints'=>$imageFingerprints,
        'duplicate_fingerprint'=>nzss_duplicate_fingerprint($category,$subcategory,$title,$desc,$attributes,$price),
        'video' => $video,
        'video_duration' => $videoDuration,
        'chat_enabled' => isset($_POST['chat_enabled']),
        'phone_visible' => isset($_POST['phone_visible']),
        'status' => 'pending',
        'review_status' => 'pending',
        'review_priority' => !empty($moderation['flagged']) ? 'high' : 'normal',
        'moderation' => $moderation,
        'featured' => false,
        'views' => 0,
        'created_at' => nzc_now(),
        'submitted_at' => nzc_now(),
        'updated_at' => nzc_now(),
    ];

    nzc_db_write(function (&$db) use ($post) {
        $db['posts'][] = $post;
        if (!empty($post['moderation']['flagged'])) nzm_add_system_report($db, $post);
        $suffix = !empty($post['moderation']['flagged']) ? ' این آگهی توسط پایش خودکار برای بررسی فوری مدیر علامت‌گذاری شده است.' : '';
        nzc_support_message($db, $post['user_id'], 'آگهی «'.$post['title'].'» دریافت شد و برای بررسی مدیر ارسال شد. تا زمان تأیید مدیر برای سایر کاربران نمایش داده نمی‌شود.'.$suffix);
        nzc_notify($db, $post['user_id'], 'آگهی «'.$post['title'].'» در انتظار تأیید مدیر است.');
    });

    if (!empty($moderation['flagged'])) {
        nzc_flash('آگهی ثبت شد و به دلیل تشخیص محتوای مشکوک با اولویت بالا برای بررسی مدیر ارسال شد. تا تصمیم مدیر منتشر نمی‌شود.', 'warn');
    } else {
        nzc_flash('آگهی برای بررسی مدیر ارسال شد. نتیجه تأیید یا رد در چت پشتیبانی برای شما فرستاده می‌شود.', 'ok');
    }
    nzc_redirect('/nz?page=myposts');
}
