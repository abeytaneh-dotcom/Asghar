from pathlib import Path
import re

root = Path(__file__).resolve().parent
p = root / 'index.php'
text = p.read_text(encoding='utf-8')

# Final policy: every SMS/verification code rendered by this app is exactly 4 digits.
def four_code_input(match: re.Match) -> str:
    tag = match.group(0)
    tag = re.sub(r'\s+(?:pattern|minlength|maxlength|inputmode|autocomplete)="[^"]*"', '', tag)
    return tag[:-1] + ' inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="one-time-code" required>'

text = re.sub(r'<input(?=[^>]*\bname="code")[^>]*>', four_code_input, text)
text = text.replace('کد ۶ رقمی', 'کد ۴ رقمی')
text = text.replace('کد شش‌رقمی', 'کد ۴ رقمی')
text = text.replace('کد شش رقمی', 'کد ۴ رقمی')

# Representative login is mobile-only. Do not accept the branch/referral code as a username.
old_identity = "(($r['mobile']??'')===$u||($r['code']??'')===$u)"
new_identity = "(preg_match('/^09\\d{9}$/',sec_mobile($u))&&sec_mobile((string)($r['mobile']??''))===sec_mobile($u))"
if old_identity not in text:
    raise SystemExit('representative mobile-or-code login marker missing')
text = text.replace(old_identity, new_identity, 1)

# Login PIN itself must also be exactly four digits at the server, not only in HTML.
needle = "$p=(string)($_POST['password']??'');$remember=!empty($_POST['remember']);"
replacement = "$p=(string)($_POST['password']??'');if(!preg_match('/^\\d{4}$/',$p)){fl('شماره موبایل یا رمز ورود صحیح نیست.','err');go('./?mode=login');}$remember=!empty($_POST['remember']);"
if needle not in text:
    raise SystemExit('representative login PIN marker missing')
text = text.replace(needle, replacement, 1)

# Normal login page is intentionally minimal. Registration remains reachable only by its explicit links.
marker = "if(!$b&&!$isAdmin){headx('ورود و ثبت‌نام نمایندگی');"
if marker not in text:
    raise SystemExit('public auth render marker missing')
minimal = r'''if(!$b&&!$isAdmin&&$mode==='login'){headx('ورود نمایندگی');echo '<main class="auth"><section class="authbox"><div class="authcard">';flash($flash);echo '<form method="post" class="form">'.cf().'<input type="hidden" name="action" value="login"><label>شماره موبایل<input name="username" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" autocomplete="username" placeholder="09xxxxxxxxx" required></label><label>رمز ورود<input type="password" name="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="current-password" required></label><label class="remember"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label><button class="btn dark" type="submit">ورود</button></form><a class="adminlink" href="?mode=recover">فراموشی رمز</a></div></section></main></body></html>';exit;}
'''
text = text.replace(marker, minimal + marker, 1)

# Final build invariants for the requested login experience.
login_start = text.find("if(!$b&&!$isAdmin&&$mode==='login')")
login_end = text.find(marker, login_start)
login_html = text[login_start:login_end]
for required in ['شماره موبایل', 'رمز ورود', 'مرا به خاطر بسپار', 'فراموشی رمز', 'pattern="[0-9]{4}"', 'maxlength="4"']:
    if required not in login_html:
        raise SystemExit('minimal login invariant missing: ' + required)
for forbidden in ['ثبت‌نام', 'موبایل یا کد نمایندگی', 'ورود مدیر سیستم']:
    if forbidden in login_html:
        raise SystemExit('minimal login contains forbidden item: ' + forbidden)
if old_identity in text:
    raise SystemExit('branch-code login still enabled')

# The OTP generator is shared by recovery, verification and trusted-device flows.
s = root / '_security.php'
security = s.read_text(encoding='utf-8')
old_rng = 'random_int(100000,999999)'
if old_rng not in security:
    raise SystemExit('six-digit OTP generator marker missing')
security = security.replace(old_rng, 'random_int(1000,9999)', 1)
if old_rng in security or 'random_int(1000,9999)' not in security:
    raise SystemExit('OTP generator did not become four digits')
s.write_text(security, encoding='utf-8')

p.write_text(text, encoding='utf-8')
print('WEBSAZA_LOGIN_MINIMAL_4_V7_OK')
