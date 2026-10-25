<?php
declare(strict_types=1);

function ts_image_marker_done(PDO $pdo,string $key): bool {
    $q=$pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=? LIMIT 1');
    $q->execute([$key]);
    return (string)($q->fetchColumn()?:'')!=='';
}
function ts_image_marker_save(PDO $pdo,string $key,array $data): void {
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $s=$pdo->prepare('INSERT INTO store_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
    $s->execute([$key,$json]);
}
function ts_image_marker_key(string $prefix,string $hash): string {
    // store_settings.setting_key is VARCHAR(80); keep manifest markers safely below that limit.
    return $prefix.substr($hash,0,48);
}
function ts_apply_product_image_cleanup(PDO $pdo): void {
    $files=glob(__DIR__.'/storage/product_image_cleanup*.jsonl')?:[];
    sort($files);
    foreach($files as $file){
        $hash=hash_file('sha256',$file);if(!is_string($hash)||$hash==='')continue;
        $key=ts_image_marker_key('product_image_cleanup_',$hash);if(ts_image_marker_done($pdo,$key))continue;
        $st=$pdo->prepare("UPDATE products SET image_path=NULL,image_source=NULL,updated_at=NOW() WHERE active=1 AND pyramid_code2=? AND image_source LIKE 'snapp.market|%'");
        $received=0;$cleared=0;$fh=fopen($file,'rb');if(!$fh)continue;
        $pdo->beginTransaction();
        try{
            while(($line=fgets($fh))!==false){
                $line=trim($line);if($line==='')continue;$r=json_decode($line,true);if(!is_array($r))continue;
                $code=trim((string)($r['code2']??''));if($code==='')continue;$received++;$st->execute([$code]);$cleared+=$st->rowCount();
            }
            fclose($fh);$summary=['received'=>$received,'cleared'=>$cleared,'hash'=>$hash,'at'=>date(DATE_ATOM)];ts_image_marker_save($pdo,$key,$summary);$pdo->commit();
            @file_put_contents(__DIR__.'/storage/product_image_cleanup_status.json',json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        }catch(Throwable $e){if(is_resource($fh))fclose($fh);if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
function ts_apply_product_image_manifest(PDO $pdo): void {
    static $ran=false;if($ran)return;$ran=true;
    try{
        ts_apply_product_image_cleanup($pdo);
        $files=glob(__DIR__.'/storage/product_image_manifest*.jsonl')?:[];sort($files);
        foreach($files as $file){
            $hash=hash_file('sha256',$file);if(!is_string($hash)||$hash==='')continue;
            $key=ts_image_marker_key('product_image_manifest_',$hash);if(ts_image_marker_done($pdo,$key))continue;
            $byCode=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE active=1 AND pyramid_code2=? AND (image_path IS NULL OR image_path='')");
            $byBarcode=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE active=1 AND barcode=? AND (image_path IS NULL OR image_path='')");
            $fh=fopen($file,'rb');if(!$fh)continue;$received=0;$updated=0;$pdo->beginTransaction();
            try{
                while(($line=fgets($fh))!==false){
                    $line=trim($line);if($line==='')continue;$r=json_decode($line,true);if(!is_array($r))continue;
                    $path=trim((string)($r['path']??''));$src=trim((string)($r['source']??''));$code=trim((string)($r['code2']??''));$barcode=trim((string)($r['barcode']??''));
                    if($path===''||!preg_match('#^uploads/products/[A-Za-z0-9._-]+\.webp$#',$path)||$src==='')continue;
                    $received++;
                    if($code!==''){$byCode->execute([$path,$src,$code]);$updated+=$byCode->rowCount();}
                    elseif($barcode!==''){$byBarcode->execute([$path,$src,$barcode]);$updated+=$byBarcode->rowCount();}
                }
                fclose($fh);$summary=['received'=>$received,'updated'=>$updated,'file'=>basename($file),'hash'=>$hash,'at'=>date(DATE_ATOM)];ts_image_marker_save($pdo,$key,$summary);$pdo->commit();
                @file_put_contents(__DIR__.'/storage/product_image_manifest_status.json',json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
            }catch(Throwable $e){if(is_resource($fh))fclose($fh);if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if(function_exists('ts_log_internal'))ts_log_internal($e,'IMG-SEED');
    }
}
