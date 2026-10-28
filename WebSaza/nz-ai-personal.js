(()=>{
  'use strict';
  const cfg=JSON.parse(document.getElementById('nz-ai-config')?.textContent||'{}');
  const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  function init(){
    const panel=document.getElementById('nzai-panel'),root=document.getElementById('nz-ai');if(!panel||root.dataset.mode!=='search')return;
    const section=node('section',undefined,'nzai-personal');section.append(node('h3','پیشنهاد برای تو'));root.querySelector('.nzai-examples').after(section);
    if(!cfg.logged_in){const p=node('p','برای ادامهٔ جستجوهای قبلی و پیشنهادهای شخصی وارد حسابت شو.');const a=node('a','ورود به حساب');a.href='/nz?page=login';section.append(p,a);return;}
    const label=node('label',undefined,'nzai-personal-consent'),enabled=node('input');enabled.type='checkbox';enabled.disabled=true;label.append(enabled,node('span','اجازه می‌دهم از جستجوها و بازخوردهایم برای پیشنهادهای شخصی تا ۳۰ روز استفاده شود.'));section.append(label);
    const collection=node('details'),summary=node('summary','جستجوهای قبلی و آگهی‌های پیشنهادی'),content=node('div'),status=node('p');collection.append(summary,content);status.setAttribute('role','status');section.append(collection,status);let seq=0;
    const api=async data=>{const r=await fetch('/nz-ai-personal-api.php',data?{method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({csrf:cfg.csrf,...data})}:{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'پیشنهادها فعلاً در دسترس نیستند.');return j;};
    function render(j){
      enabled.checked=j.enabled;collection.hidden=!j.enabled;summary.textContent=j.cards.length?j.cards.length.toLocaleString('fa-IR')+' آگهی پیشنهادی و ادامهٔ جستجوهای قبلی':'ادامهٔ جستجوهای قبلی و پیشنهادها';content.replaceChildren();if(!j.enabled){content.append(node('p','با فعال‌کردن این گزینه، از جستجوهای بعدی‌ات یاد می‌گیرم. خاموش‌کردن آن، سابقه و بازخوردهای شخصی را پاک می‌کند.'));return;}
      const history=node('div',undefined,'nzai-history');j.history.forEach(h=>{const b=node('button','ادامهٔ '+h.label);b.type='button';b.onclick=()=>document.dispatchEvent(new CustomEvent('nzai:recall',{detail:{id:h.id}}));history.append(b);});content.append(history);
      if(!j.cards.length){content.append(node('p',j.history.length?'فعلاً آگهی مطابق جستجوهای قبلی‌ات پیدا نشد. می‌توانی همان جستجو را ادامه بدهی.':'بعد از یک جستجوی کامل، پیشنهادهای مرتبط اینجا ظاهر می‌شوند.'));return;}
      const grid=node('div',undefined,'nzai-results');j.cards.forEach(p=>{const card=node('article',undefined,'nzai-card');const a=node('a',p.title);a.href=p.url;card.append(a,node('p',p.reason,'nzai-reason'),node('p',p.city+' · '+(p.price?Number(p.price).toLocaleString('fa-IR')+' تومان':'توافقی')));
        const like=node('button',p.liked?'پسندیده شد؛ لغو':'بیشتر شبیه این'),dismiss=node('button','علاقه ندارم');like.type=dismiss.type='button';like.setAttribute('aria-pressed',String(p.liked));
        like.onclick=()=>react(p.id,p.liked?'clear':'like');dismiss.onclick=()=>react(p.id,'dismiss');card.append(like,dismiss);grid.append(card);});content.append(grid);
    }
    async function refresh(){const turn=++seq;try{const j=await api();if(turn===seq){render(j);enabled.disabled=false;}}catch(e){if(turn===seq){status.textContent=e.message;enabled.disabled=false;}}}
    async function react(id,value){const turn=++seq;content.querySelectorAll('button').forEach(b=>b.disabled=true);try{const j=await api({action:'reaction',id,value});if(turn!==seq)return;render(j);status.replaceChildren(node('span',value==='dismiss'?'این آگهی از پیشنهادها کنار گذاشته شد.':'بازخوردت در پیشنهادهای بعدی اثر می‌گذارد.'));if(value==='dismiss'){const undo=node('button','بازگردانی');undo.type='button';undo.onclick=()=>react(id,'clear');status.append(undo);}}catch(e){if(turn===seq){status.textContent=e.message;content.querySelectorAll('button').forEach(b=>b.disabled=false);}}}
    enabled.onchange=async()=>{const desired=enabled.checked,turn=++seq;enabled.disabled=true;try{const j=await api({action:'consent',enabled:desired?'1':'0'});if(turn===seq){render(j);status.textContent=desired?'پیشنهادهای شخصی با اجازهٔ شما فعال شد.':'سابقه و بازخوردهای شخصی حذف شدند.';}}catch(e){if(turn===seq){enabled.checked=!desired;status.textContent=e.message;}}finally{if(turn===seq)enabled.disabled=false;}};
    document.addEventListener('nzai:turn',refresh);document.addEventListener('nzai:forgotten',refresh);refresh();
  }
  if(document.readyState==='complete')init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();
