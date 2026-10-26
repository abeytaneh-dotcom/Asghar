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

$serverShellHead = <<<'HTML'
<!-- tm-direct-server-shell-v1 -->
<script id="tm-storefront-direct-class">document.documentElement.classList.add('tm-storefront-server');</script>
<link rel="stylesheet" href="assets/server_storefront_critical_v1.css?v=20261027-5">
<link rel="stylesheet" href="assets/server_mobile_nav_v1.css?v=20261027-5">
<script defer src="assets/server_mobile_nav_v1.js?v=20261027-5"></script>
HTML;
$html = str_replace('</head>', $serverShellHead.'</head>', $html);

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

$barcodeUi = <<<'HTML'
<style id="tehran-barcode-scanner-ui">
.barcode-scan-fab{position:fixed;right:14px;bottom:14px;z-index:90000;width:52px;height:52px;border:0;border-radius:16px;background:#11823b;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.24);font-size:25px;display:flex;align-items:center;justify-content:center;cursor:pointer}
.barcode-scan-fab:active{transform:scale(.96)}
.barcode-sheet[hidden]{display:none!important}.barcode-sheet{position:fixed;inset:0;z-index:100001;background:rgba(6,17,11,.78);display:flex;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px)}
.barcode-card{width:min(520px,100%);background:#fff;border-radius:24px 24px 0 0;padding:16px 16px 22px;box-shadow:0 -12px 50px rgba(0,0,0,.3)}
.barcode-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.barcode-head b{font-size:18px;color:#20362a}.barcode-close{width:38px;height:38px;border:0;border-radius:12px;background:#f0f4f1;font-size:25px;cursor:pointer}
.barcode-camera{position:relative;border-radius:18px;overflow:hidden;background:#0d1711;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center}.barcode-camera video{width:100%;height:100%;object-fit:cover}.barcode-guide{position:absolute;left:10%;right:10%;top:36%;height:28%;border:2px solid #fff;border-radius:14px;box-shadow:0 0 0 999px rgba(0,0,0,.18);pointer-events:none}.barcode-line{position:absolute;left:15%;right:15%;top:50%;height:2px;background:#45e579;box-shadow:0 0 10px #45e579}
.barcode-status{margin:10px 0 0;padding:10px 12px;border-radius:12px;background:#f2f7f3;color:#53655b;font-size:12px;line-height:1.8}.barcode-result{margin-top:10px;padding:14px;border-radius:15px;background:#eef9f0;display:none}.barcode-result.show{display:block}.barcode-result .name{font-weight:900;color:#163c25;font-size:16px;margin-bottom:6px}.barcode-result .meta{display:grid;grid-template-columns:1fr 1fr;gap:8px}.barcode-result .pill{background:#fff;border-radius:11px;padding:9px 10px;font-size:12px}.barcode-result .pill b{display:block;color:#11823b;font-size:15px;margin-top:2px}
.barcode-manual{display:flex;gap:7px;margin-top:10px}.barcode-manual input{flex:1;min-width:0;border:1px solid #dbe5de;border-radius:11px;padding:10px 11px;font:inherit;direction:ltr;text-align:center}.barcode-manual button{border:0;border-radius:11px;background:#173b28;color:#fff;padding:10px 14px;font:inherit;font-weight:800;cursor:pointer}.barcode-retry{margin-top:9px;width:100%;border:1px solid #cfe0d3;background:#fff;color:#11823b;border-radius:11px;padding:9px;font:inherit;font-weight:800;cursor:pointer}
@media(min-width:700px){.barcode-sheet{align-items:center;padding:20px}.barcode-card{border-radius:24px}}
</style>
<button id="barcode-scan-fab" class="barcode-scan-fab" type="button" aria-label="اسکن بارکد" title="اسکن بارکد">▥</button>
<div id="barcode-sheet" class="barcode-sheet" hidden>
  <div class="barcode-card" role="dialog" aria-modal="true" aria-labelledby="barcode-title">
    <div class="barcode-head"><b id="barcode-title">اسکن بارکد محصول</b><button id="barcode-close" class="barcode-close" type="button" aria-label="بستن">×</button></div>
    <div class="barcode-camera"><video id="barcode-video" playsinline muted></video><div class="barcode-guide"></div><div class="barcode-line"></div></div>
    <div id="barcode-status" class="barcode-status">بارکد را داخل کادر مقابل دوربین بگیرید.</div>
    <div id="barcode-result" class="barcode-result"><div id="barcode-name" class="name"></div><div class="meta"><div class="pill">قیمت<b id="barcode-price">—</b></div><div class="pill">موجودی<b id="barcode-stock">—</b></div></div></div>
    <div class="barcode-manual"><input id="barcode-manual-input" inputmode="numeric" autocomplete="off" placeholder="ورود دستی بارکد"><button id="barcode-manual-go" type="button">بررسی</button></div>
    <button id="barcode-retry" class="barcode-retry" type="button">اسکن مجدد</button>
  </div>
</div>
<script id="tehran-barcode-scanner">
(function(){
  var fab=document.getElementById('barcode-scan-fab'),sheet=document.getElementById('barcode-sheet'),closeBtn=document.getElementById('barcode-close'),video=document.getElementById('barcode-video'),statusEl=document.getElementById('barcode-status'),result=document.getElementById('barcode-result'),nameEl=document.getElementById('barcode-name'),priceEl=document.getElementById('barcode-price'),stockEl=document.getElementById('barcode-stock'),manual=document.getElementById('barcode-manual-input'),manualGo=document.getElementById('barcode-manual-go'),retry=document.getElementById('barcode-retry');
  if(!fab||!sheet||!video)return;
  var stream=null,detector=null,scanning=false,raf=0,lastCode='',lastAt=0;
  function faNum(n){try{return new Intl.NumberFormat('fa-IR').format(n);}catch(e){return String(n)}}
  function speak(text){try{if(!('speechSynthesis' in window))return;window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang='fa-IR';u.rate=.95;window.speechSynthesis.speak(u);}catch(e){}}
  function setStatus(t){statusEl.textContent=t;}
  function stopCamera(){scanning=false;if(raf)cancelAnimationFrame(raf);raf=0;if(stream){stream.getTracks().forEach(function(t){t.stop();});stream=null;}video.srcObject=null;}
  async function lookup(code){
    code=String(code||'').replace(/\D/g,'');if(!code)return;
    if(code===lastCode && Date.now()-lastAt<2500)return;lastCode=code;lastAt=Date.now();
    setStatus('در حال بررسی بارکد '+code+' ...');result.classList.remove('show');
    try{
      var r=await fetch('barcode_lookup.php?barcode='+encodeURIComponent(code),{cache:'no-store'});var d=await r.json();
      if(d&&d.ok&&d.found&&d.product){
        var p=d.product;nameEl.textContent=p.name;priceEl.textContent=faNum(p.price)+' تومان';stockEl.textContent=faNum(p.stock)+' عدد';result.classList.add('show');
        setStatus('بارکد پیدا شد: '+p.barcode);speak(p.name+'، قیمت '+faNum(p.price)+' تومان، موجودی '+faNum(p.stock)+' عدد');stopCamera();
      }else{setStatus('این بارکد در محصولات ثبت نشده است.');speak('این بارکد در محصولات ثبت نشده است');}
    }catch(e){setStatus('خطا در دریافت اطلاعات محصول. دوباره تلاش کنید.');}
  }
  async function scanLoop(){
    if(!scanning||!detector)return;
    try{var found=await detector.detect(video);if(found&&found.length){var raw=found[0].rawValue||'';if(raw){await lookup(raw);}}}catch(e){}
    if(scanning)raf=requestAnimationFrame(scanLoop);
  }
  async function startCamera(){
    stopCamera();result.classList.remove('show');setStatus('بارکد را داخل کادر مقابل دوربین بگیرید.');
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){setStatus('دوربین در این مرورگر در دسترس نیست؛ بارکد را دستی وارد کنید.');return;}
    try{
      stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});video.srcObject=stream;await video.play();
      if('BarcodeDetector' in window){
        var formats=['ean_13','ean_8','upc_a','upc_e','code_128','code_39','itf'];
        try{var supported=await BarcodeDetector.getSupportedFormats();formats=formats.filter(function(x){return supported.indexOf(x)!==-1;});}catch(e){}
        detector=new BarcodeDetector({formats:formats.length?formats:undefined});scanning=true;scanLoop();
      }else{setStatus('خواندن خودکار بارکد روی این مرورگر پشتیبانی نمی‌شود؛ می‌توانید بارکد را دستی وارد کنید.');}
    }catch(e){setStatus('اجازه دوربین داده نشد یا دوربین باز نشد؛ بارکد را دستی وارد کنید.');}
  }
  function openSheet(){sheet.hidden=false;document.documentElement.style.overflow='hidden';startCamera();}
  function closeSheet(){stopCamera();sheet.hidden=true;document.documentElement.style.overflow='';}
  fab.addEventListener('click',openSheet);closeBtn.addEventListener('click',closeSheet);retry.addEventListener('click',startCamera);
  sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  manualGo.addEventListener('click',function(){lookup(manual.value);});manual.addEventListener('keydown',function(e){if(e.key==='Enter')lookup(manual.value);});
  document.addEventListener('visibilitychange',function(){if(document.hidden)stopCamera();});
})();
</script>
HTML;
$html = str_replace('</body>', $barcodeUi.'</body>', $html);

$barcodeV2 = '<script src="barcode_camera_v8.js?v=20261026-1"></script><script src="barcode_scanner_v2.js?v=20261026-8"></script>';
$html = str_replace('</body>', $barcodeV2.'</body>', $html);

$aiAssistant = '<script src="assets/ai_assistant_v2.js?v=20261026-2"></script>';
$html = str_replace('</body>', $aiAssistant.'</body>', $html);

$aiAutoCommit = '<script src="assets/ai_assistant_autocommit.js?v=20261026-3"></script>';
$html = str_replace('</body>', $aiAutoCommit.'</body>', $html);

$aiCartManager = '<script src="assets/ai_cart_manager.js?v=20261026-1"></script>';
$html = str_replace('</body>', $aiCartManager.'</body>', $html);

$customerMemory = '<script src="assets/customer_memory.js?v=20261026-1"></script>';
$html = str_replace('</body>', $customerMemory.'</body>', $html);

$aiLocation = '<script src="assets/ai_location_checkout.js?v=20261026-2"></script>';
$html = str_replace('</body>', $aiLocation.'</body>', $html);

$aiEntry = '<script src="assets/ai_entry_flow.js?v=20261027-104"></script>';
$html = str_replace('</body>', $aiEntry.'</body>', $html);

$aiLearning = '<script src="assets/ai_learning.js?v=20261026-1"></script>';
$html = str_replace('</body>', $aiLearning.'</body>', $html);

echo $html;