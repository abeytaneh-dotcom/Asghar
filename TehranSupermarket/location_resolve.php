<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function out(array $data, int $code=200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function allowed_host(string $host): bool {
    $host = strtolower(trim($host, '.'));
    $allowed = [
        'maps.app.goo.gl','goo.gl','google.com','www.google.com','maps.google.com',
        'neshan.org','www.neshan.org','nshn.ir','www.nshn.ir',
        'balad.ir','www.balad.ir'
    ];
    return in_array($host, $allowed, true);
}
function valid_coords(float $lat, float $lng): bool {
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}
function extract_coords(string $text): ?array {
    $text = html_entity_decode(urldecode($text), ENT_QUOTES|ENT_HTML5, 'UTF-8');
    $patterns = [
        '~@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)~',
        '~!3d(-?\d{1,2}(?:\.\d+)?)[^!]*!4d(-?\d{1,3}(?:\.\d+)?)~',
        '~(?:[?&](?:q|query|ll|center)=)(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)~i',
        '~[?&]lat(?:itude)?=(-?\d{1,2}(?:\.\d+)?).*?[?&](?:lng|lon|longitude)=(-?\d{1,3}(?:\.\d+)?)~i',
        '~[?&](?:lng|lon|longitude)=(-?\d{1,3}(?:\.\d+)?).*?[?&]lat(?:itude)?=(-?\d{1,2}(?:\.\d+)?)~i',
    ];
    foreach ($patterns as $i => $p) {
        if (preg_match($p, $text, $m)) {
            if ($i === 4) { $lat=(float)$m[2]; $lng=(float)$m[1]; }
            else { $lat=(float)$m[1]; $lng=(float)$m[2]; }
            if (valid_coords($lat,$lng)) return [$lat,$lng];
        }
    }
    return null;
}
function absolute_url(string $base, string $location): ?string {
    if (preg_match('~^https?://~i', $location)) return $location;
    $b = parse_url($base);
    if (!$b || empty($b['scheme']) || empty($b['host'])) return null;
    if (str_starts_with($location, '//')) return $b['scheme'].':'.$location;
    if (str_starts_with($location, '/')) return $b['scheme'].'://'.$b['host'].$location;
    $path = $b['path'] ?? '/';
    $dir = rtrim(str_replace('\\','/',dirname($path)),'/');
    return $b['scheme'].'://'.$b['host'].($dir ? $dir.'/' : '/').$location;
}
function fetch_once(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 TehranSupermarketLocationResolver/1.0',
        CURLOPT_RANGE => '0-16383',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if (!is_string($raw)) return [$code,'',''];
    return [$code, substr($raw,0,$hs), substr($raw,$hs)];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'method'],405);
$now = time();
$times = array_values(array_filter($_SESSION['loc_resolve_times'] ?? [], fn($t)=>is_int($t) && $t>$now-60));
if (count($times) >= 10) out(['ok'=>false,'error'=>'تعداد درخواست‌ها زیاد است. چند لحظه بعد دوباره امتحان کنید.'],429);
$times[]=$now; $_SESSION['loc_resolve_times']=$times;

$url = trim((string)($_POST['url'] ?? ''));
if ($url === '' || strlen($url) > 1500) out(['ok'=>false,'error'=>'لینک معتبر وارد کنید.'],422);
if (!preg_match('~^https?://~i',$url)) $url='https://'.$url;

for ($hop=0; $hop<6; $hop++) {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !allowed_host((string)$parts['host'])) out(['ok'=>false,'error'=>'فقط لینک Google Maps، نشان یا بلد قابل قبول است.'],422);
    if ($xy=extract_coords($url)) out(['ok'=>true,'lat'=>$xy[0],'lng'=>$xy[1],'source'=>$parts['host']]);
    [$code,$headers,$body]=fetch_once($url);
    if ($xy=extract_coords($headers."\n".$body)) out(['ok'=>true,'lat'=>$xy[0],'lng'=>$xy[1],'source'=>$parts['host']]);
    if ($code>=300 && $code<400 && preg_match('~^Location:\s*(.+)$~mi',$headers,$m)) {
        $next=absolute_url($url,trim($m[1]));
        if (!$next) break;
        $url=$next;
        continue;
    }
    break;
}
out(['ok'=>false,'error'=>'مختصات از این لینک پیدا نشد. در Google Maps/نشان روی Share بزنید و لینک خودِ مکان را کپی کنید.'],422);
