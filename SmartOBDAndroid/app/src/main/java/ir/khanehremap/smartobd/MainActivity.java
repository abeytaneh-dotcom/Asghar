package ir.khanehremap.smartobd;

import android.Manifest;
import android.app.*;
import android.bluetooth.*;
import android.bluetooth.le.*;
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
 static final UUID BLE_SERVICE=UUID.fromString("7f640101-7c7d-4f0a-8b6f-4f484f4d4501");
 static final UUID BLE_CMD=UUID.fromString("7f640102-7c7d-4f0a-8b6f-4f484f4d4501");
 static final UUID BLE_DATA=UUID.fromString("7f640103-7c7d-4f0a-8b6f-4f484f4d4501");
 static final UUID CCCD=UUID.fromString("00002902-0000-1000-8000-00805f9b34fb");
 final int BG=Color.rgb(3,10,18), PANEL=Color.rgb(8,21,34), CYAN=Color.rgb(0,184,255), BLUE=Color.rgb(0,105,255), RED=Color.rgb(255,45,60), GREEN=Color.rgb(35,230,125), AMBER=Color.rgb(255,174,24), MUTED=Color.rgb(155,174,192);
 LinearLayout root,body; TextView status,modeChip,rpmText,speedText,tempText,coolText,ecuText,fuelText,battText,logText;
 BluetoothGatt gatt; BluetoothGattCharacteristic cmdCh,dataCh; BluetoothLeScanner bleScanner; ScanCallback scanCallback;
 TextToSpeech tts; Handler h=new Handler(Looper.getMainLooper());
 boolean demo=true,alive=true,bleConnected=false; int rpm=900,speed=0,temp=88; boolean up=true;
 float fuel=48f,batt=13.8f; String protocol="NONE",coolantState="مناسب";

 @Override public void onCreate(Bundle b){super.onCreate(b);getWindow().setStatusBarColor(BG);getWindow().setNavigationBarColor(BG);tts=new TextToSpeech(this,s->{if(s==TextToSpeech.SUCCESS)tts.setLanguage(new Locale("fa","IR"));});askBt();render("خانه");demoLoop();}
 void askBt(){
  if(Build.VERSION.SDK_INT>=31){
   ArrayList<String> p=new ArrayList<>();
   if(checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED)p.add(Manifest.permission.BLUETOOTH_CONNECT);
   if(checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)!=PackageManager.PERMISSION_GRANTED)p.add(Manifest.permission.BLUETOOTH_SCAN);
   if(!p.isEmpty())requestPermissions(p.toArray(new String[0]),8);
  }else if(Build.VERSION.SDK_INT>=23&&checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)!=PackageManager.PERMISSION_GRANTED){
   requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION},9);
  }
 }
 GradientDrawable box(int fill,int stroke,float r){GradientDrawable d=new GradientDrawable();d.setColor(fill);d.setCornerRadius(r);if(stroke!=0)d.setStroke(2,stroke);return d;}
 TextView txt(String s,int sp,int c){TextView v=new TextView(this);v.setText(s);v.setTextSize(sp);v.setTextColor(c);v.setGravity(Gravity.CENTER);v.setPadding(10,7,10,7);return v;}
 Button button(String s){Button b=new Button(this);b.setText(s);b.setTextSize(15);b.setTextColor(Color.WHITE);b.setAllCaps(false);b.setBackground(box(PANEL,CYAN,18));b.setPadding(5,3,5,3);return b;}
 void base(){root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setBackgroundColor(BG);root.setPadding(6,4,6,5);setContentView(root);
  LinearLayout head=new LinearLayout(this);head.setGravity(Gravity.CENTER_VERTICAL);TextView brand=txt("خانه ریمپ   SMART OBD",18,Color.WHITE);brand.setGravity(Gravity.RIGHT);brand.setTypeface(null,Typeface.BOLD);head.addView(brand,new LinearLayout.LayoutParams(0,-2,1));
  modeChip=txt(demo?"● دمو":"● واقعی",15,demo?CYAN:GREEN);modeChip.setBackground(box(PANEL,demo?CYAN:GREEN,24));head.addView(modeChip);root.addView(head);
  status=txt(demo?"● DEMO  •  ESP32_OBD":"● BLE  •  آماده اتصال ESP32-C3",12,demo?AMBER:GREEN);status.setGravity(Gravity.RIGHT);root.addView(status);
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
    p.setStyle(Paint.Style.FILL);p.setShadowLayer(30,0,10,Color.rgb(0,95,210));p.setColor(Color.rgb(8,32,62));
    Path b=new Path();b.moveTo(cx-82*s,top+69*s);b.quadTo(cx-72*s,top+39*s,cx-47*s,top+27*s);b.lineTo(cx-31*s,top+7*s);b.quadTo(cx,top-7*s,cx+31*s,top+7*s);b.lineTo(cx+47*s,top+27*s);b.quadTo(cx+72*s,top+39*s,cx+82*s,top+69*s);b.lineTo(cx+75*s,top+94*s);b.quadTo(cx,top+108*s,cx-75*s,top+94*s);b.close();c.drawPath(b,p);p.clearShadowLayer();
    LinearGradient lg=new LinearGradient(cx-70*s,top,cx+70*s,top+100*s,Color.rgb(20,78,135),Color.rgb(1,10,22),Shader.TileMode.CLAMP);p.setShader(lg);c.drawPath(b,p);p.setShader(null);
    p.setColor(Color.rgb(1,8,16));Path glass=new Path();glass.moveTo(cx-43*s,top+29*s);glass.lineTo(cx-27*s,top+9*s);glass.quadTo(cx,top+1*s,cx+27*s,top+9*s);glass.lineTo(cx+43*s,top+29*s);glass.close();c.drawPath(glass,p);
    p.setColor(Color.rgb(0,205,255));p.setShadowLayer(18,0,0,CYAN);c.drawRoundRect(cx-69*s,top+48*s,cx-31*s,top+61*s,7*s,7*s,p);c.drawRoundRect(cx+31*s,top+48*s,cx+69*s,top+61*s,7*s,7*s,p);p.clearShadowLayer();
    p.setColor(Color.rgb(0,5,10));c.drawRoundRect(cx-47*s,top+63*s,cx+47*s,top+86*s,8*s,8*s,p);p.setColor(Color.rgb(75,112,140));for(int i=-36;i<=36;i+=12)c.drawRoundRect(cx+i*s-2*s,top+66*s,cx+i*s+2*s,top+83*s,2*s,2*s,p);
    p.setColor(Color.rgb(220,230,238));c.drawRoundRect(cx-25*s,top+87*s,cx+25*s,top+99*s,2*s,2*s,p);tx(c,"خانه ریمپ",cx,top+96*s,7*s,Color.rgb(7,22,36),Paint.Align.CENTER,true);
    p.setColor(Color.rgb(1,5,9));c.drawRoundRect(cx-82*s,top+76*s,cx-64*s,top+105*s,7*s,7*s,p);c.drawRoundRect(cx+64*s,top+76*s,cx+82*s,top+105*s,7*s,7*s,p);
  }
  void stat(Canvas c,float l,float t,float x,float y,String ico,String name,String val,int col){rr(c,l,t,x,y,12,Color.rgb(5,22,35),Color.rgb(18,61,92));tx(c,ico,l+14,t+28,18,col,Paint.Align.LEFT,true);tx(c,name,x-10,t+22,11,MUTED,Paint.Align.RIGHT,false);tx(c,val,x-10,y-12,17,col,Paint.Align.RIGHT,true);}
  void tile(Canvas c,float l,float t,float x,float y,String ico,String name,int col){rr(c,l,t,x,y,12,Color.rgb(4,19,32),Color.rgb(18,60,90));tx(c,ico,(l+x)/2,t+(y-t)*.42f,24,col,Paint.Align.CENTER,true);tx(c,name,(l+x)/2,y-12,12,Color.WHITE,Paint.Align.CENTER,true);}
  protected void onDraw(Canvas c){super.onDraw(c);float w=getWidth(),h=getHeight();boolean land=w>h;c.drawColor(BG);
   if(!land){
    // Premium portrait cockpit: dense, readable and fills the useful screen.
    float header=h*.052f; rr(c,8,7,w-8,header,16,Color.rgb(3,18,31),Color.rgb(12,65,98));
    tx(c,"☰",27,header*.68f,25,Color.WHITE,Paint.Align.CENTER,true); tx(c,"خانه ریمپ",w*.50f,header*.42f,21,Color.WHITE,Paint.Align.CENTER,true); tx(c,"SMART OBD",w*.50f,header*.77f,14,CYAN,Paint.Align.CENTER,true);
    rr(c,w-145,12,w-14,header-6,12,Color.rgb(4,27,44),CYAN); tx(c,"● ESP32_OBD",w-80,header*.42f,11,Color.WHITE,Paint.Align.CENTER,true); tx(c,demo?"DEMO":"LIVE",w-80,header*.72f,10,demo?AMBER:GREEN,Paint.Align.CENTER,true);

    float gy=h*.178f,rad=Math.min(w*.205f,h*.115f); gauge(c,w*.255f,gy,rad,Math.min(1,rpm/8000f),true); gauge(c,w*.745f,gy,rad,Math.min(1,speed/240f),false);
    tx(c,"SPORT",w*.5f,h*.135f,12,MUTED,Paint.Align.CENTER,true); tx(c,"D",w*.5f,h*.185f,42,Color.WHITE,Paint.Align.CENTER,true); tx(c,"ECO",w*.5f,h*.212f,11,GREEN,Paint.Align.CENTER,true);

    float sceneTop=h*.285f,sceneBot=h*.455f;
    LinearGradient sky=new LinearGradient(0,sceneTop,0,sceneBot,Color.rgb(3,22,43),Color.rgb(1,7,14),Shader.TileMode.CLAMP);p.setShader(sky);p.setStyle(Paint.Style.FILL);c.drawRect(0,sceneTop,w,sceneBot,p);p.setShader(null); city(c,0,sceneTop,w,sceneBot);
    Path road=new Path();road.moveTo(w*.04f,sceneBot);road.lineTo(w*.42f,sceneTop+h*.055f);road.lineTo(w*.58f,sceneTop+h*.055f);road.lineTo(w*.96f,sceneBot);road.close();p.setColor(Color.rgb(5,18,29));c.drawPath(road,p);
    glowLine(c,w*.45f,sceneBot,w*.492f,sceneTop+h*.08f,CYAN,2); glowLine(c,w*.55f,sceneBot,w*.508f,sceneTop+h*.08f,CYAN,2); car(c,w/2,sceneTop+h*.016f,Math.max(1.45f,w/445f));

    float sy=h*.466f,cw=(w-35)/4f,ch=h*.086f;
    stat(c,7,sy,7+cw,sy+ch,"T","دمای آب",temp+" °C",RED); stat(c,14+cw,sy,14+2*cw,sy+ch,"F","سوخت",String.format(Locale.US,"%.0f %%",fuel),AMBER); stat(c,21+2*cw,sy,21+3*cw,sy+ch,"V","باتری",String.format(Locale.US,"%.1f V",batt),GREEN); stat(c,28+3*cw,sy,w-7,sy+ch,"P","سطح آب",coolantState,coolantState.equals("کمبود آب")?RED:GREEN);

    float sy2=sy+ch+7, cw2=(w-28)/3f, ch2=h*.072f;
    stat(c,7,sy2,7+cw2,sy2+ch2,"A","فشار مانیفولد","35 kPa",CYAN); stat(c,14+cw2,sy2,14+2*cw2,sy2+ch2,"L","مصرف لحظه‌ای","7.2 L/100",AMBER); stat(c,21+2*cw2,sy2,w-7,sy2+ch2,"I","دمای ورودی","32 °C",Color.WHITE);

    float ey=sy2+ch2+7; rr(c,7,ey,w-7,ey+h*.047f,14,Color.rgb(4,25,37),Color.rgb(24,105,94)); tx(c,"● موتور سالم",20,ey+h*.029f,13,GREEN,Paint.Align.LEFT,true); tx(c,"OBD-II  •  "+protocol,w-18,ey+h*.029f,11,Color.WHITE,Paint.Align.RIGHT,true);

    float ty=ey+h*.057f,th=h*.075f,tw=(w-30)/4f; tile(c,6,ty,6+tw,ty+th,"ECU","دیاگ",BLUE); tile(c,12+tw,ty,12+2*tw,ty+th,"▥","داده زنده",AMBER); tile(c,18+2*tw,ty,18+3*tw,ty+th,"!","DTC",RED); tile(c,24+3*tw,ty,w-6,ty+th,"✓","تست عملگر",Color.rgb(160,80,255));

    float iy=ty+th+8,ih=h*.066f,iw=(w-42)/6f; String[] ic={"A/C","فن","چراغ","ABS","AIR","ENG"}; int[] cc={CYAN,GREEN,BLUE,AMBER,RED,GREEN}; for(int i=0;i<6;i++){float x=6+i*(iw+6);rr(c,x,iy,x+iw,iy+ih,11,Color.rgb(4,22,34),Color.rgb(18,62,82));tx(c,ic[i],x+iw/2,iy+ih*.58f,13,cc[i],Paint.Align.CENTER,true);}

    float ny=h-h*.075f; rr(c,0,ny,w,h,0,Color.rgb(2,12,22),0); glowLine(c,0,ny,w,ny,Color.rgb(0,90,145),1); String[] n={"⌂\\nخانه","▣\\nECU","▥\\nگزارش","⚙\\nتنظیمات"}; for(int i=0;i<4;i++){float cx=w*(i+.5f)/4;tx(c,n[i].split("\\\\n")[0],cx,ny+h*.028f,22,i==0?CYAN:MUTED,Paint.Align.CENTER,true);tx(c,n[i].split("\\\\n")[1],cx,ny+h*.055f,11,i==0?Color.WHITE:MUTED,Paint.Align.CENTER,false);}
   }else{
    float side=170;rr(c,0,0,side,h,0,Color.rgb(3,17,29),Color.rgb(16,56,83));tx(c,"خانه ریمپ",side/2,24,17,Color.WHITE,Paint.Align.CENTER,true);tx(c,"SMART OBD",side/2,43,12,CYAN,Paint.Align.CENTER,true);String[] menu={"⌂ داشبورد","▥ داده زنده","⚠ کد خطا","⚙ تست عملکرد","▣ تحلیل ECU","▤ گزارش","⚙ تنظیمات"};for(int i=0;i<menu.length;i++){float yy=55+i*38;rr(c,7,yy,side-7,yy+31,8,i==0?Color.rgb(0,67,120):Color.rgb(5,25,40),i==0?CYAN:Color.rgb(20,53,76));tx(c,menu[i],side-14,yy+21,12,Color.WHITE,Paint.Align.RIGHT,i==0);}
    float x0=side+7,mw=w-side-14;rr(c,x0,6,w-7,40,9,Color.rgb(5,23,37),Color.rgb(20,60,86));tx(c,"● ESP32_OBD     "+protocol+"     "+(bleConnected?"BLE LIVE":"آماده"),x0+mw/2,28,12,GREEN,Paint.Align.CENTER,true);
    float gy=h*.38f,rad=Math.min(h*.27f,mw*.19f);city(c,x0,45,w-7,h*.66f);gauge(c,x0+mw*.24f,gy,rad,Math.min(1,rpm/8000f),true);gauge(c,x0+mw*.76f,gy,rad,Math.min(1,speed/240f),false);car(c,x0+mw*.5f,gy-rad*.18f,.95f);tx(c,"D",x0+mw*.5f,gy-rad*.48f,22,Color.WHITE,Paint.Align.CENTER,true);
    float sy=h*.68f,g=5,cw=(mw-3*g)/4;stat(c,x0,sy,x0+cw,h-7,"T","دمای آب",temp+" °C",RED);stat(c,x0+cw+g,sy,x0+2*cw+g,h-7,"F","سوخت","48 %",AMBER);stat(c,x0+2*(cw+g),sy,x0+3*cw+2*g,h-7,"V","باتری","13.8 V",GREEN);stat(c,x0+3*(cw+g),sy,w-7,h-7,"P","سطح آب","مناسب",GREEN);
   }
  }
  public boolean onTouchEvent(android.view.MotionEvent e){if(e.getAction()!=MotionEvent.ACTION_UP)return true;float y=e.getY(),x=e.getX(),w=getWidth(),h=getHeight();if(w<=h&&y>h-80){if(x>w*.75f)render("تنظیمات");else if(x>w*.5f)render("داده زنده");else if(x>w*.25f)render("اتصال");return true;}if(w<=h&&y>520&&y<650){if(x>w*.5f)render("دیاگ");else render("داده زنده");return true;}return true;}
 } class GaugeView extends View{boolean r;Paint p=new Paint(1);GaugeView(Context c,boolean rpmGauge){super(c);r=rpmGauge;setLayerType(View.LAYER_TYPE_SOFTWARE,null);}
  protected void onDraw(Canvas c){super.onDraw(c);float w=getWidth(),hh=getHeight(),rad=Math.min(w,hh)*.40f,cx=w/2,cy=hh*.55f;p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(13);p.setStrokeCap(Paint.Cap.ROUND);p.setColor(Color.rgb(17,42,62));c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,140,260,false,p);p.setShadowLayer(18,0,0,CYAN);p.setColor(CYAN);float val=r?rpm/8000f:speed/240f;c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,140,260*Math.max(.02f,val),false,p);p.clearShadowLayer();p.setColor(RED);c.drawArc(cx-rad,cy-rad,cx+rad,cy+rad,350,50,false,p);p.setStyle(Paint.Style.FILL);p.setTextAlign(Paint.Align.CENTER);p.setTypeface(Typeface.DEFAULT_BOLD);p.setColor(Color.WHITE);p.setTextSize(rad*.43f);c.drawText(r?String.valueOf(rpm):String.valueOf(speed),cx,cy+rad*.12f,p);p.setTextSize(rad*.18f);p.setColor(MUTED);c.drawText(r?"RPM":"km/h",cx,cy+rad*.48f,p);p.setTextSize(rad*.15f);p.setColor(Color.WHITE);for(int i=0;i<=8;i++){double a=Math.toRadians(140+i*32.5);float x=(float)(cx+Math.cos(a)*rad*.72),y=(float)(cy+Math.sin(a)*rad*.72);c.drawText(r?""+i:""+i*30,x,y,p);}}
 }
 void diag(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("دیاگ و کدهای خطا (DTC)",26,Color.WHITE));Button read=button("خواندن خطاهای ECU");Button clear=button("پاک کردن خطاها");logText=txt("در حالت واقعی پس از اتصال، پاسخ ECU اینجا نمایش داده می‌شود.",17,MUTED);read.setOnClickListener(v->send("READ_DTC"));clear.setOnClickListener(v->new AlertDialog.Builder(this).setTitle("پاک کردن DTC").setMessage("پاک‌کردن خطا ممکن است Freeze Frame و Readiness را نیز پاک کند. ادامه؟").setNegativeButton("خیر",null).setPositiveButton("بله",(d,w)->send("CLEAR_DTC")).show());body.addView(read);body.addView(clear);body.addView(logText,new LinearLayout.LayoutParams(-1,0,1));}
 void live(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("داده‌های زنده ECU",26,Color.WHITE));String[] x={"RPM","سرعت خودرو","دمای آب","سطح مخزن","وضعیت پروتکل","ولتاژ/سوخت در صورت پشتیبانی ECU"};for(String s:x){LinearLayout p=panel();p.addView(txt(s,18,MUTED));p.addView(txt("دریافت زنده / وابسته به پشتیبانی ECU",19,CYAN));body.addView(p,new LinearLayout.LayoutParams(-1,0,1));}}
 void connectPage(){
  body.setOrientation(LinearLayout.VERTICAL);
  body.addView(txt("اتصال به SMART OBD",26,Color.WHITE));
  body.addView(txt(demo?"حالت دمو فعال است؛ برای اتصال واقعی، حالت واقعی را فعال کنید.":"BLE 5 • بدون Pair کردن • مخصوص ESP32-C3",16,demo?AMBER:GREEN));
  Button mode=button(demo?"فعال کردن حالت واقعی":"بازگشت به حالت دمو");
  mode.setOnClickListener(v->{demo=!demo;if(demo)disconnect();render("اتصال");});
  body.addView(mode);
  Button con=button(bleConnected?"قطع اتصال BLE":"جستجو و اتصال به ESP32-C3");
  con.setEnabled(!demo);
  con.setOnClickListener(v->{if(bleConnected){disconnect();render("اتصال");}else choose();});
  body.addView(con);
  Button redetect=button("تشخیص دوباره پروتکل خودرو");
  redetect.setEnabled(!demo&&bleConnected);
  redetect.setOnClickListener(v->send("REDETECT"));
  body.addView(redetect);
  body.addView(txt("نام دستگاه: KhanehRemap-OBD-C3\nCAN: تشخیص خودکار 500/250 kbps • K-Line: ISO9141/KWP2000",15,MUTED));
 }
 void settingsPage(){body.setOrientation(LinearLayout.VERTICAL);body.addView(txt("تنظیمات",27,Color.WHITE));Button sp=button("تست هشدار صوتی کمبود آب");sp.setOnClickListener(v->speak("هشدار، سطح آب خنک کننده پایین است"));body.addView(sp);Button bt=button("تنظیمات بلوتوث گوشی");bt.setOnClickListener(v->startActivity(new Intent(Settings.ACTION_BLUETOOTH_SETTINGS)));body.addView(bt);body.addView(txt("BLE مخصوص ESP32-C3 • CAN 500/250 خودکار • K-Line • DTC • هشدار صوتی سطح آب\nچرخش صفحه: خودکار • عمودی و افقی رسپانسیو",16,MUTED));}
 void demoLoop(){h.postDelayed(new Runnable(){public void run(){if(!alive)return;if(demo){rpm+=up?180:-160;if(rpm>5200)up=false;if(rpm<850)up=true;speed=Math.max(0,(rpm-600)/34);temp=88+(rpm/900)%5;apply("RPM:"+rpm);apply("SPEED:"+speed);apply("ECT:"+temp);apply("COOLANT:OK");}invalidateGauges(root);h.postDelayed(this,500);}},500);}
 void invalidateGauges(View v){if(v instanceof GaugeView || v instanceof ClusterView)v.invalidate();if(v instanceof ViewGroup){ViewGroup g=(ViewGroup)v;for(int i=0;i<g.getChildCount();i++)invalidateGauges(g.getChildAt(i));}}
 void choose(){
  askBt();
  BluetoothManager bm=(BluetoothManager)getSystemService(BLUETOOTH_SERVICE);
  BluetoothAdapter a=bm==null?null:bm.getAdapter();
  if(a==null){toast("بلوتوث در دسترس نیست");return;}
  if(Build.VERSION.SDK_INT>=31&&checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)!=PackageManager.PERMISSION_GRANTED){askBt();return;}
  if(!a.isEnabled()){startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));return;}
  bleScanner=a.getBluetoothLeScanner();
  if(bleScanner==null){toast("BLE Scanner در دسترس نیست");return;}
  ArrayList<BluetoothDevice> devices=new ArrayList<>(); ArrayList<String> names=new ArrayList<>();
  if(status!=null){status.setText("در حال جستجوی SMART OBD…");status.setTextColor(AMBER);}
  scanCallback=new ScanCallback(){
   public void onScanResult(int callbackType,ScanResult result){
    BluetoothDevice d=result.getDevice(); if(d==null)return;
    String name=null; try{name=d.getName();}catch(Exception ignored){}
    boolean ours=name!=null&&(name.contains("KhanehRemap")||name.contains("OBD-C3"));
    if(!ours&&result.getScanRecord()!=null&&result.getScanRecord().getServiceUuids()!=null){
     for(ParcelUuid u:result.getScanRecord().getServiceUuids())if(BLE_SERVICE.equals(u.getUuid()))ours=true;
    }
    if(ours&&!devices.contains(d)){devices.add(d);names.add((name==null?"SMART OBD":name)+"\n"+d.getAddress());}
   }
   public void onScanFailed(int errorCode){runOnUiThread(()->{toast("خطای جستجوی BLE: "+errorCode);if(status!=null)status.setText("جستجو ناموفق");});}
  };
  bleScanner.startScan(scanCallback);
  h.postDelayed(()->{
   try{bleScanner.stopScan(scanCallback);}catch(Exception ignored){}
   if(devices.isEmpty()){if(status!=null)status.setText("SMART OBD پیدا نشد");toast("دستگاه پیدا نشد؛ ESP32-C3 روشن و نزدیک گوشی باشد.");return;}
   new AlertDialog.Builder(this).setTitle("انتخاب SMART OBD").setItems(names.toArray(new String[0]),(dlg,i)->connect(devices.get(i))).show();
  },5000);
 }
 void connect(BluetoothDevice d){
  if(status!=null){status.setText("در حال اتصال BLE…");status.setTextColor(AMBER);}
  try{
   if(Build.VERSION.SDK_INT>=31&&checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED){askBt();return;}
   gatt=d.connectGatt(this,false,new BluetoothGattCallback(){
    @Override public void onConnectionStateChange(BluetoothGatt g,int st,int ns){
     if(ns==BluetoothProfile.STATE_CONNECTED){
      bleConnected=true; try{g.requestMtu(185);}catch(Exception ignored){}
      g.discoverServices();
      runOnUiThread(()->{if(status!=null){status.setText("● BLE متصل • در حال آماده‌سازی");status.setTextColor(GREEN);}});
     }else if(ns==BluetoothProfile.STATE_DISCONNECTED){
      bleConnected=false;cmdCh=null;dataCh=null;
      runOnUiThread(()->{if(status!=null){status.setText("ارتباط BLE قطع شد");status.setTextColor(RED);}invalidateGauges(root);});
     }
    }
    @Override public void onServicesDiscovered(BluetoothGatt g,int st){
     BluetoothGattService svc=g.getService(BLE_SERVICE);
     if(svc==null){runOnUiThread(()->toast("سرویس SMART OBD روی دستگاه پیدا نشد"));return;}
     cmdCh=svc.getCharacteristic(BLE_CMD); dataCh=svc.getCharacteristic(BLE_DATA);
     if(dataCh!=null){
      g.setCharacteristicNotification(dataCh,true);
      BluetoothGattDescriptor desc=dataCh.getDescriptor(CCCD);
      if(desc!=null){desc.setValue(BluetoothGattDescriptor.ENABLE_NOTIFICATION_VALUE);g.writeDescriptor(desc);}
     }
     runOnUiThread(()->{if(status!=null){status.setText("● LIVE • متصل به "+safeName(d));status.setTextColor(GREEN);}toast("SMART OBD متصل شد");});
     h.postDelayed(()->send("STATUS"),450);
    }
    @Override public void onCharacteristicChanged(BluetoothGatt g,BluetoothGattCharacteristic ch){
     if(BLE_DATA.equals(ch.getUuid())){
      String line=new String(ch.getValue(),java.nio.charset.StandardCharsets.UTF_8).trim();
      runOnUiThread(()->apply(line));
     }
    }
   },BluetoothDevice.TRANSPORT_LE);
  }catch(Exception e){toast("اتصال ناموفق: "+e.getMessage());}
 }
 String safeName(BluetoothDevice d){try{String n=d.getName();return n==null?"ESP32-C3":n;}catch(Exception e){return "ESP32-C3";}}
 void send(String s){
  if(demo){if(logText!=null)logText.setText("دمو: "+s);return;}
  if(!bleConnected||gatt==null||cmdCh==null){toast("ابتدا به SMART OBD متصل شوید");return;}
  try{
   cmdCh.setWriteType(BluetoothGattCharacteristic.WRITE_TYPE_NO_RESPONSE);
   cmdCh.setValue(s.getBytes(java.nio.charset.StandardCharsets.UTF_8));
   if(!gatt.writeCharacteristic(cmdCh))toast("ارسال فرمان ناموفق بود");
  }catch(Exception e){toast("ارسال ناموفق");}
 }
 void apply(String s){
  try{
   if(s==null)return; s=s.trim();
   if(s.startsWith("RPM:"))rpm=Integer.parseInt(s.substring(4).trim());
   else if(s.startsWith("SPEED:"))speed=Integer.parseInt(s.substring(6).trim());
   else if(s.startsWith("ECT:")){temp=Integer.parseInt(s.substring(4).trim());if(tempText!=null)tempText.setText(temp+" °C");}
   else if(s.startsWith("FUEL:")){fuel=Float.parseFloat(s.substring(5).trim());if(fuelText!=null)fuelText.setText(String.format(Locale.US,"%.0f %%",fuel));}
   else if(s.startsWith("VOLT:")){batt=Float.parseFloat(s.substring(5).trim());if(battText!=null)battText.setText(String.format(Locale.US,"%.1f V",batt));}
   else if(s.equals("COOLANT:OK")){coolantState="مناسب";if(coolText!=null){coolText.setText("مناسب");coolText.setTextColor(GREEN);}}
   else if(s.startsWith("COOLANT:CHECK")){coolantState="در حال بررسی";if(coolText!=null){coolText.setText("بررسی");coolText.setTextColor(AMBER);}}
   else if(s.startsWith("ALARM:LOW_COOLANT")){coolantState="کمبود آب";if(coolText!=null){coolText.setText("کمبود آب");coolText.setTextColor(RED);}speak("هشدار، سطح آب خنک کننده پایین است");}
   else if(s.startsWith("ECU:CONNECTED")){
    protocol=s.replace("ECU:CONNECTED,","");
    if(ecuText!=null)ecuText.setText("متصل • "+protocol);
    if(status!=null){status.setText("● LIVE • "+protocol);status.setTextColor(GREEN);}
   }
   else if(s.startsWith("STATUS,")){
    int p=s.indexOf("PROTO:"); if(p>=0){int e=s.indexOf(",",p);protocol=s.substring(p+6,e<0?s.length():e);}
    if(logText!=null)logText.append("\n"+s);
   }
   else if(s.startsWith("DTC:")){
    if(logText!=null){
     if(s.equals("DTC:NONE"))logText.setText("هیچ کد خطای فعالی گزارش نشد.");
     else logText.setText("نتیجه ECU:\n"+s.substring(4).replace(",","\n"));
    }
   }
   else if(s.startsWith("CLEAR_DTC:")&&logText!=null)logText.setText(s.equals("CLEAR_DTC:SENT")?"فرمان پاک‌کردن خطا ارسال شد.":"پاک‌کردن خطا انجام نشد: "+s);
   else if(logText!=null)logText.append("\n"+s);
  }catch(Exception ignored){}
  invalidateGauges(root);
 }
 void speak(String s){if(tts!=null)tts.speak(s,TextToSpeech.QUEUE_FLUSH,null,"alarm");}
 void disconnect(){
  try{if(bleScanner!=null&&scanCallback!=null)bleScanner.stopScan(scanCallback);}catch(Exception ignored){}
  try{if(gatt!=null){gatt.disconnect();gatt.close();}}catch(Exception ignored){}
  gatt=null;cmdCh=null;dataCh=null;bleConnected=false;protocol="NONE";
 }
 void toast(String s){Toast.makeText(this,s==null?"خطا":s,Toast.LENGTH_SHORT).show();}
 @Override public void onConfigurationChanged(Configuration c){super.onConfigurationChanged(c);render("خانه");}
 @Override protected void onDestroy(){alive=false;disconnect();if(tts!=null){tts.stop();tts.shutdown();}super.onDestroy();}
}