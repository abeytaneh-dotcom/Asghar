package ir.khanehremap.smartobd;

import android.Manifest;
import android.app.*;
import android.bluetooth.*;
import android.content.*;
import android.content.pm.PackageManager;
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
  final int BG=Color.rgb(7,16,25), CARD=Color.rgb(14,29,42), CYAN=Color.rgb(0,229,255), GREEN=Color.rgb(70,230,140), RED=Color.rgb(255,78,92), MUTED=Color.rgb(150,170,185);
  LinearLayout root, content; TextView conn, modeBadge, rpmV,speedV,tempV,coolV,protoV,logV; Button connectBtn,modeBtn;
  BluetoothSocket socket; BufferedReader in; BufferedWriter out; Thread rxThread; boolean demo=true, running=true; TextToSpeech tts;
  final Handler h=new Handler(Looper.getMainLooper()); int drpm=850, dspeed=0, dtemp=88; boolean rising=true;

  @Override public void onCreate(Bundle b){super.onCreate(b); getWindow().setStatusBarColor(BG); tts=new TextToSpeech(this,s->{if(s==TextToSpeech.SUCCESS)tts.setLanguage(new Locale("fa","IR"));}); askBt(); build();}
  void askBt(){if(Build.VERSION.SDK_INT>=31 && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED) requestPermissions(new String[]{Manifest.permission.BLUETOOTH_CONNECT,Manifest.permission.BLUETOOTH_SCAN},77);}
  TextView tv(String s,int sp,int color){TextView v=new TextView(this);v.setText(s);v.setTextSize(sp);v.setTextColor(color);v.setGravity(Gravity.CENTER);v.setPadding(12,10,12,10);return v;}
  GradientDrawable bg(int color,int stroke){GradientDrawable d=new GradientDrawable();d.setColor(color);d.setCornerRadius(28);if(stroke!=0)d.setStroke(2,stroke);return d;}
  Button btn(String s){Button b=new Button(this);b.setText(s);b.setTextSize(17);b.setTextColor(Color.WHITE);b.setAllCaps(false);b.setBackground(bg(CARD,CYAN));b.setPadding(12,8,12,8);return b;}
  void build(){root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setBackgroundColor(BG);root.setPadding(18,12,18,12);setContentView(root);
    LinearLayout top=new LinearLayout(this);top.setGravity(Gravity.CENTER_VERTICAL);top.setOrientation(LinearLayout.HORIZONTAL);
    TextView title=tv("SMART OBD  •  خانه ریمپ",22,Color.WHITE);title.setGravity(Gravity.RIGHT|Gravity.CENTER_VERTICAL);top.addView(title,new LinearLayout.LayoutParams(0,-2,1));
    modeBadge=tv("",15,CYAN);top.addView(modeBadge);root.addView(top);
    conn=tv("آماده",16,MUTED);conn.setGravity(Gravity.RIGHT);root.addView(conn);
    content=new LinearLayout(this);content.setPadding(0,8,0,8);root.addView(content,new LinearLayout.LayoutParams(-1,0,1));
    LinearLayout nav=new LinearLayout(this);nav.setGravity(Gravity.CENTER); String[] ns={"داشبورد","دیاگ","اتصال","تنظیمات"};for(String n:ns){Button x=btn(n);x.setOnClickListener(v->page(n));nav.addView(x,new LinearLayout.LayoutParams(0,-2,1));}root.addView(nav);
    page("داشبورد"); startDemo(); updateMode();
  }
  void page(String p){content.removeAllViews(); boolean land=getResources().getConfiguration().orientation==2;content.setOrientation(land?LinearLayout.HORIZONTAL:LinearLayout.VERTICAL);
    if(p.equals("داشبورد")) dashboard(land); else if(p.equals("دیاگ")) diag(); else if(p.equals("اتصال")) connection(); else settingsPage();
  }
  LinearLayout card(String label){LinearLayout c=new LinearLayout(this);c.setOrientation(LinearLayout.VERTICAL);c.setGravity(Gravity.CENTER);c.setPadding(12,14,12,14);c.setBackground(bg(CARD,0));TextView l=tv(label,16,MUTED);c.addView(l);return c;}
  TextView value(LinearLayout c,String unit){TextView v=tv("-- "+unit,34,Color.WHITE);v.setTypeface(null,Typeface.BOLD);c.addView(v,new LinearLayout.LayoutParams(-1,0,1));return v;}
  void dashboard(boolean land){LinearLayout gauges=new LinearLayout(this);gauges.setOrientation(LinearLayout.HORIZONTAL);LinearLayout a=card("دور موتور");rpmV=value(a,"RPM");LinearLayout b=card("سرعت");speedV=value(b,"km/h");gauges.addView(a,new LinearLayout.LayoutParams(0,land?-1:230,1));space(gauges);gauges.addView(b,new LinearLayout.LayoutParams(0,land?-1:230,1));content.addView(gauges,new LinearLayout.LayoutParams(land?0:-1,land?-1:-2,land?1:0));
    LinearLayout stats=new LinearLayout(this);stats.setOrientation(LinearLayout.VERTICAL);tempV=small(stats,"دمای آب","-- °C");coolV=small(stats,"سطح آب","--");protoV=small(stats,"ارتباط ECU","--");content.addView(stats,new LinearLayout.LayoutParams(land?0:-1,land?-1:0,land?1:1));
  }
  void space(LinearLayout l){Space s=new Space(this);l.addView(s,new LinearLayout.LayoutParams(10,1));}
  TextView small(LinearLayout p,String name,String val){LinearLayout c=card(name);TextView v=tv(val,25,Color.WHITE);v.setTypeface(null,Typeface.BOLD);c.addView(v);p.addView(c,new LinearLayout.LayoutParams(-1,0,1));Space s=new Space(this);p.addView(s,new LinearLayout.LayoutParams(1,8));return v;}
  void diag(){content.setOrientation(LinearLayout.VERTICAL);TextView t=tv("عیب‌یابی ECU",28,Color.WHITE);content.addView(t);Button read=btn("خواندن خطاها");Button clear=btn("پاک کردن خطاها");logV=tv("برای خواندن خطاها، ابتدا در حالت واقعی متصل شوید.",18,MUTED);read.setOnClickListener(v->send("READ_DTC"));clear.setOnClickListener(v->new AlertDialog.Builder(this).setTitle("پاک کردن خطاها").setMessage("این فرمان می‌تواند Freeze Frame و وضعیت Readiness را نیز پاک کند. ادامه می‌دهید؟").setNegativeButton("خیر",null).setPositiveButton("بله",(d,w)->send("CLEAR_DTC")).show());content.addView(read);content.addView(clear);content.addView(logV,new LinearLayout.LayoutParams(-1,0,1));}
  void connection(){content.setOrientation(LinearLayout.VERTICAL);modeBtn=btn(demo?"تغییر به حالت واقعی":"تغییر به حالت دمو");modeBtn.setOnClickListener(v->{demo=!demo;if(demo)disconnect();updateMode();connection();});content.addView(modeBtn);connectBtn=btn("اتصال به KhanehRemap-OBD");connectBtn.setEnabled(!demo);connectBtn.setOnClickListener(v->chooseDevice());content.addView(connectBtn);TextView hint=tv("حالت واقعی: Bluetooth Classic SPP • نام پیش‌فرض ESP32: KhanehRemap-OBD",17,MUTED);content.addView(hint);}
  void settingsPage(){content.setOrientation(LinearLayout.VERTICAL);content.addView(tv("تنظیمات",28,Color.WHITE));Button speak=btn("تست هشدار صوتی فارسی");speak.setOnClickListener(v->speak("هشدار، سطح آب خنک کننده پایین است"));content.addView(speak);Button bt=btn("باز کردن تنظیمات بلوتوث");bt.setOnClickListener(v->startActivity(new Intent(Settings.ACTION_BLUETOOTH_SETTINGS)));content.addView(bt);content.addView(tv("صفحه با چرخش گوشی بین حالت عمودی و افقی بازچینی می‌شود.",17,MUTED));}
  void updateMode(){modeBadge.setText(demo?"● دمو":"● واقعی");modeBadge.setTextColor(demo?CYAN:GREEN);conn.setText(demo?"حالت آزمایشی فعال است":"حالت واقعی • آماده اتصال");}
  void startDemo(){h.postDelayed(new Runnable(){public void run(){if(!running)return;if(demo){drpm+=rising?173:-151;if(drpm>4300)rising=false;if(drpm<850)rising=true;dspeed=Math.max(0,(drpm-700)/32);dtemp=87+(drpm/700)%5;apply("RPM:"+drpm);apply("SPEED:"+dspeed);apply("ECT:"+dtemp);apply("COOLANT:OK");apply("ECU:CONNECTED,CAN:500");}h.postDelayed(this,700);}},700);}
  void chooseDevice(){if(Build.VERSION.SDK_INT>=31 && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED){askBt();return;}BluetoothAdapter a=BluetoothAdapter.getDefaultAdapter();if(a==null){toast("این گوشی بلوتوث ندارد");return;}if(!a.isEnabled()){startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));return;}ArrayList<BluetoothDevice> ds=new ArrayList<>(a.getBondedDevices());if(ds.isEmpty()){toast("ابتدا ESP32 را در تنظیمات بلوتوث Pair کنید");return;}String[] names=new String[ds.size()];for(int i=0;i<ds.size();i++)names[i]=(ds.get(i).getName()==null?"Bluetooth":ds.get(i).getName())+"\n"+ds.get(i).getAddress();new AlertDialog.Builder(this).setTitle("انتخاب دستگاه").setItems(names,(d,i)->connect(ds.get(i))).show();}
  void connect(BluetoothDevice d){conn.setText("در حال اتصال به "+d.getName()+"…");Executors.newSingleThreadExecutor().execute(()->{try{socket=d.createRfcommSocketToServiceRecord(SPP);socket.connect();in=new BufferedReader(new InputStreamReader(socket.getInputStream()));out=new BufferedWriter(new OutputStreamWriter(socket.getOutputStream()));runOnUiThread(()->{conn.setText("● متصل: "+d.getName());conn.setTextColor(GREEN);toast("اتصال برقرار شد");});rxThread=new Thread(()->{try{String s;while(socket!=null&&socket.isConnected()&&(s=in.readLine())!=null){final String q=s;runOnUiThread(()->apply(q));}}catch(Exception e){runOnUiThread(()->conn.setText("ارتباط قطع شد"));}});rxThread.start();send("STATUS");}catch(Exception e){runOnUiThread(()->{conn.setText("اتصال ناموفق");toast(e.getMessage());});}});}
  void send(String s){if(demo){if(logV!=null)logV.setText("در حالت دمو فرمان «"+s+"» شبیه‌سازی شد.");return;}try{if(out==null){toast("ابتدا متصل شوید");return;}out.write(s+"\n");out.flush();}catch(Exception e){toast("ارسال فرمان ناموفق");}}
  void apply(String s){try{if(s.startsWith("RPM:")&&rpmV!=null)rpmV.setText(s.substring(4)+" RPM");else if(s.startsWith("SPEED:")&&speedV!=null)speedV.setText(s.substring(6)+" km/h");else if(s.startsWith("ECT:")&&tempV!=null)tempV.setText(s.substring(4)+" °C");else if(s.equals("COOLANT:OK")&&coolV!=null){coolV.setText("مناسب");coolV.setTextColor(GREEN);}else if(s.startsWith("ALARM:LOW_COOLANT")){if(coolV!=null){coolV.setText("کمبود آب");coolV.setTextColor(RED);}speak("هشدار، سطح آب خنک کننده پایین است");}else if(s.startsWith("ECU:CONNECTED")&&protoV!=null)protoV.setText(s.replace("ECU:CONNECTED,",""));else if(logV!=null)logV.append("\n"+s);}catch(Exception ignored){}}
  void speak(String s){if(tts!=null)tts.speak(s,TextToSpeech.QUEUE_FLUSH,null,"coolant");}
  void disconnect(){try{if(socket!=null)socket.close();}catch(Exception ignored){}socket=null;in=null;out=null;conn.setText("حالت دمو");conn.setTextColor(MUTED);}
  void toast(String s){Toast.makeText(this,s==null?"خطا":s,Toast.LENGTH_SHORT).show();}
  @Override public void onConfigurationChanged(android.content.res.Configuration c){super.onConfigurationChanged(c);build();}
  @Override protected void onDestroy(){running=false;disconnect();if(tts!=null){tts.stop();tts.shutdown();}super.onDestroy();}
}