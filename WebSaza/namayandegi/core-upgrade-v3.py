from pathlib import Path

p=Path(__file__).resolve().parent/'index.php'
text=p.read_text(encoding='utf-8')

def block(src,marker,repl):
    start=src.find(marker)
    if start<0: raise SystemExit('marker missing: '+marker)
    brace=src.find('{',start)
    if brace<0: raise SystemExit('brace missing: '+marker)
    depth=0;quote=None;esc=False;i=brace
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
    raise SystemExit('unterminated: '+marker)

def func(src,name,repl):
    return block(src,'function '+name+'(',repl)

if "platform-v3.php" not in text:
    needle="require_once __DIR__.'/_security.php';"
    if needle not in text: raise SystemExit('security require missing')
    text=text.replace(needle,needle+"require_once __DIR__.'/platform-v3.php';",1)

text=func(text,'j',"function j(string $f):array{$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];return $c[$f]=ws_store_read($f);}")
text=func(text,'sj',"function sj(string $f,array $x):bool{$ok=ws_store_write($f,$x);if($ok){$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=$x;}return $ok;}")

text=func(text,'branchStats',r'''function branchStats(string $bid):array{$cut=time()-2592000;$assigned=0;$handled=0;$won=0;$seen=0;$speed=0;$missed=0;$follow=0;$reviews=0;foreach(j(A) as $a){if((string)($a['branch_id']??'')!==$bid)continue;$ts=strtotime((string)($a['created_at']??''))?:0;if($ts&&$ts<$cut)continue;$st=(string)($a['status']??'new');if($st==='reassigned')continue;$assigned++;if(!empty($a['seen_at'])){$seen++;$ss=strtotime((string)$a['seen_at'])?:0;if($ss&&$ts)$speed+=max(0,$ss-$ts);}if($st!=='new'){$handled++;if($st==='won')$won++;}if($st==='followup'){$follow++;$fu=strtotime((string)($a['followup_at']??''))?:0;if($fu&&$fu<time())$missed++;}if(!empty($a['manager_review']))$reviews++;}$resp=$assigned?($handled/$assigned):0.0;$conv=$handled?($won/$handled):0.0;$avg=$seen?($speed/$seen):21600;$speedScore=$avg<=1800?100:($avg<=7200?80:($avg<=21600?60:35));$followScore=$follow?max(0,100-(int)round($missed*100/$follow)):80;$score=$handled<5?50:(int)round(($conv*35)+($resp*25)+($speedScore/100*20)+($followScore/100*20));$score-=min(20,$reviews*4);$score=max(1,min(100,$score));return ['assigned'=>$assigned,'handled'=>$handled,'won'=>$won,'response_rate'=>$resp,'conversion_rate'=>$conv,'score'=>$score,'avg_first_response_seconds'=>(int)$avg,'followup_score'=>$followScore];}''')

text=func(text,'eligibleBranches',r'''function eligibleBranches(array $lead,array $exclude=[]):array{$out=[];$ex=array_fill_keys($exclude,true);foreach(j(B) as $b){$id=(string)($b['id']??'');if(!$id||empty($b['active'])||(array_key_exists('mobile_verified',$b)&&empty($b['mobile_verified']))||isset($ex[$id]))continue;$today=todayCount($id);$quota=branchQuota($b);if($today>=$quota)continue;$st=branchStats($id);$loc=ws_location_bonus($lead,$b);$load=$today/max(1,$quota);$rank=$st['score']+$loc-(int)round($load*25);$out[]=['b'=>$b,'today'=>$today,'quota'=>$quota,'score'=>$st['score'],'rank'=>$rank];}usort($out,function($x,$y){$d=$y['rank']<=>$x['rank'];if($d!==0)return $d;$d=$y['score']<=>$x['score'];return $d!==0?$d:($x['today']<=>$y['today']);});return $out;}''')
text=func(text,'daily',"function daily(string $bid):void{}")
text=func(text,'markTodaySeen',"function markTodaySeen(string $bid):void{}")

if "if(($_GET['system_tick']??'')==='1')" in text:
    text=block(text,"if(($_GET['system_tick']??'')==='1')","")
text=text.replace("\nsystemTick();\n$action=(string)","\n$action=(string)",1)

mark=r'''if(post('mark_seen')&&($seenBranch=br())){header('Content-Type: application/json; charset=utf-8');if(!okc()){http_response_code(403);echo json_encode(['ok'=>false]);exit;}$aid=(string)($_POST['assignment_id']??'');$rows=j(A);$changed=false;foreach($rows as &$r)if((string)($r['id']??'')===$aid&&(string)($r['branch_id']??'')===(string)$seenBranch['id']&&($r['current']??true)!==false){if(empty($r['seen_at'])){$r['seen_at']=now();$r['updated_at']=now();$changed=true;}break;}unset($r);if($changed){sj(A,$rows);$hit=null;foreach($rows as $r)if((string)($r['id']??'')===$aid){$hit=$r;break;}if($hit)event((string)$seenBranch['id'],$aid,(string)($hit['lead_id']??''),'seen','سفارش توسط نمایندگی باز شد');}echo json_encode(['ok'=>true,'changed'=>$changed],JSON_UNESCAPED_UNICODE);exit;}
'''
needle="$action=(string)($_POST['action']??$_GET['action']??'');"
if needle not in text: raise SystemExit('action marker missing')
text=text.replace(needle,needle+"\n"+mark,1)

new_add=r'''if(post('add_order')&&admin()){if(!okc())exit('درخواست نامعتبر');$name=trim((string)($_POST['name']??''));$ph=ws_phone((string)($_POST['phone']??''));$business=trim((string)($_POST['business']??''));$city=trim((string)($_POST['city']??''));$region=trim((string)($_POST['region']??''));$category=trim((string)($_POST['category']??''));$proposal=trim((string)($_POST['proposal']??''));if($ph===''){fl('شماره موبایل معتبر الزامی است.','err');go('./?admin=1&view=intake');}$rows=j(L);ws_import_contacts($rows);if(ws_contact_seen($ph)){fl('این شماره قبلاً در سیستم ثبت یا تماس داده شده و دوباره توزیع نمی‌شود.','err');go('./?admin=1&view=intake');}$id='L'.bin2hex(random_bytes(6));$lead=['id'=>$id,'name'=>$name?:'مشتری','phone'=>$ph,'business'=>$business,'city'=>$city,'region'=>$region,'category'=>$category,'proposal'=>$proposal?:'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم. کسب‌وکار شما را بررسی کردیم و یک پیشنهاد مشخص برای بهتر شدن جذب مشتری داریم. اگر دو دقیقه فرصت دارید خیلی کوتاه توضیح می‌دهم.','source'=>'ثبت مرکزی','website_status'=>trim((string)($_POST['website_status']??'')),'source_url'=>trim((string)($_POST['source_url']??'')),'best_call_time'=>trim((string)($_POST['best_call_time']??'')),'decision_maker'=>trim((string)($_POST['decision_maker']??'')),'system_need'=>trim((string)($_POST['system_need']??'')),'evidence'=>trim((string)($_POST['evidence']??'')),'lead_score'=>max(0,min(100,(int)($_POST['lead_score']??0))),'active'=>true,'created_at'=>now()];$rows[]=$lead;if(!sj(L,$rows)){fl('ذخیره سفارش انجام نشد.','err');go('./?admin=1&view=intake');}ws_contact_touch($ph,['status'=>'ingested','lead_id'=>$id,'business'=>$business,'source'=>'ثبت مرکزی']);$ok=dispatchLead($id);fl($ok?'سفارش ثبت و همان لحظه به مناسب‌ترین نماینده ارسال شد.':'سفارش ثبت شد؛ فعلاً ظرفیت آزاد پیدا نشد.',$ok?'ok':'err');go('./?admin=1&view=intake');}'''
text=block(text,"if(post('add_order')&&admin())",new_add)

new_bulk=r'''if(post('add_leads')&&admin()){if(!okc())exit('درخواست نامعتبر');$raw=trim((string)($_POST['bulk']??''));$rows=j(L);ws_import_contacts($rows);$n=0;$skip=0;$ids=[];foreach(preg_split('/\R/u',$raw) as $line){$line=trim($line);if(!$line)continue;$p=array_map('trim',explode('|',$line));$ph=ws_phone((string)($p[1]??''));if($ph===''||ws_contact_seen($ph)){$skip++;continue;}$id='L'.bin2hex(random_bytes(6));$score=max(0,min(100,(int)($p[16]??0)));$lead=['id'=>$id,'name'=>$p[0]??'مشتری','phone'=>$ph,'business'=>$p[2]??'','city'=>$p[3]??'','region'=>$p[4]??'','category'=>$p[5]??'','proposal'=>$p[6]??'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>$p[7]??'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم.','source'=>$p[8]??'ورودی مدیر','source_url'=>$p[9]??'','website_status'=>$p[10]??'','social_url'=>$p[11]??'','best_call_time'=>$p[12]??'','decision_maker'=>$p[13]??'','system_need'=>$p[14]??'','evidence'=>$p[15]??'','lead_score'=>$score,'active'=>true,'created_at'=>now()];$rows[]=$lead;$ids[]=$id;$n++;ws_contact_touch($ph,['status'=>'ingested','lead_id'=>$id,'business'=>(string)$lead['business'],'source'=>(string)$lead['source']]);}if($n&&!sj(L,$rows)){fl('ذخیره گروهی انجام نشد.','err');go('./?admin=1&view=leads');}$sent=0;foreach($ids as $id)if(dispatchLead($id))$sent++;fl("$n فرصت اضافه شد؛ $sent مورد توزیع شد".($skip?"؛ $skip شماره تکراری/نامعتبر برای همیشه از توزیع مجدد حذف شد.":'.'));go('./?admin=1&view=leads');}'''
text=block(text,"if(post('add_leads')&&admin())",new_bulk)

text=func(text,'guide',r'''function guide(array $l):string{$biz=e((string)($l['business']?:$l['name']??'کسب‌وکار'));$pr=e((string)($l['proposal']??'طراحی سایت و جذب مشتری'));$script=e((string)($l['script']??'سلام وقت بخیر، از وب ساز تماس می‌گیرم.'));$time=e((string)($l['best_call_time']??'زمان اداری متناسب با نوع کسب‌وکار'));$dm=e((string)($l['decision_maker']??'مدیر یا صاحب کسب‌وکار'));$site=e((string)($l['website_status']??'فاقد سایت یا نیازمند بررسی'));$need=e((string)($l['system_need']??$l['proposal']??'وب‌سایت حرفه‌ای'));$evidence=e((string)($l['evidence']??'اطلاعات عمومی کسب‌وکار و وضعیت حضور آنلاین بررسی شده است.'));$score=(int)($l['lead_score']??ws_score($l));$src=trim((string)($l['source_url']??''));$obj=$l['objections']??'';if(is_array($obj))$obj=implode(' · ',array_map('strval',$obj));$obj=e((string)$obj);$sourceHtml=$src!==''&&filter_var($src,FILTER_VALIDATE_URL)?'<a href="'.e($src).'" target="_blank" rel="noopener noreferrer">مشاهده منبع عمومی</a>':'';return '<div class="guide"><div><b>CRM اختصاصی این مشتری · امتیاز '.$score.'/100</b><p>وضعیت سایت: '.$site.'<br>بهترین زمان تماس: '.$time.'<br>فرد مناسب برای گفتگو: '.$dm.'</p>'.$sourceHtml.'</div><div><b>چرا این مشتری مناسب است؟</b><p>'.$evidence.'</p></div><div><b>شروع تماس</b><p>'.$script.'</p></div><div><b>نیاز احتمالی</b><p>'.$need.'</p></div><div><b>پیشنهاد دقیق</b><p>برای '.$biz.' روی '.$pr.' تمرکز کن؛ مزیت را با نتیجه کسب‌وکاری توضیح بده، نه فقط امکانات فنی.</p></div>'.($obj!==''?'<div><b>اعتراض‌های محتمل</b><p>'.$obj.'</p></div>':'').'<div><b>کشف نیاز</b><p>بپرس: «الان مشتری‌ها بیشتر از چه طریقی شما را پیدا می‌کنند؟» «کدام محصول یا خدمت برایتان سودآورتر است؟» «ثبت سفارش/رزرو/پیگیری مشتری الان چطور انجام می‌شود؟»</p></div><div><b>پایان تماس</b><p>یک اقدام بعدی مشخص کن؛ ارسال نمونه، زمان تماس بعدی یا شروع پروژه. نتیجه را همان لحظه ثبت کن.</p></div></div>';}''')

text=text.replace("<article class=\"lead\" id=\"lead-'.$id.'\">","<article class=\"lead\" id=\"lead-'.$id.'\" data-assignment-id=\"'.$id.'\">",1)
text=text.replace("$c=cfg();if($b&&$view==='leads')markTodaySeen((string)$b['id']);$flash=popfl();","$c=cfg();$flash=popfl();",1)
text=text.replace('فرصت فروش متناسب با منطقه؛','فرصت فروش آماده تماس؛')
text=text.replace('سفارش‌ها بر اساس شهر، منطقه، ظرفیت و عملکرد به‌صورت هوشمند توزیع می‌شوند.','سفارش‌ها بر اساس ظرفیت، عملکرد و کیفیت پیگیری در سراسر ایران هوشمند توزیع می‌شوند.')
text=text.replace('توزیع منطقه‌ای','توزیع سراسری')
text=text.replace('توزیع هوشمند بر اساس منطقه، ظرفیت و امتیاز واقعی نماینده.','توزیع هوشمند سراسری بر اساس ظرفیت، امتیاز واقعی و کیفیت پیگیری نماینده.')

p.write_text(text,encoding='utf-8')
