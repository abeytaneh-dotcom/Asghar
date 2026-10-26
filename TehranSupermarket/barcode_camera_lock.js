(function(){
'use strict';
if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia||window.__tehranRearCameraLockInstalled)return;
window.__tehranRearCameraLockInstalled=true;
var original=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
function scannerOpen(){var s=document.getElementById('barcode-sheet');return !!(s&&!s.hidden);}
function rearConstraints(c,exact){
  c=c||{};
  var v=c.video;
  if(v===true||!v)v={};
  if(typeof v!=='object')v={};
  var nv={};
  Object.keys(v).forEach(function(k){if(k!=='deviceId'&&k!=='facingMode')nv[k]=v[k];});
  nv.facingMode=exact?{exact:'environment'}:{ideal:'environment'};
  return {video:nv,audio:false};
}
navigator.mediaDevices.getUserMedia=function(constraints){
  if(!scannerOpen())return original(constraints);
  var hard=rearConstraints(constraints,true);
  return original(hard).catch(function(err){
    var soft=rearConstraints(constraints,false);
    return original(soft);
  });
};
})();
