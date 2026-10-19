<?php
declare(strict_types=1);

function nzfast_read_buckets(array $buckets,array $defaults): array {
    $want=array_values(array_unique(array_filter(array_map('strval',$buckets))));$out=[];foreach($want as$b)$out[$b]=$defaults[$b]??[];$pdo=nzstore_sqlite();
    if(!$pdo){$db=nzstore_read($defaults);foreach($want as$b)$out[$b]=$db[$b]??[];return$out;}
    nzstore_ensure($pdo,$defaults);if(!$want)return$out;$marks=implode(',',array_fill(0,count($want),'?'));$st=$pdo->prepare("SELECT bucket,payload FROM nz_rows WHERE bucket IN ($marks) ORDER BY bucket,seq");$st->execute($want);foreach($st->fetchAll()as$r){$item=json_decode((string)$r['payload'],true);if(is_array($item))$out[(string)$r['bucket']][]=$item;}return$out;
}
function nzfast_conversation_messages(string $cid,array $defaults): array {
    $pdo=nzstore_sqlite();if(!$pdo){$db=nzstore_read($defaults);return array_values(array_filter($db['messages']??[],fn($m)=>($m['conversation_id']??'')===$cid));}
    nzstore_ensure($pdo,$defaults);$st=$pdo->prepare("SELECT r.payload FROM nz_message_index m JOIN nz_rows r ON r.bucket='messages' AND r.item_key=m.id WHERE m.conversation_id=? ORDER BY m.created_at");$st->execute([$cid]);$out=[];foreach($st->fetchAll()as$r){$item=json_decode((string)$r['payload'],true);if(is_array($item))$out[]=$item;}return$out;
}
function nzfast_mark_conversation_read(string $cid,string $uid,array $defaults): void {
    $pdo=nzstore_sqlite();if(!$pdo){nzstore_write($defaults,function(&$db)use($cid,$uid){foreach($db['messages']??[]as&$m){if(($m['conversation_id']??'')!==$cid||($m['sender_id']??'')===$uid)continue;$read=(array)($m['read_by']??[]);if(!in_array($uid,$read,true)){$read[]=$uid;$m['read_by']=$read;}}unset($m);});return;}
    nzstore_ensure($pdo,$defaults);$st=$pdo->prepare("SELECT r.item_key,r.payload FROM nz_message_index m JOIN nz_rows r ON r.bucket='messages' AND r.item_key=m.id WHERE m.conversation_id=?");$st->execute([$cid]);$up=$pdo->prepare("UPDATE nz_rows SET payload=?,updated_at=? WHERE bucket='messages' AND item_key=?");$pdo->beginTransaction();try{foreach($st->fetchAll()as$r){$m=json_decode((string)$r['payload'],true);if(!is_array($m)||($m['sender_id']??'')===$uid)continue;$read=(array)($m['read_by']??[]);if(in_array($uid,$read,true))continue;$read[]=$uid;$m['read_by']=$read;$json=json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json!==false)$up->execute([$json,date('c'),(string)$r['item_key']]);}$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function nzfast_published_posts(array $defaults): array {
    $pdo=nzstore_sqlite();if(!$pdo){$db=nzstore_read($defaults);return array_values(array_filter($db['posts']??[],fn($p)=>($p['status']??'')==='published'));}
    nzstore_ensure($pdo,$defaults);$sql="SELECT r.payload FROM nz_post_index p JOIN nz_rows r ON r.bucket='posts' AND r.item_key=p.id WHERE p.status='published' ORDER BY p.created_at DESC";$out=[];foreach($pdo->query($sql)->fetchAll()as$r){$item=json_decode((string)$r['payload'],true);if(is_array($item))$out[]=$item;}return$out;
}
