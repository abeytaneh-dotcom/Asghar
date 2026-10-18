from pathlib import Path

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')
old = "sec_sms_log($settingsFile,'iranpayamak_pattern',$code,$body,$from,$pattern);"
new = "sec_sms_log($settingsFile,'iranpayamak_pattern',$code,$body,$from,$pattern);@copy(dirname($settingsFile).'/sms_last_provider.json',dirname($settingsFile).'/sms_pattern_last_provider.json');"
if old not in text:
    raise SystemExit('pattern log marker missing')
text = text.replace(old,new,1)
p.write_text(text,encoding='utf-8')
