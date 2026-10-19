<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-hardening.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=86400');
header('X-Robots-Tag: noindex, nofollow');

$lat=$_GET['lat']??null;$lon=$_GET['lon']??null;
if(!is_numeric($lat)||!is_numeric($lon)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_coordinates']);exit;}
$lat=(float)$lat;$lon=(float)$lon;
if($lat<24||$lat>40.5||$lon<43||$lon>64){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'out_of_bounds']);exit;}
nzh_rate_hit('geocode|'.hash('sha256',nzh_ip()),40,3600,'تعداد درخواست موقعیت زیاد است. کمی بعد دوباره تلاش کنید.');
$dir=__DIR__.'/.marketplace-data/geocode-cache';if(!is_dir($dir))@mkdir($dir,0750,true);
$key=hash('sha256',round($lat,4).'|'.round($lon,4));$file=$dir.'/'.$key.'.json';
if(is_file($file)&&filemtime($file)>time()-30*86400){$cached=@file_get_contents($file);if($cached!==false){echo $cached;exit;}}
$url='https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=fa&zoom=18&addressdetails=1&lat='.rawurlencode((string)$lat).'&lon='.rawurlencode((string)$lon);
$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: Niyazestan/1.0 (+https://websaza.ir/nz)']]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if($raw===false||$status<200||$status>=300){http_response_code(502);echo json_encode(['ok'=>false,'error'=>'geocoder_unavailable']);exit;}
$data=json_decode((string)$raw,true);if(!is_array($data)){http_response_code(502);echo json_encode(['ok'=>false,'error'=>'invalid_geocoder_response']);exit;}
$out=json_encode(['ok'=>true,'display_name'=>(string)($data['display_name']??''),'address'=>(array)($data['address']??[])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
if($out===false){http_response_code(500);echo json_encode(['ok'=>false]);exit;}@file_put_contents($file,$out,LOCK_EX);@chmod($file,0640);echo $out;
