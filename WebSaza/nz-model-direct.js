(()=>{
'use strict';
let req=0;
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function getBrand(box){return box?.querySelector('select[name="attributes[برند]"],select[name="attributes[برند/سازنده]"]')||null}
function getModel(box){return box?.querySelector('select[name="attributes[مدل]"],input[name="attributes[مدل]"]')||null}
function ensureSelect(el){
  if(!el)return null;
  if(el.tagName==='SELECT')return el;
  const s=document.createElement('select');
  [...el.attributes].forEach(a=>{if(!['type','placeholder','list','inputmode'].includes(a.name))s.setAttribute(a.name,a.value)});
  el.replaceWith(s);
  return s;
}
async function loadModels(brand){
  const box=brand.closest('#nzSpecs')||document.getElementById('nzSpecs');
  if(!box)return;
  let model=ensureSelect(getModel(box));
  if(!model)return;
  model.dataset.nzControlled='1';
  model.disabled=true;
  model.innerHTML='<option value="">در حال بارگذاری مدل‌ها…</option>';
  const sub=document.querySelector('[name="subcategory"]')?.value||'';
  const my=++req;
  try{
    const r=await fetch('/nz-models.php?subcategory='+encodeURIComponent(sub)+'&brand='+encodeURIComponent(brand.value),{cache:'no-store',credentials:'same-origin'});
    if(!r.ok)throw new Error('HTTP '+r.status);
    const data=await r.json();
    if(my!==req)return;
    const models=Array.isArray(data.models)&&data.models.length?data.models:['سایر مدل'];
    model.innerHTML='<option value="">انتخاب مدل</option>'+models.map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join('');
    model.disabled=false;
  }catch(_){
    if(my!==req)return;
    model.innerHTML='<option value="">انتخاب مدل</option><option value="سایر مدل">سایر مدل</option>';
    model.disabled=false;
  }
}
function bindBox(){
  const box=document.getElementById('nzSpecs');
  if(!box)return;
  const brand=getBrand(box);
  if(!brand||brand.dataset.nzModelDirect==='1')return;
  brand.dataset.nzModelDirect='1';
  brand.addEventListener('change',()=>loadModels(brand));
  if(brand.value)loadModels(brand);
}
function init(){
  bindBox();
  const mo=new MutationObserver(()=>bindBox());
  mo.observe(document.body,{childList:true,subtree:true});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
