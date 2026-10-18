(()=>{
  const video=document.getElementById('nzVideo');
  const durationInput=document.getElementById('nzVideoDuration');
  const info=document.getElementById('nzVideoInfo');
  if(!video||!durationInput)return;

  let duration=0;
  let compressedReady=false;
  let compressing=false;
  const form=video.closest('form');

  const fmtBytes=n=>n<1048576?`${Math.max(1,Math.round(n/1024))} KB`:`${(n/1048576).toFixed(1)} MB`;
  const getDuration=file=>new Promise((resolve,reject)=>{
    const url=URL.createObjectURL(file);
    const v=document.createElement('video');
    v.preload='metadata';
    v.onloadedmetadata=()=>{const d=Number(v.duration||0);URL.revokeObjectURL(url);resolve(d)};
    v.onerror=()=>{URL.revokeObjectURL(url);reject(new Error('metadata'))};
    v.src=url;
  });

  const ensureProgress=()=>{
    let box=document.getElementById('nzClientCompressBox');
    if(box)return box;
    box=document.createElement('div');
    box.id='nzClientCompressBox';
    box.style.cssText='margin-top:10px;padding:10px 11px;border:1px solid #e5e7eb;border-radius:12px;background:#fafafa;font:11px Tahoma,Arial,sans-serif;color:#626970';
    box.innerHTML='<b style="display:block;color:#23262a;margin-bottom:7px">در حال بهینه‌سازی ویدیو…</b><div style="height:7px;border-radius:99px;background:#eceff2;overflow:hidden"><span id="nzClientProgress" style="display:block;height:100%;width:0;background:#b4232c;transition:width .25s"></span></div><div id="nzClientProgressText" style="margin-top:6px"></div>';
    video.parentElement?.appendChild(box);
    return box;
  };

  async function compressFile(file){
    if(!window.MediaRecorder)return null;
    const el=document.createElement('video');
    el.playsInline=true;
    el.muted=true;
    el.preload='auto';
    el.style.cssText='position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;pointer-events:none';
    document.body.appendChild(el);
    const url=URL.createObjectURL(file);
    el.src=url;
    await new Promise((resolve,reject)=>{el.onloadedmetadata=resolve;el.onerror=reject});

    const capture=el.captureStream||el.mozCaptureStream;
    if(!capture){URL.revokeObjectURL(url);el.remove();return null}
    const mime=['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm','video/mp4'].find(x=>MediaRecorder.isTypeSupported?.(x))||'';

    let stream;
    try{await el.play();stream=capture.call(el)}catch(_){URL.revokeObjectURL(url);el.remove();return null}
    const chunks=[];
    let recorder;
    try{
      recorder=new MediaRecorder(stream,{mimeType:mime||undefined,videoBitsPerSecond:1800000,audioBitsPerSecond:96000});
    }catch(_){el.pause();stream.getTracks().forEach(t=>t.stop());URL.revokeObjectURL(url);el.remove();return null}

    const box=ensureProgress();
    const bar=box.querySelector('#nzClientProgress');
    const text=box.querySelector('#nzClientProgressText');
    const done=new Promise((resolve,reject)=>{
      recorder.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data)};
      recorder.onerror=e=>reject(e.error||new Error('record'));
      recorder.onstop=()=>resolve(new Blob(chunks,{type:recorder.mimeType||mime||'video/webm'}));
    });

    recorder.start(1000);
    const tick=setInterval(()=>{
      const p=Math.min(99,Math.round((el.currentTime/Math.max(1,duration))*100));
      if(bar)bar.style.width=p+'%';
      if(text)text.textContent=`${p}٪ — فایل قبل از ارسال سبک می‌شود`;
    },350);

    await new Promise(resolve=>{el.onended=resolve});
    if(recorder.state!=='inactive')recorder.stop();
    const blob=await done;
    clearInterval(tick);
    if(bar)bar.style.width='100%';
    if(text)text.textContent='بهینه‌سازی تمام شد.';
    stream.getTracks().forEach(t=>t.stop());
    URL.revokeObjectURL(url);
    el.remove();
    const ext=(blob.type||'').includes('mp4')?'mp4':'webm';
    return new File([blob],`niazestan-video.${ext}`,{type:blob.type||'video/webm',lastModified:Date.now()});
  }

  video.addEventListener('change',async()=>{
    compressedReady=false;duration=0;
    const f=video.files?.[0];
    if(!f)return;
    if(f.size>120*1024*1024){alert('حجم اولیه ویدیو باید کمتر از ۱۲۰ مگابایت باشد.');video.value='';durationInput.value='0';return}
    try{duration=await getDuration(f)}catch(_){alert('این ویدیو قابل بررسی نیست.');video.value='';durationInput.value='0';return}
    if(!duration||duration>60.5){alert('ویدیو باید حداکثر ۱ دقیقه باشد.');video.value='';durationInput.value='0';return}
    durationInput.value=duration.toFixed(2);
    if(info)info.textContent=`${Math.ceil(duration)} ثانیه · ${fmtBytes(f.size)} · هنگام انتشار بهینه می‌شود.`;
  });

  if(form)form.addEventListener('submit',async e=>{
    const f=video.files?.[0];
    if(!f||compressedReady||compressing)return;
    if(f.size<=5*1024*1024){compressedReady=true;return}
    e.preventDefault();
    compressing=true;
    try{
      const out=await compressFile(f);
      if(out&&out.size<f.size&&typeof DataTransfer!=='undefined'){
        const dt=new DataTransfer();dt.items.add(out);video.files=dt.files;
        if(info)info.textContent=`بهینه شد: ${fmtBytes(f.size)} ← ${fmtBytes(out.size)}`;
      }else if(info){
        info.textContent='مرورگر فشرده‌سازی پیشرفته را پشتیبانی نکرد؛ نسخه اصلی ارسال می‌شود.';
      }
      compressedReady=true;compressing=false;form.requestSubmit();
    }catch(_){
      compressing=false;
      alert('بهینه‌سازی ویدیو انجام نشد. دوباره تلاش کنید یا ویدیوی سبک‌تری انتخاب کنید.');
    }
  });
})();
