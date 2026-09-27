<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$data = input_json();
$action = (string)($data['action'] ?? $_GET['action'] ?? '');

if ($action === 'request_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $patientId = trim((string)($data['patient_id'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    if ($phone === '' || $patientId === '' || $deviceId === '') {
        json_out(['ok'=>false,'code'=>'MISSING_FIELDS'], 422);
    }

    $db = db();

    $pid = $db->prepare('SELECT enabled FROM patient_ids WHERE patient_id=? LIMIT 1');
    $pid->execute([$patientId]);
    $pidEnabled = $pid->fetchColumn();
    if ($pidEnabled === false || (int)$pidEnabled !== 1) {
        json_out([
            'ok'=>false,
            'code'=>'PATIENT_ID_NOT_ALLOWED',
            'message'=>'این آیدی بیمار در پنل مدیریت ثبت یا فعال نشده است.'
        ], 403);
    }

    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR patient_id=? OR device_id=? LIMIT 1');
    $s->execute([$phone, $patientId, $deviceId]);
    $existing = $s->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['phone'] === $phone
            && str_starts_with((string)$existing['device_id'], 'UNBOUND:')) {
            if ($existing['patient_id'] !== $patientId) {
                json_out(['ok'=>false,'code'=>'ACCOUNT_MISMATCH',
                    'message'=>'این حساب برای بیمار دیگری ثبت شده است.'], 409);
            }
            $db->prepare('UPDATE accounts SET device_id=?,auth_token=NULL,updated_at=? WHERE id=?')
                ->execute([$deviceId,now_iso(),$existing['id']]);
            $existing['device_id'] = $deviceId;
        }

        if ($existing['phone'] === $phone && $existing['device_id'] !== $deviceId) {
            json_out(['ok'=>false,'code'=>'ACCOUNT_BOUND_OTHER_DEVICE',
                'message'=>'این حساب قبلاً روی گوشی دیگری ثبت شده است.'], 409);
        }
        if ($existing['patient_id'] === $patientId && $existing['device_id'] !== $deviceId) {
            json_out(['ok'=>false,'code'=>'PATIENT_BOUND_OTHER_DEVICE',
                'message'=>'این آیدی بیمار قبلاً روی گوشی دیگری ثبت شده است.'], 409);
        }
        if ($existing['device_id'] === $deviceId && $existing['patient_id'] !== $patientId) {
            json_out(['ok'=>false,'code'=>'DEVICE_BOUND_OTHER_PATIENT',
                'message'=>'این حساب/گوشی قبلاً برای بیمار دیگری ثبت شده است.'], 409);
        }
        if ($existing['phone'] !== $phone || $existing['patient_id'] !== $patientId) {
            json_out(['ok'=>false,'code'=>'ACCOUNT_MISMATCH',
                'message'=>'اطلاعات حساب با ثبت قبلی مطابقت ندارد.'], 409);
        }
    }

    $code = (string)random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = time() + 300;

    $q = $db->prepare('INSERT INTO otp_codes(phone,code_hash,patient_id,device_id,expires_at,attempts,created_at)
        VALUES(?,?,?,?,?,0,?)
        ON CONFLICT(phone) DO UPDATE SET
        code_hash=excluded.code_hash, patient_id=excluded.patient_id,
        device_id=excluded.device_id, expires_at=excluded.expires_at,
        attempts=0, created_at=excluded.created_at');
    $q->execute([$phone,$hash,$patientId,$deviceId,$expires,now_iso()]);

    $sent = send_otp_sms($phone, $code);
    if (!$sent['ok']) {
        json_out(['ok'=>false,'code'=>$sent['error'] ?? 'SMS_FAILED'], 503);
    }

    json_out(['ok'=>true,'message'=>'کد تایید ارسال شد.']);
}

if ($action === 'verify_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $patientId = trim((string)($data['patient_id'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));
    $code = trim((string)($data['code'] ?? ''));

    $db = db();
    $q = $db->prepare('SELECT * FROM otp_codes WHERE phone=?');
    $q->execute([$phone]);
    $otp = $q->fetch(PDO::FETCH_ASSOC);

    if (!$otp || time() > (int)$otp['expires_at']) {
        json_out(['ok'=>false,'code'=>'OTP_EXPIRED','message'=>'کد منقضی شده است.'], 401);
    }
    if ((int)$otp['attempts'] >= 6) {
        json_out(['ok'=>false,'code'=>'OTP_LOCKED','message'=>'تعداد تلاش بیش از حد مجاز است.'], 429);
    }
    if ($otp['patient_id'] !== $patientId || $otp['device_id'] !== $deviceId) {
        json_out(['ok'=>false,'code'=>'OTP_CONTEXT_MISMATCH'], 409);
    }
    if (!password_verify($code, $otp['code_hash'])) {
        $db->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE phone=?')->execute([$phone]);
        json_out(['ok'=>false,'code'=>'OTP_INVALID','message'=>'کد تایید صحیح نیست.'], 401);
    }

    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR patient_id=? OR device_id=? LIMIT 1');
    $s->execute([$phone,$patientId,$deviceId]);
    $a = $s->fetch(PDO::FETCH_ASSOC);

    if ($a) {
        if ($a['phone'] !== $phone || $a['patient_id'] !== $patientId || $a['device_id'] !== $deviceId) {
            json_out(['ok'=>false,'code'=>'ACCOUNT_BOUND',
                'message'=>'این حساب یا بیمار قبلاً روی گوشی دیگری ثبت شده است.'], 409);
        }
        $token = $a['auth_token'] ?: random_token();
        $db->prepare('UPDATE accounts SET auth_token=?, updated_at=?, last_seen_at=? WHERE id=?')
            ->execute([$token,now_iso(),now_iso(),$a['id']]);
        $active = (int)$a['active'] === 1;
    } else {
        $token = random_token();
        try {
            $db->prepare('INSERT INTO accounts(phone,patient_id,device_id,active,auth_token,created_at,updated_at,last_seen_at)
                VALUES(?,?,?,0,?,?,?,?)')
                ->execute([$phone,$patientId,$deviceId,$token,now_iso(),now_iso(),now_iso()]);
        } catch (PDOException $e) {
            json_out(['ok'=>false,'code'=>'ACCOUNT_BOUND',
                'message'=>'این حساب، آیدی بیمار یا گوشی قبلاً ثبت شده است.'], 409);
        }
        $active = false;
    }

    $db->prepare('DELETE FROM otp_codes WHERE phone=?')->execute([$phone]);
    json_out([
        'ok'=>true,
        'token'=>$token,
        'active'=>$active,
        'patient_id'=>$patientId,
        'message'=>$active ? 'حساب فعال است.' : 'ثبت‌نام انجام شد؛ منتظر فعال‌سازی مدیر باشید.'
    ]);
}

if ($action === 'status') {
    $token = trim((string)($data['token'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $q = db()->prepare('SELECT phone,patient_id,device_id,active FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    if (!$a) json_out(['ok'=>false,'code'=>'AUTH_INVALID'], 401);
    if ($a['device_id'] !== $deviceId) {
        json_out(['ok'=>false,'code'=>'DEVICE_MISMATCH',
            'message'=>'این حساب روی دستگاه دیگری ثبت شده است.'], 403);
    }

    db()->prepare('UPDATE accounts SET last_seen_at=?,updated_at=? WHERE auth_token=?')
        ->execute([now_iso(),now_iso(),$token]);

    json_out([
        'ok'=>true,
        'active'=>(int)$a['active'] === 1,
        'phone'=>$a['phone'],
        'patient_id'=>$a['patient_id']
    ]);
}

if ($action === 'update_check') {
    $base = trim(setting('api_base_url'));
    $apk = trim(setting('update_apk_url'));
    if ($apk === '' && $base !== '') $apk = rtrim($base,'/') . '/updates/CareAI-latest.apk';

    json_out([
        'ok'=>true,
        'version_code'=>(int)setting('update_version_code','0'),
        'version_name'=>setting('update_version_name'),
        'notes'=>setting('update_notes'),
        'apk_url'=>$apk
    ]);
}

json_out(['ok'=>false,'code'=>'UNKNOWN_ACTION'], 404);
