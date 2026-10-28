(()=>{
  'use strict';
  const cfg=JSON.parse(document.getElementById('nz-ai-config')?.textContent||'{}');
  async function init(){
    const button=document.getElementById('nznt-enable');if(!button||!cfg.logged_in)return;
    const status=document.getElementById('nznt-status'),badge=document.getElementById('nznt-count');let seen=new Set(),first=true,busy=false,publicKey='',registration=null,enabled=false;
    const api=async(data=null)=>{const r=await fetch('/nz-notification-api.php',data?{method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({csrf:cfg.csrf,...data})}:{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'ارتباط برقرار نشد.');return j;};
    const refresh=async()=>{if(busy||document.visibilityState!=='visible')return;busy=true;try{const j=await api();publicKey=j.public_key;badge.textContent=j.count?`اعلان‌های خوانده‌نشده (${j.count.toLocaleString('fa-IR')})`:'گفتگوها';
        if(!first&&j.items.some(n=>!seen.has(n.id))){document.querySelector('.nzai-toast')?.remove();const toast=document.createElement('div');toast.className='nzai-toast';toast.setAttribute('role','status');const a=document.createElement('a');a.href='/nz?page=notifications';a.textContent='پیام یا اعلان تازه دارید؛ مشاهده';toast.append(a);document.body.append(toast);setTimeout(()=>toast.remove(),6500);}
        seen=new Set(j.items.map(n=>n.id));first=false;
      }catch(e){if(first)status.textContent=e.message;}finally{busy=false;}};
    if('serviceWorker' in navigator&&'PushManager' in window){
      try{registration=await navigator.serviceWorker.getRegistration('/nz');if(!registration)registration=await navigator.serviceWorker.register('/niazestan-sw.js',{scope:'/nz',updateViaCache:'none'});
        const sub=await registration.pushManager.getSubscription();if(sub){await api({endpoint:sub.endpoint});enabled=true;button.textContent='غیرفعال‌کردن اعلان این دستگاه';}
      }catch(_){status.textContent='برای فعال‌سازی اعلان دوباره تلاش کنید.';}
    }else{button.disabled=true;status.textContent='اعلان پس‌زمینه در این مرورگر پشتیبانی نمی‌شود. در آیفون، نیازستان را به صفحهٔ اصلی اضافه و از آیکون آن باز کنید.';}
    button.onclick=async()=>{
      if(enabled){button.disabled=true;try{const sub=await registration.pushManager.getSubscription();if(sub){await api({action:'unsubscribe',endpoint:sub.endpoint});await sub.unsubscribe();}enabled=false;button.textContent='فعال‌کردن اعلان پیام‌ها';status.textContent='اعلان این دستگاه غیرفعال شد.';}catch(e){status.textContent=e.message;}finally{button.disabled=false;}return;}
      if(!publicKey||!registration?.active){status.textContent='آماده‌سازی اعلان در جریان است؛ چند لحظه بعد دوباره بزنید.';return;}
      button.disabled=true;try{
        const b64=publicKey.replace(/-/g,'+').replace(/_/g,'/');const key=Uint8Array.from(atob(b64+'='.repeat((4-b64.length%4)%4)),c=>c.charCodeAt(0));
        // Subscribe directly in the click handler so Safari retains user activation.
        const sub=await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});await api({endpoint:sub.endpoint});enabled=true;button.textContent='غیرفعال‌کردن اعلان این دستگاه';status.textContent='اعلان پیام‌ها برای این دستگاه فعال شد.';
      }catch(e){status.textContent=window.Notification?.permission==='denied'?'اجازهٔ اعلان در تنظیمات مرورگر بسته است.':e.message;}finally{button.disabled=false;}
    };
    await refresh();setInterval(refresh,15000);document.addEventListener('visibilitychange',refresh);
  }
  if(document.readyState==='complete')init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();
