(()=>{
'use strict';
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
})();
