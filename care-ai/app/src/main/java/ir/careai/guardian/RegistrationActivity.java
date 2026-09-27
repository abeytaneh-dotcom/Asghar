package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONObject;

public class RegistrationActivity extends Activity {

    private final Handler handler = new Handler(Looper.getMainLooper());
    private EditText phone;
    private EditText patientId;
    private EditText otp;
    private TextView status;
    private boolean polling = false;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        render();

        if (AuthManager.hasToken(this)) {
            showPending("در حال بررسی وضعیت فعال‌سازی...");
            checkActivation(true);
        }
    }

    private GradientDrawable bg(int color, int r) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(r));
        return g;
    }

    private TextView text(String s, float size, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(s);
        t.setTextSize(size);
        t.setTextColor(color);
        if (bold) t.setTypeface(t.getTypeface(), android.graphics.Typeface.BOLD);
        return t;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setTextSize(16);
        e.setSingleLine(true);
        e.setPadding(dp(14), dp(10), dp(14), dp(10));
        e.setBackground(bg(Color.WHITE, 16));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(58));
        p.setMargins(0, dp(6), 0, dp(6));
        e.setLayoutParams(p);
        return e;
    }

    private Button button(String s, int color) {
        Button b = new Button(this);
        b.setText(s);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(16);
        b.setBackground(bg(color, 18));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1, dp(58));
        p.setMargins(0, dp(6), 0, dp(6));
        b.setLayoutParams(p);
        return b;
    }

    private void render() {
        ScrollView sc = new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(Color.rgb(244,249,253));

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(18), dp(30), dp(18), dp(30));

        TextView title = text("فعال‌سازی Care AI", 28, Color.rgb(22,62,101), true);
        title.setGravity(Gravity.CENTER);
        root.addView(title);

        TextView desc = text(
                "هر حساب فقط برای یک بیمار و یک گوشی فعال می‌شود. پس از تایید پیامکی، مدیر باید حساب را فعال کند.",
                14,
                Color.rgb(91,111,134),
                false
        );
        desc.setGravity(Gravity.CENTER);
        desc.setPadding(dp(8), dp(10), dp(8), dp(18));
        root.addView(desc);

        phone = input("شماره موبایل");
        patientId = input("آیدی بیمار");
        otp = input("کد تایید ۶ رقمی");
        otp.setVisibility(View.GONE);

        root.addView(phone);
        root.addView(patientId);
        root.addView(otp);

        Button send = button("ارسال کد تایید", Color.rgb(18,185,170));
        Button verify = button("ثبت کد و ارسال برای فعال‌سازی", Color.rgb(29,120,220));
        verify.setVisibility(View.GONE);

        root.addView(send);
        root.addView(verify);

        status = text("", 14, Color.rgb(52,101,128), false);
        status.setGravity(Gravity.CENTER);
        status.setPadding(dp(10), dp(14), dp(10), dp(14));
        root.addView(status);

        Button refresh = button("بررسی وضعیت فعال‌سازی", Color.rgb(116,83,207));
        refresh.setVisibility(AuthManager.hasToken(this) ? View.VISIBLE : View.GONE);
        root.addView(refresh);

        send.setOnClickListener(v -> {
            String p = phone.getText().toString().trim();
            String id = patientId.getText().toString().trim();

            if (p.isEmpty() || id.isEmpty()) {
                Toast.makeText(this, "شماره و آیدی بیمار را وارد کنید", Toast.LENGTH_LONG).show();
                return;
            }

            if (checkSelfPermission(Manifest.permission.SEND_SMS)
                    != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(new String[]{Manifest.permission.SEND_SMS}, 301);
            }

            status.setText("در حال ارسال کد...");
            AuthManager.requestOtp(this, p, id, (result, error) ->
                    runOnUiThread(() -> {
                        if (error != null || result == null) {
                            status.setText("ارتباط با سرور انجام نشد.");
                            return;
                        }

                        if (!result.optBoolean("ok")) {
                            status.setText(result.optString("message",
                                    "ثبت این حساب ممکن نیست: " + result.optString("code")));
                            return;
                        }

                        otp.setVisibility(View.VISIBLE);
                        verify.setVisibility(View.VISIBLE);
                        status.setText("کد تایید ارسال شد.");
                    })
            );
        });

        verify.setOnClickListener(v -> {
            String p = phone.getText().toString().trim();
            String id = patientId.getText().toString().trim();
            String code = otp.getText().toString().trim();

            status.setText("در حال تایید...");
            AuthManager.verifyOtp(this, p, id, code, (result, error) ->
                    runOnUiThread(() -> {
                        if (error != null || result == null) {
                            status.setText("ارتباط با سرور انجام نشد.");
                            return;
                        }

                        if (!result.optBoolean("ok")) {
                            status.setText(result.optString("message", "کد تایید صحیح نیست."));
                            return;
                        }

                        if (result.optBoolean("active")) {
                            openMain();
                        } else {
                            refresh.setVisibility(View.VISIBLE);
                            showPending(result.optString(
                                    "message",
                                    "ثبت‌نام شد؛ منتظر فعال‌سازی مدیر باشید."
                            ));
                            startPolling();
                        }
                    })
            );
        });

        refresh.setOnClickListener(v -> checkActivation(false));

        sc.addView(root);
        setContentView(sc);
    }

    private void showPending(String message) {
        if (status != null) status.setText(message);
    }

    private void checkActivation(boolean auto) {
        AuthManager.checkStatus(this, (result, error) ->
                runOnUiThread(() -> {
                    if (error != null || result == null) {
                        if (!auto) status.setText("سرور در دسترس نیست.");
                        return;
                    }

                    if (!result.optBoolean("ok")) {
                        status.setText(result.optString("message", "حساب معتبر نیست."));
                        return;
                    }

                    if (result.optBoolean("active")) {
                        openMain();
                    } else {
                        status.setText("حساب هنوز توسط مدیر فعال نشده است.");
                        startPolling();
                    }
                })
        );
    }

    private void startPolling() {
        if (polling) return;
        polling = true;
        handler.postDelayed(new Runnable() {
            @Override
            public void run() {
                if (isFinishing()) return;
                AuthManager.checkStatus(RegistrationActivity.this, (result, error) ->
                        runOnUiThread(() -> {
                            if (result != null && result.optBoolean("active")) {
                                openMain();
                                return;
                            }
                            handler.postDelayed(this, 10000L);
                        })
                );
            }
        }, 10000L);
    }

    private void openMain() {
        polling = false;
        Intent i = new Intent(this, MainActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK);
        startActivity(i);
        finish();
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
