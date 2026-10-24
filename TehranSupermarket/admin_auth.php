<?php
declare(strict_types=1);
require_once __DIR__.'/error_bootstrap.php';

function ts_admin_schema(PDO $pdo): void {
    $cols=[];foreach($pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_ASSOC) as $r)$cols[$r['Field']]=true;$a=[];
    if(!isset($cols['display_name']))$a[]="ADD COLUMN display_name VARCHAR(120) NULL AFTER username";
    if(!isset($cols['role']))$a[]="ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'owner' AFTER password_hash";
    if(!isset($cols['active']))$a[]="ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role";
    if(!isset($cols['failed_attempts']))$a[]="ADD COLUMN failed_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER active";
    if(!isset($cols['locked_until']))$a[]="ADD COLUMN locked_until DATETIME NULL AFTER failed_attempts";
    if(!isset($cols['last_login_at']))$a[]="ADD COLUMN last_login_at DATETIME NULL AFTER locked_until";
    if(!isset($cols['last_login_ip']))$a[]="ADD COLUMN last_login_ip VARCHAR(45) NULL AFTER last_login_at";
    if(!isset($cols['password_changed_at']))$a[]="ADD COLUMN password_changed_at DATETIME NULL AFTER last_login_ip";
    if($a)$pdo->exec('ALTER TABLE admins '.implode(', ',$a));
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,admin_id INT UNSIGNED NULL,username VARCHAR(80) NULL,action VARCHAR(80) NOT NULL,details VARCHAR(500) NULL,ip VARCHAR(45) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(admin_id,created_at),INDEX(action,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ts_ip(): string{return substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);}function ts_roles():array{return ['owner','manager','staff'];}function ts_role_label(string $role):string{return ['owner'=>'مدیر کل','manager'=>'مدیر فروشگاه','staff'=>'کارمند سفارش'][$role]??$role;}
function ts_audit(PDO $pdo,string $action,string $details='',?int $adminId=null,?string $username=null):void{try{$adminId=$adminId??(!empty($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null);if($username===null&&$adminId){$s=$pdo->prepare('SELECT username FROM admins WHERE id=?');$s->execute([$adminId]);$username=(string)($s->fetchColumn()?:'');}$s=$pdo->prepare('INSERT INTO admin_audit_log(admin_id,username,action,details,ip,created_at) VALUES(?,?,?,?,?,NOW())');$s->execute([$adminId,$username?:null,$action,mb_substr($details,0,500),ts_ip()?:null]);}catch(Throwable $e){ts_log_internal($e,'SYS-100');}}
function ts_admin_current(PDO $pdo):?array{if(empty($_SESSION['admin_id']))return null;$last=(int)($_SESSION['auth_last_seen']??time());if(time()-$last>7200){ts_audit($pdo,'session_timeout');$_SESSION=[];session_destroy();return null;}$s=$pdo->prepare('SELECT id,username,display_name,role,active,last_login_at FROM admins WHERE id=? LIMIT 1');$s->execute([(int)$_SESSION['admin_id']]);$u=$s->fetch();if(!$u||(int)$u['active']!==1){$_SESSION=[];session_destroy();return null;}if(!in_array((string)$u['role'],ts_roles(),true))$u['role']='staff';$_SESSION['admin_role']=$u['role'];$_SESSION['auth_last_seen']=time();return $u;}
function ts_require_roles(PDO $pdo,array $roles):array{$u=ts_admin_current($pdo);if(!$u){header('Location: admin.php');exit;}if(!in_array((string)$u['role'],$roles,true))ts_html_fail('AUTH-104',403,'admin.php');return $u;}
function ts_login(PDO $pdo,string $username,string $password):array{
    $username=trim($username);$s=$pdo->prepare('SELECT * FROM admins WHERE username=? LIMIT 1');$s->execute([$username]);$u=$s->fetch();
    if(!$u){usleep(random_int(150000,350000));ts_audit($pdo,'login_failed','unknown user',null,$username);return [false,ts_error_text('AUTH-101'),null];}
    if((int)$u['active']!==1){ts_audit($pdo,'login_blocked','inactive',(int)$u['id'],$username);return [false,ts_error_text('AUTH-103'),null];}
    if(!empty($u['locked_until'])&&strtotime((string)$u['locked_until'])>time())return [false,ts_error_text('AUTH-102'),null];
    if(!password_verify($password,(string)$u['password_hash'])){$fails=(int)$u['failed_attempts']+1;$lock=$fails>=5?date('Y-m-d H:i:s',time()+900):null;$q=$pdo->prepare('UPDATE admins SET failed_attempts=?,locked_until=? WHERE id=?');$q->execute([$fails,$lock,(int)$u['id']]);ts_audit($pdo,'login_failed','attempt '.$fails,(int)$u['id'],$username);return [false,ts_error_text($lock?'AUTH-102':'AUTH-101'),null];}
    if(password_needs_rehash((string)$u['password_hash'],PASSWORD_DEFAULT)){$q=$pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),(int)$u['id']]);}
    $q=$pdo->prepare('UPDATE admins SET failed_attempts=0,locked_until=NULL,last_login_at=NOW(),last_login_ip=? WHERE id=?');$q->execute([ts_ip()?:null,(int)$u['id']]);session_regenerate_id(true);$_SESSION['admin_id']=(int)$u['id'];$_SESSION['admin_role']=(string)$u['role'];$_SESSION['auth_last_seen']=time();ts_audit($pdo,'login_success','',(int)$u['id'],$username);return [true,'',(string)$u['role']];
}
