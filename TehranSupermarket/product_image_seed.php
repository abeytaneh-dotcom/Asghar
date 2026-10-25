<?php
declare(strict_types=1);

function ts_apply_product_image_manifest(PDO $pdo): void {
    static $ran=false;
    if($ran)return;$ran=true;
    $file=__DIR__.'/storage/product_image_manifest.jsonl';
    if(!is_file($file))return;
    $hash=hash_file('sha256',$file);
    if(!is_string($hash)||$hash==='')return;
    $key='product_image_manifest_'.$hash;
    try{
        $q=$pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=? LIMIT 1');
        $q->execute([$key]);
        if((string)($q->fetchColumn()?:'')!=='')return;
        $byCode=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE active=1 AND pyramid_code2=? AND (image_path IS NULL OR image_path='')");
        $byBarcode=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE active=1 AND barcode=? AND (image_path IS NULL OR image_path='')");
        $fh=fopen($file,'rb');if(!$fh)return;
        $received=0;$updated=0;$pdo->beginTransaction();
        while(($line=fgets($fh))!==false){
            $line=trim($line);if($line==='')continue;
            $r=json_decode($line,true);if(!is_array($r))continue;
            $path=trim((string)($r['path']??''));$src=trim((string)($r['source']??''));$code=trim((string)($r['code2']??''));$barcode=trim((string)($r['barcode']??''));
            if($path===''||!preg_match('#^uploads/products/[A-Za-z0-9._-]+\.webp$#',$path)||$src==='')continue;
            $received++;
            if($code!==''){$byCode->execute([$path,$src,$code]);$updated+=$byCode->rowCount();}
            elseif($barcode!==''){$byBarcode->execute([$path,$src,$barcode]);$updated+=$byBarcode->rowCount();}
        }
        fclose($fh);
        $summary=json_encode(['received'=>$received,'updated'=>$updated,'hash'=>$hash,'at'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $s=$pdo->prepare('INSERT INTO store_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
        $s->execute([$key,$summary]);
        $pdo->commit();
        @file_put_contents(__DIR__.'/storage/product_image_manifest_status.json',$summary,LOCK_EX);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if(function_exists('ts_log_internal'))ts_log_internal($e,'IMG-SEED');
    }
}
