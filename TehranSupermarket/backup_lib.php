<?php
declare(strict_types=1);

function ts_backup_dir(): string { return dirname(dirname(__DIR__)).'/tehran_backups'; }
function ts_backup_status_shadow(): string { return __DIR__.'/storage/backups/backup-status.json'; }
function ts_backup_due(): bool {
    $status=ts_backup_dir().'/backup-status.json';
    return !is_file($status) || time()-(int)@filemtime($status) > 20*3600;
}
function ts_schedule_auto_backup(PDO $pdo): void {
    if(!ts_backup_due())return;
    register_shutdown_function(function() use ($pdo): void {
        if(!ts_backup_due())return;
        $dir=ts_backup_dir();if(!is_dir($dir)&&!@mkdir($dir,0700,true))return;@chmod($dir,0700);
        $lock=@fopen($dir.'/backup.lock','c');if(!$lock||!@flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return;}
        try{if(ts_backup_due())ts_run_backup($pdo);}catch(Throwable $e){@file_put_contents($dir.'/backup-error.log',date('c').' '.$e->getMessage()."\n",FILE_APPEND|LOCK_EX);}finally{@flock($lock,LOCK_UN);@fclose($lock);}
    });
}
function ts_run_backup(PDO $pdo): array {
    $dir=ts_backup_dir();if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('backup dir');@chmod($dir,0700);
    $stamp=date('Y-m-d_His');$sqlFile=$dir.'/db-'.$stamp.'.sql';$fh=fopen($sqlFile,'wb');if(!$fh)throw new RuntimeException('backup file');
    fwrite($fh,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){
        $table=(string)$table;$q='`'.str_replace('`','``',$table).'`';$cr=$pdo->query('SHOW CREATE TABLE '.$q)->fetch(PDO::FETCH_NUM);if(!$cr)continue;
        fwrite($fh,"\nDROP TABLE IF EXISTS $q;\n".$cr[1].";\n");$st=$pdo->query('SELECT * FROM '.$q);
        while($row=$st->fetch(PDO::FETCH_ASSOC)){$cols=array_map(fn($k)=>'`'.str_replace('`','``',(string)$k).'`',array_keys($row));$vals=[];foreach($row as $v)$vals[]=$v===null?'NULL':$pdo->quote((string)$v);fwrite($fh,'INSERT INTO '.$q.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');'."\n");}
    }
    fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($fh);@chmod($sqlFile,0600);$dbOut=$sqlFile;
    if(function_exists('gzopen')){$gz=$sqlFile.'.gz';$in=fopen($sqlFile,'rb');$out=gzopen($gz,'wb9');if($in&&$out){while(!feof($in))gzwrite($out,(string)fread($in,1048576));fclose($in);gzclose($out);@unlink($sqlFile);@chmod($gz,0600);$dbOut=$gz;}else{if($in)fclose($in);if($out)gzclose($out);}}
    $uploadsFile=null;$uploads=__DIR__.'/uploads/products';$weekly=(int)date('N')===7;
    if($weekly&&is_dir($uploads)&&class_exists('ZipArchive')){$zp=$dir.'/uploads-'.date('Y-m-d').'.zip';$zip=new ZipArchive();if($zip->open($zp,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile()){$full=$f->getPathname();$zip->addFile($full,substr($full,strlen($uploads)+1));}}$zip->close();@chmod($zp,0600);$uploadsFile=basename($zp);}}
    $now=time();foreach(glob($dir.'/db-*.sql*')?:[] as $f)if(is_file($f)&&$now-filemtime($f)>14*86400)@unlink($f);$zips=glob($dir.'/uploads-*.zip')?:[];usort($zips,fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($zips,6) as $f)@unlink($f);
    $status=['ok'=>true,'finished_at'=>date('Y-m-d H:i:s'),'database_file'=>basename($dbOut),'database_bytes'=>(int)(filesize($dbOut)?:0),'uploads_file'=>$uploadsFile,'storage'=>'outside_webroot'];
    $json=json_encode($status,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);@file_put_contents($dir.'/backup-status.json',$json,LOCK_EX);@chmod($dir.'/backup-status.json',0600);
    $shadowDir=dirname(ts_backup_status_shadow());if(!is_dir($shadowDir))@mkdir($shadowDir,0700,true);@file_put_contents($shadowDir.'/.htaccess',"Require all denied\nDeny from all\nOptions -Indexes\n",LOCK_EX);@file_put_contents(ts_backup_status_shadow(),$json,LOCK_EX);@chmod(ts_backup_status_shadow(),0600);
    return $status;
}
