<?php
declare(strict_types=1);

function nzcp_media_dir(): string {$dir=__DIR__.'/marketplace-uploads';if(!is_dir($dir))@mkdir($dir,0755,true);return$dir;}
function nzcp_disabled_function(string $name): bool {$disabled=array_filter(array_map('trim',explode(',',(string)ini_get('disable_functions'))));return in_array($name,$disabled,true);}
function nzcp_find_binary(string $name): ?string {foreach(['/usr/bin/'.$name,'/usr/local/bin/'.$name,'/opt/local/bin/'.$name]as$p)if(is_file($p)&&is_executable($p))return$p;if(!function_exists('shell_exec')||nzcp_disabled_function('shell_exec'))return null;$out=trim((string)@shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));return$out!==''?$out:null;}
function nzcp_image_resource(string $tmp,string $mime){if($mime==='image/jpeg'&&function_exists('imagecreatefromjpeg'))return@imagecreatefromjpeg($tmp);if($mime==='image/png'&&function_exists('imagecreatefrompng'))return@imagecreatefrompng($tmp);if($mime==='image/webp'&&function_exists('imagecreatefromwebp'))return@imagecreatefromwebp($tmp);return false;}
function nzcp_orient_jpeg($img,string $tmp){if(!function_exists('exif_read_data'))return$img;$exif=@exif_read_data($tmp);$o=(int)($exif['Orientation']??1);if($o===3)return imagerotate($img,180,0);if($o===6)return imagerotate($img,-90,0);if($o===8)return imagerotate($img,90,0);return$img;}
function nzcp_owner_prefix(string $ownerId): string {return $ownerId===''?'anon':substr(hash('sha256','media|'.$ownerId),0,12);}
function nzcp_media_usage_bytes(int $since=0,?string $prefix=null): int {$sum=0;$pattern=nzcp_media_dir().'/'.($prefix!==null?$prefix.'-*':'*');foreach(glob($pattern)?:[]as$f){if(!is_file($f))continue;if($since>0&&filemtime($f)<$since)continue;$sum+=(int)filesize($f);}return$sum;}
function nzcp_assert_capacity(int $incoming,string $ownerId=''): void {
    $free=@disk_free_space(nzcp_media_dir());if(is_numeric($free)&&$free<$incoming+256*1024*1024)throw new RuntimeException('فضای ذخیره‌سازی موقتاً کافی نیست. کمی بعد دوباره تلاش کنید.');
    if(nzcp_media_usage_bytes(strtotime('today'))+$incoming>768*1024*1024)throw new RuntimeException('سقف روزانه بارگذاری رسانه موقتاً تکمیل شده است. کمی بعد دوباره تلاش کنید.');
    if($ownerId!==''&&nzcp_media_usage_bytes(0,nzcp_owner_prefix($ownerId))+$incoming>512*1024*1024)throw new RuntimeException('سقف فضای رسانه این حساب تکمیل شده است. برای آزادسازی فضا آگهی‌های قدیمی را مدیریت کنید.');
}
function nzcp_delete_media(array $names): void {foreach($names as$n){$b=basename((string)$n);if($b!=='')@unlink(nzcp_media_dir().'/'.$b);}}
function nzcp_process_images(array $files,string $ownerId=''): array {
    if(empty($files['name'])||!is_array($files['name']))return[];$dir=nzcp_media_dir();$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$count=min(5,count($files['name']));$out=[];$prefix=nzcp_owner_prefix($ownerId);
    try{for($i=0;$i<$count;$i++){
        if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;if(($files['error'][$i]??1)!==UPLOAD_ERR_OK)throw new RuntimeException('بارگذاری یکی از عکس‌ها ناموفق بود.');$tmp=(string)$files['tmp_name'][$i];$size=(int)($files['size'][$i]??0);if($size<=0)throw new RuntimeException('فایل عکس خالی است.');nzcp_assert_capacity($size,$ownerId);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!isset($allowed[$mime]))throw new RuntimeException('فرمت عکس باید JPG، PNG یا WebP باشد.');
        $info=@getimagesize($tmp);if(!$info||$info[0]<1||$info[1]<1||$info[0]*$info[1]>60000000)throw new RuntimeException('ابعاد تصویر قابل پردازش نیست؛ عکس دیگری انتخاب کن.');$src=nzcp_image_resource($tmp,$mime);if(!$src)throw new RuntimeException('پردازش امن تصویر روی سرور ممکن نیست؛ لطفاً تصویر دیگری انتخاب کنید.');if($mime==='image/jpeg')$src=nzcp_orient_jpeg($src,$tmp);$w=imagesx($src);$h=imagesy($src);if($w<1||$h<1||$w*$h>60000000){imagedestroy($src);throw new RuntimeException('ابعاد تصویر قابل قبول نیست.');}$max=1800;$ratio=min(1,$max/max($w,$h));$nw=max(1,(int)round($w*$ratio));$nh=max(1,(int)round($h*$ratio));$dst=imagecreatetruecolor($nw,$nh);imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefill($dst,0,0,$transparent);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);$base=$prefix.'-'.date('YmdHis').'-'.bin2hex(random_bytes(8));$saved=false;
        if(function_exists('imagewebp')){$name=$base.'.webp';$saved=@imagewebp($dst,$dir.'/'.$name,84);}elseif(function_exists('imagejpeg')){$name=$base.'.jpg';$canvas=imagecreatetruecolor($nw,$nh);$white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);imagecopy($canvas,$dst,0,0,0,0,$nw,$nh);$saved=@imagejpeg($canvas,$dir.'/'.$name,86);imagedestroy($canvas);}imagedestroy($dst);imagedestroy($src);if(!$saved){nzcp_delete_media($out);throw new RuntimeException('ذخیره امن تصویر انجام نشد.');}@chmod($dir.'/'.$name,0644);$out[]=$name;
    }}catch(Throwable $e){nzcp_delete_media($out);throw $e;}return$out;
}
function nzcp_vint($f,bool $id=false): ?array {
    $b=fread($f,1);if($b==='')return null;$v=ord($b);$mask=128;$len=1;while($len<=8&&!($v&$mask)){$mask>>=1;$len++;}if($len>8)return null;$value=$id?$v:($v&($mask-1));$unknown=!$id&&$value===$mask-1;
    for($i=1;$i<$len;$i++){$b=fread($f,1);if($b==='')return null;$n=ord($b);$unknown=$unknown&&$n===255;$value=($value<<8)|$n;}return[$value,$len,$unknown];
}
function nzcp_container_duration(string $path): ?float {
    $f=fopen($path,'rb');if(!$f)return null;$size=filesize($path);$head=fread($f,12);$duration=null;
    try{
        if(substr($head,4,4)==='ftyp'){
            $walk=function(int $end,int $depth=0)use(&$walk,$f,&$duration){if($depth>4)return;while(ftell($f)+8<=$end){$start=ftell($f);$h=fread($f,8);$len=unpack('N',substr($h,0,4))[1];$kind=substr($h,4,4);if($len<8||$start+$len>$end)break;if($kind==='moov')$walk($start+$len,$depth+1);elseif($kind==='mvhd'){$raw=fread($f,min($len-8,36));$version=ord($raw[0]??chr(0));$offset=$version===1?20:12;if(strlen($raw)>=$offset+($version===1?12:8)){$scale=unpack('N',substr($raw,$offset,4))[1];$ticks=$version===1?unpack('J',substr($raw,$offset+4,8))[1]:unpack('N',substr($raw,$offset+4,4))[1];if($scale>0)$duration=$ticks/$scale;}}fseek($f,$start+$len);}};
            rewind($f);$walk($size);
        }elseif(substr($head,0,4)==="\x1a\x45\xdf\xa3"){
            $scale=1000000;$ticks=0;$base=0;$count=0;
            $walk=function(int $end,int $depth=0)use(&$walk,$f,&$duration,&$scale,&$ticks,&$base,&$count,$size){if($depth>8)return;while(ftell($f)<$end&&++$count<200000){$id=nzcp_vint($f,true);$len=nzcp_vint($f);if(!$id||!$len)break;$at=ftell($f);$next=$len[2]?$end:$at+$len[0];if($next>$end||$next<$at)break;
                if(in_array($id[0],[0x18538067,0x1f43b675],true)){if(!$len[2])$walk($next,$depth+1);else continue;}
                elseif($id[0]===0x1549a966)$walk($next,$depth+1);
                elseif(in_array($id[0],[0x2ad7b1,0xe7],true)&&$len[0]<=8){$v=0;foreach(unpack('C*',fread($f,$len[0])) as $n)$v=($v<<8)|$n;if($id[0]===0xe7){$base=$v;$ticks=max($ticks,$base);}else $scale=$v;}
                elseif($id[0]===0x4489&&in_array($len[0],[4,8],true)){$raw=fread($f,$len[0]);$duration=unpack($len[0]===4?'G':'E',$raw)[1];}
                elseif($id[0]===0xa3&&$len[0]>=4){$track=nzcp_vint($f);$raw=fread($f,2);if($track&&strlen($raw)===2){$rel=unpack('n',$raw)[1];if($rel>32767)$rel-=65536;$ticks=max($ticks,$base+$rel+42);}}
                fseek($f,$next);
            }};
            rewind($f);$walk($size);$duration=($duration??$ticks)*$scale/1000000000;
        }
    }finally{fclose($f);}return is_numeric($duration)&&is_finite((float)$duration)&&$duration>0?(float)$duration:null;
}
function nzcp_probe_duration(string $tmp): ?float {
    $ffprobe=nzcp_find_binary('ffprobe');if($ffprobe&&function_exists('shell_exec')&&!nzcp_disabled_function('shell_exec')){$cmd=escapeshellarg($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($tmp).' 2>/dev/null';$raw=trim((string)@shell_exec($cmd));if(is_numeric($raw))return(float)$raw;}
    return nzcp_container_duration($tmp);
}
function nzcp_process_video(array $file,float $clientDuration,string $ownerId='',bool $staged=false): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return[null,null];if(($file['error']??1)!==UPLOAD_ERR_OK)throw new RuntimeException('بارگذاری ویدیو ناموفق بود.');$tmp=(string)$file['tmp_name'];$size=(int)($file['size']??0);if($size<=0)throw new RuntimeException('فایل ویدیو خالی است.');nzcp_assert_capacity($size,$ownerId);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$allowed=['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm'];if(!isset($allowed[$mime]))throw new RuntimeException('فرمت ویدیو باید MP4، MOV یا WebM باشد.');
    $duration=nzcp_probe_duration($tmp);if($duration===null)throw new RuntimeException('امکان بررسی امن ویدیو روی سرور فعال نیست؛ فعلاً بدون ویدیو آگهی را ثبت کنید.');if($duration<=0)throw new RuntimeException('ویدیو قابل خواندن نیست.');$dir=nzcp_media_dir();$base=nzcp_owner_prefix($ownerId).'-'.date('YmdHis').'-'.bin2hex(random_bytes(8));$ffmpeg=nzcp_find_binary('ffmpeg');
    if($ffmpeg&&function_exists('shell_exec')&&!nzcp_disabled_function('shell_exec')){$name=$base.'.mp4';$dest=$dir.'/'.$name;$filter="scale='min(1280,iw)':-2:force_original_aspect_ratio=decrease";$cmd=escapeshellarg($ffmpeg).' -y -i '.escapeshellarg($tmp).' -map_metadata -1 -map_chapters -1 -vf '.escapeshellarg($filter).' -c:v libx264 -preset veryfast -crf 27 -pix_fmt yuv420p -c:a aac -b:a 96k -movflags +faststart '.escapeshellarg($dest).' 2>&1';@shell_exec($cmd);if(is_file($dest)&&filesize($dest)>0){@chmod($dest,0644);return[$name,$duration];}@unlink($dest);throw new RuntimeException('پردازش امن ویدیو انجام نشد؛ لطفاً بدون ویدیو ثبت کنید.');}
    $name=$base.'.'.$allowed[$mime];if(!($staged?rename($tmp,$dir.'/'.$name):move_uploaded_file($tmp,$dir.'/'.$name)))throw new RuntimeException('ذخیره ویدیو انجام نشد.');@chmod($dir.'/'.$name,0644);return[$name,$duration];
}
function nzcp_handle_create(): never {throw new RuntimeException('مسیر قدیمی ثبت آگهی غیرفعال است.');}