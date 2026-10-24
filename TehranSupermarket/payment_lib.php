<?php
declare(strict_types=1);
require_once __DIR__.'/store_lib.php';
function ts_payment_config(PDO $pdo): array {return ['enabled'=>store_setting($pdo,'payment_enabled','0')==='1','merchant'=>trim(store_setting($pdo,'payment_merchant_id','')),'multiplier'=>max(1,(int)store_setting($pdo,'payment_multiplier','10'))];}
function ts_zarinpal_call(string $url,array $payload): array {
    $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Content-Length: '.strlen($body)]]);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($resp===false)throw new RuntimeException($err?:'ارتباط با درگاه برقرار نشد.');$data=json_decode((string)$resp,true);if(!is_array($data))throw new RuntimeException('پاسخ نامعتبر از درگاه.');if($code<200||$code>=300)throw new RuntimeException((string)($data['errors']['message']??'خطای درگاه پرداخت.'));return $data;
}
function ts_payment_request(PDO $pdo,array $order,string $callback): string {
    $cfg=ts_payment_config($pdo);if(!$cfg['enabled']||$cfg['merchant']==='')throw new RuntimeException('پرداخت آنلاین فعال نیست.');$amount=(int)$order['total']*$cfg['multiplier'];$payload=['merchant_id'=>$cfg['merchant'],'amount'=>$amount,'callback_url'=>$callback,'description'=>'سفارش #'.(int)$order['id'].' سوپرمارکت طهران','metadata'=>['mobile'=>(string)($order['phone']??'')]];$r=ts_zarinpal_call('https://api.zarinpal.com/pg/v4/payment/request.json',$payload);$code=(int)($r['data']['code']??0);$authority=(string)($r['data']['authority']??'');if($code!==100||$authority==='')throw new RuntimeException((string)($r['errors']['message']??'درگاه درخواست پرداخت را نپذیرفت.'));return $authority;
}
function ts_payment_verify(PDO $pdo,array $order,string $authority): array {
    $cfg=ts_payment_config($pdo);$amount=(int)$order['total']*$cfg['multiplier'];$r=ts_zarinpal_call('https://api.zarinpal.com/pg/v4/payment/verify.json',['merchant_id'=>$cfg['merchant'],'amount'=>$amount,'authority'=>$authority]);$code=(int)($r['data']['code']??0);if(!in_array($code,[100,101],true))throw new RuntimeException((string)($r['errors']['message']??'تأیید پرداخت ناموفق بود.'));return ['code'=>$code,'ref_id'=>(string)($r['data']['ref_id']??''),'card_pan'=>(string)($r['data']['card_pan']??'')];
}
