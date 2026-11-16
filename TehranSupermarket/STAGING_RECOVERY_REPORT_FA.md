# طهران مارکت — گزارش بازیابی ایزوله و بررسی زیرساخت

**تاریخ:** ۱۶ نوامبر ۲۰۲۶
**وضعیت:** آزمون ایزوله موفق؛ **بدون انتشار و بدون تغییر دیتابیس زنده**
**منبع دیتابیس:** نسخه آرشیوی حفاظت‌شده ۸ نوامبر ۲۰۲۶ (خوانده‌شده فقط از FTPS خصوصی)
**مقصد آزمون:** MySQL 8 موقت روی GitHub runner، حذف‌شونده پس از پایان اجرا

## نتایج واقعی (موفق)

1. بکاپ SQL حفاظت‌شده با دسترسی FTP رمزگذاری‌شده، بدون انتشار محتوا، از Staging خوانده شد.
2. `gzip -t` و نشانگر کامل‌بودن آرشیو تأیید شدند.
3. بازیابی کامل SQL در دیتابیس موقت مستقل از هاست اصلی موفق شد.
4. تعداد جدول‌های بازیابی‌شده: **۱۸**.
5. تعداد ردیف‌های محصول: **۹٬۸۴۹**.
6. ابزار واقعی `TehranSupermarket/bin/preflight.php` از شاخه `feature/enterprise-v3` با `TEHRAN_STAGING=1` و `--check` در حالت فقط‌خواندنی علیه دیتابیس موقت اجرا شد.
7. تمام فیلدهای الزامی `products`, `orders`, `order_items` توسط preflight تأیید شد.
8. در اجرای GitHub، هیچ SQL یا اطلاعات شخصی به خروجی یا Artifact جدید منتقل نشد.
9. آخرین اجرای موفق: [43923044687](https://github.com/abeytaneh-dotcom/tehranmarket-site1/actions/runs/43923044687). آزمون اولیه بازیابی: [43922345758](https://github.com/abeytaneh-dotcom/tehranmarket-site1/actions/runs/43922345758).

## DNS / TLS / cPanel

- DNS دامنه `www.tehranmarket-baharestan.ir`: پاسخ معتبر (resolved).
- اعتبار TLS: PASS؛ سررسید گواهی: **۲۰۲۷-۰۲-۰۸**.
- هیچ صفحه PHP عملیاتی برای تست TLS درخواست نشد (فقط handshake).
- Secret لازم برای API cPanel: `TEHRANMARKET_CPANEL_TOKEN` در زمان آزمون **MISSING**.
- خواندن ظرفیت CPU، RAM، Cron، دیتابیس cPanel و محدودیت پردازش‌ها از طریق API: **نامشخص** تا زمان پیکربندی Secret.
- آزمون ظرفیت: [43922530339](https://github.com/abeytaneh-dotcom/tehranmarket-site1/actions/runs/43922530339).
- در ۸ نوامبر، دسترسی مستقیم وب به پوشه private staging با HTTP 403 تأیید شده بود؛ [42504416929](https://github.com/abeytaneh-dotcom/tehranmarket-site1/actions/runs/42504416929).

## وضعیت‌های هنوز بسته‌نشده

- **بازیابی روی هاست مقصد انجام نشده**؛ بازیابی موفق فقط در محیط موقت جداگانه GitHub بوده است.
- دیتابیس cPanel مقصد، کاربر دیتابیس، محدودیت منابع، قابل‌اجرا بودن Cron و اتصال PHP مقصد هنوز به‌صورت end-to-end تأیید نشده است.
- سیستم واقعی سفارش/ورود/پرداخت/برگشت، تست هم‌زمانی ۱۰۰ سفارش، UI، بارکدخوان و AI روی دامنه مقصد آزمایش نشده‌اند.
- اطلاعات رسمی نرم‌افزار پیرامید و رابط تأییدشده آن هنوز موجود نیست.
- کد ایمن‌سازی در `feature/enterprise-v3` **ادغام یا منتشر نشده است**. ادغام در `websaza-hosting` به علت workflow انتشار خودکار به دامنه قدیمی خطرناک است و نیاز به مجوز مستقل بعد از QA دارد.
- تا رفع موانع بالا، هیچ عملیات cutover، تغییر DNS، غیرفعال‌سازی سایت قدیمی یا همگام‌سازی نوشتنی قیمت/موجودی مجاز نیست.

## اقدامات انجام‌شده در GitHub

- شاخه بررسی مستقل در مقصد: [feature/staging-recovery-validation](https://github.com/abeytaneh-dotcom/tehranmarket-site1/tree/feature/staging-recovery-validation).
- Workflow بازیابی فقط روی MySQL موقت: `.github/workflows/verify-isolated-tehran-sql-restore.yml`.
- Workflow ظرفیت فقط‌خواندنی: `.github/workflows/verify-cpanel-capacity-readonly.yml`.
- هر دو workflow تنها در شاخه آزمایشی فعال شدند؛ هیچ workflow اصلی انتشار اجرا نشد.

## اقدام ضروری

مدیر باید `TEHRANMARKET_CPANEL_TOKEN` را در **GitHub repository Secrets** مخزن مقصد ایجاد کند؛ **مقدار محرمانه را ارسال نکند**. سپس بررسی فقط‌خواندنی cPanel و ایجاد دیتابیس اختصاصی آزمایشی صرفاً با کنترل عدم‌تداخل، بررسی backup و تأیید انسانی مرحله‌ای انجام شود.

**نتیجه:** قابلیت بازیابی بکاپ و سازگاری ضروری schema ثابت شد؛ آماده‌بودن سایت زنده برای استقرار همچنان تأییدنشده است.
