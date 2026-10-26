(function(){
'use strict';
if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)return;

window.__tehranRearCameraLockInstalled=true;
var rawGUM=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
var bestId='';
try{bestId=sessionStorage.getItem('tehran_best_rear_camera_v8')||'';}catch(e){}
var resolving=null,activeTrack=null,keepTorchTimer=0;

function scannerOpen(){var s=document.getElementById('barcode-sheet');return !!(s&&!s.hidden);}
function labelScore(label){
  var l=(label||'').toLowerCase(),s=0;
  if(/back|rear|environment|پشت/.test(l))s+=100;
  if(/front|user|selfie/.test(l))s-=1000;
  if(/main|primary|wide|camera2 0|camera 0/.test(l))s+=70;
  if(/ultra|ultrawide|macro|tele|depth/.test(l))s-=140;
  return s;
}
function stopStream(s){try{s&&s.getTracks&&s.getTracks().forEach(function(t){try{t.stop();}catch(e){}});}catch(e){}}
function capsScore(track,label){
  var s=labelScore(label),c={},st={};
  try{c=track.getCapabilities?track.getCapabilities():{};}catch(e){}
  try{st=track.getSettings?track.getSettings():{};}catch(e){}
  if(st.facingMode==='environment')s+=100;
  if(c.torch)s+=320;
  if(c.focusMode&&c.focusMode.indexOf('continuous')>=0)s+=280;
  if(c.focusMode&&c.focusMode.indexOf('single-shot')>=0)s+=120;
  if(c.width&&c.width.max>=1920)s+=60;
  if(c.height&&c.height.max>=1080)s+=40;
  if(c.zoom&&typeof c.zoom.max==='number'&&c.zoom.max>1.5)s+=20;
  return s;
}
async function rearDevices(){
  var list=[];try{list=await navigator.mediaDevices.enumerateDevices();}catch(e){}
  var vids=(list||[]).filter(function(d){return d.kind==='videoinput';});
  var clearlyRear=vids.filter(function(d){return /back|rear|environment|پشت/i.test(d.label||'')&&!/front|user|selfie/i.test(d.label||'');});
  var nonFront=vids.filter(function(d){return !/front|user|selfie/i.test(d.label||'');});
  var out=clearlyRear.length?clearlyRear:nonFront;
  out.sort(function(a,b){return labelScore(b.label)-labelScore(a.label);});
  return out;
}
async function seedPermission(){
  var s=null;
  try{s=await rawGUM({video:{facingMode:{exact:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});}
  catch(e){try{s=await rawGUM({video:{facingMode:{ideal:'environment'}},audio:false});}catch(x){}}
  var id='';try{id=s&&s.getVideoTracks()[0]&&s.getVideoTracks()[0].getSettings().deviceId||'';}catch(e){}
  stopStream(s);return id;
}
async function probeDevice(d){
  var s=null;
  try{
    s=await rawGUM({video:{deviceId:{exact:d.deviceId},width:{ideal:1280},height:{ideal:720},frameRate:{ideal:30}},audio:false});
    var tr=s.getVideoTracks()[0];
    return {id:d.deviceId,score:capsScore(tr,d.label),label:d.label||''};
  }catch(e){return {id:d.deviceId,score:-9999,label:d.label||''};}
  finally{stopStream(s);}
}
async function resolveBestRear(){
  if(resolving)return resolving;
  resolving=(async function(){
    var devs=await rearDevices();
    if(!devs.length||!devs.some(function(d){return !!d.label;})){
      var seeded=await seedPermission();devs=await rearDevices();
      if(seeded&&!bestId)bestId=seeded;
    }
    if(bestId&&devs.some(function(d){return d.deviceId===bestId;}))return bestId;
    if(!devs.length)return bestId||'';
    var candidates=devs.slice(0,5),best={id:'',score:-99999};
    for(var i=0;i<candidates.length;i++){
      var r=await probeDevice(candidates[i]);
      if(r.score>best.score)best=r;
      if(r.score>=650)break;
    }
    bestId=best.id||bestId;
    if(bestId)try{sessionStorage.setItem('tehran_best_rear_camera_v8',bestId);}catch(e){}
    return bestId;
  })().finally(function(){resolving=null;});
  return resolving;
}
async function configureStream(stream){
  var tr=stream&&stream.getVideoTracks&&stream.getVideoTracks()[0];if(!tr)return stream;activeTrack=tr;
  var caps={};try{caps=tr.getCapabilities?tr.getCapabilities():{};}catch(e){}
  try{await tr.applyConstraints({width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}});}catch(e){}
  var adv=[];
  if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)adv.push({focusMode:'continuous'});
  if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)adv.push({exposureMode:'continuous'});
  if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)adv.push({whiteBalanceMode:'continuous'});
  if(caps.resizeMode&&caps.resizeMode.indexOf('none')>=0)adv.push({resizeMode:'none'});
  try{if(adv.length)await tr.applyConstraints({advanced:adv});}catch(e){}
  if(caps.zoom&&typeof caps.zoom.min==='number'){
    try{await tr.applyConstraints({advanced:[{zoom:Math.max(caps.zoom.min,1)}]});}catch(e){}
  }
  async function torchOn(){if(!activeTrack||activeTrack.readyState!=='live'||!caps.torch)return false;try{await activeTrack.applyConstraints({advanced:[{torch:true}]});return true;}catch(e){return false;}}
  if(caps.torch){await torchOn();setTimeout(torchOn,350);setTimeout(torchOn,900);}
  if(keepTorchTimer)clearInterval(keepTorchTimer);
  keepTorchTimer=setInterval(function(){if(!scannerOpen()){clearInterval(keepTorchTimer);keepTorchTimer=0;return;}torchOn();},1800);
  return stream;
}

navigator.mediaDevices.getUserMedia=async function(constraints){
  if(!scannerOpen())return rawGUM(constraints);
  var v=constraints&&constraints.video;
  var requestedId='';
  try{requestedId=v&&v.deviceId&&(typeof v.deviceId==='string'?v.deviceId:(v.deviceId.exact||''));}catch(e){}
  var id=requestedId||await resolveBestRear();
  var s=null;
  if(id){
    try{s=await rawGUM({video:{deviceId:{exact:id},width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}},audio:false});}
    catch(e){if(id===bestId){bestId='';try{sessionStorage.removeItem('tehran_best_rear_camera_v8');}catch(x){}}}
  }
  if(!s){
    try{s=await rawGUM({video:{facingMode:{exact:'environment'},width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}},audio:false});}
    catch(e){s=await rawGUM({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});}
  }
  try{var st=s.getVideoTracks()[0].getSettings();if(st.deviceId){bestId=st.deviceId;sessionStorage.setItem('tehran_best_rear_camera_v8',bestId);}}catch(e){}
  return configureStream(s);
};

// Extra hard live decoding: process a sharpened/contrast frame repeatedly.
var hardTimer=0,hardBusy=false,hardReader=null,zxingLoading=null,variant=0;
function loadZXing(){
  if(window.ZXing&&window.ZXing.BrowserMultiFormatReader)return Promise.resolve(window.ZXing);
  if(zxingLoading)return zxingLoading;
  zxingLoading=new Promise(function(resolve,reject){var s=document.createElement('script');s.src='assets/zxing.min.js?v=0.23.0-tehran2';s.async=true;s.onload=function(){window.ZXing?resolve(window.ZXing):reject(new Error('zxing'));};s.onerror=reject;document.head.appendChild(s);});
  return zxingLoading;
}
function submitCode(text){var code=String(text||'').replace(/\D/g,'');if(code.length<4)return false;var i=document.getElementById('barcode-manual-input'),b=document.getElementById('barcode-manual-go');if(!i||!b)return false;i.value=code;b.click();return true;}
function makeReader(Z){var D=Z.DecodeHintType||{},B=Z.BarcodeFormat||{},h=new Map(),f=[B.EAN_13,B.EAN_8,B.UPC_A,B.UPC_E,B.CODE_128,B.CODE_39,B.CODE_93,B.CODABAR,B.ITF,B.RSS_14,B.RSS_EXPANDED].filter(function(x){return typeof x!=='undefined';});if(typeof D.POSSIBLE_FORMATS!=='undefined')h.set(D.POSSIBLE_FORMATS,f);if(typeof D.TRY_HARDER!=='undefined')h.set(D.TRY_HARDER,true);return new Z.BrowserMultiFormatReader(h,80);}
function frameCanvas(video,mode){
  var vw=video.videoWidth,vh=video.videoHeight;if(!vw||!vh)return null;
  var crop=(mode%3)!==0,sw=crop?Math.round(vw*.96):vw,sh=crop?Math.round(vh*.62):vh,sx=Math.round((vw-sw)/2),sy=Math.round((vh-sh)/2);
  var scale=Math.min(1.45,1500/sw),w=Math.max(900,Math.round(sw*scale)),h=Math.max(420,Math.round(sh*scale));
  var c=document.createElement('canvas'),x=c.getContext('2d',{willReadFrequently:true});c.width=w;c.height=h;x.drawImage(video,sx,sy,sw,sh,0,0,w,h);
  if(mode%3){var d=x.getImageData(0,0,w,h),a=d.data;for(var i=0;i<a.length;i+=4){var g=.299*a[i]+.587*a[i+1]+.114*a[i+2];g=(g-128)*(mode%3===1?1.65:2.15)+128;g=Math.max(0,Math.min(255,g));a[i]=a[i+1]=a[i+2]=g;}x.putImageData(d,0,0);}
  return c;
}
async function decodeCanvas(c){
  var Z=await loadZXing();if(!hardReader)hardReader=makeReader(Z);
  if(typeof hardReader.decodeFromCanvas==='function')return hardReader.decodeFromCanvas(c);
  return new Promise(function(resolve,reject){c.toBlob(function(b){if(!b)return reject(new Error('blob'));var u=URL.createObjectURL(b),im=new Image();im.onload=function(){hardReader.decodeFromImageElement(im).then(function(r){URL.revokeObjectURL(u);resolve(r);},function(e){URL.revokeObjectURL(u);reject(e);});};im.onerror=function(){URL.revokeObjectURL(u);reject(new Error('image'));};im.src=u;},'image/jpeg',.96);});
}
async function hardTick(){
  if(!scannerOpen()){hardTimer=0;hardBusy=false;return;}
  var video=document.querySelector('#barcode-reader-v3 video');
  if(!video||video.readyState<2){hardTimer=setTimeout(hardTick,220);return;}
  if(hardBusy){hardTimer=setTimeout(hardTick,150);return;}
  hardBusy=true;
  try{var c=frameCanvas(video,variant++);if(c){var r=await decodeCanvas(c);var t=r&&(r.getText?r.getText():r.text);if(t&&submitCode(t)){hardBusy=false;return;}}}catch(e){}
  hardBusy=false;hardTimer=setTimeout(hardTick,260);
}
function startHard(){if(hardTimer)clearTimeout(hardTimer);hardTimer=setTimeout(hardTick,350);}
function watch(){var s=document.getElementById('barcode-sheet');if(!s)return;new MutationObserver(function(){if(!s.hidden)startHard();}).observe(s,{attributes:true,attributeFilter:['hidden']});document.addEventListener('click',function(e){if(e.target&&e.target.id==='barcode-retry')startHard();});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',watch,{once:true});else watch();
})();
