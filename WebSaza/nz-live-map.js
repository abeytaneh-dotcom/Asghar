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

const style=document.createElement('style');
style.id='nz-live-map-style';
style.textContent=`
.nz-map-launch{position:fixed;right:16px;bottom:88px;z-index:78;display:inline-flex;align-items:center;gap:7px;height:44px;padding:0 14px;border-radius:999px;background:#b4232c;color:#fff!important;text-decoration:none;font:900 13px Tahoma,Arial,sans-serif;box-shadow:0 8px 24px rgba(38,31,31,.25)}
.nz-map-launch svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2}
.nz-live-map-root{position:fixed;inset:0 0 74px;z-index:90;background:#eef1f4;direction:rtl;font-family:Tahoma,"Vazirmatn",Arial,sans-serif;color:#20242a;overflow:hidden}
.nz-live-map-canvas{position:absolute;inset:0;z-index:1;background:#dce2e7}
.nz-map-top{position:absolute;z-index:800;top:max(10px,env(safe-area-inset-top));left:10px;right:10px;display:flex;flex-direction:column;gap:7px;pointer-events:none}
.nz-map-searchrow,.nz-map-chiprow{display:flex;align-items:center;gap:7px;pointer-events:auto}
.nz-map-back,.nz-map-locate,.nz-map-chip,.nz-map-filter-select{height:44px;border:1px solid rgba(29,36,45,.16);background:rgba(255,255,255,.96);border-radius:13px;box-shadow:0 4px 18px rgba(30,42,55,.12);color:#24282e;font:800 12px Tahoma,Arial,sans-serif}
.nz-map-back{width:44px;min-width:44px;font-size:22px;display:grid;place-items:center;cursor:pointer}
.nz-map-search{height:44px;flex:1;min-width:0;border:1px solid rgba(29,36,45,.16);background:rgba(255,255,255,.97);border-radius:13px;box-shadow:0 4px 18px rgba(30,42,55,.12);padding:0 13px;font:700 13px Tahoma,Arial,sans-serif;outline:none}
.nz-map-locate{width:44px;min-width:44px;display:grid;place-items:center;cursor:pointer;font-size:19px}
.nz-map-chip,.nz-map-filter-select{padding:0 12px;max-width:42vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nz-map-filter-select{appearance:auto;max-width:165px}
.nz-map-live{height:34px;display:inline-flex;align-items:center;gap:6px;padding:0 10px;border-radius:999px;background:rgba(255,255,255,.94);box-shadow:0 4px 18px rgba(30,42,55,.10);font-size:11px;font-weight:800;color:#676d75;margin-inline-start:auto}
.nz-map-live i{width:8px;height:8px;border-radius:50%;background:#1ca95b;box-shadow:0 0 0 4px rgba(28,169,91,.14)}
.nz-map-marker{position:relative;min-width:58px;height:34px;padding:0 10px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#f4515b;color:#17191c;border:2px solid rgba(180,35,44,.12);box-shadow:0 4px 12px rgba(98,22,27,.24);font:900 11.5px Tahoma,Arial,sans-serif;white-space:nowrap;transition:.16s transform,.16s background,.16s color,.16s box-shadow;box-sizing:border-box}
.nz-map-marker::after{content:'';position:absolute;left:50%;bottom:-7px;transform:translateX(-50%);border-left:7px solid transparent;border-right:7px solid transparent;border-top:8px solid #f4515b;transition:border-top-color .16s}
.nz-map-marker.selected{transform:scale(1.06);background:#fff;color:#17191c;border-color:#e3e6ea;box-shadow:0 6px 18px rgba(20,24,29,.22);z-index:3}.nz-map-marker.selected::after{border-top-color:#fff}
.nz-map-cluster{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#ef535c;color:#17191c;border:7px solid rgba(239,83,92,.28);background-clip:padding-box;box-shadow:0 5px 14px rgba(129,26,32,.26);font:900 12px Tahoma,Arial,sans-serif}
.nz-map-preview-rail{position:absolute;z-index:830;left:0;right:0;bottom:92px;display:flex;justify-content:center;padding:0 38px;pointer-events:none;transform:translateY(14px);opacity:0;transition:.18s ease}.nz-map-preview-rail.show{transform:none;opacity:1}
.nz-map-preview-card{pointer-events:auto;width:min(82vw,520px);max-height:320px;background:#fff;border:1px solid rgba(31,37,44,.10);border-radius:15px;box-shadow:0 14px 36px rgba(26,32,40,.26);overflow:hidden;color:#1f2328;text-decoration:none;touch-action:pan-y;user-select:none}
.nz-map-preview-media{display:flex;gap:8px;padding:10px 10px 0;height:138px}.nz-map-preview-media>div{flex:1;min-width:0;border-radius:11px;overflow:hidden;background:#e9ecef;display:grid;place-items:center;color:#89919a;font-size:11px}.nz-map-preview-media img{width:100%;height:100%;object-fit:cover;display:block}
.nz-map-preview-body{padding:10px 13px 13px}.nz-map-preview-title{font-size:14px;line-height:1.85;font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nz-map-preview-price{font-size:13px;font-weight:900;margin-top:7px}.nz-map-preview-meta{display:flex;align-items:center;gap:6px;margin-top:8px;overflow:hidden}.nz-map-preview-meta span{height:26px;padding:0 8px;border-radius:7px;background:#f2f3f5;display:inline-flex;align-items:center;font-size:10px;color:#646b74;white-space:nowrap}.nz-map-preview-loc{margin-top:8px;font-size:11px;color:#6f7680;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nz-map-preview-side{position:absolute;top:50%;transform:translateY(-50%);width:20px;height:128px;background:rgba(255,255,255,.88);border-radius:10px;box-shadow:0 8px 20px rgba(24,30,38,.15);opacity:.72}.nz-map-preview-side.prev{right:8px}.nz-map-preview-side.next{left:8px}
.nz-map-sheet{position:absolute;z-index:820;left:0;right:0;bottom:0;height:78px;background:#fff;border-radius:22px 22px 0 0;box-shadow:0 -10px 32px rgba(30,38,48,.16);display:flex;flex-direction:column;overflow:hidden}.nz-map-handle{width:52px;height:5px;border-radius:999px;background:#b9bdc3;margin:9px auto 4px;flex:none}.nz-map-sheet-head{padding:7px 16px 12px;font-weight:950;font-size:15px;display:flex;align-items:center;justify-content:center;gap:8px;flex:none}.nz-map-sheet-head small{font-size:10px;font-weight:700;color:#8a9098}.nz-map-empty{padding:26px 10px;text-align:center;color:#7d848c;font-size:12px;line-height:2}.nz-map-loading{position:absolute;z-index:850;top:116px;left:50%;transform:translateX(-50%);padding:7px 12px;border-radius:999px;background:rgba(34,38,43,.85);color:#fff;font-size:10px;display:none}.nz-map-loading.show{display:block}
.nz-edit-map-modal{position:fixed;inset:0;z-index:1000;background:rgba(16,20,25,.55);display:none;align-items:flex-end;justify-content:center}.nz-edit-map-modal.show{display:flex}.nz-edit-map-card{width:min(100%,600px);height:min(82dvh,720px);background:#fff;border-radius:22px 22px 0 0;display:flex;flex-direction:column;overflow:hidden}.nz-edit-map-head{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eceef1}.nz-edit-map-head b{font-size:15px}.nz-edit-map-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f2f4;font-size:22px}.nz-edit-map-area{position:relative;flex:1;min-height:280px}.nz-edit-map-canvas{position:absolute;inset:0}.nz-edit-map-pin{position:absolute;z-index:500;left:50%;top:50%;transform:translate(-50%,-100%);font-size:38px;filter:drop-shadow(0 3px 4px rgba(0,0,0,.25));pointer-events:none}.nz-edit-map-foot{padding:12px 14px calc(12px + env(safe-area-inset-bottom));border-top:1px solid #eceef1}.nz-edit-mode{display:flex;gap:8px;margin-bottom:10px}.nz-edit-mode label{flex:1;border:1px solid #e1e4e8;border-radius:12px;padding:9px;font-size:11px;line-height:1.7}.nz-edit-map-save{width:100%;height:44px;border:0;border-radius:12px;background:#b4232c;color:#fff;font:900 13px Tahoma,Arial,sans-serif}.nzpm-location-summary{margin:6px 0 10px;padding:10px 12px;background:#f7f8f9;border:1px solid #eceef1;border-radius:12px;font-size:11px;line-height:1.9;color:#656c74}.nzpm-location-btn{width:100%;margin-bottom:14px}
@media(min-width:761px){.nz-live-map-root{bottom:0}.nz-map-launch{bottom:24px}.nz-map-sheet{left:18px;right:auto;bottom:18px;width:390px;height:78px;border-radius:20px}.nz-map-preview-rail{left:428px;right:18px;bottom:30px;padding:0;align-items:flex-end}.nz-map-preview-card{width:min(520px,calc(100vw - 500px))}.nz-map-preview-side{display:none}.nz-map-top{right:18px;left:auto;width:min(640px,calc(100vw - 36px))}.nz-edit-map-card{border-radius:22px;margin-bottom:3vh}}
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
function fmtMarkerPrice(n){
  const v=Number(n||0);if(!Number.isFinite(v)||v<=0)return'توافقی';
  if(v>=1e9){const x=Math.round(v/1e8)/10;return new Intl.NumberFormat('fa-IR',{maximumFractionDigits:1}).format(x)+' میلیارد';}
  if(v>=1e6){const x=Math.round(v/1e5)/10;return new Intl.NumberFormat('fa-IR',{maximumFractionDigits:1}).format(x)+' م';}
  if(v>=1e3){const x=Math.round(v/1e3);return new Intl.NumberFormat('fa-IR').format(x)+' ه';}
  return new Intl.NumberFormat('fa-IR').format(v);
}
function currentCityCenter(){
  const c=params.get('city')||'';if(cityCenters[c])return cityCenters[c];
  const lat=Number(params.get('lat')),lon=Number(params.get('lon'));if(Number.isFinite(lat)&&Number.isFinite(lon)&&lat>=24&&lat<=40&&lon>=43&&lon<=64)return[lat,lon,13];
  return[32.4,53.7,6];
}

async function initLiveMap(){
  document.body.classList.add('nz-map-page');
  const root=document.createElement('section');root.className='nz-live-map-root';root.setAttribute('aria-label','نقشه زنده آگهی‌ها');
  root.innerHTML=`<div id="nzLiveMap" class="nz-live-map-canvas"></div>
    <div class="nz-map-top">
      <div class="nz-map-searchrow"><button type="button" class="nz-map-back" aria-label="بازگشت">←</button><input id="nzMapSearch" class="nz-map-search" type="search" placeholder="جستجو در آگهی‌های روی نقشه"><button type="button" class="nz-map-locate" aria-label="موقعیت من">⌖</button></div>
      <div class="nz-map-chiprow"><button type="button" class="nz-map-chip" id="nzMapCity"></button><select id="nzMapCategory" class="nz-map-filter-select" aria-label="دسته‌بندی"><option value="">همه دسته‌ها</option><option>املاک</option><option>وسایل نقلیه</option><option>کالای دیجیتال</option><option>خانه و آشپزخانه</option><option>خدمات</option></select><span class="nz-map-live"><i></i>زنده</span></div>
    </div>
    <div id="nzMapLoading" class="nz-map-loading">در حال بروزرسانی…</div>
    <div id="nzMapPreview" class="nz-map-preview-rail" aria-live="polite"></div>
    <section class="nz-map-sheet"><div class="nz-map-handle"></div><div class="nz-map-sheet-head"><span id="nzMapCount">آگهی‌های این محدوده</span><small id="nzMapLimit"></small></div></section>`;
  document.body.appendChild(root);
  qs('.nz-map-back',root).onclick=()=>history.length>1?history.back():location.assign('/nz');
  const cityLabel=params.get('city')||'همه شهرها';qs('#nzMapCity',root).textContent=cityLabel;
  const category=params.get('category')||'';const catSelect=qs('#nzMapCategory',root);if(category&&!Array.from(catSelect.options).some(o=>o.value===category)){const o=document.createElement('option');o.value=category;o.textContent=category;catSelect.appendChild(o);}catSelect.value=category;
  const search=qs('#nzMapSearch',root);search.value=params.get('q')||'';

  let L;try{L=await loadLeaflet();}catch(_){qs('#nzMapCount',root).textContent='بارگذاری نقشه انجام نشد';return;}
  const start=currentCityCenter();const map=L.map('nzLiveMap',{zoomControl:false,attributionControl:true,preferCanvas:true}).setView([start[0],start[1]],start[2]);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
  L.control.zoom({position:'bottomright'}).addTo(map);
  const markerLayer=L.layerGroup().addTo(map);let items=[];let selected='';let timer=0;let aborter=null;let firstFocus=true;let touchStartX=0;

  function selectedCategory(){return catSelect.value||'';}
  function endpoint(extra={}){
    const b=map.getBounds();const u=new URL('/nz-map-feed.php',location.origin);
    u.searchParams.set('minLat',b.getSouth().toFixed(6));u.searchParams.set('maxLat',b.getNorth().toFixed(6));u.searchParams.set('minLng',b.getWest().toFixed(6));u.searchParams.set('maxLng',b.getEast().toFixed(6));
    const c=params.get('city')||'';if(c)u.searchParams.set('city',c);if(selectedCategory())u.searchParams.set('category',selectedCategory());if(search.value.trim())u.searchParams.set('q',search.value.trim());
    Object.entries(extra).forEach(([k,v])=>{if(v)u.searchParams.set(k,String(v));});return u;
  }
  function renderCount(total,limited){
    qs('#nzMapCount',root).textContent=new Intl.NumberFormat('fa-IR').format(total)+' آگهی در این محدوده';
    qs('#nzMapLimit',root).textContent=limited?'نمایش نزدیک‌ترین نتایج':'';
  }
  function selectedItem(){return items.find(p=>p.id===selected)||null;}
  function itemIndex(){return Math.max(0,items.findIndex(p=>p.id===selected));}
  function renderPreview(){
    const rail=qs('#nzMapPreview',root);rail.replaceChildren();const p=selectedItem();
    if(!p){rail.classList.remove('show');return;}
    const card=document.createElement('a');card.className='nz-map-preview-card';card.href='/nz?id='+encodeURIComponent(p.id);card.dataset.id=p.id;
    const media=document.createElement('div');media.className='nz-map-preview-media';const imgs=(Array.isArray(p.images)?p.images:[]).filter(Boolean).slice(0,2);if(!imgs.length&&p.image)imgs.push(p.image);
    if(imgs.length){imgs.forEach(src=>{const wrap=document.createElement('div');const img=document.createElement('img');img.src=src;img.alt='';img.loading='eager';wrap.appendChild(img);media.appendChild(wrap);});if(imgs.length===1){const ph=document.createElement('div');ph.textContent=p.category||'آگهی';media.appendChild(ph);}}
    else{const ph=document.createElement('div');ph.textContent='بدون تصویر';media.appendChild(ph);}
    const body=document.createElement('div');body.className='nz-map-preview-body';const title=document.createElement('div');title.className='nz-map-preview-title';title.textContent=p.title||'آگهی';const price=document.createElement('div');price.className='nz-map-preview-price';price.textContent=fmtPrice(p.price);const meta=document.createElement('div');meta.className='nz-map-preview-meta';[p.category,p.featured?'ویژه':''].filter(Boolean).forEach(t=>{const s=document.createElement('span');s.textContent=t;meta.appendChild(s);});const loc=document.createElement('div');loc.className='nz-map-preview-loc';loc.textContent=[p.city,p.district].filter(Boolean).join('، ')||'موقعیت روی نقشه';body.append(title,price,meta,loc);card.append(media,body);
    card.addEventListener('touchstart',e=>{touchStartX=e.changedTouches[0]?.clientX||0;},{passive:true});card.addEventListener('touchend',e=>{const x=e.changedTouches[0]?.clientX||0;const dx=x-touchStartX;if(Math.abs(dx)>55){e.preventDefault();selectRelative(dx>0?-1:1);}}, {passive:false});
    const prev=document.createElement('div');prev.className='nz-map-preview-side prev';const next=document.createElement('div');next.className='nz-map-preview-side next';rail.append(prev,card,next);rail.classList.add('show');
  }
  function selectItem(p,{pan=false}={}){
    if(!p)return;selected=p.id;renderMarkers();renderPreview();if(pan)map.panTo([p.latitude,p.longitude],{animate:true,duration:.25});
  }
  function selectRelative(step){
    if(!items.length)return;const idx=itemIndex();let next=idx+step;if(next<0)next=items.length-1;if(next>=items.length)next=0;selectItem(items[next],{pan:true});
  }
  function clusterItems(){
    const groups=new Map();const cell=82;
    items.forEach(p=>{const pt=map.latLngToContainerPoint([p.latitude,p.longitude]);const key=Math.round(pt.x/cell)+'|'+Math.round(pt.y/cell);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(p);});return Array.from(groups.values());
  }
  function renderMarkers(){
    markerLayer.clearLayers();clusterItems().forEach(group=>{
      const lat=group.reduce((s,p)=>s+Number(p.latitude),0)/group.length;const lng=group.reduce((s,p)=>s+Number(p.longitude),0)/group.length;
      if(group.length>1){const html='<div class="nz-map-cluster">'+new Intl.NumberFormat('fa-IR').format(group.length)+'</div>';const icon=L.divIcon({className:'',html,iconSize:[48,48],iconAnchor:[24,24]});const m=L.marker([lat,lng],{icon,keyboard:true}).addTo(markerLayer);m.on('click',()=>map.setView([lat,lng],Math.min(map.getZoom()+2,18)));return;}
      const p=group[0];const active=selected===p.id;const label=fmtMarkerPrice(p.price);const width=Math.max(58,Math.min(104,42+label.length*6));const html='<div class="nz-map-marker '+(active?'selected':'')+'">'+label+'</div>';const icon=L.divIcon({className:'',html,iconSize:[width,42],iconAnchor:[Math.round(width/2),39]});const m=L.marker([lat,lng],{icon,keyboard:true,zIndexOffset:active?700:0}).addTo(markerLayer);m.on('click',()=>selectItem(p));
    });
  }
  async function loadViewport(){
    if(aborter)aborter.abort();aborter=new AbortController();qs('#nzMapLoading',root).classList.add('show');
    try{
      const r=await fetch(endpoint(),{credentials:'same-origin',signal:aborter.signal,headers:{Accept:'application/json'}});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'feed');items=Array.isArray(data.items)?data.items:[];if(selected&&!items.some(p=>p.id===selected)){selected='';renderPreview();}renderMarkers();renderCount(Number(data.total||items.length),!!data.limited);
    }catch(e){if(e.name!=='AbortError'){items=[];selected='';renderMarkers();renderPreview();renderCount(0,false);}}
    finally{qs('#nzMapLoading',root).classList.remove('show');}
  }
  function queueLoad(ms=220){clearTimeout(timer);timer=setTimeout(loadViewport,ms);}
  map.on('moveend zoomend',()=>queueLoad());map.on('click',()=>{if(selected){selected='';renderMarkers();renderPreview();}});search.addEventListener('input',()=>queueLoad(350));catSelect.addEventListener('change',()=>{selected='';renderPreview();queueLoad(50);});
  qs('.nz-map-locate',root).onclick=()=>{if(!navigator.geolocation)return;navigator.geolocation.getCurrentPosition(pos=>{map.setView([pos.coords.latitude,pos.coords.longitude],15);},()=>{}, {enableHighAccuracy:true,timeout:8000,maximumAge:30000});};
  qs('#nzMapCity',root).onclick=()=>{const u=new URL('/nz',location.origin);if(params.get('city'))u.searchParams.set('city',params.get('city'));location.assign(u.pathname+u.search);};

  const focus=params.get('focus');
  if(focus&&firstFocus){firstFocus=false;try{const u=new URL('/nz-map-feed.php',location.origin);u.searchParams.set('id',focus);const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json'}});const d=await r.json();if(d&&d.focus){selected=d.focus.id;map.setView([d.focus.latitude,d.focus.longitude],16);}}catch(_){} }
  await loadViewport();if(selected){const p=selectedItem();if(p)selectItem(p);}setTimeout(()=>map.invalidateSize(),100);
}

function initEditLocation(){
  if(page!=='edit')return;const btn=qs('#nzpmEditLocation');if(!btn)return;
  const latInput=qs('input[name="latitude"]'),lngInput=qs('input[name="longitude"]'),modeInput=qs('input[name="location_mode"]'),cityInput=qs('input[name="city"]'),districtInput=qs('input[name="district"]'),streetInput=qs('input[name="street"]'),provinceInput=qs('input[name="province"]'),summary=qs('#nzpmLocationSummary');
  const modal=document.createElement('div');modal.className='nz-edit-map-modal';modal.innerHTML=`<div class="nz-edit-map-card"><div class="nz-edit-map-head"><b>ویرایش موقعیت آگهی</b><button type="button" class="nz-edit-map-close" aria-label="بستن">×</button></div><div class="nz-edit-map-area"><div id="nzEditMap" class="nz-edit-map-canvas"></div><div class="nz-edit-map-pin">📍</div></div><div class="nz-edit-map-foot"><div class="nz-edit-mode"><label><input type="radio" name="nzEditMode" value="exact"> موقعیت دقیق<br><small>نقطه انتخاب‌شده نمایش داده می‌شود.</small></label><label><input type="radio" name="nzEditMode" value="approx"> موقعیت حدودی<br><small>مختصات قبل از ذخیره گرد می‌شود.</small></label></div><button type="button" class="nz-edit-map-save">ثبت این موقعیت</button></div></div>`;document.body.appendChild(modal);
  let map=null;let L=null;
  const close=()=>modal.classList.remove('show');qs('.nz-edit-map-close',modal).onclick=close;modal.addEventListener('click',e=>{if(e.target===modal)close();});
  btn.onclick=async()=>{
    modal.classList.add('show');L=await loadLeaflet();
    const lat=Number(latInput.value),lng=Number(lngInput.value);const fallback=currentCityCenter();const center=Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=24&&lat<=40&&lng>=43&&lng<=64?[lat,lng,16]:fallback;
    qsa('input[name="nzEditMode"]',modal).forEach(r=>r.checked=r.value===(modeInput.value||'approx'));
    if(!map){map=L.map('nzEditMap',{zoomControl:true,attributionControl:true}).setView([center[0],center[1]],center[2]||15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);}else map.setView([center[0],center[1]],center[2]||15);
    setTimeout(()=>map.invalidateSize(),80);
  };
  qs('.nz-edit-map-save',modal).onclick=async()=>{
    if(!map)return;let c=map.getCenter();const mode=(qs('input[name="nzEditMode"]:checked',modal)||{}).value||'approx';let lat=c.lat,lng=c.lng;if(mode==='approx'){lat=Math.round(lat*1000)/1000;lng=Math.round(lng*1000)/1000;}if(lat<24||lat>40||lng<43||lng>64)return;
    latInput.value=String(lat);lngInput.value=String(lng);modeInput.value=mode;
    try{const u=new URL('/nz-geocode.php',location.origin);u.searchParams.set('lat',String(lat));u.searchParams.set('lon',String(lng));const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json'}});const d=await r.json();const a=d.address||{};const city=a.city||a.town||a.village||a.county||'';const district=a.suburb||a.neighbourhood||a.quarter||a.city_district||'';const street=a.road||a.pedestrian||a.residential||'';if(cityInput)cityInput.value=city;if(districtInput)districtInput.value=district;if(streetInput)streetInput.value=street;if(provinceInput)provinceInput.value=a.state||'';}catch(_){}
    if(summary)summary.textContent='موقعیت جدید انتخاب شد · '+(mode==='exact'?'دقیق':'حدودی')+' · مختصات پس از ذخیره همراه آگهی ثبت می‌شود.';close();
  };
}

function boot(){rewriteDetailMapLink();if(page==='map')initLiveMap();else addMapLaunch();initEditLocation();}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();