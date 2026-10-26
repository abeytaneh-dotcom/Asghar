(function(){
'use strict';
if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia&&!window.__tehranRearCameraLockInstalled){
  window.__tehranRearCameraLockInstalled=true;
  var __tehranOriginalGUM=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
  function __tehranScannerOpen(){var s=document.getElementById('barcode-sheet');return !!(s&&!s.hidden);}
  function __tehranRearConstraints(c,exact){
    c=c||{};var v=c.video;if(v===true||!v||typeof v!=='object')v={};var nv={};
    Object.keys(v).forEach(function(k){if(k!=='deviceId'&&k!=='facingMode')nv[k]=v[k];});
    nv.facingMode=exact?{exact:'environment'}:{ideal:'environment'};
    if(!nv.width)nv.width={ideal:1920};
    if(!nv.height)nv.height={ideal:1080};
    if(!nv.frameRate)nv.frameRate={ideal:30};
    return {video:nv,audio:false};
  }
  navigator.mediaDevices.getUserMedia=function(constraints){
    if(!__tehranScannerOpen())return __tehranOriginalGUM(constraints);
    return __tehranOriginalGUM(__tehranRearConstraints(constraints,true)).catch(function(){return __tehranOriginalGUM(__tehranRearConstraints(constraints,false));});
  };
}
})();

(function(){
'use strict';
var LIB='assets/html5-qrcode.min.js?v=2.3.8-4';
function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn,{once:true});else fn();}
function loadLib(){
  if(window.Html5Qrcode)return Promise.resolve();
  if(window.__tehranHtml5QrLoading)return window.__tehranHtml5QrLoading;
  window.__tehranHtml5QrLoading=new Promise(function(resolve,reject){
    var s=document.createElement('script');s.src=LIB;s.async=true;
    s.onload=function(){window.Html5Qrcode?resolve():reject(new Error('library_not_ready'));};
    s.onerror=function(){reject(new Error('library_load_failed'));};document.head.appendChild(s);
  });
  return window.__tehranHtml5QrLoading;
}
ready(function(){
  var oldFab=document.getElementById('barcode-scan-fab'),oldSheet=document.getElementById('barcode-sheet');
  if(!oldFab||!oldSheet)return;
  var fab=oldFab.cloneNode(true),sheet=oldSheet.cloneNode(true);oldFab.replaceWith(fab);oldSheet.replaceWith(sheet);
  var closeBtn=sheet.querySelector('#barcode-close'),statusEl=sheet.querySelector('#barcode-status'),result=sheet.querySelector('#barcode-result'),nameEl=sheet.querySelector('#barcode-name'),priceEl=sheet.querySelector('#barcode-price'),stockEl=sheet.querySelector('#barcode-stock'),manual=sheet.querySelector('#barcode-manual-input'),manualGo=sheet.querySelector('#barcode-manual-go'),retry=sheet.querySelector('#barcode-retry'),camera=sheet.querySelector('.barcode-camera');
  if(!camera)return;
  camera.innerHTML='<div id="barcode-reader-v3" class="barcode-reader-v3"></div>';
  var reader=sheet.querySelector('#barcode-reader-v3');
  var tools=document.createElement('div');tools.className='barcode-v3-tools';
  tools.innerHTML='<button type="button" id="barcode-lens">🔄 تغییر لنز پشت</button><button type="button" id="barcode-focus">◎ فوکوس</button><button type="button" id="barcode-torch">🔦 نور</button><label id="barcode-zoom-wrap">زوم <input id="barcode-zoom" type="range" min="1" max="4" step="0.1" value="1"></label><button type="button" id="barcode-photo">📷 عکس بارکد</button><input id="barcode-photo-input" type="file" accept="image/*" capture="environment" hidden>';
  camera.insertAdjacentElement('afterend',tools);
  var lensBtn=tools.querySelector('#barcode-lens'),focusBtn=tools.querySelector('#barcode-focus'),torchBtn=tools.querySelector('#barcode-torch'),zoomWrap=tools.querySelector('#barcode-zoom-wrap'),zoomInput=tools.querySelector('#barcode-zoom'),photoBtn=tools.querySelector('#barcode-photo'),photoInput=tools.querySelector('#barcode-photo-input');
  var style=document.createElement('style');style.id='barcode-scanner-v6-style';style.textContent='\
.barcode-camera{aspect-ratio:16/10!important;min-height:285px!important;background:#07100b!important}.barcode-reader-v3{width:100%;height:100%;min-height:285px;overflow:hidden;border-radius:18px}.barcode-reader-v3 video{width:100%!important;height:100%!important;object-fit:cover!important}.barcode-reader-v3 img{max-width:100%!important;max-height:100%!important;object-fit:contain!important}.barcode-v3-tools{display:flex;align-items:center;gap:7px;margin-top:8px;overflow-x:auto;padding-bottom:2px}.barcode-v3-tools button,.barcode-v3-tools label{border:1px solid #cfe0d3;background:#fff;color:#17472e;border-radius:10px;padding:8px 10px;font:inherit;font-size:12px;font-weight:800;white-space:nowrap}.barcode-v3-tools label{display:flex;gap:6px;align-items:center;min-width:150px}.barcode-v3-tools input[type=range]{width:100px}.barcode-status.scan-good{background:#e9faee;color:#12642c}.barcode-status.scan-warn{background:#fff7df;color:#765c0b}#barcode-reader-v3__scan_region{min-height:285px!important;position:relative}#barcode-reader-v3__scan_region video{border-radius:18px}#barcode-reader-v3__dashboard{display:none!important}#barcode-reader-v3__scan_region:after{content:"بارکد را هر جای تصویر بگیر";position:absolute;left:50%;bottom:12px;transform:translateX(-50%);background:rgba(0,0,0,.55);color:#fff;border-radius:999px;padding:6px 11px;font-size:11px;white-space:nowrap;pointer-events:none}';document.head.appendChild(style);

  var scanner=null,active=false,devices=[],deviceIndex=0,track=null,torch=false,lastCode='',lastAt=0;
  function faNum(n){try{return new Intl.NumberFormat('fa-IR').format(n);}catch(e){return String(n)}}
  function speak(text){try{if(!('speechSynthesis' in window))return;window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang='fa-IR';u.rate=.93;window.speechSynthesis.speak(u);}catch(e){}}
  function setStatus(t,kind){statusEl.textContent=t;statusEl.classList.remove('scan-good','scan-warn');if(kind)statusEl.classList.add(kind);}
  async function lookup(code){
    code=String(code||'').replace(/\D/g,'');if(!code)return false;
    if(code===lastCode&&Date.now()-lastAt<1600)return false;lastCode=code;lastAt=Date.now();
    setStatus('بارکد '+code+' خوانده شد؛ در حال دریافت قیمت و موجودی…','scan-good');
    try{var r=await fetch('barcode_lookup.php?barcode='+encodeURIComponent(code),{cache:'no-store'}),d=await r.json();
      if(d&&d.ok&&d.found&&d.product){var p=d.product;nameEl.textContent=p.name;priceEl.textContent=faNum(p.price)+' تومان';stockEl.textContent=faNum(p.stock)+' عدد';result.classList.add('show');setStatus('محصول پیدا شد ✓','scan-good');speak(p.name+'، قیمت '+faNum(p.price)+' تومان، موجودی '+faNum(p.stock)+' عدد');await stopScanner();return true;}
      setStatus('بارکد خوانده شد ولی این کالا در فهرست فروشگاه ثبت نشده است.','scan-warn');speak('این بارکد در محصولات ثبت نشده است');return false;
    }catch(e){setStatus('بارکد خوانده شد ولی دریافت اطلاعات محصول خطا داشت.','scan-warn');return false;}
  }
  async function stopScanner(){active=false;track=null;torch=false;if(scanner){try{await scanner.stop();}catch(e){}try{scanner.clear();}catch(e){}}}
  function scoreCamera(d){var l=(d.label||'').toLowerCase(),s=0;if(/back|rear|environment|پشت/.test(l))s+=100;if(/front|user|selfie/.test(l))s-=1000;if(/macro|ultra|tele|depth/.test(l))s-=30;if(/main|primary|camera2 0|camera 0/.test(l))s+=25;return s;}
  async function getRearDevices(){
    try{
      var list=await Html5Qrcode.getCameras();list=list||[];
      var clearlyRear=list.filter(function(d){return /back|rear|environment|پشت/i.test(d.label||'')&&!/front|user|selfie/i.test(d.label||'');});
      var nonFront=list.filter(function(d){return !/front|user|selfie/i.test(d.label||'');});
      devices=(clearlyRear.length?clearlyRear:nonFront).sort(function(a,b){return scoreCamera(b)-scoreCamera(a);});
    }catch(e){devices=[];}
    lensBtn.hidden=devices.length<2;
  }
  function formats(){var F=window.Html5QrcodeSupportedFormats;if(!F)return undefined;return [F.EAN_13,F.EAN_8,F.UPC_A,F.UPC_E,F.CODE_128,F.ITF];}
  async function tuneTrack(){
    var v=reader.querySelector('video');track=v&&v.srcObject&&v.srcObject.getVideoTracks?v.srcObject.getVideoTracks()[0]:null;if(!track)return;
    var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}
    var adv=[];if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)adv.push({focusMode:'continuous'});if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)adv.push({exposureMode:'continuous'});if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)adv.push({whiteBalanceMode:'continuous'});
    try{await track.applyConstraints({width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}});}catch(e){}
    try{if(adv.length)await track.applyConstraints({advanced:adv});}catch(e){}
    if(caps.zoom&&typeof caps.zoom.min==='number'){zoomWrap.hidden=false;zoomInput.min=caps.zoom.min;zoomInput.max=caps.zoom.max;zoomInput.step=caps.zoom.step||.1;var z=Math.min(caps.zoom.max,Math.max(caps.zoom.min,1));zoomInput.value=z;try{await track.applyConstraints({advanced:[{zoom:z}]});}catch(e){}}else zoomWrap.hidden=true;
    torchBtn.hidden=!caps.torch;
    if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){try{await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});}catch(e){}setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},650);}
    if(caps.torch){
      var turnLightOn=async function(){try{await track.applyConstraints({advanced:[{torch:true}]});torch=true;torchBtn.textContent='🔦 خاموش';return true;}catch(e){return false;}};
      if(!(await turnLightOn()))setTimeout(function(){if(track&&!torch)turnLightOn();},500);
    }
    getRearDevices().catch(function(){});
  }
  async function startScanner(useRearIndex){
    await stopScanner();result.classList.remove('show');reader.innerHTML='';setStatus('دوربین پشت در حال آماده‌سازی است…');
    try{await loadLib();}catch(e){setStatus('موتور اسکن بارکد بارگذاری نشد. صفحه را یک بار تازه‌سازی کنید.','scan-warn');return;}
    var fs=formats();scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:fs,useBarCodeDetectorIfSupported:false,verbose:false});
    var cameraConfig;
    if(useRearIndex&&devices.length){cameraConfig={deviceId:{exact:devices[deviceIndex%devices.length].id}};}
    else cameraConfig={facingMode:{exact:'environment'}};
    var config={fps:24,disableFlip:true,aspectRatio:1.7777778};
    try{
      await scanner.start(cameraConfig,config,function(text){lookup(text);},function(){});active=true;setStatus('دوربین پشت فعال است؛ بارکد را جلوی دوربین بگیر.');setTimeout(tuneTrack,300);
    }catch(e){
      try{scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:fs,useBarCodeDetectorIfSupported:false,verbose:false});await scanner.start({facingMode:{ideal:'environment'}},{fps:20,disableFlip:true,aspectRatio:1.7777778},function(text){lookup(text);},function(){});active=true;setStatus('دوربین پشت فعال است؛ بارکد را جلوی دوربین بگیر.');setTimeout(tuneTrack,300);return;}catch(x){}
      setStatus('دوربین پشت باز نشد. از «عکس بارکد» استفاده کن.','scan-warn');
    }
  }
  async function refocus(){if(!track){setStatus('دوربین هنوز آماده نشده است.');return;}var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}try{if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},500);}else if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0){await track.applyConstraints({advanced:[{focusMode:'continuous'}]});}setStatus('فوکوس انجام شد.','scan-good');}catch(e){setStatus('فوکوس خودکار فعال است.');}}
  async function openSheet(){sheet.hidden=false;document.documentElement.style.overflow='hidden';deviceIndex=0;devices=[];await startScanner(false);}
  async function closeSheet(){await stopScanner();sheet.hidden=true;document.documentElement.style.overflow='';}
  fab.addEventListener('click',openSheet);closeBtn&&closeBtn.addEventListener('click',closeSheet);retry&&retry.addEventListener('click',function(){startScanner(false);});sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  manualGo&&manualGo.addEventListener('click',function(){lookup(manual.value);});manual&&manual.addEventListener('keydown',function(e){if(e.key==='Enter')lookup(manual.value);});
  lensBtn.addEventListener('click',async function(){await getRearDevices();if(!devices.length){setStatus('لنز پشت دیگری پیدا نشد.');return;}deviceIndex=(deviceIndex+1)%devices.length;setStatus('در حال تغییر لنز پشت…');await startScanner(true);});
  focusBtn.addEventListener('click',refocus);reader.addEventListener('pointerdown',function(){refocus();});
  torchBtn.addEventListener('click',async function(){if(!track)return;torch=!torch;try{await track.applyConstraints({advanced:[{torch:torch}]});torchBtn.textContent=torch?'🔦 خاموش':'🔦 نور';}catch(e){torch=false;}});
  zoomInput.addEventListener('input',function(){if(!track)return;track.applyConstraints({advanced:[{zoom:parseFloat(zoomInput.value)}]}).catch(function(){});});
  photoBtn.addEventListener('click',function(){photoInput.click();});photoInput.addEventListener('change',async function(){var f=photoInput.files&&photoInput.files[0];if(!f)return;try{await stopScanner();await loadLib();if(!scanner)scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:formats(),useBarCodeDetectorIfSupported:false,verbose:false});setStatus('در حال خواندن عکس بارکد…');var text=await scanner.scanFile(f,true);await lookup(text);}catch(e){setStatus('بارکد از این عکس خوانده نشد؛ یک عکس واضح‌تر بگیر.','scan-warn');}finally{photoInput.value='';}});
  document.addEventListener('visibilitychange',function(){if(document.hidden)stopScanner();});
  loadLib().catch(function(){});
});
})();
