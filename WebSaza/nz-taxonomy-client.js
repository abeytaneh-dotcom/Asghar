(()=>{
'use strict';
let catalog=null,loading=null;
const d=document;
async function load(){if(catalog)return catalog;if(loading)return loading;loading=fetch('/nz-taxonomy.php',{cache:'force-cache',credentials:'same-origin'}).then(r=>r.ok?r.json():Promise.reject()).then(j=>{if(!j?.ok)throw 0;catalog=j;return j}).catch(()=>null);return loading;}
function setOptions(sel,items,placeholder){if(!sel)return;const current=sel.value;sel.innerHTML='<option value="">'+placeholder+'</option>'+items.map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join('');if(items.includes(current))sel.value=current;sel.dispatchEvent(new Event('change',{bubbles:true}))}
function esc(v){return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
async function bind(){const c=await load();if(!c)return;const cat=d.querySelector('[name="category"]'),sub=d.querySelector('[name="subcategory"]'),prov=d.querySelector('[name="province"]'),city=d.querySelector('[name="city"]');if(cat&&cat.dataset.nzTax!=='1'){cat.dataset.nzTax='1';const cur=cat.value;cat.innerHTML='<option value="">انتخاب دسته</option>'+Object.keys(c.categories).map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join('');if(c.categories[cur])cat.value=cur;cat.addEventListener('change',()=>setOptions(sub,c.categories[cat.value]||[],'انتخاب زیر‌دسته'));if(cat.value)setOptions(sub,c.categories[cat.value]||[],'انتخاب زیر‌دسته')}
if(prov&&prov.dataset.nzTax!=='1'){prov.dataset.nzTax='1';const cur=prov.value;prov.innerHTML='<option value="">انتخاب استان</option>'+Object.keys(c.provinces).map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join('');if(c.provinces[cur])prov.value=cur;prov.addEventListener('change',()=>setOptions(city,c.provinces[prov.value]||[],'انتخاب شهر'));if(prov.value)setOptions(city,c.provinces[prov.value]||[],'انتخاب شهر')}}
function init(){bind();new MutationObserver(()=>bind()).observe(d.body,{childList:true,subtree:true})}
if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
