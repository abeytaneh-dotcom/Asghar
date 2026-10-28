(()=>{
  'use strict';
  async function image(file){
    const bitmap=await createImageBitmap(file,{imageOrientation:'from-image'});
    try{const ratio=Math.min(1,1800/Math.max(bitmap.width,bitmap.height)),canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*ratio));canvas.height=Math.max(1,Math.round(bitmap.height*ratio));const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);
      let blob;for(const quality of [.84,.72,.60]){blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/webp',quality));if(!blob)throw Error('این عکس قابل خواندن نیست؛ نسخهٔ JPG آن را انتخاب کن.');if(blob.size<900000)break;}
      return new File([blob],blob.type==='image/webp'?'photo.webp':'photo.png',{type:blob.type});
    }finally{bitmap.close();}
  }
  const timed=(promise,ms)=>new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error('مهلت پردازش تمام شد.')),ms);promise.then(v=>{clearTimeout(timer);resolve(v);},e=>{clearTimeout(timer);reject(e);});});
  async function video(file,progress){
    if(!window.MediaRecorder||!HTMLCanvasElement.prototype.captureStream||!window.AudioContext)return file;
    const mime=['video/webm;codecs=vp8,opus','video/webm','video/mp4'].find(v=>MediaRecorder.isTypeSupported(v));if(!mime)return file;
    const el=document.createElement('video'),url=URL.createObjectURL(file);let stream,audio,recorder,timer;
    el.muted=true;el.playsInline=true;el.preload='auto';el.src=url;
    try{
      await timed(new Promise((resolve,reject)=>{el.onloadedmetadata=resolve;el.onerror=()=>reject(Error('ویدیو قابل خواندن نیست.'));}),20000);
      if(!Number.isFinite(el.duration)||el.duration<=0)return file;
      const ratio=Math.min(1,1280/Math.max(el.videoWidth,el.videoHeight)),canvas=document.createElement('canvas');canvas.width=Math.max(2,Math.floor(el.videoWidth*ratio/2)*2);canvas.height=Math.max(2,Math.floor(el.videoHeight*ratio/2)*2);const ctx=canvas.getContext('2d');
      stream=canvas.captureStream(24);audio=new AudioContext();await timed(audio.resume(),2000);const source=audio.createMediaElementSource(el),dest=audio.createMediaStreamDestination();source.connect(dest);dest.stream.getAudioTracks().forEach(t=>stream.addTrack(t));
      recorder=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:1400000,audioBitsPerSecond:96000});const parts=[];
      const done=new Promise((resolve,reject)=>{recorder.ondataavailable=e=>{if(e.data.size)parts.push(e.data);};recorder.onstop=()=>resolve(new Blob(parts,{type:recorder.mimeType}));recorder.onerror=()=>reject(Error('بهینه‌سازی ویدیو کامل نشد.'));});
      done.catch(()=>{});const ended=new Promise((resolve,reject)=>{el.onended=resolve;el.onerror=reject;});recorder.start(1000);await timed(el.play(),10000);
      timer=setInterval(()=>{ctx.drawImage(el,0,0,canvas.width,canvas.height);progress(Math.min(99,Math.round(el.currentTime/el.duration*100)));},1000/24);
      await timed(ended,el.duration*1500+30000);recorder.stop();const blob=await timed(done,5000);progress(100);if(!blob.size||blob.size>=file.size)return file;
      return new File([blob],blob.type.includes('mp4')?'video.mp4':'video.webm',{type:blob.type});
    }catch(_){return file;}finally{clearInterval(timer);el.pause();if(recorder?.state==='recording')recorder.stop();stream?.getTracks().forEach(t=>t.stop());await audio?.close().catch(()=>{});URL.revokeObjectURL(url);el.remove();}
  }
  async function api(data){const r=await fetch('/nz-ai-submit.php',{method:'POST',credentials:'same-origin',cache:'no-store',body:data});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'ارسال کامل نشد؛ دوباره تلاش کن.');return j;}
  async function upload(file,kind,csrf,progress){
    const form=new FormData();form.set('csrf',csrf);form.set('action','start');form.set('size',String(file.size));form.set('kind',kind);const {id}=await api(form);let offset=0;
    while(offset<file.size){const part=new FormData();part.set('csrf',csrf);part.set('action','chunk');part.set('id',id);part.set('offset',String(offset));part.set('chunk',file.slice(offset,offset+1024*1024),'part');let j;try{j=await api(part);}catch(e){const check=new FormData();check.set('csrf',csrf);check.set('action','status');check.set('id',id);j=await api(check);if(j.offset===offset)j=await api(part);}if(j.offset<=offset)throw Error('ارسال متوقف شد؛ دوباره تلاش کن.');offset=j.offset;progress(Math.round(offset/file.size*100));}
    return id;
  }
  window.nzAIFile={image,video,upload,api};
})();
