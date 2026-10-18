<?php
declare(strict_types=1);

function nzcp_media_dir(): string {
    $dir = __DIR__ . '/marketplace-uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}
function nzcp_disabled_function(string $name): bool {
    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    return in_array($name, $disabled, true);
}
function nzcp_find_binary(string $name): ?string {
    foreach (['/usr/bin/'.$name, '/usr/local/bin/'.$name, '/opt/local/bin/'.$name] as $p) {
        if (is_file($p) && is_executable($p)) return $p;
    }
    if (!function_exists('shell_exec') || nzcp_disabled_function('shell_exec')) return null;
    $out = trim((string)@shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));
    return $out !== '' ? $out : null;
}
function nzcp_image_resource(string $tmp, string $mime) {
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($tmp);
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($tmp);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($tmp);
    return false;
}
function nzcp_orient_jpeg($img, string $tmp) {
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($tmp);
    $o = (int)($exif['Orientation'] ?? 1);
    if ($o === 3) return imagerotate($img, 180, 0);
    if ($o === 6) return imagerotate($img, -90, 0);
    if ($o === 8) return imagerotate($img, 90, 0);
    return $img;
}
function nzcp_process_images(array $files): array {
    if (empty($files['name']) || !is_array($files['name'])) return [];
    $dir = nzcp_media_dir();
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $count = min(5, count($files['name']));
    $out = [];
    for ($i=0; $i<$count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری یکی از عکس‌ها ناموفق بود.');
        $tmp = (string)$files['tmp_name'][$i];
        $size = (int)($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > 20*1024*1024) throw new RuntimeException('حجم اولیه هر عکس باید کمتر از ۲۰ مگابایت باشد.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('فرمت عکس باید JPG، PNG یا WebP باشد.');
        $base = date('YmdHis').'-'.bin2hex(random_bytes(5));
        $src = nzcp_image_resource($tmp, $mime);
        $saved = false;
        if ($src) {
            if ($mime === 'image/jpeg') $src = nzcp_orient_jpeg($src, $tmp);
            $w=imagesx($src); $h=imagesy($src); $max=1800;
            $ratio=min(1,$max/max($w,$h));
            $nw=max(1,(int)round($w*$ratio)); $nh=max(1,(int)round($h*$ratio));
            $dst=imagecreatetruecolor($nw,$nh);
            imagealphablending($dst,false); imagesavealpha($dst,true);
            $transparent=imagecolorallocatealpha($dst,0,0,0,127); imagefill($dst,0,0,$transparent);
            imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
            if (function_exists('imagewebp')) {
                $name=$base.'.webp'; $saved=@imagewebp($dst,$dir.'/'.$name,84);
            } elseif (function_exists('imagejpeg')) {
                $name=$base.'.jpg';
                $canvas=imagecreatetruecolor($nw,$nh); $white=imagecolorallocate($canvas,255,255,255); imagefill($canvas,0,0,$white);
                imagecopy($canvas,$dst,0,0,0,0,$nw,$nh); $saved=@imagejpeg($canvas,$dir.'/'.$name,86); imagedestroy($canvas);
            }
            imagedestroy($dst); imagedestroy($src);
        }
        if (!$saved) {
            $name=$base.'.'.$allowed[$mime];
            if (!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('ذخیره عکس انجام نشد.');
        }
        $out[]=$name;
    }
    return $out;
}
function nzcp_probe_duration(string $tmp): ?float {
    $ffprobe=nzcp_find_binary('ffprobe');
    if (!$ffprobe || !function_exists('shell_exec') || nzcp_disabled_function('shell_exec')) return null;
    $cmd=escapeshellarg($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($tmp).' 2>/dev/null';
    $raw=trim((string)@shell_exec($cmd));
    return is_numeric($raw)?(float)$raw:null;
}
function nzcp_process_video(array $file, float $clientDuration): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null,null];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری ویدیو ناموفق بود.');
    $tmp=(string)$file['tmp_name']; $size=(int)($file['size']??0);
    if ($size<=0 || $size>120*1024*1024) throw new RuntimeException('حجم اولیه ویدیو باید کمتر از ۱۲۰ مگابایت باشد.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed=['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','application/octet-stream'=>'mp4'];
    if (!isset($allowed[$mime])) throw new RuntimeException('فرمت ویدیو باید MP4، MOV یا WebM باشد.');
    $duration=nzcp_probe_duration($tmp) ?? $clientDuration;
    if ($duration<=0) throw new RuntimeException('مدت ویدیو قابل تشخیص نیست؛ دوباره ویدیو را انتخاب کنید.');
    if ($duration>60.5) throw new RuntimeException('ویدیو باید حداکثر ۱ دقیقه باشد.');
    $dir=nzcp_media_dir(); $base=date('YmdHis').'-'.bin2hex(random_bytes(5));
    $ffmpeg=nzcp_find_binary('ffmpeg');
    if ($ffmpeg && function_exists('shell_exec') && !nzcp_disabled_function('shell_exec')) {
        $name=$base.'.mp4'; $dest=$dir.'/'.$name; $filter="scale='min(1280,iw)':-2:force_original_aspect_ratio=decrease";
        $cmd=escapeshellarg($ffmpeg).' -y -i '.escapeshellarg($tmp).' -t 60 -vf '.escapeshellarg($filter).' -c:v libx264 -preset veryfast -crf 27 -pix_fmt yuv420p -c:a aac -b:a 96k -movflags +faststart '.escapeshellarg($dest).' 2>&1';
        @shell_exec($cmd);
        if (is_file($dest) && filesize($dest)>0) return [$name,$duration];
        @unlink($dest);
    }
    $name=$base.'.'.$allowed[$mime];
    if (!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('ذخیره ویدیو انجام نشد.');
    return [$name,$duration];
}

function nzcp_handle_create(): never {
    nzc_session_open();
    $db=nzc_db_read();
    $user=nzc_require_user($db);
    if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    $title=trim((string)($_POST['title']??''));
    $desc=trim((string)($_POST['description']??''));
    if (mb_strlen($title)<5) throw new RuntimeException('عنوان آگهی حداقل ۵ حرف باشد.');
    if (mb_strlen($desc)<15) throw new RuntimeException('توضیحات را کمی کامل‌تر بنویسید.');
    $images=nzcp_process_images($_FILES['images']??[]);
    [$video,$videoDuration]=nzcp_process_video($_FILES['video']??[],(float)($_POST['video_duration']??0));
    $post=[
        'id'=>nzc_uid('p'),'user_id'=>$user['id'],'title'=>$title,'description'=>$desc,
        'category'=>trim((string)($_POST['category']??'')),'subcategory'=>trim((string)($_POST['subcategory']??'')),
        'city'=>trim((string)($_POST['city']??'')),'district'=>trim((string)($_POST['district']??'')),
        'price'=>(int)($_POST['price']??0),'images'=>$images,'video'=>$video,'video_duration'=>$videoDuration,
        'chat_enabled'=>isset($_POST['chat_enabled']),'phone_visible'=>isset($_POST['phone_visible']),
        'status'=>'pending','review_status'=>'pending','featured'=>false,'views'=>0,
        'created_at'=>nzc_now(),'submitted_at'=>nzc_now(),'updated_at'=>nzc_now()
    ];
    nzc_db_write(function(&$db)use($post){
        $db['posts'][]=$post;
        $text='آگهی «'.$post['title'].'» دریافت شد و برای بررسی مدیر ارسال شد. تا زمان تأیید مدیر برای سایر کاربران نمایش داده نمی‌شود.';
        nzc_support_message($db,$post['user_id'],$text);
        nzc_notify($db,$post['user_id'],'آگهی «'.$post['title'].'» در انتظار تأیید مدیر است.');
    });
    nzc_flash('آگهی برای بررسی مدیر ارسال شد. نتیجه تأیید یا رد در چت پشتیبانی برای شما فرستاده می‌شود.','ok');
    nzc_redirect('/nz?page=myposts');
}
