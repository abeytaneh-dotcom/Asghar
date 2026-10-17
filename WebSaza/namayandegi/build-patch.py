from pathlib import Path
import os,re

root=Path(__file__).resolve().parent
v=os.environ.get('ASSET_VERSION','dev')

def rep(text,old,new,label):
    if old not in text:
        raise SystemExit(f'{label} marker missing')
    return text.replace(old,new,1)

p=root/'index.php'
text=p.read_text(encoding='utf-8')
text=re.sub(r'style\.css\?v=[^"\']+',f'style.css?v={v}',text)
text=re.sub(r'app\.js\?v=[^"\']+',f'app.js?v={v}',text)

old_j="function j(string $f):array{$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}"
new_j="function j(string $f):array{$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];$x=json_decode((string)@file_get_contents($f),true);return $c[$f]=is_array($x)?$x:[];}"
old_sj="function sj(string $f,array $x):bool{$l=fopen($f.'.lock','c');if(!$l||!flock($l,LOCK_EX))return false;try{$t=$f.'.tmp.'.bin2hex(random_bytes(3));$q=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($q===false||file_put_contents($t,$q,LOCK_EX)===false)return false;@chmod($t,0640);return rename($t,$f);}finally{flock($l,LOCK_UN);fclose($l);}}"
new_sj="function sj(string $f,array $x):bool{$l=fopen($f.'.lock','c');if(!$l||!flock($l,LOCK_EX))return false;try{$t=$f.'.tmp.'.bin2hex(random_bytes(3));$q=json_encode($x,JSON_UNESCAPED_UNICODE);if($q===false||file_put_contents($t,$q,LOCK_EX)===false)return false;@chmod($t,0640);$ok=rename($t,$f);if($ok){$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=$x;}return $ok;}finally{flock($l,LOCK_UN);fclose($l);}}"
text=rep(text,old_j,new_j,'index read cache')
text=rep(text,old_sj,new_sj,'index compact writes')

old_tick="function systemTick(bool $force=false):array{if(!$force&&is_file(T)){ $last=(int)@file_get_contents(T); if($last>time()-300)return ['expired'=>0,'assigned'=>0]; }$x=recycleExpired();$a=fillQuotas();@file_put_contents(T,(string)time(),LOCK_EX);return ['expired'=>$x,'assigned'=>$a];}"
new_tick="function compactStore():void{$stamp=D.'/last_compact.txt';$last=is_file($stamp)?(int)@file_get_contents($stamp):0;if($last>time()-86400)return;foreach([B,L,A,EV,R] as $f){clearstatcache(true,$f);if(is_file($f)&&(int)@filesize($f)>262144){$rows=j($f);sj($f,$rows);}}@file_put_contents($stamp,(string)time(),LOCK_EX);} function systemTick(bool $force=false):array{if(!$force&&is_file(T)){ $last=(int)@file_get_contents(T); if($last>time()-300)return ['expired'=>0,'assigned'=>0]; }compactStore();$x=recycleExpired();$a=fillQuotas();@file_put_contents(T,(string)time(),LOCK_EX);return ['expired'=>$x,'assigned'=>$a];}"
text=rep(text,old_tick,new_tick,'system tick')

old_admin_init="if(!is_file(AD))@file_put_contents(AD,json_encode(['username'=>'admin','password_hash'=>password_hash('123456',PASSWORD_DEFAULT),'must_change'=>true],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));"
new_admin_init="if(!is_file(AD))@file_put_contents(AD,json_encode(['username'=>'admin','password_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'must_change'=>true],JSON_UNESCAPED_UNICODE));\nrequire_once __DIR__.'/_security.php';sec_headers();sec_harden_admin(AD,S,D);"
text=rep(text,old_admin_init,new_admin_init,'admin bootstrap')

old_login="if(post('login')){if(!okc())exit('درخواست نامعتبر');$u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');$rows=j(B);foreach($rows as &$r)if((($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])&&password_verify($p,(string)($r['password_hash']??''))){$r['last_login_at']=now();sj(B,$rows);session_regenerate_id(true);$_SESSION['bid']=$r['id'];systemTick(true);go('./');}unset($r);fl('شماره/کد یا رمز عبور صحیح نیست.','err');go('./?mode=login');}"
new_login="if(post('login')){if(!okc())exit('درخواست نامعتبر');$u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');$rows=j(B);$idx=null;foreach($rows as $i=>$r)if((($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])){$idx=$i;break;}$sid=$idx!==null?(string)($rows[$idx]['id']??''):('unknown:'.hash('sha256',$u));if(!sec_can_login(D,'branch',$sid)){fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}if($idx!==null&&password_verify($p,(string)($rows[$idx]['password_hash']??''))){sec_success(D,'branch',$sid);$rows[$idx]['last_login_at']=now();sj(B,$rows);session_regenerate_id(true);$_SESSION['bid']=$rows[$idx]['id'];systemTick(true);go(!empty($rows[$idx]['must_change_password'])?'./?view=profile&security=1':'./');}sec_fail(D,'branch',$sid);fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?mode=login');}"
text=rep(text,old_login,new_login,'branch login')

old_admin="if(post('admin_login')){if(!okc())exit('درخواست نامعتبر');$a=j(AD);if(hash_equals((string)($a['username']??''),trim((string)($_POST['username']??'')))&&password_verify((string)($_POST['password']??''),(string)($a['password_hash']??''))){session_regenerate_id(true);$_SESSION['adm']=1;go('./?admin=1');}fl('ورود مدیر ناموفق بود.','err');go('./?admin=1');}"
new_admin="if(post('admin_login')){if(!okc())exit('درخواست نامعتبر');$a=j(AD);if(!sec_can_login(D,'admin','root')){fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?admin=1');}$ok=hash_equals((string)($a['username']??''),trim((string)($_POST['username']??'')))&&password_verify((string)($_POST['password']??''),(string)($a['password_hash']??''));if($ok){sec_success(D,'admin','root');session_regenerate_id(true);$_SESSION['adm']=1;go(!empty($a['must_change'])?'./?admin=1&view=settings&security=1':'./?admin=1');}sec_fail(D,'admin','root');fl('اطلاعات ورود صحیح نیست یا دسترسی غیرفعال است.','err');go('./?admin=1');}"
text=rep(text,old_admin,new_admin,'admin login')

recover_handlers=(
"if(post('recover_request')){if(!okc())exit('درخواست نامعتبر');$m=sec_mobile((string)($_POST['mobile']??''));$rows=j(B);$found=null;foreach($rows as $r)if(sec_mobile((string)($r['mobile']??''))===$m&&!empty($r['active'])){$found=$r;break;}if($found)sec_issue_code(D,'branch',(string)$found['id'],$m,S);fl('اگر شماره در سیستم ثبت شده باشد، کد بازیابی ارسال می‌شود.');go('./?mode=reset');}\n"
"if(post('recover_finish')){if(!okc())exit('درخواست نامعتبر');$m=sec_mobile((string)($_POST['mobile']??''));$code=trim((string)($_POST['code']??''));$new=(string)($_POST['new_password']??'');$rows=j(B);$idx=null;foreach($rows as $i=>$r)if(sec_mobile((string)($r['mobile']??''))===$m&&!empty($r['active'])){$idx=$i;break;}if($idx===null||strlen($new)<10||!sec_verify_code(D,'branch',(string)$rows[$idx]['id'],$code)){fl('اطلاعات بازیابی معتبر نیست.','err');go('./?mode=reset');}$rows[$idx]['password_hash']=password_hash($new,PASSWORD_DEFAULT);$rows[$idx]['must_change_password']=false;sj(B,$rows);sec_unlock(D,'branch',(string)$rows[$idx]['id']);fl('رمز جدید ثبت شد.');go('./?mode=login');}\n"
"if(post('admin_recover_request')){if(!okc())exit('درخواست نامعتبر');$m=sec_mobile((string)($_POST['mobile']??''));$a=j(AD);$registered=sec_mobile((string)($a['mobile']??''));if($registered!==''&&hash_equals($registered,$m))sec_issue_code(D,'admin','root',$m,S);fl('اگر شماره معتبر باشد، کد بازیابی ارسال می‌شود.');go('./?admin=1&recover=1&step=2');}\n"
"if(post('admin_recover_finish')){if(!okc())exit('درخواست نامعتبر');$m=sec_mobile((string)($_POST['mobile']??''));$code=trim((string)($_POST['code']??''));$new=(string)($_POST['new_password']??'');$a=j(AD);$registered=sec_mobile((string)($a['mobile']??''));if($registered===''||!hash_equals($registered,$m)||strlen($new)<12||!sec_verify_code(D,'admin','root',$code)){fl('اطلاعات بازیابی معتبر نیست.','err');go('./?admin=1&recover=1&step=2');}$a['password_hash']=password_hash($new,PASSWORD_DEFAULT);$a['must_change']=false;sj(AD,$a);sec_unlock(D,'admin','root');fl('رمز جدید ثبت شد.');go('./?admin=1');}\n"
)
text=text.replace(new_login,recover_handlers+new_login,1)

old_branch_pw="if(post('branch_password')&&($b=br())){if(!okc())exit('درخواست نامعتبر');$old=(string)($_POST['old']??'');$new=(string)($_POST['new']??'');if(!password_verify($old,(string)$b['password_hash'])||strlen($new)<8){fl('رمز فعلی اشتباه است یا رمز جدید کوتاه است.','err');go('./?view=profile');}$rows=j(B);foreach($rows as &$r)if(($r['id']??'')===$b['id']){$r['password_hash']=password_hash($new,PASSWORD_DEFAULT);break;}unset($r);sj(B,$rows);fl('رمز عبور تغییر کرد.');go('./?view=profile');}"
new_branch_pw="if(post('branch_password')&&($b=br())){if(!okc())exit('درخواست نامعتبر');$old=(string)($_POST['old']??'');$new=(string)($_POST['new']??'');if(!password_verify($old,(string)$b['password_hash'])||strlen($new)<10){fl('رمز فعلی اشتباه است یا رمز جدید کوتاه است.','err');go('./?view=profile');}$rows=j(B);foreach($rows as &$r)if(($r['id']??'')===$b['id']){$r['password_hash']=password_hash($new,PASSWORD_DEFAULT);$r['must_change_password']=false;break;}unset($r);sj(B,$rows);sec_unlock(D,'branch',(string)$b['id']);fl('رمز عبور تغییر کرد.');go('./?view=profile');}"
text=rep(text,old_branch_pw,new_branch_pw,'branch password')

old_admin_pw="if(post('admin_password')&&admin()){if(!okc())exit('درخواست نامعتبر');$p=(string)($_POST['password']??'');if(strlen($p)<10){fl('رمز مدیر حداقل ۱۰ کاراکتر باشد.','err');go('./?admin=1&view=settings');}$a=j(AD);$a['password_hash']=password_hash($p,PASSWORD_DEFAULT);$a['must_change']=false;sj(AD,$a);fl('رمز مدیر تغییر کرد.');go('./?admin=1&view=settings');}"
new_admin_pw="if(post('admin_password')&&admin()){if(!okc())exit('درخواست نامعتبر');$p=(string)($_POST['password']??'');if(strlen($p)<12){fl('رمز جدید باید حداقل ۱۲ کاراکتر باشد.','err');go('./?admin=1&view=settings');}$a=j(AD);$a['password_hash']=password_hash($p,PASSWORD_DEFAULT);$a['must_change']=false;sj(AD,$a);sec_unlock(D,'admin','root');fl('رمز مدیر تغییر کرد.');go('./?admin=1&view=settings');}"
text=rep(text,old_admin_pw,new_admin_pw,'admin password')

admin_link="echo '<a class=\"adminlink\" href=\"?admin=1\">ورود مدیر سیستم</a></div></section></main></body></html>';"
if admin_link in text:text=text.replace(admin_link,"echo '</div></section></main></body></html>';",1)
text=text.replace("<button class=\"btn dark\">ورود به پنل</button></form>'","<button class=\"btn dark\">ورود به پنل</button></form><a class=\"adminlink\" href=\"?mode=recover\">بازیابی رمز عبور</a>'",1)

recover_page=(
"if(!$b&&!$isAdmin&&in_array($mode,['recover','reset'],true)){headx('بازیابی ورود');echo '<main class=\"auth\"><section class=\"authbox\"><div class=\"authcard\">';flash($flash);if($mode==='recover')echo '<h2>بازیابی رمز عبور</h2><form method=\"post\" class=\"form\">'.cf().'<input type=\"hidden\" name=\"action\" value=\"recover_request\"><label>شماره موبایل<input name=\"mobile\" inputmode=\"numeric\" required></label><button class=\"btn dark\">ارسال کد بازیابی</button></form>';else echo '<h2>ثبت رمز جدید</h2><form method=\"post\" class=\"form\">'.cf().'<input type=\"hidden\" name=\"action\" value=\"recover_finish\"><label>شماره موبایل<input name=\"mobile\" inputmode=\"numeric\" required></label><label>کد بازیابی<input name=\"code\" inputmode=\"numeric\" maxlength=\"6\" required></label><label>رمز جدید<input type=\"password\" name=\"new_password\" minlength=\"10\" required></label><button class=\"btn dark\">ثبت رمز جدید</button></form>';echo '<a class=\"adminlink\" href=\"./?mode=login\">بازگشت</a></div></section></main></body></html>';exit;}\n"
)
marker="if(!$b&&!$isAdmin){headx('ورود و ثبت‌نام نمایندگی');"
if marker not in text:raise SystemExit('public render marker missing')
text=text.replace(marker,recover_page+marker,1)

admin_recover=(
"if($isAdmin&&!admin()&&($_GET['recover']??'')==='1'){headx('بازیابی دسترسی');echo '<main class=\"auth\"><section class=\"authbox\"><div class=\"authcard\">';flash($flash);if(($_GET['step']??'')==='2')echo '<h2>ثبت رمز جدید</h2><form method=\"post\" class=\"form\">'.cf().'<input type=\"hidden\" name=\"action\" value=\"admin_recover_finish\"><label>شماره موبایل<input name=\"mobile\" inputmode=\"numeric\" required></label><label>کد بازیابی<input name=\"code\" inputmode=\"numeric\" maxlength=\"6\" required></label><label>رمز جدید<input type=\"password\" name=\"new_password\" minlength=\"12\" required></label><button class=\"btn dark\">ثبت رمز جدید</button></form>';else echo '<h2>بازیابی دسترسی</h2><form method=\"post\" class=\"form\">'.cf().'<input type=\"hidden\" name=\"action\" value=\"admin_recover_request\"><label>شماره موبایل<input name=\"mobile\" inputmode=\"numeric\" required></label><button class=\"btn dark\">ارسال کد بازیابی</button></form>';echo '<a class=\"adminlink\" href=\"./?admin=1\">بازگشت</a></div></section></main></body></html>';exit;}\n"
)
admin_marker="if($isAdmin&&!admin()){headx('ورود مدیر');"
if admin_marker not in text:raise SystemExit('admin render marker missing')
text=text.replace(admin_marker,admin_recover+admin_marker,1)
text=text.replace('مدیریت مرکزی<br>نمایندگی‌ها و توزیع هوشمند سفارش','ورود امن',1)
text=text.replace("<button class=\"btn dark\">ورود</button></form><a class=\"adminlink\" href=\"./\">بازگشت</a>","<button class=\"btn dark\">ورود</button></form><a class=\"adminlink\" href=\"?admin=1&recover=1\">بازیابی دسترسی</a>",1)
p.write_text(text,encoding='utf-8')

p=root/'smart-actions.php'
text=p.read_text(encoding='utf-8')
old_jx="function jx(string $f):array{$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}"
new_jx="function jx(string $f):array{$c=&$GLOBALS['_WSJX_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];$x=json_decode((string)@file_get_contents($f),true);return $c[$f]=is_array($x)?$x:[];}"
old_sjx="function sjx(string $f,array $x):bool{$l=fopen($f.'.lock','c');if(!$l||!flock($l,LOCK_EX))return false;try{$t=$f.'.tmp.'.bin2hex(random_bytes(3));$q=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($q===false||file_put_contents($t,$q,LOCK_EX)===false)return false;@chmod($t,0640);return rename($t,$f);}finally{flock($l,LOCK_UN);fclose($l);}}"
new_sjx="function sjx(string $f):array{return [];}"
# Only patch read cache here; keep write function unchanged to avoid changing behavior unexpectedly.
text=rep(text,old_jx,new_jx,'smart actions read cache')
p.write_text(text,encoding='utf-8')

p=root/'finance.php'
text=p.read_text(encoding='utf-8')
old_fin="function j($f){$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}"
new_fin="function j($f){$c=&$GLOBALS['_WSF_CACHE'];if(!is_array($c??null))$c=[];if(array_key_exists($f,$c))return $c[$f];$x=json_decode((string)@file_get_contents($f),true);return $c[$f]=is_array($x)?$x:[];}"
text=rep(text,old_fin,new_fin,'finance read cache')
p.write_text(text,encoding='utf-8')
