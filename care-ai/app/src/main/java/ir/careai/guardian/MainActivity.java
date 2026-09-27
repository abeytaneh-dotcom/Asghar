package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.Build;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {
    private SharedPreferences prefs;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private boolean autoLaunchScheduled = false;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        prefs = getSharedPreferences("careai", MODE_PRIVATE);
        route();
    }

    private void route() {
        String role = prefs.getString("role", "");
        if ("patient".equals(role)) {
            showPatientDashboard(true);
        } else if ("caregiver".equals(role)) {
            showCaregiverDashboard();
        } else {
            showRolePicker();
        }
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
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(title("Care AI"));
        l.addView(note("سامانه ارتباط و مراقبت کمکی برای افرادی که توان گفتار محدود دارند"));

        Button patient = button("ورود به عنوان بیمار");
        Button caregiver = button("ورود به عنوان پرستار / همراه");
        l.addView(patient);
        l.addView(caregiver);

        l.addView(note("پس از تنظیم بیمار، Care Mode می‌تواند بدون لمس و فقط با چشم و پلک کنترل شود."));

        patient.setOnClickListener(v -> showPatientSetup());
        caregiver.setOnClickListener(v -> showCaregiverSetup());

        sc.addView(l);
        setContentView(sc);
    }

    private void showPatientSetup() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(title("تنظیم بیمار"));

        EditText name = input("نام بیمار", false);
        EditText own = input("شماره بیمار", true);
        EditText n1 = input("نام همراه ۱", false);
        EditText t1 = input("شماره اضطراری ۱", true);
        EditText n2 = input("نام همراه ۲", false);
        EditText t2 = input("شماره اضطراری ۲", true);
        EditText n3 = input("نام همراه ۳", false);
        EditText t3 = input("شماره اضطراری ۳", true);

        name.setText(prefs.getString("patient_name", ""));
        own.setText(prefs.getString("patient_phone", ""));
        n1.setText(prefs.getString("trusted_name1", ""));
        t1.setText(prefs.getString("trusted1", ""));
        n2.setText(prefs.getString("trusted_name2", ""));
        t2.setText(prefs.getString("trusted2", ""));
        n3.setText(prefs.getString("trusted_name3", ""));
        t3.setText(prefs.getString("trusted3", ""));

        l.addView(name);
        l.addView(own);
        l.addView(n1);
        l.addView(t1);
        l.addView(n2);
        l.addView(t2);
        l.addView(n3);
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
                            name.getText().toString().trim().isEmpty()
                                    ? "بیمار"
                                    : name.getText().toString().trim())
                    .putString("patient_phone", own.getText().toString().trim())
                    .putString("trusted_name1", n1.getText().toString().trim())
                    .putString("trusted1", t1.getText().toString().trim())
                    .putString("trusted_name2", n2.getText().toString().trim())
                    .putString("trusted2", t2.getText().toString().trim())
                    .putString("trusted_name3", n3.getText().toString().trim())
                    .putString("trusted3", t3.getText().toString().trim())
                    .apply();

            if (checkSelfPermission(Manifest.permission.CAMERA)
                    != PackageManager.PERMISSION_GRANTED) {
                requestCorePermissions();
            } else {
                launchCareMode(false);
            }
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void showCaregiverSetup() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

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
            if (own.getText().toString().trim().isEmpty()
                    || patient.getText().toString().trim().isEmpty()) {
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

    private void showPatientDashboard(boolean autoStart) {
        ScrollView sc = shell();
        LinearLayout l = column();

        String name = prefs.getString("patient_name", "بیمار");
        l.addView(title("Care AI — " + name));
        l.addView(note(
                "کنترل چشمی فعال است: هر سؤال زمان کافی روی صفحه می‌ماند؛ بستن ارادی چشم‌ها یعنی تأیید و نگاه به راست یعنی رد و رفتن به سؤال بعدی."
        ));

        Button monitor = button("شروع Care Mode چشمی");
        Button talk = button("صحبت با من — کنترل با چشم");
        Button sos = button("SOS — درخواست کمک فوری");
        Button settings = button("ویرایش شماره‌های اضطراری");
        Button reset = button("خروج از حالت بیمار");

        l.addView(monitor);
        l.addView(talk);
        l.addView(sos);
        l.addView(settings);
        l.addView(reset);

        monitor.setOnClickListener(v -> launchCareMode(false));
        talk.setOnClickListener(v -> launchCareMode(true));
        sos.setOnClickListener(v ->
                EmergencyManager.sendEmergency(this, "درخواست مستقیم بیمار", true));
        settings.setOnClickListener(v -> showPatientSetup());
        reset.setOnClickListener(v -> {
            prefs.edit().remove("role").apply();
            showRolePicker();
        });

        sc.addView(l);
        setContentView(sc);

        if (autoStart && !autoLaunchScheduled) {
            autoLaunchScheduled = true;
            handler.postDelayed(() -> {
                if (isFinishing()) return;
                if (allCorePermissionsGranted()) {
                    launchCareMode(false);
                } else {
                    requestCorePermissions();
                }
            }, 2500L);
        }
    }

    private void showCaregiverDashboard() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();

        String patient = prefs.getString("linked_patient", "");
        l.addView(title("پنل پرستار / همراه"));
        l.addView(note("بیمار متصل: " + patient));

        Button call = button("تماس تلفنی با بیمار");
        Button sms = button("ارسال پیام به بیمار");
        Button video = button("تماس تصویری امن — نیازمند سرور");
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
                Toast.makeText(
                        this,
                        "WebRTC و Push پس از اتصال سرور فعال می‌شود.",
                        Toast.LENGTH_LONG
                ).show());

        reset.setOnClickListener(v -> {
            prefs.edit().remove("role").apply();
            showRolePicker();
        });

        sc.addView(l);
        setContentView(sc);
    }

    private void launchCareMode(boolean talkMode) {
        if (checkSelfPermission(Manifest.permission.CAMERA)
                != PackageManager.PERMISSION_GRANTED) {
            requestCorePermissions();
            return;
        }

        Intent i = new Intent(this, CameraMonitorActivity.class);
        i.putExtra("talk_mode", talkMode);
        startActivity(i);
    }

    private boolean allCorePermissionsGranted() {
        if (checkSelfPermission(Manifest.permission.CAMERA)
                != PackageManager.PERMISSION_GRANTED) return false;

        if (checkSelfPermission(Manifest.permission.CALL_PHONE)
                != PackageManager.PERMISSION_GRANTED) return false;

        if (Build.VERSION.SDK_INT >= 33) {
            if (checkSelfPermission(Manifest.permission.READ_MEDIA_AUDIO)
                    != PackageManager.PERMISSION_GRANTED) return false;
            if (checkSelfPermission(Manifest.permission.READ_MEDIA_VIDEO)
                    != PackageManager.PERMISSION_GRANTED) return false;
        } else {
            if (checkSelfPermission(Manifest.permission.READ_EXTERNAL_STORAGE)
                    != PackageManager.PERMISSION_GRANTED) return false;
        }

        return true;
    }

    private void requestCorePermissions() {
        java.util.ArrayList<String> permissions = new java.util.ArrayList<>();
        permissions.add(Manifest.permission.CAMERA);
        permissions.add(Manifest.permission.SEND_SMS);
        permissions.add(Manifest.permission.CALL_PHONE);

        if (Build.VERSION.SDK_INT >= 33) {
            permissions.add(Manifest.permission.READ_MEDIA_AUDIO);
            permissions.add(Manifest.permission.READ_MEDIA_VIDEO);
        } else {
            permissions.add(Manifest.permission.READ_EXTERNAL_STORAGE);
        }

        requestPermissions(
                permissions.toArray(new String[0]),
                100
        );
    }

    @Override
    public void onRequestPermissionsResult(
            int requestCode,
            String[] permissions,
            int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode == 100
                && checkSelfPermission(Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED
                && "patient".equals(prefs.getString("role", ""))) {
            launchCareMode(false);
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }

    private int dp(int v) {
        return (int) (
                v * getResources().getDisplayMetrics().density + 0.5f
        );
    }
}
