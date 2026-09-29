package ir.careai.guardian;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONObject;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class RegistrationActivity extends Activity {

    private final Handler handler = new Handler(Looper.getMainLooper());

    private EditText phone;
    private EditText otp;
    private TextView status;
    private Button send;
    private Button verify;
    private Button refresh;
    private LinearLayout expiredCard;
    private TextView expiredId;
    private Button whatsappButton;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        render();

        if (AuthManager.hasToken(this)) {
            String savedPhone = getSharedPreferences("careai",MODE_PRIVATE)
                    .getString("auth_phone","");
            if (!savedPhone.isEmpty()) phone.setText(savedPhone);

            status.setText("در حال بررسی اعتبار حساب...");
            checkActivation(true);
        }
    }

    private GradientDrawable bg(int color,int radius) {
        GradientDrawable g=new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(radius));
        return g;
    }

    private TextView text(String value,float size,int color,boolean bold) {
        TextView t=new TextView(this);
        t.setText(value);
        t.setTextSize(size);
        t.setTextColor(color);
        if(bold)t.setTypeface(t.getTypeface(), Typeface.BOLD);
        return t;
    }

    private EditText input(String hint) {
        EditText e=new EditText(this);
        e.setHint(hint);
        e.setSingleLine(true);
        e.setTextSize(17);
        e.setPadding(dp(14),dp(10),dp(14),dp(10));
        e.setBackground(bg(Color.WHITE,16));

        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,dp(62));
        p.setMargins(0,dp(6),0,dp(6));
        e.setLayoutParams(p);
        return e;
    }

    private Button button(String title,int color) {
        Button b=new Button(this);
        b.setText(title);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(16);
        b.setBackground(bg(color,18));

        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,dp(60));
        p.setMargins(0,dp(6),0,dp(6));
        b.setLayoutParams(p);
        return b;
    }

    private void render() {
        ScrollView sc=new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(Color.rgb(244,249,253));

        LinearLayout root=new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(18),dp(28),dp(18),dp(30));

        TextView title=text(
                "فعال‌سازی Care AI",
                28,
                Color.rgb(22,62,101),
                true
        );
        title.setGravity(Gravity.CENTER);
        root.addView(title);

        TextView desc=text(
                "ثبت‌نام اولیه فقط با شماره موبایل و کد پیامکی انجام می‌شود. "
                        +"شناسه گوشی به‌صورت خودکار خوانده می‌شود و ماه اول رایگان است.",
                14,
                Color.rgb(91,111,134),
                false
        );
        desc.setGravity(Gravity.CENTER);
        desc.setPadding(dp(8),dp(10),dp(8),dp(16));
        root.addView(desc);

        LinearLayout deviceCard=new LinearLayout(this);
        deviceCard.setOrientation(LinearLayout.VERTICAL);
        deviceCard.setPadding(dp(14),dp(12),dp(14),dp(12));
        deviceCard.setBackground(bg(Color.rgb(232,243,254),16));

        TextView deviceTitle=text(
                "شناسه این گوشی",
                13,
                Color.rgb(65,93,119),
                true
        );
        TextView deviceValue=text(
                AuthManager.deviceId(this),
                16,
                Color.rgb(22,62,101),
                true
        );
        deviceValue.setTextDirection(View.TEXT_DIRECTION_LTR);
        deviceValue.setGravity(Gravity.LEFT);
        deviceValue.setPadding(0,dp(5),0,0);

        deviceCard.addView(deviceTitle);
        deviceCard.addView(deviceValue);

        LinearLayout.LayoutParams dcp=new LinearLayout.LayoutParams(-1,-2);
        dcp.setMargins(0,0,0,dp(14));
        root.addView(deviceCard,dcp);

        phone=input("شماره موبایل");
        phone.setInputType(InputType.TYPE_CLASS_PHONE);

        otp=input("کد تایید ۶ رقمی");
        otp.setInputType(InputType.TYPE_CLASS_NUMBER);
        otp.setVisibility(View.GONE);

        root.addView(phone);
        root.addView(otp);

        send=button(
                "ارسال کد تایید",
                Color.rgb(18,185,170)
        );

        verify=button(
                "تایید کد و شروع ماه رایگان",
                Color.rgb(29,120,220)
        );
        verify.setVisibility(View.GONE);

        root.addView(send);
        root.addView(verify);

        status=text(
                "",
                14,
                Color.rgb(52,101,128),
                false
        );
        status.setGravity(Gravity.CENTER);
        status.setPadding(dp(10),dp(14),dp(10),dp(14));
        root.addView(status);

        refresh=button(
                "بررسی وضعیت حساب",
                Color.rgb(116,83,207)
        );
        refresh.setVisibility(
                AuthManager.hasToken(this)
                        ? View.VISIBLE
                        : View.GONE
        );
        root.addView(refresh);

        expiredCard=new LinearLayout(this);
        expiredCard.setOrientation(LinearLayout.VERTICAL);
        expiredCard.setPadding(dp(16),dp(16),dp(16),dp(16));
        expiredCard.setBackground(bg(Color.rgb(255,242,242),20));
        expiredCard.setVisibility(View.GONE);

        TextView expiredTitle=text(
                "اعتبار حساب شما به پایان رسیده",
                20,
                Color.rgb(181,51,61),
                true
        );
        expiredTitle.setGravity(Gravity.CENTER);

        TextView expiredMessage=text(
                "جهت شارژ اعتبار با پشتیبانی تماس بگیرید.",
                15,
                Color.rgb(112,72,76),
                false
        );
        expiredMessage.setGravity(Gravity.CENTER);
        expiredMessage.setPadding(0,dp(8),0,dp(8));

        expiredId=text(
                "",
                14,
                Color.rgb(62,82,102),
                true
        );
        expiredId.setGravity(Gravity.CENTER);
        expiredId.setTextDirection(View.TEXT_DIRECTION_LTR);

        whatsappButton=button(
                "تمدید حساب در واتساپ",
                Color.rgb(29,168,91)
        );
        whatsappButton.setOnClickListener(v->openWhatsApp());

        expiredCard.addView(expiredTitle);
        expiredCard.addView(expiredMessage);
        expiredCard.addView(expiredId);
        expiredCard.addView(whatsappButton);

        LinearLayout.LayoutParams ep=new LinearLayout.LayoutParams(-1,-2);
        ep.setMargins(0,dp(14),0,0);
        root.addView(expiredCard,ep);

        send.setOnClickListener(v->requestOtp());
        verify.setOnClickListener(v->verifyOtp());
        refresh.setOnClickListener(v->checkActivation(false));

        sc.addView(root);
        setContentView(sc);
    }

    private void requestOtp() {
        String p=phone.getText().toString().trim();

        if(p.isEmpty()) {
            Toast.makeText(
                    this,
                    "شماره موبایل را وارد کنید",
                    Toast.LENGTH_LONG
            ).show();
            return;
        }

        String deviceId=AuthManager.deviceId(this);
        if("unknown-device".equals(deviceId)) {
            status.setText("شناسه گوشی قابل خواندن نیست.");
            return;
        }

        hideExpired();
        send.setEnabled(false);
        status.setText("در حال ارسال کد تایید...");

        AuthManager.requestOtp(
                this,
                p,
                (result,error)->runOnUiThread(()->{
                    send.setEnabled(true);

                    if(error!=null||result==null) {
                        status.setText("ارتباط با سرور انجام نشد.");
                        return;
                    }

                    if(!result.optBoolean("ok")) {
                        status.setText(
                                result.optString(
                                        "message",
                                        "ثبت این حساب ممکن نیست."
                                )
                        );
                        return;
                    }

                    otp.setVisibility(View.VISIBLE);
                    verify.setVisibility(View.VISIBLE);
                    status.setText(
                            "کد تایید ارسال شد. پس از تایید، ماه اول به‌صورت خودکار فعال می‌شود."
                    );
                })
        );
    }

    private void verifyOtp() {
        String p=phone.getText().toString().trim();
        String code=otp.getText().toString().trim();

        if(p.isEmpty()||code.isEmpty()) {
            Toast.makeText(
                    this,
                    "شماره موبایل و کد تایید را وارد کنید",
                    Toast.LENGTH_LONG
            ).show();
            return;
        }

        verify.setEnabled(false);
        status.setText("در حال تایید و فعال‌سازی ماه رایگان...");

        AuthManager.verifyOtp(
                this,
                p,
                code,
                (result,error)->runOnUiThread(()->{
                    verify.setEnabled(true);

                    if(error!=null||result==null) {
                        status.setText("ارتباط با سرور انجام نشد.");
                        return;
                    }

                    if(!result.optBoolean("ok")) {
                        status.setText(
                                result.optString(
                                        "message",
                                        "کد تایید صحیح نیست."
                                )
                        );
                        return;
                    }

                    refresh.setVisibility(View.VISIBLE);

                    if(result.optBoolean("active")) {
                        hideExpired();
                        showActivationSuccess(result);
                        handler.postDelayed(this::openMain,800L);
                        return;
                    }

                    String activationCode=result.optString(
                            "activation_code",
                            ""
                    );

                    if("ACTIVATION_EXPIRED".equals(activationCode)) {
                        showExpired(result);
                        return;
                    }

                    hideExpired();
                    status.setText(
                            result.optString(
                                    "message",
                                    "این حساب در حال حاضر فعال نیست."
                            )
                    );
                })
        );
    }

    private void checkActivation(boolean auto) {
        if(!AuthManager.hasToken(this)) {
            if(!auto) {
                status.setText(
                        "ابتدا شماره موبایل را با کد پیامکی تایید کنید."
                );
            }
            return;
        }

        AuthManager.checkStatus(
                this,
                (result,error)->runOnUiThread(()->{
                    if(error!=null||result==null) {
                        if(!auto) {
                            status.setText("سرور در دسترس نیست.");
                        }
                        return;
                    }

                    if(!result.optBoolean("ok")) {
                        status.setText(
                                result.optString(
                                        "message",
                                        "حساب معتبر نیست."
                                )
                        );
                        return;
                    }

                    if(result.optBoolean("active")) {
                        hideExpired();
                        showActivationSuccess(result);
                        handler.postDelayed(this::openMain,650L);
                        return;
                    }

                    String code=result.optString(
                            "activation_code",
                            ""
                    );

                    if("ACTIVATION_EXPIRED".equals(code)) {
                        showExpired(result);
                        return;
                    }

                    hideExpired();
                    status.setText(
                            result.optString(
                                    "message",
                                    "این حساب در حال حاضر فعال نیست."
                            )
                    );
                })
        );
    }

    private void showActivationSuccess(JSONObject result) {
        String mode=result.optString(
                "activation_mode",
                "active"
        );
        long expiresAt=result.optLong(
                "expires_at",
                0L
        );

        String title="trial".equals(mode)
                ? "ماه اول رایگان فعال شد"
                : "حساب فعال است";

        String expiry=formatExpiry(expiresAt);

        status.setText(
                title
                        +(expiry.isEmpty()
                        ? ""
                        : " • اعتبار تا "+expiry)
        );
    }

    private void showExpired(JSONObject result) {
        status.setText(
                "اعتبار حساب شما به پایان رسیده، جهت شارژ اعتبار با پشتیبانی تماس بگیرید."
        );

        String id=result.optString(
                "patient_id",
                AuthManager.patientId(this)
        );

        if(id==null||id.trim().isEmpty()) {
            id=AuthManager.patientId(this);
        }

        expiredId.setText("ID: "+id);
        expiredCard.setVisibility(View.VISIBLE);

        String support=result.optString(
                "support_whatsapp",
                AuthManager.supportWhatsapp(this)
        );

        if(support==null||support.trim().isEmpty()) {
            whatsappButton.setEnabled(false);
            whatsappButton.setText(
                    "شماره واتساپ پشتیبانی تنظیم نشده"
            );
        } else {
            whatsappButton.setEnabled(true);
            whatsappButton.setText(
                    "تمدید حساب در واتساپ"
            );
        }
    }

    private void hideExpired() {
        if(expiredCard!=null) {
            expiredCard.setVisibility(View.GONE);
        }
    }

    private void openWhatsApp() {
        String support=normalizeWhatsApp(
                AuthManager.supportWhatsapp(this)
        );

        if(support.isEmpty()) {
            Toast.makeText(
                    this,
                    "شماره واتساپ پشتیبانی در پنل مدیریت تنظیم نشده است.",
                    Toast.LENGTH_LONG
            ).show();
            return;
        }

        String id=AuthManager.patientId(this);
        String message=
                "سلام ، جهت تمدید حساب پرستار آنلاین پیام میدم ، شماره ایدی "
                        +id;

        Uri uri=Uri.parse(
                "https://wa.me/"
                        +support
                        +"?text="
                        +Uri.encode(message)
        );

        Intent normal=new Intent(Intent.ACTION_VIEW,uri);
        normal.setPackage("com.whatsapp");

        try {
            startActivity(normal);
            return;
        } catch(ActivityNotFoundException ignored) {}

        Intent business=new Intent(Intent.ACTION_VIEW,uri);
        business.setPackage("com.whatsapp.w4b");

        try {
            startActivity(business);
            return;
        } catch(ActivityNotFoundException ignored) {}

        try {
            startActivity(new Intent(Intent.ACTION_VIEW,uri));
        } catch(Exception e) {
            Toast.makeText(
                    this,
                    "واتساپ روی این گوشی در دسترس نیست.",
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private String normalizeWhatsApp(String value) {
        if(value==null)return "";

        String digits=value.replaceAll("[^0-9]","");
        if(digits.startsWith("0098")) {
            digits=digits.substring(4);
            return "98"+digits;
        }

        if(digits.startsWith("98")) {
            return digits;
        }

        if(digits.startsWith("0")&&digits.length()>=10) {
            return "98"+digits.substring(1);
        }

        return digits;
    }

    private String formatExpiry(long seconds) {
        if(seconds<=0L)return "";

        try {
            return new SimpleDateFormat(
                    "yyyy/MM/dd HH:mm",
                    Locale.US
            ).format(new Date(seconds*1000L));
        } catch(Exception e) {
            return "";
        }
    }

    private void openMain() {
        handler.removeCallbacksAndMessages(null);

        Intent i=new Intent(
                this,
                MainActivity.class
        );

        i.addFlags(
                Intent.FLAG_ACTIVITY_CLEAR_TOP
                        |Intent.FLAG_ACTIVITY_NEW_TASK
        );

        startActivity(i);
        finish();
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }

    private int dp(int value) {
        return (int)(
                value
                        *getResources()
                        .getDisplayMetrics()
                        .density
                        +0.5f
        );
    }
}
