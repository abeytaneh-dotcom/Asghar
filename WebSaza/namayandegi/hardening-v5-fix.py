from pathlib import Path
import re

root=Path(__file__).resolve().parent

# Fix row-cache indexing precedence bug that could emit Array-to-string warnings.
p=root/'platform-v3.php'
text=p.read_text(encoding='utf-8')
bad="$old[(string)$r['item_key']=$r];"
good="$old[(string)$r['item_key']]=$r;"
if bad in text:
    text=text.replace(bad,good,1)
elif good not in text:
    raise SystemExit('platform row index marker missing')
p.write_text(text,encoding='utf-8')

# Correct auth UI after representative PIN hardening.
p=root/'index.php'
text=p.read_text(encoding='utf-8')
branch_field='<label>رمز جدید ۶ رقمی<input type="password" name="new_password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
admin_field='<label>رمز جدید مدیر<input type="password" name="new_password" minlength="8" autocomplete="new-password" required><small>حداقل ۸ کاراکتر، شامل حرف و عدد</small></label>'
if text.count(branch_field)>=2:
    idx=text.rfind(branch_field)
    text=text[:idx]+admin_field+text[idx+len(branch_field):]
admin_settings='<input type="password" name="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>'
if admin_settings in text:
    text=text.replace(admin_settings,'<input type="password" name="password" minlength="8" autocomplete="new-password" required>',1)
needle="$r['password_hash']=password_hash($new,PASSWORD_DEFAULT);"
if needle in text and "unset($r['must_change_password'])" not in text:
    text=text.replace(needle,needle+"unset($r['must_change_password']);",1)
if 'name="terms"' not in text:
    consent='<label class="terms-consent"><input type="checkbox" name="terms" value="1" required> با <a href="?mode=terms" target="_blank" rel="noopener">شرایط همکاری</a> و <a href="?mode=privacy" target="_blank" rel="noopener">حریم خصوصی</a> موافقم.</label>'
    text,n=re.subn(r'(<button[^>]*>ثبت و تأیید شماره</button>)',lambda m:consent+m.group(1),text,count=1)
    if n!=1:
        raise SystemExit('registration submit marker missing')
text=text.replace('<label>کد معرف (اختیاری)<input name="ref"','<label>کد معرف (اختیاری)<input name="ref" inputmode="numeric" maxlength="4" placeholder="کد ۴ رقمی معرف"',1)
p.write_text(text,encoding='utf-8')

# Ensure the first V5 archive is produced even if the daily snapshot is not due yet.
p=root/'backup-worker.php'
text=p.read_text(encoding='utf-8')
old="$statusFile=D.'/_backup_status.json';$force=in_array('--force',$argv??[],true);$old=ws_raw_read($statusFile);$last=strtotime((string)($old['completed_at']??''))?:0;if(!$force&&$last>time()-72000){echo \"BACKUP_NOT_DUE\\n\";exit(0);} "
new="$statusFile=D.'/_backup_status.json';$force=in_array('--force',$argv??[],true);$old=ws_raw_read($statusFile);$last=strtotime((string)($old['completed_at']??''))?:0;$preBase=dirname(__DIR__,2).'/websaza_backups';if(!$force&&$last>time()-72000&&is_file($preBase.'/latest-export.tar.gz')){echo \"BACKUP_NOT_DUE\\n\";exit(0);} "
if old in text:
    text=text.replace(old,new,1)
p.write_text(text,encoding='utf-8')

# Deny CLI maintenance tools from the web. Maintenance mode itself is applied
# by the deploy workflow using a temporary htaccess and removed by replacing it
# with this final production htaccess, avoiding stale lock files.
p=root/'.htaccess'
text=p.read_text(encoding='utf-8')
text=text.replace('RewriteRule ^(?:backup-worker|restore-backup|sms-worker|ops-worker)\\.php$ - [F,L,NC]','RewriteRule ^(?:backup-worker|restore-backup|sms-worker|ops-worker|db-export-v5|sms-canary)\\.php$ - [F,L,NC]')
text=re.sub(r'\n# WEBSAZA_DEPLOY_LOCK_V5\nRewriteCond .*?ErrorDocument 503 .*?\n','\n',text,flags=re.S)
if 'WEBSAZA_DEPLOY_LOCK_V5' not in text:
    text += '\n# WEBSAZA_DEPLOY_LOCK_V5 managed temporarily by deploy workflow\n'
p.write_text(text,encoding='utf-8')
