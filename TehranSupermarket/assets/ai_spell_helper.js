(function(){
'use strict';
if(window.__tehranAiSpellHelper)return;window.__tehranAiSpellHelper=1;
var nativeFetch=window.fetch.bind(window);
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#039;'}[m]||m;});}
function ensureStyle(){
  if(document.getElementById('tehran-ai-spell-style'))return;
  var st=document.createElement('style');st.id='tehran-ai-spell-style';st.textContent='\
.tehran-ai-spell-note{margin:7px 0;display:flex}.tehran-ai-spell-note>div{max-width:92%;background:#fff8df;border:1px solid #f2df9e;color:#5a4a15;border-radius:15px;padding:10px 12px;box-shadow:0 2px 10px #0001;font:12px/1.8 system-ui}.tehran-ai-spell-note b{color:#173b28}.tehran-ai-spell-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}.tehran-ai-spell-chips button{border:1px solid #cddfcf;background:#fff;color:#12602f;border-radius:999px;padding:6px 9px;font:800 11px system-ui}';document.head.appendChild(st);
}
function insertNote(d,original){
  if(!d||!d.ok)return;
  var corrected=String(d.corrected_query||'').trim();
  var sugs=Array.isArray(d.suggestions)?d.suggestions.filter(Boolean):[];
  if(!corrected&&!sugs.length)return;
  var msgs=document.querySelector('.tehran-ai-msgs');if(!msgs)return;
  ensureStyle();
  var sig=(original||'')+'|'+corrected+'|'+sugs.join(',');
  if(msgs.querySelector('[data-spell-sig="'+CSS.escape(sig)+'"]'))return;
  var n=document.createElement('div');n.className='tehran-ai-spell-note';n.setAttribute('data-spell-sig',sig);
  var html='<div>';
  if(corrected){html+='احتمالاً منظورت <b>«'+esc(corrected)+'»</b> بوده؛ نزدیک‌ترین نتیجه‌ها را برات آوردم.';}
  else{html+='نزدیک‌ترین عبارت‌هایی که پیدا کردم:';}
  var chips=[];if(corrected)chips.push(corrected);sugs.forEach(function(x){if(chips.indexOf(x)<0)chips.push(x);});
  if(chips.length){html+='<div class="tehran-ai-spell-chips">';chips.slice(0,4).forEach(function(x){html+='<button type="button" data-ai-spell="'+esc(x)+'">'+esc(x)+'</button>';});html+='</div>';}
  html+='</div>';n.innerHTML=html;msgs.appendChild(n);msgs.scrollTop=msgs.scrollHeight;
}
window.fetch=function(input,init){
  try{
    var raw=typeof input==='string'?input:(input&&input.url)||'';
    var u=new URL(raw,location.href);
    if(/ai_assistant\.php$/i.test(u.pathname)&&u.searchParams.get('action')==='search'){
      var q=u.searchParams.get('q')||'';
      var fu=new URL('ai_search_fuzzy.php',location.href);fu.searchParams.set('q',q);
      return nativeFetch(fu.toString(),Object.assign({},init||{},{credentials:'same-origin',cache:'no-store'})).then(function(r){
        return r.clone().json().then(function(d){insertNote(d,q);return r;}).catch(function(){return r;});
      });
    }
  }catch(e){}
  return nativeFetch(input,init);
};
document.addEventListener('click',function(e){
  var b=e.target.closest&&e.target.closest('[data-ai-spell]');if(!b)return;
  e.preventDefault();var q=b.getAttribute('data-ai-spell')||'';var form=document.querySelector('.tehran-ai-compose');var inp=form&&form.querySelector('input');if(!form||!inp)return;
  inp.value=q;if(form.requestSubmit)form.requestSubmit();else form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
},true);
ensureStyle();
})();
