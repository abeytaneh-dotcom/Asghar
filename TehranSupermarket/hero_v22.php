<?php
declare(strict_types=1);

$shopFile = __DIR__ . '/shop.php';
if (!is_file($shopFile)) {
    http_response_code(404);
    exit;
}

$shopSource = (string)file_get_contents($shopFile);
$encoded = '';
if (preg_match("/TM_REF_HERO_JPEG_V22_START.*?base64_decode\\('([^']+)'\\s*,\\s*true\\)/s", $shopSource, $match) === 1) {
    $encoded = $match[1];
}

// Fallback for deployments where the generated inline marker is temporarily absent.
if ($encoded === '') {
    $asset = __DIR__ . '/assets/hero-v22.b64';
    if (is_file($asset)) {
        $encoded = preg_replace('/\s+/', '', (string)file_get_contents($asset)) ?: '';
    }
}

$image = $encoded !== '' ? base64_decode($encoded, true) : false;
if ($image === false || strlen($image) < 4 || substr($image, 0, 2) !== "\xFF\xD8") {
    http_response_code(500);
    exit;
}

// The generated legacy hero misses the JPEG EOI marker. Add it once so
// browsers receive a complete JPEG while preserving the exact approved image.
if (substr($image, -2) !== "\xFF\xD9") {
    $image .= "\xFF\xD9";
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=604800, immutable');
echo $image;
exit;
