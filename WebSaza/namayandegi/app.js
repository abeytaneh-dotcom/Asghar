document.querySelectorAll('.copy').forEach(btn=>{btn.addEventListener('click',async()=>{const value=btn.dataset.copy||'';try{await navigator.clipboard.writeText(value);const old=btn.textContent;btn.textContent='کپی شد ✓';setTimeout(()=>btn.textContent=old,1200)}catch(e){const t=document.createElement('textarea');t.value=value;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}})});

// Add a one-tap invite button next to each account's unique referral link.
document.querySelectorAll('.copy[data-copy^="http"]').forEach(copyBtn=>{
  const link=copyBtn.dataset.copy||'';
  if(!link||copyBtn.parentElement?.querySelector('.invite-friends'))return;
  const invite=document.createElement('button');
  invite.type='button';
  invite.className='btn lime invite-friends';
  invite.textContent='دعوت از دوستان ↗';
  invite.addEventListener('click',async()=>{
    const text='برای همکاری به عنوان نمایندگی وب ساز از لینک اختصاصی من ثبت‌نام کن:';
    try{
      if(navigator.share){
        await navigator.share({title:'دعوت به همکاری با وب ساز',text,url:link});
        return;
      }
    }catch(err){
      if(err&&err.name==='AbortError')return;
    }
    const message=text+'\n'+link;
    const whatsapp='https://wa.me/?text='+encodeURIComponent(message);
    const opened=window.open(whatsapp,'_blank','noopener,noreferrer');
    if(!opened){
      try{await navigator.clipboard.writeText(message);invite.textContent='لینک دعوت کپی شد ✓';setTimeout(()=>invite.textContent='دعوت از دوستان ↗',1600)}catch(e){}
    }
  });
  copyBtn.insertAdjacentElement('beforebegin',invite);
});

// Compact professional single-row mobile navigation.
(()=>{
  const nav=document.querySelector('.mobile');
  if(!nav)return;
  const links=[...nav.querySelectorAll('a')];
  const isAdmin=links.some(a=>{try{return new URL(a.href,location.href).searchParams.has('admin')}catch(e){return false}});
  const branchLabels={dashboard:'خانه',leads:'امروز',followups:'پیگیری',history:'سوابق',referrals:'معرفی',profile:'حساب',help:'راهنما'};
  const adminLabels={dashboard:'خانه',branches:'نماینده',leads:'فرصت',activity:'فعالیت',referrals:'پاداش',settings:'تنظیمات'};
  const labels=isAdmin?adminLabels:branchLabels;
  links.forEach(a=>{
    try{
      const view=new URL(a.href,location.href).searchParams.get('view')||'dashboard';
      if(labels[view])a.textContent=labels[view];
    }catch(e){}
  });
  nav.classList.add(isAdmin?'admin-mobile-v3':'branch-mobile-v3');
  if(!document.getElementById('mobile-nav-single-row-v3')){
    const style=document.createElement('style');
    style.id='mobile-nav-single-row-v3';
    style.textContent=`
      @media(max-width:980px){
        html,body{max-width:100%;overflow-x:hidden}
        .layout{padding-bottom:80px!important}
        .mobile{
          display:grid!important;
          grid-auto-flow:row!important;
          grid-auto-columns:auto!important;
          width:100%!important;
          max-width:100vw!important;
          gap:4px!important;
          overflow:hidden!important;
          padding:6px 5px calc(6px + env(safe-area-inset-bottom))!important;
          background:#0e131a!important;
          border-top:1px solid #29313a!important;
          box-shadow:0 -8px 24px rgba(0,0,0,.18)!important;
        }
        .mobile.branch-mobile-v3{grid-template-columns:repeat(7,minmax(0,1fr))!important}
        .mobile.admin-mobile-v3{grid-template-columns:repeat(6,minmax(0,1fr))!important}
        .mobile a{
          grid-column:auto!important;
          min-width:0!important;
          width:100%!important;
          min-height:50px!important;
          flex:none!important;
          margin:0!important;
          padding:5px 1px!important;
          border:1px solid #303844!important;
          border-radius:10px!important;
          background:#171c24!important;
          color:#d7dde5!important;
          font-size:9px!important;
          font-weight:900!important;
          line-height:1!important;
          display:flex!important;
          align-items:center!important;
          justify-content:center!important;
          text-align:center!important;
          white-space:nowrap!important;
          overflow:hidden!important;
          box-shadow:inset 0 1px 0 rgba(255,255,255,.025)!important;
        }
        .mobile a.on{
          color:#10151c!important;
          background:var(--brand)!important;
          border-color:var(--brand)!important;
          box-shadow:0 4px 14px rgba(201,243,91,.22)!important;
        }
      }
      @media(max-width:380px){
        .layout{padding-bottom:74px!important}
        .mobile{gap:3px!important;padding:5px 3px calc(5px + env(safe-area-inset-bottom))!important}
        .mobile a{min-height:46px!important;font-size:8px!important;border-radius:9px!important;padding:4px 0!important}
      }
    `;
    document.head.appendChild(style);
  }
})();

document.querySelectorAll('input[inputmode="numeric"]').forEach(i=>i.addEventListener('input',()=>{i.value=i.value.replace(/[^0-9]/g,'')}));
document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('button[type="submit"],button:not([type])');if(b){b.dataset.old=b.textContent;b.textContent='در حال ثبت...';b.disabled=true;setTimeout(()=>{b.disabled=false;b.textContent=b.dataset.old||'ثبت'},5000)}}));
