<?php
declare(strict_types=1);

function nzcv_colors(): array {
    return ['سفید','مشکی','نقره‌ای','خاکستری','نوک‌مدادی','ذغالی','آبی','آبی تیره','آبی روشن','سرمه‌ای','قرمز','زرشکی','سبز','سبز تیره','یشمی','زرد','نارنجی','قهوه‌ای','کرم','بژ','طلایی','مسی','بنفش','صورتی','فیروزه‌ای','طوسی','دودی','سفید صدفی','مشکی متالیک','نقره‌ای متالیک','خاکستری متالیک','چند رنگ','سایر'];
}
function nzcv_catalog(string $subcategory): array {
    $catalog = match ($subcategory) {
        'خودرو' => [
            'ایران خودرو'=>['پژو 206','پژو 207','پژو 405','پژو پارس','سمند','سمند سورن','سورن پلاس','دنا','دنا پلاس','دنا پلاس توربو','رانا','رانا پلاس','تارا','ری‌را','آریسان','وانت آریسان','هایما S5','هایما S7','هایما S8','هایما 7X'],
            'سایپا'=>['پراید 111','پراید 131','پراید 132','پراید 141','پراید 151','تیبا','تیبا 2','ساینا','ساینا S','کوییک','کوییک R','کوییک S','شاهین','شاهین G','شاهین پلاس','اطلس','سهند','چانگان CS35','چانگان CS55 پلاس'],
            'پارس خودرو'=>['رنو ساندرو','رنو ساندرو استپ‌وی','تندر 90','پارس تندر','برلیانس H220','برلیانس H230','برلیانس H320','برلیانس H330','برلیانس کراس'],
            'مدیران خودرو'=>['ام‌وی‌ام 110','ام‌وی‌ام 315','ام‌وی‌ام X22','ام‌وی‌ام X22 Pro','ام‌وی‌ام X33','ام‌وی‌ام X55 Pro','آریزو 5','آریزو 6','آریزو 6 Pro','تیگو 5','تیگو 7','تیگو 7 Pro','تیگو 8 Pro','فونیکس FX','فونیکس تیگو 7 پرو مکس','فونیکس تیگو 8 پرو مکس'],
            'کرمان موتور'=>['جک J3','جک J4','جک J5','جک S3','جک S5','کی‌ام‌سی J7','کی‌ام‌سی K7','کی‌ام‌سی X5','کی‌ام‌سی T8','کی‌ام‌سی T9','هیوندای اکسنت','هیوندای النترا'],
            'بهمن موتور'=>['مزدا 3','مزدا 3 نیو','بسترن B30','فیدلیتی پرایم','فیدلیتی پرستیژ','دیگنیتی پرایم','دیگنیتی پرستیژ','ریسپکت','وانت کاپرا','اینرودز'],
            'پژو'=>['206','207','208','301','308','405','406','407','508','2008','3008','5008','پارس'],
            'رنو'=>['تندر 90','لوگان','ساندرو','استپ‌وی','مگان','سیمبل','داستر','کپچر','کولیوس','تلیسمان','فلوئنس','کلیو'],
            'هیوندای'=>['اکسنت','النترا','سوناتا','آزرا','جنسیس','سانتافه','توسان','کرتا','ولستر','i20','i30','کوپه','ورنا'],
            'کیا'=>['پراید','ریو','سراتو','اپتیما','کادنزا','اسپورتیج','سورنتو','پیکانتو','سول','کارنز','موهاوی','استینگر'],
            'تویوتا'=>['کرولا','کمری','یاریس','پریوس','راوفور','لندکروزر','پرادو','هایلوکس','اف‌جی کروزر','CHR','آریون','ونزا'],
            'نیسان'=>['ماکسیما','تیانا','مورانو','جوک','ایکس‌تریل','پاترول','وانت نیسان','قشقایی','سنترا'],
            'مزدا'=>['2','3','3 نیو','6','323','626','CX-3','CX-5','CX-9','وانت B2000'],
            'فولکس واگن'=>['گل','پولو','گلف','جتا','پاسات','تیگوان','توارگ','آرتئون','T-Roc'],
            'بی‌ام‌و'=>['سری 1','سری 2','سری 3','سری 4','سری 5','سری 6','سری 7','X1','X2','X3','X4','X5','X6','X7','i8'],
            'مرسدس بنز'=>['کلاس A','کلاس B','کلاس C','کلاس E','کلاس S','CLA','CLS','GLA','GLC','GLE','GLS','G-Class'],
            'لکسوس'=>['CT','IS','ES','GS','LS','UX','NX','RX','GX','LX'],
            'میتسوبیشی'=>['لنسر','میراژ','ASX','اوتلندر','پاجرو','اکلیپس کراس'],
            'سوزوکی'=>['ویتارا','گرند ویتارا','سوئیفت','کیزاشی','جیمنی','بالنو'],
            'هوندا'=>['سیویک','آکورد','CR-V','HR-V','سیتی','فیت'],
            'شورولت'=>['کروز','مالیبو','کاپریس','کامارو','تاهو','سابربن'],
            'فورد'=>['فوکوس','فیستا','فیوژن','موستانگ','اسکیپ','اکسپلورر','F-150'],
            'جیلی'=>['امگرند 7','امگرند X7','GC6','آزکارا','کولری'],
            'چری'=>['A5','ویانا','آریزو 5','آریزو 6','تیگو 5','تیگو 7','تیگو 8'],
            'چانگان'=>['CS35','CS55 Plus','CS75','Eado','Alsvin'],
            'لاماری'=>['ایما'],'فردا موتور'=>['SX5','T5','Suba M4'],'دایون'=>['Y5','Y7'],'مکث موتور'=>['کلوت','تیارا'],
            'فونیکس'=>['FX','آریزو 6 GT','تیگو 7 پرو','تیگو 7 پرو مکس','تیگو 8 پرو','تیگو 8 پرو مکس','تیگو 8 پرو e+'],
            'لوکانو'=>['L7','L8'],'اکستریم'=>['LX','TXL','VX'],'سایر'=>['سایر مدل']
        ],
        'موتورسیکلت' => [
            'هوندا'=>['CG125','CB125','CB150','CB190','CBR250','CBR500','PCX','ADV','Click','Forza'],
            'یاماها'=>['YBR125','YZF-R15','YZF-R25','MT-15','MT-25','NMAX','XMAX','Aerox'],
            'سوزوکی'=>['GN125','GSX-R150','GSX-R250','V-Strom','Burgman'],'کاوازاکی'=>['Ninja 250','Ninja 400','Z250','Z400','Versys'],
            'باجاج'=>['Pulsar 150','Pulsar 180','Pulsar 200NS','Dominar 250','Dominar 400','Boxer'],'TVS'=>['Apache 160','Apache 180','Apache 200','NTorq','Jupiter'],
            'بنلی'=>['TNT15','TNT25','TNT249S','302S','TRK251','TRK502'],'کی‌تی‌ام'=>['Duke 200','Duke 250','Duke 390','RC200','RC390'],
            'کویر موتور'=>['CDI125','KPS200','T2','T4','مگلی 200','اس‌تی‌ار 200'],'نیرو موتور'=>['پرواز 125','پرواز 200','طرح کلیک','طرح ویو'],
            'احسان'=>['125','150','200'],'تکتاز'=>['125','150','200'],'دینو'=>['125','150','200'],'سایر'=>['سایر مدل']
        ],
        'موبایل' => [
            'Apple'=>['iPhone 6','iPhone 6s','iPhone 7','iPhone 8','iPhone X','iPhone XR','iPhone XS','iPhone 11','iPhone 11 Pro','iPhone 12','iPhone 12 mini','iPhone 12 Pro','iPhone 13','iPhone 13 mini','iPhone 13 Pro','iPhone 14','iPhone 14 Plus','iPhone 14 Pro','iPhone 15','iPhone 15 Plus','iPhone 15 Pro','iPhone 16','iPhone 16 Plus','iPhone 16 Pro','iPhone 16e','iPhone 17','iPhone 17 Air','iPhone 17 Pro'],
            'Samsung'=>['Galaxy A05','Galaxy A06','Galaxy A14','Galaxy A15','Galaxy A16','Galaxy A24','Galaxy A25','Galaxy A34','Galaxy A35','Galaxy A54','Galaxy A55','Galaxy A56','Galaxy S20','Galaxy S21','Galaxy S22','Galaxy S23','Galaxy S24','Galaxy S25','Galaxy S26','Galaxy Note 20','Galaxy Z Flip','Galaxy Z Fold','Galaxy M14','Galaxy M34','Galaxy M54'],
            'Xiaomi'=>['Redmi 9','Redmi 10','Redmi 12','Redmi 13','Redmi 14','Redmi Note 10','Redmi Note 11','Redmi Note 12','Redmi Note 13','Redmi Note 14','Poco X3','Poco X4','Poco X5','Poco X6','Poco X7','Poco F3','Poco F4','Poco F5','Poco F6','Xiaomi 12','Xiaomi 13','Xiaomi 14','Xiaomi 15','Mi 11'],
            'Huawei'=>['P30','P40','P50','P60','Mate 20','Mate 30','Mate 40','Nova 7','Nova 8','Nova 9','Nova 10','Y7','Y9'],
            'Honor'=>['X5','X6','X7','X8','X9','Honor 70','Honor 90','Honor 100','Magic 5','Magic 6','Magic 7'],
            'Nokia'=>['C10','C20','C30','G10','G20','G21','G42','X10','X20','3310'],'OnePlus'=>['8','9','10','11','12','13','Nord','Nord 2','Nord 3'],
            'Google'=>['Pixel 6','Pixel 7','Pixel 8','Pixel 9','Pixel Fold'],'Motorola'=>['Moto G54','Moto G84','Edge 30','Edge 40','Edge 50','Razr'],
            'Realme'=>['C35','C53','C55','C67','11 Pro','12 Pro','GT Neo'],'Nothing'=>['Phone 1','Phone 2','Phone 2a','Phone 3'],
            'Sony'=>['Xperia 1','Xperia 5','Xperia 10','Xperia XZ'],'LG'=>['G6','G7','G8','V30','V40','V50','Velvet'],'سایر'=>['سایر مدل']
        ],
        'لپ‌تاپ' => [
            'Apple'=>['MacBook Air M1','MacBook Air M2','MacBook Air M3','MacBook Air M4','MacBook Pro 13','MacBook Pro 14','MacBook Pro 16'],
            'ASUS'=>['VivoBook','ZenBook','ExpertBook','TUF Gaming','ROG Strix','ROG Zephyrus','ProArt'],'Lenovo'=>['IdeaPad 1','IdeaPad 3','IdeaPad 5','ThinkPad E','ThinkPad T','ThinkPad X','Yoga','Legion 5','Legion 7','LOQ'],
            'HP'=>['15','250','Pavilion','Envy','Spectre','Victus','Omen','ProBook','EliteBook','ZBook'],'Dell'=>['Inspiron','Vostro','Latitude','XPS','Precision','G15','Alienware'],
            'Acer'=>['Aspire 3','Aspire 5','Swift','Nitro 5','Predator Helios','TravelMate'],'MSI'=>['Modern','Prestige','Katana','Cyborg','Stealth','Raider','Titan'],
            'Microsoft'=>['Surface Laptop','Surface Pro','Surface Book'],'Razer'=>['Blade 14','Blade 15','Blade 16','Blade 18'],'Huawei'=>['MateBook D14','MateBook D15','MateBook 14','MateBook X Pro'],'Samsung'=>['Galaxy Book','Galaxy Book Pro','Galaxy Book Ultra'],'سایر'=>['سایر مدل']
        ],
        'تبلت' => [
            'Apple'=>['iPad','iPad mini','iPad Air','iPad Pro 11','iPad Pro 12.9','iPad Pro 13'],'Samsung'=>['Galaxy Tab A7','Galaxy Tab A8','Galaxy Tab A9','Galaxy Tab S6 Lite','Galaxy Tab S7','Galaxy Tab S8','Galaxy Tab S9','Galaxy Tab S10'],
            'Xiaomi'=>['Pad 5','Pad 6','Pad 7','Redmi Pad','Redmi Pad SE'],'Lenovo'=>['Tab M8','Tab M10','Tab P11','Tab P12','Yoga Tab'],'Huawei'=>['MatePad 10','MatePad 11','MatePad Pro'],'Microsoft'=>['Surface Go','Surface Pro'],'سایر'=>['سایر مدل']
        ],
        'کنسول بازی' => [
            'Sony'=>['PlayStation 2','PlayStation 3','PlayStation 4','PlayStation 4 Slim','PlayStation 4 Pro','PlayStation 5','PlayStation 5 Slim','PlayStation 5 Pro','PSP','PS Vita'],
            'Microsoft'=>['Xbox 360','Xbox One','Xbox One S','Xbox One X','Xbox Series S','Xbox Series X'],'Nintendo'=>['Wii','Wii U','Switch','Switch Lite','Switch OLED','Switch 2'],'Valve'=>['Steam Deck','Steam Deck OLED'],'سایر'=>['سایر مدل']
        ],
        default => []
    };
    // Keep a valid manual and assistant path for models outside this finite catalogue.
    foreach ($catalog as &$models) if (!in_array('سایر مدل', $models, true)) $models[] = 'سایر مدل';
    unset($models);
    return $catalog;
}
function nzcv_validate(string $category, string $subcategory, array $attrs): void {
    foreach ($attrs as $label => $value) {
        if (in_array($label, ['سال ساخت','سال تولید'], true)) {
            $year = filter_var($value, FILTER_VALIDATE_INT);
            if ($year === false || $year < 1300 || $year > 1405) throw new RuntimeException('سال ساخت/تولید باید بین ۱۳۰۰ تا ۱۴۰۵ انتخاب شود.');
        }
        if ($label === 'رنگ' && !in_array($value, nzcv_colors(), true)) throw new RuntimeException('رنگ انتخاب‌شده معتبر نیست.');
    }
    $enums = [
        'وضعیت بدنه'=>['بی‌رنگ','یک لکه رنگ','دو لکه رنگ','چند لکه رنگ','یک در رنگ','دو در رنگ','کاپوت رنگ','گلگیر رنگ','دور رنگ','تمام رنگ','تعویض قطعه بدنه','تصادفی','اوراقی'],
        'گیربکس'=>['دستی','اتوماتیک'],'سوخت'=>['بنزین','دوگانه‌سوز','دیزل','برقی','هیبرید'],
        'آسانسور'=>['دارد','ندارد'],'پارکینگ'=>['دارد','ندارد'],'انباری'=>['دارد','ندارد'],'مهلت تست'=>['دارد','ندارد'],
        'بیمه'=>['دارد','ندارد'],'امکان ارسال'=>['دارد','ندارد'],'نوع عرضه'=>['خرده','عمده','هر دو'],'نوع برگزاری'=>['حضوری','آنلاین','هر دو'],
        'نوع همکاری'=>['تمام‌وقت','پاره‌وقت','پروژه‌ای','دورکاری','کارآموزی'],'نوع معامله'=>['فروش','رهن و اجاره','اجاره','پیش‌فروش']
    ];
    foreach ($enums as $label=>$allowed) if (isset($attrs[$label]) && !in_array($attrs[$label], $allowed, true)) throw new RuntimeException('مقدار «'.$label.'» معتبر نیست.');
    $catalog = nzcv_catalog($subcategory);
    if ($catalog) {
        $brand = (string)($attrs['برند'] ?? $attrs['برند/سازنده'] ?? '');
        $model = (string)($attrs['مدل'] ?? '');
        if ($brand === '' || !array_key_exists($brand, $catalog)) throw new RuntimeException('برند را از فهرست معتبر انتخاب کنید.');
        if ($model === '' || !in_array($model, $catalog[$brand], true)) throw new RuntimeException('مدل را از فهرست مربوط به برند انتخاب کنید.');
    }
}
