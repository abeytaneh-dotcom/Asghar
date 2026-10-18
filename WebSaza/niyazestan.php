<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/marketplace.php';
$html = ob_get_clean();

if ($html !== false) {
    $html = str_replace(
        ['بازار وب‌ساز', 'بازار وب ساز'],
        ['نیازستان', 'نیازستان'],
        $html
    );
    echo $html;
}
