(()=>{
const $=s=>document.querySelector(s), barcode=$('#barcode'), nameInput=$('#name'), scanBtn=$('#scan'), video=$('#video'), scanmsg=$('#scanmsg'), results=$('#imageResults'), status=$('#imageSearchStatus');
const productForm=$('#productForm'), imageFile=$('#image_file'), selectedPreview=$('#selectedPreview');
let reader=null, productImageUploading=false, productImageUploadFailed=false;
const SCANNER_VERSION='camera-v6';
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const humanSize=n=>{n=Number(n)||0;if(n<1024)return n+' B';if(n<1024*1024)return (n/1024).toFixed(1)+' KB';return (n/1024/1024).toFixed(1)+' MB';};
function setScan(t,type=''){scanmsg.textContent=t;scanmsg.className='scan-status '+type;}
function closeScanner(){try{if(reader)reader.reset();}catch(_e){}reader=null;if(video&&video.srcObject){try{video.srcObject.getTracks().forEach(t=>t.stop());}catch(_e){}video.srcObject=null;}if(video)video.style.display='none';if(scanBtn)scanBtn.textContent='اسکن زنده';}
function cameraErrorMessage(e){const n=e?.name||'';const c='کد خطا: BAR-801 — ';if(!window.isSecureContext)return c+'اسکن زنده در این صفحه در دسترس نیست. راه‌حل: از «عکس بارکد» استفاده کنید.';if(!navigator.mediaDevices?.getUserMedia)return c+'این مرورگر اسکن زنده را پشتیبانی نمی‌کند. راه‌حل: «عکس بارکد» را بزنید.';if(n==='NotAllowedError'||n==='PermissionDeniedError')return c+'مجوز دوربین داده نشده است. راه‌حل: Camera را Allow کنید یا «عکس بارکد» را بزنید.';if(n==='NotFoundError'||n==='DevicesNotFoundError')return c+'دوربین در دسترس نیست. راه‌حل: از «عکس بارکد» یا ورود دستی استفاده کنید.';if(n==='NotReadableError'||n==='TrackStartError')return c+'دوربین فعلاً قابل استفاده نیست. راه‌حل: برنامه دیگری که از دوربین استفاده می‌کند ببندید یا «عکس بارکد» را بزنید.';return c+'اسکن زنده باز نشد. راه‌حل: از «عکس بارکد» یا ورود دستی استفاده کنید.';}
function fillProduct(p){$('#product_id').value=p.id||0;barcode.value=p.barcode||'';nameInput.value=p.name||'';$('#brand').value=p.brand||'';$('#quantity_label').value=p.quantity_label||'';$('#price').value=p.price??'';$('#stock').value=p.stock??1;$('#category').value=p.category||'سایر';if(p.image_path){selectedPreview.innerHTML=`<div class="chosen"><img src="${esc(p.image_path)}"><span>عکس فعلی محصول</span></div>`;}else selectedPreview.innerHTML='';$('#saveProduct').textContent='ذخیره تغییرات محصول';setScan('این بارکد قبلاً ثبت شده؛ اطلاعات محصول برای ویرایش باز شد.','ok');nameInput.focus();}
async function lookup(){const b=barcode.value.trim();if(!b)return;setScan('در حال بررسی بارکد...');try{const r=await fetch(`api.php?action=barcode_lookup&barcode=${encodeURIComponent(b)}`,{cache:'no-store'}),d=await r.json();if(d.found)fillProduct(d.product);else{$('#product_id').value='0';setScan('بارکد جدید است. حالا نام محصول را بنویسید و عکس را انتخاب کنید.','ok');nameInput.focus();}}catch(_e){setScan('کد خطا: BAR-803 — بررسی بارکد انجام نشد. راه‌حل: می‌توانید اطلاعات کالا را دستی ادامه دهید یا دوباره بررسی کنید.','warn');}}
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
function canvasBlob(canvas,type='image/webp',quality=.84){return new Promise(resolve=>canvas.toBlob(resolve,type,quality));}
async function normalizeProductPhoto(file){
  let source=null;
  try{
    source=await loadPhoto(file);
    const canvas=makeCanvas(source,{crop:1,rot:0,gray:false,limit:1800});
    let blob=await canvasBlob(canvas,'image/webp',.84);
    let ext='webp';
    if(!blob){blob=await canvasBlob(canvas,'image/jpeg',.86);ext='jpg';}
    canvas.width=1;canvas.height=1;
    if(!blob) return file;
    return new File([blob],`product_${Date.now()}.${ext}`,{type:blob.type||`image/${ext}`,lastModified:Date.now()});
  }catch(_e){
    return file;
  }finally{try{source?.close?.();}catch(_e){}}
}
async function uploadProductPhoto(file){
  if(!file)return;
  productImageUploading=true;productImageUploadFailed=false;
  const previewUrl=URL.createObjectURL(file);
  selectedPreview.innerHTML=`<div class="chosen"><img src="${previewUrl}"><span>در حال تبدیل و آپلود عکس…<br><small>${esc(file.name||'image')} · ${humanSize(file.size)}</small></span></div>`;
  status.textContent='در حال آماده‌سازی عکس برای فروشگاه…';
  let uploadSafe='کد خطا: IMG-305 — پردازش عکس انجام نشد. راه‌حل: یک عکس دیگر انتخاب کنید و دوباره تلاش کنید.';
  try{
    const normalized=await normalizeProductPhoto(file);
    const fd=new FormData();
    fd.append('csrf',productForm?.querySelector('input[name="csrf"]')?.value||'');
    fd.append('image_file',normalized,normalized.name||file.name||'product-image');
    const r=await fetch('image_upload.php',{method:'POST',body:fd,credentials:'same-origin',cache:'no-store'});
    let d=null;try{d=await r.json();}catch(_e){uploadSafe=r.status===413?'کد خطا: IMG-303 — حجم عکس بیش از حد مجاز است. راه‌حل: عکس کوچک‌تری انتخاب کنید.':'کد خطا: IMG-305 — پاسخ قابل پردازش دریافت نشد. راه‌حل: دوباره تلاش کنید و در صورت تکرار این کد را به پشتیبانی بدهید.';throw new Error('safe');}
    if(!r.ok||!d?.ok){const code=d?.code||'IMG-305',title=d?.error||'آپلود عکس انجام نشد',message=d?.message||'',help=d?.help||'دوباره تلاش کنید.';uploadSafe=`کد خطا: ${code} — ${title}${message?' | '+message:''} راه‌حل: ${help}`;throw new Error('safe');}
    $('#selected_image_url').value=d.url;
    $('#selected_image_source').value='تصویر مدیر';
    selectedPreview.innerHTML=`<div class="chosen"><img src="${esc(d.url)}?v=${Date.now()}"><span>✅ عکس آماده و روی سرور ذخیره شد<br><small>${d.width}×${d.height} · ${String(d.format||'').toUpperCase()} · ${humanSize(d.bytes)}${d.original_bytes?` (اصل: ${humanSize(d.original_bytes)})`:''}</small></span></div>`;
    status.textContent='عکس با موفقیت تبدیل و آپلود شد؛ حالا محصول را ذخیره کنید.';
    if(imageFile)imageFile.value='';
    productImageUploadFailed=false;
  }catch(_e){
    productImageUploadFailed=true;
    selectedPreview.innerHTML=`<div class="notice">❌ ${esc(uploadSafe)}</div>`;
    status.textContent=uploadSafe;
  }finally{
    productImageUploading=false;
    setTimeout(()=>URL.revokeObjectURL(previewUrl),1000);
  }
}

async function detectNative(surface){
  if(!('BarcodeDetector' in window))return '';
  try{const formats=['ean_13','ean_8','upc_a','upc_e','code_128','code_39','itf','codabar'];const detector=new BarcodeDetector({formats});const found=await detector.detect(surface);return found?.[0]?.rawValue||'';}catch(_e){return '';}
}
async function detectZXing(surface){
  if(!window.ZXing)return '';
  const r=new ZXing.BrowserMultiFormatReader();
  try{const url=surface.toDataURL('image/jpeg',.94);const result=await r.decodeFromImageUrl(url);return result?.getText?.()||'';}catch(_e){return '';}finally{try{r.reset();}catch(_e){}}
}
async function readBarcodeFromPhoto(file){
  if(!file)return;setScan('در حال تحلیل عکس بارکد…','ok');let source=null;
  try{
    source=await loadPhoto(file);
    const passes=[{crop:1,rot:0,gray:false,limit:2000,label:'تصویر اصلی'},{crop:.9,rot:0,gray:false,limit:1800,label:'برش نزدیک'},{crop:.72,rot:0,gray:false,limit:1800,label:'برش مرکزی'},{crop:1,rot:90,gray:false,limit:1800,label:'چرخش ۹۰ درجه'},{crop:1,rot:270,gray:false,limit:1800,label:'چرخش ۲۷۰ درجه'},{crop:.9,rot:0,gray:true,limit:1600,label:'کنتراست بالا'},{crop:.72,rot:90,gray:true,limit:1600,label:'برش و چرخش'},{crop:.72,rot:270,gray:true,limit:1600,label:'برش و چرخش معکوس'}];
    for(let i=0;i<passes.length;i++){setScan(`در حال خواندن بارکد… مرحله ${i+1} از ${passes.length} (${passes[i].label})`,'ok');const c=makeCanvas(source,passes[i]);let code=await detectNative(c);if(!code)code=await detectZXing(c);c.width=1;c.height=1;if(code){barcode.value=String(code).trim();setScan('بارکد با موفقیت از روی عکس خوانده شد: '+barcode.value,'ok');await lookup();return;}await new Promise(r=>setTimeout(r,25));}
    setScan('کد خطا: BAR-802 — بارکد خوانده نشد. راه‌حل: عکس را صاف، نزدیک و با نور کافی بگیرید؛ همه خط‌ها و عدد زیر بارکد داخل تصویر باشد.','warn');
  }catch(_e){setScan('کد خطا: BAR-802 — بارکد از عکس خوانده نشد. راه‌حل: عکس واضح‌تری بگیرید یا عدد زیر بارکد را دستی وارد کنید.','warn');}
  finally{try{source?.close?.();}catch(_e){}}
}
const barcodePhoto=$('#barcode_photo');barcodePhoto?.addEventListener('change',async e=>{const f=e.target.files?.[0];if(f)await readBarcodeFromPhoto(f);e.target.value='';});

scanBtn?.addEventListener('click',async()=>{if(reader){closeScanner();return;}if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){setScan(cameraErrorMessage({name:'Unsupported'}),'warn');barcodePhoto?.click();return;}if(!window.ZXing){setScan('کد خطا: BAR-801 — اسکن زنده آماده نیست. راه‌حل: از عکس بارکد استفاده کنید.','warn');barcodePhoto?.click();return;}try{setScan('در حال درخواست مجوز دوربین...');const probe=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'}}});probe.getTracks().forEach(t=>t.stop());video.style.display='block';scanBtn.textContent='بستن دوربین';reader=new ZXing.BrowserMultiFormatReader();await reader.decodeFromConstraints({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1920},height:{ideal:1080}}},video,result=>{if(result){barcode.value=result.getText();setScan('بارکد خوانده شد: '+barcode.value,'ok');closeScanner();lookup();}});setScan('دوربین فعال است؛ بارکد را نزدیک و صاف داخل کادر بگیرید.','ok');}catch(e){closeScanner();setScan(cameraErrorMessage(e),'warn');setTimeout(()=>barcodePhoto?.click(),150);}});
function chooseImage(it,el){document.querySelectorAll('.image-option').forEach(x=>x.classList.remove('selected'));el.classList.add('selected');$('#selected_image_url').value=it.image;$('#selected_image_source').value=it.source||'';selectedPreview.innerHTML=`<div class="chosen"><img src="${esc(it.image)}"><span>انتخاب شد${it.source?' · '+esc(it.source):''}</span></div>`;productImageUploadFailed=false;if(!nameInput.value.trim()&&it.name)nameInput.value=it.name;else if(!nameInput.value.trim()&&it.title)nameInput.value=it.title;if(!barcode.value.trim()&&it.barcode){barcode.value=it.barcode;lookup()}if(!$('#brand').value.trim()&&it.brand)$('#brand').value=it.brand;if(!$('#quantity_label').value.trim()&&it.quantity)$('#quantity_label').value=it.quantity;}
async function searchImages(){const q=nameInput.value.trim();if(q.length<2){status.textContent='حداقل دو حرف از نام محصول را بنویسید.';nameInput.focus();return}status.textContent='در حال پیدا کردن عکس‌های مرتبط...';results.innerHTML='<div class="gallery-loading">در حال جستجو…</div>';try{const r=await fetch(`api.php?action=image_search&q=${encodeURIComponent(q)}`,{cache:'no-store'}),d=await r.json();if(!d.ok)throw new Error(d.error||'خطا');results.innerHTML='';if(!d.items?.length){results.innerHTML='<div class="notice">عکسی پیدا نشد. نام کوتاه‌تر یا برند محصول را امتحان کنید، یا از دوربین خودتان عکس بگیرید.</div>';status.textContent='نتیجه‌ای پیدا نشد.';return}d.items.forEach(it=>{const b=document.createElement('button');b.type='button';b.className='image-option';b.innerHTML=`<img loading="lazy" referrerpolicy="no-referrer" src="${esc(it.image)}" alt="${esc(it.title||q)}"><span>${esc((it.title||q).slice(0,60))}</span><small>${esc(it.source||'')}</small>`;b.addEventListener('click',()=>chooseImage(it,b));results.appendChild(b)});status.textContent=`${d.items.length} عکس پیدا شد؛ روی عکس موردنظر بزنید.`;}catch(e){results.innerHTML='<div class="notice">جستجوی آنلاین عکس در دسترس نیست. می‌توانید عکس را از گوشی انتخاب کنید.</div>';status.textContent='جستجوی آنلاین انجام نشد.';}}
$('#searchImages')?.addEventListener('click',searchImages);nameInput?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();searchImages();}});
imageFile?.addEventListener('change',async e=>{const f=e.target.files?.[0];if(f)await uploadProductPhoto(f);});
productForm?.addEventListener('submit',e=>{if(productImageUploading){e.preventDefault();alert('عکس هنوز در حال تبدیل و آپلود است. بعد از نمایش پیام «عکس آماده شد» دوباره ذخیره محصول را بزنید.');return;}if(productImageUploadFailed){e.preventDefault();alert('عکس انتخاب‌شده هنوز روی سرور ذخیره نشده است. لطفاً دوباره عکس را انتخاب کنید.');}});
$('#newProduct')?.addEventListener('click',()=>{productForm.reset();$('#product_id').value='0';$('#selected_image_url').value='';$('#selected_image_source').value='';selectedPreview.innerHTML='';results.innerHTML='';status.textContent='بعد از نوشتن نام، «نمایش عکس‌ها» را بزنید.';$('#saveProduct').textContent='ثبت محصول';productImageUploading=false;productImageUploadFailed=false;setScan('فرم آماده محصول جدید است.');barcode.focus();});

let jsBarcodePromise=null;
function loadJsBarcode(){
  if(window.JsBarcode)return Promise.resolve();
  if(jsBarcodePromise)return jsBarcodePromise;
  jsBarcodePromise=new Promise((resolve,reject)=>{
    const s=document.createElement('script');
    s.src='https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
    s.onload=()=>resolve();s.onerror=()=>reject(new Error('کتابخانه نمایش بارکد لود نشد.'));
    document.head.appendChild(s);
  });
  return jsBarcodePromise;
}
async function decorateOrderBarcodes(){
  const rows=[...document.querySelectorAll('.orders-table tbody tr')];
  for(const row of rows){
    const orderCell=row.querySelector('td b');
    const m=(orderCell?.textContent||'').match(/(\d+)/);if(!m)continue;
    const orderId=m[1];
    try{
      const r=await fetch(`api.php?action=order_items&order_id=${encodeURIComponent(orderId)}`,{cache:'no-store'});
      const d=await r.json();if(!r.ok||!d?.ok)continue;
      const nodes=[...row.querySelectorAll('.order-item')];
      (d.items||[]).forEach((it,i)=>{
        const node=nodes[i];if(!node)return;
        const code=String(it.barcode||'').trim();
        const box=document.createElement('div');
        box.style.cssText='margin-top:7px;padding:7px 9px;border:1px dashed #b7c7bc;border-radius:9px;background:#fff;display:flex;gap:7px;align-items:center;flex-wrap:wrap';
        if(!code){box.innerHTML='<small style="color:#8a958e">بارکد ثبت نشده</small>';node.appendChild(box);return;}
        box.innerHTML=`<code style="direction:ltr;font-size:12px;font-weight:700;letter-spacing:.7px">${esc(code)}</code><button type="button" class="btn pale" style="padding:5px 8px;font-size:10px">نمایش بارکد</button>`;
        const btn=box.querySelector('button');
        btn.addEventListener('click',async()=>{
          let wrap=box.querySelector('.screen-barcode');
          if(wrap){wrap.remove();btn.textContent='نمایش بارکد';return;}
          try{
            await loadJsBarcode();
            wrap=document.createElement('div');wrap.className='screen-barcode';wrap.style.cssText='width:100%;background:#fff;padding:8px;border-radius:8px;overflow:auto;text-align:center';
            const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');wrap.appendChild(svg);box.appendChild(wrap);
            JsBarcode(svg,code,{format:'CODE128',displayValue:true,height:48,margin:4,fontSize:14});
            btn.textContent='بستن بارکد';
          }catch(_e){alert('نمایش گرافیکی بارکد در این مرورگر آماده نشد؛ شماره بارکد همین بالاست: '+code);}
        });
        node.appendChild(box);
      });
    }catch(_e){}
  }
}

decorateOrderBarcodes();
if(scanmsg){scanmsg.innerHTML=`دو روش دارید: <b>اسکن زنده</b> یا <b>عکس بارکد</b> · ${SCANNER_VERSION}`;scanmsg.className='scan-status ok';}
window.addEventListener('pagehide',closeScanner);
})();