package ir.khanehremap.smartobd;

import android.Manifest;
import android.app.*;
import android.bluetooth.*;
import android.bluetooth.le.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.graphics.*;
import android.graphics.drawable.GradientDrawable;
import android.os.*;
import android.provider.Settings;
import android.speech.tts.TextToSpeech;
import android.view.*;
import android.widget.*;

import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.concurrent.*;

public class MainActivity extends Activity {
    static final UUID BLE_SERVICE = UUID.fromString("7f640101-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID BLE_CMD     = UUID.fromString("7f640102-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID BLE_DATA    = UUID.fromString("7f640103-7c7d-4f0a-8b6f-4f484f4d4501");
    static final UUID CCCD        = UUID.fromString("00002902-0000-1000-8000-00805f9b34fb");

    final int BG=Color.rgb(1,14,24), CARD=Color.rgb(9,34,52), CARD2=Color.rgb(10,39,58);
    final int CYAN=Color.rgb(0,211,230), CYAN2=Color.rgb(0,157,184), RED=Color.rgb(255,74,96);
    final int GREEN=Color.rgb(48,220,150), AMBER=Color.rgb(255,184,60), TEXT=Color.rgb(238,246,251);
    final int MUTED=Color.rgb(132,156,173), LINE=Color.rgb(18,70,88);

    LinearLayout root, content, bottomNav;
    TextView connectionPill, heroState, heroDevice, ecuState, ecuDetail, serialState;
    TextView rpmVal, speedVal, ectVal, fuelVal, voltVal, coolantVal, dtcLog;
    Switch demoSwitch, voiceSwitch;
    TextToSpeech tts;
    Handler h = new Handler(Looper.getMainLooper());

    BluetoothGatt gatt;
    BluetoothGattCharacteristic cmdCh, dataCh;
    BluetoothLeScanner scanner;
    ScanCallback scanCallback;
    boolean bleConnected=false, serialVerified=false, demo=false, alive=true;
    String page="connect", pendingSerial="", deviceSerial="", protocol="NONE", lastDeviceName="SMART OBD";

    int rpm=0, speed=0, ect=0;
    float fuel=0, volt=0;
    String coolant="--";

    SharedPreferences prefs;

    @Override public void onCreate(Bundle b){
        super.onCreate(b);
        getWindow().setStatusBarColor(BG);
        getWindow().setNavigationBarColor(BG);
        getWindow().getDecorView().setSystemUiVisibility(0);
        prefs=getSharedPreferences("smartobd",MODE_PRIVATE);
        pendingSerial=prefs.getString("serial","");
        tts=new TextToSpeech(this,s->{ if(s==TextToSpeech.SUCCESS) tts.setLanguage(new Locale("fa","IR")); });
        requestBluetoothPermissions();
        render("connect");
        demoLoop();
    }

    int dp(float n){return (int)(n*getResources().getDisplayMetrics().density+.5f);}
    GradientDrawable bg(int fill,int stroke,float radius){
        GradientDrawable d=new GradientDrawable();
        d.setColor(fill); d.setCornerRadius(dp(radius));
        if(stroke!=0)d.setStroke(dp(1),stroke);
        return d;
    }
    TextView text(String s,float sp,int color){
        TextView v=new TextView(this);
        v.setText(s); v.setTextSize(sp); v.setTextColor(color);
        v.setGravity(Gravity.RIGHT|Gravity.CENTER_VERTICAL);
        v.setTextDirection(View.TEXT_DIRECTION_RTL);
        v.setPadding(dp(8),dp(6),dp(8),dp(6));
        return v;
    }
    TextView title(String s,float sp){
        TextView v=text(s,sp,TEXT);
        v.setTypeface(android.graphics.Typeface.create("sans-serif",android.graphics.Typeface.BOLD));
        return v;
    }
    LinearLayout card(){
        LinearLayout c=new LinearLayout(this);
        c.setOrientation(LinearLayout.VERTICAL);
        c.setPadding(dp(18),dp(16),dp(18),dp(16));
        c.setBackground(bg(CARD,0,22));
        return c;
    }
    Button outlineButton(String s){
        Button b=new Button(this);
        b.setText(s); b.setAllCaps(false); b.setTextSize(17); b.setTextColor(CYAN);
        b.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        b.setBackground(bg(Color.TRANSPARENT,CYAN2,18));
        b.setPadding(dp(10),0,dp(10),0);
        return b;
    }
    Button solidButton(String s){
        Button b=new Button(this);
        b.setText(s); b.setAllCaps(false); b.setTextSize(16); b.setTextColor(Color.WHITE);
        b.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        b.setBackground(bg(Color.rgb(0,125,150),0,18));
        return b;
    }

    void render(String p){
        page=p;
        root=new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(BG);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        setContentView(root);

        header();
        ScrollView sc=new ScrollView(this);
        sc.setFillViewport(true);
        content=new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(dp(16),dp(14),dp(16),dp(18));
        sc.addView(content,new ScrollView.LayoutParams(-1,-2));
        root.addView(sc,new LinearLayout.LayoutParams(-1,0,1));

        if("connect".equals(p)) connectionPage();
        else if("diag".equals(p)) diagPage();
        else if("live".equals(p)) livePage();
        else dashboardPage();

        nav();
    }

    void header(){
        LinearLayout hbox=new LinearLayout(this);
        hbox.setOrientation(LinearLayout.VERTICAL);
        hbox.setPadding(dp(20),dp(20),dp(20),dp(12));

        LinearLayout top=new LinearLayout(this);
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.setLayoutDirection(View.LAYOUT_DIRECTION_LTR);

        connectionPill=text(serialVerified?"● OBD متصل":"● قطع OBD",14,serialVerified?GREEN:RED);
        connectionPill.setGravity(Gravity.CENTER);
        connectionPill.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        connectionPill.setBackground(bg(serialVerified?Color.rgb(15,53,48):Color.rgb(54,27,37),0,28));
        top.addView(connectionPill,new LinearLayout.LayoutParams(dp(140),dp(48)));

        LinearLayout brand=new LinearLayout(this);
        brand.setOrientation(LinearLayout.VERTICAL);
        brand.setGravity(Gravity.RIGHT);
        TextView n=title("خانه ریمپ",25); n.setGravity(Gravity.RIGHT);
        TextView sub=title("SMART OBD",14); sub.setTextColor(CYAN); sub.setGravity(Gravity.RIGHT);
        brand.addView(n);brand.addView(sub);
        LinearLayout.LayoutParams bp=new LinearLayout.LayoutParams(0,-2,1);bp.setMargins(dp(12),0,dp(12),0);
        top.addView(brand,bp);

        HexLogo logo=new HexLogo(this);
        top.addView(logo,new LinearLayout.LayoutParams(dp(62),dp(62)));

        hbox.addView(top);
        View line=new View(this);line.setBackgroundColor(Color.rgb(14,65,78));
        LinearLayout.LayoutParams lp=new LinearLayout.LayoutParams(-1,dp(1));lp.setMargins(0,dp(18),0,0);
        hbox.addView(line,lp);
        root.addView(hbox);
    }

    void connectionPage(){
        TextView t=title("اتصال و تنظیمات",28); t.setGravity(Gravity.RIGHT);content.addView(t);
        TextView st=text("مدیریت ارتباط BLE، سریال دستگاه و تجربه رانندگی",14,MUTED);st.setGravity(Gravity.RIGHT);
        LinearLayout.LayoutParams sp=new LinearLayout.LayoutParams(-1,-2);sp.setMargins(0,0,0,dp(18));content.addView(st,sp);

        LinearLayout hero=card();
        hero.setPadding(dp(20),dp(20),dp(20),dp(18));

        LinearLayout hr=new LinearLayout(this);hr.setGravity(Gravity.CENTER_VERTICAL);
        LinearLayout hi=new LinearLayout(this);hi.setOrientation(LinearLayout.VERTICAL);
        heroState=title(serialVerified?"متصل و فعال":"بدون اتصال",27);heroState.setGravity(Gravity.RIGHT);
        String shown=pendingSerial.isEmpty()?"سریال ثبت نشده":pendingSerial;
        heroDevice=title(shown,15);heroDevice.setTextColor(serialVerified?GREEN:RED);heroDevice.setGravity(Gravity.RIGHT);
        hi.addView(heroState);hi.addView(heroDevice);
        hr.addView(hi,new LinearLayout.LayoutParams(0,-2,1));

        TextView bolt=title("⌁",38);bolt.setGravity(Gravity.CENTER);bolt.setTextColor(serialVerified?GREEN:RED);
        bolt.setBackground(bg(Color.rgb(39,40,62),0,44));
        hr.addView(bolt,new LinearLayout.LayoutParams(dp(88),dp(88)));
        hero.addView(hr);

        serialState=text(serialVerified?"سریال دستگاه تأیید شده است":"برای شروع، سریال دستگاه را وارد و به ESP32-C3 متصل شوید",13,MUTED);
        serialState.setGravity(Gravity.RIGHT);hero.addView(serialState);

        Button connect=outlineButton(serialVerified?"قطع اتصال":"اتصال به OBD");
        LinearLayout.LayoutParams cp=new LinearLayout.LayoutParams(-1,dp(56));cp.setMargins(0,dp(12),0,0);
        hero.addView(connect,cp);
        connect.setOnClickListener(v->{ if(bleConnected) disconnect(); else beginActivation(); render("connect"); });

        LinearLayout.LayoutParams hp=new LinearLayout.LayoutParams(-1,-2);hp.setMargins(0,0,0,dp(16));content.addView(hero,hp);

        LinearLayout ecu=card();
        TextView et=text("شناسایی خودکار ECU",16,MUTED);ecu.addView(et);
        ecuState=title(serialVerified?(protocol.equals("NONE")?"در حال شناسایی…":"ECU متصل"):"هنوز شناسایی نشده",21);ecu.addView(ecuState);
        ecuDetail=text(serialVerified?"تشخیص خودکار CAN 500/250 و K-Line":"تشخیص خودکار • OBD-II • بعد از تأیید سریال",13,CYAN);
        ecu.addView(ecuDetail);
        LinearLayout.LayoutParams ep=new LinearLayout.LayoutParams(-1,-2);ep.setMargins(0,0,0,dp(16));content.addView(ecu,ep);

        LinearLayout demoCard=card();demoCard.setOrientation(LinearLayout.HORIZONTAL);demoCard.setGravity(Gravity.CENTER_VERTICAL);
        demoSwitch=new Switch(this);demoSwitch.setChecked(demo);demoSwitch.setOnCheckedChangeListener((b,c)->{demo=c;if(demo){serialVerified=false;disconnect();}render("connect");});
        demoCard.addView(demoSwitch,new LinearLayout.LayoutParams(dp(95),-2));
        TextView dl=title("حالت دمو بدون خودرو",18);demoCard.addView(dl,new LinearLayout.LayoutParams(0,-2,1));
        LinearLayout.LayoutParams dp1=new LinearLayout.LayoutParams(-1,-2);dp1.setMargins(0,0,0,dp(16));content.addView(demoCard,dp1);

        LinearLayout voice=card();voice.setOrientation(LinearLayout.HORIZONTAL);voice.setGravity(Gravity.CENTER_VERTICAL);
        voiceSwitch=new Switch(this);voiceSwitch.setChecked(prefs.getBoolean("voice",true));voiceSwitch.setOnCheckedChangeListener((b,c)->prefs.edit().putBoolean("voice",c).apply());
        voice.addView(voiceSwitch,new LinearLayout.LayoutParams(dp(95),-2));
        TextView vl=title("هشدار صوتی پارامترهای بحرانی",18);voice.addView(vl,new LinearLayout.LayoutParams(0,-2,1));
        content.addView(voice);

        TextView foot=text("نسخه حرفه‌ای 2.4  •  طراحی اختصاصی خانه ریمپ",12,Color.rgb(71,96,112));foot.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams fp=new LinearLayout.LayoutParams(-1,-2);fp.setMargins(0,dp(26),0,dp(12));content.addView(foot,fp);
    }

    void beginActivation(){
        if(demo){toast("ابتدا حالت دمو را خاموش کنید");return;}
        if(!hasBlePermissions()){requestBluetoothPermissions();return;}
        if(pendingSerial==null||pendingSerial.trim().isEmpty()){
            askSerialThenScan();
        } else {
            scanAndConnect();
        }
    }

    void askSerialThenScan(){
        final EditText in=new EditText(this);
        in.setHint("مثال: KR-26-000001");
        in.setSingleLine(true);
        in.setTextColor(TEXT);in.setHintTextColor(MUTED);
        in.setTextDirection(View.TEXT_DIRECTION_LTR);
        in.setBackground(bg(Color.rgb(7,29,44),CYAN2,12));
        in.setPadding(dp(14),dp(8),dp(14),dp(8));
        LinearLayout wrap=new LinearLayout(this);wrap.setPadding(dp(18),0,dp(18),0);wrap.addView(in,new LinearLayout.LayoutParams(-1,dp(52)));
        AlertDialog d=new AlertDialog.Builder(this)
                .setTitle("سریال SMART OBD")
                .setMessage("سریال روی لیبل دستگاه را وارد کنید. فعلاً فعال‌سازی فقط با سریال انجام می‌شود.")
                .setView(wrap)
                .setNegativeButton("انصراف",null)
                .setPositiveButton("ادامه",null).create();
        d.setOnShowListener(x->d.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v->{
            String s=in.getText().toString().trim().toUpperCase(Locale.US);
            if(!validSerial(s)){in.setError("سریال معتبر نیست");return;}
            pendingSerial=s;d.dismiss();scanAndConnect();
        }));
        d.show();
    }

    boolean validSerial(String s){return s!=null&&s.matches("[A-Z0-9_-]{8,32}");}

    void scanAndConnect(){
        BluetoothManager bm=(BluetoothManager)getSystemService(BLUETOOTH_SERVICE);
        BluetoothAdapter a=bm==null?null:bm.getAdapter();
        if(a==null){toast("بلوتوث این گوشی در دسترس نیست");return;}
        if(!a.isEnabled()){startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));return;}
        scanner=a.getBluetoothLeScanner();
        if(scanner==null){toast("BLE در دسترس نیست");return;}

        if(heroState!=null)heroState.setText("در حال جستجو…");
        if(serialState!=null)serialState.setText("ESP32-C3 روشن و نزدیک گوشی باشد");
        final ArrayList<BluetoothDevice> found=new ArrayList<>();
        final ArrayList<String> labels=new ArrayList<>();

        scanCallback=new ScanCallback(){
            @Override public void onScanResult(int type,ScanResult r){
                BluetoothDevice d=r.getDevice();if(d==null)return;
                String n=safeName(d);
                boolean ours=n.toLowerCase(Locale.US).contains("khanehremap")||n.toLowerCase(Locale.US).contains("obd");
                if(!ours&&r.getScanRecord()!=null&&r.getScanRecord().getServiceUuids()!=null){
                    for(ParcelUuid u:r.getScanRecord().getServiceUuids())if(BLE_SERVICE.equals(u.getUuid())){ours=true;break;}
                }
                if(ours&&!found.contains(d)){found.add(d);labels.add(n+"\n"+d.getAddress());}
            }
            @Override public void onScanFailed(int e){runOnUiThread(()->{toast("خطای جستجوی BLE: "+e);render("connect");});}
        };
        scanner.startScan(scanCallback);
        h.postDelayed(()->{
            try{scanner.stopScan(scanCallback);}catch(Exception ignored){}
            if(found.isEmpty()){toast("SMART OBD پیدا نشد");render("connect");return;}
            if(found.size()==1){connectGatt(found.get(0));return;}
            new AlertDialog.Builder(this).setTitle("انتخاب SMART OBD").setItems(labels.toArray(new String[0]),(d,i)->connectGatt(found.get(i))).show();
        },4500);
    }

    void connectGatt(BluetoothDevice d){
        lastDeviceName=safeName(d);
        if(heroState!=null)heroState.setText("در حال اتصال…");
        try{
            gatt=d.connectGatt(this,false,gattCallback,BluetoothDevice.TRANSPORT_LE);
        }catch(Exception e){toast("اتصال BLE ناموفق: "+e.getMessage());render("connect");}
    }

    final BluetoothGattCallback gattCallback=new BluetoothGattCallback(){
        @Override public void onConnectionStateChange(BluetoothGatt g,int status,int newState){
            if(newState==BluetoothProfile.STATE_CONNECTED){
                bleConnected=true;
                try{g.requestMtu(185);}catch(Exception ignored){}
                g.discoverServices();
                runOnUiThread(()->{if(connectionPill!=null){connectionPill.setText("● BLE متصل");connectionPill.setTextColor(AMBER);}});
            }else if(newState==BluetoothProfile.STATE_DISCONNECTED){
                bleConnected=false;serialVerified=false;cmdCh=null;dataCh=null;
                runOnUiThread(()->render(page));
            }
        }
        @Override public void onServicesDiscovered(BluetoothGatt g,int status){
            BluetoothGattService s=g.getService(BLE_SERVICE);
            if(s==null){runOnUiThread(()->{toast("سرویس SMART OBD روی ESP32 پیدا نشد");disconnect();});return;}
            cmdCh=s.getCharacteristic(BLE_CMD); dataCh=s.getCharacteristic(BLE_DATA);
            if(cmdCh==null||dataCh==null){runOnUiThread(()->{toast("Firmware دستگاه با اپ سازگار نیست");disconnect();});return;}
            enableNotify(g,dataCh);
            h.postDelayed(()->sendRaw("GET_SERIAL"),650);
            runOnUiThread(()->{if(heroState!=null)heroState.setText("بررسی سریال…");});
        }
        @Override public void onCharacteristicChanged(BluetoothGatt g,BluetoothGattCharacteristic c){
            handleBleValue(c.getValue());
        }
        @Override public void onCharacteristicChanged(BluetoothGatt g,BluetoothGattCharacteristic c,byte[] value){
            handleBleValue(value);
        }
    };

    void enableNotify(BluetoothGatt g,BluetoothGattCharacteristic c){
        try{
            g.setCharacteristicNotification(c,true);
            BluetoothGattDescriptor d=c.getDescriptor(CCCD);
            if(d!=null){
                if(Build.VERSION.SDK_INT>=33)g.writeDescriptor(d,BluetoothGattDescriptor.ENABLE_NOTIFICATION_VALUE);
                else{d.setValue(BluetoothGattDescriptor.ENABLE_NOTIFICATION_VALUE);g.writeDescriptor(d);}
            }
        }catch(Exception ignored){}
    }

    void handleBleValue(byte[] v){
        String line=new String(v,StandardCharsets.UTF_8).trim();
        runOnUiThread(()->applyLine(line));
    }

    void applyLine(String s){
        if(s==null||s.isEmpty())return;
        try{
            if(s.startsWith("SERIAL:SET,")){deviceSerial=s.substring("SERIAL:SET,".length()).trim();verifySerial(deviceSerial);return;}
            if(s.startsWith("SERIAL:")){
                deviceSerial=s.substring(7).trim().toUpperCase(Locale.US);
                if("UNSET".equals(deviceSerial)){
                    if(validSerial(pendingSerial)){
                        new AlertDialog.Builder(this)
                                .setTitle("ثبت سریال روی دستگاه")
                                .setMessage("این ESP32 هنوز سریال ندارد.\nسریال "+pendingSerial+" برای همیشه روی این دستگاه ثبت شود؟")
                                .setNegativeButton("خیر",(d,w)->disconnect())
                                .setPositiveButton("ثبت",(d,w)->sendRaw("SET_SERIAL:"+pendingSerial))
                                .show();
                    }else{toast("ESP32 بدون سریال است");disconnect();}
                }else verifySerial(deviceSerial);
                return;
            }

            if(!serialVerified)return;
            if(s.startsWith("RPM:"))rpm=Integer.parseInt(s.substring(4).trim());
            else if(s.startsWith("SPEED:"))speed=Integer.parseInt(s.substring(6).trim());
            else if(s.startsWith("ECT:"))ect=Integer.parseInt(s.substring(4).trim());
            else if(s.startsWith("FUEL:"))fuel=Float.parseFloat(s.substring(5).trim());
            else if(s.startsWith("VOLT:"))volt=Float.parseFloat(s.substring(5).trim());
            else if(s.equals("COOLANT:OK"))coolant="مناسب";
            else if(s.startsWith("COOLANT:CHECK"))coolant="در حال بررسی";
            else if(s.startsWith("ALARM:LOW_COOLANT")){coolant="کمبود آب";if(prefs.getBoolean("voice",true))speak("هشدار، سطح آب خنک کننده پایین است");}
            else if(s.startsWith("ECU:CONNECTED")){protocol=s.replace("ECU:CONNECTED,","");}
            else if(s.startsWith("DTC:")){if(dtcLog!=null)dtcLog.setText(s.equals("DTC:NONE")?"هیچ خطای فعالی گزارش نشد":s.substring(4).replace(",","\n"));}
            else if(s.startsWith("CLEAR_DTC:")){if(dtcLog!=null)dtcLog.setText(s.equals("CLEAR_DTC:SENT")?"فرمان پاک‌کردن خطا ارسال شد":"پاک‌کردن خطا انجام نشد");}
            updateVisibleData();
        }catch(Exception ignored){}
    }

    void verifySerial(String actual){
        String want=pendingSerial==null?"":pendingSerial.trim().toUpperCase(Locale.US);
        if(want.equals(actual)){
            serialVerified=true;
            prefs.edit().putString("serial",actual).apply();
            if(connectionPill!=null){connectionPill.setText("● OBD متصل");connectionPill.setTextColor(GREEN);}
            toast("سریال تأیید شد • دستگاه فعال است");
            sendRaw("STATUS");
            h.postDelayed(()->sendRaw("REDETECT"),350);
            render(page);
        }else{
            serialVerified=false;
            new AlertDialog.Builder(this)
                    .setTitle("عدم تطابق سریال")
                    .setMessage("سریال واردشده:\n"+want+"\n\nسریال ESP32:\n"+actual+"\n\nاین دستگاه اجازه فعالیت با این سریال را ندارد.")
                    .setPositiveButton("تغییر سریال",(d,w)->{prefs.edit().remove("serial").apply();pendingSerial="";disconnect();render("connect");})
                    .setNegativeButton("بستن",(d,w)->disconnect()).show();
        }
    }

    void sendRaw(String s){
        if(gatt==null||cmdCh==null)return;
        try{
            byte[] b=s.getBytes(StandardCharsets.UTF_8);
            if(Build.VERSION.SDK_INT>=33)gatt.writeCharacteristic(cmdCh,b,BluetoothGattCharacteristic.WRITE_TYPE_NO_RESPONSE);
            else{cmdCh.setWriteType(BluetoothGattCharacteristic.WRITE_TYPE_NO_RESPONSE);cmdCh.setValue(b);gatt.writeCharacteristic(cmdCh);}
        }catch(Exception ignored){}
    }
    void send(String s){
        if(demo){toast("حالت دمو");return;}
        if(!serialVerified){toast("ابتدا سریال دستگاه را تأیید و متصل شوید");return;}
        sendRaw(s);
    }

    void diagPage(){
        sectionTitle("عیب‌یابی","خواندن و پاک‌کردن کدهای خطای ECU");
        LinearLayout c=card();
        dtcLog=title(serialVerified?"آماده خواندن خطا":"ابتدا دستگاه را متصل کنید",18);
        c.addView(dtcLog);
        Button read=solidButton("خواندن DTC");
        read.setOnClickListener(v->send("READ_DTC"));
        LinearLayout.LayoutParams rp=new LinearLayout.LayoutParams(-1,dp(54));rp.setMargins(0,dp(16),0,dp(10));c.addView(read,rp);
        Button clear=outlineButton("پاک کردن خطاها");
        clear.setOnClickListener(v->new AlertDialog.Builder(this).setTitle("پاک کردن DTC").setMessage("در صورت اطمینان ادامه دهید.").setNegativeButton("انصراف",null).setPositiveButton("پاک کردن",(d,w)->send("CLEAR_DTC")).show());
        c.addView(clear,new LinearLayout.LayoutParams(-1,dp(54)));
        content.addView(c);
    }

    void livePage(){
        sectionTitle("داده زنده","پارامترهای دریافتی از ECU و سنسور دستگاه");
        rpmVal=metric("RPM","دور موتور",rpm+" rpm",CYAN);
        speedVal=metric("SPD","سرعت",speed+" km/h",TEXT);
        ectVal=metric("ECT","دمای آب",(ect==0?"--":ect+" °C"),RED);
        fuelVal=metric("FUEL","سطح سوخت",(fuel==0?"--":String.format(Locale.US,"%.0f %%",fuel)),AMBER);
        voltVal=metric("V","ولتاژ",(volt==0?"--":String.format(Locale.US,"%.2f V",volt)),GREEN);
        coolantVal=metric("H2O","سطح آب",coolant,coolant.equals("کمبود آب")?RED:GREEN);
    }

    void dashboardPage(){
        sectionTitle("داشبورد","SMART OBD خانه ریمپ");
        LinearLayout s=card();
        TextView big=title(serialVerified?"خودرو آنلاین":"OBD آفلاین",28);big.setTextColor(serialVerified?GREEN:RED);s.addView(big);
        s.addView(text("سریال: "+(pendingSerial.isEmpty()?"ثبت نشده":pendingSerial),15,MUTED));
        s.addView(text("پروتکل: "+protocol,15,CYAN));
        LinearLayout.LayoutParams pp=new LinearLayout.LayoutParams(-1,-2);pp.setMargins(0,0,0,dp(14));content.addView(s,pp);
        rpmVal=metric("RPM","دور موتور",rpm+" rpm",CYAN);
        speedVal=metric("SPD","سرعت",speed+" km/h",TEXT);
        ectVal=metric("ECT","دمای آب",(ect==0?"--":ect+" °C"),RED);
        coolantVal=metric("H2O","سطح آب",coolant,coolant.equals("کمبود آب")?RED:GREEN);
    }

    void sectionTitle(String a,String b){
        TextView t=title(a,28);content.addView(t);
        TextView s=text(b,14,MUTED);
        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,-2);p.setMargins(0,0,0,dp(18));content.addView(s,p);
    }

    TextView metric(String icon,String name,String value,int color){
        LinearLayout c=card();c.setOrientation(LinearLayout.HORIZONTAL);c.setGravity(Gravity.CENTER_VERTICAL);
        TextView ic=title(icon,16);ic.setGravity(Gravity.CENTER);ic.setTextColor(color);ic.setBackground(bg(Color.rgb(20,48,67),0,32));
        c.addView(ic,new LinearLayout.LayoutParams(dp(64),dp(64)));
        LinearLayout v=new LinearLayout(this);v.setOrientation(LinearLayout.VERTICAL);
        TextView n=text(name,14,MUTED);TextView val=title(value,22);val.setTextColor(color);
        v.addView(n);v.addView(val);c.addView(v,new LinearLayout.LayoutParams(0,-2,1));
        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,-2);p.setMargins(0,0,0,dp(12));content.addView(c,p);
        return val;
    }

    void updateVisibleData(){
        if(rpmVal!=null)rpmVal.setText(rpm+" rpm");
        if(speedVal!=null)speedVal.setText(speed+" km/h");
        if(ectVal!=null)ectVal.setText(ect==0?"--":ect+" °C");
        if(fuelVal!=null)fuelVal.setText(fuel==0?"--":String.format(Locale.US,"%.0f %%",fuel));
        if(voltVal!=null)voltVal.setText(volt==0?"--":String.format(Locale.US,"%.2f V",volt));
        if(coolantVal!=null){coolantVal.setText(coolant);coolantVal.setTextColor(coolant.equals("کمبود آب")?RED:GREEN);}
        if(ecuState!=null)ecuState.setText(serialVerified?(protocol.equals("NONE")?"در حال شناسایی…":"ECU متصل"):"هنوز شناسایی نشده");
        if(ecuDetail!=null)ecuDetail.setText(serialVerified?"پروتکل: "+protocol:"تشخیص خودکار • بعد از تأیید سریال");
    }

    void nav(){
        bottomNav=new LinearLayout(this);
        bottomNav.setGravity(Gravity.CENTER);
        bottomNav.setBackgroundColor(Color.rgb(2,24,36));
        String[][] tabs={{"connect","⌁","اتصال"},{"diag","⌕","عیب‌یابی"},{"live","≋","داده زنده"},{"dash","▣","داشبورد"}};
        for(String[] t:tabs){
            LinearLayout b=new LinearLayout(this);b.setOrientation(LinearLayout.VERTICAL);b.setGravity(Gravity.CENTER);b.setPadding(dp(2),dp(6),dp(2),dp(4));
            TextView i=title(t[1],25);i.setGravity(Gravity.CENTER);i.setTextColor(page.equals(t[0])?CYAN:MUTED);
            TextView n=text(t[2],12,page.equals(t[0])?CYAN:MUTED);n.setGravity(Gravity.CENTER);
            b.addView(i);b.addView(n);
            String target=t[0];b.setOnClickListener(v->render(target));
            bottomNav.addView(b,new LinearLayout.LayoutParams(0,dp(82),1));
        }
        root.addView(bottomNav);
    }

    void demoLoop(){
        h.postDelayed(new Runnable(){public void run(){
            if(!alive)return;
            if(demo){
                rpm=900+(int)((Math.sin(System.currentTimeMillis()/900.0)+1)*1500);
                speed=Math.max(0,(rpm-900)/28);ect=89;fuel=58;volt=13.9f;coolant="مناسب";protocol="DEMO";
                updateVisibleData();
            }
            h.postDelayed(this,700);
        }},700);
    }

    void requestBluetoothPermissions(){
        if(Build.VERSION.SDK_INT>=31){
            ArrayList<String> q=new ArrayList<>();
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)!=PackageManager.PERMISSION_GRANTED)q.add(Manifest.permission.BLUETOOTH_SCAN);
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED)q.add(Manifest.permission.BLUETOOTH_CONNECT);
            if(!q.isEmpty())requestPermissions(q.toArray(new String[0]),10);
        }else if(Build.VERSION.SDK_INT>=23&&checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)!=PackageManager.PERMISSION_GRANTED){
            requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION},11);
        }
    }
    boolean hasBlePermissions(){
        return Build.VERSION.SDK_INT<31 || (checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)==PackageManager.PERMISSION_GRANTED && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)==PackageManager.PERMISSION_GRANTED);
    }
    String safeName(BluetoothDevice d){
        try{String n=d.getName();return n==null?"KhanehRemap-OBD-C3":n;}catch(Exception e){return "KhanehRemap-OBD-C3";}
    }
    void disconnect(){
        try{if(scanner!=null&&scanCallback!=null&&hasBlePermissions())scanner.stopScan(scanCallback);}catch(Exception ignored){}
        try{if(gatt!=null){gatt.disconnect();gatt.close();}}catch(Exception ignored){}
        gatt=null;cmdCh=null;dataCh=null;bleConnected=false;serialVerified=false;deviceSerial="";protocol="NONE";
    }
    void speak(String s){if(tts!=null)tts.speak(s,TextToSpeech.QUEUE_FLUSH,null,"obd-alert");}
    void toast(String s){Toast.makeText(this,s,Toast.LENGTH_SHORT).show();}

    @Override protected void onDestroy(){alive=false;disconnect();if(tts!=null){tts.stop();tts.shutdown();}super.onDestroy();}

    class HexLogo extends View{
        Paint p=new Paint(Paint.ANTI_ALIAS_FLAG);
        HexLogo(Context c){super(c);setLayerType(View.LAYER_TYPE_SOFTWARE,null);}
        @Override protected void onDraw(Canvas c){
            float w=getWidth(),h=getHeight(),cx=w/2,cy=h/2,r=Math.min(w,h)*.42f;
            Path q=new Path();
            for(int i=0;i<6;i++){double a=Math.toRadians(-90+i*60);float x=(float)(cx+r*Math.cos(a)),y=(float)(cy+r*Math.sin(a));if(i==0)q.moveTo(x,y);else q.lineTo(x,y);}
            q.close();p.setStyle(Paint.Style.FILL);p.setColor(CYAN);p.setShadowLayer(dp(12),0,dp(4),Color.rgb(0,130,155));c.drawPath(q,p);p.clearShadowLayer();
            p.setColor(Color.rgb(0,40,54));p.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);p.setTextAlign(Paint.Align.CENTER);p.setTextSize(dp(26));c.drawText("A",cx,cy+dp(9),p);
        }
    }
}
