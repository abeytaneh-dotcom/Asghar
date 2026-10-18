from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')

# Representative PIN policy: exactly 4 numeric digits. Admin password policy remains strong.
if "strlen($p)<10" not in text:
    raise SystemExit('registration PIN marker missing')
text = text.replace("strlen($p)<10", "!preg_match('/^\\d{4}$/',$p)", 1)
text = text.replace("اطلاعات ثبت‌نام معتبر نیست.", "اطلاعات ثبت‌نام معتبر نیست. رمز باید دقیقاً ۴ رقم باشد.", 1)

# Branch recovery and branch password change (admin recovery remains 12+ chars).
if text.count("strlen($new)<10") < 2:
    raise SystemExit('branch PIN reset markers missing')
text = text.replace("strlen($new)<10", "!preg_match('/^\\d{4}$/',$new)")
text = text.replace("رمز فعلی اشتباه است یا رمز جدید کوتاه است.", "رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۴ رقم باشد.", 1)

# Registration UI.
old = '<label>رمز عبور<input type="password" name="password" minlength="8" required></label>'
new = '<label>رمز ورود ۴ رقمی<input type="password" name="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required></label>'
if old not in text:
    raise SystemExit('registration password UI marker missing')
text = text.replace(old, new, 1)

# Recovery UI for representative only. Admin form has minlength=12 and is intentionally untouched.
old = '<label>رمز جدید<input type="password" name="new_password" minlength="10" required></label>'
new = '<label>رمز جدید ۴ رقمی<input type="password" name="new_password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required></label>'
if old not in text:
    raise SystemExit('branch recovery UI marker missing')
text = text.replace(old, new, 1)

# Profile password UI may be legacy minlength=8. Restrict only the representative's "new" field.
text = text.replace('<input type="password" name="new" minlength="8" required>', '<input type="password" name="new" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required>', 1)

# Persistent remember-me tokens. Cookie stores selector + random validator; only a hash is stored server-side.
helpers = r'''
function ws_remember_file():string{return D.'/_remember_tokens.json';}
function ws_remember_secure():bool{return !empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';}
function ws_remember_cookie(string $value,int $expires):void{setcookie('WS_REMEMBER',$value,['expires'=>$expires,'path'=>'/namayandegi/','secure'=>ws_remember_secure(),'httponly'=>true,'samesite'=>'Lax']);}
function ws_remember_clear_cookie():void{ws_remember_cookie('',time()-3600);unset($_COOKIE['WS_REMEMBER']);}
function ws_remember_prune(array $rows):array{$now=time();$out=[];foreach($rows as $r)if(is_array($r)&&(int)($r['expires']??0)>$now&&!empty($r['selector'])&&!empty($r['hash'])&&!empty($r['branch_id']))$out[]=$r;return array_slice($out,-500);}
function ws_remember_revoke_branch(string $bid):void{$f=ws_remember_file();$rows=ws_remember_prune(j($f));$rows=array_values(array_filter($rows,fn($r)=>(string)($r['branch_id']??'')!==$bid));sj($f,$rows);ws_remember_clear_cookie();}
function ws_remember_revoke_current():void{$raw=(string)($_COOKIE['WS_REMEMBER']??'');$parts=explode(':',$raw,2);$selector=$parts[0]??'';$f=ws_remember_file();$rows=ws_remember_prune(j($f));if($selector!=='')$rows=array_values(array_filter($rows,fn($r)=>!hash_equals((string)($r['selector']??''),$selector)));sj($f,$rows);ws_remember_clear_cookie();}
function ws_remember_issue(string $bid):void{$selector=bin2hex(random_bytes(9));$validator=bin2hex(random_bytes(32));$expires=time()+2592000;$f=ws_remember_file();$rows=ws_remember_prune(j($f));$mine=[];$other=[];foreach($rows as $r){if((string)($r['branch_id']??'')===$bid)$mine[]=$r;else $other[]=$r;}$mine=array_slice($mine,-4);$mine[]=['selector'=>$selector,'hash'=>hash('sha256',$validator),'branch_id'=>$bid,'expires'=>$expires,'created_at'=>now()];sj($f,array_merge($other,$mine));ws_remember_cookie($selector.':'.$validator,$expires);}
function ws_remember_try():void{if(!empty($_SESSION['bid']))return;$raw=(string)($_COOKIE['WS_REMEMBER']??'');if($raw==='')return;$parts=explode(':',$raw,2);if(count($parts)!==2){ws_remember_clear_cookie();return;}[$selector,$validator]=$parts;if(!preg_match('/^[a-f0-9]{18}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){ws_remember_clear_cookie();return;}$f=ws_remember_file();$rows=ws_remember_prune(j($f));$hit=null;foreach($rows as $i=>$r)if(hash_equals((string)($r['selector']??''),$selector)){$hit=$i;break;}if($hit===null||!hash_equals((string)($rows[$hit]['hash']??''),hash('sha256',$validator))){ws_remember_clear_cookie();return;}$bid=(string)($rows[$hit]['branch_id']??'');$branch=null;foreach(j(B) as $r)if((string)($r['id']??'')===$bid&&!empty($r['active'])&&(!array_key_exists('mobile_verified',$r)||!empty($r['mobile_verified']))){$branch=$r;break;}if(!$branch){ws_remember_revoke_current();return;}array_splice($rows,$hit,1);sj($f,$rows);session_regenerate_id(true);$_SESSION['bid']=$bid;ws_remember_issue($bid);}
'''
marker = "if(($_GET['system_tick']??'')==='1'){"
if marker not in text:
    raise SystemExit('remember helper insertion marker missing')
text = text.replace(marker, helpers + "\n" + marker, 1)

# Replace secured representative login handler produced by build/security patches.
login_re = re.compile(r"if\(post\('login'\)\)\{.*?go\('\./\?mode=login'\);\}", re.S)
m = login_re.search(text)
if not m:
    raise SystemExit('secured branch login handler missing')
new_login = r'''if(post('login')){if(!okc())exit('درخواست نامعتبر');$u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');$remember=!empty($_POST['remember']);$rows=j(B);$idx=null;foreach($rows as $i=>$r)if((($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])&&(!array_key_exists('mobile_verified',$r)||!empty($r['mobile_verified']))){$idx=$i;break;}$sid=$idx!==null?(string)($rows[$idx]['id']??''):('unknown:'.hash('sha256',$u));if(!sec_can_login(D,'branch',$sid)){fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}if($idx!==null&&password_verify($p,(string)($rows[$idx]['password_hash']??''))){sec_success(D,'branch',$sid);$rows[$idx]['last_login_at']=now();sj(B,$rows);session_regenerate_id(true);$_SESSION['bid']=$rows[$idx]['id'];if($remember)ws_remember_issue((string)$rows[$idx]['id']);else ws_remember_revoke_current();systemTick(true);go(!empty($rows[$idx]['must_change_password'])?'./?view=profile&security=1':'./');}sec_fail(D,'branch',$sid);fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}'''
text = text[:m.start()] + new_login + text[m.end():]

# Logout explicitly revokes the current remembered browser.
old_logout = "if($action==='logout'){unset($_SESSION['bid']);session_regenerate_id(true);go('./');}"
new_logout = "if($action==='logout'){ws_remember_revoke_current();unset($_SESSION['bid']);session_regenerate_id(true);go('./');}"
if old_logout not in text:
    raise SystemExit('logout marker missing')
text = text.replace(old_logout,new_logout,1)

# Revoke remembered sessions when representative changes or resets the PIN.
text = text.replace("sec_unlock(D,'branch',(string)$rows[$idx]['id']);fl('رمز جدید ثبت شد.');", "sec_unlock(D,'branch',(string)$rows[$idx]['id']);ws_remember_revoke_branch((string)$rows[$idx]['id']);fl('رمز جدید ثبت شد.');", 1)
text = text.replace("sec_unlock(D,'branch',(string)$b['id']);fl('رمز عبور تغییر کرد.');", "sec_unlock(D,'branch',(string)$b['id']);ws_remember_revoke_branch((string)$b['id']);fl('رمز عبور تغییر کرد.');", 1)

# Try persistent sign-in before resolving current representative.
state_marker = "$isAdmin=isset($_GET['admin']);$b=br();"
if state_marker not in text:
    raise SystemExit('auth state marker missing')
text = text.replace(state_marker, "ws_remember_try();" + state_marker, 1)

# Add visible Remember me checkbox to representative login only.
old_form = '<label>رمز عبور<input type="password" name="password" required></label><button class="btn dark">ورود به پنل</button></form>'
new_form = '<label>رمز ورود<input type="password" name="password" inputmode="numeric" autocomplete="current-password" required></label><label class="remember"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label><button class="btn dark">ورود به پنل</button></form>'
if old_form not in text:
    raise SystemExit('login form marker missing')
text = text.replace(old_form,new_form,1)

p.write_text(text,encoding='utf-8')
