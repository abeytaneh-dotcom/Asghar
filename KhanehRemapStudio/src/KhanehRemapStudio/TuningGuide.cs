namespace KhanehRemapStudio;

public sealed class TuningGuideRule
{
    public string Key { get; init; }="";
    public string Title { get; init; }="";
    public string Purpose { get; init; }="";
    public string Direction { get; init; }="";
    public string SuggestedRange { get; init; }="";
    public string EditMode { get; init; }="percent"; // percent | value | none
    public double DefaultValue { get; init; }
    public string Monitor { get; init; }="";
    public string StopCondition { get; init; }="";
    public bool AutoAllowed { get; init; }
    public bool RequiresExactDefinition { get; init; }=true;
    public string Warning { get; init; }="";
}

public static class TuningGuide
{
    public static TuningGuideRule For(MapDefinition? map)
    {
        if(map==null) return Generic("هیچ جدول انتخاب نشده","ابتدا یک جدول کالیبراسیون را انتخاب کنید.");

        string t=(map.NameFa+" "+map.NameEn+" "+map.Category).ToLowerInvariant();

        if(t.Contains("فن")||t.Contains("fan"))
            return new TuningGuideRule
            {
                Key="fan",Title="دمای فن / Fan Temperature",
                Purpose="دمای روشن/خاموش شدن فن را تعیین می‌کند.",
                Direction="برای زودتر روشن شدن فن، مقدار دمای هدف کاهش پیدا می‌کند.",
                SuggestedRange="شروع محافظه‌کارانه: 2 تا 4 درجه سانتی‌گراد کاهش؛ پس از تست حرارتی تصمیم نهایی بگیرید.",
                EditMode="value",DefaultValue=-3,AutoAllowed=true,
                Monitor="ECT، زمان کارکرد فن، دمای محیط، عملکرد ترموستات و افت ولتاژ.",
                StopCondition="اگر فن تقریباً دائم روشن می‌ماند، ولتاژ افت می‌کند یا موتور به دمای کاری مناسب نمی‌رسد، تغییر را برگردانید.",
                Warning="Auto فقط وقتی فعال است که واحد و Scale جدول مشخص و تعریف دقیق فایل تأیید شده باشد."
            };

        if(t.Contains("جرقه")||t.Contains("spark")||t.Contains("ignition"))
            return new TuningGuideRule
            {
                Key="spark",Title="جرقه / Ignition",
                Purpose="زمان‌بندی جرقه نسبت به موقعیت پیستون را تعیین می‌کند.",
                Direction="افزایش Advance می‌تواند پاسخ و گشتاور را تغییر دهد؛ تغییر باید فقط در ناحیه بار/RPM موردنظر انجام شود.",
                SuggestedRange="برای شروع تست، معمولاً تغییرهای کوچک حدود 0.5 تا 1 درجه‌ای بررسی می‌شوند؛ عدد نهایی باید با Knock و دیتالاگ تعیین شود.",
                EditMode="value",DefaultValue=0.5,AutoAllowed=false,
                Monitor="Knock/Retard، AFR/Lambda، IAT، ECT، EGT و کیفیت سوخت.",
                StopCondition="با مشاهده Knock، افزایش EGT غیرعادی یا افت گشتاور، Advance را برگردانید.",
                Warning="برای جدول‌هایی که Scale زاویه جرقه دقیقاً معلوم نیست، تغییر خودکار غیرفعال است."
            };

        if(t.Contains("پاشش")||t.Contains("fuel")||t.Contains("injection")||t.Contains("سوخت"))
            return new TuningGuideRule
            {
                Key="fuel",Title="سوخت / Injection",
                Purpose="مقدار یا زمان پاشش سوخت را در شرایط بار و دور مختلف کنترل می‌کند.",
                Direction="بسته به نوع جدول، افزایش مقدار ممکن است سوخت را بیشتر کند؛ قبل از تغییر باید جهت Scale همان ECU تأیید شود.",
                SuggestedRange="برای شروع تست روی جدولی با Scale تأییدشده، تغییرهای 2 تا 3 درصدی و سپس بررسی Wideband مناسب‌تر از تغییر بزرگ یک‌مرحله‌ای است.",
                EditMode="percent",DefaultValue=2,AutoAllowed=false,
                Monitor="Wideband Lambda/AFR، STFT/LTFT، Knock، EGT، Injector Duty و فشار سوخت.",
                StopCondition="Lambda خارج از هدف، Duty بالا، Misfire یا افزایش EGT یعنی تغییر باید متوقف/اصلاح شود.",
                Warning="Auto تا زمانی که جهت و Scale سوخت برای همان SW/CAL دقیق تأیید نشده فعال نمی‌شود."
            };

        if(t.Contains("گشتاور")||t.Contains("torque"))
            return new TuningGuideRule
            {
                Key="torque",Title="گشتاور / Torque",
                Purpose="درخواست یا محدودیت گشتاور موتور را کنترل می‌کند و ممکن است با چند جدول دیگر وابستگی داشته باشد.",
                Direction="افزایش یک محدودکننده بدون هماهنگ‌کردن زنجیره Torque Model ممکن است بی‌اثر یا نامطمئن باشد.",
                SuggestedRange="شروع تست محافظه‌کارانه روی تعریف دقیق: 3 تا 5 درصد، سپس دیتالاگ Torque Request/Actual و Load.",
                EditMode="percent",DefaultValue=3,AutoAllowed=false,
                Monitor="Torque Request/Actual، Load، Boost/MAP، Knock، Lambda، دمای هوا و آب.",
                StopCondition="اختلاف زیاد Requested/Actual، Knock، Overboost یا دمای غیرعادی.",
                Warning="برای گیربکس و محدودیت‌های حفاظتی تغییر خودکار انجام نمی‌شود."
            };

        if(t.Contains("لامبدا")||t.Contains("lambda"))
            return new TuningGuideRule
            {
                Key="lambda",Title="Lambda Target",
                Purpose="هدف نسبت هوا به سوخت را در شرایط مختلف تعیین می‌کند.",
                Direction="کم یا زیاد شدن عدد Lambda معنای مشخصی دارد و باید دقیقاً با نوع جدول و واحد آن بررسی شود.",
                SuggestedRange="اگر جدول واقعاً Lambda باشد، تغییرهای بسیار کوچک 0.01 تا 0.02 و تأیید با Wideband انجام شود.",
                EditMode="value",DefaultValue=-0.01,AutoAllowed=false,
                Monitor="Wideband Lambda، EGT، Knock، Fuel Pressure و Injector Duty.",
                StopCondition="مقادیر خارج از محدوده هدف، Misfire، Knock یا EGT غیرعادی.",
                Warning="بدون Scale تأییدشده هیچ تغییر خودکار روی Lambda انجام نمی‌شود."
            };

        if(t.Contains("دریچه")||t.Contains("throttle"))
            return new TuningGuideRule
            {
                Key="throttle",Title="دریچه گاز / Throttle",
                Purpose="رابطه درخواست راننده، پدال و زاویه دریچه را شکل می‌دهد.",
                Direction="تغییر باید تدریجی باشد تا پرش گشتاور یا کنترل‌پذیری نامناسب ایجاد نشود.",
                SuggestedRange="در جدول Percent تأییدشده، ابتدا 2 تا 4 درصد در ناحیه موردنظر و سپس تست پاسخ پدال.",
                EditMode="percent",DefaultValue=2,AutoAllowed=false,
                Monitor="APP، Throttle Command/Actual، Torque Request و خطاهای ETC.",
                StopCondition="اختلاف زیاد Command/Actual، خطای ETC یا پاسخ ناگهانی.",
                Warning="محدودیت‌های ایمنی ETC نباید حذف یا دور زده شوند."
            };

        if(t.Contains("کات")||t.Contains("cutoff")||t.Contains("limiter")||t.Contains("محدود"))
            return new TuningGuideRule
            {
                Key="limit",Title="محدودکننده / Cutoff",
                Purpose="حد دور، سرعت یا شرایط قطع سوخت/گشتاور را تعیین می‌کند.",
                Direction="تغییر این جداول می‌تواند مستقیماً روی محدودیت‌های مکانیکی و حفاظتی اثر بگذارد.",
                SuggestedRange="برای این دسته مقدار عمومی ایمن وجود ندارد؛ مقدار باید از حد مکانیکی موتور/گیربکس و مشخصات همان خودرو تعیین شود.",
                EditMode="none",DefaultValue=0,AutoAllowed=false,
                Monitor="RPM، Oil Pressure، EGT، Knock، Valve Train و محدودیت گیربکس.",
                StopCondition="هر علامت مکانیکی/حرارتی غیرعادی یا عبور از محدودیت سازنده.",
                Warning="Auto برای افزایش دور/سرعت غیرفعال است."
            };

        if(t.Contains("کویل")||t.Contains("coil")||t.Contains("dwell"))
            return new TuningGuideRule
            {
                Key="coil",Title="زمان شارژ کویل / Dwell",
                Purpose="زمان شارژ اولیه کویل را کنترل می‌کند.",
                Direction="Dwell زیاد می‌تواند کویل و درایور را داغ کند؛ کم بودن آن می‌تواند انرژی جرقه را کاهش دهد.",
                SuggestedRange="بدون مشخصات کویل، ولتاژ باتری و Scale دقیق توصیه عددی عمومی اعمال نمی‌شود.",
                EditMode="none",AutoAllowed=false,
                Monitor="VBAT، جریان کویل، Misfire و دمای کویل/درایور.",
                StopCondition="داغی غیرعادی، Misfire یا جریان بیش از مشخصات.",
                Warning="Auto غیرفعال است."
            };

        if(t.Contains("استارت")||t.Contains("cold")||t.Contains("crank"))
            return new TuningGuideRule
            {
                Key="cold",Title="استارت سرد / Cranking",
                Purpose="پاشش/اصلاحات هنگام استارت و گرم‌شدن اولیه را کنترل می‌کند.",
                Direction="تغییر باید بر اساس دمای آب/هوا و رفتار استارت انجام شود.",
                SuggestedRange="در جدول درصدی با جهت تأییدشده، تغییرهای 2 تا 3 درصدی و تست در دمای واقعی.",
                EditMode="percent",DefaultValue=2,AutoAllowed=false,
                Monitor="ECT، IAT، زمان استارت، Lambda بعد از روشن‌شدن و Misfire.",
                StopCondition="استارت طولانی‌تر، دود غیرعادی، Misfire یا غنی/فقیر شدن شدید.",
                Warning="Auto بدون دیتالاگ و Scale دقیق غیرفعال است."
            };

        return Generic(map.NameFa,"برای این جدول هنوز Rule عددی تأییدشده ثبت نشده است. اطلاعات آدرس/Scale و تعریف همان ECU را بررسی کنید.");
    }

    public static bool CanAuto(MapDefinition map,TuningGuideRule rule)
    {
        if(!rule.AutoAllowed || map.ReadOnly) return false;
        bool exact=map.Confidence.Contains("exact",StringComparison.OrdinalIgnoreCase) ||
                   map.Confidence.Contains("imported",StringComparison.OrdinalIgnoreCase);
        if(rule.RequiresExactDefinition && !exact) return false;
        if(rule.Key=="fan")
            return map.Unit.Contains("°",StringComparison.OrdinalIgnoreCase) && Math.Abs(map.Factor)>1e-12;
        return false;
    }

    private static TuningGuideRule Generic(string title,string purpose)=>new()
    {
        Key="generic",Title=title,Purpose=purpose,Direction="ابتدا نوع جدول، واحد، Scale و محورهای آن را تأیید کنید.",
        SuggestedRange="توصیه عددی عمومی برای این جدول ثبت نشده است.",EditMode="none",AutoAllowed=false,
        Monitor="دیتالاگ مرتبط با همان تابع ECU.",StopCondition="هر رفتار غیرعادی یا داده خارج از محدوده.",
        Warning="تا وقتی تعریف دقیق نباشد، برنامه تغییر خودکار انجام نمی‌دهد."
    };
}