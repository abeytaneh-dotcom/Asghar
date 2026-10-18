<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-fileinfo-compat.php';
require_once __DIR__ . '/nz-community.php';
require_once __DIR__ . '/nz-create-pending.php';
require_once __DIR__ . '/nz-submit-server.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'create_post')) {
        nzss_handle_create();
    }
    nzc_prepare_request();
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
        $html = str_replace(
            '<link rel="manifest" href="/nz-manifest.json">',
            '<link rel="manifest" href="/nz-manifest.json?v=20261019-3" crossorigin="use-credentials">',
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
            "navigator.serviceWorker.register('/niazestan-sw.js?v=20261019-5',{scope:'/',updateViaCache:'none'})",
            $html
        );

        if (!empty($_GET['id'])) {
            $postId = (string)$_GET['id'];
            $detailPost = null;
            foreach (nzc_db_read()['posts'] as $candidate) {
                if (($candidate['id'] ?? '') === $postId) { $detailPost = $candidate; break; }
            }
            if (is_array($detailPost)) {
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

        $installTag = '<script src="/nz-install-direct.js?v=20261019-6"></script>';
        $mediaTag = '<script src="/nz-media-client.js?v=20261019-2" defer></script>';
        $wizardTag = '<script src="/nz-submit-wizard.js?v=20261019-3" defer></script>';
        $specTag = '<script src="/nz-spec-enforcer.js?v=20261019-1" defer></script>';
        $html = str_replace('</head>', $installTag."\n</head>", $html);
        $html = str_replace('</body>', $mediaTag."\n".$wizardTag."\n".$specTag."\n</body>", $html);
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
