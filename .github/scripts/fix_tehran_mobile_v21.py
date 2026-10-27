from pathlib import Path
import re

shop_path = Path('TehranSupermarket/shop.php')
shop = shop_path.read_text(encoding='utf-8')

# Remove every experimental mobile-hero layer from earlier iterations.
shop = re.sub(r'<picture class="tm-ref-mobile-picture-v20">.*?</picture>', '', shop, flags=re.S)
shop = re.sub(r'<a class="tm-ref-mobile-hero-click-v20".*?</a>', '', shop, flags=re.S)
shop = re.sub(r'<style id="tm-ref-mobile-v20">.*?</style>', '', shop, flags=re.S)
shop = re.sub(r'<picture class="tm-ref-mobile-picture-v21">.*?</picture>', '', shop, flags=re.S)
shop = re.sub(r'<a class="tm-ref-mobile-hero-click-v21".*?</a>', '', shop, flags=re.S)
shop = re.sub(r'<style id="tm-ref-mobile-v21">.*?</style>', '', shop, flags=re.S)

hero_open = re.search(r'(<section class="tm-ref-hero"[^>]*>)', shop)
if not hero_open:
    raise SystemExit('tm-ref-hero section not found')

# Use a normal server asset, never a data URI. This asset is text SVG with its
# own embedded JPEG and has already been proven deployable by cPanel.
picture = (
    '<picture class="tm-ref-mobile-picture-v21" aria-hidden="true">'
    '<img src="assets/tehran-storefront-v3.svg?v=20261027-ref21" '
    'alt="نمای فروشگاه طهران مارکت" width="560" height="324">'
    '</picture>'
    '<div class="tm-ref-mobile-copy-v21">'
      '<div class="tm-ref-mobile-kicker-v21">اولین فروشگاه مواد غذایی هوشمند در ایران</div>'
      '<a class="tm-ref-mobile-buy-v21" href="#products">خرید کنید ←</a>'
    '</div>'
)
shop = shop[:hero_open.end()] + picture + shop[hero_open.end():]

style = r'''<style id="tm-ref-mobile-v21">
.tm-ref-mobile-picture-v21,.tm-ref-mobile-copy-v21{display:none}
@media(max-width:800px){
  /* One clean header row: menu | cart | brand | account. */
  .tm-ref-head-main{grid-template-columns:44px 44px minmax(0,1fr) 44px!important;grid-template-areas:"menu cart brand account" "search search search search"!important;column-gap:8px!important;row-gap:9px!important;padding:8px 14px 10px!important}
  .tm-ref-menu-mobile{grid-area:menu!important;display:grid!important;place-items:center!important;font-size:27px!important;line-height:1!important}
  .tm-ref-head-actions{display:contents!important}
  .tm-ref-head-actions .cart{grid-area:cart!important;display:flex!important;align-items:center!important;justify-content:center!important;font-size:23px!important}
  .tm-ref-head-actions .acct{grid-area:account!important;display:flex!important;align-items:center!important;justify-content:center!important;font-size:22px!important}
  .tm-ref-brand{grid-area:brand!important;justify-self:center!important;display:flex!important;align-items:center!important;min-width:0!important;max-width:210px!important;gap:0!important}
  .tm-ref-brand-mark{display:none!important}
  .tm-ref-brand-copy{display:block!important;text-align:center!important;min-width:0!important}
  .tm-ref-brand-copy b{display:block!important;font-size:20px!important;line-height:1.05!important;white-space:nowrap!important}
  .tm-ref-brand-copy small{display:block!important;font-size:7px!important;line-height:1.35!important;white-space:nowrap!important;margin-top:4px!important;opacity:.74!important}
  .tm-ref-search{grid-area:search!important;width:100%!important;margin:0!important}

  /* Stable hero: real img element from a verified local SVG asset. */
  .tm-ref-hero{position:relative!important;height:300px!important;min-height:300px!important;background:#080a0d!important;overflow:hidden!important}
  .tm-ref-hero:before{display:none!important}
  .tm-ref-hero-inner,.tm-ref-dots{display:none!important}
  .tm-ref-mobile-picture-v21{display:block!important;position:absolute!important;inset:0!important;width:100%!important;height:100%!important;margin:0!important;line-height:0!important}
  .tm-ref-mobile-picture-v21 img{display:block!important;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover!important;object-position:center center!important;margin:0!important;border:0!important}
  .tm-ref-hero:after{content:""!important;position:absolute!important;inset:0!important;background:linear-gradient(90deg,rgba(0,0,0,.72) 0%,rgba(0,0,0,.40) 42%,rgba(0,0,0,.07) 72%,rgba(0,0,0,0) 100%)!important;z-index:2!important;pointer-events:none!important}
  .tm-ref-mobile-copy-v21{display:flex!important;position:absolute!important;z-index:4!important;right:18px!important;left:42%!important;bottom:22px!important;flex-direction:column!important;align-items:flex-start!important;gap:12px!important;color:#fff!important;text-align:right!important}
  .tm-ref-mobile-kicker-v21{font-size:23px!important;font-weight:950!important;line-height:1.45!important;text-shadow:0 2px 12px rgba(0,0,0,.55)!important}
  .tm-ref-mobile-kicker-v21::first-line{color:#fff!important}
  .tm-ref-mobile-buy-v21{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:125px!important;height:40px!important;padding:0 20px!important;border-radius:999px!important;background:#f7c53e!important;color:#17120a!important;font-size:13px!important;font-weight:900!important;box-shadow:0 8px 24px rgba(247,197,62,.24)!important;text-decoration:none!important}
  .tm-ref-location-mobile{padding:7px 14px!important}
  .tm-ref-trust-wrap{margin:-1px 0 12px!important;padding:0 10px!important;position:relative!important;z-index:6!important}
}
@media(max-width:430px){
  .tm-ref-hero{height:270px!important;min-height:270px!important}
  .tm-ref-mobile-copy-v21{right:16px!important;left:38%!important;bottom:17px!important;gap:9px!important}
  .tm-ref-mobile-kicker-v21{font-size:20px!important;line-height:1.38!important}
  .tm-ref-mobile-buy-v21{height:38px!important;min-width:116px!important;padding:0 16px!important;font-size:12px!important}
}
</style>'''

if '</head>' not in shop:
    raise SystemExit('head marker missing')
shop = shop.replace('</head>', style + '</head>', 1)
shop_path.write_text(shop, encoding='utf-8')
