from pathlib import Path
import re

p = Path(__file__).resolve().parent / 'index.php'
text = p.read_text(encoding='utf-8')


def normalize_pin_input(match: re.Match) -> str:
    tag = match.group(0)
    # Remove any stale constraints left by older 8/10/12-char password policies.
    tag = re.sub(r'\s+(?:minlength|maxlength|pattern|inputmode|autocomplete)="[^"]*"', '', tag)
    return tag[:-1] + ' inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password">'

# Normalize only the representative registration PIN fields.
for name in ('password', 'password_confirm'):
    pattern = rf'<input(?=[^>]*\bname="{name}")[^>]*>'
    matches = list(re.finditer(pattern, text))
    if name == 'password_confirm' and not matches:
        # Add confirmation immediately after the registration PIN label if an older build omitted it.
        reg = re.search(r'(<label>رمز ورود ۶ رقمی<input(?=[^>]*\bname="password")[^>]*></label>)', text)
        if reg:
            add = '<label>تکرار رمز ۶ رقمی<input type="password" name="password_confirm" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
            text = text[:reg.end()] + add + text[reg.end():]
            matches = list(re.finditer(pattern, text))
    if matches:
        # The first password field is the public representative registration field; confirmation is unique.
        target = matches[0]
        text = text[:target.start()] + normalize_pin_input(target) + text[target.end():]

# Ensure the registration consent is explicit and includes both legal documents.
privacy_link = '<a href="./privacy.html" target="_blank" rel="noopener">حریم خصوصی</a>'
terms_link = '<a href="./terms.html" target="_blank" rel="noopener">شرایط همکاری</a>'

# Upgrade an existing consent label if present.
terms_label = re.search(r'<label[^>]*class="[^"]*terms-consent[^"]*"[^>]*>.*?</label>', text)
if terms_label:
    label = terms_label.group(0)
    if 'privacy.html' not in label:
        label = re.sub(r'<a[^>]*>شرایط همکاری</a>', terms_link, label)
        if 'حریم خصوصی' not in label:
            label = label.replace('موافقم.', 'و ' + privacy_link + ' موافقم.')
        elif 'privacy.html' not in label:
            label = label.replace('حریم خصوصی', privacy_link)
        text = text[:terms_label.start()] + label + text[terms_label.end():]
else:
    # Older markup had a simple checkbox label. Replace/insert a canonical consent row.
    simple = re.search(r'<label[^>]*>\s*<input[^>]*name="terms"[^>]*>.*?</label>', text)
    canonical = '<label class="terms-consent"><input type="checkbox" name="terms" value="1" required> با ' + terms_link + ' و ' + privacy_link + ' موافقم.</label>'
    if simple:
        text = text[:simple.start()] + canonical + text[simple.end():]
    else:
        confirm = re.search(r'<label>تکرار رمز ۶ رقمی<input(?=[^>]*\bname="password_confirm")[^>]*></label>', text)
        if confirm:
            text = text[:confirm.end()] + canonical + text[confirm.end():]

# Browser-side guard: exactly six digits and identical confirmation.
# This is intentionally tiny and complements server-side validation.
if 'WEBSAZA_REGISTER_PIN_V5' not in text:
    marker = '</body></html>'
    js = '''<script>/* WEBSAZA_REGISTER_PIN_V5 */document.addEventListener('submit',function(e){var f=e.target;if(!(f instanceof HTMLFormElement))return;var p=f.querySelector('input[name="password"]'),c=f.querySelector('input[name="password_confirm"]');if(!p||!c)return;if(!/^\\d{6}$/.test(p.value)){e.preventDefault();p.setCustomValidity('رمز ورود باید دقیقاً ۶ رقم باشد.');p.reportValidity();return;}p.setCustomValidity('');if(p.value!==c.value){e.preventDefault();c.setCustomValidity('تکرار رمز با رمز ورود یکسان نیست.');c.reportValidity();return;}c.setCustomValidity('');},true);</script>'''
    if marker in text:
        text = text.replace(marker, js + marker, 1)

p.write_text(text, encoding='utf-8')
print('WEBSAZA_REGISTER_UI_V5_OK')
