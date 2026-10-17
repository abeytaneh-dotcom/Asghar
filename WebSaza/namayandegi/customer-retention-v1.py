from pathlib import Path

root = Path(__file__).resolve().parent
p = root / 'index.php'
text = p.read_text(encoding='utf-8')

# Add a small retention layer that sends one respectful follow-up SMS when a lead
# is finally cancelled. Customers with two or more prior interested events get a
# warmer message acknowledging the previous conversations.
marker = "function referredBranches(string $bid):array{$me=branchBy($bid);if(!$me)return [];$code=(string)($me['code']??'');return array_values(array_filter(j(B),fn($x)=>(string)($x['referrer_id']??'')===$bid||($code!==''&&(string)($x['referrer_code']??'')===$code)));}"
if marker not in text:
    raise SystemExit('retention function insertion marker missing')

retention_functions = r'''
function retentionSmsSent(string $lid):bool{foreach(j(EV) as $x)if((string)($x['lead_id']??'')===$lid&&(string)($x['status']??'')==='retention_sms')return true;return false;}
function retentionInterestedCount(string $lid):int{$n=0;foreach(j(EV) as $x)if((string)($x['lead_id']??'')===$lid&&(string)($x['status']??'')==='interested')$n++;return $n;}
function sendRetentionSms(string $bid,string $aid,string $lid):bool{if($lid===''||retentionSmsSent($lid))return false;$l=lead($lid);if(!$l)return false;$mobile=sec_mobile((string)($l['phone']??''));if(!preg_match('/^09\d{9}$/',$mobile))return false;$warm=retentionInterestedCount($lid)>=2;if($warm){$msg="سلام، از مجموعه وب ساز چند نوبت درباره طراحی سایت با شما در ارتباط بودیم. فعلاً مزاحم‌تان نمی‌شویم. اگر هر زمان در آینده تصمیم به طراحی سایت، فروشگاه اینترنتی یا توسعه حضور آنلاین گرفتید، خوشحال می‌شویم در خدمت شما باشیم.\nوب ساز";}else{$msg="سلام، از مجموعه وب ساز بابت طراحی سایت با شما در ارتباط بودیم. فعلاً مزاحم‌تان نمی‌شویم. هر زمان برای طراحی سایت یا توسعه حضور آنلاین کسب‌وکارتان تصمیم گرفتید، با افتخار در خدمت شما هستیم.\nوب ساز";}$support=trim((string)(cfg()['support_phone']??''));if($support!=='')$msg.="\nتماس: ".$support;if(!sec_sms(S,$mobile,$msg))return false;event($bid,$aid,$lid,'retention_sms',$warm?'پیام حفظ ارتباط پس از دو نوبت علاقه‌مندی ارسال شد.':'پیام حفظ ارتباط پس از کنسلی ارسال شد.');return true;}
'''
text = text.replace(marker, marker + "\n" + retention_functions, 1)

old = "sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);$rw=0;if($st==='won')$rw=awardReferral((string)$b['id'],$aid,$lid,$dv);systemTick(true);"
new = "sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);if($st==='cancelled')sendRetentionSms((string)$b['id'],$aid,$lid);$rw=0;if($st==='won')$rw=awardReferral((string)$b['id'],$aid,$lid,$dv);systemTick(true);"
if old not in text:
    raise SystemExit('assignment update marker missing')
text = text.replace(old, new, 1)

p.write_text(text, encoding='utf-8')
