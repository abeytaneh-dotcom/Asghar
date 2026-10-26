(function(){
'use strict';
var KEY='tehran_ai_entry_seen_v1';
function seen(){try{return sessionStorage.getItem(KEY)==='1';}catch(e){return false;}}
function mark(){try{sessionStorage.setItem(KEY,'1');}catch(e){}}
function openAssistant(){
  if(seen())return true;
  var fab=document.getElementById('tehran-ai-fab-v2');
  if(!fab)return false;
  mark();fab.click();
  setTimeout(function(){
    var msgs=document.querySelector('.tehran-ai-msgs');
    if(!msgs||msgs.querySelector('[data-entry-welcome]'))return;
    var m=document.createElement('div');m.className='tehran-ai-msg';m.setAttribute('data-entry-welcome','1');
    m.innerHTML='<div class="tehran-ai-bubble"><b>چی لازم داری؟</b><br>اسم کالا یا چیزی که دنبالش هستی رو بگو. اگر هم ترجیح می‌دی خودت بین محصولات بگردی، با × این پنجره رو ببند؛ خرید دستی همیشه در دسترسه.</div>';
    msgs.appendChild(m);msgs.scrollTop=msgs.scrollHeight;
  },250);
  return true;
}
function start(){
  if(seen())return;
  var n=0,t=setInterval(function(){n++;if(openAssistant()||n>30)clearInterval(t);},150);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(start,450);},{once:true});else setTimeout(start,450);
})();
