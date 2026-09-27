<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
http_response_code(200);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Care AI | سامانه بیمار کمک</title>
<meta name="description" content="سامانه Care AI برای ثبت‌نام، احراز هویت پیامکی و فعال‌سازی کاربران اپلیکیشن کمک‌یار بیمار.">
<meta name="robots" content="index,follow">
<style>
:root{
  --blue:#1678d3;
  --teal:#17b7aa;
  --ink:#173552;
  --muted:#64788c;
  --bg:#f4f9fd;
  --card:#fff;
  --line:#dfeaf2;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:Tahoma,Arial,sans-serif;
  background:linear-gradient(180deg,#f6fbff 0%,#eef7fc 100%);
  color:var(--ink);
  min-height:100vh;
}
.wrap{max-width:860px;margin:0 auto;padding:28px 16px 44px}
.hero{
  background:linear-gradient(135deg,#1678d3 0%,#17b7aa 100%);
  border-radius:28px;
  color:#fff;
  padding:30px 24px;
  box-shadow:0 14px 34px #156faa26;
}
.logo{
  width:72px;height:72px;border-radius:22px;background:#fff;
  display:grid;place-items:center;margin-bottom:18px;
  box-shadow:0 8px 22px #0b5d8e22;
}
.eye{
  width:46px;height:28px;border-radius:60% 60% 60% 60%;
  border:5px solid #1678d3;position:relative;
}
.eye:after{
  content:"";position:absolute;width:12px;height:12px;border-radius:50%;
  background:#17b7aa;left:12px;top:3px;
}
h1{margin:0 0 10px;font-size:30px;line-height:1.45}
.hero p{margin:0;line-height:2;font-size:15px;opacity:.96}
.grid{
  display:grid;grid-template-columns:repeat(2,minmax(0,1fr));
  gap:14px;margin-top:18px
}
.card{
  background:var(--card);border:1px solid var(--line);
  border-radius:22px;padding:20px;
  box-shadow:0 8px 24px #113b5a10;
}
.card h2{font-size:18px;margin:0 0 10px}
.card p,.card li{font-size:14px;line-height:2;color:var(--muted)}
.badge{
  display:inline-block;background:#e9f8f6;color:#087b72;
  border-radius:999px;padding:7px 12px;font-size:13px;font-weight:bold;
  margin-bottom:10px
}
.otp{
  background:#f8fbfe;border:1px dashed #bad4e8;border-radius:16px;
  padding:14px;margin-top:12px;color:#315a79;font-size:14px
}
.note{
  margin-top:16px;background:#fff8e8;border:1px solid #f1dfac;
  color:#7b652b;border-radius:18px;padding:15px;line-height:2;font-size:13px
}
.footer{
  text-align:center;margin-top:24px;color:#76899b;font-size:12px;line-height:2
}
a{color:var(--blue);text-decoration:none}
@media(max-width:650px){
  .grid{grid-template-columns:1fr}
  h1{font-size:25px}
  .wrap{padding:18px 12px 34px}
  .hero{padding:24px 18px;border-radius:22px}
}
</style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <div class="logo" aria-hidden="true"><div class="eye"></div></div>
    <h1>Care AI — سامانه بیمار کمک</h1>
    <p>
      Care AI یک سامانه کمک‌یار برای ارتباط و مراقبت از بیمارانی است که ممکن است
      توانایی صحبت یا استفاده معمول از تلفن همراه را نداشته باشند.
    </p>
  </section>

  <section class="grid">
    <article class="card">
      <span class="badge">احراز هویت پیامکی</span>
      <h2>کاربرد پیامک OTP</h2>
      <p>
        شماره موبایل کاربر هنگام ثبت‌نام با یک کد یک‌بارمصرف ۶ رقمی تأیید می‌شود.
        این کد فقط برای احراز هویت و فعال‌سازی حساب Care AI استفاده می‌شود.
      </p>
      <div class="otp">
        نمونه پیام:<br>
        «رمز ورود شما به سامانه بیمار کمک: 123456»
      </div>
    </article>

    <article class="card">
      <span class="badge">فعال‌سازی مدیر</span>
      <h2>ثبت و فعال‌سازی حساب</h2>
      <p>
        پس از تأیید شماره موبایل، حساب کاربر برای Patient ID مشخص ثبت شده
        و فعال‌سازی نهایی آن توسط مدیر سامانه انجام می‌شود.
      </p>
    </article>

    <article class="card">
      <span class="badge">حریم خصوصی</span>
      <h2>استفاده محدود از اطلاعات</h2>
      <p>
        اطلاعات ثبت‌نام فقط برای احراز هویت، مدیریت حساب و ارائه خدمات Care AI
        استفاده می‌شود و کد OTP کاربرد تبلیغاتی ندارد.
      </p>
    </article>

    <article class="card">
      <span class="badge">وضعیت سامانه</span>
      <h2>سرویس فعال است</h2>
      <p>
        این صفحه عمومی برای معرفی سامانه و بررسی آدرس مورد استفاده سرویس پیامکی
        در دسترس است.
      </p>
    </article>
  </section>

  <div class="note">
    Care AI یک ابزار کمک‌یار مراقبتی و ارتباطی است و جایگزین خدمات تخصصی پزشکی،
    اورژانس یا تشخیص پزشک نیست.
  </div>

  <footer class="footer">
    Care AI © <?=date('Y')?> — سامانه بیمار کمک<br>
    مسیر API و پنل مدیریت عمومی نیستند و از این صفحه جدا هستند.
  </footer>
</main>
</body>
</html>
