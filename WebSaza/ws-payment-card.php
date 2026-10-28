<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);
$card = trim((string)getenv('WEBSAZ_PAYMENT_CARD'));
if ($card === '') {
    $privateFile = dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__)) . '/.websaz-payment-card';
    if (is_file($privateFile) && is_readable($privateFile)) {
        $card = trim((string)file_get_contents($privateFile));
    }
}
$card = preg_replace('/\D+/', '', $card ?? '');
if (!is_string($card) || strlen($card) !== 16) {
    http_response_code(503);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE);
