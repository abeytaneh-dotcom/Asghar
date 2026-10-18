<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tehran');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
const D=__DIR__.'/_data';
function deny():never{http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo 'Forbidden';exit;}
if($_SERVER['REQUEST_METHOD']!=='GET')deny();
$token=(string)($_SERVER['HTTP_X_WEBSAZA_BACKUP_TOKEN']??'');if(!preg_match('/^[a-f0-9]{64}$/',$token))deny();
$f=D.'/_backup_download_token.json';$r=json_decode((string)@file_get_contents($f),true);if(!is_array($r)||empty($r['hash'])||(int)($r['expires']??0)<time()||!empty($r['used']))deny();
if(!hash_equals((string)$r['hash'],hash('sha256',$token)))deny();
$archive=dirname(__DIR__,2).'/websaza_backups/latest-export.tar.gz';if(!is_file($archive)||filesize($archive)<100){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Backup not ready';exit;}
// Mark the short-lived token used before streaming so it cannot be replayed.
$r['used']=true;$r['used_at']=date('c');@file_put_contents($f,json_encode($r,JSON_UNESCAPED_SLASHES),LOCK_EX);@chmod($f,0600);
header('Content-Type: application/gzip');header('Content-Length: '.filesize($archive));header('Content-Disposition: attachment; filename="websaza-backup.tar.gz"');
$h=fopen($archive,'rb');if(!$h){http_response_code(500);exit;}while(!feof($h)){echo fread($h,1048576);flush();}fclose($h);exit;
