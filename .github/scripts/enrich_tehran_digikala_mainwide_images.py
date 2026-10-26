from __future__ import annotations
import base64,gzip,hashlib,io,json,pathlib,re,sys,time,unicodedata,zipfile
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor,as_completed
from PIL import Image
from rapidfuzz import fuzz
from curl_cffi import requests
work=pathlib.Path(sys.argv[1]);work.mkdir(parents=True,exist_ok=True);(work/'images').mkdir(exist_ok=True);(work/'bundles').mkdir(exist_ok=True)
text=pathlib.Path('TehranSupermarket/pyramid_seed_1400_data.php').read_text('utf-8',errors='replace');m=re.search(r"return\s+'([^']+)'\s*;",text,re.S)
payload=gzip.decompress(base64.b64decode(m.group(1))).decode('utf-8');targets=[]
for line in payload.splitlines():
 if line.strip():
  r=json.loads(line)
  if r.get('code2') and r.get('name'):targets.append({'code2':str(r['code2']).strip(),'barcode':str(r.get('barcode') or '').strip(),'name':str(r['name']).strip()})
print('TARGETS',len(targets),flush=True)
tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'});stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','ساشه','کارتن','جعبه'}
def norm(s):
 s=unicodedata.normalize('NFKC',str(s)).translate(tr).lower().replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('بدون چربی','بدونچربی').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی');s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s);s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s);return ' '.join(s.split())
def toks(s):return [x for x in norm(s).split() if x not in stop and len(x)>1 and not re.fullmatch(r'\d+(?:\.\d+)?',x)]
def core(s):return ' '.join(toks(s))
def qty(s):
 s=norm(s);out=[]
 for pat,mul,u in [(r'(\d+(?:\.\d+)?)\s*(کیلوگرم|کیلو)',1000,'g'),(r'(\d+(?:\.\d+)?)\s*گرم',1,'g'),(r'(\d+(?:\.\d+)?)\s*لیتر',1000,'ml'),(r'(\d+(?:\.\d+)?)\s*(میلیلیتر|سیسی)',1,'ml')]:
  for x in re.findall(pat,s):
   try:out.append((u,float(x[0] if isinstance(x,tuple) else x)*mul))
   except:pass
 return out
def qty_ok(a,b):
 qa,qb=qty(a),qty(b)
 if not qa or not qb:return True
 return any(ua==ub and max(va,vb)>0 and abs(va-vb)/max(va,vb)<=.10 for ua,va in qa for ub,vb in qb)
def img(p):
 u=(((p.get('images') or {}).get('main') or {}).get('url') or [])
 if isinstance(u,list) and u:return str(u[0]).split('?',1)[0]
 if isinstance(u,str):return u.split('?',1)[0]
 return ''
sess=requests.Session(impersonate='chrome');H={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9','Origin':'https://www.digikala.com','Referer':'https://www.digikala.com/','x-web-client':'desktop','x-web-client-id':'web'};BASE='https://api.digikala.com'
def get(path,params):
 for a in range(3):
  try:
   r=sess.get(BASE+path,params=params,headers=H,timeout=30)
   if r.status_code==200:return r.json()
  except:pass
  time.sleep(.5*(a+1))
 return {}
cats=['groceries','dairy','snacks','protein-foods','breakfast','beverages','warm-drinks','fruits-and-vegetables','personal-hygiene','baby-and-mother','home-hygiene','dried-fruit-nuts','ready-made-canned-food','condiments','frozen-food','salts-and-pickles'];sources={};calls=0
for ci,cat in enumerate(cats,1):
 total=1
 for page in range(1,46):
  if page>total:break
  b=get(f'/fresh/v1/categories/{cat}/search/',{'page':page,'sort':22});d=(b.get('data') or {}) if isinstance(b,dict) else {};ps=d.get('products') or [];pg=d.get('pager') or {}
  try:total=min(45,int(pg.get('total_pages') or 1))
  except:total=1
  if not ps:break
  for p in ps:
   if not isinstance(p,dict):continue
   name=str(p.get('title_fa') or '').strip();u=img(p)
   if name and u:
    n=norm(name);sources.setdefault(n,{'name':name,'norm':n,'core':core(name),'image':u,'brand':str(((p.get('data_layer') or {}).get('brand') or '')).strip(),'source':'digikala.com/supermarket'})
  calls+=1;time.sleep(.08)
 print('CATEGORY',ci,cat,'sources',len(sources),'pages',total,flush=True)
print('SOURCE_PRODUCTS',len(sources),'CALLS',calls,flush=True)
if not sources:raise SystemExit('no sources')
src=list(sources.values());idx=defaultdict(set);exact={};cex={}
for i,r in enumerate(src):
 exact[r['norm']]=i
 if r['core']:cex.setdefault(r['core'],i)
 for t in set(toks(r['name'])):idx[t].add(i)
def pick(name):
 n=norm(name);c=core(name);tt=toks(name);ts=set(tt)
 if n in exact:return src[exact[n]],100.0
 if c in cex:
  x=src[cex[c]]
  if qty_ok(name,x['name']):return x,99.0
 pool=set()
 for t in ts:pool.update(idx.get(t,()))
 best=None;bs=0
 for i in pool:
  x=src[i];ct=set(toks(x['name']));ov=ts&ct
  if not ov or not qty_ok(name,x['name']):continue
  wr=fuzz.WRatio(n,x['norm']);tr=fuzz.token_set_ratio(c,x['core']) if c and x['core'] else 0
  ok=(len(ts)<=2 and ts.issubset(ct) and wr>=84 and tr>=93) or (len(ts)>2 and len(ov)>=2 and wr>=85 and tr>=91) or (len(ov)>=1 and wr>=95 and tr>=95)
  score=.52*wr+.38*tr+10*len(ov)/max(1,len(ts))
  if ok and score>bs:best,bs=x,score
 return best,bs
matches=[]
for j,t in enumerate(targets,1):
 x,s=pick(t['name'])
 if x:matches.append({'code2':t['code2'],'barcode':t['barcode'],'target_name':t['name'],'source_name':x['name'],'url':x['image'],'source':x['source'],'score':round(float(s),1)})
 if j%2000==0:print('MATCH',j,len(matches),flush=True)
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True);(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')
def dl(m):
 key=m['barcode'] or m['code2'];fn='digiwide_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp';p=work/'images'/fn
 try:
  r=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://www.digikala.com/'},timeout=30)
  if r.status_code!=200 or len(r.content)<500:return None
  im=Image.open(io.BytesIO(r.content));im.load()
  if getattr(im,'is_animated',False):im.seek(0)
  if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
  im.thumbnail((700,700),Image.Resampling.LANCZOS);im.save(p,'WEBP',quality=82,method=6)
  if p.stat().st_size>130000:im.thumbnail((600,600),Image.Resampling.LANCZOS);im.save(p,'WEBP',quality=76,method=6)
  z=dict(m);z['file']=fn;z['bytes']=p.stat().st_size;return z
 except Exception:p.unlink(missing_ok=True);return None
good=[]
with ThreadPoolExecutor(max_workers=8) as ex:
 for f in as_completed([ex.submit(dl,m) for m in matches]):
  v=f.result()
  if v:good.append(v)
if not good:raise SystemExit('no images')
sizes=[x['bytes'] for x in good]
for bi in range(0,len(good),220):
 part=good[bi:bi+220];zp=work/'bundles'/f'image-bundle-{bi//220:03d}.zip'
 with zipfile.ZipFile(zp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
  z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
  for r in part:z.write(work/'images'/r['file'],'images/'+r['file'])
summary={'targets':len(targets),'source_products':len(sources),'calls':calls,'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)};(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
