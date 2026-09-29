<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();

$db = db();
$flash = '';

if (isset($_POST['setup_admin']) && setting('admin_pass_hash') === '') {
    $user = trim((string)($_POST['user'] ?? 'admin'));
    $pass = (string)($_POST['pass'] ?? '');
    if (strlen($pass) >= 8) {
        set_setting('admin_user', $user);
        set_setting('admin_pass_hash', password_hash($pass, PASSWORD_DEFAULT));
        $flash = 'مدیر ساخته شد. اکنون وارد شوید.';
    } else $flash = 'رمز حداقل ۸ کاراکتر باشد.';
}

if (isset($_POST['login'])) {
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    if ($user === setting('admin_user')
        && password_verify($pass, setting('admin_pass_hash'))) {
        $_SESSION['careai_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $flash = 'نام کاربری یا رمز اشتباه است.';
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$logged = !empty($_SESSION['careai_admin']);

if ($logged && isset($_POST['account_action'])) {
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['account_action'] ?? '');

    if (in_array($action, ['activate','test','renew_active','renew_test'], true)) {
        $now = time();
        $until = strtotime('+1 month', $now);

        $mode = in_array($action, ['test','renew_test'], true)
            ? 'test'
            : 'active';

        $db->prepare("UPDATE accounts
            SET active=1,
                activation_mode=?,
                activated_at=?,
                expires_at=?,
                updated_at=?
            WHERE id=?")
            ->execute([$mode,$now,$until,now_iso(),$id]);

        $flash = $mode === 'test'
            ? 'حساب در حالت تست برای یک ماه فعال شد.'
            : 'حساب برای یک ماه فعال شد.';
    }

    if ($action === 'deactivate') {
        $db->prepare("UPDATE accounts
            SET active=0,
                activation_mode='blocked',
                updated_at=?
            WHERE id=?")
            ->execute([now_iso(),$id]);

        $flash = 'حساب غیرفعال شد.';
    }

    if ($action === 'reset_device') {
        $db->prepare("UPDATE accounts
            SET device_id='UNBOUND:'||id||':'||strftime('%s','now'),
                auth_token=NULL,
                active=0,
                activation_mode='inactive',
                activated_at=0,
                expires_at=0,
                updated_at=?
            WHERE id=?")
            ->execute([now_iso(),$id]);

        $flash = 'اتصال حساب به گوشی آزاد شد. کاربر می‌تواند با همان شماره روی گوشی جدید ثبت شود.';
    }

    if ($action === 'delete') {
        $db->prepare('DELETE FROM accounts WHERE id=?')->execute([$id]);
        $flash = 'حساب حذف شد.';
    }
}

if ($logged && isset($_POST['test_sms'])) {
    $testPhone = trim((string)($_POST['test_phone'] ?? ''));
    if ($testPhone === '') {
        $flash = 'شماره تست را وارد کنید.';
    } else {
        $testCode = (string)random_int(100000,999999);
        $testResult = send_otp_sms(normalize_phone($testPhone), $testCode);
        $flash = $testResult['ok']
            ? 'پیامک تست با موفقیت ارسال شد. کد تست: ' . $testCode
            : ($testResult['message'] ?? ('خطای پیامک: ' . ($testResult['error'] ?? 'UNKNOWN')));
    }
}

if ($logged && isset($_POST['save_sms'])) {
    set_setting('sms_token', trim((string)($_POST['sms_token'] ?? '')));
    set_setting('sms_sender', trim((string)($_POST['sms_sender'] ?? '')));
    set_setting('sms_pattern', trim((string)($_POST['sms_pattern'] ?? '')));
    set_setting('api_base_url', trim((string)($_POST['api_base_url'] ?? '')));
    $flash = 'تنظیمات ذخیره شد.';
}

if ($logged && isset($_POST['save_support'])) {\n    set_setting('support_whatsapp', trim((string)($_POST['support_whatsapp'] ?? '')));\n    $flash = 'شماره واتساپ پشتیبانی ذخیره شد.';\n}\n\nif ($logged && isset($_POST['save_rtc'])) {
    set_setting('turn_url', trim((string)($_POST['turn_url'] ?? '')));
    set_setting('turn_user', trim((string)($_POST['turn_user'] ?? '')));
    set_setting('turn_pass', trim((string)($_POST['turn_pass'] ?? '')));
    $flash = 'تنظیمات تماس تصویری ذخیره شد.';
}

if ($logged && isset($_POST['save_update'])) {
    set_setting('update_version_code', (string)(int)($_POST['version_code'] ?? 0));
    set_setting('update_version_name', trim((string)($_POST['version_name'] ?? '')));
    set_setting('update_notes', trim((string)($_POST['notes'] ?? '')));

    if (!empty($_FILES['apk']['tmp_name'])) {
        $dir = __DIR__ . '/updates';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        move_uploaded_file($_FILES['apk']['tmp_name'], $dir . '/CareAI-latest.apk');

        $base = trim(setting('api_base_url'));
        if ($base !== '') set_setting('update_apk_url', rtrim($base,'/') . '/updates/CareAI-latest.apk');
    }
    $flash = 'نسخه آپدیت ذخیره شد.';
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Care AI Management</title>
<style>
body{font-family:tahoma;background:#f4f9fd;color:#173552;margin:0}.wrap{max-width:1100px;margin:auto;padding:20px}
.card{background:#fff;border-radius:18px;padding:18px;margin:12px 0;box-shadow:0 4px 18px #0c4a6e12}
input,textarea,button{font:inherit;padding:10px;border:1px solid #d6e3ed;border-radius:10px;box-sizing:border-box}
input,textarea{width:100%;margin:5px 0}button{cursor:pointer;background:#1479d6;color:#fff;border:0}
.danger{background:#dc3545}.ok{background:#15a579}.muted{background:#778899}.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
table{width:100%;border-collapse:collapse}td,th{padding:9px;border-bottom:1px solid #e7eef4;text-align:right}
.badge{padding:4px 8px;border-radius:10px;background:#e8f5ff}.on{color:#09834d}.off{color:#c43e3e}
</style></head><body><div class="wrap">
<h1>Care AI — مدیریت</h1>
<?php if($flash):?><div class="card"><?=h($flash)?></div><?php endif;?>

<?php if(setting('admin_pass_hash')===''):?>
<div class="card"><h3>ساخت مدیر</h3><form method="post">
<input name="user" value="admin" placeholder="نام کاربری">
<input name="pass" type="password" placeholder="رمز حداقل ۸ کاراکتر">
<button name="setup_admin">ساخت مدیر</button></form></div>
<?php elseif(!$logged):?>
<div class="card"><h3>ورود مدیر</h3><form method="post">
<input name="user" placeholder="نام کاربری"><input name="pass" type="password" placeholder="رمز">
<button name="login">ورود</button></form></div>
<?php else:?>
<p><a href="?logout=1">خروج</a></p>

<div class="card">
<h3>فعال‌سازی بر اساس شناسه خود گوشی</h3>
<p style="line-height:2;color:#64788c">
کاربر دیگر هیچ Patient ID را دستی وارد نمی‌کند. اپ شناسه دستگاه را مستقیماً از Android می‌خواند
و حساب با <b>شماره موبایل + Device ID</b> قفل می‌شود.
مدیر می‌تواند حساب را در حالت <b>فعال</b> یا <b>تست</b> برای یک ماه فعال کند.
پس از پایان تاریخ، اپ به‌صورت خودکار خطای انقضای فعال‌سازی می‌دهد.
</p>
</div>

<div class="card"><h3>حساب‌ها و فعال‌سازی یک‌ماهه</h3>
<div style="overflow-x:auto">
<table>
<tr>
<th>موبایل</th>
<th>Device ID گوشی</th>
<th>نوع</th>
<th>وضعیت</th>
<th>اعتبار تا</th>
<th>مدیریت</th>
</tr>
<?php foreach($db->query('SELECT * FROM accounts ORDER BY id DESC') as $a):
    $state = expire_account_if_needed($db,$a);
    $isActive = $state['active'];
    $isExpired = $state['expired'];
    $mode = $state['mode'];
    $expiresText = (int)$state['expires_at'] > 0
        ? date('Y-m-d H:i',(int)$state['expires_at'])
        : '—';
?>
<tr>
<td><?=h($a['phone'])?></td>
<td><small style="direction:ltr;display:inline-block;word-break:break-all"><?=h($a['device_id'])?></small></td>
<td>
<?php if($isActive && $mode==='test'):?>
<span class="badge">تست</span>
<?php elseif($isActive):?>
<span class="badge">فعال</span>
<?php elseif($isExpired):?>
<span class="badge">منقضی</span>
<?php else:?>
<span class="badge">—</span>
<?php endif;?>
</td>
<td class="<?=$isActive?'on':'off'?>">
<?php if($isActive):?>
فعال
<?php elseif($isExpired):?>
منقضی
<?php else:?>
غیرفعال
<?php endif;?>
</td>
<td>
<?php if($isActive):?>
<?=h((string)$state['remaining_days'])?> روز باقی‌مانده<br>
<small><?=h($expiresText)?></small>
<?php elseif($isExpired):?>
پایان یافته<br><small><?=h($expiresText)?></small>
<?php else:?>
—
<?php endif;?>
</td>
<td>
<form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
<input type="hidden" name="id" value="<?=$a['id']?>">

<?php if($isActive):?>
<button class="ok" name="account_action" value="<?=$mode==='test'?'renew_test':'renew_active'?>">
تمدید یک ماه
</button>
<button class="muted" name="account_action" value="deactivate">غیرفعال</button>
<?php else:?>
<button class="ok" name="account_action" value="activate">فعال ۱ ماه</button>
<button name="account_action" value="test">تست ۱ ماه</button>
<?php endif;?>

<button name="account_action" value="reset_device">آزادسازی گوشی</button>
<button class="danger" name="account_action" value="delete" onclick="return confirm('حساب حذف شود؟')">حذف</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table>
</div>
</div>

<div class="card"><h3>تنظیمات پیامک OTP — IranPayamak / FarazSMS</h3><form method="post">
<p style="color:#64788c;font-size:13px;line-height:1.9">
ارسال OTP با API جدید IranPayamak انجام می‌شود. Pattern باید متغیر <b>code</b> داشته باشد.
</p>
<div class="row">
<div><label>API Key</label><input name="sms_token" value="<?=h(setting('sms_token'))?>" placeholder="API Key ایران‌پیامک"></div>
<div><label>شماره ارسال‌کننده / Line Number</label><input name="sms_sender" value="<?=h(setting('sms_sender'))?>" placeholder="مثلاً 5000..."></div>
<div><label>کد Pattern</label><input name="sms_pattern" value="<?=h(setting('sms_pattern'))?>" placeholder="کد پترن تأییدشده"></div>
</div>
<label>آدرس پایه سرور</label>
<input name="api_base_url" value="<?=h(setting('api_base_url'))?>" placeholder="https://achinu.ir/careai">
<button name="save_sms">ذخیره تنظیمات پیامک</button></form>

<hr style="border:0;border-top:1px solid #e7eef4;margin:18px 0">

<form method="post">
<label>تست ارسال OTP</label>
<div class="row">
<div><input name="test_phone" placeholder="مثلاً 09121234567"></div>
<div style="align-self:end"><button name="test_sms">ارسال پیامک تست</button></div>
</div>
</form></div>

<div class="card"><h3>تنظیمات تماس تصویری WebRTC</h3>
<form method="post">
<label>TURN URL اختیاری</label>
<input name="turn_url" value="<?=h(setting('turn_url'))?>" placeholder="مثلاً turn:turn.example.com:3478">
<div class="row">
<div><label>TURN Username</label><input name="turn_user" value="<?=h(setting('turn_user'))?>"></div>
<div><label>TURN Password</label><input name="turn_pass" value="<?=h(setting('turn_pass'))?>"></div>
</div>
<p style="color:#64788c;font-size:13px">اگر خالی باشد تماس با STUN انجام می‌شود. برای پایداری بیشتر تماس روی شبکه‌های مختلف، TURN توصیه می‌شود.</p>
<button name="save_rtc">ذخیره تنظیمات تماس</button>
</form></div>

<div class="card"><h3>مدیریت آپدیت اپ</h3><form method="post" enctype="multipart/form-data">
<div class="row"><div><label>Version Code</label><input type="number" name="version_code" value="<?=h(setting('update_version_code','0'))?>"></div>
<div><label>Version Name</label><input name="version_name" value="<?=h(setting('update_version_name'))?>"></div></div>
<label>توضیحات آپدیت</label><textarea name="notes"><?=h(setting('update_notes'))?></textarea>
<label>APK جدید</label><input type="file" name="apk" accept=".apk,application/vnd.android.package-archive">
<button name="save_update">ثبت و انتشار آپدیت</button></form></div>
<?php endif;?>
</div></body></html>
