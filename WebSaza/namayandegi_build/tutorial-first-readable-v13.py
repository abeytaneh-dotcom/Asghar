from pathlib import Path

support = Path(__file__).resolve().parent
root = support.parent / 'namayandegi'
style_path = root / 'style.css'
app_path = root / 'app.js'

css = r'''
/* WEBSAZA_TUTORIAL_FIRST_READABLE_V13 */
.joinTraining.ts-wrap{
  margin:0 0 34px!important;
  padding:34px max(14px,calc((100vw - 1180px)/2)) 38px!important;
  background:linear-gradient(180deg,#eef7d7 0%,#f7faef 100%)!important;
  border-top:5px solid #111820!important;
  border-bottom:5px solid #c8ff45!important;
  box-shadow:0 18px 55px rgba(17,24,32,.13)!important;
}
.joinTraining.ts-wrap:before{
  content:'راهنمای شروع سریع';
  background:#111820!important;
  color:#c8ff45!important;
  border-color:#fff!important;
  box-shadow:0 8px 20px rgba(17,24,32,.2)!important;
}
.joinTraining.ts-wrap .joinSectionHead>span{
  background:#111820!important;
  color:#c8ff45!important;
  border-color:#111820!important;
}
.joinTraining.ts-wrap .joinSectionHead h2{color:#111820!important;text-shadow:none!important}
.joinTraining.ts-wrap .joinSectionHead p{color:#4b5563!important;font-weight:700!important}
.joinTraining.ts-wrap .ts-slider{
  background:#fff!important;
  border:3px solid #111820!important;
  box-shadow:0 20px 48px rgba(17,24,32,.17),0 0 0 6px #c8ff45!important;
}
.joinTraining.ts-wrap .ts-viewport{background:#fff!important;border-color:#cfd6dc!important}
.joinTraining.ts-wrap .ts-slide,
.joinTraining.ts-wrap .ts-slide.is-active{
  opacity:1!important;
  transform:none!important;
  filter:none!important;
}
.joinTraining.ts-wrap .ts-slide *,
.joinTraining.ts-wrap .ts-slide.is-active *{opacity:1!important;filter:none!important}
.joinTraining.ts-wrap .ts-stage{background:#f8fafb!important;color:#111820!important}
.joinTraining.ts-wrap .ts-stage h1,
.joinTraining.ts-wrap .ts-stage h2,
.joinTraining.ts-wrap .ts-stage h3,
.joinTraining.ts-wrap .ts-stage h4,
.joinTraining.ts-wrap .ts-stage b,
.joinTraining.ts-wrap .ts-stage strong{color:#111820!important}
.joinTraining.ts-wrap .ts-stage p,
.joinTraining.ts-wrap .ts-stage small,
.joinTraining.ts-wrap .ts-stage label{color:#4b5563!important}
.joinTraining.ts-wrap .ts-guide{background:#fff!important;color:#111820!important;border-top:2px solid #d9e0e5!important}
.joinTraining.ts-wrap .ts-guide h3{color:#111820!important;font-weight:950!important}
.joinTraining.ts-wrap .ts-guide li span{color:#26313e!important;font-weight:750!important}
.joinTraining.ts-wrap .ts-guide li i{background:#c8ff45!important;color:#111820!important;border:1px solid #a7d228!important}
.joinTraining.ts-wrap .ts-tag{background:#111820!important;color:#c8ff45!important;border-color:#111820!important}
.joinTraining.ts-wrap .ts-browser,
.joinTraining.ts-wrap .ts-loginmock,
.joinTraining.ts-wrap .ts-dashboard,
.joinTraining.ts-wrap .ts-leadmock,
.joinTraining.ts-wrap .ts-finance,
.joinTraining.ts-wrap .ts-formmock{background:#fff!important;color:#111820!important}
.joinTraining.ts-wrap .ts-fields label,
.joinTraining.ts-wrap .ts-loginbox label{color:#374151!important}
.joinTraining.ts-wrap .ts-fields label span,
.joinTraining.ts-wrap .ts-loginbox label span{background:#fff!important;color:#111820!important;border-color:#cfd6dc!important}
.joinTraining.ts-wrap .ts-formmock button,
.joinTraining.ts-wrap .ts-loginbox button,
.joinTraining.ts-wrap .ts-result button{background:#c8ff45!important;color:#111820!important;border:1px solid #a6ce32!important;font-weight:950!important}
.joinTraining.ts-wrap .ts-arrow{background:#111820!important;color:#c8ff45!important;border-color:#c8ff45!important}
.joinTraining.ts-wrap .ts-controls{background:#fff!important;border:2px solid #d7dee4!important}
.joinTraining.ts-wrap .ts-dot{background:#aab4bd!important}
.joinTraining.ts-wrap .ts-dot.on{background:#111820!important;box-shadow:0 0 0 3px rgba(200,255,69,.65)!important}
.joinTraining.ts-wrap .ts-count{background:#111820!important;color:#fff!important}
.joinTraining.ts-wrap .ts-progress{background:#dfe5e9!important}
.joinTraining.ts-wrap .ts-progress i{background:#9bc21d!important}
.joinTraining.ts-wrap .joinTrainingNote{
  background:#111820!important;
  border:2px solid #c8ff45!important;
  color:#fff!important;
}
.joinTraining.ts-wrap .joinTrainingNote b{color:#c8ff45!important}
.joinTraining.ts-wrap .joinTrainingNote span{color:#fff!important}
@media(max-width:700px){
  .joinTraining.ts-wrap{margin:0 0 25px!important;padding:31px 9px 27px!important;border-top-width:4px!important;border-bottom-width:4px!important}
  .joinTraining.ts-wrap .joinSectionHead h2{font-size:28px!important;color:#111820!important}
  .joinTraining.ts-wrap .joinSectionHead p{color:#4b5563!important;font-size:12px!important}
  .joinTraining.ts-wrap .ts-slider{border-width:2px!important;box-shadow:0 15px 36px rgba(17,24,32,.16),0 0 0 4px #c8ff45!important}
  .joinTraining.ts-wrap .ts-guide h3{font-size:22px!important;color:#111820!important}
  .joinTraining.ts-wrap .ts-guide li span{font-size:13px!important;line-height:1.9!important;color:#26313e!important}
}
'''

js = r'''
/* WEBSAZA_TUTORIAL_FIRST_READABLE_V13 */
(function(){
  function moveTutorialFirst(){
    var training=document.getElementById('join-training');
    if(!training)return;
    var landing=document.querySelector('.joinLanding');
    var top=document.querySelector('.joinLanding .joinTop');
    var register=document.getElementById('join-register');
    if(!landing)return;

    /* First visible content: visual tutorial, immediately after the compact top bar. */
    if(top){
      if(top.nextElementSibling!==training) top.insertAdjacentElement('afterend',training);
    }else if(landing.firstElementChild!==training){
      landing.insertAdjacentElement('afterbegin',training);
    }

    /* Second content: registration panel. All marketing/explanatory sections follow it. */
    if(register && training.nextElementSibling!==register){
      training.insertAdjacentElement('afterend',register);
    }

    var slides=training.querySelectorAll('[data-ts-slide]');
    for(var i=0;i<slides.length;i++){
      slides[i].style.opacity='1';
      slides[i].style.filter='none';
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',moveTutorialFirst,{once:true});else moveTutorialFirst();
})();
'''

style=style_path.read_text(encoding='utf-8')
if 'WEBSAZA_TUTORIAL_FIRST_READABLE_V13' not in style:
    style_path.write_text(style+'\n'+css+'\n',encoding='utf-8')
app=app_path.read_text(encoding='utf-8')
if 'WEBSAZA_TUTORIAL_FIRST_READABLE_V13' not in app:
    app_path.write_text(app+'\n'+js+'\n',encoding='utf-8')
print('WEBSAZA_TUTORIAL_FIRST_READABLE_V13_OK')
