from pathlib import Path
import re

# deploy trigger: v20 reliable mobile hero from current HTML
shop_path = Path('TehranSupermarket/shop.php')
source_path = Path('.github/scripts/inline_tehran_v18.py')

shop = shop_path.read_text(encoding='utf-8')
source = source_path.read_text(encoding='utf-8')

# Prefer the image already embedded in the current storefront; it is the exact approved mobile crop.
found = re.findall(r'data:image/jpeg;base64,([A-Za-z0-9+/=]+)', shop)
mobile_b64 = max(found, key=len) if found else ''

# Fallback to the build source only if the current HTML has no embedded image.
if len(mobile_b64) < 10000:
    m = re.search(r"mobile_b64\s*=\s*'''(.*?)'''", source, flags=re.S)
    if m:
        mobile_b64 = re.sub(r'\s+', '', m.group(1))

if len(mobile_b64) < 10000:
    raise SystemExit('approved mobile hero image not found')

shop = re.sub(r'<picture class="tm-ref-mobile-picture-v20">.*?</picture>', '', shop, flags=re.S)
shop = re.sub(r'<a class="tm-ref-mobile-hero-click-v20".*?</a>', '', shop, flags=re.S)
shop = re.sub(r'<style id="tm-ref-mobile-v20">.*?</style>', '', shop, flags=re.S)

hero_open = re.search(r'(<section class="tm-ref-hero"[^>]*>)', shop)
if not hero_open:
    raise SystemExit('tm-ref-hero section not found')

picture = (
    '<picture class="tm-ref-mobile-picture-v20" aria-hidden="true">'
    '<img src="data:image/jpeg;base64,' + mobile_b64 + '" alt="نمای فروشگاه طهران مارکت">'
    '</picture>'
    '<a class="tm-ref-mobile-hero-click-v20" href="#products" aria-label="خرید کنید"></a>'
)
shop = shop[:hero_open.end()] + picture + shop[hero_open.end():]

style = r'''<style id="tm-ref-mobile-v20">
.tm-ref-mobile-picture-v20,.tm-ref-mobile-hero-click-v20{display:none}
@media(max-width:800px){
  .tm-ref-head-main{grid-template-columns:44px 46px minmax(0,1fr) 44px!important;grid-template-areas:"menu cart brand account" "search search search search"!important;gap:7px!important;padding:8px 12px!important}
  .tm-ref-menu-mobile{grid-area:menu!important;display:grid!important;place-items:center!important;font-size:25px!important}
  .tm-ref-head-actions{display:contents!important}
  .tm-ref-head-actions .cart{grid-area:cart!important;display:flex!important;align-items:center!important;justify-content:center!important}
  .tm-ref-head-actions .acct{grid-area:account!important;display:flex!important;align-items:center!important;justify-content:center!important}
  .tm-ref-brand{grid-area:brand!important;justify-self:center!important;min-width:0!important;max-width:190px!important;display:flex!important;flex-direction:row!important;align-items:center!important;gap:5px!important;white-space:nowrap!important}
  .tm-ref-brand-mark{font-size:21px!important;flex:0 0 auto!important}
  .tm-ref-brand-copy{min-width:0!important;text-align:right!important}
  .tm-ref-brand-copy b{font-size:17px!important;line-height:1.05!important;white-space:nowrap!important;display:block!important}
  .tm-ref-brand-copy small{font-size:6.5px!important;line-height:1.3!important;white-space:nowrap!important;display:block!important;margin-top:3px!important}
  .tm-ref-search{grid-area:search!important;width:100%!important;margin:0!important}
  .tm-ref-hero{position:relative!important;min-height:0!important;height:auto!important;background:#080a0d!important;overflow:hidden!important}
  .tm-ref-hero:before{display:none!important}
  .tm-ref-hero-inner,.tm-ref-dots{display:none!important}
  .tm-ref-mobile-picture-v20{display:block!important;width:100%!important;margin:0!important;padding:0!important;line-height:0!important}
  .tm-ref-mobile-picture-v20 img{display:block!important;width:100%!important;height:auto!important;max-width:none!important;object-fit:contain!important;margin:0!important;padding:0!important;border:0!important}
  .tm-ref-mobile-hero-click-v20{display:block!important;position:absolute!important;z-index:8!important;left:5.5%!important;bottom:7%!important;width:34%!important;height:18%!important;border-radius:999px!important;background:transparent!important}
  .tm-ref-trust-wrap{margin:-1px 0 10px!important;padding:0 8px!important}
  .tm-ref-location-mobile{padding:7px 14px!important}
}
</style>'''

if '</head>' not in shop:
    raise SystemExit('head marker not found')
shop = shop.replace('</head>', style + '</head>', 1)
shop_path.write_text(shop, encoding='utf-8')
