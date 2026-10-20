<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/nz-external-feeds.php';
require_once $root . '/nz-zero-friction.php';
nzext_maybe_sync();
ob_start('nzux_output_filter');
require $root . '/nz-v2.php';
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; connect-src 'self' https://cdn.jsdelivr.net https://*.jsdelivr.net https://nominatim.openstreetmap.org; worker-src 'self' blob:; font-src 'self' data:; object-src 'none'; frame-src 'none'; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");
}
if (ob_get_level() > 0) ob_end_flush();
