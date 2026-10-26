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
function installReady(){
  document.documentElement.classList.add('tm-server-nav-ready');
  if(document.body)document.body.classList.add('tm-server-nav-ready');
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
function createNav(){
  var nav=document.createElement('nav');
  nav.id=NAV_ID;
  nav.setAttribute('aria-label','منوی پایین موبایل');
  nav.innerHTML=''
    +'<button type="button" data-server-nav="home"><span class="tm-snav-ico">⌂</span><span>خانه</span></button>'
    +'<button type="button" data-server-nav="products"><span class="tm-snav-ico">▦</span><span>محصولات</span></button>'
    +'<button type="button" class="tm-snav-scan" data-server-nav="scan"><span class="tm-snav-ico">▥</span><span>اسکن</span></button>'
    +'<button type="button" data-server-nav="cart"><span class="tm-snav-ico">🛒</span><span>سبد خرید</span></button>'
    +'<a href="account.php" data-server-nav="account"><span class="tm-snav-ico">◯</span><span>حساب من</span></a>';
  return nav;
}
function ensureNav(){
  removeLegacy();
  installReady();
  var nav=document.getElementById(NAV_ID);
  if(!nav&&document.body){
    nav=createNav();
    document.body.appendChild(nav);
  }
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
  var t=e.target&&e.target.closest?e.target.closest('#'+NAV_ID+' [data-server-nav],.tm-mobile-nav [data-nav]'):null;
  if(!t)return;
  var name=t.getAttribute('data-server-nav')||t.getAttribute('data-nav')||'';
  if(name==='account')return;
  e.preventDefault();
  e.stopPropagation();
  if(e.stopImmediatePropagation)e.stopImmediatePropagation();
  handleNav(name);
}
function blockOldNavAnchors(e){
  var a=e.target&&e.target.closest?e.target.closest('a'):null;
  if(!a)return;
  var txt=(a.textContent||'').trim();
  var href=(a.getAttribute('href')||'').trim();
  if((txt==='خانه'||txt==='صفحه اصلی')&&(href==='index.php'||href==='/tehran/'||href==='/tehran/index.php')){
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();handleNav('home');return;
  }
  if(txt==='محصولات'&&(href==='#products'||href==='index.php#products'||href==='/tehran/#products'||href==='/tehran/index.php#products')){
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();handleNav('products');return;
  }
}

document.addEventListener('click',capture,true);
document.addEventListener('touchend',capture,true);
document.addEventListener('click',blockOldNavAnchors,true);
document.addEventListener('touchend',blockOldNavAnchors,true);

function init(){
  ensureNav();
  var mo=new MutationObserver(function(){
    removeLegacy();
    if(!document.getElementById(NAV_ID))ensureNav();
  });
  mo.observe(document.body,{childList:true,subtree:true});
  setTimeout(ensureNav,100);
  setTimeout(ensureNav,400);
  setTimeout(ensureNav,1000);
}
installReady();
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
window.addEventListener('pageshow',ensureNav);
})();
