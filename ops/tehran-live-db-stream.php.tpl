<?php
declare(strict_types=1);
@ini_set('display_errors', '0');
@set_time_limit(300);
$expected='__ONE_TIME_AUTH__';
$got=(string)($_SERVER['HTTP_X_TEHRAN_MIGRATION_AUTH'] ?? '');
if ($got==='' || !hash_equals($expected,$got)) { http_response_code(404); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') { http_response_code(405); exit; }
$config=@require __DIR__.'/config.php';
if (!is_array($config) || empty($config['db_name']) || empty($config['db_user'])) { http_response_code(500); exit('CONFIG_ERROR'); }
try {
  $pdo=new PDO('mysql:host='.($config['db_host']??'localhost').';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false]);
  $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  $pdo->exec('START TRANSACTION READ ONLY WITH CONSISTENT SNAPSHOT');
} catch (Throwable $e) { http_response_code(503); exit('DB_CONNECTION_ERROR'); }
while (ob_get_level()>0) @ob_end_clean();
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="tehran-live-database.sql"');
header('Cache-Control: no-cache, no-store, private');
header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','wb');
fwrite($out,"-- Tehran MySQL snapshot ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n");
try {
  $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  foreach ($tables as $table) {
    $t=chr(96).str_replace(chr(96),chr(96).chr(96),(string)$table).chr(96);
    $statement=$pdo->query('SHOW CREATE TABLE '.$t);
    $create=$statement->fetch(PDO::FETCH_NUM);
    $statement->closeCursor();
    if (!$create) continue;
    fwrite($out,"\nDROP TABLE IF EXISTS $t;\n".$create[1].";\n");
    $rs=$pdo->query('SELECT * FROM '.$t);
    $count=0;
    while ($row=$rs->fetch(PDO::FETCH_ASSOC)) {
      $cols=array_map(static fn($c)=>chr(96).str_replace(chr(96),chr(96).chr(96),(string)$c).chr(96),array_keys($row));
      $vals=[];
      foreach ($row as $v) $vals[]=$v===null?'NULL':$pdo->quote((string)$v);
      fwrite($out,'INSERT INTO '.$t.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n");
      if ((++$count%500)===0) { @fflush($out); @flush(); }
    }
    $rs->closeCursor();
  }
  fwrite($out,"COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n-- TEHRAN_DUMP_COMPLETE\n");
  $pdo->exec('COMMIT');
  @fflush($out);
} catch(Throwable $e) { fwrite($out,"\n-- TEHRAN_EXPORT_INCOMPLETE\n"); }
fclose($out);