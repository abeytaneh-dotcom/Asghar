<?php
declare(strict_types=1);
ob_start();
require __DIR__ . '/nz-app.php';
$html = ob_get_clean();
if ($html !== false) {
    $tag = '<script src="/nz-media-client.js?v=20261018-1" defer></script>';
    $html = str_replace('</body>', $tag."\n</body>", $html);
    echo $html;
}
