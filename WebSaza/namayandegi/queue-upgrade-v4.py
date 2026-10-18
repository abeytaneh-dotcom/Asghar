from pathlib import Path

root=Path(__file__).resolve().parent

def block(src,marker,repl):
    start=src.find(marker)
    if start<0: raise SystemExit('marker missing: '+marker)
    brace=src.find('{',start);depth=0;quote=None;esc=False;i=brace
    while i<len(src):
        c=src[i]
        if quote:
            if esc: esc=False
            elif c=='\\': esc=True
            elif c==quote: quote=None
        else:
            if c in "'\"": quote=c
            elif c=='{': depth+=1
            elif c=='}':
                depth-=1
                if depth==0:return src[:start]+repl+src[i+1:]
        i+=1
    raise SystemExit('unterminated '+marker)

def func(src,name,repl): return block(src,'function '+name+'(',repl)

p=root/'finance.php'; text=p.read_text(encoding='utf-8')
text=func(text,'sms',r'''function sms($phone,$message,$pid){$c=cfg();$isSafety=str_contains((string)$message,'پیش از پرداخت')||str_contains((string)$message,'خارج از هماهنگی رسمی');$pattern=$isSafety?trim((string)($c['sms_payment_safety_pattern']??'')):trim((string)($c['sms_customer_payment_pattern']??''));if($pattern==='')return 'pattern_required';$x=ws_sms_pattern(S,(string)$phone,$pattern,['message'=>mb_substr((string)$message,0,500)],$isSafety?'payment_safety':'finance_payment',(string)$pid);return !empty($x['ok'])?'queued':'failed';}''')
p.write_text(text,encoding='utf-8')
