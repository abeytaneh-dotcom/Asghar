(()=>{
  'use strict';
  const KEY='niazestan_first_tour_v3';
  const $=(s,r=document)=>r.querySelector(s);
  const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));

  const hasSeen=()=>{
    try{if(localStorage.getItem(KEY)==='1')return true}catch(_){}
    return document.cookie.split(';').some(x=>x.trim().startsWith(KEY+'='));
  };
  const markSeen=()=>{
    try{localStorage.setItem(KEY,'1')}catch(_){}
    document.cookie=`${KEY}=1; Max-Age=31536000; Path=/nz; SameSite=Lax`;
  };
  const visible=el=>{
    if(!el||!el.isConnected)return false;
    const r=el.getBoundingClientRect(),s=getComputedStyle(el);
    return r.width>12&&r.height>12&&s.display!=='none'&&s.visibility!=='hidden'&&Number(s.opacity)!==0;
  };
  const firstVisible=selectors=>{
    for(const sel of selectors){for(const el of $$(sel)){if(visible(el))return el}}
    return null;
  };

  function buildSteps(){
    const defs=[
      [['.mobile-search','.top-search','input[name="q"]'],'جستجو از همین‌جا شروع می‌شود','نام کالا، شغل یا خدمتی که می‌خواهی را بنویس؛ نیازستان نتیجه‌های مرتبط را برایت پیدا می‌کند.'],
      [['.mobile-location','.location','select[name="city"]'],'شهر و محدوده را انتخاب کن','با انتخاب شهر، آگهی‌های نزدیک‌تر و مناسب‌تر را می‌بینی.'],
      [['.category-strip','.cat-chip'],'دسته‌بندی‌ها مسیر سریع‌تر هستند','اگر خواستی مستقیم وارد املاک، خودرو، خدمات، استخدام و بقیه دسته‌ها شو.'],
      [['.listing','.listings'],'هر کارت یک آگهی است','روی هر آگهی بزن تا عکس‌ها، مشخصات، موقعیت و راه ارتباط با آگهی‌دهنده را ببینی.'],
      [['.mobile-nav .create','.post-btn','.nzux-post','a[href*="page=new"]'],'ثبت آگهی از اینجا','ثبت آگهی رایگان و مرحله‌ای است؛ فقط چند مورد ساده را وارد می‌کنی.'],
      [['.mobile-nav a:nth-child(2)','a[href*="page=favorites"]'],'ذخیره‌ها','آگهی‌هایی را که پسندیدی ذخیره کن تا بعداً سریع به آن‌ها برگردی.'],
      [['.mobile-nav a:nth-child(4)','a[href*="page=messages"]','a[href*="page=chats"]'],'پیام‌ها و گفتگوها','گفتگو با آگهی‌دهنده‌ها و پاسخ‌های جدیدت را از این بخش دنبال کن.'],
      [['.mobile-nav a:nth-child(5)','.top-actions','a[href*="page=profile"]','a[href*="page=login"]'],'حساب کاربری','آگهی‌های خودت، حساب و تنظیمات شخصی از اینجا در دسترس هستند.']
    ];
    return defs.map(([selectors,title,text])=>({target:firstVisible(selectors),title,text})).filter(x=>x.target);
  }

  let steps=[],index=0,focus,card,blocker,arrow,titleEl,textEl,countEl,dotsEl,nextBtn,skipBtn,resizeTimer,started=false;

  function makeUi(){
    blocker=document.createElement('div'); blocker.className='nz-tour-blocker'; blocker.setAttribute('aria-hidden','true');
    focus=document.createElement('div'); focus.className='nz-tour-focus'; focus.setAttribute('aria-hidden','true');
    card=document.createElement('section'); card.className='nz-tour-card'; card.setAttribute('role','dialog'); card.setAttribute('aria-modal','true'); card.setAttribute('aria-label','راهنمای شروع نیازستان');
    card.innerHTML='<div class="nz-tour-arrow"></div><div class="nz-tour-progress"><span class="nz-tour-count"></span><div class="nz-tour-dots"></div></div><h2 class="nz-tour-title"></h2><p class="nz-tour-text"></p><div class="nz-tour-hint">قسمت روشن‌شده را نگاه کن</div><div class="nz-tour-actions"><button type="button" class="nz-tour-next">متوجه شدم</button><button type="button" class="nz-tour-skip">رد کردن آموزش</button></div>';
    document.body.append(blocker,focus,card);
    arrow=$('.nz-tour-arrow',card); titleEl=$('.nz-tour-title',card); textEl=$('.nz-tour-text',card); countEl=$('.nz-tour-count',card); dotsEl=$('.nz-tour-dots',card); nextBtn=$('.nz-tour-next',card); skipBtn=$('.nz-tour-skip',card);
    nextBtn.addEventListener('click',()=>go(index+1));
    skipBtn.addEventListener('click',finish);
    blocker.addEventListener('click',e=>{e.preventDefault();e.stopPropagation()});
    card.addEventListener('click',e=>e.stopPropagation());
    document.addEventListener('keydown',onKey,true);
    window.addEventListener('resize',onResize,{passive:true});
  }

  function onKey(e){if(e.key==='Escape'){e.preventDefault();finish()}}
  function onResize(){clearTimeout(resizeTimer);resizeTimer=setTimeout(()=>place(steps[index]?.target),80)}

  function place(target){
    if(!target||!focus||!card)return;
    const r=target.getBoundingClientRect(),pad=8;
    const top=Math.max(8,r.top-pad),left=Math.max(8,r.left-pad);
    const width=Math.min(innerWidth-left-8,r.width+pad*2),height=Math.min(innerHeight-top-8,r.height+pad*2);
    Object.assign(focus.style,{top:top+'px',left:left+'px',width:Math.max(34,width)+'px',height:Math.max(34,height)+'px'});
    const cardW=Math.min(370,innerWidth-24),cardH=Math.max(190,card.offsetHeight||210),gap=16;
    let placeName='below',cardTop=top+height+gap;
    if(cardTop+cardH>innerHeight-10){placeName='above';cardTop=top-cardH-gap}
    if(cardTop<10){placeName='below';cardTop=Math.min(innerHeight-cardH-10,top+height+gap)}
    const cardLeft=Math.min(innerWidth-cardW-10,Math.max(10,left+(width-cardW)/2));
    Object.assign(card.style,{width:cardW+'px',top:Math.max(10,cardTop)+'px',left:cardLeft+'px'}); card.dataset.place=placeName;
    arrow.style.left=(Math.max(18,Math.min(cardW-18,(left+width/2)-cardLeft))-7)+'px';
  }

  function ensureInView(target,done){
    const r=target.getBoundingClientRect(),safeTop=90,safeBottom=innerHeight-230;
    if(r.top<safeTop||r.bottom>safeBottom){
      target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center',inline:'nearest'});
      setTimeout(done,420);
    }else done();
  }

  function render(){
    const step=steps[index]; if(!step)return finish();
    if(!visible(step.target)){
      steps=buildSteps();
      if(index>=steps.length)index=Math.max(0,steps.length-1);
    }
    const current=steps[index]; if(!current)return finish();
    card.classList.add('nz-tour-switching');
    titleEl.textContent=current.title; textEl.textContent=current.text; countEl.textContent=`${index+1} از ${steps.length}`;
    dotsEl.innerHTML=steps.map((_,i)=>`<span class="nz-tour-dot ${i<index?'done':i===index?'on':''}"></span>`).join('');
    nextBtn.textContent=index===steps.length-1?'تمام شد':'متوجه شدم';
    ensureInView(current.target,()=>{place(current.target);requestAnimationFrame(()=>{card.classList.remove('nz-tour-switching');nextBtn.focus({preventScroll:true})})});
  }

  function go(next){if(next>=steps.length)return finish();index=next;render()}
  function finish(){
    document.body.classList.remove('nz-tour-lock');
    document.removeEventListener('keydown',onKey,true); window.removeEventListener('resize',onResize);
    blocker?.remove(); focus?.remove(); card?.remove(); blocker=focus=card=null;
  }

  function tryStart(attempt=0){
    if(started)return;
    const params=new URLSearchParams(location.search),page=params.get('page')||'home',force=params.get('tour')==='1'||window.__NZ_TOUR_FORCE__===true;
    if(page!=='home'||params.has('id')||(!force&&hasSeen()))return;
    steps=buildSteps();
    if(steps.length===0&&attempt<15){setTimeout(()=>tryStart(attempt+1),300);return}
    if(steps.length===0)return;
    started=true;
    if(!force)markSeen();
    document.body.classList.add('nz-tour-lock'); makeUi(); render();
  }

  const boot=()=>setTimeout(()=>tryStart(0),250);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();