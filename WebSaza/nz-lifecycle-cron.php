<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-lifecycle.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
try {
    $stats=nzl_tick(true);
    echo json_encode(['ok'=>true,'stats'=>$stats,'checked_at'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'maintenance_failed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
