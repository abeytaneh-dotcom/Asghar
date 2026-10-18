from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')

pat = re.compile(r"if\(post\('admin_recover_request'\)\)\{.*?go\('\./\?admin=1&recover=1&step=2'\);\}", re.S)
new = r'''if(post('admin_recover_request')){if(!okc())exit('درخواست نامعتبر');$m=sec_mobile((string)($_POST['mobile']??''));$a=j(AD);$registered=sec_mobile((string)($a['mobile']??''));if($registered===''||!hash_equals($registered,$m)){fl('شماره مدیر با اطلاعات ثبت‌شده مطابقت ندارد.','err');go('./?admin=1&recover=1');}$lf=D.'/sms_last_provider.json';$before=is_file($lf)?(int)@filemtime($lf):0;$sent=sec_issue_code(D,'admin','root',$m,S);clearstatcache(true,$lf);$after=is_file($lf)?(int)@filemtime($lf):0;if(!$sent){if($after>$before){$lg=j($lf);$pm=trim((string)($lg['message']??$lg['response']??''));$pc=trim((string)($lg['message_code']??''));$detail=trim($pc.' '.$pm);fl('ارسال پیامک توسط سرویس پذیرفته نشد'.($detail!==''?' — '.$detail:''),'err');}else{fl('درخواست قبل از رسیدن به سرویس پیامک متوقف شد؛ احتمالاً محدودیت درخواست یا تنظیمات داخلی مانع ارسال شده است.','err');}go('./?admin=1&recover=1');}fl('درخواست پیامک از سمت سرور با موفقیت ثبت شد.');go('./?admin=1&recover=1&step=2');}'''
text, n = pat.subn(lambda m: new, text, count=1)
if n != 1:
    raise SystemExit('admin recovery handler marker missing')

p.write_text(text, encoding='utf-8')
