<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-community.php';
require_once __DIR__ . '/nz-create-pending.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'create_post')) {
        nzcp_handle_create();
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
        $installTag = '<script src="/nz-install-direct.js?v=20261019-3"></script>';
        $mediaTag = '<script src="/nz-media-client.js?v=20261019-2" defer></script>';
        $html = str_replace('</head>', $installTag."\n</head>", $html);
        $html = str_replace('</body>', $mediaTag."\n</body>", $html);
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
