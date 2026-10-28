<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function enabled_fn($name) {
    if (!function_exists($name)) return false;
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array($name, $disabled, true);
}
function cmd_exists($cmd) {
    if (!enabled_fn('shell_exec')) return false;
    $out = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
    return is_string($out) && trim($out) !== '';
}
function run_short($cmd) {
    if (!enabled_fn('shell_exec')) return null;
    $out = @shell_exec($cmd . ' 2>&1');
    if (!is_string($out)) return null;
    return trim(substr($out, 0, 300));
}

$bins = ['chromium','chromium-browser','google-chrome','google-chrome-stable','node','npm','python3','docker','podman','xvfb-run'];
$found = [];
foreach ($bins as $bin) $found[$bin] = cmd_exists($bin);

$processFns = [
  'exec' => enabled_fn('exec'),
  'shell_exec' => enabled_fn('shell_exec'),
  'system' => enabled_fn('system'),
  'passthru' => enabled_fn('passthru'),
  'proc_open' => enabled_fn('proc_open'),
  'popen' => enabled_fn('popen'),
];

$nodeVersion = $found['node'] ? run_short('node --version') : null;
$pythonVersion = $found['python3'] ? run_short('python3 --version') : null;
$chromeVersion = null;
foreach (['chromium','chromium-browser','google-chrome','google-chrome-stable'] as $c) {
    if ($found[$c]) { $chromeVersion = run_short($c . ' --version'); break; }
}

$result = [
  'ok' => true,
  'php_version' => PHP_VERSION,
  'sapi' => PHP_SAPI,
  'os_family' => PHP_OS_FAMILY,
  'memory_limit' => ini_get('memory_limit'),
  'max_execution_time' => ini_get('max_execution_time'),
  'tmp_writable' => is_writable(sys_get_temp_dir()),
  'process_functions' => $processFns,
  'binaries' => $found,
  'versions' => [
      'node' => $nodeVersion,
      'python3' => $pythonVersion,
      'chrome' => $chromeVersion,
  ],
  'browser_runtime_possible' => ($processFns['proc_open'] || $processFns['shell_exec']) && ($found['chromium'] || $found['chromium-browser'] || $found['google-chrome'] || $found['google-chrome-stable']),
  'note' => 'Diagnostic only; no secrets, usernames, passwords, paths, IPs, environment variables, or account data are exposed.'
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
