<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';
ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Strict');if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');session_start();
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function respond(array $data,int $status=200): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['admin_id']))ts_json_fail('REQ-102',401);
if(!in_array((string)($_SESSION['admin_role']??''),['owner','manager'],true))ts_json_fail('AUTH-104',403);
if($_SERVER['REQUEST_METHOD']!=='POST')ts_json_fail('REQ-101',405);
if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??'')))ts_json_fail('REQ-102',419);
if(empty($_FILES['image_file']))ts_json_fail('IMG-301',422);
$f=$_FILES['image_file'];$uploadError=(int)($f['error']??UPLOAD_ERR_NO_FILE);
if($uploadError!==UPLOAD_ERR_OK){
    if(in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))ts_json_fail('IMG-303',413);
    if($uploadError===UPLOAD_ERR_NO_FILE)ts_json_fail('IMG-301',422);
    ts_json_fail('IMG-302',422);
}
$tmp=(string)($f['tmp_name']??'');$size=(int)($f['size']??0);
if($tmp===''||!is_uploaded_file($tmp)||$size<1)ts_json_fail('IMG-301',422);
if($size>96*1024*1024)ts_json_fail('IMG-303',413);
$dir=__DIR__.'/uploads/products';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))ts_json_fail('IMG-305',500);
$base='product_'.date('Ymd_His').'_'.bin2hex(random_bytes(6));$width=0;$height=0;$format='';$bytes=0;$relative='';
try{
    if(class_exists('Imagick')){
        $im=new Imagick();$im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY,256*1024*1024);$im->setResourceLimit(Imagick::RESOURCETYPE_MAP,512*1024*1024);$im->readImage($tmp);
        if($im->getNumberImages()>1)$im->setIteratorIndex(0);if(method_exists($im,'autoOrient'))$im->autoOrient();elseif(method_exists($im,'autoOrientImage'))$im->autoOrientImage();$im->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $w=$im->getImageWidth();$h=$im->getImageHeight();if($w<1||$h<1)throw new RuntimeException('bad_dimensions');$max=1800;if(max($w,$h)>$max)$im->thumbnailImage($max,$max,true,true);$im->stripImage();
        $webpSupported=!empty(Imagick::queryFormats('WEBP'));if($webpSupported){$format='webp';$im->setImageFormat('webp');$im->setImageCompressionQuality(84);if(method_exists($im,'setOption')){$im->setOption('webp:method','6');$im->setOption('webp:alpha-quality','90');}}else{$format='jpg';$im->setImageBackgroundColor('white');if($im->getImageAlphaChannel())$im=$im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);$im->setImageFormat('jpeg');$im->setImageCompression(Imagick::COMPRESSION_JPEG);$im->setImageCompressionQuality(86);}
        $path=$dir.'/'.$base.'.'.$format;if(!$im->writeImage($path))throw new RuntimeException('write_failed');$width=$im->getImageWidth();$height=$im->getImageHeight();$im->clear();$im->destroy();$relative='uploads/products/'.basename($path);$bytes=(int)filesize($path);
    }else{
        $raw=file_get_contents($tmp);if($raw===false)throw new RuntimeException('read_failed');$src=@imagecreatefromstring($raw);if(!$src)ts_json_fail('IMG-304',422);$w=imagesx($src);$h=imagesy($src);if($w<1||$h<1)throw new RuntimeException('bad_dimensions');
        $max=1800;$scale=min(1,$max/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));$dst=imagecreatetruecolor($nw,$nh);imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,255,255,255,127);imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
        if(function_exists('imagewebp')){$format='webp';$path=$dir.'/'.$base.'.webp';$ok=imagewebp($dst,$path,84);}else{$format='jpg';$path=$dir.'/'.$base.'.jpg';$white=imagecreatetruecolor($nw,$nh);$bg=imagecolorallocate($white,255,255,255);imagefill($white,0,0,$bg);imagecopy($white,$dst,0,0,0,0,$nw,$nh);$ok=imagejpeg($white,$path,86);imagedestroy($white);}imagedestroy($src);imagedestroy($dst);if(!$ok)throw new RuntimeException('write_failed');$width=$nw;$height=$nh;$relative='uploads/products/'.basename($path);$bytes=(int)filesize($path);
    }
}catch(Throwable $e){ts_log_internal($e,'IMG-305');ts_json_fail('IMG-305',422);}
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'websaza.ir');$basePath=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/tehran/image_upload.php'))),'/');$url=$scheme.'://'.$host.$basePath.'/'.$relative;
respond(['ok'=>true,'url'=>$url,'path'=>$relative,'format'=>$format,'width'=>$width,'height'=>$height,'bytes'=>$bytes,'original_bytes'=>$size]);
