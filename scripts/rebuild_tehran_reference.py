from pathlib import Path
import re

shop=Path('TehranSupermarket/shop.php')
s=shop.read_text(encoding='utf-8')
s=s.replace('<meta name="theme-color" content="#11823b">','<meta name="theme-color" content="#07090b">')
s=s.replace('<title>سوپرمارکت طهران</title>','<title>طهران مارکت</title>')
if 'assets/tehran-reference-v3.css?v=20261027-3' not in s:
    s=s.replace('</head>','<link rel="stylesheet" href="assets/tehran-reference-v3.css?v=20261027-3"></head>',1)
header='''<header class="top tmx-header"><a class="tmx-brand" href="index.php" aria-label="طهران مارکت"><span class="tmx-mark">ط</span><span class="tmx-brand-copy"><b>طهران مارکت</b><small>فروشگاه هوشمند مواد غذایی</small></span></a><form class="tmx-head-search" action="index.php" method="get"><input name="q" value="<?=e($q)?>" autocomplete="off" placeholder="جستجوی محصول؛ مثلا برنج، روغن، شیر…"><button type="submit" aria-label="جستجو">⌕</button></form><div class="tmx-top-actions"><a href="account.php">حساب من</a><a href="track.php">پیگیری سفارش</a></div></header>'''
s,n=re.subn(r'<header class="top">.*?</header>',header,s,count=1,flags=re.S)
if n!=1: raise SystemExit('header replace failed')
hero='''<section class="hero tmx-hero" id="smart-shopping"><div class="tmx-hero-copy"><span class="tmx-eyebrow">خرید هوشمند، ساده و سریع</span><h1>اولین فروشگاه <strong>مواد غذایی هوشمند در ایران</strong></h1><p>محصول را جستجو کن، بارکدش را اسکن کن یا از دستیار هوشمند کمک بگیر؛ خرید روزانه سریع‌تر و ساده‌تر.</p><div class="tmx-hero-actions"><a class="tmx-primary" href="#products" data-tmx-jump="#products">مشاهده محصولات ←</a><button class="tmx-secondary" type="button" data-tmx-ai>✦ خرید با هوش مصنوعی</button></div></div></section><section class="tmx-smart-row" aria-label="امکانات هوشمند"><a class="tmx-smart-card" href="#" data-tmx-ai><span class="tmx-smart-ico">AI</span><span><b>هوش مصنوعی</b><small>سبد خرید هوشمند و پیشنهاد سریع کالا</small></span></a><a class="tmx-smart-card" href="#" data-tmx-scan><span class="tmx-smart-ico">▥</span><span><b>اسکن بارکد</b><small>قیمت، موجودی و مشخصات در چند ثانیه</small></span></a><a class="tmx-smart-card" href="#checkout" data-tmx-jump="#checkout"><span class="tmx-smart-ico">🚚</span><span><b>ارسال سریع</b><small>ثبت لوکیشن و تحویل سریع سفارش</small></span></a></section>'''
s,n=re.subn(r'<section class="hero">.*?</section>',hero,s,count=1,flags=re.S)
if n!=1: raise SystemExit('hero replace failed')
s=s.replace('سوپرمارکت طهران · بهارستان اصفهان','طهران مارکت · فروشگاه هوشمند مواد غذایی')
s=s.replace("navigator.serviceWorker.register('sw.js',{scope:'/tehran/'}).catch(()=>{})","navigator.serviceWorker.register('sw.js?v=20261027-reference-3',{scope:'/tehran/',updateViaCache:'none'}).then(r=>r.update()).catch(()=>{})")
if 'id="tmx-mobile-nav"' not in s:
    nav='''<nav id="tmx-mobile-nav" aria-label="منوی پایین موبایل"><button type="button" data-tmx-nav="home" class="is-current"><span class="tmx-nav-ico">⌂</span><span>خانه</span></button><button type="button" data-tmx-nav="products"><span class="tmx-nav-ico">▦</span><span>محصولات</span></button><button type="button" data-tmx-nav="scan" class="tmx-scan"><span class="tmx-nav-ico">▥</span><span>اسکن</span></button><button type="button" data-tmx-nav="cart"><span class="tmx-nav-ico">🛒</span><span>سبد خرید</span></button><a href="account.php" data-tmx-nav="account"><span class="tmx-nav-ico">◯</span><span>حساب من</span></a></nav><script defer src="assets/tehran-reference-v3.js?v=20261027-3"></script>'''
    s=s.replace('</body>',nav+'</body>',1)
shop.write_text(s,encoding='utf-8')

idx=Path('TehranSupermarket/index.php')
x=idx.read_text(encoding='utf-8')
x=re.sub(r"\$serverShellHead\s*=\s*<<<'HTML'.*?\$html\s*=\s*str_replace\('</head>',\s*\$serverShellHead\.'</head>',\s*\$html\);\s*",'',x,flags=re.S)
x=re.sub(r"\$serverNavBody\s*=\s*<<<'HTML'.*?\$html\s*=\s*str_replace\('</body>',\s*\$serverNavBody\.'</body>',\s*\$html\);\s*",'',x,flags=re.S)
x=re.sub(r'assets/ai_entry_flow\.js\?v=[^"\']+','assets/ai_entry_flow.js?v=20261027-reference-3',x)
idx.write_text(x,encoding='utf-8')
