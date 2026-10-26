(function(){
'use strict';
document.documentElement.classList.add('tm-js');
function retireStalePwa(){
  try{
    if('caches' in window){caches.keys().then(function(keys){return Promise.all(keys.filter(function(k){return /^tehran-pwa/i.test(k);}).map(function(k){return caches.delete(k);}));}).catch(function(){});}
    if('serviceWorker' in navigator){
      navigator.serviceWorker.register('sw.js?v=20261027-navfix-13',{scope:'/tehran/',updateViaCache:'none'}).then(function(r){
        r.update().catch(function(){});
        if(r.waiting)r.waiting.postMessage('SKIP_WAITING');
      }).catch(function(){});
    }
  }catch(e){}
}
retireStalePwa();
function esc(v){return String(v||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
function qParam(name){try{return new URLSearchParams(location.search).get(name)||'';}catch(e){return '';}}
function ensureCss(id,href){
  var old=document.getElementById(id);
  if(old){old.href=href;return old;}
  var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href=href;document.head.appendChild(l);return l;
}
function loadCss(){
  ensureCss('tehran-premium-css','assets/tehran-premium-v1.css?v=20261027-5');
  ensureCss('tehran-header-hotfix','assets/header-logo-hotfix.css?v=20261027-2');
  ensureCss('tehran-mobile-polish','assets/mobile-polish-v3.css?v=20261027-2');
  ensureCss('tehran-product-nav-fix','assets/product-nav-fix-v4.css?v=20261027-2');
}
function scrollToEl(el){
  if(!el)return;
  var top=el.getBoundingClientRect().top+window.pageYOffset-86;
  window.scrollTo({top:Math.max(0,top),behavior:'smooth'});
}
function setMobileActive(name){
  document.querySelectorAll('.tm-mobile-nav [data-nav],#tm-server-mobile-nav [data-server-nav]').forEach(function(x){
    var key=x.getAttribute('data-nav')||x.getAttribute('data-server-nav')||'';
    x.classList.toggle('is-current',key===name);
  });
}
function openAi(){var a=document.getElementById('tehran-ai-fab-v2');if(a){a.click();return true;}return false;}
function openScan(){var s=document.getElementById('barcode-scan-fab');if(s){s.click();return true;}return false;}
function handleLegacyMobileNav(e){
  var item=e.target&&e.target.closest?e.target.closest('.tm-mobile-nav [data-nav]'):null;
  if(!item)return;
  var name=item.getAttribute('data-nav')||'';
  if(name==='account')return;
  e.preventDefault();
  e.stopPropagation();
  if(typeof e.stopImmediatePropagation==='function')e.stopImmediatePropagation();
  if(name==='home'){setMobileActive('home');window.scrollTo({top:0,behavior:'smooth'});return;}
  if(name==='products'){setMobileActive('products');scrollToEl(document.getElementById('products'));return;}
  if(name==='cart'){setMobileActive('cart');scrollToEl(document.getElementById('cart')||document.querySelector('.cartbox'));return;}
  if(name==='scan'){setMobileActive('scan');openScan();return;}
}
document.addEventListener('click',handleLegacyMobileNav,true);
document.addEventListener('touchend',handleLegacyMobileNav,true);
function removeLegacyMobileNav(){document.querySelectorAll('.tm-mobile-nav').forEach(function(x){x.remove();});}
function rebuild(){
  loadCss();
  document.body.classList.add('tehran-premium-home');
  document.body.id=document.body.id||'page-top';
  document.documentElement.style.setProperty('color-scheme','light');
  document.querySelectorAll('.tm-feature-row,.tm-mini-banner,.tm-mobile-nav,.tm-nav,.tm-top-search').forEach(function(x){x.remove();});
  var top=document.querySelector('header.top');
  if(top){
    var brand=top.querySelector('.brand');
    if(brand){brand.innerHTML='<span class="tm-brand-mark" aria-hidden="true">ط</span><span class="tm-brand-copy"><b>طهران مارکت</b><small>فروشگاه هوشمند</small></span>';brand.setAttribute('aria-label','طهران مارکت');}
    var tools=top.querySelector(':scope > div');
    var search=document.createElement('form');search.className='tm-top-search';search.method='get';search.action='index.php';
    search.innerHTML='<input name="q" value="'+esc(qParam('q'))+'" autocomplete="off" aria-label="جستجوی محصولات" placeholder="جستجوی محصول؛ مثلا برنج، روغن، شیر…"><button type="submit" aria-label="جستجو">⌕</button>';
    if(tools)top.insertBefore(search,tools);else top.appendChild(search);
    var nav=document.createElement('nav');nav.className='tm-nav';nav.setAttribute('aria-label','منوی اصلی');
    nav.innerHTML='<a class="is-active" href="#page-top" data-tm-scroll="top">صفحه اصلی</a><a href="#products" data-tm-scroll="products">محصولات</a><a href="#smart-shopping" data-tm-scroll="smart-shopping">خرید هوشمند</a><a href="track.php">پیگیری سفارش</a><a href="account.php">حساب من</a>';
    top.appendChild(nav);
  }
  var hero=document.querySelector('.hero');
  if(hero){
    hero.id='smart-shopping';
    hero.innerHTML='<div class="tm-hero-copy"><span class="tm-eyebrow">خرید هوشمند، ساده و سریع</span><h1>اولین فروشگاه <strong>مواد غذایی هوشمند در ایران</strong></h1><p>محصول را جستجو کن، بارکدش را اسکن کن یا از دستیار هوشمند کمک بگیر؛ خرید روزانه سریع‌تر و ساده‌تر.</p><div class="tm-hero-actions"><a class="tm-gold-btn" href="#products" data-tm-scroll="products">مشاهده محصولات ←</a><button class="tm-dark-btn" type="button" data-tm-ai>✦ خرید با هوش مصنوعی</button></div></div>';
    var features=document.createElement('section');features.className='tm-feature-row';features.setAttribute('aria-label','امکانات فروشگاه');
    features.innerHTML='<button class="tm-feature-card" type="button" data-tm-ai><span class="tm-feature-icon">AI</span><span><b>هوش مصنوعی</b><small>کمک برای پیدا کردن کالا و ساخت سبد خرید</small></span></button><button class="tm-feature-card" type="button" data-tm-scan><span class="tm-feature-icon">▥</span><span><b>اسکن بارکد</b><small>مشاهده سریع قیمت، موجودی و مشخصات</small></span></button><a class="tm-feature-card" href="#checkout" data-tm-scroll="checkout"><span class="tm-feature-icon">🚚</span><span><b>ارسال سریع</b><small>انتخاب لوکیشن و ثبت ساده سفارش</small></span></a>';
    hero.insertAdjacentElement('afterend',features);
    var banner=document.createElement('section');banner.className='tm-mini-banner';
    banner.innerHTML='<div class="tm-mini-banner-inner"><div><strong>خرید روزانه، بدون سردرگمی</strong><br><span>دسته‌بندی‌ها، جستجو، بارکدخوان و سبد خرید همه در یک صفحه</span></div><a href="#products" data-tm-scroll="products">شروع خرید</a></div>';
    features.insertAdjacentElement('afterend',banner);
  }
  removeLegacyMobileNav();
  document.body.classList.add('tm-shell-ready');
}
document.addEventListener('click',function(e){
  var ai=e.target.closest('[data-tm-ai]');if(ai){e.preventDefault();openAi();return;}
  var scan=e.target.closest('[data-tm-scan]');if(scan){e.preventDefault();setMobileActive('scan');openScan();return;}
  var smooth=e.target.closest('[data-tm-scroll]');if(smooth){e.preventDefault();var id=smooth.getAttribute('data-tm-scroll');if(id==='top'){window.scrollTo({top:0,behavior:'smooth'});}else{scrollToEl(document.getElementById(id));}return;}
});
loadCss();
if(document.body)document.body.classList.add('tehran-premium-home');
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',rebuild,{once:true});else rebuild();
})();
// deploy-marker: premium-reference-shell-v1 premium-reference-shell-v2-clean header-logo-fallback-v1 mobile-polish-v3 product-nav-fix-v4 hard-no-reload-v6 single-mobile-nav-v1 purge-stale-pwa-v8
