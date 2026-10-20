(()=>{
  'use strict';
  const KEY='niazestan_first_tour_v1';
  const $=(s,r=document)=>r.querySelector(s);
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];

  const canUseStorage=()=>{try{localStorage.setItem('__nz_t','1');localStorage.removeItem('__nz_t');return true}catch(_){return false}};
  const hasSeen=()=>{
    try{if(canUseStorage()&&localStorage.getItem(KEY)==='1')return true}catch(_){}
    return document.cookie.split(';').some(x=>x.trim().startsWith(KEY+'='));
  };
  const markSeen=()=>{
    try{if(canUseStorage())localStorage.setItem(KEY,'1')}catch(_){}
    document.cookie=`${KEY}=1; Max-Age=31536000; Path=/nz; SameSite=Lax`;
  };
  const isVisible=el=>{
    if(!el)return false;const r=el.getBoundingClientRect(),s=getComputedStyle(el);
    return r.width>20&&r.height>20&&s.display!=='none'&&s.visibility!=='hidden'&&Number(s.opacity)!==0;
  };
  const firstVisible=selectors=>{
    for(const sel of selectors){for(const el of $$(sel)){if(isVisible(el))return el}}
    return null;
  };

  function buildSteps(){
    const raw=[
      {
        selectors:['.mobile-search','.top-search'],
        title:'از همین‌جا هر چیزی را پیدا کن',
        text:'نام کالا، شغل یا خدمتی که می‌خواهی را بنویس؛ نتایج همان لحظه برایت پیدا می‌شوند.'
      },
      {
        selectors:['.mobile-location','.location'],
        title:'شهر و محدوده را اینجا مشخص کن',
        text:'با انتخاب شهر، آگهی‌های نزدیک‌تر و مرتبط‌تر را می‌بینی.'
      },
      {
        selectors:['.category-strip'],
        title:'اگر خواستی از دسته‌بندی‌ها وارد شو',
        text:'برای پیدا کردن سریع‌تر، دسته موردنظرت را بزن؛ مثلاً املاک، خودرو، خدمات یا استخدام.'
      },
      {
        selectors:['.listing'],
        title:'هر کارت، یک آگهی کامل است',
        text:'روی هر آگهی بزن تا عکس‌ها، مشخصات، موقعیت و راه ارتباط با آگهی‌دهنده را ببینی.'
      },
      {
        selectors:['.mobile-nav .create','.post-btn','.nzux-post','a[href*="page=new"]'],
        title:'ثبت آگهی از اینجا شروع می‌شود',
        text:'ثبت آگهی رایگان و مرحله‌ای است؛ فقط چند مورد ساده را وارد می‌کنی و تمام.'
      },
      {
        selectors:['.mobile-nav a:nth-child(2)','a[href*="page=favorites"]'],
        title:'آگهی‌های موردعلاقه‌ات را نگه دار',
        text:'هر چیزی را که پسندیدی ذخیره کن تا بعداً بدون جست‌وجوی دوباره به آن برگردی.'
      },
      {
        selectors:['.mobile-nav a:nth-child(4)','a[href*="page=messages"]','a[href*="page=chats"]'],
        title:'پیام‌ها و گفتگوها اینجاست',
        text:'گفتگو با آگهی‌دهنده‌ها را از این بخش دنبال کن و پاسخ‌ها را از دست نده.'
      },
      {
        selectors:['.mobile-nav a:nth-child(5)','.top-actions','a[href*="page=profile"]','a[href*="page=login"]'],
        title:'حساب کاربری و کارهای خودت',
        text:'از این بخش به حساب، آگهی‌های خودت و تنظیمات شخصی دسترسی داری.'
      }
    ];
    return raw.map(x=>({...x,target:firstVisible(x.selectors)})).filter(x=>x.target);
  }

  let steps=[],index=0,focus,card,blocker,arrow,titleEl,textEl,countEl,dotsEl,nextBtn,skipBtn,resizeTimer;

  function makeUi(){
    blocker=document.createElement('div');blocker.className='nz-tour-blocker';blocker.setAttribute('aria-hidden','true');
    focus=document.createElement('div');focus.className='nz-tour-focus';focus.setAttribute('aria-hidden','true');
    card=document.createElement('section');card.className='nz-tour-card';card.setAttribute('role','dialog');card.setAttribute('aria-modal','true');card.setAttribute('aria-label','راهنمای شروع نیازستان');
    card.innerHTML=`<div class="nz-tour-arrow"></div><div class="nz-tour-progress"><span class="nz-tour-count"></span><div class="nz-tour-dots"></div></div><h2 class="nz-tour-title"></h2><p class="nz-tour-text"></p><div class="nz-tour-hint">قسمت روشن‌شده را نگاه کن</div><div class="nz-tour-actions"><button type="button" class="nz-tour-next">متوجه شدم</button><button type="button" class="nz-tour-skip">رد کردن آموزش</button></div>`;
    document.body.append(blocker,focus,card);
    arrow=$('.nz-tour-arrow',card);titleEl=$('.nz-tour-title',card);textEl=$('.nz-tour-text',card);countEl=$('.nz-tour-count',card);dotsEl=$('.nz-tour-dots',card);nextBtn=$('.nz-tour-next',card);skipBtn=$('.nz-tour-skip',card);
    nextBtn.addEventListener('click',()=>go(index+1));
    skipBtn.addEventListener('click',finish);
    blocker.addEventListener('click',e=>{e.preventDefault();e.stopPropagation()});
    card.addEventListener('click',e=>e.stopPropagation());
    document.addEventListener('keydown',onKey,true);
    window.addEventListener('resize',onResize,{passive:true});
  }

  function onKey(e){
    if(e.key==='Escape'){e.preventDefault();finish();return}
    if((e.key==='Enter'||e.key===' ')&&document.activeElement===nextBtn){e.preventDefault();go(index+1)}
  }
  function onResize(){clearTimeout(resizeTimer);resizeTimer=setTimeout(()=>place(steps[index]?.target),80)}

  function place(target){
    if(!target||!focus||!card)return;
    const r=target.getBoundingClientRect();
    const pad=8;
    const top=Math.max(8,r.top-pad),left=Math.max(8,r.left-pad);
    const width=Math.min(innerWidth-left-8,r.width+pad*2),height=Math.min(innerHeight-top-8,r.height+pad*2);
    focus.style.top=top+'px';focus.style.left=left+'px';focus.style.width=Math.max(34,width)+'px';focus.style.height=Math.max(34,height)+'px';

    const cardW=Math.min(370,innerWidth-24),estimatedH=Math.max(190,card.offsetHeight||210),gap=16;
    let place='below',cardTop=top+height+gap;
    if(cardTop+estimatedH>innerHeight-10){place='above';cardTop=top-estimatedH-gap}
    if(cardTop<10){place='below';cardTop=Math.min(innerHeight-estimatedH-10,top+height+gap)}
    let cardLeft=Math.min(innerWidth-cardW-10,Math.max(10,left+(width-cardW)/2));
    card.style.width=cardW+'px';card.style.top=Math.max(10,cardTop)+'px';card.style.left=cardLeft+'px';card.dataset.place=place;

    const center=Math.max(18,Math.min(cardW-18,(left+width/2)-cardLeft));
    arrow.style.left=(center-7)+'px';
  }

  function ensureInView(target,done){
    const r=target.getBoundingClientRect();
    const safeTop=90,safeBottom=innerHeight-230;
    if(r.top<safeTop||r.bottom>safeBottom){
      target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center',inline:'nearest'});
      setTimeout(done,360);
    }else done();
  }

  function render(){
    const step=steps[index];if(!step)return finish();
    card.classList.add('nz-tour-switching');
    titleEl.textContent=step.title;textEl.textContent=step.text;countEl.textContent=`${index+1} از ${steps.length}`;
    dotsEl.innerHTML=steps.map((_,i)=>`<span class="nz-tour-dot ${i<index?'done':i===index?'on':''}"></span>`).join('');
    nextBtn.textContent=index===steps.length-1?'تمام شد':'متوجه شدم';
    ensureInView(step.target,()=>{
      place(step.target);
      requestAnimationFrame(()=>{card.classList.remove('nz-tour-switching');nextBtn.focus({preventScroll:true})});
    });
  }

  function go(nextIndex){
    if(nextIndex>=steps.length){finish();return}
    index=nextIndex;render();
  }

  function finish(){
    document.body.classList.remove('nz-tour-lock');
    document.removeEventListener('keydown',onKey,true);window.removeEventListener('resize',onResize);
    blocker?.remove();focus?.remove();card?.remove();
    blocker=focus=card=null;
  }

  function start(){
    const params=new URLSearchParams(location.search);
    const page=params.get('page')||'home';
    if(page!=='home'||params.has('id')||hasSeen())return;
    steps=buildSteps();
    if(steps.length<4)return;
    markSeen();
    document.body.classList.add('nz-tour-lock');
    makeUi();render();
  }

  const boot=()=>setTimeout(start,950);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
