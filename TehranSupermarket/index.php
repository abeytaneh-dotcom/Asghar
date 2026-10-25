<?php
declare(strict_types=1);

require_once __DIR__.'/pyramid_seed_1400.php';
ts_seed_pyramid_1400();

ob_start();
require __DIR__.'/shop.php';
$html = (string)ob_get_clean();

$oldBrand = '<span class="logo">🛒</span><span><b>سوپرمارکت طهران</b><small>خرید سریع محلی</small></span>';
$newBrand = '<span class="tehran-market-logo"><img src="pwa-icon.php?size=512&amp;v=20261026-gold" alt="طهران مارکت"></span>';
$html = str_replace($oldBrand, $newBrand, $html);
$html = str_replace('<title>سوپرمارکت طهران</title>', '<title>طهران مارکت</title>', $html);
$html = str_replace('سوپرمارکت طهران · بهارستان اصفهان', 'طهران مارکت · بهارستان اصفهان', $html);

$brandCss = '<style id="tehran-market-brand">.tehran-market-logo{width:150px;height:94px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:16px;background:#fff;flex:0 0 auto}.tehran-market-logo img{width:100%;height:100%;object-fit:contain;display:block}@media(max-width:800px){.tehran-market-logo{width:112px;height:70px;border-radius:12px}.top{gap:8px}.top>div{font-size:11px}}</style>';
$html = str_replace('</head>', $brandCss.'</head>', $html);

echo $html;
