/* SEEN_V3 — mark an order seen only when the representative actually interacts with it */
(()=>{
  const sent=new Set();
  const tokenFor=el=>el.querySelector('input[name="csrf"]')?.value||document.querySelector('input[name="csrf"]')?.value||'';
  const aidFor=el=>el?.dataset?.assignmentId||(el?.id||'').replace(/^lead-/,'');
  const mark=async el=>{
    const aid=aidFor(el);if(!aid||sent.has(aid))return;const csrf=tokenFor(el);if(!csrf)return;sent.add(aid);
    const fd=new FormData();fd.set('action','mark_seen');fd.set('assignment_id',aid);fd.set('csrf',csrf);
    try{const r=await fetch('./',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)sent.delete(aid);}catch(e){sent.delete(aid);}
  };
  const bind=()=>document.querySelectorAll('.lead[id^="lead-"],.market-card-host[data-assignment-id]').forEach(el=>{
    if(el.dataset.seenBound==='1')return;el.dataset.seenBound='1';
    el.addEventListener('click',e=>{if(e.target.closest('a[href^="tel:"],button.copy,summary,.result input,.result button'))mark(el);},{passive:true});
    el.querySelectorAll('details').forEach(d=>d.addEventListener('toggle',()=>{if(d.open)mark(el);}));
  });
  document.addEventListener('DOMContentLoaded',bind);new MutationObserver(bind).observe(document.documentElement,{childList:true,subtree:true});
})();
