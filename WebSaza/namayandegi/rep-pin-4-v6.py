from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')


def replace_block(src: str, marker: str, transform):
    start = src.find(marker)
    if start < 0:
        raise SystemExit(f'marker missing: {marker}')
    brace = src.find('{', start)
    if brace < 0:
        raise SystemExit(f'brace missing: {marker}')
    depth = 0
    quote = None
    esc = False
    i = brace
    while i < len(src):
        c = src[i]
        if quote is not None:
            if esc:
                esc = False
            elif c == '\\':
                esc = True
            elif c == quote:
                quote = None
        else:
            if c in ("'", '"'):
                quote = c
            elif c == '{':
                depth += 1
            elif c == '}':
                depth -= 1
                if depth == 0:
                    old = src[start:i+1]
                    new = transform(old)
                    return src[:start] + new + src[i+1:]
        i += 1
    raise SystemExit(f'unterminated block: {marker}')


# Registration policy: representative PIN is exactly 4 numeric digits.
def fix_register(z: str) -> str:
    z = z.replace("!preg_match('/^\\d{6}$/',$p)", "!preg_match('/^\\d{4}$/',$p)")
    z = z.replace('رمز باید دقیقاً ۶ رقم باشد و تأیید آن نیز یکسان وارد شود.', 'رمز باید دقیقاً ۴ رقم باشد و تأیید آن نیز یکسان وارد شود.')
    return z

text = replace_block(text, "if(post('register')){", fix_register)

# Representative password recovery and profile change use the same 4-digit PIN.
def fix_branch_new(z: str) -> str:
    z = z.replace("!preg_match('/^\\d{6}$/',$new)", "!preg_match('/^\\d{4}$/',$new)")
    z = z.replace('رمز جدید باید دقیقاً ۶ رقم باشد.', 'رمز جدید باید دقیقاً ۴ رقم باشد.')
    z = z.replace('رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۶ رقم باشد.', 'رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۴ رقم باشد.')
    return z

for marker in ["if(post('recover_reset'))", "if(post('branch_password'))"]:
    if marker in text:
        text = replace_block(text, marker, fix_branch_new)

# A valid 4-digit PIN is the final policy, not a legacy password that needs upgrading.
def fix_login(z: str) -> str:
    z = z.replace("$rows[$idx]['last_login_at']=now();if(preg_match('/^\\d{4}$/',$p))$rows[$idx]['must_change_password']=true;", "$rows[$idx]['last_login_at']=now();unset($rows[$idx]['must_change_password']);")
    if "$rows[$idx]['last_login_at']=now();" in z and "unset($rows[$idx]['must_change_password']);" not in z:
        z = z.replace("$rows[$idx]['last_login_at']=now();", "$rows[$idx]['last_login_at']=now();unset($rows[$idx]['must_change_password']);", 1)
    return z

text = replace_block(text, "if(post('login')){", fix_login)

# Public registration UI labels.
text = text.replace('رمز ورود ۶ رقمی', 'رمز ورود ۴ رقمی')
text = text.replace('تکرار رمز ۶ رقمی', 'تکرار رمز ۴ رقمی')
text = text.replace('رمز جدید ۶ رقمی', 'رمز جدید ۴ رقمی')

# Force exact native browser constraints on the public registration fields.
reg_start = text.find("if($mode==='register')")
reg_end = text.find("else echo '<h2>ورود نمایندگی", reg_start)
if reg_start < 0 or reg_end < 0:
    raise SystemExit('registration markup boundaries missing')
reg_html = text[reg_start:reg_end]
reg_password = '<input type="password" name="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required>'
reg_confirm = '<input type="password" name="password_confirm" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required>'
reg_html, n1 = re.subn(r'<input(?=[^>]*\bname="password")[^>]*>', reg_password, reg_html, count=1)
reg_html, n2 = re.subn(r'<input(?=[^>]*\bname="password_confirm")[^>]*>', reg_confirm, reg_html, count=1)
if n1 != 1 or n2 != 1:
    raise SystemExit('registration password fields missing')
text = text[:reg_start] + reg_html + text[reg_end:]

# Representative recovery/profile numeric inputs stay exactly four digits.
def force_four(tag: str) -> str:
    tag = re.sub(r'\s+(?:pattern|minlength|maxlength|inputmode|autocomplete)="[^"]*"', '', tag)
    return tag[:-1] + ' inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password">'

text = re.sub(
    r'(<label>رمز جدید ۴ رقمی)(<input[^>]*name="new_password"[^>]*>)',
    lambda m: m.group(1) + force_four(m.group(2)),
    text,
)
text = re.sub(
    r'<input(?=[^>]*\bname="new")(?=[^>]*type="password")[^>]*>',
    lambda m: force_four(m.group(0)),
    text,
    count=1,
)

# Final registration invariants: browser and server agree on exactly 4 digits.
reg_start = text.find("if($mode==='register')")
reg_end = text.find("else echo '<h2>ورود نمایندگی", reg_start)
reg_html = text[reg_start:reg_end]
if 'رمز ورود ۴ رقمی' not in reg_html or 'تکرار رمز ۴ رقمی' not in reg_html:
    raise SystemExit('4-digit registration labels missing')
if reg_password not in reg_html or reg_confirm not in reg_html:
    raise SystemExit('exact 4-digit registration inputs missing')
if 'رمز ورود ۶ رقمی' in reg_html or 'تکرار رمز ۶ رقمی' in reg_html:
    raise SystemExit('stale 6-digit registration UI remains')

reg_handler_start = text.find("if(post('register')){")
reg_handler_end = text.find("if(post('register_verify'))", reg_handler_start)
reg_handler = text[reg_handler_start:reg_handler_end]
if "!preg_match('/^\\d{4}$/',$p)" not in reg_handler or "!preg_match('/^\\d{6}$/',$p)" in reg_handler:
    raise SystemExit('registration server PIN policy mismatch')

p.write_text(text, encoding='utf-8')
print('WEBSAZA_REP_PIN4_V6_OK')
