const CACHE='tehran-pwa-v2';
const SHELL=['/tehran/assets/style.css'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL).catch(()=>{})));self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));self.clients.claim();});
self.addEventListener('fetch',event=>{
  const req=event.request;if(req.method!=='GET')return;
  const url=new URL(req.url);if(url.origin!==location.origin)return;
  const p=url.pathname;
  if(/\/(admin|api|image_upload|pyramid|backup|install|track|reorder|courier|payment)/.test(p))return;
  if(req.mode==='navigate'){
    event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body dir="rtl" style="font-family:Tahoma;padding:30px;text-align:center"><h2>اینترنت در دسترس نیست</h2><p>برای مشاهده موجودی و ثبت سفارش دوباره به اینترنت وصل شوید.</p></body>',{headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
    return;
  }
  if(p.startsWith('/tehran/assets/')||p.includes('/uploads/products/')){
    event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(r=>{if(r.ok){const copy=r.clone();caches.open(CACHE).then(c=>c.put(req,copy));}return r;})));
  }
});
