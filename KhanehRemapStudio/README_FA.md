# Khaneh Remap Studio Pro

برنامه مستقل ویندوز برای تحلیل و ویرایش فایل‌های کالیبراسیون ECU.

## هسته واقعی
- باز/ذخیره مستقیم BIN/ORI/MOD/ROM/DUMP
- SHA-256 و تشخیص دامپ از بانک محلی
- ایندکس پوشه بانک دامپ با SHA-256 و اثرانگشت 16 بلوکی
- تطبیق دقیق و تطبیق نزدیک فایل‌های تغییر یافته
- جدول واقعی متصل به بایت‌های فایل
- 2D / 3D / HEX
- Undo / Redo
- Compare Original / Modified
- ویرایش گروهی Set / Add / Multiply / Percent
- Import: TunerPro XDF, ASAM A2L, JSON, CSV
- محورهای X/Y از XDF در صورت وجود
- چند XDF آزاد OpenGK همراه مجوز Apache-2.0

## Checksum plugins
الگوریتم‌های زیر روی بانک دامپ کاربر آزمایش شدند:
- Sagem S2000
- Valeo PL4 / J34 family
- Bosch ME7.4.4
- Bosch ME7.4.5
- Bosch ME7.4.9
- Bosch M7.9.7.1
- Bosch M7.4.11
- Bosch M7.9.7 (variant محدود)
- Bosch M7.4.4 CRC32

برای فایل‌هایی که پلاگین تأییدشده ندارند، برنامه صریحاً Unsupported نمایش می‌دهد و چکسام حدسی نمی‌نویسد.

## Map confidence
- exact-hash+signature: پروفایل دقیق قابل ویرایش
- imported: تعریف XDF/CSV معتبر
- family-signature: تشخیص ساختاری خانواده، به صورت Read-only تا تطبیق دقیق
- A2L partial: فقط اطلاعاتی که قطعی تفسیر شده‌اند

## نکته IR System
فایل IRSystem کاربر به عنوان مرجع/منبع مجاز بررسی می‌شود، اما برنامه برای اجرا به IR System وابسته نیست و هیچ مکانیزم فعال‌سازی یا قفل نرم‌افزار دیگری را دور نمی‌زند.
