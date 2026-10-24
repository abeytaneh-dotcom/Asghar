<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
date_default_timezone_set('Asia/Tehran');
$root=__DIR__;$config=$root.'/config.php';if(!is_file($config)){fwrite(STDERR,"config missing\n");exit(2);} $c=require $config;
try{$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(Throwable $e){fwrite(STDERR,"db connect failed\n");exit(3);}
$dir=$root.'/storage/backups';if(!is_dir($dir)&&!mkdir($dir,0700,true)){fwrite(STDERR,"backup dir failed\n");exit(4);} @chmod($dir,0700);
@file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\nOptions -Indexes\n",LOCK_EX);
$stamp=date('Y-m-d_His');$sqlFile=$dir.'/db-'.$stamp.'.sql';$fh=fopen($sqlFile,'wb');if(!$fh){fwrite(STDERR,"open failed\n");exit(5);} 
fwrite($fh,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table){
 $table=(string)$table;$q='`'.str_replace('`','``',$table).'`';$cr=$pdo->query('SHOW CREATE TABLE '.$q)->fetch(PDO::FETCH_NUM);if(!$cr)continue;
 fwrite($fh,"\nDROP TABLE IF EXISTS $q;\n".$cr[1].";\n");
 $st=$pdo->query('SELECT * FROM '.$q,PDO::FETCH_ASSOC);
 while($row=$st->fetch(PDO::FETCH_ASSOC)){
  $cols=array_map(fn($k)=>'`'.str_replace('`','``',(string)$k).'`',array_keys($row));$vals=[];
  foreach($row as $v)$vals[]=$v===null?'NULL':$pdo->quote((string)$v);
  fwrite($fh,'INSERT INTO '.$q.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');'."\n");
 }
}
fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($fh);@chmod($sqlFile,0600);
$dbOut=$sqlFile;
if(function_exists('gzopen')){$gz=$sqlFile.'.gz';$in=fopen($sqlFile,'rb');$out=gzopen($gz,'wb9');if($in&&$out){while(!feof($in)){gzwrite($out,(string)fread($in,1024*1024));}fclose($in);gzclose($out);unlink($sqlFile);@chmod($gz,0600);$dbOut=$gz;}else{if($in)fclose($in);if($out)gzclose($out);}}
$uploadsFile='';
$weekly=(int)date('N')===7;$uploads=$root.'/uploads/products';
if($weekly&&is_dir($uploads)&&class_exists('ZipArchive')){$zipPath=$dir.'/uploads-'.date('Y-m-d').'.zip';$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile()){$full=$f->getPathname();$rel=substr($full,strlen($uploads)+1);$zip->addFile($full,$rel);}}$zip->close();@chmod($zipPath,0600);$uploadsFile=basename($zipPath);}}
$now=time();foreach(glob($dir.'/db-*.sql*')?:[] as $f){if(is_file($f)&&$now-filemtime($f)>14*86400)@unlink($f);} $zips=glob($dir.'/uploads-*.zip')?:[];usort($zips,fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($zips,6) as $f)@unlink($f);
$status=['ok'=>true,'finished_at'=>date('Y-m-d H:i:s'),'database_file'=>basename($dbOut),'database_bytes'=>filesize($dbOut)?:0,'uploads_file'=>$uploadsFile?:null];@file_put_contents($dir.'/backup-status.json',json_encode($status,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);@chmod($dir.'/backup-status.json',0600);
echo "BACKUP_OK ".$status['database_file']."\n";
