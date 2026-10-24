const CACHE='tehran-pwa-v4';
const SHELL=['/tehran/assets/style.css','/tehran/offline.html'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL).catch(()=>{})));self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));self.clients.claim();});
self.addEventListener('fetch',event=>{
  const req=event.request;if(req.method!=='GET')return;
  const url=new URL(req.url);if(url.origin!==location.origin)return;
  const p=url.pathname;
  if(/\/(admin|api|image_upload|pyramid|backup|install|track|reorder|courier|payment|account|push_)/.test(p))return;
  if(req.mode==='navigate'){
    event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>caches.match('/tehran/offline.html')));return;
  }
  if(p.startsWith('/tehran/assets/')||p.includes('/uploads/products/')){
    event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(r=>{if(r.ok){const copy=r.clone();caches.open(CACHE).then(c=>c.put(req,copy));}return r;})));
  }
});
self.addEventListener('push',event=>{
  let title='🛒 سفارش جدید سوپرمارکت طهران';let body='یک سفارش جدید ثبت شده؛ برای مشاهده سفارش‌ها وارد پنل شوید.';
  try{if(event.data){const d=event.data.json();title=d.title||title;body=d.body||body;}}catch(e){}
  event.waitUntil(self.registration.showNotification(title,{body,icon:'/tehran/pwa-icon.php?size=192',badge:'/tehran/pwa-icon.php?size=192',tag:'tehran-new-order',renotify:true,data:{url:'/tehran/admin_orders.php?status=new'}}));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();const target=(event.notification.data&&event.notification.data.url)||'/tehran/admin_orders.php?status=new';
  event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{for(const c of list){if('focus'in c){c.navigate(target);return c.focus();}}return clients.openWindow(target);}));
});
