from __future__ import annotations

import hashlib, html, io, json, math, pathlib, random, re, sys, threading, time, unicodedata, urllib.parse, zipfile
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urlparse

from PIL import Image, ImageDraw, ImageFont, ImageFilter
from rapidfuzz import fuzz
from curl_cffi import requests
import arabic_reshaper
from bidi.algorithm import get_display

work=pathlib.Path(sys.argv[1]); report_path=pathlib.Path(sys.argv[2])
(work/'images').mkdir(parents=True,exist_ok=True); (work/'bundles').mkdir(parents=True,exist_ok=True)
report=json.loads(report_path.read_text('utf-8')); targets=report.get('rows') or []
print('MISSING_TARGETS',len(targets),flush=True)

TR=str.maketrans({'ي':'ی','ى':'ی','ك':'ک','ة':'ه','ۀ':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'})
STOP={'محصول','بسته','عددی','عدد','گرمی','گرم','کیلوگرم','کیلو','میلی','لیتر','مدل','نوع','با','بدون','حجم','وزن','قوطی','بطری','پاکت','شیشه','ساشه','کارتن','جعبه','اصل','اصلی','تعداد','تایی','تای'}
BLOCKED_DOMAINS={'pinterest.com','pinimg.com','instagram.com','facebook.com','fbcdn.net','youtube.com','ytimg.com','tiktok.com','twitter.com','x.com','alamy.com','shutterstock.com','gettyimages.com','dreamstime.com','123rf.com','freepik.com'}

def norm(s):
    s=unicodedata.normalize('NFKC',str(s or '')).translate(TR).lower()
    s=s.replace('پر چرب','پرچرب').replace('کم چرب','کمچرب').replace('بدون چربی','بدونچربی').replace('میلی لیتر','میلیلیتر').replace('سی سی','سیسی')
    s=re.sub(r'[\u064b-\u065f\u0670\u200c\u200d\ufeff]',' ',s)
    s=re.sub(r'[^0-9a-zآ-ی.]+',' ',s)
    return ' '.join(s.split())
def toks(s):
    return [x for x in norm(s).split() if x not in STOP and len(x)>1 and not re.fullmatch(r'\d+(?:\.\d+)?',x)]
def valid_barcode(v):
    b=re.sub(r'\D','',str(v or ''))
    if not 8<=len(b)<=14:return ''
    if len(set(b))<=2:return ''
    if b in {'12345678','1234567890123','0000000000000','1111111111111'}:return ''
    return b

def quantities(s):
    s=norm(s);out=[]
    pats=[(r'(\d+(?:\.\d+)?)\s*(کیلوگرم|کیلو)',1000,'g'),(r'(\d+(?:\.\d+)?)\s*گرم',1,'g'),(r'(\d+(?:\.\d+)?)\s*لیتر',1000,'ml'),(r'(\d+(?:\.\d+)?)\s*(میلیلیتر|سیسی)',1,'ml')]
    for pat,mul,u in pats:
        for x in re.findall(pat,s):
            v=x[0] if isinstance(x,tuple) else x
            try:out.append((u,float(v)*mul))
            except:pass
    return out

def qty_conflict(a,b):
    qa,qb=quantities(a),quantities(b)
    if not qa or not qb:return False
    comparable=[(va,vb) for ua,va in qa for ub,vb in qb if ua==ub]
    if not comparable:return False
    return all(abs(va-vb)/max(va,vb,1)>.18 for va,vb in comparable)

def host_of(u):
    try:
        h=(urlparse(u).hostname or '').lower().removeprefix('www.')
        return h
    except:return ''
def blocked_host(h):
    return any(h==d or h.endswith('.'+d) for d in BLOCKED_DOMAINS)

def meaningful_match(target,title):
    if not target or not title:return False,0.0
    if qty_conflict(target,title):return False,0.0
    tt=toks(target);ct=toks(title);ts=set(tt);cs=set(ct)
    if not ts or not cs:return False,0.0
    overlap=ts&cs
    wr=fuzz.WRatio(norm(target),norm(title));tr=fuzz.token_set_ratio(' '.join(tt),' '.join(ct))
    if len(ts)<=2:
        ok=ts.issubset(cs) and wr>=82 and tr>=90
    else:
        ok=len(overlap)>=2 and wr>=82 and tr>=88
    if overlap and wr>=94 and tr>=94:ok=True
    score=.52*wr+.38*tr+10*len(overlap)/max(1,len(ts))
    return ok,score

local=threading.local(); pace_lock=threading.Lock(); next_slot=[0.0]
def session():
    if not hasattr(local,'s'):local.s=requests.Session(impersonate='chrome')
    return local.s
def pace():
    with pace_lock:
        now=time.monotonic();wait=max(0,next_slot[0]-now);next_slot[0]=max(now,next_slot[0])+.08
    if wait:time.sleep(wait)

def bing_candidates(q):
    url='https://www.bing.com/images/search'
    params={'q':q,'form':'HDRSC3','first':'1','tsc':'ImageBasicHover'}
    hdr={'Accept-Language':'fa-IR,fa;q=0.9,en-US;q=0.7,en;q=0.6','Referer':'https://www.bing.com/'}
    try:
        pace();r=session().get(url,params=params,headers=hdr,timeout=22)
        if r.status_code!=200:return []
        text=r.text
    except Exception:return []
    out=[]
    for raw in re.findall(r'<a[^>]+class="[^"]*\biusc\b[^"]*"[^>]+m="([^"]+)"',text,re.I):
        try:
            meta=json.loads(html.unescape(raw))
        except Exception:continue
        murl=str(meta.get('murl') or '').strip();purl=str(meta.get('purl') or '').strip();title=str(meta.get('t') or meta.get('title') or '').strip()
        h=host_of(purl or murl)
        if not murl.startswith('http') or blocked_host(h):continue
        if any(x in murl.lower() for x in ('logo','icon','sprite','banner','avatar')):continue
        out.append({'murl':murl,'purl':purl,'title':title,'host':h})
        if len(out)>=12:break
    if not out:
        # Bing occasionally emits m data with attributes reordered.
        for raw in re.findall(r'\bm="([^"]+)"',text,re.I):
            try:meta=json.loads(html.unescape(raw))
            except Exception:continue
            murl=str(meta.get('murl') or '').strip();purl=str(meta.get('purl') or '').strip();title=str(meta.get('t') or meta.get('title') or '').strip();h=host_of(purl or murl)
            if not murl.startswith('http') or blocked_host(h):continue
            out.append({'murl':murl,'purl':purl,'title':title,'host':h})
            if len(out)>=12:break
    return out

def choose_candidate(row):
    name=str(row.get('name') or '').strip();brand=str(row.get('brand') or '').strip();qty=str(row.get('quantity_label') or '').strip();bc=valid_barcode(row.get('barcode'))
    if not name:return None
    if bc:
        q=f'"{bc}" {name}'
    else:
        q=' '.join(x for x in (name,brand,qty) if x)
    cands=bing_candidates(q[:180])
    best=None;bs=0.0
    for c in cands:
        title=c['title']
        # An exact barcode in the source page/title is strong evidence even when the title is sparse.
        blob=(title+' '+c['purl']).lower()
        if bc and bc in re.sub(r'\D','',blob):
            score=120.0
            if qty_conflict(name+' '+qty,title):continue
            if score>bs:best,bs=c,score
            continue
        ok,score=meaningful_match(' '.join(x for x in (name,brand,qty) if x),title)
        if ok and score>bs:best,bs=c,score
    # One name-only retry when barcode query had no confident result.
    if best is None and bc:
        q2=' '.join(x for x in (name,brand,qty) if x)
        for c in bing_candidates(q2[:180]):
            ok,score=meaningful_match(q2,c['title'])
            if ok and score>bs:best,bs=c,score
    if best:
        best=dict(best);best['score']=round(bs,1);return best
    return None

def download_real(row,cand):
    key=str(row.get('pyramid_code2') or row.get('barcode') or row.get('id'))
    fn='web_'+hashlib.sha1(key.encode()).hexdigest()[:22]+'.webp';path=work/'images'/fn
    try:
        rr=requests.get(cand['murl'],impersonate='chrome',headers={'Referer':cand['purl'] or 'https://www.bing.com/','Accept':'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'},timeout=28)
        if rr.status_code!=200 or len(rr.content)<1000 or len(rr.content)>12_000_000:return None
        im=Image.open(io.BytesIO(rr.content));im.load()
        if getattr(im,'is_animated',False):im.seek(0)
        if im.width<180 or im.height<180:return None
        if im.mode not in ('RGB','RGBA'):im=im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
        im.thumbnail((720,720),Image.Resampling.LANCZOS)
        im.save(path,'WEBP',quality=80,method=6)
        if path.stat().st_size>140000:
            im.thumbnail((620,620),Image.Resampling.LANCZOS);im.save(path,'WEBP',quality=74,method=6)
        return {'code2':str(row.get('pyramid_code2') or ''),'barcode':str(row.get('barcode') or ''),'file':fn,'bytes':path.stat().st_size,'source':'web-search|'+(cand['host'] or 'unknown')+'|'+(cand['purl'] or cand['murl']),'target_name':str(row.get('name') or ''),'source_title':cand['title'],'score':cand['score']}
    except Exception:
        path.unlink(missing_ok=True);return None

# Font helpers for Persian fallback cards.
FONT_CANDS=['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']
FONT=next((x for x in FONT_CANDS if pathlib.Path(x).exists()),None)
def rtl(s):
    try:return get_display(arabic_reshaper.reshape(str(s)))
    except:return str(s)
def fit_text(draw,text,maxw,max_size=48,min_size=22,bold=True):
    path=FONT
    for size in range(max_size,min_size-1,-2):
        f=ImageFont.truetype(path,size) if path else ImageFont.load_default()
        if draw.textbbox((0,0),text,font=f)[2]<=maxw:return f
    return ImageFont.truetype(path,min_size) if path else ImageFont.load_default()
def wrap_words(draw,text,font,maxw):
    words=str(text).split();lines=[];cur=[]
    for w in words:
        trial=' '.join(cur+[w]);disp=rtl(trial)
        if cur and draw.textbbox((0,0),disp,font=font)[2]>maxw:
            lines.append(' '.join(cur));cur=[w]
        else:cur.append(w)
    if cur:lines.append(' '.join(cur))
    return lines[:4]
def fallback_image(row):
    key=str(row.get('pyramid_code2') or row.get('barcode') or row.get('id'));fn='fallback_'+hashlib.sha1(key.encode()).hexdigest()[:22]+'.webp';path=work/'images'/fn
    W=640;H=640
    # Light neutral background with a subtle product-card silhouette; clearly not pretending to be a real package photo.
    im=Image.new('RGB',(W,H),(250,250,248));d=ImageDraw.Draw(im)
    d.rounded_rectangle((54,54,W-54,H-54),radius=34,fill=(255,255,255),outline=(225,226,222),width=3)
    d.rounded_rectangle((230,105,410,295),radius=26,fill=(242,244,240),outline=(216,220,213),width=3)
    d.rectangle((260,135,380,250),fill=(232,236,229))
    d.ellipse((288,165,352,229),fill=(220,226,217))
    name=str(row.get('name') or 'کالا').strip();display=rtl(name)
    font=fit_text(d,display,500,44,24)
    lines=wrap_words(d,name,font,500)
    line_h=max(38,font.size+10 if hasattr(font,'size') else 42);y=350
    for line in lines:
        disp=rtl(line);box=d.textbbox((0,0),disp,font=font);x=(W-(box[2]-box[0]))//2;d.text((x,y),disp,font=font,fill=(35,42,38));y+=line_h
    small=ImageFont.truetype(FONT,22) if FONT else ImageFont.load_default();brand=rtl('طهران مارکت')
    box=d.textbbox((0,0),brand,font=small);d.text(((W-(box[2]-box[0]))//2,555),brand,font=small,fill=(105,112,106))
    im.save(path,'WEBP',quality=76,method=6)
    return {'code2':str(row.get('pyramid_code2') or ''),'barcode':str(row.get('barcode') or ''),'file':fn,'bytes':path.stat().st_size,'source':'generated-fallback|tehran-market','target_name':name}

real=[];unmatched=[]
# Keep web concurrency modest to avoid hammering search providers.
with ThreadPoolExecutor(max_workers=6) as ex:
    futs={ex.submit(choose_candidate,r):r for r in targets}
    for i,f in enumerate(as_completed(futs),1):
        row=futs[f];cand=None
        try:cand=f.result()
        except Exception:cand=None
        if cand:
            got=download_real(row,cand)
            if got:real.append(got)
            else:unmatched.append(row)
        else:unmatched.append(row)
        if i%200==0:print('SEARCHED',i,'real',len(real),'fallback_pending',len(unmatched),flush=True)
print('REAL_IMAGES_READY',len(real),'UNMATCHED',len(unmatched),flush=True)

fallback=[]
for i,row in enumerate(unmatched,1):
    try:fallback.append(fallback_image(row))
    except Exception as e:print('FALLBACK_ERR',row.get('pyramid_code2'),repr(e),flush=True)
    if i%500==0:print('FALLBACK_MADE',i,flush=True)

allrows=real+fallback
# Stable order makes reruns easy to inspect.
allrows.sort(key=lambda x:(x['source'].startswith('generated-fallback'),x.get('code2','')))
chunk=170
for bi in range(0,len(allrows),chunk):
    part=allrows[bi:bi+chunk];zp=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for r in part:z.write(work/'images'/r['file'],'images/'+r['file'])
summary={'targets':len(targets),'real_images_ready':len(real),'fallback_images_ready':len(fallback),'total_images_ready':len(allrows),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':(sum(x['bytes'] for x in allrows)//len(allrows) if allrows else 0),'max_bytes':(max((x['bytes'] for x in allrows),default=0))}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');(work/'real-matches.json').write_text(json.dumps(real,ensure_ascii=False,indent=2),'utf-8')
print(json.dumps(summary,ensure_ascii=False),flush=True)
