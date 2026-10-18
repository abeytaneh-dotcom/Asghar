from pathlib import Path

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

# Correct admin password UI after representative PIN hardening.
p=root/'index.php'
text=p.read_text(encoding='utf-8')
branch_field='<label>رمز جدید ۶ رقمی<input type="password" name="new_password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
admin_field='<label>رمز جدید مدیر<input type="password" name="new_password" minlength="8" autocomplete="new-password" required><small>حداقل ۸ کاراکتر، شامل حرف و عدد</small></label>'
# Branch recovery should keep the first numeric field; admin recovery, if present, is the last one.
if text.count(branch_field)>=2:
    idx=text.rfind(branch_field)
    text=text[:idx]+admin_field+text[idx+len(branch_field):]
admin_settings='<input type="password" name="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>'
if admin_settings in text:
    text=text.replace(admin_settings,'<input type="password" name="password" minlength="8" autocomplete="new-password" required>',1)
# Make sure changing a branch PIN clears the forced-upgrade flag.
needle="$r['password_hash']=password_hash($new,PASSWORD_DEFAULT);"
if needle in text and "unset($r['must_change_password'])" not in text:
    text=text.replace(needle,needle+"unset($r['must_change_password']);",1)
p.write_text(text,encoding='utf-8')

# Ensure the first V5 archive is produced even if the daily snapshot is not due yet.
p=root/'backup-worker.php'
text=p.read_text(encoding='utf-8')
old="$statusFile=D.'/_backup_status.json';$force=in_array('--force',$argv??[],true);$old=ws_raw_read($statusFile);$last=strtotime((string)($old['completed_at']??''))?:0;if(!$force&&$last>time()-72000){echo \"BACKUP_NOT_DUE\\n\";exit(0);} "
new="$statusFile=D.'/_backup_status.json';$force=in_array('--force',$argv??[],true);$old=ws_raw_read($statusFile);$last=strtotime((string)($old['completed_at']??''))?:0;$preBase=dirname(__DIR__,2).'/websaza_backups';if(!$force&&$last>time()-72000&&is_file($preBase.'/latest-export.tar.gz')){echo \"BACKUP_NOT_DUE\\n\";exit(0);} "
if old in text:
    text=text.replace(old,new,1)
p.write_text(text,encoding='utf-8')

# Deny all CLI maintenance tools from the web and add a deployment lock rule.
p=root/'.htaccess'
text=p.read_text(encoding='utf-8')
text=text.replace('RewriteRule ^(?:backup-worker|restore-backup|sms-worker|ops-worker)\\.php$ - [F,L,NC]','RewriteRule ^(?:backup-worker|restore-backup|sms-worker|ops-worker|db-export-v5|sms-canary)\\.php$ - [F,L,NC]')
if 'WEBSAZA_DEPLOY_LOCK_V5' not in text:
    lock="""\n# WEBSAZA_DEPLOY_LOCK_V5\nRewriteCond %{DOCUMENT_ROOT}/namayandegi/.deploying -f\nRewriteCond %{REQUEST_URI} !/health\\.php$ [NC]\nRewriteRule ^ - [R=503,L]\nErrorDocument 503 \"سامانه در حال بروزرسانی است؛ چند لحظه دیگر دوباره تلاش کنید.\"\n"""
    insert=text.find('\n<Files "_security.php">')
    text=text[:insert]+lock+text[insert:] if insert>=0 else text+lock
p.write_text(text,encoding='utf-8')
