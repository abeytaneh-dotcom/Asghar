from pathlib import Path
import re

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')

# Safe provider logging for IranPayamak responses.
text = re.sub(
    r"function sec_sms_log\(\$settingsFile,\$type,\$http,\$body,\$sender,\$pattern=''\)\{.*?\}\n",
    "function sec_sms_log($settingsFile,$type,$http,$body,$sender,$pattern=''){ $j=json_decode((string)$body,true);$safe=['at'=>date('c'),'type'=>$type,'http'=>(int)$http,'sender'=>$sender,'pattern'=>$pattern];if(is_array($j)){$safe['status']=$j['status']??($j['meta']['status']??null);$safe['message']=$j['messages']??($j['message']??($j['meta']['message']??''));$safe['message_code']=$j['meta']['message_code']??'';$safe['data']=$j['data']??null;$safe['errors']=$j['errors']??null;$safe['error']=$j['error']??null;}else{$safe['response']=mb_substr((string)$body,0,500);}@file_put_contents(dirname($settingsFile).'/sms_last_provider.json',json_encode($safe,JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod(dirname($settingsFile).'/sms_last_provider.json',0600);}\n",
    text,
    count=1,
    flags=re.S,
)

sms_re = re.compile(r"function sec_sms\(\$settingsFile,\$mobile,\$message\)\{.*?return true;\}\n", re.S)
if not sms_re.search(text):
    raise SystemExit('sec_sms marker missing')
new_sms = r'''function sec_sms($settingsFile,$mobile,$message){
$c=sec_read($settingsFile);if(empty($c['sms_enabled'])&&array_key_exists('sms_enabled',$c))return false;
$key=sec_sms_key($c);$m=sec_mobile($mobile);if($key===''||!preg_match('/^09\d{9}$/',$m)||!function_exists('curl_init'))return false;
$from=trim((string)($c['sms_sender']??'90008361'));if($from==='')$from='90008361';
$url=trim((string)($c['sms_simple_endpoint']??'https://api.iranpayamak.com/ws/v1/sms/simple'));
$payload=['text'=>$message,'line_number'=>$from,'recipients'=>[$m],'number_format'=>'english'];
$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Api-Key: '.$key],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_USERAGENT=>'WebSaza-SMS/1.0']);
$body=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);sec_sms_log($settingsFile,'iranpayamak_simple',$code,$body,$from);
if($code<200||$code>=300)return false;$j=json_decode($body,true);if(is_array($j)&&isset($j['status'])&&strtolower((string)$j['status'])!=='success')return false;return true;}
'''
text = sms_re.sub(lambda _m:new_sms, text, count=1)

pat_re = re.compile(r"function sec_sms_pattern\(\$settingsFile,\$mobile,\$pattern,\$params\)\{.*?return true;\}\n", re.S)
if not pat_re.search(text):
    raise SystemExit('sec_sms_pattern marker missing')
new_pat = r'''function sec_sms_pattern($settingsFile,$mobile,$pattern,$params){
$c=sec_read($settingsFile);if(empty($c['sms_enabled'])&&array_key_exists('sms_enabled',$c))return false;
$key=sec_sms_key($c);$m=sec_mobile($mobile);$pattern=trim((string)$pattern);if($key===''||!preg_match('/^09\d{9}$/',$m)||$pattern===''||!function_exists('curl_init'))return false;
$from=trim((string)($c['sms_sender']??'90008361'));if($from==='')$from='90008361';
$url=trim((string)($c['sms_pattern_endpoint']??'https://api.iranpayamak.com/ws/v1/sms/pattern'));
$payload=['code'=>$pattern,'attributes'=>(object)$params,'recipient'=>$m,'line_number'=>$from,'number_format'=>'english'];
$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Api-Key: '.$key],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_USERAGENT=>'WebSaza-SMS/1.0']);
$body=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);sec_sms_log($settingsFile,'iranpayamak_pattern',$code,$body,$from,$pattern);@copy(dirname($settingsFile).'/sms_last_provider.json',dirname($settingsFile).'/sms_pattern_last_provider.json');
if($code<200||$code>=300)return false;$j=json_decode($body,true);if(is_array($j)&&isset($j['status'])&&strtolower((string)$j['status'])!=='success')return false;return true;}
'''
text = pat_re.sub(lambda _m:new_pat, text, count=1)

# OTP/recovery must use an approved API pattern only. Do not fall back to free-text SMS,
# because IranPayamak rejects those messages for this use case and sends rejection notices.
old_fallback = "$sent=sec_sms_pattern($settingsFile,$mobile,$pattern,['code'=>$code]);if(!$sent){$sent=sec_sms($settingsFile,$mobile,'کد بازیابی وب ساز: '.$code.' | اعتبار: ۵ دقیقه');}if(!$sent){unset($s['otp'][$k]);sec_save($d,$s);return false;}return true;"
old_plain = "$sent=sec_sms_pattern($settingsFile,$mobile,$pattern,['code'=>$code]);if(!$sent){unset($s['otp'][$k]);sec_save($d,$s);return false;}return true;"
replacement = "$sent=sec_sms_pattern($settingsFile,$mobile,$pattern,['code'=>$code]);if(!$sent){unset($s['otp'][$k]);sec_save($d,$s);return false;}return true;"
if old_fallback in text:
    text=text.replace(old_fallback,replacement,1)
elif old_plain in text:
    pass
else:
    raise SystemExit('OTP send marker missing')

p.write_text(text,encoding='utf-8')
