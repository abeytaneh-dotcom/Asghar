import {mkdtemp,cp,writeFile,readFile,rm} from 'node:fs/promises';
import {join,resolve} from 'node:path';import {tmpdir} from 'node:os';import {spawnSync} from 'node:child_process';import assert from 'node:assert/strict';
const temp=await mkdtemp(join(tmpdir(),'nz-media-'));const flags=JSON.parse(process.env.PHP_ARGS_JSON||'[]');
try{
 await cp(resolve('WebSaza/nz-create-pending.php'),join(temp,'media.php'));
 for(const [name,codec,format] of [['long.mp4','libx264','mp4'],['short.webm','libvpx','webm']]){const seconds=name==='long.mp4'?62:2;const r=spawnSync('ffmpeg',['-y','-f','lavfi','-i','color=c=blue:s=320x240:r=4','-f','lavfi','-i','sine=frequency=440:sample_rate=22050','-t',String(seconds),'-c:v',codec,'-c:a',format==='mp4'?'aac':'libvorbis',join(temp,name)],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);}
 await writeFile(join(temp,'test.php'),`<?php require __DIR__.'/media.php';
 foreach(['long.mp4'=>62,'short.webm'=>2] as $name=>$expected){$d=nzcp_container_duration(__DIR__.'/'.$name);if($d===null||abs($d-$expected)>.6)throw new RuntimeException('Container duration '.$name.' '.var_export($d,true));}
 [$name,$duration]=nzcp_process_video(['error'=>UPLOAD_ERR_OK,'tmp_name'=>__DIR__.'/long.mp4','size'=>filesize(__DIR__.'/long.mp4')],0,'test',true);
 if($duration<61)throw new RuntimeException('Video was shortened');echo json_encode(['file'=>__DIR__.'/marketplace-uploads/'.$name,'duration'=>$duration]);`);
 const r=spawnSync(process.env.PHP_BIN||'php',[...flags,join(temp,'test.php')],{encoding:'utf8'});assert.equal(r.status,0,r.stderr+r.stdout);const out=JSON.parse(r.stdout);const probe=spawnSync('ffprobe',['-v','error','-show_entries','format=duration:stream=codec_type,width,height','-of','json',out.file],{encoding:'utf8'});assert.equal(probe.status,0,probe.stderr);const meta=JSON.parse(probe.stdout);assert(Number(meta.format.duration)>61);assert(meta.streams.some(s=>s.codec_type==='audio'));const v=meta.streams.find(s=>s.codec_type==='video');assert(v.width<=1280);assert((await readFile(out.file)).length>0);
 console.log('PASS MP4/WebM duration fallback, full video beyond one minute, automatic transcoding and preserved audio');
}finally{await rm(temp,{recursive:true,force:true});}
