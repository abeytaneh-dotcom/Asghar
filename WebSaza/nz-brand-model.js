(()=>{
'use strict';

const state={sub:'',brand:'',model:''};
let requestSeq=0;

function norm(v){return String(v||'').toLowerCase().replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[ًٌٍَُِّْـ]/g,'').replace(/\s+/g,' ').trim()}
function fieldByLabel(labels){
  const box=document.getElementById('nzSpecs');if(!box)return null;
  for(const field of box.querySelectorAll('.field')){
    const label=(field.querySelector('label')?.textContent||'').replace(/\s*\*\s*$/,'').trim();
    if(labels.includes(label))return field;
  }
  return null;
}
function control(field){return field?.querySelector('input[name^="attributes["],select[name^="attributes["]')||null}
function filtered(items,q){
  const words=norm(q).split(' ').filter(Boolean);
  if(!words.length)return items.slice();
  return items.filter(v=>{const h=norm(v);return words.every(w=>h.includes(w))}).sort((a,b)=>{
    const aa=norm(a),bb=norm(b),qq=norm(q);return (aa.startsWith(qq)?0:1)-(bb.startsWith(qq)?0:1)||aa.localeCompare(bb,'fa');
  });
}
function injectStyle(){
  if(document.getElementById('nzBrandModelStyle'))return;
  const s=document.createElement('style');s.id='nzBrandModelStyle';s.textContent=`
  .nz-bm{position:relative;width:100%}.nz-bm-search{width:100%;min-height:46px;padding:0 14px 0 42px;border:1px solid #d7d9de;border-radius:12px;background:#fff;font:inherit;box-sizing:border-box;outline:none}.nz-bm-search:focus{border-color:#111;box-shadow:0 0 0 2px rgba(0,0,0,.06)}.nz-bm-search:disabled{background:#f4f4f4;color:#999}.nz-bm-toggle{position:absolute;left:4px;top:4px;width:36px;height:38px;border:0;background:transparent;font-size:18px;cursor:pointer}.nz-bm-list{display:none;position:absolute;z-index:10060;top:calc(100% + 6px);right:0;left:0;max-height:280px;overflow:auto;background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 14px 35px rgba(0,0,0,.16);padding:5px}.nz-bm.open .nz-bm-list{display:block}.nz-bm-opt{display:block;width:100%;border:0;background:#fff;padding:10px 11px;border-radius:9px;text-align:right;font:inherit;cursor:pointer}.nz-bm-opt:hover{background:#f1f2f4}.nz-bm-empty{padding:12px;text-align:center;color:#777;font-size:13px}`;
  document.head.appendChild(s);
}
function makeCombo(field,kind,name){
  if(!field)return null;
  const existing=field.querySelector('.nz-bm[data-kind="'+kind+'"]');
  if(existing&&existing._api)return existing._api;
  const old=control(field);if(!old)return null;
  const initial=old.value||'';
  const hidden=document.createElement('input');hidden.type='hidden';hidden.name=name;hidden.value=initial;
  old.remove();
  const wrap=document.createElement('div');wrap.className='nz-bm';wrap.dataset.kind=kind;
  wrap.innerHTML='<input type="text" class="nz-bm-search" autocomplete="off" spellcheck="false"><button type="button" class="nz-bm-toggle" aria-label="باز کردن فهرست">⌄</button><div class="nz-bm-list"></div>';
  field.appendChild(hidden);field.appendChild(wrap);
  const input=wrap.querySelector('.nz-bm-search'),toggle=wrap.querySelector('.nz-bm-toggle'),list=wrap.querySelector('.nz-bm-list');
  const api={field,wrap,hidden,input,toggle,list,kind,items:[],selected:initial};wrap._api=api;
  const title=kind==='brand'?'برند':'مدل';
  const close=()=>wrap.classList.remove('open');
  const render=(q='')=>{const items=filtered(api.items,q);list.innerHTML='';if(!items.length){const d=document.createElement('div');d.className='nz-bm-empty';d.textContent=api.items.length?'موردی پیدا نشد':'گزینه‌ای موجود نیست';list.appendChild(d);return}items.forEach(v=>{const b=document.createElement('button');b.type='button';b.className='nz-bm-opt';b.textContent=v;b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>select(v));list.appendChild(b)})};
  const open=()=>{if(input.disabled)return;render(input.value===api.selected?'':input.value);wrap.classList.add('open')};
  const select=v=>{api.selected=v;hidden.value=v;input.value=v;close();hidden.dispatchEvent(new Event('change',{bubbles:true}));if(kind==='brand'){state.brand=v;state.model='';loadModels(v)}};
  api.setItems=(items,selected='')=>{api.items=[...new Set((items||[]).filter(Boolean))];api.selected=api.items.includes(selected)?selected:'';hidden.value=api.selected;input.value=api.selected;input.disabled=false;toggle.disabled=false;input.placeholder='انتخاب یا جستجوی '+title;render('')};
  api.disable=text=>{api.items=[];api.selected='';hidden.value='';input.value='';input.disabled=true;toggle.disabled=true;input.placeholder=text;list.innerHTML=''};
  input.addEventListener('focus',open);input.addEventListener('click',open);input.addEventListener('input',()=>{hidden.value='';api.selected='';render(input.value);wrap.classList.add('open')});
  input.addEventListener('blur',()=>setTimeout(()=>{if(!wrap.contains(document.activeElement)){const exact=api.items.find(v=>norm(v)===norm(input.value));if(exact)select(exact);else{input.value=api.selected;hidden.value=api.selected;close()}}},120));
  input.addEventListener('keydown',e=>{if(e.key==='Escape'){input.value=api.selected;close()}else if(e.key==='Enter'){const first=list.querySelector('.nz-bm-opt');if(first&&wrap.classList.contains('open')){e.preventDefault();first.click()}}});
  toggle.addEventListener('mousedown',e=>e.preventDefault());toggle.addEventListener('click',()=>wrap.classList.contains('open')?close():open());
  return api;
}
function getSub(){return document.querySelector('[name="subcategory"]')?.value||''}
async function fetchCatalog(brand=''){
  const sub=getSub();const seq=++requestSeq;
  const r=await fetch('/nz-models.php?subcategory='+encodeURIComponent(sub)+'&brand='+encodeURIComponent(brand),{cache:'no-store',credentials:'same-origin'});
  if(!r.ok)throw new Error('HTTP '+r.status);
  const data=await r.json();if(seq!==requestSeq)return null;return data;
}
async function loadModels(brand,selected=''){
  const field=fieldByLabel(['مدل']);if(!field)return;
  const name=control(field)?.name||field.querySelector('input[type="hidden"][name^="attributes["]')?.name||'attributes[مدل]';
  const api=makeCombo(field,'model',name);if(!api)return;
  api.disable('در حال بارگذاری مدل‌ها…');
  try{const data=await fetchCatalog(brand);if(!data)return;const items=Array.isArray(data.models)?data.models:[];api.setItems(items,selected);state.model=api.hidden.value}
  catch(_){api.setItems([],selected)}
}
async function build(force=false){
  const sub=getSub();
  const brandField=fieldByLabel(['برند','برند/سازنده']);
  const modelField=fieldByLabel(['مدل']);
  if(!brandField&&!modelField)return;
  const sameSub=sub===state.sub;
  const alreadyReady=(!brandField||!!brandField.querySelector('.nz-bm[data-kind="brand"]'))&&(!modelField||!!modelField.querySelector('.nz-bm[data-kind="model"]'));
  if(!force&&sameSub&&alreadyReady)return;
  const brandOld=control(brandField);const modelOld=control(modelField);
  const initialBrand=brandOld?.value||brandField?.querySelector('input[type="hidden"]')?.value||state.brand||'';
  const initialModel=modelOld?.value||modelField?.querySelector('input[type="hidden"]')?.value||state.model||'';
  const brandName=brandOld?.name||brandField?.querySelector('input[type="hidden"]')?.name||'attributes[برند]';
  const modelName=modelOld?.name||modelField?.querySelector('input[type="hidden"]')?.name||'attributes[مدل]';
  const brandApi=brandField?makeCombo(brandField,'brand',brandName):null;
  const modelApi=modelField?makeCombo(modelField,'model',modelName):null;
  state.sub=sub;
  if(!brandApi)return;
  brandApi.disable('در حال بارگذاری برندها…');
  if(modelApi)modelApi.disable('ابتدا برند را انتخاب کنید');
  try{
    const data=await fetchCatalog('');if(!data)return;
    const brands=Array.isArray(data.brands)?data.brands:[];
    brandApi.setItems(brands,initialBrand);
    state.brand=brandApi.hidden.value;
    if(state.brand)await loadModels(state.brand,initialModel);
  }catch(_){brandApi.setItems([],initialBrand)}
}
function init(){
  injectStyle();build(true);
  let timer=0;
  const mo=new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(()=>build(false),40)});
  mo.observe(document.body,{childList:true,subtree:true});
  document.addEventListener('change',e=>{if(e.target?.name==='category'||e.target?.name==='subcategory'){state.sub='';state.brand='';state.model='';clearTimeout(timer);timer=setTimeout(()=>build(true),0)}});
  document.addEventListener('click',e=>{document.querySelectorAll('.nz-bm.open').forEach(w=>{if(!w.contains(e.target))w.classList.remove('open')})});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
