(function(){
'use strict';
var ZXING_SRC='assets/zxing.min.js?v=0.23.0-v8';
function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn,{once:true});else fn();}
function loadZXing(){
  if(window.ZXing&&window.ZXing.BrowserMultiFormatReader)return Promise.resolve(window.ZXing);
  if(window.__tehranZxingV8)return window.__tehranZxingV8;
  window.__tehranZxingV8=new Promise(function(resolve,reject){
    var s=document.createElement('script');s.src=ZXING_SRC;s.async=true;
    s.onload=function(){window.ZXing?resolve(window.ZXing):reject(new Error('zxing_not_ready'));};
    s.onerror=function(){reject(new Error('zxing_load_failed'));};document.head.appendChild(s);
  });
  return window.__tehranZxingV8;
}
ready(function(){
  var oldFab=document.getElementById('barcode-scan-fab'),oldSheet=document.getElementById('barcode-sheet');
  if(!oldFab||!oldSheet)return;
  var fab=oldFab.cloneNode(true),sheet=oldSheet.cloneNode(true);oldFab.replaceWith(fab);oldSheet.replaceWith(sheet);
  var oldTools=sheet.querySelector('.barcode-v3-tools');if(oldTools)oldTools.remove();
  var camera=sheet.querySelector('.barcode-camera'),statusEl=sheet.querySelector('#barcode-status'),result=sheet.querySelector('#barcode-result'),nameEl=sheet.querySelector('#barcode-name'),priceEl=sheet.querySelector('#barcode-price'),stockEl=sheet.querySelector('#barcode-stock'),closeBtn=sheet.querySelector('#barcode-close'),retry=sheet.querySelector('#barcode-retry'),manual=sheet.querySelector('#barcode-manual-input'),manualGo=sheet.querySelector('#barcode-manual-go');
  if(!camera||!statusEl)return;
  camera.innerHTML='<video id="barcode-video-v8" playsinline muted autoplay></video><div class="barcode-v8-guide"><span></span></div>';
  var video=camera.querySelector('#barcode-video-v8');
  var tools=document.createElement('div');tools.className='barcode-v8-tools';
  tools.innerHTML='<button type="button" id="barcode-v8-focus">◎ فوکوس</button><button type="button" id="barcode-v8-torch">🔦 نور</button><button type="button" id="barcode-v8-lens">🔄 لنز پشت</button><button type="button" id="barcode-v8-photo">📷 عکس بارکد</button><input id="barcode-v8-photo-input" type="file" accept="image/*" capture="environment" hidden>';
  camera.insertAdjacentElement('afterend',tools);
  var focusBtn=tools.querySelector('#barcode-v8-focus'),torchBtn=tools.querySelector('#barcode-v8-torch'),lensBtn=tools.querySelector('#barcode-v8-lens'),photoBtn=tools.querySelector('#barcode-v8-photo'),photoInput=tools.querySelector('#barcode-v8-photo-input');
  var style=document.createElement('style');style.id='tehran-barcode-v8-style';style.textContent='\
.barcode-camera{aspect-ratio:16/10!important;min-height:290px!important;background:#07100b!important}.barcode-camera video{width:100%!important;height:100%!important;object-fit:cover!important;display:block}.barcode-v8-guide{position:absolute;left:4%;right:4%;top:31%;height:38%;border:3px solid #5cff88;border-radius:16px;box-shadow:0 0 0 999px rgba(0,0,0,.08);pointer-events:none}.barcode-v8-guide span{position:absolute;left:4%;right:4%;top:50%;height:2px;background:#5cff88;box-shadow:0 0 12px #33e76b}.barcode-v8-tools{display:flex;gap:7px;margin-top:8px;overflow:auto;padding-bottom:2px}.barcode-v8-tools button{border:1px solid #cfe0d3;background:#fff;color:#17472e;border-radius:10px;padding:8px 10px;font:inherit;font-size:12px;font-weight:800;white-space:nowrap}.barcode-status.scan-good{background:#e9faee;color:#12642c}.barcode-status.scan-warn{background:#fff7df;color:#765c0b}';document.head.appendChild(style);

  var stream=null,track=null,rearDevices=[],rearIndex=0,decoder=null,scanTimer=0,active=false,busy=false,torchOn=false,noHit=0,lastCode='',lastAt=0;
  var cFull=document.createElement('canvas'),cCenter=document.createElement('canvas'),cContrast=document.createElement('canvas');
  function faNum(n){try{return new Intl.NumberFormat('fa-IR').format(n);}catch(e){return String(n)}}
  function speak(text){try{if(!('speechSynthesis' in window))return;window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang='fa-IR';u.rate=.93;window.speechSynthesis.speak(u);}catch(e){}}
  function setStatus(t,kind){statusEl.textContent=t;statusEl.classList.remove('scan-good','scan-warn');if(kind)statusEl.classList.add(kind);}
  function zxingFormats(Z){var B=Z.BarcodeFormat||{};return [B.EAN_13,B.EAN_8,B.UPC_A,B.UPC_E,B.CODE_128,B.CODE_39,B.CODE_93,B.CODABAR,B.ITF,B.RSS_14,B.RSS_EXPANDED].filter(function(x){return typeof x!=='undefined';});}
  function makeDecoder(Z){var D=Z.DecodeHintType||{},h=new Map();if(D.POSSIBLE_FORMATS!==undefined)h.set(D.POSSIBLE_FORMATS,zxingFormats(Z));if(D.TRY_HARDER!==undefined)h.set(D.TRY_HARDER,true);if(D.ASSUME_GS1!==undefined)h.set(D.ASSUME_GS1,true);return new Z.BrowserMultiFormatReader(h,80);}
  async function lookup(code){
    code=String(code||'').replace(/\D/g,'');if(!code)return false;if(code===lastCode&&Date.now()-lastAt<1500)return false;lastCode=code;lastAt=Date.now();
    setStatus('بارکد '+code+' خوانده شد؛ در حال دریافت قیمت و موجودی…','scan-good');
    try{var r=await fetch('barcode_lookup.php?barcode='+encodeURIComponent(code),{cache:'no-store'}),d=await r.json();if(d&&d.ok&&d.found&&d.product){var p=d.product;nameEl.textContent=p.name;priceEl.textContent=faNum(p.price)+' تومان';stockEl.textContent=faNum(p.stock)+' عدد';result.classList.add('show');setStatus('محصول پیدا شد ✓','scan-good');speak(p.name+'، قیمت '+faNum(p.price)+' تومان، موجودی '+faNum(p.stock)+' عدد');await stopCamera();return true;}setStatus('بارکد '+code+' خوانده شد ولی محصول در فهرست ثبت نشده است.','scan-warn');speak('این بارکد در محصولات ثبت نشده است');return false;}catch(e){setStatus('بارکد خوانده شد ولی دریافت اطلاعات محصول خطا داشت.','scan-warn');return false;}
  }
  function scoreDevice(d){var l=(d.label||'').toLowerCase(),s=0;if(/back|rear|environment|پشت/.test(l))s+=200;if(/front|user|selfie/.test(l))s-=2000;if(/main|primary|wide|camera2 0|camera 0/.test(l))s+=150;if(/ultra|macro|tele|depth/.test(l))s-=180;return s;}
  async function discoverRear(){
    var warm=null;try{warm=await navigator.mediaDevices.getUserMedia({video:{facingMode:{exact:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});}catch(e){try{warm=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});}catch(x){}}
    if(warm)warm.getTracks().forEach(function(t){try{t.stop();}catch(e){}});
    var ds=[];try{ds=await navigator.mediaDevices.enumerateDevices();}catch(e){}
    var vids=ds.filter(function(d){return d.kind==='videoinput';});
    var clear=vids.filter(function(d){return /back|rear|environment|پشت/i.test(d.label||'')&&!/front|user|selfie/i.test(d.label||'');});
    var nonFront=vids.filter(function(d){return !/front|user|selfie/i.test(d.label||'');});
    rearDevices=(clear.length?clear:nonFront).sort(function(a,b){return scoreDevice(b)-scoreDevice(a);});lensBtn.hidden=rearDevices.length<2;
  }
  async function autoTorch(){if(!track)return false;var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}if(!caps.torch){torchBtn.textContent='🔦 بدون فلش';torchBtn.disabled=true;return false;}torchBtn.disabled=false;for(var i=0;i<3;i++){try{await track.applyConstraints({advanced:[{torch:true}]});torchOn=true;torchBtn.textContent='🔦 خاموش';return true;}catch(e){await new Promise(function(r){setTimeout(r,250);});}}return false;}
  async function tuneTrack(){
    if(!track)return;var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}
    var c={};if(caps.width&&caps.width.max)c.width={ideal:Math.min(1920,caps.width.max)};if(caps.height&&caps.height.max)c.height={ideal:Math.min(1080,caps.height.max)};if(caps.frameRate)c.frameRate={ideal:Math.min(30,caps.frameRate.max||30)};
    try{if(Object.keys(c).length)await track.applyConstraints(c);}catch(e){}
    var adv=[];if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)adv.push({focusMode:'continuous'});if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)adv.push({exposureMode:'continuous'});if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)adv.push({whiteBalanceMode:'continuous'});try{if(adv.length)await track.applyConstraints({advanced:adv});}catch(e){}
    if(caps.zoom&&typeof caps.zoom.min==='number'){try{await track.applyConstraints({advanced:[{zoom:caps.zoom.min}]});}catch(e){}}
    if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){try{await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});}catch(e){}setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},600);}
    await autoTorch();
  }
  function drawCrops(){
    if(!video.videoWidth||!video.videoHeight)return false;var vw=video.videoWidth,vh=video.videoHeight;
    var fullW=Math.min(1600,vw),fullH=Math.max(1,Math.round(vh*fullW/vw));cFull.width=fullW;cFull.height=fullH;cFull.getContext('2d',{willReadFrequently:true}).drawImage(video,0,0,vw,vh,0,0,fullW,fullH);
    var sw=Math.round(vw*.96),sh=Math.round(vh*.52),sx=Math.round((vw-sw)/2),sy=Math.round(vh*.24);var outW=Math.min(1900,Math.max(1300,sw*1.25)),outH=Math.round(sh*outW/sw);cCenter.width=outW;cCenter.height=outH;cCenter.getContext('2d',{willReadFrequently:true}).drawImage(video,sx,sy,sw,sh,0,0,outW,outH);
    cContrast.width=outW;cContrast.height=outH;var cc=cContrast.getContext('2d',{willReadFrequently:true});cc.filter='grayscale(1) contrast(1.85)';cc.drawImage(video,sx,sy,sw,sh,0,0,outW,outH);cc.filter='none';return true;
  }
  function decodeCanvas(c){try{var r=decoder.decodeFromCanvas(c);return r?(r.getText?r.getText():r.text||String(r)):'';}catch(e){return '';}}
  async function scanLoop(){
    if(!active)return;if(busy){scanTimer=setTimeout(scanLoop,70);return;}busy=true;
    try{if(drawCrops()){var code=decodeCanvas(cCenter);if(!code&&noHit%2===0)code=decodeCanvas(cContrast);if(!code&&noHit%4===0)code=decodeCanvas(cFull);if(code){await lookup(code);busy=false;return;}noHit++;if(noHit===10||noHit===25)refocus(false);}}
    catch(e){}busy=false;if(active)scanTimer=setTimeout(scanLoop,90);
  }
  async function stopCamera(){active=false;if(scanTimer)clearTimeout(scanTimer);scanTimer=0;busy=false;if(stream){stream.getTracks().forEach(function(t){try{t.stop();}catch(e){}});}stream=null;track=null;video.srcObject=null;torchOn=false;}
  async function startCamera(useIndex){
    await stopCamera();result.classList.remove('show');setStatus('در حال باز کردن لنز اصلی پشت…');try{var Z=await loadZXing();decoder=makeDecoder(Z);}catch(e){setStatus('موتور بارکدخوان بارگذاری نشد.','scan-warn');return;}
    if(!rearDevices.length)await discoverRear();var vc={width:{ideal:1920,min:1280},height:{ideal:1080,min:720},frameRate:{ideal:30,min:20}};if(rearDevices.length){vc.deviceId={exact:rearDevices[(useIndex?rearIndex:0)%rearDevices.length].deviceId};}else vc.facingMode={exact:'environment'};
    try{stream=await navigator.mediaDevices.getUserMedia({video:vc,audio:false});}catch(e){delete vc.deviceId;vc.facingMode={ideal:'environment'};try{stream=await navigator.mediaDevices.getUserMedia({video:vc,audio:false});}catch(x){setStatus('دوربین پشت باز نشد.','scan-warn');return;}}
    track=stream.getVideoTracks()[0]||null;video.srcObject=stream;try{await video.play();}catch(e){}await tuneTrack();active=true;noHit=0;setStatus('اسکن خودکار فعال است؛ بارکد را داخل کادر سبز بگیر. چراغ‌قوه در صورت پشتیبانی خودکار روشن می‌شود.');scanLoop();
  }
  async function refocus(show){if(!track)return;var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}try{if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},550);}else if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)await track.applyConstraints({advanced:[{focusMode:'continuous'}]});if(show!==false)setStatus('فوکوس مجدد انجام شد.','scan-good');}catch(e){}}
  function makeVariant(src,mode,rot,crop){var w=src.width,h=src.height,sx=0,sy=0,sw=w,sh=h;if(crop==='center'){sx=Math.round(w*.05);sw=Math.round(w*.90);sy=Math.round(h*.18);sh=Math.round(h*.64);}else if(crop==='lower'){sx=Math.round(w*.03);sw=Math.round(w*.94);sy=Math.round(h*.35);sh=Math.round(h*.60);}var cw=sw,ch=sh;if(rot===90||rot===-90){cw=sh;ch=sw;}var c=document.createElement('canvas');var scale=Math.min(2.2,Math.max(1,1800/Math.max(cw,ch)));c.width=Math.max(1,Math.round(cw*scale));c.height=Math.max(1,Math.round(ch*scale));var x=c.getContext('2d',{willReadFrequently:true});x.save();if(rot===90){x.translate(c.width,0);x.rotate(Math.PI/2);}else if(rot===-90){x.translate(0,c.height);x.rotate(-Math.PI/2);}if(mode==='contrast')x.filter='grayscale(1) contrast(2)';x.drawImage(src,sx,sy,sw,sh,0,0,Math.round(sw*scale),Math.round(sh*scale));x.restore();x.filter='none';return c;}
  async function decodePhoto(file){var bmp=null;try{bmp=await createImageBitmap(file);}catch(e){var u=URL.createObjectURL(file);bmp=await new Promise(function(res,rej){var im=new Image();im.onload=function(){URL.revokeObjectURL(u);res(im)};im.onerror=rej;im.src=u;});}var vars=[];['full','center','lower'].forEach(function(c){vars.push(makeVariant(bmp,'raw',0,c));vars.push(makeVariant(bmp,'contrast',0,c));});vars.push(makeVariant(bmp,'contrast',90,'full'));vars.push(makeVariant(bmp,'contrast',-90,'full'));for(var i=0;i<vars.length;i++){var code=decodeCanvas(vars[i]);if(code)return code;}return '';}
  async function openSheet(){sheet.hidden=false;document.documentElement.style.overflow='hidden';rearIndex=0;rearDevices=[];await startCamera(false);}
  async function closeSheet(){await stopCamera();sheet.hidden=true;document.documentElement.style.overflow='';}
  fab.addEventListener('click',openSheet);closeBtn&&closeBtn.addEventListener('click',closeSheet);retry&&retry.addEventListener('click',function(){startCamera(false);});sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  focusBtn.addEventListener('click',function(){refocus(true);});torchBtn.addEventListener('click',async function(){if(!track)return;var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}if(!caps.torch){setStatus('این لنز اجازه کنترل چراغ‌قوه را به مرورگر نمی‌دهد.','scan-warn');return;}torchOn=!torchOn;try{await track.applyConstraints({advanced:[{torch:torchOn}]});torchBtn.textContent=torchOn?'🔦 خاموش':'🔦 نور';}catch(e){torchOn=!torchOn;}});
  lensBtn.addEventListener('click',async function(){if(!rearDevices.length)await discoverRear();if(rearDevices.length<2){setStatus('لنز پشت دیگری پیدا نشد.');return;}rearIndex=(rearIndex+1)%rearDevices.length;setStatus('در حال تغییر لنز پشت…');await startCamera(true);});
  manualGo&&manualGo.addEventListener('click',function(){lookup(manual.value);});manual&&manual.addEventListener('keydown',function(e){if(e.key==='Enter')lookup(manual.value);});
  photoBtn.addEventListener('click',function(){photoInput.click();});photoInput.addEventListener('change',async function(){var f=photoInput.files&&photoInput.files[0];if(!f)return;try{await stopCamera();setStatus('در حال تحلیل چندمرحله‌ای عکس بارکد…');var Z=await loadZXing();decoder=makeDecoder(Z);var code=await decodePhoto(f);if(code)await lookup(code);else setStatus('بارکد از این عکس خوانده نشد.','scan-warn');}catch(e){setStatus('خطا در تحلیل عکس بارکد.','scan-warn');}finally{photoInput.value='';}});
  document.addEventListener('visibilitychange',function(){if(document.hidden)stopCamera();});loadZXing().catch(function(){});
});
})();
