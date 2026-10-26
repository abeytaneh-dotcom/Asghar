from __future__ import annotations
import hashlib,io,json,pathlib,re,sys,threading,time,unicodedata,uuid,zipfile
from concurrent.futures import ThreadPoolExecutor,as_completed
from PIL import Image
from rapidfuzz import fuzz
from curl_cffi import requests

work=pathlib.Path(sys.argv[1]); priority=pathlib.Path(sys.argv[2]); (work/'images').mkdir(parents=True,exist_ok=True); (work/'bundles').mkdir(parents=True,exist_ok=True)
rows=(json.loads(priority.read_text('utf-8')).get('rows') or [])[:350]
print('PRIORITY_TARGETS',len(rows),flush=True)
tr=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
stop={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','ساشه','کارتن','جعبه','اصل','اصلی'}
def norm(s):
 s=unicodedata.normalize('NFKC',str(s or '')).translate(tr).lower().replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('بدون چربی','بدونچربی').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی');s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s);s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s);return ' '.join(s.split())
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
def walk(obj,path=''):
 if isinstance(obj,dict):
  for k,v in obj.items():yield from walk(v,path+'/'+str(k))
 elif isinstance(obj,list):
  for i,v in enumerate(obj):yield from walk(v,path+'/'+str(i))
 else:yield path,obj
def pick_name(d):
 for k in ('title','name','productTitle','product_title','variationTitle'):
  v=d.get(k)
  if isinstance(v,str) and v.strip():return v.strip()
 return ''
def pick_brand(d):
 for k in ('brand','brandTitle','brand_title'):
  v=d.get(k)
  if isinstance(v,dict):v=v.get('title') or v.get('name')
  if isinstance(v,str) and v.strip():return v.strip()
 return ''
def pick_image(d):
 cand=[]
 for path,v in walk(d):
  if not isinstance(v,str):continue
  u=v.replace('\\/','/').replace('\\u002F','/').strip()
  if u.startswith('//'):u='https:'+u
  if not u.startswith('http'):continue
  low=(path+' '+u).lower();score=0
  if any(k in path.lower() for k in ('image','photo','picture','thumbnail','media')):score+=7
  if any(k in u.lower() for k in ('snapp','cdn','image')):score+=3
  if re.search(r'\.(?:jpe?g|png|webp)(?:\?|$)',u,re.I):score+=3
  if 'logo' in low or 'vendor' in path.lower() or 'icon' in low:score-=8
  if score>0:cand.append((score,u))
 return max(cand,default=(0,''))[1]
def candidate_dicts(body):
 out=[]; seen=set()
 try:
  data=(body.get('data') or {}).get('vendor_product_variations') or {}
  for v in data.get('items') or []:
   for p in v.get('products') or []:
    if isinstance(p,dict):out.append(p)
 except Exception:pass
 # fallback: any nested dict that resembles a product
 def rec(o):
  if isinstance(o,dict):
   name=pick_name(o);im=pick_image(o)
   if name and im:
    key=(name,im)
    if key not in seen:seen.add(key);out.append(o)
   for v in o.values():rec(v)
  elif isinstance(o,list):
   for v in o:rec(v)
 rec(body)
 return out

def acceptable(target,cand):
 ts=set(toks(target));cs=set(toks(cand));ov=ts&cs
 if not ts or not cs or not qty_ok(target,cand):return False,0
 wr=fuzz.WRatio(norm(target),norm(cand));tr=fuzz.token_set_ratio(core(target),core(cand))
 if len(ts)<=2:ok=ts.issubset(cs) and wr>=84 and tr>=93
 else:ok=len(ov)>=2 and wr>=85 and tr>=91
 if ov and wr>=95 and tr>=95:ok=True
 return ok,.52*wr+.38*tr+10*len(ov)/max(1,len(ts))

BASE='https://svc.snapp.market';COMMON={'client':'PWA','deviceType':'PWA','appVersion':'1.403.1'};LAT,LON=35.7219,51.3347
local=threading.local();rate=threading.Lock();next_at=[0.0]
def sess():
 if not hasattr(local,'s'):local.s=requests.Session(impersonate='chrome')
 return local.s
def throttle():
 with rate:
  now=time.monotonic();w=max(0,next_at[0]-now);next_at[0]=max(now,next_at[0])+.22
 if w:time.sleep(w)
def search(q):
 params={**COMMON,'query':q,'lat':LAT,'long':LON,'superType':'[4]','new_search':1,'page':0,'size':10}
 h={'Accept':'application/json','Accept-Language':'fa-IR,fa;q=0.9','Origin':'https://snapp.market','Referer':'https://snapp.market/'}
 for a in range(3):
  try:
   throttle();r=sess().get(BASE+'/mobile/v3/search',params=params,headers=h,timeout=25)
   if r.status_code==200:return r.json()
  except:pass
  time.sleep(.6*(a+1))
 return {}
def find_one(r):
 target=str(r.get('name') or '').strip();brand=str(r.get('brand') or '').strip();q=target
 if brand and norm(brand) not in norm(target):q=f'{target} {brand}'
 body=search(q[:100]);best=None;bs=0
 for p in candidate_dicts(body)[:80]:
  title=pick_name(p);im=pick_image(p)
  if not title or not im:continue
  ok,score=acceptable(target,title)
  if not ok:continue
  cb=pick_brand(p)
  if brand and cb:
   br=fuzz.ratio(norm(brand),norm(cb))
   if br<60 and norm(brand) not in norm(title):continue
   score+=min(8,br/12.5)
  if score>bs:best,bs={'code2':str(r.get('pyramid_code2') or ''),'barcode':str(r.get('barcode') or ''),'target_name':target,'source_name':title,'url':im,'source':'snapp.market/search','score':round(float(score),1),'stock':int(r.get('stock') or 0),'sold_qty':float(r.get('sold_qty') or 0)},score
 return best
matches=[]
with ThreadPoolExecutor(max_workers=2) as ex:
 fs=[ex.submit(find_one,r) for r in rows]
 for i,f in enumerate(as_completed(fs),1):
  v=f.result()
  if v and v['code2']:matches.append(v)
  if i%50==0:print('SEARCHED',i,'matches',len(matches),flush=True)
print('HIGH_CONFIDENCE_MATCHES',len(matches),flush=True);(work/'matches.json').write_text(json.dumps(matches,ensure_ascii=False,indent=2),'utf-8')
def dl(m):
 key=m['barcode'] or m['code2'];fn='snappsearch_'+hashlib.sha1(key.encode()).hexdigest()[:20]+'.webp';p=work/'images'/fn
 try:
  rr=requests.get(m['url'],impersonate='chrome',headers={'Referer':'https://snapp.market/'},timeout=30)
  if rr.status_code!=200 or len(rr.content)<500:return None
  im=Image.open(io.BytesIO(rr.content));im.load()
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
if not good:
 (work/'summary.json').write_text(json.dumps({'targets':len(rows),'matches':len(matches),'images_ready':0,'bundles':0},ensure_ascii=False),'utf-8');print('NO_IMAGES_READY',flush=True);sys.exit(0)
sizes=[x['bytes'] for x in good]
for bi in range(0,len(good),180):
 part=good[bi:bi+180];zp=work/'bundles'/f'image-bundle-{bi//180:03d}.zip'
 with zipfile.ZipFile(zp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
  z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
  for row in part:z.write(work/'images'/row['file'],'images/'+row['file'])
summary={'targets':len(rows),'matches':len(matches),'images_ready':len(good),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(sizes)//len(sizes),'max_bytes':max(sizes)};(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
