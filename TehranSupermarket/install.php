<?php
declare(strict_types=1);
// Retired browser installer. Initialize a staging database through reviewed,
// versioned migrations only after verified backups and an explicit approval.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Cache-Control: no-store');
    exit('Installer disabled');
}
fwrite(STDERR, "Legacy installer retired. Use approved, versioned migrations on a staged clone.\n");
exit(1);
