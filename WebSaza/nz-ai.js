(()=>{
  'use strict';
  const cfg=JSON.parse(document.getElementById('nz-ai-config')?.textContent||'{}');
  function init(){
    const root=document.getElementById('nz-ai');if(!root)return;
    const mode=root.dataset.mode,log=document.getElementById('nzai-log'),status=document.getElementById('nzai-status'),output=document.getElementById('nzai-output');
    if(!window.matchMedia('(display-mode: standalone)').matches&&!navigator.standalone){const install=document.createElement('button');install.type='button';install.textContent='نصب نیازستان روی گوشی';install.className='nzai-install';install.onclick=()=>window.nzInstallApp?.();root.querySelector('.nzai-heading').after(install);window.addEventListener('appinstalled',()=>install.remove(),{once:true});}
    const compose=document.getElementById('nzai-compose'),input=document.getElementById('nzai-message'),send=compose.querySelector('button');let busy=false,plan=null;
    const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    const manual=()=>[...document.querySelectorAll('.nz-wizard-head,.nz-wizard-form')];
    const toggle=smart=>{root.classList.toggle('nzai-manual-only',!smart);document.getElementById('nzai-smart').setAttribute('aria-pressed',String(smart));document.getElementById('nzai-manual').setAttribute('aria-pressed',String(!smart));if(mode==='create')manual().forEach(e=>e.classList.toggle('nzai-hide-manual',smart));if(!smart&&mode==='search')document.querySelector('.filter-card input,.mobile-search input')?.focus();};
    document.getElementById('nzai-smart').onclick=()=>toggle(true);document.getElementById('nzai-manual').onclick=()=>toggle(false);
    const bubble=(text,mine=false)=>{log.appendChild(node('div',text,'nzai-msg'+(mine?' mine':'')));log.scrollTop=log.scrollHeight;};
    const request=async data=>{const r=await fetch('/nz-ai-api.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:cfg.csrf,mode,...data}),signal:AbortSignal.timeout(45000)});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'ارتباط برقرار نشد. دوباره تلاش کنید.');return j;};
    function showDraft(p){
      output.replaceChildren();if(!p.title&&!p.category)return;
      const preview=node('section',undefined,'nzai-preview');preview.append(node('h3',p.title||'پیش‌نویس آگهی'),node('p',[p.category,p.subcategory,p.province,p.city,p.district,p.street].filter(Boolean).join(' · ')),node('p',p.description));
      if(p.price!==null)preview.append(node('p',p.price?Number(p.price).toLocaleString('fa-IR')+' تومان':'توافقی'));
      const rows=node('dl');Object.entries(p.attributes||{}).forEach(([label,value])=>{rows.append(node('dt',label),node('dd',value));});preview.append(rows);
      const apply=node('button','تأیید پیش‌نویس و تکمیل عکس و نقشه','nzai-apply');apply.type='button';
      apply.onclick=()=>{const f=document.querySelector('input[name="action"][value="create_post"]')?.form;if(!f){status.textContent='برای ثبت آگهی وارد حساب شوید.';return;}
        const event=new CustomEvent('nz:ai-draft',{detail:plan,cancelable:true});f.dispatchEvent(event);
        if(!event.defaultPrevented){status.textContent='فرم هنوز آماده نیست؛ مسیر دستی را باز کنید.';return;}
        toggle(false);status.textContent='اطلاعات منتقل شد. عکس، نقطه روی نقشه، ویژگی‌ها و اجازهٔ نمایش شماره را بررسی کنید؛ سپس برای بررسی ارسال کنید.';f.scrollIntoView({behavior:'smooth',block:'start'});
      };if(p.ready!==false)preview.append(apply);output.append(preview);
    }
    function showResults(results){
      output.replaceChildren();if(!results.length){output.append(node('p','آگهی مطابق این شرایط پیدا نشد. می‌توانی شهر، بودجه یا مشخصات را تغییر بدهی.'));return;}
      output.append(node('p',`${results.length.toLocaleString('fa-IR')} آگهی مطابق شرایط گفتگو`));const grid=node('div',undefined,'nzai-results');
      results.forEach(p=>{const card=node('article',undefined,'nzai-card'),link=node('a',p.title);link.href=p.url;card.append(node('h3'));card.querySelector('h3').append(link);card.append(node('p',[p.city,p.district].filter(Boolean).join('، ')),node('p',p.price?Number(p.price).toLocaleString('fa-IR')+' تومان':'توافقی'));
        const b=node('button','اطلاعات تماس و گفتگو');b.type='button';const contact=node('div',undefined,'nzai-contact');
        b.onclick=async()=>{b.disabled=true;try{const j=await request({action:'contact',id:p.id});contact.replaceChildren();if(j.phone){const phone=node('a',j.phone);phone.href='tel:'+j.phone;phone.dir='ltr';contact.append(phone);}else contact.append(node('p','صاحب آگهی نمایش شماره را فعال نکرده است.'));if(j.chat_enabled){const chat=node('a','بازکردن آگهی و شروع گفتگو');chat.href=j.url;contact.append(chat);}}catch(e){contact.textContent=e.message;if(!cfg.logged_in){const login=node('a','ورود به حساب');login.href='/nz?page=login';contact.append(login);}}finally{b.disabled=false;}};
        card.append(b,contact);grid.append(card);
      });output.append(grid);
    }
    async function submit(text){if(busy)return;busy=true;send.disabled=true;document.getElementById('nzai-reset').disabled=true;input.disabled=true;status.textContent='دارم بررسی می‌کنم…';bubble(text,true);
      try{const j=await request({message:text});plan=j.plan;bubble(plan.reply||'اطلاعات دریافت شد.');status.textContent='';input.value='';if(mode==='create')showDraft(plan);else if(plan.ready!==false)showResults(j.results||[]);else output.replaceChildren();
        root.querySelector('.nzai-choices')?.remove();if(plan.choices?.length){const options=node('div',undefined,'nzai-choices');const select=node('select');select.setAttribute('aria-label','پاسخ‌های پیشنهادی');const empty=node('option','انتخاب پاسخ پیشنهادی');empty.value='';select.append(empty);plan.choices.forEach(value=>{const o=node('option',value);o.value=value;select.append(o);});select.onchange=()=>{if(select.value)submit(select.value);};options.append(select);compose.before(options);}
        document.dispatchEvent(new CustomEvent('nzai:turn',{detail:{turn:j.turn,plan}}));
      }
      catch(e){status.textContent=e.name==='TimeoutError'?'پاسخ دیر شد. دوباره تلاش کنید.':e.message;}
      finally{busy=false;send.disabled=false;input.disabled=false;document.getElementById('nzai-reset').disabled=false;input.focus();}
    }
    compose.addEventListener('submit',e=>{e.preventDefault();const text=input.value.trim();if(text)submit(text);});
    root.querySelectorAll('[data-example]').forEach(b=>{b.onclick=()=>{input.value=b.dataset.example;input.focus();};});
    const clear=()=>{log.replaceChildren();output.replaceChildren();root.querySelector('.nzai-choices')?.remove();plan=null;input.value='';document.dispatchEvent(new CustomEvent('nzai:turn',{detail:{turn:null,plan:null}}));};
    document.getElementById('nzai-reset').onclick=async()=>{if(busy)return;try{await request({action:'reset'});clear();status.textContent='گفتگوی تازه آماده است.';input.focus();}catch(e){status.textContent=e.message;}};
    document.addEventListener('nzai:forgotten',()=>{clear();status.textContent='حافظه و نمونه‌های آموزشی شما حذف شدند.';});
    busy=true;send.disabled=true;input.disabled=true;
    request({action:'resume'}).then(j=>{if(!j.plan)return;plan=j.plan;(j.history||[]).forEach(h=>bubble(h.text,h.role==='user'));if(mode==='create')showDraft(plan);else if(plan.ready!==false)showResults(j.results||[]);}).catch(e=>{status.textContent=e.message;}).finally(()=>{busy=false;send.disabled=false;input.disabled=false;});
    toggle(true);
    if(mode==='create'&&!cfg.logged_in){status.textContent='برای ثبت آگهی ابتدا وارد حساب شوید.';toggle(false);}
  }
  if(document.readyState==='complete')init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();
