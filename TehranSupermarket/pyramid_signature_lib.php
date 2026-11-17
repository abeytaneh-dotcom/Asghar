<?php
declare(strict_types=1);
/** Authorized, timestamp-bound signatures from EXSOM-J3.
 * A private key never leaves the Windows machine; only the public key is on the server.
 * All writes remain independently gated by protected website configuration.
 */
function ts_pyramid_verify_device_signature(): bool {
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))==='TRACE')return false;
    $file=__DIR__.'/storage/pyramid_bridge_public.pem';
    if(!is_file($file) || !function_exists('openssl_verify'))return false;
    $ts=(string)($_SERVER['HTTP_X_PYRAMID_TIMESTAMP']??'');
    $device=(string)($_SERVER['HTTP_X_DEVICE_ID']??'');
    $rid=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');
    $sig=(string)($_SERVER['HTTP_X_PYRAMID_SIGNATURE']??'');
    if($device!=='EXSOM-J3' || !preg_match('/^[0-9]{10,11}$/D',$ts)
       || abs(time()-(int)$ts)>120
       || strlen($rid)<12 || strlen($rid)>120
       || !preg_match('/^[A-Za-z0-9._:-]+$/D',$rid)
       || strlen($sig)<100 || strlen($sig)>1024) return false;
    $binary=base64_decode($sig,true);
    if($binary===false)return false;
    $body=file_get_contents('php://input');
    if(!is_string($body) || strlen($body)>8*1024*1024)return false;
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    $query=(string)($_SERVER['QUERY_STRING']??'');
    $canonical=implode("\n",['v1',$method,$query,$ts,$device,$rid,hash('sha256',$body)]);
    $pem=@file_get_contents($file);
    if(!is_string($pem)||strlen($pem)>5000)return false;
    return openssl_verify($canonical,$binary,$pem,OPENSSL_ALGO_SHA256)===1;
}
