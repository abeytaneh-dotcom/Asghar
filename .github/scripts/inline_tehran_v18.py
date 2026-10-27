from pathlib import Path
import re

shop = Path('TehranSupermarket/shop.php')
cssp = Path('TehranSupermarket/assets/tehran-final-v16.css')
s = shop.read_text(encoding='utf-8')
css = cssp.read_text(encoding='utf-8')

# Inline CSS is resolved from /tehran/, not /tehran/assets/.
css = css.replace("url('tehran-storefront-v3.svg?v=20261027-ref17')", "url('assets/tehran-storefront-v3.svg?v=20261027-ref18')")
css = css.replace('url("tehran-storefront-v3.svg?v=20261027-ref17")', 'url("assets/tehran-storefront-v3.svg?v=20261027-ref18")')

cleanup = '''
/* v18 hard cleanup */
#barcode-scan-fab,#tehran-ai-fab-v2,.tehran-ai-ping{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important}
body{visibility:visible!important;opacity:1!important}
.tm-ref-header,.tm-ref-hero,.tm-ref-trust-wrap,.tm-ref-section,.tm-ref-promos,.tm-ref-specials{visibility:visible!important;opacity:1!important}
'''
inline = '<style id="tm-ref-inline-v18">\n' + css + '\n' + cleanup + '\n</style>'

s = re.sub(r'<style id="tm-ref-inline-v\d+">.*?</style>', '', s, flags=re.S)
s = re.sub(r'<link rel="stylesheet" href="assets/tehran-final-v16\.css\?v=[^"\']+">', '', s)
s = re.sub(r'<link href="assets/tehran-final-v16\.css\?v=[^"\']+" rel="stylesheet">', '', s)
if '</head>' not in s:
    raise SystemExit('head marker missing')
s = s.replace('</head>', inline + '</head>', 1)
shop.write_text(s, encoding='utf-8')
