(function(){
'use strict';
var API='ai_assistant.php';
var token='';
var busy=false;
var nf;try{nf=new Intl.NumberFormat('fa-IR');}catch(e){nf={format:function(n){return String(n);}};}
function money(n){return nf.format(Number(n||0))+' تومان';}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});}
function getEls(){
  var sheet=document.querySelector('.tehran-ai-sheet');
  if(!sheet)return null;
  return {
    sheet:sheet,
    msgs:sheet.querySelector('.tehran-ai-msgs'),
    quick:sheet.querySelector('.tehran-ai-quick'),
    compose:sheet.querySelector('.tehran-ai-compose'),
    title:sheet.querySelector('.tehran-ai-title b'),
    sub:sheet.querySelector('.tehran-ai-title small')
  };
}
function boot(){
  if(token)return Promise.resolve(token);
  return fetch(API+'?action=boot',{credentials:'same-origin',cache:'no-store'})
    .then(function(r){return r.json();})
    .then(function(d){if(d&&d.ok&&d.token)token=String(d.token);return token;});
}
function getCart(){
  return fetch(API+'?action=cart',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();});
}
function setQty(id,qty){
  return boot().then(function(){
    var f=new URLSearchParams();f.set('product_id',String(id));f.set('qty',String(qty));f.set('token',token);
    return fetch(API+'?action=set_qty',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:f.toString()});
  }).then(function(r){return r.json();});
}
function ensureStyle(){
  if(document.getElementById('tehran-ai-cart-manager-style'))return;
  var s=document.createElement('style');s.id='tehran-ai-cart-manager-style';s.textContent='\
.tehran-ai-cart-screen{display:flex;flex-direction:column;min-height:100%;gap:10px}.tehran-ai-cart-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.tehran-ai-cart-top h3{margin:0;font-size:17px;color:#173b28}.tehran-ai-cart-back{border:1px solid #cfe0d3;background:#fff;color:#17472e;border-radius:11px;padding:8px 11px;font:800 11px system-ui}.tehran-ai-cart-list{display:flex;flex-direction:column;gap:9px}.tehran-ai-cart-item{background:#fff;border:1px solid #dfe9e2;border-radius:15px;padding:10px;display:grid;grid-template-columns:60px 1fr;gap:10px;box-shadow:0 2px 10px #0000000b}.tehran-ai-cart-item img{width:60px;height:60px;object-fit:contain;background:#fafcfa;border-radius:10px}.tehran-ai-cart-ph{width:60px;height:60px;display:grid;place-items:center;background:#f2f6f3;border-radius:10px;font-size:27px}.tehran-ai-cart-info b{font-size:12px;line-height:1.55;display:block}.tehran-ai-cart-unit{font-size:10px;color:#708078;margin-top:2px}.tehran-ai-cart-actions{display:flex;align-items:center;gap:6px;margin-top:8px;flex-wrap:wrap}.tehran-ai-cart-actions button{border:0;border-radius:9px;height:33px;min-width:33px;padding:0 9px;font:800 12px system-ui}.tehran-ai-cart-minus,.tehran-ai-cart-plus{background:#edf7ef;color:#11823b;font-size:18px!important}.tehran-ai-cart-remove{background:#fff0ee;color:#b4372e}.tehran-ai-cart-qty{min-width:27px;text-align:center;font-weight:900}.tehran-ai-cart-line{margin-right:auto;font-size:11px;font-weight:900;color:#11823b}.tehran-ai-cart-summary{position:sticky;bottom:0;background:#173b28;color:#fff;border-radius:16px;padding:12px;box-shadow:0 8px 25px #0003}.tehran-ai-cart-summary-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.tehran-ai-cart-summary-row span{font-size:11px;opacity:.85}.tehran-ai-cart-summary-row b{font-size:16px}.tehran-ai-cart-buttons{display:grid;grid-template-columns:1fr 1fr;gap:8px}.tehran-ai-cart-buttons button{border:0;border-radius:11px;padding:10px;font:800 12px system-ui}.tehran-ai-cart-continue{background:#fff;color:#17472e}.tehran-ai-cart-checkout{background:#36c36a;color:#0d321d}.tehran-ai-cart-empty{background:#fff;border:1px dashed #cfdcd2;border-radius:16px;padding:28px 14px;text-align:center;color:#65736a}.tehran-ai-cart-empty .ico{font-size:38px;margin-bottom:8px}.tehran-ai-cart-loading{opacity:.55;pointer-events:none}';document.head.appendChild(s);
}
function leaveCart(){
  var e=getEls();if(!e)return;
  e.msgs.innerHTML='';
  if(e.quick)e.quick.style.display='flex';
  if(e.compose)e.compose.style.display='flex';
  if(e.title)e.title.textContent='دستیار خرید طهران مارکت';
  if(e.sub)e.sub.textContent='قیمت، موجودی، سبد و ثبت سفارش';
  var m=document.createElement('div');m.className='tehran-ai-msg';m.innerHTML='<div class="tehran-ai-bubble">ادامه خرید 👌 اسم کالای بعدی رو بگو.</div>';e.msgs.appendChild(m);
  var inp=e.compose&&e.compose.querySelector('input');if(inp)setTimeout(function(){inp.focus();},50);
}
function renderCart(c){
  ensureStyle();
  var e=getEls();if(!e)return;
  if(e.quick)e.quick.style.display='none';
  if(e.compose)e.compose.style.display='none';
  if(e.title)e.title.textContent='سبد خرید من';
  if(e.sub)e.sub.textContent='تعداد، حذف و مبلغ کل';
  var html='<div class="tehran-ai-cart-screen"><div class="tehran-ai-cart-top"><h3>🛒 سبد خرید</h3><button type="button" class="tehran-ai-cart-back" data-cart-back>ادامه خرید</button></div>';
  if(!c||!c.items||!c.items.length){
    html+='<div class="tehran-ai-cart-empty"><div class="ico">🛒</div><b>سبدت خالیه</b><div style="font-size:11px;margin-top:5px">برگرد و محصول انتخاب کن.</div></div>';
  }else{
    html+='<div class="tehran-ai-cart-list">';
    c.items.forEach(function(x){
      html+='<div class="tehran-ai-cart-item" data-cart-id="'+Number(x.id)+'" data-cart-qty="'+Number(x.qty)+'" data-cart-stock="'+Number(x.stock)+'">'+(x.image?'<img src="'+esc(x.image)+'" alt="">':'<div class="tehran-ai-cart-ph">🛍️</div>')+'<div class="tehran-ai-cart-info"><b>'+esc(x.name)+'</b><div class="tehran-ai-cart-unit">قیمت واحد: '+money(x.price)+' · موجودی: '+nf.format(x.stock)+'</div><div class="tehran-ai-cart-actions"><button type="button" class="tehran-ai-cart-minus" data-cart-minus>−</button><span class="tehran-ai-cart-qty">'+nf.format(x.qty)+'</span><button type="button" class="tehran-ai-cart-plus" data-cart-plus>+</button><button type="button" class="tehran-ai-cart-remove" data-cart-remove>حذف</button><span class="tehran-ai-cart-line">'+money(x.line_total)+'</span></div></div></div>';
    });
    html+='</div>';
  }
  html+='<div class="tehran-ai-cart-summary"><div class="tehran-ai-cart-summary-row"><span>'+nf.format(c&&c.count||0)+' کالا</span><div>جمع کل: <b>'+money(c&&c.total||0)+'</b></div></div><div class="tehran-ai-cart-buttons"><button type="button" class="tehran-ai-cart-continue" data-cart-back>ادامه خرید</button><button type="button" class="tehran-ai-cart-checkout" data-cart-checkout '+((!c||!c.items||!c.items.length)?'disabled':'')+'>تکمیل سفارش</button></div></div></div>';
  e.msgs.innerHTML=html;
  e.msgs.scrollTop=0;
}
function showCart(){
  var e=getEls();if(!e)return;
  e.msgs.classList.add('tehran-ai-cart-loading');
  getCart().then(function(d){renderCart(d&&d.cart?d.cart:{items:[],count:0,total:0});}).catch(function(){renderCart({items:[],count:0,total:0});}).finally(function(){var x=getEls();if(x)x.msgs.classList.remove('tehran-ai-cart-loading');});
}
function changeItem(item,newQty){
  if(busy)return;busy=true;
  var e=getEls();if(e)e.msgs.classList.add('tehran-ai-cart-loading');
  setQty(Number(item.dataset.cartId),newQty).then(function(d){
    if(d&&d.ok&&d.cart)renderCart(d.cart);else return getCart().then(function(x){renderCart(x.cart||{items:[],count:0,total:0});});
  }).catch(function(){return getCart().then(function(x){renderCart(x.cart||{items:[],count:0,total:0});});}).finally(function(){busy=false;var x=getEls();if(x)x.msgs.classList.remove('tehran-ai-cart-loading');});
}
document.addEventListener('click',function(ev){
  var t=ev.target;if(!t||!t.closest)return;
  var q=t.closest('.tehran-ai-quick button[data-q="سبدم"]');
  if(q){ev.preventDefault();ev.stopPropagation();ev.stopImmediatePropagation();showCart();return;}
  var back=t.closest('[data-cart-back]');if(back){ev.preventDefault();ev.stopPropagation();leaveCart();return;}
  var checkout=t.closest('[data-cart-checkout]');if(checkout){
    ev.preventDefault();ev.stopPropagation();ev.stopImmediatePropagation();
    leaveCart();setTimeout(function(){var b=document.querySelector('.tehran-ai-quick button[data-q="تکمیل سفارش"]');if(b)b.click();},30);return;
  }
  var item=t.closest('.tehran-ai-cart-item[data-cart-id]');if(!item)return;
  var qty=Math.max(1,Number(item.dataset.cartQty||1)),stock=Math.max(0,Number(item.dataset.cartStock||0));
  if(t.closest('[data-cart-plus]')){ev.preventDefault();changeItem(item,Math.min(50,stock,qty+1));return;}
  if(t.closest('[data-cart-minus]')){ev.preventDefault();changeItem(item,qty<=1?0:qty-1);return;}
  if(t.closest('[data-cart-remove]')){ev.preventDefault();changeItem(item,0);return;}
},true);
})();
