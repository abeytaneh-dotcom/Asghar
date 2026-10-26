from __future__ import annotations
import hashlib,html,io,json,pathlib,re,sys,threading,time,unicodedata,zipfile
from concurrent.futures import ThreadPoolExecutor,as_completed
from urllib.parse import urlparse
from PIL import Image
from rapidfuzz import fuzz
from curl_cffi import requests

work=pathlib.Path(sys.argv[1]);report=pathlib.Path(sys.argv[2]);(work/'images').mkdir(parents=True,exist_ok=True);(work/'bundles').mkdir(parents=True,exist_ok=True)
rows=json.loads(report.read_text('utf-8')).get('rows') or [];print('FALLBACK_TARGETS',len(rows),flush=True)
TR=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'});STOP={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','جعبه','کارتن','اصل','اصلی'};BLOCK={'pinterest.com','pinimg.com','instagram.com','facebook.com','youtube.com','ytimg.com','tiktok.com','freepik.com','shutterstock.com','gettyimages.com'}
def norm(s):
 s=unicodedata.normalize('NFKC',str(s or '')).translate(TR).lower().replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی');s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s);s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s);return ' '.join(s.split())
def toks(s):return [x for x in norm(s).split() if x not in STOP and len(x)>1 and not re.fullmatch(r'\d+(?:\.\d+)?',x)]
def bc(v):
 b=re.sub(r'\D','',str(v or ''));return b if 8<=len(b)<=14 and len(set(b))>2 else ''
def host(u):
 try:return (urlparse(u).hostname or '').lower().removeprefix('www.')
 except:return ''
def blocked(h):return any(h==d or h.endswith('.'+d) for d in BLOCK)
def good_title(target,title):
 a=toks(target);b=toks(title);sa=set(a);sb=set(b)
 if not sa or not sb:return False,0
 ov=sa&sb;wr=fuzz.WRatio(norm(target),norm(title));ts=fuzz.token_set_ratio(' '.join(a),' '.join(b))
 ok=(len(sa)<=2 and sa.issubset(sb) and wr>=82 and ts>=90) or (len(sa)>2 and len(ov)>=2 and wr>=82 and ts>=88) or (ov and wr>=95 and ts>=94)
 return ok,.55*wr+.35*ts+10*len(ov)/max(1,len(sa))
local=threading.local();lock=threading.Lock();next_at=[0.0]
def sess():
 if not hasattr(local,'s'):local.s=requests.Session(impersonate='chrome')
 return local.s
def pace():
 with lock:
  n=time.monotonic();w=max(0,next_at[0]-n);next_at[0]=max(n,next_at[0])+.025
 if w:time.sleep(w)
def search(q):
 try:
  pace();r=sess().get('https://www.bing.com/images/search',params={'q':q,'form':'HDRSC3','first':'1'},headers={'Accept-Language':'fa-IR,fa;q=0.9,en;q=0.7'},timeout=18)
  if r.status_code!=200:return []
 except:return []
 out=[]
 for raw in re.findall(r'\bm="([^"]+)"',r.text,re.I):
  try:m=json.loads(html.unescape(raw))
  except:continue
  u=str(m.get('murl') or '');p=str(m.get('purl') or '');t=str(m.get('t') or m.get('title') or '');h=host(p or u)
  if not u.startswith('http') or blocked(h) or any(x in u.lower() for x in ('logo','icon','sprite','banner')):continue
  out.append((u,p,t,h))
  if len(out)>=8:break
 return out
def one(r):
 name=str(r.get('name') or '').strip();brand=str(r.get('brand') or '').strip();qty=str(r.get('quantity_label') or '').strip();barcode=bc(r.get('barcode'));target=' '.join(x for x in (name,brand,qty) if x)
 q=(f'"{barcode}" {name}' if barcode else target)[:180];cands=search(q);best=None;bs=0
 for u,p,t,h in cands:
  digits=re.sub(r'\D','',t+' '+p)
  if barcode and barcode in digits:score=120
  else:
   ok,score=good_title(target,t)
   if not ok:continue
  if score>bs:best=(u,p,t,h);bs=score
 if not best:return None
 u,p,t,h=best;key=str(r.get('pyramid_code2') or r.get('barcode') or r.get('id'));fn='webfast_'+hashlib.sha1(key.encode()).hexdigest()[:22]+'.webp';dest=work/'images'/fn
 try:
  rr=requests.get(u,impersonate='chrome',headers={'Referer':p or 'https://www.bing.com/'},timeout=22)
  if rr.status_code!=200 or len(rr.content)<1000 or len(rr.content)>10_000_000:return None
  im=Image.open(io.BytesIO(rr.content));im.load()
  if getattr(im,'is_animated',False):im.seek(0)
  if im.width<180 or im.height<180:return None
  if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
  im.thumbnail((700,700),Image.Resampling.LANCZOS);im.save(dest,'WEBP',quality=79,method=4)
  if dest.stat().st_size>140000:im.thumbnail((600,600),Image.Resampling.LANCZOS);im.save(dest,'WEBP',quality=73,method=4)
  return {'code2':str(r.get('pyramid_code2') or ''),'barcode':str(r.get('barcode') or ''),'file':fn,'bytes':dest.stat().st_size,'source':'web-search|'+(h or 'unknown')+'|'+(p or u),'target_name':name,'source_title':t,'score':round(float(bs),1)}
 except Exception:dest.unlink(missing_ok=True);return None
found=[]
with ThreadPoolExecutor(max_workers=16) as ex:
 fs=[ex.submit(one,r) for r in rows]
 for i,f in enumerate(as_completed(fs),1):
  try:v=f.result()
  except:v=None
  if v:found.append(v)
  if i%250==0:print('SEARCHED',i,'real',len(found),flush=True)
print('REAL_FOUND',len(found),flush=True)
for bi in range(0,len(found),220):
 part=found[bi:bi+220];zp=work/'bundles'/f'image-bundle-{bi//220:03d}.zip'
 with zipfile.ZipFile(zp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=5) as z:
  z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
  for x in part:z.write(work/'images'/x['file'],'images/'+x['file'])
summary={'targets':len(rows),'real_found':len(found),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum((x['bytes'] for x in found),0)//max(1,len(found))};(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');(work/'matches.json').write_text(json.dumps(found,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
