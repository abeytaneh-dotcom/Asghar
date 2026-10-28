<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Service-Worker-Allowed: /nz');
header('X-Content-Type-Options: nosniff');

function nzsw_asset(string $name): string {
    $path=__DIR__.'/'.$name;
    $version=is_file($path)?substr(hash_file('sha256',$path),0,12):'missing';
    return '/'.$name.'?v='.$version;
}
$static=[
    nzsw_asset('nz-manifest.json'),nzsw_asset('niazestan-icon.svg'),nzsw_asset('niazestan-icon-192.php'),nzsw_asset('niazestan-icon-512.php'),
    nzsw_asset('nz-offline.html'),nzsw_asset('nz-install-direct.js'),nzsw_asset('nz-media-client.js'),nzsw_asset('nz-geocode-proxy.js'),
    nzsw_asset('nz-submit-wizard.js'),nzsw_asset('nz-location-privacy.js'),nzsw_asset('nz-taxonomy-client.js'),nzsw_asset('nz-spec-lite.js'),
    nzsw_asset('nz-brand-model.js'),nzsw_asset('nz-image-moderation.js'),nzsw_asset('nz-draft-preview.js'),nzsw_asset('nz-chat-live.js'),nzsw_asset('nz-a11y.js'),
    nzsw_asset('nz-ai.css'),nzsw_asset('nz-ai.js'),nzsw_asset('nz-notify.js'),nzsw_asset('nz-ai-learning.js')
];
$cache='niazestan-'.substr(hash('sha256',implode('|',$static)),0,16);
$staticJson=json_encode($static,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$cacheJson=json_encode($cache,JSON_UNESCAPED_SLASHES);

echo "const CACHE={$cacheJson};\nconst STATIC={$staticJson};\n";
?>
const NEVER_CACHE_PATHS=new Set([
  '/nz-chat-api.php','/nz-lifecycle-cron.php','/nz-models.php','/nz-taxonomy.php','/nz-geocode.php','/nz-health.php',
  '/nz-sitemap.php','/nz-sms-status.php','/nz-backup-export.php','/nz-sw.php','/nz-ai-api.php','/nz-notification-api.php','/nz-ai-learning-api.php'
]);
function staticMatch(url){if(url.origin!==self.location.origin||NEVER_CACHE_PATHS.has(url.pathname))return false;return STATIC.some(entry=>{const e=new URL(entry,self.location.origin);return e.pathname===url.pathname&&(!e.search||e.search===url.search)});}
async function cacheOne(cache,url){try{const r=await fetch(url,{cache:'reload'});if(r&&r.ok)await cache.put(url,r.clone());return !!(r&&r.ok)}catch(_){return false}}
self.addEventListener('install',event=>{event.waitUntil((async()=>{const cache=await caches.open(CACHE);const offline=STATIC.find(x=>new URL(x,self.location.origin).pathname==='/nz-offline.html');if(offline)await cacheOne(cache,offline);await Promise.allSettled(STATIC.filter(x=>x!==offline).map(x=>cacheOne(cache,x)));await self.skipWaiting()})());});
self.addEventListener('activate',event=>{event.waitUntil((async()=>{const keys=await caches.keys();await Promise.all(keys.filter(k=>k.startsWith('niazestan-')&&k!==CACHE).map(k=>caches.delete(k)));await self.clients.claim()})());});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;const url=new URL(event.request.url);if(url.origin!==self.location.origin)return;
  if(event.request.mode==='navigate'){
    event.respondWith(fetch(event.request,{cache:'no-store'}).catch(async()=>{const offline=STATIC.find(x=>new URL(x,self.location.origin).pathname==='/nz-offline.html');return offline?(await caches.match(offline)||Response.error()):Response.error()}));return;
  }
  if(NEVER_CACHE_PATHS.has(url.pathname)){event.respondWith(fetch(event.request,{cache:'no-store'}));return;}
  if(staticMatch(url)){event.respondWith((async()=>{const hit=await caches.match(event.request);if(hit)return hit;const response=await fetch(event.request);if(response&&response.ok){const cache=await caches.open(CACHE);await cache.put(event.request,response.clone())}return response})());}
});
self.addEventListener('push',event=>{
  // Empty payload: no private message or phone is sent through the push provider.
  event.waitUntil(self.registration.showNotification('نیازستان',{
    body:'پیام یا اعلان تازه دارید. برای مشاهده وارد نیازستان شوید.',
    icon:'/niazestan-icon-192.php',badge:'/niazestan-icon-192.php',
    tag:'niazestan-messages',data:{url:'/nz?page=notifications'}
  }));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();const url=new URL('/nz?page=notifications',self.location.origin).href;
  event.waitUntil((async()=>{const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});for(const client of windows){const u=new URL(client.url);if(u.origin===self.location.origin&&(u.pathname==='/nz'||u.pathname.startsWith('/nz/'))){await client.navigate(url);return client.focus();}}return self.clients.openWindow(url);})());
});
