const CACHE='niazestan-v6';
const STATIC=[
  '/nz-manifest.json?v=20261019-4',
  '/niazestan-icon.svg',
  '/niazestan-icon-192.php?v=20261019-2',
  '/niazestan-icon-512.php?v=20261019-2',
  '/nz-offline.html',
  '/nz-install-direct.js?v=20261019-6',
  '/nz-media-client.js?v=20261019-2',
  '/nz-submit-wizard.js?v=20261019-3',
  '/nz-spec-lite.js?v=20261019-1',
  '/nz-brand-model.js?v=20261019-2',
  '/nz-image-moderation.js?v=20261019-1',
  '/nz-chat-live.js?v=20261019-2'
];
const NEVER_CACHE_PATHS=[
  '/nz-chat-api.php','/nz-lifecycle-cron.php','/nz-models.php','/nz-taxonomy.php',
  '/nz-sitemap.php','/nz-sms-health.php'
];
function isPrivateNavigation(url){
  if(url.pathname!=='/nz') return false;
  const page=url.searchParams.get('page')||'';
  return ['login','new','profile','myposts','chat','chats','admin','support','saved-searches','services','edit'].includes(page);
}
function isStatic(url){
  if(url.origin!==self.location.origin) return false;
  if(NEVER_CACHE_PATHS.includes(url.pathname)) return false;
  return STATIC.some(entry=>{
    const e=new URL(entry,self.location.origin);
    return e.pathname===url.pathname && (!e.search || e.search===url.search);
  });
}
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC)).catch(()=>{}));
  self.skipWaiting();
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('niazestan-')&&k!==CACHE).map(k=>caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET') return;
  const url=new URL(event.request.url);
  if(url.origin!==self.location.origin) return;

  if(event.request.mode==='navigate'){
    event.respondWith(fetch(event.request,{cache:'no-store'}).catch(()=>caches.match('/nz-offline.html')));
    return;
  }

  if(isPrivateNavigation(url) || NEVER_CACHE_PATHS.includes(url.pathname)){
    event.respondWith(fetch(event.request,{cache:'no-store'}));
    return;
  }

  if(isStatic(url)){
    event.respondWith(caches.match(event.request).then(hit=>hit||fetch(event.request).then(response=>{
      if(response&&response.ok){const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,copy)).catch(()=>{});}
      return response;
    })));
  }
});
