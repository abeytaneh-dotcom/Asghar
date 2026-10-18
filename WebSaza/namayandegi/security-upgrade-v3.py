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

def func(src,name,repl):return block(src,'function '+name+'(',repl)

s=root/'_security.php';text=s.read_text(encoding='utf-8')
text=func(text,'sec_can_login',r'''function sec_can_login($d,$scope,$id){$s=sec_state($d);$a=sec_key($d,'a|'.$scope.'|'.$id);$i=sec_key($d,'i|'.sec_ip());$r=$s['accounts'][$a]??[];if(!empty($r['locked'])&&empty($r['until'])){$r['locked']=false;$r['until']=time()+900;$s['accounts'][$a]=$r;sec_save($d,$s);}if((int)($r['until']??0)>time())return false;return (int)($s['ips'][$i]['until']??0)<=time();}''')
text=func(text,'sec_fail',r'''function sec_fail($d,$scope,$id){$s=sec_state($d);$now=time();$a=sec_key($d,'a|'.$scope.'|'.$id);$r=$s['accounts'][$a]??['n'=>0,'last'=>0,'until'=>0,'locked'=>false];if($now-(int)($r['last']??0)>3600)$r=['n'=>0,'last'=>0,'until'=>0,'locked'=>false];$r['n']=(int)($r['n']??0)+1;$r['last']=$now;$r['locked']=false;if($r['n']>=5){$step=(int)floor(($r['n']-5)/2);$wait=min(3600,900*(2**min(2,$step)));$r['until']=$now+$wait;}$s['accounts'][$a]=$r;$i=sec_key($d,'i|'.sec_ip());$q=$s['ips'][$i]??['n'=>0,'last'=>0,'until'=>0];if($now-(int)($q['last']??0)>3600)$q=['n'=>0,'last'=>0,'until'=>0];$q['n']=(int)($q['n']??0)+1;$q['last']=$now;if($q['n']>=15)$q['until']=$now+3600;$s['ips'][$i]=$q;sec_save($d,$s);}''')
s.write_text(text,encoding='utf-8')

p=root/'index.php';text=p.read_text(encoding='utf-8')
helpers=r'''
function ws_admin_trust_file():string{return D.'/_admin_trusted_devices.json';}
function ws_admin_trust_cookie(string $v,int $exp):void{setcookie('WS_ADMIN_TRUST',$v,['expires'=>$exp,'path'=>'/namayandegi/','secure'=>ws_remember_secure(),'httponly'=>true,'samesite'=>'Lax']);}
function ws_admin_trust_prune(array $rows):array{$now=time();return array_values(array_filter($rows,fn($r)=>is_array($r)&&(int)($r['expires']??0)>$now&&!empty($r['selector'])&&!empty($r['hash'])));}
function ws_admin_trust_valid():bool{$raw=(string)($_COOKIE['WS_ADMIN_TRUST']??'');$parts=explode(':',$raw,2);if(count($parts)!==2)return false;[$sel,$val]=$parts;if(!preg_match('/^[a-f0-9]{18}$/',$sel)||!preg_match('/^[a-f0-9]{64}$/',$val))return false;$rows=ws_admin_trust_prune(j(ws_admin_trust_file()));foreach($rows as $r)if(hash_equals((string)$r['selector'],$sel)&&hash_equals((string)$r['hash'],hash('sha256',$val)))return true;return false;}
function ws_admin_trust_issue():void{$sel=bin2hex(random_bytes(9));$val=bin2hex(random_bytes(32));$exp=time()+7776000;$rows=ws_admin_trust_prune(j(ws_admin_trust_file()));$rows=array_slice($rows,-9);$rows[]=['selector'=>$sel,'hash'=>hash('sha256',$val),'expires'=>$exp,'created_at'=>now()];sj(ws_admin_trust_file(),$rows);ws_admin_trust_cookie($sel.':'.$val,$exp);}
function ws_admin_trust_revoke_all():void{sj(ws_admin_trust_file(),[]);ws_admin_trust_cookie('',time()-3600);}
'''
marker="function ws_admin_remember_file():string"
if marker not in text: raise SystemExit('remember helper marker missing')
text=text.replace(marker,helpers+'\n'+marker,1)

new_admin=r'''if(post('admin_login')){if(!okc())exit('درخواست نامعتبر');$a=j(AD);$remember=!empty($_POST['remember']);if(!sec_can_login(D,'admin','root')){fl('ورود موقتاً محدود شده است؛ چند دقیقه بعد یا از بازیابی رمز استفاده کنید.','err');go('./?admin=1');}$ok=hash_equals((string)($a['username']??''),trim((string)($_POST['username']??'')))&&password_verify((string)($_POST['password']??''),(string)($a['password_hash']??''));if(!$ok){sec_fail(D,'admin','root');fl('اطلاعات ورود صحیح نیست.','err');go('./?admin=1');}sec_success(D,'admin','root');if(!ws_admin_trust_valid()){$m=sec_mobile((string)($a['mobile']??''));if(!preg_match('/^09\d{9}$/',$m)||!sec_issue_code(D,'admin_device','root',$m,S)){fl('ارسال کد تأیید دستگاه ممکن نشد. از بازیابی دسترسی استفاده کنید.','err');go('./?admin=1');}$_SESSION['admin_device_pending']=1;$_SESSION['admin_device_remember']=$remember?1:0;go('./?admin=1&device_verify=1');}session_regenerate_id(true);$_SESSION['adm']=1;if($remember)ws_admin_remember_issue();else ws_admin_remember_revoke_current();go(!empty($a['must_change'])?'./?admin=1&view=settings&security=1':'./?admin=1');}'''
text=block(text,"if(post('admin_login'))",new_admin)

handler=r'''if(post('admin_device_verify')){if(!okc())exit('درخواست نامعتبر');if(empty($_SESSION['admin_device_pending'])){fl('درخواست تأیید دستگاه منقضی شده است.','err');go('./?admin=1');}$code=trim((string)($_POST['code']??''));if(!sec_verify_code(D,'admin_device','root',$code)){fl('کد تأیید معتبر نیست.','err');go('./?admin=1&device_verify=1');}$remember=!empty($_SESSION['admin_device_remember']);unset($_SESSION['admin_device_pending'],$_SESSION['admin_device_remember']);ws_admin_trust_issue();session_regenerate_id(true);$_SESSION['adm']=1;if($remember)ws_admin_remember_issue();go('./?admin=1');}
'''
needle="if(post('admin_login'))"
pos=text.find(needle)
if pos<0: raise SystemExit('admin login insertion missing')
text=text[:pos]+handler+text[pos:]

page=r'''if($isAdmin&&!admin()&&isset($_GET['device_verify'])){headx('تأیید دستگاه مدیر');echo '<main class="auth"><section class="authbox"><div class="authcard">';flash($flash);echo '<h2>تأیید دستگاه جدید</h2><p>برای امنیت مدیریت، کد ۶ رقمی ارسال‌شده به شماره مدیر را وارد کنید. این دستگاه پس از تأیید تا ۹۰ روز شناخته می‌شود.</p><form method="post" class="form">'.cf().'<input type="hidden" name="action" value="admin_device_verify"><label>کد تأیید<input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required></label><button class="btn dark">تأیید و ورود</button></form></div></section></main></body></html>';exit;}
'''
needle="if($isAdmin&&!admin()){"
if needle not in text: raise SystemExit('admin page marker missing')
text=text.replace(needle,page+needle,1)

# Changing/recovering the admin PIN invalidates trusted devices.
text=text.replace("ws_admin_remember_revoke_all();sec_unlock(D,'admin','root');","ws_admin_remember_revoke_all();ws_admin_trust_revoke_all();sec_unlock(D,'admin','root');")
text=text.replace("sec_unlock(D,'admin','root');fl('رمز مدیر تغییر کرد.');","ws_admin_remember_revoke_all();ws_admin_trust_revoke_all();sec_unlock(D,'admin','root');fl('رمز مدیر تغییر کرد.');")
p.write_text(text,encoding='utf-8')
