<?php
declare(strict_types=1);
$size=(int)($_GET['size']??192);if(!in_array($size,[192,512],true))$size=192;
$source=__DIR__.'/assets/tehran-icon-192.jpg.b64';
$b64=@file_get_contents($source);$jpg=$b64!==false?base64_decode(trim($b64),true):false;
if($jpg===false){http_response_code(404);exit;}
header('Cache-Control: public, max-age=86400');
if($size===192||!function_exists('imagecreatefromstring')){header('Content-Type: image/jpeg');echo $jpg;exit;}
$src=@imagecreatefromstring($jpg);if(!$src){header('Content-Type: image/jpeg');echo $jpg;exit;}
$dst=imagecreatetruecolor($size,$size);$white=imagecolorallocate($dst,255,255,255);imagefill($dst,0,0,$white);imagecopyresampled($dst,$src,0,0,0,0,$size,$size,imagesx($src),imagesy($src));
header('Content-Type: image/jpeg');imagejpeg($dst,null,90);imagedestroy($src);imagedestroy($dst);
