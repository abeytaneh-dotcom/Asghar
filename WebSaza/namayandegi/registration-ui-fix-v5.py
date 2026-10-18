from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')


def normalize_pin_input(match: re.Match) -> str:
    tag = match.group(0)
    # Remove stale browser validation left by older 8/10/12 character policies.
    tag = re.sub(r'\s+(?:minlength|maxlength|pattern|inputmode|autocomplete)="[^"]*"', '', tag)
    return tag[:-1] + ' inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password">'

# The public registration password is the first password input in index.php.
password_pattern = r'<input(?=[^>]*\bname="password")[^>]*>'
matches = list(re.finditer(password_pattern, text))
if not matches:
    raise SystemExit('registration password input missing')
target = matches[0]
text = text[:target.start()] + normalize_pin_input(target) + text[target.end():]

# Confirmation field is unique; add it if an older build omitted it.
confirm_pattern = r'<input(?=[^>]*\bname="password_confirm")[^>]*>'
matches = list(re.finditer(confirm_pattern, text))
if not matches:
    reg = re.search(r'(<label>رمز ورود ۶ رقمی<input(?=[^>]*\bname="password")[^>]*></label>)', text)
    if not reg:
        raise SystemExit('six digit registration label missing')
    add = '<label>تکرار رمز ۶ رقمی<input type="password" name="password_confirm" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
    text = text[:reg.end()] + add + text[reg.end():]
else:
    target = matches[0]
    text = text[:target.start()] + normalize_pin_input(target) + text[target.end():]

# Make sure both fields are explicitly described as six digits.
text = text.replace('<label>رمز عبور<input', '<label>رمز ورود ۶ رقمی<input', 1)

# Ensure explicit legal consent and keep links inside the application.
privacy_link = '<a href="?mode=privacy" target="_blank" rel="noopener">حریم خصوصی</a>'
terms_link = '<a href="?mode=terms" target="_blank" rel="noopener">شرایط همکاری</a>'
terms_label = re.search(r'<label[^>]*class="[^"]*terms-consent[^"]*"[^>]*>.*?</label>', text)
if terms_label:
    label = terms_label.group(0)
    label = re.sub(r'<a[^>]*>شرایط همکاری</a>', terms_link, label)
    label = re.sub(r'<a[^>]*>حریم خصوصی</a>', privacy_link, label)
    if 'حریم خصوصی' not in label:
        label = label.replace('موافقم.', 'و ' + privacy_link + ' موافقم.')
    text = text[:terms_label.start()] + label + text[terms_label.end():]
else:
    simple = re.search(r'<label[^>]*>\s*<input[^>]*name="terms"[^>]*>.*?</label>', text)
    canonical = '<label class="terms-consent"><input type="checkbox" name="terms" value="1" required> با ' + terms_link + ' و ' + privacy_link + ' موافقم.</label>'
    if simple:
        text = text[:simple.start()] + canonical + text[simple.end():]
    else:
        confirm = re.search(r'<label>تکرار رمز ۶ رقمی<input(?=[^>]*\bname="password_confirm")[^>]*></label>', text)
        if not confirm:
            raise SystemExit('confirmation field missing for consent insertion')
        text = text[:confirm.end()] + canonical + text[confirm.end():]

# Build-time invariants: native browser validation must not ask for 8 characters.
reg_start = text.find("if($mode==='register')")
reg_end = text.find("else echo '<h2>ورود نمایندگی", reg_start)
if reg_start < 0 or reg_end < 0:
    raise SystemExit('registration markup boundaries missing')
reg_html = text[reg_start:reg_end]
if 'minlength="8"' in reg_html or 'minlength="10"' in reg_html or 'minlength="12"' in reg_html:
    raise SystemExit('stale registration minlength remains')
if reg_html.count('minlength="6"') < 2 or reg_html.count('maxlength="6"') < 2:
    raise SystemExit('six digit registration constraints missing')

p.write_text(text, encoding='utf-8')
print('WEBSAZA_REGISTER_UI_V5_OK')
