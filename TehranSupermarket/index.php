<?php
declare(strict_types=1);

require_once __DIR__.'/pyramid_seed_1400.php';
ts_seed_pyramid_1400();

ob_start();
require __DIR__.'/shop.php';
$html = (string)ob_get_clean();

require_once __DIR__.'/product_image_seed.php';
if(isset($pdo) && $pdo instanceof PDO) ts_apply_product_image_manifest($pdo);

$oldBrand = '<span class="logo">🛒</span><span><b>سوپرمارکت طهران</b><small>خرید سریع محلی</small></span>';
$newBrand = '<span class="tehran-market-logo"><img src="pwa-icon.php?size=512&amp;v=20261026-gold" alt="طهران مارکت"></span>';
$html = str_replace($oldBrand, $newBrand, $html);
$html = str_replace('<title>سوپرمارکت طهران</title>', '<title>طهران مارکت</title>', $html);
$html = str_replace('سوپرمارکت طهران · بهارستان اصفهان', 'طهران مارکت · بهارستان اصفهان', $html);

$brandCss = '<style id="tehran-market-brand">.tehran-market-logo{width:150px;height:94px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:16px;background:#fff;flex:0 0 auto}.tehran-market-logo img{width:100%;height:100%;object-fit:contain;display:block}@media(max-width:800px){.tehran-market-logo{width:112px;height:70px;border-radius:12px}.top{gap:8px}.top>div{font-size:11px}}</style>';
$html = str_replace('</head>', $brandCss.'</head>', $html);

$tobaccoUi = <<<'HTML'
<style id="tehran-tobacco-ui">
.tobacco-age-overlay{position:fixed;inset:0;z-index:100000;background:rgba(8,20,13,.72);display:flex;align-items:center;justify-content:center;padding:22px;backdrop-filter:blur(4px)}
.tobacco-age-card{width:min(430px,100%);background:#fff;border-radius:22px;padding:24px 20px;text-align:center;box-shadow:0 20px 70px rgba(0,0,0,.32)}
.tobacco-age-icon{width:72px;height:72px;margin:0 auto 12px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff1ee;font-size:38px}
.tobacco-age-card h2{margin:0 0 9px;font-size:22px;color:#263a2e}.tobacco-age-card p{margin:0 0 18px;line-height:1.9;color:#5f6d64;font-size:14px}
.tobacco-age-card button{width:100%;border:0;border-radius:13px;background:#11823b;color:#fff;padding:12px 16px;font:inherit;font-weight:800;cursor:pointer}
</style>
<script id="tehran-tobacco-category">
document.addEventListener('DOMContentLoaded',function(){
  var tobacco='دخانیات';
  var cats=document.querySelector('.cats');
  if(cats){
    var exists=false;
    cats.querySelectorAll('a').forEach(function(a){if((a.textContent||'').indexOf(tobacco)!==-1)exists=true;});
    if(!exists){
      var a=document.createElement('a');
      a.href='index.php?cat='+encodeURIComponent(tobacco)+'#products';
      a.textContent='🚭 دخانیات';
      if(new URLSearchParams(location.search).get('cat')===tobacco)a.classList.add('on');
      cats.appendChild(a);
    }
  }
  if(new URLSearchParams(location.search).get('cat')===tobacco){
    var ov=document.createElement('div');ov.className='tobacco-age-overlay';
    ov.innerHTML='<div class="tobacco-age-card" role="dialog" aria-modal="true" aria-label="هشدار محدودیت سنی"><div class="tobacco-age-icon">🔞</div><h2>محدودیت سنی دخانیات</h2><p>فروش دخانیات به افراد زیر ۱۸ سال نداریم.</p><button type="button">متوجه شدم</button></div>';
    ov.querySelector('button').addEventListener('click',function(){ov.remove();});
    document.body.appendChild(ov);
  }
});
</script>
HTML;
$html = str_replace('</body>', $tobaccoUi.'</body>', $html);

echo $html;