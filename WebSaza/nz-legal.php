<?php
declare(strict_types=1);

function nzlegal_page(string $page): string {
    if($page==='privacy')return'<main class="page page-narrow"><div class="surface"><h1>حریم خصوصی نیازستان</h1><p class="lead">اطلاعات فقط برای ارائه سرویس، امنیت و مقابله با سوءاستفاده پردازش می‌شود.</p><div class="description"><b>اطلاعات حساب:</b> شماره موبایل تأییدشده، نام نمایشی و زمان فعالیت.<br><br><b>اطلاعات امنیتی:</b> آخرین IP شناخته‌شده، نوع دستگاه/مرورگر و User-Agent برای تشخیص سوءاستفاده، پشتیبانی و رسیدگی به گزارش‌ها نگهداری می‌شود و برای کاربران دیگر نمایش داده نمی‌شود. اطلاعات فنی کاربران غیرفعال در نگهداری دوره‌ای پاک یا حداقل می‌شود.<br><br><b>آگهی و گفتگو:</b> محتوای آگهی، رسانه، گفتگوها، گزارش‌ها و نتایج پایش امنیتی برای اداره پلتفرم ذخیره می‌شوند. پیام‌ها و تصاویر مشکوک ممکن است برای بررسی مدیر علامت‌گذاری شوند.<br><br><b>موقعیت:</b> موقعیت دقیق یا حدودی فقط مطابق انتخاب کاربر در آگهی ذخیره می‌شود.<br><br><b>پرداخت:</b> اطلاعات کارت بانکی در نیازستان ذخیره نمی‌شود.<br><br><b>اشتراک‌گذاری:</b> شماره تماس، IP و اطلاعات دستگاه به سایر کاربران فروخته یا نمایش داده نمی‌شود؛ دسترسی مدیریتی صرفاً برای امنیت و پشتیبانی است.</div><a class="btn ghost" style="margin-top:18px" href="/nz?page=profile">بازگشت</a></div></main>';
    if($page==='rules')return'<main class="page page-narrow"><div class="surface"><h1>قوانین انتشار آگهی</h1><div class="description">ثبت اولیه آگهی رایگان است و انتشار پس از بررسی مدیر انجام می‌شود. آگهی تکراری یا بسیار مشابه، حتی با شماره، حساب یا شهر متفاوت، قابل ثبت نیست. محتوای مستهجن، برهنگی، توهین، تهدید، دشنام، کالای ممنوع، اطلاعات گمراه‌کننده و تصاویر مشکوک می‌تواند باعث توقف انتشار و بررسی فوری شود. آگهی منتشرشده ۳۰ روز فعال است و در صورت عدم تمدید از نمایش عمومی خارج می‌شود. ویرایش عنوان، توضیحات یا قیمت باعث بررسی دوباره آگهی می‌شود. کاربران مسئول صحت اطلاعات و رعایت قوانین معامله هستند و نیازستان می‌تواند برای امنیت، حساب یا گفتگوهای پرریسک را محدود کند.</div><a class="btn ghost" style="margin-top:18px" href="/nz">بازگشت</a></div></main>';
    return'';
}
function nzlegal_enhance(string $html): string {
    $page=(string)($_GET['page']??'');if(in_array($page,['privacy','rules'],true)&&function_exists('nzd_replace_main'))return nzd_replace_main($html,nzlegal_page($page));
    if($page==='profile')$html=str_replace('</div><form method="post" style="margin-top:18px">','<a class="menu-item" href="/nz?page=privacy"><span>حریم خصوصی</span><span>‹</span></a><a class="menu-item" href="/nz?page=rules"><span>قوانین انتشار</span><span>‹</span></a></div><form method="post" style="margin-top:18px">',$html);
    return$html;
}
