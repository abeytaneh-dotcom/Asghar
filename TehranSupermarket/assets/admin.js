(()=>{
const $=s=>document.querySelector(s), barcode=$('#barcode'), nameInput=$('#name'), scanBtn=$('#scan'), video=$('#video'), scanmsg=$('#scanmsg'), results=$('#imageResults'), status=$('#imageSearchStatus');
let reader=null;
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
function setScan(t,type=''){scanmsg.textContent=t;scanmsg.className='scan-status '+type;}
function closeScanner(){
 try{if(reader)reader.reset();}catch(_e){}
 reader=null;
 if(video&&video.srcObject){try{video.srcObject.getTracks().forEach(t=>t.stop());}catch(_e){} video.srcObject=null;}
 video.style.display='none';scanBtn.textContent='باز کردن دوربین';
}
function cameraErrorMessage(e){
 const n=e?.name||'';
 if(!window.isSecureContext)return 'دوربین فقط روی HTTPS باز می‌شود. همین صفحه را با https:// باز کنید.';
 if(!navigator.mediaDevices?.getUserMedia)return 'این مرورگر داخلی اجازه دسترسی مستقیم به دوربین را نمی‌دهد. صفحه را با Chrome یا Samsung Internet باز کنید.';
 if(n==='NotAllowedError'||n==='PermissionDeniedError')return 'مجوز دوربین رد شده است. از تنظیمات سایت/مرورگر، Camera را روی Allow بگذارید و دوباره امتحان کنید.';
 if(n==='NotFoundError'||n==='DevicesNotFoundError')return 'هیچ دوربین قابل‌استفاده‌ای پیدا نشد.';
 if(n==='NotReadableError'||n==='TrackStartError')return 'دوربین در اختیار برنامه دیگری است یا سیستم اجازه باز شدنش را نمی‌دهد. برنامه دوربین و اپ‌های دیگر را ببندید و دوباره امتحان کنید.';
 if(n==='OverconstrainedError'||n==='ConstraintNotSatisfiedError')return 'دوربین پشت با تنظیمات فعلی پیدا نشد؛ دوباره امتحان کنید.';
 if(n==='SecurityError')return 'مرورگر به دلایل امنیتی دسترسی دوربین را مسدود کرده است. صفحه را در Chrome باز کنید.';
 return 'دوربین باز نشد'+(n?' ('+n+')':'')+'. صفحه را در Chrome باز کنید و مجوز Camera را Allow کنید.';
}
function fillProduct(p){
 $('#product_id').value=p.id||0; barcode.value=p.barcode||''; nameInput.value=p.name||''; $('#brand').value=p.brand||''; $('#quantity_label').value=p.quantity_label||''; $('#price').value=p.price??''; $('#stock').value=p.stock??1; $('#category').value=p.category||'سایر';
 if(p.image_path){$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${esc(p.image_path)}"><span>عکس فعلی محصول</span></div>`;}else $('#selectedPreview').innerHTML='';
 $('#saveProduct').textContent='ذخیره تغییرات محصول';setScan('این بارکد قبلاً ثبت شده؛ اطلاعات محصول برای ویرایش باز شد.','ok');nameInput.focus();
}
async function lookup(){const b=barcode.value.trim();if(!b)return;setScan('در حال بررسی بارکد...');try{const r=await fetch(`api.php?action=barcode_lookup&barcode=${encodeURIComponent(b)}`,{cache:'no-store'}),d=await r.json();if(d.found)fillProduct(d.product);else{$('#product_id').value='0';setScan('بارکد جدید است. حالا نام محصول را بنویسید؛ مثلاً «پفک»، سپس عکس را انتخاب کنید.','ok');nameInput.focus();}}catch(e){setScan('بررسی بارکد انجام نشد؛ می‌توانید ادامه دهید.','warn');}}
$('#lookupBarcode').addEventListener('click',lookup);barcode.addEventListener('change',lookup);
scanBtn.addEventListener('click',async()=>{
 if(reader){closeScanner();return;}
 if(!window.isSecureContext){setScan('دوربین فقط روی HTTPS باز می‌شود.','warn');return;}
 if(!navigator.mediaDevices?.getUserMedia){setScan('این مرورگر داخلی دسترسی مستقیم به دوربین را پشتیبانی نمی‌کند. صفحه را با Chrome یا Samsung Internet باز کنید.','warn');return;}
 if(!window.ZXing){setScan('کتابخانه بارکدخوان بارگذاری نشده؛ اتصال اینترنت را بررسی کنید.','warn');return;}
 try{
   setScan('در حال درخواست مجوز دوربین...');
   const probe=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'}}});
   probe.getTracks().forEach(t=>t.stop());
   video.style.display='block';scanBtn.textContent='بستن دوربین';
   reader=new ZXing.BrowserMultiFormatReader();
   const constraints={audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}};
   await reader.decodeFromConstraints(constraints,video,(result)=>{
     if(result){const code=result.getText();barcode.value=code;setScan('بارکد خوانده شد: '+code,'ok');closeScanner();lookup();}
   });
   setScan('دوربین فعال است؛ بارکد را داخل کادر بگیرید.','ok');
 }catch(e){console.error('camera_error',e);closeScanner();setScan(cameraErrorMessage(e),'warn');}
});
function chooseImage(it,el){document.querySelectorAll('.image-option').forEach(x=>x.classList.remove('selected'));el.classList.add('selected');$('#selected_image_url').value=it.image;$('#selected_image_source').value=it.source||'';$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${esc(it.image)}"><span>انتخاب شد${it.source?' · '+esc(it.source):''}</span></div>`;if(!nameInput.value.trim()&&it.name)nameInput.value=it.name;else if(!nameInput.value.trim()&&it.title)nameInput.value=it.title;if(!barcode.value.trim()&&it.barcode){barcode.value=it.barcode;lookup()}if(!$('#brand').value.trim()&&it.brand)$('#brand').value=it.brand;if(!$('#quantity_label').value.trim()&&it.quantity)$('#quantity_label').value=it.quantity;}
async function searchImages(){const q=nameInput.value.trim();if(q.length<2){status.textContent='حداقل دو حرف از نام محصول را بنویسید.';nameInput.focus();return}status.textContent='در حال پیدا کردن عکس‌های مرتبط...';results.innerHTML='<div class="gallery-loading">در حال جستجو…</div>';try{const r=await fetch(`api.php?action=image_search&q=${encodeURIComponent(q)}`,{cache:'no-store'}),d=await r.json();if(!d.ok)throw new Error(d.error||'خطا');results.innerHTML='';if(!d.items?.length){results.innerHTML='<div class="notice">عکسی پیدا نشد. نام کوتاه‌تر یا برند محصول را امتحان کنید، یا از دوربین خودتان عکس بگیرید.</div>';status.textContent='نتیجه‌ای پیدا نشد.';return}d.items.forEach(it=>{const b=document.createElement('button');b.type='button';b.className='image-option';b.innerHTML=`<img loading="lazy" referrerpolicy="no-referrer" src="${esc(it.image)}" alt="${esc(it.title||q)}"><span>${esc((it.title||q).slice(0,60))}</span><small>${esc(it.source||'')}</small>`;b.addEventListener('click',()=>chooseImage(it,b));results.appendChild(b)});status.textContent=`${d.items.length} عکس پیدا شد؛ روی عکس موردنظر بزنید.`;}catch(e){results.innerHTML='<div class="notice">جستجوی آنلاین عکس در دسترس نیست. می‌توانید عکس را از گوشی انتخاب کنید.</div>';status.textContent='جستجوی آنلاین انجام نشد.';}}
$('#searchImages').addEventListener('click',searchImages);nameInput.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();searchImages();}});
$('#image_file').addEventListener('change',e=>{const f=e.target.files?.[0];if(!f)return;const u=URL.createObjectURL(f);$('#selectedPreview').innerHTML=`<div class="chosen"><img src="${u}"><span>عکس انتخاب‌شده از گوشی</span></div>`;$('#selected_image_url').value='';$('#selected_image_source').value='تصویر مدیر';});
$('#newProduct').addEventListener('click',()=>{$('#productForm').reset();$('#product_id').value='0';$('#selected_image_url').value='';$('#selected_image_source').value='';$('#selectedPreview').innerHTML='';results.innerHTML='';status.textContent='بعد از نوشتن نام، «نمایش عکس‌ها» را بزنید.';$('#saveProduct').textContent='ثبت محصول';setScan('فرم آماده محصول جدید است.');barcode.focus();});
window.addEventListener('pagehide',closeScanner);
})();