(function(){
'use strict';
var API='ai_assistant.php';
var bootData=null;
var storageKey='tehran_ai_delivery_location_v1';
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});}
function getBoot(){
  if(bootData&&bootData.csrf)return Promise.resolve(bootData);
  return fetch(API+'?action=boot',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(d){if(d&&d.ok)bootData=d;return d;});
}
function readSaved(){try{return JSON.parse(sessionStorage.getItem(storageKey)||'null');}catch(e){return null;}}
function saveLoc(d){try{sessionStorage.setItem(storageKey,JSON.stringify(d));}catch(e){}}
function style(){
  if(document.getElementById('tehran-ai-location-style'))return;
  var s=document.createElement('style');s.id='tehran-ai-location-style';s.textContent='\
.tehran-ai-location-box{margin-top:10px;padding:10px;border:1px solid #d7e5da;border-radius:13px;background:#f7fbf8}.tehran-ai-location-box b{display:block;font-size:11px;margin-bottom:6px}.tehran-ai-location-btn{width:100%;border:1px solid #bcd6c4;background:#fff;color:#12602f;border-radius:11px;padding:10px 12px;font:800 12px system-ui}.tehran-ai-location-status{margin-top:7px;font:11px/1.7 system-ui;color:#6b7a70}.tehran-ai-location-status.ok{color:#147239;font-weight:800}.tehran-ai-location-status.err{color:#b33a30}.tehran-ai-location-note{font:10px/1.7 system-ui;color:#7a877f;margin-top:5px}';document.head.appendChild(s);
}
function attach(box){
  if(!box||box.querySelector('.tehran-ai-location-box'))return;
  style();
  var saved=readSaved();
  var wrap=document.createElement('div');wrap.className='tehran-ai-location-box';
  wrap.innerHTML='<b>📍 لوکیشن تحویل</b><button type="button" class="tehran-ai-location-btn" data-ai-location>'+((saved&&saved.lat&&saved.lng)?'تغییر لوکیشن ثبت‌شده':'ثبت لوکیشن فعلی')+'</button><div class="tehran-ai-location-status '+((saved&&saved.lat&&saved.lng)?'ok':'')+'" data-ai-location-status>'+((saved&&saved.lat&&saved.lng)?'✅ لوکیشن تحویل ثبت شده است.':'برای ثبت سفارش، لوکیشن تحویل را هم ثبت کن.')+'</div><div class="tehran-ai-location-note">لوکیشن فقط برای تحویل همین سفارش استفاده می‌شود.</div>';
  var address=box.querySelector('[data-address]');
  if(address&&address.parentNode){address.insertAdjacentElement('afterend',wrap);}else box.appendChild(wrap);
  if(saved&&saved.lat&&saved.lng){box.dataset.deliveryLat=String(saved.lat);box.dataset.deliveryLng=String(saved.lng);box.dataset.deliveryAccuracy=String(saved.acc||'');}
}
function locate(box,btn){
  var st=box.querySelector('[data-ai-location-status]');
  if(!navigator.geolocation){st.className='tehran-ai-location-status err';st.textContent='این مرورگر امکان دریافت لوکیشن ندارد.';return;}
  btn.disabled=true;btn.textContent='در حال دریافت لوکیشن…';st.className='tehran-ai-location-status';st.textContent='اجازه Location را تأیید کن.';
  navigator.geolocation.getCurrentPosition(function(pos){
    var d={lat:Number(pos.coords.latitude).toFixed(7),lng:Number(pos.coords.longitude).toFixed(7),acc:Math.max(1,Math.round(Number(pos.coords.accuracy||0)))};
    box.dataset.deliveryLat=d.lat;box.dataset.deliveryLng=d.lng;box.dataset.deliveryAccuracy=String(d.acc);saveLoc(d);
    btn.disabled=false;btn.textContent='تغییر لوکیشن ثبت‌شده';st.className='tehran-ai-location-status ok';st.textContent='✅ لوکیشن تحویل ثبت شد'+(d.acc?' · دقت تقریبی '+d.acc.toLocaleString('fa-IR')+' متر':'')+'.';
  },function(){btn.disabled=false;btn.textContent='تلاش دوباره برای لوکیشن';st.className='tehran-ai-location-status err';st.textContent='لوکیشن دریافت نشد. دسترسی Location را فعال کن و دوباره بزن.';},{enableHighAccuracy:true,timeout:12000,maximumAge:5000});
}
function submitOrder(box){
  var name=box.querySelector('[data-name]')?box.querySelector('[data-name]').value.trim():'',phone=box.querySelector('[data-phone]')?box.querySelector('[data-phone]').value.trim():'',address=box.querySelector('[data-address]')?box.querySelector('[data-address]').value.trim():'';
  if(name.length<2||!/^09\d{9}$/.test(phone)||address.length<5){alert('نام، موبایل و آدرس را کامل وارد کن.');return;}
  var lat=box.dataset.deliveryLat||'',lng=box.dataset.deliveryLng||'',acc=box.dataset.deliveryAccuracy||'';
  if(!lat||!lng){var st=box.querySelector('[data-ai-location-status]');if(st){st.className='tehran-ai-location-status err';st.textContent='برای ثبت سفارش، اول لوکیشن تحویل را ثبت کن.';}var b=box.querySelector('[data-ai-location]');if(b)b.scrollIntoView({behavior:'smooth',block:'center'});return;}
  getBoot().then(function(d){
    if(!d||!d.ok||!d.csrf){alert('اتصال به فروشگاه برقرار نشد. دوباره امتحان کن.');return;}
    var f=document.createElement('form');f.method='post';f.action='index.php';f.style.display='none';
    function h(k,v){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=String(v==null?'':v);f.appendChild(i);}
    h('csrf',d.csrf);h('action','checkout');h('name',name);h('phone',phone);h('address',address);
    var z=box.querySelector('[data-zone]'),sl=box.querySelector('[data-slot]');h('zone_id',z?z.value:'0');h('slot',sl?sl.value:'اولین زمان ممکن');h('coupon_code','');
    h('delivery_lat',lat);h('delivery_lng',lng);h('delivery_accuracy',acc);document.body.appendChild(f);f.submit();
  }).catch(function(){alert('ثبت سفارش انجام نشد. دوباره امتحان کن.');});
}
var mo=new MutationObserver(function(){document.querySelectorAll('.tehran-ai-check').forEach(attach);});
function start(){style();document.querySelectorAll('.tehran-ai-check').forEach(attach);mo.observe(document.body,{childList:true,subtree:true});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-ai-location]');if(b){e.preventDefault();e.stopPropagation();var box=b.closest('.tehran-ai-check');if(box)locate(box,b);return;}var fin=e.target.closest&&e.target.closest('[data-final]');if(fin){var box2=fin.closest('.tehran-ai-check');if(!box2)return;e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();submitOrder(box2);}},true);
})();
