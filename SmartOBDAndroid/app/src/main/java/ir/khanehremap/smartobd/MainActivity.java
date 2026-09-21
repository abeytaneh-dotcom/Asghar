package ir.khanehremap.smartobd;

import android.Manifest;
import android.app.*;
import android.bluetooth.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.content.res.Configuration;
import android.graphics.*;
import android.graphics.drawable.*;
import android.os.*;
import android.provider.Settings;
import android.speech.tts.TextToSpeech;
import android.view.*;
import android.widget.*;
import java.io.*;
import java.util.*;
import java.util.concurrent.*;

public class MainActivity extends Activity {
 static final UUID SPP=UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");
 final int BG=Color.rgb(3,10,18), PANEL=Color.rgb(8,21,34), CYAN=Color.rgb(0,184,255), BLUE=Color.rgb(0,105,255), RED=Color.rgb(255,45,60), GREEN=Color.rgb(35,230,125), AMBER=Color.rgb(255,174,24), MUTED=Color.rgb(155,174,192);
 LinearLayout root,body; TextView status,modeChip,rpmText,speedText,tempText,coolText,ecuText,fuelText,battText,logText;
 BluetoothSocket socket; BufferedReader input; BufferedWriter output; TextToSpeech tts; Handler h=new Handler(Looper.getMainLooper());
 boolean demo=true,alive=true; int rpm=900,speed=0,temp=88; boolean up=true;

 @Override public void onCreate(Bundle b){super.onCreate(b);getWindow().setStatusBarColor(BG);getWindow().setNavigationBarColor(BG);tts=new TextToSpeech(this,s->{if(s==TextToSpeech.SUCCESS)tts.setLanguage(new Locale("fa","IR"));});askBt();render("خانه");demoLoop();}
 void askBt(){if(Build.VERSION.SDK_INT>=31&&checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED)requestPermissions(new String[]{Manifest.permission.BLUETOOTH_CONNECT,Manifest.permission.BLUETOOTH_SCAN},8);}
 GradientDrawable box(int fill,int stroke,float r){GradientDrawable d=new GradientDrawable();d.setColor(fill);d.setCornerRadius(r);if(stroke!=0)d.setStroke(2,stroke);return d;}
 TextView txt(String s,int sp,int c){TextView v=new TextView(this);v.setText(s);v.setTextSize(sp);v.setTextColor(c);v.setGravity(Gravity.CENTER);v.setPadding(10,7,10,7);return v;}
 Button button(String s){Button b=new Button(this);b.setText(s);b.setTextSize(15);b.setTextColor(Color.WHITE);b.setAllCaps(false);b.setBackground(box(PANEL,CYAN,18));b.setPadding(5,3,5,3);return b;}
 void base(){root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setBackgroundColor(BG);root.setPadding(6,4,6,5);setContentView(root);
  LinearLayout head=new LinearLayout(this);head.setGravity(Gravity.CENTER_VERTICAL);TextView brand=txt("خانه ریمپ   SMART OBD",18,Color.WHITE);brand.setGravity(Gravity.RIGHT);brand.setTypeface(null,Typeface.BOLD);head.addView(brand,new LinearLayout.LayoutParams(0,-2,1));
  modeChip=txt(demo?"● دمو":"● واقعی",15,demo?CYAN:GREEN);modeChip.setBackground(box(PANEL,demo?CYAN:GREEN,24));head.addView(modeChip);root.addView(head);
  status=txt(demo?"● DEMO  •  ESP32_OBD":"● LIVE  •  آماده اتصال ESP32_OBD",12,demo?AMBER:GREEN);status.setGravity(Gravity.RIGHT);root.addView(status);
  body=new LinearLayout(this);root.addView(body,new LinearLayout.LayoutParams(-1,0,1));
 }
 void render(String page){
  if(page.equals("خانه")){
    root=new LinearLayout(this);root.setBackgroundColor(BG);root.setPadding(0,0,0,0);setContentView(root);
    body=root;dashboard(getResources().getConfiguration().orientation==Configuration.ORIENTATION_LANDSCAPE);return;
  }
  base();if(page.equals("دیاگ"))diag();else if(page.equals("داده زنده"))live();else if(page.equals("اتصال"))connectPage();else settingsPage();nav();
}
 void nav(){LinearLayout n=new LinearLayout(this);String[] a={"⌂ خانه","⚙ ECU","▤ گزارش","⚠ خطا","▥ تنظیمات"};for(String s:a){Button b=button(s);b.setOnClickListener(v->{if(s.contains("خانه"))render("خانه");else if(s.contains("خطا"))render("دیاگ");else if(s.contains("تنظیمات"))render("تنظیمات");else render("داده زنده");});n.addView(b,new LinearLayout.LayoutParams(0,55,1));}root.addView(n);}
 LinearLayout panel(){LinearLayout p=new LinearLayout(this);p.setOrientation(LinearLayout.VERTICAL);p.setGravity(Gravity.CENTER);p.setPadding(8,8,8,8);p.setBackground(box(PANEL,Color.rgb(24,52,75),24));return p;}
 void dashboard(boolean land){
  body.setOrientation(LinearLayout.VERTICAL);
  body.removeAllViews();
  body.addView(new ClusterView(this),new LinearLayout.LayoutParams(-1,-1));
 }
 TextView stat(LinearLayout row,String icon,String name,String value,int color){LinearLayout p=panel();p.addView(txt(icon,21,color));p.addView(txt(name,12,MUTED));TextView v=txt(value,18,color);v.setTypeface(null,Typeface.BOLD);p.addView(v);row.addView(p,new LinearLayout.LayoutParams(0,-1,1));return v;}
 class ClusterView extends View{
  Paint p=new Paint(Paint.ANTI_ALIAS_FLAG); RectF r=new RectF(); Random rnd=new Random(4);
  ClusterView(Context x){super(x);setLayerType(View.LAYER_TYPE_SOFTWARE,null);setOnClickListener(v->{});}
  void tx(Canvas c,String s,float x,float y,float z,int col,Paint.Align al,boolean bold){p.setStyle(Paint.Style.FILL);p.setColor(col);p.setTextSize(z);p.setTextAlign(al);p.setTypeface(bold?Typeface.DEFAULT_BOLD:Typeface.DEFAULT);p.clearShadowLayer();c.drawText(s,x,y,p);}
  void rr(Canvas c,float l,float t,float x,float y,float rad,int fill,int stroke){p.setStyle(Paint.Style.FILL);p.setColor(fill);p.clearShadowLayer();c.drawRoundRect(l,t,x,y,rad,rad,p);if(stroke!=0){p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(1.5f);p.setColor(stroke);c.drawRoundRect(l,t,x,y,rad,rad,p);}}
  void glowLine(Canvas c,float x1,float y1,float x2,float y2,int col,float sw){p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(sw);p.setStrokeCap(Paint.Cap.ROUND);p.setColor(col);p.setShadowLayer(sw*2,0,0,col);c.drawLine(x1,y1,x2,y2,p);p.clearShadowLayer();}
  void city(Canvas c,float l,float t,float x,float y){p.setStyle(Paint.Style.FILL);p.setColor(Color.rgb(4,15,28));c.drawRect(l,t,x,y,p);for(int i=0;i<22;i++){float bw=(x-l)/22f*.75f,bx=l+i*(x-l)/22f,bh=(25+(i*37)%85);p.setColor(Color.rgb(8,26,45));c.drawRect(bx,y-bh,bx+bw,y,p);p.setColor(i%3==0?Color.rgb(0,110,170):Color.rgb(18,55,76));for(int q=0;q<4;q++)c.drawRect(bx+4,y-bh+8+q*15,bx+7,y-bh+11+q*15,p);}p.setColor(Color.rgb(0,72,120));c.drawRect(l,y-2,x,y,p);}
  void gauge(Canvas c,float cx,float cy,float rad,float val,boolean rpmg){
    p.setStyle(Paint.Style.FILL);p.setColor(Color.rgb(1,8,15));p.setShadowLayer(25,0,0,Color.rgb(0,75,130));c.drawCircle(cx,cy,rad,p);p.clearShadowLayer();
    p.setStyle(Paint.Style.STROKE);p.setStrokeCap(Paint.Cap.ROUND);p.setStrokeWidth(rad*.065f);r.set(cx-rad*.90f,cy-rad*.90f,cx+rad*.90f,cy+rad*.90f);p.setColor(Color.rgb(10,35,58));c.drawArc(r,140,260,false,p);
    p.setShadowLayer(16,0,0,CYAN);p.setColor(CYAN);c.drawArc(r,140,Math.max(8,215*Math.min(1,val)),false,p);p.clearShadowLayer();p.setShadowLayer(14,0,0,RED);p.setColor(RED);c.drawArc(r,350,50,false,p);p.clearShadowLayer();
    int max=rpmg?8:240,step=rpmg?1:30;p.setStrokeWidth(1.8f);
    for(int v=0;v<=max;v+=step){float q=(float)v/max,an=(float)Math.toRadians(140+260*q);float x1=(float)(cx+Math.cos(an)*rad*.70),y1=(float)(cy+Math.sin(an)*rad*.70),x2=(float)(cx+Math.cos(an)*rad*.82),y2=(float)(cy+Math.sin(an)*rad*.82);p.setColor(Color.WHITE);c.drawLine(x1,y1,x2,y2,p);tx(c,""+v,(float)(cx+Math.cos(an)*rad*.57),(float)(cy+Math.sin(an)*rad*.57)+4,rad*.085f,Color.WHITE,Paint.Align.CENTER,false);}
    tx(c,rpmg?""+rpm:""+speed,cx,cy+rad*.09f,rad*.31f,Color.WHITE,Paint.Align.CENTER,true);tx(c,rpmg?"RPM  x1000":"km/h",cx,cy+rad*.33f,rad*.105f,MUTED,Paint.Align.CENTER,true);
  }
  void car(Canvas c,float cx,float top,float s){
    p.setStyle(Paint.Style.FILL);p.setShadowLayer(26,0,7,Color.rgb(0,105,210));p.setColor(Color.rgb(8,38,72));
    Path hood=new Path();hood.moveTo(cx-72*s,top+52*s);hood.lineTo(cx-47*s,top+17*s);hood.quadTo(cx,top-2*s,cx+47*s,top+17*s);hood.lineTo(cx+72*s,top+52*s);hood.lineTo(cx+61*s,top+88*s);hood.lineTo(cx-61*s,top+88*s);hood.close();c.drawPath(hood,p);p.clearShadowLayer();
    p.setColor(Color.rgb(10,20,30));Path glass=new Path();glass.moveTo(cx-42*s,top+22*s);glass.lineTo(cx-28*s,top+7*s);glass.lineTo(cx+28*s,top+7*s);glass.lineTo(cx+42*s,top+22*s);glass.close();c.drawPath(glass,p);
    p.setColor(Color.rgb(0,145,255));c.drawRoundRect(cx-59*s,top+44*s,cx-27*s,top+57*s,6*s,6*s,p);c.drawRoundRect(cx+27*s,top+44*s,cx+59*s,top+57*s,6*s,6*s,p);
    p.setColor(Color.rgb(1,8,14));c.drawRoundRect(cx-42*s,top+60*s,cx+42*s,top+79*s,6*s,6*s,p);p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(2*s);p.setColor(Color.rgb(70,105,130));for(int i=-30;i<=30;i+=10)c.drawLine(cx+i*s,top+63*s,cx+i*s,top+76*s,p);
    p.setStyle(Paint.Style.FILL);p.setColor(Color.WHITE);c.drawRoundRect(cx-24*s,top+80*s,cx+24*s,top+92*s,3*s,3*s,p);tx(c,"خانه ریمپ",cx,top+89*s,7*s,Color.rgb(10,25,40),Paint.Align.CENTER,true);
    p.setColor(Color.rgb(2,7,12));c.drawRoundRect(cx-70*s,top+78*s,cx-53*s,top+101*s,6*s,6*s,p);c.drawRoundRect(cx+53*s,top+78*s,cx+70*s,top+101*s,6*s,6*s,p);
  }
  void stat(Canvas c,float l,float t,float x,float y,String ico,String name,String val,int col){rr(c,l,t,x,y,12,Color.rgb(5,22,35),Color.rgb(18,61,92));tx(c,ico,l+14,t+28,18,col,Paint.Align.LEFT,true);tx(c,name,x-10,t+22,11,MUTED,Paint.Align.RIGHT,false);tx(c,val,x-10,y-12,17,col,Paint.Align.RIGHT,true);}
  void tile(Canvas c,float l,float t,float x,float y,String ico,String name,int col){rr(c,l,t,x,y,12,Color.rgb(4,19,32),Color.rgb(18,60,90));tx(c,ico,(l+x)/2,t+(y-t)*.42f,24,col,Paint.Align.CENTER,true);tx(c,name,(l+x)/2,y-12,12,Color.WHITE,Paint.Align.CENTER,true);}
  protected void onDraw(Canvas c){super.onDraw(c);float w=getWidth(),h=getHeight();boolean land=w>h;c.drawColor(BG);
   if(!land){
    float hh=58;rr(c,8,7,w-8,hh,14,Color.rgb(4,18,31),Color.rgb(15,56,84));tx(c,"☰",26,39,25,Color.WHITE,Paint.Align.CENTER,true);tx(c,"خانه ریمپ",w*.67f,29,20,Color.WHITE,Paint.Align.CENTER,true);tx(c,"SMART OBD",w*.67f,49,16,CYAN,Paint.Align.CENTER,true);rr(c,w-145,12,w-14,52,12,Color.rgb(5,26,42),CYAN);tx(c,"● ESP32_OBD",w-80,30,11,Color.WHITE,Paint.Align.CENTER,true);tx(c,demo?"DEMO":"متصل",w-80,45,10,demo?AMBER:GREEN,Paint.Align.CENTER,true);
    float gy=170,rad=Math.min(w*.225f,105);gauge(c,w*.25f,gy,rad,Math.min(1,rpm/8000f),true);gauge(c,w*.75f,gy,rad,Math.min(1,speed/240f),false);
    city(c,0,235,w,390);car(c,w/2,265,1.35f);
    float sy=398,g=6,cw=(w-24)/3f,ch=92;stat(c,6,sy,6+cw,sy+ch,"T","دمای آب",temp+" °C",RED);stat(c,12+cw,sy,12+2*cw,sy+ch,"F","سوخت","48 %",AMBER);stat(c,18+2*cw,sy,w-6,sy+ch,"V","ولتاژ","13.8 V",GREEN);
    float ey=sy+ch+6;rr(c,6,ey,w-6,ey+55,12,Color.rgb(4,25,37),Color.rgb(20,78,93));tx(c,"✓  وضعیت موتور: سالم",18,ey+24,13,GREEN,Paint.Align.LEFT,true);tx(c,"ECU: CAN 500kbps",w-16,ey+24,11,MUTED,Paint.Align.RIGHT,false);tx(c,"خودرو: TU5 • پروتکل OBD-II",w-16,ey+43,10,MUTED,Paint.Align.RIGHT,false);
    float ty=ey+62,th=78,tw=(w-30)/4f;tile(c,6,ty,6+tw,ty+th,"ECU","دیاگ",BLUE);tile(c,12+tw,ty,12+2*tw,ty+th,"▥","داده زنده",AMBER);tile(c,18+2*tw,ty,18+3*tw,ty+th,"!","کد خطا",RED);tile(c,24+3*tw,ty,w-6,ty+th,"✓","تست‌ها",Color.rgb(160,80,255));
    float ny=h-62;rr(c,0,ny,w,h,0,Color.rgb(2,12,22),0);String[] n={"⌂\nخانه","▣\nخودرو","▥\nگزارش","⚙\nتنظیمات"};for(int i=0;i<4;i++){float cx=w*(i+.5f)/4;tx(c,n[i].split("\\n")[0],cx,ny+25,20,i==0?CYAN:MUTED,Paint.Align.CENTER,true);tx(c,n[i].split("\\n")[1],cx,ny+48,11,i==0?Color.WHITE:MUTED,Paint.Align.CENTER,false);}
   }else{
    float side=170;rr(c,0,0,side,h,0,Color.rgb(3,17,29),Color.rgb(16,56,83));tx(c,"خانه ریمپ",side/2,24,17,Color.WHITE,Paint.Align.CENTER,true);tx(c,"SMART OBD",side/2,43,12,CYAN,Paint.Align.CENTER,true);String[] menu={"⌂ داشبورد","▥ داده زنده","⚠ کد خطا","⚙ تست عملکرد","▣ تحلیل ECU","▤ گزارش","⚙ تنظیمات"};for(int i=0;i<menu.length;i++){float yy=55+i*38;rr(c,7,yy,side-7,yy+31,8,i==0?Color.rgb(0,67,120):Color.rgb(5,25,40),i==0?CYAN:Color.rgb(20,53,76));tx(c,menu[i],side-14,yy+21,12,Color.WHITE,Paint.Align.RIGHT,i==0);}
    float x0=side+7,mw=w-side-14;rr(c,x0,6,w-7,40,9,Color.rgb(5,23,37),Color.rgb(20,60,86));tx(c,"● ESP32_OBD     TU5-206     CAN 500kbps     موتور سالم",x0+mw/2,28,12,GREEN,Paint.Align.CENTER,true);
    float gy=h*.38f,rad=Math.min(h*.27f,mw*.19f);city(c,x0,45,w-7,h*.66f);gauge(c,x0+mw*.24f,gy,rad,Math.min(1,rpm/8000f),true);gauge(c,x0+mw*.76f,gy,rad,Math.min(1,speed/240f),false);car(c,x0+mw*.5f,gy-rad*.18f,.95f);tx(c,"D",x0+mw*.5f,gy-rad*.48f,22,Color.WHITE,Paint.Align.CENTER,true);
    float sy=h*.68f,g=5,cw=(mw-3*g)/4;stat(c,x0,sy,x0+cw,h-7,"T","دمای آب",temp+" °C",RED);stat(c,x0+cw+g,sy,x0+2*cw+g,h-7,"F","سوخت","48 %",AMBER);stat(c,x0+2*(cw+g),sy,x0+3*cw+2*g,h-7,"V","باتری","13.8 V",GREEN);stat(c,x0+3*(cw+g),sy,w-7,h-7,"P","سطح آب","مناسب",GREEN);
   }
  }
  public boolean onTouchEvent(android.view.MotionEvent e){if(e.getAction()!=MotionEvent.ACTION_UP)return true;float y=e.getY(),x=e.getX(),w=getWidth(),h=getHeight();if(w<=h&&y>h-80){if(x>w*.75f)render("تنظیمات");else if(x>w*.5f)render("داده زنده");else if(x>w*.25f)render("اتصال");return true;}if(w<=h&&y>520&&y<650){if(x>w*.5f)render("دیاگ");else render("داده زنده");return true;}return true;}
 } } class GaugeView extends View{boolean r;Paint p=new Paint(1);GaugeView(Context c,boolean rpmGauge){super(c);r=rpmGauge;setLayerType(View.LAYER_TYPE_SOFTWARE,null);}
  protected void onDraw(Canvas c){super.onDraw(c);float w=getWidth(),hh=getHeight(),rad=Math.min(w,hh)*.40f,cx=w/2,cy=hh*.55f;p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(13);p.setStrokeCap(Paint.Cap.ROUND);p.setColor(Color.rgb(17,42,62));c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,140,260,false,p);p.setShadowLayer(18,0,0,CYAN);p.setColor(CYAN);float val=r?rpm/8000f:speed/240f;c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,140,260*Math.max(.02f,val),false,p);p.clearShadowLayer();p.setColor(RED);c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,350,50,false,p);p.setStyle(Paint.Style.FILL);p.setTextAlign(Paint.Align.CENTER);p.setTypeface(Typeface.DEFAULT_BOLD);p.setColor(Color.WHITE);p.setTextSize(rad*.43f);c.drawText(r?String.valueOf(rpm):String.valueOf(speed),cx,cy+rad*.12f,p);p.setTextSize(rad*.18f);p.setColor(MUTED);c.drawText(r?"RPM":"km/h",cx,cy+rad*.48f,p);p.setTextSize(rad*.15f);p.setColor(Color.WHITE);for(int i=0;i<=8;i++){double a=Math.toRadians(140+i*32.5);float x=(float)(cx+Math.cos(a)*rad*.72),y=(float)(cy+Math.sin(a)*rad*.72);c.drawText(r?""+i:""+i*30,x,y,p);}}
 }
 void diag(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("دیاگ و کدهای خطا (DTC)",26,Color.WHITE));Button read=button("خواندن خطاهای ECU");Button clear=button("پاک کردن خطاها");logText=txt("در حالت واقعی پس از اتصال، پاسخ ECU اینجا نمایش داده می‌شود.",17,MUTED);read.setOnClickListener(v->send("READ_DTC"));clear.setOnClickListener(v->new AlertDialog.Builder(this).setTitle("پاک کردن DTC").setMessage("پاک‌کردن خطا ممکن است Freeze Frame و Readiness را نیز پاک کند. ادامه؟").setNegativeButton("خیر",null).setPositiveButton("بله",(d,w)->send("CLEAR_DTC")).show());body.addView(read);body.addView(clear);body.addView(logText,new LinearLayout.LayoutParams(-1,0,1));}
 void live(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("داده‌های زنده ECU",26,Color.WHITE));String[] x={"RPM","سرعت خودرو","دمای آب","سطح مخزن","وضعیت پروتکل","ولتاژ/سوخت در صورت پشتیبانی ECU"};for(String s:x){LinearLayout p=panel();p.addView(txt(s,18,MUTED));p.addView(txt("دریافت زنده / وابسته به پشتیبانی ECU",19,CYAN));body.addView(p,new LinearLayout.LayoutParams(-1,0,1));}}
 void connectPage(){body.setOrientation(LinearLayout.VERTICAL);Button mode=button(demo?"فعال کردن حالت واقعی":"فعال کردن حالت دمو");mode.setOnClickListener(v->{demo=!demo;if(demo)disconnect();render("اتصال");});body.addView(mode);Button con=button("اتصال Bluetooth به ESP32");con.setEnabled(!demo);con.setOnClickListener(v->choose());body.addView(con);body.addView(txt("Bluetooth Classic SPP • دستگاه را ابتدا Pair کنید.\nنام پیشنهادی: KhanehRemap-OBD",17,MUTED));}
 void settingsPage(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("تنظیمات",27,Color.WHITE));Button sp=button("تست هشدار صوتی کمبود آب");sp.setOnClickListener(v->speak("هشدار، سطح آب خنک کننده پایین است"));body.addView(sp);Button bt=button("تنظیمات بلوتوث گوشی");bt.setOnClickListener(v->startActivity(new Intent(Settings.ACTION_BLUETOOTH_SETTINGS)));body.addView(bt);body.addView(txt("چرخش صفحه: خودکار • عمودی و افقی رسپانسیو",17,MUTED));}
 void demoLoop(){h.postDelayed(new Runnable(){public void run(){if(!alive)return;if(demo){rpm+=up?180:-160;if(rpm>5200)up=false;if(rpm<850)up=true;speed=Math.max(0,(rpm-600)/34);temp=88+(rpm/900)%5;apply("RPM:"+rpm);apply("SPEED:"+speed);apply("ECT:"+temp);apply("COOLANT:OK");}invalidateGauges(root);h.postDelayed(this,500);}},500);}
 void invalidateGauges(View v){if(v instanceof GaugeView || v instanceof ClusterView)v.invalidate();if(v instanceof ViewGroup){ViewGroup g=(ViewGroup)v;for(int i=0;i<g.getChildCount();i++)invalidateGauges(g.getChildAt(i));}}
 void choose(){if(Build.VERSION.SDK_INT>=31&&checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED){askBt();return;}BluetoothAdapter a=BluetoothAdapter.getDefaultAdapter();if(a==null){toast("بلوتوث در دسترس نیست");return;}if(!a.isEnabled()){startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));return;}ArrayList<BluetoothDevice> ds=new ArrayList<>(a.getBondedDevices());if(ds.isEmpty()){toast("ابتدا ESP32 را Pair کنید");return;}String[] n=new String[ds.size()];for(int i=0;i<n.length;i++)n[i]=(ds.get(i).getName()==null?"Bluetooth":ds.get(i).getName())+"\n"+ds.get(i).getAddress();new AlertDialog.Builder(this).setTitle("انتخاب ESP32").setItems(n,(d,i)->connect(ds.get(i))).show();}
 void connect(BluetoothDevice d){status.setText("در حال اتصال…");Executors.newSingleThreadExecutor().execute(()->{try{socket=d.createRfcommSocketToServiceRecord(SPP);socket.connect();input=new BufferedReader(new InputStreamReader(socket.getInputStream()));output=new BufferedWriter(new OutputStreamWriter(socket.getOutputStream()));runOnUiThread(()->{status.setText("● متصل به "+d.getName());status.setTextColor(GREEN);});new Thread(()->{try{String s;while(socket!=null&&socket.isConnected()&&(s=input.readLine())!=null){String q=s;runOnUiThread(()->apply(q));}}catch(Exception e){runOnUiThread(()->status.setText("ارتباط قطع شد"));}}).start();send("STATUS");}catch(Exception e){runOnUiThread(()->{status.setText("اتصال ناموفق");toast(e.getMessage());});}});}
 void send(String s){if(demo){if(logText!=null)logText.setText("دمو: "+s);return;}try{if(output==null){toast("ابتدا متصل شوید");return;}output.write(s+"\n");output.flush();}catch(Exception e){toast("ارسال ناموفق");}}
 void apply(String s){try{if(s.startsWith("RPM:"))rpm=Integer.parseInt(s.substring(4).trim());else if(s.startsWith("SPEED:"))speed=Integer.parseInt(s.substring(6).trim());else if(s.startsWith("ECT:")){temp=Integer.parseInt(s.substring(4).trim());if(tempText!=null)tempText.setText(temp+" °C");}else if(s.equals("COOLANT:OK")&&coolText!=null){coolText.setText("مناسب");coolText.setTextColor(GREEN);}else if(s.startsWith("ALARM:LOW_COOLANT")){if(coolText!=null){coolText.setText("کمبود آب");coolText.setTextColor(RED);}speak("هشدار، سطح آب خنک کننده پایین است");}else if(s.startsWith("ECU:CONNECTED")&&ecuText!=null)ecuText.setText("موتور سالم • "+s.replace("ECU:CONNECTED,",""));else if(logText!=null)logText.append("\n"+s);}catch(Exception ignored){}invalidateGauges(root);}
 void speak(String s){if(tts!=null)tts.speak(s,TextToSpeech.QUEUE_FLUSH,null,"alarm");}
 void disconnect(){try{if(socket!=null)socket.close();}catch(Exception ignored){}socket=null;input=null;output=null;}
 void toast(String s){Toast.makeText(this,s==null?"خطا":s,Toast.LENGTH_SHORT).show();}
 @Override public void onConfigurationChanged(Configuration c){super.onConfigurationChanged(c);render("خانه");}
 @Override protected void onDestroy(){alive=false;disconnect();if(tts!=null){tts.stop();tts.shutdown();}super.onDestroy();}
}