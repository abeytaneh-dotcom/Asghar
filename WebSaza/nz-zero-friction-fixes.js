(()=>{
  'use strict';
  const $=(s,r=document)=>r.querySelector(s);
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const mobileRe=/(آیفون|گوشی|موبایل|سامسونگ|شیائومی|هواوی|پوکو)/i;

  function chooseMobileCategory(){
    const trigger=$('#nzChooseCategory');
    if(!trigger)return;
    trigger.click();
    setTimeout(()=>{
      const modal=$('.nz-modal.show');
      if(!modal)return;
      const root=$$('.nz-list button',modal).find(b=>String(b.textContent||'').includes('کالای دیجیتال'));
      if(!root)return;
      root.click();
      setTimeout(()=>{
        const child=$$('.nz-list button',modal).find(b=>String(b.textContent||'').trim()==='موبایل');
        child?.click();
      },40);
    },40);
  }

  function init(){
    if(new URLSearchParams(location.search).get('page')!=='new')return;
    const title=$('input[name="title"]');
    const desc=$('textarea[name="description"]');
    const cat=$('select[name="category"]');
    const sub=$('select[name="subcategory"]');
    const suggest=$('.nzux-smart-suggest');
    if(!title||!suggest)return;

    let fallbackActive=false;
    const refresh=()=>{
      const text=`${title.value||''} ${desc?.value||''}`;
      fallbackActive=mobileRe.test(text)&&!cat?.value&&!sub?.value;
      if(fallbackActive&&!suggest.classList.contains('show')){
        const b=$('b',suggest),s=$('span',suggest);
        if(b)b.textContent='پیشنهاد: کالای دیجیتال ← موبایل';
        if(s)s.textContent='با یک لمس دسته را انتخاب کن';
        suggest.classList.add('show');
      }
    };

    suggest.addEventListener('click',e=>{
      if(!fallbackActive)return;
      e.preventDefault();
      e.stopImmediatePropagation();
      chooseMobileCategory();
      fallbackActive=false;
    },true);
    title.addEventListener('input',refresh);
    desc?.addEventListener('input',refresh);
    refresh();
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(init,20),{once:true});
  else setTimeout(init,20);
})();
