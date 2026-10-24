<?php
declare(strict_types=1);

function ts_b64url(string $s): string { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
function ts_vapid_file(): string { return __DIR__.'/storage/vapid_key.php'; }
function ts_vapid_keys(): array {
    $file=ts_vapid_file();
    if(is_file($file)){
        $v=require $file;
        if(is_array($v)&&!empty($v['private_pem'])&&!empty($v['public']))return $v;
    }
    if(!function_exists('openssl_pkey_new'))throw new RuntimeException('OpenSSL unavailable');
    $res=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
    if(!$res)throw new RuntimeException('VAPID key generation failed');
    $pem='';if(!openssl_pkey_export($res,$pem))throw new RuntimeException('VAPID private key export failed');
    $d=openssl_pkey_get_details($res);$x=$d['ec']['x']??'';$y=$d['ec']['y']??'';
    if(strlen($x)!==32||strlen($y)!==32)throw new RuntimeException('VAPID public key failed');
    $v=['private_pem'=>$pem,'public'=>ts_b64url("\x04".$x.$y)];
    @file_put_contents($file,"<?php\nreturn ".var_export($v,true).";\n",LOCK_EX);@chmod($file,0600);
    return $v;
}
function ts_der_length(string $der,int &$o): int {
    $n=ord($der[$o++]);if(($n&0x80)===0)return $n;$bytes=$n&0x7f;$len=0;for($i=0;$i<$bytes;$i++)$len=($len<<8)|ord($der[$o++]);return $len;
}
function ts_der_to_jose(string $der): string {
    $o=0;if(ord($der[$o++])!==0x30)throw new RuntimeException('Bad ES256 signature');ts_der_length($der,$o);
    if(ord($der[$o++])!==0x02)throw new RuntimeException('Bad ES256 R');$rl=ts_der_length($der,$o);$r=substr($der,$o,$rl);$o+=$rl;
    if(ord($der[$o++])!==0x02)throw new RuntimeException('Bad ES256 S');$sl=ts_der_length($der,$o);$s=substr($der,$o,$sl);
    $r=ltrim($r,"\x00");$s=ltrim($s,"\x00");$r=str_pad(substr($r,-32),32,"\x00",STR_PAD_LEFT);$s=str_pad(substr($s,-32),32,"\x00",STR_PAD_LEFT);
    return $r.$s;
}
function ts_vapid_jwt(string $endpoint): array {
    $u=parse_url($endpoint);if(!$u||empty($u['scheme'])||empty($u['host']))throw new RuntimeException('Bad push endpoint');
    $aud=$u['scheme'].'://'.$u['host'].(!empty($u['port'])?':'.$u['port']:'');$keys=ts_vapid_keys();
    $head=ts_b64url(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_UNESCAPED_SLASHES));
    $body=ts_b64url(json_encode(['aud'=>$aud,'exp'=>time()+43200,'sub'=>'mailto:admin@websaza.ir'],JSON_UNESCAPED_SLASHES));
    $input=$head.'.'.$body;$sig='';$pk=openssl_pkey_get_private($keys['private_pem']);if(!$pk||!openssl_sign($input,$sig,$pk,OPENSSL_ALGO_SHA256))throw new RuntimeException('VAPID sign failed');
    return [$input.'.'.ts_b64url(ts_der_to_jose($sig)),$keys['public']];
}
function ts_push_public_key(): string { return (string)ts_vapid_keys()['public']; }
function ts_push_send_endpoint(string $endpoint): bool {
    [$jwt,$pub]=ts_vapid_jwt($endpoint);$ch=curl_init($endpoint);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['TTL: 120','Urgency: high','Authorization: vapid t='.$jwt.', k='.$pub,'Content-Length: 0']]);
    $r=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return $r!==false&&$code>=200&&$code<300;
}
function ts_push_notify_new_order(PDO $pdo,int $orderId): void {
    try{
        $rows=$pdo->query('SELECT id,endpoint FROM push_subscriptions ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){$ok=false;$code=0;try{$endpoint=(string)$row['endpoint'];[$jwt,$pub]=ts_vapid_jwt($endpoint);$ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['TTL: 120','Urgency: high','Topic: order'.$orderId,'Authorization: vapid t='.$jwt.', k='.$pub,'Content-Length: 0']]);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$ok=$resp!==false&&$code>=200&&$code<300;}catch(Throwable $e){}
            if($ok){$s=$pdo->prepare('UPDATE push_subscriptions SET last_success_at=NOW(),last_error_at=NULL WHERE id=?');$s->execute([(int)$row['id']]);}
            elseif(in_array($code,[404,410],true)){$s=$pdo->prepare('DELETE FROM push_subscriptions WHERE id=?');$s->execute([(int)$row['id']]);}
            else{$s=$pdo->prepare('UPDATE push_subscriptions SET last_error_at=NOW() WHERE id=?');$s->execute([(int)$row['id']]);}
        }
    }catch(Throwable $e){}
}
