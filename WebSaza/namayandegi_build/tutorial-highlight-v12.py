from pathlib import Path

support = Path(__file__).resolve().parent
root = support.parent / 'namayandegi'
style_path = root / 'style.css'
app_path = root / 'app.js'

css = r'''
/* WEBSAZA_TUTORIAL_HIGHLIGHT_V12 */
.joinTraining.ts-wrap{
  max-width:none!important;
  width:100%!important;
  margin:16px 0 54px!important;
  padding:28px max(14px,calc((100vw - 1180px)/2)) 36px!important;
  background:
    radial-gradient(circle at 12% 18%,rgba(200,255,69,.16),transparent 28%),
    radial-gradient(circle at 88% 82%,rgba(111,144,255,.12),transparent 30%),
    linear-gradient(145deg,#0a0f16 0%,#111a25 55%,#0b1119 100%)!important;
  border-top:3px solid #c8ff45!important;
  border-bottom:3px solid #c8ff45!important;
  box-shadow:0 22px 70px rgba(8,14,22,.24),inset 0 1px rgba(255,255,255,.04)!important;
  position:relative;
  isolation:isolate;
}
.joinTraining.ts-wrap:before{
  content:'راهنمای شروع سریع';
  position:absolute;
  top:-17px;
  right:max(20px,calc((100vw - 1136px)/2));
  background:#c8ff45;
  color:#101720;
  border:4px solid #f4f6f8;
  border-radius:999px;
  padding:8px 16px;
  font-size:12px;
  font-weight:950;
  letter-spacing:-.2px;
  box-shadow:0 8px 24px rgba(0,0,0,.18);
  z-index:4;
}
.joinTraining.ts-wrap .joinSectionHead{max-width:760px!important;margin:14px auto 24px!important}.joinTraining.ts-wrap .joinSectionHead>span{background:rgba(200,255,69,.1)!important;border-color:rgba(200,255,69,.35)!important;color:#c8ff45!important}.joinTraining.ts-wrap .joinSectionHead h2{color:#fff!important;font-size:clamp(28px,4vw,43px)!important;margin:12px 0 8px!important}.joinTraining.ts-wrap .joinSectionHead p{color:#aeb9c6!important;font-size:13px!important}
.joinTraining.ts-wrap .ts-slider{
  max-width:1120px!important;
  margin:0 auto!important;
  background:#f9fbfc!important;
  border:2px solid #c8ff45!important;
  border-radius:30px!important;
  padding:14px!important;
  box-shadow:0 30px 80px rgba(0,0,0,.34),0 0 0 7px rgba(200,255,69,.07)!important;
  overflow:visible!important;
  position:relative!important;
}
.joinTraining.ts-wrap .ts-viewport{border-radius:22px!important;overflow:hidden!important;background:#eef2f5!important;border:1px solid #dce3e8!important}.joinTraining.ts-wrap .ts-track{transition:transform .68s cubic-bezier(.22,.72,.2,1)!important;will-change:transform}.joinTraining.ts-wrap .ts-slide{transition:opacity .36s ease,transform .55s cubic-bezier(.22,.72,.2,1)!important;opacity:.48;transform:scale(.955)}.joinTraining.ts-wrap .ts-slide.is-active{opacity:1;transform:scale(1)}
.joinTraining.ts-wrap .ts-stage{background:linear-gradient(145deg,#eef3f5,#fff)!important}.joinTraining.ts-wrap .ts-guide{background:#fff!important;border-top:1px solid #dfe5e9!important}.joinTraining.ts-wrap .ts-tag{background:#111a25!important;color:#c8ff45!important;border:1px solid rgba(200,255,69,.26)!important;box-shadow:0 6px 18px rgba(10,16,24,.12)!important}.joinTraining.ts-wrap .ts-guide h3{color:#101720!important}.joinTraining.ts-wrap .ts-guide li i{background:#c8ff45!important;color:#101720!important;box-shadow:0 4px 12px rgba(138,175,41,.2)!important}
.joinTraining.ts-wrap .ts-arrow{width:52px!important;height:52px!important;border-radius:16px!important;background:#111a25!important;color:#c8ff45!important;border:2px solid #c8ff45!important;box-shadow:0 10px 24px rgba(0,0,0,.2)!important;font-size:32px!important;transition:transform .18s ease,background .18s ease!important}.joinTraining.ts-wrap .ts-arrow:hover{transform:translateY(-50%) scale(1.06)!important;background:#1a2634!important}.joinTraining.ts-wrap .ts-controls{margin-top:14px!important;background:#eef2f4!important;border:1px solid #d8e0e5!important;border-radius:16px!important;padding:10px 13px!important}.joinTraining.ts-wrap .ts-dot{width:10px!important;height:10px!important;background:#aeb7bf!important;transition:width .25s ease,background .25s ease!important}.joinTraining.ts-wrap .ts-dot.on{width:30px!important;border-radius:999px!important;background:#111a25!important}.joinTraining.ts-wrap .ts-count{background:#111a25!important;color:#fff!important;border-radius:999px!important;padding:7px 12px!important}.joinTraining.ts-wrap .ts-count b{color:#c8ff45!important}.joinTraining.ts-wrap .ts-progress{height:5px!important;background:#dfe5e9!important;border-radius:999px!important;overflow:hidden!important;margin-top:10px!important}.joinTraining.ts-wrap .ts-progress i{background:linear-gradient(90deg,#9cc51c,#c8ff45)!important;box-shadow:0 0 14px rgba(200,255,69,.5)!important}.joinTraining.ts-wrap .joinTrainingNote{max-width:1120px!important;margin:16px auto 0!important;background:rgba(255,255,255,.06)!important;border:1px solid rgba(255,255,255,.12)!important;color:#fff!important;box-shadow:none!important}.joinTraining.ts-wrap .joinTrainingNote b{color:#c8ff45!important}.joinTraining.ts-wrap .joinTrainingNote span{color:#c5ced8!important}
@media(max-width:700px){
  .joinTraining.ts-wrap{margin:10px 0 34px!important;padding:31px 9px 25px!important;border-top-width:2px!important;border-bottom-width:2px!important}
  .joinTraining.ts-wrap:before{top:-16px;right:14px;font-size:10px;padding:7px 12px;border-width:3px}
  .joinTraining.ts-wrap .joinSectionHead{margin:8px auto 17px!important;padding:0 8px}.joinTraining.ts-wrap .joinSectionHead h2{font-size:27px!important}.joinTraining.ts-wrap .joinSectionHead p{font-size:11px!important;line-height:1.9!important}
  .joinTraining.ts-wrap .ts-slider{border-radius:20px!important;padding:7px!important;border-width:2px!important;box-shadow:0 18px 45px rgba(0,0,0,.3),0 0 0 4px rgba(200,255,69,.06)!important}.joinTraining.ts-wrap .ts-viewport{border-radius:15px!important}.joinTraining.ts-wrap .ts-arrow{width:43px!important;height:43px!important;border-radius:13px!important;font-size:27px!important}.joinTraining.ts-wrap .ts-controls{padding:8px 10px!important;border-radius:13px!important}.joinTraining.ts-wrap .ts-dot{width:8px!important;height:8px!important}.joinTraining.ts-wrap .ts-dot.on{width:24px!important}.joinTraining.ts-wrap .joinTrainingNote{margin-top:11px!important;border-radius:13px!important;padding:13px!important}
}
'''

js = r'''
/* WEBSAZA_TUTORIAL_HIGHLIGHT_V12 */
(function(){
  function initTutorialHighlight(){
    var training=document.getElementById('join-training');
    if(!training)return;
    var hero=document.querySelector('.joinLanding .joinHero');
    if(hero && hero.nextElementSibling!==training) hero.insertAdjacentElement('afterend',training);
    var slider=document.getElementById('tutorialSlider');
    if(!slider)return;
    var slides=[].slice.call(slider.querySelectorAll('[data-ts-slide]'));
    var dots=[].slice.call(slider.querySelectorAll('.ts-dot'));
    function syncActive(){
      var active=0;
      dots.forEach(function(d,i){if(d.classList.contains('on'))active=i;});
      slides.forEach(function(s,i){s.classList.toggle('is-active',i===active);});
    }
    syncActive();
    if(window.MutationObserver){
      var observer=new MutationObserver(syncActive);
      dots.forEach(function(d){observer.observe(d,{attributes:true,attributeFilter:['class']});});
    }
    slider.addEventListener('pointerdown',function(){slider.classList.add('ts-manual');},{passive:true});
    slider.addEventListener('pointerup',function(){setTimeout(function(){slider.classList.remove('ts-manual');},450);},{passive:true});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initTutorialHighlight,{once:true});else initTutorialHighlight();
})();
'''

style = style_path.read_text(encoding='utf-8')
if 'WEBSAZA_TUTORIAL_HIGHLIGHT_V12' not in style:
    style_path.write_text(style + '\n' + css + '\n', encoding='utf-8')

app = app_path.read_text(encoding='utf-8')
if 'WEBSAZA_TUTORIAL_HIGHLIGHT_V12' not in app:
    app_path.write_text(app + '\n' + js + '\n', encoding='utf-8')

print('WEBSAZA_TUTORIAL_HIGHLIGHT_V12_OK')
