(()=>{
'use strict';
const API='ai_assistant.php';
const nf=new Intl.NumberFormat('fa-IR');
let boot=null, opened=false, productCache=new Map(), pendingAge=null;
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const money=n=>nf.format(Number(n||0))+' تومان';

const style=document.createElement('style');
style.textContent=`
.ai-shop-fab{position:fixed;left:14px;bottom:14px;z-index:90000;width:56px;height:56px;border:0;border-radius:18px;background:linear-gradient(145deg,#173b28,#11823b);color:#fff;box-shadow:0 10px 30px #0004;display:grid;place-items:center;cursor:pointer;font:900 18px/1 system-ui}.ai-shop-fab .spark{font-size:24px;line-height:1}.ai-shop-fab small{font-size:9px;letter-spacing:.5px;margin-top:-4px}.ai-shop-fab.cart-up{bottom:78px}.ai-shop-ping{position:fixed;left:78px;bottom:20px;z-index:89999;background:#fff;color:#173b28;border:1px solid #dce9df;border-radius:16px 16px 4px 16px;padding:10px 12px;box-shadow:0 8px 28px #0002;font:700 12px/1.7 system-ui;max-width:210px;animation:aiPop .25s ease}.ai-shop-ping.cart-up{bottom:84px}@keyframes aiPop{from{transform:translateY(6px);opacity:0}to{transform:none;opacity:1}}
.ai-shop-sheet[hidden]{display:none!important}.ai-shop-sheet{position:fixed;inset:0;z-index:100002;background:#0008;display:flex;align-items:flex-end;justify-content:flex-start;backdrop-filter:blur(3px);direction:rtl}.ai-shop-panel{width:min(460px,100%);height:min(720px,88vh);background:#f7faf8;border-radius:24px 24px 0 0;box-shadow:0 -15px 60px #0005;display:flex;flex-direction:column;overflow:hidden}.ai-shop-head{background:#173b28;color:#fff;padding:13px 14px;display:flex;align-items:center;gap:10px}.ai-shop-avatar{width:40px;height:40px;border-radius:14px;background:#fff2;display:grid;place-items:center;font-size:22px}.ai-shop-headtext{flex:1}.ai-shop-headtext b{display:block;font-size:15px}.ai-shop-headtext small{font-size:10px;opacity:.8}.ai-shop-close{width:38px;height:38px;border:0;border-radius:12px;background:#fff2;color:#fff;font-size:25px}.ai-shop-messages{flex:1;overflow:auto;padding:12px;scroll-behavior:smooth}.ai-msg{display:flex;margin:7px 0}.ai-msg.user{justify-content:flex-start}.ai-bubble{max-width:88%;padding:10px 12px;border-radius:16px;background:#fff;color:#263d30;box-shadow:0 2px 10px #0000000d;font:13px/1.8 system-ui}.ai-msg.user .ai-bubble{background:#dff4e5;color:#144328;border-bottom-left-radius:5px}.ai-products{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:9px}.ai-product{border:1px solid #e1eae3;background:#fff;border-radius:14px;padding:8px;cursor:pointer;text-align:right}.ai-product img{width:100%;height:105px;object-fit:contain;background:#fafcfa;border-radius:10px}.ai-product .ph{height:105px;display:grid;place-items:center;font-size:32px;background:#f2f6f3;border-radius:10px}.ai-product b{display:block;font-size:12px;line-height:1.55;margin-top:6px;min-height:37px}.ai-product small{display:block;color:#6f7d74;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ai-product .pprice{color:#11823b;font-weight:900;font-size:12px;margin-top:4px}.ai-product .stock{font-size:10px;margin-top:2px;color:#647269}.ai-product.out{opacity:.55}.ai-qty-card,.ai-cart-card,.ai-checkout-card,.ai-age-card{margin-top:8px;padding:11px;border:1px solid #dfe9e2;background:#fff;border-radius:14px}.ai-qty-row{display:flex;align-items:center;gap:8px;margin-top:8px}.ai-qty-row button{width:36px;height:36px;border:0;border-radius:10px;background:#edf7ef;color:#11823b;font-size:20px;font-weight:900}.ai-qty-row .num{min-width:32px;text-align:center;font-weight:900}.ai-primary{border:0;border-radius:11px;background:#11823b;color:#fff;padding:10px 13px;font:800 12px system-ui;cursor:pointer}.ai-secondary{border:1px solid #cfe0d3;border-radius:11px;background:#fff;color:#17472e;padding:9px 12px;font:800 11px system-ui;cursor:pointer}.ai-cart-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #eef3ef}.ai-cart-row:last-child{border-bottom:0}.ai-cart-row img{width:42px;height:42px;object-fit:contain;border-radius:9px;background:#fafcfa}.ai-cart-row .txt{flex:1}.ai-cart-row b{font-size:11px}.ai-cart-row small{display:block;color:#708078;font-size:10px}.ai-checkout-card label{display:block;font-size:11px;font-weight:800;margin:8px 0 4px}.ai-checkout-card input,.ai-checkout-card textarea,.ai-checkout-card select{width:100%;box-sizing:border-box;border:1px solid #d7e3da;border-radius:11px;background:#fff;padding:10px;font:12px system-ui;color:#263b2f}.ai-checkout-card textarea{min-height:70px;resize:vertical}.ai-quick{display:flex;gap:7px;padding:8px 12px;border-top:1px solid #e3ebe5;background:#fff;overflow:auto}.ai-quick button{white-space:nowrap;border:1px solid #d9e6dc;background:#f8fbf9;color:#17472e;border-radius:999px;padding:7px 10px;font:800 11px system-ui}.ai-compose{display:flex;gap:7px;padding:9px 12px calc(9px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid #e4ece6}.ai-compose input{flex:1;min-width:0;border:1px solid #d6e3d9;border-radius:13px;padding:11px 12px;font:13px system-ui}.ai-compose button{border:0;border-radius:13px;background:#173b28;color:#fff;padding:0 15px;font-size:18px}.ai-note{font-size:10px;color:#748179;margin-top:5px}.ai-busy{opacity:.65;pointer-events:none}
@media(min-width:700px){.ai-shop-sheet{align-items:flex-end;padding:18px}.ai-shop-panel{border-radius:24px;height:min(720px,calc(100vh - 36px))}}
`;
document.head.appendChild(style);

const fab=document.createElement('button');fab.type='button';fab.className='ai-shop-fab';fab.setAttribute('aria-label','دستیار هوشمند خرید');fab.innerHTML='<span class="spark">✦</span><small>AI</small>';
const ping=document.createElement('div');ping.className='ai-shop-ping';ping.hidden=true;ping.textContent='سلام 👋 چی لازم داری؟ بگو تا از موجودی فروشگاه برات پیدا کنم.';
const sheet=document.createElement('div');sheet.className='ai-shop-sheet';sheet.hidden=true;sheet.innerHTML=`<section class="ai-shop-panel" role="dialog" aria-modal="true" aria-label="دستیار هوشمند خرید"><div class="ai-shop-head"><div class="ai-shop-avatar">✦</div><div class="ai-shop-headtext"><b>دستیار هوشمند طهران مارکت</b><small>جستجو، قیمت، موجودی و ثبت سفارش</small></div><button class="ai-shop-close" type="button" aria-label="بستن">×</button></div><div class="ai-shop-messages"></div><div class="ai-quick"><button type="button" data-quick="سبدم">🛒 سبدم</button><button type="button" data-quick="تکمیل سفارش">✅ تکمیل سفارش</button><button type="button" data-quick="شیر">🥛 شیر</button><button type="button" data-quick="نوشیدنی">🧃 نوشیدنی</button><button type="button" data-quick="تنقلات">🍪 تنقلات</button></div><form class="ai-compose"><input autocomplete="off" maxlength="120" placeholder="مثلاً: شیر کم‌چرب، روغن، نوشابه..."><button type="submit" aria-label="ارسال">➤</button></form></section>`;
document.body.append(fab,ping,sheet);
const messages=sheet.querySelector('.ai-shop-messages'), input=sheet.querySelector('.ai-compose input');
if(document.querySelector('.sticky')){fab.classList.add('cart-up');ping.classList.add('cart-up');}

function scrollEnd(){requestAnimationFrame(()=>{messages.scrollTop=messages.scrollHeight;});}
function bot(html){const d=document.createElement('div');d.className='ai-msg';d.innerHTML='<div class="ai-bubble">'+html+'</div>';messages.appendChild(d);scrollEnd();return d;}
function user(text){const d=document.createElement('div');d.className='ai-msg user';d.innerHTML='<div class="ai-bubble">'+esc(text)+'</div>';messages.appendChild(d);scrollEnd();}
function busy(on){sheet.querySelector('.ai-compose').classList.toggle('ai-busy',!!on);}
async function req(action,data={},method='POST'){
  const opts={method,credentials:'same-origin',cache:'no-store'};
  let url=API+'?action='+encodeURIComponent(action);
  if(method==='GET'){const q=new URLSearchParams(data);url+='&'+q.toString();}
  else{const f=new URLSearchParams();Object.entries(data).forEach(([k,v])=>f.set(k,String(v)));if(boot?.token&&!f.has('token'))f.set('token',boot.token);opts.headers={'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'};opts.body=f.toString();}
  const r=await fetch(url,opts);let d={};try{d=await r.json();}catch(e){throw new Error('پاسخ فروشگاه قابل خواندن نبود.');}return d;
}
async function init(){
  try{const d=await req('boot',{},'GET');if(d.ok)boot=d;}catch(e){}
  setTimeout(()=>{if(!opened){ping.hidden=false;setTimeout(()=>ping.hidden=true,7000);}},1600);
}
function open(){opened=true;ping.hidden=true;sheet.hidden=false;document.documentElement.style.overflow='hidden';if(!messages.children.length){bot('سلام 👋<br>چی لازم داری؟ اسم کالا یا چیزی که دنبالش هستی رو بگو؛ نمونه‌های موجود، <b>قیمت</b> و <b>موجودی</b> رو نشونت می‌دم.');}setTimeout(()=>input.focus(),100);}
function close(){sheet.hidden=true;document.documentElement.style.overflow='';}
fab.addEventListener('click',open);ping.addEventListener('click',open);sheet.querySelector('.ai-shop-close').addEventListener('click',close);sheet.addEventListener('click',e=>{if(e.target===sheet)close();});

function productGrid(items,q){
  if(!items.length)return 'برای «'+esc(q)+'» کالای موجودی پیدا نکردم. اسم یا برند دیگه‌ای امتحان کن.';
  items.forEach(p=>productCache.set(Number(p.id),p));
  return 'این‌ها رو پیدا کردم؛ روی هر کدوم بزن تا تعدادش رو انتخاب کنی:<div class="ai-products">'+items.map(p=>`<button type="button" class="ai-product ${Number(p.stock)<1?'out':''}" data-product="${Number(p.id)}" ${Number(p.stock)<1?'disabled':''}>${p.image?`<img src="${esc(p.image)}" alt="">`:'<div class="ph">🛒</div>'}<b>${esc(p.name)}</b><small>${esc([p.brand,p.quantity_label].filter(Boolean).join(' · '))}</small><div class="pprice">${money(p.price)}</div><div class="stock">${Number(p.stock)>0?'موجودی: '+nf.format(p.stock):'ناموجود'}</div></button>`).join('')+'</div>';
}
async function search(q){
  busy(true);try{const d=await req('search',{q},'GET');if(!d.ok)bot(esc(d.message||'جستجو انجام نشد.'));else bot(productGrid(d.items||[],q));}catch(e){bot('ارتباط با فروشگاه برقرار نشد. دوباره امتحان کن.');}finally{busy(false);}
}
function chooseProduct(id){
  const p=productCache.get(Number(id));if(!p)return;
  bot(`<b>${esc(p.name)}</b><div class="ai-note">قیمت هر عدد: ${money(p.price)} · موجودی: ${nf.format(p.stock)}</div><div class="ai-qty-card" data-qty-card="${p.id}"><div>چند عدد می‌خوای؟</div><div class="ai-qty-row"><button type="button" data-minus>−</button><span class="num" data-num>۱</span><button type="button" data-plus>+</button><button type="button" class="ai-primary" data-add>افزودن به سبد</button></div></div>`);
}
async function setQty(id,qty){
  const d=await req('set_qty',{product_id:id,qty});
  if(d.age_required){pendingAge={id,qty};bot('<div class="ai-age-card">🔞 برای خرید این کالا باید ۱۸ سال یا بیشتر داشته باشی.<br><button type="button" class="ai-primary" data-age-confirm style="margin-top:8px">۱۸ سال یا بیشتر دارم</button></div>');return false;}
  if(!d.ok){bot(esc(d.message||'به سبد اضافه نشد.'));return false;}
  bot('✅ '+esc(d.product.name)+'، تعداد '+nf.format(d.product.qty)+' به سبد اضافه شد.<div class="ai-note">سبد فعلی: '+nf.format(d.cart.count)+' کالا · '+money(d.cart.total)+'</div>');
  return true;
}
async function showCart(){
  try{const d=await req('cart',{},'GET'),c=d.cart;if(!c||!c.items?.length){bot('سبدت هنوز خالیه. اسم چیزی که می‌خوای رو بگو تا پیدا کنم.');return;}
    bot('<b>سبد خریدت</b><div class="ai-cart-card">'+c.items.map(x=>`<div class="ai-cart-row">${x.image?`<img src="${esc(x.image)}" alt="">`:''}<div class="txt"><b>${esc(x.name)}</b><small>${nf.format(x.qty)} عدد · ${money(x.line_total)}</small></div></div>`).join('')+`<div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px"><b>جمع: ${money(c.total)}</b><button type="button" class="ai-primary" data-checkout>تکمیل سفارش</button></div></div>`);
  }catch(e){bot('سبد خرید خوانده نشد.');}
}
async function checkout(){
  if(!boot){try{boot=await req('boot',{},'GET');}catch(e){}}
  const d=await req('cart',{},'GET');if(!d.cart?.items?.length){bot('اول حداقل یک کالا به سبد اضافه کن.');return;}
  if(boot?.store && !boot.store.open){bot('الان سفارش‌گیری فروشگاه بسته است. می‌تونی سبدت رو آماده نگه داری و در ساعت کاری ثبتش کنی.');return;}
  const z=(boot?.zones||[]);const pre=boot?.prefill||{};
  bot(`<b>برای ثبت نهایی این مشخصات رو وارد کن:</b><div class="ai-checkout-card"><label>نام و نام خانوادگی</label><input data-co-name value="${esc(pre.name||'')}"><label>شماره موبایل</label><input data-co-phone inputmode="numeric" maxlength="11" placeholder="09xxxxxxxxx" value="${esc(pre.phone||'')}"><label>آدرس کامل تحویل</label><textarea data-co-address>${esc(pre.address||'')}</textarea><label>محدوده ارسال</label><select data-co-zone>${z.map(x=>`<option value="${Number(x.id)}" data-min="${Number(x.min_order)}">${esc(x.name)} · ${money(x.fee)}</option>`).join('')}</select><div class="ai-note">جمع کالاها: ${money(d.cart.total)}</div><button type="button" class="ai-primary" data-place-order style="width:100%;margin-top:10px">ثبت نهایی سفارش</button></div>`);
}
function submitOrder(card){
  const name=card.querySelector('[data-co-name]').value.trim(),phone=card.querySelector('[data-co-phone]').value.trim(),address=card.querySelector('[data-co-address]').value.trim(),zone=card.querySelector('[data-co-zone]').value;
  if(name.length<2){bot('نام را کامل وارد کن.');return;}if(!/^09\d{9}$/.test(phone)){bot('شماره موبایل باید ۱۱ رقمی و با 09 شروع شود.');return;}if(address.length<5){bot('آدرس تحویل را کامل‌تر وارد کن.');return;}
  const f=document.createElement('form');f.method='post';f.action='index.php';f.style.display='none';
  const vals={csrf:boot.csrf,action:'checkout',name,phone,address,zone_id:zone,slot:'اولین زمان ممکن',coupon_code:'',delivery_lat:'',delivery_lng:'',delivery_accuracy:''};
  Object.entries(vals).forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=String(v??'');f.appendChild(i);});
  document.body.appendChild(f);bot('سفارشت داره ثبت می‌شه…');setTimeout(()=>f.submit(),150);
}

messages.addEventListener('click',async e=>{
  const pb=e.target.closest('[data-product]');if(pb){chooseProduct(pb.dataset.product);return;}
  const card=e.target.closest('[data-qty-card]');
  if(card){let n=Number(card.dataset.qty||1);const p=productCache.get(Number(card.dataset.qtyCard));if(e.target.closest('[data-minus]'))n=Math.max(1,n-1);if(e.target.closest('[data-plus]'))n=Math.min(Number(p?.stock||50),50,n+1);card.dataset.qty=String(n);card.querySelector('[data-num]').textContent=nf.format(n);if(e.target.closest('[data-add]')){e.target.disabled=true;try{await setQty(Number(card.dataset.qtyCard),n);}finally{e.target.disabled=false;}}return;}
  if(e.target.closest('[data-age-confirm]')){const d=await req('age_confirm',{});if(d.ok&&pendingAge){const p=pendingAge;pendingAge=null;await setQty(p.id,p.qty);}return;}
  if(e.target.closest('[data-checkout]')){checkout();return;}
  const place=e.target.closest('[data-place-order]');if(place){submitOrder(place.closest('.ai-checkout-card'));return;}
});
sheet.querySelector('.ai-quick').addEventListener('click',e=>{const b=e.target.closest('[data-quick]');if(!b)return;const q=b.dataset.quick;if(q==='سبدم')showCart();else if(q==='تکمیل سفارش')checkout();else{user(q);search(q);}});
sheet.querySelector('.ai-compose').addEventListener('submit',e=>{e.preventDefault();const q=input.value.trim();if(!q)return;input.value='';user(q);const n=q.replace(/\s/g,'');if(/سبد/.test(q)){showCart();return;}if(/تکمیل|ثبت سفارش|سفارشمو|سفارشم/.test(n)){checkout();return;}search(q);});
init();
})();
