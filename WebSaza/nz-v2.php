<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-session-bootstrap.php';
require_once __DIR__ . '/nz-fileinfo-compat.php';
require_once __DIR__ . '/nz-community.php';
require_once __DIR__ . '/nz-support-default.php';
require_once __DIR__ . '/nz-hardening.php';
require_once __DIR__ . '/nz-otp.php';
require_once __DIR__ . '/nz-admin-stepup.php';
require_once __DIR__ . '/nz-detail-enhance.php';
require_once __DIR__ . '/nz-chat-enhance.php';
require_once __DIR__ . '/nz-search-enhance.php';
require_once __DIR__ . '/nz-market-upgrades.php';
require_once __DIR__ . '/nz-lifecycle.php';
require_once __DIR__ . '/nz-post-management.php';
require_once __DIR__ . '/nz-admin-security.php';
require_once __DIR__ . '/nz-notifications.php';
require_once __DIR__ . '/nz-legal.php';
require_once __DIR__ . '/nz-create-pending.php';
require_once __DIR__ . '/nz-submit-server.php';

nzh_headers();
try {
    nzh_guard_request();
    nzsupport_touch_current_user();
    nzsupport_ensure_for_current_user();

    $action = (string)($_POST['action'] ?? '');
    $isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');

    if ($isPost && in_array($action, ['admin_stepup_request','admin_stepup_verify','admin_stepup_lock'], true)) {
        nzauth_handle_post($action);
    }
    if ($isPost) {
        nzauth_require_for_action($action);
    }
    if ($isPost && in_array($action, ['request_ad_service','approve_service_payment','reject_service_payment'], true)) {
        nzl_prepare_request();
    }
    if ($isPost && in_array($action, ['request_otp','verify_otp'], true)) {
        if (!nzotp_ready()) throw new RuntimeException('سرویس پیامک ورود هنوز روی سرور پیکربندی نشده است.');
        if ($action === 'request_otp') nzotp_request();
        if ($action === 'verify_otp') nzotp_verify();
    }
    if ($isPost && in_array($action, ['start_chat','send_message'], true)) {
        nzchat_handle_post($action);
    }
    if ($isPost && in_array($action, ['block_chat_user','unblock_chat_user','report_chat'], true)) {
        nzcc_handle_post($action);
    }
    if ($isPost && in_array($action, ['save_search','delete_saved_search'], true)) {
        nzs_handle_post($action);
    }
    if ($isPost && in_array($action, ['notifications_read_all','notification_read'], true)) {
        nzn_handle_post($action);
    }
    if ($isPost && in_array($action, ['mark_sold','archive_post','restore_post','update_post'], true)) {
        nzpm_handle_post($action);
    }
    if ($isPost && in_array($action, ['admin_block_user','admin_unblock_user'], true)) {
        nzasec_handle_post($action);
    }
    if ($isPost && $action === 'create_post') {
        nzss_handle_create();
    }

    nzu_prepare_approval_watch();
    nzc_prepare_request();
    nzchat_mark_read();
} catch (Throwable $e) {
    nzc_flash($e->getMessage(), 'error');
    $back = (string)($_SERVER['HTTP_REFERER'] ?? '/nz');
    if ($back === '' || !str_starts_with($back, 'https://websaza.ir/')) $back = '/nz';
    header('Location: '.$back, true, 302);
    exit;
}

ob_start();
try {
    require __DIR__ . '/nz-app.php';
    $html = ob_get_clean();
    if ($html !== false) {
        $html = nzc_transform($html);
        $html = nzm_enhance_admin_html($html);
        $html = str_replace(
            '<link rel="manifest" href="/nz-manifest.json">',
            '<link rel="manifest" href="/nz-manifest.json?v=20261019-5" crossorigin="use-credentials">',
            $html
        );
        $html = str_replace(
            '<link rel="apple-touch-icon" href="/niazestan-icon.svg">',
            '<link rel="apple-touch-icon" sizes="180x180" href="/niazestan-icon-180.php?v=20261019-2">',
            $html
        );
        $html = str_replace(
            '<link rel="icon" href="/niazestan-icon.svg" type="image/svg+xml">',
            '<link rel="icon" type="image/png" sizes="192x192" href="/niazestan-icon-192.php?v=20261019-2"><link rel="icon" href="/niazestan-icon.svg" type="image/svg+xml">',
            $html
        );
        if (!str_contains($html, 'name="color-scheme"')) {
            $html = str_replace('<meta name="theme-color" content="#ffffff">', '<meta name="theme-color" content="#ffffff"><meta name="color-scheme" content="only light">', $html);
        }
        $html = str_replace(
            "navigator.serviceWorker.register('/niazestan-sw.js')",
            "navigator.serviceWorker.register('/niazestan-sw.js?v=20261019-7',{scope:'/nz',updateViaCache:'none'})",
            $html
        );

        if (!empty($_GET['id'])) {
            $postId = (string)$_GET['id'];
            $detailPost = null;
            foreach (nzc_db_read()['posts'] as $candidate) {
                if (($candidate['id'] ?? '') === $postId) { $detailPost = $candidate; break; }
            }
            if (is_array($detailPost) && ($detailPost['status'] ?? '') === 'published') {
                $extraSpecs = '';
                if (!empty($detailPost['province'])) {
                    $extraSpecs .= '<div class="spec"><span>استان</span><b>'.nzc_e($detailPost['province']).'</b></div>';
                }
                if (!empty($detailPost['district'])) {
                    $extraSpecs .= '<div class="spec"><span>محله</span><b>'.nzc_e($detailPost['district']).'</b></div>';
                }
                foreach (($detailPost['attributes'] ?? []) as $label => $value) {
                    if ((string)$value === '') continue;
                    $extraSpecs .= '<div class="spec"><span>'.nzc_e($label).'</span><b>'.nzc_e($value).'</b></div>';
                }
                if ($extraSpecs !== '') {
                    $html = str_replace('<div class="spec"><span>بازدید</span>', $extraSpecs.'<div class="spec"><span>بازدید</span>', $html);
                }
                if (isset($detailPost['latitude'], $detailPost['longitude']) && is_numeric($detailPost['latitude']) && is_numeric($detailPost['longitude'])) {
                    $lat = (float)$detailPost['latitude'];
                    $lng = (float)$detailPost['longitude'];
                    $mode = (($detailPost['location_mode'] ?? '') === 'approx') ? 'موقعیت حدودی' : 'موقعیت دقیق';
                    $address = trim(implode('، ', array_filter([(string)($detailPost['province'] ?? ''),(string)($detailPost['city'] ?? ''),(string)($detailPost['district'] ?? ''),(string)($detailPost['street'] ?? '')])));
                    $mapUrl = 'https://www.openstreetmap.org/?mlat='.rawurlencode((string)$lat).'&mlon='.rawurlencode((string)$lng).'#map=16/'.rawurlencode((string)$lat).'/'.rawurlencode((string)$lng);
                    $locationCard = '<div class="surface" style="margin-bottom:14px"><h3 style="margin-top:0">موقعیت آگهی</h3><div class="subtle" style="line-height:1.9;margin-bottom:10px">'.nzc_e($address).' · '.nzc_e($mode).'</div><a class="btn ghost" href="'.nzc_e($mapUrl).'" target="_blank" rel="noopener">مشاهده روی نقشه</a></div>';
                    $html = str_replace('<div class="surface"><h3 style="margin-top:0">توضیحات</h3>', $locationCard.'<div class="surface"><h3 style="margin-top:0">توضیحات</h3>', $html);
                }
            }
        }

        $html = nzd_enhance($html);
        $html = nzs_enhance($html);
        $html = nzu_enhance($html);
        $html = nzl_enhance($html);
        $html = nzpm_enhance($html);
        $html = nzasec_enhance($html);
        $html = nzn_enhance($html);
        $html = nzlegal_enhance($html);
        $html = nzauth_enhance($html);
        $html = nzh_seo($html);
        $html = nzchat_enhance_html($html);
        $html = nzsupport_enhance($html);

        $installTag = '<script src="/nz-install-direct.js?v=20261019-6"></script>';
        $mediaTag = '<script src="/nz-media-client.js?v=20261019-2" defer></script>';
        $wizardTag = '<script src="/nz-submit-wizard.js?v=20261019-3" defer></script>';
        $taxonomyTag = '<script src="/nz-taxonomy-client.js?v=20261019-1" defer></script>';
        $specTag = '<script src="/nz-spec-lite.js?v=20261019-1" defer></script>';
        $brandModelTag = '<script src="/nz-brand-model.js?v=20261019-2" defer></script>';
        $moderationTag = '<script src="/nz-image-moderation.js?v=20261019-1" defer></script>';
        $draftTag = '<script src="/nz-draft-preview.js?v=20261019-1" defer></script>';
        $html = str_replace('</head>', $installTag."\n</head>", $html);
        $html = str_replace('</body>', $mediaTag."\n".$wizardTag."\n".$taxonomyTag."\n".$specTag."\n".$brandModelTag."\n".$moderationTag."\n".$draftTag."\n</body>", $html);
        echo $html;
    }
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    if (function_exists('flash')) {
        flash($e->getMessage(), 'error');
        header('Location: /nz?page=new');
        exit;
    }
    nzc_flash($e->getMessage(), 'error');
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;max-width:560px;margin:60px auto;padding:20px"><h2>خطا در بارگذاری</h2><p>'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p><a href="/nz?page=new">بازگشت به ثبت آگهی</a></div>';
}
