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

new_bulk=r'''if(post('add_leads')&&admin()){if(!okc())exit('درخواست نامعتبر');$raw=trim((string)($_POST['bulk']??''));$rows=j(L);ws_import_contacts($rows);$n=0;$skip=0;$ids=[];$contacts=[];foreach(preg_split('/\R/u',$raw) as $line){$line=trim($line);if(!$line)continue;$p=array_map('trim',explode('|',$line));$ph=ws_phone((string)($p[1]??''));if($ph===''||ws_contact_seen($ph)){$skip++;continue;}$id='L'.bin2hex(random_bytes(6));$score=max(0,min(100,(int)($p[16]??0)));$lead=['id'=>$id,'name'=>$p[0]??'مشتری','phone'=>$ph,'business'=>$p[2]??'','city'=>$p[3]??'','region'=>$p[4]??'','category'=>$p[5]??'','proposal'=>$p[6]??'طراحی سایت حرفه‌ای و مسیر جذب مشتری','script'=>$p[7]??'سلام وقت بخیر، از مجموعه وب ساز تماس می‌گیرم.','source'=>$p[8]??'ورودی مدیر','source_url'=>$p[9]??'','website_status'=>$p[10]??'','social_url'=>$p[11]??'','best_call_time'=>$p[12]??'','decision_maker'=>$p[13]??'','system_need'=>$p[14]??'','evidence'=>$p[15]??'','lead_score'=>$score,'active'=>true,'created_at'=>now()];$rows[]=$lead;$ids[]=$id;$contacts[]=['phone'=>$ph,'meta'=>['status'=>'ingested','lead_id'=>$id,'business'=>(string)$lead['business'],'source'=>(string)$lead['source'],'score'=>$score,'city'=>(string)$lead['city']]];$n++;}if($n&&!ws_ingest_commit(L,$rows,$contacts)){fl('ذخیره گروهی انجام نشد.','err');go('./?admin=1&view=leads');}if($n){$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];$c[L]=$rows;}$sent=0;foreach($ids as $id)if(dispatchLead($id))$sent++;fl("$n فرصت اضافه شد؛ $sent مورد توزیع شد".($skip?"؛ $skip شماره تکراری/نامعتبر برای همیشه از توزیع مجدد حذف شد.":'.'));go('./?admin=1&view=leads');}'''
text=block(text,"if(post('add_leads')&&admin())",new_bulk)
p.write_text(text,encoding='utf-8')
