<?php
declare(strict_types=1);

ob_start();
require dirname(__DIR__) . '/marketplace.php';
$html = ob_get_clean();

if ($html !== false) {
    $html = str_replace(
        ['بازار وب‌ساز', 'بازار وب ساز', '/marketplace.php'],
        ['نیازستان', 'نیازستان', '/nz'],
        $html
    );
    echo $html;
}
