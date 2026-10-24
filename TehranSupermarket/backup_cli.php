<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
date_default_timezone_set('Asia/Tehran');
$config=__DIR__.'/config.php';if(!is_file($config)){fwrite(STDERR,"config missing\n");exit(2);} $c=require $config;
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);require_once __DIR__.'/backup_lib.php';$s=ts_run_backup($pdo);echo 'BACKUP_OK '.($s['database_file']??'')."\n";}catch(Throwable $e){fwrite(STDERR,"backup failed\n");exit(3);}
