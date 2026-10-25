from pathlib import Path

support = Path(__file__).resolve().parent
root = support.parent / 'namayandegi'
index_path = root / 'index.php'
s = index_path.read_text(encoding='utf-8')
marker = 'WEBSAZA_ORDER_COUPON_V14'
if marker in s:
    print('WEBSAZA_ORDER_COUPON_V14_ALREADY')
    raise SystemExit(0)

# Shared coupon data store used by the public websaza.ir customer order endpoint.
needle = "const D=__DIR__.'/_data';"
if needle not in s:
    raise SystemExit('coupon patch: data constant marker missing')
s = s.replace(needle, needle + " const CPN=D.'/coupons.json';", 1)

init_needle = "if(!is_dir(D))@mkdir(D,0750,true);"
if init_needle not in s:
    raise SystemExit('coupon patch: data init marker missing')
s = s.replace(init_needle, init_needle + " if(!is_file(CPN))@file_put_contents(CPN,\"[]\\n\");", 1)

helpers = r'''
/* WEBSAZA_ORDER_COUPON_V14 */
function couponNorm(string $s):string{$s=mb_strtoupper(trim($s),'UTF-8');return preg_replace('/[^\\p{L}\\p{N}_-]+/u','',$s)??'';}
function couponRows():array{if(!is_file(CPN))@file_put_contents(CPN,"[]\\n");return j(CPN);}
function couponGenerate():string{do{$c='WEB'.random_int(100000,999999);$used=false;foreach(couponRows() as $r)if(couponNorm((string)($r['code']??''))===$c){$used=true;break;}}while($used);return $c;}
'''
anchor = "if(post('register'))"
if anchor not in s:
    raise SystemExit('coupon patch: register action marker missing')
s = s.replace(anchor, helpers + "\n" + anchor, 1)

actions = r'''
if(post('add_coupon')&&admin()){if(!okc())exit('درخواست نامعتبر');$code=couponNorm((string)($_POST['code']??''));if($code==='')$code=couponGenerate();$percent=(int)($_POST['percent']??0);if($percent<10||$percent>90){fl('درصد تخفیف باید بین ۱۰ تا ۹۰ باشد.','err');go('./?admin=1&view=coupons');}$rows=couponRows();foreach($rows as $r)if(couponNorm((string)($r['code']??''))===$code){fl('این کد تخفیف قبلاً ساخته شده است.','err');go('./?admin=1&view=coupons');}$rows[]=['id'=>'C'.bin2hex(random_bytes(5)),'code'=>$code,'percent'=>$percent,'active'=>true,'used_count'=>0,'created_at'=>now()];sj(CPN,$rows);fl('کد تخفیف '.$code.' با تخفیف '.$percent.'٪ ساخته شد.');go('./?admin=1&view=coupons');}
if(post('toggle_coupon')&&admin()){if(!okc())exit('درخواست نامعتبر');$id=(string)($_POST['id']??'');$rows=couponRows();foreach($rows as &$r)if((string)($r['id']??'')===$id){$r['active']=empty($r['active']);$r['updated_at']=now();break;}unset($r);sj(CPN,$rows);fl('وضعیت کد تخفیف تغییر کرد.');go('./?admin=1&view=coupons');}
if(post('delete_coupon')&&admin()){if(!okc())exit('درخواست نامعتبر');$id=(string)($_POST['id']??'');$rows=array_values(array_filter(couponRows(),fn($r)=>(string)($r['id']??'')!==$id));sj(CPN,$rows);fl('کد تخفیف حذف شد.');go('./?admin=1&view=coupons');}
'''
action_anchor = "if(post('add_order')&&admin())"
if action_anchor not in s:
    raise SystemExit('coupon patch: admin order marker missing')
s = s.replace(action_anchor, actions + "\n" + action_anchor, 1)

nav_old = "['dashboard'=>'نمای کلی','intake'=>'ثبت سفارش','distribution'=>'توزیع هوشمند'"
nav_new = "['dashboard'=>'نمای کلی','intake'=>'ثبت سفارش','coupons'=>'کدهای تخفیف','distribution'=>'توزیع هوشمند'"
if nav_old not in s:
    raise SystemExit('coupon patch: admin nav marker missing')
s = s.replace(nav_old, nav_new, 1)

coupon_view = r'''elseif($view==='coupons'){$coupons=couponRows();usort($coupons,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));echo '<div class="title"><div><h1>کدهای تخفیف</h1><p>برای سفارش مستقیم مشتری از سایت، کد تخفیف ۱۰ تا ۹۰ درصدی بساز و هر زمان خواستی فعال یا غیرفعال کن.</p></div></div><div class="grid"><section class="panel"><h2>ساخت کد جدید</h2><form method="post" class="form">'.cf().'<input type="hidden" name="action" value="add_coupon"><label>کد تخفیف<input name="code" maxlength="24" placeholder="مثلاً WEB20"><small>اگر خالی بگذاری، سیستم یک کد امن می‌سازد.</small></label><label>درصد تخفیف<input type="number" name="percent" min="10" max="90" value="10" required></label><button class="btn lime">ساخت کد تخفیف</button></form></section><section class="panel wide"><h2>کدهای ساخته‌شده</h2>';if(!$coupons)echo '<p>هنوز کد تخفیفی ساخته نشده است.</p>';else{echo '<div class="table"><table><tr><th>کد</th><th>تخفیف</th><th>وضعیت</th><th>استفاده</th><th>ساخته‌شده</th><th>عملیات</th></tr>';foreach($coupons as $cp){$active=!empty($cp['active']);echo '<tr><td><b style="direction:ltr;display:inline-block">'.e((string)($cp['code']??'')).'</b></td><td><span class="pill good">'.(int)($cp['percent']??0).'%</span></td><td><span class="pill '.($active?'good':'gray').'">'.($active?'فعال':'غیرفعال').'</span></td><td>'.(int)($cp['used_count']??0).'</td><td><small>'.e((string)($cp['created_at']??'')).'</small></td><td><div class="actions"><form method="post">'.cf().'<input type="hidden" name="action" value="toggle_coupon"><input type="hidden" name="id" value="'.e((string)($cp['id']??'')).'"><button class="btn light sm">'.($active?'غیرفعال':'فعال').' کردن</button></form><form method="post" onsubmit="return confirm(\'این کد حذف شود؟\')">'.cf().'<input type="hidden" name="action" value="delete_coupon"><input type="hidden" name="id" value="'.e((string)($cp['id']??'')).'"><button class="btn light sm" type="submit">حذف</button></form></div></td></tr>';}echo '</table></div>';}echo '</section></div>';}
'''
view_anchor = "elseif($view==='distribution')"
if view_anchor not in s:
    raise SystemExit('coupon patch: distribution view marker missing')
s = s.replace(view_anchor, coupon_view + "\n" + view_anchor, 1)

# Invariants: admin UI plus public-store bridge must be present exactly once.
for required in [marker, "view=coupons", "add_coupon", "toggle_coupon", "delete_coupon", "CPN=D.'/coupons.json'"]:
    if required not in s:
        raise SystemExit('coupon patch invariant missing: '+required)
index_path.write_text(s,encoding='utf-8')
print('WEBSAZA_ORDER_COUPON_V14_OK')
