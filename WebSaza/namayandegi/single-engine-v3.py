from pathlib import Path
p=Path(__file__).resolve().parent/'index.php'
text=p.read_text(encoding='utf-8')

def block(src,marker,repl):
    start=src.find(marker)
    if start<0: raise SystemExit('marker missing: '+marker)
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
                if depth==0:return src[:start]+repl+src[i+1:]
        i+=1
    raise SystemExit('unterminated '+marker)

def func(src,name,repl):return block(src,'function '+name+'(',repl)

# The browser-facing portal never mutates distribution state. smart-actions.php + cPanel cron is authoritative.
text=func(text,'systemTick',"function systemTick(bool $force=false):array{return ['expired'=>0,'assigned'=>0];} /* SINGLE_ENGINE_V3 */")

new_add=r'''if(post('add_order')&&admin()){if(!okc())exit('درخواست نامعتبر');$name=trim((string)($_POST['name']??''));$ph=ws_phone((string)($_POST['phone']??''));$business=trim((string)($_POST['business']??''));$city=trim((string)($_POST['city']??''));$region=trim((string)($_POST['region']??''));$category=trim((string)($_POST['category']??''));$proposal=trim((string)($_POST['proposal']??''));if($ph===''){fl('شماره موبایل معتبر الزامی است.','err');go('./?admin=1&view=intake');}$rows=j(L);ws_import_contacts($rows);if(ws_contact_seen($ph)){fl('این شماره قبلاً در سیستم ثبت یا تماس داده شده و دوباره توزیع نمی‌شود.','err');go('./?admin=1&view=intake');}$id='L'.bin2hex(random_bytes(6));$lead=['id'=>$id,'name'=>$name?:'مشتری','phone'=>$ph,'business'=>$business,'city'=>$city,'region'=>$region,'category'=>$category,'proposal'=>$proposal?:'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم. کسب‌وکار شما را بررسی کردیم و یک پیشنهاد مشخص برای بهتر شدن جذب مشتری داریم. اگر دو دقیقه فرصت دارید خیلی کوتاه توضیح می‌دهم.','source'=>'ثبت مرکزی','website_status'=>trim((string)($_POST['website_status']??'')),'source_url'=>trim((string)($_POST['source_url']??'')),'best_call_time'=>trim((string)($_POST['best_call_time']??'')),'decision_maker'=>trim((string)($_POST['decision_maker']??'')),'system_need'=>trim((string)($_POST['system_need']??'')),'evidence'=>trim((string)($_POST['evidence']??'')),'lead_score'=>max(0,min(100,(int)($_POST['lead_score']??0))),'active'=>true,'created_at'=>now()];$rows[]=$lead;if(!sj(L,$rows)){fl('ذخیره سفارش انجام نشد.','err');go('./?admin=1&view=intake');}ws_contact_touch($ph,['status'=>'ingested','lead_id'=>$id,'business'=>$business,'source'=>'ثبت مرکزی']);fl('سفارش ثبت شد و موتور مرکزی حداکثر تا یک دقیقه آن را بر اساس ظرفیت و امتیاز توزیع می‌کند.');go('./?admin=1&view=intake');}'''
text=block(text,"if(post('add_order')&&admin())",new_add)

new_bulk=r'''if(post('add_leads')&&admin()){if(!okc())exit('درخواست نامعتبر');$raw=trim((string)($_POST['bulk']??''));$rows=j(L);ws_import_contacts($rows);$n=0;$skip=0;foreach(preg_split('/\R/u',$raw) as $line){$line=trim($line);if(!$line)continue;$p=array_map('trim',explode('|',$line));$ph=ws_phone((string)($p[1]??''));if($ph===''||ws_contact_seen($ph)){$skip++;continue;}$id='L'.bin2hex(random_bytes(6));$score=max(0,min(100,(int)($p[16]??0)));$lead=['id'=>$id,'name'=>$p[0]??'مشتری','phone'=>$ph,'business'=>$p[2]??'','city'=>$p[3]??'','region'=>$p[4]??'','category'=>$p[5]??'','proposal'=>$p[6]??'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>$p[7]??'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم.','source'=>$p[8]??'ورودی مدیر','source_url'=>$p[9]??'','website_status'=>$p[10]??'','social_url'=>$p[11]??'','best_call_time'=>$p[12]??'','decision_maker'=>$p[13]??'','system_need'=>$p[14]??'','evidence'=>$p[15]??'','lead_score'=>$score,'active'=>true,'created_at'=>now()];$rows[]=$lead;$n++;ws_contact_touch($ph,['status'=>'ingested','lead_id'=>$id,'business'=>(string)$lead['business'],'source'=>(string)$lead['source']]);}if($n&&!sj(L,$rows)){fl('ذخیره گروهی انجام نشد.','err');go('./?admin=1&view=leads');}fl("$n فرصت اضافه شد و برای توزیع موتور مرکزی صف شد".($skip?"؛ $skip شماره تکراری/نامعتبر رد شد.":'.'));go('./?admin=1&view=leads');}'''
text=block(text,"if(post('add_leads')&&admin())",new_bulk)

new_assign=r'''if(post('assign_now')&&admin()){if(!okc())exit('درخواست نامعتبر');fl('موتور مرکزی توزیع روی سرور فعال است و حداکثر هر یک دقیقه صف را پردازش می‌کند.');go('./?admin=1');}'''
text=block(text,"if(post('assign_now')&&admin())",new_assign)

p.write_text(text,encoding='utf-8')
