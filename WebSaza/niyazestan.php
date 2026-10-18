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

    // Use concept 2 brand lockup instead of the old letter mark/plain wordmark.
    $brandLockup = '<span class="niazestan-lockup"><img class="niazestan-brand-logo" src="/niazestan-logo.svg" alt=""><span class="niazestan-wordmark">نیازستان</span></span>';
    $html = str_replace('<span class="brand-mark">ن</span><span>نیازستان</span>', $brandLockup, $html);

    // Remove the large "use like an app" hint from the home page.
    $html = preg_replace(
        '/<div class="install-hint">.*?<\/div><nav class="category-strip">/su',
        '<nav class="category-strip">',
        $html,
        1
    ) ?? $html;

    // Replace the rough mobile search glyph with a clean hook for vector styling.
    $html = str_replace(
        '<form class="mobile-search" method="get"><span>⌕</span>',
        '<form class="mobile-search" method="get"><span class="mobile-search-icon" aria-hidden="true"></span>',
        $html
    );

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
/* Concept 2 brand lockup */
.niazestan-lockup{display:inline-flex;align-items:center;gap:7px;line-height:1;text-decoration:none}.niazestan-brand-logo{display:block;width:46px;height:46px;object-fit:contain}.niazestan-wordmark{font-family:Tahoma,"Vazirmatn",Arial,sans-serif;font-weight:950;color:#073b79;font-size:19px;letter-spacing:-.4px}.brand .niazestan-lockup{flex-direction:row}.mobile-brand .niazestan-lockup{flex-direction:column;gap:0}.mobile-brand .niazestan-brand-logo{width:50px;height:42px}.mobile-brand .niazestan-wordmark{font-size:12.5px;margin-top:-1px;letter-spacing:-.3px}

/* Keep logo at left and city selector at right, like the approved mockup. */
.mobile-row{position:relative;direction:ltr;min-height:54px}.mobile-row>*{direction:rtl}.mobile-location{font-weight:850;background:#fff!important;border:1px solid #e3e7ec!important;box-shadow:0 3px 12px rgba(20,35,60,.05);padding-right:31px!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23161b22'%3E%3Cpath d='M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.4A2.4 2.4 0 1 1 12 6.6a2.4 2.4 0 0 1 0 4.8Z'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-size:17px 17px!important;background-position:right 9px center!important}

/* Prominent install CTA */
.install-mini{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:25;border:2px solid #111;background:#ffd83d;box-shadow:0 5px 14px rgba(0,0,0,.18),0 0 0 1px rgba(255,216,61,.35);border-radius:999px;height:34px;padding:0 12px;color:#e31b23;font-family:Tahoma,"Vazirmatn",Arial,sans-serif;font-size:13px;font-weight:950;line-height:1;display:inline-flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap}.install-mini svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.5}.install-mini:hover{filter:brightness(1.03)}.install-mini:active{transform:translate(-50%,-50%) scale(.96)}

/* Mockup-like mobile search */
.mobile-search{position:relative;background:#fff!important;border:1px solid #e6e9ee!important;box-shadow:0 6px 20px rgba(28,42,65,.07);height:48px!important;border-radius:16px!important;padding:0 44px 0 13px!important}.mobile-search-icon{position:absolute;right:13px;top:50%;transform:translateY(-50%);width:22px;height:22px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230b1f3a' stroke-width='2.1' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-4-4'/%3E%3C/svg%3E") center/contain no-repeat}.mobile-search::after{content:'';position:absolute;left:13px;top:50%;transform:translateY(-50%);width:22px;height:22px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230b1f3a' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='M4 7h10M18 7h2M10 12h10M4 12h2M4 17h6M14 17h6'/%3E%3Ccircle cx='16' cy='7' r='2'/%3E%3Ccircle cx='8' cy='12' r='2'/%3E%3Ccircle cx='12' cy='17' r='2'/%3E%3C/svg%3E") center/contain no-repeat}.mobile-search input{padding-left:25px!important;font-weight:650;color:#252a32}.mobile-search input::placeholder{color:#a6abb3}

/* Colorful category tiles from the approved visual direction */
@media (max-width:760px){.category-strip{gap:7px!important;padding-top:7px!important}.cat-chip{min-width:82px!important;gap:7px!important;font-size:11px!important;font-weight:800;color:#252a32!important}.cat-icon{width:56px!important;height:56px!important;border-radius:16px!important;background:#f3f4f7!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7);font-size:0!important}.cat-icon svg{width:31px;height:31px;display:block}.cat-icon.c-blue{background:#dcecff!important;color:#1769e8}.cat-icon.c-green{background:#dcf7e5!important;color:#06a84f}.cat-icon.c-pink{background:#ffe0eb!important;color:#f01868}.cat-icon.c-purple{background:#e8e0ff!important;color:#773ce3}.cat-icon.c-orange{background:#ffe8d8!important;color:#f56a12}.cat-icon.c-teal{background:#d9f5f3!important;color:#079f9b}.cat-icon.c-yellow{background:#fff0c6!important;color:#f3a500}.cat-icon.c-violet{background:#ece9ff!important;color:#6254ce}
.section-head h1,.section-head h2{font-size:18px!important;font-weight:950}.section-head h1::before,.section-head h2::before{content:'🔥';margin-left:7px;font-size:19px}.section-head p{font-size:11px!important}
.content-grid section .listings{display:grid!important;grid-template-columns:none!important;grid-auto-flow:column;grid-auto-columns:76%;overflow-x:auto;gap:12px!important;border-top:0!important;padding:3px 2px 13px;scroll-snap-type:x mandatory;scrollbar-width:none}.content-grid section .listings::-webkit-scrollbar{display:none}.content-grid section .listing{display:block!important;border:1px solid #e8ebef!important;border-radius:18px!important;padding:0!important;overflow:hidden!important;box-shadow:0 6px 18px rgba(25,39,63,.055);scroll-snap-align:start;background:#fff}.content-grid section .listing-media{display:block!important;aspect-ratio:1.28!important;border-radius:0!important}.content-grid section .listing-body{display:block!important;padding:10px 12px 12px!important}.content-grid section .listing-title{font-weight:900!important;font-size:14px!important;line-height:1.65!important;max-height:47px!important;margin:0 0 7px!important}.content-grid section .listing-price{font-weight:950!important;color:#1260db!important;font-size:13px!important;margin:0 0 6px!important}.content-grid section .listing-meta{font-size:10px!important;color:#8b929b!important}.content-grid section .feature-badge{background:#ff3535!important;border-radius:999px!important;padding:5px 9px!important}.content-grid section .fav-dot{background:rgba(255,255,255,.95)!important;color:#fff!important;text-shadow:0 1px 4px rgba(0,0,0,.35);font-size:22px!important}
}

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
  if(btn&&sheet){
    const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
    if(standalone){btn.style.display='none';}
    let deferredPrompt=null;
    const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);
    const isSafari=/safari/i.test(navigator.userAgent)&&!/crios|fxios|edgios/i.test(navigator.userAgent);
    window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;});
    window.addEventListener('appinstalled',()=>{btn.style.display='none';sheet.classList.remove('show');deferredPrompt=null;});
    btn.addEventListener('click',async()=>{
      if(deferredPrompt){deferredPrompt.prompt();try{const choice=await deferredPrompt.userChoice;if(choice&&choice.outcome==='accepted')btn.style.display='none';}catch(_){ }deferredPrompt=null;return;}
      if(isIos&&isSafari){text.innerHTML='در Safari روی دکمه <b>اشتراک‌گذاری</b> بزنید و سپس <b>Add to Home Screen / افزودن به صفحه اصلی</b> را انتخاب کنید.';}
      else{text.innerHTML='از منوی مرورگر، گزینه <b>Install app / نصب برنامه</b> یا <b>افزودن به صفحه اصلی</b> را انتخاب کنید.';}
      sheet.classList.add('show');
    });
    close.addEventListener('click',()=>sheet.classList.remove('show'));
    sheet.addEventListener('click',e=>{if(e.target===sheet)sheet.classList.remove('show')});
  }
})();

// Upgrade category glyphs to the colorful vector icon system from concept 2.
(()=>{
  const icons={
    'همه':['c-violet','<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="6" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="18" cy="12" r="2"/></svg>'],
    'املاک':['c-green','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 11.2 12 4l9 7.2V21h-6v-6H9v6H3z"/></svg>'],
    'وسایل نقلیه':['c-blue','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 8.5 7 5h10l2 3.5 2 1.5v7h-2v2h-3v-2H8v2H5v-2H3v-7l2-1.5ZM7 9h10l-1.2-2H8.2L7 9Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>'],
    'کالای دیجیتال':['c-pink','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 5h4M11 19h2"/></svg>'],
    'خانه و آشپزخانه':['c-purple','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 9a3 3 0 0 0-3 3v6h2v2h3v-2h8v2h3v-2h2v-6a3 3 0 0 0-3-3h-1V7a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3v2H6Zm3 0V7a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9Z"/></svg>'],
    'خدمات':['c-orange','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.7 3.3a5 5 0 0 0-6.4 6.4L3 15l6 6 5.3-5.3a5 5 0 0 0 6.4-6.4l-3.1 3.1-3-3 3.1-3.1a5 5 0 0 0-3-3Z"/></svg>'],
    'استخدام و کاریابی':['c-teal','<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 5V3h6v2h4a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3h4Zm2 0h2V4h-2v1Zm11 6H2v2h8v2h4v-2h8v-2Z"/></svg>'],
    'شخصی':['c-yellow','<svg viewBox="0 0 24 24" fill="currentColor"><path d="m8 4 4 2 4-2 5 3-3 5-2-1v9H8v-9l-2 1-3-5 5-3Z"/></svg>'],
    'سرگرمی':['c-violet','<svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 2.5 2.7 5.6 6.2.9-4.5 4.4 1.1 6.2-5.5-2.9-5.5 2.9 1.1-6.2L3.1 9l6.2-.9L12 2.5Z"/></svg>']
  };
  document.querySelectorAll('.cat-chip').forEach(chip=>{
    const label=(chip.querySelector('span:last-child')?.textContent||'').trim();
    const target=chip.querySelector('.cat-icon');
    if(target&&icons[label]){target.className='cat-icon '+icons[label][0];target.innerHTML=icons[label][1];}
  });
})();
</script>
HTML;

    $html = str_replace('</head>', $installCss . "\n</head>", $html);
    $html = str_replace('</body>', $installUi . "\n</body>", $html);

    echo $html;
}
