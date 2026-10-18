<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-controlled-values.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
$sub = trim((string)($_GET['subcategory'] ?? ''));
$brand = trim((string)($_GET['brand'] ?? ''));
$catalog = nzcv_catalog($sub);
$models = [];
if ($brand !== '' && isset($catalog[$brand]) && is_array($catalog[$brand])) {
    $models = array_values(array_filter(array_map('strval', $catalog[$brand]), static fn($v) => $v !== ''));
}
if (!$models) $models = ['سایر مدل'];
echo json_encode(['ok'=>true,'models'=>$models], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
