(function(){
'use strict';
var KEY='tehran_ai_entry_seen_v1';

function loadPremiumCss(){
  if(document.getElementById('tehran-premium-css'))return;
  var l=document.createElement('link');
  l.id='tehran-premium-css';l.rel='stylesheet';l.href='assets/tehran-premium-v1.css?v=20261027-1';
  document.head.appendChild(l);
}
function esc(v){return String(v||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
function qParam(name){try{return new URLSearchParams(location.search).get(name)||'';}catch(e){return '';}}
function upgradeShell(){
  if(document.body.classList.contains('tehran-premium-home'))return;
  loadPremiumCss();
  document.body.classList.add('tehran-premium-home');
  document.documentElement.style.setProperty('color-scheme','light');
  var top=document.querySelector('header.top');
  if(top){
    var tools=top.querySelector(':scope > div');
    var search=document.createElement('form');search.className='tm-top-search';search.method='get';search.action='index.php';
    search.innerHTML='<input name="q" value="'+esc(qParam('q'))+'" autocomplete="off" aria-label="جستجوی محصولات" placeholder="جستجوی محصولات؛ مثلا برنج، روغن، شیر…"><button type="submit" aria-label="جستجو">⌕</button>';
    if(tools)top.insertBefore(search,tools);else top.appendChild(search);
    var nav=document.createElement('nav');nav.className='tm-nav';nav.setAttribute('aria-label','منوی اصلی');
    nav.innerHTML='<a class="is-active" href="index.php">صفحه اصلی</a><a href="#products">محصولات</a><a href="#smart-shopping">پیشنهادهای هوشمند</a><a class="tm-categories" href="#products">☰ دسته‌بندی کالاها</a><a href="track.php">پیگیری سفارش</a><a href="account.php">حساب من</a>';
    top.appendChild(nav);
  }
  var hero=document.querySelector('.hero');
  if(hero){
    hero.id='smart-shopping';
    hero.innerHTML='<div class="tm-hero-copy"><span class="tm-eyebrow">خرید هوشمند، ساده و سریع</span><h1>اولین فروشگاه <strong>مواد غذایی هوشمند</strong> در ایران</h1><p>کالای موردنظرت را جستجو کن، با بارکد پیدا کن یا از دستیار هوشمند کمک بگیر؛ خرید روزانه با چند لمس ساده.</p><div class="tm-hero-actions"><a class="tm-gold-btn" href="#products">خرید کنید <span>←</span></a><button class="tm-dark-btn" type="button" data-tm-ai>✦ خرید با هوش مصنوعی</button></div><div class="tm-hero-points"><span>پیدا کردن سریع کالا</span><span>سبد خرید هوشمند</span><span>پیگیری ساده سفارش</span></div></div><aside class="tm-hero-panel" aria-label="امکانات هوشمند"><button class="tm-smart-row" type="button" data-tm-ai><span class="tm-smart-icon"><img src="assets/tehran_ai_logo.png" alt=""></span><span><b>هوش مصنوعی</b><small>پیشنهاد محصول و کمک برای ساخت سبد خرید</small></span></button><button class="tm-smart-row" type="button" data-tm-scan><span class="tm-smart-icon">▥</span><span><b>اسکن بارکد</b><small>قیمت، موجودی و مشخصات محصول را سریع ببینید</small></span></button><a class="tm-smart-row" href="#checkout"><span class="tm-smart-icon">🚚</span><span><b>ارسال سریع</b><small>انتخاب محدوده و لوکیشن تحویل روی نقشه</small></span></a></aside>';
    var trust=document.createElement('section');trust.className='tm-trust';trust.setAttribute('aria-label','مزیت‌های فروشگاه');
    trust.innerHTML='<div class="tm-trust-item"><span class="tm-trust-icon">🚚</span><span><b>ارسال سریع</b><small>طبق محدوده انتخابی</small></span></div><div class="tm-trust-item"><span class="tm-trust-icon">✓</span><span><b>موجودی واقعی</b><small>متصل به کالاهای فروشگاه</small></span></div><div class="tm-trust-item"><span class="tm-trust-icon">▥</span><span><b>بارکدخوان</b><small>جستجوی دقیق محصول</small></span></div><div class="tm-trust-item"><span class="tm-trust-icon">✦</span><span><b>خرید هوشمند</b><small>با دستیار هوش مصنوعی</small></span></div><div class="tm-trust-item"><span class="tm-trust-icon">📦</span><span><b>پیگیری سفارش</b><small>وضعیت سفارش آنلاین</small></span></div>';
    hero.insertAdjacentElement('afterend',trust);
    var promos=document.createElement('section');promos.className='tm-promos';promos.setAttribute('aria-label','امکانات ویژه');
    promos.innerHTML='<article class="tm-promo ai"><span class="tm-promo-kicker">تجربه متفاوت خرید</span><h3>خرید هوشمند با هوش مصنوعی</h3><p>بگو چه لازم داری تا دستیار هوشمند برای پیدا کردن کالاها و مدیریت سبد کمکت کند.</p><button type="button" data-tm-ai>شروع خرید هوشمند</button></article><article class="tm-promo scan"><span class="tm-promo-kicker">پیدا کردن فوری محصول</span><h3>اسکن بارکد</h3><p>بارکد محصول را با دوربین بگیر و قیمت و موجودی را همان لحظه ببین.</p><button type="button" data-tm-scan>اسکن بارکد</button></article><article class="tm-promo delivery"><span class="tm-promo-kicker">تحویل ساده‌تر</span><h3>لوکیشن دقیق تحویل</h3><p>روی نقشه محل تحویل را انتخاب کن تا ثبت سفارش سریع و دقیق انجام شود.</p><a href="#checkout">ثبت سفارش</a></article>';
    trust.insertAdjacentElement('afterend',promos);
  }
  var mobile=document.createElement('nav');mobile.className='tm-mobile-nav';mobile.setAttribute('aria-label','منوی پایین موبایل');
  mobile.innerHTML='<a class="home" href="index.php"><span class="ico">⌂</span><span>خانه</span></a><a href="#products"><span class="ico">▦</span><span>دسته‌ها</span></a><button class="scan" type="button" data-tm-scan aria-label="اسکن بارکد"><span class="ico">▥</span><span>اسکن</span></button><a href="#cart"><span class="ico">🛒</span><span>سبد خرید</span></a><a href="account.php"><span class="ico">◯</span><span>حساب من</span></a>';
  document.body.appendChild(mobile);
  document.addEventListener('click',function(e){
    var ai=e.target.closest('[data-tm-ai]');if(ai){e.preventDefault();var a=document.getElementById('tehran-ai-fab-v2');if(a)a.click();return;}
    var scan=e.target.closest('[data-tm-scan]');if(scan){e.preventDefault();var s=document.getElementById('barcode-scan-fab');if(s)s.click();}
  });
}
function seen(){try{return sessionStorage.getItem(KEY)==='1';}catch(e){return false;}}
function mark(){try{sessionStorage.setItem(KEY,'1');}catch(e){}}
function openAssistant(){
  if(seen())return true;
  var fab=document.getElementById('tehran-ai-fab-v2');
  if(!fab)return false;
  mark();fab.click();
  setTimeout(function(){
    var msgs=document.querySelector('.tehran-ai-msgs');
    if(!msgs||msgs.querySelector('[data-entry-welcome]'))return;
    var m=document.createElement('div');m.className='tehran-ai-msg';m.setAttribute('data-entry-welcome','1');
    m.innerHTML='<div class="tehran-ai-bubble"><b>چی لازم داری؟</b><br>اسم کالا یا چیزی که دنبالش هستی رو بگو. اگر هم ترجیح می‌دی خودت بین محصولات بگردی، با × این پنجره رو ببند؛ خرید دستی همیشه در دسترسه.</div>';
    msgs.appendChild(m);msgs.scrollTop=msgs.scrollHeight;
  },250);
  return true;
}
function start(){
  upgradeShell();
  if(seen())return;
  var n=0,t=setInterval(function(){n++;if(openAssistant()||n>30)clearInterval(t);},150);
}
loadPremiumCss();
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){upgradeShell();setTimeout(start,650);},{once:true});else{upgradeShell();setTimeout(start,650);}
})();
// deploy-marker: premium-reference-shell-v1
