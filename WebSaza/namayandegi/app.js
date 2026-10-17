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

document.querySelectorAll('input[inputmode="numeric"]').forEach(i=>i.addEventListener('input',()=>{i.value=i.value.replace(/[^0-9]/g,'')}));
document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('button[type="submit"],button:not([type])');if(b){b.dataset.old=b.textContent;b.textContent='در حال ثبت...';b.disabled=true;setTimeout(()=>{b.disabled=false;b.textContent=b.dataset.old||'ثبت'},5000)}}));
