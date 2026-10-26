from pathlib import Path

p = Path('TehranSupermarket/barcode_scanner_v2.js')
s = p.read_text(encoding='utf-8')

old = "function formats(){var F=window.Html5QrcodeSupportedFormats;if(!F)return undefined;return [F.EAN_13,F.EAN_8,F.UPC_A,F.UPC_E,F.CODE_128,F.ITF];}"
new = "function formats(){var F=window.Html5QrcodeSupportedFormats;if(!F)return undefined;return [F.EAN_13,F.EAN_8,F.UPC_A,F.UPC_E,F.UPC_EAN_EXTENSION,F.CODE_128,F.CODE_39,F.CODE_93,F.CODABAR,F.ITF,F.RSS_14,F.RSS_EXPANDED].filter(function(x){return typeof x!=='undefined';});}"
if old in s:
    s = s.replace(old, new)

marker = "\n//# tehran-harddecode-v7\n"
if marker not in s:
    addon = r'''

//# tehran-harddecode-v7
(function(){
'use strict';
var ZXING_LIB='assets/zxing.min.js?v=0.23.0-tehran1';
var zxingPromise=null, hardReader=null, liveStarted=false, liveVideo=null, liveTimer=0;
function loadZXing(){
  if(window.ZXing&&window.ZXing.BrowserMultiFormatReader)return Promise.resolve(window.ZXing);
  if(zxingPromise)return zxingPromise;
  zxingPromise=new Promise(function(resolve,reject){
    var sc=document.createElement('script');sc.src=ZXING_LIB;sc.async=true;
    sc.onload=function(){window.ZXing&&window.ZXing.BrowserMultiFormatReader?resolve(window.ZXing):reject(new Error('zxing_not_ready'));};
    sc.onerror=function(){reject(new Error('zxing_load_failed'));};document.head.appendChild(sc);
  });
  return zxingPromise;
}
function statusEl(){return document.getElementById('barcode-status');}
function setStatus(t,kind){var e=statusEl();if(!e)return;e.textContent=t;e.classList.remove('scan-good','scan-warn');if(kind)e.classList.add(kind);}
function submitCode(text){
  var code=String(text||'').replace(/\D/g,'');if(code.length<4)return false;
  var input=document.getElementById('barcode-manual-input'),btn=document.getElementById('barcode-manual-go');
  if(!input||!btn)return false;input.value=code;btn.click();return true;
}
function zxingFormats(Z){
  var B=Z.BarcodeFormat||{};
  return [B.EAN_13,B.EAN_8,B.UPC_A,B.UPC_E,B.CODE_128,B.CODE_39,B.CODE_93,B.CODABAR,B.ITF,B.RSS_14,B.RSS_EXPANDED].filter(function(x){return typeof x!=='undefined';});
}
function makeHardReader(Z){
  var D=Z.DecodeHintType||{},hints=new Map();
  if(typeof D.POSSIBLE_FORMATS!=='undefined')hints.set(D.POSSIBLE_FORMATS,zxingFormats(Z));
  if(typeof D.TRY_HARDER!=='undefined')hints.set(D.TRY_HARDER,true);
  if(typeof D.ASSUME_GS1!=='undefined')hints.set(D.ASSUME_GS1,true);
  return new Z.BrowserMultiFormatReader(hints,90);
}
function stopHardLive(){
  liveStarted=false;liveVideo=null;if(liveTimer){clearTimeout(liveTimer);liveTimer=0;}
  if(hardReader){try{hardReader.stopContinuousDecode&&hardReader.stopContinuousDecode();}catch(e){}try{hardReader.stopAsyncDecode&&hardReader.stopAsyncDecode();}catch(e){}try{hardReader.reset&&hardReader.reset();}catch(e){}}
}
async function improveVideoTrack(video){
  try{
    var tr=video&&video.srcObject&&video.srcObject.getVideoTracks&&video.srcObject.getVideoTracks()[0];if(!tr)return;
    var caps=tr.getCapabilities?tr.getCapabilities():{};
    try{await tr.applyConstraints({width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}});}catch(e){}
    var adv=[];
    if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)adv.push({focusMode:'continuous'});
    if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)adv.push({exposureMode:'continuous'});
    if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)adv.push({whiteBalanceMode:'continuous'});
    if(caps.resizeMode&&caps.resizeMode.indexOf('none')>=0)adv.push({resizeMode:'none'});
    try{if(adv.length)await tr.applyConstraints({advanced:adv});}catch(e){}
    if(caps.zoom&&typeof caps.zoom.min==='number'){try{await tr.applyConstraints({advanced:[{zoom:Math.max(caps.zoom.min,1)}]});}catch(e){}}
    if(caps.torch){try{await tr.applyConstraints({advanced:[{torch:true}]});}catch(e){}}
    if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){setTimeout(function(){try{tr.applyConstraints({advanced:[{focusMode:'single-shot'}]});}catch(e){}},350);}
  }catch(e){}
}
async function startHardLive(){
  var sheet=document.getElementById('barcode-sheet');if(!sheet||sheet.hidden)return;
  var video=document.querySelector('#barcode-reader-v3 video');
  if(!video||!video.srcObject||video.readyState<2){liveTimer=setTimeout(startHardLive,220);return;}
  if(liveStarted&&liveVideo===video)return;
  stopHardLive();liveStarted=true;liveVideo=video;await improveVideoTrack(video);
  try{
    var Z=await loadZXing();if(!liveStarted)return;hardReader=makeHardReader(Z);
    if(hardReader.decodeFromVideoElementContinuously){
      hardReader.decodeFromVideoElementContinuously(video,function(res,err){if(res&&submitCode(res.getText?res.getText():res.text)){stopHardLive();}}).catch(function(){});
    }else if(hardReader.decodeOnceFromVideoElement){
      hardReader.decodeOnceFromVideoElement(video).then(function(res){if(res)submitCode(res.getText?res.getText():res.text);}).catch(function(){});
    }
  }catch(e){}
}
function imageFromFile(file){return new Promise(function(resolve,reject){var u=URL.createObjectURL(file),im=new Image();im.onload=function(){URL.revokeObjectURL(u);resolve(im)};im.onerror=function(){URL.revokeObjectURL(u);reject(new Error('image_load'))};im.src=u;});}
function canvasVariant(img,mode,rotation,cropY){
  rotation=rotation||0;cropY=cropY||0;
  var iw=img.naturalWidth||img.width,ih=img.naturalHeight||img.height;
  var sy=0,sh=ih;if(cropY){sh=Math.round(ih*.62);sy=Math.max(0,Math.min(ih-sh,Math.round((ih-sh)*cropY)));}
  var scale=Math.min(1,2400/Math.max(iw,sh));var w=Math.max(2,Math.round(iw*scale)),h=Math.max(2,Math.round(sh*scale));
  var c=document.createElement('canvas'),ctx=c.getContext('2d',{willReadFrequently:true});
  if(Math.abs(rotation)%180===90){c.width=h;c.height=w;ctx.translate(c.width/2,c.height/2);ctx.rotate(rotation*Math.PI/180);ctx.drawImage(img,0,sy,iw,sh,-w/2,-h/2,w,h);}
  else{c.width=w;c.height=h;ctx.drawImage(img,0,sy,iw,sh,0,0,w,h);}
  if(mode&&mode!=='color'){
    var d=ctx.getImageData(0,0,c.width,c.height),a=d.data;
    for(var i=0;i<a.length;i+=4){var g=.299*a[i]+.587*a[i+1]+.114*a[i+2];if(mode==='contrast')g=Math.max(0,Math.min(255,(g-128)*2.15+128));if(mode==='threshold')g=g>150?255:0;a[i]=a[i+1]=a[i+2]=g;}
    ctx.putImageData(d,0,0);
  }
  return c;
}
function decodeCanvas(reader,c){return new Promise(function(resolve,reject){c.toBlob(function(b){if(!b){reject(new Error('blob'));return;}var u=URL.createObjectURL(b),im=new Image();im.onload=function(){reader.decodeFromImageElement(im).then(function(r){URL.revokeObjectURL(u);resolve(r);},function(e){URL.revokeObjectURL(u);reject(e);});};im.onerror=function(){URL.revokeObjectURL(u);reject(new Error('image'));};im.src=u;},'image/jpeg',.96);});}
async function hardDecodePhoto(file){
  setStatus('در حال خواندن دقیق بارکد از عکس…','scan-good');
  var Z=await loadZXing(),r=makeHardReader(Z),url=URL.createObjectURL(file);
  try{var direct=await r.decodeFromImageUrl(url);if(direct)return direct;}catch(e){}finally{URL.revokeObjectURL(url);}
  var img=await imageFromFile(file);
  var variants=[
    ['color',0,0],['contrast',0,0],['threshold',0,0],
    ['contrast',90,0],['contrast',-90,0],
    ['contrast',0,.0],['contrast',0,.5],['contrast',0,1],
    ['threshold',0,.0],['threshold',0,.5],['threshold',0,1]
  ];
  for(var i=0;i<variants.length;i++){
    try{var v=variants[i],res=await decodeCanvas(r,canvasVariant(img,v[0],v[1],v[2]));if(res)return res;}catch(e){}
  }
  throw new Error('not_found');
}
function bindPhoto(){
  var inp=document.getElementById('barcode-photo-input');if(!inp||inp.__hardBound)return;inp.__hardBound=true;
  inp.addEventListener('change',function(ev){
    ev.stopImmediatePropagation();var f=inp.files&&inp.files[0];if(!f)return;
    hardDecodePhoto(f).then(function(res){var text=res&&(res.getText?res.getText():res.text);if(!submitCode(text))setStatus('بارکد خوانده شد ولی مقدار معتبر نبود.','scan-warn');},function(){setStatus('بارکد از عکس خوانده نشد؛ تصویر را طوری بگیر که خود بارکد بخش بزرگی از عکس باشد.','scan-warn');}).finally(function(){inp.value='';});
  },true);
}
function watch(){
  bindPhoto();var sheet=document.getElementById('barcode-sheet');if(!sheet)return;
  var mo=new MutationObserver(function(){if(sheet.hidden)stopHardLive();else setTimeout(startHardLive,180);});mo.observe(sheet,{attributes:true,attributeFilter:['hidden']});
  if(!sheet.hidden)setTimeout(startHardLive,180);
  var reader=document.getElementById('barcode-reader-v3');if(reader){new MutationObserver(function(){if(!sheet.hidden)setTimeout(startHardLive,100);}).observe(reader,{childList:true,subtree:true});}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',watch,{once:true});else watch();
loadZXing().catch(function(){});
})();
'''
    s += marker + addon

p.write_text(s, encoding='utf-8')
print('patched', p, len(s))
