from pathlib import Path
import re

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')

pat = re.compile(r"function sec_sms_log\(\$settingsFile,\$type,\$http,\$body,\$sender,\$pattern=''\)\{.*?\}\n", re.S)
new = r'''function sec_sms_log($settingsFile,$type,$http,$body,$sender,$pattern=''){ $j=json_decode((string)$body,true);$safe=['at'=>date('c'),'type'=>$type,'http'=>(int)$http,'sender'=>$sender,'pattern'=>$pattern];if(is_array($j)){$safe['status']=$j['meta']['status']??$j['status']??null;$safe['message']=$j['meta']['message']??$j['message']??$j['error']??'';$safe['message_code']=$j['meta']['message_code']??$j['message_code']??$j['code']??'';$safe['response']=mb_substr((string)$body,0,1200);}else{$safe['response']=mb_substr((string)$body,0,1200);}@file_put_contents(dirname($settingsFile).'/sms_last_provider.json',json_encode($safe,JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod(dirname($settingsFile).'/sms_last_provider.json',0600);}
'''
text, n = pat.subn(lambda m: new, text, count=1)
if n != 1:
    raise SystemExit('sec_sms_log marker missing')

p.write_text(text, encoding='utf-8')
