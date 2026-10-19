(()=>{
'use strict';
const d=document;
const loadScript=(src,id)=>new Promise((resolve,reject)=>{
  if(id&&d.getElementById(id)){resolve();return;}
  const s=d.createElement('script'); if(id)s.id=id; s.src=src; s.async=true;
  s.onload=()=>resolve(); s.onerror=()=>reject(new Error('load failed'));
  d.head.appendChild(s);
});
const timeout=(p,ms)=>Promise.race([p,new Promise((_,r)=>setTimeout(()=>r(new Error('timeout')),ms))]);
let nsfwModelPromise=null, blazePromise=null, ocrReady=null;
async function imageFromFile(file){
  const url=URL.createObjectURL(file); const img=new Image(); img.decoding='async';
  await new Promise((res,rej)=>{img.onload=res;img.onerror=rej;img.src=url});
  const max=640, ratio=Math.min(1,max/Math.max(img.naturalWidth||1,img.naturalHeight||1));
  const c=d.createElement('canvas'); c.width=Math.max(1,Math.round(img.naturalWidth*ratio)); c.height=Math.max(1,Math.round(img.naturalHeight*ratio));
  c.getContext('2d',{willReadFrequently:true}).drawImage(img,0,0,c.width,c.height); URL.revokeObjectURL(url); return {img,canvas:c};
}
async function getNSFW(){
  if(nsfwModelPromise)return nsfwModelPromise;
  nsfwModelPromise=(async()=>{
    if(!window.tf) await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js','nz-tfjs');
    if(!window.nsfwjs) await loadScript('https://cdn.jsdelivr.net/npm/nsfwjs@2.4.2/dist/nsfwjs.min.js','nz-nsfwjs');
    return window.nsfwjs.load();
  })();
  return nsfwModelPromise;
}
async function getBlaze(){
  if(blazePromise)return blazePromise;
  blazePromise=(async()=>{
    if(!window.tf) await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js','nz-tfjs');
    if(!window.blazeface) await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface@0.1.0/dist/blazeface.js','nz-blazeface');
    return window.blazeface.load();
  })();
  return blazePromise;
}
async function detectFace(canvas){
  try{
    if('FaceDetector' in window){
      const f=new FaceDetector({fastMode:true,maxDetectedFaces:4});
      const faces=await timeout(f.detect(canvas),3500); return faces.length>0;
    }
  }catch(e){}
  try{
    const model=await timeout(getBlaze(),9000); const faces=await timeout(model.estimateFaces(canvas,false),5000); return Array.isArray(faces)&&faces.length>0;
  }catch(e){return false;}
}
async function detectNSFW(canvas){
  const out={porn:0,sexy:0,hentai:0};
  try{
    const model=await timeout(getNSFW(),12000); const rows=await timeout(model.classify(canvas,5),6000);
    for(const r of rows||[]){const k=String(r.className||'').toLowerCase(); const p=Number(r.probability||0); if(k==='porn')out.porn=p; if(k==='sexy')out.sexy=p; if(k==='hentai')out.hentai=p;}
  }catch(e){}
  return out;
}
async function runOCR(canvas){
  try{
    if(!window.Tesseract){
      if(!ocrReady)ocrReady=loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js','nz-tesseract');
      await timeout(ocrReady,10000);
    }
    const res=await timeout(window.Tesseract.recognize(canvas,'fas+eng',{logger:()=>{}}),14000);
    return String(res?.data?.text||'').replace(/\s+/g,' ').trim().slice(0,1200);
  }catch(e){return '';}
}
async function scanOne(file){
  const base={name:String(file.name||'').slice(0,100),face:false,porn:0,sexy:0,hentai:0,ocr:''};
  try{
    const {canvas}=await imageFromFile(file);
    const results=await Promise.allSettled([detectFace(canvas),detectNSFW(canvas),runOCR(canvas)]);
    if(results[0].status==='fulfilled')base.face=!!results[0].value;
    if(results[1].status==='fulfilled')Object.assign(base,results[1].value||{});
    if(results[2].status==='fulfilled')base.ocr=results[2].value||'';
  }catch(e){}
  return base;
}
function install(){
  const form=d.querySelector('form[enctype="multipart/form-data"]'); const input=d.getElementById('nzImages')||d.querySelector('input[name="images[]"]');
  if(!form||!input||form.dataset.nzModeration==='1')return; form.dataset.nzModeration='1';
  const hidden=d.createElement('input'); hidden.type='hidden'; hidden.name='moderation_client'; hidden.value=''; form.appendChild(hidden);
  const note=d.createElement('div'); note.className='subtle'; note.style.cssText='margin-top:6px;line-height:1.8'; note.textContent='تصاویر پیش از ارسال از نظر متن داخل عکس و محتوای مشکوک بررسی اولیه می‌شوند.'; input.closest('.field')?.appendChild(note);
  let scanPromise=Promise.resolve([]);
  const start=()=>{
    const files=Array.from(input.files||[]).slice(0,5); if(!files.length){hidden.value='';return;}
    note.textContent='در حال بررسی اولیه تصاویر…';
    scanPromise=(async()=>{const rows=[];for(const f of files)rows.push(await scanOne(f));hidden.value=JSON.stringify({version:1,images:rows});note.textContent='بررسی اولیه تصاویر انجام شد؛ انتشار نهایی همچنان منوط به تأیید مدیر است.';return rows;})().catch(()=>{note.textContent='بررسی خودکار تصویر کامل نشد؛ آگهی برای بررسی دستی مدیر ارسال می‌شود.';return[]});
  };
  input.addEventListener('change',start);
  form.addEventListener('submit',async e=>{
    if(form.dataset.nzModerationSubmitting==='1')return;
    if((input.files||[]).length){e.preventDefault();form.dataset.nzModerationSubmitting='1';note.textContent='در حال تکمیل بررسی امنیتی تصاویر…';try{await timeout(scanPromise,18000);}catch(_){} form.submit();}
  });
}
if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',install);else install();
new MutationObserver(()=>install()).observe(d.documentElement,{childList:true,subtree:true});
})();
