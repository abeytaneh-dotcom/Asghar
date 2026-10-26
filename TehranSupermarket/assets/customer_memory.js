(function(){
'use strict';
var KEY='tehran_customer_profile_v2';
function read(){try{var x=JSON.parse(localStorage.getItem(KEY)||'null');return x&&typeof x==='object'?x:null;}catch(e){return null;}}
function write(x){try{localStorage.setItem(KEY,JSON.stringify(x));}catch(e){}}
function forget(){try{localStorage.removeItem(KEY);}catch(e){}}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#039;'}[m];});}
function maskPhone(p){p=String(p||'');return p.length===11?p.slice(0,4)+'•••'+p.slice(-4):p;}
function style(){if(document.getElementById('tehran-customer-memory-style'))return;var s=document.createElement('style');s.id='tehran-customer-memory-style';s.textContent='\
.tehran-memory{margin:10px 0;padding:11px;border:1px solid #d8e7dc;border-radius:14px;background:#f7fbf8}.tehran-memory-title{font:900 12px/1.8 system-ui;color:#193e29}.tehran-memory-note{font:10px/1.7 system-ui;color:#6e7d73;margin-top:3px}.tehran-memory-saved{margin-top:8px;padding:9px;border-radius:11px;background:#fff;border:1px solid #e1ebe4;font:11px/1.8 system-ui}.tehran-memory-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.tehran-memory-actions button{border:1px solid #c9ddce;background:#fff;color:#17472e;border-radius:10px;padding:8px 10px;font:800 11px system-ui}.tehran-memory-actions button.primary{background:#11823b;color:#fff;border-color:#11823b}.tehran-memory-check{display:flex!important;align-items:center!important;gap:8px!important;margin-top:9px!important;font:800 11px system-ui!important}.tehran-memory-check input{width:auto!important;min-width:18px!important;height:18px!important;margin:0!important}.tehran-memory-forget{color:#9b352d!important;border-color:#efd2ce!important}';document.head.appendChild(s);}
function setVal(el,v){if(!el)return;el.value=String(v==null?'':v);el.dispatchEvent(new Event('change',{bubbles:true}));}
function profileFrom(container){
  var old=read()||{};
  var isAi=container.classList.contains('tehran-ai-check');
  var name=isAi?(container.querySelector('[data-name]')||{}).value:(container.querySelector('[name="name"]')||{}).value;
  var phone=isAi?(container.querySelector('[data-phone]')||{}).value:(container.querySelector('[name="phone"]')||{}).value;
  var address=isAi?(container.querySelector('[data-address]')||{}).value:(container.querySelector('[name="address"]')||{}).value;
  var zone=isAi?container.querySelector('[data-zone]'):container.querySelector('[name="zone_id"]');
  var method=isAi?(container.dataset.fulfillmentMethod||'delivery'):((container.querySelector('[name="fulfillment_method"]')||{}).value||'delivery');
  if(method!=='pickup'&&method!=='delivery')method='delivery';
  var lat=isAi?(container.dataset.deliveryLat||''):(container.querySelector('[name="delivery_lat"]')||{}).value||'';
  var lng=isAi?(container.dataset.deliveryLng||''):(container.querySelector('[name="delivery_lng"]')||{}).value||'';
  var acc=isAi?(container.dataset.deliveryAccuracy||''):(container.querySelector('[name="delivery_accuracy"]')||{}).value||'';
  var p={name:String(name||'').trim(),phone:String(phone||'').trim(),address:old.address||'',zone_id:old.zone_id||0,lat:old.lat||'',lng:old.lng||'',accuracy:old.accuracy||'',fulfillment_method:method,updated_at:Date.now()};
  if(method==='delivery'){
    p.address=String(address||'').trim();p.zone_id=zone?Number(zone.value||0):0;p.lat=String(lat||'');p.lng=String(lng||'');p.accuracy=String(acc||'');
  }
  return p;
}
function rememberChecked(container){var c=container.querySelector('[data-memory-remember]');return !!(c&&c.checked);}
function saveNow(container){if(!rememberChecked(container))return;var p=profileFrom(container);if(p.name.length<2||!/^09\d{9}$/.test(p.phone))return;if(p.fulfillment_method==='delivery'&&p.address.length<5)return;write(p);}
function apply(container,keepAddress){
  var p=read();if(!p)return;var isAi=container.classList.contains('tehran-ai-check');
  var name=isAi?container.querySelector('[data-name]'):container.querySelector('[name="name"]');
  var phone=isAi?container.querySelector('[data-phone]'):container.querySelector('[name="phone"]');
  var address=isAi?container.querySelector('[data-address]'):container.querySelector('[name="address"]');
  setVal(name,p.name||'');setVal(phone,p.phone||'');
  if(keepAddress){
    setVal(address,p.address||'');
    var zone=isAi?container.querySelector('[data-zone]'):container.querySelector('[name="zone_id"]');if(zone&&p.zone_id!=null)setVal(zone,p.zone_id);
    if(isAi){container.dataset.deliveryLat=p.lat||'';container.dataset.deliveryLng=p.lng||'';container.dataset.deliveryAccuracy=p.accuracy||'';try{if(p.lat&&p.lng)sessionStorage.setItem('tehran_ai_delivery_location_v1',JSON.stringify({lat:p.lat,lng:p.lng,acc:p.accuracy||''}));}catch(e){}}
    else{
      setVal(container.querySelector('[name="delivery_lat"]'),p.lat||'');setVal(container.querySelector('[name="delivery_lng"]'),p.lng||'');setVal(container.querySelector('[name="delivery_accuracy"]'),p.accuracy||'');
      var picked=document.getElementById('locationPicked'),title=document.getElementById('locationMainTitle'),sub=document.getElementById('locationMainSub');if(p.lat&&p.lng){if(picked)picked.hidden=false;if(title)title.textContent='لوکیشن تحویل ثبت شد';if(sub)sub.textContent='آدرس ذخیره‌شده روی این دستگاه';}
      try{if(p.lat&&p.lng)sessionStorage.setItem('tehran_delivery_location_v1',JSON.stringify({lat:p.lat,lng:p.lng,acc:p.accuracy||''}));}catch(e){}
    }
  }else{
    setVal(address,'');
    if(isAi){container.dataset.deliveryLat='';container.dataset.deliveryLng='';container.dataset.deliveryAccuracy='';}
    else{setVal(container.querySelector('[name="delivery_lat"]'),'');setVal(container.querySelector('[name="delivery_lng"]'),'');setVal(container.querySelector('[name="delivery_accuracy"]'),'');}
  }
  if(isAi){var wanted=(keepAddress&&p.address)?'delivery':(p.fulfillment_method||'delivery');var b=container.querySelector('[data-ai-fulfillment="'+wanted+'"]');if(b)b.click();}
  var c=container.querySelector('[data-memory-remember]');if(c)c.checked=true;
}
function attach(container){
  if(!container||container.querySelector('.tehran-memory'))return;style();var p=read();var box=document.createElement('div');box.className='tehran-memory';
  var saved='';if(p&&p.name&&p.phone){saved='<div class="tehran-memory-saved"><b>'+esc(p.name)+'</b> · '+esc(maskPhone(p.phone))+(p.address?'<br>'+esc(p.address):'')+'</div><div class="tehran-memory-actions"><button type="button" class="primary" data-memory-use>✅ همین مشخصات و آدرس</button><button type="button" data-memory-other>📍 از آدرس دیگری استفاده می‌کنم</button><button type="button" class="tehran-memory-forget" data-memory-forget>فراموشم کن</button></div>';}
  box.innerHTML='<div class="tehran-memory-title">مشخصات این دستگاه</div>'+(p?'<div class="tehran-memory-note">قبلاً روی همین دستگاه خرید ثبت شده. می‌خوای از اطلاعات قبلی استفاده کنیم؟</div>'+saved:'<div class="tehran-memory-note">اگر بخوای، مشخصاتت فقط روی همین دستگاه ذخیره می‌شود تا دفعه بعد سریع‌تر سفارش بدهی.</div>')+'<label class="tehran-memory-check"><input type="checkbox" data-memory-remember '+(p?'checked':'')+'> مشخصاتم را روی این دستگاه به خاطر بسپار</label>';
  container.insertBefore(box,container.firstChild);
}
function start(){style();document.querySelectorAll('form.checkout,.tehran-ai-check').forEach(attach);new MutationObserver(function(){document.querySelectorAll('form.checkout,.tehran-ai-check').forEach(attach);}).observe(document.body,{childList:true,subtree:true});}
document.addEventListener('click',function(e){
  var b=e.target.closest&&e.target.closest('[data-memory-use]');if(b){var c=b.closest('form.checkout,.tehran-ai-check');if(c)apply(c,true);return;}
  b=e.target.closest&&e.target.closest('[data-memory-other]');if(b){var c2=b.closest('form.checkout,.tehran-ai-check');if(c2)apply(c2,false);return;}
  b=e.target.closest&&e.target.closest('[data-memory-forget]');if(b){forget();var c3=b.closest('form.checkout,.tehran-ai-check');if(c3){var m=c3.querySelector('.tehran-memory');if(m)m.remove();attach(c3);}return;}
  var fin=e.target.closest&&e.target.closest('[data-final]');if(fin){var ai=fin.closest('.tehran-ai-check');if(ai)saveNow(ai);}
},true);
document.addEventListener('submit',function(e){var f=e.target;if(f&&f.matches&&f.matches('form.checkout'))saveNow(f);},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
window.TehranCustomerMemory={read:read,forget:forget};
})();
