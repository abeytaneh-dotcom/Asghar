from pathlib import Path
import re

shop_path = Path('TehranSupermarket/shop.php')
shop = shop_path.read_text(encoding='utf-8')

# Keep the hero image endpoint already present in shop.php. The current image is
# rendering on real phones; this pass only repairs page structure and mobile UI.
# Remove old injected visual layers/styles so the canonical block below is the
# single source of truth.
for pat in [
    r'<picture class="tm-ref-mobile-picture-v20">.*?</picture>',
    r'<a class="tm-ref-mobile-hero-click-v20".*?</a>',
    r'<picture class="tm-ref-mobile-picture-v21">.*?</picture>',
    r'<a class="tm-ref-mobile-hero-click-v21".*?</a>',
    r'<style id="tm-ref-mobile-v20">.*?</style>',
    r'<style id="tm-ref-mobile-v21">.*?</style>',
    r'<div class="tm-ref-mobile-picture-v20"[^>]*>.*?</div>',
    r'<div class="tm-ref-mobile-copy-v21">.*?</div>\s*</div>',
    r'<div class="tm-ref-mobile-copy-v22">.*?</div>\s*</div>',
]:
    shop = re.sub(pat, '', shop, flags=re.S)

# The broken revision placed search/categories/product grid inside .tm-ref-hero.
# Rebuild everything from hero start to the first product search form, closing
# the hero before the catalog and reopening the intended main/products sections.
m = re.search(r'(<section class="tm-ref-hero"[^>]*>).*?(<form class="search">)', shop, flags=re.S)
if not m:
    raise SystemExit('hero/product boundary not found')

canonical = r'''<div class="tm-ref-hero-inner">
  <div class="tm-ref-hero-copy">
    <h1>اولین فروشگاه <strong>مواد غذایی هوشمند</strong> در ایران</h1>
    <p>خریدی آسان با پیشنهادهای هوشمند، هوش مصنوعی و اسکن بارکد برای تجربه‌ای متفاوت از خرید.</p>
    <a class="tm-ref-cta" href="#products">خرید کنید ←</a>
  </div>
  <div class="tm-ref-hero-features">
    <div class="tm-ref-feature"><span class="ico">AI</span><div><b>هوش مصنوعی</b><small>پیشنهادهای هوشمند و شخصی‌سازی‌شده</small></div></div>
    <div class="tm-ref-feature"><span class="ico">▥</span><div><b>اسکن بارکد</b><small>محصول را سریع پیدا کنید</small></div></div>
    <div class="tm-ref-feature"><span class="ico">🚚</span><div><b>ارسال سریع</b><small>تحویل سریع در تهران</small></div></div>
  </div>
</div>
<div class="tm-ref-dots"><i></i><i></i><i></i></div>
<div class="tm-ref-mobile-picture-v20" aria-hidden="true"></div>
<div class="tm-ref-mobile-copy-v22">
  <div class="tm-ref-mobile-kicker-v22"><span>اولین فروشگاه</span><strong>مواد غذایی هوشمند</strong><span>در ایران</span></div>
  <p>خرید آسان با پیشنهادهای هوشمند و اسکن سریع بارکد</p>
  <a class="tm-ref-mobile-buy-v22" href="#products">خرید کنید ←</a>
</div>
</section>
<div class="tm-ref-trust-wrap"><div class="tm-ref-trust">
  <div class="tm-ref-trust-item"><span class="ico">🚚</span><div><b>ارسال سریع</b><small>به سراسر تهران</small></div></div>
  <div class="tm-ref-trust-item"><span class="ico">◇</span><div><b>پرداخت امن</b><small>با درگاه معتبر</small></div></div>
  <div class="tm-ref-trust-item"><span class="ico">✿</span><div><b>اصالت کالا</b><small>تضمین کیفیت و سلامت</small></div></div>
  <div class="tm-ref-trust-item"><span class="ico">♧</span><div><b>پشتیبانی ۲۴ ساعته</b><small>همیشه در کنار شما</small></div></div>
  <div class="tm-ref-trust-item"><span class="ico">⬡</span><div><b>بازگشت کالا</b><small>تا ۷ روز تضمین بازگشت</small></div></div>
</div></div>
<main>
<div class="state <?=$state['open']?'open':'closed'?>"><?=e($state['message'])?></div>
<section class="tm-ref-section" id="categories">
  <div class="tm-ref-section-head"><h2>دسته‌بندی کالاها</h2><a href="index.php#products">مشاهده همه ›</a></div>
  <div class="tm-ref-categories">
    <?php foreach(array_slice($cats,0,10) as $x):?>
      <a class="tm-ref-cat <?=$cat===$x?'on':''?>" href="?cat=<?=urlencode($x)?>#products">
        <span class="tm-ref-cat-media"><?php if(!empty($catThumbs[$x])):?><img loading="lazy" src="<?=e($catThumbs[$x])?>" alt="<?=e($x)?>"><?php else:?><span><?=e($em[$x]??'🛒')?></span><?php endif;?></span>
        <b><?=e($x)?></b>
      </a>
    <?php endforeach;?>
  </div>
</section>
<div class="tm-ref-promos">
  <article class="tm-ref-promo ai"><div class="tm-ref-promo-copy"><h3>خرید هوشمند<br><span>با هوش مصنوعی</span></h3><p>پیشنهادهای هوشمند و شخصی‌سازی‌شده برای شما</p><button type="button" data-tmx-ai>شروع تجربه هوشمند ←</button></div><div class="tm-ref-promo-art">🤖</div></article>
  <article class="tm-ref-promo scan"><div class="tm-ref-promo-copy"><h3>اسکن بارکد</h3><p>محصول مشابه را با اسکن بارکد سریع پیدا کنید</p><button type="button" data-tmx-scan>اسکن بارکد ←</button></div><div class="tm-ref-promo-art">▥</div></article>
  <article class="tm-ref-promo delivery"><div class="tm-ref-promo-copy"><h3>ارسال سریع</h3><p>تحویل سریع سفارش در تهران</p><a class="cta" href="#checkout">اطلاعات بیشتر ←</a></div><div class="tm-ref-promo-art">🚚</div></article>
</div>
<section class="tm-ref-specials" id="specials">
  <div class="tm-ref-section-head"><h2>پیشنهادهای ویژه</h2><a href="#products">مشاهده همه ›</a></div>
  <div class="tm-ref-special-grid">
    <?php foreach(array_slice($specialProducts,0,6) as $sp):?>
      <article class="tm-ref-special-card">
        <?php $spDisc=!empty($sp['original_price'])&&(int)$sp['original_price']>(int)$sp['price']&&(int)$sp['price']>0;$spPct=$spDisc?round((1-(int)$sp['price']/(int)$sp['original_price'])*100):0;if($spPct):?><span class="badge"><?=$spPct?>٪</span><?php endif;?>
        <div class="photo"><?php if(!empty($sp['image_path'])):?><img loading="lazy" src="<?=e($sp['image_path'])?>" alt="<?=e($sp['name'])?>"><?php else:?><span class="em"><?=e($em[$sp['category']]??'🛒')?></span><?php endif;?></div>
        <h3><?=e($sp['name'])?></h3><div class="price"><?=toman($sp['price'])?></div>
        <?php if((int)$sp['stock']>0&&(int)$sp['price']>0):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?=$sp['id']?>"><input type="hidden" name="return_to" value="index.php#specials"><button class="plus" aria-label="افزودن">+</button></form><?php endif;?>
      </article>
    <?php endforeach;?>
  </div>
</section>
<section id="products"><div class="sectionhead"><div><h2>محصولات فروشگاه</h2><p>نام، برند یا بارکد را جستجو کن.</p></div></div>'''

shop = shop[:m.start()] + m.group(1) + canonical + m.group(2) + shop[m.end():]

style = r'''<style id="tm-ref-mobile-v20">
.tm-ref-mobile-picture-v20,.tm-ref-mobile-copy-v22{display:none}
@media(max-width:800px){
  .tm-ref-head-main{grid-template-columns:44px 44px minmax(0,1fr) 44px!important;grid-template-areas:"menu cart brand account" "search search search search"!important;column-gap:8px!important;row-gap:9px!important;padding:8px 14px 10px!important;width:100%!important;max-width:100%!important}
  .tm-ref-menu-mobile{grid-area:menu!important;display:grid!important;place-items:center!important;font-size:27px!important;line-height:1!important}
  .tm-ref-head-actions{display:contents!important}
  .tm-ref-head-actions .cart,.tm-ref-head-actions .acct{display:flex!important;align-items:center!important;justify-content:center!important;font-size:0!important;min-width:0!important;overflow:hidden!important}
  .tm-ref-head-actions .cart{grid-area:cart!important}.tm-ref-head-actions .acct{grid-area:account!important}
  .tm-ref-head-actions .cart span,.tm-ref-head-actions .acct span{display:none!important}
  .tm-ref-head-actions .cart i{font-size:23px!important}.tm-ref-head-actions .acct i{font-size:22px!important}
  .tm-ref-brand{grid-area:brand!important;justify-self:center!important;display:flex!important;align-items:center!important;min-width:0!important;width:auto!important;max-width:210px!important;gap:0!important;overflow:hidden!important}
  .tm-ref-brand-mark{display:none!important}.tm-ref-brand-copy{display:block!important;text-align:center!important;min-width:0!important;overflow:hidden!important}
  .tm-ref-brand-copy b{display:block!important;font-size:20px!important;line-height:1.05!important;white-space:nowrap!important}.tm-ref-brand-copy small{display:block!important;font-size:6.5px!important;line-height:1.35!important;white-space:nowrap!important;margin-top:4px!important;opacity:.74!important;overflow:hidden!important;text-overflow:ellipsis!important}
  .tm-ref-search{grid-area:search!important;width:100%!important;margin:0!important;min-width:0!important}
  .tm-ref-hero{position:relative!important;height:245px!important;min-height:245px!important;background:#090b0e!important;overflow:hidden!important}.tm-ref-hero:before,.tm-ref-hero:after{display:none!important}.tm-ref-hero-inner,.tm-ref-dots{display:none!important}
  .tm-ref-mobile-picture-v20{display:block!important;position:absolute!important;inset:0!important;background-image:linear-gradient(90deg,rgba(0,0,0,.74) 0%,rgba(0,0,0,.46) 31%,rgba(0,0,0,.08) 58%,rgba(0,0,0,0) 78%),url('shop.php?tm_asset=hero_v22&v=20261027-26')!important;background-size:cover!important;background-position:center center!important;background-repeat:no-repeat!important;z-index:1!important}
  .tm-ref-mobile-copy-v22{display:flex!important;position:absolute!important;z-index:3!important;left:14px!important;right:auto!important;width:46%!important;bottom:15px!important;flex-direction:column!important;align-items:flex-end!important;gap:7px!important;color:#fff!important;text-align:right!important;direction:rtl!important}
  .tm-ref-mobile-kicker-v22{display:flex!important;flex-direction:column!important;align-items:flex-end!important;gap:0!important;font-weight:950!important;line-height:1.25!important;text-shadow:0 2px 12px rgba(0,0,0,.75)!important}.tm-ref-mobile-kicker-v22 span{font-size:16px!important;color:#fff!important}.tm-ref-mobile-kicker-v22 strong{font-size:20px!important;color:#f7c53e!important;font-weight:950!important;white-space:nowrap!important}
  .tm-ref-mobile-copy-v22 p{margin:0!important;font-size:9px!important;line-height:1.65!important;color:rgba(255,255,255,.92)!important;max-width:165px!important}.tm-ref-mobile-buy-v22{display:inline-flex!important;align-items:center!important;justify-content:center!important;height:35px!important;padding:0 15px!important;border-radius:999px!important;background:#f7c53e!important;color:#17120a!important;font-size:11px!important;font-weight:900!important;text-decoration:none!important;box-shadow:0 8px 22px rgba(247,197,62,.24)!important}
  .tm-ref-location-mobile{padding:7px 14px!important}.tm-ref-trust-wrap{margin:-1px 0 12px!important;padding:0 8px!important;position:relative!important;z-index:6!important}.tm-ref-trust{grid-template-columns:repeat(4,minmax(0,1fr))!important;border-radius:0 0 16px 16px!important}.tm-ref-trust-item{display:flex!important;flex-direction:column!important;justify-content:center!important;text-align:center!important;gap:3px!important;padding:10px 4px!important;min-width:0!important}.tm-ref-trust-item:nth-child(5){display:none!important}.tm-ref-trust-item .ico{font-size:20px!important}.tm-ref-trust-item b{font-size:8px!important;white-space:nowrap!important}.tm-ref-trust-item small{display:none!important}
  body main{display:block!important;width:100%!important;max-width:100%!important;padding:0 10px 72px!important;margin:0!important;overflow:visible!important}.state{display:block!important;margin:10px 0 14px!important;text-align:center!important;font-size:10px!important}
  .tm-ref-section{display:block!important;margin:18px 0 14px!important}.tm-ref-section-head h2{font-size:21px!important}.tm-ref-section-head a{font-size:9px!important}.tm-ref-categories{display:flex!important;gap:8px!important;overflow-x:auto!important;overflow-y:hidden!important;scrollbar-width:none!important;padding-bottom:3px!important}.tm-ref-categories::-webkit-scrollbar{display:none!important}.tm-ref-cat{flex:0 0 104px!important;width:104px!important;padding:7px 5px!important}.tm-ref-cat-media{height:74px!important}.tm-ref-cat b{font-size:9px!important}
  .tm-ref-promos{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important;margin:12px 0 20px!important}.tm-ref-promo{min-height:145px!important;padding:14px!important;border-radius:14px!important}.tm-ref-promo:nth-child(3){display:none!important}.tm-ref-promo h3{font-size:18px!important}.tm-ref-promo p{font-size:8px!important;line-height:1.7!important}.tm-ref-promo-art{font-size:50px!important}.tm-ref-promo button,.tm-ref-promo a.cta{font-size:8px!important;padding:7px 10px!important}
  .tm-ref-specials{display:block!important;margin:18px 0 24px!important}.tm-ref-special-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important}.tm-ref-special-card{padding:7px!important}.tm-ref-special-card .photo{height:100px!important}.tm-ref-special-card h3{font-size:8px!important;min-height:28px!important}.tm-ref-special-card .price{font-size:9px!important}.tm-ref-special-card .plus{width:28px!important;height:28px!important;left:7px!important;bottom:7px!important}
  #products{display:block!important;overflow:visible!important;scroll-margin-top:15px!important}#products>.search,#products>.cats{display:none!important}#products .sectionhead{display:flex!important;margin:18px 0 10px!important}#products .sectionhead h2{font-size:21px!important}#products .grid,.grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:9px!important;width:100%!important;overflow:visible!important}.card{display:block!important;visibility:visible!important;opacity:1!important;min-width:0!important}.product-photo,.emoji{height:125px!important}.pager{margin:16px 0 20px!important}.cartbox{margin-top:12px!important}
}
@media(max-width:390px){.tm-ref-head-main{grid-template-columns:40px 40px minmax(0,1fr) 40px!important;padding-inline:10px!important}.tm-ref-brand-copy b{font-size:18px!important}.tm-ref-hero{height:232px!important;min-height:232px!important}.tm-ref-mobile-copy-v22{left:12px!important;width:48%!important;bottom:12px!important}.tm-ref-mobile-kicker-v22 span{font-size:15px!important}.tm-ref-mobile-kicker-v22 strong{font-size:18px!important}.tm-ref-mobile-copy-v22 p{font-size:8px!important}.tm-ref-cat{flex-basis:96px!important;width:96px!important}.tm-ref-special-card .photo{height:88px!important}}
</style>'''

if '</head>' not in shop:
    raise SystemExit('head marker not found')
shop = shop.replace('</head>', style + '</head>', 1)
shop_path.write_text(shop, encoding='utf-8')
