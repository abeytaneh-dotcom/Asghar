(()=>{
  const ICONS={
    home:'<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/>',
    phone:'<path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-4-1-2 2c-3.6-1.5-6.5-4.4-8-8l2-2z"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/>',
    gift:'<path d="M4 10h16v10H4z"/><path d="M3 7h18v3H3z"/><path d="M12 7v13"/><path d="M12 7c-1.4-4-6-4-6-1.5C6 7 8 7 12 7Zm0 0c1.4-4 6-4 6-1.5C18 7 16 7 12 7Z"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 3.5-6 8-6s7.2 2 8 6"/>',
    help:'<circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.5 2.5 0 1 1 3.8 2.1c-1 .7-1.5 1.2-1.5 2.4"/><path d="M12 17h.01"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    target:'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v2M22 12h-2M12 22v-2M2 12h2"/>',
    users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    brief:'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
    chart:'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 21 10h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    wallet:'<path d="M4 6h14a2 2 0 0 1 2 2v11H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><path d="M15 11h7v5h-7a2 2 0 0 1 0-5Z"/>',
    save:'<path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>',
    check:'<path d="m5 12 4 4L19 6"/>',
    flame:'<path d="M12 22c4 0 7-3 7-7 0-3-1.5-5.5-4.5-8 .2 2-1 3.7-2.5 4.6.2-3-1.5-6.1-4-8.6.2 3-3 5-3 9 0 5 3 10 7 10Z"/>',
    ban:'<circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>',
    x:'<path d="M6 6l12 12M18 6 6 18"/>',
    star:'<path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.9-5.4 2.9 1-6-4.3-4.2 6-.9Z"/>',
    alert:'<path d="M12 3 2.5 20h19Z"/><path d="M12 9v4M12 17h.01"/>',
    edit:'<path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/>',
    send:'<path d="m3 11 18-8-8 18-2-7z"/><path d="m11 14 4-4"/>',
    copy:'<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
    money:'<circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.8-.7-1.8-1-3-1-1.7 0-3 .9-3 2s1 1.7 3 2c2 .3 3 1 3 2s-1.3 2-3 2c-1.2 0-2.4-.4-3.2-1.2M12 5v14"/>',
    logout:'<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"/>'
  };
  const icon=(name,cls='ui-icon')=>`<span class="${cls}" aria-hidden="true"><svg viewBox="0 0 24 24">${ICONS[name]||ICONS.star}</svg></span>`;
  const clean=t=>(t||'').replace(/\s+/g,' ').trim();
  document.body.classList.add('iconic-ui');

  // Copy buttons.
  document.querySelectorAll('.copy').forEach(btn=>{
    btn.addEventListener('click',async()=>{
      const value=btn.dataset.copy||'';
      try{await navigator.clipboard.writeText(value);const old=btn.dataset.originalText||clean(btn.textContent);btn.textContent='کپی شد ✓';setTimeout(()=>{btn.innerHTML=icon('copy')+'<span class="ui-label">'+old+'</span>'},1200)}
      catch(e){const t=document.createElement('textarea');t.value=value;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}
    });
  });

  // Referral share.
  document.querySelectorAll('.copy[data-copy^="http"]').forEach(copyBtn=>{
    const link=copyBtn.dataset.copy||'';
    if(!link||copyBtn.parentElement?.querySelector('.invite-friends'))return;
    const invite=document.createElement('button');invite.type='button';invite.className='btn lime invite-friends';invite.dataset.icon='send';invite.textContent='دعوت از دوستان';
    invite.addEventListener('click',async()=>{const text='برای همکاری به عنوان نمایندگی وب ساز از لینک اختصاصی من ثبت‌نام کن:';try{if(navigator.share){await navigator.share({title:'دعوت به همکاری با وب ساز',text,url:link});return;}}catch(err){if(err&&err.name==='AbortError')return;}const message=text+'\n'+link;const opened=window.open('https://wa.me/?text='+encodeURIComponent(message),'_blank','noopener,noreferrer');if(!opened){try{await navigator.clipboard.writeText(message)}catch(e){}}});
    copyBtn.insertAdjacentElement('beforebegin',invite);
  });

  const branchViews={dashboard:['خانه','home'],leads:['فرصت‌های امروز','phone'],followups:['پیگیری‌ها','clock'],history:['سوابق','history'],referrals:['معرفی و پاداش','gift'],profile:['حساب من','user'],help:['راهنما','help']};
  const adminViews={dashboard:['خانه','home'],intake:['ثبت سفارش','plus'],distribution:['توزیع هوشمند','target'],branches:['نمایندگی‌ها','users'],leads:['بانک فرصت‌ها','brief'],activity:['فعالیت و نتایج','chart'],referrals:['پاداش معرفی','gift'],settings:['تنظیمات','settings']};
  const isAdmin=!!document.querySelector('.side a[href*="admin=1"], .mobile a[href*="admin=1"]');
  const viewMap=isAdmin?adminViews:branchViews;

  // Navigation: icon + short clear label everywhere.
  document.querySelectorAll('.side,.mobile').forEach(nav=>{
    nav.classList.add(isAdmin?'admin-mobile-v3':'branch-mobile-v3');
    nav.querySelectorAll('a').forEach(a=>{
      let view='dashboard';try{view=new URL(a.href,location.href).searchParams.get('view')||'dashboard'}catch(e){}
      const meta=viewMap[view];if(!meta)return;
      a.innerHTML=icon(meta[1])+`<span class="ui-label">${meta[0]}</span>`;
      a.title=meta[0];
    });
  });

  // Always-visible finance shortcut.
  const user=document.querySelector('.top .user');
  if(user&&!user.querySelector('.finance-shortcut')){
    const logout=user.querySelector('a[href*="logout"]');
    const a=document.createElement('a');a.className='btn lime sm finance-shortcut';a.href='./finance.php'+(isAdmin?'?admin=1':'');a.innerHTML=icon('wallet')+'<span class="ui-label">مالی</span>';a.title='حساب مالی';
    if(logout)user.insertBefore(a,logout);else user.appendChild(a);
  }

  // Main quick actions so first-time users know where to tap.
  const main=document.querySelector('.main');
  if(main&&!main.querySelector('.quick-actions')){
    const wrap=document.createElement('nav');wrap.className='quick-actions';wrap.setAttribute('aria-label','کارهای سریع');
    const items=isAdmin?[
      ['./?admin=1&view=intake','plus','ثبت سفارش','سفارش جدید مشتری'],
      ['./?admin=1&view=distribution','target','توزیع','ظرفیت و امتیازها'],
      ['./finance.php?admin=1','wallet','مالی','تأیید واریزی‌ها'],
      ['./?admin=1&view=branches','users','نماینده‌ها','مدیریت حساب‌ها']
    ]:[
      ['./?view=leads','phone','تماس امروز','فرصت‌های جدید'],
      ['./?view=followups','clock','پیگیری','موعدهای بعدی'],
      ['./finance.php','wallet','حساب مالی','دریافتی و تسویه'],
      ['./?view=referrals','gift','معرفی','لینک و پاداش']
    ];
    wrap.innerHTML=items.map(([href,ic,title,sub])=>`<a class="quick-action" href="${href}"><span class="qa-icon"><svg viewBox="0 0 24 24">${ICONS[ic]}</svg></span><span><b>${title}</b><small>${sub}</small></span></a>`).join('');
    const title=main.querySelector('.title');if(title)title.insertAdjacentElement('afterend',wrap);else main.prepend(wrap);
  }

  // Mandatory reason + rescue handoff and icon status cards.
  const statusMeta={new:['جدید','star'],won:['قطعی','check'],interested:['راغب','flame'],followup:['پیگیری','clock'],no_answer:['جواب نداد','ban'],cancelled:['کنسل','x'],bad_info:['اطلاعات اشتباه','alert'],interested_transfer:['ارجاع به قوی‌تر','send']};
  document.querySelectorAll('form.result').forEach(form=>{
    form.action='./smart-actions.php';
    const statuses=form.querySelector('.statuses');const note=form.querySelector('[name="note"]');
    if(note){note.placeholder='دلیل یا نتیجه تماس را کوتاه و واضح بنویس';if(!note.nextElementSibling?.classList?.contains('reason-help')){const help=document.createElement('small');help.className='reason-help';help.textContent='ثبت دلیل برای هر نتیجه به‌جز «جدید» اجباری است.';note.insertAdjacentElement('afterend',help);}if((note.value||'').includes('ارجاع ویژه مشتری راغب')&&!form.previousElementSibling?.classList?.contains('rescue-note')){const badge=document.createElement('div');badge.className='rescue-note';badge.textContent='⭐ مشتری راغب ارجاعی؛ سابقه تماس قبلی و پورسانت ویژه ۴۰٪ همراه پرونده است.';form.insertAdjacentElement('beforebegin',badge);}}
    if(statuses&&!statuses.querySelector('input[value="interested_transfer"]')){const label=document.createElement('label');label.innerHTML='<input type="radio" name="status" value="interested_transfer"><span></span>';statuses.appendChild(label);}
    statuses?.querySelectorAll('label').forEach(label=>{const input=label.querySelector('input[name="status"]');const span=label.querySelector('span');const m=statusMeta[input?.value||'new'];if(span&&m)span.innerHTML=icon(m[1])+`<span class="ui-label">${m[0]}</span>`;});
    const sync=()=>{const chosen=form.querySelector('input[name="status"]:checked')?.value||'new';if(note)note.required=chosen!=='new';};
    form.querySelectorAll('input[name="status"]').forEach(r=>r.addEventListener('change',sync));sync();
    form.addEventListener('submit',e=>{const chosen=form.querySelector('input[name="status"]:checked')?.value||'new';const reason=(note?.value||'').trim();if(chosen!=='new'&&reason.length<3){e.preventDefault();e.stopImmediatePropagation();if(note){note.focus();note.setCustomValidity('دلیل یا توضیح تماس را وارد کنید.');note.reportValidity();setTimeout(()=>note.setCustomValidity(''),1400)}return;}if(chosen==='interested_transfer'&&!confirm('مشتری راغب برای نماینده قوی‌تر ارسال شود؟ دلیل شما همراه پرونده منتقل می‌شود.')){e.preventDefault();e.stopImmediatePropagation();}},true);
  });

  // Decorate buttons with predictable icons.
  const iconForText=t=>{t=clean(t);if(/مالی|حساب مالی/.test(t))return'wallet';if(/ثبت|افزود|جدید/.test(t))return'plus';if(/ذخیره/.test(t))return'save';if(/تأیید/.test(t))return'check';if(/رد|حذف|خروج/.test(t))return'x';if(/ارسال|دعوت/.test(t))return'send';if(/کپی/.test(t))return'copy';if(/تماس/.test(t))return'phone';if(/پیگیری/.test(t))return'clock';if(/تنظیم/.test(t))return'settings';if(/پرداخت|واریز|تسویه|درآمد|مبلغ/.test(t))return'money';return null;};
  document.querySelectorAll('.btn,button').forEach(btn=>{if(btn.querySelector('.ui-icon'))return;const t=clean(btn.textContent);const name=btn.dataset.icon||iconForText(t);if(name){btn.dataset.originalText=t;btn.innerHTML=icon(name)+`<span class="ui-label">${t}</span>`;}});

  // Section headings get a subtle icon for scanning.
  document.querySelectorAll('.panel h2').forEach(h=>{if(h.querySelector('.ui-icon'))return;const t=clean(h.textContent);let name=/مالی|درآمد|پرداخت/.test(t)?'wallet':/نمایند/.test(t)?'users':/پیگیری/.test(t)?'clock':/پاداش|معرف/.test(t)?'gift':/تنظیم/.test(t)?'settings':/فعالیت|گزارش/.test(t)?'chart':'brief';h.insertAdjacentHTML('afterbegin',icon(name));});

  document.querySelectorAll('input[inputmode="numeric"]').forEach(i=>i.addEventListener('input',()=>{i.value=i.value.replace(/[^0-9]/g,'')}));
  document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('button[type="submit"],button:not([type])');if(b&&!b.disabled){b.dataset.savedHtml=b.innerHTML;b.innerHTML=icon('clock')+'<span class="ui-label">در حال ثبت…</span>';b.disabled=true;setTimeout(()=>{b.disabled=false;b.innerHTML=b.dataset.savedHtml||'<span class="ui-label">ثبت</span>'},5000)}}));
})();
