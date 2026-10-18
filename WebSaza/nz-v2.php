<?php
declare(strict_types=1);
ob_start();
try {
    require __DIR__ . '/nz-app.php';
    $html = ob_get_clean();
    if ($html !== false) {
        $tag = '<script src="/nz-media-client.js?v=20261018-1" defer></script>';
        $html = str_replace('</body>', $tag."\n</body>", $html);
        echo $html;
    }
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    if (function_exists('flash')) {
        flash($e->getMessage(), 'error');
        header('Location: /nz?page=new');
        exit;
    }
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;max-width:560px;margin:60px auto;padding:20px"><h2>خطا در بارگذاری</h2><p>'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p><a href="/nz?page=new">بازگشت به ثبت آگهی</a></div>';
}
