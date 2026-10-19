<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; base-uri 'none'");
echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>انتقال امن</title><style>body{font-family:Tahoma,Arial,sans-serif;padding:32px;direction:rtl}</style><p>در حال انتقال به مسیر امن نیازستان…</p><script>location.replace("/nz/admin-claim.php"+location.hash);</script></html>';
