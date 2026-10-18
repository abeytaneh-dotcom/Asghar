(()=>{
'use strict';

const state={sub:'',brands:[],models:[],brand:'',model:''};
let loadId=0;

function norm(v){return String(v||'').toLowerCase().replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[ًٌٍَُِّْـ]/g,'').replace(/\s+/g,' ').trim()}
function fieldByLabel(labels){
  const box=document.getElementById('nzSpecs');if(!box)return null;
  for(const field of box.querySelectorAll('.field')){
    const label=(field.querySelector('label')?.textContent||'').replace(/\s*\*\s*$/,'').trim();
    if(labels.includes(label))return field;
  }
  return null;
}
function originalControl(field){return field?.querySelector('input[name^="attributes["],select[name^="attributes["]')||null}
function filterItems(items,q){
  const words=norm(q).split(' ').filter(Boolean);
  if(!words.length)return items.slice();
  return items.filter(v=>{const h=norm(v);return words.every(w=>h.includes(w))}).sort((a,b)=>{
    const na=norm(a),nb=norm(b),qq=norm(q);return (na.startsWith(qq)?0:1)-(nb.startsWith(qq)?0:1)||na.localeCompare(nb,'fa');
  });
}
function injectStyle(){
 if(document.getElementById('nzBrandModelStyle'))return;
 const s=document.createElement('style');s.id='nzBrandModelStyle';s.textContent=`
 .nz-bm{position:relative;width:100%}.nz-bm input.nz-bm-search{width:100%;min-height:46px;padding:0 14px 0 42px;border:1px solid #d7d9de;border-radius:12px;background:#fff;font:inherit;box-sizing:border-box;outline:none}.nz-bm input.nz-bm-search:focus{border-color:#111;box-shadow:0 0 0 2px rgba(0,0,0,.06)}.nz-bm input.nz-bm-search:disabled{background:#f4f4f4;color:#999}.nz-bm button.nz-bm-toggle{position:absolute;left:4px;top:4px;width:36px;height:38px;border:0;background:transparent;font-size:18px;cursor:pointer}.nz-bm-list{display:none;position:absolute;z-index:10060;top:calc(100% + 6px);right:0;left:0;max-height:280px;overflow:auto;background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 14px 35px rgba(0,0,0,.16);padding:5px}.nz-bm.open .nz-bm-list{display:block}.nz-bm-opt{display:block;width:100%;border:0;background:#fff;padding:10px 11px;border-radius:9px;text-align:right;font:inherit;cursor:pointer}.nz-bm-opt:hover{background:#f1f2f4}.nz-bm-empty{padding:12px;text-align:center;color:#777;font-size:13px}
 `;document.head.appendChild(s);
}
function makeCombo(field,kind,name){
  if(!field)return null;
  let old=originalControl(field);
  let wrap=field.querySelector('.nz-bm[data-kind="'+kind+'"]');
  if(wrap)return wrap._api||null;
  if(!old)return null;
  const initial=old.value||'';
  const hidden=document.createElement('input');hidden.type='hidden';hidden.name=name;hidden.value=initial;
  old.remove();
  wrap=document.createElement('div');wrap.className='nz-bm';wrap.dataset.kind=kind;
  wrap.innerHTML='<input type="text" class="nz-bm-search" autocomplete="off" spellcheck="false"><button type="button" class="nz-bm-toggle" aria-label="باز کردن فهرست">⌄</button><div class="nz-bm-list"></div>';
  field.appendChild(hidden);field.appendChild(wrap);
  const input=wrap.querySelector('.nz-bm-search'),toggle=wrap.querySelector('.nz-bm-toggle'),list=wrap.querySelector('.nz-bm-list');
  const api={field,wrap,hidden,input,toggle,list,kind,items:[],selected:initial};
  wrap._api=api;
  function label(){return kind==='brand'?'برند':'مدل'}
  function close(){wrap.classList.remove('open')}
  function render(q=''){
    const items=filterItems(api.items,q);list.innerHTML='';
    if(!items.length){const d=document.createElement('div');d.className='nz-bm-empty';d.textContent=api.items.length?'موردی پیدا نشد':'گزینه‌ای موجود نیست';list.appendChild(d);return}
    items.forEach(v=>{const b=document.createElement('button');b.type='button';b.className='nz-bm-opt';b.textContent=v;b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>select(v));list.appendChild(b)})
  }
  function open(){if(input.disabled)return;render(input.value===api.selected?'':input.value);wrap.classList.add('open')}
  function select(v){api.selected=v;hidden.value=v;input.value=v;close();hidden.dispatchEvent(new Event('change',{bubbles:true}));if(kind==='brand')onBrandChosen(v)}
  api.setItems=(items,selected='')=>{api.items=[...new Set((items||[]).filter(Boolean))];api.selected=api.items.includes(selected)?selected:'';hidden.value=api.selected;input.value=api.selected;input.disabled=false;toggle.disabled=false;input.placeholder='انتخاب یا جستجوی '+label();render('')};
  api.disable=(text)=>{api.items=[];api.selected='';hidden.value='';input.value='';input.disabled=true;toggle.disabled=true;input.placeholder=text;list.innerHTML=''};
  input.addEventListener('focus',open);input.addEventListener('click',open);input.addEventListener('input',()=>{hidden.value='';api.selected='';render(input.value);wrap.classList.add('open')});
  input.addEventListener('blur',()=>setTimeout(()=>{if(!wrap.contains(document.activeElement)){const exact=api.items.find(v=>norm(v)===norm(input.value));if(exact)select(exact);else{input.value=api.selected;hidden.value=api.selected;close()} }},120));
  toggle.addEventListener('mousedown',e=>e.preventDefault());toggle.addEventListener('click',()=>wrap.classList.contains('open')?close():open());
  input.addEventListener('keydown',e=>{if(e.key==='Escape'){input.value=api.selected;close()}else if(e.key==='Enter'){const first=list.querySelector('.nz-bm-opt');if(first&&wrap.classList.contains('open')){e.preventDefault();first.click()}}});
  return api;
}
function getSub(){return document.querySelector('[name="subcategory"]')?.value||''}
async function fetchCatalog(brand=''){
  const sub=getSub();const id=++loadId;
  const r=await fetch('/nz-models.php?subcategory='+encodeURIComponent(sub)+'&brand='+encodeURIComponent(brand),{cache:'no-store',credentials:'same-origin'});
  if(!r.ok)throw new Error('HTTP '+r.status);
  const data=await r.json();if(id!==loadId)return null;return data;
}
async function onBrandChosen(brand){
  const modelField=fieldByLabel(['مدل']);if(!modelField)return;
  const modelApi=makeCombo(modelField,'model','attributes[مدل]');if(!modelApi)return;
  modelApi.disable('در حال بارگذاری مدل‌ها…');
  try{const data=await fetchCatalog(brand);if(!data)return;const models=Array.isArray(data.models)?data.models:[];state.models=models;modelApi.setItems(models,'')}
  catch(_){modelApi.setItems([],'')}
}
async function build(){
  const sub=getSub();
  const brandField=fieldByLabel(['برند','برند/سازنده']);
  const modelField=fieldByLabel(['مدل']);
  if(!brandField&&!modelField)return;
  const brandName=originalControl(brandField)?.name||'attributes[برند]';
  const modelName=originalControl(modelField)?.name||'attributes[مدل]';
  const brandApi=brandField?makeCombo(brandField,'brand',brandName):null;
  const modelApi=modelField?makeCombo(modelField,'model',modelName):null;
  if(sub!==state.sub){state.sub=sub;state.brand='';state.model='';state.brands=[];state.models=[]}
  if(brandApi){brandApi.disable('در حال بارگذاری برندها…');
    try{const data=await fetchCatalog('');if(!data)return;const brands=Array.isArray(data.brands)?data.brands:[];state.brands=brands;brandApi.setItems(brands,'');if(modelApi)modelApi.disable('ابتدا برند را انتخاب کنید')}
    catch(_){brandApi.setItems([],'');if(modelApi)modelApi.disable('ابتدا برند را انتخاب کنید')}
  }
}
function init(){injectStyle();build();let t=0;const mo=new MutationObserver(()=>{clearTimeout(t);t=setTimeout(build,30)});mo.observe(document.body,{childList:true,subtree:true});document.addEventListener('change',e=>{if(e.target?.name==='category'||e.target?.name==='subcategory'){clearTimeout(t);t=setTimeout(build,0)}});document.addEventListener('click',e=>{document.querySelectorAll('.nz-bm.open').forEach(w=>{if(!w.contains(e.target))w.classList.remove('open')})})}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
