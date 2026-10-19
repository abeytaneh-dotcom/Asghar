<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Service-Worker-Allowed: /nz');
header('X-Content-Type-Options: nosniff');

$js = <<<'JS'
const CACHE='niazestan-v8';
const STATIC=[
  '/nz-manifest.json?v=20261019-8',
  '/niazestan-icon.svg',
  '/niazestan-icon-192.php?v=20261019-2',
  '/niazestan-icon-512.php?v=20261019-2',
  '/nz-offline.html',
  '/nz-install-direct.js?v=20261019-6',
  '/nz-media-client.js?v=20261019-2',
  '/nz-geocode-proxy.js?v=20261019-1',
  '/nz-submit-wizard.js?v=20261019-3',
  '/nz-location-privacy.js?v=20261019-1',
  '/nz-taxonomy-client.js?v=20261019-1',
  '/nz-spec-lite.js?v=20261019-1',
  '/nz-brand-model.js?v=20261019-3',
  '/nz-image-moderation.js?v=20261019-1',
  '/nz-draft-preview.js?v=20261019-1',
  '/nz-chat-live.js?v=20261019-2'
];
const NEVER_CACHE_PATHS=new Set([
  '/nz-chat-api.php','/nz-lifecycle-cron.php','/nz-models.php','/nz-taxonomy.php','/nz-geocode.php',
  '/nz-sitemap.php','/nz-sms-status.php','/nz-backup-export.php','/nz-sw.php'
]);
function staticMatch(url){if(url.origin!==self.location.origin||NEVER_CACHE_PATHS.has(url.pathname))return false;return STATIC.some(entry=>{const e=new URL(entry,self.location.origin);return e.pathname===url.pathname&&(!e.search||e.search===url.search)});}
async function cacheOne(cache,url){try{const r=await fetch(url,{cache:'reload'});if(r&&r.ok)await cache.put(url,r.clone());return r&&r.ok}catch(_){return false}}
self.addEventListener('install',event=>{event.waitUntil((async()=>{const cache=await caches.open(CACHE);await cacheOne(cache,'/nz-offline.html');await Promise.allSettled(STATIC.filter(x=>x!=='/nz-offline.html').map(x=>cacheOne(cache,x)));await self.skipWaiting()})());});
self.addEventListener('activate',event=>{event.waitUntil((async()=>{const keys=await caches.keys();await Promise.all(keys.filter(k=>k.startsWith('niazestan-')&&k!==CACHE).map(k=>caches.delete(k)));await self.clients.claim()})());});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;const url=new URL(event.request.url);if(url.origin!==self.location.origin)return;
  if(event.request.mode==='navigate'){event.respondWith(fetch(event.request,{cache:'no-store'}).catch(async()=>await caches.match('/nz-offline.html')||Response.error()));return;}
  if(NEVER_CACHE_PATHS.has(url.pathname)){event.respondWith(fetch(event.request,{cache:'no-store'}));return;}
  if(staticMatch(url)){event.respondWith((async()=>{const hit=await caches.match(event.request);if(hit)return hit;const response=await fetch(event.request);if(response&&response.ok){const cache=await caches.open(CACHE);await cache.put(event.request,response.clone())}return response})());}
});
JS;

echo $js;