(()=>{
  'use strict';

  const TAXONOMY={
    'املاک':['آپارتمان','خانه و ویلا','زمین و کلنگی','دفتر و تجاری','مغازه','اجاره کوتاه‌مدت'],
    'وسایل نقلیه':['خودرو','موتورسیکلت','خودرو سنگین','قطعات خودرو','دوچرخه'],
    'کالای دیجیتال':['موبایل','لپ‌تاپ','تبلت','کنسول بازی','لوازم جانبی','صوتی و تصویری'],
    'خانه و آشپزخانه':['لوازم خانگی','مبلمان','دکوراسیون','ظروف و آشپزخانه','فرش و گلیم','روشنایی'],
    'خدمات':['تعمیرات','آموزش','نظافت','خدمات خودرو','ساختمان و تأسیسات','طراحی و برنامه‌نویسی','حمل‌ونقل'],
    'استخدام و کاریابی':['فروش و بازاریابی','فنی و مهندسی','اداری','راننده','کارگر','مالی و حسابداری','آموزش'],
    'شخصی':['لباس','کیف و کفش','زیورآلات','لوازم کودک','آرایشی و بهداشتی'],
    'سرگرمی و فراغت':['ورزش','کتاب','موسیقی','بازی و اسباب‌بازی','سفر و کمپینگ'],
    'تجهیزات و صنعتی':['ماشین‌آلات','ابزارآلات','تجهیزات فروشگاهی','تجهیزات پزشکی','مصالح و تجهیزات ساختمان'],
    'کسب‌وکار و عمده‌فروشی':['فروش عمده','مواد اولیه','لوازم اداری','تجهیزات رستوران','واگذاری کسب‌وکار'],
    'کشاورزی، حیوانات و مواد غذایی':['مواد غذایی و خوراکی','گل و گیاه طبیعی','کشاورزی و باغبانی','دام و طیور','لوازم جانبی حیوانات'],
    'آموزشی و اجتماعی':['کلاس و دوره آموزشی','رویداد و همایش','خدمات اجتماعی','کتاب و جزوه آموزشی']
  };

  const FIELD_SCHEMAS={
    'املاک':[
      ['نوع معامله','select',['فروش','رهن و اجاره','اجاره','پیش‌فروش'],true],
      ['متراژ','number',null,true,'متر مربع'],['تعداد اتاق','select',['بدون اتاق','۱','۲','۳','۴','۵ و بیشتر'],true],
      ['سال ساخت','number',null,false],['طبقه','text',null,false],['آسانسور','select',['دارد','ندارد'],false],
      ['پارکینگ','select',['دارد','ندارد'],false],['انباری','select',['دارد','ندارد'],false],['نوع سند','select',['تک‌برگ','منگوله‌دار','قولنامه‌ای','سایر'],false]
    ],
    'وسایل نقلیه':[
      ['برند','text',null,true],['مدل','text',null,true],['سال ساخت','number',null,true],['کارکرد','number',null,true,'کیلومتر'],
      ['رنگ','text',null,false],['وضعیت بدنه','select',['بی‌رنگ','یک لکه رنگ','چند لکه رنگ','دور رنگ','تصادفی'],true],
      ['وضعیت موتور','select',['سالم','نیاز به تعمیر','تعویض شده'],false],['گیربکس','select',['دستی','اتوماتیک'],false],
      ['سوخت','select',['بنزین','دوگانه‌سوز','دیزل','برقی','هیبرید'],false]
    ],
    'کالای دیجیتال':[
      ['برند','text',null,true],['مدل','text',null,true],['وضعیت','select',['نو','در حد نو','کارکرده سالم','نیازمند تعمیر'],true],
      ['حافظه/ظرفیت','text',null,false],['رنگ','text',null,false],['مهلت تست','select',['دارد','ندارد'],false]
    ],
    'خانه و آشپزخانه':[
      ['وضعیت','select',['نو','در حد نو','کارکرده سالم','نیازمند تعمیر'],true],['برند','text',null,false],['جنس','text',null,false],['ابعاد','text',null,false]
    ],
    'خدمات':[
      ['نوع ارائه خدمت','select',['در محل مشتری','در محل ارائه‌دهنده','آنلاین','هر دو'],true],['سابقه کار','text',null,false],
      ['محدوده خدمت‌رسانی','text',null,true],['نوع قیمت‌گذاری','select',['قیمت ثابت','توافقی','ساعتی','براساس پروژه'],false]
    ],
    'استخدام و کاریابی':[
      ['عنوان شغلی','text',null,true],['نوع همکاری','select',['تمام‌وقت','پاره‌وقت','پروژه‌ای','دورکاری','کارآموزی'],true],
      ['حقوق پیشنهادی','text',null,false],['سابقه موردنیاز','text',null,false],['بیمه','select',['دارد','ندارد'],false],['ساعات کاری','text',null,false]
    ],
    'شخصی':[
      ['وضعیت','select',['نو','در حد نو','کارکرده سالم'],true],['برند','text',null,false],['سایز','text',null,false],['جنس','text',null,false]
    ],
    'سرگرمی و فراغت':[
      ['وضعیت','select',['نو','در حد نو','کارکرده سالم'],true],['برند/سازنده','text',null,false],['رده سنی','text',null,false]
    ],
    'تجهیزات و صنعتی':[
      ['نوع دستگاه/تجهیز','text',null,true],['برند','text',null,false],['مدل','text',null,false],['وضعیت','select',['نو','کارکرده سالم','نیازمند تعمیر'],true],['سال ساخت','number',null,false],['ظرفیت/توان','text',null,false]
    ],
    'کسب‌وکار و عمده‌فروشی':[
      ['نوع فروش','select',['عمده','خرده و عمده','واگذاری کامل'],true],['حداقل سفارش','text',null,false],['امکان ارسال','select',['دارد','ندارد'],false]
    ],
    'کشاورزی، حیوانات و مواد غذایی':[
      ['نوع محصول/خدمت','text',null,true],['مقدار/وزن','text',null,false],['نوع عرضه','select',['خرده','عمده','هر دو'],false],['امکان ارسال','select',['دارد','ندارد'],false]
    ],
    'آموزشی و اجتماعی':[
      ['نوع برگزاری','select',['حضوری','آنلاین','هر دو'],true],['مدت دوره/رویداد','text',null,false],['سطح','text',null,false]
    ]
  };

  const PROVINCES={
    'آذربایجان شرقی':['تبریز','مراغه','مرند','اهر','میانه','بناب','سراب'],
    'آذربایجان غربی':['ارومیه','خوی','مهاباد','میاندوآب','بوکان','سلماس'],
    'اردبیل':['اردبیل','پارس‌آباد','مشگین‌شهر','خلخال'],
    'اصفهان':['اصفهان','کاشان','خمینی‌شهر','نجف‌آباد','شاهین‌شهر','فولادشهر','نائین'],
    'البرز':['کرج','فردیس','هشتگرد','نظرآباد','محمدشهر'],
    'ایلام':['ایلام','دهلران','مهران','ایوان'],
    'بوشهر':['بوشهر','برازجان','گناوه','کنگان','عسلویه'],
    'تهران':['تهران','ری','اسلامشهر','شهریار','قدس','ملارد','پردیس','دماوند','ورامین','رباط‌کریم'],
    'چهارمحال و بختیاری':['شهرکرد','بروجن','فارسان','لردگان'],
    'خراسان جنوبی':['بیرجند','قائن','طبس','فردوس'],
    'خراسان رضوی':['مشهد','نیشابور','سبزوار','تربت‌حیدریه','قوچان','کاشمر'],
    'خراسان شمالی':['بجنورد','شیروان','اسفراین','جاجرم'],
    'خوزستان':['اهواز','آبادان','خرمشهر','دزفول','ماهشهر','بهبهان','شوشتر'],
    'زنجان':['زنجان','ابهر','خرمدره','قیدار'],
    'سمنان':['سمنان','شاهرود','دامغان','گرمسار'],
    'سیستان و بلوچستان':['زاهدان','چابهار','ایرانشهر','زابل','خاش'],
    'فارس':['شیراز','مرودشت','جهرم','فسا','کازرون','لار','صدرا'],
    'قزوین':['قزوین','تاکستان','الوند','آبیک'],
    'قم':['قم'],
    'کردستان':['سنندج','سقز','مریوان','بانه','قروه'],
    'کرمان':['کرمان','رفسنجان','سیرجان','بم','جیرفت'],
    'کرمانشاه':['کرمانشاه','اسلام‌آباد غرب','کنگاور','پاوه'],
    'کهگیلویه و بویراحمد':['یاسوج','گچساران','دهدشت'],
    'گلستان':['گرگان','گنبدکاووس','علی‌آباد کتول','بندر ترکمن'],
    'گیلان':['رشت','انزلی','لاهیجان','لنگرود','رودسر','فومن'],
    'لرستان':['خرم‌آباد','بروجرد','دورود','الیگودرز'],
    'مازندران':['ساری','بابل','آمل','قائم‌شهر','بابلسر','چالوس','نوشهر','تنکابن'],
    'مرکزی':['اراک','ساوه','خمین','محلات'],
    'هرمزگان':['بندرعباس','قشم','کیش','میناب','بندر لنگه'],
    'همدان':['همدان','ملایر','نهاوند','تویسرکان'],
    'یزد':['یزد','میبد','اردکان','بافق']
  };

  const NEIGHBORHOODS={
    'تهران':['سعادت‌آباد','شهرک غرب','پونک','جنت‌آباد','صادقیه','ستارخان','مرزداران','ونک','یوسف‌آباد','پاسداران','نیاوران','فرمانیه','تهرانپارس','نارمک','پیروزی','افسریه','شوش','نازی‌آباد','منیریه','ولیعصر'],
    'شیراز':['معالی‌آباد','قصرالدشت','فرهنگ‌شهر','صدرا','ستارخان','عفیف‌آباد','قدوسی','بلوار چمران','ارم','زرگری','گلدشت معالی‌آباد','رحمت','پودنک'],
    'اصفهان':['مرداویج','خانه اصفهان','ملک‌شهر','سپاهان‌شهر','بهارستان','شیخ صدوق','جلفا','چهارباغ','خمینی‌شهر','شاهین‌شهر'],
    'مشهد':['سجاد','احمدآباد','هاشمیه','وکیل‌آباد','قاسم‌آباد','الهیه','طرقبه','بلوار فردوسی','طبرسی','رضاشهر'],
    'تبریز':['ولیعصر','ائل‌گلی','رشدیه','آبرسان','زعفرانیه','باغمیشه','منظریه','نصف‌راه'],
    'کرج':['جهانشهر','گوهردشت','مهرشهر','عظیمیه','فردیس','حصارک','شاهین‌ویلا','گلشهر'],
    'اهواز':['کیانپارس','زیتون کارمندی','گلستان','پادادشهر','کوی ملت','کوی مهدیس','امانیه'],
    'رشت':['گلسار','منظریه','معلم','لاکانی','سبزه میدان','بلوار دیلمان'],
    'قم':['سالاریه','زنبیل‌آباد','صفائیه','پردیسان','نیروگاه','بنیاد'],
    'یزد':['صفائیه','بلوار جمهوری','امامشهر','آزادشهر','اکرم‌آباد','شاهدیه'],
    'کرمان':['هزار و یک شب','شهرک باهنر','بلوار جمهوری','شهرک مطهری','خواجو'],
    'بندرعباس':['گلشهر','سورو','نایبند','شهرک پیامبر اعظم','بلوار امام حسین']
  };

  const CENTERS={
    'تهران':[35.6892,51.3890],'شیراز':[29.5918,52.5837],'اصفهان':[32.6546,51.6680],'مشهد':[36.2605,59.6168],
    'تبریز':[38.0800,46.2919],'کرج':[35.8400,50.9391],'اهواز':[31.3183,48.6706],'قم':[34.6416,50.8746],
    'رشت':[37.2808,49.5832],'کرمان':[30.2839,57.0834],'یزد':[31.8974,54.3569],'بندرعباس':[27.1832,56.2666],
    'ارومیه':[37.5527,45.0761],'بوشهر':[28.9234,50.8203]
  };

  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const allCities=()=>Object.values(PROVINCES).flat();
  const provinceOf=city=>Object.keys(PROVINCES).find(p=>PROVINCES[p].includes(city))||'';
  const normFa=s=>String(s??'').replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/‌/g,' ').replace(/^استان\s+/,'').replace(/\s+/g,' ').trim();

  function enhanceGlobalSelectors(){
    const cities=allCities();
    document.querySelectorAll('select.mobile-location, .filter-card select[name="city"]').forEach(sel=>{
      const current=sel.value;
      const first=sel.querySelector('option[value=""]')?.textContent||'همه شهرها';
      sel.innerHTML=`<option value="">${esc(first)}</option>`+cities.map(c=>`<option value="${esc(c)}">${esc(c)}</option>`).join('');
      if(cities.includes(current))sel.value=current;
    });
    const filter=document.querySelector('.filter-card select[name="category"]');
    if(filter){
      const current=filter.value;
      filter.innerHTML='<option value="">همه دسته‌ها</option>'+Object.keys(TAXONOMY).map(c=>`<option value="${esc(c)}">${esc(c)}</option>`).join('');
      filter.value=current;
    }
    const grid=document.getElementById('nzCategoryGrid');
    if(grid){
      const existing=new Set([...grid.querySelectorAll('a')].map(a=>a.textContent.trim()));
      Object.keys(TAXONOMY).forEach(cat=>{if(!existing.has(cat)){const a=document.createElement('a');a.href='/nz?category='+encodeURIComponent(cat);a.textContent=cat;grid.appendChild(a)}});
    }
  }

  function setSelectValue(sel,value){
    if(!sel)return;
    if(![...sel.options].some(o=>o.value===value)){const o=document.createElement('option');o.value=o.textContent=value;sel.appendChild(o)}
    sel.value=value;
    sel.dispatchEvent(new Event('change',{bubbles:true}));
  }

  function loadLeaflet(){
    if(window.L)return Promise.resolve(window.L);
    return new Promise((resolve,reject)=>{
      if(!document.querySelector('link[data-nz-leaflet]')){
        const l=document.createElement('link');l.rel='stylesheet';l.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';l.dataset.nzLeaflet='1';document.head.appendChild(l);
      }
      const existing=document.querySelector('script[data-nz-leaflet]');
      if(existing){existing.addEventListener('load',()=>resolve(window.L),{once:true});existing.addEventListener('error',reject,{once:true});return}
      const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';s.dataset.nzLeaflet='1';s.onload=()=>resolve(window.L);s.onerror=reject;document.head.appendChild(s);
    });
  }

  function initWizard(){
    const form=[...document.querySelectorAll('form')].find(f=>f.querySelector('input[name="action"][value="create_post"]'));
    if(!form)return;
    const surface=form.closest('.surface');
    const title=form.querySelector('[name="title"]'),desc=form.querySelector('[name="description"]'),cat=form.querySelector('[name="category"]'),sub=form.querySelector('[name="subcategory"]'),city=form.querySelector('[name="city"]'),district=form.querySelector('[name="district"]'),price=form.querySelector('[name="price"]'),images=form.querySelector('[name="images[]"]'),video=form.querySelector('[name="video"]');
    if(!title||!desc||!cat||!sub||!city||!district||!price)return;

    const style=document.createElement('style');
    style.textContent=`
      .nz-wizard-form{display:block!important}.nz-wizard-head{margin-bottom:18px}.nz-wizard-title{font-size:21px;font-weight:950;margin:0 0 5px}.nz-step-bars{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin:14px 0 7px}.nz-step-bars span{height:5px;border-radius:99px;background:#e7e9ed}.nz-step-bars span.on{background:#b4232c}.nz-step-label{font-size:11px;color:#777}.nz-step{display:none}.nz-step.active{display:block}.nz-section-title{font-size:16px;font-weight:950;margin:14px 0 8px}.nz-action-field{width:100%;min-height:58px;border:1px solid #dfe2e6;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 14px;font:800 13px Tahoma,Arial,sans-serif;cursor:pointer;text-align:right}.nz-action-field small{display:block;color:#858b93;font-weight:400;margin-top:4px}.nz-action-value{min-width:0}.nz-chevron{font-size:25px;color:#9aa0a7}.nz-wizard-nav{display:flex;gap:8px;margin-top:20px}.nz-wizard-nav button{flex:1;min-height:48px;border:0;border-radius:13px;font:900 13px Tahoma,Arial,sans-serif;cursor:pointer}.nz-next{background:#b4232c;color:#fff}.nz-back{background:#f1f3f5;color:#343a40}.nz-hidden-fields{display:none!important}.nz-modal{position:fixed;inset:0;z-index:10050;background:rgba(10,14,20,.55);display:none;align-items:flex-end;justify-content:center}.nz-modal.show{display:flex}.nz-modal-card{width:min(100%,560px);max-height:92dvh;overflow:auto;background:#fff;border-radius:24px 24px 0 0;padding:18px 16px calc(18px + env(safe-area-inset-bottom));box-shadow:0 -18px 70px rgba(0,0,0,.22)}.nz-modal-head{display:flex;align-items:center;justify-content:space-between;position:sticky;top:-18px;background:#fff;padding:8px 0 12px;z-index:4}.nz-modal-head h3{margin:0;font-size:17px}.nz-modal-close{border:0;background:#f1f2f4;border-radius:50%;width:36px;height:36px;font-size:22px}.nz-search{width:100%;height:44px;border:1px solid #dfe2e6;border-radius:12px;padding:0 12px;font:inherit;margin-bottom:10px}.nz-list{display:grid;gap:6px}.nz-list button{min-height:50px;border:0;border-bottom:1px solid #eee;background:#fff;text-align:right;font:800 13px Tahoma,Arial,sans-serif;padding:0 5px;cursor:pointer}.nz-list button small{display:block;color:#92979e;font-weight:400;margin-top:3px}.nz-specs{display:grid;grid-template-columns:1fr 1fr;gap:12px}.nz-specs .field{margin:0}.nz-location-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.nz-location-grid .field{margin:0}.nz-map{height:310px;border-radius:16px;overflow:hidden;background:#eef1f3;margin-top:12px;border:1px solid #e2e5e9}.nz-map-tools{display:flex;gap:8px;margin-top:10px}.nz-map-tools button{flex:1;min-height:42px;border:1px solid #dedfe3;background:#fff;border-radius:11px;font:800 11px Tahoma,Arial,sans-serif}.nz-location-mode{display:flex;gap:8px;margin-top:10px}.nz-location-mode label{flex:1;border:1px solid #e2e4e8;border-radius:12px;padding:10px;text-align:center;font-size:11px;cursor:pointer}.nz-location-mode input{margin-left:5px}.nz-modal-save{width:100%;height:48px;margin-top:14px;border:0;border-radius:13px;background:#b4232c;color:#fff;font:900 13px Tahoma,Arial,sans-serif}.nz-review{background:#f8f9fa;border-radius:16px;padding:13px;margin:12px 0}.nz-review-row{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #e8eaed;font-size:12px}.nz-review-row:last-child{border:0}.nz-review-row span{color:#7b8188}.nz-review-row b{text-align:left}.nz-wizard-error{display:none;margin:10px 0;padding:10px 12px;border-radius:11px;background:#fff0f1;color:#a21d27;font-size:12px;border:1px solid #f0c9cd}.nz-wizard-error.show{display:block}.nz-required:after{content:' *';color:#b4232c}.nz-map-note{font-size:10.5px;color:#7d838a;line-height:1.8;margin-top:7px}.nz-geo-status{font-size:11px;margin-top:8px;padding:8px 10px;border-radius:10px;background:#f5f6f7;color:#697078;display:none}.nz-geo-status.show{display:block}.nz-geo-status.ok{background:#eef8f0;color:#397548}.nz-geo-status.error{background:#fff5f5;color:#9f3038}.nz-clear{border:1px solid #e4e6e9;background:#fff;border-radius:10px;padding:7px 10px;font:800 11px Tahoma,Arial,sans-serif}.nz-final-submit button{height:50px!important;font-size:14px!important}
      @media(max-width:760px){.page:has(.nz-wizard-form){padding-top:10px}.page:has(.nz-wizard-form) .surface{padding:3px 0}.nz-specs,.nz-location-grid{grid-template-columns:1fr}.nz-modal-card{max-height:94dvh}.nz-map{height:280px}}
    `;
    document.head.appendChild(style);

    form.classList.remove('form-grid');form.classList.add('nz-wizard-form');form.noValidate=true;
    if(surface){const h=surface.querySelector(':scope>h1');const lead=surface.querySelector(':scope>.lead');if(h)h.style.display='none';if(lead)lead.style.display='none'}

    const wrap=el=>el?.closest('.field')||el?.parentElement;
    const titleW=wrap(title),descW=wrap(desc),catW=wrap(cat),subW=wrap(sub),cityW=wrap(city),districtW=wrap(district),priceW=wrap(price),imagesW=wrap(images),videoW=wrap(video);
    const checks=form.querySelector('.checks');
    const submitBtn=form.querySelector('button[type="submit"]')||form.querySelector('button.btn.primary');
    const submitW=submitBtn?.closest('.full')||submitBtn?.parentElement;

    const head=document.createElement('div');head.className='nz-wizard-head';head.innerHTML=`<div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><div><h1 class="nz-wizard-title">ثبت آگهی</h1><div class="subtle">اطلاعات را مرحله‌به‌مرحله کامل کنید.</div></div><button type="button" class="nz-clear">پاک کردن</button></div><div class="nz-step-bars"><span></span><span></span><span></span></div><div class="nz-step-label"></div><div class="nz-wizard-error"></div>`;
    form.before(head);
    const errorBox=head.querySelector('.nz-wizard-error');

    const hidden=document.createElement('div');hidden.className='nz-hidden-fields';form.appendChild(hidden);
    [catW,subW,cityW,districtW].forEach(x=>x&&hidden.appendChild(x));

    const hiddenInput=(name,value='')=>{let i=form.querySelector(`input[name="${name}"]`);if(!i){i=document.createElement('input');i.type='hidden';i.name=name;form.appendChild(i)}i.value=value;return i};
    const provinceI=hiddenInput('province'),latI=hiddenInput('latitude'),lngI=hiddenInput('longitude'),modeI=hiddenInput('location_mode','exact'),streetI=hiddenInput('street');

    const categoryBlock=document.createElement('div');categoryBlock.innerHTML=`<div class="nz-section-title nz-required">دسته‌بندی آگهی</div><button type="button" class="nz-action-field" id="nzChooseCategory"><span class="nz-action-value"><b>انتخاب دسته و زیر‌دسته</b><small>دسته مناسب را مشخص کنید؛ پس از ثبت قابل تغییر نیست.</small></span><span class="nz-chevron">‹</span></button>`;
    const locationBlock=document.createElement('div');locationBlock.innerHTML=`<div class="nz-section-title nz-required">محل آگهی</div><button type="button" class="nz-action-field" id="nzChooseLocation"><span class="nz-action-value"><b>انتخاب شهر، محله و موقعیت روی نقشه</b><small>برای نمایش بهتر آگهی محل را دقیق مشخص کنید.</small></span><span class="nz-chevron">‹</span></button>`;
    const specsBlock=document.createElement('div');specsBlock.innerHTML='<div class="nz-section-title">ویژگی‌های آگهی</div><div class="nz-specs" id="nzSpecs"><div class="subtle">بعد از انتخاب دسته، ویژگی‌های مرتبط اینجا نمایش داده می‌شود.</div></div>';

    const steps=[0,1,2].map(()=>{const d=document.createElement('section');d.className='nz-step';form.appendChild(d);return d});
    const move=(step,node)=>node&&steps[step].appendChild(node);
    move(0,imagesW);move(0,titleW);move(0,descW);move(0,videoW);
    steps[1].append(categoryBlock,locationBlock,specsBlock);
    move(2,priceW);if(checks)steps[2].appendChild(checks);
    const review=document.createElement('div');review.className='nz-review';review.innerHTML='<b>مرور آگهی</b><div id="nzReviewRows"></div><div class="nz-map-note">آگهی ابتدا برای بررسی مدیر ارسال می‌شود و فقط پس از تأیید منتشر خواهد شد.</div>';steps[2].appendChild(review);
    if(submitW){submitW.classList.add('nz-final-submit');steps[2].appendChild(submitW)}
    if(submitBtn)submitBtn.textContent='ارسال آگهی برای بررسی';

    const nav0=document.createElement('div');nav0.className='nz-wizard-nav';nav0.innerHTML='<button type="button" class="nz-next">بعدی: دسته و محل</button>';steps[0].appendChild(nav0);
    const nav1=document.createElement('div');nav1.className='nz-wizard-nav';nav1.innerHTML='<button type="button" class="nz-back">بازگشت</button><button type="button" class="nz-next">بعدی: مرور و ارسال</button>';steps[1].appendChild(nav1);
    const nav2=document.createElement('div');nav2.className='nz-wizard-nav';nav2.innerHTML='<button type="button" class="nz-back">بازگشت</button>';steps[2].insertBefore(nav2,submitW||null);

    let currentStep=0;
    const labels=['صفحه ۱ از ۳: تصاویر و توضیحات','صفحه ۲ از ۳: دسته، محل و ویژگی‌ها','صفحه ۳ از ۳: مرور و ارسال'];
    function showStep(n){currentStep=Math.max(0,Math.min(2,n));steps.forEach((s,i)=>s.classList.toggle('active',i===currentStep));head.querySelectorAll('.nz-step-bars span').forEach((x,i)=>x.classList.toggle('on',i<=currentStep));head.querySelector('.nz-step-label').textContent=labels[currentStep];errorBox.classList.remove('show');window.scrollTo({top:0,behavior:'smooth'});if(currentStep===2)updateReview()}
    function error(msg){errorBox.textContent=msg;errorBox.classList.add('show');errorBox.scrollIntoView({behavior:'smooth',block:'center'});return false}

    function validateStep(n){
      if(n===0){if(title.value.trim().length<5)return error('عنوان آگهی را کامل‌تر بنویسید.');if(desc.value.trim().length<15)return error('توضیحات آگهی را کامل‌تر بنویسید.');if(images&&images.files&&images.files.length===0)return error('حداقل یک عکس برای آگهی انتخاب کنید.');if(images?.files?.length>5)return error('حداکثر ۵ عکس می‌توانید انتخاب کنید.');}
      if(n===1){if(!cat.value||!sub.value)return error('دسته و زیر‌دسته آگهی را انتخاب کنید.');if(!city.value)return error('شهر آگهی را انتخاب کنید.');if(!latI.value||!lngI.value)return error('موقعیت آگهی را روی نقشه مشخص کنید.');const bad=[...document.querySelectorAll('#nzSpecs [required]')].find(i=>!String(i.value||'').trim());if(bad){bad.focus();return error('ویژگی‌های اجباری دسته آگهی را کامل کنید.')}}
      errorBox.classList.remove('show');return true;
    }
    nav0.querySelector('.nz-next').onclick=()=>{if(validateStep(0))showStep(1)};
    nav1.querySelector('.nz-back').onclick=()=>showStep(0);nav1.querySelector('.nz-next').onclick=()=>{if(validateStep(1))showStep(2)};nav2.querySelector('.nz-back').onclick=()=>showStep(1);
    head.querySelector('.nz-clear').onclick=()=>{if(confirm('همه اطلاعات فرم پاک شود؟')){form.reset();cat.value='';sub.value='';city.value='';district.value='';provinceI.value=latI.value=lngI.value=streetI.value='';modeI.value='exact';renderSpecs('');refreshCategorySummary();refreshLocationSummary();showStep(0)}};

    const catModal=document.createElement('div');catModal.className='nz-modal';catModal.innerHTML='<div class="nz-modal-card"><div class="nz-modal-head"><h3>انتخاب دسته آگهی</h3><button class="nz-modal-close" type="button">×</button></div><input class="nz-search" placeholder="جستجو در دسته‌ها"><div class="nz-list"></div></div>';document.body.appendChild(catModal);
    const catList=catModal.querySelector('.nz-list'),catSearch=catModal.querySelector('.nz-search');let catLevel='root',chosenCat='';
    function renderCategoryList(query=''){
      const q=query.trim();catList.innerHTML='';
      if(catLevel==='root')Object.keys(TAXONOMY).filter(x=>!q||x.includes(q)).forEach(name=>{const b=document.createElement('button');b.type='button';b.innerHTML=`${esc(name)}<small>${TAXONOMY[name].slice(0,3).map(esc).join('، ')}</small>`;b.onclick=()=>{chosenCat=name;catLevel='sub';catSearch.value='';renderCategoryList()};catList.appendChild(b)});
      else {const back=document.createElement('button');back.type='button';back.textContent='‹ بازگشت به دسته‌ها';back.onclick=()=>{catLevel='root';chosenCat='';renderCategoryList()};catList.appendChild(back);(TAXONOMY[chosenCat]||[]).filter(x=>!q||x.includes(q)).forEach(name=>{const b=document.createElement('button');b.type='button';b.textContent=name;b.onclick=()=>{setSelectValue(cat,chosenCat);setSelectValue(sub,name);renderSpecs(chosenCat);refreshCategorySummary();catModal.classList.remove('show')};catList.appendChild(b)})}
    }
    document.getElementById('nzChooseCategory').onclick=()=>{catLevel='root';chosenCat='';catSearch.value='';renderCategoryList();catModal.classList.add('show')};catModal.querySelector('.nz-modal-close').onclick=()=>catModal.classList.remove('show');catModal.onclick=e=>{if(e.target===catModal)catModal.classList.remove('show')};catSearch.oninput=()=>renderCategoryList(catSearch.value);

    function refreshCategorySummary(){const b=document.querySelector('#nzChooseCategory .nz-action-value');if(cat.value&&sub.value)b.innerHTML=`<b>${esc(cat.value)} · ${esc(sub.value)}</b><small>برای تغییر دوباره انتخاب کنید.</small>`;else b.innerHTML='<b>انتخاب دسته و زیر‌دسته</b><small>دسته مناسب را مشخص کنید؛ پس از ثبت قابل تغییر نیست.</small>'}
    function renderSpecs(category){const box=document.getElementById('nzSpecs');box.innerHTML='';const schema=FIELD_SCHEMAS[category]||[];if(!schema.length){box.innerHTML='<div class="subtle">برای این دسته ویژگی اضافه‌ای لازم نیست.</div>';return}schema.forEach(([label,type,options,required,suffix])=>{const w=document.createElement('div');w.className='field';const lab=document.createElement('label');lab.textContent=label+(required?' *':'');w.appendChild(lab);let input;if(type==='select'){input=document.createElement('select');input.innerHTML='<option value="">انتخاب</option>'+options.map(o=>`<option value="${esc(o)}">${esc(o)}</option>`).join('')}else{input=document.createElement('input');input.type=type;input.placeholder=suffix?`${label} (${suffix})`:label;if(type==='number'){input.min='0';input.inputMode='numeric'}}input.name=`attributes[${label}]`;if(required)input.required=true;w.appendChild(input);box.appendChild(w)})}

    const locModal=document.createElement('div');locModal.className='nz-modal';locModal.innerHTML=`<div class="nz-modal-card"><div class="nz-modal-head"><h3>مکان آگهی</h3><button class="nz-modal-close" type="button">×</button></div><div class="nz-location-grid"><div class="field"><label>استان *</label><select id="nzProvince"><option value="">انتخاب استان</option></select></div><div class="field"><label>شهر *</label><select id="nzCity"><option value="">ابتدا استان</option></select></div><div class="field"><label>محله / منطقه</label><input id="nzDistrict" list="nzDistrictList" placeholder="با انتخاب روی نقشه خودکار پر می‌شود"><datalist id="nzDistrictList"></datalist></div><div class="field"><label>خیابان / نشانی کوتاه</label><input id="nzStreet" placeholder="با انتخاب روی نقشه خودکار پر می‌شود"></div></div><div class="nz-map-tools"><button type="button" id="nzMyLocation">موقعیت فعلی من</button><button type="button" id="nzCenterCity">مرکز شهر انتخابی</button></div><div id="nzMap" class="nz-map"></div><div id="nzGeoStatus" class="nz-geo-status"></div><div class="nz-map-note">هرجا روی نقشه بزنید یا نشانگر را جابه‌جا کنید، استان، شهر، محله و خیابان به‌صورت خودکار از روی موقعیت تشخیص داده می‌شود. در صورت نیاز می‌توانید متن تشخیص‌داده‌شده را دستی اصلاح کنید.</div><div class="nz-location-mode"><label><input type="radio" name="nzLocMode" value="exact" checked>نمایش موقعیت دقیق</label><label><input type="radio" name="nzLocMode" value="approx">نمایش موقعیت حدودی</label></div><button type="button" class="nz-modal-save">تأیید مکان</button></div>`;document.body.appendChild(locModal);
    const pSel=locModal.querySelector('#nzProvince'),cSel=locModal.querySelector('#nzCity'),dInput=locModal.querySelector('#nzDistrict'),dList=locModal.querySelector('#nzDistrictList'),sInput=locModal.querySelector('#nzStreet'),geoStatus=locModal.querySelector('#nzGeoStatus');
    pSel.innerHTML='<option value="">انتخاب استان</option>'+Object.keys(PROVINCES).map(p=>`<option value="${esc(p)}">${esc(p)}</option>`).join('');
    let map=null,marker=null,tempLat=null,tempLng=null,reverseTimer=null,reverseController=null,reverseSeq=0;
    function setCities(province,selected=''){
      cSel.innerHTML='<option value="">انتخاب شهر</option>'+((PROVINCES[province]||[]).map(c=>`<option value="${esc(c)}">${esc(c)}</option>`).join(''));
      if(selected){if(![...cSel.options].some(o=>normFa(o.value)===normFa(selected))){const o=document.createElement('option');o.value=o.textContent=selected;cSel.appendChild(o)}const exact=[...cSel.options].find(o=>normFa(o.value)===normFa(selected));if(exact)cSel.value=exact.value}
      updateDistricts();
    }
    function updateDistricts(){dList.innerHTML=(NEIGHBORHOODS[cSel.value]||[]).map(x=>`<option value="${esc(x)}"></option>`).join('')}
    function setGeoStatus(text,type=''){
      geoStatus.textContent=text||'';
      geoStatus.className='nz-geo-status'+(text?' show':'')+(type?' '+type:'');
    }
    function matchProvince(raw){
      const n=normFa(raw);
      if(!n)return '';
      return Object.keys(PROVINCES).find(p=>{const pn=normFa(p);return pn===n||n.includes(pn)||pn.includes(n)})||'';
    }
    function matchCity(raw,province=''){
      const n=normFa(raw);
      if(!n)return '';
      const pool=province&&PROVINCES[province]?PROVINCES[province]:allCities();
      return pool.find(c=>{const cn=normFa(c);return cn===n||n.includes(cn)||cn.includes(n)})||String(raw).trim();
    }
    function applyReverseAddress(data){
      const a=data?.address||{};
      const provinceRaw=a.state||a.province||a.region||a.state_district||'';
      const cityRaw=a.city||a.town||a.village||a.municipality||a.city_district||a.county||'';
      const districtRaw=a.neighbourhood||a.suburb||a.quarter||a.city_district||a.borough||a.residential||a.hamlet||'';
      const streetRaw=a.road||a.pedestrian||a.path||a.footway||a.cycleway||'';
      let province=matchProvince(provinceRaw);
      let cityName=matchCity(cityRaw,province);
      if(!province&&cityName)province=provinceOf(cityName);
      if(province){pSel.value=province;setCities(province,cityName)}
      else if(cityName){const guessed=provinceOf(cityName);if(guessed){pSel.value=guessed;province=guessed;setCities(guessed,cityName)}}
      if(cityName&&!cSel.value){setCities(pSel.value,cityName)}
      if(districtRaw)dInput.value=String(districtRaw).trim();
      if(streetRaw)sInput.value=String(streetRaw).trim();
      updateDistricts();
      const pieces=[pSel.value,cSel.value,dInput.value,sInput.value].filter(Boolean);
      if(pieces.length)setGeoStatus('آدرس تشخیص داده شد: '+pieces.join('، '),'ok');
      else setGeoStatus('نقطه انتخاب شد؛ آدرس دقیق از سرویس نقشه دریافت نشد. می‌توانید فیلدها را دستی تکمیل کنید.','error');
    }
    async function reverseGeocode(lat,lng){
      const seq=++reverseSeq;
      if(reverseController)reverseController.abort();
      reverseController=new AbortController();
      setGeoStatus('در حال تشخیص استان، شهر و محله از روی نقشه…');
      try{
        const url=`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&addressdetails=1&accept-language=fa`;
        const r=await fetch(url,{signal:reverseController.signal,headers:{Accept:'application/json'}});
        if(!r.ok)throw new Error('reverse');
        const data=await r.json();
        if(seq!==reverseSeq)return;
        applyReverseAddress(data);
      }catch(err){
        if(err?.name==='AbortError'||seq!==reverseSeq)return;
        setGeoStatus('موقعیت انتخاب شد، اما تشخیص خودکار آدرس در این لحظه در دسترس نبود. فیلدها را دستی اصلاح کنید.','error');
      }
    }
    function scheduleReverse(lat,lng){
      clearTimeout(reverseTimer);
      reverseTimer=setTimeout(()=>reverseGeocode(Number(lat),Number(lng)),850);
    }
    function setPoint(lat,lng,zoom=15,lookup=true){
      tempLat=Number(lat);tempLng=Number(lng);
      if(map){
        if(!marker){
          marker=L.marker([tempLat,tempLng],{draggable:true}).addTo(map);
          marker.on('dragend',()=>{const p=marker.getLatLng();tempLat=p.lat;tempLng=p.lng;scheduleReverse(tempLat,tempLng)});
        }else marker.setLatLng([tempLat,tempLng]);
        map.setView([tempLat,tempLng],zoom);
      }
      if(lookup)scheduleReverse(tempLat,tempLng);
    }
    async function ensureMap(){
      try{
        await loadLeaflet();
        if(!map){
          map=L.map('nzMap',{zoomControl:true}).setView([32.4279,53.6880],5);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
          map.on('click',e=>setPoint(e.latlng.lat,e.latlng.lng,map.getZoom(),true));
        }
        setTimeout(()=>map.invalidateSize(),80);
        if(tempLat&&tempLng)setPoint(tempLat,tempLng,15,false);else if(CENTERS[cSel.value])map.setView(CENTERS[cSel.value],12);
      }catch(_){document.getElementById('nzMap').innerHTML='<div style="padding:35px 18px;text-align:center;color:#777">نقشه آنلاین در دسترس نیست. اتصال اینترنت را بررسی کنید.</div>'}
    }
    pSel.onchange=()=>setCities(pSel.value);
    cSel.onchange=()=>{updateDistricts();const center=CENTERS[cSel.value];if(center&&map)map.setView(center,12)};
    locModal.querySelector('#nzCenterCity').onclick=()=>{const center=CENTERS[cSel.value];if(center)setPoint(center[0],center[1],13,true);else alert('برای این شهر مرکز پیش‌فرض ثبت نشده؛ روی نقشه نقطه را انتخاب کنید.')};
    locModal.querySelector('#nzMyLocation').onclick=()=>{
      if(!navigator.geolocation){alert('موقعیت‌یابی در این مرورگر پشتیبانی نمی‌شود.');return}
      setGeoStatus('در حال دریافت موقعیت فعلی…');
      navigator.geolocation.getCurrentPosition(p=>setPoint(p.coords.latitude,p.coords.longitude,16,true),()=>{setGeoStatus('دسترسی به موقعیت داده نشد یا موقعیت قابل دریافت نیست.','error');alert('اجازه دسترسی به موقعیت داده نشد یا موقعیت قابل دریافت نیست.')},{enableHighAccuracy:true,timeout:12000});
    };
    function openLocation(){
      const currentProvince=provinceI.value||provinceOf(city.value);
      pSel.value=currentProvince;setCities(currentProvince,city.value);dInput.value=district.value||'';sInput.value=streetI.value||'';
      tempLat=latI.value?Number(latI.value):null;tempLng=lngI.value?Number(lngI.value):null;
      locModal.querySelector(`input[name="nzLocMode"][value="${modeI.value||'exact'}"]`).checked=true;
      setGeoStatus('');locModal.classList.add('show');ensureMap();
    }
    function refreshLocationSummary(){const b=document.querySelector('#nzChooseLocation .nz-action-value');if(city.value)b.innerHTML=`<b>${esc(city.value)}${district.value?'، '+esc(district.value):''}</b><small>${modeI.value==='approx'?'موقعیت حدودی':'موقعیت دقیق'}${streetI.value?' · '+esc(streetI.value):''}</small>`;else b.innerHTML='<b>انتخاب شهر، محله و موقعیت روی نقشه</b><small>برای نمایش بهتر آگهی محل را دقیق مشخص کنید.</small>'}
    document.getElementById('nzChooseLocation').onclick=openLocation;locModal.querySelector('.nz-modal-close').onclick=()=>locModal.classList.remove('show');locModal.onclick=e=>{if(e.target===locModal)locModal.classList.remove('show')};
    locModal.querySelector('.nz-modal-save').onclick=()=>{
      if(tempLat==null||tempLng==null){alert('یک نقطه روی نقشه انتخاب کنید.');return}
      if(!pSel.value||!cSel.value){alert('آدرس این نقطه خودکار تشخیص داده نشد؛ استان و شهر را دستی انتخاب کنید.');return}
      const mode=locModal.querySelector('input[name="nzLocMode"]:checked').value;let la=tempLat,ln=tempLng;
      if(mode==='approx'){la=Math.round(la*1000)/1000;ln=Math.round(ln*1000)/1000}
      provinceI.value=pSel.value;setSelectValue(city,cSel.value);district.value=dInput.value.trim();streetI.value=sInput.value.trim();latI.value=String(la);lngI.value=String(ln);modeI.value=mode;refreshLocationSummary();locModal.classList.remove('show');
    };

    function updateReview(){const rows=document.getElementById('nzReviewRows');rows.innerHTML=`<div class="nz-review-row"><span>عنوان</span><b>${esc(title.value||'—')}</b></div><div class="nz-review-row"><span>دسته</span><b>${esc(cat.value||'—')} · ${esc(sub.value||'—')}</b></div><div class="nz-review-row"><span>محل</span><b>${esc(city.value||'—')}${district.value?'، '+esc(district.value):''}</b></div><div class="nz-review-row"><span>قیمت</span><b>${Number(price.value||0)>0?Number(price.value).toLocaleString('fa-IR')+' تومان':'توافقی'}</b></div><div class="nz-review-row"><span>رسانه</span><b>${images?.files?.length||0} عکس${video?.files?.length?' + ویدیو':''}</b></div>`}

    form.addEventListener('submit',e=>{if(!validateStep(0)){e.preventDefault();showStep(0);return}if(!validateStep(1)){e.preventDefault();showStep(1);return}if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='در حال ارسال برای بررسی…';setTimeout(()=>{submitBtn.disabled=false;submitBtn.textContent='ارسال آگهی برای بررسی'},20000)}});

    if(images)images.addEventListener('change',()=>{if(images.files.length>5){alert('حداکثر ۵ عکس مجاز است.');images.value=''}});
    // Import an explicitly accepted AI draft through the same manual controls.
    form.addEventListener('nz:ai-draft',event=>{
      const p=event.detail;if(!p||!TAXONOMY[p.category]?.includes(p.subcategory))return;
      event.preventDefault();
      title.value=p.title||'';desc.value=p.description||'';
      setSelectValue(cat,p.category);setSelectValue(sub,p.subcategory);renderSpecs(p.category);
      if(p.city&&PROVINCES[p.province]?.includes(p.city)){provinceI.value=p.province;setSelectValue(city,p.city);}
      district.value=p.district||'';streetI.value=p.street||'';
      // A language model must never fabricate a map point or grant contact permission.
      latI.value='';lngI.value='';modeI.value='approx';
      if(p.price!==null)price.value=String(p.price);
      form.querySelectorAll('[name^="attributes["]').forEach(field=>{const label=field.name.slice(11,-1);if(Object.hasOwn(p.attributes||{},label))field.value=p.attributes[label];});
      refreshCategorySummary();refreshLocationSummary();showStep(0);
    });
    refreshCategorySummary();refreshLocationSummary();showStep(0);
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{enhanceGlobalSelectors();initWizard()},{once:true});else{enhanceGlobalSelectors();initWizard()}
})();
