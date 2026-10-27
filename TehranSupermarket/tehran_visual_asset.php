<?php
declare(strict_types=1);
$asset=(string)($_GET['asset']??'');
$base=__DIR__.'/assets/';
$parts=[];$type='';
if($asset==='hero'){$parts=['hero_v28_01.b64','hero_v28_02.b64','hero_v28_03.b64','hero_v28_04.b64','hero_v28_05.b64','hero_v28_06.b64'];$type='image/jpeg';}
elseif($asset==='ai'){$parts=['ai_v28_01.b64','ai_v28_02.b64'];$type='image/webp';}
else{http_response_code(404);exit;}
$b64='';foreach($parts as $f){$p=$base.$f;if(!is_file($p)){http_response_code(404);exit;}$b64.=preg_replace('/\s+/','',(string)file_get_contents($p));}
$bin=base64_decode($b64,true);if($bin===false){http_response_code(500);exit;}
header('Content-Type: '.$type);header('Content-Length: '.strlen($bin));header('Cache-Control: public, max-age=31536000, immutable');header('X-Content-Type-Options: nosniff');echo $bin;exit;
