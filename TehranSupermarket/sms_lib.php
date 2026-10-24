<?php
declare(strict_types=1);
require_once __DIR__.'/store_lib.php';

function ts_sms_e164(string $phone): string {
    $p=preg_replace('/\D+/','',$phone)??'';
    if(preg_match('/^09\d{9}$/',$p))return '+98'.substr($p,1);
    if(preg_match('/^98\d{10}$/',$p))return '+'.$p;
    return '';
}
function ts_queue_sms(PDO $pdo,?int $orderId,string $phone,string $event,string $message): void {
    $phone=preg_replace('/\D+/','',$phone)??'';if(!preg_match('/^09\d{9}$/',$phone))return;
    try{if($orderId){$q=$pdo->prepare("SELECT COUNT(*) FROM sms_outbox WHERE order_id=? AND event_key=? AND status IN ('queued','sent')");$q->execute([$orderId,$event]);if((int)$q->fetchColumn()>0)return;}$st=$pdo->prepare("INSERT INTO sms_outbox(order_id,phone,event_key,message_text,status,created_at) VALUES(?,?,?,?, 'queued',NOW())");$st->execute([$orderId,$phone,$event,mb_substr($message,0,500)]);}catch(Throwable $e){}
}
function ts_sms_order_event(PDO $pdo,int $orderId,string $event): void {
    try{
        $st=$pdo->prepare('SELECT o.id,o.total,o.status,c.name,c.phone FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=?');$st->execute([$orderId]);$o=$st->fetch();if(!$o)return;
        $name=trim((string)$o['name']);$prefix=$name!==''?$name.' عزیز، ':'';
        $texts=['created'=>$prefix.'سفارش #'.$orderId.' در سوپرمارکت طهران ثبت شد. مبلغ '.number_format((int)$o['total']).' تومان.','ready'=>$prefix.'سفارش #'.$orderId.' آماده ارسال است.','out'=>$prefix.'سفارش #'.$orderId.' تحویل پیک شد و در مسیر شماست.','delivered'=>$prefix.'سفارش #'.$orderId.' تحویل شد. از خرید شما سپاسگزاریم.','cancelled'=>$prefix.'سفارش #'.$orderId.' لغو شد. در صورت نیاز با فروشگاه تماس بگیرید.'];
        if(isset($texts[$event]))ts_queue_sms($pdo,$orderId,(string)$o['phone'],$event,$texts[$event]);
        if($event==='created'){try{require_once __DIR__.'/push_lib.php';ts_push_notify_new_order($pdo,$orderId);}catch(Throwable $e){}}
    }catch(Throwable $e){}
}
function ts_sms_http(string $url,array $headers,string $payload): array {
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);return [$resp,$code,$err];
}
function ts_process_sms_queue(PDO $pdo,int $limit=5): array {
    if(store_setting($pdo,'sms_enabled','0')!=='1')return ['sent'=>0,'failed'=>0,'disabled'=>true];
    $provider=store_setting($pdo,'sms_provider','ippanel');$apiToken=trim(store_setting($pdo,'sms_api_token',''));$from=trim(store_setting($pdo,'sms_from_number',''));$webhook=trim(store_setting($pdo,'sms_webhook_url',''));$webToken=trim(store_setting($pdo,'sms_webhook_token',''));
    if($provider==='ippanel'&&($apiToken===''||$from===''))return ['sent'=>0,'failed'=>0,'disabled'=>true];
    if($provider==='webhook'&&($webhook===''||!filter_var($webhook,FILTER_VALIDATE_URL)||!str_starts_with($webhook,'https://')))return ['sent'=>0,'failed'=>0,'disabled'=>true];
    $rows=$pdo->query("SELECT * FROM sms_outbox WHERE status='queued' AND attempts<5 ORDER BY id LIMIT ".max(1,min(20,$limit)))->fetchAll();$sent=0;$failed=0;
    foreach($rows as $r){$resp=false;$code=0;$err='';
        if($provider==='ippanel'){$to=ts_sms_e164((string)$r['phone']);if($to===''){$err='invalid recipient';}else{$sender=str_starts_with($from,'+')?$from:($from!==''&&$from[0]==='0'?'+98'.substr($from,1):$from);$payload=json_encode(['sending_type'=>'webservice','from_number'=>$sender,'message'=>$r['message_text'],'params'=>['recipients'=>[$to]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);[$resp,$code,$err]=ts_sms_http('https://edge.ippanel.com/v1/api/send',['Content-Type: application/json','Accept: application/json','Authorization: '.$apiToken],$payload);}}
        else{$payload=json_encode(['phone'=>$r['phone'],'message'=>$r['message_text'],'event'=>$r['event_key'],'order_id'=>$r['order_id']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$headers=['Content-Type: application/json','Accept: application/json'];if($webToken!=='')$headers[]='Authorization: Bearer '.$webToken;[$resp,$code,$err]=ts_sms_http($webhook,$headers,$payload);}
        $ok=$resp!==false&&$code>=200&&$code<300;$st=$pdo->prepare('UPDATE sms_outbox SET status=?,attempts=attempts+1,provider_response=?,sent_at=? WHERE id=?');$st->execute([$ok?'sent':'queued',mb_substr($ok?(string)$resp:($err?:('HTTP '.$code.' '.(string)$resp)),0,2000),$ok?date('Y-m-d H:i:s'):null,(int)$r['id']]);if($ok)$sent++;else$failed++;
    }
    return ['sent'=>$sent,'failed'=>$failed,'disabled'=>false];
}
