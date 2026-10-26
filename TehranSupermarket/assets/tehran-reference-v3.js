(function(){'use strict';
function scrollToSel(sel){const el=document.querySelector(sel);if(!el)return;const y=el.getBoundingClientRect().top+scrollY-76;scrollTo({top:Math.max(0,y),behavior:'smooth'});}
function active(name){document.querySelectorAll('#tmx-mobile-nav [data-tmx-nav]').forEach(x=>x.classList.toggle('is-current',x.dataset.tmxNav===name));}
document.addEventListener('click',function(e){
 const nav=e.target.closest('[data-tmx-nav]');
 if(nav){const n=nav.dataset.tmxNav;if(n==='account')return;e.preventDefault();e.stopPropagation();if(n==='home'){active('home');scrollTo({top:0,behavior:'smooth'});}if(n==='products'){active('products');scrollToSel('#products');}if(n==='cart'){active('cart');scrollToSel('#cart');}if(n==='scan'){active('scan');const b=document.getElementById('barcode-scan-fab');if(b)b.click();}return;}
 const ai=e.target.closest('[data-tmx-ai]');if(ai){e.preventDefault();const b=document.getElementById('tehran-ai-fab-v2');if(b)b.click();return;}
 const scan=e.target.closest('[data-tmx-scan]');if(scan){e.preventDefault();const b=document.getElementById('barcode-scan-fab');if(b)b.click();return;}
 const jump=e.target.closest('[data-tmx-jump]');if(jump){e.preventDefault();scrollToSel(jump.dataset.tmxJump);}
},true);
window.addEventListener('scroll',function(){if(scrollY<180)active('home');},{passive:true});
})();
