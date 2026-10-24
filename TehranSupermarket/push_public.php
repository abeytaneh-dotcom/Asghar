<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/push_lib.php';
try{echo json_encode(['ok'=>true,'public_key'=>ts_push_public_key()],JSON_UNESCAPED_SLASHES);}catch(Throwable $e){ts_log_internal($e,'SYS-100');ts_json_fail('SYS-100',503);}
