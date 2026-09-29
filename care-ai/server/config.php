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
        activation_mode TEXT NOT NULL DEFAULT 'trial',
        activated_at INTEGER NOT NULL DEFAULT 0,
        expires_at INTEGER NOT NULL DEFAULT 0,
        auth_token TEXT UNIQUE,
        video_key TEXT UNIQUE,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        last_seen_at TEXT
    )");

    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN activation_mode TEXT NOT NULL DEFAULT 'trial'");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN activated_at INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN expires_at INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}

    // حساب‌های قدیمی که قبلاً active بوده‌اند، با مهاجرت ۳۰ روز اعتبار می‌گیرند
    // تا بعد از بروزرسانی ناگهان قفل نشوند.
    $now = time();
    $monthLater = $now + (30 * 86400);
    $q = $db->prepare("UPDATE accounts
        SET activation_mode='trial',
            activated_at=CASE WHEN activated_at IS NULL OR activated_at=0 THEN ? ELSE activated_at END,
            expires_at=CASE WHEN expires_at IS NULL OR expires_at=0 THEN ? ELSE expires_at END
        WHERE active=1");
    $q->execute([$now,$monthLater]);

    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN video_key TEXT");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN activation_mode TEXT NOT NULL DEFAULT 'inactive'");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN activated_at INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE accounts ADD COLUMN expires_at INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}

    // حساب‌های فعال قدیمی از زمان نصب این نسخه یک ماه اعتبار می‌گیرند.
    $db->exec("UPDATE accounts
        SET activation_mode=CASE
                WHEN activation_mode IS NULL OR activation_mode='' OR activation_mode='inactive'
                    THEN 'active'
                ELSE activation_mode
            END,
            activated_at=CASE
                WHEN activated_at IS NULL OR activated_at=0
                    THEN CAST(strftime('%s','now') AS INTEGER)
                ELSE activated_at
            END,
            expires_at=CASE
                WHEN expires_at IS NULL OR expires_at=0
                    THEN CAST(strftime('%s','now','+1 month') AS INTEGER)
                ELSE expires_at
            END
        WHERE active=1");

    $db->exec("UPDATE accounts
        SET video_key=lower(hex(randomblob(12)))
        WHERE video_key IS NULL OR video_key=''");

    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_video_key
        ON accounts(video_key)");

    $db->exec("CREATE TABLE IF NOT EXISTS video_calls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id TEXT NOT NULL UNIQUE,
        patient_id TEXT NOT NULL,
        join_key TEXT NOT NULL,
        state TEXT NOT NULL DEFAULT 'ringing',
        offer_sdp TEXT,
        answer_sdp TEXT,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_video_calls_patient_state
        ON video_calls(patient_id,state,updated_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS patient_ids (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id TEXT NOT NULL UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 1,
        note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
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
        'update_apk_url' => '',
        'turn_url' => '',
        'turn_user' => '',
        'turn_pass' => '',
        'support_whatsapp' => ''
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

function activation_state(array $account): array {
    $active = (int)($account['active'] ?? 0) === 1;
    $mode = trim((string)($account['activation_mode'] ?? 'new'));
    $expiresAt = (int)($account['expires_at'] ?? 0);
    $now = time();

    if ($mode === 'blocked') {
        return [
            'active'=>false,
            'mode'=>'blocked',
            'expires_at'=>$expiresAt,
            'code'=>'ACCOUNT_BLOCKED',
            'message'=>'این حساب توسط مدیریت غیرفعال شده است.'
        ];
    }

    if ($mode === 'expired'
            || ($expiresAt > 0 && $now >= $expiresAt)
            || ($active && $expiresAt <= 0)) {
        return [
            'active'=>false,
            'mode'=>'expired',
            'expires_at'=>$expiresAt,
            'code'=>'ACTIVATION_EXPIRED',
            'message'=>'اعتبار حساب شما به پایان رسیده، جهت شارژ اعتبار با پشتیبانی تماس بگیرید.'
        ];
    }

    if (!$active) {
        return [
            'active'=>false,
            'mode'=>$mode === '' ? 'new' : $mode,
            'expires_at'=>$expiresAt,
            'code'=>'ACTIVATION_REQUIRED',
            'message'=>'حساب هنوز فعال نشده است.'
        ];
    }

    return [
        'active'=>true,
        'mode'=>$mode === 'trial' ? 'trial' : 'active',
        'expires_at'=>$expiresAt,
        'code'=>'OK',
        'message'=>$mode === 'trial'
            ? 'ماه اول رایگان فعال است.'
            : 'حساب فعال است.'
    ];
}

function account_is_active(array $account): bool {
    return activation_state($account)['active'] === true;
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

function iranpayamak_recipient(string $phone): string {
    $digits = preg_replace('/\\D+/', '', $phone) ?? '';

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98') && strlen($digits) >= 12) {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '0' . $digits;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return $digits;
    }

    return $phone;
}

function send_otp_sms(string $phone, string $code): array {
    $apiKey = trim(setting('sms_token'));
    $lineNumber = trim(setting('sms_sender'));
    $patternCode = trim(setting('sms_pattern'));

    if ($apiKey === '' || $lineNumber === '' || $patternCode === '') {
        return [
            'ok'=>false,
            'error'=>'SMS_NOT_CONFIGURED',
            'message'=>'تنظیمات پیامک کامل نیست؛ API Key، شماره ارسال‌کننده و کد Pattern را در پنل مدیریت وارد کنید.'
        ];
    }

    $recipient = iranpayamak_recipient($phone);

    $payload = [
        'code' => $patternCode,
        'attributes' => [
            'code' => $code
        ],
        'recipient' => $recipient,
        'line_number' => $lineNumber,
        'number_format' => 'english'
    ];

    $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/pattern');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Api-Key: ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )
    ]);

    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok'=>false,
            'error'=>'SMS_CONNECTION_FAILED',
            'message'=>'ارتباط سرور با ایران‌پیامک برقرار نشد.',
            'http'=>$http,
            'detail'=>$curlError
        ];
    }

    $decoded = json_decode($body, true);
    $providerStatus = is_array($decoded)
        ? strtolower((string)($decoded['status'] ?? ''))
        : '';

    $flattenProviderMessage = static function ($value) use (&$flattenProviderMessage): string {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_scalar($value)) return trim((string)$value);

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $text = $flattenProviderMessage($item);
                if ($text === '') continue;

                if (is_string($key) && !ctype_digit($key)) {
                    $parts[] = $key . ': ' . $text;
                } else {
                    $parts[] = $text;
                }
            }
            return implode(' | ', $parts);
        }

        return '';
    };

    $providerMessage = '';
    if (is_array($decoded)) {
        $rawMessage = $decoded['messages']
            ?? $decoded['message']
            ?? $decoded['errors']
            ?? $decoded['error']
            ?? '';

        $providerMessage = $flattenProviderMessage($rawMessage);
    }

    if ($http < 200 || $http >= 300 || ($providerStatus !== '' && $providerStatus !== 'success')) {
        $message = 'ارسال کد تایید توسط ایران‌پیامک ناموفق بود.';

        if ($providerMessage !== '') {
            $message .= ' پاسخ سرویس: ' . $providerMessage;
        } elseif ($http > 0) {
            $message .= ' HTTP ' . $http;
        }

        // اگر پاسخ سرویس ساختار متفاوتی داشت، یک خلاصه امن و قابل‌خواندن بده.
        if ($providerMessage === '' && is_array($decoded)) {
            $summary = $flattenProviderMessage($decoded);
            if ($summary !== '') {
                $message .= ' جزئیات: ' . mb_substr($summary, 0, 600);
            }
        }

        return [
            'ok'=>false,
            'error'=>'SMS_FAILED',
            'message'=>$message,
            'http'=>$http,
            'provider'=>$decoded
        ];
    }

    return [
        'ok'=>true,
        'http'=>$http,
        'provider'=>$decoded
    ];
}

function account_device_public_id(string $deviceId): string {
    $clean = preg_replace('/[^A-Za-z0-9]/', '', $deviceId) ?? '';
    if ($clean === '') $clean = bin2hex(random_bytes(8));
    return 'DEV-' . strtoupper(substr(hash('sha256', $clean), 0, 12));
}

function account_activation_state(array $account): array {
    $base = activation_state($account);

    return [
        'active'=>$base['active'],
        'expired'=>$base['code'] === 'ACTIVATION_EXPIRED',
        'expires_at'=>$base['expires_at'],
        'remaining_days'=>$base['active']
            ? (int)ceil(max(0, $base['expires_at'] - time()) / 86400)
            : 0,
        'mode'=>$base['mode'],
        'code'=>$base['code'],
        'message'=>$base['message']
    ];
}

function expire_account_if_needed(PDO $db, array $account): array {
    $state = account_activation_state($account);

    if ($state['expired'] && (int)($account['active'] ?? 0) === 1) {
        $db->prepare("UPDATE accounts
            SET active=0,activation_mode='expired',updated_at=?
            WHERE id=?")
            ->execute([now_iso(),(int)$account['id']]);

        $account['active'] = 0;
        $account['activation_mode'] = 'expired';
    }

    return $state;
}

function require_admin(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['careai_admin'])) {
        header('Location: admin.php');
        exit;
    }
}
