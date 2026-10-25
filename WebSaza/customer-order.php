<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Tehran');

const D=__DIR__.'/namayandegi/_data';
const L=D.'/leads.json';
const B=D.'/branches.json';
const A=D.'/assignments.json';
const S=D.'/settings.json';
const CPN=D.'/coupons.json';
const RATE=D.'/public_order_rate.json';
require_once __DIR__.'/namayandegi/platform-v3.php';

function out(array $x,int $code=200):never{http_response_code($code);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function clean(string $s,int $max=180):string{$s=trim(strip_tags($s));return mb_substr($s,0,$max,'UTF-8');}
function norm(string $s):string{$s=trim(mb_strtolower($s,'UTF-8'));$s=str_replace(['ي','ك','ۀ','ة'],['ی','ک','ه','ه'],$s);return preg_replace('/\s+/u',' ',$s)??$s;}
function couponCode(string $s):string{$s=mb_strtoupper(trim($s),'UTF-8');return preg_replace('/[^\p{L}\p{N}_-]+/u','',$s)??'';}
function coupons():array{if(!is_dir(D))@mkdir(D,0750,true);if(!is_file(CPN))ws_raw_write(CPN,[]);return ws_raw_read(CPN);}
function couponFind(string $code):?array{$code=couponCode($code);if($code==='')return null;foreach(coupons() as $r)if(is_array($r)&&couponCode((string)($r['code']??''))===$code&&!empty($r['active'])){$p=(int)($r['percent']??0);if($p>=10&&$p<=90)return $r;}return null;}
function rateOk():bool{$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$key=hash('sha256',$ip);$now=time();$rows=ws_raw_read(RATE);$hits=[];foreach((array)($rows[$key]??[]) as $t)if((int)$t>$now-600)$hits[]=(int)$t;if(count($hits)>=5)return false;$hits[]=$now;$rows[$key]=$hits;foreach($rows as $k=>$v)if(!is_array($v)||!array_filter($v,fn($t)=>(int)$t>$now-86400))unset($rows[$k]);ws_raw_write(RATE,$rows);return true;}
function branchQuota(array $b):int{$cfg=array_merge(['daily_quota'=>20],ws_raw_read(S));$q=(int)($b['daily_quota']??0);if($q<=0)$q=(int)($cfg['daily_quota']??20);return max(1,min(100,$q));}
function matchArea(array $lead,array $b):bool{$lc=norm((string)($lead['city']??''));$bc=norm((string)($b['city']??''));if($lc!==''&&$bc!==''&&$lc!==$bc)return false;$lr=norm((string)($lead['region']??''));if($lr==='')return true;$raw=(string)($b['regions']??'');$parts=preg_split('/[,،;\n]+/u',$raw)?:[];$parts=array_values(array_filter(array_map(fn($x)=>norm((string)$x),$parts)));if(!$parts)return true;foreach($parts as $r)if($r===$lr||str_contains($lr,$r)||str_contains($r,$lr))return true;return false;}
function repScore(string $bid,array $assignments):int{$assigned=0;$handled=0;$won=0;foreach($assignments as $a){if(!is_array($a)||(string)($a['branch_id']??'')!==$bid||($a['status']??'new')==='reassigned')continue;$assigned++;$st=(string)($a['status']??'new');if($st!=='new'){$handled++;if($st==='won')$won++;}}$resp=$assigned?($handled/$assigned):0.0;$conv=$handled?($won/$handled):0.0;return max(1,min(100,$handled<5?50:(int)round(($conv*80)+($resp*20))));}
function dispatchPublic(array $lead):bool{$branches=ws_store_read(B);$as=ws_store_read(A);$today=date('Y-m-d');$cand=[];foreach($branches as $b){if(!is_array($b)||empty($b['active'])||(array_key_exists('mobile_verified',$b)&&empty($b['mobile_verified']))||!matchArea($lead,$b))continue;$bid=(string)($b['id']??'');if($bid==='')continue;$n=0;foreach($as as $a)if(is_array($a)&&(string)($a['branch_id']??'')===$bid&&($a['date']??'')===$today&&($a['current']??true)!==false&&($a['status']??'new')!=='reassigned')$n++;$q=branchQuota($b);if($n>=$q)continue;$score=repScore($bid,$as);$cand[]=['b'=>$b,'pressure'=>$n/max(1,$score),'score'=>$score,'today'=>$n];}usort($cand,function($x,$y){$p=$x['pressure']<=>$y['pressure'];if($p!==0)return $p;$s=$y['score']<=>$x['score'];return $s!==0?$s:($x['today']<=>$y['today']);});if(!$cand)return false;$b=$cand[0]['b'];$as[]=['id'=>'A'.bin2hex(random_bytes(5)),'branch_id'=>(string)$b['id'],'lead_id'=>(string)$lead['id'],'date'=>$today,'status'=>'new','note'=>'','followup_at'=>'','project_type'=>(string)($lead['project_type']??''),'deal_value'=>'','seen_at'=>'','current'=>true,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>''];return ws_store_write(A,$as);}
function incrementCoupon(string $code):void{$code=couponCode($code);if($code==='')return;$rows=coupons();foreach($rows as &$r)if(is_array($r)&&couponCode((string)($r['code']??''))===$code){$r['used_count']=(int)($r['used_count']??0)+1;$r['last_used_at']=date('Y-m-d H:i:s');break;}unset($r);ws_raw_write(CPN,$rows);}

if($_SERVER['REQUEST_METHOD']==='GET')out(['ok'=>true,'status'=>'ready','service'=>'websaza-customer-order']);
if($_SERVER['REQUEST_METHOD']!=='POST')out(['ok'=>false,'message'=>'method_not_allowed'],405);
$raw=(string)file_get_contents('php://input');$body=json_decode($raw,true);if(!is_array($body))$body=$_POST;
$action=(string)($body['action']??'submit');
if($action==='check_coupon'){$c=couponFind((string)($body['coupon']??''));out($c?['ok'=>true,'valid'=>true,'code'=>(string)$c['code'],'percent'=>(int)$c['percent']]:['ok'=>true,'valid'=>false,'message'=>'کد تخفیف معتبر یا فعال نیست.']);}
if($action!=='submit')out(['ok'=>false,'message'=>'action_invalid'],400);
if(clean((string)($body['website']??''))!=='')out(['ok'=>true,'message'=>'ثبت شد.']);
if(!rateOk())out(['ok'=>false,'message'=>'تعداد درخواست‌ها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.'],429);
$name=clean((string)($body['name']??''),80);$phone=ws_phone((string)($body['phone']??''));$business=clean((string)($body['business']??''),120);$city=clean((string)($body['city']??''),80);$region=clean((string)($body['region']??''),100);$project=clean((string)($body['project_type']??''),100);$budget=clean((string)($body['budget']??''),80);$note=clean((string)($body['note']??''),700);$coupon=couponFind((string)($body['coupon']??''));
if(mb_strlen($name,'UTF-8')<2||$phone===''||$city===''||$project==='')out(['ok'=>false,'message'=>'نام، موبایل، شهر و نوع پروژه را کامل کنید.'],422);
$discount=$coupon?(int)$coupon['percent']:0;$couponValue=$coupon?(string)$coupon['code']:'';$id='L'.bin2hex(random_bytes(6));$orderCode='WS-'.date('ymd').'-'.strtoupper(substr((string)$id,-5));
$proposal='درخواست مستقیم مشتری برای '.$project.($discount>0?' با کد تخفیف '.$couponValue.' ('.$discount.'٪)':'');
$lead=['id'=>$id,'name'=>$name,'phone'=>$phone,'business'=>$business,'city'=>$city,'region'=>$region,'category'=>$project,'project_type'=>$project,'budget'=>$budget,'customer_note'=>$note,'coupon_code'=>$couponValue,'discount_percent'=>$discount,'order_code'=>$orderCode,'customer_order'=>true,'proposal'=>$proposal,'script'=>'سلام وقت بخیر، سفارش شما از سایت وب ساز ثبت شده. برای تکمیل نیازسنجی و ارائه پیشنهاد تماس گرفتیم.','source'=>'سفارش مستقیم سایت','active'=>true,'created_at'=>date('Y-m-d H:i:s')];
$rows=ws_store_read(L);$rows[]=$lead;if(!ws_store_write(L,$rows))out(['ok'=>false,'message'=>'ثبت سفارش انجام نشد. لطفاً دوباره تلاش کنید.'],500);$assigned=dispatchPublic($lead);if($couponValue!=='')incrementCoupon($couponValue);ws_audit('customer_order',$id,$phone,['order_code'=>$orderCode,'project_type'=>$project,'coupon'=>$couponValue,'discount_percent'=>$discount,'assigned'=>$assigned]);
out(['ok'=>true,'message'=>'سفارش شما با موفقیت ثبت شد.','order_code'=>$orderCode,'discount_percent'=>$discount,'coupon_code'=>$couponValue,'assigned'=>$assigned]);
