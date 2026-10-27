<?php
declare(strict_types=1);

$asset = __DIR__ . '/assets/hero-hq-v32.b64';
if (!is_file($asset)) {
    http_response_code(404);
    exit;
}
$encoded = preg_replace('/\s+/', '', (string) file_get_contents($asset)) ?: '';
$image = $encoded !== '' ? base64_decode($encoded, true) : false;
if ($image === false || strlen($image) < 1000 || substr($image, 0, 4) !== 'RIFF' || substr($image, 8, 4) !== 'WEBP') {
    http_response_code(500);
    exit;
}
header('Content-Type: image/webp');
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo $image;
exit;
