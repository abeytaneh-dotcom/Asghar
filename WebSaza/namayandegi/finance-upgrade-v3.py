from pathlib import Path

p=Path(__file__).resolve().parent/'finance.php'
text=p.read_text(encoding='utf-8')

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

def func(src,name,repl):return block(src,'function '+name+'(',repl)

if "platform-v3.php" not in text:
    needle="date_default_timezone_set('Asia/Tehran');"
    text=text.replace(needle,needle+"require_once __DIR__.'/platform-v3.php';",1)

text=func(text,'j',"function j($f){$c=&$GLOBALS['_WSF_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];return $c[$f]=ws_store_read((string)$f);}")
text=func(text,'sj',"function sj($f,$x){$ok=ws_store_write((string)$f,(array)$x);if($ok){$c=&$GLOBALS['_WSF_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=(array)$x;}return $ok;}")
text=func(text,'cfg',"function cfg(){return array_merge(['commission_percent'=>30,'support_phone'=>'','support_whatsapp'=>'','sms_api_key'=>'','sms_from_number'=>'90008361','sms_customer_payment_pattern'=>'','sms_payment_safety_pattern'=>''],ws_raw_read(S));}")

text=func(text,'sms',r'''function sms($phone,$message,$pid){$q=j(Q);$r=['id'=>'SMS'.bin2hex(random_bytes(5)),'payment_id'=>$pid,'phone'=>ws_phone((string)$phone),'message'=>$message,'status'=>'queued','provider_status'=>'','created_at'=>now(),'sent_at'=>'','error'=>''];$c=cfg();$pattern=str_starts_with((string)$pid,'NOTICE-')?trim((string)($c['sms_payment_safety_pattern']??'')):trim((string)($c['sms_customer_payment_pattern']??''));if($pattern!==''){$x=ws_sms_pattern(S,(string)$phone,$pattern,['message'=>mb_substr((string)$message,0,500)],'finance',(string)$pid);$r['provider_status']=(string)($x['status']??'');if(!empty($x['ok'])){$r['status']='sent';$r['sent_at']=now();}else{$r['status']='failed';$r['error']='provider '.(string)($x['http']??'');}}else{$r['status']='queued';$r['provider_status']='pattern_required';}$q[]=$r;if(count($q)>20000)$q=array_slice($q,-20000);sj(Q,$q);return $r['status'];}''')

needle="$l=by(L,$p['lead_id']);echo '<h2>تأیید دریافت وجه</h2><p>مبلغ <b>'.number_format((int)$p['amount']).' تومان</b> بابت <b>'.e($p['purpose']).'</b> برای '.e($l['business']??$l['name']??'سفارش شما').' ثبت شده است.</p>';"
if needle not in text: raise SystemExit('customer verify marker missing')
replace="$l=by(L,$p['lead_id']);$allp=array_values(array_filter(j(P),fn($x)=>(string)($x['lead_id']??'')===(string)$p['lead_id']&&($x['status']??'active')!=='void'));$totalPaid=0;$confirmed=0;foreach($allp as $px){$totalPaid+=(int)($px['amount']??0);if(($px['customer_status']??'')==='ok')$confirmed+=(int)($px['amount']??0);}echo '<h2>پرونده مالی مشتری</h2><div class=\"stats4\"><div><span>جمع مبالغ ثبت‌شده</span><b>'.number_format($totalPaid).' تومان</b></div><div><span>تأییدشده توسط شما</span><b>'.number_format($confirmed).' تومان</b></div><div><span>تعداد تراکنش</span><b>'.count($allp).'</b></div><div><span>وضعیت این تراکنش</span><b>'.e((string)($p['customer_status']??'pending')).'</b></div></div><p>مبلغ <b>'.number_format((int)$p['amount']).' تومان</b> بابت <b>'.e($p['purpose']).'</b> برای '.e($l['business']??$l['name']??'سفارش شما').' ثبت شده است.</p><div class=\"tbl\"><table><tr><th>عنوان</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>';foreach($allp as $px)echo '<tr><td>'.e((string)($px['purpose']??'')).'</td><td>'.number_format((int)($px['amount']??0)).' تومان</td><td>'.e((string)($px['customer_status']??'pending')).'</td><td>'.e((string)($px['created_at']??'')).'</td></tr>';echo '</table></div>';"
text=text.replace(needle,replace,1)

# Receipt responses use a safer MIME based on stored extension.
old="header('Content-Type: application/octet-stream');header('Content-Disposition: inline; filename=\"receipt\"');readfile($f);exit;"
new="$ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));$mime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'][$ext]??'application/octet-stream';header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename=\"receipt.'.$ext.'\"');readfile($f);exit;"
if old in text:text=text.replace(old,new,1)

p.write_text(text,encoding='utf-8')
