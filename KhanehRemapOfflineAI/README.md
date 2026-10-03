# خانه ریمپ — هوش مصنوعی آفلاین اندروید

نسخه 1.0 برای اجرای مدل GGUF مستقیماً روی گوشی، بدون API و بدون سرور.

- نام برنامه: خانه ریمپ
- Package: ir.khanehremap.offlineai
- ARM64 / Android 7+ (مناسب Galaxy A52s 5G)
- موتور: llama.cpp از طریق llama-android 0.1.1
- Context: 2048
- Threads: 4
- اینترنت عمداً از Manifest حذف شده است.
- گفتگوها و حافظه در SQLite خود گوشی ذخیره می‌شوند.

مدل پیشنهادی: Qwen2.5-3B-Instruct GGUF Q4_K_M
