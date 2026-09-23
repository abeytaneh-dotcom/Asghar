from PIL import Image, ImageDraw, ImageFont
from pathlib import Path
img=Image.new("RGBA",(256,256),"#07111f")
d=ImageDraw.Draw(img)
d.rounded_rectangle((18,18,238,238),radius=54,fill="#0b1a29",outline="#35bfff",width=10)
d.ellipse((54,54,202,202),fill="#0c79b6",outline="#7de0ff",width=5)
try:
    f=ImageFont.truetype("arialbd.ttf",72)
except Exception:
    f=ImageFont.load_default()
bbox=d.textbbox((0,0),"KR",font=f)
x=(256-(bbox[2]-bbox[0]))/2
y=(256-(bbox[3]-bbox[1]))/2-8
d.text((x,y),"KR",font=f,fill="white")
img.save(Path(__file__).with_name("app.ico"),sizes=[(256,256),(128,128),(64,64),(48,48),(32,32),(16,16)])
