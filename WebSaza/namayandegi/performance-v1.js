/* PERFORMANCE_V1 — low-cost rendering for large order lists */
(()=>{
  'use strict';
  const idle=window.requestIdleCallback||((fn)=>setTimeout(fn,120));

  const style=document.createElement('style');
  style.textContent=`
    .market-card,.market-card-host,.lead[id^="lead-"],.order-event{
      content-visibility:auto;
      contain-intrinsic-size:120px;
    }
    .perf-hidden{display:none!important}
    .perf-more-wrap{display:flex;justify-content:center;padding:12px 0 6px}
    .perf-more-btn{min-height:42px;padding:9px 18px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;color:#222;font:inherit;font-weight:800;cursor:pointer}
  `;
  document.head.appendChild(style);

  const optimizeMedia=()=>{
    document.querySelectorAll('img').forEach((img,i)=>{
      if(i>1 && !img.hasAttribute('loading')) img.loading='lazy';
      if(!img.hasAttribute('decoding')) img.decoding='async';
      img.fetchPriority=i===0?'high':'low';
    });
  };

  const chunkList=(parent,items,size=40)=>{
    if(!parent||items.length<=size||parent.dataset.perfChunked==='1')return;
    parent.dataset.perfChunked='1';
    let shown=size;
    const apply=()=>items.forEach((el,i)=>el.classList.toggle('perf-hidden',i>=shown));
    apply();
    const wrap=document.createElement('div');
    wrap.className='perf-more-wrap';
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='perf-more-btn';
    btn.textContent='نمایش سفارش‌های بیشتر';
    btn.addEventListener('click',()=>{
      shown=Math.min(items.length,shown+size);
      apply();
      if(shown>=items.length)wrap.remove();
    },{passive:true});
    wrap.appendChild(btn);
    parent.insertAdjacentElement('afterend',wrap);
  };

  const optimizeLists=()=>{
    const groups=new Map();
    document.querySelectorAll('.lead[id^="lead-"]').forEach(el=>{
      const p=el.parentElement;
      if(!p)return;
      if(!groups.has(p))groups.set(p,[]);
      groups.get(p).push(el);
    });
    groups.forEach((items,parent)=>chunkList(parent,items,40));
  };

  const optimize=()=>{optimizeMedia();optimizeLists();};
  idle(optimize);
  window.addEventListener('pageshow',()=>idle(optimize),{passive:true});
})();
