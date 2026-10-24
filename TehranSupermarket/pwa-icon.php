<?php
declare(strict_types=1);
$size=(int)($_GET['size']??192);if(!in_array($size,[192,512],true))$size=192;
header('Cache-Control: public, max-age=604800, immutable');
if(function_exists('imagecreatetruecolor')){
 header('Content-Type: image/png');$im=imagecreatetruecolor($size,$size);imagealphablending($im,true);imagesavealpha($im,true);
 $green=imagecolorallocate($im,17,130,59);$white=imagecolorallocate($im,255,255,255);$orange=imagecolorallocate($im,245,151,31);
 imagefilledrectangle($im,0,0,$size,$size,$green);$m=(int)round($size*.18);$w=$size-2*$m;
 imagefilledroundedrectangle_exists($im,$m,(int)($size*.34),$m+$w,(int)($size*.68),(int)($size*.05),$white);
 imagefilledrectangle($im,(int)($size*.28),(int)($size*.26),(int)($size*.35),(int)($size*.38),$white);
 imagefilledellipse($im,(int)($size*.36),(int)($size*.78),(int)($size*.13),(int)($size*.13),$orange);
 imagefilledellipse($im,(int)($size*.68),(int)($size*.78),(int)($size*.13),(int)($size*.13),$orange);
 imagefilledellipse($im,(int)($size*.73),(int)($size*.24),(int)($size*.12),(int)($size*.12),$orange);
 imagepng($im);imagedestroy($im);exit;
}
header('Content-Type: image/svg+xml; charset=utf-8');echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 512 512"><rect width="512" height="512" rx="96" fill="#11823b"/><path d="M126 167h45l33 187h194l39-134H190" fill="none" stroke="#fff" stroke-width="34" stroke-linecap="round" stroke-linejoin="round"/><circle cx="234" cy="398" r="27" fill="#f5971f"/><circle cx="365" cy="398" r="27" fill="#f5971f"/><circle cx="385" cy="126" r="31" fill="#f5971f"/></svg>';
function imagefilledroundedrectangle_exists($im,$x1,$y1,$x2,$y2,$r,$color){imagefilledrectangle($im,$x1+$r,$y1,$x2-$r,$y2,$color);imagefilledrectangle($im,$x1,$y1+$r,$x2,$y2-$r,$color);imagefilledellipse($im,$x1+$r,$y1+$r,$r*2,$r*2,$color);imagefilledellipse($im,$x2-$r,$y1+$r,$r*2,$r*2,$color);imagefilledellipse($im,$x1+$r,$y2-$r,$r*2,$r*2,$color);imagefilledellipse($im,$x2-$r,$y2-$r,$r*2,$r*2,$color);}
