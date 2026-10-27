(function(){'use strict';
function svg(paths){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+paths+'</svg>';}
var icons={home:svg('<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>'),products:svg('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'),cart:svg('<path d="M2 3h3l2 13h12l3-10H6"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/>'),account:svg('<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3z"/>'),pin:svg('<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>'),bag:svg('<path d="M4 8h16l-2 13H6L4 8Z"/><path d="M9 8V5a3 3 0 0 1 6 0v3M8 12v5m8-5v5"/>')};
function ready(){
 document.body.classList.add('tm27-showcase');
 var title=document.querySelector('.tm-ref-hero-copy h1');
 if(title)title.innerHTML='اولین فروشگاه <strong>موادغذایی آنلاین هوشمند</strong> در ایران';
 var cta=document.querySelector('.tm-ref-cta');if(cta)cta.textContent='همین حالا خرید کنید ‹';
 var brandText=document.querySelector('.tm-ref-brand-copy b');if(brandText)brandText.innerHTML='طهران <span>مارکت</span>';
 var brand=document.querySelector('.tm-ref-brand-mark');if(brand)brand.innerHTML=icons.bag;
 var account=document.querySelector('.tm-ref-head-actions .acct i');if(account)account.innerHTML=icons.account;
 var cart=document.querySelector('.tm-ref-head-actions .cart i');if(cart)cart.innerHTML=icons.cart;
 document.querySelectorAll('#tmx-mobile-nav [data-tmx-nav]').forEach(function(el){var name=el.getAttribute('data-tmx-nav'),slot=el.querySelector('.tmx-nav-ico');if(slot&&icons[name])slot.innerHTML=icons[name];});
 var categories=document.querySelector('.tm-ref-categories');
 var preferred=[['مواد غذایی','pantry'],['تنقلات و شیرینی','snacks'],['نوشیدنی','drinks'],['لبنیات و صبحانه','dairy']];
 if(categories){
   var original=Array.from(categories.children),front=[];
   preferred.forEach(function(pair){var el=original.find(function(x){var b=x.querySelector('b');return b&&b.textContent.trim()===pair[0];});if(!el)return;var media=el.querySelector('.tm-ref-cat-media');if(media){var im=document.createElement('img');im.src='assets/tm27-cat-'+pair[1]+'.webp?v=1';im.alt=pair[0];im.width=180;im.height=150;media.replaceChildren(im);}front.push(el);});
   front.concat(original.filter(function(x){return front.indexOf(x)<0;})).forEach(function(x){categories.appendChild(x);});
 }
 var heading=document.querySelector('#categories h2');if(heading)heading.textContent='دسته‌بندی‌ها';
 var all=document.querySelector('#categories .tm-ref-section-head a');
 if(all){all.textContent='همه دسته‌ها ‹';all.setAttribute('aria-expanded','false');all.addEventListener('click',function(e){e.preventDefault();var on=document.body.classList.toggle('tm27-categories-expanded');all.setAttribute('aria-expanded',String(on));all.textContent=on?'بستن دسته‌ها ‹':'همه دسته‌ها ‹';});}
 var hero=document.querySelector('.tm-ref-hero');
 if(hero&&!document.querySelector('.tm27-address')){
   var delivery=document.createElement('button');delivery.type='button';delivery.className='tm27-address';delivery.innerHTML='<span class="tm27-address-pin">'+icons.pin+'</span><span class="tm27-address-label">ارسال به آدرس شما</span><span class="tm27-address-arrow">‹</span>';
   hero.insertAdjacentElement('afterend',delivery);
   function refresh(){var chosen=false;try{chosen=!!JSON.parse(sessionStorage.getItem('tehran_ai_delivery_location_v1')||'null');}catch(e){}delivery.querySelector('.tm27-address-label').textContent=chosen?'مقصد ارسال مشخص شده؛ تغییر آدرس':'ارسال به آدرس شما';}
   delivery.addEventListener('click',function(){var fab=document.getElementById('tehran-ai-fab-v2'),checkout=document.querySelector('.tehran-ai-quick [data-q="تکمیل سفارش"]');if(fab)fab.click();if(checkout)checkout.click();requestAnimationFrame(function(){var choices=document.querySelectorAll('.tehran-ai-check [data-ai-fulfillment="delivery"]');if(choices.length)choices[choices.length-1].click();});});
   document.addEventListener('click',function(e){if(e.target.closest('.tehran-ai-close'))setTimeout(refresh,0);},true);refresh();
 }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});else ready();
})();
