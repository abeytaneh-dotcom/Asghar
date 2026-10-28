import {mkdtemp,cp,writeFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const temp=await mkdtemp(join(tmpdir(),'niazestan-http-')),site=join(temp,'site');
await cp(resolve('WebSaza'),site,{recursive:true,filter:p=>!p.includes('.marketplace-data')&&!p.includes('marketplace-uploads')});
await writeFile(join(temp,'router.php'),`<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/__fixture'){
 require __DIR__.'/site/nz-session-bootstrap.php';require __DIR__.'/site/nz-community.php';
 $db=nzc_db_read();if(!$db['users'])nzc_db_write(function(&$db){$db['users']=[['id'=>'one'],['id'=>'two']];$db['notifications']=[['id'=>'private-one','user_id'=>'one','text'=>'private text','read'=>false,'created_at'=>date('c')]];});
 if(in_array($_GET['uid']??'',['one','two'],true))$_SESSION['uid']=$_GET['uid'];else unset($_SESSION['uid']);
 $_SESSION['csrf']='fixture-csrf';echo json_encode(['ok'=>true,'subscriptions'=>nzc_db_read()['push_subscriptions']??[]]);return;
}
return false;`);
const port=18000+Math.floor(Math.random()*8000),base=`http://127.0.0.1:${port}`;
const flags=JSON.parse(process.env.PHP_ARGS_JSON||'[]');
const server=spawn(process.env.PHP_BIN||'php',[...flags,'-d',`session.save_path=${temp}`,'-S',`127.0.0.1:${port}`,'-t',site,join(temp,'router.php')],{stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',x=>{logs+=x});let cookie='';
async function request(path,data){const r=await fetch(base+path,{headers:{Cookie:cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},...(data?{method:'POST',body:new URLSearchParams(data)}:{})});const set=r.headers.get('set-cookie');if(set)cookie=set.split(';')[0];return {status:r.status,body:await r.json()};}
try{
 let ready=false;for(let i=0;i<50;i++){try{await request('/__fixture');ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
 assert(ready,logs);
 assert.equal((await request('/nz-ai-api.php')).status,405);
 assert.equal((await request('/nz-ai-api.php',{message:'سلام'})).status,403);
 assert.equal((await request('/nz-ai-api.php',{csrf:'fixture-csrf',action:'contact',id:'p'})).status,401);
 assert.equal((await request('/nz-notification-api.php')).status,401);
 console.log('PASS HTTP method, CSRF and anonymous access guards');
 const missing=await request('/nz-ai-api.php',{csrf:'fixture-csrf',message:'سلام',mode:'search'});assert.equal(missing.status,503);assert.match(missing.body.error,/هنوز فعال نشده/);
 console.log('PASS missing provider returns explicit unavailable response');
 await request('/__fixture?uid=one');let n=await request('/nz-notification-api.php');assert.equal(n.status,200);assert.equal(n.body.items[0].id,'private-one');assert(n.body.public_key);assert(!JSON.stringify(n.body).includes('PRIVATE KEY'));
 assert.equal((await request('/nz-notification-api.php',{endpoint:'https://fcm.googleapis.com/test'})).status,403);
 assert.equal((await request('/nz-notification-api.php',{csrf:'fixture-csrf',endpoint:'https://localhost/test'})).status,400);
 assert.equal((await request('/nz-notification-api.php',{csrf:'fixture-csrf',endpoint:'https://fcm.googleapis.com/test'})).status,200);
 await request('/__fixture?uid=two');n=await request('/nz-notification-api.php');assert.equal(n.body.count,0);assert.deepEqual(n.body.items,[]);
 await request('/nz-notification-api.php',{csrf:'fixture-csrf',action:'unsubscribe',endpoint:'https://fcm.googleapis.com/test'});
 const state=await request('/__fixture?uid=two');assert.equal(state.body.subscriptions[0].user_id,'one');
 console.log('PASS notification isolation, public-only key, SSRF rejection, registration and unsubscribe ownership');
 assert(!logs.includes('Fatal error'),logs);
}finally{server.kill();await rm(temp,{recursive:true,force:true});}
