(()=>{
'use strict';

const YEARS=Array.from({length:106},(_,i)=>String(1405-i));
const COLORS=['سفید','مشکی','نقره‌ای','خاکستری','نوک‌مدادی','ذغالی','آبی','آبی تیره','آبی روشن','سرمه‌ای','قرمز','زرشکی','سبز','سبز تیره','یشمی','زرد','نارنجی','قهوه‌ای','کرم','بژ','طلایی','مسی','بنفش','صورتی','فیروزه‌ای','طوسی','دودی','سفید صدفی','مشکی متالیک','نقره‌ای متالیک','خاکستری متالیک','چند رنگ','سایر'];
const BODY=['بی‌رنگ','یک لکه رنگ','دو لکه رنگ','چند لکه رنگ','یک در رنگ','دو در رنگ','کاپوت رنگ','گلگیر رنگ','دور رنگ','تمام رنگ','تعویض قطعه بدنه','تصادفی','اوراقی'];
const CONDITION=['نو','آکبند','در حد نو','کارکرده سالم','نیازمند تعمیر','معیوب'];
const STORAGE=['16 گیگابایت','32 گیگابایت','64 گیگابایت','128 گیگابایت','256 گیگابایت','512 گیگابایت','1 ترابایت','2 ترابایت','4 ترابایت','8 ترابایت','سایر'];
const RAM=['2 گیگابایت','3 گیگابایت','4 گیگابایت','6 گیگابایت','8 گیگابایت','12 گیگابایت','16 گیگابایت','18 گیگابایت','24 گیگابایت','32 گیگابایت','48 گیگابایت','64 گیگابایت','96 گیگابایت','128 گیگابایت','سایر'];

const CARS={
'ایران خودرو':['پژو 206','پژو 207','پژو 405','پژو پارس','سمند','سمند سورن','سورن پلاس','دنا','دنا پلاس','دنا پلاس توربو','رانا','رانا پلاس','تارا','ری‌را','آریسان','وانت آریسان','هایما S5','هایما S7','هایما S8','هایما 7X'],
'سایپا':['پراید 111','پراید 131','پراید 132','پراید 141','پراید 151','تیبا','تیبا 2','ساینا','ساینا S','کوییک','کوییک R','کوییک S','شاهین','شاهین G','شاهین پلاس','اطلس','سهند','چانگان CS35','چانگان CS55 پلاس'],
'پارس خودرو':['رنو ساندرو','رنو ساندرو استپ‌وی','تندر 90','پارس تندر','برلیانس H220','برلیانس H230','برلیانس H320','برلیانس H330','برلیانس کراس'],
'مدیران خودرو':['ام‌وی‌ام 110','ام‌وی‌ام 315','ام‌وی‌ام X22','ام‌وی‌ام X22 Pro','ام‌وی‌ام X33','ام‌وی‌ام X55 Pro','آریزو 5','آریزو 6','آریزو 6 Pro','تیگو 5','تیگو 7','تیگو 7 Pro','تیگو 8 Pro','فونیکس FX','فونیکس تیگو 7 پرو مکس','فونیکس تیگو 8 پرو مکس'],
'کرمان موتور':['جک J3','جک J4','جک J5','جک S3','جک S5','کی‌ام‌سی J7','کی‌ام‌سی K7','کی‌ام‌سی X5','کی‌ام‌سی T8','کی‌ام‌سی T9','هیوندای اکسنت','هیوندای النترا'],
'بهمن موتور':['مزدا 3','مزدا 3 نیو','بسترن B30','فیدلیتی پرایم','فیدلیتی پرستیژ','دیگنیتی پرایم','دیگنیتی پرستیژ','ریسپکت','وانت کاپرا','اینرودز'],
'پژو':['206','207','208','301','308','405','406','407','508','2008','3008','5008','پارس'],
'رنو':['تندر 90','لوگان','ساندرو','استپ‌وی','مگان','سیمبل','داستر','کپچر','کولیوس','تلیسمان','فلوئنس','کلیو'],
'هیوندای':['اکسنت','النترا','سوناتا','آزرا','جنسیس','سانتافه','توسان','کرتا','ولستر','i20','i30','کوپه','ورنا'],
'کیا':['پراید','ریو','سراتو','اپتیما','کادنزا','اسپورتیج','سورنتو','پیکانتو','سول','کارنز','موهاوی','استینگر'],
'تویوتا':['کرولا','کمری','یاریس','پریوس','راوفور','لندکروزر','پرادو','هایلوکس','اف‌جی کروزر','CHR','آریون','ونزا'],
'نیسان':['ماکسیما','تیانا','مورانو','جوک','ایکس‌تریل','پاترول','وانت نیسان','قشقایی','سنترا'],
'مزدا':['2','3','3 نیو','6','323','626','CX-3','CX-5','CX-9','وانت B2000'],
'فولکس واگن':['گل','پولو','گلف','جتا','پاسات','تیگوان','توارگ','آرتئون','T-Roc'],
'بی‌ام‌و':['سری 1','سری 2','سری 3','سری 4','سری 5','سری 6','سری 7','X1','X2','X3','X4','X5','X6','X7','i8'],
'مرسدس بنز':['کلاس A','کلاس B','کلاس C','کلاس E','کلاس S','CLA','CLS','GLA','GLC','GLE','GLS','G-Class'],
'لکسوس':['CT','IS','ES','GS','LS','UX','NX','RX','GX','LX'],
'میتسوبیشی':['لنسر','میراژ','ASX','اوتلندر','پاجرو','اکلیپس کراس'],
'سوزوکی':['ویتارا','گرند ویتارا','سوئیفت','کیزاشی','جیمنی','بالنو'],
'هوندا':['سیویک','آکورد','CR-V','HR-V','سیتی','فیت'],
'شورولت':['کروز','مالیبو','کاپریس','کامارو','تاهو','سابربن'],
'فورد':['فوکوس','فیستا','فیوژن','موستانگ','اسکیپ','اکسپلورر','F-150'],
'جیلی':['امگرند 7','امگرند X7','GC6','آزکارا','کولری'],
'چری':['A5','ویانا','آریزو 5','آریزو 6','تیگو 5','تیگو 7','تیگو 8'],
'چانگان':['CS35','CS55 Plus','CS75','Eado','Alsvin'],
'لاماری':['ایما'],
'فردا موتور':['SX5','T5','Suba M4'],
'دایون':['Y5','Y7'],
'مکث موتور':['کلوت','تیارا'],
'فونیکس':['FX','آریزو 6 GT','تیگو 7 پرو','تیگو 7 پرو مکس','تیگو 8 پرو','تیگو 8 پرو مکس','تیگو 8 پرو e+'],
'لوکانو':['L7','L8'],
'اکستریم':['LX','TXL','VX'],
'سایر':['سایر مدل']
};

const MOTORCYCLES={
'هوندا':['CG125','CB125','CB150','CB190','CBR250','CBR500','PCX','ADV','Click','Forza'],
'یاماها':['YBR125','YZF-R15','YZF-R25','MT-15','MT-25','NMAX','XMAX','Aerox'],
'سوزوکی':['GN125','GSX-R150','GSX-R250','V-Strom','Burgman'],
'کاوازاکی':['Ninja 250','Ninja 400','Z250','Z400','Versys'],
'باجاج':['Pulsar 150','Pulsar 180','Pulsar 200NS','Dominar 250','Dominar 400','Boxer'],
'TVS':['Apache 160','Apache 180','Apache 200','NTorq','Jupiter'],
'بنلی':['TNT15','TNT25','TNT249S','302S','TRK251','TRK502'],
'کی‌تی‌ام':['Duke 200','Duke 250','Duke 390','RC200','RC390'],
'کویر موتور':['CDI125','KPS200','T2','T4','مگلی 200','اس‌تی‌ار 200'],
'نیرو موتور':['پرواز 125','پرواز 200','طرح کلیک','طرح ویو'],
'احسان':['125','150','200'],
'تکتاز':['125','150','200'],
'دینو':['125','150','200'],
'سایر':['سایر مدل']
};

const PHONES={
'Apple':['iPhone 6','iPhone 6s','iPhone 7','iPhone 8','iPhone X','iPhone XR','iPhone XS','iPhone 11','iPhone 11 Pro','iPhone 12','iPhone 12 mini','iPhone 12 Pro','iPhone 13','iPhone 13 mini','iPhone 13 Pro','iPhone 14','iPhone 14 Plus','iPhone 14 Pro','iPhone 15','iPhone 15 Plus','iPhone 15 Pro','iPhone 16','iPhone 16 Plus','iPhone 16 Pro','iPhone 16e','iPhone 17','iPhone 17 Air','iPhone 17 Pro'],
'Samsung':['Galaxy A05','Galaxy A06','Galaxy A14','Galaxy A15','Galaxy A16','Galaxy A24','Galaxy A25','Galaxy A34','Galaxy A35','Galaxy A54','Galaxy A55','Galaxy A56','Galaxy S20','Galaxy S21','Galaxy S22','Galaxy S23','Galaxy S24','Galaxy S25','Galaxy S26','Galaxy Note 20','Galaxy Z Flip','Galaxy Z Fold','Galaxy M14','Galaxy M34','Galaxy M54'],
'Xiaomi':['Redmi 9','Redmi 10','Redmi 12','Redmi 13','Redmi 14','Redmi Note 10','Redmi Note 11','Redmi Note 12','Redmi Note 13','Redmi Note 14','Poco X3','Poco X4','Poco X5','Poco X6','Poco X7','Poco F3','Poco F4','Poco F5','Poco F6','Xiaomi 12','Xiaomi 13','Xiaomi 14','Xiaomi 15','Mi 11'],
'Huawei':['P30','P40','P50','P60','Mate 20','Mate 30','Mate 40','Nova 7','Nova 8','Nova 9','Nova 10','Y7','Y9'],
'Honor':['X5','X6','X7','X8','X9','Honor 70','Honor 90','Honor 100','Magic 5','Magic 6','Magic 7'],
'Nokia':['C10','C20','C30','G10','G20','G21','G42','X10','X20','3310'],
'OnePlus':['8','9','10','11','12','13','Nord','Nord 2','Nord 3'],
'Google':['Pixel 6','Pixel 7','Pixel 8','Pixel 9','Pixel Fold'],
'Motorola':['Moto G54','Moto G84','Edge 30','Edge 40','Edge 50','Razr'],
'Realme':['C35','C53','C55','C67','11 Pro','12 Pro','GT Neo'],
'Nothing':['Phone 1','Phone 2','Phone 2a','Phone 3'],
'Sony':['Xperia 1','Xperia 5','Xperia 10','Xperia XZ'],
'LG':['G6','G7','G8','V30','V40','V50','Velvet'],
'سایر':['سایر مدل']
};

const LAPTOPS={
'Apple':['MacBook Air M1','MacBook Air M2','MacBook Air M3','MacBook Air M4','MacBook Pro 13','MacBook Pro 14','MacBook Pro 16'],
'ASUS':['VivoBook','ZenBook','ExpertBook','TUF Gaming','ROG Strix','ROG Zephyrus','ProArt'],
'Lenovo':['IdeaPad 1','IdeaPad 3','IdeaPad 5','ThinkPad E','ThinkPad T','ThinkPad X','Yoga','Legion 5','Legion 7','LOQ'],
'HP':['15','250','Pavilion','Envy','Spectre','Victus','Omen','ProBook','EliteBook','ZBook'],
'Dell':['Inspiron','Vostro','Latitude','XPS','Precision','G15','Alienware'],
'Acer':['Aspire 3','Aspire 5','Swift','Nitro 5','Predator Helios','TravelMate'],
'MSI':['Modern','Prestige','Katana','Cyborg','Stealth','Raider','Titan'],
'Microsoft':['Surface Laptop','Surface Pro','Surface Book'],
'Razer':['Blade 14','Blade 15','Blade 16','Blade 18'],
'Huawei':['MateBook D14','MateBook D15','MateBook 14','MateBook X Pro'],
'Samsung':['Galaxy Book','Galaxy Book Pro','Galaxy Book Ultra'],
'سایر':['سایر مدل']
};

const TABLETS={
'Apple':['iPad','iPad mini','iPad Air','iPad Pro 11','iPad Pro 12.9','iPad Pro 13'],
'Samsung':['Galaxy Tab A7','Galaxy Tab A8','Galaxy Tab A9','Galaxy Tab S6 Lite','Galaxy Tab S7','Galaxy Tab S8','Galaxy Tab S9','Galaxy Tab S10'],
'Xiaomi':['Pad 5','Pad 6','Pad 7','Redmi Pad','Redmi Pad SE'],
'Lenovo':['Tab M8','Tab M10','Tab P11','Tab P12','Yoga Tab'],
'Huawei':['MatePad 10','MatePad 11','MatePad Pro'],
'Microsoft':['Surface Go','Surface Pro'],
'سایر':['سایر مدل']
};

const CONSOLES={
'Sony':['PlayStation 2','PlayStation 3','PlayStation 4','PlayStation 4 Slim','PlayStation 4 Pro','PlayStation 5','PlayStation 5 Slim','PlayStation 5 Pro','PSP','PS Vita'],
'Microsoft':['Xbox 360','Xbox One','Xbox One S','Xbox One X','Xbox Series S','Xbox Series X'],
'Nintendo':['Wii','Wii U','Switch','Switch Lite','Switch OLED','Switch 2'],
'Valve':['Steam Deck','Steam Deck OLED'],
'سایر':['سایر مدل']
};

const TVS={
'Samsung':['LED','QLED','Neo QLED','Crystal UHD','OLED'],
'LG':['LED','NanoCell','QNED','OLED'],
'Sony':['Bravia LED','Bravia OLED','Bravia Mini LED'],
'Xiaomi':['Mi TV','TV A','TV P','TV S'],
'TCL':['P Series','C Series','QLED','Mini LED'],
'Hisense':['A Series','U Series','QLED','Mini LED'],
'GPlus':['GTV Series'],
'Snowa':['SSD Series'],
'XVision':['XKU Series','XYU Series'],
'Daewoo':['DSL Series'],
'سایر':['سایر مدل']
};

const APPLIANCES={
'Samsung':['یخچال','فریزر','لباسشویی','ظرفشویی','مایکروویو','جاروبرقی','کولر گازی'],
'LG':['یخچال','فریزر','لباسشویی','ظرفشویی','مایکروویو','جاروبرقی','کولر گازی'],
'Bosch':['یخچال','لباسشویی','ظرفشویی','جاروبرقی','فر','مایکروویو'],
'Snowa':['یخچال','فریزر','لباسشویی','ظرفشویی','اجاق گاز','تلویزیون'],
'Daewoo':['یخچال','فریزر','لباسشویی','ظرفشویی','تلویزیون'],
'پاکشوما':['لباسشویی','ظرفشویی','یخچال','فریزر','جاروبرقی'],
'اسنوا':['یخچال','فریزر','لباسشویی','ظرفشویی','اجاق گاز'],
'امرسان':['یخچال','فریزر','لباسشویی'],
'هیمالیا':['یخچال','فریزر'],
'الکترواستیل':['یخچال','فریزر'],
'جی‌پلاس':['یخچال','لباسشویی','ظرفشویی','تلویزیون','کولر گازی'],
'ایکس‌ویژن':['تلویزیون','لباسشویی','یخچال'],
'مادیران':['تلویزیون','لوازم خانگی'],
'دوو':['یخچال','فریزر','لباسشویی','ظرفشویی'],
'کنوود':['غذاساز','مخلوط‌کن','چرخ‌گوشت','همزن'],
'فیلیپس':['جاروبرقی','سرخ‌کن','غذاساز','اتو','قهوه‌ساز'],
'تفال':['سرخ‌کن','اتو','جاروبرقی','غذاساز'],
'دلونگی':['قهوه‌ساز','اسپرسوساز','سرخ‌کن'],
'سایر':['سایر مدل']
};

const GENERIC_BRANDS=['Acer','Adidas','Apple','ASUS','Bosch','Canon','Casio','Daewoo','Dell','DJI','Epson','Garmin','GPlus','HP','Huawei','JBL','Kenwood','Lenovo','LG','Logitech','Microsoft','MSI','Nike','Nikon','Panasonic','Philips','Pioneer','Samsung','Sharp','Sony','TCL','Tefal','Xiaomi','XVision','اسنوا','امرسان','ایران خودرو','پاکشوما','پارس خزر','دوو','سایپا','هیمالیا','الکترواستیل','سایر'];

function schemaFor(category,sub){
  if(category==='وسایل نقلیه'&&sub==='خودرو')return CARS;
  if(category==='وسایل نقلیه'&&sub==='موتورسیکلت')return MOTORCYCLES;
  if(category==='کالای دیجیتال'&&sub==='موبایل')return PHONES;
  if(category==='کالای دیجیتال'&&sub==='لپ‌تاپ')return LAPTOPS;
  if(category==='کالای دیجیتال'&&sub==='تبلت')return TABLETS;
  if(category==='کالای دیجیتال'&&sub==='کنسول بازی')return CONSOLES;
  if(category==='کالای دیجیتال'&&sub==='صوتی و تصویری')return TVS;
  if(category==='خانه و آشپزخانه'&&sub==='لوازم خانگی')return APPLIANCES;
  return null;
}
function optionsHtml(items,current=''){return '<option value="">انتخاب</option>'+items.map(v=>`<option value="${String(v).replace(/"/g,'&quot;')}"${v===current?' selected':''}>${v}</option>`).join('')}
function replaceWithSelect(old,items){if(!old||old.tagName==='SELECT'&&old.dataset.nzControlled==='1')return old;const s=document.createElement('select');[...old.attributes].forEach(a=>{if(!['type','list','placeholder','min','max','inputmode'].includes(a.name))s.setAttribute(a.name,a.value)});s.dataset.nzControlled='1';s.innerHTML=optionsHtml(items,old.value||'');old.replaceWith(s);return s}
function fieldLabel(field){return (field.querySelector('label')?.textContent||'').replace(/\s*\*\s*$/,'').trim()}
function enforce(){
 const box=document.getElementById('nzSpecs');if(!box)return;
 const category=document.querySelector('[name="category"]')?.value||'';
 const sub=document.querySelector('[name="subcategory"]')?.value||'';
 const catalog=schemaFor(category,sub);
 let brandSel=null,modelSel=null;
 [...box.querySelectorAll('.field')].forEach(field=>{
   const label=fieldLabel(field);let input=field.querySelector('input,select');if(!input)return;
   if(['سال ساخت','سال تولید'].includes(label)){input=replaceWithSelect(input,YEARS)}
   else if(label==='رنگ'){input=replaceWithSelect(input,COLORS)}
   else if(label==='وضعیت بدنه'){input=replaceWithSelect(input,BODY)}
   else if(label==='وضعیت'){input=replaceWithSelect(input,CONDITION)}
   else if(label==='حافظه/ظرفیت'){input=replaceWithSelect(input,STORAGE)}
   else if(label==='رم'){input=replaceWithSelect(input,RAM)}
   else if(label==='برند'||label==='برند/سازنده'){
      const brands=catalog?Object.keys(catalog):GENERIC_BRANDS;input=replaceWithSelect(input,brands);brandSel=input;
   } else if(label==='مدل'){modelSel=input}
 });
 if(modelSel){
   const fill=()=>{const models=catalog&&brandSel?catalog[brandSel.value]:null;modelSel=replaceWithSelect(modelSel,models&&models.length?models:['سایر مدل']);};
   if(brandSel){brandSel.addEventListener('change',fill);fill()}else modelSel=replaceWithSelect(modelSel,['سایر مدل']);
 }
}
function init(){const root=document.body;const mo=new MutationObserver(()=>enforce());mo.observe(root,{childList:true,subtree:true});document.addEventListener('change',e=>{if(e.target?.name==='category'||e.target?.name==='subcategory')setTimeout(enforce,0)});enforce()}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();