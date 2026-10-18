from pathlib import Path

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')
old = "$sent=sec_sms_pattern($settingsFile,$mobile,$pattern,['code'=>$code]);if(!$sent){unset($s['otp'][$k]);sec_save($d,$s);return false;}return true;"
new = "$sent=sec_sms_pattern($settingsFile,$mobile,$pattern,['code'=>$code]);if(!$sent){$sent=sec_sms($settingsFile,$mobile,'کد بازیابی وب ساز: '.$code.' | اعتبار: ۵ دقیقه');}if(!$sent){unset($s['otp'][$k]);sec_save($d,$s);return false;}return true;"
if old not in text:
    raise SystemExit('otp send marker missing')
text = text.replace(old,new,1)
p.write_text(text,encoding='utf-8')
