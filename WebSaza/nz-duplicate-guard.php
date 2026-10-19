<?php
declare(strict_types=1);

function nzdg_norm(string $text): string {
    $text=strtr($text,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $text=preg_replace('/(?:\+?98|0)?9\d{9}/u',' phone ',$text)??$text;
    $text=preg_replace('~https?://\S+|www\.\S+~iu',' ',$text)??$text;
    return nzm_normalize($text);
}
function nzdg_tokens(string $text): array {
    $stop=array_fill_keys(['این','آن','برای','با','از','به','در','و','یا','که','یک','را','است','هست','شود','شده','می','خیلی','کاملا','کاملاً','فقط','تومان','قیمت','فروش','آگهی','phone'],true);
    $out=[];
    foreach(preg_split('/\s+/u',nzdg_norm($text))?:[] as$t){if($t===''||mb_strlen($t,'UTF-8')<2||isset($stop[$t]))continue;$out[$t]=true;}
    return array_keys($out);
}
function nzdg_jaccard(array $a,array $b): float {
    if(!$a||!$b)return 0.0;$aa=array_fill_keys($a,true);$bb=array_fill_keys($b,true);$i=count(array_intersect_key($aa,$bb));$u=count($aa+$bb);return$u?($i/$u)*100.0:0.0;
}
function nzdg_sim(string $a,string $b): float {
    $a=nzdg_norm($a);$b=nzdg_norm($b);if($a===''||$b==='')return 0.0;similar_text(mb_substr($a,0,4500),mb_substr($b,0,4500),$pct);return(float)$pct;
}
function nzdg_attr(array $attrs,array $names): string {
    $wanted=array_map('nzm_normalize',$names);foreach($attrs as$k=>$v)if(in_array(nzm_normalize((string)$k),$wanted,true))return trim((string)$v);return'';
}
function nzdg_attrs_text(array $attrs): string {
    $clean=[];foreach($attrs as$k=>$v){if(is_array($v)||trim((string)$v)==='')continue;$clean[nzm_normalize((string)$k)]=nzdg_norm((string)$v);}ksort($clean,SORT_STRING);$p=[];foreach($clean as$k=>$v)$p[]=$k.' '.$v;return implode(' ',$p);
}
function nzdg_fingerprint(string $category,string $subcategory,string $title,string $description,array $attributes,int $price): string {
    return hash('sha256',implode('|',[nzdg_norm($category),nzdg_norm($subcategory),nzdg_norm($title),nzdg_norm($description),nzdg_attrs_text($attributes),(string)$price]));
}
function nzdg_price_close(int $a,int $b): bool {if($a<=0&&$b<=0)return true;if($a<=0||$b<=0)return false;return abs($a-$b)/max($a,$b)<=0.12;}
function nzdg_img_resource(string $path){$i=@getimagesize($path);$m=(string)($i['mime']??'');if($m==='image/jpeg'&&function_exists('imagecreatefromjpeg'))return@imagecreatefromjpeg($path);if($m==='image/png'&&function_exists('imagecreatefrompng'))return@imagecreatefrompng($path);if($m==='image/webp'&&function_exists('imagecreatefromwebp'))return@imagecreatefromwebp($path);return false;}
function nzdg_dhash(string $path): string {
    if(!is_file($path)||!function_exists('imagecreatetruecolor')||!function_exists('imagecopyresampled'))return'';$src=nzdg_img_resource($path);if(!$src)return'';$w=imagesx($src);$h=imagesy($src);if($w<1||$h<1){imagedestroy($src);return'';}$dst=imagecreatetruecolor(9,8);imagecopyresampled($dst,$src,0,0,0,0,9,8,$w,$h);imagedestroy($src);$bits='';for($y=0;$y<8;$y++){$gray=[];for($x=0;$x<9;$x++){$rgb=imagecolorat($dst,$x,$y);$r=($rgb>>16)&255;$g=($rgb>>8)&255;$b=$rgb&255;$gray[]=(int)round($r*.299+$g*.587+$b*.114);}for($x=0;$x<8;$x++)$bits.=$gray[$x]>$gray[$x+1]?'1':'0';}imagedestroy($dst);$hex='';for($i=0;$i<64;$i+=4)$hex.=dechex(bindec(substr($bits,$i,4)));return$hex;
}
function nzdg_file_fp(string $path): array {if(!is_file($path))return['sha'=>'','dhash'=>''];return['sha'=>(string)@hash_file('sha256',$path),'dhash'=>nzdg_dhash($path)];}
function nzdg_upload_fps(array $files): array {$out=[];if(empty($files['tmp_name'])||!is_array($files['tmp_name']))return$out;$n=min(5,count($files['tmp_name']));for($i=0;$i<$n;$i++){if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;$fp=nzdg_file_fp((string)$files['tmp_name'][$i]);if($fp['sha']!==''||$fp['dhash']!=='')$out[]=$fp;}return$out;}
function nzdg_post_fps(array $post): array {$saved=$post['image_fingerprints']??[];if(is_array($saved)&&$saved){$o=[];foreach($saved as$fp)if(is_array($fp))$o[]=['sha'=>(string)($fp['sha']??''),'dhash'=>(string)($fp['dhash']??'')];if($o)return$o;}$o=[];foreach(array_slice((array)($post['images']??[]),0,5)as$n){$fp=nzdg_file_fp(__DIR__.'/marketplace-uploads/'.basename((string)$n));if($fp['sha']!==''||$fp['dhash']!=='')$o[]=$fp;}return$o;}
function nzdg_hamming(string $a,string $b): int {if(strlen($a)!==16||strlen($b)!==16)return 64;static$bits=['0'=>0,'1'=>1,'2'=>1,'3'=>2,'4'=>1,'5'=>2,'6'=>2,'7'=>3,'8'=>1,'9'=>2,'a'=>2,'b'=>3,'c'=>2,'d'=>3,'e'=>3,'f'=>4];$d=0;for($i=0;$i<16;$i++){$x=dechex(hexdec($a[$i])^hexdec($b[$i]));$d+=$bits[$x]??4;}return$d;}
function nzdg_image_sim(array $new,array $old): float {$best=0.0;foreach($new as$a)foreach($old as$b){if(!empty($a['sha'])&&!empty($b['sha'])&&hash_equals((string)$a['sha'],(string)$b['sha']))return 100.0;$da=(string)($a['dhash']??'');$db=(string)($b['dhash']??'');if($da===''||$db==='')continue;$dist=nzdg_hamming($da,$db);$score=$dist<=4?100:($dist<=8?94:($dist<=12?82:0));if($score>$best)$best=$score;}return$best;}

function nzdg_match(array $db,string $category,string $subcategory,string $title,string $description,array $attributes,int $price,array $newImages): ?array {
    $newFull=$title.' '.$description.' '.nzdg_attrs_text($attributes);$newTokens=nzdg_tokens($newFull);$newAttrTokens=nzdg_tokens(nzdg_attrs_text($attributes));$newBrand=nzdg_norm(nzdg_attr($attributes,['برند','برند/سازنده']));$newModel=nzdg_norm(nzdg_attr($attributes,['مدل']));$newFp=nzdg_fingerprint($category,$subcategory,$title,$description,$attributes,$price);
    foreach(array_reverse($db['posts']??[])as$p){if(($p['status']??'')==='deleted')continue;$oldAttrs=is_array($p['attributes']??null)?$p['attributes']:[];$oldFp=(string)($p['duplicate_fingerprint']??'');if($oldFp==='')$oldFp=nzdg_fingerprint((string)($p['category']??''),(string)($p['subcategory']??''),(string)($p['title']??''),(string)($p['description']??''),$oldAttrs,(int)($p['price']??0));if(hash_equals($newFp,$oldFp))return['reason'=>'این آگهی قبلاً با همین محتوا ثبت شده است.','post_id'=>(string)($p['id']??''),'score'=>100];
        $oldTitle=(string)($p['title']??'');$oldFull=$oldTitle.' '.(string)($p['description']??'').' '.nzdg_attrs_text($oldAttrs);$titleSim=nzdg_sim($title,$oldTitle);$fullSim=nzdg_sim($newFull,$oldFull);$tokenSim=nzdg_jaccard($newTokens,nzdg_tokens($oldFull));$contentSim=max($fullSim,$tokenSim);$attrSim=nzdg_jaccard($newAttrTokens,nzdg_tokens(nzdg_attrs_text($oldAttrs)));$sameSub=nzdg_norm($subcategory)!==''&&nzdg_norm($subcategory)===nzdg_norm((string)($p['subcategory']??''));$sameCat=nzdg_norm($category)!==''&&nzdg_norm($category)===nzdg_norm((string)($p['category']??''));$oldBrand=nzdg_norm(nzdg_attr($oldAttrs,['برند','برند/سازنده']));$oldModel=nzdg_norm(nzdg_attr($oldAttrs,['مدل']));$keyMatch=$newBrand!==''&&$newModel!==''&&$newBrand===$oldBrand&&$newModel===$oldModel;$priceClose=nzdg_price_close($price,(int)($p['price']??0));$imageSim=0.0;if($newImages&&($sameCat||$sameSub||$titleSim>=55||$contentSim>=55||$keyMatch))$imageSim=nzdg_image_sim($newImages,nzdg_post_fps($p));$dup=false;$reason='';$score=(int)round(max($titleSim,$contentSim,$imageSim));
        if($titleSim>=94&&$contentSim>=78){$dup=true;$reason='عنوان و محتوای آگهی با آگهی قبلی تقریباً یکسان است.';}elseif($contentSim>=92&&$tokenSim>=70){$dup=true;$reason='متن و مشخصات آگهی با آگهی قبلی بیش از حد مشابه است.';}elseif($sameSub&&$titleSim>=86&&$contentSim>=72&&$attrSim>=50&&$priceClose){$dup=true;$reason='عنوان، مشخصات و قیمت با آگهی قبلی تطابق بسیار زیادی دارد.';}elseif($imageSim>=94&&($sameSub||$sameCat)&&($titleSim>=55||$contentSim>=55||$keyMatch)){$dup=true;$reason='تصاویر و محتوای آگهی با آگهی قبلی بسیار مشابه است.';}elseif($imageSim>=100&&($titleSim>=45||$contentSim>=45||$keyMatch)){$dup=true;$reason='تصویر آگهی قبلاً برای همین محتوای مشابه استفاده شده است.';}elseif($keyMatch&&$sameSub&&$titleSim>=82&&$attrSim>=70&&$priceClose){$dup=true;$reason='برند، مدل، مشخصات و قیمت با آگهی قبلی تطابق غیرعادی دارد.';}if($dup)return['reason'=>$reason,'post_id'=>(string)($p['id']??''),'score'=>$score];
    }
    return null;
}
function nzdg_reject(array $user,array $match,string $title): never {
    $reason=(string)($match['reason']??'این آگهی تکراری تشخیص داده شد.');nzc_db_write(function(&$db)use($user,$title,$reason,$match){$msg='ثبت آگهی «'.$title.'» به دلیل تکراری بودن رد شد. '.$reason.' تغییر شهر، شماره تماس یا حساب کاربری مجوز ثبت دوباره همان آگهی نیست.';nzc_support_message($db,(string)$user['id'],$msg);nzc_notify($db,(string)$user['id'],'آگهی «'.$title.'» به دلیل تکراری بودن رد شد.');$db['reports'][]=['id'=>nzc_uid('r'),'post_id'=>(string)($match['post_id']??''),'post_title'=>$title,'reporter_id'=>'system-duplicate','reason'=>'تلاش برای ثبت آگهی تکراری','details'=>mb_substr($reason.' | امتیاز شباهت: '.(int)($match['score']??0),0,800),'priority'=>'high','status'=>'open','created_at'=>nzc_now()];});nzc_flash('این آگهی به دلیل تکراری یا بسیار مشابه بودن با آگهی قبلی رد شد. تغییر شهر یا شماره موبایل باعث مجاز شدن ثبت مجدد نمی‌شود.','error');nzc_redirect('/nz?page=new');
}
function nzdg_guard(array $db,array $user,string $category,string $subcategory,string $title,string $description,array $attributes,int $price,array $files): array {$images=nzdg_upload_fps($files);$match=nzdg_match($db,$category,$subcategory,$title,$description,$attributes,$price,$images);if($match!==null)nzdg_reject($user,$match,$title);return['image_fingerprints'=>$images,'duplicate_fingerprint'=>nzdg_fingerprint($category,$subcategory,$title,$description,$attributes,$price)];}
