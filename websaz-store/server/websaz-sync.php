<?php
header('Content-Type: application/octet-stream');
$configFile = __DIR__ . '/websaz-sync-config.php';
if (!is_file($configFile)) { http_response_code(503); echo "CONFIG_MISSING"; exit; }
$config = require $configFile;
$key = (string)($config['pairing_key'] ?? '');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($key === '' || !hash_equals('Bearer '.$key, $auth)) { http_response_code(401); echo "UNAUTHORIZED"; exit; }
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 100*1024*1024) { http_response_code(413); exit; }
$lines = preg_split('/\r?\n/', $raw);
if (($lines[0] ?? '') !== 'WEBSAZSYNC1') { http_response_code(400); echo "BAD_PROTOCOL"; exit; }
$device = base64_decode($lines[1] ?? '', true) ?: '';
$store  = base64_decode($lines[2] ?? '', true) ?: 'default';
$safeStore = preg_replace('/[^A-Za-z0-9._-]/', '_', $store);
$dir = __DIR__ . '/.websaz-sync';
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$file = $dir . '/records-' . $safeStore . '.sync';
$lock = fopen($file . '.lock', 'c+');
if (!$lock || !flock($lock, LOCK_EX)) { http_response_code(503); echo "LOCK_ERROR"; exit; }
$records = [];
if (is_file($file)) {
    $fh = fopen($file, 'rb');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n"); if ($line === '') continue;
            $p = explode("\t", $line, 5); if (count($p) < 5) continue;
            $type = base64_decode($p[0], true); $match = base64_decode($p[1], true);
            if ($type === false || $match === false) continue;
            $records[$type."\0".$match] = [$p[0],$p[1],intval($p[2]),$p[3],$p[4]];
        }
        fclose($fh);
    }
}
for ($i=4; $i<count($lines); $i++) {
    $line = rtrim($lines[$i], "\r\n"); if ($line === '') continue;
    $p = explode("\t", $line, 5); if (count($p) < 5) continue;
    $type = base64_decode($p[0], true); $match = base64_decode($p[1], true);
    if ($type === false || $match === false || $type === '' || $match === '') continue;
    $k = $type."\0".$match; $incomingTs = intval($p[2]);
    if (!isset($records[$k]) || $incomingTs > intval($records[$k][2])) $records[$k] = [$p[0],$p[1],$incomingTs,$p[3],$p[4]];
}
$tmp = $file.'.tmp';
$fh = fopen($tmp,'wb');
foreach ($records as $r) fwrite($fh, implode("\t", $r)."\n");
fclose($fh); rename($tmp,$file);
flock($lock, LOCK_UN); fclose($lock);
echo "WEBSAZSYNC1\n";
echo base64_encode($device)."\n";
echo base64_encode($store)."\n";
echo count($records)."\n";
foreach ($records as $r) echo implode("\t", $r)."\n";
