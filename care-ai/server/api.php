<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$data = input_json();
$action = (string)($data['action'] ?? $_GET['action'] ?? '');

if ($action === 'request_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    if ($phone === '' || $deviceId === '' || $deviceId === 'unknown-device') {
        json_out([
            'ok'=>false,
            'code'=>'MISSING_FIELDS',
            'message'=>'شماره موبایل یا شناسه دستگاه معتبر نیست.'
        ], 422);
    }

    $db = db();

    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR device_id=? LIMIT 1');
    $s->execute([$phone,$deviceId]);
    $existing = $s->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $storedDevice = (string)$existing['device_id'];

        // انتقال به گوشی جدید فقط بعد از «آزادسازی گوشی» توسط مدیر مجاز است.
        if ($existing['phone'] === $phone && str_starts_with($storedDevice,'UNBOUND:')) {
            $db->prepare('UPDATE accounts
                SET device_id=?,auth_token=NULL,active=0,activation_mode=\'inactive\',
                    activated_at=0,expires_at=0,updated_at=?
                WHERE id=?')
                ->execute([$deviceId,now_iso(),$existing['id']]);

            $existing['device_id'] = $deviceId;
            $existing['active'] = 0;
            $existing['activation_mode'] = 'inactive';
            $existing['activated_at'] = 0;
            $existing['expires_at'] = 0;
        }

        if ((string)$existing['phone'] !== $phone) {
            json_out([
                'ok'=>false,
                'code'=>'DEVICE_BOUND_OTHER_ACCOUNT',
                'message'=>'این گوشی قبلاً برای حساب دیگری ثبت شده است.'
            ], 409);
        }

        if ((string)$existing['device_id'] !== $deviceId) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND_OTHER_DEVICE',
                'message'=>'این حساب قبلاً روی گوشی دیگری ثبت شده است. ابتدا مدیر باید گوشی قبلی را آزاد کند.'
            ], 409);
        }

        // برای سازگاری نسخه‌های قبلی، شناسه داخلی بیمار حفظ می‌شود.
        $patientId = trim((string)$existing['patient_id']);
        if ($patientId === '') $patientId = $deviceId;
    } else {
        // از این نسخه به بعد شناسه داخلی حساب از Device ID خود گوشی ساخته می‌شود.
        $patientId = $deviceId;
    }

    $code = (string)random_int(100000,999999);
    $hash = password_hash($code,PASSWORD_DEFAULT);
    $expires = time()+300;

    $q = $db->prepare('INSERT INTO otp_codes(phone,code_hash,patient_id,device_id,expires_at,attempts,created_at)
        VALUES(?,?,?,?,?,0,?)
        ON CONFLICT(phone) DO UPDATE SET
        code_hash=excluded.code_hash,
        patient_id=excluded.patient_id,
        device_id=excluded.device_id,
        expires_at=excluded.expires_at,
        attempts=0,
        created_at=excluded.created_at');
    $q->execute([$phone,$hash,$patientId,$deviceId,$expires,now_iso()]);

    $sent = send_otp_sms($phone,$code);
    if (!$sent['ok']) {
        json_out([
            'ok'=>false,
            'code'=>$sent['error'] ?? 'SMS_FAILED',
            'message'=>$sent['message'] ?? 'ارسال پیامک انجام نشد.',
            'http'=>$sent['http'] ?? 0
        ],503);
    }

    json_out([
        'ok'=>true,
        'device_id'=>$deviceId,
        'message'=>'کد تایید ارسال شد.'
    ]);
}

if ($action === 'verify_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));
    $code = trim((string)($data['code'] ?? ''));

    $db = db();

    $q = $db->prepare('SELECT * FROM otp_codes WHERE phone=?');
    $q->execute([$phone]);
    $otp = $q->fetch(PDO::FETCH_ASSOC);

    if (!$otp || time() > (int)$otp['expires_at']) {
        json_out(['ok'=>false,'code'=>'OTP_EXPIRED','message'=>'کد تایید منقضی شده است.'],401);
    }

    if ((int)$otp['attempts'] >= 6) {
        json_out(['ok'=>false,'code'=>'OTP_LOCKED','message'=>'تعداد تلاش بیش از حد مجاز است.'],429);
    }

    if ((string)$otp['device_id'] !== $deviceId) {
        json_out([
            'ok'=>false,
            'code'=>'OTP_DEVICE_MISMATCH',
            'message'=>'کد تایید برای این گوشی صادر نشده است.'
        ],409);
    }

    if (!password_verify($code,(string)$otp['code_hash'])) {
        $db->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE phone=?')
            ->execute([$phone]);
        json_out(['ok'=>false,'code'=>'OTP_INVALID','message'=>'کد تایید صحیح نیست.'],401);
    }

    $patientId = trim((string)$otp['patient_id']);
    if ($patientId === '') $patientId = $deviceId;

    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR device_id=? LIMIT 1');
    $s->execute([$phone,$deviceId]);
    $a = $s->fetch(PDO::FETCH_ASSOC);

    if ($a) {
        if ((string)$a['phone'] !== $phone || (string)$a['device_id'] !== $deviceId) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND',
                'message'=>'این حساب یا گوشی قبلاً به ثبت دیگری متصل شده است.'
            ],409);
        }

        $patientId = trim((string)$a['patient_id']);
        if ($patientId === '') $patientId = $deviceId;

        $token = !empty($a['auth_token']) ? (string)$a['auth_token'] : random_token();
        $videoKey = !empty($a['video_key']) ? (string)$a['video_key'] : random_token(12);

        $db->prepare('UPDATE accounts
            SET auth_token=?,video_key=?,updated_at=?,last_seen_at=?
            WHERE id=?')
            ->execute([$token,$videoKey,now_iso(),now_iso(),$a['id']]);

        $state = activation_state($a);
    } else {
        $token = random_token();
        $videoKey = random_token(12);

        try {
            $db->prepare('INSERT INTO accounts(
                    phone,patient_id,device_id,active,activation_mode,activated_at,expires_at,
                    auth_token,video_key,created_at,updated_at,last_seen_at
                ) VALUES(?,?,?,0,\'inactive\',0,0,?,?,?,?,?)')
                ->execute([
                    $phone,$patientId,$deviceId,$token,$videoKey,
                    now_iso(),now_iso(),now_iso()
                ]);
        } catch (PDOException $e) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND',
                'message'=>'این شماره یا گوشی قبلاً ثبت شده است.'
            ],409);
        }

        $state = [
            'active'=>false,
            'mode'=>'inactive',
            'expires_at'=>0,
            'code'=>'ACTIVATION_REQUIRED',
            'message'=>'ثبت‌نام انجام شد؛ منتظر فعال‌سازی مدیر باشید.'
        ];
    }

    $db->prepare('DELETE FROM otp_codes WHERE phone=?')->execute([$phone]);

    json_out([
        'ok'=>true,
        'token'=>$token,
        'active'=>$state['active'],
        'activation_mode'=>$state['mode'],
        'expires_at'=>$state['expires_at'],
        'activation_code'=>$state['code'],
        'device_id'=>$deviceId,
        'patient_id'=>$patientId,
        'video_key'=>$videoKey ?? '',
        'message'=>$state['active']
            ? $state['message']
            : ($state['code']==='ACTIVATION_EXPIRED'
                ? $state['message']
                : 'ثبت‌نام انجام شد؛ منتظر فعال‌سازی مدیر باشید.')
    ]);
}

if ($action === 'status') {
    $token = trim((string)($data['token'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $q = db()->prepare('SELECT * FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
        json_out([
            'ok'=>false,
            'code'=>'AUTH_INVALID',
            'message'=>'نشست فعال‌سازی معتبر نیست. دوباره وارد حساب شوید.'
        ],401);
    }

    if ((string)$a['device_id'] !== $deviceId) {
        json_out([
            'ok'=>false,
            'code'=>'DEVICE_MISMATCH',
            'message'=>'این حساب برای گوشی دیگری فعال شده است.'
        ],403);
    }

    $state = activation_state($a);

    if (!$state['active']
            && $state['code'] === 'ACTIVATION_EXPIRED'
            && (int)$a['active'] === 1) {
        db()->prepare("UPDATE accounts
            SET active=0,activation_mode='expired',updated_at=?
            WHERE id=?")
            ->execute([now_iso(),$a['id']]);
    }

    db()->prepare('UPDATE accounts SET last_seen_at=?,updated_at=? WHERE id=?')
        ->execute([now_iso(),now_iso(),$a['id']]);

    json_out([
        'ok'=>true,
        'active'=>$state['active'],
        'activation_mode'=>$state['mode'],
        'expires_at'=>$state['expires_at'],
        'activation_code'=>$state['code'],
        'message'=>$state['message'],
        'phone'=>$a['phone'],
        'device_id'=>$a['device_id'],
        'patient_id'=>$a['patient_id'],
        'video_key'=>$a['video_key'] ?? ''
    ]);
}

if ($action === 'video_create_by_key') {
    $patientId = trim((string)($data['patient_id'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));

    $q = db()->prepare('SELECT patient_id,video_key,active,activation_mode,expires_at FROM accounts WHERE patient_id=? LIMIT 1');
    $q->execute([$patientId]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    $videoState = $a ? account_activation_state($a) : ['active'=>false];
    if (!$a || !$videoState['active'] || !hash_equals((string)$a['video_key'], $key)) {
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

    $q = db()->prepare('SELECT patient_id,device_id,active,activation_mode,expires_at FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    $incomingState = $a ? account_activation_state($a) : ['active'=>false];
    if (!$a || $a['device_id'] !== $deviceId || !$incomingState['active']) {
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

    $q = db()->prepare('SELECT patient_id,active,activation_mode,activated_at,expires_at FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);
    $callState = $a ? account_activation_state($a) : ['active'=>false];
    if (!$a || !$callState['active']) json_out(['ok'=>false,'code'=>'ACTIVATION_REQUIRED'],401);

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

    $q = db()->prepare('SELECT patient_id,active,activation_mode,expires_at FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);
    if (!$a || !account_is_active($a)) json_out(['ok'=>false,'code'=>'ACTIVATION_REQUIRED'],401);

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
