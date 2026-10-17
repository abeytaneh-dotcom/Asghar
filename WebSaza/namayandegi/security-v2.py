from pathlib import Path
import re

root = Path(__file__).resolve().parent
p = root / 'index.php'
text = p.read_text(encoding='utf-8')

# Registration must never create an authenticated/active representative directly.
pat = re.compile(r"if\(post\('register'\)\)\{.*?go\('\./'\);\}", re.S)
new_register = r'''if(post('register')){if(!okc())exit('درخواست نامعتبر');$name=trim((string)($_POST['name']??''));$mobile=preg_replace('/\D/','',(string)($_POST['mobile']??''))??'';$city=trim((string)($_POST['city']??''));$regions=trim((string)($_POST['regions']??''));$p=(string)($_POST['password']??'');$ref=trim((string)($_POST['ref']??''));if(mb_strlen($name)<3||!preg_match('/^09\d{9}$/',$mobile)||strlen($p)<10){fl('اطلاعات ثبت‌نام معتبر نیست.','err');go('./?mode=register');}$rows=j(B);foreach($rows as $r)if(($r['mobile']??'')===$mobile){fl('امکان ثبت این شماره وجود ندارد.','err');go('./?mode=login');}$id='B'.bin2hex(random_bytes(6));$rows[]=['id'=>$id,'code'=>code(),'name'=>$name,'mobile'=>$mobile,'city'=>$city,'regions'=>$regions,'password_hash'=>password_hash($p,PASSWORD_DEFAULT),'active'=>false,'mobile_verified'=>false,'approval_status'=>'pending','referrer_code'=>$ref,'daily_quota'=>0,'created_at'=>now(),'last_login_at'=>''];sj(B,$rows);$_SESSION['pending_branch_id']=$id;$_SESSION['pending_mobile']=$mobile;if(!sec_issue_code(D,'branch_verify',$id,$mobile,S)){fl('ثبت اولیه انجام شد اما ارسال کد تأیید ممکن نشد. دوباره تلاش کنید.','err');go('./?mode=verify');}fl('کد تأیید برای شماره ثبت‌شده ارسال شد.');go('./?mode=verify');}'''
text, n = pat.subn(new_register, text, count=1)
if n != 1:
    raise SystemExit('registration handler marker missing')

verify_handlers = r'''
if(post('register_verify')){if(!okc())exit('درخواست نامعتبر');$id=(string)($_SESSION['pending_branch_id']??'');$mobile=sec_mobile((string)($_SESSION['pending_mobile']??''));$code=trim((string)($_POST['code']??''));if($id===''||$mobile===''||!sec_verify_code(D,'branch_verify',$id,$code)){fl('کد تأیید معتبر نیست.','err');go('./?mode=verify');}$rows=j(B);$ok=false;foreach($rows as &$r)if((string)($r['id']??'')===$id&&sec_mobile((string)($r['mobile']??''))===$mobile){$r['mobile_verified']=true;$r['mobile_verified_at']=now();$r['active']=false;$r['approval_status']='pending';$ok=true;break;}unset($r);if(!$ok){fl('درخواست معتبر نیست.','err');go('./?mode=login');}sj(B,$rows);unset($_SESSION['pending_branch_id'],$_SESSION['pending_mobile']);sec_unlock(D,'branch_verify',$id);fl('شماره موبایل تأیید شد. پس از تأیید مدیریت، دسترسی شما فعال می‌شود.');go('./?mode=login');}
if(post('register_resend')){if(!okc())exit('درخواست نامعتبر');$id=(string)($_SESSION['pending_branch_id']??'');$mobile=sec_mobile((string)($_SESSION['pending_mobile']??''));if($id!==''&&preg_match('/^09\d{9}$/',$mobile))sec_issue_code(D,'branch_verify',$id,$mobile,S);fl('در صورت مجاز بودن درخواست، کد جدید ارسال می‌شود.');go('./?mode=verify');}
'''
needle = "if(post('login')){"
if needle not in text:
    raise SystemExit('login marker missing')
text = text.replace(needle, verify_handlers + needle, 1)

# New accounts explicitly marked unverified cannot log in. Existing legacy accounts
# without this field remain usable so this rollout does not lock out current staff.
old_login_match = "(($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])"
new_login_match = "(($r['mobile']??'')===$u||($r['code']??'')===$u)&&!empty($r['active'])&&(!array_key_exists('mobile_verified',$r)||!empty($r['mobile_verified']))"
if old_login_match not in text:
    raise SystemExit('secured login selector marker missing')
text = text.replace(old_login_match, new_login_match, 1)

# Explicitly unverified new accounts can never receive leads, even if someone
# toggles the active flag outside the intended approval flow.
old_elig = "if(!$id||empty($b['active'])||isset($ex[$id])||!matchArea($lead,$b))continue;"
new_elig = "if(!$id||empty($b['active'])||(array_key_exists('mobile_verified',$b)&&empty($b['mobile_verified']))||isset($ex[$id])||!matchArea($lead,$b))continue;"
if old_elig not in text:
    raise SystemExit('eligibility marker missing')
text = text.replace(old_elig, new_elig, 1)

# Management cannot activate a newly-created representative until their mobile is verified.
old_toggle = "{$r['active']=empty($r['active']);break;}"
new_toggle = "{if(empty($r['active'])&&array_key_exists('mobile_verified',$r)&&empty($r['mobile_verified'])){fl('شماره موبایل این نمایندگی هنوز تأیید نشده است.','err');go('./?admin=1&view=branches');}$r['active']=empty($r['active']);$r['approval_status']=$r['active']?'approved':'disabled';break;}"
if old_toggle in text:
    text = text.replace(old_toggle, new_toggle, 1)

# Verification page is isolated from the normal login/register screen.
page_marker = "if(!$b&&!$isAdmin){headx('ورود و ثبت‌نام نمایندگی');"
verify_page = r'''if(!$b&&!$isAdmin&&$mode==='verify'){headx('تأیید شماره موبایل');echo '<main class="auth"><section class="authbox"><div class="authcard">';flash($flash);echo '<h2>تأیید شماره موبایل</h2><p>کد ارسال‌شده را وارد کنید. بعد از تأیید شماره، فعال‌سازی نهایی حساب توسط مدیریت انجام می‌شود.</p><form method="post" class="form">'.cf().'<input type="hidden" name="action" value="register_verify"><label>کد تأیید<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></label><button class="btn dark">تأیید شماره</button></form><form method="post" class="form">'.cf().'<input type="hidden" name="action" value="register_resend"><button class="btn light" type="submit">ارسال مجدد کد</button></form><a class="adminlink" href="?mode=login">بازگشت به ورود</a></div></section></main></body></html>';exit;}
'''
if page_marker not in text:
    raise SystemExit('auth page marker missing')
text = text.replace(page_marker, verify_page + page_marker, 1)

text = text.replace('ساخت حساب و ورود</button>', 'ثبت و تأیید شماره</button>', 1)

p.write_text(text, encoding='utf-8')
