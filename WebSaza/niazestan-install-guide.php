<?php
declare(strict_types=1);

$source = __DIR__ . '/niazestan-install-guide.txt';
if (!is_file($source) || !is_readable($source)) {
    http_response_code(404);
    exit;
}

$encoded = trim((string) file_get_contents($source));
$binary = base64_decode($encoded, true);
if ($binary === false || $binary === '') {
    http_response_code(500);
    exit;
}

header('Content-Type: image/webp');
header('Content-Length: ' . strlen($binary));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
echo $binary;
