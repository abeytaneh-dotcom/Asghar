<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/websaza-v3-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);define('D',$dir);require __DIR__.'/platform-v3.php';
function t(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "OK: $m\n";}
t(ws_phone('0912 123 4567')==='09121234567','phone local normalize');
t(ws_phone('+98 912 123 4567')==='09121234567','phone international normalize');
$f=D.'/leads.json';file_put_contents($f,'[]');$rows=[['id'=>'L1','phone'=>'09121234567','business'=>'Test','created_at'=>ws_now()]];t(ws_store_write($f,$rows),'store write');$got=ws_store_read($f);t(count($got)===1&&($got[0]['id']??'')==='L1','store read');
t(ws_contact_touch('09121234567',['status'=>'ingested','lead_id'=>'L1','business'=>'Test']),'contact touch');t(ws_contact_seen('+989121234567'),'permanent normalized duplicate');
t(ws_score(['website_status'=>'ندارد','source_url'=>'https://example.com','proposal'=>'site','evidence'=>'public'])>=89,'lead scoring');
$h=ws_health();t(!empty($h['ok'])&&in_array($h['backend'],['sqlite-wal','json-fallback'],true),'health');
array_map('unlink',glob($dir.'/*')?:[]);@rmdir($dir);echo "SELF_TEST_V3_PASS\n";
