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

    if ($action === 'activate') $db->prepare('UPDATE accounts SET active=1,updated_at=? WHERE id=?')->execute([now_iso(),$id]);
    if ($action === 'deactivate') $db->prepare('UPDATE accounts SET active=0,updated_at=? WHERE id=?')->execute([now_iso(),$id]);
    if ($action === 'reset_device') $db->prepare("UPDATE accounts SET device_id='RESET-'||id||'-'||strftime('%s','now'),auth_token=NULL,updated_at=? WHERE id=?")->execute([now_iso(),$id]);
    if ($action === 'delete') $db->prepare('DELETE FROM accounts WHERE id=?')->execute([$id]);

    if ($action === 'update_patient') {
        $patientId = trim((string)($_POST['patient_id'] ?? ''));
        try {
            $db->prepare('UPDATE accounts SET patient_id=?,updated_at=? WHERE id=?')->execute([$patientId,now_iso(),$id]);
        } catch (PDOException $e) {
            $flash = 'این Patient ID قبلاً استفاده شده است.';
        }
    }
}

if ($logged && isset($_POST['save_sms'])) {
    set_setting('sms_token', trim((string)($_POST['sms_token'] ?? '')));
    set_setting('sms_sender', trim((string)($_POST['sms_sender'] ?? '')));
    set_setting('sms_pattern', trim((string)($_POST['sms_pattern'] ?? '')));
    set_setting('api_base_url', trim((string)($_POST['api_base_url'] ?? '')));
    $flash = 'تنظیمات ذخیره شد.';
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

<div class="card"><h3>حساب‌ها و Patient ID</h3>
<table><tr><th>موبایل</th><th>Patient ID</th><th>Device</th><th>وضعیت</th><th>مدیریت</th></tr>
<?php foreach($db->query('SELECT * FROM accounts ORDER BY id DESC') as $a):?>
<tr>
<td><?=h($a['phone'])?></td>
<td><form method="post"><input type="hidden" name="id" value="<?=$a['id']?>"><input name="patient_id" value="<?=h($a['patient_id'])?>">
<button name="account_action" value="update_patient">تغییر ID</button></form></td>
<td><small><?=h(substr($a['device_id'],0,18))?>…</small></td>
<td class="<?=$a['active']?'on':'off'?>"><?=$a['active']?'فعال':'غیرفعال'?></td>
<td>
<form method="post" style="display:flex;gap:4px;flex-wrap:wrap">
<input type="hidden" name="id" value="<?=$a['id']?>">
<?php if($a['active']):?><button class="muted" name="account_action" value="deactivate">غیرفعال</button>
<?php else:?><button class="ok" name="account_action" value="activate">فعال</button><?php endif;?>
<button name="account_action" value="reset_device">آزادسازی گوشی</button>
<button class="danger" name="account_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button>
</form></td></tr>
<?php endforeach;?></table></div>

<div class="card"><h3>تنظیمات پیامک OTP — IPPanel/FarazSMS</h3><form method="post">
<div class="row"><div><label>API Token</label><input name="sms_token" value="<?=h(setting('sms_token'))?>"></div>
<div><label>شماره ارسال‌کننده</label><input name="sms_sender" value="<?=h(setting('sms_sender'))?>"></div>
<div><label>کد Pattern</label><input name="sms_pattern" value="<?=h(setting('sms_pattern'))?>"></div></div>
<label>آدرس پایه سرور (مثال https://achinu.ir/careai)</label>
<input name="api_base_url" value="<?=h(setting('api_base_url'))?>">
<button name="save_sms">ذخیره تنظیمات</button></form></div>

<div class="card"><h3>مدیریت آپدیت اپ</h3><form method="post" enctype="multipart/form-data">
<div class="row"><div><label>Version Code</label><input type="number" name="version_code" value="<?=h(setting('update_version_code','0'))?>"></div>
<div><label>Version Name</label><input name="version_name" value="<?=h(setting('update_version_name'))?>"></div></div>
<label>توضیحات آپدیت</label><textarea name="notes"><?=h(setting('update_notes'))?></textarea>
<label>APK جدید</label><input type="file" name="apk" accept=".apk,application/vnd.android.package-archive">
<button name="save_update">ثبت و انتشار آپدیت</button></form></div>
<?php endif;?>
</div></body></html>
