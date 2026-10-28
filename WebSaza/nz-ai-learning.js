(()=>{
  'use strict';
  const cfg=JSON.parse(document.getElementById('nz-ai-config')?.textContent||'{}');
  const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;return e;};
  async function api(data){const r=await fetch('/nz-ai-learning-api.php',data?{method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({csrf:cfg.csrf,...data})}:{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'ارتباط برقرار نشد.');return j;}
  function init(){
    document.querySelectorAll('[data-ai-review]').forEach(b=>{b.onclick=async()=>{const status=document.getElementById('nzai-admin-status');b.disabled=true;try{await api({action:'review',id:b.dataset.aiReview,decision:b.dataset.decision});status.textContent='تصمیم ذخیره شد؛ تشخیص‌های بعدی از نسخهٔ به‌روز استفاده می‌کنند.';b.closest('article').remove();}catch(e){status.textContent=e.message;b.disabled=false;}};});
    const panel=document.getElementById('nzai-panel');if(!panel||!cfg.logged_in)return;
    const box=node('details'),summary=node('summary','حافظه و بهبود دستیار');box.className='nzai-learning';box.append(summary);
    const status=node('p');status.setAttribute('role','status');
    const label=node('label'),remember=node('input');remember.type='checkbox';label.append(remember,node('span','شهر و بودجهٔ جستجوی من را برای دفعات بعد به خاطر بسپار (۳۰ روز)'));box.append(label);
    remember.disabled=true;api().then(j=>{remember.checked=j.remember;remember.disabled=false;}).catch(e=>{status.textContent=e.message;});
    remember.onchange=async()=>{const desired=remember.checked;remember.disabled=true;try{await api({action:'remember',enabled:desired?'1':'0'});status.textContent=desired?'یادآوری شهر و بودجه با اجازهٔ شما فعال شد.':'یادآوری شهر و بودجه غیرفعال و حافظهٔ ذخیره‌شده حذف شد.';}catch(e){remember.checked=!desired;status.textContent=e.message;}finally{remember.disabled=false;}};
    const forget=node('button','پاک‌کردن حافظه و بازخوردهای من');forget.type='button';forget.onclick=async()=>{forget.disabled=true;try{await api({action:'forget'});remember.checked=false;status.textContent='حافظه و تمام نمونه‌های ارسالی شما حذف شدند.';document.dispatchEvent(new CustomEvent('nzai:forgotten'));}catch(e){status.textContent=e.message;}finally{forget.disabled=false;}};box.append(forget);
    const form=node('form');form.hidden=true;form.append(node('h3','اصلاح دسته‌بندی دستیار'));
    const phraseLabel=node('label','نام کوتاه کالا یا خدمت'),phrase=node('input');phrase.name='phrase';phrase.maxLength=80;phrase.minLength=2;phrase.required=true;phrase.placeholder='مثلاً مبل چستر';phraseLabel.append(phrase);
    const catLabel=node('label','دستهٔ درست'),cat=node('select'),subLabel=node('label','زیر‌دستهٔ درست'),sub=node('select');cat.required=sub.required=true;catLabel.append(cat);subLabel.append(sub);
    Object.keys(cfg.taxonomy||{}).forEach(v=>{const o=node('option',v);o.value=v;cat.append(o);});
    const updateSub=()=>{sub.replaceChildren();(cfg.taxonomy?.[cat.value]||[]).forEach(v=>{const o=node('option',v);o.value=v;sub.append(o);});};cat.onchange=updateSub;updateSub();
    const consentLabel=node('label'),consent=node('input');consent.type='checkbox';consent.required=true;consentLabel.append(consent,node('span','فقط این نام کالا و دستهٔ اصلاح‌شده را برای بهبود دستیار ارسال می‌کنم؛ پس از تأیید مدیر استفاده شود.'));
    const submit=node('button','ارسال اصلاح برای بررسی');submit.type='submit';form.append(phraseLabel,catLabel,subLabel,consentLabel,submit);let turn=null;
    document.addEventListener('nzai:turn',e=>{turn=e.detail.turn;form.hidden=!turn;consent.checked=false;phrase.value='';const p=e.detail.plan;if(p?.category&&cfg.taxonomy?.[p.category]){cat.value=p.category;updateSub();if(cfg.taxonomy[p.category].includes(p.subcategory))sub.value=p.subcategory;}});
    form.onsubmit=async e=>{e.preventDefault();if(!turn||!consent.checked||!form.reportValidity())return;submit.disabled=true;try{await api({action:'feedback',turn,phrase:phrase.value,category:cat.value,subcategory:sub.value,consent:'1'});status.textContent='اصلاح شما ثبت شد و پس از بررسی مدیر در تشخیص‌های بعدی اثر می‌گذارد.';phrase.value='';consent.checked=false;}catch(err){status.textContent=err.message;}finally{submit.disabled=false;}};
    box.append(form,status);panel.append(box);
  }
  if(document.readyState==='complete')init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();
