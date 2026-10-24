const CACHE='tehran-pwa-v1';
const SHELL=['/tehran/','/tehran/assets/style.css'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL).catch(()=>{})));self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));self.clients.claim();});
self.addEventListener('fetch',event=>{
  const req=event.request;if(req.method!=='GET')return;
  const url=new URL(req.url);if(url.origin!==location.origin)return;
  if(/\/(admin|api|image_upload|pyramid|backup|install)/.test(url.pathname))return;
  if(req.mode==='navigate'){
    event.respondWith(fetch(req).then(r=>{const copy=r.clone();caches.open(CACHE).then(c=>c.put(req,copy));return r;}).catch(()=>caches.match(req).then(r=>r||caches.match('/tehran/'))));return;
  }
  if(url.pathname.startsWith('/tehran/assets/')||url.pathname.includes('/uploads/products/'))event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(r=>{if(r.ok){const copy=r.clone();caches.open(CACHE).then(c=>c.put(req,copy));}return r;})));
});
