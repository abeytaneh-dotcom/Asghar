(()=>{
'use strict';

const qs=(s,r=document)=>r.querySelector(s);
const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
const params=new URLSearchParams(location.search);
const page=params.get('page')||'home';
const cityCenters={
  'تهران':[35.6892,51.3890,12],'مشهد':[36.2605,59.6168,12],'اصفهان':[32.6546,51.6680,12],
  'کرج':[35.8400,50.9391,12],'شیراز':[29.5918,52.5837,12],'تبریز':[38.0800,46.2919,12],
  'اهواز':[31.3183,48.6706,12],'قم':[34.6416,50.8746,12],'رشت':[37.2808,49.5832,12],
  'کرمانشاه':[34.3142,47.0650,12],'ارومیه':[37.5527,45.0761,12],'کرمان':[30.2839,57.0834,12],
  'یزد':[31.8974,54.3569,12],'بندرعباس':[27.1832,56.2666,12]
};
const OSM_TILES='https://tile.openstreetmap.org/{z}/{x}/{y}.png';

const style=document.createElement('style');
style.id='nz-live-map-style';
style.textContent=`
.nz-map-launch{position:fixed;right:16px;bottom:88px;z-index:78;display:inline-flex;align-items:center;gap:7px;height:44px;padding:0 14px;border-radius:999px;background:#b4232c;color:#fff!important;text-decoration:none;font:900 13px Tahoma,Arial,sans-serif;box-shadow:0 8px 24px rgba(38,31,31,.22)}
.nz-map-launch svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2}
.nz-live-map-root{position:fixed;inset:0 0 74px;z-index:90;background:#edf0f2;direction:rtl;font-family:Tahoma,"Vazirmatn",Arial,sans-serif;color:#20242a;overflow:hidden}
.nz-live-map-canvas{position:absolute;inset:0;z-index:1;background:#eef1f3}
.nz-live-map-canvas .leaflet-tile-pane{filter:grayscale(.92) saturate(.12) contrast(.72) brightness(1.14);opacity:.78}
.nz-live-map-canvas .leaflet-control-attribution{font-size:8px!important;opacity:.45;background:rgba(255,255,255,.82)!important}
.nz-map-top{position:absolute;z-index:800;top:max(10px,env(safe-area-inset-top));left:10px;right:10px;pointer-events:none}
.nz-map-searchrow{display:flex;align-items:center;gap:7px;pointer-events:auto}
.nz-map-back,.nz-map-locate{width:46px;min-width:46px;height:46px;border:1px solid rgba(29,36,45,.12);background:rgba(255,255,255,.96);border-radius:15px;box-shadow:0 4px 16px rgba(30,42,55,.10);color:#24282e;display:grid;place-items:center;cursor:pointer;font:900 21px Tahoma,Arial,sans-serif}
.nz-map-search{height:46px;flex:1;min-width:0;border:1px solid rgba(29,36,45,.12);background:rgba(255,255,255,.97);border-radius:15px;box-shadow:0 4px 16px rgba(30,42,55,.10);padding:0 14px;font:700 13px Tahoma,Arial,sans-serif;outline:none;color:#262b31}
.nz-map-search::placeholder{color:#8b929a}.nz-map-locate{font-size:20px}
.nz-map-pin-wrap{width:30px;height:38px;display:grid;place-items:end center}.nz-map-pin{position:relative;width:18px;height:18px;border-radius:50%;background:#b4232c;border:3px solid #fff;box-shadow:0 3px 9px rgba(45,32,32,.3);box-sizing:border-box;transition:.15s transform,.15s width,.15s height,.15s box-shadow}.nz-map-pin:after{content:'';position:absolute;left:50%;top:16px;width:2px;height:7px;background:#b4232c;transform:translateX(-50%);border-radius:2px}.nz-map-pin.selected{width:25px;height:25px;background:#fff;border:6px solid #b4232c;transform:translateY(-1px);box-shadow:0 5px 15px rgba(90,24,30,.36)}.nz-map-pin.selected:after{top:21px;height:8px}
.nz-map-cluster{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#fff;color:#a81f28;border:3px solid #b4232c;box-shadow:0 4px 12px rgba(54,40,40,.22);font:900 11px Tahoma,Arial,sans-serif;box-sizing:border-box}
.nz-map-preview-rail{position:absolute;z-index:830;left:0;right:0;bottom:92px;display:flex;justify-content:center;padding:0 18px;pointer-events:none;transform:translateY(14px);opacity:0;transition:.18s ease}.nz-map-preview-rail.show{transform:none;opacity:1}
.nz-map-preview-card{pointer-events:auto;width:min(88vw,500px);background:#fff;border:1px solid rgba(31,37,44,.08);border-radius:16px;box-shadow:0 14px 34px rgba(26,32,40,.22);overflow:hidden;color:#1f2328;text-decoration:none;touch-action:pan-y;user-select:none;display:grid;grid-template-columns:112px minmax(0,1fr);min-height:116px}.nz-map-preview-media{height:116px;background:#eef0f2;overflow:hidden;display:grid;place-items:center;color:#89919a;font-size:11px}.nz-map-preview-media img{width:100%;height:100%;object-fit:cover;display:block}.nz-map-preview-body{padding:11px 12px;min-width:0}.nz-map-preview-title{font-size:13px;line-height:1.8;font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nz-map-preview-price{font-size:12px;font-weight:900;margin-top:5px}.nz-map-preview-loc{margin-top:7px;font-size:10.5px;color:#717982;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nz-map-preview-mode{margin-top:5px;font-size:9.5px;color:#9a6a28}
.nz-map-sheet{position:absolute;z-index:820;left:0;right:0;bottom:0;height:72px;background:rgba(255,255,255,.98);border-radius:22px 22px 0 0;box-shadow:0 -8px 28px rgba(30,38,48,.12);display:flex;flex-direction:column;overflow:hidden}.nz-map-handle{width:46px;height:4px;border-radius:999px;background:#c2c6cb;margin:8px auto 3px;flex:none}.nz-map-sheet-head{padding:7px 16px 12px;font-weight:950;font-size:14px;display:flex;align-items:center;justify-content:center;gap:8px;flex:none}.nz-map-sheet-head small{font-size:9px;font-weight:700;color:#8a9098}.nz-map-loading{position:absolute;z-index:850;top:70px;left:50%;transform:translateX(-50%);padding:7px 12px;border-radius:999px;background:rgba(34,38,43,.82);color:#fff;font-size:10px;display:none}.nz-map-loading.show{display:block}.nz-map-empty-note{position:absolute;z-index:810;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.94);border:1px solid rgba(0,0,0,.06);border-radius:14px;padding:10px 14px;color:#777f87;font-size:11px;box-shadow:0 7px 20px rgba(0,0,0,.08);display:none}.nz-map-empty-note.show{display:block}
.nz-edit-map-modal{position:fixed;inset:0;z-index:1000;background:rgba(16,20,25,.55);display:none;align-items:flex-end;justify-content:center}.nz-edit-map-modal.show{display:flex}.nz-edit-map-card{width:min(100%,600px);height:min(82dvh,720px);background:#fff;border-radius:22px 22px 0 0;display:flex;flex-direction:column;overflow:hidden}.nz-edit-map-head{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eceef1}.nz-edit-map-head b{font-size:15px}.nz-edit-map-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f2f4;font-size:22px}.nz-edit-map-area{position:relative;flex:1;min-height:280px}.nz-edit-map-canvas{position:absolute;inset:0;background:#eef1f3}.nz-edit-map-canvas .leaflet-tile-pane{filter:grayscale(.92) saturate(.12) contrast(.72) brightness(1.14);opacity:.78}.nz-edit-map-pin{position:absolute;z-index:500;left:50%;top:50%;width:24px;height:24px;border-radius:50%;background:#b4232c;border:4px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.28);transform:translate(-50%,-50%);pointer-events:none}.nz-edit-map-pin:after{content:'';position:absolute;left:50%;top:22px;width:3px;height:10px;background:#b4232c;transform:translateX(-50%);border-radius:3px}.nz-edit-map-foot{padding:12px 14px calc(12px + env(safe-area-inset-bottom));border-top:1px solid #eceef1}.nz-edit-mode{display:flex;gap:8px;margin-bottom:10px}.nz-edit-mode label{flex:1;border:1px solid #e1e4e8;border-radius:12px;padding:9px;font-size:11px;line-height:1.7}.nz-edit-map-save{width:100%;height:44px;border:0;border-radius:12px;background:#b4232c;color:#fff;font:900 13px Tahoma,Arial,sans-serif}.nzpm-location-summary{margin:6px 0 10px;padding:10px 12px;background:#f7f8f9;border:1px solid #eceef1;border-radius:12px;font-size:11px;line-height:1.9;color:#656c74}.nzpm-location-btn{width:100%;margin-bottom:14px}
@media(min-width:761px){.nz-live-map-root{bottom:0}.nz-map-launch{bottom:24px}.nz-map-sheet{left:18px;right:auto;bottom:18px;width:340px;height:72px;border-radius:20px}.nz-map-preview-rail{left:380px;right:18px;bottom:28px;padding:0;align-items:flex-end}.nz-map-preview-card{width:min(500px,calc(100vw - 440px))}.nz-map-top{right:18px;left:auto;width:min(560px,calc(100vw - 36px))}.nz-edit-map-card{border-radius:22px;margin-bottom:3vh}}
body.nz-map-page{overflow:hidden!important}.nz-map-page .mobile-nav{z-index:950!important}
`;
document.head.appendChild(style);

let leafletPromise=null;
function loadLeaflet(){
  if(window.L)return Promise.resolve(window.L);
  if(leafletPromise)return leafletPromise;
  leafletPromise=new Promise((resolve,reject)=>{
    if(!qs('link[data-nz-leaflet]')){const link=document.createElement('link');link.rel='stylesheet';link.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';link.dataset.nzLeaflet='1';document.head.appendChild(link);}
    const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';s.async=true;s.onload=()=>resolve(window.L);s.onerror=()=>reject(new Error('map-load-failed'));document.head.appendChild(s);
  });
  return leafletPromise;
}
function addSimpleTiles(L,map){return L.tileLayer(OSM_TILES,{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(map);}
function mapUrl(extra={}){
  const u=new URL('/nz',location.origin);u.searchParams.set('page','map');
  ['city','district','category','q','lat','lon'].forEach(k=>{const v=params.get(k);if(v)u.searchParams.set(k,v);});
  Object.entries(extra).forEach(([k,v])=>{if(v!==null&&v!==undefined&&String(v)!=='')u.searchParams.set(k,String(v));});
  return u.pathname+'?'+u.searchParams.toString();
}
function addMapLaunch(){
  if(page==='map'||qs('.nz-map-launch'))return;
  const a=document.createElement('a');a.className='nz-map-launch';a.href=mapUrl();a.setAttribute('aria-label','نمایش آگهی‌ها روی نقشه');
  a.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-12A7 7 0 1 0 5 9c0 6.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg><span>نقشه</span>';
  document.body.appendChild(a);
}
function rewriteDetailMapLink(){
  const id=params.get('id');if(!id)return;
  qsa('a[href*="openstreetmap.org"]').forEach(a=>{a.href=mapUrl({focus:id});a.removeAttribute('target');a.removeAttribute('rel');});
}
function fmtPrice(n){const v=Number(n||0);return v>0?new Intl.NumberFormat('fa-IR').format(v)+' تومان':'توافقی';}
function currentCityCenter(){
  const c=params.get('city')||'';if(cityCenters[c])return cityCenters[c];
  const lat=Number(params.get('lat')),lon=Number(params.get('lon'));if(Number.isFinite(lat)&&Number.isFinite(lon)&&lat>=24&&lat<=40&&lon>=43&&lon<=64)return[lat,lon,13];
  return[32.4,53.7,6];
}

async function initLiveMap(){
  document.body.classList.add('nz-map-page');
  const root=document.createElement('section');root.className='nz-live-map-root';root.setAttribute('aria-label','نقشه آگهی‌های نیازستان');
  root.innerHTML=`<div id="nzLiveMap" class="nz-live-map-canvas"></div><div class="nz-map-top"><div class="nz-map-searchrow"><button type="button" class="nz-map-back" aria-label="بازگشت">←</button><input id="nzMapSearch" class="nz-map-search" type="search" placeholder="جستجو در آگهی‌ها"><button type="button" class="nz-map-locate" aria-label="موقعیت من">⌖</button></div></div><div id="nzMapLoading" class="nz-map-loading">در حال بروزرسانی…</div><div id="nzMapEmpty" class="nz-map-empty-note">در این محدوده آگهی پیدا نشد</div><div id="nzMapPreview" class="nz-map-preview-rail" aria-live="polite"></div><section class="nz-map-sheet"><div class="nz-map-handle"></div><div class="nz-map-sheet-head"><span id="nzMapCount">آگهی‌های روی نقشه</span><small id="nzMapLimit"></small></div></section>`;
  document.body.appendChild(root);
  qs('.nz-map-back',root).onclick=()=>history.length>1?history.back():location.assign('/nz');
  const search=qs('#nzMapSearch',root);search.value=params.get('q')||'';
  let L;try{L=await loadLeaflet();}catch(_){qs('#nzMapCount',root).textContent='بارگذاری نقشه انجام نشد';return;}
  const start=currentCityCenter();const map=L.map('nzLiveMap',{zoomControl:false,attributionControl:true,preferCanvas:true,minZoom:5,maxZoom:19}).setView([start[0],start[1]],start[2]);addSimpleTiles(L,map);
  const markerLayer=L.layerGroup().addTo(map);let items=[];let selected='';let timer=0;let aborter=null;let touchStartX=0;

  function endpoint(extra={}){
    const b=map.getBounds();const u=new URL('/nz-map-feed.php',location.origin);
    u.searchParams.set('minLat',b.getSouth().toFixed(6));u.searchParams.set('maxLat',b.getNorth().toFixed(6));u.searchParams.set('minLng',b.getWest().toFixed(6));u.searchParams.set('maxLng',b.getEast().toFixed(6));
    const c=params.get('city')||'';if(c)u.searchParams.set('city',c);const cat=params.get('category')||'';if(cat)u.searchParams.set('category',cat);if(search.value.trim())u.searchParams.set('q',search.value.trim());
    Object.entries(extra).forEach(([k,v])=>{if(v!==null&&v!==undefined&&String(v)!=='')u.searchParams.set(k,String(v));});return u;
  }
  function renderCount(total,limited){qs('#nzMapCount',root).textContent=new Intl.NumberFormat('fa-IR').format(total)+' آگهی روی نقشه';qs('#nzMapLimit',root).textContent=limited?'نمایش نزدیک‌ترین نتایج':'';qs('#nzMapEmpty',root).classList.toggle('show',total===0);}
  function selectedItem(){return items.find(p=>p.id===selected)||null;}
  function itemIndex(){return Math.max(0,items.findIndex(p=>p.id===selected));}
  function renderPreview(){
    const rail=qs('#nzMapPreview',root);rail.replaceChildren();const p=selectedItem();if(!p){rail.classList.remove('show');return;}
    const card=document.createElement('a');card.className='nz-map-preview-card';card.href='/nz?id='+encodeURIComponent(p.id);card.dataset.id=p.id;
    const media=document.createElement('div');media.className='nz-map-preview-media';const src=(Array.isArray(p.images)?p.images.find(Boolean):'')||p.image||'';if(src){const img=document.createElement('img');img.src=src;img.alt='';img.loading='eager';media.appendChild(img);}else media.textContent=p.category||'آگهی';
    const body=document.createElement('div');body.className='nz-map-preview-body';const title=document.createElement('div');title.className='nz-map-preview-title';title.textContent=p.title||'آگهی';const price=document.createElement('div');price.className='nz-map-preview-price';price.textContent=fmtPrice(p.price);const loc=document.createElement('div');loc.className='nz-map-preview-loc';loc.textContent=[p.city,p.district].filter(Boolean).join('، ')||'موقعیت روی نقشه';const mode=document.createElement('div');mode.className='nz-map-preview-mode';mode.textContent=p.location_mode==='exact'?'موقعیت ثبت‌شده آگهی':'موقعیت حدودی آگهی';body.append(title,price,loc,mode);card.append(media,body);
    card.addEventListener('touchstart',e=>{touchStartX=e.changedTouches[0]?.clientX||0;},{passive:true});card.addEventListener('touchend',e=>{const x=e.changedTouches[0]?.clientX||0;const dx=x-touchStartX;if(Math.abs(dx)>55){e.preventDefault();selectRelative(dx>0?-1:1);}}, {passive:false});rail.append(card);rail.classList.add('show');
  }
  function selectItem(p,{pan=false}={}){if(!p)return;selected=p.id;renderMarkers();renderPreview();if(pan)map.panTo([p.latitude,p.longitude],{animate:true,duration:.22});}
  function selectRelative(step){if(!items.length)return;const idx=itemIndex();let next=idx+step;if(next<0)next=items.length-1;if(next>=items.length)next=0;selectItem(items[next],{pan:true});}
  function clusterItems(){const z=map.getZoom();if(z>=15)return items.map(p=>[p]);const groups=new Map();const cell=z<=9?68:z<=11?56:z<=13?44:32;items.forEach(p=>{const pt=map.latLngToContainerPoint([p.latitude,p.longitude]);const key=Math.round(pt.x/cell)+'|'+Math.round(pt.y/cell);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(p);});return Array.from(groups.values());}
  function renderMarkers(){
    markerLayer.clearLayers();clusterItems().forEach(group=>{const lat=group.reduce((s,p)=>s+Number(p.latitude),0)/group.length;const lng=group.reduce((s,p)=>s+Number(p.longitude),0)/group.length;
      if(group.length>1){const html='<div class="nz-map-cluster">'+new Intl.NumberFormat('fa-IR').format(group.length)+'</div>';const icon=L.divIcon({className:'',html,iconSize:[38,38],iconAnchor:[19,19]});const m=L.marker([lat,lng],{icon,keyboard:true,title:group.length+' آگهی'}).addTo(markerLayer);m.on('click',()=>map.setView([lat,lng],Math.min(map.getZoom()+2,15),{animate:true}));return;}
      const p=group[0];const active=selected===p.id;const html='<div class="nz-map-pin-wrap"><div class="nz-map-pin '+(active?'selected':'')+'"></div></div>';const icon=L.divIcon({className:'',html,iconSize:[30,38],iconAnchor:[15,34]});const m=L.marker([lat,lng],{icon,keyboard:true,zIndexOffset:active?700:0,title:p.title||'آگهی'}).addTo(markerLayer);m.on('click',e=>{L.DomEvent.stopPropagation(e);selectItem(p);});
    });
  }
  async function loadViewport(){
    if(aborter)aborter.abort();aborter=new AbortController();qs('#nzMapLoading',root).classList.add('show');
    try{const r=await fetch(endpoint(),{credentials:'same-origin',signal:aborter.signal,headers:{Accept:'application/json'}});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'feed');items=Array.isArray(data.items)?data.items:[];if(selected&&!items.some(p=>p.id===selected)){selected='';renderPreview();}renderMarkers();renderCount(Number(data.total||items.length),!!data.limited);}catch(e){if(e.name!=='AbortError'){items=[];selected='';renderMarkers();renderPreview();renderCount(0,false);}}finally{qs('#nzMapLoading',root).classList.remove('show');}
  }
  function queueLoad(ms=220){clearTimeout(timer);timer=setTimeout(loadViewport,ms);}
  map.on('moveend zoomend',()=>queueLoad());map.on('click',()=>{if(selected){selected='';renderMarkers();renderPreview();}});search.addEventListener('input',()=>queueLoad(350));
  qs('.nz-map-locate',root).onclick=()=>{if(!navigator.geolocation)return;navigator.geolocation.getCurrentPosition(pos=>map.setView([pos.coords.latitude,pos.coords.longitude],15),()=>{}, {enableHighAccuracy:true,timeout:8000,maximumAge:30000});};
  const focus=params.get('focus');if(focus){try{const u=new URL('/nz-map-feed.php',location.origin);u.searchParams.set('id',focus);const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json'}});const d=await r.json();if(d&&d.focus){selected=d.focus.id;map.setView([d.focus.latitude,d.focus.longitude],16);}}catch(_){}}
  await loadViewport();if(selected){const p=selectedItem();if(p)selectItem(p);}setTimeout(()=>map.invalidateSize(),100);
}

function initEditLocation(){
  if(page!=='edit')return;const btn=qs('#nzpmEditLocation');if(!btn)return;
  const latInput=qs('input[name="latitude"]'),lngInput=qs('input[name="longitude"]'),modeInput=qs('input[name="location_mode"]'),cityInput=qs('input[name="city"]'),districtInput=qs('input[name="district"]'),streetInput=qs('input[name="street"]'),provinceInput=qs('input[name="province"]'),summary=qs('#nzpmLocationSummary');
  const modal=document.createElement('div');modal.className='nz-edit-map-modal';modal.innerHTML=`<div class="nz-edit-map-card"><div class="nz-edit-map-head"><b>ویرایش موقعیت آگهی</b><button type="button" class="nz-edit-map-close" aria-label="بستن">×</button></div><div class="nz-edit-map-area"><div id="nzEditMap" class="nz-edit-map-canvas"></div><div class="nz-edit-map-pin"></div></div><div class="nz-edit-map-foot"><div class="nz-edit-mode"><label><input type="radio" name="nzEditMode" value="exact"> موقعیت دقیق<br><small>نقطه انتخاب‌شده نمایش داده می‌شود.</small></label><label><input type="radio" name="nzEditMode" value="approx"> موقعیت حدودی<br><small>مختصات قبل از ذخیره گرد می‌شود.</small></label></div><button type="button" class="nz-edit-map-save">ثبت این موقعیت</button></div></div>`;document.body.appendChild(modal);
  let map=null;const close=()=>modal.classList.remove('show');qs('.nz-edit-map-close',modal).onclick=close;modal.addEventListener('click',e=>{if(e.target===modal)close();});
  btn.onclick=async()=>{modal.classList.add('show');const L=await loadLeaflet();const lat=Number(latInput?.value),lng=Number(lngInput?.value);const fallback=currentCityCenter();const center=Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=24&&lat<=40&&lng>=43&&lng<=64?[lat,lng,16]:fallback;qsa('input[name="nzEditMode"]',modal).forEach(r=>r.checked=r.value===((modeInput&&modeInput.value)||'approx'));if(!map){map=L.map('nzEditMap',{zoomControl:false,attributionControl:true,minZoom:5,maxZoom:19}).setView([center[0],center[1]],center[2]||15);addSimpleTiles(L,map);}else map.setView([center[0],center[1]],center[2]||15);setTimeout(()=>map.invalidateSize(),80);};
  qs('.nz-edit-map-save',modal).onclick=async()=>{if(!map||!latInput||!lngInput||!modeInput)return;let c=map.getCenter();const mode=(qs('input[name="nzEditMode"]:checked',modal)||{}).value||'approx';let lat=c.lat,lng=c.lng;if(mode==='approx'){lat=Math.round(lat*1000)/1000;lng=Math.round(lng*1000)/1000;}if(lat<24||lat>40||lng<43||lng>64)return;latInput.value=String(lat);lngInput.value=String(lng);modeInput.value=mode;try{const u=new URL('/nz-geocode.php',location.origin);u.searchParams.set('lat',String(lat));u.searchParams.set('lon',String(lng));const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json'}});const d=await r.json();const a=d.address||{};const city=a.city||a.town||a.village||a.county||'';const district=a.suburb||a.neighbourhood||a.quarter||a.city_district||'';const street=a.road||a.pedestrian||a.residential||'';if(cityInput)cityInput.value=city;if(districtInput)districtInput.value=district;if(streetInput)streetInput.value=street;if(provinceInput)provinceInput.value=a.state||'';}catch(_){}if(summary)summary.textContent='موقعیت جدید انتخاب شد · '+(mode==='exact'?'دقیق':'حدودی')+' · مختصات پس از ذخیره همراه آگهی ثبت می‌شود.';close();};
}

function boot(){rewriteDetailMapLink();if(page==='map')initLiveMap();else addMapLaunch();initEditLocation();}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
