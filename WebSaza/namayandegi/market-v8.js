/* MARKET_V8 — marketplace-style home for representative portal */
(()=>{
  const qs=new URLSearchParams(location.search);
  const view=qs.get('view')||'dashboard';
  const isAdmin=!!document.querySelector('.side a[href*="admin=1"],.mobile a[href*="admin=1"]')||qs.has('admin');
  if(isAdmin||view!=='dashboard')return;
  const main=document.querySelector('.main');
  if(!main)return;
  document.body.classList.add('market-v8');

  const svgSearch='<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>';
  const svgFilter='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>';
  const svgShop='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5h16l-1.2-5H5.2L4 9.5Z"/><path d="M5.5 9.5V20h13V9.5M9 20v-6h6v6"/><path d="M4 9.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5"/></svg>';

  const opp=[...main.querySelectorAll('.panel')].find(p=>p.classList.contains('simple-opportunities')||/فرصت‌های جدید|اولویت‌های امروز/.test(p.textContent||''));
  const start=main.querySelector('.simple-start');
  if(start)start.classList.add('market-shortcuts');
  if(!opp)return;
  opp.classList.add('market-opportunities');
  const heading=[...opp.querySelectorAll('h1,h2,h3')].find(h=>/فرصت‌های جدید|اولویت‌های امروز/.test(h.textContent||''));
  if(heading)heading.textContent='سفارش‌های جدید';

  if(!main.querySelector('.market-search')){
    const tools=document.createElement('section');
    tools.className='market-search';
    tools.innerHTML=`<div class="market-searchbox">${svgSearch}<input type="search" autocomplete="off" inputmode="search" placeholder="جست‌وجو در سفارش‌ها"><button type="button" class="market-filter" aria-label="فیلتر">${svgFilter}</button></div><div class="market-searchmeta"><b>تازه‌ترین سفارش‌ها</b><span>برای دیدن جزئیات روی هر مورد بزن</span></div>`;
    opp.parentNode.insertBefore(tools,opp);
  }

  const items=[];
  const mini=[...opp.querySelectorAll('a.mini')];
  mini.forEach((a,i)=>{
    if(a.classList.contains('market-card')){items.push(a);return;}
    const title=(a.querySelector('b')?.textContent||'مشتری').trim();
    const meta=(a.querySelector('small')?.textContent||'').trim();
    const pill=a.querySelector('.pill');
    const status=(pill?.textContent||'جدید').trim();
    const statusClass=pill?.className||'pill blue';
    a.classList.add('market-card');
    a.dataset.search=(title+' '+meta+' '+status).toLowerCase();
    a.innerHTML='';
    const thumb=document.createElement('span');thumb.className=`market-thumb market-thumb-${i%5+1}`;thumb.innerHTML=svgShop;
    const body=document.createElement('span');body.className='market-card-body';
    const t=document.createElement('b');t.textContent=title;
    const m=document.createElement('small');m.textContent=meta;
    const st=document.createElement('span');st.className='market-card-status '+statusClass;st.textContent=status;
    body.append(t,m,st);
    const arrow=document.createElement('span');arrow.className='market-card-arrow';arrow.textContent='‹';
    a.append(thumb,body,arrow);items.push(a);
  });

  [...opp.querySelectorAll('.home-lead-row')].forEach((row,i)=>{
    const lead=row.closest('.lead');if(!lead)return;
    if(row.classList.contains('market-card-row')){items.push(lead);return;}
    const title=(row.querySelector('.home-lead-main b')?.textContent||'مشتری').trim();
    const meta=(row.querySelector('.home-lead-main small')?.textContent||'').trim();
    const status=(row.querySelector('em')?.textContent||'جدید').trim();
    row.classList.add('market-card-row');lead.classList.add('market-card-host');
    lead.dataset.search=(title+' '+meta+' '+status).toLowerCase();
    if(!row.querySelector('.market-thumb')){
      const thumb=document.createElement('span');thumb.className=`market-thumb market-thumb-${i%5+1}`;thumb.innerHTML=svgShop;row.prepend(thumb);
    }
    row.addEventListener('click',e=>{
      e.preventDefault();e.stopImmediatePropagation();
      const id=lead.id||'';location.href='./?view=leads'+(id?'#'+id:'');
    },true);
    items.push(lead);
  });

  const input=main.querySelector('.market-search input');
  const filter=main.querySelector('.market-filter');
  const apply=()=>{
    const q=(input?.value||'').trim().toLowerCase();
    items.forEach(el=>{
      const hay=(el.dataset.search||el.textContent||'').toLowerCase();
      el.hidden=!!q&&!hay.includes(q);
    });
  };
  input?.addEventListener('input',apply);
  filter?.addEventListener('click',()=>{input?.focus();input?.select();});

  if(start&&opp){
    const search=main.querySelector('.market-search');
    if(search&&start.nextElementSibling!==search)start.insertAdjacentElement('afterend',search);
  }
})();
