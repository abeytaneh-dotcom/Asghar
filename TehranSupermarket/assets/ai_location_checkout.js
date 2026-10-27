(function(){
'use strict';
var API='ai_assistant.php';
var bootData=null;
var storageKey='tehran_ai_delivery_location_v1';
var fulfillmentKey='tehran_ai_fulfillment_v1';
var deliveryMaps=new Map();
function getBoot(){
  if(bootData&&bootData.csrf)return Promise.resolve(bootData);
  return fetch(API+'?action=boot',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(d){if(d&&d.ok)bootData=d;return d;});
}
function normalizeLocation(d){
  if(!d||d.lat==null||d.lng==null||d.lat===''||d.lng==='')return null;
  var lat=Number(d.lat),lng=Number(d.lng);
  if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat < -90||lat > 90||lng < -180||lng > 180)return null;
  return {lat:lat.toFixed(7),lng:lng.toFixed(7),acc:d.acc&&Number.isFinite(Number(d.acc))?Math.max(1,Math.round(Number(d.acc))):''};
}
function readSaved(){try{return normalizeLocation(JSON.parse(sessionStorage.getItem(storageKey)||'null'));}catch(e){return null;}}
function saveLoc(d){try{sessionStorage.setItem(storageKey,JSON.stringify(d));}catch(e){}}
function readFulfillment(){try{return sessionStorage.getItem(fulfillmentKey)||'';}catch(e){return '';}}
function saveFulfillment(v){try{sessionStorage.setItem(fulfillmentKey,v);}catch(e){}}
function style(){
  if(document.getElementById('tehran-ai-location-style'))return;
  var s=document.createElement('style');s.id='tehran-ai-location-style';s.textContent='\
.tehran-ai-fulfillment{margin:10px 0 4px;padding:11px;border:1px solid #d7e5da;border-radius:14px;background:#f7fbf8}.tehran-ai-fulfillment>b{display:block;font-size:12px;margin-bottom:4px}.tehran-ai-fulfillment>small{display:block;color:#6f7e74;font-size:10px;margin-bottom:9px}.tehran-ai-fulfillment-actions{display:grid;grid-template-columns:1fr 1fr;gap:7px}.tehran-ai-fulfillment-actions button{border:1px solid #c9ddce;background:#fff;color:#17472e;border-radius:11px;padding:10px 8px;font:800 11px system-ui}.tehran-ai-fulfillment-actions button.on{background:#11823b;color:#fff;border-color:#11823b}.tehran-ai-location-box{margin-top:10px;padding:10px;border:1px solid #d7e5da;border-radius:13px;background:#f7fbf8}.tehran-ai-location-box b{display:block;font-size:11px;margin-bottom:6px}.tehran-ai-location-btn{width:100%;border:1px solid #bcd6c4;background:#fff;color:#12602f;border-radius:11px;padding:10px 12px;font:800 12px system-ui}.tehran-ai-location-status{margin-top:7px;font:11px/1.7 system-ui;color:#6b7a70}.tehran-ai-location-status.ok{color:#147239;font-weight:800}.tehran-ai-location-status.err{color:#b33a30}.tehran-ai-location-note{font:10px/1.7 system-ui;color:#7a877f;margin-top:5px}.tehran-ai-hidden-field{display:none!important}';document.head.appendChild(s);
  var ms=document.createElement('style');ms.textContent=`
.tehran-ai-map-wrap{position:relative;margin:10px 0;border-radius:16px;overflow:hidden;border:1px solid #dce3ea;background:#e9eef3}
.tehran-ai-delivery-map{height:270px;width:100%;min-width:0;direction:ltr;z-index:0}
.tehran-ai-map-pin{position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);width:26px;height:36px;z-index:600;pointer-events:none;filter:drop-shadow(0 3px 3px #0004)}
.tehran-ai-map-pin:before{content:'';position:absolute;width:24px;height:24px;top:0;left:1px;border-radius:50% 50% 50% 0;background:#d49c22;border:2px solid #fff;transform:rotate(-45deg)}
.tehran-ai-map-pin:after{content:'';position:absolute;width:8px;height:8px;top:8px;left:10px;border-radius:50%;background:#fff}
.tehran-ai-map-help{font-size:11px;line-height:1.9;color:#526579;margin:7px 0}
.tehran-ai-map-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
.tehran-ai-map-actions button{min-height:43px;font:700 11px/1.6 system-ui;border:1px solid #d4dce7;border-radius:12px;padding:8px;color:#25384f;background:#fff;cursor:pointer}
.tehran-ai-map-actions [data-ai-location-confirm]{background:#20354d;color:#fff;border-color:#20354d}
.tehran-ai-map-actions button:disabled{opacity:.55;cursor:wait}
.tehran-ai-map-note{font-size:10px;line-height:1.8;color:#925a20;background:#fff5df;padding:7px;border-radius:10px}
.tehran-ai-map-note[hidden]{display:none}
.tehran-ai-delivery-map .leaflet-control-attribution{font-size:8px}
.tehran-ai-delivery-map .leaflet-control-zoom a{font:700 20px/30px system-ui}
.tehran-ai-location-box{background:#f7f9fc!important;border-color:#e0e6ee!important}
@media(max-width:420px){.tehran-ai-delivery-map{height:250px}.tehran-ai-map-actions{grid-template-columns:1fr}}
`;document.head.appendChild(ms);
}
function setFieldVisible(el,on){if(!el)return;el.classList.toggle('tehran-ai-hidden-field',!on);var lab=el.previousElementSibling;if(lab&&lab.tagName==='LABEL')lab.classList.toggle('tehran-ai-hidden-field',!on);}
function applyMode(box,mode){
  mode=(mode==='pickup'||mode==='delivery')?mode:'';box.dataset.fulfillmentMethod=mode;
  var buttons=box.querySelectorAll('[data-ai-fulfillment]');buttons.forEach(function(b){b.classList.toggle('on',b.getAttribute('data-ai-fulfillment')===mode);});
  var isDelivery=mode==='delivery';
  setFieldVisible(box.querySelector('[data-address]'),isDelivery);
  setFieldVisible(box.querySelector('[data-zone]'),isDelivery);
  setFieldVisible(box.querySelector('[data-slot]'),isDelivery);
  var loc=box.querySelector('.tehran-ai-location-box');if(loc)loc.classList.toggle('tehran-ai-hidden-field',!isDelivery);
  var fin=box.querySelector('[data-final]');if(fin){fin.disabled=!mode;fin.style.opacity=mode?'1':'.5';fin.textContent=mode==='pickup'?'ثبت سفارش برای تحویل حضوری':(mode==='delivery'?'ثبت سفارش و ارسال':'اول روش دریافت را انتخاب کن');}
  saveFulfillment(mode);
  if(isDelivery)setTimeout(function(){ensureDeliveryMap(box);var state=deliveryMaps.get(box);if(state)state.map.invalidateSize();},0);
}
function attach(box){
  if(!box||box.querySelector('.tehran-ai-fulfillment'))return;
  style();
  var title=box.querySelector('b');if(title)title.textContent='تکمیل سفارش';
  var chooser=document.createElement('div');chooser.className='tehran-ai-fulfillment';
  chooser.innerHTML='<b>آخر کار چطور سفارشت رو تحویل بگیری؟</b><small>خودت میای می‌بری یا برات بفرستیم؟</small><div class="tehran-ai-fulfillment-actions"><button type="button" data-ai-fulfillment="pickup">🏪 خودم میام می‌برم</button><button type="button" data-ai-fulfillment="delivery">🛵 برام بفرستید</button></div>';
  if(title&&title.parentNode)title.insertAdjacentElement('afterend',chooser);else box.insertBefore(chooser,box.firstChild);
  var saved=readSaved();
  var wrap=document.createElement('div');wrap.className='tehran-ai-location-box';
  wrap.innerHTML='<b>📍 مقصد ارسال را روی نقشه انتخاب کن</b><p class="tehran-ai-map-help">نقشه را جابه‌جا کن یا روی مقصد بزن؛ نشانگر باید روی محل تحویل باشد. می‌توانی آدرسی غیر از موقعیت فعلی‌ات انتخاب کنی.</p><div class="tehran-ai-map-wrap"><div class="tehran-ai-delivery-map" data-ai-delivery-map tabindex="0" aria-label="نقشه انتخاب مقصد ارسال"></div><span class="tehran-ai-map-pin" aria-hidden="true"></span></div><div class="tehran-ai-map-note" data-ai-map-note hidden></div><div class="tehran-ai-map-actions"><button type="button" data-ai-location>◎ موقعیت من</button><button type="button" data-ai-location-confirm>تأیید این مقصد</button></div><div class="tehran-ai-location-status '+(saved?'ok':'')+'" data-ai-location-status>'+(saved?'✅ مقصد ثبت‌شده روی نقشه نمایش داده می‌شود.':'مقصد را روی نقشه مشخص کن و «تأیید این مقصد» را بزن.')+'</div><div class="tehran-ai-location-note">موقعیت دستگاه فقط با زدن «موقعیت من» درخواست می‌شود.</div>';
  var address=box.querySelector('[data-address]');
  if(address&&address.parentNode){address.insertAdjacentElement('afterend',wrap);}else box.appendChild(wrap);
  if(saved){box.dataset.deliveryLat=String(saved.lat);box.dataset.deliveryLng=String(saved.lng);box.dataset.deliveryAccuracy=String(saved.acc||'');}
  applyMode(box,readFulfillment());
}
function locationStatus(box,message,error){
  var st=box.querySelector('[data-ai-location-status]');
  if(st){st.className='tehran-ai-location-status'+(error?' err':'');st.textContent=message;}
}
function ensureDeliveryMap(box){
  var existing=deliveryMaps.get(box);if(existing)return existing;
  var el=box.querySelector('[data-ai-delivery-map]');if(!el)return null;
  if(!window.L){locationStatus(box,'نقشه هنوز بارگذاری نشده؛ یک‌بار صفحه را تازه کن.',true);return null;}
  var saved=readSaved(),center=saved?[Number(saved.lat),Number(saved.lng)]:[35.6892,51.3890];
  var map=L.map(el,{zoomControl:true,scrollWheelZoom:false,attributionControl:true}).setView(center,saved?16:12);
  var state={map:map,revision:0,accuracy:'',applyingGps:false,disposed:false,center:center};
  deliveryMaps.set(box,state);
  var layer=L.tileLayer('map_tile.php?z={z}&x={x}&y={y}',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'});
  var note=box.querySelector('[data-ai-map-note]');
  layer.on('tileerror',function(){if(note){note.hidden=false;note.textContent='بخشی از نقشه بارگذاری نشد. اتصال اینترنت را بررسی کن یا کمی نقشه را جابه‌جا کن.';}});
  layer.on('tileload',function(){if(note)note.hidden=true;});
  layer.addTo(map);
  function moved(){
    var p=map.getCenter();el.dataset.centerLat=p.lat.toFixed(7);el.dataset.centerLng=p.lng.toFixed(7);
    if(Math.abs(p.lat-state.center[0])<0.0000001&&Math.abs(p.lng-state.center[1])<0.0000001)return;
    state.center=[p.lat,p.lng];state.revision++;
    if(!state.applyingGps)state.accuracy='';
    box.dataset.locationPending='1';
    locationStatus(box,'مقصد تغییر کرد؛ برای ثبت، «تأیید این مقصد» را بزن.',false);
  }
  map.on('moveend',moved);
  map.on('click',function(e){map.setView(e.latlng,map.getZoom(),{animate:false});});
  el.dataset.centerLat=Number(center[0]).toFixed(7);el.dataset.centerLng=Number(center[1]).toFixed(7);
  setTimeout(function(){if(!state.disposed)map.invalidateSize();},80);
  return state;
}
function confirmLocation(box){
  var state=ensureDeliveryMap(box);if(!state)return;
  var p=state.map.getCenter(),d=normalizeLocation({lat:p.lat,lng:p.lng,acc:state.accuracy});
  if(!d){locationStatus(box,'مقصد معتبر نیست؛ دوباره روی نقشه انتخاب کن.',true);return;}
  box.dataset.deliveryLat=d.lat;box.dataset.deliveryLng=d.lng;box.dataset.deliveryAccuracy=String(d.acc);
  box.dataset.locationPending='0';saveLoc(d);
  var st=box.querySelector('[data-ai-location-status]');
  st.className='tehran-ai-location-status ok';st.textContent='✅ مقصد ارسال ثبت شد. برای ارسال به جای دیگر، نقشه را جابه‌جا و دوباره تأیید کن.';
}
function locate(box,btn){
  if(btn.disabled)return;
  var state=ensureDeliveryMap(box);
  if(!navigator.geolocation){locationStatus(box,'این مرورگر دریافت موقعیت را پشتیبانی نمی‌کند؛ مقصد را روی نقشه انتخاب کن.',true);return;}
  if(!window.isSecureContext){locationStatus(box,'دریافت موقعیت به اتصال امن نیاز دارد؛ سایت را با HTTPS باز کن یا مقصد را دستی انتخاب کن.',true);return;}
  var revision=state?state.revision:0;
  btn.disabled=true;btn.textContent='در حال دریافت موقعیت…';
  locationStatus(box,'اگر مرورگر اجازه خواست، دسترسی موقعیت را تأیید کن. انتخاب دستی نقشه هم فعال است.',false);
  function finish(){btn.disabled=false;btn.textContent='◎ موقعیت من';}
  function success(pos){
    finish();if(!box.isConnected||!state||state.disposed)return;
    if(state.revision!==revision){locationStatus(box,'مقصدی که روی نقشه انتخاب کردی حفظ شد؛ برای ثبت آن تأیید کن.',false);return;}
    var d=normalizeLocation({lat:pos.coords.latitude,lng:pos.coords.longitude,acc:pos.coords.accuracy});
    if(!d){locationStatus(box,'موقعیت معتبر دریافت نشد؛ مقصد را روی نقشه انتخاب کن.',true);return;}
    state.applyingGps=true;state.accuracy=d.acc;
    state.map.setView([Number(d.lat),Number(d.lng)],Number(d.acc)<=150?17:Number(d.acc)<=1000?15:13,{animate:false});
    state.applyingGps=false;box.dataset.locationPending='1';
    locationStatus(box,(Number(d.acc)>300?'موقعیت تقریبی دستگاه پیدا شد؛ محل دقیق را روی نقشه تنظیم کن.':'موقعیت دستگاه پیدا شد.')+' برای ثبت مقصد «تأیید این مقصد» را بزن.',false);
  }
  function failure(err,retried){
    if(!retried&&err.code!==1){
      locationStatus(box,'دریافت دقیق طول کشید؛ در حال تلاش با روش موقعیت‌یابی معمولی…',false);
      navigator.geolocation.getCurrentPosition(success,function(e){failure(e,true);},{enableHighAccuracy:false,timeout:15000,maximumAge:120000});return;
    }
    finish();
    var message=err.code===1?'اجازهٔ موقعیت برای این سایت داده نشده؛ از تنظیمات مرورگر اجازه بده یا مقصد را روی نقشه انتخاب کن.':err.code===2?'موقعیت دستگاه در دسترس نیست؛ Location گوشی و اتصال اینترنت را بررسی کن یا مقصد را روی نقشه انتخاب کن.':'دریافت موقعیت زمان‌بر شد؛ می‌توانی دوباره تلاش کنی یا مقصد را دستی روی نقشه انتخاب کنی.';
    locationStatus(box,message,true);
  }
  navigator.geolocation.getCurrentPosition(success,function(err){failure(err,false);},{enableHighAccuracy:true,timeout:10000,maximumAge:15000});
}
function submitOrder(box){
  var method=box.dataset.fulfillmentMethod||'';
  if(method!=='pickup'&&method!=='delivery'){alert('اول مشخص کن خودت میای می‌بری یا سفارش برات ارسال بشه.');return;}
  var name=box.querySelector('[data-name]')?box.querySelector('[data-name]').value.trim():'',phone=box.querySelector('[data-phone]')?box.querySelector('[data-phone]').value.trim():'',address=box.querySelector('[data-address]')?box.querySelector('[data-address]').value.trim():'';
  if(name.length<2||!/^09\d{9}$/.test(phone)){alert('نام و شماره موبایل را کامل وارد کن.');return;}
  var lat='',lng='',acc='';
  if(method==='delivery'){
    if(address.length<5){alert('آدرس تحویل را کامل وارد کن.');return;}
    lat=box.dataset.deliveryLat||'';lng=box.dataset.deliveryLng||'';acc=box.dataset.deliveryAccuracy||'';
    if(!lat||!lng||box.dataset.locationPending==='1'){var st=box.querySelector('[data-ai-location-status]');if(st){st.className='tehran-ai-location-status err';st.textContent='برای ارسال سفارش، مقصد روی نقشه را تأیید کن.';}var b=box.querySelector('[data-ai-location]');if(b)b.scrollIntoView({behavior:'smooth',block:'center'});return;}
  }else{
    address='تحویل حضوری از فروشگاه';
  }
  getBoot().then(function(d){
    if(!d||!d.ok||!d.csrf){alert('اتصال به فروشگاه برقرار نشد. دوباره امتحان کن.');return;}
    var f=document.createElement('form');f.method='post';f.action='index.php';f.style.display='none';
    function h(k,v){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=String(v==null?'':v);f.appendChild(i);}
    h('csrf',d.csrf);h('action','checkout');h('name',name);h('phone',phone);h('address',address);h('fulfillment_method',method);
    var z=box.querySelector('[data-zone]'),sl=box.querySelector('[data-slot]');h('zone_id',method==='delivery'&&z?z.value:'0');h('slot',method==='delivery'&&sl?sl.value:'تحویل حضوری');h('coupon_code','');
    h('delivery_lat',lat);h('delivery_lng',lng);h('delivery_accuracy',acc);document.body.appendChild(f);f.submit();
  }).catch(function(){alert('ثبت سفارش انجام نشد. دوباره امتحان کن.');});
}
var mo=new MutationObserver(function(){
  document.querySelectorAll('.tehran-ai-check').forEach(attach);
  deliveryMaps.forEach(function(state,box){if(!box.isConnected){deliveryMaps.delete(box);state.disposed=true;state.map.remove();}});
});
function start(){style();document.querySelectorAll('.tehran-ai-check').forEach(attach);mo.observe(document.body,{childList:true,subtree:true});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
document.addEventListener('click',function(e){
  var fm=e.target.closest&&e.target.closest('[data-ai-fulfillment]');if(fm){e.preventDefault();e.stopPropagation();var bx=fm.closest('.tehran-ai-check');if(bx)applyMode(bx,fm.getAttribute('data-ai-fulfillment'));return;}
  var confirm=e.target.closest&&e.target.closest('[data-ai-location-confirm]');if(confirm){e.preventDefault();e.stopPropagation();var cb=confirm.closest('.tehran-ai-check');if(cb)confirmLocation(cb);return;}
  var b=e.target.closest&&e.target.closest('[data-ai-location]');if(b){e.preventDefault();e.stopPropagation();var box=b.closest('.tehran-ai-check');if(box)locate(box,b);return;}
  var fin=e.target.closest&&e.target.closest('[data-final]');if(fin){var box2=fin.closest('.tehran-ai-check');if(!box2)return;e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();submitOrder(box2);}
},true);
})();
