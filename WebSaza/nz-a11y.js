(()=>{
'use strict';
function text(el){return (el?.getAttribute('aria-label')||el?.getAttribute('title')||el?.textContent||'').replace(/\s+/g,' ').trim()}
function enhance(){
  const viewport=document.querySelector('meta[name="viewport"]');
  if(viewport){
    const parts=(viewport.getAttribute('content')||'').split(',').map(x=>x.trim()).filter(Boolean).filter(x=>!/^maximum-scale=/i.test(x)&&!/^user-scalable=/i.test(x));
    if(!parts.some(x=>/^width=/i.test(x)))parts.unshift('width=device-width');
    if(!parts.some(x=>/^initial-scale=/i.test(x)))parts.push('initial-scale=1');
    viewport.setAttribute('content',parts.join(','));
  }
  const main=document.querySelector('main');
  if(main&&!main.id)main.id='main-content';
  if(main&&!document.querySelector('.nz-skip-link')){
    const a=document.createElement('a');a.className='nz-skip-link';a.href='#'+main.id;a.textContent='رفتن به محتوای اصلی';document.body.prepend(a);
  }
  document.querySelectorAll('.field').forEach((field,idx)=>{
    const label=field.querySelector('label');const control=field.querySelector('input:not([type=hidden]),select,textarea,button');if(!label||!control)return;
    if(!control.id)control.id='nz-field-'+idx+'-'+Math.random().toString(36).slice(2,7);
    if(!label.htmlFor)label.htmlFor=control.id;
  });
  document.querySelectorAll('select.mobile-location').forEach(el=>{if(!el.getAttribute('aria-label'))el.setAttribute('aria-label','انتخاب شهر')});
  document.querySelectorAll('button,a[href]').forEach(el=>{
    if(text(el))return;
    const svgTitle=el.querySelector('svg title')?.textContent?.trim();
    const imgAlt=el.querySelector('img')?.getAttribute('alt')?.trim();
    const fallback=svgTitle||imgAlt||el.dataset?.label||'';
    if(fallback)el.setAttribute('aria-label',fallback);
  });
  document.querySelectorAll('.flash,.toast,.nz-geo-status,.nz-media-info').forEach(el=>{if(!el.hasAttribute('role'))el.setAttribute('role','status');if(!el.hasAttribute('aria-live'))el.setAttribute('aria-live','polite')});
  document.querySelectorAll('img:not([alt])').forEach(img=>img.setAttribute('alt',''));
  document.querySelectorAll('input[required],select[required],textarea[required]').forEach(el=>el.setAttribute('aria-required','true'));
}
function style(){if(document.getElementById('nzA11yStyle'))return;const s=document.createElement('style');s.id='nzA11yStyle';s.textContent=`:root{--muted:#666d76!important}.section-head p,.section-head .count,.listing-meta,.subtle{color:#666d76!important}.nz-skip-link{position:fixed;z-index:100000;top:8px;right:8px;transform:translateY(-180%);background:#111;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:800}.nz-skip-link:focus{transform:none}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid currentColor;outline-offset:3px}@media(max-width:760px){button,a.btn,.menu-item,.cat-chip{min-height:44px}}`;document.head.appendChild(s)}
function run(){style();enhance()}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run,{once:true});else run();
new MutationObserver(m=>{if(m.some(x=>x.addedNodes.length))enhance()}).observe(document.documentElement,{childList:true,subtree:true});
})();
