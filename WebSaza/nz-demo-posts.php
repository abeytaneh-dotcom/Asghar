<?php
declare(strict_types=1);
require_once __DIR__.'/nz-taxonomy.php';
/** The existing public showcase, shared by the home page, map and assistant. */
function nzdemo_posts(): array {
    $posts = [
 ['id'=>'demo_iphone','demo'=>true,'title'=>'آیفون ۱۵ پرو ۲۵۶ گیگ، تمیز','description'=>'گوشی کاملاً سالم و بسیار تمیز، بدون تعمیر و ضربه. باتری عالی و همراه با جعبه و کابل. این آگهی نمایشی است.','category'=>'کالای دیجیتال','subcategory'=>'موبایل','city'=>'تهران','district'=>'سعادت‌آباد','price'=>72500000,'views'=>184,'featured'=>true,'created_at'=>date('c',time()-1200),'demo_image'=>'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_207','demo'=>true,'title'=>'پژو ۲۰۷ اتومات مدل ۱۴۰۳','description'=>'خودرو بسیار تمیز و کم‌کار، بدنه بی‌رنگ، سرویس‌ها انجام شده و آماده بازدید. این آگهی نمایشی است.','category'=>'وسایل نقلیه','subcategory'=>'خودرو','city'=>'شیراز','district'=>'معالی‌آباد','price'=>1360000000,'views'=>321,'featured'=>false,'created_at'=>date('c',time()-2500),'demo_image'=>'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_home','demo'=>true,'title'=>'آپارتمان ۱۲۵ متر، نورگیر و خوش‌نقشه','description'=>'دو خواب، پارکینگ و آسانسور، ساختمان کم‌واحد و دسترسی عالی. تصاویر و مشخصات جهت نمایش رابط کاربری هستند.','category'=>'املاک','subcategory'=>'آپارتمان','city'=>'تهران','district'=>'پونک','price'=>7800000000,'views'=>267,'featured'=>true,'created_at'=>date('c',time()-3900),'demo_image'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_tv','demo'=>true,'title'=>'تلویزیون سونی ۶۵ اینچ 4K','description'=>'کاملاً سالم، تصویر عالی، ریموت و پایه کامل. این آگهی جهت نمایش ظاهر نیازستان ایجاد شده است.','category'=>'خانه و آشپزخانه','subcategory'=>'لوازم خانگی','city'=>'اصفهان','district'=>'مرداویج','price'=>48900000,'views'=>98,'featured'=>false,'created_at'=>date('c',time()-5400),'demo_image'=>'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_mac','demo'=>true,'title'=>'مک‌بوک ایر M2، رم ۸، حافظه ۲۵۶','description'=>'ظاهر بسیار تمیز، شارژدهی عالی و مناسب کار و دانشگاه. آگهی نمایشی برای پیش‌نمایش پلتفرم.','category'=>'کالای دیجیتال','subcategory'=>'لپ‌تاپ','city'=>'مشهد','district'=>'سجاد','price'=>79000000,'views'=>143,'featured'=>false,'created_at'=>date('c',time()-7200),'demo_image'=>'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_sofa','demo'=>true,'title'=>'مبل راحتی ۷ نفره، بسیار تمیز','description'=>'پارچه سالم و بدون پارگی، فوم سرد و پایه چوبی. آگهی نمونه برای نمایش کارت‌های نیازستان.','category'=>'خانه و آشپزخانه','subcategory'=>'مبلمان','city'=>'شیراز','district'=>'قصرالدشت','price'=>35000000,'views'=>76,'featured'=>false,'created_at'=>date('c',time()-9100),'demo_image'=>'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_service','demo'=>true,'title'=>'سرویس و تعمیر کولر گازی در محل','description'=>'سرویس کامل، شست‌وشو، شارژ گاز و عیب‌یابی در محل. قیمت درج شده هزینه پایه سرویس است. آگهی نمایشی.','category'=>'خدمات','subcategory'=>'تعمیرات','city'=>'تهران','district'=>'غرب تهران','price'=>750000,'views'=>211,'featured'=>false,'created_at'=>date('c',time()-12000),'demo_image'=>'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=900&q=82'],
 ['id'=>'demo_bike','demo'=>true,'title'=>'موتورسیکلت یاماها، کم‌کار و سالم','description'=>'موتور فنی سالم، ظاهر تمیز و مدارک کامل. جهت نمایش ظاهر پلتفرم نیازستان.','category'=>'وسایل نقلیه','subcategory'=>'موتورسیکلت','city'=>'کرج','district'=>'جهانشهر','price'=>285000000,'views'=>119,'featured'=>false,'created_at'=>date('c',time()-15000),'demo_image'=>'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=82'],
];
    $attrs=[
        'demo_iphone'=>['برند'=>'Apple','مدل'=>'iPhone 15 Pro','حافظه'=>'۲۵۶ گیگ'],
        'demo_207'=>['برند'=>'پژو','مدل'=>'207','سال ساخت'=>'۱۴۰۳','گیربکس'=>'اتوماتیک'],
        'demo_home'=>['متراژ'=>'۱۲۵','تعداد اتاق'=>'۲'],
        'demo_tv'=>['برند'=>'Sony','اندازه'=>'۶۵ اینچ','کیفیت تصویر'=>'4K'],
        'demo_mac'=>['برند'=>'Apple','مدل'=>'MacBook Air M2','حافظه'=>'۲۵۶ گیگ','رم'=>'۸ گیگ'],
        'demo_sofa'=>['مدل'=>'راحتی','ظرفیت'=>'۷ نفره'],
        'demo_service'=>['نوع خدمت'=>'سرویس کولر گازی'],
        'demo_bike'=>['برند'=>'Yamaha']
    ];
    foreach($posts as &$post){$post['status']='published';$post['attributes']=$attrs[$post['id']]??[];foreach(nzt_provinces() as $province=>$cities)if(in_array($post['city'],$cities,true)){$post['province']=$province;break;}}unset($post);
    return $posts;
}
