/* SIMPLE_DIVAR_V2 */
(()=>{
  const body=document.body;if(!body)return;body.classList.add('simple-v2');
  const qs=new URLSearchParams(location.search);const current=qs.get('view')||'dashboard';
  const isAdmin=!!document.querySelector('.side a[href*="admin=1"],.mobile a[href*="admin=1"]');
  const S={
    home:'<path d="M3 11.5 12 4l9 7.5M5 10.5V20h5v-6h4v6h5v-9.5"/>',
    list:'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    wallet:'<path d="M4 6h14a2 2 0 0 1 2 2v11H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><path d="M15 11h7v5h-7a2 2 0 0 1 0-5Z"/>',
    more:'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    target:'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v2M22 12h-2M12 22v-2M2 12h2"/>',
    users:'<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M16 3.2a4 4 0 0 1 0 7.6M22 21v-2a4 4 0 0 0-3-3.8"/>',
    history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 4v4h4M12 7v5l3 2"/>',
    gift:'<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13M12 7c-1.4-4-6-4-6-1.5C6 7 8 7 12 7Zm0 0c1.4-4 6-4 6-1.5C18 7 16 7 12 7Z"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 3.5-6 8-6s7.2 2 8 6"/>',
    help:'<circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.5 2.5 0 1 1 3.8 2.1c-1 .7-1.5 1.2-1.5 2.4M12 17h.01"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>'
  };
  const ico=(n)=>`<svg viewBox="0 0 24 24" aria-hidden="true">${S[n]||S.more}</svg>`;

  // Replace dense quick cards with one obvious action list, like a marketplace app.
  document.querySelectorAll('.quick-actions').forEach(x=>x.remove());
  const main=document.querySelector('.main');
  if(main&&!main.querySelector('.simple-start')){
    const rows=isAdmin?[
      ['./?admin=1&view=intake','plus','ثبت سفارش جدید','اطلاعات مشتری را وارد کنید'],
      ['./?admin=1&view=distribution','target','توزیع سفارش‌ها','ظرفیت و عملکرد نماینده‌ها'],
      ['./finance.php?admin=1','wallet','حسابداری','واریزی‌ها و مغایرت‌ها'],
      ['./?admin=1&view=branches','users','نمایندگی‌ها','مدیریت حساب و منطقه فعالیت']
    ]:[
      ['./?view=leads','list','فرصت‌های امروز','سفارش‌هایی که باید تماس بگیرید'],
      ['./?view=followups','clock','پیگیری‌ها','مشتری‌هایی که باید دوباره تماس بگیرید'],
      ['./finance.php','wallet','حساب مالی','دریافتی، سهم شما و تسویه'],
      ['./?view=history','history','سوابق','نتیجه تماس‌ها و کارهای قبلی']
    ];
    const box=document.createElement('section');box.className='simple-start';
    box.innerHTML='<div class="simple-start-title"><b>'+(isAdmin?'کارهای اصلی مدیریت':'از اینجا شروع کن')+'</b><span>یکی را انتخاب کن</span></div>'+rows.map(r=>`<a href="${r[0]}" class="simple-row"><span class="simple-row-icon">${ico(r[1])}</span><span class="simple-row-text"><b>${r[2]}</b><small>${r[3]}</small></span><span class="simple-chevron">‹</span></a>`).join('');
    const title=main.querySelector('.title');if(title)title.insertAdjacentElement('afterend',box);else main.prepend(box);
  }

  // Five-item bottom nav: only everyday actions. Everything else lives in More.
  const mobile=document.querySelector('.mobile');
  if(mobile){
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
    const isFinance=location.pathname.endsWith('/finance.php');
    mobile.innerHTML=primary.map(r=>`<a href="${r[0]}" class="${(isFinance?r[3]==='finance':current===r[3])?'on':''}">${ico(r[1])}<span>${r[2]}</span></a>`).join('')+`<button type="button" class="simple-more-btn">${ico('more')}<span>بیشتر</span></button>`;
    mobile.classList.add('simple-mobile');
    const more=isAdmin?[
      ['./?admin=1&view=branches','users','نمایندگی‌ها'],['./?admin=1&view=leads','list','بانک فرصت‌ها'],['./?admin=1&view=activity','history','فعالیت و نتایج'],['./?admin=1&view=referrals','gift','پاداش معرفی'],['./?admin=1&view=settings','settings','تنظیمات']
    ]:[
      ['./?view=history','history','سوابق'],['./?view=referrals','gift','معرفی و پاداش'],['./?view=profile','user','حساب من'],['./?view=help','help','راهنما']
    ];
    const sheet=document.createElement('div');sheet.className='simple-sheet';sheet.innerHTML='<div class="simple-sheet-backdrop"></div><div class="simple-sheet-box"><div class="simple-sheet-handle"></div><h3>بیشتر</h3>'+more.map(r=>`<a href="${r[0]}"><span>${ico(r[1])}</span><b>${r[2]}</b><i>‹</i></a>`).join('')+'</div>';
    document.body.appendChild(sheet);
    const close=()=>sheet.classList.remove('open');mobile.querySelector('.simple-more-btn')?.addEventListener('click',()=>sheet.classList.add('open'));sheet.querySelector('.simple-sheet-backdrop')?.addEventListener('click',close);
  }

  // Make dense dashboard elements quieter. Keep information, remove visual competition.
  document.querySelectorAll('.hero').forEach(x=>x.classList.add('simple-hero'));
  document.querySelectorAll('.stats').forEach(x=>x.classList.add('simple-stats'));
  document.querySelectorAll('.panel').forEach(x=>x.classList.add('simple-panel'));

  // Clear, friendly headings.
  const title=document.querySelector('.title h1');if(title){title.textContent=title.textContent.replace('داشبورد نمایندگی','خانه').replace('داشبورد مدیریت','خانه مدیریت');}
})();
