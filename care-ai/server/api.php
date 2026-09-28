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
        json_out([
            'ok'=>false,
            'code'=>$sent['error'] ?? 'SMS_FAILED',
            'message'=>$sent['message'] ?? 'ارسال پیامک انجام نشد.',
            'http'=>$sent['http'] ?? 0
        ], 503);
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
        $videoKey = !empty($a['video_key']) ? (string)$a['video_key'] : random_token(12);
        $db->prepare('UPDATE accounts SET auth_token=?,video_key=?,updated_at=?,last_seen_at=? WHERE id=?')
            ->execute([$token,$videoKey,now_iso(),now_iso(),$a['id']]);
        $active = (int)$a['active'] === 1;
    } else {
        $token = random_token();
        $videoKey = random_token(12);
        try {
            $db->prepare('INSERT INTO accounts(phone,patient_id,device_id,active,auth_token,video_key,created_at,updated_at,last_seen_at)
                VALUES(?,?,?,0,?,?,?,?,?)')
                ->execute([$phone,$patientId,$deviceId,$token,$videoKey,now_iso(),now_iso(),now_iso()]);
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
        'video_key'=>$videoKey ?? '',
        'message'=>$active ? 'حساب فعال است.' : 'ثبت‌نام انجام شد؛ منتظر فعال‌سازی مدیر باشید.'
    ]);
}

if ($action === 'status') {
    $token = trim((string)($data['token'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $q = db()->prepare('SELECT phone,patient_id,device_id,active,video_key FROM accounts WHERE auth_token=? LIMIT 1');
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
        'patient_id'=>$a['patient_id'],
        'video_key'=>$a['video_key'] ?? ''
    ]);
}

if ($action === 'video_create_by_key') {
    $patientId = trim((string)($data['patient_id'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));

    $q = db()->prepare('SELECT patient_id,video_key,active FROM accounts WHERE patient_id=? LIMIT 1');
    $q->execute([$patientId]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    if (!$a || (int)$a['active'] !== 1 || !hash_equals((string)$a['video_key'], $key)) {
        json_out(['ok'=>false,'code'=>'CALL_LINK_INVALID'], 403);
    }

    db()->prepare("UPDATE video_calls SET state='ended',updated_at=? WHERE patient_id=? AND state IN ('waiting','ringing','active')")
        ->execute([time(),$patientId]);

    $room = random_token(8);
    db()->prepare('INSERT INTO video_calls(room_id,patient_id,join_key,state,created_at,updated_at)
        VALUES(?,?,?,\'waiting\',?,?)')
        ->execute([$room,$patientId,$key,time(),time()]);

    json_out(['ok'=>true,'room'=>$room]);
}

if ($action === 'video_offer') {
    $room = trim((string)($data['room'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));
    $offer = (string)($data['offer'] ?? '');

    $q = db()->prepare('SELECT * FROM video_calls WHERE room_id=? LIMIT 1');
    $q->execute([$room]);
    $call = $q->fetch(PDO::FETCH_ASSOC);

    if (!$call || !hash_equals((string)$call['join_key'],$key)) {
        json_out(['ok'=>false,'code'=>'CALL_INVALID'],403);
    }

    db()->prepare("UPDATE video_calls SET offer_sdp=?,state='ringing',updated_at=? WHERE room_id=?")
        ->execute([$offer,time(),$room]);

    json_out(['ok'=>true]);
}

if ($action === 'video_incoming') {
    $token = trim((string)($data['token'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $q = db()->prepare('SELECT patient_id,device_id,active FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    if (!$a || $a['device_id'] !== $deviceId || (int)$a['active'] !== 1) {
        json_out(['ok'=>false,'code'=>'AUTH_INVALID'],401);
    }

    $q = db()->prepare("SELECT room_id,updated_at FROM video_calls
        WHERE patient_id=? AND state='ringing' AND updated_at>=?
        ORDER BY updated_at DESC LIMIT 1");
    $q->execute([$a['patient_id'],time()-180]);
    $call = $q->fetch(PDO::FETCH_ASSOC);

    json_out([
        'ok'=>true,
        'incoming'=>(bool)$call,
        'room'=>$call['room_id'] ?? ''
    ]);
}

if ($action === 'video_get_offer') {
    $token = trim((string)($data['token'] ?? ''));
    $room = trim((string)($data['room'] ?? ''));

    $q = db()->prepare('SELECT patient_id,active FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);
    if (!$a || (int)$a['active'] !== 1) json_out(['ok'=>false],401);

    $q = db()->prepare('SELECT patient_id,offer_sdp,state FROM video_calls WHERE room_id=? LIMIT 1');
    $q->execute([$room]);
    $call = $q->fetch(PDO::FETCH_ASSOC);

    if (!$call || $call['patient_id'] !== $a['patient_id']) {
        json_out(['ok'=>false,'code'=>'CALL_INVALID'],403);
    }

    json_out([
        'ok'=>true,
        'offer'=>$call['offer_sdp'] ?? '',
        'state'=>$call['state']
    ]);
}

if ($action === 'video_answer') {
    $token = trim((string)($data['token'] ?? ''));
    $room = trim((string)($data['room'] ?? ''));
    $answer = (string)($data['answer'] ?? '');

    $q = db()->prepare('SELECT patient_id,active FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);
    if (!$a || (int)$a['active'] !== 1) json_out(['ok'=>false],401);

    $q = db()->prepare('SELECT patient_id FROM video_calls WHERE room_id=? LIMIT 1');
    $q->execute([$room]);
    $pid = $q->fetchColumn();

    if ($pid === false || (string)$pid !== (string)$a['patient_id']) {
        json_out(['ok'=>false,'code'=>'CALL_INVALID'],403);
    }

    db()->prepare("UPDATE video_calls SET answer_sdp=?,state='active',updated_at=? WHERE room_id=?")
        ->execute([$answer,time(),$room]);

    json_out(['ok'=>true]);
}

if ($action === 'video_get_answer') {
    $room = trim((string)($data['room'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));

    $q = db()->prepare('SELECT join_key,answer_sdp,state FROM video_calls WHERE room_id=? LIMIT 1');
    $q->execute([$room]);
    $call = $q->fetch(PDO::FETCH_ASSOC);

    if (!$call || !hash_equals((string)$call['join_key'],$key)) {
        json_out(['ok'=>false],403);
    }

    json_out([
        'ok'=>true,
        'answer'=>$call['answer_sdp'] ?? '',
        'state'=>$call['state']
    ]);
}

if ($action === 'video_end') {
    $room = trim((string)($data['room'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));
    $token = trim((string)($data['token'] ?? ''));

    $allowed = false;

    if ($key !== '') {
        $q = db()->prepare('SELECT join_key FROM video_calls WHERE room_id=? LIMIT 1');
        $q->execute([$room]);
        $stored = $q->fetchColumn();
        $allowed = $stored !== false && hash_equals((string)$stored,$key);
    }

    if (!$allowed && $token !== '') {
        $q = db()->prepare('SELECT a.patient_id FROM accounts a
            JOIN video_calls v ON v.patient_id=a.patient_id
            WHERE a.auth_token=? AND v.room_id=? LIMIT 1');
        $q->execute([$token,$room]);
        $allowed = $q->fetchColumn() !== false;
    }

    if (!$allowed) json_out(['ok'=>false],403);

    db()->prepare("UPDATE video_calls SET state='ended',updated_at=? WHERE room_id=?")
        ->execute([time(),$room]);

    json_out(['ok'=>true]);
}

if ($action === 'rtc_config') {
    $servers = [
        ['urls'=>['stun:stun.l.google.com:19302','stun:stun1.l.google.com:19302']]
    ];

    $turnUrl = trim(setting('turn_url'));
    if ($turnUrl !== '') {
        $servers[] = [
            'urls'=>[$turnUrl],
            'username'=>setting('turn_user'),
            'credential'=>setting('turn_pass')
        ];
    }

    json_out(['ok'=>true,'iceServers'=>$servers]);
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
