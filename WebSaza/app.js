(()=>{
  const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>[...c.querySelectorAll(s)];
  const menu=q('.menu-toggle'),nav=q('.nav-links');
  if(menu&&nav){
    menu.addEventListener('click',()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open))});
    qa('.nav-links a').forEach(a=>a.addEventListener('click',()=>{nav.classList.remove('open');menu.setAttribute('aria-expanded','false')}));
    document.addEventListener('click',e=>{if(!nav.contains(e.target)&&!menu.contains(e.target)){nav.classList.remove('open');menu.setAttribute('aria-expanded','false')}})
  }

  const year=q('[data-year]');
  if(year)year.textContent=new Date().getFullYear();

  const reveals=qa('.reveal');
  if('IntersectionObserver'in window){
    const io=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('show');io.unobserve(e.target)}}),{threshold:.08,rootMargin:'90px 0px'});
    reveals.forEach(el=>io.observe(el))
  }else reveals.forEach(el=>el.classList.add('show'));

  const header=q('.site-header');
  addEventListener('scroll',()=>{if(header)header.classList.toggle('is-scrolled',scrollY>14)},{passive:true});
  qa('.faq details').forEach(d=>d.addEventListener('toggle',()=>{if(d.open)qa('.faq details').filter(x=>x!==d).forEach(x=>x.removeAttribute('open'))}));

  /* Floating contact menu */
  if(!q('.ws-contact-fab')){
    const phone='09993068027';
    const whatsapp='989993068027';
    const message='سلام جهت طراحی وبسایت و اپلیکیشن پیام میدم';

    const style=document.createElement('style');
    style.textContent=`
      .ws-contact-fab{position:fixed;left:max(18px,env(safe-area-inset-left));bottom:max(20px,calc(env(safe-area-inset-bottom) + 20px));z-index:9999;font-family:inherit;direction:rtl}
      .ws-contact-fab *{box-sizing:border-box}
      .ws-contact-main{width:62px;height:62px;border:0;border-radius:22px;background:#baf45b;color:#101619;display:grid;place-items:center;cursor:pointer;box-shadow:0 16px 42px rgba(9,20,18,.24),0 0 0 1px rgba(15,24,24,.08);transition:transform .2s ease,box-shadow .2s ease;background-image:linear-gradient(145deg,#c8ff69,#a8e84a)}
      .ws-contact-main:hover{transform:translateY(-2px);box-shadow:0 20px 48px rgba(9,20,18,.3)}
      .ws-contact-main:active{transform:scale(.96)}
      .ws-contact-main svg{width:29px;height:29px;display:block}
      .ws-contact-menu{position:absolute;left:0;bottom:74px;display:grid;gap:10px;min-width:196px;opacity:0;visibility:hidden;transform:translateY(12px) scale(.97);transform-origin:bottom left;transition:opacity .18s ease,transform .18s ease,visibility .18s ease;pointer-events:none}
      .ws-contact-fab.open .ws-contact-menu{opacity:1;visibility:visible;transform:none;pointer-events:auto}
      .ws-contact-option{height:54px;padding:0 14px;border-radius:17px;text-decoration:none;display:flex;align-items:center;gap:11px;background:#11191d;color:#fff;border:1px solid rgba(255,255,255,.11);box-shadow:0 12px 32px rgba(5,15,14,.18);white-space:nowrap;font-size:14px;font-weight:700;line-height:1}
      .ws-contact-option:hover{background:#172227}
      .ws-contact-option .ws-icon{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;flex:0 0 34px}
      .ws-contact-option.phone .ws-icon{background:#baf45b;color:#101619}
      .ws-contact-option.whatsapp .ws-icon{background:#25D366;color:#fff}
      .ws-contact-option svg{width:19px;height:19px;display:block}
      .ws-contact-label{display:grid;gap:4px;text-align:right}
      .ws-contact-label small{font-size:11px;font-weight:500;color:#aeb9bd;direction:ltr;text-align:right}
      .ws-contact-main .ws-x{display:none}
      .ws-contact-fab.open .ws-contact-main .ws-chat{display:none}
      .ws-contact-fab.open .ws-contact-main .ws-x{display:block}
      @media(max-width:640px){
        .ws-contact-fab{left:max(14px,env(safe-area-inset-left));bottom:max(16px,calc(env(safe-area-inset-bottom) + 16px))}
        .ws-contact-main{width:58px;height:58px;border-radius:20px}
        .ws-contact-menu{bottom:69px;min-width:185px}
        .ws-contact-option{height:52px;border-radius:16px}
      }
      @media(prefers-reduced-motion:reduce){.ws-contact-main,.ws-contact-menu{transition:none}}
    `;
    document.head.appendChild(style);

    const fab=document.createElement('div');
    fab.className='ws-contact-fab';
    fab.innerHTML=`
      <div class="ws-contact-menu" id="ws-contact-menu" aria-hidden="true">
        <a class="ws-contact-option phone" href="tel:${phone}" aria-label="تماس تلفنی با وب ساز">
          <span class="ws-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M7.2 3.5 9.6 7.7c.3.6.2 1.2-.3 1.6l-1.2 1c1.1 2.3 3 4.2 5.3 5.3l1-1.2c.4-.5 1-.6 1.6-.3l4.2 2.4c.6.3.9 1 .7 1.7l-.5 2c-.2.8-.9 1.3-1.7 1.3C9.7 21.5 2.5 14.3 2.5 5.3c0-.8.5-1.5 1.3-1.7l2-.5c.6-.2 1.1 0 1.4.4Z" fill="currentColor"/></svg></span>
          <span class="ws-contact-label"><span>تماس تلفنی</span><small>${phone}</small></span>
        </a>
        <a class="ws-contact-option whatsapp" href="https://wa.me/${whatsapp}?text=${encodeURIComponent(message)}" target="_blank" rel="noopener noreferrer" aria-label="پیام در واتساپ به وب ساز">
          <span class="ws-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a9.8 9.8 0 0 0-8.5 14.7L2 22l5.5-1.4A10 10 0 1 0 12 2Zm0 17.8a7.8 7.8 0 0 1-4-1.1l-.3-.2-3.2.8.9-3.1-.2-.3A7.8 7.8 0 1 1 12 19.8Zm4.3-5.8c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.4 6.4 0 0 1-1.9-1.2 7 7 0 0 1-1.3-1.6c-.1-.2 0-.4.1-.5l.4-.5.3-.5c.1-.2 0-.4 0-.5l-.8-2c-.2-.5-.5-.4-.7-.4h-.5c-.2 0-.5.1-.7.3-.3.3-1 1-1 2.4s1 2.7 1.1 2.9c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.4-.6 1.6-1.1.2-.6.2-1 .2-1.1-.1-.1-.3-.2-.5-.3Z"/></svg></span>
          <span class="ws-contact-label"><span>واتساپ</span><small>پیام مستقیم</small></span>
        </a>
      </div>
      <button class="ws-contact-main" type="button" aria-label="تماس با ما" aria-expanded="false" aria-controls="ws-contact-menu">
        <svg class="ws-chat" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 5.8A3.3 3.3 0 0 1 7.8 2.5h8.4a3.3 3.3 0 0 1 3.3 3.3v6.4a3.3 3.3 0 0 1-3.3 3.3h-4.9L6.5 19v-3.5A3.3 3.3 0 0 1 4.5 12V5.8Z" fill="currentColor"/><circle cx="8.5" cy="9" r="1" fill="#baf45b"/><circle cx="12" cy="9" r="1" fill="#baf45b"/><circle cx="15.5" cy="9" r="1" fill="#baf45b"/></svg>
        <svg class="ws-x" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      </button>`;
    document.body.appendChild(fab);

    const trigger=q('.ws-contact-main',fab),contactMenu=q('.ws-contact-menu',fab);
    const setOpen=open=>{fab.classList.toggle('open',open);trigger.setAttribute('aria-expanded',String(open));contactMenu.setAttribute('aria-hidden',String(!open))};
    trigger.addEventListener('click',e=>{e.stopPropagation();setOpen(!fab.classList.contains('open'))});
    contactMenu.addEventListener('click',e=>e.stopPropagation());
    document.addEventListener('click',()=>setOpen(false));
    document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
  }
})();