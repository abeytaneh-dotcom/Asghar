from pathlib import Path

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')

# All user-facing passwords become exactly 4 numeric digits.
if "strlen($p)<10" not in text:
    raise SystemExit('registration PIN marker missing')
text = text.replace("strlen($p)<10", "!preg_match('/^\\d{4}$/',$p)", 1)
text = text.replace("اطلاعات ثبت‌نام معتبر نیست.", "اطلاعات ثبت‌نام معتبر نیست. رمز باید دقیقاً ۴ رقم باشد.", 1)

if text.count("strlen($new)<10") < 2:
    raise SystemExit('branch PIN reset markers missing')
text = text.replace("strlen($new)<10", "!preg_match('/^\\d{4}$/',$new)")
text = text.replace("رمز فعلی اشتباه است یا رمز جدید کوتاه است.", "رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۴ رقم باشد.", 1)

if "strlen($new)<12" not in text or "strlen($p)<12" not in text:
    raise SystemExit('admin PIN markers missing')
text = text.replace("strlen($new)<12", "!preg_match('/^\\d{4}$/',$new)", 1)
text = text.replace("strlen($p)<12", "!preg_match('/^\\d{4}$/',$p)", 1)
text = text.replace("رمز جدید باید حداقل ۱۲ کاراکتر باشد.", "رمز مدیر باید دقیقاً ۴ رقم باشد.", 1)

# Registration UI.
old = '<label>رمز عبور<input type="password" name="password" minlength="8" required></label>'
new = '<label>رمز ورود ۴ رقمی<input type="password" name="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required></label>'
if old not in text:
    raise SystemExit('registration password UI marker missing')
text = text.replace(old, new, 1)

# Branch recovery UI.
old = '<label>رمز جدید<input type="password" name="new_password" minlength="10" required></label>'
new = '<label>رمز جدید ۴ رقمی<input type="password" name="new_password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required></label>'
if old not in text:
    raise SystemExit('branch recovery UI marker missing')
text = text.replace(old, new, 1)

# Admin recovery UI.
old = '<label>رمز جدید<input type="password" name="new_password" minlength="12" required></label>'
new = '<label>رمز جدید ۴ رقمی<input type="password" name="new_password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required></label>'
if old not in text:
    raise SystemExit('admin recovery UI marker missing')
text = text.replace(old, new, 1)

# Profile/admin settings password fields.
text = text.replace('<input type="password" name="new" minlength="8" required>', '<input type="password" name="new" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required>', 1)
text = text.replace('<input type="password" name="password" minlength="10" required>', '<input type="password" name="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required>', 1)

# Persistent remember-me tokens. Browser receives a random token; server stores only its SHA-256 hash.
helpers = r'''
function ws_remember_secure():bool{return !empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';}
function ws_remember_file():string{return D.'/_remember_tokens.json';}
function ws_remember_cookie(string $value,int $expires):void{setcookie('WS_REMEMBER',$value,['expires'=>$expires,'path'=>'/namayandegi/','secure'=>ws_remember_secure(),'httponly'=>true,'samesite'=>'Lax']);}
function ws_remember_clear_cookie():void{ws_remember_cookie('',time()-3600);unset($_COOKIE['WS_REMEMBER']);}
function ws_remember_prune(array $rows):array{$now=time();$out=[];foreach($rows as $r)if(is_array($r)&&(int)($r['expires']??0)>$now&&!empty($r['selector'])&&!empty($r['hash'])&&!empty($r['branch_id']))$out[]=$r;return array_slice($out,-500);}
function ws_remember_revoke_branch(string $bid):void{$f=ws_remember_file();$rows=ws_remember_prune(j($f));$rows=array_values(array_filter($rows,fn($r)=>(string)($r['branch_id']??'')!==$bid));sj($f,$rows);ws_remember_clear_cookie();}
function ws_remember_revoke_current():void{$raw=(string)($_COOKIE['WS_REMEMBER']??'');$parts=explode(':',$raw,2);$selector=$parts[0]??'';$f=ws_remember_file();$rows=ws_remember_prune(j($f));if($selector!=='')$rows=array_values(array_filter($rows,fn($r)=>!hash_equals((string)($r['selector']??''),$selector)));sj($f,$rows);ws_remember_clear_cookie();}
function ws_remember_issue(string $bid):void{$selector=bin2hex(random_bytes(9));$validator=bin2hex(random_bytes(32));$expires=time()+2592000;$f=ws_remember_file();$rows=ws_remember_prune(j($f));$mine=[];$other=[];foreach($rows as $r){if((string)($r['branch_id']??'')===$bid)$mine[]=$r;else $other[]=$r;}$mine=array_slice($mine,-4);$mine[]=['selector'=>$selector,'hash'=>hash('sha256',$validator),'branch_id'=>$bid,'expires'=>$expires,'created_at'=>now()];sj($f,array_merge($other,$mine));ws_remember_cookie($selector.':'.$validator,$expires);}
function ws_remember_try():void{if(!empty($_SESSION['bid']))return;$raw=(string)($_COOKIE['WS_REMEMBER']??'');if($raw==='')return;$parts=explode(':',$raw,2);if(count($parts)!==2){ws_remember_clear_cookie();return;}[$selector,$validator]=$parts;if(!preg_match('/^[a-f0-9]{18}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){ws_remember_clear_cookie();return;}$f=ws_remember_file();$rows=ws_remember_prune(j($f));$hit=null;foreach($rows as $i=>$r)if(hash_equals((string)($r['selector']??''),$selector)){$hit=$i;break;}if($hit===null||!hash_equals((string)($rows[$hit]['hash']??''),hash('sha256',$validator))){ws_remember_clear_cookie();return;}$bid=(string)($rows[$hit]['branch_id']??'');$branch=null;foreach(j(B) as $r)if((string)($r['id']??'')===$bid&&!empty($r['active'])&&(!array_key_exists('mobile_verified',$r)||!empty($r['mobile_verified']))){$branch=$r;break;}if(!$branch){ws_remember_revoke_current();return;}array_splice($rows,$hit,1);sj($f,$rows);session_regenerate_id(true);$_SESSION['bid']=$bid;ws_remember_issue($bid);}

function ws_admin_remember_file():string{return D.'/_admin_remember_tokens.json';}
function ws_admin_remember_cookie(string $value,int $expires):void{setcookie('WS_ADMIN_REMEMBER',$value,['expires'=>$expires,'path'=>'/namayandegi/','secure'=>ws_remember_secure(),'httponly'=>true,'samesite'=>'Lax']);}
function ws_admin_remember_clear_cookie():void{ws_admin_remember_cookie('',time()-3600);unset($_COOKIE['WS_ADMIN_REMEMBER']);}
function ws_admin_remember_prune(array $rows):array{$now=time();$out=[];foreach($rows as $r)if(is_array($r)&&(int)($r['expires']??0)>$now&&!empty($r['selector'])&&!empty($r['hash']))$out[]=$r;return array_slice($out,-10);}
function ws_admin_remember_revoke_all():void{sj(ws_admin_remember_file(),[]);ws_admin_remember_clear_cookie();}
function ws_admin_remember_revoke_current():void{$raw=(string)($_COOKIE['WS_ADMIN_REMEMBER']??'');$parts=explode(':',$raw,2);$selector=$parts[0]??'';$f=ws_admin_remember_file();$rows=ws_admin_remember_prune(j($f));if($selector!=='')$rows=array_values(array_filter($rows,fn($r)=>!hash_equals((string)($r['selector']??''),$selector)));sj($f,$rows);ws_admin_remember_clear_cookie();}
function ws_admin_remember_issue():void{$selector=bin2hex(random_bytes(9));$validator=bin2hex(random_bytes(32));$expires=time()+2592000;$f=ws_admin_remember_file();$rows=ws_admin_remember_prune(j($f));$rows=array_slice($rows,-4);$rows[]=['selector'=>$selector,'hash'=>hash('sha256',$validator),'expires'=>$expires,'created_at'=>now()];sj($f,$rows);ws_admin_remember_cookie($selector.':'.$validator,$expires);}
function ws_admin_remember_try():void{if(!isset($_GET['admin'])||!empty($_SESSION['adm']))return;$raw=(string)($_COOKIE['WS_ADMIN_REMEMBER']??'');if($raw==='')return;$parts=explode(':',$raw,2);if(count($parts)!==2){ws_admin_remember_clear_cookie();return;}[$selector,$validator]=$parts;if(!preg_match('/^[a-f0-9]{18}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){ws_admin_remember_clear_cookie();return;}$a=j(AD);if(!$a||!empty($a['must_change'])){ws_admin_remember_revoke_all();return;}$f=ws_admin_remember_file();$rows=ws_admin_remember_prune(j($f));$hit=null;foreach($rows as $i=>$r)if(hash_equals((string)($r['selector']??''),$selector)){$hit=$i;break;}if($hit===null||!hash_equals((string)($rows[$hit]['hash']??''),hash('sha256',$validator))){ws_admin_remember_clear_cookie();return;}array_splice($rows,$hit,1);sj($f,$rows);session_regenerate_id(true);$_SESSION['adm']=1;ws_admin_remember_issue();}
'''
marker = "if(($_GET['system_tick']??'')==='1'){"
if marker not in text:
    raise SystemExit('remember helper insertion marker missing')
text = text.replace(marker, helpers + "\n" + marker, 1)

# Find and replace a complete PHP if-block safely, including nested braces and quoted strings.
def replace_php_block(src: str, marker: str, replacement: str) -> str:
    start = src.find(marker)
    if start < 0:
        raise SystemExit(f'block marker missing: {marker}')
    brace = src.find('{', start)
    if brace < 0:
        raise SystemExit('opening brace missing')
    depth = 0
    quote = None
    escaped = False
    i = brace
    while i < len(src):
        ch = src[i]
        if quote is not None:
            if escaped:
                escaped = False
            elif ch == '\\':
                escaped = True
            elif ch == quote:
                quote = None
        else:
            if ch in ("'", '"'):
                quote = ch
            elif ch == '{':
                depth += 1
            elif ch == '}':
                depth -= 1
                if depth == 0:
                    return src[:start] + replacement + src[i+1:]
        i += 1
    raise SystemExit('unterminated PHP block')

new_login = r'''if(post('login')){if(!okc())exit('درخواست نامعتبر');$u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');$remember=!empty($_POST['remember']);$rows=j(B);$idx=null;foreach($rows as $i=>$r)if((($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])&&(!array_key_exists('mobile_verified',$r)||!empty($r['mobile_verified']))){$idx=$i;break;}$sid=$idx!==null?(string)($rows[$idx]['id']??''):('unknown:'.hash('sha256',$u));if(!sec_can_login(D,'branch',$sid)){fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}if($idx!==null&&password_verify($p,(string)($rows[$idx]['password_hash']??''))){sec_success(D,'branch',$sid);$rows[$idx]['last_login_at']=now();sj(B,$rows);session_regenerate_id(true);$_SESSION['bid']=$rows[$idx]['id'];if($remember)ws_remember_issue((string)$rows[$idx]['id']);else ws_remember_revoke_current();systemTick(true);go(!empty($rows[$idx]['must_change_password'])?'./?view=profile&security=1':'./');}sec_fail(D,'branch',$sid);fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}'''
text = replace_php_block(text, "if(post('login')){", new_login)

new_admin_login = r'''if(post('admin_login')){if(!okc())exit('درخواست نامعتبر');$a=j(AD);$remember=!empty($_POST['remember']);if(!sec_can_login(D,'admin','root')){fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?admin=1');}$ok=hash_equals((string)($a['username']??''),trim((string)($_POST['username']??'')))&&password_verify((string)($_POST['password']??''),(string)($a['password_hash']??''));if($ok){sec_success(D,'admin','root');session_regenerate_id(true);$_SESSION['adm']=1;if($remember)ws_admin_remember_issue();else ws_admin_remember_revoke_current();go(!empty($a['must_change'])?'./?admin=1&view=settings&security=1':'./?admin=1');}sec_fail(D,'admin','root');fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?admin=1');}'''
text = replace_php_block(text, "if(post('admin_login')){", new_admin_login)

# Logout explicitly revokes remembered browser token.
old_logout = "if($action==='logout'){unset($_SESSION['bid']);session_regenerate_id(true);go('./');}"
new_logout = "if($action==='logout'){ws_remember_revoke_current();unset($_SESSION['bid']);session_regenerate_id(true);go('./');}"
if old_logout not in text:
    raise SystemExit('logout marker missing')
text = text.replace(old_logout,new_logout,1)
old_admin_logout = "if($action==='admin_logout'){unset($_SESSION['adm']);session_regenerate_id(true);go('./?admin=1');}"
new_admin_logout = "if($action==='admin_logout'){ws_admin_remember_revoke_current();unset($_SESSION['adm']);session_regenerate_id(true);go('./?admin=1');}"
if old_admin_logout not in text:
    raise SystemExit('admin logout marker missing')
text = text.replace(old_admin_logout,new_admin_logout,1)

# Revoke remembered sessions when password/PIN changes or is recovered.
text = text.replace("sec_unlock(D,'branch',(string)$rows[$idx]['id']);fl('رمز جدید ثبت شد.');", "sec_unlock(D,'branch',(string)$rows[$idx]['id']);ws_remember_revoke_branch((string)$rows[$idx]['id']);fl('رمز جدید ثبت شد.');", 1)
text = text.replace("sec_unlock(D,'branch',(string)$b['id']);fl('رمز عبور تغییر کرد.');", "sec_unlock(D,'branch',(string)$b['id']);ws_remember_revoke_branch((string)$b['id']);fl('رمز عبور تغییر کرد.');", 1)
text = text.replace("sec_unlock(D,'admin','root');fl('رمز جدید ثبت شد.');", "sec_unlock(D,'admin','root');ws_admin_remember_revoke_all();fl('رمز جدید ثبت شد.');", 1)
text = text.replace("sec_unlock(D,'admin','root');fl('رمز مدیر تغییر کرد.');", "sec_unlock(D,'admin','root');ws_admin_remember_revoke_all();fl('رمز مدیر تغییر کرد.');", 1)

# Try persistent sign-in before resolving current user/admin state.
state_marker = "$isAdmin=isset($_GET['admin']);$b=br();"
if state_marker not in text:
    raise SystemExit('auth state marker missing')
text = text.replace(state_marker, "ws_remember_try();ws_admin_remember_try();" + state_marker, 1)

# Add visible Remember me checkbox to representative login.
old_form = '<label>رمز عبور<input type="password" name="password" required></label><button class="btn dark">ورود به پنل</button></form>'
new_form = '<label>رمز ورود<input type="password" name="password" autocomplete="current-password" required></label><label class="remember"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label><button class="btn dark">ورود به پنل</button></form>'
if old_form not in text:
    raise SystemExit('branch login form marker missing')
text = text.replace(old_form,new_form,1)

# Add visible Remember me checkbox to admin login.
old_admin_form = '<label>رمز عبور<input type="password" name="password" required></label><button class="btn dark">ورود</button></form>'
new_admin_form = '<label>رمز ورود<input type="password" name="password" autocomplete="current-password" required></label><label class="remember"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label><button class="btn dark">ورود</button></form>'
if old_admin_form not in text:
    raise SystemExit('admin login form marker missing')
text = text.replace(old_admin_form,new_admin_form,1)

p.write_text(text,encoding='utf-8')
