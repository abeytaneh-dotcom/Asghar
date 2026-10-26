(function(){
'use strict';
var p=(location.pathname||'').replace(/\/+$/,'');
if(!(p==='/tehran'||p==='/tehran/index.php'))return;
var NAV_ID='tm-server-mobile-nav';
var active='home';

function goTo(el){
  if(!el)return;
  var y=el.getBoundingClientRect().top+window.pageYOffset-76;
  window.scrollTo({top:Math.max(0,y),behavior:'smooth'});
}
function setActive(name){
  active=name;
  var nav=document.getElementById(NAV_ID);
  if(!nav)return;
  nav.querySelectorAll('[data-server-nav]').forEach(function(b){
    b.classList.toggle('is-current',b.getAttribute('data-server-nav')===name);
  });
}
function removeLegacy(){
  document.querySelectorAll('.tm-mobile-nav').forEach(function(n){n.remove();});
}
function ensureNav(){
  removeLegacy();
  if(document.getElementById(NAV_ID))return;
  var nav=document.createElement('nav');
  nav.id=NAV_ID;
  nav.setAttribute('aria-label','منوی پایین موبایل');
  nav.innerHTML=''
    +'<button type="button" data-server-nav="home"><span class="tm-snav-ico">⌂</span><span>خانه</span></button>'
    +'<button type="button" data-server-nav="products"><span class="tm-snav-ico">▦</span><span>محصولات</span></button>'
    +'<button type="button" class="tm-snav-scan" data-server-nav="scan"><span class="tm-snav-ico">▥</span><span>اسکن</span></button>'
    +'<button type="button" data-server-nav="cart"><span class="tm-snav-ico">🛒</span><span>سبد خرید</span></button>'
    +'<a href="account.php" data-server-nav="account"><span class="tm-snav-ico">◯</span><span>حساب من</span></a>';
  document.body.appendChild(nav);
  document.documentElement.classList.add('tm-server-nav-ready');
  document.body.classList.add('tm-server-nav-ready');
  setActive(active);
}
function handleNav(name){
  if(name==='home'){
    setActive('home');
    window.scrollTo({top:0,behavior:'smooth'});
    return;
  }
  if(name==='products'){
    setActive('products');
    goTo(document.getElementById('products')||document.querySelector('[id*="product"]'));
    return;
  }
  if(name==='cart'){
    setActive('cart');
    goTo(document.getElementById('cart')||document.querySelector('.cartbox'));
    return;
  }
  if(name==='scan'){
    setActive('scan');
    var scan=document.getElementById('barcode-scan-fab');
    if(scan)scan.click();
  }
}
function capture(e){
  var t=e.target&&e.target.closest?e.target.closest('#'+NAV_ID+' [data-server-nav]'):null;
  if(!t)return;
  var name=t.getAttribute('data-server-nav');
  if(name==='account')return;
  e.preventDefault();
  e.stopPropagation();
  if(e.stopImmediatePropagation)e.stopImmediatePropagation();
  handleNav(name);
}

document.addEventListener('click',capture,true);
document.addEventListener('touchend',capture,true);

function init(){
  ensureNav();
  var mo=new MutationObserver(function(){
    removeLegacy();
    if(!document.getElementById(NAV_ID))ensureNav();
  });
  mo.observe(document.body,{childList:true,subtree:true});
  setTimeout(ensureNav,250);
  setTimeout(ensureNav,800);
  setTimeout(ensureNav,1600);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
