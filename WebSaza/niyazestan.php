<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/marketplace.php';
$html = ob_get_clean();

if ($html !== false) {
    $html = str_replace(
        ['بازار وب‌ساز', 'بازار وب ساز', '/marketplace.php', '/niazestan.webmanifest'],
        ['نیازستان', 'نیازستان', '/nz', '/nz-manifest.json'],
        $html
    );

    // Remove the large "use like an app" hint from the home page.
    $html = preg_replace(
        '/<div class="install-hint">.*?<\/div><nav class="category-strip">/su',
        '<nav class="category-strip">',
        $html,
        1
    ) ?? $html;

    $installButton = <<<'HTML'
<button type="button" id="installMini" class="install-mini" aria-label="نصب برنامه نیازستان روی گوشی">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>
<span>نصب برنامه</span>
</button>
HTML;

    // Put the install button directly inside the mobile header row so it cannot disappear with desktop-header rules.
    $html = preg_replace(
        '/(<header class="mobile-head"><div class="mobile-row">)/',
        '$1' . $installButton,
        $html,
        1
    ) ?? $html;

    $installCss = <<<'HTML'
<style id="niazestan-install-style">
.mobile-row{position:relative}.install-mini{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:25;border:2px solid #111;background:#ffd83d;box-shadow:0 5px 14px rgba(0,0,0,.18),0 0 0 1px rgba(255,216,61,.35);border-radius:999px;height:31px;padding:0 11px;color:#b4232c;font-family:Tahoma,"Vazirmatn",Arial,sans-serif;font-size:12.5px;font-weight:950;line-height:1;display:inline-flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap}.install-mini svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2}.install-mini:hover{filter:brightness(1.03)}.install-mini:active{transform:translate(-50%,-50%) scale(.96)}
.install-sheet{position:fixed;inset:0;z-index:200;background:rgba(15,18,22,.42);display:none;align-items:flex-end;justify-content:center;padding:16px}.install-sheet.show{display:flex}.install-sheet-card{width:min(100%,440px);background:#fff;border-radius:22px;padding:18px 18px calc(18px + env(safe-area-inset-bottom,0px));box-shadow:0 20px 60px rgba(0,0,0,.2);direction:rtl}.install-sheet-card h3{margin:0 0 8px;font-size:17px}.install-sheet-card p{margin:0;color:#626970;line-height:1.9;font-size:13px}.install-sheet-card .install-ok{width:100%;margin-top:14px;min-height:44px;border:0;border-radius:13px;background:#b4232c;color:#fff;font:800 13px Tahoma,Arial,sans-serif;cursor:pointer}
@media (min-width:761px){.install-mini{display:none!important}}
@media (display-mode:standalone){.install-mini{display:none!important}.install-sheet{display:none!important}}
</style>
HTML;

    $installUi = <<<'HTML'
<div id="installSheet" class="install-sheet" role="dialog" aria-modal="true" aria-label="راهنمای نصب نیازستان">
  <div class="install-sheet-card">
    <h3 id="installSheetTitle">نصب نیازستان</h3>
    <p id="installSheetText">از منوی مرورگر، گزینه نصب برنامه یا افزودن به صفحه اصلی را انتخاب کنید.</p>
    <button type="button" class="install-ok" id="installSheetClose">متوجه شدم</button>
  </div>
</div>
<script id="niazestan-install-script">
(()=>{
  const btn=document.getElementById('installMini');
  const sheet=document.getElementById('installSheet');
  const text=document.getElementById('installSheetText');
  const close=document.getElementById('installSheetClose');
  if(!btn||!sheet)return;

  const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  if(standalone){btn.style.display='none';return;}

  let deferredPrompt=null;
  const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);
  const isSafari=/safari/i.test(navigator.userAgent)&&!/crios|fxios|edgios/i.test(navigator.userAgent);

  window.addEventListener('beforeinstallprompt',e=>{
    e.preventDefault();
    deferredPrompt=e;
  });

  window.addEventListener('appinstalled',()=>{
    btn.style.display='none';
    sheet.classList.remove('show');
    deferredPrompt=null;
  });

  btn.addEventListener('click',async()=>{
    if(deferredPrompt){
      deferredPrompt.prompt();
      try{
        const choice=await deferredPrompt.userChoice;
        if(choice&&choice.outcome==='accepted')btn.style.display='none';
      }catch(_){ }
      deferredPrompt=null;
      return;
    }

    if(isIos&&isSafari){
      text.innerHTML='در Safari روی دکمه <b>اشتراک‌گذاری</b> بزنید و سپس <b>Add to Home Screen / افزودن به صفحه اصلی</b> را انتخاب کنید.';
    }else{
      text.innerHTML='از منوی مرورگر، گزینه <b>Install app / نصب برنامه</b> یا <b>افزودن به صفحه اصلی</b> را انتخاب کنید.';
    }
    sheet.classList.add('show');
  });

  close.addEventListener('click',()=>sheet.classList.remove('show'));
  sheet.addEventListener('click',e=>{if(e.target===sheet)sheet.classList.remove('show')});
})();
</script>
HTML;

    $html = str_replace('</head>', $installCss . "\n</head>", $html);
    $html = str_replace('</body>', $installUi . "\n</body>", $html);

    echo $html;
}
