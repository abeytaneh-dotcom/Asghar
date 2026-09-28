<?php
declare(strict_types=1);

$role = ($_GET['role'] ?? '') === 'patient' ? 'patient' : 'caller';
$patientId = trim((string)($_GET['patient_id'] ?? ''));
$key = trim((string)($_GET['key'] ?? ''));
$room = trim((string)($_GET['room'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Care AI Video Call</title>
<style>
html,body{margin:0;background:#07121e;color:#fff;font-family:tahoma;height:100%;overflow:hidden}
.wrap{position:relative;width:100%;height:100%}
#remote{width:100%;height:100%;object-fit:cover;background:#0b1723}
#local{position:absolute;left:12px;top:12px;width:28%;max-width:160px;border-radius:16px;border:2px solid #fff5;display:<?= $role==='patient'?'none':'block' ?>;background:#111}
.top{position:absolute;right:12px;top:12px;background:#091725cc;border-radius:16px;padding:10px 12px}
#status{font-size:13px;color:#cfe7ff}
.controls{position:absolute;left:0;right:0;bottom:22px;display:flex;justify-content:center;gap:12px}
button{border:0;border-radius:18px;padding:13px 18px;font:inherit;color:#fff;background:#176fd0}
.end{background:#d94352}.mute{background:#495c70}
.notice{position:absolute;left:12px;right:12px;bottom:90px;text-align:center;color:#b9cad8;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
<video id="remote" autoplay playsinline></video>
<video id="local" autoplay playsinline muted></video>
<div class="top"><b>Care AI</b><div id="status">در حال آماده‌سازی تماس...</div></div>
<div class="notice"><?= $role==='patient' ? 'تصویر خود بیمار نمایش داده نمی‌شود.' : 'تماس تصویری امن Care AI' ?></div>
<div class="controls">
<button class="mute" id="mute">قطع صدا</button>
<button class="end" id="end">پایان تماس</button>
</div>
</div>
<script>
const API='api.php';
const ROLE=<?=json_encode($role)?>;
const PATIENT=<?=json_encode($patientId)?>;
const KEY=<?=json_encode($key)?>;
let ROOM=<?=json_encode($room)?>;
const TOKEN=<?=json_encode($token)?>;
const statusEl=document.getElementById('status');
const remote=document.getElementById('remote');
const local=document.getElementById('local');
let pc=null, stream=null, muted=false, ended=false;

async function api(payload){
  const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),cache:'no-store'});
  const j=await r.json().catch(()=>({ok:false}));
  if(!r.ok && !j.ok) throw new Error(j.code||'API');
  return j;
}
function status(s){statusEl.textContent=s;}
async function iceConfig(){
  const j=await api({action:'rtc_config'});
  return {iceServers:j.iceServers||[]};
}
function waitIce(pc){
  if(pc.iceGatheringState==='complete')return Promise.resolve();
  return new Promise(resolve=>{
    const f=()=>{if(pc.iceGatheringState==='complete'){pc.removeEventListener('icegatheringstatechange',f);resolve();}};
    pc.addEventListener('icegatheringstatechange',f);
    setTimeout(resolve,5000);
  });
}
async function media(){
  stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:true});
  local.srcObject=stream;
}
async function build(){
  pc=new RTCPeerConnection(await iceConfig());
  stream.getTracks().forEach(t=>pc.addTrack(t,stream));
  pc.ontrack=e=>{remote.srcObject=e.streams[0];status('تماس برقرار است');};
  pc.onconnectionstatechange=()=>{
    const s=pc.connectionState;
    if(s==='connected')status('تماس برقرار است');
    else if(s==='failed'||s==='disconnected')status('ارتباط ضعیف یا قطع شده');
    else if(s==='closed')status('تماس پایان یافت');
  };
}
async function caller(){
  status('در حال ایجاد تماس...');
  const created=await api({action:'video_create_by_key',patient_id:PATIENT,key:KEY});
  ROOM=created.room;
  await media();
  await build();
  const offer=await pc.createOffer();
  await pc.setLocalDescription(offer);
  await waitIce(pc);
  await api({action:'video_offer',room:ROOM,key:KEY,offer:JSON.stringify(pc.localDescription)});
  status('در انتظار پاسخ خودکار بیمار...');
  const poll=setInterval(async()=>{
    if(ended)return clearInterval(poll);
    try{
      const j=await api({action:'video_get_answer',room:ROOM,key:KEY});
      if(j.state==='ended'){clearInterval(poll);status('تماس پایان یافت');return;}
      if(j.answer && !pc.currentRemoteDescription){
        await pc.setRemoteDescription(JSON.parse(j.answer));
        clearInterval(poll);
        status('تماس برقرار شد');
      }
    }catch(e){}
  },900);
}
async function patient(){
  status('در حال پاسخگویی خودکار...');
  await media();
  await build();
  let offer='';
  for(let i=0;i<30 && !offer;i++){
    try{
      const j=await api({action:'video_get_offer',room:ROOM,token:TOKEN});
      offer=j.offer||'';
      if(j.state==='ended')throw new Error('ENDED');
    }catch(e){}
    if(!offer)await new Promise(r=>setTimeout(r,500));
  }
  if(!offer)throw new Error('NO_OFFER');
  await pc.setRemoteDescription(JSON.parse(offer));
  const ans=await pc.createAnswer();
  await pc.setLocalDescription(ans);
  await waitIce(pc);
  await api({action:'video_answer',room:ROOM,token:TOKEN,answer:JSON.stringify(pc.localDescription)});
  status('تماس برقرار شد');
}
async function hangup(){
  if(ended)return;
  ended=true;
  try{
    if(ROLE==='patient')await api({action:'video_end',room:ROOM,token:TOKEN});
    else await api({action:'video_end',room:ROOM,key:KEY});
  }catch(e){}
  try{if(pc)pc.close();}catch(e){}
  try{if(stream)stream.getTracks().forEach(t=>t.stop());}catch(e){}
  status('تماس پایان یافت');
  setTimeout(()=>{if(window.AndroidBridge&&AndroidBridge.closeCall)AndroidBridge.closeCall();else history.back();},500);
}
document.getElementById('end').onclick=hangup;
document.getElementById('mute').onclick=()=>{
  muted=!muted;
  if(stream)stream.getAudioTracks().forEach(t=>t.enabled=!muted);
  document.getElementById('mute').textContent=muted?'وصل صدا':'قطع صدا';
};
window.addEventListener('beforeunload',()=>{try{if(stream)stream.getTracks().forEach(t=>t.stop());}catch(e){}});
(async()=>{
  try{
    if(ROLE==='caller'){
      if(!PATIENT||!KEY)throw new Error('LINK');
      await caller();
    }else{
      if(!ROOM||!TOKEN)throw new Error('AUTH');
      await patient();
    }
  }catch(e){
    status('برقراری تماس انجام نشد');
  }
})();
</script>
</body>
</html>
