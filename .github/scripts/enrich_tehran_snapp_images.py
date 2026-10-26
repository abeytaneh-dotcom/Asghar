from __future__ import annotations

import base64, gzip, hashlib, io, json, os, pathlib, re, sys, time, unicodedata, uuid, zipfile
from concurrent.futures import ThreadPoolExecutor, as_completed
from PIL import Image
from rapidfuzz import fuzz, process
from curl_cffi import requests

work=pathlib.Path(sys.argv[1]); work.mkdir(parents=True,exist_ok=True); (work/'images').mkdir(exist_ok=True); (work/'bundles').mkdir(exist_ok=True)
text=pathlib.Path('TehranSupermarket/pyramid_seed_1400_data.php').read_text('utf-8',errors='replace')
m=re.search(r"return\s+'([^']+)'\s*;",text,re.S)
if not m: raise SystemExit('seed payload not found')
payload=gzip.decompress(base64.b64decode(m.group(1))).decode('utf-8')
targets=[]
for line in payload.splitlines():
    if not line.strip(): continue
    r=json.loads(line)
    if r.get('code2') and r.get('name'): targets.append({'code2':str(r['code2']).strip(),'barcode':str(r.get('barcode') or '').strip(),'name':str(r['name']).strip()})
print('TARGETS',len(targets),flush=True)
tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون'}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s)).translate(tr).lower(); s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s); s=re.sub(r'[^0-9a-zآ-ی]+',' ',s); return ' '.join(s.split())
def toks(s): return [x for x in norm(s).split() if x not in stop]
def nums(s): return set(re.findall(r'\d+(?:[.,]\d+)?',norm(s)))
def barcode_like(v):
    x=re.sub(r'\D','',str(v or '')); return x if 8<=len(x)<=14 else ''
def walk(obj,path=''):
    if isinstance(obj,dict):
        for k,v in obj.items(): yield from walk(v,path+'/'+str(k))
    elif isinstance(obj,list):
        for i,v in enumerate(obj): yield from walk(v,path+f'/{i}')
    else: yield path,obj
def pick_name(p):
    for k in ('title','name','productTitle','product_title','variationTitle'):
        v=p.get(k)
        if isinstance(v,str) and v.strip(): return v.strip()
    return ''
def pick_image(p):
    cand=[]
    for path,v in walk(p):
        if not isinstance(v,str): continue
        u=v.replace('\\u002F','/').replace('\\/','/').strip()
        if u.startswith('//'): u='https:'+u
        if not u.startswith('http'): continue
        low=(path+' '+u).lower(); score=0
        if any(x in path.lower() for x in ['image','picture','photo','thumbnail','media']): score+=6
        if any(x in u.lower() for x in ['snapp','cdn','image']): score+=3
        if re.search(r'\.(?:jpe?g|png|webp)(?:\?|$)',u,re.I): score+=3
        if 'logo' in low or 'icon' in low or 'vendor' in path.lower(): score-=5
        if score>0: cand.append((score,u))
    cand.sort(reverse=True); return cand[0][1] if cand else ''
def pick_barcode(p):
    for path,v in walk(p):
        if re.search(r'(barcode|ean|gtin)',path,re.I):
            b=barcode_like(v)
            if b:return b
    return ''

BASE='https://svc.snapp.market'; PARAMS={'client':'PWA','deviceType':'PWA','appVersion':'1.403.1'}; lat,long=35.7219,51.3347
sess=requests.Session(impersonate='chrome')
headers={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9,en;q=0.8','Origin':'https://snapp.market','Referer':'https://snapp.market/'}
def req(method,path,params=None,json_body=None,auth=None):
    q=dict(PARAMS); q.update(params or {}); h=dict(headers)
    if auth:h['Authorization']='Bearer '+auth
    last=None
    for a in range(3):
        try:
            r=sess.request(method,BASE+path,params=q,json=json_body,headers=h,timeout=35)
            if r.status_code in (200,201): return r.json()
            last=f'HTTP {r.status_code} {r.text[:120]}'
        except Exception as e:last=repr(e)
        time.sleep(.7*(a+1))
    print('WARN',path,last,flush=True); return {}

tok=req('POST','/oauth2/default/token',json_body={'data':{'device_uid':str(uuid.uuid4()),'client_id':'snappfood_pwa','client_secret':'snappfood_pwa_secret','grant_type':'client_credentials','scope':'mobile_v2 mobile_v1 webview'}})
token=((tok.get('data') or {}).get('access_token') or '') if isinstance(tok,dict) else ''
print('TOKEN_OK',bool(token),flush=True)
vendors=req('GET','/express-vendor/general/vendors-list',{'lat':lat,'long':long,'sort':'highest_rating','page':0,'page_size':18,'is_home':'false','page_type':'vendor_list','extra-filter[vendor_collection]':-1})
items=((vendors.get('data') or {}).get('finalResult') or []) if isinstance(vendors,dict) else []
stores=[]
for it in items:
    d=(it.get('data') or {}) if isinstance(it,dict) else {}
    if it.get('type')=='VENDOR' and d.get('code'): stores.append((str(d['code']),str(d.get('title') or '')))
stores=stores[:10]
print('STORES',stores,flush=True)
if not stores: raise SystemExit('no SnappMarket stores')

sources={}; source_by_barcode={}; sample_keys=set(); calls=0
for si,(vendor,title) in enumerate(stores,1):
    cats=req('GET','/mobile/search/categories',{'lat':lat,'long':long,'vendorCode':vendor})
    cat_items=(cats.get('items') or []) if isinstance(cats,dict) else []
    top=[]
    for c in cat_items:
        cid=c.get('id')
        if cid: top.append((cid,None,str(c.get('title') or '')))
        for s in c.get('sub_categories') or []:
            if cid and s.get('id'): top.append((cid,s.get('id'),str(s.get('title') or '')))
    seen_cat=set()
    for cid,subid,ctitle in top[:45]:
        key=(cid,subid)
        if key in seen_cat:continue
        seen_cat.add(key)
        for page in range(0,4):
            p={'lat':lat,'long':long,'vendor_code':vendor,'category_id':cid,'page':page,'size':40}
            if subid:p['subcat_id']=subid
            body=req('GET','/express-search/product-list/vendor',p)
            data=body.get('data') or {} if isinstance(body,dict) else {}
            results=data.get('finalResult') or [] if isinstance(data,dict) else []
            if not results:break
            before=len(sources)
            for x in results:
                d=(x.get('data') or x) if isinstance(x,dict) else {}
                if not isinstance(d,dict):continue
                name=pick_name(d); img=pick_image(d)
                if not name or not img:continue
                n=norm(name)
                if not n:continue
                b=pick_barcode(d); row={'name':name,'norm':n,'image':img,'barcode':b,'source':'snapp.market'}
                sources.setdefault(n,row)
                if b:source_by_barcode.setdefault(b,row)
                sample_keys.update(d.keys())
            calls+=1
            if len(results)<15:break
            time.sleep(.08)
    print('STORE',si,vendor,title,'sources',len(sources),'calls',calls,flush=True)
print('SOURCE_PRODUCTS',len(sources),'BARCODES',len(source_by_barcode),'KEYS',sorted(sample_keys)[:60],flush=True)
if not sources: raise SystemExit('SnappMarket returned no product images')

src_rows=list(sources.values()); src_norms=[r['norm'] for r in src_rows]; exact={r['norm']:r for r in src_rows}; matches=[]
for t in targets:
    tb=barcode_like(t['barcode']); chosen=None; score=0.0
    if tb and tb in source_by_barcode:chosen=source_by_barcode[tb];score=100
    tn=norm(t['name'])
    if chosen is None and tn in exact:chosen=exact[tn];score=100
    if chosen is None and tn:
        hit=process.extractOne(tn,src_norms,scorer=fuzz.WRatio,score_cutoff=91)
        if hit:
            _,score,ix=hit; cand=src_rows[ix]; at=set(toks(tn));bt=set(toks(cand['norm']));over=at&bt;an=nums(tn);bn=nums(cand['norm']);numeric_ok=(not an or not bn or bool(an&bn)); min_overlap=1 if min(len(at),len(bt))<=2 else 2
            if numeric_ok and len(over)>=min_overlap and (score>=95 or (score>=92 and len(over)>=2)):chosen=cand
    if chosen:matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':chosen['name'],'url':chosen['image'],'source':chosen['source'],'score':round(float(score),1)})
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True); (work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')

imgdir=work/'images'
def dl(m):
    key=m['barcode'] or m['code2']; fname='snapp_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp'; path=imgdir/fname
    try:
        r=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://snapp.market/'},timeout=35)
        if r.status_code!=200 or len(r.content)<500:return None
        im=Image.open(io.BytesIO(r.content));im.load()
        if getattr(im,'is_animated',False):im.seek(0)
        if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
        im.thumbnail((700,700),Image.Resampling.LANCZOS);im.save(path,'WEBP',quality=82,method=6)
        if path.stat().st_size>130000:im.thumbnail((600,600),Image.Resampling.LANCZOS);im.save(path,'WEBP',quality=76,method=6)
        mm=dict(m);mm['file']=fname;mm['bytes']=path.stat().st_size;return mm
    except Exception:
        path.unlink(missing_ok=True);return None
good=[]
with ThreadPoolExecutor(max_workers=8) as ex:
    futs=[ex.submit(dl,m) for m in matches]
    for i,f in enumerate(as_completed(futs),1):
        r=f.result()
        if r:good.append(r)
        if i%250==0:print('DOWNLOAD',i,'ok',len(good),flush=True)
if not good:raise SystemExit('no SnappMarket images downloaded')
sizes=[x['bytes'] for x in good]; chunk=220
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk]; zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part:z.write(imgdir/row['file'],'images/'+row['file'])
summary={'targets':len(targets),'source_products':len(sources),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
