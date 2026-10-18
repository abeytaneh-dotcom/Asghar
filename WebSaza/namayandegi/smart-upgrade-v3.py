from pathlib import Path

p=Path(__file__).resolve().parent/'smart-actions.php'
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

text=func(text,'jx',"function jx(string $f):array{$c=&$GLOBALS['_WSJX_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];return $c[$f]=ws_store_read($f);}")
text=func(text,'sjx',"function sjx(string $f,array $x):bool{$ok=ws_store_write($f,$x);if($ok){$c=&$GLOBALS['_WSJX_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=$x;}return $ok;}")
text=func(text,'cfgx',"function cfgx():array{return array_merge(['daily_quota'=>20,'commission_percent'=>30,'urgent_commission_percent'=>40,'special_interested_commission'=>40,'unseen_reassign_hours'=>24,'normal_reassign_hours'=>24,'urgent_reassign_hours'=>2,'sms_sender'=>'90008361','sms_manager_mobile'=>'','sms_enabled'=>true],jx(S));}")

text=func(text,'statsx',r'''function statsx(string $bid):array{$cut=time()-2592000;$assigned=0;$handled=0;$won=0;$seen=0;$speed=0;$follow=0;$missed=0;$reviews=0;foreach(jx(A) as $a){if((string)($a['branch_id']??'')!==$bid)continue;$ts=strtotime((string)($a['created_at']??''))?:0;if($ts&&$ts<$cut)continue;$st=(string)($a['status']??'new');if($st==='reassigned')continue;$assigned++;if(!empty($a['seen_at'])){$seen++;$ss=strtotime((string)$a['seen_at'])?:0;if($ss&&$ts)$speed+=max(0,$ss-$ts);}if($st!=='new'){$handled++;if($st==='won')$won++;}if($st==='followup'){$follow++;$fu=strtotime((string)($a['followup_at']??''))?:0;if($fu&&$fu<time())$missed++;}if(!empty($a['manager_review']))$reviews++;}$resp=$assigned?($handled/$assigned):0.0;$conv=$handled?($won/$handled):0.0;$avg=$seen?($speed/$seen):21600;$speedScore=$avg<=1800?100:($avg<=7200?80:($avg<=21600?60:35));$followScore=$follow?max(0,100-(int)round($missed*100/$follow)):80;$score=$handled<5?50:(int)round(($conv*35)+($resp*25)+($speedScore/100*20)+($followScore/100*20));$score-=min(20,$reviews*4);return ['score'=>max(1,min(100,$score)),'handled'=>$handled,'won'=>$won,'response_rate'=>$resp,'conversion_rate'=>$conv,'avg_first_response_seconds'=>(int)$avg,'followup_score'=>$followScore];}''')

text=func(text,'candidatesx',r'''function candidatesx(array $lead,array $exclude,bool $exact=true):array{$ex=array_fill_keys($exclude,true);$out=[];foreach(jx(B) as $b){$id=(string)($b['id']??'');if(!$id||empty($b['active'])||(array_key_exists('mobile_verified',$b)&&empty($b['mobile_verified']))||isset($ex[$id]))continue;$today=todayx($id);$quota=quotax($b);if($today>=$quota)continue;$s=statsx($id);$loc=ws_location_bonus($lead,$b);$load=$today/max(1,$quota);$rank=$s['score']+$loc-(int)round($load*25);$out[]=['b'=>$b,'score'=>$s['score'],'today'=>$today,'rank'=>$rank];}usort($out,function($x,$y){$d=$y['rank']<=>$x['rank'];if($d!==0)return $d;$d=$y['score']<=>$x['score'];return $d!==0?$d:($x['today']<=>$y['today']);});return $out;}''')
text=func(text,'durationx',"function durationx(string $priority):int{$c=cfgx();$h=$priority==='urgent'?(int)($c['urgent_reassign_hours']??2):(int)($c['normal_reassign_hours']??24);return max(1,$h)*3600;}")
text=func(text,'commissionx',"function commissionx(string $priority):int{$c=cfgx();$v=$priority==='urgent'?(int)($c['urgent_commission_percent']??40):(int)($c['commission_percent']??30);return max(0,min(100,$v));}")
text=func(text,'ensureTimerSettingsx',r'''function ensureTimerSettingsx():void{$c=jx(S);$changed=false;$defaults=['unseen_reassign_hours'=>24,'urgent_reassign_hours'=>2,'normal_reassign_hours'=>24,'commission_percent'=>30,'urgent_commission_percent'=>40,'sms_sender'=>'90008361','sms_manager_mobile'=>'','sms_enabled'=>true];foreach($defaults as $k=>$v)if(!array_key_exists($k,$c)){$c[$k]=$v;$changed=true;}if($changed)sjx(S,$c);}''')

text=func(text,'smsSendx',r'''function smsSendx(string $mobile,string $message,string $kind,string $aid='',string $lid='',array $patternParams=[]):bool{$c=cfgx();if(empty($c['sms_enabled']))return false;$pattern='';if($kind==='new_order')$pattern=trim((string)($c['sms_new_order_pattern']??''));elseif($kind==='half_reminder')$pattern=trim((string)($c['sms_half_reminder_pattern']??''));elseif($kind==='manager_report')$pattern=trim((string)($c['sms_manager_report_pattern']??''));if($pattern===''){smsLogx($kind,$mobile,'pattern_missing',$message,$aid,$lid,'');return false;}$r=ws_sms_pattern(S,$mobile,$pattern,$patternParams,$kind,$aid?:$lid);smsLogx($kind,$mobile,!empty($r['ok'])?'accepted_pattern':'pattern_failed',$message,$aid,$lid,json_encode($r,JSON_UNESCAPED_UNICODE));return !empty($r['ok']);}''')

new_create=r'''if($action==='create_timed_order'&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(empty($_SESSION['adm']))jsonx(['ok'=>false,'message'=>'unauthorized'],401);if(!csrfOkx())jsonx(['ok'=>false,'message'=>'درخواست نامعتبر'],403);ensureTimerSettingsx();
    $name=trim((string)($_POST['name']??''));$ph=ws_phone((string)($_POST['phone']??''));$business=trim((string)($_POST['business']??''));$city=trim((string)($_POST['city']??''));$region=trim((string)($_POST['region']??''));$category=trim((string)($_POST['category']??''));$proposal=trim((string)($_POST['proposal']??''));$priority=((string)($_POST['priority']??'normal'))==='urgent'?'urgent':'normal';
    if($ph==='')jsonx(['ok'=>false,'message'=>'شماره موبایل معتبر الزامی است.'],422);$leads=jx(L);ws_import_contacts($leads);if(ws_contact_seen($ph))jsonx(['ok'=>false,'message'=>'این شماره قبلاً در سیستم ثبت یا تماس داده شده و دوباره توزیع نمی‌شود.'],409);
    $id='L'.bin2hex(random_bytes(6));$lead=['id'=>$id,'name'=>$name?:'مشتری','phone'=>$ph,'business'=>$business,'city'=>$city,'region'=>$region,'category'=>$category,'proposal'=>$proposal?:'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم. کسب‌وکار شما را بررسی کردیم و یک پیشنهاد مشخص برای بهتر شدن جذب مشتری داریم. اگر دو دقیقه فرصت دارید خیلی کوتاه توضیح می‌دهم.','source'=>'ثبت مرکزی','website_status'=>trim((string)($_POST['website_status']??'')),'source_url'=>trim((string)($_POST['source_url']??'')),'best_call_time'=>trim((string)($_POST['best_call_time']??'')),'decision_maker'=>trim((string)($_POST['decision_maker']??'')),'system_need'=>trim((string)($_POST['system_need']??'')),'evidence'=>trim((string)($_POST['evidence']??'')),'lead_score'=>max(0,min(100,(int)($_POST['lead_score']??0))),'priority'=>$priority,'active'=>true,'created_at'=>nx()];$leads[]=$lead;if(!sjx(L,$leads))jsonx(['ok'=>false,'message'=>'ذخیره سفارش ناموفق بود.'],500);ws_contact_touch($ph,['status'=>'ingested','lead_id'=>$id,'business'=>$business,'source'=>'ثبت مرکزی']);eventx('','',$id,'created','سفارش '.($priority==='urgent'?'فوری':'معمولی').' ثبت شد.');
    $new=dispatchTimedx($id);$hours=(int)(durationx($priority)/3600);$comm=commissionx($priority);$msg=$new?('سفارش '.($priority==='urgent'?'فوری':'معمولی').' با مهلت '.$hours.' ساعت و سهم '.$comm.'٪ ثبت و ارسال شد.'):'سفارش ثبت شد؛ فعلاً نماینده دارای ظرفیت پیدا نشد.';flx($msg,$new?'ok':'err');jsonx(['ok'=>true,'assigned'=>(bool)$new,'message'=>$msg,'lead_id'=>$id]);
}'''
text=block(text,"if($action==='create_timed_order'&&$_SERVER['REQUEST_METHOD']==='POST')",new_create)

old="$rows[$idx]['status']=$st;$rows[$idx]['note']=$note;$rows[$idx]['followup_at']=$fu;$rows[$idx]['project_type']=$pt;$rows[$idx]['deal_value']=$dv;$rows[$idx]['seen_at']=$rows[$idx]['seen_at']?:nx();$rows[$idx]['updated_at']=nx();sjx(A,$rows);eventx($bid,$aid,$lid,$st,$note);$rw=0;"
new="$rows[$idx]['status']=$st;$rows[$idx]['note']=$note;$rows[$idx]['followup_at']=$fu;$rows[$idx]['project_type']=$pt;$rows[$idx]['deal_value']=$dv;$rows[$idx]['seen_at']=$rows[$idx]['seen_at']?:nx();$rows[$idx]['updated_at']=nx();sjx(A,$rows);eventx($bid,$aid,$lid,$st,$note);$leadNow=leadx($lid);if($leadNow)ws_contact_touch((string)($leadNow['phone']??''),['status'=>$st,'lead_id'=>$lid,'business'=>(string)($leadNow['business']??$leadNow['name']??''),'source'=>(string)($leadNow['source']??'')]);$rw=0;"
if old not in text: raise SystemExit('assignment result marker missing')
text=text.replace(old,new,1)

text=text.replace("'sms_manager_mobile'=>'09024342370'","'sms_manager_mobile'=>''")
text=text.replace("'sms_api_endpoint'=>'https://edge.ippanel.com/v1/api/send',","")

p.write_text(text,encoding='utf-8')
