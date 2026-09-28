package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.List;

public class MainActivity extends Activity {
    private static final int C_BG = Color.rgb(244, 249, 253);
    private static final int C_TEXT = Color.rgb(19, 49, 83);
    private static final int C_MUTED = Color.rgb(91, 111, 134);
    private static final int C_BLUE = Color.rgb(29, 120, 220);
    private static final int C_TEAL = Color.rgb(18, 185, 170);
    private static final int C_RED = Color.rgb(237, 76, 91);
    private static final int C_PURPLE = Color.rgb(116, 83, 207);

    private SharedPreferences prefs;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private boolean autoLaunchScheduled = false;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        getWindow().setStatusBarColor(C_BG);
        getWindow().setNavigationBarColor(Color.WHITE);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR);
        prefs = getSharedPreferences("careai", MODE_PRIVATE);
        verifyAccessThenRoute();
    }

    @Override
    protected void onResume() {
        super.onResume();
        // اگر از مدیریت دستورات برگشتیم، داشبورد تازه شود.
        if ("patient".equals(prefs.getString("role", ""))
                && !autoLaunchScheduled) {
            // فقط وقتی Activity در حال نمایش داشبورد است، بازسازی بی‌خطر است.
        }
    }

    private void verifyAccessThenRoute() {
        if (!AuthManager.hasToken(this)) {
            openRegistration();
            return;
        }

        TextView loading = text(
                "در حال بررسی فعال‌سازی Care AI...",
                18,
                C_TEXT,
                true
        );
        loading.setGravity(Gravity.CENTER);
        loading.setBackgroundColor(C_BG);
        setContentView(loading);

        AuthManager.checkStatus(this, (result, error) ->
                runOnUiThread(() -> {
                    if (error != null || result == null) {
                        if (AuthManager.cachedActive(this)) {
                            route();
                        } else {
                            openRegistration();
                        }
                        return;
                    }

                    if (result.optBoolean("ok")
                            && result.optBoolean("active")) {
                        route();
                    } else {
                        openRegistration();
                    }
                })
        );
    }

    private void openRegistration() {
        startActivity(new Intent(this, RegistrationActivity.class));
        finish();
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
        sc.setBackgroundColor(C_BG);
        return sc;
    }

    private LinearLayout column() {
        LinearLayout l = new LinearLayout(this);
        l.setOrientation(LinearLayout.VERTICAL);
        l.setPadding(dp(16), dp(18), dp(16), dp(30));
        return l;
    }

    private GradientDrawable bg(int color, int radius) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(radius));
        return g;
    }

    private GradientDrawable strokeBg(int color, int radius, int strokeColor) {
        GradientDrawable g = bg(color, radius);
        g.setStroke(dp(1), strokeColor);
        return g;
    }

    private TextView text(String value, float size, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(value);
        t.setTextSize(size);
        t.setTextColor(color);
        if (bold) t.setTypeface(t.getTypeface(), android.graphics.Typeface.BOLD);
        return t;
    }

    private TextView sectionTitle(String value) {
        TextView t = text(value, 20, C_TEXT, true);
        t.setPadding(dp(2), dp(18), dp(2), dp(10));
        return t;
    }

    private Button button(String s) {
        Button b = new Button(this);
        b.setText(s);
        b.setTextSize(16);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setBackground(bg(C_BLUE, 18));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(58));
        p.setMargins(0, dp(7), 0, dp(7));
        b.setLayoutParams(p);
        return b;
    }

    private EditText input(String hint, boolean phone) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setHintTextColor(Color.rgb(135, 151, 170));
        e.setTextColor(C_TEXT);
        e.setTextSize(16);
        e.setSingleLine(true);
        e.setPadding(dp(14), dp(8), dp(14), dp(8));
        e.setBackground(strokeBg(Color.WHITE, 15, Color.rgb(218, 229, 239)));
        if (phone) e.setInputType(InputType.TYPE_CLASS_PHONE);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(58));
        p.setMargins(0, dp(5), 0, dp(5));
        e.setLayoutParams(p);
        return e;
    }

    private LinearLayout heroHeader(String name, String subtitle) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.HORIZONTAL);
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(16), dp(15), dp(16), dp(15));
        card.setBackground(bg(Color.WHITE, 24));

        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.ic_launcher_foreground);
        LinearLayout.LayoutParams ip = new LinearLayout.LayoutParams(dp(68), dp(68));
        ip.setMargins(dp(8), 0, 0, 0);
        card.addView(logo, ip);

        LinearLayout texts = new LinearLayout(this);
        texts.setOrientation(LinearLayout.VERTICAL);
        texts.setPadding(dp(10), 0, dp(10), 0);

        TextView brand = text("Care AI", 27, C_BLUE, true);
        TextView hello = text(name, 18, C_TEXT, true);
        TextView sub = text(subtitle, 13, C_MUTED, false);
        sub.setPadding(0, dp(3), 0, 0);

        texts.addView(brand);
        texts.addView(hello);
        texts.addView(sub);
        card.addView(texts, new LinearLayout.LayoutParams(0, -2, 1f));
        return card;
    }

    private LinearLayout featureCard(
            int iconRes,
            String title,
            String subtitle,
            int accent,
            int background,
            View.OnClickListener click) {

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(14), dp(13), dp(14), dp(13));
        card.setBackground(bg(background, 22));
        card.setOnClickListener(click);
        card.setClickable(true);

        ImageView icon = new ImageView(this);
        icon.setImageResource(iconRes);
        icon.setColorFilter(accent);
        LinearLayout.LayoutParams ip = new LinearLayout.LayoutParams(dp(34), dp(34));
        ip.setMargins(0, 0, 0, dp(7));
        card.addView(icon, ip);

        TextView t = text(title, 17, C_TEXT, true);
        TextView s = text(subtitle, 12.5f, C_MUTED, false);
        s.setPadding(0, dp(3), 0, 0);
        card.addView(t);
        card.addView(s);

        return card;
    }

    private void addFeatureRow(
            LinearLayout parent,
            LinearLayout first,
            LinearLayout second) {

        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);

        LinearLayout.LayoutParams p1 =
                new LinearLayout.LayoutParams(0, dp(142), 1f);
        p1.setMargins(0, 0, dp(6), 0);

        LinearLayout.LayoutParams p2 =
                new LinearLayout.LayoutParams(0, dp(142), 1f);
        p2.setMargins(dp(6), 0, 0, 0);

        row.addView(first, p1);
        row.addView(second, p2);

        LinearLayout.LayoutParams rp =
                new LinearLayout.LayoutParams(-1, -2);
        rp.setMargins(0, dp(10), 0, 0);
        parent.addView(row, rp);
    }

    private LinearLayout wideCard(
            int iconRes,
            String title,
            String subtitle,
            int accent,
            View.OnClickListener click) {

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.HORIZONTAL);
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(14), dp(14), dp(14), dp(14));
        card.setBackground(strokeBg(Color.WHITE, 20, Color.rgb(222, 233, 242)));
        card.setOnClickListener(click);

        ImageView icon = new ImageView(this);
        icon.setImageResource(iconRes);
        icon.setColorFilter(accent);
        LinearLayout.LayoutParams ip = new LinearLayout.LayoutParams(dp(34), dp(34));
        ip.setMargins(dp(10), 0, 0, 0);
        card.addView(icon, ip);

        LinearLayout texts = new LinearLayout(this);
        texts.setOrientation(LinearLayout.VERTICAL);
        texts.addView(text(title, 16.5f, C_TEXT, true));
        TextView s = text(subtitle, 12.5f, C_MUTED, false);
        s.setPadding(0, dp(2), 0, 0);
        texts.addView(s);
        card.addView(texts, new LinearLayout.LayoutParams(0, -2, 1f));

        TextView arrow = text("‹", 28, accent, true);
        card.addView(arrow);
        return card;
    }

    private LinearLayout commandPreview(CommandStore.Command cmd) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.HORIZONTAL);
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(12), dp(10), dp(12), dp(10));
        card.setBackground(strokeBg(Color.WHITE, 16, Color.rgb(228, 236, 244)));

        int accent = C_BLUE;
        String action = cmd.action;
        if (CommandStore.ACTION_VIDEO.equals(action)
                || CommandStore.ACTION_AUDIO.equals(action)) accent = C_TEAL;
        else if (CommandStore.ACTION_EMERGENCY.equals(action)) accent = C_RED;
        else if (CommandStore.ACTION_CALL_1.equals(action)
                || CommandStore.ACTION_CALL_2.equals(action)
                || CommandStore.ACTION_CALL_3.equals(action)) accent = C_PURPLE;

        TextView tag = text(CommandStore.categoryLabel(action), 12, accent, true);
        tag.setGravity(Gravity.CENTER);
        tag.setPadding(dp(9), dp(5), dp(9), dp(5));
        tag.setBackground(bg(Color.argb(25, Color.red(accent), Color.green(accent), Color.blue(accent)), 12));
        card.addView(tag);

        LinearLayout texts = new LinearLayout(this);
        texts.setOrientation(LinearLayout.VERTICAL);
        texts.setPadding(dp(10), 0, dp(10), 0);
        texts.addView(text(cmd.question, 15.5f, C_TEXT, true));
        TextView sub = text(cmd.output, 12.5f, C_MUTED, false);
        sub.setMaxLines(1);
        texts.addView(sub);
        card.addView(texts, new LinearLayout.LayoutParams(0, -2, 1f));
        return card;
    }

    private void showRolePicker() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();

        l.addView(heroHeader("همراه مطمئن شما", "کنترل چشمی، ارتباط و مراقبت هوشمند"));

        TextView choose = sectionTitle("نحوه ورود");
        l.addView(choose);

        l.addView(wideCard(
                android.R.drawable.ic_menu_view,
                "بیمار",
                "کنترل Care AI با چشم و پلک",
                C_TEAL,
                v -> showPatientSetup()
        ));

        LinearLayout.LayoutParams gap = new LinearLayout.LayoutParams(-1, -2);
        gap.setMargins(0, dp(10), 0, 0);
        LinearLayout cg = wideCard(
                android.R.drawable.ic_menu_myplaces,
                "پرستار / همراه",
                "پیگیری و ارتباط با بیمار",
                C_BLUE,
                v -> showCaregiverSetup()
        );
        l.addView(cg, gap);

        TextView foot = text(
                "Care AI ابزار کمکی مراقبتی است و جایگزین تشخیص یا مراقبت حرفه‌ای پزشکی نیست.",
                12.5f,
                C_MUTED,
                false
        );
        foot.setGravity(Gravity.CENTER);
        foot.setPadding(dp(16), dp(24), dp(16), dp(8));
        l.addView(foot);

        sc.addView(l);
        setContentView(sc);
    }

    private void showPatientSetup() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();
        l.addView(heroHeader("تنظیم بیمار", "اطلاعات بیمار و مخاطبان مورد اعتماد"));

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

        l.addView(sectionTitle("اطلاعات بیمار"));
        l.addView(name); l.addView(own);
        l.addView(sectionTitle("شماره‌های اضطراری"));
        l.addView(n1); l.addView(t1);
        l.addView(n2); l.addView(t2);
        l.addView(n3); l.addView(t3);

        Button save = button("ذخیره و ورود به Care Mode");
        l.addView(save);

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

            if (!allCorePermissionsGranted()) {
                requestCorePermissions();
            } else {
                showPatientDashboard(false);
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
        l.addView(heroHeader("ورود همراه", "اتصال به پروفایل بیمار"));

        EditText own = input("شماره موبایل همراه", true);
        EditText patient = input("شماره بیمار", true);
        own.setText(prefs.getString("caregiver_phone", ""));
        patient.setText(prefs.getString("linked_patient", ""));

        l.addView(sectionTitle("اطلاعات اتصال"));
        l.addView(own); l.addView(patient);

        Button save = button("ورود");
        l.addView(save);

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

    private void openCarePlan(String kind) {
        Intent i = new Intent(this, CarePlanActivity.class);
        i.putExtra("kind", kind);
        startActivity(i);
    }

    private void chooseVideoNurse() {
        SharedPreferences p = getSharedPreferences("careai",MODE_PRIVATE);
        String[] labels = new String[3];
        for (int i=1;i<=3;i++) {
            String name=p.getString("trusted_name"+i,"").trim();
            String num=p.getString("trusted"+i,"").trim();
            labels[i-1]=(name.isEmpty()?"پرستار/همراه "+i:name)
                    +(num.isEmpty()?"":" — "+num);
        }

        new android.app.AlertDialog.Builder(this)
                .setTitle("ارسال لینک تماس تصویری به")
                .setItems(labels,(d,which)->
                        VideoCallManager.shareCaregiverLink(this,which+1))
                .setNegativeButton("انصراف",null)
                .show();
    }

    private void checkIncomingVideoOnDashboard() {
        if (!getSharedPreferences("careai",MODE_PRIVATE)
                .getBoolean("auto_video_answer",true)) return;

        VideoCallManager.checkIncoming(this,(result,error)->{
            if(error!=null||result==null)return;
            if(!result.optBoolean("ok")||!result.optBoolean("incoming"))return;
            String room=result.optString("room","");
            if(room.isEmpty())return;

            String handled=prefs.getString("last_video_room_handled","");
            if(room.equals(handled))return;

            prefs.edit().putString("last_video_room_handled",room).apply();

            runOnUiThread(()->{
                Intent i=new Intent(this,VideoCallActivity.class);
                i.putExtra("role","patient");
                i.putExtra("room",room);
                startActivity(i);
            });
        });
    }

    private void showPatientDashboard(boolean autoStart) {
        ScrollView sc = shell();
        LinearLayout l = column();

        String name = prefs.getString("patient_name", "بیمار");
        l.addView(heroHeader(
                "سلام " + name,
                "● مراقبت آماده است"
        ));

        LinearLayout wellness = new LinearLayout(this);
        wellness.setOrientation(LinearLayout.VERTICAL);
        wellness.setPadding(dp(16), dp(14), dp(16), dp(14));
        wellness.setBackground(bg(Color.rgb(235, 250, 245), 20));
        wellness.addView(text("روز خوبی داشته باشید", 17, Color.rgb(37, 115, 87), true));
        wellness.addView(text(
                "دو پلک = تأیید  •  نگاه راست = رد  •  نگاه چپ هنگام رسانه = بعدی",
                12.5f,
                Color.rgb(71, 126, 105),
                false
        ));
        LinearLayout.LayoutParams wp = new LinearLayout.LayoutParams(-1, -2);
        wp.setMargins(0, dp(12), 0, 0);
        l.addView(wellness, wp);

        LinearLayout care = featureCard(
                android.R.drawable.ic_menu_view,
                "شروع مراقبت",
                "پایش و کنترل چشمی",
                Color.rgb(31, 166, 126),
                Color.rgb(231, 248, 240),
                v -> launchCareMode(false)
        );

        LinearLayout talk = featureCard(
                android.R.drawable.ic_dialog_email,
                "صحبت با من",
                "ارتباط با کنترل چشم",
                C_BLUE,
                Color.rgb(232, 243, 254),
                v -> launchCareMode(true)
        );

        LinearLayout emergency = featureCard(
                android.R.drawable.ic_menu_call,
                "تماس اضطراری",
                "در مواقع ضروری",
                C_RED,
                Color.rgb(255, 237, 239),
                v -> EmergencyManager.sendEmergency(
                        this,
                        "درخواست مستقیم بیمار",
                        true
                )
        );

        LinearLayout commands = featureCard(
                android.R.drawable.ic_menu_edit,
                "مدیریت دستورات",
                "افزودن و ویرایش فرمان‌ها",
                C_PURPLE,
                Color.rgb(241, 237, 253),
                v -> startActivity(
                        new Intent(this, CommandEditorActivity.class)
                )
        );

        addFeatureRow(l, care, talk);
        addFeatureRow(l, emergency, commands);

        LinearLayout videoCall = featureCard(
                android.R.drawable.ic_menu_camera,
                "تماس تصویری",
                "ارسال لینک امن به پرستار",
                Color.rgb(19,145,165),
                Color.rgb(229,247,249),
                v -> chooseVideoNurse()
        );

        LinearLayout medicine = featureCard(
                android.R.drawable.ic_menu_agenda,
                "داروها",
                "روز، ساعت، مقدار و پیامک پرستار",
                Color.rgb(61,139,99),
                Color.rgb(235,249,241),
                v -> openCarePlan(CareScheduleStore.KIND_MEDICINE)
        );

        LinearLayout carePlan = featureCard(
                android.R.drawable.ic_menu_recent_history,
                "مراقبت و نوبت",
                "دکتر، فیزیوتراپی و مراقبت‌ها",
                Color.rgb(189,120,37),
                Color.rgb(255,247,233),
                v -> openCarePlan(CareScheduleStore.KIND_CARE)
        );

        LinearLayout sensor = featureCard(
                android.R.drawable.stat_sys_data_bluetooth,
                "سنسور سلامت",
                "ضربان و اکسیژن BLE",
                Color.rgb(190,70,87),
                Color.rgb(255,237,241),
                v -> startActivity(new Intent(this,HealthSensorActivity.class))
        );

        addFeatureRow(l, videoCall, medicine);
        addFeatureRow(l, carePlan, sensor);

        boolean autoAnswer = prefs.getBoolean("auto_video_answer",true);
        LinearLayout autoVideo = wideCard(
                android.R.drawable.ic_menu_call,
                autoAnswer ? "پاسخ خودکار تماس تصویری: روشن" : "پاسخ خودکار تماس تصویری: خاموش",
                autoAnswer
                        ? "درخواست معتبر پرستار بدون لمس بیمار پاسخ داده می‌شود"
                        : "برای روشن کردن لمس کنید",
                autoAnswer ? C_TEAL : C_RED,
                v -> {
                    boolean nv=!prefs.getBoolean("auto_video_answer",true);
                    prefs.edit().putBoolean("auto_video_answer",nv).apply();
                    showPatientDashboard(false);
                }
        );
        LinearLayout.LayoutParams avp=new LinearLayout.LayoutParams(-1,-2);
        avp.setMargins(0,dp(12),0,0);
        l.addView(autoVideo,avp);

        int lastHr=prefs.getInt("last_hr",-1);
        float lastSpO2=prefs.getFloat("last_spo2",-1f);
        String vitalSub=(lastHr>0?"ضربان "+lastHr+" bpm":"ضربان --")
                +" • "
                +(lastSpO2>0?String.format(java.util.Locale.US,"اکسیژن %.1f%%",lastSpO2):"اکسیژن --");
        LinearLayout vitals = wideCard(
                android.R.drawable.ic_menu_info_details,
                "آخرین وضعیت سنسور",
                vitalSub,
                C_TEAL,
                v -> startActivity(new Intent(this,HealthSensorActivity.class))
        );
        LinearLayout.LayoutParams vp=new LinearLayout.LayoutParams(-1,-2);
        vp.setMargins(0,dp(9),0,0);
        l.addView(vitals,vp);

        LinearLayout contacts = wideCard(
                android.R.drawable.ic_menu_myplaces,
                "شماره‌های اضطراری",
                "مدیریت مخاطبان مهم",
                C_BLUE,
                v -> showPatientSetup()
        );
        LinearLayout.LayoutParams ctp = new LinearLayout.LayoutParams(-1, -2);
        ctp.setMargins(0, dp(12), 0, 0);
        l.addView(contacts, ctp);

        l.addView(sectionTitle("دستورات جدید"));

        List<CommandStore.Command> list = CommandStore.load(this);
        int shown = 0;
        for (int i = list.size() - 1; i >= 0 && shown < 3; i--) {
            CommandStore.Command cmd = list.get(i);
            if (!cmd.enabled) continue;
            LinearLayout preview = commandPreview(cmd);
            LinearLayout.LayoutParams pp = new LinearLayout.LayoutParams(-1, -2);
            pp.setMargins(0, 0, 0, dp(8));
            l.addView(preview, pp);
            shown++;
        }

        Button addCommand = button("＋ افزودن دستور جدید");
        addCommand.setBackground(bg(C_TEAL, 18));
        addCommand.setOnClickListener(v ->
                startActivity(new Intent(this, CommandEditorActivity.class))
        );
        l.addView(addCommand);

        l.addView(sectionTitle("کنترل روی برنامه‌های دیگر"));

        boolean accessibilityOn = CareAccessibilityService.isEnabled(this);

        LinearLayout access = wideCard(
                android.R.drawable.ic_menu_view,
                accessibilityOn
                        ? "کنترل برنامه‌ها فعال است"
                        : "فعال‌سازی کنترل برنامه‌ها",
                accessibilityOn
                        ? "Care AI می‌تواند روی Instagram و برنامه‌های دیگر با چشم فعال بماند"
                        : "یک بار سرویس Care AI را در Accessibility روشن کنید",
                accessibilityOn ? C_TEAL : C_RED,
                v -> CareAccessibilityService.openSettings(this)
        );
        l.addView(access);

        l.addView(sectionTitle("بروزرسانی‌ها"));

        LinearLayout update = wideCard(
                android.R.drawable.stat_sys_download_done,
                "بررسی بروزرسانی",
                "نسخه فعلی " + BuildConfig.VERSION_NAME + " • دانلود مستقیم نسخه جدید",
                C_TEAL,
                v -> UpdateManager.check(this, true)
        );
        l.addView(update);

        LinearLayout settings = wideCard(
                android.R.drawable.ic_menu_manage,
                "تنظیمات بیمار",
                "شماره‌ها، مجوزها و نقش",
                C_MUTED,
                v -> showPatientSetup()
        );
        LinearLayout.LayoutParams sp = new LinearLayout.LayoutParams(-1, -2);
        sp.setMargins(0, dp(9), 0, 0);
        l.addView(settings, sp);

        TextView changeRole = text("تغییر نقش", 14, C_MUTED, true);
        changeRole.setGravity(Gravity.CENTER);
        changeRole.setPadding(0, dp(22), 0, dp(5));
        changeRole.setOnClickListener(v -> {
            prefs.edit().remove("role").apply();
            showRolePicker();
        });
        l.addView(changeRole);

        sc.addView(l);
        setContentView(sc);

        // بررسی خودکار آپدیت بدون مزاحمت اگر نسخه جدیدی وجود نداشته باشد.
        handler.postDelayed(() -> UpdateManager.check(this, false), 1800L);
        handler.postDelayed(this::checkIncomingVideoOnDashboard, 2200L);

        if (autoStart && !autoLaunchScheduled) {
            autoLaunchScheduled = true;
            handler.postDelayed(() -> {
                if (isFinishing()) return;
                if (allCorePermissionsGranted()) {
                    launchCareMode(false);
                } else {
                    requestCorePermissions();
                }
            }, 5500L);
        }
    }

    private void showCaregiverDashboard() {
        autoLaunchScheduled = false;
        handler.removeCallbacksAndMessages(null);

        ScrollView sc = shell();
        LinearLayout l = column();

        String patient = prefs.getString("linked_patient", "");
        l.addView(heroHeader("پنل همراه", "بیمار متصل: " + patient));

        LinearLayout call = featureCard(
                android.R.drawable.ic_menu_call,
                "تماس تلفنی",
                "تماس مستقیم با بیمار",
                C_TEAL,
                Color.rgb(232, 249, 246),
                v -> startActivity(new Intent(
                        Intent.ACTION_DIAL,
                        Uri.parse("tel:" + Uri.encode(patient))
                ))
        );

        LinearLayout sms = featureCard(
                android.R.drawable.ic_dialog_email,
                "ارسال پیام",
                "ارسال پیام به بیمار",
                C_BLUE,
                Color.rgb(232, 243, 254),
                v -> startActivity(new Intent(
                        Intent.ACTION_SENDTO,
                        Uri.parse("smsto:" + Uri.encode(patient))
                ))
        );

        addFeatureRow(l, call, sms);

        l.addView(sectionTitle("تنظیمات"));
        l.addView(wideCard(
                android.R.drawable.stat_sys_download_done,
                "بروزرسانی Care AI",
                "نسخه فعلی " + BuildConfig.VERSION_NAME,
                C_TEAL,
                v -> UpdateManager.check(this, true)
        ));

        LinearLayout change = wideCard(
                android.R.drawable.ic_menu_manage,
                "تغییر نقش",
                "بازگشت به انتخاب بیمار / همراه",
                C_PURPLE,
                v -> {
                    prefs.edit().remove("role").apply();
                    showRolePicker();
                }
        );
        LinearLayout.LayoutParams cp = new LinearLayout.LayoutParams(-1, -2);
        cp.setMargins(0, dp(10), 0, 0);
        l.addView(change, cp);

        sc.addView(l);
        setContentView(sc);
        handler.postDelayed(() -> UpdateManager.check(this, false), 1800L);
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

        if (checkSelfPermission(Manifest.permission.READ_PHONE_STATE)
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
        ArrayList<String> permissions = new ArrayList<>();
        permissions.add(Manifest.permission.CAMERA);
        permissions.add(Manifest.permission.SEND_SMS);
        permissions.add(Manifest.permission.CALL_PHONE);
        permissions.add(Manifest.permission.READ_PHONE_STATE);

        if (Build.VERSION.SDK_INT >= 33) {
            permissions.add(Manifest.permission.READ_MEDIA_AUDIO);
            permissions.add(Manifest.permission.READ_MEDIA_VIDEO);
        } else {
            permissions.add(Manifest.permission.READ_EXTERNAL_STORAGE);
        }

        requestPermissions(permissions.toArray(new String[0]), 100);
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
            showPatientDashboard(false);
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }

    private int dp(int v) {
        return (int)(v * getResources().getDisplayMetrics().density + 0.5f);
    }
}
