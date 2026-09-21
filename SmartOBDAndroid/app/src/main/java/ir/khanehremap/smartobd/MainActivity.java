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
 void base(){root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setBackgroundColor(BG);root.setPadding(10,7,10,8);setContentView(root);
  LinearLayout head=new LinearLayout(this);head.setGravity(Gravity.CENTER_VERTICAL);TextView brand=txt("خانه ریمپ\nSMART OBD",20,Color.WHITE);brand.setGravity(Gravity.RIGHT);brand.setTypeface(null,Typeface.BOLD);head.addView(brand,new LinearLayout.LayoutParams(0,-2,1));
  modeChip=txt(demo?"● دمو":"● واقعی",15,demo?CYAN:GREEN);modeChip.setBackground(box(PANEL,demo?CYAN:GREEN,24));head.addView(modeChip);root.addView(head);
  status=txt(demo?"حالت آزمایشی فعال است":"آماده اتصال به ESP32_OBD",14,MUTED);status.setGravity(Gravity.RIGHT);root.addView(status);
  body=new LinearLayout(this);root.addView(body,new LinearLayout.LayoutParams(-1,0,1));
 }
 void render(String page){base();boolean land=getResources().getConfiguration().orientation==Configuration.ORIENTATION_LANDSCAPE;if(page.equals("خانه"))dashboard(land);else if(page.equals("دیاگ"))diag();else if(page.equals("داده زنده"))live();else if(page.equals("اتصال"))connectPage();else settingsPage();nav();}
 void nav(){LinearLayout n=new LinearLayout(this);String[] a={"خانه","دیاگ","داده زنده","اتصال","تنظیمات"};for(String s:a){Button b=button(s);b.setOnClickListener(v->render(s));n.addView(b,new LinearLayout.LayoutParams(0,55,1));}root.addView(n);}
 LinearLayout panel(){LinearLayout p=new LinearLayout(this);p.setOrientation(LinearLayout.VERTICAL);p.setGravity(Gravity.CENTER);p.setPadding(8,8,8,8);p.setBackground(box(PANEL,Color.rgb(24,52,75),24));return p;}
 void dashboard(boolean land){
  body.setOrientation(LinearLayout.VERTICAL);
  body.removeAllViews();
  body.addView(new ClusterView(this),new LinearLayout.LayoutParams(-1,-1));
 }
 TextView stat(LinearLayout row,String icon,String name,String value,int color){LinearLayout p=panel();p.addView(txt(icon,21,color));p.addView(txt(name,12,MUTED));TextView v=txt(value,18,color);v.setTypeface(null,Typeface.BOLD);p.addView(v);row.addView(p,new LinearLayout.LayoutParams(0,-1,1));return v;}
 class ClusterView extends View{
  Paint p=new Paint(Paint.ANTI_ALIAS_FLAG);
  RectF r=new RectF();
  ClusterView(Context x){super(x);setLayerType(View.LAYER_TYPE_SOFTWARE,null);}
  void text(Canvas c,String s,float x,float y,float size,int color,Paint.Align align,boolean bold){
    p.setStyle(Paint.Style.FILL);p.setColor(color);p.setTextSize(size);p.setTextAlign(align);
    p.setTypeface(bold?Typeface.DEFAULT_BOLD:Typeface.DEFAULT);p.clearShadowLayer();c.drawText(s,x,y,p);
  }
  void round(Canvas c,float l,float t,float rr,float bb,float radius,int fill,int stroke){
    p.setStyle(Paint.Style.FILL);p.setColor(fill);p.clearShadowLayer();c.drawRoundRect(l,t,rr,bb,radius,radius,p);
    if(stroke!=0){p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(2);p.setColor(stroke);c.drawRoundRect(l,t,rr,bb,radius,radius,p);}
  }
  void gauge(Canvas c,float cx,float cy,float rad,float fraction,boolean isRpm){
    p.setStyle(Paint.Style.STROKE);p.setStrokeCap(Paint.Cap.ROUND);p.setStrokeWidth(rad*.105f);
    p.setColor(Color.rgb(14,39,61));p.clearShadowLayer();r.set(cx-rad,cy-rad,cx+rad,cy+rad);c.drawArc(r,140,260,false,p);
    p.setShadowLayer(rad*.10f,0,0,CYAN);p.setColor(CYAN);c.drawArc(r,140,Math.max(4,260*Math.min(1,fraction)),false,p);
    p.setShadowLayer(rad*.08f,0,0,RED);p.setColor(RED);c.drawArc(r,350,50,false,p);p.clearShadowLayer();
    p.setStrokeWidth(2);p.setColor(Color.rgb(190,220,240));
    int max=isRpm?8:240, step=isRpm?1:30;
    for(int v=0;v<=max;v+=step){
      float f=(float)v/max, ang=(float)Math.toRadians(140+260*f);
      float x1=(float)(cx+Math.cos(ang)*rad*.74),y1=(float)(cy+Math.sin(ang)*rad*.74);
      float x2=(float)(cx+Math.cos(ang)*rad*.86),y2=(float)(cy+Math.sin(ang)*rad*.86);
      c.drawLine(x1,y1,x2,y2,p);
      text(c,String.valueOf(v),(float)(cx+Math.cos(ang)*rad*.61),(float)(cy+Math.sin(ang)*rad*.61)+5,rad*.105f,Color.WHITE,Paint.Align.CENTER,false);
    }
    String value=isRpm?String.valueOf(rpm):String.valueOf(speed);
    text(c,value,cx,cy+rad*.10f,rad*.34f,Color.WHITE,Paint.Align.CENTER,true);
    text(c,isRpm?"RPM":"km/h",cx,cy+rad*.37f,rad*.13f,MUTED,Paint.Align.CENTER,true);
  }
  void card(Canvas c,float l,float t,float rr,float b,String title,String val,int accent){
    round(c,l,t,rr,b,20,Color.rgb(7,23,37),Color.rgb(25,63,88));
    p.setStyle(Paint.Style.FILL);p.setColor(accent);c.drawRoundRect(l+10,t+12,l+16,b-12,5,5,p);
    text(c,title,rr-16,t+(b-t)*.40f,Math.min(29,(b-t)*.23f),MUTED,Paint.Align.RIGHT,false);
    text(c,val,rr-16,t+(b-t)*.75f,Math.min(34,(b-t)*.29f),accent,Paint.Align.RIGHT,true);
  }
  protected void onDraw(Canvas c){
    super.onDraw(c);float w=getWidth(),h=getHeight();boolean land=w>h;
    c.drawColor(BG);
    if(land){
      float side=Math.min(245,w*.21f),mainW=w-side-12;
      round(c,0,0,side,h,22,Color.rgb(5,17,29),Color.rgb(20,55,80));
      text(c,"خانه ریمپ",side/2,42,27,Color.WHITE,Paint.Align.CENTER,true);
      text(c,"SMART OBD",side/2,69,19,CYAN,Paint.Align.CENTER,true);
      String[] m={"داشبورد","دیاگ و DTC","داده‌های زنده","اطلاعات ECU","گزارش","تنظیمات"};
      for(int i=0;i<m.length;i++){float y=98+i*55;round(c,12,y,side-12,y+44,13,i==0?Color.rgb(4,58,98):Color.rgb(8,25,40),i==0?CYAN:Color.rgb(28,57,76));text(c,m[i],side-25,y+29,16,Color.WHITE,Paint.Align.RIGHT,i==0);}
      float x0=side+14,cx1=x0+mainW*.27f,cx2=x0+mainW*.73f,cy=h*.34f,rad=Math.min(mainW*.22f,h*.30f);
      gauge(c,cx1,cy,rad,Math.min(1,rpm/8000f),true);gauge(c,cx2,cy,rad,Math.min(1,speed/240f),false);
      text(c,"🚘",x0+mainW*.5f,cy+10,52,CYAN,Paint.Align.CENTER,true);
      text(c,demo?"DEMO":"LIVE",x0+mainW*.5f,cy+52,17,demo?AMBER:GREEN,Paint.Align.CENTER,true);
      float top=h*.64f,gap=8,cw=(mainW-gap*3)/4;
      card(c,x0,top,x0+cw,top+90,"دمای آب",temp+" °C",RED);
      card(c,x0+cw+gap,top,x0+2*cw+gap,top+90,"سوخت","48 %",AMBER);
      card(c,x0+2*(cw+gap),top,x0+3*cw+2*gap,top+90,"سطح آب","مناسب",GREEN);
      card(c,x0+3*(cw+gap),top,x0+4*cw+3*gap,top+90,"باتری","13.8 V",GREEN);
      text(c,"ECU  •  CAN 500 kbps  •  موتور سالم",x0+mainW/2,h-28,18,GREEN,Paint.Align.CENTER,true);
    }else{
      // Portrait: use the whole available dashboard area, with large primary instruments.
      float pad=10f;
      float available=h-4f;
      float gaugeTop=4f;
      float gaugeH=Math.min(w*.64f,available*.35f);
      float rad=Math.min(w*.245f,gaugeH*.46f);
      float cy=gaugeTop+gaugeH*.52f;
      gauge(c,w*.255f,cy,rad,Math.min(1,rpm/8000f),true);
      gauge(c,w*.745f,cy,rad,Math.min(1,speed/240f),false);

      float heroTop=gaugeTop+gaugeH+4f;
      float heroH=Math.max(92f,available*.13f);
      round(c,pad,heroTop,w-pad,heroTop+heroH,24,Color.rgb(6,22,36),Color.rgb(20,68,98));
      text(c,"🚘",w*.50f,heroTop+heroH*.46f,Math.min(48,heroH*.42f),CYAN,Paint.Align.CENTER,true);
      text(c,demo?"DEMO":"LIVE",w*.50f,heroTop+heroH*.76f,15,demo?AMBER:GREEN,Paint.Align.CENTER,true);
      text(c,"ECU",w-pad-18,heroTop+heroH*.33f,14,MUTED,Paint.Align.RIGHT,false);
      text(c,"CAN 500 kbps",w-pad-18,heroTop+heroH*.60f,16,GREEN,Paint.Align.RIGHT,true);
      text(c,"موتور سالم",pad+18,heroTop+heroH*.48f,17,GREEN,Paint.Align.LEFT,true);

      float cardsTop=heroTop+heroH+10f;
      float bottomBarH=58f;
      float cardsAvail=Math.max(220f,available-cardsTop-bottomBarH-10f);
      float rowH=(cardsAvail-10f)/2f;
      float gap=9f,cw=(w-pad*2-gap)/2f;
      card(c,pad,cardsTop,pad+cw,cardsTop+rowH,"دمای آب",temp+" °C",RED);
      card(c,pad+cw+gap,cardsTop,w-pad,cardsTop+rowH,"سوخت","48 %",AMBER);
      float row2=cardsTop+rowH+10f;
      card(c,pad,row2,pad+cw,row2+rowH,"سطح آب","مناسب",GREEN);
      card(c,pad+cw+gap,row2,w-pad,row2+rowH,"باتری","13.8 V",GREEN);

      float barY=Math.min(available-bottomBarH,row2+rowH+8f);
      round(c,pad,barY,w-pad,barY+bottomBarH,20,Color.rgb(4,30,42),GREEN);
      text(c,"●  ارتباط ECU برقرار    •    داده زنده",w/2,barY+36,17,GREEN,Paint.Align.CENTER,true);

    }
  }
 } class GaugeView extends View{boolean r;Paint p=new Paint(1);GaugeView(Context c,boolean rpmGauge){super(c);r=rpmGauge;setLayerType(View.LAYER_TYPE_SOFTWARE,null);}
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