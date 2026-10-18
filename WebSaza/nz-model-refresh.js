(()=>{
'use strict';

const isBrandField=(el)=>{
  if(!el||el.tagName!=='SELECT') return false;
  return el.name==='attributes[برند]'||el.name==='attributes[برند/سازنده]';
};

function refreshModelForBrand(brand){
  if(!isBrandField(brand)) return;
  const box=brand.closest('#nzSpecs')||document.getElementById('nzSpecs');
  if(!box) return;

  const model=box.querySelector('[name="attributes[مدل]"]');
  if(!model) return;

  // nz-spec-enforcer keeps the complete catalog and its direct change
  // listener on the brand select knows how to populate the correct models.
  // The model select may already be marked as controlled, which previously
  // prevented its options from being rebuilt. Clear that guard once and fire
  // a non-bubbling change only on the brand element. This invokes the direct
  // catalog listener without re-triggering this document-level handler.
  model.removeAttribute('data-nz-controlled');
  brand.dispatchEvent(new Event('change',{bubbles:false}));
}

document.addEventListener('change',e=>{
  const brand=e.target;
  if(!isBrandField(brand)) return;
  setTimeout(()=>refreshModelForBrand(brand),20);
});

// One safe initial repair for restored forms. No MutationObserver is used;
// continuously rebuilding the select can make the dropdown impossible to open.
function repairInitial(){
  const box=document.getElementById('nzSpecs');
  if(!box) return;
  const brand=box.querySelector('select[name="attributes[برند]"],select[name="attributes[برند/سازنده]"]');
  if(brand&&brand.value) setTimeout(()=>refreshModelForBrand(brand),30);
}

if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',repairInitial,{once:true});
}else{
  repairInitial();
}
})();