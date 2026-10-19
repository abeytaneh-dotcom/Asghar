<?php
declare(strict_types=1);

require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-hardening.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=1800, stale-while-revalidate=86400');
header('X-Robots-Tag: noindex, nofollow');

function nzlc_norm(string $s): string {
    $s=strtr($s,['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه']);
    $s=preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\s\-_]+/u','',$s)??$s;
    return mb_strtolower(trim($s),'UTF-8');
}
function nzlc_cache_dir(): string {
    $dir=__DIR__.'/.marketplace-data/location-catalog';
    if(!is_dir($dir))@mkdir($dir,0750,true);
    return $dir;
}
function nzlc_read_cache(string $file): ?array {
    if(!is_file($file))return null;
    $raw=@file_get_contents($file);if($raw===false)return null;
    $data=json_decode($raw,true);return is_array($data)?$data:null;
}
function nzlc_write_cache(string $file,array $data): void {
    $raw=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if($raw===false)return;@file_put_contents($file,$raw,LOCK_EX);@chmod($file,0640);
}
function nzlc_get(string $url): ?array {
    if(!function_exists('curl_init'))return null;
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>12,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_HTTPHEADER=>[
            'Accept: application/json',
            'Content-Type: application/json',
            'Origin: https://divar.ir',
            'Referer: https://divar.ir/',
            'User-Agent: Mozilla/5.0 (compatible; Niyazestan/1.0; +https://websaza.ir/nz)'
        ]
    ]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false||$status<200||$status>=300)return null;
    $data=json_decode((string)$raw,true);return is_array($data)?$data:null;
}
function nzlc_cities(): array {
    $file=nzlc_cache_dir().'/divar-cities.json';$cached=nzlc_read_cache($file);
    if($cached&&is_file($file)&&filemtime($file)>time()-7*86400)return $cached;
    $fresh=nzlc_get('https://api.divar.ir/v8/places/cities');
    if($fresh&&isset($fresh['cities'])&&is_array($fresh['cities'])){nzlc_write_cache($file,$fresh);return $fresh;}
    return $cached??['cities'=>[]];
}
function nzlc_city_match(string $wanted,array $cities): ?array {
    $n=nzlc_norm($wanted);if($n==='')return null;
    $aliases=[
        'انزلی'=>'بندرانزلی','بندرانزلی'=>'انزلی','چابهار'=>'چاهبهار','چاهبهار'=>'چابهار',
        'گنبدکاووس'=>'گنبدکاووس','علیآبادکتول'=>'علیآباد','بندرترکمن'=>'ترکمن',
        'صدرا'=>'شهرجدیدصدرا','شهرجدیدصدرا'=>'صدرا','رباطکریم'=>'رباطکریم'
    ];
    $targets=[$n];if(isset($aliases[$n]))$targets[]=$aliases[$n];
    foreach($cities as$c){if(!is_array($c))continue;$cn=nzlc_norm((string)($c['name']??''));if(in_array($cn,$targets,true))return$c;}
    $matches=[];foreach($cities as$c){if(!is_array($c))continue;$cn=nzlc_norm((string)($c['name']??''));if($cn!==''&&(str_contains($cn,$n)||str_contains($n,$cn)))$matches[]=$c;}
    return count($matches)===1?$matches[0]:null;
}
function nzlc_tags($tags): array {
    if(!is_array($tags))return[];$out=[];
    foreach($tags as$t){
        if(is_string($t))$v=trim($t);
        elseif(is_array($t))$v=trim((string)($t['name']??$t['title']??$t['text']??$t['value']??''));
        else $v='';
        if($v!==''&&!in_array($v,$out,true))$out[]=$v;
    }
    return array_slice($out,0,12);
}
function nzlc_districts(string $cityId): array {
    if(!preg_match('/^\d{1,12}$/',$cityId))return[];
    $file=nzlc_cache_dir().'/divar-districts-'.$cityId.'.json';$cached=nzlc_read_cache($file);
    if($cached&&is_file($file)&&filemtime($file)>time()-7*86400)return $cached;
    $fresh=nzlc_get('https://api.divar.ir/v8/places/cities/'.rawurlencode($cityId).'/districts');
    if($fresh&&isset($fresh['districts'])&&is_array($fresh['districts'])){nzlc_write_cache($file,$fresh);return$fresh;}
    return $cached??['districts'=>[]];
}

$city=trim((string)($_GET['city']??''));
if($city===''||mb_strlen($city)>80){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_city'],JSON_UNESCAPED_UNICODE);exit;}
nzh_rate_hit('location-catalog|'.hash('sha256',nzh_ip()),240,3600,'تعداد درخواست موقعیت زیاد است. کمی بعد دوباره تلاش کنید.');

$catalog=nzlc_cities();$cityRow=nzlc_city_match($city,(array)($catalog['cities']??[]));
if(!$cityRow||empty($cityRow['id'])){
    echo json_encode(['ok'=>true,'city'=>$city,'city_id'=>null,'districts'=>[],'count'=>0,'source'=>'fallback'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
$cityId=(string)$cityRow['id'];$raw=nzlc_districts($cityId);$rows=[];
foreach((array)($raw['districts']??[])as$d){
    if(!is_array($d))continue;$name=trim((string)($d['name']??''));if($name==='')continue;
    $rows[]=[
        'id'=>(string)($d['id']??''),
        'name'=>$name,
        'slug'=>(string)($d['slug']??''),
        'second_slug'=>(string)($d['second_slug']??''),
        'tags'=>nzlc_tags($d['tags']??[]),
        'default_location'=>is_array($d['default_location']??null)?$d['default_location']:null
    ];
}
usort($rows,fn($a,$b)=>strcoll((string)$a['name'],(string)$b['name']));
echo json_encode(['ok'=>true,'city'=>(string)($cityRow['name']??$city),'city_id'=>$cityId,'districts'=>$rows,'count'=>count($rows),'source'=>'divar-public-catalog'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);