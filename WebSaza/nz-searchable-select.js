(()=>{
'use strict';

const TARGETS='select[name="attributes[برند]"],select[name="attributes[برند/سازنده]"],select[name="attributes[مدل]"]';

function norm(value){
  return String(value||'')
    .toLowerCase()
    .replace(/ي/g,'ی').replace(/ك/g,'ک')
    .replace(/[ًٌٍَُِّْـ]/g,'')
    .replace(/\s+/g,' ').trim();
}

function isModel(select){return select.name==='attributes[مدل]'}
function emptyLabel(select){return isModel(select)?'انتخاب یا جستجوی مدل':'انتخاب یا جستجوی برند'}

function optionsOf(select){
  return [...select.options]
    .filter(o=>o.value!=='')
    .map(o=>({value:o.value,label:o.textContent.trim()}));
}

function selectedLabel(select){
  const o=select.options[select.selectedIndex];
  return o&&o.value?o.textContent.trim():'';
}

function injectStyles(){
  if(document.getElementById('nzSearchSelectStyle'))return;
  const style=document.createElement('style');
  style.id='nzSearchSelectStyle';
  style.textContent=`
    .nz-search-select{position:relative;width:100%;direction:rtl}
    .nz-search-select-row{position:relative;display:flex;align-items:center;width:100%}
    .nz-search-select-input{width:100%;min-height:46px;padding:0 14px 0 42px;border:1px solid #d7d9de;border-radius:12px;background:#fff;color:#171717;font:inherit;outline:none;box-sizing:border-box}
    .nz-search-select-input:focus{border-color:#111;box-shadow:0 0 0 2px rgba(0,0,0,.06)}
    .nz-search-select-input:disabled{background:#f5f5f5;color:#999;cursor:not-allowed}
    .nz-search-select-toggle{position:absolute;left:4px;top:4px;bottom:4px;width:36px;border:0;background:transparent;color:#555;font-size:18px;cursor:pointer;border-radius:9px}
    .nz-search-select-toggle:disabled{cursor:not-allowed;color:#aaa}
    .nz-search-select-menu{position:absolute;z-index:10050;top:calc(100% + 6px);right:0;left:0;max-height:280px;overflow:auto;background:#fff;border:1px solid #d7d9de;border-radius:12px;box-shadow:0 14px 35px rgba(0,0,0,.16);padding:5px;display:none}
    .nz-search-select.open .nz-search-select-menu{display:block}
    .nz-search-select-option{display:block;width:100%;border:0;background:#fff;text-align:right;padding:10px 11px;border-radius:9px;font:inherit;color:#222;cursor:pointer;line-height:1.5}
    .nz-search-select-option:hover,.nz-search-select-option.active{background:#f2f3f5}
    .nz-search-select-empty{padding:12px;color:#777;text-align:center;font-size:13px}
  `;
  document.head.appendChild(style);
}

function enhance(select){
  if(!select||select.dataset.nzSearchable==='1')return;
  select.dataset.nzSearchable='1';

  // Keep the real select in the form and available to the existing validation/model loader.
  // It stays before the visual combobox so existing field.querySelector('input,select') logic
  // continues to resolve the authoritative select rather than the search input.
  select.style.position='absolute';
  select.style.opacity='0';
  select.style.pointerEvents='none';
  select.style.width='1px';
  select.style.height='1px';
  select.style.margin='0';

  const wrap=document.createElement('div');
  wrap.className='nz-search-select';
  wrap.dataset.forName=select.name;
  wrap.innerHTML=`<div class="nz-search-select-row"><input type="text" class="nz-search-select-input" autocomplete="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false"><button type="button" class="nz-search-select-toggle" aria-label="نمایش گزینه‌ها">⌄</button></div><div class="nz-search-select-menu" role="listbox"></div>`;
  select.insertAdjacentElement('afterend',wrap);

  const input=wrap.querySelector('.nz-search-select-input');
  const toggle=wrap.querySelector('.nz-search-select-toggle');
  const menu=wrap.querySelector('.nz-search-select-menu');
  let active=-1;

  function syncState(){
    const disabled=select.disabled;
    input.disabled=disabled;
    toggle.disabled=disabled;
    input.placeholder=disabled&&isModel(select)?'در حال بارگذاری مدل‌ها…':emptyLabel(select);
    const current=selectedLabel(select);
    if(document.activeElement!==input||!wrap.classList.contains('open')) input.value=current;
  }

  function close(){
    wrap.classList.remove('open');
    input.setAttribute('aria-expanded','false');
    active=-1;
  }

  function open(){
    if(select.disabled)return;
    render(input.value, true);
    wrap.classList.add('open');
    input.setAttribute('aria-expanded','true');
  }

  function filtered(query){
    const all=optionsOf(select);
    const q=norm(query);
    if(!q)return all;
    const words=q.split(' ').filter(Boolean);
    const matches=all.filter(item=>{
      const hay=norm(item.label+' '+item.value);
      return words.every(w=>hay.includes(w));
    });
    return matches.sort((a,b)=>{
      const an=norm(a.label),bn=norm(b.label);
      const as=an.startsWith(q)?0:1,bs=bn.startsWith(q)?0:1;
      return as-bs||an.localeCompare(bn,'fa');
    });
  }

  function render(query, forceAll=false){
    const items=filtered(forceAll&&query===selectedLabel(select)?'':query);
    menu.innerHTML='';
    active=-1;
    if(!items.length){
      const empty=document.createElement('div');
      empty.className='nz-search-select-empty';
      empty.textContent='موردی پیدا نشد';
      menu.appendChild(empty);
      return;
    }
    items.forEach((item,index)=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.className='nz-search-select-option';
      btn.setAttribute('role','option');
      btn.dataset.value=item.value;
      btn.textContent=item.label;
      btn.addEventListener('mousedown',e=>e.preventDefault());
      btn.addEventListener('click',()=>choose(item.value,item.label));
      menu.appendChild(btn);
    });
  }

  function choose(value,label){
    select.value=value;
    input.value=label;
    select.dispatchEvent(new Event('input',{bubbles:true}));
    select.dispatchEvent(new Event('change',{bubbles:true}));
    close();
  }

  function move(step){
    const buttons=[...menu.querySelectorAll('.nz-search-select-option')];
    if(!buttons.length)return;
    if(active>=0)buttons[active]?.classList.remove('active');
    active=(active+step+buttons.length)%buttons.length;
    buttons[active].classList.add('active');
    buttons[active].scrollIntoView({block:'nearest'});
  }

  input.addEventListener('focus',()=>{
    // On focus show the whole list. As soon as the user types, it is filtered.
    render('',true);open();
  });
  input.addEventListener('click',()=>{render('',true);open()});
  input.addEventListener('input',()=>{
    render(input.value,false);
    wrap.classList.add('open');
    input.setAttribute('aria-expanded','true');
  });
  input.addEventListener('keydown',e=>{
    if(e.key==='ArrowDown'){e.preventDefault();if(!wrap.classList.contains('open'))open();move(1)}
    else if(e.key==='ArrowUp'){e.preventDefault();if(!wrap.classList.contains('open'))open();move(-1)}
    else if(e.key==='Enter'){
      const buttons=[...menu.querySelectorAll('.nz-search-select-option')];
      if(wrap.classList.contains('open')&&buttons.length){e.preventDefault();(buttons[active>=0?active:0]).click()}
    }else if(e.key==='Escape'){close();input.value=selectedLabel(select)}
  });
  input.addEventListener('blur',()=>{
    setTimeout(()=>{
      if(!wrap.contains(document.activeElement)){
        // Never allow arbitrary free text to become form data.
        input.value=selectedLabel(select);
        close();
      }
    },120);
  });
  toggle.addEventListener('mousedown',e=>e.preventDefault());
  toggle.addEventListener('click',()=>{
    if(select.disabled)return;
    if(wrap.classList.contains('open'))close();
    else{input.focus();render('',true);open()}
  });

  select.addEventListener('change',()=>{
    input.value=selectedLabel(select);
    if(isModel(select))render('',true);
  });

  const mo=new MutationObserver(()=>{
    syncState();
    if(isModel(select)&&wrap.classList.contains('open'))render(input.value,false);
  });
  mo.observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
  syncState();
}

function scan(){document.querySelectorAll(TARGETS).forEach(enhance)}
function init(){
  injectStyles();
  scan();
  const mo=new MutationObserver(scan);
  mo.observe(document.body,{childList:true,subtree:true});
  document.addEventListener('click',e=>{
    document.querySelectorAll('.nz-search-select.open').forEach(w=>{if(!w.contains(e.target))w.classList.remove('open')});
  });
}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
