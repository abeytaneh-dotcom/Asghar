<?php
declare(strict_types=1);

require_once __DIR__ . '/error_bootstrap.php';

if (!defined('TS_DEMO_WATERMARK_BUFFER')) {
    define('TS_DEMO_WATERMARK_BUFFER', true);

    ob_start(static function (string $output): string {
        if ($output === '' || (stripos($output, '<html') === false && stripos($output, '<!doctype html') === false)) {
            return $output;
        }

        $watermark = <<<'HTML'
<style id="ts-demo-watermark-style">
html,body{min-height:100%}
body::after{
  content:"تستی\A جهت سفارش تماس بگیرید\A websaza.ir";
  white-space:pre;
  position:fixed;
  left:50%;
  top:50%;
  transform:translate(-50%,-50%) rotate(-18deg);
  z-index:2147483647;
  pointer-events:none;
  user-select:none;
  -webkit-user-select:none;
  text-align:center;
  direction:rtl;
  color:#176b39;
  opacity:.10;
  font-family:Vazirmatn,Tahoma,Arial,sans-serif;
  font-weight:800;
  font-size:clamp(24px,5vw,56px);
  line-height:1.9;
  letter-spacing:.2px;
  width:min(92vw,900px);
  text-shadow:0 1px 0 rgba(255,255,255,.45);
}
@media(max-width:600px){body::after{font-size:clamp(21px,7vw,34px);opacity:.11;width:94vw}}
@media print{body::after{opacity:.13}}
</style>
HTML;

        $pos = stripos($output, '</head>');
        if ($pos !== false) {
            return substr($output, 0, $pos) . $watermark . substr($output, $pos);
        }

        $bodyPos = stripos($output, '<body');
        if ($bodyPos !== false) {
            return $watermark . $output;
        }

        return $output;
    });
}
