(()=>{
  'use strict';

  const $=(s,r=document)=>r.querySelector(s);
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const params=new URLSearchParams(location.search);
  const page=params.get('page')||'home';
  const faToEn=s=>String(s||'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const visible=el=>!!(el&&el.getClientRects().length);
  const safeText=s=>String(s||'').trim();

  function enhanceNav(){
    const nav=$('.mobile-nav');if(!nav)return;
    const items=$$('a',nav);
    const labels=['خانه','ذخیره‌ها','ثبت رایگان','پیام‌ها','من'];
    items.forEach((a,i)=>{const spans=$$('span',a);const target=spans[spans.length-1];if(target&&labels[i]){target.textContent=labels[i];target.classList.add('nzux-nav-label')}});
  }

  function focusSearch(){
    const candidates=[$('.mobile-search input[name="q"]'),$('.top-search input[name="q"]'),$('input[name="q"]')].filter(Boolean);
    const input=candidates.find(visible)||candidates[0];
    if(input){input.focus({preventScroll:false});input.scrollIntoView({behavior:'smooth',block:'center'})}
  }

  function enhanceHome(){
    if(page!=='home'||$('.detail')||!$('.main'))return;
    const main=$('.main');
    const cats=$('.category-strip',main);
    if(cats&&!$('.nzux-home-hero',main)){
      const hero=document.createElement('section');hero.className='nzux-home-hero';
      hero.innerHTML=`<h1>چی لازم داری؟</h1><p>اسم کالا، شغل یا خدمتی که می‌خواهی را بنویس؛ نیازستان بقیه راه را ساده می‌کند.</p><div class="nzux-home-actions"><button type="button" class="btn nzux-find">⌕ پیدا کردن</button><a class="btn nzux-post" href="/nz?page=new">＋ ثبت رایگان</a></div>`;
      main.insertBefore(hero,cats);
      $('.nzux-find',hero).addEventListener('click',focusSearch);
    }

    const sectionHead=$('.section-head',main);
    const filterForm=$('.filter-card form',main);
    if(sectionHead&&filterForm&&!$('.nzux-result-tools',sectionHead)){
      const tools=document.createElement('div');tools.className='nzux-result-tools';
      tools.innerHTML='<button type="button" class="nzux-tool nzux-filter">☰ فیلتر</button><a class="nzux-tool" href="/nz?page=map">⌖ نقشه</a>';
      sectionHead.appendChild(tools);

      const sheet=document.createElement('div');sheet.className='nzux-sheet';sheet.setAttribute('aria-hidden','true');
      const clone=filterForm.cloneNode(true);clone.querySelectorAll('button').forEach(b=>b.textContent='نمایش نتیجه‌ها');
      sheet.innerHTML='<div class="nzux-sheet-card" role="dialog" aria-modal="true" aria-label="فیلتر آگهی‌ها"><div class="nzux-sheet-head"><h3>فیلتر آگهی‌ها</h3><button type="button" class="nzux-close" aria-label="بستن">×</button></div></div>';
      $('.nzux-sheet-card',sheet).appendChild(clone);document.body.appendChild(sheet);
      const close=()=>{sheet.classList.remove('show');sheet.setAttribute('aria-hidden','true')};
      $('.nzux-filter',tools).onclick=()=>{sheet.classList.add('show');sheet.setAttribute('aria-hidden','false')};
      $('.nzux-close',sheet).onclick=close;sheet.addEventListener('click',e=>{if(e.target===sheet)close()});
      document.addEventListener('keydown',e=>{if(e.key==='Escape'&&sheet.classList.contains('show'))close()});
    }

    const searchInputs=$$('input[name="q"]');
    searchInputs.forEach(i=>{if(!i.dataset.nzux){i.dataset.nzux='1';i.placeholder='مثلاً آیفون، حسابدار، تعمیر کولر…';i.setAttribute('autocomplete','off')}});
  }

  function enhanceLogin(){
    if(page!=='login')return;
    const phone=$('input[name="phone"]');
    if(phone){
      phone.classList.add('nzux-login-phone');phone.placeholder='0912 123 4567';phone.autocomplete='tel';
      phone.addEventListener('input',()=>{let d=faToEn(phone.value).replace(/\D/g,'').slice(0,11);phone.value=d});
      const form=phone.form;if(form&&!$('.nzux-login-note',form)){
        const note=document.createElement('div');note.className='nzux-login-note';note.textContent='فقط شماره موبایل لازم است؛ رمز عبور نمی‌خواهی.';form.insertBefore(note,form.firstChild);
      }
    }
    const code=$('input[name="code"]');
    if(code){
      code.inputMode='numeric';code.autocomplete='one-time-code';
      code.addEventListener('input',()=>{code.value=faToEn(code.value).replace(/\D/g,'').slice(0,5);if(code.value.length===5&&!code.dataset.submitting){code.dataset.submitting='1';setTimeout(()=>code.form?.requestSubmit(),120)}});
    }
  }

  const suggestions=[
    {re:/\b(آیفون|iphone|گوشی|موبایل|سامسونگ|شیائومی|هواوی|پوکو)\b/i,cat:'کالای دیجیتال',sub:'موبایل'},
    {re:/(لپ[‌ -]?تاپ|macbook|مک[‌ -]?بوک|نوت[‌ -]?بوک)/i,cat:'کالای دیجیتال',sub:'لپ‌تاپ'},
    {re:/(تبلت|ipad|آیپد)/i,cat:'کالای دیجیتال',sub:'تبلت'},
    {re:/(پلی[‌ -]?استیشن|ps5|ps4|xbox|کنسول)/i,cat:'کالای دیجیتال',sub:'کنسول بازی'},
    {re:/(خودرو|ماشین|پژو|پراید|سمند|دنا|تارا|کوییک|شاهین|سانتافه|تویوتا|هیوندای)/i,cat:'وسایل نقلیه',sub:'خودرو'},
    {re:/(موتورسیکلت|موتور سیکلت|یاماها|هوندا 125|بنلی)/i,cat:'وسایل نقلیه',sub:'موتورسیکلت'},
    {re:/(آپارتمان|خانه|ویلا|ملک|واحد مسکونی)/i,cat:'املاک',sub:'آپارتمان'},
    {re:/(تعمیر|سرویس|نصب|تأسیسات|کولر|پکیج)/i,cat:'خدمات',sub:'تعمیرات'},
    {re:/(طراحی سایت|برنامه[‌ -]?نویس|اپلیکیشن|طراحی اپ)/i,cat:'خدمات',sub:'طراحی و برنامه‌نویسی'},
    {re:/(استخدام|بازاریاب|فروشنده|ویزیتور)/i,cat:'استخدام و کاریابی',sub:'فروش و بازاریابی'},
    {re:/(حسابدار|حسابداری|مالی)/i,cat:'استخدام و کاریابی',sub:'مالی و حسابداری'},
    {re:/(مهندس|مهندسی|تکنسین|فنی)/i,cat:'استخدام و کاریابی',sub:'فنی و مهندسی'},
    {re:/(کارمند|منشی|اداری|اپراتور)/i,cat:'استخدام و کاریابی',sub:'اداری'}
  ];

  function findSuggestion(text){return suggestions.find(x=>x.re.test(text))||null}

  function enhanceWizard(){
    if(page!=='new')return;
    const form=$('.nz-wizard-form');if(!form||form.dataset.nzux)return;form.dataset.nzux='1';
    const steps=$$('.nz-step',form);if(steps.length<3)return;
    const title=$('input[name="title"]',form),desc=$('textarea[name="description"]',form),images=$('input[name="images[]"]',form),video=$('input[name="video"]',form),cat=$('select[name="category"]',form),sub=$('select[name="subcategory"]',form),price=$('input[name="price"]',form);
    const titleW=title?.closest('.field'),descW=desc?.closest('.field'),imagesW=images?.closest('.field'),videoW=video?.closest('.field'),priceW=price?.closest('.field');
    titleW?.classList.add('nzux-title-field');descW?.classList.add('nzux-desc-field');imagesW?.classList.add('nzux-photo-field');priceW?.classList.add('nzux-price-field');

    const stepIntro=(n,kicker,titleText,help)=>{const d=document.createElement('div');d.className='nzux-step-intro';d.innerHTML=`<div class="nzux-step-kicker">${kicker}</div><div class="nzux-step-title">${titleText}</div><div class="nzux-step-help">${help}</div>`;steps[n].insertBefore(d,steps[n].firstChild)};
    stepIntro(0,'مرحله ۱ از ۳','چی می‌خواهی ثبت کنی؟','اول اصل آگهی را بگو؛ جزئیات اضافی را بعداً می‌گیریم.');
    stepIntro(1,'مرحله ۲ از ۳','کجا و چه مشخصاتی؟','فقط اطلاعات ضروری را کامل کن. موارد اختیاری پنهان شده‌اند.');
    stepIntro(2,'مرحله ۳ از ۳','تمام؛ فقط قیمت و ثبت','اگر قیمت قطعی نداری، «توافقی» را بزن و آگهی را ثبت کن.');

    const catBtn=$('#nzChooseCategory');
    const catBlock=catBtn?.parentElement;
    if(catBlock&&titleW){
      const anchor=descW||imagesW||steps[0].lastChild;steps[0].insertBefore(catBlock,anchor);
      const sectionTitle=$('.nz-section-title',catBlock);if(sectionTitle)sectionTitle.textContent='دسته آگهی';
      const b=$('.nz-action-value b',catBtn),s=$('.nz-action-value small',catBtn);if(b)b.textContent='انتخاب دسته';if(s)s.textContent='اگر مطمئن نیستی، از پیشنهاد هوشمند استفاده کن.';
    }

    const suggest=document.createElement('button');suggest.type='button';suggest.className='nzux-smart-suggest';
    suggest.innerHTML='<div><b></b><span></span></div><strong>انتخاب</strong>';
    if(catBlock)catBlock.before(suggest);else titleW?.after(suggest);
    let currentSuggestion=null;
    const refreshSuggestion=()=>{
      const text=`${safeText(title?.value)} ${safeText(desc?.value)}`;currentSuggestion=findSuggestion(text);
      if(currentSuggestion&&(!cat?.value||!sub?.value)){
        $('b',suggest).textContent=`پیشنهاد: ${currentSuggestion.cat} ← ${currentSuggestion.sub}`;
        $('span',suggest).textContent='با یک لمس دسته را انتخاب کن';suggest.classList.add('show');
      }else suggest.classList.remove('show');
    };
    title?.addEventListener('input',refreshSuggestion);desc?.addEventListener('input',refreshSuggestion);

    function chooseCategory(category,subcategory){
      if(!catBtn)return;catBtn.click();
      setTimeout(()=>{
        const modal=$('.nz-modal.show');if(!modal)return;
        const root=$$('.nz-list button',modal).find(b=>safeText(b.firstChild?.textContent||b.textContent).startsWith(category));
        if(!root)return;root.click();
        setTimeout(()=>{const child=$$('.nz-list button',modal).find(b=>safeText(b.textContent)===subcategory);child?.click()},40);
      },40);
    }
    suggest.onclick=()=>{if(currentSuggestion)chooseCategory(currentSuggestion.cat,currentSuggestion.sub)};

    const firstNext=$('.nz-next',steps[0]);
    if(firstNext&&catBtn){
      const old=firstNext.onclick;
      firstNext.onclick=e=>{
        if(!cat?.value||!sub?.value){refreshSuggestion();if(currentSuggestion){chooseCategory(currentSuggestion.cat,currentSuggestion.sub)}else catBtn.click();return}
        if(typeof old==='function')old.call(firstNext,e);
      };
      firstNext.textContent='بعدی: مکان و مشخصات';
    }

    if(title){title.placeholder='مثلاً آیفون ۱۵ پرو تمیز، فروش فوری';title.maxLength=120}
    if(desc){desc.placeholder='فقط نکات مهم را بنویس؛ مثلاً سالم، بدون تعمیر، همراه جعبه…'}
    if(imagesW){const small=$('.subtle',imagesW);if(small)small.textContent='حداقل یک عکس واضح کافی است؛ نیازستان خودش حجم را کم می‌کند.'}

    if(videoW&&!videoW.closest('.nzux-optional')){
      const details=document.createElement('details');details.className='nzux-optional';details.innerHTML='<summary>افزودن ویدیو (اختیاری)</summary><div class="nzux-optional-body"></div>';
      videoW.before(details);$('.nzux-optional-body',details).appendChild(videoW);
    }

    const specs=$('#nzSpecs');
    const simplifySpecs=()=>{
      if(!specs||specs.dataset.simplifying==='1')return;specs.dataset.simplifying='1';
      specs.querySelectorAll('.nzux-optional-specs').forEach(d=>{const body=$('.nzux-optional-body',d);if(body)[...body.children].forEach(c=>specs.insertBefore(c,d));d.remove()});
      const fields=$$('.field',specs);const optional=fields.filter(f=>!safeText($('label',f)?.textContent).includes('*'));
      if(optional.length){const d=document.createElement('details');d.className='nzux-optional nzux-optional-specs';d.innerHTML='<summary>ویژگی‌های بیشتر (اختیاری)</summary><div class="nzux-optional-body"></div>';specs.appendChild(d);const body=$('.nzux-optional-body',d);optional.forEach(f=>body.appendChild(f))}
      specs.dataset.simplifying='0';
    };
    if(specs){new MutationObserver(()=>setTimeout(simplifySpecs,0)).observe(specs,{childList:true});setTimeout(simplifySpecs,0)}

    enhanceLocationModal();

    if(priceW&&price){
      const choice=document.createElement('div');choice.className='nzux-price-choice';choice.innerHTML='<button type="button" data-mode="deal">توافقی</button><button type="button" data-mode="fixed">قیمت مشخص</button>';
      priceW.before(choice);const deal=$('[data-mode="deal"]',choice),fixed=$('[data-mode="fixed"]',choice);
      const setMode=mode=>{const fixedMode=mode==='fixed';deal.classList.toggle('active',!fixedMode);fixed.classList.toggle('active',fixedMode);priceW.classList.toggle('nzux-collapsed',!fixedMode);if(!fixedMode)price.value='0';else setTimeout(()=>price.focus(),80)};
      deal.onclick=()=>setMode('deal');fixed.onclick=()=>setMode('fixed');setMode(Number(price.value||0)>0?'fixed':'deal');
      const label=$('label',priceW);if(label)label.textContent='قیمت به تومان';price.placeholder='مثلاً 25000000';price.inputMode='numeric';
    }

    const checks=$('.checks',form);if(checks){$$('.check',checks).forEach((l,i)=>{if(i===0)l.lastChild.textContent=' چت داخل نیازستان';if(i===1)l.lastChild.textContent=' نمایش شماره تماس'})}
    const finalBtn=$('.nz-final-submit .btn',form)||$('button[type="submit"]',form);if(finalBtn)finalBtn.textContent='ثبت و ارسال برای تأیید';

    const label=$('.nz-step-label');
    const normalizeStepLabel=()=>{if(!label)return;const t=label.textContent;if(t.includes('۱ از ۳'))label.textContent='چند مورد ساده؛ کمتر از چند دقیقه';else if(t.includes('۲ از ۳'))label.textContent='فقط ضروری‌ها را کامل کن';else if(t.includes('۳ از ۳'))label.textContent='آماده ثبت است'};
    if(label){new MutationObserver(normalizeStepLabel).observe(label,{childList:true,characterData:true,subtree:true});normalizeStepLabel()}
    refreshSuggestion();
  }

  function enhanceLocationModal(){
    const btn=$('#nzChooseLocation');if(!btn)return;
    const modal=$$('.nz-modal').find(m=>$('#nzProvince',m));if(!modal||modal.dataset.nzux)return;modal.dataset.nzux='1';
    const card=$('.nz-modal-card',modal),grid=$('.nz-location-grid',modal),tools=$('.nz-map-tools',modal),note=$('.nz-map-note',modal),my=$('#nzMyLocation',modal),center=$('#nzCenterCity',modal),city=$('#nzCity',modal);
    if(card&&!$('.nzux-modal-intro',card)){
      const intro=document.createElement('div');intro.className='nzux-modal-intro';intro.textContent='ساده‌ترین راه: «موقعیت فعلی من» را بزن. اگر نخواستی GPS بدهی، استان و شهر را انتخاب کن؛ نقطه تقریبی کافی است.';
      const head=$('.nz-modal-head',card);head?.after(intro);
    }
    if(tools&&grid){grid.before(tools);if(my){my.textContent='📍 موقعیت فعلی من';my.classList.add('nzux-location-primary')}if(center){center.textContent='انتخاب مرکز شهر';center.classList.add('nzux-location-secondary')}}
    if(grid){
      const fields=$$('.field',grid);if(fields.length>=4&&!$('.nzux-location-grid-optional',modal)){
        const details=document.createElement('details');details.className='nzux-optional nzux-location-grid-optional';details.innerHTML='<summary>محله و خیابان (اختیاری)</summary><div class="nzux-optional-body"></div>';
        grid.after(details);const body=$('.nzux-optional-body',details);body.appendChild(fields[2]);body.appendChild(fields[3]);
      }
    }
    if(note)note.textContent='برای حفظ حریم خصوصی، موقعیت حدودی کافی است. می‌توانی نشانگر را هم جابه‌جا کنی.';
    btn.addEventListener('click',()=>setTimeout(()=>{const approx=$('input[name="nzLocMode"][value="approx"]',modal);if(approx)approx.checked=true},60));
    city?.addEventListener('change',()=>{if(city.value)setTimeout(()=>center?.click(),100)});
  }

  function enhanceDetail(){
    const detail=$('.detail');if(!detail)return;
    const info=$('.detail-info',detail);if(info&&!$('.nzux-detail-trust',info)){
      const title=$('h1',info);const trust=document.createElement('div');trust.className='nzux-detail-trust';trust.innerHTML='<span>گفت‌وگوی مستقیم</span><span>ذخیره آسان</span><span>گزارش آگهی مشکوک</span>';title?.after(trust);
    }
    if($('.nzux-detail-cta'))return;
    const actions=$('.detail-actions',detail);if(!actions)return;
    const chatForm=$('form input[name="action"][value="start_chat"]',actions)?.form;
    const favoriteForm=$('form input[name="action"][value="favorite"]',actions)?.form;
    const external=$('.nzext-source');const demo=$('.detail-demo');
    const bar=document.createElement('div');bar.className='nzux-detail-cta';
    if(chatForm){const clone=chatForm.cloneNode(true);const b=$('button',clone);if(b){b.textContent='پیام به آگهی‌دهنده';b.classList.add('primary')}bar.appendChild(clone)}
    else if(!favoriteForm&&!external&&!demo){const a=document.createElement('a');a.href='/nz?page=login';a.className='btn primary';a.textContent='ورود برای گفتگو';bar.appendChild(a)}
    if(bar.children.length)document.body.appendChild(bar);
  }

  function polishCopy(){
    const mobileSearch=$('.mobile-search input[name="q"]');if(mobileSearch)mobileSearch.placeholder='چی لازم داری؟';
    const install=$('#installMini span');if(install)install.textContent='نصب';
    const homeTitle=$('.main .section-head h1');if(homeTitle&&page==='home'&&!params.get('q')&&!params.get('category')&&!params.get('city'))homeTitle.textContent='تازه‌ترین‌ها';
  }

  function init(){
    document.body.classList.add('nzux-ready');
    enhanceNav();enhanceHome();enhanceLogin();enhanceWizard();enhanceDetail();polishCopy();
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(init,0),{once:true});else setTimeout(init,0);
})();
