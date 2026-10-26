from pathlib import Path

v2 = Path('TehranSupermarket/barcode_scanner_v2.js')
s = v2.read_text(encoding='utf-8')
old = "Object.keys(v).forEach(function(k){if(k!=='deviceId'&&k!=='facingMode')nv[k]=v[k];});\n    nv.facingMode=exact?{exact:'environment'}:{ideal:'environment'};"
new = "Object.keys(v).forEach(function(k){if(k!=='facingMode')nv[k]=v[k];});\n    if(!nv.deviceId)nv.facingMode=exact?{exact:'environment'}:{ideal:'environment'};"
if old in s:
    s = s.replace(old, new, 1)
elif new not in s:
    raise SystemExit('rear-camera wrapper pattern not found')
v2.write_text(s, encoding='utf-8')

idx = Path('TehranSupermarket/index.php')
s = idx.read_text(encoding='utf-8')
marker = 'barcode_scanner_v8.js?v=20261026-1'
if marker not in s:
    needle = "echo $html;"
    insert = "$barcodeV8 = '<script src=\"barcode_scanner_v8.js?v=20261026-1\"></script>';\n$html = str_replace('</body>', $barcodeV8.'</body>', $html);\n\n"
    if needle not in s:
        raise SystemExit('index echo marker not found')
    s = s.replace(needle, insert + needle, 1)
idx.write_text(s, encoding='utf-8')
