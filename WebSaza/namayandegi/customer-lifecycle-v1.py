from pathlib import Path

root = Path(__file__).resolve().parent

# 1) Customer thank-you SMS immediately after an order becomes final/won.
p = root / 'index.php'
text = p.read_text(encoding='utf-8')
marker = "function referredBranches(string $bid):array{$me=branchBy($bid);if(!$me)return [];$code=(string)($me['code']??'');return array_values(array_filter(j(B),fn($x)=>(string)($x['referrer_id']??'')===$bid||($code!==''&&(string)($x['referrer_code']??'')===$code)));}"
if marker not in text:
    raise SystemExit('customer lifecycle insertion marker missing')

functions = r'''
function customerLifecycleSmsSent(string $lid,string $status):bool{foreach(j(EV) as $x)if((string)($x['lead_id']??'')===$lid&&(string)($x['status']??'')===$status)return true;return false;}
function sendWonCustomerSms(string $bid,string $aid,string $lid):bool{if($lid===''||customerLifecycleSmsSent($lid,'customer_won_sms'))return false;$l=lead($lid);if(!$l)return false;$mobile=sec_mobile((string)($l['phone']??''));if(!preg_match('/^09\d{9}$/',$mobile))return false;$msg="مشتری گرامی، از اعتماد و انتخاب مجموعه وب ساز سپاسگزاریم. سفارش طراحی سایت شما با موفقیت قطعی شد و مراحل اجرای پروژه آغاز می‌شود. در تمام مراحل در کنار شما هستیم.\nوب ساز";$support=trim((string)(cfg()['support_phone']??''));if($support!=='')$msg.="\nپشتیبانی: ".$support;if(!sec_sms(S,$mobile,$msg))return false;event($bid,$aid,$lid,'customer_won_sms','پیام تشکر و تأیید شروع همکاری برای مشتری ارسال شد.');return true;}
'''
text = text.replace(marker, marker + "\n" + functions, 1)
old = "sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);if($st==='cancelled')sendRetentionSms((string)$b['id'],$aid,$lid);$rw=0;if($st==='won')$rw=awardReferral((string)$b['id'],$aid,$lid,$dv);systemTick(true);"
new = "sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);if($st==='cancelled')sendRetentionSms((string)$b['id'],$aid,$lid);if($st==='won')sendWonCustomerSms((string)$b['id'],$aid,$lid);$rw=0;if($st==='won')$rw=awardReferral((string)$b['id'],$aid,$lid,$dv);systemTick(true);"
if old not in text:
    raise SystemExit('won customer sms marker missing')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')

# 2) Explicit "sample approved / ready to pay" step in the representative finance panel.
f = root / 'finance.php'
fin = f.read_text(encoding='utf-8')
action_marker = "if($act==='add_payment'&&$bid){"
if action_marker not in fin:
    raise SystemExit('finance action marker missing')

handler = r'''if($act==='sample_approved'&&$bid){$aid=(string)($_POST['assignment_id']??'');$aa=j(A);$idx=null;foreach($aa as $i=>$a)if((string)($a['id']??'')===$aid&&(string)($a['branch_id']??'')===$bid&&($a['status']??'')==='won'){$idx=$i;break;}if($idx===null){fl('سفارش قطعی معتبر پیدا نشد.','err');go('finance.php');}if(!empty($aa[$idx]['payment_notice_created_at'])){fl('پیام شرایط پرداخت قبلاً برای این مشتری ثبت و ارسال شده است.');go('finance.php');}$lid=(string)($aa[$idx]['lead_id']??'');$lead=by(L,$lid);if(!$lead){fl('اطلاعات مشتری پیدا نشد.','err');go('finance.php');}$aa[$idx]['sample_approved_at']=$aa[$idx]['sample_approved_at']??now();$msg="مشتری گرامی، نمونه اولیه سفارش شما مورد تأیید قرار گرفته است. لطفاً پیش از پرداخت هرگونه وجه، مبلغ و روش پرداخت را فقط با شرکت وب ساز هماهنگ و تأیید کنید. از هرگونه واریز خارج از هماهنگی رسمی شرکت خودداری فرمایید.\nوب ساز";$support=trim((string)(cfg()['support_phone']??''));if($support!=='')$msg.="\nپشتیبانی: ".$support;$st=sms((string)($lead['phone']??''),$msg,'NOTICE-'.$aid);if(in_array($st,['sent','queued'],true)){$aa[$idx]['payment_notice_created_at']=now();$aa[$idx]['payment_notice_status']=$st;sj(A,$aa);fl($st==='sent'?'نمونه تأیید شد و پیام رسمی شرایط پرداخت برای مشتری ارسال شد.':'نمونه تأیید شد و پیام شرایط پرداخت در صف ارسال ثبت شد.');}else{sj(A,$aa);fl('نمونه تأیید شد اما ارسال پیام شرایط پرداخت ناموفق بود؛ دوباره تلاش کنید.','err');}go('finance.php');}
'''
fin = fin.replace(action_marker, handler + action_marker, 1)

ui_marker = "echo '<div class=\"stats4\"><div><span>کل دریافتی مشتری</span>"
if ui_marker not in fin:
    raise SystemExit('finance branch ui marker missing')
ui = r'''echo '<section class="box"><h2>نمونه تأیید شد / آماده پرداخت</h2><p class="mut">بعد از تأیید نمونه اولیه و قبل از دریافت هر وجه، این مرحله را ثبت کنید تا پیام رسمی شرایط پرداخت برای مشتری ارسال شود.</p><form method="post" class="form">'.cf().'<input type="hidden" name="action" value="sample_approved"><label>سفارش قطعی<select name="assignment_id" required><option value="">انتخاب سفارش</option>';foreach($won as $a){$l=by(L,$a['lead_id']);$done=!empty($a['payment_notice_created_at']);echo '<option value="'.e($a['id']).'"'.($done?' disabled':'').'>'.e($l['business']??$l['name']??'مشتری').($done?' · پیام ارسال شده':'').'</option>';}echo '</select></label><button class="btn dark">نمونه تأیید شد؛ اعلام شرایط پرداخت</button></form></section>';echo '<div class="stats4"><div><span>کل دریافتی مشتری</span>'''
fin = fin.replace(ui_marker, ui, 1)
f.write_text(fin, encoding='utf-8')
