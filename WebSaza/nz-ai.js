(()=>{
  'use strict';
  const cfg=JSON.parse(document.getElementById('nz-ai-config')?.textContent||'{}');
  function init(){
    const root=document.getElementById('nz-ai');if(!root)return;
    const dialog=document.getElementById('nzai-dialog'),launcher=document.getElementById('nzai-launcher'),closeButton=document.getElementById('nzai-close');
    const mode=root.dataset.mode,log=document.getElementById('nzai-log'),status=document.getElementById('nzai-status'),output=document.getElementById('nzai-output');
    if(!window.matchMedia('(display-mode: standalone)').matches&&!navigator.standalone){const install=document.createElement('button');install.type='button';install.textContent='نصب نیازستان روی گوشی';install.className='nzai-install';install.onclick=()=>window.nzInstallApp?.();root.querySelector('.nzai-heading').after(install);window.addEventListener('appinstalled',()=>install.remove(),{once:true});}
    const compose=document.getElementById('nzai-compose'),input=document.getElementById('nzai-message'),send=compose.querySelector('button');let busy=false,plan=null;
    const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    const historyBox=node('details',undefined,'nzai-history-box');historyBox.append(node('summary','گفتگو و پاسخ‌های قبلی'));log.before(historyBox);historyBox.append(log);
    const guide=node('section',undefined,'nzai-guide');guide.setAttribute('aria-label','راهنمای مرحلهٔ جاری');
    const progress=node('p',undefined,'nzai-progress'),question=node('h3'),hint=node('p');question.id='nzai-question';hint.id='nzai-hint';question.setAttribute('aria-live','polite');guide.append(progress,question,hint);compose.before(guide);input.setAttribute('aria-describedby','nzai-question nzai-hint');
    function showQuestion(p){
      root.querySelector('.nzai-choices')?.remove();
      const stage=p?.stage||'subject';root.dataset.stage=stage;const phases=mode==='create'?['کالا یا خدمت','مشخصات','قیمت','مکان و توضیحات','بررسی و تکمیل']:['خواستهٔ شما','محدودهٔ جستجو','بودجه','نتایج'];
      const index=mode==='create'?({attribute:1,price:2,city:3,district:3,street:3,description:3,review:4}[stage]||0):({city:1,budget:2,results:3}[stage]||0);
      progress.textContent=`مرحلهٔ ${(index+1).toLocaleString('fa-IR')} از ${phases.length.toLocaleString('fa-IR')} · ${phases[index]}`;
      question.textContent=p?.reply||(mode==='create'?'چه کالا یا خدمتی می‌خواهی آگهی کنی؟':'دنبال چه کالا یا خدمتی هستی؟');
      hint.textContent=p?.hint||(mode==='create'?'نام کالا را بنویس؛ بعد مشخصات، قیمت و محل را قدم‌به‌قدم می‌پرسم. در پایان عکس و نقشه را خودت تکمیل می‌کنی.':'نام کالا، شهر و اگر خواستی بودجه‌ات را بنویس.');
      input.placeholder=p?.placeholder||(mode==='create'?'مثلاً: گوشی سامسونگ A55 برای فروش دارم':'مثلاً: مبل در شیراز تا ۲۰ میلیون تومان');
      send.textContent=p?.ready?'ارسال اصلاح':'ارسال پاسخ';
      if(p?.choices?.length){
        const options=node('div',undefined,'nzai-choices');
        if(p.choices.length<=6){options.setAttribute('aria-label','پاسخ‌های این مرحله');p.choices.forEach(value=>{const b=node('button',value);b.type='button';b.onclick=()=>submit(value);options.append(b);});}
        else{const label=node('label',`انتخاب ${p.next_attribute||'پاسخ'} از فهرست`),select=node('select');select.id='nzai-answer-select';label.htmlFor=select.id;const empty=node('option','فهرست را باز کن و انتخاب کن…');empty.value='';select.append(empty);p.choices.forEach(value=>{const o=node('option',value);o.value=value;select.append(o);});
          const accept=node('button','تأیید انتخاب');accept.type='button';accept.disabled=true;select.onchange=()=>{accept.disabled=!select.value;};accept.onclick=()=>{if(select.value)submit(select.value);};options.append(label,select,accept);
          if(p.choices.includes('سایر مدل')){const other=node('button','مدلم در فهرست نیست');other.type='button';other.onclick=()=>submit('سایر مدل');options.append(other);}
        }guide.append(options);
      }
    }
    showQuestion(null);
    const manual=()=>[...document.querySelectorAll('.nz-wizard-head,.nz-wizard-form')];
    const toggle=smart=>{root.classList.toggle('nzai-manual-only',!smart);document.getElementById('nzai-smart').setAttribute('aria-pressed',String(smart));document.getElementById('nzai-manual').setAttribute('aria-pressed',String(!smart));if(mode==='create')manual().forEach(e=>e.classList.toggle('nzai-hide-manual',smart));if(!smart&&mode==='search')document.querySelector('.filter-card input,.mobile-search input')?.focus();};
    function closed(){if(dialog.open)return;launcher.hidden=false;launcher.setAttribute('aria-expanded','false');document.documentElement.classList.remove('nzai-modal-open');toggle(false);}
    function closeAssistant(focus=true){if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');closed();if(focus)launcher.focus({preventScroll:true});}
    function openAssistant(){toggle(true);if(!dialog.open){if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');}launcher.hidden=true;launcher.setAttribute('aria-expanded','true');document.documentElement.classList.add('nzai-modal-open');closeButton.focus({preventScroll:true});}
    launcher.onclick=openAssistant;closeButton.onclick=()=>closeAssistant();
    dialog.addEventListener('cancel',event=>{event.preventDefault();closeAssistant();});dialog.addEventListener('close',closed);
    dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)closeAssistant();});
    dialog.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeAssistant();}else if(event.key==='Tab'){const controls=[...dialog.querySelectorAll('button,a[href],input,select,textarea,summary')].filter(e=>!e.disabled&&!e.closest('[hidden]')&&e.getClientRects().length);const first=controls[0],last=controls.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}});
    function positionLauncher(){let bottom=16;document.querySelectorAll('.mobile-nav,.nzux-detail-cta,.compose').forEach(el=>{const r=el.getBoundingClientRect();const style=getComputedStyle(el);if(style.position==='fixed'&&style.display!=='none'&&r.height&&r.top<innerHeight&&r.bottom>0)bottom=Math.max(bottom,innerHeight-r.top+12);});document.documentElement.style.setProperty('--nzai-dock-bottom',bottom+'px');}
    positionLauncher();window.addEventListener('resize',positionLauncher);window.visualViewport?.addEventListener('resize',positionLauncher);if('ResizeObserver' in window){const observer=new ResizeObserver(positionLauncher);document.querySelectorAll('.mobile-nav,.nzux-detail-cta,.compose').forEach(el=>observer.observe(el));}
    document.getElementById('nzai-smart').onclick=openAssistant;document.getElementById('nzai-manual').onclick=()=>closeAssistant(false);
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
        closeAssistant(false);status.textContent='اطلاعات منتقل شد. عکس، نقطه روی نقشه، ویژگی‌ها و اجازهٔ نمایش شماره را بررسی کنید؛ سپس برای بررسی ارسال کنید.';f.scrollIntoView({behavior:'smooth',block:'start'});f.querySelector('[name="title"]')?.focus({preventScroll:true});
      };if(p.ready!==false){preview.append(apply);output.append(preview);}else{const collected=node('details',undefined,'nzai-collected');collected.append(node('summary','اطلاعاتی که تا اینجا گفتی'),preview);output.append(collected);}
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
    async function submit(text,extra={}){if(busy)return;busy=true;send.disabled=true;document.getElementById('nzai-reset').disabled=true;input.disabled=true;guide.setAttribute('aria-busy','true');guide.querySelectorAll('button,select').forEach(e=>e.disabled=true);status.textContent='دارم بررسی می‌کنم…';bubble(text,true);
      try{const j=await request({message:text,...extra});plan=j.plan;bubble(plan.reply||'اطلاعات دریافت شد.');status.textContent='';input.value='';if(mode==='create')showDraft(plan);else if(plan.ready!==false)showResults(j.results||[]);else output.replaceChildren();
        showQuestion(plan);
        document.dispatchEvent(new CustomEvent('nzai:turn',{detail:{turn:j.turn,plan}}));
      }
      catch(e){status.textContent=e.name==='TimeoutError'?'پاسخ دیر شد. دوباره تلاش کنید.':e.message;}
      finally{busy=false;send.disabled=false;input.disabled=false;guide.removeAttribute('aria-busy');guide.querySelectorAll('button,select').forEach(e=>{e.disabled=e.textContent==='تأیید انتخاب'&&!guide.querySelector('select')?.value;});document.getElementById('nzai-reset').disabled=false;if(dialog.open)input.focus();}
    }
    compose.addEventListener('submit',e=>{e.preventDefault();const text=input.value.trim();if(text)submit(text);});
    root.querySelectorAll('[data-example]').forEach(b=>{b.onclick=()=>{input.value=b.dataset.example;input.focus();};});
    const clear=()=>{log.replaceChildren();output.replaceChildren();plan=null;input.value='';showQuestion(null);document.dispatchEvent(new CustomEvent('nzai:turn',{detail:{turn:null,plan:null}}));};
    document.getElementById('nzai-reset').onclick=async()=>{if(busy)return;try{await request({action:'reset'});clear();status.textContent='گفتگوی تازه آماده است.';input.focus();}catch(e){status.textContent=e.message;}};
    document.addEventListener('nzai:recall',event=>{if(mode==='search')submit('ادامهٔ جستجوی قبلی',{action:'recall',history_id:event.detail.id});});
    document.addEventListener('nzai:forgotten',()=>{clear();status.textContent='حافظه و نمونه‌های آموزشی شما حذف شدند.';});
    busy=true;send.disabled=true;input.disabled=true;
    request({action:'resume'}).then(j=>{if(!j.plan)return;plan=j.plan;showQuestion(plan);(j.history||[]).forEach(h=>bubble(h.text,h.role==='user'));if(mode==='create')showDraft(plan);else if(plan.ready!==false)showResults(j.results||[]);}).catch(e=>{status.textContent=e.message;}).finally(()=>{busy=false;send.disabled=false;input.disabled=false;});
    let autoOpen=true;try{autoOpen=sessionStorage.getItem('nzai-entered')!=='1';sessionStorage.setItem('nzai-entered','1');}catch(_){}
    if(autoOpen||cfg.open_requested)openAssistant();else closed();
    if(mode==='create'&&!cfg.logged_in){status.textContent='برای ثبت آگهی ابتدا وارد حساب شوید.';toggle(false);}
  }
  if(document.readyState==='complete')init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();
