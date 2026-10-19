(()=>{
'use strict';

if(document.querySelector('form input[name="action"][value="create_post"]')) return;

let PROVINCES={
  'آذربایجان شرقی':['تبریز','مراغه','مرند','اهر','میانه','بناب','سراب'],
  'آذربایجان غربی':['ارومیه','خوی','مهاباد','میاندوآب','بوکان','سلماس'],
  'اردبیل':['اردبیل','پارس‌آباد','مشگین‌شهر','خلخال'],
  'اصفهان':['اصفهان','کاشان','خمینی‌شهر','نجف‌آباد','شاهین‌شهر','فولادشهر','نائین'],
  'البرز':['کرج','فردیس','هشتگرد','نظرآباد','محمدشهر'],
  'ایلام':['ایلام','دهلران','مهران','ایوان'],
  'بوشهر':['بوشهر','برازجان','گناوه','کنگان','عسلویه'],
  'تهران':['تهران','ری','اسلامشهر','شهریار','قدس','ملارد','پردیس','دماوند','ورامین','رباط‌کریم'],
  'چهارمحال و بختیاری':['شهرکرد','بروجن','فارسان','لردگان'],
  'خراسان جنوبی':['بیرجند','قائن','طبس','فردوس'],
  'خراسان رضوی':['مشهد','نیشابور','سبزوار','تربت‌حیدریه','قوچان','کاشمر'],
  'خراسان شمالی':['بجنورد','شیروان','اسفراین','جاجرم'],
  'خوزستان':['اهواز','آبادان','خرمشهر','دزفول','ماهشهر','بهبهان','شوشتر'],
  'زنجان':['زنجان','ابهر','خرمدره','قیدار'],
  'سمنان':['سمنان','شاهرود','دامغان','گرمسار'],
  'سیستان و بلوچستان':['زاهدان','چابهار','ایرانشهر','زابل','خاش'],
  'فارس':['شیراز','مرودشت','جهرم','فسا','کازرون','لار','صدرا'],
  'قزوین':['قزوین','تاکستان','الوند','آبیک'],
  'قم':['قم'],
  'کردستان':['سنندج','سقز','مریوان','بانه','قروه'],
  'کرمان':['کرمان','رفسنجان','سیرجان','بم','جیرفت'],
  'کرمانشاه':['کرمانشاه','اسلام‌آباد غرب','کنگاور','پاوه'],
  'کهگیلویه و بویراحمد':['یاسوج','گچساران','دهدشت'],
  'گلستان':['گرگان','گنبدکاووس','علی‌آباد کتول','بندر ترکمن'],
  'گیلان':['رشت','انزلی','لاهیجان','لنگرود','رودسر','فومن'],
  'لرستان':['خرم‌آباد','بروجرد','دورود','الیگودرز'],
  'مازندران':['ساری','بابل','آمل','قائم‌شهر','بابلسر','چالوس','نوشهر','تنکابن'],
  'مرکزی':['اراک','ساوه','خمین','محلات'],
  'هرمزگان':['بندرعباس','قشم','کیش','میناب','بندر لنگه'],
  'همدان':['همدان','ملایر','نهاوند','تویسرکان'],
  'یزد':['یزد','میبد','اردکان','بافق']
};

const FALLBACK_NEIGHBORHOODS={
  'تهران':['آجودانیه','آذری','آرارات','آرژانتین','آشتیانی','آهنگ','اختیاریه','اقدسیه','الهیه','امانیه','امیرآباد','امیریه','انقلاب','اوین','بازار','باغ فیض','باغ فردوس','بریانتک','تهرانپارس شرقی','تهرانپارس غربی','جردن','جماران','جنت‌آباد جنوبی','جنت‌آباد شمالی','جنت‌آباد مرکزی','جوادیه','چیذر','حکیمیه','خانی‌آباد','دارآباد','دروس','دولت','زعفرانیه','سعادت‌آباد','سلسبیل','سهروردی','شهرآرا','شهران','شهرک اکباتان','شهرک غرب','شوش','صادقیه','طرشت','فرمانیه','فلسطین','قلهک','قیطریه','کامرانیه','گاندی','گیشا','لویزان','مجیدیه','مرزداران','مهرآباد','میدان حر','نارمک','نازی‌آباد','نیاوران','ونک','ولیعصر','یوسف‌آباد'],
  'شیراز':['آستانه','ارم','بلوار چمران','پودنک','تاچارا','زرگری','ستارخان','صدرا','عفیف‌آباد','فرهنگ‌شهر','قصرالدشت','قدوسی شرقی','قدوسی غربی','گلدشت معالی‌آباد','معالی‌آباد','نمازی','همت شمالی'],
  'اصفهان':['آتشگاه','باغ غدیر','بهارستان','جلفا','خانه اصفهان','خمینی‌شهر','سپاهان‌شهر','شاهین‌شهر','شیخ صدوق','ملک‌شهر','مرداویج','هشت‌بهشت'],
  'مشهد':['احمدآباد','الهیه','بلوار فردوسی','رضاشهر','سجاد','طبرسی','طرقبه','قاسم‌آباد','وکیل‌آباد','هاشمیه'],
  'تبریز':['آبرسان','ائل‌گلی','باغمیشه','رشدیه','زعفرانیه','ولیعصر','منظریه','نصف‌راه'],
  'کرج':['عظیمیه','فردیس','گوهردشت','جهانشهر','حصارک','شاهین‌ویلا','گلشهر','مهرشهر'],
  'اهواز':['امانیه','پادادشهر','زیتون کارمندی','گلستان','کیانپارس','کوی ملت','کوی مهدیس'],
  'رشت':['بلوار دیلمان','سبزه میدان','گلسار','لاکانی','معلم','منظریه'],
  'قم':['بنیاد','پردیسان','سالاریه','صفائیه','زنبیل‌آباد','نیروگاه'],
  'یزد':['آزادشهر','اکرم‌آباد','امامشهر','بلوار جمهوری','شاهدیه','صفائیه'],
  'کرمان':['بلوار جمهوری','خواجو','شهرک باهنر','شهرک مطهری','هزار و یک شب'],
  'بندرعباس':['بلوار امام حسین','سورو','شهرک پیامبر اعظم','گلشهر','نایبند']
};

const CENTERS={
  'تهران':[35.6892,51.3890],'شیراز':[29.5918,52.5837],'اصفهان':[32.6546,51.6680],'مشهد':[36.2605,59.6168],
  'تبریز':[38.0800,46.2919],'کرج':[35.8400,50.9391],'اهواز':[31.3183,48.6706],'قم':[34.6416,50.8746],
  'رشت':[37.2808,49.5832],'کرمان':[30.2839,57.0834],'یزد':[31.8974,54.3569],'بندرعباس':[27.1832,56.2666],
  'ارومیه':[37.5527,45.0761],'بوشهر':[28.9234,50.8203],'اردبیل':[38.2498,48.2933],'زاهدان':[29.4963,60.8629],
  'سنندج':[35.3219,46.9862],'کرمانشاه':[34.3142,47.0650],'ساری':[36.5633,53.0601],'گرگان':[36.8427,54.4439]
};

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const norm=s=>String(s??'').replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/‌/g,' ').replace(/\s+/g,' ').trim().toLowerCase();
const allCities=()=>[...new Set(Object.values(PROVINCES).flat())];
const currentUrl=()=>new URL(location.href);
const queryValue=name=>currentUrl().searchParams.get(name)||'';
let selectedCity=queryValue('city'),selectedDistrict=queryValue('district'),draftCity=selectedCity,draftDistrict=selectedDistrict;
let map=null,mapReady=false,mapTimer=null,mapResult=null;
const districtCache=new Map(),districtPending=new Map();

async function loadTaxonomy(){
  try{const r=await fetch('/nz-taxonomy.php',{cache:'force-cache',credentials:'same-origin'});if(!r.ok)return;const j=await r.json();if(j?.ok&&j.provinces&&Object.keys(j.provinces).length)PROVINCES=j.provinces}catch(_){}
}
function previousCities(){try{return JSON.parse(localStorage.getItem('nz_recent_cities')||'[]').filter(x=>allCities().includes(x)).slice(0,8)}catch(_){return[]}}
function rememberCity(city){if(!city)return;try{localStorage.setItem('nz_recent_cities',JSON.stringify([city,...previousCities().filter(x=>x!==city)].slice(0,8)))}catch(_){}}
function applyLocation(city,district=''){
  const u=currentUrl();u.pathname='/nz';
  if(city){u.searchParams.set('page','search');u.searchParams.set('city',city);rememberCity(city)}else{u.searchParams.delete('city');u.searchParams.delete('district');u.searchParams.delete('province');if([...u.searchParams.keys()].filter(k=>!['page','p','seed'].includes(k)).length===0)u.searchParams.delete('page')}
  if(city&&district)u.searchParams.set('district',district);else u.searchParams.delete('district');
  ['province','p','seed','lat','lng','radius'].forEach(k=>u.searchParams.delete(k));
  location.href=u.pathname+(u.searchParams.toString()?'?'+u.searchParams.toString():'');
}

function addStyles(){
  if(document.getElementById('nzLocationFilterStyle'))return;const s=document.createElement('style');s.id='nzLocationFilterStyle';s.textContent=`
  .nz-loc-trigger{border:1px solid #d9dde2;background:#fff;color:#34383d;border-radius:12px;min-height:44px;padding:0 13px;font:800 13px Tahoma,Arial,sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;white-space:nowrap}.nz-loc-trigger.active{border-color:#b4232c;color:#981923;background:#fff8f8}.nz-loc-trigger svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8}.nz-loc-bar{display:flex;gap:8px;overflow:auto;scrollbar-width:none;padding:9px 0 2px}.nz-loc-bar::-webkit-scrollbar{display:none}
  .nz-location-sheet{position:fixed;inset:0;z-index:12000;background:rgba(10,12,16,.55);display:none;align-items:flex-end;justify-content:center}.nz-location-sheet.show{display:flex}.nz-location-card{position:relative;width:min(100%,560px);max-height:94dvh;background:#fff;border-radius:22px 22px 0 0;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 -18px 70px rgba(0,0,0,.22);direction:rtl}.nz-location-head{height:62px;display:flex;align-items:center;gap:10px;padding:0 14px;border-bottom:1px solid #eceef1;flex:0 0 auto}.nz-location-head h2{font-size:18px;margin:0;font-weight:950;flex:1}.nz-location-close{width:42px;height:42px;border:0;background:transparent;border-radius:50%;font-size:25px;cursor:pointer;color:#4c5157}.nz-location-body{padding:13px 14px 92px;overflow:auto;overscroll-behavior:contain}.nz-location-search{height:48px;border:1px solid #d5d9df;border-radius:12px;display:flex;align-items:center;gap:8px;padding:0 12px;position:sticky;top:0;background:#fff;z-index:2}.nz-location-search input{border:0;outline:0;background:transparent;flex:1;font:inherit;min-width:0}.nz-location-search svg{width:22px;height:22px;fill:none;stroke:#747b84;stroke-width:1.8}.nz-location-section-title{font-size:15px;font-weight:950;margin:20px 2px 8px}.nz-selected-chip{display:inline-flex;align-items:center;gap:8px;border:1px solid #d0d4da;border-radius:9px;padding:7px 10px;margin:3px;background:#fff;font-size:12px}.nz-selected-chip button{border:0;background:transparent;color:#a6232c;font-size:18px;padding:0;cursor:pointer}.nz-location-row{width:100%;min-height:52px;border:0;border-bottom:1px solid #eceef1;background:#fff;display:flex;align-items:center;gap:10px;text-align:right;padding:8px 4px;font:800 13px Tahoma,Arial,sans-serif;cursor:pointer;color:#292d31}.nz-location-row small{display:block;color:#767d85;font-weight:400;margin-top:4px;line-height:1.7}.nz-location-row .grow{flex:1;min-width:0}.nz-check{width:20px;height:20px;border:2px solid #979da5;border-radius:3px;display:grid;place-items:center;flex:0 0 auto}.nz-location-row.selected .nz-check{background:#b4232c;border-color:#b4232c;color:#fff}.nz-location-row.selected .nz-check:after{content:'✓';font-size:13px;font-weight:900}.nz-province-row .nz-arrow{font-size:24px;color:#8c9299;transition:.15s}.nz-province-row.open .nz-arrow{transform:rotate(-90deg)}.nz-city-sublist{padding-right:14px;display:none}.nz-city-sublist.show{display:block}.nz-city-sublist .nz-location-row{font-weight:700}.nz-location-footer{position:absolute;bottom:0;left:0;right:0;padding:10px 14px calc(10px + env(safe-area-inset-bottom));background:linear-gradient(180deg,rgba(255,255,255,.7),#fff 26%);border-top:1px solid #eceef1}.nz-location-confirm{width:100%;height:50px;border:0;border-radius:11px;background:#b4232c;color:#fff;font:950 14px Tahoma,Arial,sans-serif;cursor:pointer}.nz-location-confirm:disabled{opacity:.45;cursor:not-allowed}.nz-map-choice{color:#b4232c;font-weight:950}.nz-map-choice svg{width:24px;height:24px}.nz-manual-add{margin-top:8px;border:1px dashed #d6d9de;border-radius:12px!important;padding:11px!important}.nz-empty-loc{padding:25px 8px;text-align:center;color:#777;line-height:1.9;font-size:12px}.nz-loc-loading{padding:24px 8px;text-align:center;color:#737980;font-size:12px}.nz-location-count{font-size:11px;color:#7c8289;font-weight:500;margin-right:6px}
  .nz-map-screen{position:fixed;inset:0;z-index:13000;background:#fff;display:none;flex-direction:column;direction:rtl}.nz-map-screen.show{display:flex}.nz-map-head{height:64px;display:flex;align-items:center;gap:8px;padding:0 14px;border-bottom:1px solid #e8eaed;flex:0 0 auto;background:#fff}.nz-map-head h2{margin:0;font-size:18px;font-weight:950;flex:1}.nz-map-wrap{position:relative;flex:1;min-height:0;background:#e8ebef}.nz-map-canvas{position:absolute;inset:0}.nz-map-pin{position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);z-index:600;pointer-events:none;width:42px;height:42px;border-radius:50% 50% 50% 0;background:#c92f37;rotate:-45deg;box-shadow:0 5px 15px rgba(0,0,0,.25)}.nz-map-pin:after{content:'';position:absolute;width:14px;height:14px;border-radius:50%;background:#fff;left:14px;top:14px}.nz-map-center{position:absolute;z-index:650;right:14px;bottom:100px;width:48px;height:48px;border:0;border-radius:50%;background:#fff;box-shadow:0 3px 16px rgba(0,0,0,.22);display:grid;place-items:center;cursor:pointer}.nz-map-center svg{width:24px;height:24px;fill:none;stroke:#b4232c;stroke-width:2}.nz-map-status{position:absolute;z-index:650;left:14px;right:14px;top:12px;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border-radius:12px;padding:9px 12px;text-align:center;font-size:12px;box-shadow:0 2px 12px rgba(0,0,0,.1)}.nz-map-footer{padding:10px 14px calc(10px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid #e8eaed;display:flex;gap:9px}.nz-map-footer button{height:50px;border-radius:11px;font:950 14px Tahoma,Arial,sans-serif;cursor:pointer}.nz-map-confirm{flex:1;border:0;background:#b4232c;color:#fff}.nz-map-back{width:108px;border:1px solid #d9dde2;background:#fff;color:#333}.filter-card .nz-server-location-field{display:none!important}
  @media(max-width:760px){.mobile-head .mobile-row>form:has(select.mobile-location){display:none}.mobile-head .mobile-row{align-items:center}.mobile-head .nz-mobile-city-top{margin-right:auto}.nz-location-sheet{background:#fff}.nz-location-card{height:100dvh;max-height:none;border-radius:0}.nz-location-head{padding-top:env(safe-area-inset-top);height:calc(60px + env(safe-area-inset-top))}.nz-location-body{padding-bottom:calc(88px + env(safe-area-inset-bottom))}.nz-location-footer{position:fixed}.nz-map-head{padding-top:env(safe-area-inset-top);height:calc(62px + env(safe-area-inset-top))}.nz-loc-bar{margin:0 -2px}.nz-loc-trigger{min-height:42px}.nz-map-wrap{min-height:300px}}
  `;document.head.appendChild(s);
}
function createSheet(id,title){const root=document.createElement('div');root.id=id;root.className='nz-location-sheet';root.innerHTML=`<section class="nz-location-card" role="dialog" aria-modal="true" aria-labelledby="${id}Title"><header class="nz-location-head"><button type="button" class="nz-location-close" aria-label="بستن">→</button><h2 id="${id}Title">${esc(title)}</h2></header><div class="nz-location-body"></div><footer class="nz-location-footer"><button type="button" class="nz-location-confirm">تأیید</button></footer></section>`;document.body.appendChild(root);root.querySelector('.nz-location-close').onclick=()=>closeSheet(root);root.addEventListener('click',e=>{if(e.target===root)closeSheet(root)});return root}
function openSheet(root){root.classList.add('show');document.documentElement.style.overflow='hidden';setTimeout(()=>root.querySelector('input')?.focus(),80)}
function closeSheet(root){root.classList.remove('show');if(!document.querySelector('.nz-location-sheet.show,.nz-map-screen.show'))document.documentElement.style.overflow=''}
const citySheet=createSheet('nzCitySheet','انتخاب شهر'),districtSheet=createSheet('nzDistrictSheet','انتخاب محله');
function searchBox(placeholder){return `<label class="nz-location-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input type="search" autocomplete="off" placeholder="${esc(placeholder)}"></label>`}
function cityRow(city){return `<button type="button" class="nz-location-row ${draftCity===city?'selected':''}" data-city="${esc(city)}"><span class="grow">${esc(city)}</span><span class="nz-check" aria-hidden="true"></span></button>`}
function renderCitySheet(filter=''){
  const body=citySheet.querySelector('.nz-location-body'),n=norm(filter),recents=previousCities();let html=searchBox('جستجو در شهرهای ایران');
  if(draftCity)html+=`<div class="nz-location-section-title">انتخاب فعلی</div><div class="nz-selected-chip">${esc(draftCity)} <button type="button" data-clear-city aria-label="حذف ${esc(draftCity)}">×</button></div>`;
  if(recents.length&&!n){html+='<div class="nz-location-section-title">انتخاب‌های قبلی</div>';for(const city of recents)html+=cityRow(city)}
  html+='<div class="nz-location-section-title">همه شهرها</div>';
  if(n){const matches=allCities().filter(c=>norm(c).includes(n));html+=matches.length?matches.map(cityRow).join(''):'<div class="nz-empty-loc">شهری با این عبارت پیدا نشد.</div>'}
  else for(const [province,cities] of Object.entries(PROVINCES))html+=`<div class="nz-province"><button type="button" class="nz-location-row nz-province-row" data-province="${esc(province)}"><span class="grow">${esc(province)}</span><span class="nz-arrow">‹</span></button><div class="nz-city-sublist" data-cities="${esc(province)}">${cities.map(cityRow).join('')}</div></div>`;
  body.innerHTML=html;const input=body.querySelector('input');input.value=filter;input.oninput=()=>renderCitySheet(input.value);body.querySelector('[data-clear-city]')?.addEventListener('click',()=>{draftCity='';draftDistrict='';renderCitySheet(input.value)});body.querySelectorAll('[data-city]').forEach(btn=>btn.onclick=()=>{const city=btn.dataset.city||'';draftCity=draftCity===city?'':city;if(draftCity!==selectedCity)draftDistrict='';renderCitySheet(input.value)});body.querySelectorAll('[data-province]').forEach(btn=>btn.onclick=()=>{btn.classList.toggle('open');btn.parentElement?.querySelector('.nz-city-sublist')?.classList.toggle('show')});citySheet.querySelector('.nz-location-confirm').disabled=!draftCity;
}
citySheet.querySelector('.nz-location-confirm').onclick=()=>{if(!draftCity)return;selectedCity=draftCity;selectedDistrict='';closeSheet(citySheet);applyLocation(selectedCity,'')};

function fallbackDistricts(city){return (FALLBACK_NEIGHBORHOODS[city]||[]).map(name=>({name,desc:''}))}
async function loadDistricts(city){
  if(!city)return[];if(districtCache.has(city))return districtCache.get(city);if(districtPending.has(city))return districtPending.get(city);
  const p=fetch('/nz-location-catalog.php?city='+encodeURIComponent(city),{credentials:'same-origin',cache:'force-cache',headers:{Accept:'application/json'}}).then(async r=>{if(!r.ok)throw 0;const j=await r.json();if(!j?.ok)throw 0;const rows=(j.districts||[]).map(x=>({name:String(x.name||'').trim(),desc:Array.isArray(x.tags)?x.tags.filter(Boolean).slice(0,8).join('، '):''})).filter(x=>x.name);const out=rows.length?rows:fallbackDistricts(city);districtCache.set(city,out);return out}).catch(()=>{const out=fallbackDistricts(city);districtCache.set(city,out);return out}).finally(()=>districtPending.delete(city));districtPending.set(city,p);return p;
}
function neighborhoodItems(city){return districtCache.get(city)||fallbackDistricts(city)}
function districtRow(item){const name=item.name||String(item),desc=item.desc||'';return `<button type="button" class="nz-location-row ${draftDistrict===name?'selected':''}" data-district="${esc(name)}"><span class="grow">${esc(name)}${desc?`<small>${esc(desc)}</small>`:''}</span><span class="nz-check" aria-hidden="true"></span></button>`}
function renderDistrictSheet(filter='',loading=false){
  const body=districtSheet.querySelector('.nz-location-body'),items=neighborhoodItems(draftCity||selectedCity),n=norm(filter);let html=searchBox('جستجو در محله‌ها');
  html+=`<button type="button" class="nz-location-row nz-map-choice" data-map-pick><span class="grow">انتخاب محله از روی نقشه<small>نقطه موردنظر را روی نقشه مشخص کنید</small></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"></path><path d="M9 3v15m6-12v15"></path></svg></button>`;
  if(draftDistrict)html+=`<div class="nz-location-section-title">انتخاب فعلی</div><div class="nz-selected-chip">${esc(draftDistrict)} <button type="button" data-clear-district aria-label="حذف ${esc(draftDistrict)}">×</button></div>`;
  const matches=n?items.filter(x=>norm(x.name).includes(n)||norm(x.desc).includes(n)):items;html+=`<div class="nz-location-section-title">محله‌ها <span class="nz-location-count">${items.length?items.length.toLocaleString('fa-IR')+' مورد':''}</span></div>`;
  if(loading&&!items.length)html+='<div class="nz-loc-loading">در حال دریافت فهرست کامل محله‌ها…</div>';else if(matches.length)html+=matches.map(districtRow).join('');
  if(n&&!matches.some(x=>norm(x.name)===n))html+=`<button type="button" class="nz-location-row nz-manual-add" data-manual="${esc(filter.trim())}"><span class="grow">استفاده از «${esc(filter.trim())}»<small>محله را به‌صورت دستی انتخاب کن</small></span><span class="nz-check"></span></button>`;
  if(!loading&&!items.length&&!n)html+='<div class="nz-empty-loc">برای این شهر فهرست آماده‌ای دریافت نشد. نام محله را جستجو و دستی انتخاب کنید یا از روی نقشه انتخاب کنید.</div>';
  body.innerHTML=html;const input=body.querySelector('input');input.value=filter;input.oninput=()=>renderDistrictSheet(input.value,false);body.querySelector('[data-map-pick]').onclick=openMap;body.querySelector('[data-clear-district]')?.addEventListener('click',()=>{draftDistrict='';renderDistrictSheet(input.value,false)});body.querySelectorAll('[data-district]').forEach(btn=>btn.onclick=()=>{const d=btn.dataset.district||'';draftDistrict=draftDistrict===d?'':d;renderDistrictSheet(input.value,false)});body.querySelector('[data-manual]')?.addEventListener('click',e=>{draftDistrict=e.currentTarget.dataset.manual||'';renderDistrictSheet(draftDistrict,false)});districtSheet.querySelector('.nz-location-confirm').disabled=!draftCity;
}
districtSheet.querySelector('.nz-location-confirm').onclick=()=>{selectedCity=draftCity||selectedCity;selectedDistrict=draftDistrict;closeSheet(districtSheet);applyLocation(selectedCity,selectedDistrict)};

function buildMap(){const root=document.createElement('div');root.id='nzLocationMapScreen';root.className='nz-map-screen';root.innerHTML=`<header class="nz-map-head"><button type="button" class="nz-location-close" aria-label="بازگشت">→</button><h2>انتخاب محله از روی نقشه</h2></header><div class="nz-map-wrap"><div id="nzFilterMap" class="nz-map-canvas"></div><div class="nz-map-pin" aria-hidden="true"></div><div class="nz-map-status">نقشه را جابه‌جا کنید تا محله مشخص شود.</div><button type="button" class="nz-map-center" aria-label="موقعیت فعلی من"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="8"></circle><path d="M12 2v2m0 16v2M2 12h2m16 0h2"></path></svg></button></div><footer class="nz-map-footer"><button type="button" class="nz-map-back">بازگشت</button><button type="button" class="nz-map-confirm">تأیید</button></footer>`;document.body.appendChild(root);root.querySelector('.nz-location-close').onclick=closeMap;root.querySelector('.nz-map-back').onclick=closeMap;root.querySelector('.nz-map-center').onclick=locateMe;root.querySelector('.nz-map-confirm').onclick=confirmMap;return root}
const mapScreen=buildMap();
function loadLeaflet(){if(window.L)return Promise.resolve(window.L);return new Promise((resolve,reject)=>{if(!document.querySelector('link[data-nz-filter-leaflet]')){const l=document.createElement('link');l.rel='stylesheet';l.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';l.dataset.nzFilterLeaflet='1';document.head.appendChild(l)}const old=document.querySelector('script[data-nz-filter-leaflet],script[data-nz-leaflet]');if(old){if(window.L){resolve(window.L);return}old.addEventListener('load',()=>resolve(window.L),{once:true});old.addEventListener('error',reject,{once:true});return}const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';s.dataset.nzFilterLeaflet='1';s.onload=()=>resolve(window.L);s.onerror=reject;document.head.appendChild(s)})}
async function openMap(){closeSheet(districtSheet);mapScreen.classList.add('show');document.documentElement.style.overflow='hidden';try{await loadLeaflet();if(!mapReady){const center=CENTERS[draftCity||selectedCity]||[32.4279,53.6880];map=L.map('nzFilterMap',{zoomControl:true}).setView(center,CENTERS[draftCity||selectedCity]?13:5);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);map.on('moveend',scheduleMapLookup);mapReady=true}else{const center=CENTERS[draftCity||selectedCity];if(center)map.setView(center,13)}setTimeout(()=>{map.invalidateSize();scheduleMapLookup()},100)}catch(_){mapScreen.querySelector('.nz-map-status').textContent='نقشه آنلاین در دسترس نیست. اتصال اینترنت را بررسی کنید.'}}
function closeMap(){mapScreen.classList.remove('show');openSheet(districtSheet);renderDistrictSheet('',false)}
function locateMe(){if(!navigator.geolocation){alert('موقعیت‌یابی در این مرورگر پشتیبانی نمی‌شود.');return}const st=mapScreen.querySelector('.nz-map-status');st.textContent='در حال دریافت موقعیت فعلی…';navigator.geolocation.getCurrentPosition(p=>{if(map)map.setView([p.coords.latitude,p.coords.longitude],16)},()=>{st.textContent='دسترسی به موقعیت داده نشد. نقشه را دستی جابه‌جا کنید.'},{enableHighAccuracy:true,timeout:12000})}
function scheduleMapLookup(){clearTimeout(mapTimer);mapTimer=setTimeout(reverseMapCenter,550)}
async function reverseMapCenter(){if(!map)return;const c=map.getCenter(),st=mapScreen.querySelector('.nz-map-status');st.textContent='در حال تشخیص محله…';try{const r=await fetch(`/nz-geocode.php?lat=${encodeURIComponent(c.lat)}&lon=${encodeURIComponent(c.lng)}`,{credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)throw 0;const data=await r.json(),a=data.address||{},cityRaw=a.city||a.town||a.village||a.municipality||a.city_district||a.county||'',districtRaw=a.neighbourhood||a.suburb||a.quarter||a.borough||a.residential||a.city_district||'',city=matchCity(cityRaw),district=String(districtRaw||'').trim();mapResult={city:city||draftCity||selectedCity,district,lat:c.lat,lng:c.lng};st.textContent=[mapResult.city,district].filter(Boolean).join('، ')||'نقطه انتخاب شد؛ نام محله تشخیص داده نشد.'}catch(_){mapResult={city:draftCity||selectedCity,district:'',lat:c.lat,lng:c.lng};st.textContent='نقطه انتخاب شد؛ تشخیص خودکار محله در دسترس نبود.'}}
function matchCity(raw){const n=norm(raw);if(!n)return'';return allCities().find(c=>{const x=norm(c);return x===n||n.includes(x)||x.includes(n)})||''}
async function confirmMap(){if(!map)return;await reverseMapCenter();if(mapResult?.city)draftCity=mapResult.city;if(mapResult?.district)draftDistrict=mapResult.district;mapScreen.classList.remove('show');openSheet(districtSheet);await loadDistricts(draftCity||selectedCity);renderDistrictSheet('',false)}

function openCity(){draftCity=selectedCity;draftDistrict=selectedDistrict;renderCitySheet('');openSheet(citySheet)}
async function openDistrict(){if(!selectedCity){openCity();return}draftCity=selectedCity;draftDistrict=selectedDistrict;renderDistrictSheet('',true);openSheet(districtSheet);await loadDistricts(draftCity);if(districtSheet.classList.contains('show'))renderDistrictSheet('',false)}
function installTriggers(){
  const mobileSelect=document.querySelector('select.mobile-location');if(mobileSelect){const nativeForm=mobileSelect.closest('form');if(nativeForm)nativeForm.style.display='none';const row=mobileSelect.closest('.mobile-row');if(row&&!row.querySelector('.nz-mobile-city-top')){const b=document.createElement('button');b.type='button';b.className='nz-loc-trigger nz-mobile-city-top'+(selectedCity?' active':'');b.innerHTML=`<svg viewBox="0 0 24 24"><path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg><span>${esc(selectedCity||'انتخاب شهر')}</span>`;b.onclick=openCity;row.appendChild(b)}const head=mobileSelect.closest('.mobile-head'),search=head?.querySelector('.mobile-search');if(head&&search&&!head.querySelector('.nz-loc-bar')){const bar=document.createElement('div');bar.className='nz-loc-bar';bar.innerHTML=`<button type="button" class="nz-loc-trigger ${selectedCity?'active':''}" data-nz-city-filter>شهر: ${esc(selectedCity||'همه')}</button><button type="button" class="nz-loc-trigger ${selectedDistrict?'active':''}" data-nz-district-filter ${selectedCity?'':'disabled'}>محله${selectedDistrict?': '+esc(selectedDistrict):''}</button>`;search.after(bar);bar.querySelector('[data-nz-city-filter]').onclick=openCity;bar.querySelector('[data-nz-district-filter]').onclick=openDistrict}}
  const desktop=document.querySelector('.desktop-top .location');if(desktop){desktop.setAttribute('role','button');desktop.setAttribute('aria-label','انتخاب شهر');desktop.addEventListener('click',e=>{e.preventDefault();openCity()});desktop.innerHTML=`⌖ ${esc(selectedCity||'انتخاب شهر')}`}
  document.querySelectorAll('.filter-card').forEach(card=>{const cityField=card.querySelector('select[name="city"]')?.closest('.field'),districtField=card.querySelector('input[name="district"]')?.closest('.field');if(cityField)cityField.classList.add('nz-server-location-field');if(districtField)districtField.classList.add('nz-server-location-field');if((cityField||districtField)&&!card.querySelector('.nz-desktop-location-controls')){const box=document.createElement('div');box.className='nz-desktop-location-controls';box.innerHTML=`<div class="field"><label>شهر</label><button type="button" class="nz-loc-trigger ${selectedCity?'active':''}" style="width:100%;justify-content:space-between" data-nz-city-filter><span>${esc(selectedCity||'انتخاب شهر')}</span><span>‹</span></button></div><div class="field"><label>محله</label><button type="button" class="nz-loc-trigger ${selectedDistrict?'active':''}" style="width:100%;justify-content:space-between" data-nz-district-filter ${selectedCity?'':'disabled'}><span>${esc(selectedDistrict||'انتخاب محله')}</span><span>‹</span></button></div>`;(cityField||districtField).before(box);box.querySelector('[data-nz-city-filter]').onclick=openCity;box.querySelector('[data-nz-district-filter]').onclick=openDistrict}})
}

(async()=>{await loadTaxonomy();addStyles();installTriggers();document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;if(mapScreen.classList.contains('show'))closeMap();else document.querySelectorAll('.nz-location-sheet.show').forEach(closeSheet)})})();
})();