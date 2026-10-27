<?php
declare(strict_types=1);

$asset = __DIR__ . '/assets/hero-v22.b64';
if (!is_file($asset)) {
    http_response_code(404);
    exit;
}

$encoded = preg_replace('/\s+/', '', (string)file_get_contents($asset));
$image = base64_decode((string)$encoded, true);
if ($image === false || strlen($image) < 4 || substr($image, 0, 2) !== "\xFF\xD8") {
    http_response_code(500);
    exit;
}

// The legacy encoded hero is missing the JPEG EOI marker. Add it once so
// browsers and image validators receive a complete JPEG without changing the picture.
if (substr($image, -2) !== "\xFF\xD9") {
    $image .= "\xFF\xD9";
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=604800, immutable');
echo $image;
exit;
