<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tehran');
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
const D=__DIR__.'/_data';
require_once __DIR__.'/platform-v3.php';
$target=$argv[1]??(dirname(__DIR__,2).'/websaza_export_'.date('Ymd_His').'.json');
$db=ws_db();if(!$db){fwrite(STDERR,"SQLite unavailable\n");exit(1);} 
$buckets=['branches','leads','assignments','events','referral_rewards','finance_payments','finance_remittances','finance_feedback','sms_queue','sms_log','manager_reports'];
$out=['version'=>'websaza-export-v5','created_at'=>ws_now(),'backend'=>ws_backend(),'buckets'=>[],'contacts'=>[],'audit'=>[],'finance_ledger'=>[],'admin_audit'=>[]];
foreach($buckets as $b)$out['buckets'][$b]=ws_store_read(D.'/'.$b.'.json');
foreach(['contacts','audit','finance_ledger','admin_audit'] as $t){$s=$db->query('SELECT * FROM '.$t);$out[$t]=$s?$s->fetchAll(PDO::FETCH_ASSOC):[];}
$j=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($j===false||file_put_contents($target,$j,LOCK_EX)===false){fwrite(STDERR,"Export failed\n");exit(2);}chmod($target,0600);echo "EXPORT_OK {$target}\n";
