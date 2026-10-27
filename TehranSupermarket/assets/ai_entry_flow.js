(function(){'use strict';
function loadStyle(id,href){
  try{
    var old=document.getElementById(id);
    if(old){if(old.getAttribute('href')!==href)old.setAttribute('href',href);return;}
    var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href=href;l.media='all';
    (document.head||document.documentElement).appendChild(l);
  }catch(e){}
}
// Load the premium storefront directly from the page runtime. This is intentionally
// independent of Apache mod_substitute because some cPanel stacks do not apply it.
loadStyle('tm-server-storefront-runtime','assets/server_storefront_critical_v1.css?v=20261027-7');
loadStyle('tm-premium-mobile-runtime','assets/tehran_premium_mobile_v5.css?v=20261027-7');
loadStyle('tm-premium-hero-runtime','assets/tehran_premium_hero_v6.css?v=20261027-7');
try{document.documentElement.classList.add('tm-premium-live-v7');}catch(e){}
try{
  if('caches' in window){caches.keys().then(function(keys){return Promise.all(keys.filter(function(k){return /^tehran-pwa/i.test(k);}).map(function(k){return caches.delete(k);}));}).catch(function(){});}
  if('serviceWorker' in navigator){navigator.serviceWorker.register('sw.js?v=20261027-premium-7',{scope:'/tehran/',updateViaCache:'none'}).then(function(r){r.update().catch(function(){});if(r.waiting)r.waiting.postMessage('SKIP_WAITING');}).catch(function(){});}
}catch(e){}
})();
// deploy-marker: premium-storefront-runtime-v7 no-apache-injection-dependency
// visual-verification-trigger: 20261027-v8
