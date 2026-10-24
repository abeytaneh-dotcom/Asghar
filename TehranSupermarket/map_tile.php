<?php
declare(strict_types=1);

// Same-origin resilient tile proxy for the checkout map.
// It keeps a long local cache and automatically falls back across multiple
// OpenStreetMap-compatible tile providers so the map does not go blank if one
// upstream is temporarily unavailable from the hosting network.

$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT);
$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT);
$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT);

if ($z === false || $x === false || $y === false || $z < 0 || $z > 19) {
    http_response_code(400);
    header('Cache-Control: no-store');
    exit;
}
$max = (1 << $z) - 1;
if ($x < 0 || $y < 0 || $x > $max || $y > $max) {
    http_response_code(400);
    header('Cache-Control: no-store');
    exit;
}

$cacheRoot = __DIR__ . '/storage/map_tiles';
$dir = $cacheRoot . '/' . $z . '/' . $x;
$file = $dir . '/' . $y . '.png';
$ttl = 2592000; // 30 days

// Prevent the tile cache from growing forever. Cleanup is intentionally rare
// so normal map requests stay fast. Tiles unused for 90 days are removed.
try {
    if (random_int(1, 500) === 1 && is_dir($cacheRoot)) {
        $cutoff = time() - 7776000;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $node) {
            if ($node->isFile() && $node->getExtension() === 'png' && $node->getMTime() < $cutoff) @unlink($node->getPathname());
            elseif ($node->isDir()) @rmdir($node->getPathname());
        }
    }
} catch (Throwable $e) { /* cache cleanup must never break the map */ }

function send_tile_file(string $path, int $ttl, string $source = 'cache'): never {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=86400');
    header('X-Content-Type-Options: nosniff');
    header('X-Map-Tile-Source: ' . $source);
    readfile($path);
    exit;
}

// Prefer any cached tile immediately. Street geometry/labels are stable enough
// that a stale map is much better than a blank map on a customer's phone.
if (is_file($file) && filesize($file) > 100) {
    $age = time() - (int) filemtime($file);
    if ($age < $ttl) {
        send_tile_file($file, $ttl, 'cache');
    }
}

if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    if (is_file($file) && filesize($file) > 100) send_tile_file($file, 3600, 'stale-cache');
    http_response_code(503);
    header('Cache-Control: no-store');
    exit;
}

$templates = [
    'osm' => 'https://tile.openstreetmap.org/%d/%d/%d.png',
    'osm-de' => 'https://tile.openstreetmap.de/%d/%d/%d.png',
    'osm-hot' => 'https://a.tile.openstreetmap.fr/hot/%d/%d/%d.png',
];

$body = null;
$usedSource = '';

if (function_exists('curl_init')) {
    foreach ($templates as $name => $tpl) {
        $url = sprintf($tpl, $z, $x, $y);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'TehranSupermarketMap/2.0 (+https://websaza.ir/tehran/)',
            CURLOPT_HTTPHEADER => [
                'Referer: https://websaza.ir/tehran/',
                'Accept: image/png,image/*;q=0.9,*/*;q=0.5',
            ],
        ]);
        $candidate = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (is_string($candidate) && $code === 200 && strlen($candidate) > 100 && stripos($type, 'image/') === 0) {
            $body = $candidate;
            $usedSource = $name;
            break;
        }
    }
}

if (!is_string($body)) {
    if (is_file($file) && filesize($file) > 100) {
        send_tile_file($file, 3600, 'stale-cache');
    }
    http_response_code(502);
    header('Cache-Control: no-store, max-age=0');
    header('X-Map-Tile-Source: unavailable');
    exit;
}

$tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
if (@file_put_contents($tmp, $body, LOCK_EX) !== false) {
    @rename($tmp, $file);
    @chmod($file, 0644);
}

if (is_file($file) && filesize($file) > 100) {
    send_tile_file($file, $ttl, $usedSource ?: 'upstream');
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('X-Map-Tile-Source: ' . ($usedSource ?: 'upstream'));
echo $body;
