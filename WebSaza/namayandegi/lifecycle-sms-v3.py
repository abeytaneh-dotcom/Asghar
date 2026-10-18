from pathlib import Path

p=Path(__file__).resolve().parent/'index.php'
text=p.read_text(encoding='utf-8')

def block(src,marker,repl):
    start=src.find(marker)
    if start<0:return src,False
    brace=src.find('{',start);depth=0;quote=None;esc=False;i=brace
    while i<len(src):
        c=src[i]
        if quote:
            if esc:esc=False
            elif c=='\\':esc=True
            elif c==quote:quote=None
        else:
            if c in "'\"":quote=c
            elif c=='{':depth+=1
            elif c=='}':
                depth-=1
                if depth==0:return src[:start]+repl+src[i+1:],True
        i+=1
    raise SystemExit('unterminated '+marker)

ret=r'''function sendRetentionSms(string $bid,string $aid,string $lid):bool{if($lid===''||retentionSmsSent($lid))return false;$l=lead($lid);if(!$l)return false;$c=cfg();$pattern=trim((string)($c['sms_retention_pattern']??''));if($pattern===''){event($bid,$aid,$lid,'retention_sms_pending','پترن پیام حفظ ارتباط هنوز در تنظیمات ثبت نشده است.');return false;}$warm=retentionInterestedCount($lid)>=2;$attrs=['name'=>(string)($l['name']??'مشتری'),'business'=>(string)($l['business']??''),'type'=>$warm?'warm':'standard','support'=>(string)($c['support_phone']??'')];$r=ws_sms_pattern(S,(string)($l['phone']??''),$pattern,$attrs,'retention',$aid);if(empty($r['ok']))return false;event($bid,$aid,$lid,'retention_sms',$warm?'پیام حفظ ارتباط پس از دو نوبت علاقه‌مندی پذیرفته شد.':'پیام حفظ ارتباط پس از کنسلی پذیرفته شد.');return true;}'''
text,ok=block(text,'function sendRetentionSms(',ret)
if not ok:raise SystemExit('retention function missing')
won=r'''function sendWonCustomerSms(string $bid,string $aid,string $lid):bool{if($lid===''||customerLifecycleSmsSent($lid,'customer_won_sms'))return false;$l=lead($lid);if(!$l)return false;$c=cfg();$pattern=trim((string)($c['sms_customer_won_pattern']??''));if($pattern===''){event($bid,$aid,$lid,'customer_won_sms_pending','پترن پیام سفارش قطعی هنوز در تنظیمات ثبت نشده است.');return false;}$attrs=['name'=>(string)($l['name']??'مشتری'),'business'=>(string)($l['business']??''),'support'=>(string)($c['support_phone']??'')];$r=ws_sms_pattern(S,(string)($l['phone']??''),$pattern,$attrs,'customer_won',$aid);if(empty($r['ok']))return false;event($bid,$aid,$lid,'customer_won_sms','پیام تأیید شروع همکاری توسط سرویس پیامک پذیرفته شد.');return true;}'''
text,ok=block(text,'function sendWonCustomerSms(',won)
if not ok:raise SystemExit('won function missing')
p.write_text(text,encoding='utf-8')
