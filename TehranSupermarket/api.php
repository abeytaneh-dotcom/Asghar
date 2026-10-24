<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';
session_start();
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function http_get(string $url, int $timeout=9): ?string {
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; TehranSupermarket/1.0)',CURLOPT_ENCODING=>'']);
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        return ($body!==false && $code>=200 && $code<400)?(string)$body:null;
    }
    $ctx=stream_context_create(['http'=>['timeout'=>$timeout,'header'=>"User-Agent: Mozilla/5.0 (compatible; TehranSupermarket/1.0)\r\n"]]);
    $body=@file_get_contents($url,false,$ctx); return $body===false?null:$body;
}
function clean_image_url(string $url): ?string {
    $url=html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8');
    if (!filter_var($url,FILTER_VALIDATE_URL)) return null;
    $p=parse_url($url); if (($p['scheme']??'')!=='https') return null;
    return $url;
}
function image_host_source(string $url): string {
    $h=strtolower((string)parse_url($url,PHP_URL_HOST));
    if (str_contains($h,'okala')) return 'اکالا';
    if (str_contains($h,'modiseh')) return 'مدیسه';
    if (str_contains($h,'snapp')) return 'اسنپ‌مارکت';
    if (str_contains($h,'digikala')) return 'دیجی‌کالا جت';
    if (str_contains($h,'openfoodfacts')) return 'Open Food Facts';
    return 'وب';
}
function bing_source_images(string $q): array {
    $siteQuery=$q.' (site:okala.com OR site:modiseh.com OR site:snapp.market OR site:digikalajet.com)';
    $url='https://www.bing.com/images/search?q='.rawurlencode($siteQuery).'&form=HDRSC3&first=1';
    $html=http_get($url,10); if(!$html) return [];
    $results=[];
    if (preg_match_all('/\bm="([^"]+)"/u',$html,$m)) {
        foreach($m[1] as $raw){
            $json=html_entity_decode($raw,ENT_QUOTES|ENT_HTML5,'UTF-8');
            $d=json_decode($json,true); if(!is_array($d)) continue;
            $img=clean_image_url((string)($d['murl']??'')); if(!$img) continue;
            $page=clean_image_url((string)($d['purl']??''));
            $title=trim(strip_tags((string)($d['t']??$q)));
            $results[]=['image'=>$img,'title'=>$title!==''?$title:$q,'page'=>$page,'source'=>image_host_source($img)];
            if(count($results)>=36) break;
        }
    }
    return $results;
}
function off_images(string $q): array {
    $fields='code,product_name,brands,quantity,image_front_url,image_url';
    $url='https://world.openfoodfacts.org/cgi/search.pl?search_terms='.rawurlencode($q).'&search_simple=1&action=process&json=1&page_size=20&fields='.rawurlencode($fields);
    $body=http_get($url,9); if(!$body) return [];
    $d=json_decode($body,true); if(!is_array($d)) return [];
    $r=[];foreach(($d['products']??[]) as $p){$img=clean_image_url((string)($p['image_front_url']??$p['image_url']??''));if(!$img)continue;$name=trim((string)($p['product_name']??''));$brand=trim((string)($p['brands']??''));$qty=trim((string)($p['quantity']??''));$r[]=['image'=>$img,'title'=>trim($name.' '.$brand.' '.$qty),'page'=>null,'source'=>'Open Food Facts','barcode'=>(string)($p['code']??''),'name'=>$name,'brand'=>$brand,'quantity'=>$qty];}
    return $r;
}

if(empty($_SESSION['admin_id']))ts_json_fail('REQ-102',401);
$configFile=__DIR__.'/config.php';if(!file_exists($configFile))ts_json_fail('SYS-100',503);
$c=require $configFile;
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);require_once __DIR__.'/schema.php';ensure_product_schema($pdo);}catch(Throwable $e){ts_log_internal($e,'SYS-101');ts_json_fail('SYS-101',503);}

$action=$_GET['action']??'';
if($action==='barcode_lookup'){
    $barcode=preg_replace('/[^0-9A-Za-z\-_.]/','',trim((string)($_GET['barcode']??'')));
    if($barcode==='')ts_json_fail('REQ-101',422);
    $st=$pdo->prepare('SELECT id,barcode,name,brand,quantity_label,category,image_path,image_source,price,stock,active FROM products WHERE barcode=? LIMIT 1');$st->execute([$barcode]);$p=$st->fetch();
    out(['ok'=>true,'found'=>(bool)$p,'product'=>$p?:null]);
}
if($action==='order_items'){
    $orderId=max(0,(int)($_GET['order_id']??0));
    if($orderId<=0)ts_json_fail('REQ-101',422);
    $st=$pdo->prepare('SELECT oi.product_id,oi.product_name,oi.unit_price,oi.quantity,p.barcode FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC');
    $st->execute([$orderId]);
    $items=$st->fetchAll();
    out(['ok'=>true,'order_id'=>$orderId,'items'=>$items]);
}
if($action==='image_search'){
    $q=trim((string)($_GET['q']??'')); if(mb_strlen($q)<2)ts_json_fail('REQ-101',422);
    $items=array_merge(bing_source_images($q),off_images($q));
    $seen=[];$unique=[];foreach($items as $it){$key=preg_replace('/[?#].*/','',$it['image']);if(isset($seen[$key]))continue;$seen[$key]=1;$unique[]=$it;if(count($unique)>=48)break;}
    out(['ok'=>true,'query'=>$q,'items'=>$unique,'count'=>count($unique)]);
}
ts_json_fail('REQ-101',404);
