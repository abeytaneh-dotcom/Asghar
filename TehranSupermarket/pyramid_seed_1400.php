<?php
declare(strict_types=1);
// Legacy data seed permanently disabled: it overwrote stock and catalogue data.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Cache-Control: no-store');
    exit;
}
function ts_seed_pyramid_1400(): void {
    throw new LogicException('legacy_demo_seed_disabled');
}
