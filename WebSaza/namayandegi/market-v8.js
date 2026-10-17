/* MARKET_V8 — marketplace-style representative portal */
(()=>{
  const qs=new URLSearchParams(location.search);
  const view=qs.get('view')||'dashboard';
  const isFinance=location.pathname.endsWith('/finance.php');
  const isAdmin=!!document.querySelector('.side a[href*="admin=1"],.mobile a[href*="admin=1"]')||qs.has('admin');
  const S={
    home:'<path d="M3.5 11.2 12 4l8.5 7.2"/><path d="M5.5 10.2V20h5v-5.5h3V20h5v-9.8"/>',
    list:'<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
    clock:'<circle cx="12" cy="12" r="8.7"/><path d="M12 7.5v4.8l3.2 1.9"/>',
    wallet:'<path d="M4.5 6.5h13a2 2 0 0 1 2 2V19h-14a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2h10.5"/><path d="M15 11h6v4.5h-6a2.25 2.25 0 0 1 0-4.5Z"/>',
    history:'<path d="M4 7.5V4m0 3.5h3.5"/><path d="M4.5 7.2A8.7 8.7 0 1 1 3.4 12"/><path d="M12 7.4v4.8l3.2 1.9"/>',
    gift:'<path d="M4.5 10h15v10h-15zM3.5 7h17v3h-17zM12 7v13"/><path d="M12 7c-1.4-2.8-4.8-3.2-4.8-.8 0 1.5 1.7 2.1 4.8.8Zm0 0c1.4-2.8 4.8-3.2 4.8-.8 0 1.5-1.7 2.1-4.8.8Z"/>',
    user:'<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-3.8 3.3-5.8 7.5-5.8s6.7 2 7.5 5.8"/>',
    help:'<circle cx="12" cy="12" r="8.7"/><path d="M9.6 9.2a2.7 2.7 0 1 1 4.1 2.3c-1 .7-1.7 1.3-1.7 2.7M12 17.4h.01"/>'
  };
  const svg=(n)=>`<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${S[n]}</svg>`;
  const fa=n=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const remainText=s=>{s=Math.max(0,Number(s)||0);const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),z=Math.floor(s%60);return fa(String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(z).padStart(2,'0'));};
  let lastTimerData=null,tickBusy=false,zeroTriggered=false;

  const aidFrom=(el)=>{if(!el)return'';if(el.dataset?.assignmentId)return el.dataset.assignmentId;const id=(el.id||'').match(/^lead-(A[\w-]+)/i);if(id)return id[1];const href=el.getAttribute?.('href')||'';const m=href.match(/#lead-(A[\w-]+)/i);return m?m[1]:'';};
  const timerHtml=(m)=>{const urgent=m.priority==='urgent';const live=m.current&&m.status==='new';return `<div class="order-timebar ${urgent?'urgent':'normal'}"><span class="order-kind">${urgent?'فوری · مهلت ۲ ساعت':'معمولی · مهلت ۲۴ ساعت'}</span>${live?`<span class="order-countdown" data-remain="${Math.max(0,m.remaining_seconds||0)}">${remainText(m.remaining_seconds)}</span>`:'<span class="order-countdown stopped">مهلت متوقف</span>'}</div><div class="order-dates"><span>ثبت: ${m.created_fa||'-'}</span>${m.deadline_fa?`<span>پایان مهلت: ${m.deadline_fa}</span>`:''}</div>`;};
  const decorateTimers=(assignments)=>{
    const map=new Map(assignments.map(x=>[x.id,x]));
    document.querySelectorAll('.market-card,.market-card-host,.lead[id^="lead-"]').forEach(el=>{
      const aid=aidFrom(el);const m=map.get(aid);if(!m)return;el.dataset.assignmentId=aid;el.dataset.priority=m.priority||'normal';let host=el.querySelector('.market-card-body')||el.querySelector('.leadtop')?.parentElement||el;if(host===el&&el.classList.contains('market-card-host'))host=el.querySelector('.market-card-row')||el;let box=el.querySelector('.order-timer-box');if(!box){box=document.createElement('div');box.className='order-timer-box';if(host.classList?.contains('market-card-body'))host.appendChild(box);else{const top=el.querySelector('.leadtop');if(top)top.insertAdjacentElement('afterend',box);else host.appendChild(box);}}box.innerHTML=timerHtml(m);
    });
    return map;
  };
  const renderTimeline=(events)=>{
    if(!((!isAdmin&&view==='history')||(isAdmin&&view==='activity')))return;
    const main=document.querySelector('.main');if(!main)return;let panel=main.querySelector('.order-events-panel');if(!panel){panel=document.createElement('section');panel.className='panel order-events-panel';const first=main.querySelector('.panel');if(first)first.insertAdjacentElement('beforebegin',panel);else main.appendChild(panel);}panel.innerHTML='<div class="order-events-head"><div><h2>تاریخچه دقیق سفارش‌ها</h2><p>روز هفته، تاریخ شمسی و ساعت هر تغییر</p></div></div><div class="order-event-list">'+(events.length?events.map(ev=>`<article class="order-event"><div class="order-event-dot"></div><div><b>${escapeHtml(ev.business||'مشتری')} · ${escapeHtml(ev.status_label||ev.status||'رویداد')}</b><small>${escapeHtml(ev.created_fa||'')}</small>${ev.note?`<p>${escapeHtml(ev.note)}</p>`:''}</div></article>`).join(''):'<div class="empty">هنوز رویدادی ثبت نشده است.</div>')+'</div>';
  };
  const escapeHtml=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const refreshTimerData=async()=>{if(tickBusy)return;tickBusy=true;try{const r=await fetch('./smart-actions.php?action=timer_tick',{cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)return;const d=await r.json();if(!d.ok)return;lastTimerData=d;const map=decorateTimers(d.assignments||[]);renderTimeline(d.events||[]);if(!isAdmin&&view==='dashboard')groupOrders(map);zeroTriggered=false;}catch(e){}finally{tickBusy=false;}};
  setInterval(()=>{let hitZero=false;document.querySelectorAll('.order-countdown[data-remain]').forEach(el=>{let s=Math.max(0,(Number(el.dataset.remain)||0)-1);el.dataset.remain=String(s);el.textContent=remainText(s);if(s===0){el.classList.add('expired');el.textContent='در حال انتقال…';hitZero=true;}});if(hitZero&&!zeroTriggered){zeroTriggered=true;refreshTimerData();}},1000);

  const setupAdminIntake=()=>{
    const action=document.querySelector('form input[name="action"][value="add_order"]');const form=action?.form;if(!form||form.dataset.timedOrder==='1')return;form.dataset.timedOrder='1';const cols=form.querySelector('.cols')||form;const label=document.createElement('label');label.className='order-priority-field';label.innerHTML='<span>نوع سفارش</span><select name="priority"><option value="normal">معمولی — مهلت ۲۴ ساعت</option><option value="urgent">فوری — مهلت ۲ ساعت</option></select>';cols.appendChild(label);form.addEventListener('submit',async e=>{e.preventDefault();e.stopImmediatePropagation();const btn=form.querySelector('button');if(btn)btn.disabled=true;const fd=new FormData(form);fd.set('action','create_timed_order');try{const r=await fetch('./smart-actions.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'خطا در ثبت سفارش');location.reload();}catch(err){alert(err.message||'خطا در ثبت سفارش');if(btn)btn.disabled=false;}},true);
  };

  document.querySelectorAll('p').forEach(p=>{if(/سفارش دیده‌نشده بعد از/.test(p.textContent||''))p.textContent='سفارش معمولی ۲۴ ساعت و سفارش فوری ۲ ساعت مهلت دارد؛ اگر نتیجه تماس ثبت نشود، خودکار به نفر بعدی منتقل می‌شود.';});
  if(isAdmin){setupAdminIntake();refreshTimerData();setInterval(refreshTimerData,60000);return;}

  document.body.classList.add('market-v8');
  const mobile=document.querySelector('.mobile');
  if(mobile){const active=isFinance?'finance':view;const bottom=[['./?view=dashboard','home','خانه','dashboard'],['./?view=leads','list','فرصت‌ها','leads'],['./?view=followups','clock','پیگیری','followups'],['./finance.php','wallet','مالی','finance']];mobile.innerHTML=bottom.map(([href,ic,label,key])=>`<a href="${href}" class="${active===key?'on':''}">${svg(ic)}<span>${label}</span></a>`).join('');mobile.classList.add('simple-mobile','market-bottom-four');}
  document.querySelectorAll('.simple-sheet').forEach(el=>el.remove());
  refreshTimerData();setInterval(refreshTimerData,60000);
  if(isFinance||view!=='dashboard')return;

  const main=document.querySelector('.main');if(!main)return;
  const svgSearch='<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>';
  const svgFilter='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>';
  const svgShop='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5h16l-1.2-5H5.2L4 9.5Z"/><path d="M5.5 9.5V20h13V9.5M9 20v-6h6v6"/><path d="M4 9.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5"/></svg>';
  const opp=[...main.querySelectorAll('.panel')].find(p=>p.classList.contains('simple-opportunities')||/فرصت‌های جدید|اولویت‌های امروز/.test(p.textContent||''));
  const start=main.querySelector('.simple-start');
  if(start){start.classList.add('market-shortcuts');const top=[['./?view=history','history','سوابق'],['./?view=referrals','gift','معرفی و پاداش'],['./?view=profile','user','حساب من'],['./?view=help','help','راهنما']];start.innerHTML=top.map(([href,ic,label],i)=>`<a href="${href}" class="simple-row market-top-item"><span class="simple-row-icon tone-${i+1}">${svg(ic)}</span><span class="simple-row-text"><b>${label}</b></span></a>`).join('');}
  if(!opp)return;opp.classList.add('market-opportunities');const heading=[...opp.querySelectorAll('h1,h2,h3')].find(h=>/فرصت‌های جدید|اولویت‌های امروز/.test(h.textContent||''));if(heading)heading.textContent='سفارش‌های جدید';
  if(!main.querySelector('.market-search')){const tools=document.createElement('section');tools.className='market-search';tools.innerHTML=`<div class="market-searchbox">${svgSearch}<input type="search" autocomplete="off" inputmode="search" placeholder="جست‌وجو در سفارش‌ها"><button type="button" class="market-filter" aria-label="فیلتر">${svgFilter}</button></div><div class="market-searchmeta"><b>تازه‌ترین سفارش‌ها</b><span>فوری‌ها ۲ ساعت · معمولی‌ها ۲۴ ساعت</span></div>`;opp.parentNode.insertBefore(tools,opp);}
  const items=[];
  [...opp.querySelectorAll('a.mini')].forEach((a,i)=>{if(a.classList.contains('market-card')){items.push(a);return;}const href=a.getAttribute('href')||'';const am=href.match(/#lead-(A[\w-]+)/i);if(am)a.dataset.assignmentId=am[1];const title=(a.querySelector('b')?.textContent||'مشتری').trim();const meta=(a.querySelector('small')?.textContent||'').trim();const pill=a.querySelector('.pill');const status=(pill?.textContent||'جدید').trim();const statusClass=pill?.className||'pill blue';a.classList.add('market-card');a.dataset.search=(title+' '+meta+' '+status).toLowerCase();a.innerHTML='';const thumb=document.createElement('span');thumb.className=`market-thumb market-thumb-${i%5+1}`;thumb.innerHTML=svgShop;const body=document.createElement('span');body.className='market-card-body';const t=document.createElement('b');t.textContent=title;const m=document.createElement('small');m.textContent=meta;const st=document.createElement('span');st.className='market-card-status '+statusClass;st.textContent=status;body.append(t,m,st);const arrow=document.createElement('span');arrow.className='market-card-arrow';arrow.textContent='‹';a.append(thumb,body,arrow);items.push(a);});
  [...opp.querySelectorAll('.home-lead-row')].forEach((row,i)=>{const lead=row.closest('.lead');if(!lead)return;const aid=aidFrom(lead);if(aid)lead.dataset.assignmentId=aid;if(row.classList.contains('market-card-row')){items.push(lead);return;}const title=(row.querySelector('.home-lead-main b')?.textContent||'مشتری').trim();const meta=(row.querySelector('.home-lead-main small')?.textContent||'').trim();const status=(row.querySelector('em')?.textContent||'جدید').trim();row.classList.add('market-card-row');lead.classList.add('market-card-host');lead.dataset.search=(title+' '+meta+' '+status).toLowerCase();if(!row.querySelector('.market-thumb')){const thumb=document.createElement('span');thumb.className=`market-thumb market-thumb-${i%5+1}`;thumb.innerHTML=svgShop;row.prepend(thumb);}row.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();const id=lead.id||'';location.href='./?view=leads'+(id?'#'+id:'');},true);items.push(lead);});
  const input=main.querySelector('.market-search input'),filter=main.querySelector('.market-filter');const apply=()=>{const q=(input?.value||'').trim().toLowerCase();items.forEach(el=>{const hay=(el.dataset.search||el.textContent||'').toLowerCase();el.hidden=!!q&&!hay.includes(q);});};input?.addEventListener('input',apply);filter?.addEventListener('click',()=>{input?.focus();input?.select();});
  if(start&&opp){const search=main.querySelector('.market-search');if(search&&start.nextElementSibling!==search)start.insertAdjacentElement('afterend',search);}
  function groupOrders(map){if(!opp||opp.dataset.timerGrouped==='1')return;const cards=[...opp.querySelectorAll('.market-card,.market-card-host')];if(!cards.length)return;const urgent=document.createElement('div'),normal=document.createElement('div');urgent.className='market-group market-group-urgent';normal.className='market-group market-group-normal';urgent.innerHTML='<div class="market-group-title"><b>سفارش‌های فوری</b><span>مهلت پاسخ‌گویی ۲ ساعت</span></div>';normal.innerHTML='<div class="market-group-title"><b>سفارش‌های معمولی</b><span>مهلت پاسخ‌گویی ۲۴ ساعت</span></div>';let u=0,n=0;cards.forEach(card=>{const m=map.get(aidFrom(card));if(m?.priority==='urgent'){urgent.appendChild(card);u++;}else{normal.appendChild(card);n++;}});if(u)opp.appendChild(urgent);if(n)opp.appendChild(normal);opp.dataset.timerGrouped='1';decorateTimers(lastTimerData?.assignments||[]);}
  if(lastTimerData){const map=decorateTimers(lastTimerData.assignments||[]);groupOrders(map);}else setTimeout(refreshTimerData,250);
})();
