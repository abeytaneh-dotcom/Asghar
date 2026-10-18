from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')


def normalize_pin_input(match: re.Match) -> str:
    tag = match.group(0)
    # Remove any stale constraints left by older 8/10/12-char password policies.
    tag = re.sub(r'\s+(?:minlength|maxlength|pattern|inputmode|autocomplete)="[^"]*"', '', tag)
    return tag[:-1] + ' inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password">'

# Normalize only the representative registration PIN fields. In the public auth markup,
# the registration password input is the first password field; confirmation is unique.
for name in ('password', 'password_confirm'):
    pattern = rf'<input(?=[^>]*\bname="{name}")[^>]*>'
    matches = list(re.finditer(pattern, text))
    if name == 'password_confirm' and not matches:
        reg = re.search(r'(<label>رمز ورود ۶ رقمی<input(?=[^>]*\bname="password")[^>]*></label>)', text)
        if reg:
            add = '<label>تکرار رمز ۶ رقمی<input type="password" name="password_confirm" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
            text = text[:reg.end()] + add + text[reg.end():]
            matches = list(re.finditer(pattern, text))
    if matches:
        target = matches[0]
        text = text[:target.start()] + normalize_pin_input(target) + text[target.end():]

# Ensure the registration consent is explicit and includes both built-in legal pages.
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
        if confirm:
            text = text[:confirm.end()] + canonical + text[confirm.end():]

# Browser-side guard complements the server-side check.
if 'WEBSAZA_REGISTER_PIN_V5' not in text:
    marker = '</body></html>'
    js = '''<script>/* WEBSAZA_REGISTER_PIN_V5 */document.addEventListener('submit',function(e){var f=e.target;if(!(f instanceof HTMLFormElement))return;var a=f.querySelector('input[name="action"]');if(!a||a.value!=='register')return;var p=f.querySelector('input[name="password"]'),c=f.querySelector('input[name="password_confirm"]');if(!p||!c)return;p.setCustomValidity('');c.setCustomValidity('');if(!/^\\d{6}$/.test(p.value)){e.preventDefault();p.setCustomValidity('رمز ورود باید دقیقاً ۶ رقم باشد.');p.reportValidity();return;}if(p.value!==c.value){e.preventDefault();c.setCustomValidity('تکرار رمز با رمز ورود یکسان نیست.');c.reportValidity();return;}},true);</script>'''
    if marker in text:
        text = text.replace(marker, js + marker, 1)

p.write_text(text, encoding='utf-8')
print('WEBSAZA_REGISTER_UI_V5_OK')
