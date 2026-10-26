(function(){
'use strict';
var API='ai_assistant.php';
var token='';
var pending=null;
var bypass=false;

function boot(){
  return fetch(API+'?action=boot',{credentials:'same-origin',cache:'no-store'})
    .then(function(r){return r.json();})
    .then(function(d){if(d&&d.ok&&d.token)token=String(d.token);return d;})
    .catch(function(){return null;});
}

function newestPendingCard(){
  var cards=document.querySelectorAll('.tehran-ai-card[data-pid][data-qty]');
  if(!cards.length)return null;
  return cards[cards.length-1];
}

function setPending(card){
  if(!card)return;
  pending=card;
  if(!card.dataset.autoSaved)card.dataset.autoSaved='0';
}

function paintSaved(card){
  if(!card)return;
  var add=card.querySelector('[data-add]');
  if(add){
    var q=Math.max(1,Number(card.dataset.qty||1));
    add.textContent='✓ '+q.toLocaleString('fa-IR')+' عدد ثبت شد';
  }
}

function commitPending(){
  var card=pending||newestPendingCard();
  if(!card||card.dataset.autoSaved==='1')return Promise.resolve(true);
  var id=Number(card.dataset.pid||0), qty=Math.max(1,Number(card.dataset.qty||1));
  if(!id)return Promise.resolve(true);
  function send(){
    var f=new URLSearchParams();
    f.set('product_id',String(id));
    f.set('qty',String(qty));
    f.set('token',token);
    return fetch(API+'?action=set_qty',{
      method:'POST',credentials:'same-origin',cache:'no-store',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:f.toString()
    }).then(function(r){return r.json();});
  }
  var ready=token?Promise.resolve():boot();
  return ready.then(function(){if(!token)return false;return send();}).then(function(d){
    if(d&&d.age_required){
      var add=card.querySelector('[data-add]');
      if(add)add.click();
      return false;
    }
    if(d&&d.ok){
      card.dataset.autoSaved='1';
      paintSaved(card);
      return true;
    }
    return false;
  }).catch(function(){return false;});
}

function replayClick(el){
  bypass=true;
  try{el.click();}finally{setTimeout(function(){bypass=false;},0);}
}
function replaySubmit(form){
  bypass=true;
  try{if(form.requestSubmit)form.requestSubmit();else form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
  finally{setTimeout(function(){bypass=false;},0);}
}

var mo=new MutationObserver(function(){
  var card=newestPendingCard();
  if(card&&card!==pending)setPending(card);
});
function start(){
  var root=document.querySelector('.tehran-ai-sheet')||document.body;
  mo.observe(root,{childList:true,subtree:true});
  boot();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();

document.addEventListener('click',function(e){
  var target=e.target;
  if(!target||bypass)return;

  var card=target.closest&&target.closest('.tehran-ai-card[data-pid][data-qty]');
  if(card&&(target.hasAttribute('data-plus')||target.hasAttribute('data-minus'))){
    setTimeout(function(){card.dataset.autoSaved='0';setPending(card);var add=card.querySelector('[data-add]');if(add)add.textContent='افزودن به سبد';},0);
    return;
  }
  if(card&&target.hasAttribute('data-add')){
    card.dataset.autoSaved='1';
    setPending(card);
    return;
  }

  var nextProduct=target.closest&&target.closest('.tehran-ai-product[data-pid]');
  var quick=target.closest&&target.closest('.tehran-ai-quick button');
  var checkoutBtn=target.closest&&target.closest('[data-checkout]');
  if((nextProduct||quick||checkoutBtn)&&pending&&pending.dataset.autoSaved!=='1'){
    e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();
    var el=nextProduct||quick||checkoutBtn;
    commitPending().then(function(ok){if(ok)replayClick(el);});
  }
},true);

document.addEventListener('submit',function(e){
  if(bypass)return;
  var form=e.target;
  if(!form||!form.classList||!form.classList.contains('tehran-ai-compose'))return;
  if(pending&&pending.dataset.autoSaved!=='1'){
    e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();
    commitPending().then(function(ok){if(ok)replaySubmit(form);});
  }
},true);
})();
