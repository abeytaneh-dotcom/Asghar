<?php
declare(strict_types=1);
ini_set('display_errors', '0');
@set_time_limit(0);
$expected = '__MIGRATION_TOKEN__';
$given = (string)($_SERVER['HTTP_X_TEHRAN_MIGRATION_AUTH'] ?? '');
if ($given === '' || !hash_equals($expected, $given)) { http_response_code(404); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !class_exists('ZipArchive')) { http_response_code(503); exit('export not available'); }
$zipPath = tempnam(sys_get_temp_dir(), 'tehran_export_');
if ($zipPath === false) { http_response_code(500); exit('temp error'); }
register_shutdown_function(static function () use ($zipPath): void { @unlink($zipPath); });
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('zip error'); }
$base = realpath(__DIR__);
$count=0; $bytes=0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $f) {
    if (!$f->isFile() || $f->isLink()) continue;
    $path = $f->getRealPath();
    if ($path === false || strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) continue;
    $rel = substr($path, strlen($base) + 1);
    if (preg_match('~(^|/)(?:tm-migrate-export-|tm-crypt-|tm-key-)[^/]*$~', $rel)) continue;
    if ($zip->addFile($path, 'tehran/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel))) { $count++; $bytes += (int)$f->getSize(); }
}
$backups = dirname(dirname($base)) . '/tehran_backups';
$dbFiles = glob($backups . '/db-*.sql.gz') ?: [];
usort($dbFiles, static fn($a, $b) => filemtime($b) <=> filemtime($a));
$db = $dbFiles[0] ?? null;
if ($db !== null && is_readable($db)) $zip->addFile($db, 'migration-data/latest-database.sql.gz');
$upFiles = glob($backups . '/uploads-*.zip') ?: [];
usort($upFiles, static fn($a, $b) => filemtime($b) <=> filemtime($a));
if (!empty($upFiles) && is_readable($upFiles[0])) $zip->addFile($upFiles[0], 'migration-data/latest-uploads.zip');
$manifest = ['exported_at' => gmdate('c'), 'source' => 'websaza.ir/tehran/', 'files' => $count, 'uncompressed_file_bytes' => $bytes, 'database_backup' => $db ? basename($db) : null, 'database_backup_bytes' => $db ? filesize($db) : null, 'extra_uploads_backup' => !empty($upFiles) ? basename($upFiles[0]) : null];
$zip->addFromString('migration-data/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if (!$zip->close()) { http_response_code(500); exit('zip close error'); }
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store, private');
header('Content-Disposition: attachment; filename="tehran-migration-snapshot.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
