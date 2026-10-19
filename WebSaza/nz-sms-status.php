<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/nz-otp.php';
$c = nzotp_legacy_settings();
$legacyKey = nzotp_legacy_key($c) !== '';
$legacyPattern = nzotp_legacy_pattern() !== '';
$legacyReady = nzotp_legacy_ready();
$directReady = nzotp_direct_ready();
echo json_encode([
    'ok' => $legacyReady || $directReady,
    'api_configured' => $legacyKey || nzotp_env('IPPANEL_API_KEY') !== '',
    'pattern_configured' => $legacyPattern || nzotp_env('IPPANEL_PATTERN_CODE') !== '',
    'provider' => $legacyReady ? 'iranpayamak' : ($directReady ? 'ippanel' : 'none'),
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
