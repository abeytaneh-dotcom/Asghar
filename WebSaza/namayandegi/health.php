<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Tehran');
const D=__DIR__.'/_data';
require_once __DIR__.'/platform-v3.php';
$files=['branches.json','leads.json','assignments.json','events.json','referral_rewards.json','finance_payments.json','finance_remittances.json','finance_feedback.json','sms_queue.json','sms_log.json','manager_reports.json'];
foreach($files as $name){$f=D.'/'.$name;if(is_file($f))ws_store_read($f);}
$leads=ws_store_read(D.'/leads.json');$imported=ws_import_contacts($leads);
$out=ws_health();$out['legacy_contacts_imported']=$imported;$out['php']=PHP_VERSION;$out['cron_expected']='smart-actions.php every minute';
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
