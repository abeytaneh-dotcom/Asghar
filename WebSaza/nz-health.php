<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$integrity='unknown';$pdo=nzstore_sqlite();if($pdo){try{$integrity=(string)$pdo->query('PRAGMA quick_check')->fetchColumn();}catch(Throwable $e){$integrity='error';}}
$free=@disk_free_space(__DIR__);$release=[];$raw=@file_get_contents(__DIR__.'/.marketplace-data/release.json');if($raw!==false){$j=json_decode($raw,true);if(is_array($j))$release=$j;}
$ok=($integrity==='ok'||$integrity==='unknown')&&(!is_numeric($free)||(float)$free>256*1024*1024);
http_response_code($ok?200:503);
echo json_encode(['ok'=>$ok,'backend'=>nzstore_backend(),'integrity'=>$integrity,'disk_free_mb'=>is_numeric($free)?round((float)$free/1048576):null,'release'=>isset($release['sha'])?substr((string)$release['sha'],0,12):null,'deployed_at'=>$release['deployed_at']??null,'checked_at'=>date('c')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);