<?php
declare(strict_types=1);
require_once __DIR__.'/nz-session-bootstrap.php';
require_once __DIR__.'/nz-community.php';
require_once __DIR__.'/nz-fast-read.php';
require_once __DIR__.'/nz-chat-enhance.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
nzc_session_open();$defaults=nzc_default_db();$db=nzfast_read_buckets(['users','conversations'],$defaults);$user=nzc_current_user($db);$cid=trim((string)($_GET['cid']??''));$after=trim((string)($_GET['after']??''));if(!$user||$cid===''){http_response_code(401);echo json_encode(['ok'=>false]);exit;}$conv=nzchat_conversation($db,$cid);$isAdmin=nzc_is_admin($user);if(!$conv||(!$isAdmin&&!nzchat_member($conv,(string)$user['id']))){http_response_code(403);echo json_encode(['ok'=>false]);exit;}
$rows=nzfast_conversation_messages($cid,$defaults);$needsRead=false;foreach($rows as$m){if(($m['sender_id']??'')===$user['id'])continue;if(!in_array($user['id'],(array)($m['read_by']??[]),true)){$needsRead=true;break;}}
if($needsRead){nzfast_mark_conversation_read($cid,(string)$user['id'],$defaults);foreach($rows as&$m){if(($m['sender_id']??'')===$user['id'])continue;$read=(array)($m['read_by']??[]);if(!in_array($user['id'],$read,true)){$read[]=$user['id'];$m['read_by']=$read;}}unset($m);}
usort($rows,fn($a,$b)=>strtotime($a['created_at']??'now')<=>strtotime($b['created_at']??'now'));$initial=$after==='';if(!$initial){$pos=-1;foreach($rows as$i=>$m)if(($m['id']??'')===$after){$pos=$i;break;}$rows=$pos>=0?array_slice($rows,$pos+1):array_slice($rows,-30);}else$rows=array_slice($rows,-100);
$out=[];$last=$after;foreach($rows as$m){$sender=(string)($m['sender_id']??'');$support=$sender===NZC_SUPPORT_SENDER;$mine=($sender===$user['id'])||($isAdmin&&$support);$last=(string)($m['id']??$last);$out[]=['id'=>(string)($m['id']??''),'text'=>(string)($m['text']??''),'time'=>date('H:i',strtotime($m['created_at']??'now')),'mine'=>$mine,'support'=>$support,'sender'=>$support?'پشتیبانی':($mine?'من':'کاربر')];}
echo json_encode(['ok'=>true,'messages'=>$out,'last'=>$last,'initial'=>$initial],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);