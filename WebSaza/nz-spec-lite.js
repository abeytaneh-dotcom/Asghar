(()=>{
'use strict';
const YEARS=Array.from({length:106},(_,i)=>String(1405-i));
const COLORS=['سفید','مشکی','نقره‌ای','خاکستری','نوک‌مدادی','ذغالی','آبی','آبی تیره','آبی روشن','سرمه‌ای','قرمز','زرشکی','سبز','سبز تیره','یشمی','زرد','نارنجی','قهوه‌ای','کرم','بژ','طلایی','مسی','بنفش','صورتی','فیروزه‌ای','طوسی','دودی','سفید صدفی','مشکی متالیک','نقره‌ای متالیک','خاکستری متالیک','چند رنگ','سایر'];
const BODY=['بی‌رنگ','یک لکه رنگ','دو لکه رنگ','چند لکه رنگ','یک در رنگ','دو در رنگ','کاپوت رنگ','گلگیر رنگ','دور رنگ','تمام رنگ','تعویض قطعه بدنه','تصادفی','اوراقی'];
const CONDITION=['نو','آکبند','در حد نو','کارکرده سالم','نیازمند تعمیر','معیوب'];
const STORAGE=['16 گیگابایت','32 گیگابایت','64 گیگابایت','128 گیگابایت','256 گیگابایت','512 گیگابایت','1 ترابایت','2 ترابایت','4 ترابایت','8 ترابایت','سایر'];
const RAM=['2 گیگابایت','3 گیگابایت','4 گیگابایت','6 گیگابایت','8 گیگابایت','12 گیگابایت','16 گیگابایت','18 گیگابایت','24 گیگابایت','32 گیگابایت','48 گیگابایت','64 گیگابایت','96 گیگابایت','128 گیگابایت','سایر'];
function fieldLabel(field){return (field.querySelector('label')?.textContent||'').replace(/\s*\*\s*$/,'').trim()}
function optionsHtml(items,current=''){return '<option value="">انتخاب</option>'+items.map(v=>`<option value="${String(v).replace(/"/g,'&quot;')}"${v===current?' selected':''}>${v}</option>`).join('')}
function replaceWithSelect(old,items){
  if(!old)return old;
  if(old.tagName==='SELECT'&&old.dataset.nzLite==='1')return old;
  const s=document.createElement('select');
  [...old.attributes].forEach(a=>{if(!['type','list','placeholder','min','max','inputmode'].includes(a.name))s.setAttribute(a.name,a.value)});
  s.dataset.nzLite='1';
  s.innerHTML=optionsHtml(items,old.value||'');
  old.replaceWith(s);
  return s;
}
function enforce(){
 const box=document.getElementById('nzSpecs');if(!box)return;
 [...box.querySelectorAll('.field')].forEach(field=>{
   const label=fieldLabel(field);
   let input=field.querySelector('input,select');if(!input)return;
   if(label==='برند'||label==='برند/سازنده'||label==='مدل')return;
   if(['سال ساخت','سال تولید'].includes(label))replaceWithSelect(input,YEARS);
   else if(label==='رنگ')replaceWithSelect(input,COLORS);
   else if(label==='وضعیت بدنه')replaceWithSelect(input,BODY);
   else if(label==='وضعیت')replaceWithSelect(input,CONDITION);
   else if(label==='حافظه/ظرفیت')replaceWithSelect(input,STORAGE);
   else if(label==='رم')replaceWithSelect(input,RAM);
 });
}
function init(){
 enforce();
 const mo=new MutationObserver(()=>enforce());
 mo.observe(document.body,{childList:true,subtree:true});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
