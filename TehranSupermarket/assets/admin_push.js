(function(){
  function b64ToU8(s){const p='='.repeat((4-s.length%4)%4),b=atob((s+p).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from([...b].map(c=>c.charCodeAt(0)));}
  const pushError='کد خطا: PUSH-901 — اعلان پس‌زمینه فعال نشد. راه‌حل: مجوز Notifications را برای این سایت روی Allow بگذارید و دوباره تلاش کنید.';
  async function enable(btn){
    try{
      if(!('serviceWorker'in navigator)||!('PushManager'in window)||!('Notification'in window))throw new Error('unsupported');
      const perm=await Notification.requestPermission();if(perm!=='granted')throw new Error('denied');
      const reg=await navigator.serviceWorker.register('sw.js');await navigator.serviceWorker.ready;
      const k=await fetch('push_public.php',{cache:'no-store',credentials:'same-origin'}).then(r=>r.json());if(!k.ok)throw new Error('key');
      let sub=await reg.pushManager.getSubscription();if(!sub)sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToU8(k.public_key)});
      const r=await fetch('push_subscribe.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(sub.toJSON())});if(!r.ok)throw new Error('save');
      localStorage.setItem('tehran_push_enabled','1');btn.textContent='✅ اعلان پس‌زمینه فعال است';btn.title='';btn.disabled=true;
    }catch(_e){btn.textContent='⚠️ PUSH-901 · اعلان فعال نشد';btn.title=pushError;btn.disabled=false;if(typeof window.showToast==='function')window.showToast(pushError);}
  }
  document.addEventListener('DOMContentLoaded',()=>{
    let btn=document.getElementById('bgPushEnable');if(!btn){btn=document.createElement('button');btn.id='bgPushEnable';btn.type='button';btn.className='btn pale';btn.style.cssText='position:fixed;left:12px;bottom:58px;z-index:9997';document.body.appendChild(btn);}
    btn.textContent=localStorage.getItem('tehran_push_enabled')==='1'?'🔔 بررسی اعلان پس‌زمینه':'🔔 اعلان حتی وقتی پنل بسته است';btn.onclick=()=>enable(btn);
  });
})();
