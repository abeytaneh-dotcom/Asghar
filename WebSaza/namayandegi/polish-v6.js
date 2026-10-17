/* POLISH_V6 POLISH_V7 */
(()=>{
  const body=document.body;if(!body)return;body.classList.add('polish-v6');
  const I={
    home:'<path d="M3.5 11.2 12 4l8.5 7.2"/><path d="M5.5 10.2V20h5v-5.5h3V20h5v-9.8"/>',
    list:'<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
    clock:'<circle cx="12" cy="12" r="8.7"/><path d="M12 7.5v4.8l3.2 1.9"/>',
    wallet:'<path d="M4.5 6.5h13a2 2 0 0 1 2 2V19h-14a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2h10.5"/><path d="M15 11h6v4.5h-6a2.25 2.25 0 0 1 0-4.5Z"/>',
    more:'<circle cx="5" cy="12" r="1.15"/><circle cx="12" cy="12" r="1.15"/><circle cx="19" cy="12" r="1.15"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    target:'<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r=".8"/>',
    users:'<path d="M8.8 11a3.4 3.4 0 1 0 0-6.8 3.4 3.4 0 0 0 0 6.8Z"/><path d="M2.8 20v-1.4a4.8 4.8 0 0 1 4.8-4.8H10a4.8 4.8 0 0 1 4.8 4.8V20"/><path d="M15.6 5a3 3 0 0 1 0 5.2M17 14a4.5 4.5 0 0 1 4.2 4.5V20"/>',
    history:'<path d="M4 7.5V4m0 3.5h3.5"/><path d="M4.5 7.2A8.7 8.7 0 1 1 3.4 12"/><path d="M12 7.4v4.8l3.2 1.9"/>',
    gift:'<path d="M4.5 10h15v10h-15zM3.5 7h17v3h-17zM12 7v13"/><path d="M12 7c-1.4-2.8-4.8-3.2-4.8-.8 0 1.5 1.7 2.1 4.8.8Zm0 0c1.4-2.8 4.8-3.2 4.8-.8 0 1.5-1.7 2.1-4.8.8Z"/>',
    user:'<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-3.8 3.3-5.8 7.5-5.8s6.7 2 7.5 5.8"/>',
    help:'<circle cx="12" cy="12" r="8.7"/><path d="M9.6 9.2a2.7 2.7 0 1 1 4.1 2.3c-1 .7-1.7 1.3-1.7 2.7M12 17.4h.01"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2M12 19.2v2M5.5 5.5 7 7M17 17l1.5 1.5M2.8 12h2M19.2 12h2M5.5 18.5 7 17M17 7l1.5-1.5"/>',
    store:'<path d="M4 9.5h16l-1.2-5H5.2L4 9.5Z"/><path d="M5.5 9.5V20h13V9.5M9 20v-6h6v6"/><path d="M4 9.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5s2.3-1.1 2.3-2.5c0 1.4 1 2.5 2.3 2.5"/>',
    report:'<path d="M5 3.5h14v17H5z"/><path d="M8.5 8h7M8.5 12h7M8.5 16H13"/>'
  };
  const svg=(n)=>`<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${I[n]||I.more}</svg>`;
  const iconFor=(el)=>{
    const href=(el.getAttribute?.('href')||'').toLowerCase();
    const txt=(el.textContent||'').trim();
    if(href.includes('dashboard')||txt==='خانه')return 'home';
    if(href.includes('followup')||txt.includes('پیگیری'))return 'clock';
    if(href.includes('finance')||txt.includes('مالی'))return 'wallet';
    if(href.includes('referral')||txt.includes('معرفی')||txt.includes('پاداش'))return 'gift';
    if(href.includes('history')||txt.includes('سوابق'))return 'history';
    if(href.includes('profile')||txt.includes('حساب من'))return 'user';
    if(href.includes('help')||txt.includes('راهنما'))return 'help';
    if(href.includes('settings')||txt.includes('تنظیمات'))return 'settings';
    if(href.includes('intake')||txt.includes('ثبت'))return 'plus';
    if(href.includes('distribution')||txt.includes('توزیع'))return 'target';
    if(href.includes('branches')||txt.includes('نمایندگی'))return 'users';
    if(href.includes('activity')||txt.includes('گزارش'))return 'report';
    if(href.includes('leads')||txt.includes('فرصت'))return 'list';
    if(el.classList?.contains('simple-more-btn')||txt.includes('بیشتر'))return 'more';
    return 'list';
  };

  document.querySelectorAll('.simple-row-icon').forEach((box,i)=>{const a=box.closest('a');box.innerHTML=svg(iconFor(a||box));box.classList.add(`tone-${i%5+1}`);});
  document.querySelectorAll('.mobile.simple-mobile a,.mobile.simple-mobile button').forEach(el=>{const old=el.querySelector('svg');if(old)old.outerHTML=svg(iconFor(el));});
  document.querySelectorAll('.simple-sheet a').forEach((a,i)=>{const box=a.querySelector(':scope > span');if(box){box.innerHTML=svg(iconFor(a));box.classList.add(`tone-${i%5+1}`);}});

  document.querySelectorAll('.home-lead-row').forEach((row,i)=>{
    if(!row.querySelector('.home-lead-avatar')){
      const av=document.createElement('span');av.className=`home-lead-avatar tone-${i%5+1}`;av.innerHTML=svg('store');row.prepend(av);
    }else{
      row.querySelector('.home-lead-avatar')?.classList.add(`tone-${i%5+1}`);
    }
    const em=row.querySelector('em');if(!em)return;
    const t=em.textContent.trim();
    if(t.includes('جدید'))em.classList.add('is-new');
    else if(t.includes('قطعی'))em.classList.add('is-won');
    else if(t.includes('راغب'))em.classList.add('is-interested');
    else if(t.includes('پیگیری'))em.classList.add('is-followup');
  });
})();
