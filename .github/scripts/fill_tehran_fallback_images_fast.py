from __future__ import annotations
import hashlib,json,pathlib,sys,zipfile
from PIL import Image,ImageDraw,ImageFont
import arabic_reshaper
from bidi.algorithm import get_display

work=pathlib.Path(sys.argv[1]);report=pathlib.Path(sys.argv[2]);(work/'images').mkdir(parents=True,exist_ok=True);(work/'bundles').mkdir(parents=True,exist_ok=True)
rows=json.loads(report.read_text('utf-8')).get('rows') or []
font_path='/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';bold_path='/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
def rtl(s):
    try:return get_display(arabic_reshaper.reshape(str(s)))
    except:return str(s)
def font(sz,bold=False):return ImageFont.truetype(bold_path if bold else font_path,sz)
def wrap(draw,text,f,maxw):
    words=str(text).split();out=[];cur=[]
    for w in words:
        trial=' '.join(cur+[w]);disp=rtl(trial);box=draw.textbbox((0,0),disp,font=f)
        if cur and box[2]-box[0]>maxw:out.append(' '.join(cur));cur=[w]
        else:cur.append(w)
    if cur:out.append(' '.join(cur))
    return out[:4]
def make(r):
    key=str(r.get('pyramid_code2') or r.get('barcode') or r.get('id'));fn='fallback_'+hashlib.sha1(key.encode()).hexdigest()[:22]+'.webp';p=work/'images'/fn
    im=Image.new('RGB',(560,560),(250,250,247));d=ImageDraw.Draw(im)
    d.rounded_rectangle((36,36,524,524),radius=28,fill=(255,255,255),outline=(225,227,222),width=3)
    d.rounded_rectangle((205,82,355,238),radius=24,fill=(241,244,239),outline=(218,222,215),width=3)
    d.rectangle((229,108,331,205),fill=(231,235,228));d.ellipse((252,127,308,183),fill=(216,224,214))
    name=str(r.get('name') or 'کالا').strip();f=font(34,True);lines=wrap(d,name,f,450);y=285
    for line in lines:
        disp=rtl(line);box=d.textbbox((0,0),disp,font=f);d.text(((560-(box[2]-box[0]))//2,y),disp,font=f,fill=(38,44,40));y+=48
    sf=font(19);brand=rtl('طهران مارکت');box=d.textbbox((0,0),brand,font=sf);d.text(((560-(box[2]-box[0]))//2,486),brand,font=sf,fill=(105,112,106))
    im.save(p,'WEBP',quality=68,method=2)
    return {'code2':str(r.get('pyramid_code2') or ''),'barcode':str(r.get('barcode') or ''),'file':fn,'source':'generated-fallback|tehran-market','bytes':p.stat().st_size,'target_name':name}
items=[]
for i,r in enumerate(rows,1):
    try:items.append(make(r))
    except Exception as e:print('ERR',r.get('pyramid_code2'),repr(e),flush=True)
    if i%500==0:print('MADE',i,flush=True)
chunk=450
for bi in range(0,len(items),chunk):
    part=items[bi:bi+chunk];zp=work/'bundles'/f'image-bundle-{bi//chunk:03d}.zip'
    with zipfile.ZipFile(zp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=4) as z:
        z.writestr('mapping.json',json.dumps(part,ensure_ascii=False,separators=(',',':')))
        for x in part:z.write(work/'images'/x['file'],'images/'+x['file'])
summary={'targets':len(rows),'images_ready':len(items),'bundles':len(list((work/'bundles').glob('*.zip'))),'avg_bytes':sum(x['bytes'] for x in items)//max(1,len(items)),'max_bytes':max((x['bytes'] for x in items),default=0)}
(work/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),'utf-8');print(json.dumps(summary,ensure_ascii=False),flush=True)
