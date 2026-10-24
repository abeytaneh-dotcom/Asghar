<?php
declare(strict_types=1);

// Same-origin tile proxy for the checkout map. It only fetches tiles actually
// requested by the interactive map and keeps a local cache for at least 7 days.
// OpenStreetMap attribution remains visible in the Leaflet UI.

$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT);
$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT);
$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT);

if ($z === false || $x === false || $y === false || $z < 0 || $z > 19) {
    http_response_code(400);
    exit;
}
$max = (1 << $z) - 1;
if ($x < 0 || $y < 0 || $x > $max || $y > $max) {
    http_response_code(400);
    exit;
}

$cacheRoot = __DIR__ . '/storage/map_tiles';
$dir = $cacheRoot . '/' . $z . '/' . $x;
$file = $dir . '/' . $y . '.png';
$ttl = 604800; // 7 days

function send_tile(string $path, int $ttl): never {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=' . $ttl . ', immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if (is_file($file) && (time() - (int)filemtime($file)) < $ttl) {
    send_tile($file, $ttl);
}

if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    http_response_code(503);
    exit;
}

$url = "https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png";
$body = null;
$code = 0;
$type = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'TehranSupermarketMap/1.0 (+https://websaza.ir/tehran/)',
        CURLOPT_HTTPHEADER => [
            'Referer: https://websaza.ir/tehran/',
            'Accept: image/png,image/*;q=0.8,*/*;q=0.5',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
}

if (!is_string($body) || $code !== 200 || strlen($body) < 100 || stripos($type, 'image/') !== 0) {
    // If an older cached tile exists, prefer a stale map over a blank map.
    if (is_file($file)) {
        send_tile($file, 3600);
    }
    http_response_code(502);
    header('Cache-Control: no-store');
    exit;
}

$tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
if (@file_put_contents($tmp, $body, LOCK_EX) !== false) {
    @rename($tmp, $file);
    @chmod($file, 0644);
}
if (is_file($file)) {
    send_tile($file, $ttl);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
echo $body;
