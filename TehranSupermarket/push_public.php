<?php
declare(strict_types=1);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/push_lib.php';
try{echo json_encode(['ok'=>true,'public_key'=>ts_push_public_key()],JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false]);}
