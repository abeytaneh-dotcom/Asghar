from pathlib import Path

p = Path('WebSaza/namayandegi/index.php')
s = p.read_text(encoding='utf-8')

if 'REFERRAL_REWARD_V1' in s:
    print('already patched')
    raise SystemExit(0)

def must(old: str, new: str, count: int = 1):
    global s
    n = s.count(old)
    if n < count:
        raise SystemExit(f'MISSING PATTERN ({n}): {old[:180]}')
    s = s.replace(old, new, count)

must(
    "const D=__DIR__.'/_data'; const B=D.'/branches.json'; const L=D.'/leads.json'; const A=D.'/assignments.json'; const AD=D.'/admin.json'; const S=D.'/settings.json'; const EV=D.'/events.json';",
    "const D=__DIR__.'/_data'; const B=D.'/branches.json'; const L=D.'/leads.json'; const A=D.'/assignments.json'; const AD=D.'/admin.json'; const S=D.'/settings.json'; const EV=D.'/events.json'; const R=D.'/referral_rewards.json'; // REFERRAL_REWARD_V1"
)
must("foreach([B,L,A,EV] as $f)", "foreach([B,L,A,EV,R] as $f)")

marker = "function post(string $a):bool{return $_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')===$a;} function money(string $x):string{return preg_replace('/\\D/','',$x)??'';}"
helpers = marker + "\n" + """function referrerFor(array $child):?array{$rid=(string)($child['referrer_id']??'');$rcode=(string)($child['referrer_code']??'');foreach(j(B) as $x){if($rid!==''&&($x['id']??'')===$rid)return $x;if($rcode!==''&&($x['code']??'')===$rcode)return $x;}return null;}
function rewardAmount(array $referrer):int{$v=array_key_exists('referral_reward_amount',$referrer)?(int)$referrer['referral_reward_amount']:200000;return max(0,$v);}
function rewardRowsFor(string $bid):array{$x=array_values(array_filter(j(R),fn($r)=>($r['referrer_branch_id']??'')===$bid&&($r['status']??'credited')==='credited'));usort($x,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return $x;}
function rewardTotal(string $bid):int{$n=0;foreach(rewardRowsFor($bid) as $r)$n+=(int)($r['amount']??0);return $n;}
function awardReferral(string $childId,string $aid,string $lid,string $deal=''):int{$child=branchBy($childId);if(!$child)return 0;$ref=referrerFor($child);if(!$ref||($ref['id']??'')===$childId)return 0;$rows=j(R);foreach($rows as $r)if(($r['assignment_id']??'')===$aid&&($r['type']??'referral_order')==='referral_order')return 0;$amount=rewardAmount($ref);if($amount<=0)return 0;$rows[]=['id'=>'R'.bin2hex(random_bytes(6)),'type'=>'referral_order','referrer_branch_id'=>$ref['id'],'referred_branch_id'=>$childId,'assignment_id'=>$aid,'lead_id'=>$lid,'amount'=>$amount,'deal_value'=>$deal,'status'=>'credited','created_at'=>now()];sj(R,$rows);return $amount;}
function referredBranches(string $bid):array{$me=branchBy($bid);if(!$me)return [];$code=(string)($me['code']??'');return array_values(array_filter(j(B),fn($x)=>(string)($x['referrer_id']??'')===$bid||($code!==''&&(string)($x['referrer_code']??'')===$code)));}"""
must(marker, helpers)

old_update = "if(post('update_assignment')&&($b=br())){if(!okc())exit('درخواست نامعتبر');$aid=(string)($_POST['assignment_id']??'');$st=(string)($_POST['status']??'new');if(!in_array($st,['new','won','interested','followup','no_answer','cancelled','bad_info'],true))$st='new';$note=trim((string)($_POST['note']??''));$fu=trim((string)($_POST['followup_at']??''));$pt=trim((string)($_POST['project_type']??''));$dv=money((string)($_POST['deal_value']??''));$rows=j(A);$lid='';foreach($rows as &$r)if(($r['id']??'')===$aid&&($r['branch_id']??'')===$b['id']){$lid=(string)$r['lead_id'];$r['status']=$st;$r['note']=$note;$r['followup_at']=$fu;$r['project_type']=$pt;$r['deal_value']=$dv;$r['updated_at']=now();break;}unset($r);sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);fl('نتیجه تماس ذخیره شد.');go('./?view=leads#lead-'.$aid);}"
new_update = "if(post('update_assignment')&&($b=br())){if(!okc())exit('درخواست نامعتبر');$aid=(string)($_POST['assignment_id']??'');$st=(string)($_POST['status']??'new');if(!in_array($st,['new','won','interested','followup','no_answer','cancelled','bad_info'],true))$st='new';$note=trim((string)($_POST['note']??''));$fu=trim((string)($_POST['followup_at']??''));$pt=trim((string)($_POST['project_type']??''));$dv=money((string)($_POST['deal_value']??''));$rows=j(A);$lid='';foreach($rows as &$r)if(($r['id']??'')===$aid&&($r['branch_id']??'')===$b['id']){$lid=(string)$r['lead_id'];$r['status']=$st;$r['note']=$note;$r['followup_at']=$fu;$r['project_type']=$pt;$r['deal_value']=$dv;$r['updated_at']=now();break;}unset($r);sj(A,$rows);event((string)$b['id'],$aid,$lid,$st,$note);$rw=0;if($st==='won')$rw=awardReferral((string)$b['id'],$aid,$lid,$dv);fl($rw>0?'نتیجه تماس ذخیره شد و پاداش معرفی به حساب معرف اضافه شد.':'نتیجه تماس ذخیره شد.');go('./?view=leads#lead-'.$aid);}"
must(old_update, new_update)

toggle = "if(post('toggle_branch')&&admin()){if(!okc())exit('درخواست نامعتبر');$id=(string)($_POST['id']??'');$rows=j(B);foreach($rows as &$r)if(($r['id']??'')===$id){$r['active']=empty($r['active']);break;}unset($r);sj(B,$rows);fl('وضعیت نمایندگی تغییر کرد.');go('./?admin=1&view=branches');}"
save_reward = toggle + "\n" + "if(post('save_referral_amount')&&admin()){if(!okc())exit('درخواست نامعتبر');$id=(string)($_POST['id']??'');$amount=max(0,(int)money((string)($_POST['amount']??'0')));$rows=j(B);foreach($rows as &$r)if(($r['id']??'')===$id){$r['referral_reward_amount']=$amount;break;}unset($r);sj(B,$rows);fl('مبلغ پاداش معرفی این نمایندگی ذخیره شد.');go('./?admin=1&view=referrals');}"
must(toggle, save_reward)

nav_old = "function nav(string $v,array $b=null,bool $adm=false):void{$it=$adm?['dashboard'=>'نمای کلی','branches'=>'نمایندگی‌ها','leads'=>'بانک فرصت‌ها','activity'=>'فعالیت و نتایج','settings'=>'تنظیمات']:['dashboard'=>'داشبورد','leads'=>'فرصت‌های امروز','followups'=>'پیگیری‌ها','history'=>'سوابق','profile'=>'حساب من','help'=>'راهنمای فروش'];"
nav_new = "function nav(string $v,array $b=null,bool $adm=false):void{$it=$adm?['dashboard'=>'نمای کلی','branches'=>'نمایندگی‌ها','leads'=>'بانک فرصت‌ها','activity'=>'فعالیت و نتایج','referrals'=>'پاداش معرفی','settings'=>'تنظیمات']:['dashboard'=>'داشبورد','leads'=>'فرصت‌های امروز','followups'=>'پیگیری‌ها','history'=>'سوابق','referrals'=>'معرفی و پاداش','profile'=>'حساب من','help'=>'راهنمای فروش'];"
must(nav_old, nav_new)

activity_marker = "elseif($view==='activity'){"
admin_referrals = """elseif($view==='referrals'){$rr=j(R);usort($rr,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));$sum=0;foreach($rr as $x)if(($x['status']??'credited')==='credited')$sum+=(int)($x['amount']??0);echo '<div class=\"title\"><div><h1>پاداش معرفی</h1><p>مبلغ پاداش هر معرف را جداگانه تعیین کن. هر سفارش قطعی نماینده معرفی‌شده فقط یک‌بار پاداش می‌سازد و این ارتباط دائمی است.</p></div><span class=\"kpi\">کل اعتبار ثبت‌شده: '.number_format($sum).' تومان</span></div><section class=\"panel\"><h2>مبلغ پاداش هر نمایندگی</h2><div class=\"table\"><table><tr><th>نمایندگی</th><th>کد معرفی</th><th>نماینده معرفی‌شده</th><th>مبلغ هر سفارش قطعی</th><th>عملیات</th></tr>';foreach(array_reverse(j(B)) as $x){$kids=referredBranches((string)$x['id']);echo '<tr><td><b>'.e((string)$x['name']).'</b></td><td>'.e((string)$x['code']).'</td><td>'.count($kids).'</td><td>'.number_format(rewardAmount($x)).' تومان</td><td><form method=\"post\" class=\"row\">'.cf().'<input type=\"hidden\" name=\"action\" value=\"save_referral_amount\"><input type=\"hidden\" name=\"id\" value=\"'.e((string)$x['id']).'\"><input style=\"max-width:150px\" inputmode=\"numeric\" name=\"amount\" value=\"'.e((string)rewardAmount($x)).'\"><button class=\"btn light sm\">ذخیره مبلغ</button></form></td></tr>';}echo '</table></div></section><section class=\"panel\"><h2>گردش پاداش‌ها</h2><div class=\"table\"><table><tr><th>معرف</th><th>نماینده معرفی‌شده</th><th>سفارش</th><th>پاداش</th><th>ارزش قرارداد</th><th>زمان</th></tr>';if(!$rr)echo '<tr><td colspan=\"6\">هنوز پاداشی ثبت نشده است.</td></tr>';foreach(array_slice($rr,0,500) as $x){$ref=branchBy((string)($x['referrer_branch_id']??''));$child=branchBy((string)($x['referred_branch_id']??''));$ld=lead((string)($x['lead_id']??''));echo '<tr><td><b>'.e((string)($ref['name']??'-')).'</b></td><td>'.e((string)($child['name']??'-')).'</td><td>'.e((string)($ld['business']??$ld['name']??'-')).'</td><td>'.number_format((int)($x['amount']??0)).' تومان</td><td>'.(!empty($x['deal_value'])?number_format((int)$x['deal_value']).' تومان':'-').'</td><td>'.e((string)($x['created_at']??'')).'</td></tr>';}echo '</table></div></section>';}"""
must(activity_marker, admin_referrals + "\n" + activity_marker)

profile_marker = "elseif($view==='profile'){"
branch_referrals = """elseif($view==='referrals'){$kids=referredBranches((string)$b['id']);$rewards=rewardRowsFor((string)$b['id']);$total=rewardTotal((string)$b['id']);$link='https://websaza.ir/namayandegi/?mode=register&ref='.(string)$b['code'];echo '<div class=\"title\"><div><h1>معرفی و پاداش</h1><p>لینک یا کد خودت را برای ثبت‌نام نمایندگی جدید بفرست. هر سفارش قطعی آن نماینده، برای همیشه برای شما پاداش ایجاد می‌کند.</p></div></div><div class=\"stats\"><div><span>کد معرفی</span><b>'.e((string)$b['code']).'</b></div><div><span>نماینده معرفی‌شده</span><b>'.count($kids).'</b></div><div><span>سفارش پاداش‌دار</span><b>'.count($rewards).'</b></div><div><span>موجودی پاداش</span><b>'.number_format($total).' <small>تومان</small></b></div></div><div class=\"grid\"><section class=\"panel wide\"><h2>لینک اختصاصی معرفی</h2><label>لینک معرفی<input readonly value=\"'.e($link).'\"></label><div class=\"actions\"><button class=\"btn lime copy\" type=\"button\" data-copy=\"'.e($link).'\">کپی لینک معرفی</button><button class=\"btn light copy\" type=\"button\" data-copy=\"'.e((string)$b['code']).'\">کپی کد '.e((string)$b['code']).'</button></div><p class=\"muted\">پاداش فعلی شما برای هر سفارش قطعی نماینده معرفی‌شده: '.number_format(rewardAmount($b)).' تومان. مدیر می‌تواند مبلغ مخصوص حساب شما را تغییر دهد.</p></section><section class=\"panel\"><h2>قانون پاداش</h2><p>۱. نماینده جدید با کد یا لینک شما ثبت‌نام می‌کند.</p><p>۲. فقط وقتی سفارش خودش را «سفارش قطعی» ثبت کند پاداش ساخته می‌شود.</p><p>۳. برای هر سفارش فقط یک‌بار پاداش ثبت می‌شود.</p><p>۴. این ارتباط همیشگی است؛ سفارش‌های قطعی آینده همان نماینده هم پاداش دارند.</p></section></div><section class=\"panel\"><h2>نماینده‌های معرفی‌شده</h2><div class=\"table\"><table><tr><th>نام</th><th>شهر</th><th>کد</th><th>عضویت</th><th>سفارش قطعی</th></tr>';if(!$kids)echo '<tr><td colspan=\"5\">هنوز نماینده‌ای با کد شما ثبت نشده است.</td></tr>';foreach($kids as $x){$bc=counts(bas((string)$x['id']));echo '<tr><td><b>'.e((string)$x['name']).'</b></td><td>'.e((string)($x['city']??'')).'</td><td>'.e((string)($x['code']??'')).'</td><td>'.e((string)($x['created_at']??'')).'</td><td>'.$bc['won'].'</td></tr>';}echo '</table></div></section><section class=\"panel\"><h2>گردش پاداش‌های من</h2><div class=\"table\"><table><tr><th>نماینده</th><th>سفارش</th><th>پاداش</th><th>زمان</th></tr>';if(!$rewards)echo '<tr><td colspan=\"4\">هنوز پاداشی ثبت نشده است.</td></tr>';foreach($rewards as $x){$child=branchBy((string)($x['referred_branch_id']??''));$ld=lead((string)($x['lead_id']??''));echo '<tr><td>'.e((string)($child['name']??'-')).'</td><td>'.e((string)($ld['business']??$ld['name']??'-')).'</td><td><b>'.number_format((int)($x['amount']??0)).' تومان</b></td><td>'.e((string)($x['created_at']??'')).'</td></tr>';}echo '</table></div></section>';}"""
must(profile_marker, branch_referrals + "\n" + profile_marker)

must(
    "https://websaza.ir/namayandegi/?ref='.e((string)$b['code']).'",
    "https://websaza.ir/namayandegi/?mode=register&ref='.e((string)$b['code']).'",
    2
)
must(
    "<p class=\"muted\">پورسانت فعلی: '.(int)$c['commission_percent'].'٪ طبق شرایط همکاری.</p>",
    "<p class=\"muted\">پاداش معرفی فعلی شما: '.number_format(rewardAmount($b)).' تومان برای هر سفارش قطعی نماینده‌ای که معرفی کرده‌اید.</p><a class=\"btn lime\" href=\"?view=referrals\">مشاهده پاداش‌ها</a>"
)

p.write_text(s, encoding='utf-8')
print('patched referral rewards successfully')
