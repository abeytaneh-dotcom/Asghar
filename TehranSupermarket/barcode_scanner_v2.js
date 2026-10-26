(function(){
'use strict';
var ZXING_URL='https://unpkg.com/@zxing/browser@0.2.1';
function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn,{once:true});else fn();}
ready(function(){
  var oldFab=document.getElementById('barcode-scan-fab'),oldSheet=document.getElementById('barcode-sheet');
  if(!oldFab||!oldSheet)return;
  // Clone the scanner DOM so the first-generation listeners are fully detached.
  var fab=oldFab.cloneNode(true),sheet=oldSheet.cloneNode(true);oldFab.replaceWith(fab);oldSheet.replaceWith(sheet);
  var closeBtn=sheet.querySelector('#barcode-close'),video=sheet.querySelector('#barcode-video'),statusEl=sheet.querySelector('#barcode-status'),result=sheet.querySelector('#barcode-result'),nameEl=sheet.querySelector('#barcode-name'),priceEl=sheet.querySelector('#barcode-price'),stockEl=sheet.querySelector('#barcode-stock'),manual=sheet.querySelector('#barcode-manual-input'),manualGo=sheet.querySelector('#barcode-manual-go'),retry=sheet.querySelector('#barcode-retry'),camera=sheet.querySelector('.barcode-camera'),guide=sheet.querySelector('.barcode-guide');
  if(!video||!camera)return;
  video.setAttribute('playsinline','');video.setAttribute('webkit-playsinline','');video.muted=true;

  var tools=document.createElement('div');tools.className='barcode-camera-tools';
  tools.innerHTML='<button type="button" id="barcode-focus-btn">◎ فوکوس</button><button type="button" id="barcode-torch-btn" hidden>🔦 نور</button><label id="barcode-zoom-wrap" hidden>زوم <input id="barcode-zoom" type="range"></label>';
  camera.insertAdjacentElement('afterend',tools);
  var focusBtn=tools.querySelector('#barcode-focus-btn'),torchBtn=tools.querySelector('#barcode-torch-btn'),zoomWrap=tools.querySelector('#barcode-zoom-wrap'),zoomInput=tools.querySelector('#barcode-zoom');

  var extraStyle=document.createElement('style');extraStyle.id='barcode-scanner-v2-style';extraStyle.textContent='\
.barcode-camera{aspect-ratio:16/10!important;min-height:250px}.barcode-camera video{object-fit:cover!important;image-rendering:auto}.barcode-guide{left:5%!important;right:5%!important;top:37%!important;height:26%!important;border-width:3px!important;border-color:#64ff8d!important}.barcode-line{left:8%!important;right:8%!important;background:#64ff8d!important;box-shadow:0 0 13px #37e66c!important}.barcode-camera-tools{display:flex;align-items:center;gap:7px;margin-top:8px;overflow:auto}.barcode-camera-tools button,.barcode-camera-tools label{border:1px solid #cfe0d3;background:#fff;color:#17472e;border-radius:10px;padding:8px 10px;font:inherit;font-size:12px;font-weight:800;white-space:nowrap}.barcode-camera-tools label{display:flex;align-items:center;gap:7px;flex:1}.barcode-camera-tools input{min-width:100px;width:100%}.barcode-status.scan-good{background:#e9faee;color:#12642c}.barcode-status.scan-warn{background:#fff7df;color:#765c0b}';
  document.head.appendChild(extraStyle);

  var stream=null,track=null,detector=null,zxingReader=null,zxingLoading=null,scanning=false,timer=0,lastCode='',lastAt=0,torchOn=false,scanCount=0,lastZXing=0;
  var canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});
  var canvasWide=document.createElement('canvas'),ctxWide=canvasWide.getContext('2d',{willReadFrequently:true});

  function faNum(n){try{return new Intl.NumberFormat('fa-IR').format(n);}catch(e){return String(n)}}
  function speak(text){try{if(!('speechSynthesis' in window))return;window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang='fa-IR';u.rate=.93;window.speechSynthesis.speak(u);}catch(e){}}
  function setStatus(t,kind){statusEl.textContent=t;statusEl.classList.remove('scan-good','scan-warn');if(kind)statusEl.classList.add(kind);}
  function stopCamera(){scanning=false;if(timer)clearTimeout(timer);timer=0;if(stream){stream.getTracks().forEach(function(t){try{t.stop();}catch(e){}});}stream=null;track=null;video.srcObject=null;detector=null;torchOn=false;}
  async function lookup(code){
    code=String(code||'').replace(/\D/g,'');if(!code)return false;
    if(code===lastCode&&Date.now()-lastAt<2000)return false;lastCode=code;lastAt=Date.now();
    setStatus('بارکد '+code+' خوانده شد؛ در حال دریافت قیمت و موجودی…','scan-good');
    try{
      var r=await fetch('barcode_lookup.php?barcode='+encodeURIComponent(code),{cache:'no-store'}),d=await r.json();
      if(d&&d.ok&&d.found&&d.product){var p=d.product;nameEl.textContent=p.name;priceEl.textContent=faNum(p.price)+' تومان';stockEl.textContent=faNum(p.stock)+' عدد';result.classList.add('show');setStatus('محصول پیدا شد ✓','scan-good');speak(p.name+'، قیمت '+faNum(p.price)+' تومان، موجودی '+faNum(p.stock)+' عدد');stopCamera();return true;}
      setStatus('بارکد خوانده شد ولی این کالا در فهرست فروشگاه ثبت نشده است.','scan-warn');speak('این بارکد در محصولات ثبت نشده است');return false;
    }catch(e){setStatus('بارکد خوانده شد ولی دریافت اطلاعات محصول خطا داشت.','scan-warn');return false;}
  }
  function loadZXing(){
    if(window.ZXingBrowser)return Promise.resolve(window.ZXingBrowser);
    if(zxingLoading)return zxingLoading;
    zxingLoading=new Promise(function(resolve,reject){var s=document.createElement('script');s.src=ZXING_URL;s.async=true;s.onload=function(){resolve(window.ZXingBrowser||null)};s.onerror=reject;document.head.appendChild(s);});
    return zxingLoading;
  }
  function paintCrops(){
    if(!video.videoWidth||!video.videoHeight)return false;
    var vw=video.videoWidth,vh=video.videoHeight;
    // Tight crop: enlarge the center barcode region 2x-3x for distant codes.
    var sw=Math.round(vw*.92),sh=Math.round(vh*.38),sx=Math.round((vw-sw)/2),sy=Math.round(vh*.31);
    canvas.width=Math.min(1800,Math.max(1100,sw*1.45));canvas.height=Math.min(760,Math.max(440,sh*1.45));ctx.drawImage(video,sx,sy,sw,sh,0,0,canvas.width,canvas.height);
    // Wider crop catches tilted or imperfectly centered barcodes.
    var wsw=Math.round(vw*.98),wsh=Math.round(vh*.68),wsx=Math.round((vw-wsw)/2),wsy=Math.round(vh*.16);
    canvasWide.width=Math.min(1600,Math.max(960,wsw));canvasWide.height=Math.min(1000,Math.max(560,wsh));ctxWide.drawImage(video,wsx,wsy,wsw,wsh,0,0,canvasWide.width,canvasWide.height);
    return true;
  }
  async function nativeDetect(source){if(!detector)return '';try{var arr=await detector.detect(source);return arr&&arr[0]&&arr[0].rawValue?String(arr[0].rawValue):'';}catch(e){return '';}}
  async function zxingDetect(source){
    if(!window.ZXingBrowser)return '';
    try{if(!zxingReader)zxingReader=new window.ZXingBrowser.BrowserMultiFormatReader();var r=await zxingReader.decodeFromCanvas(source);return r&&r.getText?r.getText():String(r&&r.text||'');}catch(e){return '';}
  }
  async function scanTick(){
    if(!scanning)return;
    try{
      if(video.readyState>=2&&paintCrops()){
        var code='';
        if(detector){code=await nativeDetect(canvas);if(!code&&scanCount%3===0)code=await nativeDetect(canvasWide);}
        if(!code&&Date.now()-lastZXing>500){lastZXing=Date.now();if(window.ZXingBrowser)code=await zxingDetect(canvas);}
        if(code){await lookup(code);if(!scanning)return;}
        scanCount++;
        if(scanCount===16)setStatus('اگر هنوز نخوانده: بارکد را حدود ۲۰ تا ۴۰ سانتی‌متر نگه دارید، گوشی را ثابت کنید و در صورت نیاز «فوکوس» یا «نور» را بزنید.','scan-warn');
      }
    }catch(e){}
    if(scanning)timer=setTimeout(scanTick,90);
  }
  async function applyBestCameraSettings(){
    if(!track)return;
    var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}
    var advanced=[];
    if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)advanced.push({focusMode:'continuous'});
    if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)advanced.push({exposureMode:'continuous'});
    if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)advanced.push({whiteBalanceMode:'continuous'});
    try{if(advanced.length)await track.applyConstraints({advanced:advanced});}catch(e){}
    if(caps.zoom&&typeof caps.zoom.min==='number'){
      zoomWrap.hidden=false;zoomInput.min=String(caps.zoom.min);zoomInput.max=String(caps.zoom.max);zoomInput.step=String(caps.zoom.step||.1);
      var target=Math.min(caps.zoom.max,Math.max(caps.zoom.min,caps.zoom.min+(caps.zoom.max-caps.zoom.min)*.12));if(target>2)target=2;zoomInput.value=String(target);
      try{await track.applyConstraints({advanced:[{zoom:target}]});}catch(e){}
    }else zoomWrap.hidden=true;
    if(caps.torch){torchBtn.hidden=false;}else torchBtn.hidden=true;
  }
  async function refocus(){
    if(!track)return;var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}
    try{
      if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},700);}
      else if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)await track.applyConstraints({advanced:[{focusMode:'continuous'}]});
      setStatus('فوکوس مجدد انجام شد؛ بارکد را در کادر سبز نگه دارید.','scan-good');
    }catch(e){setStatus('فوکوس خودکار دوربین فعال است؛ گوشی را یک لحظه ثابت نگه دارید.');}
  }
  async function startCamera(){
    stopCamera();result.classList.remove('show');setStatus('بارکد را با فاصله حدود ۲۰ تا ۴۰ سانتی‌متر داخل کادر سبز نگه دارید. لازم نیست خیلی نزدیک شوید.');
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){setStatus('دوربین روی این مرورگر در دسترس نیست؛ بارکد را دستی وارد کنید.','scan-warn');return;}
    try{
      var constraints={video:{facingMode:{ideal:'environment'},width:{ideal:1920,min:1280},height:{ideal:1080,min:720},frameRate:{ideal:30,min:15}},audio:false};
      try{stream=await navigator.mediaDevices.getUserMedia(constraints);}catch(e){stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});}
      track=stream.getVideoTracks()[0]||null;video.srcObject=stream;await video.play();await applyBestCameraSettings();
      if('BarcodeDetector' in window){var formats=['ean_13','ean_8','upc_a','upc_e','code_128','code_39','itf'];try{var sup=await BarcodeDetector.getSupportedFormats();formats=formats.filter(function(f){return sup.indexOf(f)>=0;});}catch(e){}try{detector=new BarcodeDetector({formats:formats.length?formats:undefined});}catch(e){detector=null;}}
      // Load ZXing in parallel; it becomes a second decoder if native detection misses.
      loadZXing().catch(function(){});scanning=true;scanCount=0;lastZXing=0;scanTick();
    }catch(e){setStatus('دوربین باز نشد یا اجازه آن داده نشده؛ بارکد را دستی وارد کنید.','scan-warn');}
  }
  function openSheet(){sheet.hidden=false;document.documentElement.style.overflow='hidden';startCamera();}
  function closeSheet(){stopCamera();sheet.hidden=true;document.documentElement.style.overflow='';}
  fab.addEventListener('click',openSheet);closeBtn&&closeBtn.addEventListener('click',closeSheet);retry&&retry.addEventListener('click',startCamera);sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  manualGo&&manualGo.addEventListener('click',function(){lookup(manual.value);});manual&&manual.addEventListener('keydown',function(e){if(e.key==='Enter')lookup(manual.value);});
  focusBtn.addEventListener('click',refocus);
  torchBtn.addEventListener('click',async function(){if(!track)return;torchOn=!torchOn;try{await track.applyConstraints({advanced:[{torch:torchOn}]});torchBtn.textContent=torchOn?'🔦 خاموش':'🔦 نور';}catch(e){torchOn=false;}});
  zoomInput.addEventListener('input',function(){if(!track)return;var z=parseFloat(zoomInput.value);track.applyConstraints({advanced:[{zoom:z}]}).catch(function(){});});
  document.addEventListener('visibilitychange',function(){if(document.hidden)stopCamera();});
});
})();
