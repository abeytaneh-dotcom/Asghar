from __future__ import annotations

import hashlib, io, json, pathlib, random, re, sys, threading, time, unicodedata, zipfile
from concurrent.futures import ThreadPoolExecutor, as_completed
from PIL import Image
from rapidfuzz import fuzz
from curl_cffi import requests

work=pathlib.Path(sys.argv[1]); priority_file=pathlib.Path(sys.argv[2])
(work/'images').mkdir(parents=True,exist_ok=True); (work/'bundles').mkdir(parents=True,exist_ok=True)
report=json.loads(priority_file.read_text('utf-8'))
rows=(report.get('rows') or [])[:450]
print('PRIORITY_TARGETS',len(rows),flush=True)

tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','ساشه','کارتن','جعبه','اصل','اصلی'}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s or '')).translate(tr).lower()
    s=s.replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('بدون چربی','بدونچربی').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی')
    s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s)
    s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s)
    return ' '.join(s.split())
def toks(s): return [x for x in norm(s).split() if x not in stop and len(x)>1 and not re.fullmatch(r'\d+(?:\.\d+)?',x)]
def core(s): return ' '.join(toks(s))
def quantities(s):
    s=norm(s); out=[]
    patterns=[(r'(\d+(?:\.\d+)?)\s*(کیلوگرم|کیلو)',1000,'g'),(r'(\d+(?:\.\d+)?)\s*گرم',1,'g'),(r'(\d+(?:\.\d+)?)\s*لیتر',1000,'ml'),(r'(\d+(?:\.\d+)?)\s*(میلیلیتر|سیسی)',1,'ml')]
    for pat,mul,u in patterns:
        for x in re.findall(pat,s):
            v=x[0] if isinstance(x,tuple) else x
            try:out.append((u,float(v)*mul))
            except:pass
    return out
def qty_ok(a,b):
    qa,qb=quantities(a),quantities(b)
    if not qa or not qb:return True
    for ua,va in qa:
        for ub,vb in qb:
            if ua==ub and max(va,vb)>0 and abs(va-vb)/max(va,vb)<=.10:return True
    return False

def image_of(p):
    u=(((p.get('images') or {}).get('main') or {}).get('url') or [])
    if isinstance(u,list) and u:return str(u[0]).split('?',1)[0]
    if isinstance(u,str):return u.split('?',1)[0]
    return ''
def brand_of(p):return str(((p.get('data_layer') or {}).get('brand') or '')).strip()

def acceptable(target,cand):
    tn=norm(target); cn=norm(cand); tt=toks(target); ct=toks(cand); ts=set(tt); cs=set(ct)
    if not ts or not cs:return (False,0)
    if not qty_ok(target,cand):return (False,0)
    overlap=ts&cs; wr=fuzz.WRatio(tn,cn); tset=fuzz.token_set_ratio(core(target),core(cand))
    # short legacy names need every meaningful token represented; longer names need at least two
    if len(ts)<=2:
        ok=ts.issubset(cs) and wr>=82 and tset>=92
    else:
        ok=len(overlap)>=2 and wr>=84 and tset>=90
    # very high whole-string confidence can rescue a spelling variation, but still require one token
    if overlap and wr>=94 and tset>=94:ok=True
    score=.55*wr+.35*tset+10*(len(overlap)/max(1,len(ts)))
    return ok,score

BASE='https://api.digikala.com'
HEAD={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9,en;q=0.8','Origin':'https://www.digikala.com','Referer':'https://www.digikala.com/','x-web-client':'desktop','x-web-client-id':'web'}
local=threading.local(); rate_lock=threading.Lock(); next_time=[0.0]
def session():
    if not hasattr(local,'s'): local.s=requests.Session(impersonate='chrome')
    return local.s
def throttle():
    with rate_lock:
        now=time.monotonic(); wait=max(0,next_time[0]-now)
        next_time[0]=max(now,next_time[0])+.12
    if wait:time.sleep(wait)
def get_json(path,q):
    for a in range(3):
        try:
            throttle(); r=session().get(BASE+path,params={'q':q,'page':1,'sort':22},headers=HEAD,timeout=25)
            if r.status_code==200:
                body=r.json(); return body if isinstance(body,dict) else {}
        except Exception:pass
        time.sleep(.5*(a+1))
    return {}
def query_text(r):
    name=str(r.get('name') or '').strip(); brand=str(r.get('brand') or '').strip()
    q=name
    if brand and norm(brand) not in norm(name): q=f'{name} {brand}'
    return q[:100]
def find_one(r):
    target=str(r.get('name') or '').strip(); q=query_text(r)
    if not target or not q:return None
    best=None; bestscore=0.0
    for source,path in [('digikala-fresh','/fresh/v1/search/'),('digikala','/v1/search/')]:
        body=get_json(path,q); products=((body.get('data') or {}).get('products') or []) if isinstance(body,dict) else []
        for p in products[:12]:
            if not isinstance(p,dict):continue
            title=str(p.get('title_fa') or '').strip(); img=image_of(p)
            if not title or not img:continue
            ok,score=acceptable(target,title)
            if not ok:continue
            tb=str(r.get('brand') or '').strip(); cb=brand_of(p)
            if tb and cb:
                bsim=fuzz.ratio(norm(tb),norm(cb))
                if bsim<60 and norm(tb) not in norm(title):continue
                score+=min(8,bsim/12.5)
            if score>bestscore:
                bestscore=score; best={'code2':str(r.get('pyramid_code2') or ''),'barcode':str(r.get('barcode') or ''),'target_name':target,'source_name':title,'url':img,'source':source,'score':round(float(score),1),'stock':int(r.get('stock') or 0),'sold_qty':float(r.get('sold_qty') or 0),'order_count':int(r.get('order_count') or 0)}
        if best and bestscore>=92:break
    return best

matches=[]
with ThreadPoolExecutor(max_workers=3) as ex:
    futs=[ex.submit(find_one,r) for r in rows]
    for i,f in enumerate(as_completed(futs),1):
        v=f.result()
        if v and v['code2']:matches.append(v)
        if i%50==0:print('SEARCHED',i,'matches',len(matches),flush=True)
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True)
(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')

def download(m):
    key=m['barcode'] or m['code2']; fname='priority_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp'; path=work/'images'/fname
    try:
        r=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://www.digikala.com/'},timeout=30)
        if r.status_code!=200 or len(r.content)<500:return None
        im=Image.open(io.BytesIO(r.content));im.load()
        if getattr(im,'is_animated',False):im.seek(0)
        if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
        im.thumbnail((700,700),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=82,method=6)
        if path.stat().st_size>130000:im.thumbnail((600,600),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=76,method=6)
        mm=dict(m);mm['file']=fname;mm['bytes']=path.stat().st_size;return mm
    except Exception:
        path.unlink(missing_ok=True);return None

good=[]
with ThreadPoolExecutor(max_workers=8) as ex:
    fs=[ex.submit(download,m) for m in matches]
    for f in as_completed(fs):
        v=f.result()
        if v:good.append(v)
if not good:
    (work/'summary.json').write_text(json.dumps({'targets':len(rows),'matches':len(matches),'images_ready':0,'bundles':0},ensure_ascii=False),'utf-8')
    print('NO_IMAGES_READY',flush=True);sys.exit(0)
sizes=[x['bytes'] for x in good]; chunk=180
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk]; zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part:z.write(work/'images'/row['file'],'images/'+row['file'])
summary={'targets':len(rows),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
