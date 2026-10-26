from __future__ import annotations

import base64, gzip, hashlib, io, json, pathlib, re, sys, time, unicodedata, zipfile
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
    if r.get('code2') and r.get('name'):targets.append({'code2':str(r['code2']).strip(),'barcode':str(r.get('barcode') or '').strip(),'name':str(r['name']).strip()})
print('TARGETS',len(targets),flush=True)
tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم'}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s)).translate(tr).lower();s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s);s=re.sub(r'[^0-9a-zآ-ی]+',' ',s);return ' '.join(s.split())
def toks(s):return [x for x in norm(s).split() if x not in stop]
def nums(s):return set(re.findall(r'\d+(?:[.,]\d+)?',norm(s)))

sess=requests.Session(impersonate='chrome')
headers={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9,en;q=0.8','Origin':'https://www.digikala.com','Referer':'https://www.digikala.com/','x-web-client':'desktop','x-web-client-id':'web'}
BASE='https://api.digikala.com'
def get(path,params=None):
    last=None
    for a in range(3):
        try:
            r=sess.get(BASE+path,params=params or {},headers=headers,timeout=35)
            if r.status_code==200:
                body=r.json()
                if isinstance(body,dict) and int(body.get('status',200)) in (200,201):return body
                return body if isinstance(body,dict) else {}
            last=f'HTTP {r.status_code}'
        except Exception as e:last=repr(e)
        time.sleep(.7*(a+1))
    print('WARN',path,last,flush=True);return {}

def img_of(p):
    try:
        u=((p.get('images') or {}).get('main') or {}).get('url') or []
        if isinstance(u,list) and u:return str(u[0]).split('?',1)[0]
        if isinstance(u,str):return u.split('?',1)[0]
    except Exception:pass
    return ''

categories=['groceries','dairy','snacks','protein-foods','breakfast','beverages','warm-drinks','personal-hygiene','baby-and-mother','home-hygiene','dried-fruit-nuts','ready-made-canned-food','condiments','frozen-food','salts-and-pickles']
sources={}; total_calls=0
for ci,cat in enumerate(categories,1):
    total_pages=1
    for page in range(1,31):
        if page>total_pages:break
        body=get(f'/fresh/v1/categories/{cat}/search/',{'page':page,'sort':22,'has_selling_stock':1})
        data=body.get('data') or {} if isinstance(body,dict) else {}
        products=data.get('products') or [] if isinstance(data,dict) else []
        pager=data.get('pager') or {} if isinstance(data,dict) else {}
        try:total_pages=min(30,int(pager.get('total_pages') or 1))
        except Exception:total_pages=1
        if not products:break
        for p in products:
            if not isinstance(p,dict):continue
            name=str(p.get('title_fa') or '').strip();img=img_of(p)
            if not name or not img:continue
            n=norm(name)
            if not n:continue
            brand=str(((p.get('data_layer') or {}).get('brand') or '')).strip()
            sources.setdefault(n,{'name':name,'norm':n,'image':img,'brand':brand,'source':'digikala.com/supermarket'})
        total_calls+=1
        time.sleep(.15)
    print('CATEGORY',ci,cat,'sources',len(sources),'pages',total_pages,flush=True)
print('SOURCE_PRODUCTS',len(sources),'CALLS',total_calls,flush=True)
if not sources:raise SystemExit('Digikala supermarket returned no images')

src_rows=list(sources.values());src_norms=[r['norm'] for r in src_rows];exact={r['norm']:r for r in src_rows};matches=[]
for t in targets:
    tn=norm(t['name']);chosen=None;score=0.0
    if tn in exact:chosen=exact[tn];score=100
    if chosen is None and tn:
        hit=process.extractOne(tn,src_norms,scorer=fuzz.WRatio,score_cutoff=90)
        if hit:
            _,score,ix=hit;cand=src_rows[ix];at=set(toks(tn));bt=set(toks(cand['norm']));over=at&bt;an=nums(tn);bn=nums(cand['norm']);numeric_ok=(not an or not bn or bool(an&bn));min_overlap=1 if min(len(at),len(bt))<=2 else 2
            if numeric_ok and len(over)>=min_overlap and (score>=95 or (score>=92 and len(over)>=2) or (score>=90 and len(over)>=3)):chosen=cand
    if chosen:matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':chosen['name'],'url':chosen['image'],'source':chosen['source'],'score':round(float(score),1)})
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True);(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')

imgdir=work/'images'
def dl(m):
    key=m['barcode'] or m['code2'];fname='digi_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp';path=imgdir/fname
    try:
        r=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://www.digikala.com/'},timeout=35)
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
if not good:raise SystemExit('no Digikala images downloaded')
sizes=[x['bytes'] for x in good];chunk=220
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk];zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part:z.write(imgdir/row['file'],'images/'+row['file'])
summary={'targets':len(targets),'source_products':len(sources),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
