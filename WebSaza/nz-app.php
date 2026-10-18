<?php
declare(strict_types=1);

$nzCreatePost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'create_post');
if ($nzCreatePost) {
    $_POST['action'] = 'create_post_media';
}

function nz_media_dir(): string {
    $dir = __DIR__ . '/marketplace-uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function nz_disabled_function(string $name): bool {
    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    return in_array($name, $disabled, true);
}

function nz_find_binary(string $name): ?string {
    foreach (['/usr/bin/'.$name, '/usr/local/bin/'.$name, '/opt/local/bin/'.$name] as $p) {
        if (is_file($p) && is_executable($p)) return $p;
    }
    if (!function_exists('shell_exec') || nz_disabled_function('shell_exec')) return null;
    $out = trim((string)@shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));
    return $out !== '' ? $out : null;
}

function nz_image_resource(string $tmp, string $mime) {
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($tmp);
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($tmp);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($tmp);
    return false;
}

function nz_orient_jpeg($img, string $tmp) {
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($tmp);
    $o = (int)($exif['Orientation'] ?? 1);
    if ($o === 3) return imagerotate($img, 180, 0);
    if ($o === 6) return imagerotate($img, -90, 0);
    if ($o === 8) return imagerotate($img, 90, 0);
    return $img;
}

function nz_process_images(array $files): array {
    if (empty($files['name']) || !is_array($files['name'])) return [];
    $dir = nz_media_dir();
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $count = min(5, count($files['name']));
    $out = [];

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری یکی از عکس‌ها ناموفق بود.');
        $tmp = (string)$files['tmp_name'][$i];
        $size = (int)($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > 20 * 1024 * 1024) throw new RuntimeException('حجم اولیه هر عکس باید کمتر از ۲۰ مگابایت باشد.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('فرمت عکس باید JPG، PNG یا WebP باشد.');

        $base = date('YmdHis').'-'.bin2hex(random_bytes(5));
        $src = nz_image_resource($tmp, $mime);
        $saved = false;

        if ($src) {
            if ($mime === 'image/jpeg') $src = nz_orient_jpeg($src, $tmp);
            $w = imagesx($src); $h = imagesy($src);
            $max = 1800;
            $ratio = min(1, $max / max($w, $h));
            $nw = max(1, (int)round($w * $ratio));
            $nh = max(1, (int)round($h * $ratio));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh,$w,$h);

            if (function_exists('imagewebp')) {
                $name = $base.'.webp';
                $saved = @imagewebp($dst, $dir.'/'.$name, 84);
            } elseif (function_exists('imagejpeg')) {
                $name = $base.'.jpg';
                $canvas = imagecreatetruecolor($nw, $nh);
                $white = imagecolorallocate($canvas,255,255,255);
                imagefill($canvas,0,0,$white);
                imagecopy($canvas,$dst,0,0,0,0,$nw,$nh);
                $saved = @imagejpeg($canvas, $dir.'/'.$name, 86);
                imagedestroy($canvas);
            }
            imagedestroy($dst);
            imagedestroy($src);
        }

        if (!$saved) {
            $name = $base.'.'.$allowed[$mime];
            if (!move_uploaded_file($tmp, $dir.'/'.$name)) throw new RuntimeException('ذخیره عکس انجام نشد.');
        }
        $out[] = $name;
    }
    return $out;
}

function nz_probe_duration(string $tmp): ?float {
    $ffprobe = nz_find_binary('ffprobe');
    if (!$ffprobe || !function_exists('shell_exec') || nz_disabled_function('shell_exec')) return null;
    $cmd = escapeshellarg($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($tmp).' 2>/dev/null';
    $raw = trim((string)@shell_exec($cmd));
    return is_numeric($raw) ? (float)$raw : null;
}

function nz_process_video(array $file, float $clientDuration): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری ویدیو ناموفق بود.');
    $tmp = (string)$file['tmp_name'];
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 120 * 1024 * 1024) throw new RuntimeException('حجم اولیه ویدیو باید کمتر از ۱۲۰ مگابایت باشد.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','application/octet-stream'=>'mp4'];
    if (!isset($allowed[$mime])) throw new RuntimeException('فرمت ویدیو باید MP4، MOV یا WebM باشد.');

    $duration = nz_probe_duration($tmp) ?? $clientDuration;
    if ($duration <= 0) throw new RuntimeException('مدت ویدیو قابل تشخیص نیست؛ دوباره ویدیو را انتخاب کنید.');
    if ($duration > 60.5) throw new RuntimeException('ویدیو باید حداکثر ۱ دقیقه باشد.');

    $dir = nz_media_dir();
    $base = date('YmdHis').'-'.bin2hex(random_bytes(5));
    $ffmpeg = nz_find_binary('ffmpeg');
    if ($ffmpeg && function_exists('shell_exec') && !nz_disabled_function('shell_exec')) {
        $name = $base.'.mp4';
        $dest = $dir.'/'.$name;
        $filter = "scale='min(1280,iw)':-2:force_original_aspect_ratio=decrease";
        $cmd = escapeshellarg($ffmpeg)
             .' -y -i '.escapeshellarg($tmp)
             .' -t 60 -vf '.escapeshellarg($filter)
             .' -c:v libx264 -preset veryfast -crf 27 -pix_fmt yuv420p'
             .' -c:a aac -b:a 96k -movflags +faststart '
             .escapeshellarg($dest).' 2>&1';
        @shell_exec($cmd);
        if (is_file($dest) && filesize($dest) > 0) return [$name, $duration];
        @unlink($dest);
    }

    $name = $base.'.'.$allowed[$mime];
    if (!move_uploaded_file($tmp, $dir.'/'.$name)) throw new RuntimeException('ذخیره ویدیو انجام نشد.');
    return [$name, $duration];
}

ob_start();
require __DIR__ . '/marketplace.php';
$html = ob_get_clean();

if ($nzCreatePost) {
    $db = db_read();
    $user = require_user($db);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (mb_strlen($title) < 5) throw new RuntimeException('عنوان آگهی حداقل ۵ حرف باشد.');
    if (mb_strlen($desc) < 15) throw new RuntimeException('توضیحات را کمی کامل‌تر بنویسید.');

    $images = nz_process_images($_FILES['images'] ?? []);
    $clientDuration = (float)($_POST['video_duration'] ?? 0);
    [$video, $videoDuration] = nz_process_video($_FILES['video'] ?? [], $clientDuration);

    $post = [
        'id'=>uid('p'),'user_id'=>$user['id'],'title'=>$title,'description'=>$desc,
        'category'=>trim($_POST['category']??''),'subcategory'=>trim($_POST['subcategory']??''),
        'city'=>trim($_POST['city']??''),'district'=>trim($_POST['district']??''),
        'price'=>(int)($_POST['price']??0),'images'=>$images,'video'=>$video,'video_duration'=>$videoDuration,
        'chat_enabled'=>isset($_POST['chat_enabled']),'phone_visible'=>isset($_POST['phone_visible']),
        'status'=>'published','featured'=>false,'views'=>0,'created_at'=>now(),'updated_at'=>now()
    ];
    db_write(function(&$db)use($post){
        $db['posts'][]=$post;
        notify_user($db,$post['user_id'],'آگهی «'.$post['title'].'» منتشر شد.');
    });
    flash('آگهی با موفقیت منتشر شد.');
    redirect('/nz?id='.urlencode($post['id']));
}

if ($html !== false) {
    $html = str_replace(
        ['بازار وب‌ساز', 'بازار وب ساز', '/marketplace.php', '/niazestan.webmanifest'],
        ['نیازستان', 'نیازستان', '/nz', '/nz-manifest.json'],
        $html
    );

    $brandLockup = '<span class="niazestan-lockup"><img class="niazestan-brand-logo" src="/niazestan-logo.svg" alt=""><span class="niazestan-wordmark">نیازستان</span></span>';
    $html = str_replace('<span class="brand-mark">ن</span><span>نیازستان</span>', $brandLockup, $html);

    $html = preg_replace(
        '/<div class="install-hint">.*?<\/div><nav class="category-strip">/su',
        '<nav class="category-strip">',
        $html,
        1
    ) ?? $html;

    $html = str_replace(
        '<form class="mobile-search" method="get"><span>⌕</span>',
        '<form class="mobile-search" method="get"><span class="mobile-search-icon" aria-hidden="true"></span>',
        $html
    );

    $oldMedia = '<div class="field"><label>عکس‌ها</label><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="subtle">حداکثر ۸ تصویر، هر تصویر تا ۵ مگابایت</small></div>';
    $newMedia = '<div class="field full nz-media-field"><label>عکس‌ها</label><input id="nzImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="subtle">حداکثر ۵ عکس؛ سیستم بعد از ارسال، حجم عکس‌ها را با حفظ کیفیت مناسب کم می‌کند.</small><div id="nzImagesInfo" class="nz-media-info"></div></div><div class="field full nz-media-field"><label>ویدیو</label><input id="nzVideo" type="file" name="video" accept="video/mp4,video/quicktime,video/webm"><input type="hidden" name="video_duration" id="nzVideoDuration" value="0"><small class="subtle">حداکثر ۱ ویدیو تا ۱ دقیقه؛ ویدیو برای پخش سریع‌تر در وب بهینه می‌شود.</small><div id="nzVideoInfo" class="nz-media-info"></div></div>';
    $html = str_replace($oldMedia, $newMedia, $html);

    if (!empty($post) && !($post['demo'] ?? false) && (!empty($post['images']) || !empty($post['video']))) {
        $items = '';
        foreach (($post['images'] ?? []) as $img) {
            $src = '/marketplace-uploads/'.rawurlencode(basename((string)$img));
            $items .= '<div class="nz-slide"><img src="'.e($src).'" alt="'.e($post['title'] ?? '').'"></div>';
        }
        if (!empty($post['video'])) {
            $vsrc = '/marketplace-uploads/'.rawurlencode(basename((string)$post['video']));
            $items .= '<div class="nz-slide"><video controls playsinline preload="metadata" src="'.e($vsrc).'"></video></div>';
        }
        $count = count($post['images'] ?? []) + (!empty($post['video']) ? 1 : 0);
        $gallery = '<main class="detail"><div class="detail-media nz-gallery"><div class="nz-gallery-track">'.$items.'</div>'.($count>1?'<div class="nz-gallery-count">'.$count.' رسانه · افقی بکشید</div>':'').'</div><section class="detail-info">';
        $html = preg_replace('/<main class="detail"><div class="detail-media">.*?<\/div><section class="detail-info">/su', $gallery, $html, 1) ?? $html;
    }

    $installButton = <<<'HTML'
<button type="button" id="installMini" class="install-mini" aria-label="نصب برنامه نیازستان روی گوشی">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>
<span>نصب برنامه</span>
</button>
HTML;

    $html = preg_replace('/(<header class="mobile-head"><div class="mobile-row">)/', '$1'.$installButton, $html, 1) ?? $html;

    $css = <<<'HTML'
<style id="niazestan-install-style">
.niazestan-lockup{display:inline-flex;align-items:center;gap:7px;line-height:1;text-decoration:none}.niazestan-brand-logo{display:block;width:46px;height:46px;object-fit:contain}.niazestan-wordmark{font-family:Tahoma,"Vazirmatn",Arial,sans-serif;font-weight:950;color:#073b79;font-size:19px;letter-spacing:-.4px}.brand .niazestan-lockup{flex-direction:row}.mobile-brand .niazestan-lockup{flex-direction:column;gap:0}.mobile-brand .niazestan-brand-logo{width:50px;height:42px}.mobile-brand .niazestan-wordmark{font-size:12.5px;margin-top:-1px;letter-spacing:-.3px}
.mobile-row{position:relative;direction:ltr;min-height:54px}.mobile-row>*{direction:rtl}.mobile-location{font-weight:850;background:#fff!important;border:1px solid #e3e7ec!important;box-shadow:0 3px 12px rgba(20,35,60,.05);padding-right:31px!important}
.install-mini{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:25;border:2px solid #111;background:#ffd83d;box-shadow:0 5px 14px rgba(0,0,0,.18);border-radius:999px;height:34px;padding:0 12px;color:#e31b23;font-family:Tahoma,"Vazirmatn",Arial,sans-serif;font-size:13px;font-weight:950;line-height:1;display:inline-flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap}.install-mini svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.5}.install-mini:active{transform:translate(-50%,-50%) scale(.96)}
.mobile-search{position:relative;background:#fff!important;border:1px solid #e6e9ee!important;box-shadow:0 6px 20px rgba(28,42,65,.07);height:48px!important;border-radius:16px!important;padding:0 44px 0 13px!important}.mobile-search-icon{position:absolute;right:13px;top:50%;transform:translateY(-50%);width:22px;height:22px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230b1f3a' stroke-width='2.1' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-4-4'/%3E%3C/svg%3E") center/contain no-repeat}.mobile-search::after{content:'';position:absolute;left:13px;top:50%;transform:translateY(-50%);width:22px;height:22px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230b1f3a' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='M4 7h10M18 7h2M10 12h10M4 12h2M4 17h6M14 17h6'/%3E%3Ccircle cx='16' cy='7' r='2'/%3E%3Ccircle cx='8' cy='12' r='2'/%3E%3Ccircle cx='12' cy='17' r='2'/%3E%3C/svg%3E") center/contain no-repeat}.mobile-search input{padding-left:25px!important;font-weight:650;color:#252a32}.mobile-search input::placeholder{color:#a6abb3}
.nz-media-field input[type=file]{padding:10px;background:#fafbfc}.nz-media-info{font-size:11px;color:#68707a;margin-top:6px;min-height:15px}.nz-gallery{overflow:hidden!important}.nz-gallery-track{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none}.nz-gallery-track::-webkit-scrollbar{display:none}.nz-slide{min-width:100%;scroll-snap-align:start;background:#111}.nz-slide img,.nz-slide video{display:block;width:100%;aspect-ratio:4/3;object-fit:contain;background:#111}.nz-gallery-count{position:absolute;left:12px;bottom:12px;background:rgba(0,0,0,.62);color:#fff;border-radius:999px;padding:5px 9px;font-size:10px;z-index:3}
.install-sheet,.nz-category-sheet{position:fixed;inset:0;z-index:200;background:rgba(15,18,22,.42);display:none;align-items:flex-end;justify-content:center;padding:16px}.install-sheet.show,.nz-category-sheet.show{display:flex}.install-sheet-card,.nz-category-card{width:min(100%,440px);background:#fff;border-radius:22px;padding:18px 18px calc(18px + env(safe-area-inset-bottom,0px));box-shadow:0 20px 60px rgba(0,0,0,.2);direction:rtl}.install-sheet-card h3,.nz-category-card h3{margin:0 0 12px;font-size:17px}.install-sheet-card p{margin:0;color:#626970;line-height:1.9;font-size:13px}.install-sheet-card .install-ok{width:100%;margin-top:14px;min-height:44px;border:0;border-radius:13px;background:#b4232c;color:#fff;font:800 13px Tahoma,Arial,sans-serif;cursor:pointer}.nz-category-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.nz-category-grid a{min-height:45px;border:1px solid #e8eaee;border-radius:12px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-weight:800;font-size:12px;background:#fafafa}
@media (max-width:760px){
.category-strip{display:grid!important;grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:2px!important;padding:4px 0 11px!important;margin:0!important;overflow:visible!important}.cat-chip{min-width:0!important;width:auto!important;padding:4px 1px!important;gap:4px!important;font-size:9.2px!important;font-weight:800!important;color:#252a32!important;overflow:hidden!important}.cat-chip:nth-child(n+7){display:none!important}.cat-chip span:last-child{display:block;width:100%;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.cat-icon{width:38px!important;height:38px!important;border-radius:11px!important;background:#f3f4f7!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7);font-size:0!important}.cat-icon svg{width:22px!important;height:22px!important;display:block}.cat-icon.c-blue{background:#dcecff!important;color:#1769e8}.cat-icon.c-green{background:#dcf7e5!important;color:#06a84f}.cat-icon.c-pink{background:#ffe0eb!important;color:#f01868}.cat-icon.c-purple{background:#e8e0ff!important;color:#773ce3}.cat-icon.c-orange{background:#ffe8d8!important;color:#f56a12}.cat-icon.c-teal{background:#d9f5f3!important;color:#079f9b}.cat-icon.c-yellow{background:#fff0c6!important;color:#f3a500}.cat-icon.c-violet{background:#ece9ff!important;color:#6254ce}
.section-head h1,.section-head h2{font-size:17px!important;font-weight:950}.section-head h1::before,.section-head h2::before{content:none!important}.section-head p{font-size:11px!important}
.content-grid section .listings{display:block!important;overflow:visible!important;border-top:1px solid #eceef1!important;padding:0!important;scroll-snap-type:none!important}.content-grid section .listing{width:100%!important;display:grid!important;grid-template-columns:minmax(0,1fr) 116px!important;gap:12px!important;border:0!important;border-bottom:1px solid #eceef1!important;border-radius:0!important;padding:12px 0!important;overflow:visible!important;box-shadow:none!important;background:#fff!important;min-height:140px}.content-grid section .listing:hover{box-shadow:none!important;transform:none!important}.content-grid section .listing-media{grid-column:2!important;grid-row:1!important;width:116px!important;height:116px!important;aspect-ratio:1!important;border-radius:12px!important;overflow:hidden!important}.content-grid section .listing-media img{width:100%!important;height:100%!important;object-fit:cover!important}.content-grid section .listing-body{grid-column:1!important;grid-row:1!important;padding:1px 0!important;display:flex!important;flex-direction:column!important;min-width:0!important;min-height:116px!important;text-align:right!important}.content-grid section .listing-title{font-weight:850!important;font-size:14px!important;line-height:1.75!important;height:auto!important;max-height:50px!important;margin:0 0 7px!important;overflow:hidden!important}.content-grid section .listing-price{margin-top:auto!important;margin-bottom:7px!important;font-weight:850!important;color:#4f555c!important;font-size:12.5px!important}.content-grid section .listing-meta{font-size:10.5px!important;color:#8a9097!important;white-space:nowrap!important;overflow:hidden!important}.content-grid section .fav-dot{width:29px!important;height:29px!important;top:6px!important;left:6px!important;font-size:17px!important}.content-grid section .demo-badge{right:6px!important;top:6px!important;font-size:8.5px!important;padding:4px 6px!important}.content-grid section .feature-badge{right:6px!important;bottom:6px!important;background:#b4232c!important;padding:4px 7px!important;font-size:8.5px!important}
}
@media (min-width:761px){.install-mini{display:none!important}}
@media (display-mode:standalone){.install-mini{display:none!important}.install-sheet{display:none!important}}
</style>
HTML;

    $ui = <<<'HTML'
<div id="installSheet" class="install-sheet" role="dialog" aria-modal="true" aria-label="راهنمای نصب نیازستان">
  <div class="install-sheet-card">
    <h3>نصب نیازستان</h3>
    <p id="installSheetText">از منوی مرورگر، گزینه نصب برنامه یا افزودن به صفحه اصلی را انتخاب کنید.</p>
    <button type="button" class="install-ok" id="installSheetClose">متوجه شدم</button>
  </div>
</div>
<div id="nzCategorySheet" class="nz-category-sheet" role="dialog" aria-modal="true" aria-label="همه دسته‌بندی‌ها">
  <div class="nz-category-card"><h3>همه دسته‌بندی‌ها</h3><div class="nz-category-grid" id="nzCategoryGrid"></div></div>
</div>
<script id="niazestan-install-script">
(()=>{
  const btn=document.getElementById('installMini'),sheet=document.getElementById('installSheet'),text=document.getElementById('installSheetText'),close=document.getElementById('installSheetClose');
  if(btn&&sheet){
    const standalone=matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
    if(standalone)btn.style.display='none';
    let deferredPrompt=null;
    const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent),isSafari=/safari/i.test(navigator.userAgent)&&!/crios|fxios|edgios/i.test(navigator.userAgent);
    addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;});
    addEventListener('appinstalled',()=>{btn.style.display='none';sheet.classList.remove('show');deferredPrompt=null;});
    btn.addEventListener('click',async()=>{if(deferredPrompt){deferredPrompt.prompt();try{const c=await deferredPrompt.userChoice;if(c?.outcome==='accepted')btn.style.display='none'}catch(_){}deferredPrompt=null;return}text.innerHTML=isIos&&isSafari?'در Safari روی دکمه <b>اشتراک‌گذاری</b> بزنید و سپس <b>Add to Home Screen / افزودن به صفحه اصلی</b> را انتخاب کنید.':'از منوی مرورگر، گزینه <b>Install app / نصب برنامه</b> یا <b>افزودن به صفحه اصلی</b> را انتخاب کنید.';sheet.classList.add('show')});
    close?.addEventListener('click',()=>sheet.classList.remove('show'));sheet.addEventListener('click',e=>{if(e.target===sheet)sheet.classList.remove('show')});
  }
})();

(()=>{
  const icons={
    'همه':['c-violet','<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="6" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="18" cy="12" r="2"/></svg>'],
    'املاک':['c-green','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 11.2 12 4l9 7.2V21h-6v-6H9v6H3z"/></svg>'],
    'وسایل نقلیه':['c-blue','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 8.5 7 5h10l2 3.5 2 1.5v7h-2v2h-3v-2H8v2H5v-2H3v-7l2-1.5ZM7 9h10l-1.2-2H8.2L7 9Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>'],
    'کالای دیجیتال':['c-pink','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 5h4M11 19h2"/></svg>'],
    'خانه و آشپزخانه':['c-purple','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 9a3 3 0 0 0-3 3v6h2v2h3v-2h8v2h3v-2h2v-6a3 3 0 0 0-3-3h-1V7a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3v2H6Zm3 0V7a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9Z"/></svg>'],
    'خدمات':['c-orange','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.7 3.3a5 5 0 0 0-6.4 6.4L3 15l6 6 5.3-5.3a5 5 0 0 0 6.4-6.4l-3.1 3.1-3-3 3.1-3.1a5 5 0 0 0-3-3Z"/></svg>'],
    'استخدام و کاریابی':['c-teal','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 5V3h6v2h4a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3h4Zm2 0h2V4h-2v1Zm11 6H2v2h8v2h4v-2h8v-2Z"/></svg>'],
    'شخصی':['c-yellow','<svg viewBox="0 0 24 24" fill="currentColor"><path d="m8 4 4 2 4-2 5 3-3 5-2-1v9H8v-9l-2 1-3-5 5-3Z"/></svg>'],
    'سرگرمی':['c-violet','<svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 2.5 2.7 5.6 6.2.9-4.5 4.4 1.1 6.2-5.5-2.9-5.5 2.9 1.1-6.2L3.1 9l6.2-.9L12 2.5Z"/></svg>']
  };
  const chips=[...document.querySelectorAll('.cat-chip')];
  chips.forEach(chip=>{const label=(chip.querySelector('span:last-child')?.textContent||'').trim(),target=chip.querySelector('.cat-icon');if(target&&icons[label]){target.className='cat-icon '+icons[label][0];target.innerHTML=icons[label][1]}});
  const allChip=chips[0],catSheet=document.getElementById('nzCategorySheet'),grid=document.getElementById('nzCategoryGrid');
  if(allChip&&catSheet&&grid){chips.forEach(chip=>{const a=document.createElement('a');a.href=chip.href;a.textContent=(chip.querySelector('span:last-child')?.textContent||'').trim();grid.appendChild(a)});allChip.addEventListener('click',e=>{if(matchMedia('(max-width:760px)').matches){e.preventDefault();catSheet.classList.add('show')}});catSheet.addEventListener('click',e=>{if(e.target===catSheet)catSheet.classList.remove('show')})}
})();

(()=>{
  const imgs=document.getElementById('nzImages'),info=document.getElementById('nzImagesInfo');
  if(imgs){imgs.addEventListener('change',()=>{if(imgs.files.length>5){alert('حداکثر ۵ عکس می‌توانید انتخاب کنید.');imgs.value='';if(info)info.textContent='';return}if(info)info.textContent=imgs.files.length?`${imgs.files.length} عکس انتخاب شد؛ هنگام ارسال بهینه می‌شود.`:''})}
  const video=document.getElementById('nzVideo'),durationInput=document.getElementById('nzVideoDuration'),vinfo=document.getElementById('nzVideoInfo');
  if(video){video.addEventListener('change',()=>{durationInput.value='0';if(vinfo)vinfo.textContent='';const f=video.files[0];if(!f)return;if(f.size>120*1024*1024){alert('حجم اولیه ویدیو باید کمتر از ۱۲۰ مگابایت باشد.');video.value='';return}const url=URL.createObjectURL(f),v=document.createElement('video');v.preload='metadata';v.onloadedmetadata=()=>{URL.revokeObjectURL(url);const d=Number(v.duration||0);if(!d||d>60.5){alert('ویدیو باید حداکثر ۱ دقیقه باشد.');video.value='';durationInput.value='0';if(vinfo)vinfo.textContent='';return}durationInput.value=d.toFixed(2);if(vinfo)vinfo.textContent=`مدت ویدیو: ${Math.ceil(d)} ثانیه؛ هنگام ارسال بهینه می‌شود.`};v.onerror=()=>{URL.revokeObjectURL(url);alert('این ویدیو قابل بررسی نیست. فایل دیگری انتخاب کنید.');video.value=''};v.src=url})}
})();
</script>
HTML;

    $html = str_replace('</head>', $css."\n</head>", $html);
    $html = str_replace('</body>', $ui."\n</body>", $html);
    echo $html;
}
