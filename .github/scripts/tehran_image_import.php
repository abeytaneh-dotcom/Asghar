<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$expected='__TOKEN__';
if(!hash_equals($expected,(string)($_SERVER['HTTP_X_IMPORT_TOKEN']??''))){http_response_code(403);exit;}
if(($_POST['finish']??'')==='1'){@unlink(__FILE__);echo json_encode(['ok'=>true,'finished'=>true]);exit;}
$bundle=basename((string)($_POST['bundle']??''));
if(!preg_match('/^image-bundle-[0-9]{3}\.zip$/',$bundle)){http_response_code(422);exit;}
$zipPath=__DIR__.'/storage/'.$bundle;
if(!is_file($zipPath)){http_response_code(404);exit;}
$zip=new ZipArchive();
if($zip->open($zipPath)!==true){http_response_code(422);exit;}
$raw=$zip->getFromName('mapping.json');
$map=json_decode((string)$raw,true);
if(!is_array($map)){http_response_code(422);exit;}
$c=require __DIR__.'/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$dir=__DIR__.'/uploads/products/auto';
if(!is_dir($dir))@mkdir($dir,0755,true);
$st=$pdo->prepare("UPDATE products SET image_path=?,image_source=?,updated_at=NOW() WHERE pyramid_code2=? AND active=1 AND (image_path IS NULL OR image_path='')");
$updated=0;$saved=0;$bytes=0;
foreach($map as $r){
    $f=basename((string)($r['file']??''));
    $code=trim((string)($r['code2']??''));
    if($f===''||$code==='')continue;
    $entry='images/'.$f;
    $data=$zip->getFromName($entry);
    if($data===false||strlen($data)<200)continue;
    $dest=$dir.'/'.$f;
    if(file_put_contents($dest,$data,LOCK_EX)===false)continue;
    @chmod($dest,0644);
    $saved++;$bytes+=strlen($data);
    $rel='uploads/products/auto/'.$f;
    $st->execute([$rel,(string)($r['source']??'catalog'),$code]);
    $rows=$st->rowCount();$updated+=$rows;
    if($rows===0)@unlink($dest);
}
$zip->close();
@unlink($zipPath);
$status=['ok'=>true,'bundle'=>$bundle,'rows'=>count($map),'saved'=>$saved,'updated'=>$updated,'bytes'=>$bytes,'at'=>date(DATE_ATOM)];
@file_put_contents(__DIR__.'/storage/image_import_last.json',json_encode($status,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
echo json_encode($status,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
