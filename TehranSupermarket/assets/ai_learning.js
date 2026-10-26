(function(){
'use strict';
var endpoint='ai_learning.php';
function send(data){
  try{
    var body=new URLSearchParams();Object.keys(data||{}).forEach(function(k){if(data[k]!==undefined&&data[k]!==null&&data[k]!=='')body.set(k,String(data[k]));});
    if(navigator.sendBeacon){var blob=new Blob([body.toString()],{type:'application/x-www-form-urlencoded;charset=UTF-8'});if(navigator.sendBeacon(endpoint,blob))return;}
    fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString(),keepalive:true,cache:'no-store'}).catch(function(){});
  }catch(e){}
}
document.addEventListener('submit',function(e){
  var f=e.target;if(!f||!f.classList||!f.classList.contains('tehran-ai-compose'))return;
  var i=f.querySelector('input');var q=i&&i.value?i.value.trim():'';if(q)send({event_type:'search',q:q});
},true);
document.addEventListener('click',function(e){
  var t=e.target.closest&&e.target.closest('.tehran-ai-product[data-pid]');
  if(t){send({event_type:'product_click',product_id:t.getAttribute('data-pid')||''});return;}
  var add=e.target.closest&&e.target.closest('[data-add]');
  if(add){var card=add.closest('.tehran-ai-card');send({event_type:'add_to_cart',product_id:card&&card.dataset?card.dataset.pid:'',quantity:card&&card.dataset?card.dataset.qty:'1'});return;}
  var spell=e.target.closest&&e.target.closest('[data-ai-spell]');
  if(spell){send({event_type:'spell_accept',q:spell.getAttribute('data-ai-spell-original')||'',corrected:spell.getAttribute('data-ai-spell')||''});return;}
  var co=e.target.closest&&e.target.closest('[data-checkout]');if(co){send({event_type:'checkout_start'});return;}
  var fm=e.target.closest&&e.target.closest('[data-ai-fulfillment]');if(fm){send({event_type:'fulfillment_choice',fulfillment_method:fm.getAttribute('data-ai-fulfillment')||''});}
},true);
})();
