<?php
declare(strict_types=1);

require_once __DIR__.'/error_bootstrap.php';
require_once __DIR__.'/pyramid_sync_lib.php';

header('Content-Type: application/octet-stream');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Websaz-Sync-Version: 1');

function ws_sync_auth_header(): string {
    foreach (['HTTP_AUTHORIZATION','REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        $v = trim((string)($_SERVER[$k] ?? ''));
        if ($v !== '') return $v;
    }
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) {
            foreach ($h as $k=>$v) if (strcasecmp((string)$k,'Authorization')===0) return trim((string)$v);
        }
    }
    return '';
}
function ws_sync_fail(int $status,string $body): never {
    http_response_code($status);
    echo $body;
    exit;
}

try {
    $expected = ts_sync_key();
} catch (Throwable $e) {
    ts_log_internal($e,'SYNC-703');
    ws_sync_fail(503,'SYNC_STORAGE_ERROR');
}
$auth = ws_sync_auth_header();
if ($expected==='' || !hash_equals('Bearer '.$expected,$auth)) ws_sync_fail(401,'UNAUTHORIZED');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') ws_sync_fail(405,'POST_REQUIRED');

$raw = file_get_contents('php://input');
if ($raw===false) ws_sync_fail(400,'EMPTY_BODY');
if (strlen($raw)>100*1024*1024) ws_sync_fail(413,'PAYLOAD_TOO_LARGE');
$lines = preg_split('/\r?\n/',$raw);
if (!is_array($lines) || ($lines[0] ?? '')!=='WEBSAZSYNC1') ws_sync_fail(400,'BAD_PROTOCOL');

$device = base64_decode((string)($lines[1] ?? ''),true);
$store = base64_decode((string)($lines[2] ?? ''),true);
$device = is_string($device) ? $device : '';
$store = is_string($store) && trim($store)!=='' ? trim($store) : 'default';
$safeStore = preg_replace('/[^A-Za-z0-9._-]/','_',$store) ?: 'default';

$dir = __DIR__.'/storage/websaz-sync';
if (!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) ws_sync_fail(503,'STORAGE_ERROR');
$file = $dir.'/records-'.$safeStore.'.sync';
$lockPath = $dir.'/records-'.$safeStore.'.lock';
$lock = @fopen($lockPath,'c+');
if (!$lock || !flock($lock,LOCK_EX)) ws_sync_fail(503,'LOCK_ERROR');

try {
    $records=[];
    if (is_file($file)) {
        $fh=@fopen($file,'rb');
        if ($fh) {
            while (($line=fgets($fh))!==false) {
                $line=rtrim($line,"\r\n"); if ($line==='') continue;
                $p=explode("\t",$line,5); if (count($p)<5) continue;
                $type=base64_decode($p[0],true); $match=base64_decode($p[1],true);
                if (!is_string($type)||!is_string($match)||$type===''||$match==='') continue;
                $records[$type."\0".$match]=[$p[0],$p[1],(int)$p[2],$p[3],$p[4]];
            }
            fclose($fh);
        }
    }

    for ($i=4,$n=count($lines);$i<$n;$i++) {
        $line=rtrim((string)$lines[$i],"\r\n"); if ($line==='') continue;
        $p=explode("\t",$line,5); if (count($p)<5) continue;
        $type=base64_decode($p[0],true); $match=base64_decode($p[1],true);
        if (!is_string($type)||!is_string($match)||$type===''||$match==='') continue;
        $key=$type."\0".$match; $incomingTs=(int)$p[2];
        if (!isset($records[$key]) || $incomingTs>(int)$records[$key][2]) $records[$key]=[$p[0],$p[1],$incomingTs,$p[3],$p[4]];
    }

    $tmp=$file.'.tmp-'.bin2hex(random_bytes(4));
    $fh=@fopen($tmp,'wb');
    if (!$fh) ws_sync_fail(503,'WRITE_ERROR');
    foreach ($records as $r) fwrite($fh,implode("\t",$r)."\n");
    fflush($fh); fclose($fh);
    if (!@rename($tmp,$file)) { @unlink($tmp); ws_sync_fail(503,'WRITE_ERROR'); }
    @chmod($file,0640);

    echo "WEBSAZSYNC1\n";
    echo base64_encode($device)."\n";
    echo base64_encode($store)."\n";
    echo count($records)."\n";
    foreach ($records as $r) echo implode("\t",$r)."\n";
} finally {
    flock($lock,LOCK_UN);
    fclose($lock);
}
