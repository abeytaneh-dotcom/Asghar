from pathlib import Path

support = Path(__file__).resolve().parent
root = support.parent / 'namayandegi'
index_path = root / 'index.php'
style_path = root / 'style.css'
text = index_path.read_text(encoding='utf-8')


def replace_block(src: str, marker: str, replacement: str) -> str:
    start = src.find(marker)
    if start < 0:
        raise SystemExit('v10 marker missing: ' + marker)
    brace = src.find('{', start)
    if brace < 0:
        raise SystemExit('v10 opening brace missing: ' + marker)
    depth = 0
    quote = None
    escaped = False
    i = brace
    while i < len(src):
        ch = src[i]
        if quote is not None:
            if escaped:
                escaped = False
            elif ch == '\\':
                escaped = True
            elif ch == quote:
                quote = None
        else:
            if ch in ("'", '"'):
                quote = ch
            elif ch == '{':
                depth += 1
            elif ch == '}':
                depth -= 1
                if depth == 0:
                    return src[:start] + replacement + src[i + 1:]
        i += 1
    raise SystemExit('v10 unterminated block: ' + marker)


# ---------------------------------------------------------------------------
# Requested temporary policy: no SMS verification or management approval on
# initial registration. A valid mobile number + the user's chosen 4-digit PIN
# is enough. New representatives are signed in immediately after registration.
# Password recovery keeps its own OTP flow.
# ---------------------------------------------------------------------------
register = r'''if(post('register')){
    if(!okc())exit('درخواست نامعتبر');
    $name=trim((string)($_POST['name']??''));
    $mobile=sec_mobile((string)($_POST['mobile']??''));
    $city=trim((string)($_POST['city']??''));
    $regions=trim((string)($_POST['regions']??''));
    $p=(string)($_POST['password']??'');
    $pc=(string)($_POST['password_confirm']??'');
    $terms=!empty($_POST['terms']);
    $ref=preg_replace('/\D/','',(string)($_POST['ref']??''))??'';
    if(mb_strlen($name)<3||!preg_match('/^09\d{9}$/',$mobile)||$city===''||!preg_match('/^\d{4}$/',$p)||!hash_equals($p,$pc)||!$terms){
        fl('اطلاعات ثبت‌نام معتبر نیست. رمز باید دقیقاً ۴ رقم باشد و تکرار آن یکسان وارد شود.','err');
        go('./?mode=register');
    }
    if($ref!==''&&!preg_match('/^\d{4}$/',$ref)){
        fl('کد معرف باید ۴ رقمی باشد.','err');
        go('./?mode=register');
    }
    $rows=j(B);
    foreach($rows as $r)if(sec_mobile((string)($r['mobile']??''))===$mobile){
        fl('این شماره قبلاً ثبت شده است. با همان شماره موبایل و رمز ۴ رقمی وارد شوید.','err');
        go('./?mode=login');
    }
    $id='B'.bin2hex(random_bytes(6));
    $rows[]=[
        'id'=>$id,
        'code'=>code(),
        'name'=>$name,
        'mobile'=>$mobile,
        'city'=>$city,
        'regions'=>$regions,
        'password_hash'=>password_hash($p,PASSWORD_DEFAULT),
        'active'=>true,
        'mobile_verified'=>true,
        'mobile_verified_at'=>now(),
        'approval_status'=>'approved',
        'referrer_code'=>$ref,
        'daily_quota'=>0,
        'terms_version'=>'2026-10-18-v1',
        'terms_accepted_at'=>now(),
        'created_at'=>now(),
        'last_login_at'=>now()
    ];
    if(!sj(B,$rows)){
        fl('ثبت‌نام انجام نشد. دوباره تلاش کنید.','err');
        go('./?mode=register');
    }
    unset($_SESSION['pending_branch_id'],$_SESSION['pending_mobile']);
    session_regenerate_id(true);
    $_SESSION['bid']=$id;
    systemTick(true);
    fl('ثبت‌نام انجام شد و پنل شما آماده است.');
    go('./');
}'''
text = replace_block(text, "if(post('register')){", register)

# Remove only registration verification/resend. Recovery OTP is intentionally kept.
for marker in ["if(post('register_verify')){", "if(post('register_resend')){"]:
    if marker in text:
        text = replace_block(text, marker, '')

verify_page = "if(!$b&&!$isAdmin&&$mode==='verify'){"
if verify_page in text:
    text = replace_block(text, verify_page, '')

# Existing registrations that were stranded by the old OTP/approval flow become
# usable. Explicitly disabled accounts stay disabled because their status is not
# 'pending'. Run once per request but write only if a migration is needed.
migration = r'''
function ws_retire_registration_otp():void{
    $rows=j(B);$changed=false;
    foreach($rows as &$r){
        if((string)($r['approval_status']??'')==='pending'&&empty($r['active'])){
            $r['active']=true;
            $r['mobile_verified']=true;
            if(empty($r['mobile_verified_at']))$r['mobile_verified_at']=now();
            $r['approval_status']='approved';
            $changed=true;
        }
    }
    unset($r);
    if($changed)sj(B,$rows);
    unset($_SESSION['pending_branch_id'],$_SESSION['pending_mobile']);
}
ws_retire_registration_otp();
'''
action_marker = "$action=(string)($_POST['action']??$_GET['action']??'');"
if action_marker not in text:
    raise SystemExit('v10 action marker missing')
text = text.replace(action_marker, migration + "\n" + action_marker, 1)

# Remove outdated legal wording that implied manual activation.
text = text.replace(
    'ثبت‌نام به معنی درخواست همکاری است و فعال‌سازی نهایی پس از تأیید مدیریت انجام می‌شود.',
    'ثبت‌نام و ساخت پنل به‌صورت فوری انجام می‌شود.'
)
text = text.replace(
    'بعد از تأیید شماره، فعال‌سازی نهایی حساب توسط مدیریت انجام می‌شود.',
    'پس از ثبت‌نام می‌توانید مستقیماً وارد پنل و شروع به فعالیت کنید.'
)

# ---------------------------------------------------------------------------
# Tutorial gallery: generated training graphics are embedded as compact WebP
# data URIs, so there is no external image host or extra production asset.
# ---------------------------------------------------------------------------
images = []
for i in range(1, 7):
    p = support / f'tutorial-{i}.b64'
    if not p.is_file():
        raise SystemExit(f'v10 tutorial asset missing: {p.name}')
    data = p.read_text(encoding='utf-8').strip()
    if not data.startswith('UklG'):
        raise SystemExit(f'v10 tutorial asset invalid: {p.name}')
    images.append(data)

titles = [
    '۱. آشنایی با صفحه همکاری و شروع ثبت‌نام',
    '۲. تکمیل فرم ثبت‌نام اولیه',
    '۳. ورود با شماره موبایل و رمز ۴ رقمی',
    '۴. آشنایی با داشبورد نمایندگی',
    '۵. ثبت مشتری یا فرصت جدید',
    '۶. پیگیری پروژه‌ها و مشاهده پورسانت',
]

cards = []
for i, (title, data) in enumerate(zip(titles, images), 1):
    cards.append(
        '<article class="joinTrainingCard">'
        '<button type="button" class="joinTrainingImage" data-training-open="%d" aria-label="نمایش بزرگ آموزش %d">'
        '<img src="data:image/webp;base64,%s" alt="%s" loading="lazy" decoding="async">'
        '<span>برای مشاهده بزرگ‌تر بزنید</span></button>'
        '<div><b>%s</b><small>آموزش تصویری مرحله‌به‌مرحله</small></div></article>'
        % (i, i, data, title, title)
    )

gallery = (
    '<section class="joinTraining" id="join-training">'
    '<div class="joinSectionHead"><span>راهنمای تصویری</span><h2>کار با سیستم را مرحله‌به‌مرحله ببین</h2>'
    '<p>از ثبت‌نام و ورود تا ثبت مشتری، پیگیری پروژه و پورسانت؛ تمام مراحل اصلی با تصویر توضیح داده شده است.</p></div>'
    '<div class="joinTrainingGrid">' + ''.join(cards) + '</div>'
    '<div class="joinTrainingNote"><b>ورود خیلی ساده است:</b><span>نام کاربری همان شماره موبایل و رمز ورود همان PIN چهاررقمی‌ای است که هنگام ثبت‌نام تعیین می‌کنی.</span></div>'
    '</section>'
    '<div class="joinTrainingModal" id="joinTrainingModal" aria-hidden="true"><button type="button" class="joinTrainingClose" aria-label="بستن">×</button><img alt="آموزش بزرگ‌شده"></div>'
    '<script>(function(){var m=document.getElementById("joinTrainingModal"),mi=m&&m.querySelector("img");if(!m||!mi)return;document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest("[data-training-open]");if(b){var im=b.querySelector("img");if(im){mi.src=im.src;mi.alt=im.alt;m.classList.add("on");m.setAttribute("aria-hidden","false");document.body.style.overflow="hidden";}return;}if(e.target===m||e.target.closest(".joinTrainingClose")){m.classList.remove("on");m.setAttribute("aria-hidden","true");document.body.style.overflow="";}});document.addEventListener("keydown",function(e){if(e.key==="Escape"&&m.classList.contains("on")){m.classList.remove("on");m.setAttribute("aria-hidden","true");document.body.style.overflow="";}});})();</script>'
)

faq_marker = "echo '<section class=\"joinFaq\">"
if faq_marker not in text:
    raise SystemExit('v10 FAQ insertion marker missing')
text = text.replace(faq_marker, "echo '" + gallery.replace("'", "\\'") + "';\n    " + faq_marker, 1)

# Build guarantees: signup OTP must be gone, mobile+4PIN login remains, gallery exists.
for forbidden in ["register_verify", "register_resend", "mode==='verify'", 'تأیید شماره موبایل']:
    if forbidden in text:
        raise SystemExit('v10 stale registration OTP remains: ' + forbidden)
for required in [
    "'active'=>true",
    "'mobile_verified'=>true",
    "'approval_status'=>'approved'",
    "!preg_match('/^\\d{4}$/',$p)",
    "$_SESSION['bid']=$id",
    'name="password_confirm"',
    'name="terms"',
    'joinTrainingGrid',
    'data-training-open="6"',
    'شماره موبایل',
    'رمز ورود',
]:
    if required not in text:
        raise SystemExit('v10 invariant missing: ' + required)

index_path.write_text(text, encoding='utf-8')

css = r'''
/* WEBSAZA_DIRECT_ACCESS_GALLERY_V10 */
.joinTraining{max-width:1180px;margin:0 auto;padding:85px 22px 95px;direction:rtl}.joinTrainingGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.joinTrainingCard{background:#fff;border:1px solid #e1e6ea;border-radius:22px;overflow:hidden;box-shadow:0 14px 40px rgba(20,29,39,.07)}.joinTrainingImage{display:block;width:100%;padding:0;border:0;background:#111820;cursor:pointer;position:relative;overflow:hidden}.joinTrainingImage img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;transition:transform .25s ease}.joinTrainingImage:hover img{transform:scale(1.015)}.joinTrainingImage>span{position:absolute;left:12px;bottom:12px;padding:7px 10px;border-radius:999px;background:rgba(10,15,20,.78);color:#fff;font:700 10px/1.4 inherit;backdrop-filter:blur(8px)}.joinTrainingCard>div{padding:17px 18px;display:flex;flex-direction:column;gap:5px}.joinTrainingCard b{font-size:14px;color:#141a22}.joinTrainingCard small{font-size:10px;color:#89929e}.joinTrainingNote{margin-top:20px;background:#111820;color:#fff;border-radius:18px;padding:18px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}.joinTrainingNote b{color:#c8ff45;font-size:13px}.joinTrainingNote span{font-size:12px;color:#c6ced8;line-height:1.9}.joinTrainingModal{position:fixed;inset:0;z-index:99999;background:rgba(4,7,10,.92);display:none;align-items:center;justify-content:center;padding:20px}.joinTrainingModal.on{display:flex}.joinTrainingModal img{display:block;max-width:min(1200px,96vw);max-height:91vh;width:auto;height:auto;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.45)}.joinTrainingClose{position:fixed;top:18px;left:18px;width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.2);background:#fff;color:#111820;font:700 28px/1 Arial;cursor:pointer;z-index:2}
@media(max-width:700px){.joinTraining{padding:58px 12px 68px}.joinTrainingGrid{grid-template-columns:1fr;gap:12px}.joinTrainingCard{border-radius:16px}.joinTrainingCard>div{padding:13px 14px}.joinTrainingImage>span{font-size:9px}.joinTrainingNote{margin-top:14px;border-radius:15px;padding:15px}.joinTrainingModal{padding:8px}.joinTrainingModal img{max-width:98vw;border-radius:10px}.joinTrainingClose{top:10px;left:10px;width:40px;height:40px}}
'''
style = style_path.read_text(encoding='utf-8')
if 'WEBSAZA_DIRECT_ACCESS_GALLERY_V10' not in style:
    style_path.write_text(style + '\n' + css + '\n', encoding='utf-8')

print('WEBSAZA_DIRECT_ACCESS_GALLERY_V10_OK')
