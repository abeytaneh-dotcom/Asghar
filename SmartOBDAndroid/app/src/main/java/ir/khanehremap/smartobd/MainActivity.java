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
    float fuel=0, voltage=0, waterLevel=-1f;
    boolean lowCoolant=false;
    // Living-car feature states. Firmware can drive these later without redesigning the UI.
    boolean headlightsOn=false, leftSignal=false, rightSignal=false, wipersOn=false;
    boolean hoodOpen=false, doorsOpen=false;
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
                        send("GET_SERIAL");
                        h.postDelayed(()->{
                            if(waitingIdentity&&connected){
                                connectionFailed("Firmware دستگاه پاسخ سریال نداد؛ Firmware سریال‌دار را روی ESP32-C3 نصب کنید");
                            }
                        },2200);
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
            String payload=s.substring(7).trim();
            String got=normalizeSerial(payload);
            if(got.equals("UNSET")||got.isEmpty()){
                waitingIdentity=true;
                send("SET_SERIAL:"+pendingSerial);
                return;
            }
            if(got.startsWith("SET,")){
                deviceSerial=normalizeSerial(got.substring(4));
                if(deviceSerial.equals(pendingSerial)){
                    prefs.edit().putString("activated_serial",deviceSerial)
                            .putString("bound_addr",connectedDevice==null?"":connectedDevice.getAddress()).apply();
                    activationOk(false);
                }else connectionFailed("سریال ذخیره‌شده با سریال واردشده مطابقت ندارد");
                return;
            }
            if(got.equals("LOCKED")){
                connectionFailed("این ESP32 قبلاً با سریال دیگری ثبت شده است");
                return;
            }
            if(got.equals("INVALID")){
                connectionFailed("فرمت سریال معتبر نیست");
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
            else if(s.startsWith("WATER_LEVEL:")) waterLevel=Float.parseFloat(s.substring(12).trim());
            else if(s.startsWith("WATER:")) waterLevel=Float.parseFloat(s.substring(6).trim());
            else if(s.startsWith("ALARM:LOW_COOLANT")){
                lowCoolant=true;
                if(soundAlert)speak("تشنمه");
            }
            else if(s.equals("COOLANT:OK")) lowCoolant=false;
            else if(s.startsWith("LIGHTS:")) headlightsOn=s.endsWith("ON");
            else if(s.startsWith("WIPER:")) wipersOn=s.endsWith("ON");
            else if(s.startsWith("SIGNAL:")){
                String v=s.substring(7).trim();
                leftSignal="LEFT".equals(v)||"HAZARD".equals(v);
                rightSignal="RIGHT".equals(v)||"HAZARD".equals(v);
                if("OFF".equals(v)){leftSignal=false;rightSignal=false;}
            }
            else if(s.startsWith("HOOD:")) hoodOpen=s.endsWith("OPEN");
            else if(s.startsWith("DOORS:")||s.startsWith("DOOR:")) doorsOpen=s.endsWith("OPEN");
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
        proView.message="اتصال OBD با موفقیت برقرار شد • سریال تأیید شد";
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
        final Paint p=new Paint(Paint.ANTI_ALIAS_FLAG);
        final RectF r=new RectF();
        final Random random=new Random();
        int page=0; // 0 خانه، 1 گزارش‌ها، 2 سرویس، 3 وضعیت خودرو، 4 بیشتر
        String message="بدون اتصال";

        // Logical responsive canvas
        float logicalW=420, logicalH=820, uiScale=1, uiDx=0, uiDy=0;
        boolean landscape=false;

        // Living face animation: no frame swapping, all geometry moves smoothly.
        float eyeX=0f, eyeY=0f, eyeTargetX=0f, eyeTargetY=0f, blink=0f;
        long nextLook=0, nextBlink=0, blinkStart=0;
        boolean blinking=false;

        ProView(Context c){
            super(c);
            setLayerType(View.LAYER_TYPE_SOFTWARE,null);
            nextLook=SystemClock.uptimeMillis()+900;
            nextBlink=SystemClock.uptimeMillis()+2200;
        }

        int rgb(int rr,int gg,int bb){return Color.rgb(rr,gg,bb);}
        int argb(int aa,int rr,int gg,int bb){return Color.argb(aa,rr,gg,bb);}

        void txt(Canvas c,String text,float x,float y,float size,int color,Paint.Align align,boolean bold){
            p.reset(); p.setAntiAlias(true); p.setStyle(Paint.Style.FILL); p.setColor(color);
            p.setTextSize(size); p.setTextAlign(align);
            p.setTypeface(Typeface.create("sans",bold?Typeface.BOLD:Typeface.NORMAL));
            c.drawText(text,x,y,p);
        }
        void rr(Canvas c,float l,float t,float rt,float b,float rad,int color,int stroke){
            p.reset();p.setAntiAlias(true);p.setStyle(Paint.Style.FILL);p.setColor(color);
            c.drawRoundRect(l,t,rt,b,rad,rad,p);
            if(stroke!=0){p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(1.2f);p.setColor(stroke);c.drawRoundRect(l,t,rt,b,rad,rad,p);}
        }
        void line(Canvas c,float x1,float y1,float x2,float y2,int color,float sw){
            p.reset();p.setAntiAlias(true);p.setStyle(Paint.Style.STROKE);p.setStrokeCap(Paint.Cap.ROUND);
            p.setColor(color);p.setStrokeWidth(sw);c.drawLine(x1,y1,x2,y2,p);
        }
        void circle(Canvas c,float x,float y,float rad,int color){
            p.reset();p.setAntiAlias(true);p.setStyle(Paint.Style.FILL);p.setColor(color);c.drawCircle(x,y,rad,p);
        }

        void setupLogical(Canvas c,float w,float h){
            landscape=w>h*1.15f;
            logicalW=landscape?820:420;
            logicalH=landscape?420:820;
            uiScale=Math.min(w/logicalW,h/logicalH);
            uiDx=(w-logicalW*uiScale)/2f;
            uiDy=(h-logicalH*uiScale)/2f;
            c.translate(uiDx,uiDy);
            c.scale(uiScale,uiScale);
        }

        @Override protected void onDraw(Canvas canvas){
            super.onDraw(canvas);
            canvas.drawColor(BG);
            canvas.save();
            setupLogical(canvas,getWidth(),getHeight());
            animateFace();

            if(landscape) drawLandscape(canvas);
            else drawPortrait(canvas);

            canvas.restore();
            postInvalidateOnAnimation();
        }

        void animateFace(){
            long now=SystemClock.uptimeMillis();

            if(lowCoolant){
                eyeTargetX=0f; eyeTargetY=.40f;
            } else if(now>=nextLook && !blinking){
                eyeTargetX=-.80f+random.nextFloat()*1.60f;
                eyeTargetY=-.20f+random.nextFloat()*.45f;
                nextLook=now+1200+random.nextInt(2200);
            }
            eyeX+=(eyeTargetX-eyeX)*.055f;
            eyeY+=(eyeTargetY-eyeY)*.055f;

            if(!lowCoolant && !blinking && now>=nextBlink){
                blinking=true;blinkStart=now;
                nextBlink=now+2600+random.nextInt(2800);
            }
            if(blinking){
                long d=now-blinkStart;
                if(d<90) blink=d/90f;
                else if(d<145) blink=1f;
                else if(d<240) blink=1f-(d-145)/95f;
                else {blink=0f;blinking=false;}
            }else blink+=(0f-blink)*.22f;
        }

        void drawPortrait(Canvas c){
            drawBg(c,420,820);
            drawHeader(c,420);
            if(page==0){
                drawLivingCar(c,210,218,1f);
                drawTwinGauges(c,210,430);
                drawDataGrid(c,18,555,384);
            }else{
                drawSecondary(c,420,820);
            }
            drawBottom(c,420,820);
        }

        void drawLandscape(Canvas c){
            drawBg(c,820,420);
            drawHeader(c,820);
            if(page==0){
                drawLivingCar(c,195,190,.72f);
                drawGauge(c,430,190,78,speed,220,"km/h",CYAN,false);
                drawGauge(c,585,190,78,rpm,8000,"RPM",CYAN,true);
                drawMiniData(c,675,107,128,56,"آب",waterText(),lowCoolant?RED:CYAN);
                drawMiniData(c,675,170,128,56,"دما",coolant>0?coolant+"°C":"—",RED);
                drawMiniData(c,675,233,128,56,"سوخت",fuel>0?String.format(Locale.US,"%.0f%%",fuel):"—",AMBER);
            }else drawSecondaryLandscape(c);
            drawBottomLandscape(c,820,420);
        }

        void drawBg(Canvas c,float w,float h){
            p.reset();p.setAntiAlias(true);
            LinearGradient g=new LinearGradient(0,0,0,h,rgb(4,23,43),rgb(0,5,11),Shader.TileMode.CLAMP);
            p.setShader(g);c.drawRect(0,0,w,h,p);p.setShader(null);
            for(int i=0;i<8;i++){
                float x=(i*79+31)%w;
                p.setColor(argb(24,0,139,255));p.setShadowLayer(18,0,0,rgb(0,130,255));
                c.drawCircle(x,120+(i%3)*38,3.5f,p);p.clearShadowLayer();
            }
        }

        void drawHeader(Canvas c,float w){
            float center=w/2f;
            rr(c,16,18,68,68,16,argb(170,4,20,38),rgb(17,100,175));
            txt(c,"☰",42,54,29,WHITE,Paint.Align.CENTER,false);

            rr(c,w-68,18,w-16,68,16,argb(170,4,20,38),rgb(17,100,175));
            txt(c,"⚙",w-42,54,29,WHITE,Paint.Align.CENTER,false);

            txt(c,"پژو ۲۰۶ من",center,42,22,WHITE,Paint.Align.CENTER,true);
            rr(c,center-92,52,center+92,83,18,argb(195,3,27,43),connected?rgb(13,103,92):rgb(35,46,62));
            circle(c,center-72,67.5f,6,connected?GREEN:rgb(112,132,148));
            txt(c,connected?"خودرو آماده حرکت است":"SMART OBD قطع",center+4,72,12,connected?WHITE:rgb(190,205,218),Paint.Align.CENTER,true);
        }

        void drawLivingCar(Canvas c,float cx,float cy,float scale){
            c.save();c.translate(cx,cy);c.scale(scale,scale);
            float bob=(float)Math.sin(SystemClock.uptimeMillis()/760.0)*1.6f;
            c.translate(0,bob);

            // shadow
            p.reset();p.setAntiAlias(true);p.setColor(argb(130,0,0,0));
            c.drawOval(new RectF(-154,92,154,132),p);

            // optional open doors; architecture is ready for future commands.
            if(doorsOpen){
                p.setColor(rgb(7,98,214));
                Path dl=new Path();dl.moveTo(-128,-22);dl.lineTo(-182,5);dl.lineTo(-175,82);dl.lineTo(-120,65);dl.close();c.drawPath(dl,p);
                Path dr=new Path();dr.moveTo(128,-22);dr.lineTo(182,5);dr.lineTo(175,82);dr.lineTo(120,65);dr.close();c.drawPath(dr,p);
            }

            // body
            p.setStyle(Paint.Style.FILL);
            LinearGradient bodyG=new LinearGradient(0,-92,0,92,rgb(38,158,255),rgb(0,78,190),Shader.TileMode.CLAMP);
            p.setShader(bodyG);
            Path body=new Path();
            body.moveTo(-154,65);body.cubicTo(-154,9,-135,-38,-100,-62);
            body.cubicTo(-77,-103,77,-103,100,-62);
            body.cubicTo(135,-38,154,9,154,65);
            body.quadTo(148,103,118,108);body.lineTo(-118,108);
            body.quadTo(-148,103,-154,65);body.close();c.drawPath(body,p);p.setShader(null);

            // roof + windshield
            p.setColor(rgb(1,39,82));
            Path wind=new Path();wind.moveTo(-92,-57);wind.quadTo(-73,-94,-42,-104);wind.quadTo(0,-116,42,-104);
            wind.quadTo(73,-94,92,-57);wind.lineTo(78,-10);wind.lineTo(-78,-10);wind.close();c.drawPath(wind,p);

            p.setColor(rgb(13,93,150));
            Path glass=new Path();glass.moveTo(-82,-55);glass.quadTo(-64,-85,-38,-94);glass.quadTo(0,-104,38,-94);
            glass.quadTo(64,-85,82,-55);glass.lineTo(70,-18);glass.lineTo(-70,-18);glass.close();c.drawPath(glass,p);

            // eyebrows integrated into glass
            line(c,-67,-67,-20,-78,rgb(0,8,16),7);
            line(c,67,-67,20,-78,rgb(0,8,16),7);

            // eyes live inside windshield, not image swapping
            drawEye(c,-38,-50);
            drawEye(c,38,-50);

            // wipers future-ready
            if(wipersOn){
                float a=(float)Math.sin(SystemClock.uptimeMillis()/180.0)*.65f;
                float x=(float)(Math.sin(a)*54), y=(float)(-16-Math.cos(a)*46);
                line(c,-4,-11,x-4,y,rgb(12,18,24),5);
                line(c,9,-11,-x+9,y,rgb(12,18,24),5);
            }

            // hood; moves if HOOD:OPEN arrives in future firmware
            p.setColor(rgb(17,124,238));
            Path hood=new Path();
            float hoodLift=hoodOpen?-26:0;
            hood.moveTo(-119,-7+hoodLift);hood.lineTo(119,-7+hoodLift);hood.lineTo(139,51);hood.lineTo(-139,51);hood.close();c.drawPath(hood,p);
            line(c,-116,-5+hoodLift,116,-5+hoodLift,rgb(70,190,255),2);

            // headlights
            drawHeadlight(c,-116,44,headlightsOn,leftSignal);
            drawHeadlight(c,116,44,headlightsOn,rightSignal);

            // grille + lion badge
            rr(c,-66,48,66,69,9,rgb(0,13,24),0);
            rr(c,-17,39,17,70,6,rgb(4,14,25),rgb(215,230,244));
            txt(c,"♌",0,61,20,WHITE,Paint.Align.CENTER,true);

            // bumper / smile
            p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(8);p.setStrokeCap(Paint.Cap.ROUND);p.setColor(rgb(0,15,26));
            c.drawArc(new RectF(-72,57,72,104),8,164,false,p);
            p.setStrokeWidth(4);p.setColor(rgb(244,248,250));c.drawArc(new RectF(-52,63,52,90),18,144,false,p);

            // tongue ONLY on coolant warning
            if(lowCoolant){
                p.setStyle(Paint.Style.FILL);p.setColor(rgb(255,82,95));
                Path tongue=new Path();tongue.moveTo(-15,82);tongue.cubicTo(-11,112,12,116,17,87);
                tongue.cubicTo(8,92,-2,91,-15,82);tongue.close();c.drawPath(tongue,p);
                line(c,2,91,5,106,rgb(194,47,62),2.2f);
            }

            // plate
            rr(c,-45,97,45,119,5,rgb(5,13,24),rgb(130,155,176));
            txt(c,"206",0,114,18,WHITE,Paint.Align.CENTER,true);

            if(lowCoolant){
                rr(c,69,-118,147,-77,20,rgb(255,248,244),rgb(255,84,90));
                txt(c,"تشنمه",108,-91,17,rgb(138,11,28),Paint.Align.CENTER,true);
                circle(c,139,-66,4,rgb(69,190,255));circle(c,148,-58,3,rgb(69,190,255));
            }
            c.restore();
        }

        void drawEye(Canvas c,float ex,float ey){
            float openness=Math.max(.05f,1f-blink);
            float h=34f*openness;
            p.setStyle(Paint.Style.FILL);p.setColor(rgb(249,250,245));
            c.drawOval(new RectF(ex-27,ey-h/2,ex+27,ey+h/2),p);
            if(openness>.22f){
                float px=ex+eyeX*10f, py=ey+eyeY*6f;
                circle(c,px,py,12,rgb(21,173,229));
                circle(c,px,py,7.5f,rgb(2,20,30));
                circle(c,px-3.2f,py-4.5f,2.8f,Color.WHITE);
            }
            // eyelid is body-colored and slides down naturally
            if(blink>.01f){
                p.setColor(rgb(11,100,195));
                float cover=34f*blink;
                c.drawRoundRect(ex-29,ey-20,ex+29,ey-20+cover,8,8,p);
            }
        }

        void drawHeadlight(Canvas c,float x,float y,boolean on,boolean signal){
            p.reset();p.setAntiAlias(true);
            if(on){p.setShadowLayer(22,0,0,rgb(255,239,180));p.setColor(rgb(255,245,205));}
            else p.setColor(rgb(235,240,230));
            c.drawOval(new RectF(x-29,y-13,x+29,y+13),p);p.clearShadowLayer();
            p.setStyle(Paint.Style.STROKE);p.setStrokeWidth(2);p.setColor(rgb(120,160,190));c.drawOval(new RectF(x-29,y-13,x+29,y+13),p);
            if(signal){
                p.setStyle(Paint.Style.FILL);p.setColor(AMBER);p.setShadowLayer(15,0,0,AMBER);circle(c,x+(x<0?-18:18),y,7,AMBER);p.clearShadowLayer();
            }
        }

        void drawTwinGauges(Canvas c,float cx,float y){
            drawGauge(c,cx-105,y,82,speed,220,"km/h",CYAN,false);
            drawGauge(c,cx+105,y,82,rpm,8000,"RPM",CYAN,true);
        }

        void drawGauge(Canvas c,float cx,float cy,float rad,int value,int max,String unit,int color,boolean redZone){
            p.reset();p.setAntiAlias(true);p.setStyle(Paint.Style.STROKE);p.setStrokeCap(Paint.Cap.ROUND);p.setStrokeWidth(8);
            r.set(cx-rad,cy-rad,cx+rad,cy+rad);p.setColor(rgb(12,37,61));c.drawArc(r,135,270,false,p);
            float q=Math.max(0,Math.min(1,value/(float)max));p.setColor(color);p.setShadowLayer(12,0,0,color);c.drawArc(r,135,270*q,false,p);p.clearShadowLayer();
            if(redZone){p.setColor(RED);c.drawArc(r,355,50,false,p);}
            txt(c,String.valueOf(value),cx,cy+8,33,WHITE,Paint.Align.CENTER,true);
            txt(c,unit,cx,cy+34,13,rgb(202,220,235),Paint.Align.CENTER,false);
            float a=(float)Math.toRadians(135+270*q);
            float nx=cx+(float)Math.cos(a)*(rad-20), ny=cy+(float)Math.sin(a)*(rad-20);
            line(c,cx,cy,nx,ny,WHITE,3);
            circle(c,cx,cy,5,WHITE);
        }

        String waterText(){
            if(lowCoolant)return "کم";
            if(waterLevel>=0)return String.format(Locale.US,"%.0f%%",Math.max(0,Math.min(100,waterLevel)));
            return "—";
        }

        void drawDataGrid(Canvas c,float x,float y,float width){
            float gap=10, cw=(width-gap)/2f, ch=76;
            dataCard(c,x,y,cw,ch,"میزان آب رادیاتور",waterText(),lowCoolant?RED:CYAN,0);
            dataCard(c,x+cw+gap,y,cw,ch,"دمای موتور",coolant>0?coolant+"°C":"—",RED,1);
            dataCard(c,x,y+ch+gap,cw,ch,"میزان سوخت",fuel>0?String.format(Locale.US,"%.0f%%",fuel):"—",AMBER,2);
            dataCard(c,x+cw+gap,y+ch+gap,cw,ch,"ولتاژ باتری",voltage>0?String.format(Locale.US,"%.1f V",voltage):"—",GREEN,3);
        }

        void dataCard(Canvas c,float x,float y,float w,float h,String title,String value,int accent,int icon){
            int bg=lowCoolant&&icon==0?rgb(42,10,18):rgb(5,22,38);
            rr(c,x,y,x+w,y+h,17,bg,argb(190,17,101,169));
            String ic=icon==0?"💧":icon==1?"♨":icon==2?"⛽":"▣";
            txt(c,ic,x+23,y+30,20,accent,Paint.Align.CENTER,true);
            txt(c,title,x+w-14,y+27,12,rgb(204,221,235),Paint.Align.RIGHT,false);
            txt(c,value,x+w-14,y+55,20,accent,Paint.Align.RIGHT,true);
            rr(c,x+16,y+h-12,x+w-16,y+h-7,4,rgb(15,42,64),0);
            float pct=icon==0?(waterLevel>=0?Math.max(0,Math.min(1,waterLevel/100f)):(lowCoolant?.12f:.75f)):
                    icon==1?(coolant>0?Math.min(1,coolant/120f):.45f):
                    icon==2?(fuel>0?Math.min(1,fuel/100f):.45f):
                    (voltage>0?Math.max(.08f,Math.min(1,(voltage-10f)/5f)):.45f);
            rr(c,x+16,y+h-12,x+16+(w-32)*pct,y+h-7,4,accent,0);
        }

        void drawMiniData(Canvas c,float x,float y,float w,float h,String title,String val,int accent){
            rr(c,x,y,x+w,y+h,14,rgb(5,22,38),rgb(11,78,128));
            txt(c,title,x+w-10,y+20,11,rgb(185,205,222),Paint.Align.RIGHT,false);
            txt(c,val,x+w-10,y+43,18,accent,Paint.Align.RIGHT,true);
        }

        void drawBottom(Canvas c,float w,float h){
            float y=h-76;
            rr(c,12,y,w-12,h-8,24,argb(245,2,17,30),rgb(7,78,136));
            String[] icons={"▥","🔧","⌂","🚗","•••"};
            String[] labels={"گزارش‌ها","سرویس","خانه","وضعیت خودرو","بیشتر"};
            int[] pages={1,2,0,3,4};
            for(int i=0;i<5;i++){
                float cx=20+(i+.5f)*(w-40)/5f;
                boolean on=page==pages[i];
                if(on)rr(c,cx-32,y+7,cx+32,y+58,18,rgb(4,74,146),rgb(21,142,255));
                txt(c,icons[i],cx,y+31,18,on?WHITE:rgb(188,211,231),Paint.Align.CENTER,true);
                txt(c,labels[i],cx,y+52,9,on?CYAN:rgb(202,217,231),Paint.Align.CENTER,on);
            }
        }

        void drawBottomLandscape(Canvas c,float w,float h){
            float y=h-54;
            rr(c,10,y,w-10,h-6,18,argb(245,2,17,30),rgb(7,78,136));
            String[] labels={"گزارش","سرویس","خانه","خودرو","بیشتر"};
            int[] pages={1,2,0,3,4};
            for(int i=0;i<5;i++){
                float cx=(i+.5f)*w/5f;
                boolean on=page==pages[i];
                if(on)rr(c,cx-38,y+6,cx+38,h-12,13,rgb(4,74,146),rgb(21,142,255));
                txt(c,labels[i],cx,y+31,11,on?CYAN:WHITE,Paint.Align.CENTER,on);
            }
        }

        void drawSecondary(Canvas c,float w,float h){
            float top=118;
            String title=page==1?"گزارش‌ها و عیب‌یابی":page==2?"سرویس و داده زنده":page==3?"وضعیت خودرو":"بیشتر";
            txt(c,title,w-24,top,24,WHITE,Paint.Align.RIGHT,true);
            if(page==1){
                rr(c,20,150,w-20,270,20,rgb(5,25,42),rgb(13,87,140));
                txt(c,"DTC",w-42,182,13,MUTED,Paint.Align.RIGHT,false);
                String[] lines=dtcText.split("\n");
                float yy=212;for(int i=0;i<Math.min(3,lines.length);i++){txt(c,lines[i],w-42,yy,14,WHITE,Paint.Align.RIGHT,true);yy+=23;}
                rr(c,24,292,w-24,340,16,rgb(4,48,68),CYAN);txt(c,"اسکن خطاها",w/2,323,16,CYAN,Paint.Align.CENTER,true);
                rr(c,24,352,w-24,400,16,rgb(54,25,33),RED);txt(c,"پاک‌کردن خطاها",w/2,383,16,RED,Paint.Align.CENTER,true);
            }else if(page==2){
                float y=150;
                String[][] rows={{"دور موتور",rpm+" RPM"},{"سرعت",speed+" km/h"},{"دما",coolant>0?coolant+"°C":"—"},{"سوخت",fuel>0?String.format(Locale.US,"%.0f%%",fuel):"—"},{"ولتاژ",voltage>0?String.format(Locale.US,"%.2f V",voltage):"—"}};
                for(String[] row:rows){rr(c,20,y,w-20,y+58,16,rgb(5,25,42),rgb(10,64,105));txt(c,row[0],w-40,y+24,12,MUTED,Paint.Align.RIGHT,false);txt(c,row[1],w-40,y+46,16,WHITE,Paint.Align.RIGHT,true);y+=68;}
            }else if(page==3){
                rr(c,20,150,w-20,300,22,rgb(5,25,42),rgb(13,87,140));
                txt(c,connected?"SMART OBD متصل":"SMART OBD قطع",w-42,190,20,connected?GREEN:RED,Paint.Align.RIGHT,true);
                txt(c,message,w-42,220,12,MUTED,Paint.Align.RIGHT,false);
                txt(c,prefs.getString("activated_serial","سریال ثبت نشده"),w-42,252,13,CYAN,Paint.Align.RIGHT,true);
                rr(c,35,318,w-35,370,17,rgb(4,48,68),CYAN);txt(c,connected?"قطع ارتباط":"اتصال OBD",w/2,351,17,CYAN,Paint.Align.CENTER,true);
            }else{
                rr(c,20,150,w-20,330,22,rgb(5,25,42),rgb(13,87,140));
                txt(c,"ساختار خودروی زنده آماده توسعه است",w-42,190,16,WHITE,Paint.Align.RIGHT,true);
                txt(c,"چراغ‌ها • راهنما • برف‌پاک‌کن • کاپوت • درها",w-42,224,13,CYAN,Paint.Align.RIGHT,false);
                txt(c,"فرمان‌های BLE آینده مستقیماً همین مدل را حرکت می‌دهند.",w-42,258,12,MUTED,Paint.Align.RIGHT,false);
                txt(c,"نسخه Living Car 3.0",w-42,300,13,GREEN,Paint.Align.RIGHT,true);
            }
        }

        void drawSecondaryLandscape(Canvas c){
            txt(c,page==1?"گزارش‌ها":page==2?"سرویس":page==3?"وضعیت خودرو":"بیشتر",790,110,24,WHITE,Paint.Align.RIGHT,true);
            rr(c,260,125,800,330,22,rgb(5,25,42),rgb(13,87,140));
            if(page==3){
                txt(c,connected?"OBD متصل":"OBD قطع",770,170,20,connected?GREEN:RED,Paint.Align.RIGHT,true);
                txt(c,message,770,205,13,MUTED,Paint.Align.RIGHT,false);
                txt(c,prefs.getString("activated_serial","سریال ثبت نشده"),770,240,14,CYAN,Paint.Align.RIGHT,true);
            }else if(page==1){
                txt(c,"DTC",770,165,14,MUTED,Paint.Align.RIGHT,false);
                txt(c,dtcText.replace("\n"," • "),770,205,13,WHITE,Paint.Align.RIGHT,true);
            }else if(page==2){
                txt(c,"RPM "+rpm+"   |   "+speed+" km/h   |   "+coolant+"°C",770,185,19,WHITE,Paint.Align.RIGHT,true);
                txt(c,"سوخت "+String.format(Locale.US,"%.0f%%",fuel)+"   |   باتری "+String.format(Locale.US,"%.1fV",voltage),770,230,16,CYAN,Paint.Align.RIGHT,false);
            }else{
                txt(c,"Living Car Engine آماده توسعه",770,185,18,WHITE,Paint.Align.RIGHT,true);
                txt(c,"LIGHTS / WIPER / SIGNAL / HOOD / DOORS",770,225,14,CYAN,Paint.Align.RIGHT,true);
            }
        }

        void showQuickSettings(){
            String[] items={
                    connected?"قطع ارتباط OBD":"اتصال به OBD",
                    demo?"خاموش کردن دمو":"روشن کردن دمو",
                    soundAlert?"خاموش کردن هشدار صوتی":"روشن کردن هشدار صوتی",
                    "پاک کردن مچ سریال"
            };
            new AlertDialog.Builder(MainActivity.this).setTitle("تنظیمات سریع")
                    .setItems(items,(d,which)->{
                        if(which==0){if(connected)disconnect();else beginConnect();}
                        else if(which==1){demo=!demo;if(demo)disconnect();invalidate();}
                        else if(which==2){soundAlert=!soundAlert;toast(soundAlert?"هشدار صوتی فعال شد":"هشدار صوتی خاموش شد");invalidate();}
                        else if(which==3)clearActivation();
                    }).show();
        }

        @Override public boolean onTouchEvent(MotionEvent e){
            if(e.getAction()!=MotionEvent.ACTION_UP)return true;
            float x=(e.getX()-uiDx)/uiScale, y=(e.getY()-uiDy)/uiScale;
            float w=logicalW,h=logicalH;

            if(y>=18&&y<=90&&x>=w-86){showQuickSettings();return true;}
            if(y>=45&&y<=90&&x>w/2-110&&x<w/2+110){if(connected)disconnect();else beginConnect();return true;}

            if(landscape){
                if(y>h-62){
                    int i=Math.max(0,Math.min(4,(int)(x/(w/5f))));
                    int[] pages={1,2,0,3,4};page=pages[i];invalidate();return true;
                }
                if(page==3&&x>260&&y>125&&y<330){if(connected)disconnect();else beginConnect();return true;}
            }else{
                if(y>h-84){
                    int i=Math.max(0,Math.min(4,(int)((x-12)/((w-24)/5f))));
                    int[] pages={1,2,0,3,4};page=pages[i];invalidate();return true;
                }
                if(page==1&&y>285&&y<345){readDtc();return true;}
                if(page==1&&y>345&&y<410){clearDtc();return true;}
                if(page==3&&y>305&&y<385){if(connected)disconnect();else beginConnect();return true;}
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
