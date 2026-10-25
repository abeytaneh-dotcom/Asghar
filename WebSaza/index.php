<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$path=__DIR__.'/index.html';
$html=@file_get_contents($path);
if($html===false){http_response_code(500);echo 'WebSaza';exit;}

$employment='<a href="/namayandegi/" class="ws-employment-link">استخدام در وب سازا</a>';
if(!str_contains($html,$employment)){
    $html=str_replace('<a href="#faq">سؤالات متداول</a>','<a href="#faq">سؤالات متداول</a>'."\n      ".$employment,$html);
}

$html=preg_replace('#/app\.js\?v=[^"\']+#','/app.js?v=20261025-2',$html,1)??$html;
$order='<script src="/order-widget-v1.js?v=20261025-2" defer></script>';
if(!str_contains($html,'/order-widget-v1.js')){
    $html=str_replace('</body>',$order."\n</body>",$html);
}

echo $html;
