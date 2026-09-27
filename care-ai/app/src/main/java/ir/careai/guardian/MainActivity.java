package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.speech.tts.TextToSpeech;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.util.Locale;

public class MainActivity extends Activity implements TextToSpeech.OnInitListener {
    private SharedPreferences prefs;
    private TextToSpeech tts;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        prefs = getSharedPreferences("careai", MODE_PRIVATE);
        tts = new TextToSpeech(this, this);
        route();
    }

    private void route() {
        String role = prefs.getString("role", "");
        if ("patient".equals(role)) showPatientDashboard();
        else if ("caregiver".equals(role)) showCaregiverDashboard();
        else showRolePicker();
    }

    private ScrollView shell() {
        ScrollView sc = new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(Color.rgb(245, 248, 252));
        return sc;
    }

    private LinearLayout column() {
        LinearLayout l = new LinearLayout(this);
        l.setOrientation(LinearLayout.VERTICAL);
        l.setPadding(dp(20), dp(28), dp(20), dp(28));
        l.setGravity(Gravity.CENTER_HORIZONTAL);
        return l;
    }

    private TextView title(String s) {
        TextView t = new TextView(this);
        t.setText(s);
        t.setTextColor(Color.rgb(22, 32, 48));
        t.setTextSize(26);
        t.setGravity(Gravity.CENTER);
        t.setPadding(0, 0, 0, dp(20));
        return t;
    }

    private TextView note(String s) {
        TextView t = new TextView(this);
        t.setText(s);
        t.setTextColor(Color.rgb(77, 92, 112));
        t.setTextSize(15);
        t.setGravity(Gravity.CENTER);
        t.setPadding(dp(8), dp(8), dp(8), dp(18));
        return t;
    }

    private Button button(String s) {
        Button b = new Button(this);
        b.setText(s);
        b.setTextSize(18);
        b.setAllCaps(false);
        b.setMinHeight(dp(58));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(62));
        p.setMargins(0, dp(7), 0, dp(7));
        b.setLayoutParams(p);
        return b;
    }

    private EditText input(String hint, boolean phone) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setTextSize(17);
        e.setSingleLine(true);
        e.setPadding(dp(12), dp(12), dp(12), dp(12));
        if (phone) e.setInputType(InputType.TYPE_CLASS_PHONE);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(58));
        p.setMargins(0, dp(6), 0, dp(6));
        e.setLayoutParams(p);
        return e;
    }

    private void showRolePicker() {
        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(title("Care AI"));
        l.addView(note("سامانه ارتباط و مراقبت کمکی برای افرادی که توان گفتار محدود دارند"));

        Button patient = button("ورود به عنوان بیمار");
        Button caregiver = button("ورود به عنوان پرستار / همراه");
        l.addView(patient);
        l.addView(caregiver);

        l.addView(note("این نسخه ابزار کمکی است و جایگزین پزشک، پرستار یا تجهیزات پزشکی تأییدشده نیست."));

        patient.setOnClickListener(v -> showPatientSetup());
        caregiver.setOnClickListener(v -> showCaregiverSetup());

        sc.addView(l);
        setContentView(sc);
    }

    private void showPatientSetup() {
        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(title("تنظیم بیمار"));

        EditText name = input("نام بیمار", false);
        EditText own = input("شماره بیمار", true);
        EditText t1 = input("شماره اضطراری ۱", true);
        EditText t2 = input("شماره اضطراری ۲", true);
        EditText t3 = input("شماره اضطراری ۳", true);

        name.setText(prefs.getString("patient_name", ""));
        own.setText(prefs.getString("patient_phone", ""));
        t1.setText(prefs.getString("trusted1", ""));
        t2.setText(prefs.getString("trusted2", ""));
        t3.setText(prefs.getString("trusted3", ""));

        l.addView(name);
        l.addView(own);
        l.addView(t1);
        l.addView(t2);
        l.addView(t3);

        Button save = button("ذخیره و فعال‌سازی Care Mode");
        l.addView(save);
        l.addView(note("فقط همین سه شماره به عنوان مخاطب مورد اعتماد ذخیره می‌شوند."));

        save.setOnClickListener(v -> {
            if (t1.getText().toString().trim().isEmpty()) {
                Toast.makeText(this, "حداقل شماره اضطراری اول را وارد کنید", Toast.LENGTH_LONG).show();
                return;
            }

            prefs.edit()
                    .putString("role", "patient")
                    .putString("patient_name",
                            name.getText().toString().trim().isEmpty() ? "بیمار" : name.getText().toString().trim())
                    .putString("patient_phone", own.getText().toString().trim())
                    .putString("trusted1", t1.getText().toString().trim())
                    .putString("trusted2", t2.getText().toString().trim())
                    .putString("trusted3", t3.getText().toString().trim())
                    .apply();

            requestCorePermissions();
            showPatientDashboard();
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void showCaregiverSetup() {
        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(title("تنظیم همراه"));

        EditText own = input("شماره موبایل همراه", true);
        EditText patient = input("شماره بیمار", true);

        own.setText(prefs.getString("caregiver_phone", ""));
        patient.setText(prefs.getString("linked_patient", ""));

        l.addView(own);
        l.addView(patient);

        Button save = button("ورود");
        l.addView(save);
        l.addView(note("در نسخه سروری، شماره همراه با OTP و شناسه دستگاه با بیمار جفت می‌شود."));

        save.setOnClickListener(v -> {
            if (own.getText().toString().trim().isEmpty() || patient.getText().toString().trim().isEmpty()) {
                Toast.makeText(this, "شماره همراه و بیمار را وارد کنید", Toast.LENGTH_LONG).show();
                return;
            }

            prefs.edit()
                    .putString("role", "caregiver")
                    .putString("caregiver_phone", own.getText().toString().trim())
                    .putString("linked_patient", patient.getText().toString().trim())
                    .apply();

            showCaregiverDashboard();
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void showPatientDashboard() {
        ScrollView sc = shell();
        LinearLayout l = column();

        String name = prefs.getString("patient_name", "بیمار");
        l.addView(title("Care AI — " + name));
        l.addView(note("حالت بیمار فعال است • مخاطبان اضطراری از تنظیمات خوانده می‌شوند"));

        Button monitor = button("شروع پایش با دوربین جلو");
        Button sos = button("SOS — درخواست کمک فوری");
        Button speak = button("صحبت با من");
        Button settings = button("ویرایش شماره‌های اضطراری");
        Button reset = button("خروج از حالت بیمار");

        l.addView(monitor);
        l.addView(sos);
        l.addView(speak);
        l.addView(settings);
        l.addView(reset);

        monitor.setOnClickListener(v -> {
            if (checkSelfPermission(Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
                requestCorePermissions();
            } else {
                startActivity(new Intent(this, CameraMonitorActivity.class));
            }
        });

        sos.setOnClickListener(v ->
                EmergencyManager.sendEmergency(this, "درخواست مستقیم بیمار", true));

        speak.setOnClickListener(v ->
                speak("من اینجا هستم. اگر کمک می‌خواهید دکمه درخواست کمک را انتخاب کنید."));

        settings.setOnClickListener(v -> showPatientSetup());

        reset.setOnClickListener(v -> {
            prefs.edit().remove("role").apply();
            showRolePicker();
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void showCaregiverDashboard() {
        ScrollView sc = shell();
        LinearLayout l = column();

        String patient = prefs.getString("linked_patient", "");
        l.addView(title("پنل پرستار / همراه"));
        l.addView(note("بیمار متصل: " + patient));

        Button call = button("تماس تلفنی با بیمار");
        Button sms = button("ارسال پیام به بیمار");
        Button video = button("تماس تصویری امن — نیازمند سرور M2");
        Button reset = button("تغییر نقش");

        l.addView(call);
        l.addView(sms);
        l.addView(video);
        l.addView(reset);

        call.setOnClickListener(v ->
                startActivity(new Intent(Intent.ACTION_DIAL,
                        Uri.parse("tel:" + Uri.encode(patient)))));

        sms.setOnClickListener(v ->
                startActivity(new Intent(Intent.ACTION_SENDTO,
                        Uri.parse("smsto:" + Uri.encode(patient)))));

        video.setOnClickListener(v ->
                Toast.makeText(this,
                        "WebRTC و Push در نسخه M2 پس از اتصال سرور فعال می‌شود.",
                        Toast.LENGTH_LONG).show());

        reset.setOnClickListener(v -> {
            prefs.edit().remove("role").apply();
            showRolePicker();
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void requestCorePermissions() {
        String[] perms = new String[]{
                Manifest.permission.CAMERA,
                Manifest.permission.SEND_SMS,
                Manifest.permission.CALL_PHONE
        };
        requestPermissions(perms, 100);
    }

    private void speak(String s) {
        if (tts != null) {
            tts.speak(s, TextToSpeech.QUEUE_FLUSH, null, "careai");
        }
    }

    @Override
    public void onInit(int status) {
        if (status == TextToSpeech.SUCCESS) {
            tts.setLanguage(new Locale("fa", "IR"));
        }
    }

    @Override
    protected void onDestroy() {
        if (tts != null) {
            tts.stop();
            tts.shutdown();
        }
        super.onDestroy();
    }

    private int dp(int v) {
        return (int) (v * getResources().getDisplayMetrics().density + 0.5f);
    }
}
