<?php
declare(strict_types=1);require_once __DIR__.'/error_bootstrap.php';
$status=(int)($_GET['s']??($_SERVER['REDIRECT_STATUS']??500));
$map=[403=>'HTTP-403',404=>'HTTP-404',413=>'HTTP-413',429=>'HTTP-429',500=>'HTTP-500'];
$code=$map[$status]??'ERR-999';
if(!isset($map[$status]))$status=500;
ts_html_fail($code,$status,'index.php');
