(()=>{
'use strict';

const isBrandField=(el)=>{
  if(!el||el.tagName!=='SELECT')return false;
  return el.name==='attributes[برند]'||el.name==='attributes[برند/سازنده]';
};

function refreshModelForBrand(brand){
  if(!isBrandField(brand))return;
  const box=brand.closest('#nzSpecs')||document.getElementById('nzSpecs');
  if(!box)return;
  const model=box.querySelector('[name="attributes[مدل]"]');
  if(!model)return;

  // nz-spec-enforcer already owns the catalog and brand->model mapping.
  // Its first pass converts the model field to a controlled select while the
  // brand is still empty. Remove the guard once, then re-fire the brand event
  // only on the element itself so the enforcer rebuilds model options using
  // the currently selected brand without causing a document-level loop.
  model.removeAttribute('data-nz-controlled');
  brand.dispatchEvent(new Event('change',{bubbles:false}));
}

document.addEventListener('change',e=>{
  const brand=e.target;
  if(!isBrandField(brand))return;
  setTimeout(()=>refreshModelForBrand(brand),0);
});

// Also repair an already-selected brand when the wizard/spec fields are
// recreated after choosing a category/subcategory.
function repairCurrent(){
  const box=document.getElementById('nzSpecs');
  if(!box)return;
  const brand=box.querySelector('select[name="attributes[برند]"],select[name="attributes[برند/سازنده]"]');
  if(brand&&brand.value)setTimeout(()=>refreshModelForBrand(brand),0);
}

const mo=new MutationObserver(()=>repairCurrent());
if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',()=>{mo.observe(document.body,{childList:true,subtree:true});repairCurrent()},{once:true});
}else{
  mo.observe(document.body,{childList:true,subtree:true});repairCurrent();
}
})();