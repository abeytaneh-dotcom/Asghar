from __future__ import annotations

import base64, gzip, hashlib, io, json, math, pathlib, re, sys, time, unicodedata, zipfile
from collections import defaultdict, deque
from concurrent.futures import ThreadPoolExecutor, as_completed
from PIL import Image
from rapidfuzz import fuzz
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
    if r.get('code2') and r.get('name'):
        targets.append({'code2':str(r['code2']).strip(),'barcode':str(r.get('barcode') or '').strip(),'name':str(r['name']).strip()})
print('TARGETS',len(targets),flush=True)

tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','ساشه','کارتن','جعبه'}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s)).translate(tr).lower()
    s=s.replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('بدون چربی','بدونچربی').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی')
    s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s)
    s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s)
    return ' '.join(s.split())
def tokens(s): return [x for x in norm(s).split() if x not in stop and len(x)>1 and not re.fullmatch(r'\d+(?:\.\d+)?',x)]
def core(s): return ' '.join(tokens(s))
def qty(s):
    s=norm(s).replace('میلی لیتر','میلیلیتر').replace('میلیلیتر','میلیلیتر').replace('سی سی','سیسی')
    found=[]
    pats=[
      (r'(\d+(?:\.\d+)?)\s*(کیلوگرم|کیلو)',1000,'g'),(r'(\d+(?:\.\d+)?)\s*گرم',1,'g'),
      (r'(\d+(?:\.\d+)?)\s*لیتر',1000,'ml'),(r'(\d+(?:\.\d+)?)\s*(میلیلیتر|سیسی)',1,'ml')]
    for pat,mul,unit in pats:
        for x in re.findall(pat,s):
            v=x[0] if isinstance(x,tuple) else x
            try: found.append((unit,float(v)*mul))
            except: pass
    return found
def qty_ok(a,b):
    qa,qb=qty(a),qty(b)
    if not qa or not qb:return True
    for ua,va in qa:
        for ub,vb in qb:
            if ua==ub and max(va,vb)>0 and abs(va-vb)/max(va,vb)<=0.08:return True
    return False

sess=requests.Session(impersonate='chrome')
headers={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9,en;q=0.8','Origin':'https://www.digikala.com','Referer':'https://www.digikala.com/','x-web-client':'desktop','x-web-client-id':'web'}
BASE='https://api.digikala.com'
def get(path,params=None):
    last=None
    for a in range(4):
        try:
            r=sess.get(BASE+path,params=params or {},headers=headers,timeout=35)
            if r.status_code==200:
                body=r.json(); return body if isinstance(body,dict) else {}
            last=f'HTTP {r.status_code}'
        except Exception as e:last=repr(e)
        time.sleep(.6*(a+1))
    print('WARN',path,last,flush=True); return {}
def img_of(p):
    u=((p.get('images') or {}).get('main') or {}).get('url') or []
    if isinstance(u,list) and u:return str(u[0]).split('?',1)[0]
    if isinstance(u,str):return u.split('?',1)[0]
    return ''

def add_product(p,sources):
    if not isinstance(p,dict):return
    name=str(p.get('title_fa') or '').strip(); img=img_of(p)
    if not name or not img:return
    n=norm(name)
    if not n:return
    brand=str(((p.get('data_layer') or {}).get('brand') or '')).strip()
    row={'name':name,'norm':n,'core':core(name),'image':img,'brand':brand,'source':'digikala.com/supermarket'}
    old=sources.get(n)
    if old is None or (not old.get('brand') and brand): sources[n]=row

main_categories=['groceries','dairy','snacks','protein-foods','breakfast','beverages','warm-drinks','fruits-and-vegetables','personal-hygiene','baby-and-mother','home-hygiene','dried-fruit-nuts','ready-made-canned-food','condiments','frozen-food','salts-and-pickles']
queue=deque(main_categories); seen_codes=set(); sources={}; calls=0
while queue and len(seen_codes)<350:
    code=queue.popleft()
    if not code or code in seen_codes:continue
    seen_codes.add(code)
    first=get(f'/fresh/v1/categories/{code}/search/',{'page':1,'sort':22})
    data=first.get('data') or {} if isinstance(first,dict) else {}
    for p in data.get('products') or []: add_product(p,sources)
    calls+=1
    opts=((((data.get('filters') or {}).get('categories') or {}).get('options') or []))
    for o in opts:
        c=str(o.get('code') or '').strip()
        if c and c not in seen_codes: queue.append(c)
    pager=data.get('pager') or {}
    try: total_pages=min(45,int(pager.get('total_pages') or 1))
    except: total_pages=1
    for page in range(2,total_pages+1):
        body=get(f'/fresh/v1/categories/{code}/search/',{'page':page,'sort':22})
        d=body.get('data') or {} if isinstance(body,dict) else {}
        ps=d.get('products') or []
        if not ps:break
        for p in ps:add_product(p,sources)
        calls+=1
        time.sleep(.08)
    if len(seen_codes)%20==0: print('CATEGORY_CODES',len(seen_codes),'queued',len(queue),'sources',len(sources),'calls',calls,flush=True)
print('SOURCE_PRODUCTS',len(sources),'CATEGORY_CODES',len(seen_codes),'CALLS',calls,flush=True)
if not sources: raise SystemExit('deep Digikala scan returned no images')

src_rows=list(sources.values())
index=defaultdict(set)
exact={r['norm']:i for i,r in enumerate(src_rows)}
core_exact={}
for i,r in enumerate(src_rows):
    for tok in set(tokens(r['name'])): index[tok].add(i)
    if r['core']: core_exact.setdefault(r['core'],i)

def brand_ok(toks,cand):
    b=tokens(cand.get('brand') or '')
    return (not b) or all(x in toks for x in b)

def best_for(name):
    tn=norm(name); tc=core(name); tt=tokens(name); ts=set(tt)
    if tn in exact:return src_rows[exact[tn]],100.0
    if tc and tc in core_exact:
        c=src_rows[core_exact[tc]]
        if qty_ok(name,c['name']): return c,99.0
    pool=set()
    for tok in ts: pool.update(index.get(tok,()))
    if not pool:return None,0.0
    if len(pool)>1800:
        # keep candidates sharing at least two meaningful tokens
        pool={i for i in pool if len(ts & set(tokens(src_rows[i]['name'])))>=2}
    best=None; bestscore=0.0
    for i in pool:
        c=src_rows[i]; ct=set(tokens(c['name'])); over=ts&ct
        if not over:continue
        if not qty_ok(name,c['name']):continue
        wr=fuzz.WRatio(tn,c['norm']); tset=fuzz.token_set_ratio(tc,c['core']) if tc and c['core'] else 0
        j=len(over)/max(1,len(ts|ct))
        b_ok=brand_ok(ts,c)
        score=0.45*wr+0.35*tset+20*j
        accept=False
        if b_ok and len(over)>=2 and tset>=90 and wr>=84:accept=True
        if len(over)>=3 and tset>=93 and wr>=82:accept=True
        if len(ts)<=2 and ts.issubset(ct) and tset>=96 and wr>=90:accept=True
        if accept and score>bestscore:best,bestscore=c,score
    return best,bestscore

matches=[]
for i,t in enumerate(targets,1):
    chosen,score=best_for(t['name'])
    if chosen:
        matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':chosen['name'],'url':chosen['image'],'source':chosen['source'],'score':round(float(score),1)})
    if i%1500==0:print('MATCH_PROGRESS',i,'matches',len(matches),flush=True)
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True)
(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')

imgdir=work/'images'
def dl(m):
    key=m['barcode'] or m['code2'];fname='digideep_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp';path=imgdir/fname
    try:
        r=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://www.digikala.com/'},timeout=35)
        if r.status_code!=200 or len(r.content)<500:return None
        im=Image.open(io.BytesIO(r.content));im.load()
        if getattr(im,'is_animated',False):im.seek(0)
        if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
        im.thumbnail((700,700),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=82,method=6)
        if path.stat().st_size>130000: im.thumbnail((600,600),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=76,method=6)
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
if not good: raise SystemExit('no deep Digikala images downloaded')
sizes=[x['bytes'] for x in good];chunk=220
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk]; zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part:z.write(imgdir/row['file'],'images/'+row['file'])
summary={'targets':len(targets),'source_products':len(sources),'category_codes':len(seen_codes),'calls':calls,'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
