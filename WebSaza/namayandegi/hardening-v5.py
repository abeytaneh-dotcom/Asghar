from pathlib import Path
import re

root = Path(__file__).resolve().parent

def block(src: str, marker: str, repl: str) -> str:
    start = src.find(marker)
    if start < 0:
        raise SystemExit('marker missing: ' + marker)
    brace = src.find('{', start)
    if brace < 0:
        raise SystemExit('brace missing: ' + marker)
    depth = 0
    quote = None
    esc = False
    i = brace
    while i < len(src):
        c = src[i]
        if quote:
            if esc:
                esc = False
            elif c == '\\':
                esc = True
            elif c == quote:
                quote = None
        else:
            if c in "'\"":
                quote = c
            elif c == '{':
                depth += 1
            elif c == '}':
                depth -= 1
                if depth == 0:
                    return src[:start] + repl + src[i+1:]
        i += 1
    raise SystemExit('unterminated: ' + marker)

def func(src: str, name: str, repl: str) -> str:
    return block(src, 'function ' + name + '(', repl)

# -----------------------------------------------------------------------------
# Platform: immutable admin audit trail and portable export metadata.
# -----------------------------------------------------------------------------
p = root / 'platform-v3.php'
text = p.read_text(encoding='utf-8')
if 'CREATE TABLE IF NOT EXISTS admin_audit' not in text:
    needle = "$db->exec('CREATE INDEX IF NOT EXISTS finance_ledger_ref ON finance_ledger(bucket,item_key)');"
    add = needle + "$db->exec('CREATE TABLE IF NOT EXISTS admin_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,actor TEXT,action TEXT,bucket TEXT,before_hash TEXT,after_hash TEXT,meta_json TEXT,created_at TEXT)');$db->exec('CREATE INDEX IF NOT EXISTS admin_audit_created ON admin_audit(created_at)');$db->exec('CREATE INDEX IF NOT EXISTS admin_audit_bucket ON admin_audit(bucket,created_at)');"
    if needle not in text:
        raise SystemExit('platform db marker missing')
    text = text.replace(needle, add, 1)
if 'function ws_admin_audit(' not in text:
    marker = 'function ws_backend()'
    pos = text.find(marker)
    if pos < 0:
        raise SystemExit('platform helper marker missing')
    helper = r'''function ws_audit_redact(array $x):array{$deny=['sms_api_key','faraz_api_key','ippanel_api_key','api_key','password_hash','verify_token','hash'];$walk=function($v)use(&$walk,$deny){if(!is_array($v))return $v;$o=[];foreach($v as $k=>$z){$kk=(string)$k;if(in_array($kk,$deny,true)||str_contains(mb_strtolower($kk),'secret')||str_contains(mb_strtolower($kk),'token')){$o[$k]='[REDACTED]';}else{$o[$k]=$walk($z);}}return $o;};return $walk($x);} 
function ws_admin_audit(string $action,string $bucket,array $before=[],array $after=[],array $meta=[]):void{if(!($db=ws_db()))return;try{$b=json_encode(ws_audit_redact($before),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}';$a=json_encode(ws_audit_redact($after),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}';$m=json_encode(ws_audit_redact($meta),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}';$st=$db->prepare('INSERT INTO admin_audit(actor,action,bucket,before_hash,after_hash,meta_json,created_at) VALUES(?,?,?,?,?,?,?)');$st->execute(['admin',$action,$bucket,hash('sha256',$b),hash('sha256',$a),$m,ws_now()]);}catch(Throwable $e){}}
'''
    text = text[:pos] + helper + text[pos:]
p.write_text(text, encoding='utf-8')

# -----------------------------------------------------------------------------
# Auth + registration: 6-digit representative PIN, confirmation, legal consent.
# Existing 4-digit users remain able to log in, but are forced to upgrade after login.
# Admin remains protected by device OTP and is upgraded to minimum 8 chars.
# -----------------------------------------------------------------------------
p = root / 'index.php'
text = p.read_text(encoding='utf-8')

# Registration handler.
start = text.find("if(post('register')){")
if start < 0:
    raise SystemExit('register handler missing')
end_text = block(text, "if(post('register')){", '__WS_REGISTER__')
# Extract original block by reconstructing from source boundaries.
brace = text.find('{', start); depth=0; quote=None; esc=False; end=None
i=brace
while i < len(text):
    c=text[i]
    if quote:
        if esc: esc=False
        elif c=='\\': esc=True
        elif c==quote: quote=None
    else:
        if c in "'\"": quote=c
        elif c=='{': depth+=1
        elif c=='}':
            depth-=1
            if depth==0: end=i+1; break
    i+=1
if end is None: raise SystemExit('register handler unterminated')
reg=text[start:end]
reg=reg.replace("$ref=trim((string)($_POST['ref']??''));", "$ref=trim((string)($_POST['ref']??''));$pc=(string)($_POST['password_confirm']??'');$terms=!empty($_POST['terms']);")
reg=reg.replace("!preg_match('/^\\d{4}$/',$p)", "!preg_match('/^\\d{6}$/',$p)")
reg=reg.replace("||!preg_match('/^\\d{6}$/',$p)){", "||!preg_match('/^\\d{6}$/',$p)||!hash_equals($p,$pc)||!$terms){")
reg=reg.replace("رمز باید دقیقاً ۴ رقم باشد.", "رمز باید دقیقاً ۶ رقم باشد و تأیید آن نیز یکسان وارد شود.")
reg=reg.replace("'created_at'=>now(),'last_login_at'=>'']", "'terms_version'=>'2026-10-18-v1','terms_accepted_at'=>now(),'created_at'=>now(),'last_login_at'=>'']")
text=text[:start]+reg+text[end:]

# All representative-facing PIN fields become 6 digits.
text=text.replace('رمز ورود ۴ رقمی','رمز ورود ۶ رقمی')
text=text.replace('رمز جدید ۴ رقمی','رمز جدید ۶ رقمی')
text=text.replace('رمز جدید باید دقیقاً ۴ رقم باشد.','رمز جدید باید دقیقاً ۶ رقم باشد.')
text=text.replace('رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۴ رقم باشد.','رمز فعلی اشتباه است یا رمز جدید باید دقیقاً ۶ رقم باشد.')
text=text.replace('pattern="[0-9]{4}" minlength="4" maxlength="4"','pattern="[0-9]{6}" minlength="6" maxlength="6"')
# Server-side branch PIN checks; admin blocks are hardened separately below.
for marker in ["if(post('recover_reset'))", "if(post('branch_password'))"]:
    if marker in text:
        s=text.find(marker); b=text.find('{',s); d=0; q=None; e=False; ee=None; j=b
        while j<len(text):
            c=text[j]
            if q:
                if e:e=False
                elif c=='\\':e=True
                elif c==q:q=None
            else:
                if c in "'\"":q=c
                elif c=='{':d+=1
                elif c=='}':
                    d-=1
                    if d==0:ee=j+1;break
            j+=1
        if ee:
            z=text[s:ee].replace("!preg_match('/^\\d{4}$/',$new)","!preg_match('/^\\d{6}$/',$new)")
            text=text[:s]+z+text[ee:]

# Successful legacy 4-digit branch login forces a PIN upgrade without locking the user out.
login_marker="if(post('login')){"
if login_marker in text and "must_change_password']=(bool)preg_match('/^\\d{4}$/',$p)" not in text:
    s=text.find(login_marker); b=text.find('{',s); d=0; q=None; e=False; ee=None; j=b
    while j<len(text):
        c=text[j]
        if q:
            if e:e=False
            elif c=='\\':e=True
            elif c==q:q=None
        else:
            if c in "'\"":q=c
            elif c=='{':d+=1
            elif c=='}':
                d-=1
                if d==0:ee=j+1;break
        j+=1
    if ee:
        z=text[s:ee]
        z=z.replace("$rows[$idx]['last_login_at']=now();", "$rows[$idx]['last_login_at']=now();if(preg_match('/^\\d{4}$/',$p))$rows[$idx]['must_change_password']=true;")
        text=text[:s]+z+text[ee:]

# Admin password policy: 8+ chars with at least one letter and one digit.
for marker in ["if(post('admin_recover_reset'))", "if(post('admin_password'))"]:
    if marker in text:
        s=text.find(marker); b=text.find('{',s); d=0; q=None; e=False; ee=None; j=b
        while j<len(text):
            c=text[j]
            if q:
                if e:e=False
                elif c=='\\':e=True
                elif c==q:q=None
            else:
                if c in "'\"":q=c
                elif c=='{':d+=1
                elif c=='}':
                    d-=1
                    if d==0:ee=j+1;break
            j+=1
        if ee:
            z=text[s:ee]
            z=z.replace("!preg_match('/^\\d{4}$/',$new)","strlen($new)<8||!preg_match('/[\\p{L}]/u',$new)||!preg_match('/\\d/',$new)")
            z=z.replace("!preg_match('/^\\d{4}$/',$p)","strlen($p)<8||!preg_match('/[\\p{L}]/u',$p)||!preg_match('/\\d/',$p)")
            z=z.replace('رمز مدیر باید دقیقاً ۴ رقم باشد.','رمز مدیر باید حداقل ۸ کاراکتر و شامل حرف و عدد باشد.')
            text=text[:s]+z+text[ee:]

# Registration UI: confirmation, terms/privacy, referral guidance.
needle='<label>رمز ورود ۶ رقمی<input type="password" name="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>'
if needle in text and 'name="password_confirm"' not in text:
    text=text.replace(needle, needle+'<label>تکرار رمز ۶ رقمی<input type="password" name="password_confirm" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>',1)
text=text.replace('<label>کد معرف (اختیاری)<input name="ref" value="'.replace("'", "'"), '<label>کد معرف (اختیاری)<input name="ref" inputmode="numeric" maxlength="4" placeholder="کد ۴ رقمی معرف" value="'.replace("'", "'"), 1) if '<label>کد معرف (اختیاری)<input name="ref" value="' in text else text
if 'name="terms"' not in text:
    submit='ثبت و تأیید شماره</button>'
    legal='<label class="terms-consent"><input type="checkbox" name="terms" value="1" required> با <a href="?mode=terms" target="_blank" rel="noopener">شرایط همکاری</a> و <a href="?mode=privacy" target="_blank" rel="noopener">حریم خصوصی</a> موافقم.</label><button class="btn dark">ثبت و تأیید شماره</button>'
    text=text.replace('<button class="btn dark">'+submit, legal, 1)

# Remember-me should be an explicit opt-in, not pre-checked.
text=text.replace('name="remember" value="1" checked','name="remember" value="1"')
text=text.replace('name="remember" checked','name="remember"')

# Clearer login language.
text=text.replace('موبایل یا کد نمایندگی','شماره موبایل یا کد ۴ رقمی نمایندگی')

# Legal pages are public and versioned.
mode_marker="if(!$b&&!$isAdmin&&$mode==='verify')"
if mode_marker in text and "mode==='terms'" not in text:
    pos=text.find(mode_marker)
    legal_pages=r'''if(!$b&&!$isAdmin&&$mode==='terms'){headx('شرایط همکاری نمایندگی');echo '<main class="auth"><section class="authbox"><div class="authcard"><h2>شرایط همکاری نمایندگی وب ساز</h2><p>نسخه 2026-10-18-v1</p><p>ثبت‌نام به معنی درخواست همکاری است و فعال‌سازی نهایی پس از تأیید مدیریت انجام می‌شود. نماینده موظف است اطلاعات مشتری را فقط برای پیگیری همان فرصت فروش استفاده کند، از ارسال مزاحمت یا استفاده خارج از سامانه خودداری کند و وضعیت پیگیری را دقیق ثبت نماید. پورسانت، پاداش معرفی و تسویه‌ها بر اساس تنظیمات و سوابق ثبت‌شده در سامانه محاسبه می‌شوند. هرگونه دسترسی غیرمجاز، واگذاری حساب یا استفاده نادرست از اطلاعات مشتری می‌تواند باعث تعلیق دسترسی شود.</p><a class="adminlink" href="?mode=register">بازگشت به ثبت‌نام</a></div></section></main></body></html>';exit;}
if(!$b&&!$isAdmin&&$mode==='privacy'){headx('حریم خصوصی');echo '<main class="auth"><section class="authbox"><div class="authcard"><h2>حریم خصوصی وب ساز</h2><p>نسخه 2026-10-18-v1</p><p>اطلاعات ثبت‌نام، شماره تماس، سوابق ورود، فعالیت فروش، ارجاع‌ها و اطلاعات مالی فقط برای ارائه و امنیت خدمات سامانه نگهداری می‌شود. دسترسی‌ها بر اساس نقش محدود شده‌اند، داده‌های حساس در مسیرهای محافظت‌شده ذخیره می‌شوند و اطلاعات کاربران برای تبلیغات اشخاص ثالث فروخته نمی‌شود. اطلاعات عملیاتی و مالی تا زمانی که برای حسابداری، امنیت و جلوگیری از تماس تکراری لازم باشد نگهداری می‌شود.</p><a class="adminlink" href="?mode=register">بازگشت به ثبت‌نام</a></div></section></main></body></html>';exit;}
'''
    text=text[:pos]+legal_pages+text[pos:]

# Admin audit for sensitive writes from the main portal. Generic hashes avoid leaking API keys.
old="function sj(string $f,array $x):bool{$ok=ws_store_write($f,$x);if($ok){$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];$c[$f]=$x;}return $ok;}"
if old in text:
    new="function sj(string $f,array $x):bool{$c=&$GLOBALS['_WSJ_CACHE'];if(!is_array($c??null))$c=[];$before=array_key_exists($f,$c)?$c[$f]:ws_store_read($f);$ok=ws_store_write($f,$x);if($ok){$c[$f]=$x;if(!empty($_SESSION['adm'])&&in_array($f,[B,S,AD,R],true))ws_admin_audit('write',basename($f),is_array($before)?$before:[],$x,['request_action'=>(string)($_POST['action']??$_GET['action']??'')]);}return $ok;}"
    text=text.replace(old,new,1)

# Add a clear main-site navigation link on public auth pages when an auth card closes.
if 'بازگشت به سایت اصلی وب ساز' not in text:
    text=text.replace("<a class=\"adminlink\" href=\"?mode=register\">ثبت‌نام نمایندگی</a>","<a class=\"adminlink\" href=\"?mode=register\">ثبت‌نام نمایندگی</a><a class=\"adminlink\" href=\"https://websaza.ir/\">بازگشت به سایت اصلی وب ساز</a>",1)

p.write_text(text,encoding='utf-8')

# -----------------------------------------------------------------------------
# Finance: strict receipt validation + expiring customer verification links.
# -----------------------------------------------------------------------------
p=root/'finance.php'
text=p.read_text(encoding='utf-8')

# Payment link expiry (30 days).
marker="if($act==='add_payment'&&$bid)"
if marker in text:
    s=text.find(marker); b=text.find('{',s); d=0; q=None; e=False; ee=None; j=b
    while j<len(text):
        c=text[j]
        if q:
            if e:e=False
            elif c=='\\':e=True
            elif c==q:q=None
        else:
            if c in "'\"":q=c
            elif c=='{':d+=1
            elif c=='}':
                d-=1
                if d==0:ee=j+1;break
        j+=1
    if ee:
        z=text[s:ee]
        z=z.replace("'verify_token'=>$token,'status'=>'active'", "'verify_token'=>$token,'verify_expires_at'=>date('Y-m-d H:i:s',time()+2592000),'verify_revoked_at'=>'','status'=>'active'")
        text=text[:s]+z+text[ee:]

# GET verify: reject expired/revoked token.
needle="if(!$p){echo '<h2>لینک معتبر نیست</h2></section></main></body></html>';exit;}"
if needle in text and 'لینک منقضی یا باطل شده است' not in text:
    repl=needle+"if(!empty($p['verify_revoked_at'])||(!empty($p['verify_expires_at'])&&strtotime((string)$p['verify_expires_at'])<time())){echo '<h2>لینک منقضی یا باطل شده است</h2><p class=\"mut\">برای دریافت لینک جدید با پشتیبانی تماس بگیرید.</p></section></main></body></html>';exit;}"
    text=text.replace(needle,repl,1)

# POST verify: enforce expiry too.
needle="if(!$p)exit('لینک نامعتبر');"
if needle in text and "verify_revoked_at" not in text[text.find("customer_confirm"):text.find("customer_confirm")+1500]:
    text=text.replace(needle,needle+"if(!empty($p['verify_revoked_at'])||(!empty($p['verify_expires_at'])&&strtotime((string)$p['verify_expires_at'])<time()))exit('لینک منقضی یا باطل شده است');",1)

# Safe remittance upload.
new_remit=r'''if($act==='add_remittance'&&$bid){$amt=mon($_POST['amount']??0);$tr=trim((string)($_POST['tracking_code']??''));if($amt<=0||mb_strlen($tr)<3){fl('مبلغ و کد پیگیری الزامی است.','err');go('finance.php');}$file='';if(!empty($_FILES['receipt']['tmp_name'])&&is_uploaded_file($_FILES['receipt']['tmp_name'])){$size=(int)($_FILES['receipt']['size']??0);if($size<=0||$size>5242880){fl('حجم رسید باید حداکثر ۵ مگابایت باشد.','err');go('finance.php');}$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($_FILES['receipt']['tmp_name']);$allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];if(!isset($allow[$mime])){fl('فقط تصویر JPG/PNG/WEBP یا PDF معتبر قابل بارگذاری است.','err');go('finance.php');}if(str_starts_with($mime,'image/')){$dim=@getimagesize($_FILES['receipt']['tmp_name']);if(!$dim||(int)$dim[0]>12000||(int)$dim[1]>12000){fl('فایل تصویر معتبر نیست یا ابعاد آن بیش از حد مجاز است.','err');go('finance.php');}}$file='R'.bin2hex(random_bytes(16)).'.'.$allow[$mime];if(!@move_uploaded_file($_FILES['receipt']['tmp_name'],RD.'/'.$file)){fl('ذخیره رسید انجام نشد.','err');go('finance.php');}@chmod(RD.'/'.$file,0640);}$mm=j(M);$row=['id'=>'M'.bin2hex(random_bytes(6)),'branch_id'=>$bid,'amount'=>$amt,'tracking_code'=>$tr,'receipt_file'=>$file,'status'=>'pending','created_at'=>now(),'reviewed_at'=>'','review_note'=>''];$mm[]=$row;if(!sj(M,$mm)){if($file!=='')@unlink(RD.'/'.$file);fl('ثبت واریزی انجام نشد.','err');go('finance.php');}fl('واریزی ثبت شد؛ تا تأیید مدیریت از بدهی کم نمی‌شود.');go('finance.php');}'''
if "if($act==='add_remittance'&&$bid)" in text:
    text=block(text,"if($act==='add_remittance'&&$bid)",new_remit)

# Admin actions to revoke or reissue customer verification links.
anchor="if($act==='review_remittance'&&$adm)"
if anchor in text and "reissue_payment_link" not in text:
    pos=text.find(anchor)
    actions=r'''if($act==='revoke_payment_link'&&$adm){$id=(string)($_POST['id']??'');$pp=j(P);$ok=false;foreach($pp as &$x)if((string)($x['id']??'')===$id){$x['verify_revoked_at']=now();$ok=true;break;}unset($x);if($ok)sj(P,$pp);fl($ok?'لینک تأیید مشتری باطل شد.':'تراکنش پیدا نشد.',$ok?'ok':'err');go('finance.php?admin=1');}
if($act==='reissue_payment_link'&&$adm){$id=(string)($_POST['id']??'');$pp=j(P);$ok=false;foreach($pp as &$x)if((string)($x['id']??'')===$id){$x['verify_token']=bin2hex(random_bytes(20));$x['verify_expires_at']=date('Y-m-d H:i:s',time()+2592000);$x['verify_revoked_at']='';$ok=true;break;}unset($x);if($ok)sj(P,$pp);fl($ok?'لینک تأیید مشتری با اعتبار ۳۰ روز دوباره صادر شد.':'تراکنش پیدا نشد.',$ok?'ok':'err');go('finance.php?admin=1');}
'''
    text=text[:pos]+actions+text[pos:]
p.write_text(text,encoding='utf-8')

# -----------------------------------------------------------------------------
# Backup: produce fixed latest archive for offsite pull and restore receipts too.
# -----------------------------------------------------------------------------
p=root/'backup-worker.php'
text=p.read_text(encoding='utf-8')
if "latest-export.tar.gz" not in text:
    needle="$manifest=['version'=>'backup-v1','created_at'=>ws_now(),'release'=>'platform-v4','files'=>$files];file_put_contents($dir.'/manifest.json',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);@chmod($dir.'/manifest.json',0600);"
    if needle not in text:
        raise SystemExit('backup manifest marker missing')
    add=needle+"$latest=$base.'/latest-export.tar.gz';@unlink($latest);$tmpTar=$base.'/latest-export.tar';@unlink($tmpTar);try{$ph=new PharData($tmpTar);$ph->buildFromDirectory($dir);$ph->compress(Phar::GZ);unset($ph);@unlink($tmpTar);$gz=$tmpTar.'.gz';if(is_file($gz)){@rename($gz,$latest);@chmod($latest,0600);}}catch(Throwable $archiveError){@unlink($tmpTar);@unlink($tmpTar.'.gz');}"
    text=text.replace(needle,add,1)
p.write_text(text,encoding='utf-8')

p=root/'restore-backup.php'
text=p.read_text(encoding='utf-8')
if 'function rb_copy_tree' not in text:
    marker="const D=__DIR__.'/_data';"
    helper="function rb_rm_tree(string $p):void{if(!is_dir($p))return;foreach(scandir($p)?:[] as $n){if($n==='.'||$n==='..')continue;$x=$p.'/'.$n;if(is_dir($x))rb_rm_tree($x);else @unlink($x);}@rmdir($p);}function rb_copy_tree(string $src,string $dst):void{if(!is_dir($src))return;if(!is_dir($dst))mkdir($dst,0750,true);foreach(scandir($src)?:[] as $n){if($n==='.'||$n==='..')continue;$a=$src.'/'.$n;$b=$dst.'/'.$n;if(is_dir($a))rb_copy_tree($a,$b);elseif(is_file($a)){copy($a,$b);@chmod($b,0640);}}}\n"
    if marker not in text: raise SystemExit('restore marker missing')
    text=text.replace(marker,helper+marker,1)
if "rb_copy_tree($src.'/receipts'" not in text:
    needle="foreach(['settings.json','admin.json','_login_security.json','_login_secret','_admin_trusted_devices.json','contact_registry.json'] as $n){if(is_file($src.'/'.$n)){copy($src.'/'.$n,D.'/'.$n);@chmod(D.'/'.$n,$n==='_login_secret'?0600:0640);}}"
    add=needle+"rb_rm_tree(D.'/receipts');rb_copy_tree($src.'/receipts',D.'/receipts');$check=new PDO('sqlite:'.D.'/websaza.sqlite3');$integrity=(string)$check->query('PRAGMA integrity_check')->fetchColumn();if(strtolower($integrity)!=='ok')throw new RuntimeException('integrity failed');"
    if needle not in text: raise SystemExit('restore copy marker missing')
    text=text.replace(needle,add,1)
p.write_text(text,encoding='utf-8')

# -----------------------------------------------------------------------------
# Ops: include independent offsite-backup freshness marker if present.
# -----------------------------------------------------------------------------
p=root/'ops-worker.php'
text=p.read_text(encoding='utf-8')
if "_offsite_backup_status.json" not in text:
    needle="$backup=ws_raw_read(D.'/_backup_status.json');"
    if needle in text:
        text=text.replace(needle,needle+"$offsite=ws_raw_read(D.'/_offsite_backup_status.json');",1)
    # Keep local backup as mandatory; offsite freshness is reported but does not make the local service unavailable.
    needle2="$status=['ok'=>$ok,'checked_at'=>ws_now(),'db_ok'=>!empty($core['ok']),'backup_ok'=>$backupOk,'disk_ok'=>$diskOk,'sms_dead'=>$dead,'sms_retry'=>$retry,'sms_old_pending'=>$oldQueued];"
    if needle2 in text:
        repl="$offsiteTs=strtotime((string)($offsite['completed_at']??''))?:0;$offsiteOk=!empty($offsite['ok'])&&$offsiteTs>time()-172800;$status=['ok'=>$ok,'checked_at'=>ws_now(),'db_ok'=>!empty($core['ok']),'backup_ok'=>$backupOk,'offsite_backup_ok'=>$offsiteOk,'disk_ok'=>$diskOk,'sms_dead'=>$dead,'sms_retry'=>$retry,'sms_old_pending'=>$oldQueued];"
        text=text.replace(needle2,repl,1)
p.write_text(text,encoding='utf-8')

# -----------------------------------------------------------------------------
# Browser UX: prevent accidental double submits on auth forms, while preserving validation.
# -----------------------------------------------------------------------------
p=root/'app.js'
text=p.read_text(encoding='utf-8')
if 'WEBSAZA_SUBMIT_GUARD_V5' not in text:
    text += r'''
/* WEBSAZA_SUBMIT_GUARD_V5 */
document.addEventListener('submit',function(ev){var f=ev.target;if(!(f instanceof HTMLFormElement))return;if(!f.checkValidity())return;var b=f.querySelector('button[type="submit"],button:not([type])');if(!b||b.dataset.wsBusy==='1')return;b.dataset.wsBusy='1';b.disabled=true;var old=b.textContent;b.dataset.wsOld=old||'';b.textContent='در حال پردازش…';setTimeout(function(){if(document.body.contains(b)){b.disabled=false;b.dataset.wsBusy='0';if(b.dataset.wsOld)b.textContent=b.dataset.wsOld;}},12000);},true);
'''
p.write_text(text,encoding='utf-8')

p=root/'style.css'
text=p.read_text(encoding='utf-8')
if 'terms-consent' not in text:
    text += r'''
/* V5 auth/legal polish */
.terms-consent{display:flex!important;align-items:flex-start!important;gap:8px!important;font-size:12px!important;line-height:1.9!important;color:#666!important}.terms-consent input[type=checkbox]{width:17px!important;height:17px!important;min-width:17px!important;margin-top:4px!important}.terms-consent a{text-decoration:underline;font-weight:800}.form button[disabled]{opacity:.66;cursor:wait}
'''
p.write_text(text,encoding='utf-8')
