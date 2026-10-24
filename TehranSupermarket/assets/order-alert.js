(function(){
  const KEY='tehran_last_order_seen';
  let enabled=false;
  let audioCtx=null;
  function ensureButton(){
    if(document.getElementById('orderNotifyEnable'))return;
    const b=document.createElement('button');b.id='orderNotifyEnable';b.type='button';b.textContent='🔔 فعال‌سازی اعلان سفارش';
    b.style.cssText='position:fixed;left:12px;bottom:12px;z-index:9999;border:0;border-radius:12px;padding:10px 13px;background:#123c27;color:#fff;font:inherit;box-shadow:0 8px 25px #0003';
    b.onclick=async()=>{try{if('Notification'in window&&Notification.permission==='default')await Notification.requestPermission();audioCtx=new(window.AudioContext||window.webkitAudioContext)();await audioCtx.resume();enabled=true;b.textContent='🔔 اعلان فعال است';setTimeout(()=>b.style.display='none',1800);}catch(e){b.textContent='اعلان مرورگر فعال نشد';}};
    document.body.appendChild(b);
  }
  function beep(){try{if(!audioCtx)return;const o=audioCtx.createOscillator(),g=audioCtx.createGain();o.frequency.value=880;g.gain.value=.08;o.connect(g);g.connect(audioCtx.destination);o.start();setTimeout(()=>o.stop(),240);}catch(e){}}
  async function poll(){
    try{const r=await fetch('admin_alert.php',{cache:'no-store',credentials:'same-origin'});if(!r.ok)return;const d=await r.json();if(!d.ok)return;
      document.querySelectorAll('[data-new-order-count]').forEach(el=>{el.textContent=d.new_count||0;el.style.display=(d.new_count||0)>0?'inline-flex':'none';});
      const prev=parseInt(localStorage.getItem(KEY)||'0',10);const latest=parseInt(d.latest_id||0,10);
      if(!prev&&latest){localStorage.setItem(KEY,String(latest));return;}
      if(latest>prev){localStorage.setItem(KEY,String(latest));beep();if('Notification'in window&&Notification.permission==='granted'){const name=d.latest&&d.latest.customer_name?d.latest.customer_name:'مشتری';new Notification('سفارش جدید سوپرمارکت طهران',{body:'سفارش #'+latest+' - '+name,tag:'order-'+latest});}const box=document.createElement('a');box.href='admin_orders.php?status=new';box.textContent='🛒 سفارش جدید #'+latest;box.style.cssText='position:fixed;right:12px;bottom:12px;z-index:9998;background:#11823b;color:#fff;padding:13px 16px;border-radius:12px;font-weight:800;box-shadow:0 10px 30px #0003';document.body.appendChild(box);setTimeout(()=>box.remove(),12000);}
    }catch(e){}
  }
  ensureButton();poll();setInterval(poll,10000);
})();
