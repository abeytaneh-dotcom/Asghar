from __future__ import annotations

import base64, gzip, hashlib, io, json, pathlib, re, sys, time, unicodedata, urllib.parse, zipfile
from collections import deque
from concurrent.futures import ThreadPoolExecutor, as_completed
from bs4 import BeautifulSoup
from curl_cffi import requests
from PIL import Image
from rapidfuzz import fuzz, process

work=pathlib.Path(sys.argv[1]);work.mkdir(parents=True,exist_ok=True);(work/'images').mkdir(exist_ok=True);(work/'bundles').mkdir(exist_ok=True)
text=pathlib.Path('TehranSupermarket/pyramid_seed_1400_data.php').read_text('utf-8',errors='replace')
m=re.search(r"return\s+'([^']+)'\s*;",text,re.S)
if not m:raise SystemExit('seed payload not found')
payload=gzip.decompress(base64.b64decode(m.group(1))).decode('utf-8')
targets=[];by_barcode={}
for line in payload.splitlines():
    if not line.strip():continue
    r=json.loads(line);code=str(r.get('code2') or '').strip();name=str(r.get('name') or '').strip();bc=re.sub(r'\D','',str(r.get('barcode') or ''))
    if code and name:
        row={'code2':code,'barcode':bc,'name':name};targets.append(row)
        if 8<=len(bc)<=14:by_barcode.setdefault(bc,row)
print('TARGETS',len(targets),'BARCODES',len(by_barcode),flush=True)

tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم'}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s)).translate(tr).lower();s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s);s=re.sub(r'[^0-9a-zآ-ی]+',' ',s);return ' '.join(s.split())
def toks(s):return [x for x in norm(s).split() if x not in stop]
def nums(s):return set(re.findall(r'\d+(?:[.,]\d+)?',norm(s)))

BASE='https://www.modiseh.com';sess=requests.Session(impersonate='chrome');hdr={'Accept-Language':'fa-IR,fa;q=0.9,en;q=0.7','Referer':BASE+'/supermarket/'}
def fetch(url,tries=3):
    last=None
    for a in range(tries):
        try:
            r=sess.get(url,headers=hdr,timeout=35)
            if r.status_code==200 and len(r.content)>500:return r
            last=f'HTTP {r.status_code}'
        except Exception as e:last=repr(e)
        time.sleep(.5*(a+1))
    return None

def clean_category_url(href,base):
    u=urllib.parse.urljoin(base,href);p=urllib.parse.urlsplit(u)
    if p.netloc not in ('www.modiseh.com','modiseh.com'):return ''
    if not p.path.startswith('/supermarket/'):return ''
    q=urllib.parse.parse_qs(p.query)
    page=(q.get('page') or [''])[0]
    query=urllib.parse.urlencode({'page':page}) if page.isdigit() else ''
    return urllib.parse.urlunsplit(('https','www.modiseh.com',p.path,query,''))
def clean_product_url(href,base):
    u=urllib.parse.urljoin(base,href);p=urllib.parse.urlsplit(u)
    if p.netloc not in ('www.modiseh.com','modiseh.com'):return ''
    if not re.match(r'^/product/[^/]+/?$',p.path):return ''
    return urllib.parse.urlunsplit(('https','www.modiseh.com',p.path,'',''))

q=deque([BASE+'/supermarket/']);seen=set();products=set();pages=0
while q and pages<320:
    url=q.popleft()
    if url in seen:continue
    seen.add(url);r=fetch(url,2)
    if not r:continue
    pages+=1;soup=BeautifulSoup(r.text,'html.parser')
    for a in soup.find_all('a',href=True):
        href=a.get('href','')
        pu=clean_product_url(href,url)
        if pu:products.add(pu);continue
        cu=clean_category_url(href,url)
        if cu and cu not in seen:
            # keep category depth reasonable and skip obvious account/cart/search URLs
            low=cu.lower()
            if not any(x in low for x in ['/search','/account','/cart','/login']):q.append(cu)
    if pages%25==0:print('CATEGORY_PAGES',pages,'queue',len(q),'products',len(products),flush=True)
print('DISCOVERED_PRODUCTS',len(products),'CATEGORY_PAGES',pages,flush=True)
if not products:raise SystemExit('no Modiseh products discovered')

def recursive_dict(obj):
    if isinstance(obj,dict):
        yield obj
        for v in obj.values():yield from recursive_dict(v)
    elif isinstance(obj,list):
        for v in obj:yield from recursive_dict(v)
def digits(v):
    x=re.sub(r'\D','',str(v or ''));return x if 8<=len(x)<=14 else ''
def parse_product(url):
    r=fetch(url,2)
    if not r:return None
    soup=BeautifulSoup(r.text,'html.parser');title='';h1=soup.find('h1')
    if h1:title=' '.join(h1.stripped_strings)
    image='';og=soup.find('meta',attrs={'property':'og:image'})
    if og:image=str(og.get('content') or '').strip()
    candidates=[];json_images=[]
    for sc in soup.find_all('script',attrs={'type':'application/ld+json'}):
        try:data=json.loads(sc.string or sc.get_text() or '{}')
        except Exception:continue
        for d in recursive_dict(data):
            if not title and isinstance(d.get('name'),str):title=d['name'].strip()
            for key in ('gtin13','gtin14','gtin12','gtin','barcode','sku'):
                b=digits(d.get(key))
                if b:candidates.append(b)
            im=d.get('image')
            if isinstance(im,str):json_images.append(im)
            elif isinstance(im,list):json_images.extend([x for x in im if isinstance(x,str)])
    txt=' '.join(soup.stripped_strings)
    for pat in [r'شناسه\s*کالا\s*[:：]?\s*(\d{8,14})',r'GTIN\s*[:：]?\s*(\d{8,14})']:
        candidates.extend(re.findall(pat,txt,re.I))
    candidates=list(dict.fromkeys(digits(x) for x in candidates if digits(x)))
    bc=next((x for x in candidates if x in by_barcode),'')
    if not image and json_images:image=json_images[0]
    if not image:
        for im in soup.find_all('img'):
            alt=str(im.get('alt') or '');src=str(im.get('src') or im.get('data-src') or '')
            if src and title and norm(title) in norm(alt):image=src;break
    if image.startswith('//'):image='https:'+image
    if image.startswith('/'):image=urllib.parse.urljoin(BASE,image)
    return {'url':url,'barcode':bc,'candidates':candidates[:5],'name':title,'image':image}

found=[];unmatched=[]
with ThreadPoolExecutor(max_workers=10) as ex:
    futs=[ex.submit(parse_product,u) for u in products]
    for i,f in enumerate(as_completed(futs),1):
        x=f.result()
        if x and x['image']:
            if x['barcode']:found.append(x)
            else:unmatched.append(x)
        if i%250==0:print('PRODUCT_PAGES',i,'exact',len(found),'unmatched',len(unmatched),flush=True)
print('EXACT_BARCODE_MATCHES',len(found),'UNMATCHED_WITH_IMAGE',len(unmatched),flush=True)

# Add only very high-confidence name matches for pages whose barcode is absent/not in our backup.
un_names=[norm(x['name']) for x in unmatched];name_matches=[]
for t in targets:
    if not un_names:break
    tn=norm(t['name']);hit=process.extractOne(tn,un_names,scorer=fuzz.WRatio,score_cutoff=94) if tn else None
    if not hit:continue
    _,score,ix=hit;cand=unmatched[ix];at=set(toks(tn));bt=set(toks(cand['name']));over=at&bt;an=nums(tn);bn=nums(cand['name']);numeric_ok=(not an or not bn or bool(an&bn))
    if numeric_ok and len(over)>=2 and score>=96:name_matches.append({'target':t,'page':cand,'score':score})

matches=[];seen_codes=set()
for x in found:
    t=by_barcode.get(x['barcode'])
    if not t or t['code2'] in seen_codes:continue
    seen_codes.add(t['code2']);matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':x['name'],'url':x['image'],'source':'modiseh.com','score':100})
for x in name_matches:
    t=x['target'];p=x['page']
    if t['code2'] in seen_codes:continue
    seen_codes.add(t['code2']);matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':p['name'],'url':p['image'],'source':'modiseh.com','score':round(float(x['score']),1)})
print('TOTAL_MATCHES',len(matches),'NAME_FALLBACK',len(name_matches),flush=True);(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')
if not matches:raise SystemExit('no Modiseh matches')

imgdir=work/'images'
def dl(m):
    key=m['barcode'] or m['code2'];fname='mod_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp';path=imgdir/fname
    try:
        u=m['url'].replace('&amp;','&');r=requests.get(u,impersonate='chrome',headers={'Referer':BASE+'/'},timeout=35)
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
        rr=f.result()
        if rr:good.append(rr)
        if i%250==0:print('DOWNLOAD',i,'ok',len(good),flush=True)
if not good:raise SystemExit('no Modiseh images downloaded')
sizes=[x['bytes'] for x in good];chunk=220
for bi in range(0,len(good),chunk):
    part=good[bi:bi+chunk];zpath=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zpath,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for row in part:z.write(imgdir/row['file'],'images/'+row['file'])
summary={'targets':len(targets),'category_pages':pages,'source_products':len(products),'exact_barcode_matches':len(found),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
