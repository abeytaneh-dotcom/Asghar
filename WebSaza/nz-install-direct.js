(()=>{
  let installPrompt=null;
  const isStandalone=()=>window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);

  const showStatus=(message)=>{
    let toast=document.getElementById('nzInstallStatus');
    if(!toast){
      toast=document.createElement('div');
      toast.id='nzInstallStatus';
      toast.setAttribute('role','status');
      Object.assign(toast.style,{
        position:'fixed',left:'50%',bottom:'88px',transform:'translateX(-50%)',zIndex:'9999',
        background:'#151515',color:'#fff',padding:'10px 14px',borderRadius:'12px',font:'800 12px Tahoma,Arial,sans-serif',
        direction:'rtl',boxShadow:'0 8px 28px rgba(0,0,0,.25)',maxWidth:'calc(100vw - 32px)',textAlign:'center'
      });
      document.body.appendChild(toast);
    }
    toast.textContent=message;
    toast.style.display='block';
    clearTimeout(showStatus.timer);
    showStatus.timer=setTimeout(()=>{toast.style.display='none';},2600);
  };

  window.addEventListener('beforeinstallprompt',e=>{
    e.preventDefault();
    installPrompt=e;
  });

  window.addEventListener('appinstalled',()=>{
    installPrompt=null;
    const btn=document.getElementById('installMini');
    if(btn) btn.style.display='none';
  });

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

      if(installPrompt){
        const prompt=installPrompt;
        installPrompt=null;
        try{
          await prompt.prompt();
          const choice=await prompt.userChoice;
          if(choice&&choice.outcome==='accepted') btn.style.display='none';
        }catch(_){
          installPrompt=prompt;
        }
        return;
      }

      if(isIos){
        showStatus('iPhone نصب مستقیم خودکار را پشتیبانی نمی‌کند.');
      }else{
        showStatus('نصب مستقیم در این مرورگر در دسترس نیست.');
      }
    },true);
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',bind,{once:true});
  else bind();
})();
