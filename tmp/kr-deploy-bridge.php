<?php
declare(strict_types=1);
@set_time_limit(240);
error_reporting(E_ALL);
ini_set('display_errors', '0');

function out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $message, int $status = 500): void { out(['ok' => false, 'error' => $message], $status); }
function ensure_dir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) fail('Cannot create directory.');
}
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}
function copy_tree(string $src, string $dst, int $depth = 0): void {
    ensure_dir($dst);
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($depth === 0 && $item === 'kr-deploy-bridge.php') continue;
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_link($from)) continue;
        if (is_dir($from)) copy_tree($from, $to, $depth + 1);
        elseif (!copy($from, $to)) fail('Copy failed: ' . $item);
    }
}
function zip_backup(string $src, string $dest): void {
    if (!class_exists('ZipArchive')) fail('ZipArchive is not enabled.');
    $zip = new ZipArchive();
    if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) fail('Could not create backup archive.');
    $src = rtrim($src, DIRECTORY_SEPARATOR);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        if ($file->isLink()) continue;
        $path = $file->getPathname();
        $local = ltrim(substr($path, strlen($src)), DIRECTORY_SEPARATOR);
        if ($local === 'kr-deploy-bridge.php') continue;
        if ($file->isDir()) $zip->addEmptyDir($local); else $zip->addFile($path, $local);
    }
    $zip->close();
}
function bearer_secret(): string {
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return '';
    return trim($m[1]);
}

$root = __DIR__;
$home = dirname(dirname($root));
$authFile = $home . DIRECTORY_SEPARATOR . '.aloasansor_deploy_secret.sha256';
$backupDir = $home . DIRECTORY_SEPARATOR . 'kr_deploy_backups';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    out(['ok' => true, 'service' => 'aloasansor-deploy-bridge', 'version' => '1.0', 'paired' => is_file($authFile)]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed.', 405);

$secret = bearer_secret();
if ($secret === '' || strlen($secret) < 24) fail('Unauthorized.', 403);
$secretHash = hash('sha256', $secret);
$action = (string)($_POST['action'] ?? '');

if ($action === 'pair') {
    if (is_file($authFile)) {
        $savedHash = trim((string)file_get_contents($authFile));
        if ($savedHash === '' || !hash_equals($savedHash, $secretHash)) fail('Unauthorized.', 403);
        out(['ok' => true, 'paired' => true, 'existing' => true]);
    }
    if (file_put_contents($authFile, $secretHash . PHP_EOL, LOCK_EX) === false) fail('Pairing failed.');
    @chmod($authFile, 0600);
    out(['ok' => true, 'paired' => true, 'existing' => false]);
}

if (!is_file($authFile)) fail('Bridge is not paired.', 409);
$savedHash = trim((string)file_get_contents($authFile));
if ($savedHash === '' || !hash_equals($savedHash, $secretHash)) fail('Unauthorized.', 403);
if ($action !== 'deploy') fail('Unknown action.', 400);

if (!isset($_FILES['package']) || !is_array($_FILES['package'])) fail('Package missing.', 400);
$package = $_FILES['package'];
if (($package['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fail('Package upload failed.', 400);
if (!is_uploaded_file((string)$package['tmp_name'])) fail('Invalid package upload.', 400);
if ((int)($package['size'] ?? 0) > 50 * 1024 * 1024) fail('Package is too large.', 413);

$expectedSha = strtolower(trim((string)($_SERVER['HTTP_X_PACKAGE_SHA256'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $expectedSha)) fail('Package checksum missing.', 400);
$actualSha = hash_file('sha256', (string)$package['tmp_name']);
if (!hash_equals($expectedSha, $actualSha)) fail('Package checksum mismatch.', 400);
if (!class_exists('ZipArchive')) fail('ZipArchive is not enabled.');

ensure_dir($backupDir);
$backupPath = $backupDir . DIRECTORY_SEPARATOR . 'vakil_' . date('Ymd_His') . '.zip';
zip_backup($root, $backupPath);

$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aloasansor_' . bin2hex(random_bytes(8));
ensure_dir($tempDir);

try {
    $zip = new ZipArchive();
    if ($zip->open((string)$package['tmp_name']) !== true) fail('Could not open deployment package.');
    if (!$zip->extractTo($tempDir)) { $zip->close(); fail('Could not extract deployment package.'); }
    $zip->close();

    if (!is_file($tempDir . DIRECTORY_SEPARATOR . 'index.php')) fail('Package validation failed: index.php missing.');
    if (!is_file($tempDir . DIRECTORY_SEPARATOR . 'config.php')) fail('Package validation failed: config.php missing.');

    $oldConfig = [];
    $oldConfigPath = $root . DIRECTORY_SEPARATOR . 'config.php';
    if (is_file($oldConfigPath)) {
        $loaded = @include $oldConfigPath;
        if (is_array($loaded)) $oldConfig = $loaded;
    }

    copy_tree($tempDir, $root);

    $newConfigPath = $root . DIRECTORY_SEPARATOR . 'config.php';
    $newConfig = @include $newConfigPath;
    if (!is_array($newConfig)) fail('New config.php is invalid.');

    foreach (['card_number', 'card_holder', 'base_amount_rial', 'bridge_secret', 'admin_password'] as $key) {
        if (array_key_exists($key, $oldConfig) && $oldConfig[$key] !== '' && $oldConfig[$key] !== null) {
            $newConfig[$key] = $oldConfig[$key];
        }
    }
    $newConfig['site_url'] = 'https://aloasans.ir/vakil';
    $newConfig['ai_endpoint'] = '';
    $newConfig['ai_api_key'] = '';
    $newConfig['ai_model'] = 'LOCAL_RULE_ENGINE_V5';

    $configPhp = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($newConfigPath, $configPhp, LOCK_EX) === false) fail('Could not write merged config.php.');

    rrmdir($tempDir);
    out([
        'ok' => true,
        'deployed_to' => 'public_html/vakil',
        'public_url' => 'https://aloasans.ir/vakil/',
        'backup_created' => basename($backupPath),
        'ai_mode' => 'LOCAL_RULE_ENGINE_V5'
    ]);
} catch (Throwable $e) {
    rrmdir($tempDir);
    fail($e->getMessage());
}
