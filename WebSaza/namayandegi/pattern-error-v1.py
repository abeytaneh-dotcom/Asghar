from pathlib import Path

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')
old = "$safe['data']=$j['data']??null;"
new = "$safe['data']=$j['data']??null;$safe['errors']=$j['errors']??null;$safe['error']=$j['error']??null;"
if old not in text:
    raise SystemExit('provider logger marker missing')
text = text.replace(old,new,1)
p.write_text(text,encoding='utf-8')
