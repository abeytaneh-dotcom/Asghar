<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود مدیریت</title><body style="font-family:Tahoma,Arial,sans-serif;padding:32px"><p>ورود مدیریت فقط از صفحه مدیریت نیازستان و با کد پیامکی انجام می‌شود.</p><a href="/nz?page=admin">ورود مدیریت</a></body></html>';
