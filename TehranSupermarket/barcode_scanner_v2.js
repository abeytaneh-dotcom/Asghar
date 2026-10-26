(function(){
'use strict';
var LIB='assets/html5-qrcode.min.js?v=2.3.8-1';
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
  tools.innerHTML='<button type="button" id="barcode-lens">🔄 تغییر لنز</button><button type="button" id="barcode-focus">◎ فوکوس</button><button type="button" id="barcode-torch">🔦 نور</button><label id="barcode-zoom-wrap">زوم <input id="barcode-zoom" type="range" min="1" max="4" step="0.1" value="1"></label><button type="button" id="barcode-photo">📷 عکس بارکد</button><input id="barcode-photo-input" type="file" accept="image/*" capture="environment" hidden>';
  camera.insertAdjacentElement('afterend',tools);
  var lensBtn=tools.querySelector('#barcode-lens'),focusBtn=tools.querySelector('#barcode-focus'),torchBtn=tools.querySelector('#barcode-torch'),zoomWrap=tools.querySelector('#barcode-zoom-wrap'),zoomInput=tools.querySelector('#barcode-zoom'),photoBtn=tools.querySelector('#barcode-photo'),photoInput=tools.querySelector('#barcode-photo-input');
  var style=document.createElement('style');style.id='barcode-scanner-v3-style';style.textContent='\
.barcode-camera{aspect-ratio:16/10!important;min-height:270px!important;background:#07100b!important}.barcode-reader-v3{width:100%;height:100%;min-height:270px;overflow:hidden;border-radius:18px}.barcode-reader-v3 video{width:100%!important;height:100%!important;object-fit:cover!important}.barcode-reader-v3 img{max-width:100%!important;max-height:100%!important;object-fit:contain!important}.barcode-v3-tools{display:flex;align-items:center;gap:7px;margin-top:8px;overflow-x:auto;padding-bottom:2px}.barcode-v3-tools button,.barcode-v3-tools label{border:1px solid #cfe0d3;background:#fff;color:#17472e;border-radius:10px;padding:8px 10px;font:inherit;font-size:12px;font-weight:800;white-space:nowrap}.barcode-v3-tools label{display:flex;gap:6px;align-items:center;min-width:150px}.barcode-v3-tools input[type=range]{width:100px}.barcode-status.scan-good{background:#e9faee;color:#12642c}.barcode-status.scan-warn{background:#fff7df;color:#765c0b}#barcode-reader-v3__scan_region{min-height:270px!important}#barcode-reader-v3__scan_region video{border-radius:18px}#barcode-reader-v3__dashboard{display:none!important}';document.head.appendChild(style);

  var scanner=null,active=false,devices=[],deviceIndex=0,track=null,torch=false,lastCode='',lastAt=0;
  function faNum(n){try{return new Intl.NumberFormat('fa-IR').format(n);}catch(e){return String(n)}}
  function speak(text){try{if(!('speechSynthesis' in window))return;window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang='fa-IR';u.rate=.93;window.speechSynthesis.speak(u);}catch(e){}}
  function setStatus(t,kind){statusEl.textContent=t;statusEl.classList.remove('scan-good','scan-warn');if(kind)statusEl.classList.add(kind);}
  async function lookup(code){
    code=String(code||'').replace(/\D/g,'');if(!code)return false;
    if(code===lastCode&&Date.now()-lastAt<1800)return false;lastCode=code;lastAt=Date.now();
    setStatus('بارکد '+code+' خوانده شد؛ در حال دریافت قیمت و موجودی…','scan-good');
    try{var r=await fetch('barcode_lookup.php?barcode='+encodeURIComponent(code),{cache:'no-store'}),d=await r.json();
      if(d&&d.ok&&d.found&&d.product){var p=d.product;nameEl.textContent=p.name;priceEl.textContent=faNum(p.price)+' تومان';stockEl.textContent=faNum(p.stock)+' عدد';result.classList.add('show');setStatus('محصول پیدا شد ✓','scan-good');speak(p.name+'، قیمت '+faNum(p.price)+' تومان، موجودی '+faNum(p.stock)+' عدد');await stopScanner();return true;}
      setStatus('بارکد خوانده شد ولی این کالا در فهرست فروشگاه ثبت نشده است.','scan-warn');speak('این بارکد در محصولات ثبت نشده است');return false;
    }catch(e){setStatus('بارکد خوانده شد ولی دریافت اطلاعات محصول خطا داشت.','scan-warn');return false;}
  }
  async function stopScanner(){
    active=false;track=null;torch=false;
    if(scanner){try{await scanner.stop();}catch(e){}try{scanner.clear();}catch(e){}}
  }
  function scoreCamera(d){var l=(d.label||'').toLowerCase(),s=0;if(/back|rear|environment|پشت/.test(l))s+=50;if(/front|user|selfie/.test(l))s-=100;if(/macro|ultra|tele|depth/.test(l))s-=25;if(/main|primary|camera2 0|camera 0/.test(l))s+=18;return s;}
  async function getDevices(){
    try{var list=await Html5Qrcode.getCameras();devices=(list||[]).filter(function(d){return !/front|user|selfie/i.test(d.label||'');});if(!devices.length)devices=list||[];devices.sort(function(a,b){return scoreCamera(b)-scoreCamera(a);});}catch(e){devices=[];}
    lensBtn.hidden=devices.length<2;
  }
  function formats(){var F=window.Html5QrcodeSupportedFormats;if(!F)return undefined;return [F.EAN_13,F.EAN_8,F.UPC_A,F.UPC_E,F.UPC_EAN_EXTENSION,F.CODE_128,F.CODE_39,F.ITF,F.CODE_93,F.CODABAR];}
  async function tuneTrack(){
    var v=reader.querySelector('video');track=v&&v.srcObject&&v.srcObject.getVideoTracks?v.srcObject.getVideoTracks()[0]:null;if(!track)return;
    var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}
    var adv=[];if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0)adv.push({focusMode:'continuous'});if(caps.exposureMode&&caps.exposureMode.indexOf('continuous')>=0)adv.push({exposureMode:'continuous'});if(caps.whiteBalanceMode&&caps.whiteBalanceMode.indexOf('continuous')>=0)adv.push({whiteBalanceMode:'continuous'});
    try{if(adv.length)await track.applyConstraints({advanced:adv});}catch(e){}
    if(caps.zoom&&typeof caps.zoom.min==='number'){zoomWrap.hidden=false;zoomInput.min=caps.zoom.min;zoomInput.max=caps.zoom.max;zoomInput.step=caps.zoom.step||.1;var z=Math.min(caps.zoom.max,Math.max(caps.zoom.min,1.4));zoomInput.value=z;try{await track.applyConstraints({advanced:[{zoom:z}]});}catch(e){}}else zoomWrap.hidden=true;
    torchBtn.hidden=!caps.torch;
  }
  async function startScanner(useIndex){
    await stopScanner();result.classList.remove('show');reader.innerHTML='';setStatus('دوربین در حال آماده‌سازی است…');
    try{await loadLib();}catch(e){setStatus('موتور اسکن بارکد بارگذاری نشد. صفحه را یک بار تازه‌سازی کنید.','scan-warn');return;}
    if(!devices.length)await getDevices();
    var fs=formats();scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:fs,useBarCodeDetectorIfSupported:true,verbose:false});
    var videoConstraints={width:{ideal:1920,min:1280},height:{ideal:1080,min:720},frameRate:{ideal:30,min:15}};
    if(devices.length&&useIndex!==false){var d=devices[deviceIndex%devices.length];videoConstraints.deviceId={exact:d.id};}else videoConstraints.facingMode={ideal:'environment'};
    var config={fps:18,qrbox:function(w,h){return {width:Math.max(260,Math.floor(w*.92)),height:Math.max(120,Math.min(190,Math.floor(h*.34))) };},disableFlip:true,videoConstraints:videoConstraints};
    try{
      await scanner.start({},config,function(text){lookup(text);},function(){});active=true;setStatus('بارکد را از فاصله حدود ۱۵ تا ۳۵ سانتی‌متر داخل کادر قرار بده. لازم نیست کل محصول داخل تصویر باشد.');setTimeout(tuneTrack,350);
    }catch(e){
      if(devices.length&&useIndex!==false){try{scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:fs,useBarCodeDetectorIfSupported:true,verbose:false});await scanner.start({facingMode:'environment'},{fps:15,qrbox:{width:360,height:150},aspectRatio:1.777},function(text){lookup(text);},function(){});active=true;setStatus('اسکنر فعال شد؛ بارکد را صاف و ثابت داخل کادر نگه دار.');setTimeout(tuneTrack,350);return;}catch(x){}}
      setStatus('دوربین باز شد ولی موتور اسکن شروع نشد. از «عکس بارکد» استفاده کن.','scan-warn');
    }
  }
  async function refocus(){if(!track){setStatus('دوربین هنوز آماده نشده است.');return;}var caps={};try{caps=track.getCapabilities?track.getCapabilities():{};}catch(e){}try{if(caps.focusMode&&caps.focusMode.indexOf('single-shot')>=0){await track.applyConstraints({advanced:[{focusMode:'single-shot'}]});setTimeout(function(){if(track&&caps.focusMode.indexOf('continuous')>=0)track.applyConstraints({advanced:[{focusMode:'continuous'}]}).catch(function(){});},600);}else if(caps.focusMode&&caps.focusMode.indexOf('continuous')>=0){await track.applyConstraints({advanced:[{focusMode:'continuous'}]});}setStatus('فوکوس انجام شد؛ گوشی را یک لحظه ثابت نگه دار.','scan-good');}catch(e){setStatus('فوکوس خودکار دوربین فعال است.');}}
  async function openSheet(){sheet.hidden=false;document.documentElement.style.overflow='hidden';await startScanner(true);}
  async function closeSheet(){await stopScanner();sheet.hidden=true;document.documentElement.style.overflow='';}
  fab.addEventListener('click',openSheet);closeBtn&&closeBtn.addEventListener('click',closeSheet);retry&&retry.addEventListener('click',function(){startScanner(true);});sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  manualGo&&manualGo.addEventListener('click',function(){lookup(manual.value);});manual&&manual.addEventListener('keydown',function(e){if(e.key==='Enter')lookup(manual.value);});
  lensBtn.addEventListener('click',async function(){if(devices.length<2)return;deviceIndex=(deviceIndex+1)%devices.length;setStatus('در حال تغییر لنز…');await startScanner(true);});
  focusBtn.addEventListener('click',refocus);reader.addEventListener('pointerdown',function(){refocus();});
  torchBtn.addEventListener('click',async function(){if(!track)return;torch=!torch;try{await track.applyConstraints({advanced:[{torch:torch}]});torchBtn.textContent=torch?'🔦 خاموش':'🔦 نور';}catch(e){torch=false;}});
  zoomInput.addEventListener('input',function(){if(!track)return;track.applyConstraints({advanced:[{zoom:parseFloat(zoomInput.value)}]}).catch(function(){});});
  photoBtn.addEventListener('click',function(){photoInput.click();});photoInput.addEventListener('change',async function(){var f=photoInput.files&&photoInput.files[0];if(!f)return;try{await stopScanner();await loadLib();if(!scanner)scanner=new Html5Qrcode('barcode-reader-v3',{formatsToSupport:formats(),useBarCodeDetectorIfSupported:true,verbose:false});setStatus('در حال خواندن عکس بارکد…');var text=await scanner.scanFile(f,true);await lookup(text);}catch(e){setStatus('بارکد از این عکس خوانده نشد؛ یک عکس واضح‌تر بگیر.','scan-warn');}finally{photoInput.value='';}});
  document.addEventListener('visibilitychange',function(){if(document.hidden)stopScanner();});
  loadLib().catch(function(){});
});
})();