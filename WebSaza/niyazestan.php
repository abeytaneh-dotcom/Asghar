<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/marketplace.php';
$html = ob_get_clean();

if ($html !== false) {
    $html = str_replace(
        ['بازار وب‌ساز', 'بازار وب ساز', '/marketplace.php'],
        ['نیازستان', 'نیازستان', '/nz'],
        $html
    );

    // Remove the large "use like an app" hint from the home page.
    $html = preg_replace(
        '/<div class="install-hint">.*?<\/div><nav class="category-strip">/su',
        '<nav class="category-strip">',
        $html,
        1
    ) ?? $html;

    $installCss = <<<'HTML'
<style id="niazestan-install-style">
.install-mini{position:fixed;left:12px;top:calc(env(safe-area-inset-top,0px) + 12px);z-index:120;border:1px solid rgba(0,0,0,.08);background:rgba(255,255,255,.94);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:0 5px 20px rgba(0,0,0,.10);border-radius:999px;height:34px;padding:0 11px;color:#333;font:700 12px Tahoma,Arial,sans-serif;display:none;align-items:center;gap:5px;cursor:pointer}.install-mini svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}.install-mini:active{transform:scale(.96)}
.install-sheet{position:fixed;inset:0;z-index:200;background:rgba(15,18,22,.42);display:none;align-items:flex-end;justify-content:center;padding:16px}.install-sheet.show{display:flex}.install-sheet-card{width:min(100%,440px);background:#fff;border-radius:22px;padding:18px 18px calc(18px + env(safe-area-inset-bottom,0px));box-shadow:0 20px 60px rgba(0,0,0,.2);direction:rtl}.install-sheet-card h3{margin:0 0 8px;font-size:17px}.install-sheet-card p{margin:0;color:#626970;line-height:1.9;font-size:13px}.install-sheet-card .install-ok{width:100%;margin-top:14px;min-height:44px;border:0;border-radius:13px;background:#b4232c;color:#fff;font:800 13px Tahoma,Arial,sans-serif;cursor:pointer}
@media (max-width:760px){.install-mini.ready{display:flex}}
@media (display-mode:standalone){.install-mini{display:none!important}.install-sheet{display:none!important}}
</style>
HTML;

    $installUi = <<<'HTML'
<button type="button" id="installMini" class="install-mini" aria-label="نصب نیازستان روی گوشی">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>
<span>نصب</span>
</button>
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
  if(standalone)return;
  let deferredPrompt=null;
  const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);
  const isSafari=/safari/i.test(navigator.userAgent)&&!/crios|fxios|edgios/i.test(navigator.userAgent);
  btn.classList.add('ready');
  window.addEventListener('beforeinstallprompt',e=>{
    e.preventDefault();
    deferredPrompt=e;
    btn.classList.add('ready');
  });
  window.addEventListener('appinstalled',()=>{btn.classList.remove('ready');sheet.classList.remove('show');deferredPrompt=null;});
  btn.addEventListener('click',async()=>{
    if(deferredPrompt){
      deferredPrompt.prompt();
      try{const choice=await deferredPrompt.userChoice;if(choice&&choice.outcome==='accepted')btn.classList.remove('ready')}catch(_){ }
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
