<?php
declare(strict_types=1);

const CAREAI_DB = __DIR__ . '/data/careai.sqlite';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dir = dirname(CAREAI_DB);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . CAREAI_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        k TEXT PRIMARY KEY,
        v TEXT NOT NULL DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT NOT NULL UNIQUE,
        patient_id TEXT NOT NULL UNIQUE,
        device_id TEXT NOT NULL UNIQUE,
        active INTEGER NOT NULL DEFAULT 0,
        auth_token TEXT UNIQUE,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        last_seen_at TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        phone TEXT PRIMARY KEY,
        code_hash TEXT NOT NULL,
        patient_id TEXT NOT NULL,
        device_id TEXT NOT NULL,
        expires_at INTEGER NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )");

    $defaults = [
        'admin_user' => 'admin',
        'admin_pass_hash' => '',
        'sms_token' => '',
        'sms_sender' => '',
        'sms_pattern' => '',
        'api_base_url' => '',
        'update_version_code' => '0',
        'update_version_name' => '',
        'update_notes' => '',
        'update_apk_url' => ''
    ];

    $stmt = $db->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(?,?)');
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
}

function setting(string $key, string $default=''): string {
    $s = db()->prepare('SELECT v FROM settings WHERE k=?');
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function set_setting(string $key, string $value): void {
    $s = db()->prepare('INSERT INTO settings(k,v) VALUES(?,?)
        ON CONFLICT(k) DO UPDATE SET v=excluded.v');
    $s->execute([$key, $value]);
}

function json_out(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function normalize_phone(string $phone): string {
    $p = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($p, '0098')) $p = substr($p, 4);
    if (str_starts_with($p, '98') && strlen($p) >= 12) $p = substr($p, 2);
    if (str_starts_with($p, '0')) $p = substr($p, 1);
    if (strlen($p) === 10 && str_starts_with($p, '9')) return '+98' . $p;
    if (str_starts_with($phone, '+')) return $phone;
    return '+' . $p;
}

function now_iso(): string {
    return gmdate('c');
}

function random_token(int $bytes=32): string {
    return bin2hex(random_bytes($bytes));
}

function send_otp_sms(string $phone, string $code): array {
    $token = trim(setting('sms_token'));
    $sender = trim(setting('sms_sender'));
    $pattern = trim(setting('sms_pattern'));

    if ($token === '' || $sender === '' || $pattern === '') {
        return ['ok'=>false, 'error'=>'SMS_NOT_CONFIGURED'];
    }

    $payload = [
        'sending_type' => 'pattern',
        'from_number' => $sender,
        'code' => $pattern,
        'recipients' => [$phone],
        'params' => ['code' => $code]
    ];

    $ch = curl_init('https://edge.ippanel.com/v1/api/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $http < 200 || $http >= 300) {
        return ['ok'=>false, 'error'=>'SMS_FAILED', 'http'=>$http, 'detail'=>$err];
    }

    return ['ok'=>true];
}

function require_admin(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['careai_admin'])) {
        header('Location: admin.php');
        exit;
    }
}
