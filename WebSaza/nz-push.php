<?php
declare(strict_types=1);

function nzpush_b64(string $v): string {return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
function nzpush_keys(): array {
    static $keys=null;if($keys!==null)return $keys;
    if(!function_exists('openssl_pkey_new'))throw new RuntimeException('سرویس اعلان روی سرور پشتیبانی نمی‌شود.');
    $file=nzstore_dir().'/push-vapid.json';$lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('سرویس اعلان در دسترس نیست.');
    try{
        if(is_file($file)){$keys=json_decode((string)file_get_contents($file),true);if(!is_array($keys)||empty($keys['private'])||empty($keys['public']))throw new RuntimeException('کلید اعلان نیازمند بررسی مدیر است.');return $keys;}
        $key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);if(!$key||!openssl_pkey_export($key,$pem))throw new RuntimeException('ساخت کلید اعلان ممکن نشد.');
        $details=openssl_pkey_get_details($key);$keys=['private'=>$pem,'public'=>nzpush_b64("\x04".$details['ec']['x'].$details['ec']['y'])];
        $tmp=$file.'.tmp';if(file_put_contents($tmp,json_encode($keys),LOCK_EX)===false)throw new RuntimeException('ذخیره کلید اعلان ممکن نشد.');chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('ذخیره کلید اعلان ممکن نشد.');return $keys;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function nzpush_host(string $endpoint): string {
    $u=parse_url($endpoint);$host=strtolower((string)($u['host']??''));
    if(($u['scheme']??'')!=='https'||isset($u['user'])||isset($u['pass'])||isset($u['fragment'])||($u['port']??443)!==443||strlen($endpoint)>2000)throw new RuntimeException('آدرس سرویس اعلان معتبر نیست.');
    if(!in_array($host,['fcm.googleapis.com','updates.push.services.mozilla.com'],true)&&!preg_match('/^(?:[a-z0-9-]+\.)*push\.apple\.com$/D',$host))throw new RuntimeException('این سرویس اعلان پشتیبانی نمی‌شود.');
    return $host;
}
function nzpush_jwt(string $host,array $keys): string {
    $head=nzpush_b64('{"typ":"JWT","alg":"ES256"}');$claims=nzpush_b64(json_encode(['aud'=>'https://'.$host,'exp'=>time()+3600,'sub'=>getenv('NZ_PUSH_SUBJECT')?:'https://websaza.ir/nz']));$input=$head.'.'.$claims;
    if(!openssl_sign($input,$der,$keys['private'],OPENSSL_ALGO_SHA256))throw new RuntimeException('امضای اعلان ممکن نشد.');
    // OpenSSL returns DER integers; ES256 JWT requires two 32-byte unsigned integers.
    $pos=2;if(ord($der[1])&128)$pos=2+(ord($der[1])&127);$raw='';
    for($i=0;$i<2;$i++){if(ord($der[$pos++])!==2)throw new RuntimeException('امضای نامعتبر.');$len=ord($der[$pos++]);$int=ltrim(substr($der,$pos,$len),"\x00");$pos+=$len;if(strlen($int)>32)throw new RuntimeException('امضای نامعتبر.');$raw.=str_pad($int,32,"\x00",STR_PAD_LEFT);}
    return $input.'.'.nzpush_b64($raw);
}
function nzpush_send(string $endpoint,array $keys): int {
    $host=nzpush_host($endpoint);$ips=gethostbynamel($host)?:[];$ip='';foreach($ips as $candidate)if(filter_var($candidate,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){$ip=$candidate;break;}
    if($ip==='')return 0;
    $ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'',CURLOPT_HTTPHEADER=>['TTL: 300','Urgency: high','Topic: niazestan-messages','Content-Length: 0','Authorization: vapid t='.nzpush_jwt($host,$keys).', k='.$keys['public']],CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>3]);curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $status;
}
/** Called after a successful data commit, never inside its transaction. */
function nzpush_dispatch(array $recipients): void {
    if(!$recipients||!function_exists('curl_init'))return;
    try{$db=nzc_db_read();if(empty($db['push_subscriptions']))return;$keys=nzpush_keys();$sent=0;foreach($db['push_subscriptions']??[] as $s){if(!in_array($s['user_id']??'',$recipients,true))continue;if(++$sent>6)break;try{$status=nzpush_send((string)$s['endpoint'],$keys);if($status<200||$status>=300)error_log('Niazestan push delivery status '.$status);}catch(Throwable $e){error_log('Niazestan push delivery unavailable');}}}catch(Throwable $e){error_log('Niazestan push unavailable');}
}
