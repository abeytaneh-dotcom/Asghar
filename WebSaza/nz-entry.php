<?php
declare(strict_types=1);

require_once __DIR__ . '/nz-external-feeds.php';
nzext_maybe_sync();
ob_start('nzext_output_filter');
require __DIR__ . '/nz-v2.php';
if (ob_get_level() > 0) ob_end_flush();
