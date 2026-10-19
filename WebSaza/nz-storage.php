<?php
declare(strict_types=1);

function nzstore_dir(): string {
    $dir=__DIR__.'/.marketplace-data';
    if(!is_dir($dir)) @mkdir($dir,0750,true);
    return $dir;
}
function nzstore_legacy_file(): string { return nzstore_dir().'/data.json'; }
function nzstore_db_file(): string { return nzstore_dir().'/niazestan.sqlite3'; }
function nzstore_backups_dir(): string {
    $dir=nzstore_dir().'/backups';
    if(!is_dir($dir)) @mkdir($dir,0750,true);
    return $dir;
}
function nzstore_sqlite(): ?PDO {
    static $pdo=null,$attempted=false;
    if($attempted) return $pdo;
    $attempted=true;
    if(!class_exists('PDO') || !in_array('sqlite',PDO::getAvailableDrivers(),true)) return null;
    try{
        $pdo=new PDO('sqlite:'.nzstore_db_file(),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA busy_timeout=7000');
        $pdo->exec('CREATE TABLE IF NOT EXISTS nz_meta(k TEXT PRIMARY KEY,v TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS nz_rows(bucket TEXT NOT NULL,item_key TEXT NOT NULL,seq INTEGER NOT NULL,payload TEXT NOT NULL,updated_at TEXT NOT NULL,PRIMARY KEY(bucket,item_key))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_rows_bucket_seq ON nz_rows(bucket,seq)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS nz_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id TEXT,action TEXT,target_type TEXT,target_id TEXT,ip TEXT,user_agent TEXT,payload TEXT,created_at TEXT NOT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_audit_created ON nz_audit(created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_audit_target ON nz_audit(target_type,target_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS nz_post_index(id TEXT PRIMARY KEY,user_id TEXT,status TEXT,review_status TEXT,category TEXT,subcategory TEXT,province TEXT,city TEXT,price INTEGER,created_at TEXT,updated_at TEXT,expires_at TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_post_status_created ON nz_post_index(status,created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_post_cat_city ON nz_post_index(category,subcategory,city,status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_post_user ON nz_post_index(user_id,status)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS nz_message_index(id TEXT PRIMARY KEY,conversation_id TEXT,sender_id TEXT,created_at TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS nz_msg_conv_created ON nz_message_index(conversation_id,created_at)');
        @chmod(nzstore_db_file(),0640);
        return $pdo;
    }catch(Throwable $e){$pdo=null;return null;}
}
function nzstore_json_read(array $defaults): array {
    $raw=@file_get_contents(nzstore_legacy_file());
    $db=json_decode($raw?:'',true);
    return is_array($db)?array_replace($defaults,$db):$defaults;
}
function nzstore_atomic_json(string $file,array $data): void {
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if($json===false) return;
    $tmp=$file.'.tmp.'.bin2hex(random_bytes(3));
    if(@file_put_contents($tmp,$json,LOCK_EX)===false) return;
    @chmod($tmp,0640);
    @rename($tmp,$file);
}
function nzstore_row_key(string $bucket,array $row,int $seq): string {
    $id=trim((string)($row['id']??''));
    if($id!=='') return $id;
    if($bucket==='favorites'){
        $u=trim((string)($row['user_id']??''));$p=trim((string)($row['post_id']??''));
        if($u!==''||$p!=='') return 'fav_'.substr(hash('sha256',$u.'|'.$p),0,24);
    }
    return 'row_'.$seq.'_'.substr(hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).$seq),0,20);
}
function nzstore_meta_get(PDO $pdo,string $key): string {
    $st=$pdo->prepare('SELECT v FROM nz_meta WHERE k=?');$st->execute([$key]);$v=$st->fetchColumn();return $v===false?'':(string)$v;
}
function nzstore_meta_set(PDO $pdo,string $key,string $value): void {
    $pdo->prepare('INSERT INTO nz_meta(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$key,$value]);
}
function nzstore_import(PDO $pdo,array $db): void {
    $pdo->beginTransaction();
    try{
        $ins=$pdo->prepare('INSERT OR REPLACE INTO nz_rows(bucket,item_key,seq,payload,updated_at) VALUES(?,?,?,?,?)');
        foreach($db as $bucket=>$rows){
            if(!is_array($rows)) continue;
            $seq=0;
            foreach($rows as $row){
                if(!is_array($row)) continue;
                $json=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                if($json===false) continue;
                $ins->execute([(string)$bucket,nzstore_row_key((string)$bucket,$row,$seq),$seq++,$json,date('c')]);
            }
        }
        nzstore_meta_set($pdo,'schema','2');
        nzstore_meta_set($pdo,'legacy_imported_at',date('c'));
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function nzstore_ensure(PDO $pdo,array $defaults): void {
    if(nzstore_meta_get($pdo,'schema')!=='') return;
    nzstore_import($pdo,nzstore_json_read($defaults));
}
function nzstore_read_sqlite(PDO $pdo,array $defaults): array {
    nzstore_ensure($pdo,$defaults);
    $db=$defaults;
    $rows=$pdo->query('SELECT bucket,payload FROM nz_rows ORDER BY bucket,seq')->fetchAll();
    foreach($rows as $r){
        $bucket=(string)$r['bucket'];$item=json_decode((string)$r['payload'],true);
        if(!is_array($item)) continue;
        if(!isset($db[$bucket])||!is_array($db[$bucket]))$db[$bucket]=[];
        $db[$bucket][]=$item;
    }
    return $db;
}
function nzstore_read(array $defaults): array {
    $pdo=nzstore_sqlite();
    if(!$pdo) return nzstore_json_read($defaults);
    try{return nzstore_read_sqlite($pdo,$defaults);}catch(Throwable $e){return nzstore_json_read($defaults);}
}
function nzstore_existing_map(PDO $pdo,string $bucket): array {
    $st=$pdo->prepare('SELECT item_key,seq,payload FROM nz_rows WHERE bucket=?');$st->execute([$bucket]);$out=[];
    foreach($st->fetchAll() as$r)$out[(string)$r['item_key']]=['seq'=>(int)$r['seq'],'payload'=>(string)$r['payload']];
    return$out;
}
function nzstore_sync_bucket(PDO $pdo,string $bucket,array $rows): void {
    $old=nzstore_existing_map($pdo,$bucket);$seen=[];$up=$pdo->prepare('INSERT INTO nz_rows(bucket,item_key,seq,payload,updated_at) VALUES(?,?,?,?,?) ON CONFLICT(bucket,item_key) DO UPDATE SET seq=excluded.seq,payload=excluded.payload,updated_at=excluded.updated_at');$seq=0;
    foreach($rows as$row){if(!is_array($row))continue;$key=nzstore_row_key($bucket,$row,$seq);while(isset($seen[$key]))$key.='_'.$seq;$seen[$key]=true;$json=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false){$seq++;continue;}$prev=$old[$key]['payload']??null;if($prev!==$json||($old[$key]['seq']??null)!==$seq)$up->execute([$bucket,$key,$seq,$json,date('c')]);$seq++;}
    $del=$pdo->prepare('DELETE FROM nz_rows WHERE bucket=? AND item_key=?');foreach($old as$key=>$r)if(!isset($seen[$key]))$del->execute([$bucket,$key]);
}
function nzstore_rebuild_indexes(PDO $pdo,array $db): void {
    $pdo->exec('DELETE FROM nz_post_index');
    $st=$pdo->prepare('INSERT INTO nz_post_index(id,user_id,status,review_status,category,subcategory,province,city,price,created_at,updated_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach($db['posts']??[] as$p){if(!is_array($p)||empty($p['id']))continue;$st->execute([(string)$p['id'],(string)($p['user_id']??''),(string)($p['status']??''),(string)($p['review_status']??''),(string)($p['category']??''),(string)($p['subcategory']??''),(string)($p['province']??''),(string)($p['city']??''),(int)($p['price']??0),(string)($p['created_at']??''),(string)($p['updated_at']??''),(string)($p['expires_at']??'')]);}
    $pdo->exec('DELETE FROM nz_message_index');
    $ms=$pdo->prepare('INSERT INTO nz_message_index(id,conversation_id,sender_id,created_at) VALUES(?,?,?,?)');
    foreach($db['messages']??[] as$m){if(!is_array($m)||empty($m['id']))continue;$ms->execute([(string)$m['id'],(string)($m['conversation_id']??''),(string)($m['sender_id']??''),(string)($m['created_at']??'')]);}
}
function nzstore_write(array $defaults,callable $mutator): mixed {
    $pdo=nzstore_sqlite();
    if(!$pdo){
        $file=nzstore_legacy_file();$lock=$file.'.lock';$fh=@fopen($lock,'c');if(!$fh||!flock($fh,LOCK_EX))throw new RuntimeException('امکان قفل‌کردن اطلاعات نیازستان وجود ندارد.');
        try{$db=nzstore_json_read($defaults);$result=$mutator($db);nzstore_atomic_json($file,$db);return$result;}finally{flock($fh,LOCK_UN);fclose($fh);}
    }
    try{
        $pdo->exec('BEGIN IMMEDIATE');
        $db=nzstore_read_sqlite($pdo,$defaults);$result=$mutator($db);
        foreach($db as$bucket=>$rows)if(is_array($rows))nzstore_sync_bucket($pdo,(string)$bucket,$rows);
        nzstore_rebuild_indexes($pdo,$db);
        nzstore_meta_set($pdo,'last_write_at',date('c'));
        $pdo->commit();
        $last=(int)nzstore_meta_get($pdo,'last_json_snapshot');
        if(time()-$last>=300){nzstore_atomic_json(nzstore_legacy_file(),$db);nzstore_meta_set($pdo,'last_json_snapshot',(string)time());}
        return$result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function nzstore_audit(string $actorId,string $action,string $targetType='',string $targetId='',array $payload=[]): void {
    $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));$ua=mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT']??'')),0,400);$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}';$pdo=nzstore_sqlite();
    if($pdo){try{$pdo->prepare('INSERT INTO nz_audit(actor_id,action,target_type,target_id,ip,user_agent,payload,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$actorId,$action,$targetType,$targetId,$ip,$ua,$json,date('c')]);return;}catch(Throwable $e){}}
    $line=json_encode(['actor_id'=>$actorId,'action'=>$action,'target_type'=>$targetType,'target_id'=>$targetId,'ip'=>$ip,'user_agent'=>$ua,'payload'=>$payload,'created_at'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";@file_put_contents(nzstore_dir().'/audit.log',$line,FILE_APPEND|LOCK_EX);
}
function nzstore_backend(): string { return nzstore_sqlite()?'sqlite-wal':'json-fallback'; }
function nzstore_backup(array $defaults): array {
    $dir=nzstore_backups_dir();$day=date('Y-m-d');$made=[];$pdo=nzstore_sqlite();
    if($pdo){try{$pdo->exec('PRAGMA wal_checkpoint(FULL)');$src=nzstore_db_file();$dst=$dir.'/niazestan-'.$day.'.sqlite3';if(!is_file($dst)&&@copy($src,$dst)){@chmod($dst,0640);$made[]=$dst;}}catch(Throwable $e){}}
    $json=$dir.'/niazestan-'.$day.'.json';if(!is_file($json)){nzstore_atomic_json($json,nzstore_read($defaults));if(is_file($json))$made[]=$json;}
    foreach(glob($dir.'/niazestan-*')?:[] as$f)if(is_file($f)&&filemtime($f)<time()-21*86400)@unlink($f);
    return['backend'=>nzstore_backend(),'created'=>array_map('basename',$made)];
}
