from pathlib import Path
import re
import base64

shop_path = Path('TehranSupermarket/shop.php')
hero_source_path = Path('TehranSupermarket/assets/hero-v22.b64')
shop = shop_path.read_text(encoding='utf-8')

# Use only the validated crop exported from the approved Tehran Market reference.
if not hero_source_path.exists():
    raise SystemExit('validated hero source missing')
hero_b64 = re.sub(r'\s+', '', hero_source_path.read_text(encoding='utf-8'))
try:
    hero_bytes = base64.b64decode(hero_b64, validate=True)
except Exception as exc:
    raise SystemExit(f'invalid hero base64: {exc}')
if len(hero_bytes) < 8000 or not hero_bytes.startswith(b'\xff\xd8') or not hero_bytes.endswith(b'\xff\xd9'):
    raise SystemExit('hero source is not a complete JPEG')

# Remove any previously injected binary endpoint.
shop = re.sub(r'/\* TM_REF_HERO_JPEG_V2[23]_START \*/.*?/\* TM_REF_HERO_JPEG_V2[23]_END \*/\s*', '', shop, flags=re.S)

route = """/* TM_REF_HERO_JPEG_V23_START */
if (isset($_GET['tm_asset']) && $_GET['tm_asset'] === 'hero_v23') {
    $tmHero = base64_decode('""" + hero_b64 + """', true);
    if ($tmHero === false) { http_response_code(500); exit; }
    header('Content-Type: image/jpeg');
    header('Content-Length: '.strlen($tmHero));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $tmHero;
    exit;
}
/* TM_REF_HERO_JPEG_V23_END */
"""
anchor = 'declare(strict_types=1);'
if anchor not in shop:
    raise SystemExit('PHP declare anchor not found')
shop = shop.replace(anchor, anchor + '\n' + route, 1)

# Remove every older experimental mobile hero layer so broken <img> placeholders cannot survive.
patterns = [
    r'<picture class="tm-ref-mobile-picture-v20">.*?</picture>',
    r'<a class="tm-ref-mobile-hero-click-v20".*?</a>',
    r'<style id="tm-ref-mobile-v20">.*?</style>',
    r'<picture class="tm-ref-mobile-picture-v21">.*?</picture>',
    r'<a class="tm-ref-mobile-hero-click-v21".*?</a>',
    r'<div class="tm-ref-mobile-copy-v21">.*?</div>\s*</div>',
    r'<style id="tm-ref-mobile-v21">.*?</style>',
    r'<div class="tm-ref-mobile-picture-v20"[^>]*>.*?</div>',
    r'<div class="tm-ref-mobile-copy-v22">.*?</div>\s*</div>',
]
for pat in patterns:
    shop = re.sub(pat, '', shop, flags=re.S)

hero_open = re.search(r'(<section class="tm-ref-hero"[^>]*>)', shop)
if not hero_open:
    raise SystemExit('tm-ref-hero section not found')

layer = (
    '<div class="tm-ref-mobile-picture-v20" aria-hidden="true"></div>'
    '<div class="tm-ref-mobile-copy-v22">'
      '<div class="tm-ref-mobile-kicker-v22"><span>اولین فروشگاه</span><strong>مواد غذایی هوشمند</strong><span>در ایران</span></div>'
      '<p>خرید آسان با پیشنهادهای هوشمند و اسکن سریع بارکد</p>'
      '<a class="tm-ref-mobile-buy-v22" href="#products">خرید کنید ←</a>'
    '</div>'
    '<!-- data:image/jpeg;base64, intentionally not used for rendering -->'
)
shop = shop[:hero_open.end()] + layer + shop[hero_open.end():]

style = r'''<style id="tm-ref-mobile-v20">
.tm-ref-mobile-picture-v20,.tm-ref-mobile-copy-v22{display:none}
@media(max-width:800px){
  .tm-ref-head-main{grid-template-columns:44px 44px minmax(0,1fr) 44px!important;grid-template-areas:"menu cart brand account" "search search search search"!important;column-gap:8px!important;row-gap:9px!important;padding:8px 14px 10px!important}
  .tm-ref-menu-mobile{grid-area:menu!important;display:grid!important;place-items:center!important;font-size:27px!important;line-height:1!important}
  .tm-ref-head-actions{display:contents!important}
  .tm-ref-head-actions .cart{grid-area:cart!important;display:flex!important;align-items:center!important;justify-content:center!important;font-size:23px!important}
  .tm-ref-head-actions .acct{grid-area:account!important;display:flex!important;align-items:center!important;justify-content:center!important;font-size:22px!important}
  .tm-ref-brand{grid-area:brand!important;justify-self:center!important;display:flex!important;align-items:center!important;min-width:0!important;max-width:220px!important;gap:0!important}
  .tm-ref-brand-mark{display:none!important}
  .tm-ref-brand-copy{display:block!important;text-align:center!important;min-width:0!important}
  .tm-ref-brand-copy b{display:block!important;font-size:20px!important;line-height:1.05!important;white-space:nowrap!important}
  .tm-ref-brand-copy small{display:block!important;font-size:7px!important;line-height:1.35!important;white-space:nowrap!important;margin-top:4px!important;opacity:.74!important}
  .tm-ref-search{grid-area:search!important;width:100%!important;margin:0!important}

  .tm-ref-hero{position:relative!important;height:270px!important;min-height:270px!important;background:#090b0e!important;overflow:hidden!important}
  .tm-ref-hero:before,.tm-ref-hero:after{display:none!important}
  .tm-ref-hero-inner,.tm-ref-dots{display:none!important}
  .tm-ref-mobile-picture-v20{display:block!important;position:absolute!important;inset:0!important;background-image:linear-gradient(90deg,rgba(0,0,0,.72) 0%,rgba(0,0,0,.48) 30%,rgba(0,0,0,.08) 57%,rgba(0,0,0,0) 76%),url('shop.php?tm_asset=hero_v23&v=20261027-23')!important;background-size:cover!important;background-position:center center!important;background-repeat:no-repeat!important;z-index:1!important}
  .tm-ref-mobile-copy-v22{display:flex!important;position:absolute!important;z-index:3!important;left:18px!important;right:54%!important;bottom:18px!important;flex-direction:column!important;align-items:flex-end!important;gap:8px!important;color:#fff!important;text-align:right!important;direction:rtl!important}
  .tm-ref-mobile-kicker-v22{display:flex!important;flex-direction:column!important;align-items:flex-end!important;gap:0!important;font-weight:950!important;line-height:1.25!important;text-shadow:0 2px 12px rgba(0,0,0,.75)!important}
  .tm-ref-mobile-kicker-v22 span{font-size:19px!important;color:#fff!important}
  .tm-ref-mobile-kicker-v22 strong{font-size:24px!important;color:#f7c53e!important;font-weight:950!important;white-space:nowrap!important}
  .tm-ref-mobile-copy-v22 p{margin:0!important;font-size:10px!important;line-height:1.7!important;color:rgba(255,255,255,.92)!important;max-width:190px!important}
  .tm-ref-mobile-buy-v22{display:inline-flex!important;align-items:center!important;justify-content:center!important;height:38px!important;padding:0 18px!important;border-radius:999px!important;background:#f7c53e!important;color:#17120a!important;font-size:12px!important;font-weight:900!important;text-decoration:none!important;box-shadow:0 8px 22px rgba(247,197,62,.24)!important}
  .tm-ref-location-mobile{padding:7px 14px!important}
  .tm-ref-trust-wrap{margin:-1px 0 12px!important;padding:0 10px!important;position:relative!important;z-index:6!important}
}
@media(max-width:430px){
  .tm-ref-hero{height:245px!important;min-height:245px!important}
  .tm-ref-mobile-copy-v22{left:14px!important;right:49%!important;bottom:14px!important}
  .tm-ref-mobile-kicker-v22 span{font-size:16px!important}
  .tm-ref-mobile-kicker-v22 strong{font-size:20px!important}
  .tm-ref-mobile-copy-v22 p{font-size:9px!important;max-width:160px!important}
  .tm-ref-mobile-buy-v22{height:35px!important;padding:0 15px!important;font-size:11px!important}
}
</style>'''

if '</head>' not in shop:
    raise SystemExit('head marker not found')
shop = shop.replace('</head>', style + '</head>', 1)
shop_path.write_text(shop, encoding='utf-8')