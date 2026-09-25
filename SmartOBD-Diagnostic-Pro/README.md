# Khaneh Remap Smart OBD Diagnostic Pro

نسخه یکپارچه Android + ESP32-C3 برای دیاگ خانه ریمپ، با همان تم سرمه‌ای/آبی/نارنجی طراحی‌شده.

## بخش فعال

`عیب یابی خودرو` کاملاً به BLE و VCI متصل است و این مسیر را اجرا می‌کند:

`شرکت → خودرو → ECU → مشخصات ECU / DTC / پارامترها / ریست ECU / صفحه کیلومتر`

امکانات تشخیصی Firmware:

- Auto Detect: CAN 500 kbps → CAN 250 kbps → K-Line Fast Init → K-Line 5-Baud
- OBD-II CAN با ISO-TP Single/Multi Frame
- ISO9141 / KWP روی K-Line
- ECU Info: VIN / Calibration ID / ECU Name / Protocol
- Stored / Pending / Permanent DTC
- Clear DTC
- Live PIDs: RPM, Speed, ECT, Fuel, Voltage, Load, MAP, IAT, MAF, TPS, Ignition Advance, STFT, LTFT, Runtime
- ECU Reset استاندارد (`0x11 0x01`) فقط در ECUهایی که آن را پشتیبانی می‌کنند
- صفحه کیلومتر زنده + هشدار دائمی کمبود آب
- قفل سریال ESP32 در NVS و Match شدن با اپ
- BLE fragmentation برای پیام‌های بلند
- صف فرمان BLE برای جلوگیری از تداخل Writeها

## بخش‌های فعلاً غیرفعال

طبق طراحی فعلی، این کلیدها قفل هستند و پیام `به زودی...` می‌دهند:

- جعبه ابزار ECU
- تعریف سوییچ و ریموت
- تنظیم کیلومتر
- اتصال OBD مستقل
- اسیلسکوپ
- تست باتری
- تنظیمات

## سخت‌افزار

ESP32-C3 به تنهایی نباید مستقیم به سیم‌های خودرو وصل شود. این Firmware برای سخت‌افزار فعلی پروژه با این لایه‌ها نوشته شده است:

- CAN transceiver → GPIO4 TX / GPIO5 RX
- L9637D یا MC33290 برای K-Line → GPIO7 TX / GPIO6 RX
- ورودی سنسور سطح آب → GPIO3

اگر در برد نهایی پایه‌ها متفاوت هستند، فقط ثابت‌های بالای `firmware/src/main.cpp` تغییر می‌کنند.

## BLE

- Service: `7f640201-7c7d-4f0a-8b6f-4f484f4d4501`
- CMD: `7f640202-7c7d-4f0a-8b6f-4f484f4d4501`
- DATA/Notify: `7f640203-7c7d-4f0a-8b6f-4f484f4d4501`

## Build

Firmware:

```bash
cd firmware
pio run
```

Android:

```bash
cd android
gradle :app:assembleDebug
```

Android Studio نیز می‌تواند پوشه `android` را مستقیم باز کند.

## نکته فنی مهم

عملیات موجود در این نسخه Diagnostics هستند و Flash/EEPROM ECU را نمی‌نویسند. انتخاب نام ECU در UI برای مسیر کاربری و Profile است؛ ارتباط واقعی همیشه با Auto Detect و پاسخ واقعی ECU تأیید می‌شود تا انتخاب اشتباه باعث اجرای فریم جعلی روی خودرو نشود.
