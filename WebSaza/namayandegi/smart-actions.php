<?php
declare(strict_types=1);
session_name('WEBSAZA_NAMAYANDEGI');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/namayandegi/']);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Tehran');

const D=__DIR__.'/_data';
const B=D.'/branches.json';
const L=D.'/leads.json';
const A=D.'/assignments.json';
const S=D.'/settings.json';
const EV=D.'/events.json';
const R=D.'/referral_rewards.json';

function jx(string $f):array{$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}
function sjx(string $f,array $x):bool{$l=fopen($f.'.lock','c');if(!$l||!flock($l,LOCK_EX))return false;try{$t=$f.'.tmp.'.bin2hex(random_bytes(3));$q=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($q===false||file_put_contents($t,$q,LOCK_EX)===false)return false;@chmod($t,0640);return rename($t,$f);}finally{flock($l,LOCK_UN);fclose($l);}}
function nx():string{return date('Y-m-d H:i:s');}
function tdx():string{return date('Y-m-d');}
function gx(string $u):never{header('Location: '.$u);exit;}
function flx(string $m,string $t='ok'):void{$_SESSION['fl']=[$m,$t];}
function normx(string $s):string{$s=trim(mb_strtolower($s,'UTF-8'));$s=str_replace(['ي','ك','ۀ','ة'],['ی','ک','ه','ه'],$s);return preg_replace('/\s+/u',' ',$s)??$s;}
function splitx(string $s):array{$p=preg_split('/[,،;\n]+/u',$s)?:[];return array_values(array_filter(array_map(fn($x)=>normx((string)$x),$p)));}
function cfgx():array{return array_merge(['daily_quota'=>20,'commission_percent'=>30,'special_interested_commission'=>40],jx(S));}
function branchx(string $id):?array{foreach(jx(B) as $x)if((string)($x['id']??'')===$id)return $x;return null;}
function leadx(string $id):?array{foreach(jx(L) as $x)if((string)($x['id']??'')===$id)return $x;return null;}
function quotax(array $b):int{$q=(int)($b['daily_quota']??0);if($q<=0)$q=(int)(cfgx()['daily_quota']??20);return max(1,min(100,$q));}
function currentx():array{return array_values(array_filter(jx(A),fn($a)=>($a['current']??true)!==false&&($a['status']??'new')!=='reassigned'));}
function todayx(string $bid):int{$n=0;foreach(currentx() as $a)if((string)($a['branch_id']??'')===$bid&&($a['date']??'')===tdx())$n++;return $n;}
function statsx(string $bid):array{$assigned=0;$handled=0;$won=0;foreach(jx(A) as $a){if((string)($a['branch_id']??'')!==$bid)continue;$st=(string)($a['status']??'new');if($st==='reassigned')continue;$assigned++;if($st!=='new'){$handled++;if($st==='won')$won++;}}$resp=$assigned?($handled/$assigned):0.0;$conv=$handled?($won/$handled):0.0;$score=$handled<5?50:(int)round(($conv*80)+($resp*20));return ['score'=>max(1,min(100,$score)),'handled'=>$handled,'won'=>$won];}
function areaExactx(array $lead,array $b):bool{$lc=normx((string)($lead['city']??''));$bc=normx((string)($b['city']??''));if($lc!==''&&$bc!==''&&$lc!==$bc)return false;$lr=normx((string)($lead['region']??''));if($lr==='')return true;$br=splitx((string)($b['regions']??''));if(!$br)return true;foreach($br as $r)if($r===$lr||str_contains($lr,$r)||str_contains($r,$lr))return true;return false;}
function sameCityx(array $lead,array $b):bool{$lc=normx((string)($lead['city']??''));$bc=normx((string)($b['city']??''));return $lc===''||$bc===''||$lc===$bc;}
function previousx(string $lid):array{$out=[];foreach(jx(A) as $a)if((string)($a['lead_id']??'')===$lid)$out[(string)($a['branch_id']??'')]=true;return array_keys($out);}
function candidatesx(array $lead,array $exclude,bool $exact=true):array{$ex=array_fill_keys($exclude,true);$out=[];foreach(jx(B) as $b){$id=(string)($b['id']??'');if(!$id||empty($b['active'])||isset($ex[$id]))continue;if($exact?!areaExactx($lead,$b):!sameCityx($lead,$b))continue;$today=todayx($id);if($today>=quotax($b))continue;$s=statsx($id);$out[]=['b'=>$b,'score'=>$s['score'],'today'=>$today];}usort($out,function($x,$y){$d=$y['score']<=>$x['score'];return $d!==0?$d:($x['today']<=>$y['today']);});return $out;}
function eventx(string $bid,string $aid,string $lid,string $status,string $note):void{$rows=jx(EV);$rows[]=['id'=>'E'.bin2hex(random_bytes(5)),'branch_id'=>$bid,'assignment_id'=>$aid,'lead_id'=>$lid,'status'=>$status,'note'=>$note,'created_at'=>nx()];if(count($rows)>25000)$rows=array_slice($rows,-25000);sjx(EV,$rows);}
function refRewardx(string $childId,string $aid,string $lid,string $deal=''):int{$child=branchx($childId);if(!$child)return 0;$rid=(string)($child['referrer_id']??'');$rcode=(string)($child['referrer_code']??'');$ref=null;foreach(jx(B) as $x){if($rid!==''&&(string)($x['id']??'')===$rid){$ref=$x;break;}if($rcode!==''&&(string)($x['code']??'')===$rcode){$ref=$x;break;}}if(!$ref||($ref['id']??'')===$childId)return 0;$rows=jx(R);foreach($rows as $r)if(($r['assignment_id']??'')===$aid&&($r['type']??'referral_order')==='referral_order')return 0;$amount=max(0,(int)(array_key_exists('referral_reward_amount',$ref)?$ref['referral_reward_amount']:200000));if($amount<=0)return 0;$rows[]=['id'=>'R'.bin2hex(random_bytes(6)),'type'=>'referral_order','referrer_branch_id'=>$ref['id'],'referred_branch_id'=>$childId,'assignment_id'=>$aid,'lead_id'=>$lid,'amount'=>$amount,'deal_value'=>$deal,'status'=>'credited','created_at'=>nx()];sjx(R,$rows);return $amount;}

if($_SERVER['REQUEST_METHOD']!=='POST'||($_POST['action']??'')!=='update_assignment')gx('./');
if(!isset($_POST['csrf'],$_SESSION['csrf'])||!hash_equals((string)$_SESSION['csrf'],(string)$_POST['csrf']))exit('درخواست نامعتبر');
$bid=(string)($_SESSION['bid']??'');$branch=branchx($bid);if(!$branch){flx('نشست شما منقضی شده است. دوباره وارد شوید.','err');gx('./?mode=login');}
$aid=(string)($_POST['assignment_id']??'');$st=(string)($_POST['status']??'new');$note=trim((string)($_POST['note']??''));$fu=trim((string)($_POST['followup_at']??''));$pt=trim((string)($_POST['project_type']??''));$dv=preg_replace('/\D/','',(string)($_POST['deal_value']??''))??'';
$allowed=['new','won','interested','followup','no_answer','cancelled','bad_info','interested_transfer'];if(!in_array($st,$allowed,true))$st='new';
if($st!=='new'&&mb_strlen($note)<3){flx('برای ثبت نتیجه، دلیل یا توضیح تماس الزامی است.','err');gx('./?view=leads#lead-'.$aid);}

$rows=jx(A);$idx=-1;$lid='';foreach($rows as $i=>$r)if((string)($r['id']??'')===$aid&&(string)($r['branch_id']??'')===$bid&&($r['current']??true)!==false){$idx=$i;$lid=(string)($r['lead_id']??'');break;}
if($idx<0||$lid===''){flx('این سفارش دیگر در اختیار شما نیست.','err');gx('./?view=leads');}
if(($rows[$idx]['status']??'')==='won'&&$st!=='won')$st='won';

if($st==='interested_transfer'){
  $lead=leadx($lid);if(!$lead){flx('پرونده مشتری پیدا نشد.','err');gx('./?view=leads');}
  $cand=candidatesx($lead,previousx($lid),true);if(!$cand)$cand=candidatesx($lead,previousx($lid),false);
  if(!$cand){flx('مشتری راغب ثبت شد، اما فعلاً نماینده قوی‌ترِ دارای ظرفیت در این شهر پیدا نشد. سفارش نزد شما باقی ماند.','err');gx('./?view=leads#lead-'.$aid);}
  $target=$cand[0]['b'];$commission=max(0,min(100,(int)(cfgx()['special_interested_commission']??40)));
  $rows[$idx]['current']=false;$rows[$idx]['status']='reassigned';$rows[$idx]['handoff_type']='interested';$rows[$idx]['handoff_reason']=$note;$rows[$idx]['reassigned_at']=nx();$rows[$idx]['updated_at']=nx();$rows[$idx]['note']=$note;$rows[$idx]['seen_at']=$rows[$idx]['seen_at']?:nx();
  $newid='A'.bin2hex(random_bytes(5));$carried='ارجاع ویژه مشتری راغب | دلیل نماینده قبلی: '.$note.' | پورسانت ویژه: '.$commission.'%';
  $rows[]=['id'=>$newid,'branch_id'=>$target['id'],'lead_id'=>$lid,'date'=>tdx(),'status'=>'new','note'=>$carried,'followup_at'=>$fu,'project_type'=>$pt,'deal_value'=>$dv,'seen_at'=>'','current'=>true,'special_lead'=>true,'handoff_type'=>'interested','handoff_reason'=>$note,'previous_branch_id'=>$bid,'commission_percent'=>$commission,'created_at'=>nx(),'updated_at'=>''];
  sjx(A,$rows);eventx($bid,$aid,$lid,'reassigned','ارجاع مشتری راغب به نماینده قوی‌تر؛ دلیل: '.$note);eventx((string)$target['id'],$newid,$lid,'new',$carried);
  flx('مشتری راغب به نماینده قوی‌تر منتقل شد. دلیل تماس همراه پرونده ارسال شد و پورسانت ویژه '.$commission.'٪ برای این سفارش ثبت شد.');gx('./?view=history');
}

$rows[$idx]['status']=$st;$rows[$idx]['note']=$note;$rows[$idx]['followup_at']=$fu;$rows[$idx]['project_type']=$pt;$rows[$idx]['deal_value']=$dv;$rows[$idx]['seen_at']=$rows[$idx]['seen_at']?:nx();$rows[$idx]['updated_at']=nx();sjx(A,$rows);eventx($bid,$aid,$lid,$st,$note);$rw=0;if($st==='won')$rw=refRewardx($bid,$aid,$lid,$dv);flx($rw>0?'نتیجه تماس با دلیل ذخیره شد و پاداش معرفی هم ثبت شد.':'نتیجه تماس و دلیل آن ذخیره شد.');gx('./?view=leads#lead-'.$aid);
