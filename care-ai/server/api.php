<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$data = input_json();
$action = (string)($data['action'] ?? $_GET['action'] ?? '');

if ($action === 'request_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    if ($phone === '' || $deviceId === '') {
        json_out([
            'ok'=>false,
            'code'=>'MISSING_FIELDS',
            'message'=>'شماره موبایل یا شناسه دستگاه دریافت نشد.'
        ], 422);
    }

    $db = db();

    // حساب از این نسخه با شماره موبایل + شناسه واقعی خود گوشی قفل می‌شود.
    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR device_id=? LIMIT 1');
    $s->execute([$phone,$deviceId]);
    $existing = $s->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['phone'] === $phone
            && str_starts_with((string)$existing['device_id'], 'UNBOUND:')) {
            $db->prepare('UPDATE accounts
                SET device_id=?,auth_token=NULL,active=0,expires_at=0,updated_at=?
                WHERE id=?')
                ->execute([$deviceId,now_iso(),$existing['id']]);
            $existing['device_id'] = $deviceId;
        }

        if ($existing['phone'] === $phone && $existing['device_id'] !== $deviceId) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND_OTHER_DEVICE',
                'message'=>'این شماره قبلاً روی گوشی دیگری ثبت شده است. ابتدا مدیر باید دستگاه قبلی را آزاد کند.'
            ], 409);
        }

        if ($existing['device_id'] === $deviceId && $existing['phone'] !== $phone) {
            json_out([
                'ok'=>false,
                'code'=>'DEVICE_BOUND_OTHER_ACCOUNT',
                'message'=>'این گوشی قبلاً برای حساب دیگری ثبت شده است.'
            ], 409);
        }

        // Patient ID قدیمی فقط برای سازگاری داخلی نگه داشته می‌شود؛
        // کاربر دیگر آن را وارد نمی‌کند.
        $patientId = (string)$existing['patient_id'];
    } else {
        $patientId = account_device_public_id($deviceId);

        // برخورد بسیار بعید hash را هم مدیریت می‌کنیم.
        $base = $patientId;
        $n = 1;
        while (true) {
            $q = $db->prepare('SELECT 1 FROM accounts WHERE patient_id=? LIMIT 1');
            $q->execute([$patientId]);
            if ($q->fetchColumn() === false) break;
            $patientId = $base . '-' . $n++;
        }
    }

    $code = (string)random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = time() + 300;

    $q = $db->prepare('INSERT INTO otp_codes(
            phone,code_hash,patient_id,device_id,expires_at,attempts,created_at
        ) VALUES(?,?,?,?,?,0,?)
        ON CONFLICT(phone) DO UPDATE SET
            code_hash=excluded.code_hash,
            patient_id=excluded.patient_id,
            device_id=excluded.device_id,
            expires_at=excluded.expires_at,
            attempts=0,
            created_at=excluded.created_at');
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

    json_out([
        'ok'=>true,
        'message'=>'کد تایید ارسال شد.',
        'device_id'=>$deviceId,
        'account_id'=>$patientId
    ]);
}

if ($action === 'verify_otp') {
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));
    $code = trim((string)($data['code'] ?? ''));

    if ($phone === '' || $deviceId === '' || $code === '') {
        json_out([
            'ok'=>false,
            'code'=>'MISSING_FIELDS',
            'message'=>'اطلاعات تایید کامل نیست.'
        ], 422);
    }

    $db = db();
    $q = $db->prepare('SELECT * FROM otp_codes WHERE phone=?');
    $q->execute([$phone]);
    $otp = $q->fetch(PDO::FETCH_ASSOC);

    if (!$otp || time() > (int)$otp['expires_at']) {
        json_out([
            'ok'=>false,
            'code'=>'OTP_EXPIRED',
            'message'=>'کد تایید منقضی شده است. دوباره کد بگیرید.'
        ], 401);
    }

    if ((int)$otp['attempts'] >= 6) {
        json_out([
            'ok'=>false,
            'code'=>'OTP_LOCKED',
            'message'=>'تعداد تلاش بیش از حد مجاز است. دوباره کد بگیرید.'
        ], 429);
    }

    if ($otp['device_id'] !== $deviceId) {
        json_out([
            'ok'=>false,
            'code'=>'OTP_CONTEXT_MISMATCH',
            'message'=>'کد تایید برای این گوشی صادر نشده است.'
        ], 409);
    }

    if (!password_verify($code, $otp['code_hash'])) {
        $db->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE phone=?')
            ->execute([$phone]);
        json_out([
            'ok'=>false,
            'code'=>'OTP_INVALID',
            'message'=>'کد تایید صحیح نیست.'
        ], 401);
    }

    $patientId = (string)$otp['patient_id'];

    $s = $db->prepare('SELECT * FROM accounts WHERE phone=? OR device_id=? LIMIT 1');
    $s->execute([$phone,$deviceId]);
    $a = $s->fetch(PDO::FETCH_ASSOC);

    if ($a) {
        if ($a['phone'] !== $phone || $a['device_id'] !== $deviceId) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND',
                'message'=>'این حساب یا گوشی قبلاً به ثبت دیگری متصل شده است.'
            ], 409);
        }

        $token = $a['auth_token'] ?: random_token();
        $videoKey = !empty($a['video_key'])
            ? (string)$a['video_key']
            : random_token(12);

        $db->prepare('UPDATE accounts
            SET auth_token=?,video_key=?,last_seen_at=?,updated_at=?
            WHERE id=?')
            ->execute([$token,$videoKey,now_iso(),now_iso(),$a['id']]);

        // دوباره رکورد را بخوان تا وضعیت فعال‌سازی و انقضا قطعی باشد.
        $s = $db->prepare('SELECT * FROM accounts WHERE id=?');
        $s->execute([$a['id']]);
        $a = $s->fetch(PDO::FETCH_ASSOC);
    } else {
        $token = random_token();
        $videoKey = random_token(12);

        try {
            $db->prepare('INSERT INTO accounts(
                    phone,patient_id,device_id,active,
                    activation_mode,activated_at,expires_at,
                    auth_token,video_key,created_at,updated_at,last_seen_at
                ) VALUES(?,?,?,0,\'trial\',0,0,?,?,?,?,?)')
                ->execute([
                    $phone,$patientId,$deviceId,
                    $token,$videoKey,
                    now_iso(),now_iso(),now_iso()
                ]);

            $id = (int)$db->lastInsertId();
            $s = $db->prepare('SELECT * FROM accounts WHERE id=?');
            $s->execute([$id]);
            $a = $s->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            json_out([
                'ok'=>false,
                'code'=>'ACCOUNT_BOUND',
                'message'=>'این شماره یا گوشی قبلاً ثبت شده است.'
            ], 409);
        }
    }

    $state = expire_account_if_needed($db,$a);

    $db->prepare('DELETE FROM otp_codes WHERE phone=?')->execute([$phone]);

    $message = 'ثبت‌نام انجام شد؛ منتظر فعال‌سازی مدیر باشید.';
    if ($state['active']) {
        $message = 'حساب فعال است. ' . $state['remaining_days'] . ' روز از اعتبار تست باقی مانده است.';
    } elseif ($state['expired']) {
        $message = 'مدت فعال‌سازی ۳۰ روزه این حساب تمام شده است. مدیر باید حساب را تمدید کند.';
    }

    json_out([
        'ok'=>true,
        'token'=>$token,
        'active'=>$state['active'],
        'expired'=>$state['expired'],
        'activation_mode'=>$state['mode'],
        'expires_at'=>$state['expires_at'],
        'remaining_days'=>$state['remaining_days'],
        'patient_id'=>$a['patient_id'],
        'device_id'=>$a['device_id'],
        'video_key'=>$videoKey ?? '',
        'message'=>$message
    ]);
}

if ($action === 'status') {
    $token = trim((string)($data['token'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $db = db();
    $q = $db->prepare('SELECT * FROM accounts WHERE auth_token=? LIMIT 1');
    $q->execute([$token]);
    $a = $q->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
        json_out([
            'ok'=>false,
            'code'=>'AUTH_INVALID',
            'message'=>'نشست فعال‌سازی معتبر نیست. دوباره وارد شوید.'
        ], 401);
    }

    if ($a['device_id'] !== $deviceId) {
        json_out([
            'ok'=>false,
            'code'=>'DEVICE_MISMATCH',
            'message'=>'این حساب برای گوشی دیگری فعال شده است.'
        ], 403);
    }

    $state = expire_account_if_needed($db,$a);

    $db->prepare('UPDATE accounts SET last_seen_at=?,updated_at=? WHERE id=?')
        ->execute([now_iso(),now_iso(),$a['id']]);

    $message = 'حساب هنوز توسط مدیر فعال نشده است.';
    if ($state['active']) {
        $message = 'فعال‌سازی معتبر است. ' . $state['remaining_days'] . ' روز باقی مانده است.';
    } elseif ($state['expired']) {
        $message = 'مدت فعال‌سازی ۳۰ روزه تمام شده است. برای تمدید با مدیر تماس بگیرید.';
    }

    json_out([
        'ok'=>true,
        'active'=>$state['active'],
        'expired'=>$state['expired'],
        'activation_mode'=>$state['mode'],
        'expires_at'=>$state['expires_at'],
        'remaining_days'=>$state['remaining_days'],
        'phone'=>$a['phone'],
        'patient_id'=>$a['patient_id'],
        'device_id'=>$a['device_id'],
        'video_key'=>$a['video_key'] ?? '',
        'message'=>$message
    ]);
}

if ($action === 'video_create_by_key') {
    $patientId = trim((string)($data['patient_id'] ?? ''));
    $key = trim((string)($data['key'] ?? ''));

    $q = db()->prepare('SELECT patient_id,video_key,active FROM accounts WHERE patient_id=? LIMIT 1');
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

    $q = db()->prepare('SELECT patient_id,device_id,active FROM accounts WHERE auth_token=? LIMIT 1');
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
