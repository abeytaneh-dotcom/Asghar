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
import android.view.inputmethod.InputMethodManager;
import android.widget.*;

import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.concurrent.*;

public class MainActivity extends Activity {
    static final UUID BLE_SERVICE = UUID.fromString("7f640101-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID BLE_CMD     = UUID.fromString("7f640102-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID BLE_DATA    = UUID.fromString("7f640103-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID CCCD        = UUID.fromString("00002902-0000-1000-8000-00805f9b34fb");

    final int BG=Color.rgb(1,12,21), CARD=Color.rgb(8,34,51), CARD2=Color.rgb(10,39,57);
    final int CYAN=Color.rgb(0,215,235), GREEN=Color.rgb(42,220,147), RED=Color.rgb(255,72,96);
    final int AMBER=Color.rgb(255,188,70), MUTED=Color.rgb(122,148,165), WHITE=Color.rgb(238,246,250);

    BluetoothGatt gatt;
    BluetoothGattCharacteristic cmdCh, dataCh;
    BluetoothLeScanner scanner;
    ScanCallback scanCallback;
    BluetoothDevice connectedDevice;
    boolean connected=false, demo=false, soundAlert=true, alive=true, waitingIdentity=false;
    String deviceSerial="", pendingSerial="", protocol="NONE", ecuName="هنوز شناسایی نشده";
    int rpm=0, speed=0, coolant=0;
    float fuel=0, voltage=0;
    String dtcText="برای بررسی خودرو «اسکن خطاها» را بزنید";

    Handler h=new Handler(Looper.getMainLooper());
    TextToSpeech tts;
    ProView proView;
    SharedPreferences prefs;
    ExecutorService io=Executors.newSingleThreadExecutor();

    @Override public void onCreate(Bundle b){
        super.onCreate(b);
        getWindow().setStatusBarColor(BG);
        getWindow().setNavigationBarColor(BG);
        prefs=getSharedPreferences("smartobd_serial",MODE_PRIVATE);
        tts=new TextToSpeech(this,s->{if(s==TextToSpeech.SUCCESS)tts.setLanguage(new Locale("fa","IR"));});
        requestBluetoothPermissions();
        proView=new ProView(this);
        setContentView(proView);
    }

    void requestBluetoothPermissions(){
        ArrayList<String> req=new ArrayList<>();
        if(Build.VERSION.SDK_INT>=31){
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)!=PackageManager.PERMISSION_GRANTED) req.add(Manifest.permission.BLUETOOTH_SCAN);
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED) req.add(Manifest.permission.BLUETOOTH_CONNECT);
        } else if(Build.VERSION.SDK_INT>=23 && checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)!=PackageManager.PERMISSION_GRANTED){
            req.add(Manifest.permission.ACCESS_FINE_LOCATION);
        }
        if(!req.isEmpty()) requestPermissions(req.toArray(new String[0]),61);
    }

    boolean btAllowed(){
        if(Build.VERSION.SDK_INT>=31) return checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)==PackageManager.PERMISSION_GRANTED
                && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)==PackageManager.PERMISSION_GRANTED;
        return Build.VERSION.SDK_INT<23 || checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED;
    }

    void beginConnect(){
        if(demo){ toast("حالت دمو را خاموش کنید"); return; }
        pendingSerial=prefs.getString("activated_serial","");
        if(pendingSerial.isEmpty()){
            askSerialThenScan();
        }else scanBle();
    }

    void askSerialThenScan(){
        final EditText e=new EditText(this);
        e.setHint("مثال: KR-26-000001");
        e.setSingleLine(true);
        e.setTextColor(Color.WHITE);
        e.setHintTextColor(MUTED);
        e.setGravity(Gravity.CENTER);
        e.setTextSize(18);
        e.setInputType(android.text.InputType.TYPE_CLASS_TEXT|android.text.InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS);
        int pad=(int)(18*getResources().getDisplayMetrics().density);
        e.setPadding(pad,pad,pad,pad);
        GradientDrawable gd=new GradientDrawable(); gd.setColor(Color.rgb(7,29,44)); gd.setCornerRadius(18); gd.setStroke(2,CYAN); e.setBackground(gd);

        FrameLayout wrap=new FrameLayout(this);
        wrap.setPadding(pad,pad/2,pad,pad/2);
        wrap.addView(e,new FrameLayout.LayoutParams(-1,-2));

        AlertDialog d=new AlertDialog.Builder(this)
                .setTitle("سریال SMART OBD")
                .setMessage("سریال چاپ‌شده روی دستگاه را وارد کنید. فعلاً فعال‌سازی فقط با همین سریال انجام می‌شود.")
                .setView(wrap)
                .setNegativeButton("انصراف",null)
                .setPositiveButton("ادامه",null).create();
        d.setOnShowListener(x->d.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v->{
            String s=normalizeSerial(e.getText().toString());
            if(!validSerial(s)){ e.setError("سریال معتبر وارد کنید"); return; }
            pendingSerial=s;
            prefs.edit().putString("pending_serial",s).apply();
            d.dismiss();
            scanBle();
        }));
        d.show();
    }

    String normalizeSerial(String s){return s==null?"":s.trim().toUpperCase(Locale.US).replace(" ","");}
    boolean validSerial(String s){return s.matches("[A-Z0-9]{2,8}-\\d{2}-\\d{6}");}

    void scanBle(){
        requestBluetoothPermissions();
        if(!btAllowed()){toast("مجوز بلوتوث را تأیید کنید");return;}
        BluetoothManager bm=(BluetoothManager)getSystemService(BLUETOOTH_SERVICE);
        BluetoothAdapter ad=bm==null?null:bm.getAdapter();
        if(ad==null){toast("بلوتوث در این گوشی پشتیبانی نمی‌شود");return;}
        if(!ad.isEnabled()){startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));return;}
        scanner=ad.getBluetoothLeScanner();
        if(scanner==null){toast("BLE در دسترس نیست");return;}

        final LinkedHashMap<String,BluetoothDevice> found=new LinkedHashMap<>();
        proView.message="در حال جستجوی SMART OBD ..."; proView.invalidate();

        scanCallback=new ScanCallback(){
            @Override public void onScanResult(int callbackType, ScanResult result){
                BluetoothDevice d=result.getDevice(); if(d==null)return;
                boolean ours=false;
                String name="";
                try{name=d.getName()==null?"":d.getName();}catch(Exception ignored){}
                if(name.contains("KhanehRemap")||name.contains("OBD-C3")||name.contains("SMART OBD")) ours=true;
                ScanRecord rec=result.getScanRecord();
                if(rec!=null&&rec.getServiceUuids()!=null){
                    for(ParcelUuid p:rec.getServiceUuids()) if(BLE_SERVICE.equals(p.getUuid())) ours=true;
                }
                if(ours) found.put(d.getAddress(),d);
            }
            @Override public void onScanFailed(int errorCode){
                runOnUiThread(()->{proView.message="خطای جستجوی BLE";proView.invalidate();toast("خطای BLE: "+errorCode);});
            }
        };
        try{scanner.startScan(scanCallback);}catch(Exception ex){toast("شروع جستجو ناموفق بود");return;}
        h.postDelayed(()->{
            try{scanner.stopScan(scanCallback);}catch(Exception ignored){}
            if(found.isEmpty()){
                proView.message="SMART OBD پیدا نشد";
                proView.invalidate();
                toast("ESP32-C3 پیدا نشد؛ برق دستگاه و BLE را بررسی کنید");
                return;
            }
            BluetoothDevice best=found.values().iterator().next();
            connectTo(best);
        },4300);
    }

    void connectTo(BluetoothDevice d){
        connectedDevice=d;
        proView.message="در حال اتصال BLE ...";proView.invalidate();
        try{
            if(Build.VERSION.SDK_INT>=31&&checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED){requestBluetoothPermissions();return;}
            gatt=d.connectGatt(this,false,new BluetoothGattCallback(){
                @Override public void onConnectionStateChange(BluetoothGatt g,int status,int newState){
                    if(newState==BluetoothProfile.STATE_CONNECTED){
                        connected=true;
                        try{g.requestMtu(185);}catch(Exception ignored){}
                        g.discoverServices();
                        runOnUiThread(()->{proView.message="BLE متصل شد؛ در حال بررسی سریال";proView.invalidate();});
                    } else if(newState==BluetoothProfile.STATE_DISCONNECTED){
                        connected=false;cmdCh=null;dataCh=null;deviceSerial="";
                        runOnUiThread(()->{proView.message="اتصال OBD قطع شد";proView.invalidate();});
                    }
                }
                @Override public void onServicesDiscovered(BluetoothGatt g,int status){
                    BluetoothGattService s=g.getService(BLE_SERVICE);
                    if(s==null){runOnUiThread(()->connectionFailed("Firmware SMART OBD روی ESP32 پیدا نشد"));return;}
                    cmdCh=s.getCharacteristic(BLE_CMD); dataCh=s.getCharacteristic(BLE_DATA);
                    if(cmdCh==null||dataCh==null){runOnUiThread(()->connectionFailed("کانال ارتباطی BLE کامل نیست"));return;}
                    g.setCharacteristicNotification(dataCh,true);
                    BluetoothGattDescriptor dd=dataCh.getDescriptor(CCCD);
                    if(dd!=null){dd.setValue(BluetoothGattDescriptor.ENABLE_NOTIFICATION_VALUE);g.writeDescriptor(dd);}
                    runOnUiThread(()->{
                        waitingIdentity=true;
                        send("IDENTITY");
                        h.postDelayed(()->{
                            if(waitingIdentity&&connected){
                                // Backward-compatible test mode for the current prototype firmware.
                                String bound=prefs.getString("bound_addr","");
                                String now=connectedDevice==null?"":connectedDevice.getAddress();
                                if(bound.isEmpty()||bound.equals(now)){
                                    prefs.edit().putString("activated_serial",pendingSerial).putString("bound_addr",now).apply();
                                    deviceSerial=pendingSerial;
                                    waitingIdentity=false;
                                    activationOk(true);
                                }else connectionFailed("این سریال قبلاً با یک دستگاه دیگر مچ شده است");
                            }
                        },1800);
                    });
                }
                @Override public void onCharacteristicChanged(BluetoothGatt g,BluetoothGattCharacteristic c){
                    if(BLE_DATA.equals(c.getUuid())){
                        String line=new String(c.getValue(), StandardCharsets.UTF_8).trim();
                        runOnUiThread(()->handleLine(line));
                    }
                }
            },BluetoothDevice.TRANSPORT_LE);
        }catch(Exception e){connectionFailed("اتصال BLE برقرار نشد");}
    }

    void handleLine(String s){
        if(s==null||s.isEmpty())return;
        if(s.startsWith("SERIAL:")){
            waitingIdentity=false;
            String got=normalizeSerial(s.substring(7));
            if(got.equals("UNSET")||got.isEmpty()){
                send("SET_SERIAL:"+pendingSerial);
                return;
            }
            deviceSerial=got;
            if(pendingSerial.isEmpty()) pendingSerial=prefs.getString("activated_serial","");
            if(got.equals(pendingSerial)){
                prefs.edit().putString("activated_serial",got)
                        .putString("bound_addr",connectedDevice==null?"":connectedDevice.getAddress()).apply();
                activationOk(false);
            }else connectionFailed("سریال دستگاه با سریال واردشده مطابقت ندارد");
            return;
        }
        if(s.startsWith("SET_SERIAL:OK,")){
            waitingIdentity=false;
            deviceSerial=normalizeSerial(s.substring("SET_SERIAL:OK,".length()));
            if(deviceSerial.equals(pendingSerial)){
                prefs.edit().putString("activated_serial",deviceSerial)
                        .putString("bound_addr",connectedDevice==null?"":connectedDevice.getAddress()).apply();
                activationOk(false);
            }
            return;
        }
        if(s.startsWith("SET_SERIAL:LOCKED,")){
            waitingIdentity=false;
            deviceSerial=normalizeSerial(s.substring("SET_SERIAL:LOCKED,".length()));
            if(deviceSerial.equals(pendingSerial)) activationOk(false);
            else connectionFailed("این ESP32 با سریال دیگری ثبت شده است");
            return;
        }

        if(!isActivated()) return;
        try{
            if(s.startsWith("RPM:")) rpm=Integer.parseInt(s.substring(4).trim());
            else if(s.startsWith("SPEED:")) speed=Integer.parseInt(s.substring(6).trim());
            else if(s.startsWith("ECT:")) coolant=Integer.parseInt(s.substring(4).trim());
            else if(s.startsWith("FUEL:")) fuel=Float.parseFloat(s.substring(5).trim());
            else if(s.startsWith("VOLT:")) voltage=Float.parseFloat(s.substring(5).trim());
            else if(s.startsWith("ECU:CONNECTED,")){protocol=s.substring(14);ecuName="ECU شناسایی شد — "+protocol;}
            else if(s.startsWith("STATUS,")){
                int p=s.indexOf("PROTO:");
                if(p>=0){int e=s.indexOf(",",p);protocol=s.substring(p+6,e<0?s.length():e);ecuName="ECU شناسایی شد — "+protocol;}
            }
            else if(s.startsWith("DTC:")){
                if(s.equals("DTC:NONE"))dtcText="هیچ خطای فعالی ثبت نشده";
                else dtcText=s.substring(4).replace(",","\n");
            }
            else if(s.startsWith("CLEAR_DTC:SENT"))dtcText="حافظه خطا پاک شد";
            else if(s.startsWith("ALARM:LOW_COOLANT")&&soundAlert)speak("هشدار، سطح آب خنک کننده پایین است");
        }catch(Exception ignored){}
        proView.invalidate();
    }

    boolean isActivated(){
        String act=prefs.getString("activated_serial","");
        return connected&&!act.isEmpty()&&(deviceSerial.isEmpty()||act.equals(deviceSerial));
    }

    void activationOk(boolean legacy){
        waitingIdentity=false;
        connected=true;
        proView.message=(legacy?"اتصال برقرار شد • مچ محلی سریال":"اتصال OBD با موفقیت برقرار شد");
        send("STATUS");
        h.postDelayed(()->send("REDETECT"),350);
        proView.invalidate();
        toast("سریال "+prefs.getString("activated_serial","")+" تأیید شد");
    }

    void connectionFailed(String msg){
        proView.message=msg;
        proView.invalidate();
        toast(msg);
        disconnect();
    }

    void send(String s){
        if(!connected||gatt==null||cmdCh==null)return;
        try{
            cmdCh.setWriteType(BluetoothGattCharacteristic.WRITE_TYPE_NO_RESPONSE);
            cmdCh.setValue(s.getBytes(StandardCharsets.UTF_8));
            gatt.writeCharacteristic(cmdCh);
        }catch(Exception ignored){}
    }

    void disconnect(){
        try{if(scanner!=null&&scanCallback!=null)scanner.stopScan(scanCallback);}catch(Exception ignored){}
        try{if(gatt!=null){gatt.disconnect();gatt.close();}}catch(Exception ignored){}
        gatt=null;cmdCh=null;dataCh=null;connected=false;waitingIdentity=false;deviceSerial="";
        proView.message="بدون اتصال";proView.invalidate();
    }

    void clearActivation(){
        disconnect();
        prefs.edit().remove("activated_serial").remove("bound_addr").remove("pending_serial").apply();
        pendingSerial="";
        toast("مچ سریال پاک شد");
    }

    void speak(String s){if(tts!=null)tts.speak(s,TextToSpeech.QUEUE_FLUSH,null,"obd-alert");}
    void toast(String s){Toast.makeText(this,s,Toast.LENGTH_SHORT).show();}

    void readDtc(){
        if(demo){dtcText="P0130 — مدار سنسور اکسیژن\nP0420 — بازده کاتالیست پایین‌تر از حد مجاز";proView.page=1;proView.invalidate();return;}
        if(!isActivated()){toast("ابتدا با سریال معتبر به OBD متصل شوید");return;}
        dtcText="در حال خواندن خطاهای ECU ...";proView.page=1;proView.invalidate();send("READ_DTC");
    }

    void clearDtc(){
        if(!demo&&!isActivated()){toast("ابتدا به OBD متصل شوید");return;}
        new AlertDialog.Builder(this)
                .setTitle("پاک‌کردن DTC")
                .setMessage("سوییچ باز و موتور خاموش باشد. داده‌های Freeze Frame نیز ممکن است پاک شوند.")
                .setNegativeButton("انصراف",null)
                .setPositiveButton("پاک شود",(d,w)->{
                    if(demo){dtcText="خطاهای دمو پاک شدند";proView.invalidate();}
                    else send("CLEAR_DTC");
                }).show();
    }

    class ProView extends View {
        Paint p=new Paint(Paint.ANTI_ALIAS_FLAG);
        RectF r=new RectF();
        int page=3; // 0 dashboard, 1 diag, 2 live, 3 connect/settings
        String message="بدون اتصال";
        Bitmap cockpit;

        ProView(Context c){
            super(c);setLayerType(View.LAYER_TYPE_SOFTWARE,null);
            try{
                int id=getResources().getIdentifier("cockpit_portrait","drawable",getPackageName());
                if(id!=0) cockpit=BitmapFactory.decodeResource(getResources(),id);
            }catch(Exception ignored){}
        }

        void fill(Canvas c,int color){c.drawColor(color);}
        void txt(Canvas c,String s,float x,float y,float size,int col,Paint.Align a,boolean bold){
            p.setStyle(Paint.Style.FILL);p.setColor(col);p.setTextSize(size);p.setTextAlign(a);
            p.setTypeface(bold?Typeface.create("sans",Typeface.BOLD):Typeface.create("sans",Typeface.NORMAL));
            p.clearShadowLayer();c.drawText(s,x,y,p);
        }
        void rr(Canvas c,float l,float t,float x,float y,float rad,int col,int stroke){
            p.setStyle(Paint.Style.FILL);p.setColor(col);p.clearShadowLayer();c.drawRoundRect(l,t,x,y,rad,rad,p);
            if(stroke!=0){p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(1.5f);p.setColor(stroke);c.drawRoundRect(l,t,x,y,rad,rad,p);}
        }
        void line(Canvas c,float x1,float y1,float x2,float y2,int col,float sw){
            p.setColor(col);p.setStrokeWidth(sw);p.setStyle(Paint.Style.STROKE);c.drawLine(x1,y1,x2,y2,p);
        }

        @Override protected void onDraw(Canvas c){
            super.onDraw(c);
            float w=getWidth(),h=getHeight();
            if(getResources().getConfiguration().orientation==Configuration.ORIENTATION_LANDSCAPE){drawLandscape(c,w,h);return;}
            drawBackground(c,w,h);
            drawHeader(c,w,h);
            if(page==0)drawDashboard(c,w,h);
            else if(page==1)drawDiag(c,w,h);
            else if(page==2)drawLive(c,w,h);
            else drawConnect(c,w,h);
            drawBottom(c,w,h);
        }

        void drawBackground(Canvas c,float w,float h){
            fill(c,BG);
            if(cockpit!=null){
                Rect src=new Rect(0,0,cockpit.getWidth(),cockpit.getHeight());
                RectF dst=new RectF(0,h*.22f,w,h);
                p.setAlpha(90);c.drawBitmap(cockpit,src,dst,p);p.setAlpha(255);
                LinearGradient shade=new LinearGradient(0,h*.18f,0,h,Color.argb(250,1,12,21),Color.argb(140,1,12,21),Shader.TileMode.CLAMP);
                p.setShader(shade);c.drawRect(0,h*.18f,w,h,p);p.setShader(null);
            }
        }

        void drawHeader(Canvas c,float w,float h){
            float top=22;
            rr(c,w-122,40,w-28,88,28,connected?Color.rgb(9,61,54):Color.rgb(58,30,40),0);
            txt(c,"●  "+(connected?"OBD وصل":"OBD قطع"),w-75,70,14,connected?GREEN:RED,Paint.Align.CENTER,true);

            Path hex=new Path();float cx=58,cy=62,rad=29;
            for(int i=0;i<6;i++){double a=Math.toRadians(30+i*60);float x=(float)(cx+Math.cos(a)*rad),y=(float)(cy+Math.sin(a)*rad);if(i==0)hex.moveTo(x,y);else hex.lineTo(x,y);}hex.close();
            p.setColor(CYAN);p.setStyle(Paint.Style.FILL);p.setShadowLayer(18,0,0,CYAN);c.drawPath(hex,p);p.clearShadowLayer();
            txt(c,"A",cx,cy+9,26,BG,Paint.Align.CENTER,true);
            txt(c,"خانه ریمپ",w-170,56,23,WHITE,Paint.Align.RIGHT,false);
            txt(c,"SMART OBD",w-170,82,13,CYAN,Paint.Align.RIGHT,true);
            line(c,28,110,w-28,110,Color.rgb(10,84,98),1);
        }

        void drawConnect(Canvas c,float w,float h){
            txt(c,"اتصال و تنظیمات",w-36,155,27,WHITE,Paint.Align.RIGHT,false);
            txt(c,"مدیریت رابط بلوتوث و تجربه رانندگی",w-36,184,13,MUTED,Paint.Align.RIGHT,false);

            float t=205;
            rr(c,28,t,w-28,t+196,28,CARD,0);
            rr(c,w-140,t+36,w-70,t+106,35,connected?Color.rgb(24,70,66):Color.rgb(48,42,67),0);
            txt(c,"ϟ",w-105,t+80,28,connected?GREEN:RED,Paint.Align.CENTER,true);
            txt(c,connected?"متصل":"بدون اتصال",w-165,t+74,25,WHITE,Paint.Align.RIGHT,false);
            String id=connected?(deviceSerial.isEmpty()?prefs.getString("activated_serial","SMART OBD"):deviceSerial):"KHANEH_REMAP_C3";
            txt(c,id,w-165,t+108,14,connected?GREEN:RED,Paint.Align.RIGHT,true);
            txt(c,message,w-165,t+142,12,MUTED,Paint.Align.RIGHT,false);

            rr(c,55,t+150,w-55,t+190,16,Color.TRANSPARENT,CYAN);
            txt(c,connected?"قطع ارتباط":"اتصال به OBD",w/2,t+177,18,CYAN,Paint.Align.CENTER,false);
            txt(c,"⌁",w-95,t+177,20,CYAN,Paint.Align.CENTER,true);

            float y=t+220;
            rr(c,28,y,w-28,y+118,24,CARD,0);
            txt(c,"شناسایی خودکار ECU",w-58,y+36,15,MUTED,Paint.Align.RIGHT,false);
            txt(c,ecuName,w-58,y+72,17,WHITE,Paint.Align.RIGHT,true);
            txt(c,"تشخیص خودکار • PID استاندارد • "+protocol,w-58,y+101,12,CYAN,Paint.Align.RIGHT,false);

            y+=140;
            rr(c,28,y,w-28,y+84,22,CARD,0);
            txt(c,"حالت دمو بدون خودرو",w-58,y+48,17,WHITE,Paint.Align.RIGHT,false);
            drawSwitch(c,68,y+42,demo);

            y+=100;
            rr(c,28,y,w-28,y+84,22,CARD,0);
            txt(c,"هشدار صوتی پارامترهای بحرانی",w-58,y+48,17,WHITE,Paint.Align.RIGHT,false);
            drawSwitch(c,68,y+42,soundAlert);

            txt(c,"نسخه حرفه‌ای  2.3 Serial  •  طراحی اختصاصی خانه ریمپ",w/2,y+128,11,Color.rgb(70,94,109),Paint.Align.CENTER,false);
            String act=prefs.getString("activated_serial","");
            if(!act.isEmpty()) txt(c,"سریال فعال: "+act,w/2,y+150,11,Color.rgb(78,120,137),Paint.Align.CENTER,false);
        }

        void drawSwitch(Canvas c,float x,float y,boolean on){
            rr(c,x-38,y-18,x+38,y+18,18,on?Color.rgb(11,132,142):Color.rgb(50,76,91),0);
            p.setColor(on?CYAN:Color.rgb(133,160,175));p.setStyle(Paint.Style.FILL);c.drawCircle(x+(on?19:-19),y,14,p);
        }

        void drawDashboard(Canvas c,float w,float h){
            txt(c,"داشبورد",w-36,155,28,WHITE,Paint.Align.RIGHT,false);
            txt(c,connected?"خودرو و ECU متصل":"آماده اتصال به خودرو",w-36,181,13,connected?GREEN:MUTED,Paint.Align.RIGHT,false);

            float gy=300,rad=112;
            gauge(c,w*.28f,gy,rad,rpm,8000,"RPM");
            gauge(c,w*.72f,gy,rad,speed,240,"km/h");
            rr(c,w*.42f,237,w*.58f,330,24,Color.rgb(4,24,38),Color.rgb(13,81,104));
            txt(c,connected?"D":"P",w*.5f,292,44,WHITE,Paint.Align.CENTER,true);
            txt(c,protocol,w*.5f,322,11,CYAN,Paint.Align.CENTER,true);

            float y=440, gap=9, cw=(w-56-gap)/2;
            dataCard(c,28,y,28+cw,y+88,"دمای مایع خنک‌کننده",coolant>0?coolant+" °C":"—",RED);
            dataCard(c,28+cw+gap,y,w-28,y+88,"ولتاژ کنترلر",voltage>0?String.format(Locale.US,"%.1f V",voltage):"—",GREEN);
            y+=100;
            dataCard(c,28,y,28+cw,y+88,"سطح سوخت",fuel>0?String.format(Locale.US,"%.0f %%",fuel):"—",AMBER);
            dataCard(c,28+cw+gap,y,w-28,y+88,"وضعیت ECU",connected?"مطلوب است":"بدون اتصال",connected?GREEN:MUTED);

            rr(c,28,y+110,w-28,y+182,22,CARD,0);
            txt(c,connected?"ارتباط پایدار با ECU":"برای شروع از بخش اتصال وارد شوید",w/2,y+154,15,connected?GREEN:MUTED,Paint.Align.CENTER,true);
        }

        void gauge(Canvas c,float cx,float cy,float rad,int value,int max,String unit){
            p.setStyle(Paint.Style.STROKE);p.setStrokeCap(Paint.Cap.ROUND);p.setStrokeWidth(12);
            r.set(cx-rad,cy-rad,cx+rad,cy+rad);p.setColor(Color.rgb(19,48,67));c.drawArc(r,140,260,false,p);
            float q=Math.max(0,Math.min(1,value/(float)max));p.setColor(CYAN);p.setShadowLayer(14,0,0,CYAN);c.drawArc(r,140,260*q,false,p);p.clearShadowLayer();
            txt(c,String.valueOf(value),cx,cy+12,40,WHITE,Paint.Align.CENTER,true);
            txt(c,unit,cx,cy+43,13,MUTED,Paint.Align.CENTER,false);
        }

        void dataCard(Canvas c,float l,float t,float x,float y,String name,String val,int col){
            rr(c,l,t,x,y,20,CARD,Color.rgb(14,56,76));
            txt(c,name,x-16,t+30,12,MUTED,Paint.Align.RIGHT,false);
            txt(c,val,x-16,y-20,19,col,Paint.Align.RIGHT,true);
        }

        void drawLive(Canvas c,float w,float h){
            txt(c,"داده‌های زنده ECU",w-36,155,27,WHITE,Paint.Align.RIGHT,false);
            txt(c,"نمایش لحظه‌ای پارامترهای موتور",w-36,184,13,MUTED,Paint.Align.RIGHT,false);
            String[][] rows={
                    {"دور موتور",rpm+" RPM"},
                    {"سرعت خودرو",speed+" km/h"},
                    {"دمای مایع خنک‌کننده",coolant>0?coolant+" °C":"—"},
                    {"سطح سوخت",fuel>0?String.format(Locale.US,"%.0f %%",fuel):"—"},
                    {"ولتاژ کنترلر",voltage>0?String.format(Locale.US,"%.2f V",voltage):"—"},
                    {"پروتکل",protocol}
            };
            float y=214;
            for(int i=0;i<rows.length;i++){
                rr(c,28,y,w-28,y+72,20,CARD,0);
                txt(c,rows[i][0],w-58,y+30,14,MUTED,Paint.Align.RIGHT,false);
                txt(c,rows[i][1],w-58,y+57,19,i<2?CYAN:WHITE,Paint.Align.RIGHT,true);
                y+=82;
            }
        }

        void drawDiag(Canvas c,float w,float h){
            txt(c,"عیب‌یابی هوشمند",w-36,155,27,WHITE,Paint.Align.RIGHT,false);
            txt(c,"خواندن و پاک‌کردن خطاهای استاندارد OBD-II",w-36,184,13,MUTED,Paint.Align.RIGHT,false);
            rr(c,28,210,w-28,330,24,CARD,0);
            txt(c,"کد خطا (DTC)",w-58,245,15,MUTED,Paint.Align.RIGHT,false);
            String[] lines=dtcText.split("\\n");
            float y=280;
            for(int i=0;i<Math.min(lines.length,3);i++){txt(c,lines[i],w-58,y,15,WHITE,Paint.Align.RIGHT,true);y+=25;}
            rr(c,45,354,w-45,406,18,Color.rgb(7,55,74),CYAN);
            txt(c,"اسکن خطاها",w/2,387,18,CYAN,Paint.Align.CENTER,true);
            rr(c,45,420,w-45,472,18,Color.rgb(55,28,36),RED);
            txt(c,"پاک‌کردن خطاهای ECU",w/2,453,17,RED,Paint.Align.CENTER,true);

            rr(c,28,500,w-28,588,22,CARD,0);
            txt(c,"اطلاعات ECU",w-58,532,14,MUTED,Paint.Align.RIGHT,false);
            txt(c,ecuName,w-58,566,16,connected?GREEN:WHITE,Paint.Align.RIGHT,true);
        }

        void drawBottom(Canvas c,float w,float h){
            float bh=104,y=h-bh;
            rr(c,0,y,w,h,0,Color.rgb(2,19,29),0);
            line(c,0,y,w,y,Color.rgb(7,45,59),1);
            String[] icons={"⌂","▥","≋","⌁"};
            String[] labels={"داشبورد","داده زنده","عیب‌یابی","اتصال"};
            for(int i=0;i<4;i++){
                float cx=w*(i+.5f)/4;
                boolean on=page==i;
                if(on)rr(c,cx-38,y+9,cx+38,y+70,29,Color.rgb(3,49,64),0);
                txt(c,icons[i],cx,y+42,24,on?CYAN:MUTED,Paint.Align.CENTER,true);
                txt(c,labels[i],cx,y+85,12,on?CYAN:MUTED,Paint.Align.CENTER,false);
                if(on)line(c,cx-28,y+2,cx+28,y+2,CYAN,4);
            }
        }

        void drawLandscape(Canvas c,float w,float h){
            fill(c,BG);
            if(cockpit!=null){p.setAlpha(70);c.drawBitmap(cockpit,null,new RectF(0,0,w,h),p);p.setAlpha(255);}
            rr(c,12,12,220,h-12,22,Color.argb(230,5,29,43),0);
            txt(c,"خانه ریمپ",200,52,21,WHITE,Paint.Align.RIGHT,true);
            txt(c,"SMART OBD",200,76,12,CYAN,Paint.Align.RIGHT,true);
            String[] n={"داشبورد","داده زنده","عیب‌یابی","اتصال"};
            for(int i=0;i<4;i++){float y=110+i*58;rr(c,25,y,207,y+46,14,page==i?Color.rgb(5,67,84):Color.TRANSPARENT,page==i?CYAN:0);txt(c,n[i],190,y+30,14,page==i?CYAN:WHITE,Paint.Align.RIGHT,page==i);}
            if(page==3){
                txt(c,"اتصال و تنظیمات",w-35,55,25,WHITE,Paint.Align.RIGHT,true);
                rr(c,250,82,w-25,h-25,26,Color.argb(225,7,32,47),0);
                txt(c,connected?"OBD متصل":"بدون اتصال",w-65,130,24,connected?GREEN:RED,Paint.Align.RIGHT,true);
                txt(c,prefs.getString("activated_serial","سریال ثبت نشده"),w-65,164,15,CYAN,Paint.Align.RIGHT,true);
                txt(c,message,w-65,196,13,MUTED,Paint.Align.RIGHT,false);
                rr(c,280,230,w-55,282,18,Color.TRANSPARENT,CYAN);txt(c,connected?"قطع ارتباط":"اتصال به OBD",(280+w-55)/2,263,18,CYAN,Paint.Align.CENTER,true);
            }else{
                txt(c,page==0?"داشبورد":page==1?"عیب‌یابی":"داده زنده",w-35,55,25,WHITE,Paint.Align.RIGHT,true);
                gauge(c,w*.48f,h*.47f,Math.min(120,h*.3f),rpm,8000,"RPM");
                gauge(c,w*.78f,h*.47f,Math.min(120,h*.3f),speed,240,"km/h");
            }
        }

        @Override public boolean onTouchEvent(MotionEvent e){
            if(e.getAction()!=MotionEvent.ACTION_UP)return true;
            float x=e.getX(),y=e.getY(),w=getWidth(),h=getHeight();
            if(getResources().getConfiguration().orientation==Configuration.ORIENTATION_LANDSCAPE){
                if(x<230){
                    if(y>110&&y<156)page=0; else if(y>168&&y<214)page=1; else if(y>226&&y<272)page=2; else if(y>284&&y<330)page=3;
                    invalidate();return true;
                }
                if(page==3&&y>220&&y<300){if(connected)disconnect();else beginConnect();return true;}
                return true;
            }
            if(y>h-108){
                int i=Math.min(3,(int)(x/(w/4f)));page=i;invalidate();return true;
            }
            if(page==3){
                float t=205;
                if(y>t+145&&y<t+198){if(connected)disconnect();else beginConnect();return true;}
                float sw1=t+220+140+42;
                if(y>sw1-42&&y<sw1+42){demo=!demo;if(demo)disconnect();invalidate();return true;}
                float sw2=t+220+140+100+42;
                if(y>sw2-42&&y<sw2+42){soundAlert=!soundAlert;toast(soundAlert?"هشدار صوتی فعال شد":"هشدار صوتی خاموش شد");invalidate();return true;}
                // long-ish tap footer area clears serial binding
                if(y>h-190&&y<h-110&&x<w*.45f){clearActivation();return true;}
            }else if(page==1){
                if(y>348&&y<414){readDtc();return true;}
                if(y>414&&y<480){clearDtc();return true;}
            }
            return true;
        }
    }

    @Override public void onConfigurationChanged(Configuration c){super.onConfigurationChanged(c);proView.invalidate();}
    @Override protected void onDestroy(){
        alive=false;disconnect();io.shutdownNow();
        if(tts!=null){tts.stop();tts.shutdown();}
        super.onDestroy();
    }
}
