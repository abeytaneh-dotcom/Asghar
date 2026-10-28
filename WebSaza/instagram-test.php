<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$target = 'https://www.instagram.com/';
$started = microtime(true);
$status = 0;
$effective = '';
$error = '';
$errno = 0;
$bytes = 0;
$curlAvailable = function_exists('curl_init');

if ($curlAvailable) {
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if (is_string($body)) {
        $bytes = strlen($body);
    }
    curl_close($ch);
} else {
    $body = false;
    $error = 'PHP cURL extension is not available on this host.';
}

$elapsed = (int) round((microtime(true) - $started) * 1000);
$reachable = $curlAvailable && $errno === 0 && $status >= 200 && $status < 500 && $bytes > 0;

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>تست اتصال Instagram | وب ساز</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#15171a;font-family:Tahoma,Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:20px}.card{width:min(760px,100%);background:#fff;border:1px solid #e7e9ee;border-radius:22px;padding:28px;box-shadow:0 14px 45px rgba(0,0,0,.08)}h1{font-size:22px;margin:0 0 18px}.state{padding:15px 18px;border-radius:14px;font-weight:700;margin-bottom:20px}.ok{background:#eaf8ef;color:#137333}.bad{background:#fdecec;color:#b42318}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.item{background:#f7f8fa;border-radius:12px;padding:13px}.label{font-size:12px;color:#68707a;margin-bottom:7px}.value{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;direction:ltr;text-align:left;word-break:break-all;font-size:13px}.note{margin-top:20px;color:#5d6570;font-size:13px;line-height:1.9}.button{display:inline-block;margin-top:18px;padding:12px 18px;border-radius:12px;background:#111827;color:#fff;text-decoration:none;font-weight:700}@media(max-width:600px){.grid{grid-template-columns:1fr}.card{padding:20px}}
</style>
</head>
<body>
<div class="card">
<h1>تست اتصال سرور وب ساز به Instagram</h1>
<div class="state <?= $reachable ? 'ok' : 'bad' ?>"><?= $reachable ? '✅ سرور وب ساز به Instagram دسترسی دارد' : '❌ اتصال سرور به Instagram برقرار نشد' ?></div>
<div class="grid">
<div class="item"><div class="label">HTTP Status</div><div class="value"><?= e($status ?: '-') ?></div></div>
<div class="item"><div class="label">Response time</div><div class="value"><?= e($elapsed) ?> ms</div></div>
<div class="item"><div class="label">Downloaded bytes</div><div class="value"><?= e($bytes) ?></div></div>
<div class="item"><div class="label">Final URL</div><div class="value"><?= e($effective ?: '-') ?></div></div>
<?php if ($error !== ''): ?><div class="item" style="grid-column:1/-1"><div class="label">cURL error</div><div class="value"><?= e($errno . ': ' . $error) ?></div></div><?php endif; ?>
</div>
<div class="note">این صفحه فقط بررسی می‌کند درخواست از خود سرور وب ساز به Instagram می‌رسد یا نه. هیچ نام کاربری، رمز عبور یا کوکی اینستاگرام دریافت یا ذخیره نمی‌شود. موفقیت این تست به‌تنهایی به معنی امکان اجرای کامل نسخه وب Instagram روی هاست اشتراکی نیست.</div>
<a class="button" href="https://www.instagram.com/" rel="noopener noreferrer">باز کردن مستقیم Instagram</a>
</div>
</body>
</html>
