<?php
declare(strict_types=1);

function ts_ai_norm(string $s): string {
    $s=mb_strtolower(trim($s),'UTF-8');
    $s=strtr($s,['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','‌'=>' ','ـ'=>' ']);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}

function ts_ai_learning_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_learning_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        session_hash CHAR(64) NOT NULL,
        event_type VARCHAR(32) NOT NULL,
        query_text VARCHAR(120) NULL,
        corrected_text VARCHAR(120) NULL,
        product_id INT UNSIGNED NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 0,
        fulfillment_method VARCHAR(20) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ai_event_type_created(event_type,created_at),
        KEY idx_ai_event_product(product_id,event_type,created_at),
        KEY idx_ai_event_session(session_hash,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_product_signals (
        product_id INT UNSIGNED NOT NULL PRIMARY KEY,
        clicks INT UNSIGNED NOT NULL DEFAULT 0,
        adds INT UNSIGNED NOT NULL DEFAULT 0,
        orders INT UNSIGNED NOT NULL DEFAULT 0,
        score DECIMAL(8,3) NOT NULL DEFAULT 0,
        updated_at DATETIME NULL,
        KEY idx_ai_signal_score(score,updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_query_aliases (
        wrong_norm VARCHAR(120) NOT NULL PRIMARY KEY,
        correct_norm VARCHAR(120) NOT NULL,
        accepts INT UNSIGNED NOT NULL DEFAULT 1,
        updated_at DATETIME NULL,
        KEY idx_ai_alias_accepts(accepts,updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ts_ai_session_hash(): string {
    $sid=session_id();
    if($sid==='')$sid=bin2hex(random_bytes(16));
    return hash('sha256','tehran-market-ai-v1|'.$sid);
}

function ts_ai_log_event(PDO $pdo,string $type,?string $query=null,?string $corrected=null,?int $productId=null,int $qty=0,?string $fulfillment=null): void {
    try{
        $allowed=['search','product_click','add_to_cart','spell_accept','checkout_start','fulfillment_choice','order_complete'];
        if(!in_array($type,$allowed,true))return;
        $query=$query!==null?mb_substr(trim($query),0,120,'UTF-8'):null;
        $corrected=$corrected!==null?mb_substr(trim($corrected),0,120,'UTF-8'):null;
        $fulfillment=in_array($fulfillment,['pickup','delivery'],true)?$fulfillment:null;
        $st=$pdo->prepare('INSERT INTO ai_learning_events(session_hash,event_type,query_text,corrected_text,product_id,quantity,fulfillment_method,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
        $st->execute([ts_ai_session_hash(),$type,$query,$corrected,$productId?:null,max(0,$qty),$fulfillment]);
    }catch(Throwable $e){}
}

function ts_ai_signal(PDO $pdo,int $productId,string $type): void {
    if($productId<1)return;
    $click=$type==='product_click'?1:0;
    $add=$type==='add_to_cart'?1:0;
    $order=$type==='order_complete'?1:0;
    $weight=$click?1.5:($add?4.0:($order?12.0:0.0));
    if($weight<=0)return;
    try{
        $sql='INSERT INTO ai_product_signals(product_id,clicks,adds,orders,score,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE clicks=clicks+VALUES(clicks),adds=adds+VALUES(adds),orders=orders+VALUES(orders),score=LEAST(100,score*0.997+VALUES(score)),updated_at=NOW()';
        $pdo->prepare($sql)->execute([$productId,$click,$add,$order,$weight]);
    }catch(Throwable $e){}
}

function ts_ai_learn_alias(PDO $pdo,string $wrong,string $correct): void {
    $wrong=ts_ai_norm($wrong);$correct=ts_ai_norm($correct);
    if($wrong===''||$correct===''||$wrong===$correct||mb_strlen($wrong,'UTF-8')>120||mb_strlen($correct,'UTF-8')>120)return;
    try{
        $pdo->prepare('INSERT INTO ai_query_aliases(wrong_norm,correct_norm,accepts,updated_at) VALUES(?,?,1,NOW()) ON DUPLICATE KEY UPDATE correct_norm=VALUES(correct_norm),accepts=accepts+1,updated_at=NOW()')->execute([$wrong,$correct]);
    }catch(Throwable $e){}
}

function ts_ai_alias_map(PDO $pdo): array {
    try{
        $out=[];$st=$pdo->query('SELECT wrong_norm,correct_norm FROM ai_query_aliases WHERE accepts>=2 ORDER BY accepts DESC,updated_at DESC LIMIT 1000');
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(string)$r['wrong_norm']]=(string)$r['correct_norm'];
        return $out;
    }catch(Throwable $e){return [];}
}

function ts_ai_score_map(PDO $pdo): array {
    try{
        $out=[];$st=$pdo->query('SELECT product_id,score FROM ai_product_signals WHERE score>0 ORDER BY score DESC LIMIT 12000');
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(int)$r['product_id']]=(float)$r['score'];
        return $out;
    }catch(Throwable $e){return [];}
}

function ts_ai_learn_order(PDO $pdo,array $items,string $fulfillment): void {
    foreach($items as $it){
        $pid=(int)($it['id']??$it['product_id']??0);$qty=max(1,(int)($it['qty']??$it['quantity']??1));
        if($pid<1)continue;
        ts_ai_log_event($pdo,'order_complete',null,null,$pid,$qty,$fulfillment);
        for($i=0;$i<min(3,$qty);$i++)ts_ai_signal($pdo,$pid,'order_complete');
    }
}
