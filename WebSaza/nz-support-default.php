<?php
declare(strict_types=1);

function nzsupport_client_ip(): string {
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function nzsupport_user_agent(): string {
    return mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 400);
}

function nzsupport_device_label(string $ua): string {
    $device = 'دستگاه نامشخص';
    if (stripos($ua, 'iPhone') !== false) $device = 'iPhone';
    elseif (stripos($ua, 'iPad') !== false) $device = 'iPad';
    elseif (stripos($ua, 'Android') !== false) $device = 'Android';
    elseif (stripos($ua, 'Windows') !== false) $device = 'Windows';
    elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) $device = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $device = 'Linux';

    $browser = '';
    if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';

    return $browser !== '' ? $device . ' · ' . $browser : $device;
}

function nzsupport_touch_current_user(): void {
    nzc_session_open();
    $uid = trim((string)($_SESSION['uid'] ?? ''));
    if ($uid === '') return;

    $db = nzc_db_read();
    $user = null;
    foreach ($db['users'] as $u) if (($u['id'] ?? '') === $uid) { $user = $u; break; }
    if (!$user) return;

    $ip = nzsupport_client_ip();
    $ua = nzsupport_user_agent();
    $device = nzsupport_device_label($ua);
    $lastSeen = strtotime((string)($user['last_seen_at'] ?? '')) ?: 0;
    $changed = ($ip !== '' && ($user['last_ip'] ?? '') !== $ip)
        || ($ua !== '' && ($user['last_user_agent'] ?? '') !== $ua)
        || (time() - $lastSeen >= 600);
    if (!$changed) return;

    nzc_db_write(function (&$db) use ($uid, $ip, $ua, $device): void {
        foreach ($db['users'] as &$u) {
            if (($u['id'] ?? '') !== $uid) continue;
            if ($ip !== '') $u['last_ip'] = $ip;
            if ($ua !== '') $u['last_user_agent'] = $ua;
            $u['last_device'] = $device;
            $u['last_seen_at'] = nzc_now();
            break;
        }
        unset($u);
    });
}

function nzsupport_find_cid(array $db, string $uid): string {
    foreach ($db['conversations'] as $c) {
        if ((($c['type'] ?? '') === 'support' || ($c['post_id'] ?? '') === NZC_SUPPORT_POST)
            && in_array($uid, (array)($c['members'] ?? []), true)) {
            return (string)($c['id'] ?? '');
        }
    }
    return '';
}

function nzsupport_ensure_for_current_user(): string {
    nzc_session_open();
    $uid = trim((string)($_SESSION['uid'] ?? ''));
    if ($uid === '') return '';

    $db = nzc_db_read();
    $existing = nzsupport_find_cid($db, $uid);
    if ($existing !== '') return $existing;

    return (string)nzc_db_write(function (&$db) use ($uid): string {
        $existing = nzsupport_find_cid($db, $uid);
        if ($existing !== '') return $existing;
        return nzc_support_conversation($db, $uid);
    });
}

function nzsupport_admin_user_card(array $db, array $admin, string $cid): string {
    if (!nzc_is_admin($admin)) return '';
    $conv = null;
    foreach ($db['conversations'] as $c) if (($c['id'] ?? '') === $cid) { $conv = $c; break; }
    if (!$conv) return '';

    $ownerId = '';
    $isSupport = (($conv['type'] ?? '') === 'support' || ($conv['post_id'] ?? '') === NZC_SUPPORT_POST);
    foreach ((array)($conv['members'] ?? []) as $member) {
        $member = (string)$member;
        if ($isSupport || $member !== (string)($admin['id'] ?? '')) { $ownerId = $member; break; }
    }
    if ($ownerId === '') return '';

    $owner = null;
    foreach ($db['users'] as $u) if (($u['id'] ?? '') === $ownerId) { $owner = $u; break; }
    if (!$owner) return '';

    $name = trim((string)($owner['name'] ?? '')) ?: 'کاربر نیازستان';
    $phone = trim((string)($owner['phone'] ?? '')) ?: 'ثبت نشده';
    $ip = trim((string)($owner['last_ip'] ?? '')) ?: 'ثبت نشده';
    $device = trim((string)($owner['last_device'] ?? '')) ?: 'ثبت نشده';
    $seen = trim((string)($owner['last_seen_at'] ?? ''));
    $seenFa = $seen !== '' ? date('Y/m/d H:i', strtotime($seen)) : 'ثبت نشده';
    $ua = trim((string)($owner['last_user_agent'] ?? ''));

    return '<section class="nzs-admin-user-card"><div class="nzs-admin-user-title"><b>مشخصات کاربر</b><span>فقط برای مدیر</span></div>'
        .'<div class="nzs-admin-user-grid">'
        .'<div><small>نام</small><b>'.nzc_e($name).'</b></div>'
        .'<div><small>شماره تماس</small><b dir="ltr">'.nzc_e($phone).'</b></div>'
        .'<div><small>آخرین IP</small><b dir="ltr">'.nzc_e($ip).'</b></div>'
        .'<div><small>دستگاه / مرورگر</small><b>'.nzc_e($device).'</b></div>'
        .'<div><small>آخرین فعالیت</small><b>'.nzc_e($seenFa).'</b></div>'
        .'</div>'
        .($ua !== '' ? '<details><summary>جزئیات User-Agent</summary><code dir="ltr">'.nzc_e($ua).'</code></details>' : '')
        .'</section>';
}

function nzsupport_enhance(string $html): string {
    nzc_session_open();
    $db = nzc_db_read();
    $user = nzc_current_user($db);
    if (!$user) return $html;

    $page = (string)($_GET['page'] ?? 'home');
    $supportCid = nzsupport_ensure_for_current_user();

    if ($page === 'chats' && $supportCid !== '') {
        $html = preg_replace('/<a class="menu-item nzc-support-link".*?<\/a>/su', '', $html, 1) ?? $html;
        $quoted = preg_quote($supportCid, '/');
        $html = preg_replace('/<a class="menu-item" style="margin-bottom:8px" href="\/nz\?page=chat&cid='.$quoted.'">.*?<\/a>/su', '', $html, 1) ?? $html;
        $html = str_replace('<div class="empty">هنوز گفتگویی شروع نشده است.</div>', '', $html);
        $supportLink = '<a class="menu-item nzc-support-link" style="margin-bottom:12px" href="/nz?page=chat&cid='.rawurlencode($supportCid).'">'
            .'<span><b>پشتیبانی نیازستان</b><small class="subtle" style="display:block;margin-top:4px">پاسخگویی پشتیبانی، وضعیت آگهی‌ها و پیگیری گزارش‌ها</small></span><span>‹</span></a>';
        $html = str_replace('<main class="page page-narrow">', '<main class="page page-narrow">'.$supportLink, $html);
    }

    if ($page === 'chat' && !empty($_GET['cid']) && nzc_is_admin($user)) {
        $cid = (string)$_GET['cid'];
        $card = nzsupport_admin_user_card($db, $user, $cid);
        if ($card !== '' && str_contains($html, '<div class="messages" id="messages">')) {
            $html = str_replace('<div class="messages" id="messages">', $card.'<div class="messages" id="messages">', $html);
        }
    }

    $css = '<style id="nz-support-default-style">'
        .'.nzs-admin-user-card{margin:0 0 12px;border:1px solid #dce8f7;background:#f8fbff;border-radius:16px;padding:13px}'
        .'.nzs-admin-user-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.nzs-admin-user-title span{font-size:10px;color:#687386}'
        .'.nzs-admin-user-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.nzs-admin-user-grid>div{background:#fff;border:1px solid #e7edf5;border-radius:11px;padding:9px}.nzs-admin-user-grid small,.nzs-admin-user-grid b{display:block}.nzs-admin-user-grid small{font-size:10px;color:#7b8491;margin-bottom:4px}.nzs-admin-user-grid b{font-size:12px;overflow-wrap:anywhere}.nzs-admin-user-card details{margin-top:9px}.nzs-admin-user-card summary{cursor:pointer;font-size:11px;font-weight:800}.nzs-admin-user-card code{display:block;margin-top:7px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:10px;line-height:1.7}'
        .'@media(max-width:620px){.nzs-admin-user-grid{grid-template-columns:1fr}}'
        .'</style>';
    if (!str_contains($html, 'nz-support-default-style')) $html = str_replace('</head>', $css."\n</head>", $html);
    return $html;
}
