from pathlib import Path

p=Path(__file__).resolve().parent/'admin-control.php'
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
    text=text.replace(needle,needle+"\nrequire_once __DIR__.'/platform-v3.php';",1)
text=func(text,'j',"function j(string $f):array{$c=&$GLOBALS['_WSA_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];return $c[$f]=ws_store_read($f);}")
text=func(text,'sj',"function sj(string $f,array $x):bool{$ok=ws_store_write($f,$x);if($ok){$c=&$GLOBALS['_WSA_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=$x;}return $ok;}")

# Keep SMS configuration on IranPayamak and add pattern settings used by finance/lifecycle.
old="'sms_enabled'=>true,'sms_sender'=>'90008361','sms_manager_mobile'=>'09024342370',\n  'sms_api_endpoint'=>'https://edge.ippanel.com/v1/api/send',\n  'sms_new_order_pattern'=>'','sms_half_reminder_pattern'=>'','sms_manager_report_pattern'=>'',"
new="'sms_enabled'=>true,'sms_sender'=>'90008361','sms_manager_mobile'=>'',\n  'sms_new_order_pattern'=>'','sms_half_reminder_pattern'=>'','sms_manager_report_pattern'=>'','sms_customer_payment_pattern'=>'','sms_payment_safety_pattern'=>'','sms_customer_won_pattern'=>'','sms_retention_pattern'=>'',"
if old in text:text=text.replace(old,new,1)
text=text.replace("'sms_api_endpoint',","")
text=text.replace("$c['sms_api_endpoint']=s('sms_api_endpoint',(string)$c['sms_api_endpoint']);\n","")

# Admin editing a representative must follow the same 4-digit PIN rule as the live portal.
text=text.replace("if(strlen($newPassword)<8)out(['ok'=>false,'message'=>'رمز جدید شعبه حداقل ۸ کاراکتر باشد.'],422);","if(!preg_match('/^\\d{4}$/',$newPassword))out(['ok'=>false,'message'=>'رمز جدید نمایندگی باید دقیقاً ۴ رقم باشد.'],422);",1)

# Add analytics to the GET response without exposing private provider responses.
needle="$recentSms=array_values(array_reverse(array_slice($sms,-40)));"
if needle not in text: raise SystemExit('analytics insertion marker missing')
analytics=r'''$analytics=['sources'=>[],'categories'=>[],'last30'=>['assigned'=>0,'handled'=>0,'won'=>0,'conversion'=>0]];$leadBy=[];foreach($leads as $l)$leadBy[(string)($l['id']??'')]=$l;$cut=time()-2592000;foreach($assignments as $a){$ts=strtotime((string)($a['created_at']??''))?:0;if($ts&&$ts<$cut)continue;if(($a['status']??'new')==='reassigned')continue;$analytics['last30']['assigned']++;$st=(string)($a['status']??'new');if($st!=='new')$analytics['last30']['handled']++;if($st==='won')$analytics['last30']['won']++;$l=$leadBy[(string)($a['lead_id']??'')]??[];$src=trim((string)($l['source']??'نامشخص'))?:'نامشخص';$cat=trim((string)($l['category']??'نامشخص'))?:'نامشخص';foreach([['sources',$src],['categories',$cat]] as $z){[$group,$key]=$z;if(!isset($analytics[$group][$key]))$analytics[$group][$key]=['assigned'=>0,'won'=>0,'handled'=>0];$analytics[$group][$key]['assigned']++;if($st!=='new')$analytics[$group][$key]['handled']++;if($st==='won')$analytics[$group][$key]['won']++;}}$analytics['last30']['conversion']=$analytics['last30']['handled']?round($analytics['last30']['won']*100/$analytics['last30']['handled'],1):0;foreach(['sources','categories'] as $group){foreach($analytics[$group] as &$r)$r['conversion']=$r['handled']?round($r['won']*100/$r['handled'],1):0;unset($r);uasort($analytics[$group],fn($a,$b)=>($b['won']<=>$a['won'])?:($b['conversion']<=>$a['conversion']));$analytics[$group]=array_slice($analytics[$group],0,20,true);}$analytics['contacts_total']=function_exists('ws_contact_get')?(int)(ws_health()['contacts']??0):0;$analytics['storage_backend']=function_exists('ws_backend')?ws_backend():'json';
'''
text=text.replace(needle,analytics+needle,1)
old_resp="'sms_sent'=>$smsSent,'sms_failed'=>$smsFailed\n  ],'manager_reports'=>$recentReports,'sms_logs'=>$recentSms]);"
new_resp="'sms_sent'=>$smsSent,'sms_failed'=>$smsFailed\n  ],'analytics'=>$analytics,'manager_reports'=>$recentReports,'sms_logs'=>$recentSms]);"
if old_resp not in text: raise SystemExit('admin response marker missing')
text=text.replace(old_resp,new_resp,1)

p.write_text(text,encoding='utf-8')
