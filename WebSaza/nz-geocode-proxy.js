(()=>{
'use strict';

/* Keep reverse geocoding on our own origin. */
const nativeFetch=window.fetch.bind(window);
window.fetch=(input,init)=>{
  try{
    const raw=typeof input==='string'?input:(input&&input.url)||'';
    const u=new URL(raw,location.href);
    if(u.hostname==='nominatim.openstreetmap.org'&&u.pathname.includes('/reverse')){
      const local=new URL('/nz-geocode.php',location.origin);
      local.searchParams.set('lat',u.searchParams.get('lat')||'');
      local.searchParams.set('lon',u.searchParams.get('lon')||'');
      return nativeFetch(local.toString(),{...(init||{}),credentials:'same-origin',headers:{Accept:'application/json'}}).then(async r=>{
        if(!r.ok)return r;
        const data=await r.json();
        const body=JSON.stringify({display_name:data.display_name||'',address:data.address||{}});
        return new Response(body,{status:200,headers:{'Content-Type':'application/json; charset=utf-8'}});
      });
    }
  }catch(_){}
  return nativeFetch(input,init);
};

/*
 * The location picker historically asks unpkg for Leaflet at runtime, while
 * Niyazestan's CSP intentionally blocks that host. Rewrite only those Leaflet
 * requests before they enter the document. JS goes through jsDelivr (already
 * allowed by CSP); the essential Leaflet CSS is injected inline, which is also
 * allowed by the existing style policy. This keeps the map working without
 * weakening CSP for unrelated third-party resources.
 */
const leafletCss=`
.leaflet-pane,.leaflet-tile,.leaflet-marker-icon,.leaflet-marker-shadow,.leaflet-tile-container,.leaflet-pane>svg,.leaflet-pane>canvas,.leaflet-zoom-box,.leaflet-image-layer,.leaflet-layer{position:absolute;left:0;top:0}
.leaflet-container{overflow:hidden;background:#ddd;outline-offset:1px;-webkit-tap-highlight-color:transparent;font:12px/1.5 Arial,Helvetica,sans-serif}
.leaflet-tile,.leaflet-marker-icon,.leaflet-marker-shadow{-webkit-user-select:none;-moz-user-select:none;user-select:none;-webkit-user-drag:none}
.leaflet-container .leaflet-overlay-pane svg{max-width:none!important;max-height:none!important}
.leaflet-container .leaflet-tile-pane img,.leaflet-container .leaflet-tile{max-width:none!important;max-height:none!important;width:auto;padding:0}
.leaflet-tile{filter:inherit;visibility:hidden}.leaflet-tile-loaded{visibility:inherit}
.leaflet-pane{z-index:400}.leaflet-tile-pane{z-index:200}.leaflet-overlay-pane{z-index:400}.leaflet-shadow-pane{z-index:500}.leaflet-marker-pane{z-index:600}.leaflet-tooltip-pane{z-index:650}.leaflet-popup-pane{z-index:700}
.leaflet-map-pane canvas{z-index:100}.leaflet-map-pane svg{z-index:200}
.leaflet-control{position:relative;z-index:800;pointer-events:auto}.leaflet-top,.leaflet-bottom{position:absolute;z-index:1000;pointer-events:none}.leaflet-top{top:0}.leaflet-right{right:0}.leaflet-bottom{bottom:0}.leaflet-left{left:0}
.leaflet-control{float:left;clear:both}.leaflet-right .leaflet-control{float:right}.leaflet-top .leaflet-control{margin-top:10px}.leaflet-bottom .leaflet-control{margin-bottom:10px}.leaflet-left .leaflet-control{margin-left:10px}.leaflet-right .leaflet-control{margin-right:10px}
.leaflet-zoom-animated{transform-origin:0 0}.leaflet-zoom-anim .leaflet-zoom-animated{transition:transform .25s cubic-bezier(0,0,.25,1)}.leaflet-zoom-anim .leaflet-tile,.leaflet-pan-anim .leaflet-tile{transition:none}.leaflet-zoom-anim .leaflet-zoom-hide{visibility:hidden}
.leaflet-grab{cursor:grab}.leaflet-dragging .leaflet-grab{cursor:grabbing}.leaflet-marker-icon,.leaflet-marker-shadow,.leaflet-image-layer,.leaflet-pane>svg path,.leaflet-tile-container{pointer-events:none}
.leaflet-bar{box-shadow:0 1px 5px rgba(0,0,0,.65);border-radius:4px}.leaflet-bar a{background:#fff;border-bottom:1px solid #ccc;width:30px;height:30px;line-height:30px;display:block;text-align:center;text-decoration:none;color:#111}.leaflet-bar a:hover,.leaflet-bar a:focus{background:#f4f4f4}.leaflet-bar a:first-child{border-radius:4px 4px 0 0}.leaflet-bar a:last-child{border-radius:0 0 4px 4px;border-bottom:0}.leaflet-bar a.leaflet-disabled{cursor:default;background:#f4f4f4;color:#bbb}.leaflet-control-zoom-in,.leaflet-control-zoom-out{font:bold 22px 'Lucida Console',Monaco,monospace}
.leaflet-container a{color:#0078a8}.leaflet-container .leaflet-control-attribution{background:rgba(255,255,255,.82);margin:0}.leaflet-control-attribution{padding:0 5px;color:#333;line-height:1.4}.leaflet-control-attribution a{text-decoration:none}
.leaflet-container.leaflet-touch-zoom{touch-action:pan-x pan-y}.leaflet-container.leaflet-touch-drag{touch-action:pinch-zoom}.leaflet-container.leaflet-touch-drag.leaflet-touch-zoom{touch-action:none}
`;
function ensureLeafletCss(){
  if(document.getElementById('nzLeafletBaseCss'))return;
  const style=document.createElement('style');style.id='nzLeafletBaseCss';style.textContent=leafletCss;document.head.appendChild(style);
}
const nativeAppend=Node.prototype.appendChild;
Node.prototype.appendChild=function(node){
  try{
    if(node instanceof HTMLLinkElement&&/unpkg\.com\/leaflet@1\.9\.4\/dist\/leaflet\.css/i.test(node.href||'')){
      ensureLeafletCss();
      node.href='data:text/css,';
    }
    if(node instanceof HTMLScriptElement&&/unpkg\.com\/leaflet@1\.9\.4\/dist\/leaflet\.js/i.test(node.src||'')){
      node.src='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
    }
  }catch(_){}
  return nativeAppend.call(this,node);
};
})();
