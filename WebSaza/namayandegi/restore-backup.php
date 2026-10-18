<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tehran');
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(($argc??0)<2){fwrite(STDERR,"Usage: php restore-backup.php YYYYMMDD_HHMMSS\n");exit(2);} 
$name=(string)$argv[1];if(!preg_match('/^\d{8}_\d{6}$/',$name)){fwrite(STDERR,"Invalid snapshot name\n");exit(2);} 
const D=__DIR__.'/_data';$base=dirname(__DIR__,2).'/websaza_backups';$src=$base.'/'.$name;$manifest=$src.'/manifest.json';if(!is_dir($src)||!is_file($manifest)||!is_file($src.'/websaza.sqlite3')){fwrite(STDERR,"Snapshot not found\n");exit(3);} 
$m=json_decode((string)file_get_contents($manifest),true);if(!is_array($m)||($m['version']??'')!=='backup-v1'){fwrite(STDERR,"Invalid manifest\n");exit(3);}foreach((array)($m['files']??[]) as $rel=>$hash){$f=$src.'/'.$rel;if(!is_file($f)||!hash_equals((string)$hash,hash_file('sha256',$f))){fwrite(STDERR,"Checksum failed: {$rel}\n");exit(4);}}
$guard=D.'/.restore.lock';$fh=fopen($guard,'c');if(!$fh||!flock($fh,LOCK_EX)){fwrite(STDERR,"Restore lock failed\n");exit(5);}try{foreach(glob(D.'/websaza.sqlite3*')?:[] as $f)@unlink($f);if(!copy($src.'/websaza.sqlite3',D.'/websaza.sqlite3'))throw new RuntimeException('db copy failed');@chmod(D.'/websaza.sqlite3',0640);foreach(['settings.json','admin.json','_login_security.json','_login_secret','_admin_trusted_devices.json','contact_registry.json'] as $n){if(is_file($src.'/'.$n)){copy($src.'/'.$n,D.'/'.$n);@chmod(D.'/'.$n,$n==='_login_secret'?0600:0640);}}file_put_contents(D.'/_restore_status.json',json_encode(['ok'=>true,'snapshot'=>$name,'restored_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);echo "RESTORE_OK {$name}\n";}catch(Throwable $e){fwrite(STDERR,"RESTORE_FAILED\n");exit(6);}finally{flock($fh,LOCK_UN);fclose($fh);} 
