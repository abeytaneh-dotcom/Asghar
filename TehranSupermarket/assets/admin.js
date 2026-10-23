(()=>{
const $=s=>document.querySelector(s), barcode=$('#barcode'), nameInput=$('#name'), scanBtn=$('#scan'), video=$('#video'), scanmsg=$('#scanmsg'), results=$('#imageResults'), status=$('#imageSearchStatus');
let reader=null;
const SCANNER_VERSION='camera-v6';
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
function setScan(t,type=''){scanmsg.textContent=t;scanmsg.className='scan-status '+type;}
function closeScanner(){try{if(reader)reader.reset();}catch(_e){}reader=null;if(video&&video.srcObject){try{video.srcObject.getTracks().forEach(t=>t.stop());}catch(_e){}video.srcObject=null;}if(video)video.style.display='none';if(scanBtn)scanBtn.textContent='اسکن زنده';}
function cameraErrorMessage(e){const n=e?.name||'';if(!window.isSecureContext)return 'دوربین زنده فقط روی HTTPS باز می‌شود؛ از دکمه «عکس بارکد» استفاده کنید.';if(!navigator.mediaDevices?.getUserMedia)return 'این مرورگر داخلی دوربین زنده را پشتیبانی نمی‌کند؛ دکمه «عکس بارکد» را بزنید.';if(n==='NotAllowedError'||n==='PermissionDeniedError')return 'مجوز دوربین زنده رد شده است؛ دکمه «عکس بارکد» را بزنید یا Camera را Allow کنید.';if(n==='NotFoundError'||n==='DevicesNotFoundError')return 'دوربین پیدا نشد؛ دکمه «عکس بارکد» را امتحان کنید.';if(n==='NotReadableError'||n==='TrackStartError')return 'دوربین درگیر برنامه دیگری است؛ دکمه «عکس بارکد» را امتحان کنید.';return 'دوربین زنده باز نشد؛ دکمه «عکس بارکد» را بزنید.';}
function fillProduct(p){$('#product_id').value=p.id||0;barcode.value=p.barcode||'';nameInput.value=p.name||'';$('#brand').value=p.brand||'';$('#quantity_label').value=p.quantity_label||'';$('#price').value=p.price??'';$('#stock').value=p.stock??1;$('#category').value=p.category||'سایر';if(p.image_path){$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${esc(p.image_path)}"><span>عکس فعلی محصول</span></div>`;}else $('#selectedPreview').innerHTML='';$('#saveProduct').textContent='ذخیره تغییرات محصول';setScan('این بارکد قبلاً ثبت شده؛ اطلاعات محصول برای ویرایش باز شد.','ok');nameInput.focus();}
async function lookup(){const b=barcode.value.trim();if(!b)return;setScan('در حال بررسی بارکد...');try{const r=await fetch(`api.php?action=barcode_lookup&barcode=${encodeURIComponent(b)}`,{cache:'no-store'}),d=await r.json();if(d.found)fillProduct(d.product);else{$('#product_id').value='0';setScan('بارکد جدید است. حالا نام محصول را بنویسید و عکس را انتخاب کنید.','ok');nameInput.focus();}}catch(e){setScan('بررسی بارکد انجام نشد؛ می‌توانید ادامه دهید.','warn');}}
$('#lookupBarcode')?.addEventListener('click',lookup);barcode?.addEventListener('change',lookup);

async function loadPhoto(file){
  try{return await createImageBitmap(file,{imageOrientation:'from-image'});}catch(_e){
    const u=URL.createObjectURL(file);try{const img=new Image();img.src=u;await img.decode();return img;}finally{setTimeout(()=>URL.revokeObjectURL(u),1000);}
  }
}
function makeCanvas(source,opts={}){
  const sw=source.width||source.naturalWidth, sh=source.height||source.naturalHeight;
  const crop=opts.crop??1, rot=opts.rot??0, gray=!!opts.gray;
  const cw=Math.max(1,Math.round(sw*crop)), ch=Math.max(1,Math.round(sh*crop));
  const sx=Math.max(0,Math.round((sw-cw)/2)), sy=Math.max(0,Math.round((sh-ch)/2));
  const limit=opts.limit||1800, scale=Math.min(1,limit/Math.max(cw,ch));
  const dw=Math.max(1,Math.round(cw*scale)), dh=Math.max(1,Math.round(ch*scale));
  const sideways=Math.abs(rot)%180===90;
  const canvas=document.createElement('canvas');canvas.width=sideways?dh:dw;canvas.height=sideways?dw:dh;
  const ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
  ctx.save();ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(rot*Math.PI/180);ctx.drawImage(source,sx,sy,cw,ch,-dw/2,-dh/2,dw,dh);ctx.restore();
  if(gray){const id=ctx.getImageData(0,0,canvas.width,canvas.height),d=id.data;for(let i=0;i<d.length;i+=4){const y=Math.round(.299*d[i]+.587*d[i+1]+.114*d[i+2]);const c=y<115?Math.max(0,y-35):Math.min(255,y+25);d[i]=d[i+1]=d[i+2]=c;}ctx.putImageData(id,0,0);}
  return canvas;
}
async function detectNative(surface){
  if(!('BarcodeDetector' in window))return '';
  try{const formats=['ean_13','ean_8','upc_a','upc_e','code_128','code_39','itf','codabar'];const detector=new BarcodeDetector({formats});const found=await detector.detect(surface);return found?.[0]?.rawValue||'';}catch(_e){return '';}
}
async function detectZXing(surface){
  if(!window.ZXing)return '';
  const r=new ZXing.BrowserMultiFormatReader();
  try{
    const url=surface.toDataURL('image/jpeg',.94);
    const result=await r.decodeFromImageUrl(url);
    return result?.getText?.()||'';
  }catch(_e){return '';}finally{try{r.reset();}catch(_e){}}
}
async function readBarcodeFromPhoto(file){
  if(!file)return;
  setScan('در حال تحلیل عکس بارکد…','ok');
  let source=null;
  try{
    source=await loadPhoto(file);
    const passes=[
      {crop:1,rot:0,gray:false,limit:2000,label:'تصویر اصلی'},
      {crop:.9,rot:0,gray:false,limit:1800,label:'برش نزدیک'},
      {crop:.72,rot:0,gray:false,limit:1800,label:'برش مرکزی'},
      {crop:1,rot:90,gray:false,limit:1800,label:'چرخش ۹۰ درجه'},
      {crop:1,rot:270,gray:false,limit:1800,label:'چرخش ۲۷۰ درجه'},
      {crop:.9,rot:0,gray:true,limit:1600,label:'کنتراست بالا'},
      {crop:.72,rot:90,gray:true,limit:1600,label:'برش و چرخش'},
      {crop:.72,rot:270,gray:true,limit:1600,label:'برش و چرخش معکوس'}
    ];
    for(let i=0;i<passes.length;i++){
      setScan(`در حال خواندن بارکد… مرحله ${i+1} از ${passes.length} (${passes[i].label})`,'ok');
      const c=makeCanvas(source,passes[i]);
      let code=await detectNative(c);
      if(!code)code=await detectZXing(c);
      c.width=1;c.height=1;
      if(code){barcode.value=String(code).trim();setScan('بارکد با موفقیت از روی عکس خوانده شد: '+barcode.value,'ok');await lookup();return;}
      await new Promise(r=>setTimeout(r,25));
    }
    setScan('عکس واضح است اما موتور بارکد عددی پیدا نکرد. لطفاً بارکد را طوری بگیرید که تمام خط‌ها و عدد زیر آن داخل عکس باشند؛ یا عدد زیر بارکد را دستی وارد کنید.','warn');
  }catch(e){console.error('photo_barcode_error',e);setScan('خواندن عکس با خطا روبه‌رو شد. یک‌بار دیگر عکس بارکد را بگیرید.','warn');}
  finally{try{source?.close?.();}catch(_e){}}
}
const barcodePhoto=$('#barcode_photo');barcodePhoto?.addEventListener('change',async e=>{const f=e.target.files?.[0];if(f)await readBarcodeFromPhoto(f);e.target.value='';});

scanBtn?.addEventListener('click',async()=>{if(reader){closeScanner();return;}if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){setScan(cameraErrorMessage({name:'Unsupported'}),'warn');barcodePhoto?.click();return;}if(!window.ZXing){setScan('اسکن زنده آماده نیست؛ دوربین گوشی برای عکس بارکد باز می‌شود.','warn');barcodePhoto?.click();return;}try{setScan('در حال درخواست مجوز دوربین...');const probe=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'}}});probe.getTracks().forEach(t=>t.stop());video.style.display='block';scanBtn.textContent='بستن دوربین';reader=new ZXing.BrowserMultiFormatReader();await reader.decodeFromConstraints({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1920},height:{ideal:1080}}},video,result=>{if(result){barcode.value=result.getText();setScan('بارکد خوانده شد: '+barcode.value,'ok');closeScanner();lookup();}});setScan('دوربین فعال است؛ بارکد را نزدیک و صاف داخل کادر بگیرید.','ok');}catch(e){console.error('camera_error',e);closeScanner();setScan(cameraErrorMessage(e),'warn');setTimeout(()=>barcodePhoto?.click(),150);}});
function chooseImage(it,el){document.querySelectorAll('.image-option').forEach(x=>x.classList.remove('selected'));el.classList.add('selected');$('#selected_image_url').value=it.image;$('#selected_image_source').value=it.source||'';$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${esc(it.image)}"><span>انتخاب شد${it.source?' · '+esc(it.source):''}</span></div>`;if(!nameInput.value.trim()&&it.name)nameInput.value=it.name;else if(!nameInput.value.trim()&&it.title)nameInput.value=it.title;if(!barcode.value.trim()&&it.barcode){barcode.value=it.barcode;lookup()}if(!$('#brand').value.trim()&&it.brand)$('#brand').value=it.brand;if(!$('#quantity_label').value.trim()&&it.quantity)$('#quantity_label').value=it.quantity;}
async function searchImages(){const q=nameInput.value.trim();if(q.length<2){status.textContent='حداقل دو حرف از نام محصول را بنویسید.';nameInput.focus();return}status.textContent='در حال پیدا کردن عکس‌های مرتبط...';results.innerHTML='<div class="gallery-loading">در حال جستجو…</div>';try{const r=await fetch(`api.php?action=image_search&q=${encodeURIComponent(q)}`,{cache:'no-store'}),d=await r.json();if(!d.ok)throw new Error(d.error||'خطا');results.innerHTML='';if(!d.items?.length){results.innerHTML='<div class="notice">عکسی پیدا نشد. نام کوتاه‌تر یا برند محصول را امتحان کنید، یا از دوربین خودتان عکس بگیرید.</div>';status.textContent='نتیجه‌ای پیدا نشد.';return}d.items.forEach(it=>{const b=document.createElement('button');b.type='button';b.className='image-option';b.innerHTML=`<img loading="lazy" referrerpolicy="no-referrer" src="${esc(it.image)}" alt="${esc(it.title||q)}"><span>${esc((it.title||q).slice(0,60))}</span><small>${esc(it.source||'')}</small>`;b.addEventListener('click',()=>chooseImage(it,b));results.appendChild(b)});status.textContent=`${d.items.length} عکس پیدا شد؛ روی عکس موردنظر بزنید.`;}catch(e){results.innerHTML='<div class="notice">جستجوی آنلاین عکس در دسترس نیست. می‌توانید عکس را از گوشی انتخاب کنید.</div>';status.textContent='جستجوی آنلاین انجام نشد.';}}
$('#searchImages')?.addEventListener('click',searchImages);nameInput?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();searchImages();}});
$('#image_file')?.addEventListener('change',e=>{const f=e.target.files?.[0];if(!f)return;const u=URL.createObjectURL(f);$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${u}"><span>عکس انتخاب‌شده از گوشی</span></div>`;$('#selected_image_url').value='';$('#selected_image_source').value='تصویر مدیر';});
$('#newProduct')?.addEventListener('click',()=>{$('#productForm').reset();$('#product_id').value='0';$('#selected_image_url').value='';$('#selected_image_source').value='';$('#selectedPreview').innerHTML='';results.innerHTML='';status.textContent='بعد از نوشتن نام، «نمایش عکس‌ها» را بزنید.';$('#saveProduct').textContent='ثبت محصول';setScan('فرم آماده محصول جدید است.');barcode.focus();});
if(scanmsg){scanmsg.innerHTML=`دو روش دارید: <b>اسکن زنده</b> یا <b>عکس بارکد</b> · ${SCANNER_VERSION}`;scanmsg.className='scan-status ok';}
window.addEventListener('pagehide',closeScanner);
})();