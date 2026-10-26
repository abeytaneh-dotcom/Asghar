from __future__ import annotations

import base64, gzip, hashlib, html, io, json, pathlib, re, sys, time, unicodedata, urllib.parse, zipfile
from collections import deque
from concurrent.futures import ThreadPoolExecutor, as_completed

from bs4 import BeautifulSoup
from curl_cffi import requests
from PIL import Image
from rapidfuzz import fuzz, process

work=pathlib.Path(sys.argv[1]); work.mkdir(parents=True,exist_ok=True); (work/'images').mkdir(exist_ok=True); (work/'bundles').mkdir(exist_ok=True)
seed=pathlib.Path('TehranSupermarket/pyramid_seed_1400_data.php').read_text('utf-8',errors='replace')
m=re.search(r"return\s+'([^']+)'\s*;",seed,re.S)
if not m: raise SystemExit('seed payload not found')
payload=gzip.decompress(base64.b64decode(m.group(1))).decode('utf-8')
targets=[]
for line in payload.splitlines():
    if not line.strip(): continue
    r=json.loads(line); code=str(r.get('code2') or '').strip(); name=str(r.get('name') or '').strip(); bc=str(r.get('barcode') or '').strip()
    if code and name: targets.append({'code2':code,'barcode':bc,'name':name})
print('TARGETS',len(targets),flush=True)

tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','ارسال','فقط','تهران','جت'}
def norm(s):
    s=html.unescape(str(s)); s=unicodedata.normalize('NFKC',s).translate(tr).lower(); s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s); s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s); return ' '.join(s.split())
def toks(s): return [x for x in norm(s).split() if x not in stop and len(x)>1]
def numeric_tokens(s): return set(re.findall(r'\d+(?:\.\d+)?',norm(s)))
def weight_signature(s):
    n=norm(s); out=[]
    patterns=[(r'(\d+(?:\.\d+)?)\s*(?:کیلوگرم|کیلو|kg)',1000.0),(r'(\d+(?:\.\d+)?)\s*(?:گرم|gr|g)',1.0),(r'(\d+(?:\.\d+)?)\s*(?:لیتر|litre|liter|l)',1000.0),(r'(\d+(?:\.\d+)?)\s*(?:میلی\s*لیتر|ml)',1.0)]
    for pat,mul in patterns:
        for x in re.findall(pat,n,re.I):
            try: out.append(round(float(x)*mul,2))
            except: pass
    return set(out)
def numbers_compatible(a,b):
    wa,wb=weight_signature(a),weight_signature(b)
    if wa and wb: return bool(wa & wb)
    na,nb=numeric_tokens(a),numeric_tokens(b)
    # If both contain numbers, require at least one common numeric token. This protects pack size/weight mismatches.
    return not na or not nb or bool(na & nb)

BASE='https://www.modiseh.com'; sess=requests.Session(impersonate='chrome'); headers={'Accept-Language':'fa-IR,fa;q=0.9,en;q=0.6','Referer':BASE+'/supermarket/'}
def fetch(url):
    for a in range(3):
        try:
            r=sess.get(url,headers=headers,timeout=30)
            if r.status_code==200 and len(r.content)>1000: return r
        except Exception: pass
        time.sleep(.5*(a+1))
    return None
def canonical(href,base):
    u=urllib.parse.urljoin(base,href); p=urllib.parse.urlsplit(u)
    if p.netloc not in ('www.modiseh.com','modiseh.com') or not p.path.startswith('/supermarket/'): return ''
    q=urllib.parse.parse_qs(p.query); page=(q.get('page') or [''])[0]
    query=urllib.parse.urlencode({'page':page}) if page.isdigit() else ''
    return urllib.parse.urlunsplit(('https','www.modiseh.com',p.path,query,''))
def img_url(tag):
    vals=[]
    for key in ('src','data-src','data-original','data-lazy-src'):
        v=tag.get(key)
        if isinstance(v,str) and v.strip(): vals.append(v.strip())
    srcset=tag.get('srcset') or tag.get('data-srcset')
    if isinstance(srcset,str):
        for part in srcset.split(','):
            u=part.strip().split(' ')[0]
            if u: vals.append(u)
    for u in reversed(vals):
        u=html.unescape(u)
        if u.startswith('//'): u='https:'+u
        if u.startswith('/'): u=urllib.parse.urljoin(BASE,u)
        if 'cdn.modiseh.com' in u or re.search(r'\.(?:jpe?g|png|webp)(?:\?|$)',u,re.I): return u
    return ''

def extract_cards(soup):
    cards=[]
    # Strongest signal: product image alt + CDN URL.
    for im in soup.find_all('img'):
        u=img_url(im); alt=' '.join(str(im.get('alt') or '').split())
        if not u or 'cdn.modiseh.com' not in u or len(alt)<3: continue
        low=norm(alt)
        if low in {'مدیسه','تندیس جشنواره'} or any(x in low for x in ['لوگو','بنر']): continue
        name=alt
        # Prefer nearby product-link text when it is a sensible title.
        parent=im.find_parent('a',href=True)
        candidates=[]
        if parent:
            candidates.append(' '.join(parent.stripped_strings))
        node=im.parent
        for _ in range(4):
            if node is None: break
            for a in node.find_all('a',href=True,limit=6):
                href=str(a.get('href') or '')
                if '/product/' in href:
                    txt=' '.join(a.stripped_strings)
                    if 3<=len(txt)<=180: candidates.append(txt)
            node=node.parent
        for c in candidates:
            c=re.sub(r'\s+',' ',c).strip()
            if len(c)>=3 and len(c)<=180 and len(set(toks(c)) & set(toks(alt)))>=1:
                name=c; break
        cards.append({'name':name,'image':u,'source':'modiseh.com'})
    return cards

queue=deque([BASE+'/supermarket/']); seen=set(); sources={}; pages=0
while queue and pages<260:
    url=queue.popleft()
    if url in seen: continue
    seen.add(url); r=fetch(url)
    if not r: continue
    pages+=1; soup=BeautifulSoup(r.text,'html.parser')
    for card in extract_cards(soup):
        n=norm(card['name'])
        if n and len(toks(n))>=1: sources.setdefault(n,{**card,'norm':n})
    for a in soup.find_all('a',href=True):
        cu=canonical(str(a.get('href') or ''),url)
        if not cu or cu in seen: continue
        low=cu.lower()
        if any(x in low for x in ['/search','/account','/cart','/login','/product/']): continue
        # Stay within supermarket listing/category URLs and pagination.
        if cu.startswith(BASE+'/supermarket/') and cu not in queue: queue.append(cu)
    if pages%20==0: print('LISTING_PAGES',pages,'queue',len(queue),'source_products',len(sources),flush=True)
print('LISTING_SOURCE_PRODUCTS',len(sources),'PAGES',pages,flush=True)
if not sources: raise SystemExit('no Modiseh listing products extracted')

src_rows=list(sources.values()); src_norms=[r['norm'] for r in src_rows]; exact={r['norm']:r for r in src_rows}; matches=[]
for t in targets:
    tn=norm(t['name']); chosen=None; score=0.0
    if tn in exact: chosen=exact[tn]; score=100
    if chosen is None and tn:
        hit=process.extractOne(tn,src_norms,scorer=fuzz.WRatio,score_cutoff=90)
        if hit:
            _,score,ix=hit; cand=src_rows[ix]; at=set(toks(tn)); bt=set(toks(cand['norm'])); overlap=at&bt
            short=min(len(at),len(bt))<=2
            if numbers_compatible(tn,cand['norm']) and ((score>=96 and len(overlap)>=1) or (score>=93 and len(overlap)>=2) or (score>=91 and len(overlap)>=3) or (short and score>=97)):
                chosen=cand
    if chosen:
        matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':chosen['name'],'url':chosen['image'],'source':'modiseh.com','score':round(float(score),1)})
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True)
(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')
if not matches: raise SystemExit('no high-confidence Modiseh listing matches')

imgdir=work/'images'
def dl(m):
    key=m['barcode'] or m['code2']; fname='mod_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp'; path=imgdir/fname
    try:
        r=requests.get(m['url'],impersonate='chrome',headers={'Referer':BASE+'/'},timeout=35)
        if r.status_code!=200 or len(r.content)<500: return None
        im=Image.open(io.BytesIO(r.content)); im.load()
        if getattr(im,'is_animated',False): im.seek(0)
        if im.mode not in ('RGB','RGBA'): im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
        im.thumbnail((700,700),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=82,method=6)
        if path.stat().st_size>130000:
            im.thumbnail((600,600),Image.Resampling.LANCZOS); im.save(path,'WEBP',quality=76,method=6)
        mm=dict(m); mm['file']=fname; mm['bytes']=path.stat().st_size; return mm
    except Exception:
        path.unlink(missing_ok=True); return None

good=[]
with ThreadPoolExecutor(max_workers=8) as ex:
    futs=[ex.submit(dl,m) for m in matches]
    for i,f in enumerate(as_completed(futs),1):
        x=f.result()
        if x: good.append(x)
        if i%200==0: print('DOWNLOAD',i,'ok',len(good),flush=True)
if not good: raise SystemExit('no Modiseh listing images downloaded')
sizes=[x['bytes'] for x in good]; chunk=220
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk]; zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part: z.write(imgdir/row['file'],'images/'+row['file'])
summary={'targets':len(targets),'listing_pages':pages,'source_products':len(sources),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8'); print(json.dumps(summary,ensure_ascii=False),flush=True)
