<?php
declare(strict_types=1);

require_once __DIR__ . '/nz-external-feeds.php';
nzext_maybe_sync();
ob_start('nzext_output_filter');
require __DIR__ . '/nz-v2.php';
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; connect-src 'self' https://cdn.jsdelivr.net https://*.jsdelivr.net; worker-src 'self' blob:; font-src 'self' data:; object-src 'none'; frame-src 'none'; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");
}
if (ob_get_level() > 0) ob_end_flush();
