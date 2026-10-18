(()=>{
  let installPrompt=null;
  const GUIDE='/niazestan-install-guide.php?v=20261019-2';
  const isStandalone=()=>window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);

  window.addEventListener('beforeinstallprompt',e=>{
    e.preventDefault();
    installPrompt=e;
  });

  window.addEventListener('appinstalled',()=>{
    installPrompt=null;
    const btn=document.getElementById('installMini');
    if(btn) btn.style.display='none';
    const modal=document.getElementById('nzInstallGuideModal');
    if(modal) modal.remove();
  });

  const showGuide=()=>{
    let modal=document.getElementById('nzInstallGuideModal');
    if(modal){modal.style.display='flex';return;}

    modal=document.createElement('div');
    modal.id='nzInstallGuideModal';
    modal.setAttribute('role','dialog');
    modal.setAttribute('aria-modal','true');
    modal.innerHTML=`<div class="nz-install-guide-card"><button type="button" class="nz-install-guide-close" aria-label="بستن">×</button><div class="nz-install-guide-loading">در حال باز کردن راهنمای نصب…</div><img src="${GUIDE}" alt="راهنمای تصویری نصب برنامه نیازستان" class="nz-install-guide-image"></div>`;
    Object.assign(modal.style,{position:'fixed',inset:'0',zIndex:'10000',background:'rgba(7,12,20,.72)',display:'flex',alignItems:'center',justifyContent:'center',padding:'12px',direction:'rtl'});
    document.body.appendChild(modal);

    const card=modal.querySelector('.nz-install-guide-card');
    Object.assign(card.style,{position:'relative',width:'min(94vw,620px)',maxHeight:'94vh',overflow:'auto',background:'#fff',borderRadius:'20px',boxShadow:'0 24px 80px rgba(0,0,0,.35)',padding:'8px'});
    const close=modal.querySelector('.nz-install-guide-close');
    Object.assign(close.style,{position:'sticky',top:'6px',float:'left',zIndex:'3',width:'38px',height:'38px',border:'0',borderRadius:'50%',background:'#111',color:'#fff',fontSize:'26px',lineHeight:'36px',cursor:'pointer'});
    const loading=modal.querySelector('.nz-install-guide-loading');
    Object.assign(loading.style,{padding:'50px 20px',textAlign:'center',font:'800 13px Tahoma,Arial,sans-serif',color:'#555'});
    const image=modal.querySelector('.nz-install-guide-image');
    Object.assign(image.style,{display:'none',width:'100%',height:'auto',borderRadius:'14px'});
    image.onload=()=>{loading.style.display='none';image.style.display='block';};
    image.onerror=()=>{loading.innerHTML='راهنمای تصویری بارگذاری نشد.<br><a href="'+GUIDE+'" target="_blank" rel="noopener" style="display:inline-block;margin-top:12px;color:#b4232c;font-weight:900">باز کردن راهنمای نصب</a>';};

    const hide=()=>{modal.style.display='none';};
    close.addEventListener('click',hide);
    modal.addEventListener('click',e=>{if(e.target===modal)hide();});
  };

  const bind=()=>{
    const btn=document.getElementById('installMini');
    if(!btn) return;

    const oldSheet=document.getElementById('installSheet');
    if(oldSheet) oldSheet.remove();

    if(isStandalone()){
      btn.style.display='none';
      return;
    }

    btn.addEventListener('click',async e=>{
      e.preventDefault();
      e.stopImmediatePropagation();

      if(isStandalone()){
        btn.style.display='none';
        return;
      }

      if(installPrompt&&!isIos){
        const prompt=installPrompt;
        installPrompt=null;
        try{
          await prompt.prompt();
          const choice=await prompt.userChoice;
          if(choice&&choice.outcome==='accepted'){
            btn.style.display='none';
            return;
          }
        }catch(_){ }
        showGuide();
        return;
      }

      showGuide();
    },true);
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',bind,{once:true});
  else bind();
})();
