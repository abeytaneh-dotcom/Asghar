/* SIMPLE_DIVAR_V3 */
(()=>{
  const body=document.body;if(!body)return;body.classList.add('simple-v2','simple-v3');
  const qs=new URLSearchParams(location.search);const current=qs.get('view')||'dashboard';
  const isAdmin=!!document.querySelector('.side a[href*="admin=1"],.mobile a[href*="admin=1"]') || qs.has('admin');
  const isFinance=location.pathname.endsWith('/finance.php');
  const S={
    home:'<path d="M3 11.5 12 4l9 7.5M5 10.5V20h5v-6h4v6h5v-9.5"/>',
    list:'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    wallet:'<path d="M4 6h14a2 2 0 0 1 2 2v11H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><path d="M15 11h7v5h-7a2 2 0 0 1 0-5Z"/>',
    more:'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    target:'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/>',
    users:'<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M16 3.2a4 4 0 0 1 0 7.6M22 21v-2a4 4 0 0 0-3-3.8"/>',
    history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 4v4h4M12 7v5l3 2"/>',
    gift:'<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 3.5-6 8-6s7.2 2 8 6"/>',
    help:'<circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.5 2.5 0 1 1 3.8 2.1c-1 .7-1.5 1.2-1.5 2.4M12 17h.01"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>'
  };
  const ico=(n)=>`<svg viewBox="0 0 24 24" aria-hidden="true">${S[n]||S.more}</svg>`;

  // Remove old dashboard-style shortcuts.
  document.querySelectorAll('.quick-actions,.divar-menu,.dv-home-menu,.simple-start').forEach(x=>x.remove());
  const main=document.querySelector('.main');

  // Home is now one calm, obvious list. No dashboard hero or KPI wall.
  if(main&&current==='dashboard'&&!isFinance){
    document.querySelectorAll('.hero,.stats,.progressbox').forEach(x=>x.classList.add('simple-home-hidden'));
    const rows=isAdmin?[
      ['./?admin=1&view=intake','plus','ثبت سفارش جدید','مشتری جدید را وارد کنید'],
      ['./?admin=1&view=distribution','target','توزیع سفارش‌ها','ظرفیت و امتیاز نماینده‌ها'],
      ['./finance.php?admin=1','wallet','حساب مالی','واریزی‌ها و مغایرت‌ها'],
      ['./?admin=1&view=branches','users','نمایندگی‌ها','مدیریت حساب و منطقه فعالیت'],
      ['./?admin=1&view=activity','history','گزارش فعالیت','نتیجه تماس‌ها و فروش']
    ]:[
      ['./?view=leads','list','فرصت‌های امروز','مشتری‌هایی که امروز باید تماس بگیری'],
      ['./?view=followups','clock','پیگیری‌ها','مشتری‌هایی که دوباره باید تماس بگیری'],
      ['./finance.php','wallet','حساب مالی','دریافتی، سهم شما و تسویه'],
      ['./?view=history','history','سوابق','نتیجه تماس‌ها و کارهای قبلی'],
      ['./?view=referrals','gift','معرفی و پاداش','لینک معرفی و پاداش‌ها']
    ];
    const box=document.createElement('section');box.className='simple-start simple-start-v3';
    box.innerHTML='<div class="simple-start-title"><b>'+(isAdmin?'مدیریت':'کارهای امروز')+'</b><span>انتخاب کنید</span></div>'+rows.map(r=>`<a href="${r[0]}" class="simple-row"><span class="simple-row-icon">${ico(r[1])}</span><span class="simple-row-text"><b>${r[2]}</b><small>${r[3]}</small></span><span class="simple-chevron">‹</span></a>`).join('');
    const title=main.querySelector('.title');if(title)title.insertAdjacentElement('afterend',box);else main.prepend(box);
  }

  // Exactly five mobile destinations.
  const mobile=document.querySelector('.mobile');
  if(mobile&&!isFinance){
    const primary=isAdmin?[
      ['./?admin=1&view=dashboard','home','خانه','dashboard'],
      ['./?admin=1&view=intake','plus','ثبت','intake'],
      ['./?admin=1&view=distribution','target','توزیع','distribution'],
      ['./finance.php?admin=1','wallet','مالی','finance']
    ]:[
      ['./?view=dashboard','home','خانه','dashboard'],
      ['./?view=leads','list','فرصت‌ها','leads'],
      ['./?view=followups','clock','پیگیری','followups'],
      ['./finance.php','wallet','مالی','finance']
    ];
    mobile.innerHTML=primary.map(r=>`<a href="${r[0]}" class="${current===r[3]?'on':''}">${ico(r[1])}<span>${r[2]}</span></a>`).join('')+`<button type="button" class="simple-more-btn">${ico('more')}<span>بیشتر</span></button>`;
    mobile.classList.add('simple-mobile');
    document.querySelectorAll('.simple-sheet').forEach(x=>x.remove());
    const more=isAdmin?[
      ['./?admin=1&view=branches','users','نمایندگی‌ها'],['./?admin=1&view=leads','list','بانک فرصت‌ها'],['./?admin=1&view=activity','history','فعالیت و نتایج'],['./?admin=1&view=referrals','gift','پاداش معرفی'],['./?admin=1&view=settings','settings','تنظیمات']
    ]:[
      ['./?view=history','history','سوابق'],['./?view=referrals','gift','معرفی و پاداش'],['./?view=profile','user','حساب من'],['./?view=help','help','راهنما']
    ];
    const sheet=document.createElement('div');sheet.className='simple-sheet';sheet.innerHTML='<div class="simple-sheet-backdrop"></div><div class="simple-sheet-box"><div class="simple-sheet-handle"></div><h3>بیشتر</h3>'+more.map(r=>`<a href="${r[0]}"><span>${ico(r[1])}</span><b>${r[2]}</b><i>‹</i></a>`).join('')+'</div>';
    document.body.appendChild(sheet);
    const close=()=>sheet.classList.remove('open');mobile.querySelector('.simple-more-btn')?.addEventListener('click',()=>sheet.classList.add('open'));sheet.querySelector('.simple-sheet-backdrop')?.addEventListener('click',close);
  }

  // Quieter content cards.
  document.querySelectorAll('.panel').forEach(x=>x.classList.add('simple-panel'));
  document.querySelectorAll('.lead').forEach(x=>x.classList.add('simple-lead-v3'));
  document.querySelectorAll('.stats').forEach(x=>x.classList.add('simple-stats'));
  document.querySelectorAll('.hero').forEach(x=>x.classList.add('simple-hero'));

  // Shorter page titles.
  const title=document.querySelector('.title h1');if(title){title.textContent=title.textContent.replace('داشبورد نمایندگی','خانه').replace('داشبورد مدیریت','خانه مدیریت');}
})();
