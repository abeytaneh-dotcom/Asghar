(function(){'use strict';
try{
  if('caches' in window){caches.keys().then(function(keys){return Promise.all(keys.filter(function(k){return /^tehran-pwa/i.test(k);}).map(function(k){return caches.delete(k);}));}).catch(function(){});}
  if('serviceWorker' in navigator){navigator.serviceWorker.register('sw.js?v=20261027-reference-3',{scope:'/tehran/',updateViaCache:'none'}).then(function(r){r.update().catch(function(){});if(r.waiting)r.waiting.postMessage('SKIP_WAITING');}).catch(function(){});}
}catch(e){}
})();
// deploy-marker: direct-reference-shell-v3 no-legacy-dom-rewrite
