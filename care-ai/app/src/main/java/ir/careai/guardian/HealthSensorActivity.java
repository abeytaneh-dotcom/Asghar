package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothManager;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanResult;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import java.util.LinkedHashMap;
import java.util.Locale;
import java.util.Map;

public class HealthSensorActivity extends Activity {

    private static final int BLUE = Color.rgb(29,120,220);
    private static final int TEAL = Color.rgb(18,185,170);
    private static final int RED = Color.rgb(220,70,78);
    private static final int TEXT = Color.rgb(19,49,83);
    private static final int MUTED = Color.rgb(91,111,134);
    private static final int BG = Color.rgb(244,249,253);

    private TextView state;
    private TextView hr;
    private TextView spo2;
    private TextView trend;
    private LinearLayout devices;

    private EditText hrMin;
    private EditText hrMax;
    private EditText spoMin;
    private EditText spoMax;
    private Spinner nurse;

    private BluetoothLeScanner scanner;
    private boolean scanning = false;
    private final Map<String,ScanResult> found = new LinkedHashMap<>();

    private final BroadcastReceiver vitalsReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            if (!BleVitalsService.ACTION_VITALS.equals(intent.getAction())) return;

            int hv = intent.getIntExtra(BleVitalsService.EXTRA_HR,-1);
            float sv = intent.getFloatExtra(BleVitalsService.EXTRA_SPO2,-1f);
            String tv = intent.getStringExtra(BleVitalsService.EXTRA_TREND);
            String st = intent.getStringExtra(BleVitalsService.EXTRA_STATE);

            updateValues(hv,sv,tv,st);
        }
    };

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(android.view.View.LAYOUT_DIRECTION_RTL);
        render();
        loadSavedValues();
        requestBlePermissionsIfNeeded();
    }

    @Override
    protected void onStart() {
        super.onStart();
        IntentFilter f = new IntentFilter(BleVitalsService.ACTION_VITALS);
        if (Build.VERSION.SDK_INT >= 33) {
            registerReceiver(vitalsReceiver,f,Context.RECEIVER_NOT_EXPORTED);
        } else {
            registerReceiver(vitalsReceiver,f);
        }
    }

    @Override
    protected void onStop() {
        try { unregisterReceiver(vitalsReceiver); } catch (Exception ignored) {}
        stopScan();
        super.onStop();
    }

    private GradientDrawable bg(int color,int r) {
        GradientDrawable g=new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(r));
        return g;
    }

    private TextView text(String s,float size,int color,boolean bold) {
        TextView t=new TextView(this);
        t.setText(s);
        t.setTextSize(size);
        t.setTextColor(color);
        if(bold)t.setTypeface(t.getTypeface(),android.graphics.Typeface.BOLD);
        return t;
    }

    private EditText input(String hint) {
        EditText e=new EditText(this);
        e.setHint(hint);
        e.setSingleLine(true);
        e.setTextSize(15);
        e.setTextColor(TEXT);
        e.setBackground(bg(Color.WHITE,14));
        e.setPadding(dp(12),dp(8),dp(12),dp(8));
        return e;
    }

    private Button button(String s,int color) {
        Button b=new Button(this);
        b.setText(s);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(15);
        b.setBackground(bg(color,16));
        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,dp(54));
        p.setMargins(0,dp(5),0,dp(5));
        b.setLayoutParams(p);
        return b;
    }

    private void render() {
        ScrollView sc=new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(BG);

        LinearLayout root=new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(16),dp(20),dp(16),dp(30));

        root.addView(text("سنسور ضربان و اکسیژن",26,TEXT,true));

        TextView note=text(
                "اتصال بر پایه Bluetooth LE استاندارد است. مقادیر و هشدارها کمک‌یار هستند و جایگزین ارزیابی پزشکی نیستند.",
                13.5f,MUTED,false
        );
        note.setPadding(0,dp(5),0,dp(14));
        root.addView(note);

        LinearLayout live=new LinearLayout(this);
        live.setOrientation(LinearLayout.VERTICAL);
        live.setPadding(dp(14),dp(14),dp(14),dp(14));
        live.setBackground(bg(Color.WHITE,22));

        state=text("وضعیت: بدون اتصال",14,MUTED,true);
        hr=text("ضربان: -- bpm",28,BLUE,true);
        spo2=text("اکسیژن: -- %",28,TEAL,true);
        trend=text("تحلیل روند: --",14,MUTED,false);

        live.addView(state);
        live.addView(hr);
        live.addView(spo2);
        live.addView(trend);
        root.addView(live);

        TextView limits=text("محدوده هشدار پیامکی",20,TEXT,true);
        limits.setPadding(0,dp(18),0,dp(8));
        root.addView(limits);

        LinearLayout lim=new LinearLayout(this);
        lim.setOrientation(LinearLayout.VERTICAL);
        lim.setPadding(dp(12),dp(12),dp(12),dp(12));
        lim.setBackground(bg(Color.WHITE,20));

        hrMin=input("حداقل ضربان");
        hrMax=input("حداکثر ضربان");
        spoMin=input("حداقل اکسیژن");
        spoMax=input("حداکثر اکسیژن");

        SharedPreferences p=getSharedPreferences("careai",MODE_PRIVATE);
        hrMin.setText(String.valueOf(p.getInt("hr_min",50)));
        hrMax.setText(String.valueOf(p.getInt("hr_max",120)));
        spoMin.setText(String.valueOf(p.getFloat("spo2_min",90f)));
        spoMax.setText(String.valueOf(p.getFloat("spo2_max",100f)));

        lim.addView(hrMin,new LinearLayout.LayoutParams(-1,dp(54)));
        lim.addView(hrMax,new LinearLayout.LayoutParams(-1,dp(54)));
        lim.addView(spoMin,new LinearLayout.LayoutParams(-1,dp(54)));
        lim.addView(spoMax,new LinearLayout.LayoutParams(-1,dp(54)));

        String[] nurses=new String[3];
        for(int i=1;i<=3;i++){
            String n=p.getString("trusted_name"+i,"").trim();
            String num=p.getString("trusted"+i,"").trim();
            nurses[i-1]=(n.isEmpty()?"پرستار/همراه "+i:n)
                    +(num.isEmpty()?"":" — "+num);
        }
        nurse=new Spinner(this);
        nurse.setAdapter(new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_dropdown_item,
                nurses
        ));
        nurse.setSelection(Math.max(0,p.getInt("vitals_nurse_slot",1)-1));
        lim.addView(nurse,new LinearLayout.LayoutParams(-1,dp(54)));

        Button save=button("ذخیره محدوده‌ها",BLUE);
        save.setOnClickListener(v->saveLimits());
        lim.addView(save);
        root.addView(lim);

        TextView con=text("اتصال سنسور BLE",20,TEXT,true);
        con.setPadding(0,dp(18),0,dp(8));
        root.addView(con);

        Button scan=button("جستجوی سنسورهای بلوتوث",TEAL);
        scan.setOnClickListener(v->startScan());
        root.addView(scan);

        devices=new LinearLayout(this);
        devices.setOrientation(LinearLayout.VERTICAL);
        root.addView(devices);

        Button disconnect=button("قطع اتصال سنسور",RED);
        disconnect.setOnClickListener(v->{
            Intent i=new Intent(this,BleVitalsService.class);
            i.setAction(BleVitalsService.ACTION_DISCONNECT);
            startService(i);
            state.setText("وضعیت: قطع شد");
        });
        root.addView(disconnect);

        Button back=button("بازگشت",Color.rgb(106,122,138));
        back.setOnClickListener(v->finish());
        root.addView(back);

        sc.addView(root);
        setContentView(sc);
    }

    private void saveLimits() {
        try {
            int hmin=Integer.parseInt(hrMin.getText().toString().trim());
            int hmax=Integer.parseInt(hrMax.getText().toString().trim());
            float smin=Float.parseFloat(spoMin.getText().toString().trim());
            float smax=Float.parseFloat(spoMax.getText().toString().trim());

            if(hmin<=0||hmax<=hmin||smin<=0||smax<smin||smax>100f){
                Toast.makeText(this,"محدوده‌ها معتبر نیستند",Toast.LENGTH_LONG).show();
                return;
            }

            getSharedPreferences("careai",MODE_PRIVATE).edit()
                    .putInt("hr_min",hmin)
                    .putInt("hr_max",hmax)
                    .putFloat("spo2_min",smin)
                    .putFloat("spo2_max",smax)
                    .putInt("vitals_nurse_slot",nurse.getSelectedItemPosition()+1)
                    .apply();

            Toast.makeText(this,"محدوده‌های هشدار ذخیره شد",Toast.LENGTH_SHORT).show();
        } catch(Exception e){
            Toast.makeText(this,"مقادیر را به صورت عدد وارد کنید",Toast.LENGTH_LONG).show();
        }
    }

    private void requestBlePermissionsIfNeeded() {
        java.util.ArrayList<String> req=new java.util.ArrayList<>();
        if(Build.VERSION.SDK_INT>=31){
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)!=PackageManager.PERMISSION_GRANTED)
                req.add(Manifest.permission.BLUETOOTH_SCAN);
            if(checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)!=PackageManager.PERMISSION_GRANTED)
                req.add(Manifest.permission.BLUETOOTH_CONNECT);
        }else{
            if(checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)!=PackageManager.PERMISSION_GRANTED)
                req.add(Manifest.permission.ACCESS_FINE_LOCATION);
        }
        if(Build.VERSION.SDK_INT>=33
                && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED){
            req.add(Manifest.permission.POST_NOTIFICATIONS);
        }
        if(!req.isEmpty()) requestPermissions(req.toArray(new String[0]),520);
    }

    private boolean hasBlePermissions() {
        if(Build.VERSION.SDK_INT>=31){
            return checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)==PackageManager.PERMISSION_GRANTED
                    && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)==PackageManager.PERMISSION_GRANTED;
        }
        return checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED;
    }

    private void startScan() {
        if(!hasBlePermissions()){
            requestBlePermissionsIfNeeded();
            return;
        }

        BluetoothManager bm=(BluetoothManager)getSystemService(BLUETOOTH_SERVICE);
        if(bm==null||bm.getAdapter()==null||!bm.getAdapter().isEnabled()){
            Toast.makeText(this,"بلوتوث را روشن کنید",Toast.LENGTH_LONG).show();
            return;
        }

        scanner=bm.getAdapter().getBluetoothLeScanner();
        if(scanner==null){
            Toast.makeText(this,"BLE Scanner در دسترس نیست",Toast.LENGTH_LONG).show();
            return;
        }

        found.clear();
        devices.removeAllViews();
        state.setText("وضعیت: در حال جستجو...");
        scanning=true;

        try{
            scanner.startScan(scanCallback);
            devices.postDelayed(this::stopScan,10000L);
        }catch(Exception e){
            state.setText("وضعیت: شروع اسکن ناموفق بود");
        }
    }

    private final ScanCallback scanCallback=new ScanCallback(){
        @Override
        public void onScanResult(int callbackType, ScanResult result){
            if(result==null||result.getDevice()==null)return;
            String address=result.getDevice().getAddress();
            if(found.containsKey(address))return;
            found.put(address,result);
            addDevice(result);
        }
    };

    private void addDevice(ScanResult result) {
        BluetoothDevice d=result.getDevice();
        String name="سنسور بدون نام";
        if(Build.VERSION.SDK_INT<31
                || checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)==PackageManager.PERMISSION_GRANTED){
            try{
                if(d.getName()!=null&&!d.getName().trim().isEmpty())name=d.getName();
            }catch(Exception ignored){}
        }

        Button b=button(
                name+"\n"+d.getAddress()+"   RSSI "+result.getRssi(),
                Color.rgb(54,117,162)
        );
        b.setGravity(Gravity.CENTER_VERTICAL|Gravity.RIGHT);
        b.setOnClickListener(v->connect(d.getAddress(),name));
        devices.addView(b);
    }

    private void connect(String address,String name) {
        stopScan();
        getSharedPreferences("careai",MODE_PRIVATE).edit()
                .putString("ble_vitals_address",address)
                .putString("ble_vitals_name",name)
                .apply();

        Intent i=new Intent(this,BleVitalsService.class);
        i.setAction(BleVitalsService.ACTION_CONNECT);
        i.putExtra(BleVitalsService.EXTRA_ADDRESS,address);
        if(Build.VERSION.SDK_INT>=26) startForegroundService(i);
        else startService(i);

        state.setText("وضعیت: در حال اتصال به "+name);
    }

    private void stopScan() {
        if(!scanning||scanner==null)return;
        scanning=false;
        try{
            if(Build.VERSION.SDK_INT<31
                    || checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN)==PackageManager.PERMISSION_GRANTED){
                scanner.stopScan(scanCallback);
            }
        }catch(Exception ignored){}
        if(found.isEmpty()) state.setText("وضعیت: سنسور BLE پیدا نشد");
    }

    private void loadSavedValues() {
        SharedPreferences p=getSharedPreferences("careai",MODE_PRIVATE);
        updateValues(
                p.getInt("last_hr",-1),
                p.getFloat("last_spo2",-1f),
                p.getString("last_vitals_trend","--"),
                p.getString("ble_vitals_address","").isEmpty()
                        ?"بدون اتصال"
                        :"آخرین سنسور: "+p.getString("ble_vitals_name","")
        );
    }

    private void updateValues(int h,float s,String tr,String st) {
        if(hr==null)return;
        hr.setText(h>0 ? "ضربان: "+h+" bpm" : "ضربان: -- bpm");
        spo2.setText(s>0 ? String.format(Locale.US,"اکسیژن: %.1f %%",s) : "اکسیژن: -- %");
        trend.setText("تحلیل روند: "+(tr==null?"--":tr));
        state.setText("وضعیت: "+(st==null?"--":st));
    }

    private int dp(int v){
        return (int)(v*getResources().getDisplayMetrics().density+0.5f);
    }
}
