(()=>{
'use strict';
function init(){
  const mode=document.querySelector('input[name="location_mode"]');
  if(mode&&(!mode.value||mode.value==='exact'))mode.value='approx';
  const modal=document.querySelector('.nz-location-modal, #nzLocationModal, [data-nz-location-modal]');
  const exact=modal?.querySelector('input[name="nzLocMode"][value="exact"]');
  const approx=modal?.querySelector('input[name="nzLocMode"][value="approx"]');
  if(approx&&mode?.value==='approx')approx.checked=true;
  if(exact){
    const host=exact.closest('label')||exact.parentElement;
    if(host&&!modal.querySelector('.nz-exact-warning')){
      const note=document.createElement('small');note.className='nz-exact-warning';note.setAttribute('role','note');note.textContent='موقعیت دقیق برای کاربران قابل مشاهده می‌شود. برای حفظ حریم خصوصی، موقعیت حدودی پیشنهاد می‌شود.';note.style.cssText='display:block;color:#8a5a00;margin-top:6px;line-height:1.7';host.insertAdjacentElement('afterend',note);
    }
  }
  const save=modal?.querySelector('.nz-modal-save');
  if(save&&!save.dataset.nzPrivacyBound){
    save.dataset.nzPrivacyBound='1';
    save.addEventListener('click',e=>{
      const picked=modal.querySelector('input[name="nzLocMode"]:checked')?.value;
      if(picked==='exact'&&!confirm('موقعیت دقیق انتخاب شده است و محل انتخاب‌شده برای کاربران قابل مشاهده خواهد بود. ادامه می‌دهید؟')){
        e.preventDefault();e.stopImmediatePropagation();if(approx)approx.checked=true;if(mode)mode.value='approx';
      }
    },true);
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(init,0),{once:true});else setTimeout(init,0);
})();
