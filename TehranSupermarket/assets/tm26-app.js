(function(){'use strict';
function ready(){
 document.body.classList.add('tm26-app');
 var hero=document.querySelector('.tm-ref-hero-copy');
 if(hero&&!hero.querySelector('.tm26-kicker')){var tag=document.createElement('span');tag.className='tm26-kicker';tag.textContent='✦ یک تجربه تازه از خرید روزانه';hero.prepend(tag);}
 var art=document.querySelector('.tm-ref-promo.ai .tm-ref-promo-art');
 if(art){var image=document.createElement('img');image.src='assets/tm26-assistant.webp?v=1';image.alt='دستیار هوشمند خرید';image.width=110;image.height=110;image.loading='lazy';art.replaceChildren(image);}
 // Keep the center dock position for the assistant; barcode scanning stays beside search.
 var center=document.querySelector('#tmx-mobile-nav .tmx-scan');
 if(center){center.setAttribute('aria-hidden','true');center.setAttribute('tabindex','-1');}
 var menu=document.querySelector('.tm-ref-menu-mobile');
 if(menu){menu.setAttribute('aria-label','دسته‌بندی کالاها');}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});else ready();
})();
