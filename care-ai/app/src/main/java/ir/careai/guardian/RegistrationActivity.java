package ir.careai.guardian;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
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

public class RegistrationActivity extends Activity {

    private final Handler handler = new Handler(Looper.getMainLooper());

    private EditText phone;
    private EditText otp;
    private TextView status;
    private Button send;
    private Button verify;
    private Button refresh;

    private boolean polling = false;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);

        getWindow()
                .getDecorView()
                .setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        render();

        if (AuthManager.hasToken(this)) {
            String savedPhone = getSharedPreferences("careai", MODE_PRIVATE)
                    .getString("auth_phone", "");
            if (!savedPhone.isEmpty()) phone.setText(savedPhone);

            showPending("در حال بررسی وضعیت فعال‌سازی این گوشی...");
            checkActivation(true);
        }
    }

    private GradientDrawable bg(int color, int r) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(r));
        return g;
    }

    private TextView text(
            String s,
            float size,
            int color,
            boolean bold) {

        TextView t = new TextView(this);
        t.setText(s);
        t.setTextSize(size);
        t.setTextColor(color);

        if (bold) {
            t.setTypeface(t.getTypeface(), Typeface.BOLD);
        }

        return t;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setTextSize(17);
        e.setSingleLine(true);
        e.setPadding(dp(14), dp(10), dp(14), dp(10));
        e.setBackground(bg(Color.WHITE, 16));

        LinearLayout.LayoutParams p =
                new LinearLayout.LayoutParams(-1, dp(62));
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

        LinearLayout.LayoutParams p =
                new LinearLayout.LayoutParams(-1, dp(60));
        p.setMargins(0, dp(6), 0, dp(6));
        b.setLayoutParams(p);

        return b;
    }

    private void render() {
        ScrollView sc = new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(Color.rgb(244, 249, 253));

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(18), dp(28), dp(18), dp(30));

        TextView title = text(
                "فعال‌سازی Care AI",
                28,
                Color.rgb(22, 62, 101),
                true
        );
        title.setGravity(Gravity.CENTER);
        root.addView(title);

        TextView desc = text(
                "شناسه گوشی به‌صورت خودکار خوانده می‌شود. "
                        + "هر شماره فقط روی یک گوشی فعال می‌شود و "
                        + "فعال‌سازی تست مدیر ۳۰ روز اعتبار دارد.",
                14,
                Color.rgb(91, 111, 134),
                false
        );
        desc.setGravity(Gravity.CENTER);
        desc.setPadding(
                dp(8), dp(10), dp(8), dp(16)
        );
        root.addView(desc);

        LinearLayout deviceCard = new LinearLayout(this);
        deviceCard.setOrientation(LinearLayout.VERTICAL);
        deviceCard.setPadding(
                dp(14), dp(12), dp(14), dp(12)
        );
        deviceCard.setBackground(
                bg(Color.rgb(232, 243, 254), 16)
        );

        TextView deviceLabel = text(
                "شناسه این گوشی",
                13,
                Color.rgb(65, 93, 119),
                true
        );

        TextView deviceValue = text(
                AuthManager.deviceId(this),
                16,
                Color.rgb(22, 62, 101),
                true
        );
        deviceValue.setTextDirection(
                View.TEXT_DIRECTION_LTR
        );
        deviceValue.setGravity(Gravity.LEFT);
        deviceValue.setPadding(0, dp(5), 0, 0);

        deviceCard.addView(deviceLabel);
        deviceCard.addView(deviceValue);

        LinearLayout.LayoutParams dcp =
                new LinearLayout.LayoutParams(-1, -2);
        dcp.setMargins(0, 0, 0, dp(14));
        root.addView(deviceCard, dcp);

        phone = input("شماره موبایل");
        phone.setInputType(
                InputType.TYPE_CLASS_PHONE
        );

        otp = input("کد تایید ۶ رقمی");
        otp.setInputType(
                InputType.TYPE_CLASS_NUMBER
        );
        otp.setVisibility(View.GONE);

        root.addView(phone);
        root.addView(otp);

        send = button(
                "ارسال کد تایید",
                Color.rgb(18, 185, 170)
        );

        verify = button(
                "تایید کد و ثبت این گوشی",
                Color.rgb(29, 120, 220)
        );
        verify.setVisibility(View.GONE);

        root.addView(send);
        root.addView(verify);

        status = text(
                "",
                14,
                Color.rgb(52, 101, 128),
                false
        );
        status.setGravity(Gravity.CENTER);
        status.setPadding(
                dp(10), dp(14), dp(10), dp(14)
        );
        root.addView(status);

        refresh = button(
                "بررسی وضعیت فعال‌سازی",
                Color.rgb(116, 83, 207)
        );
        refresh.setVisibility(
                AuthManager.hasToken(this)
                        ? View.VISIBLE
                        : View.GONE
        );
        root.addView(refresh);

        send.setOnClickListener(v -> requestOtp());
        verify.setOnClickListener(v -> verifyOtp());
        refresh.setOnClickListener(
                v -> checkActivation(false)
        );

        sc.addView(root);
        setContentView(sc);
    }

    private void requestOtp() {
        String p = phone.getText().toString().trim();

        if (p.isEmpty()) {
            Toast.makeText(
                    this,
                    "شماره موبایل را وارد کنید",
                    Toast.LENGTH_LONG
            ).show();
            return;
        }

        send.setEnabled(false);
        status.setText("در حال ارسال کد تایید...");

        AuthManager.requestOtp(
                this,
                p,
                (result, error) ->
                        runOnUiThread(() -> {
                            send.setEnabled(true);

                            if (error != null || result == null) {
                                status.setText(
                                        "ارتباط با سرور انجام نشد."
                                );
                                return;
                            }

                            if (!result.optBoolean("ok")) {
                                status.setText(
                                        result.optString(
                                                "message",
                                                "ثبت این گوشی ممکن نیست: "
                                                        + result.optString("code")
                                        )
                                );
                                return;
                            }

                            otp.setVisibility(View.VISIBLE);
                            verify.setVisibility(View.VISIBLE);

                            status.setText(
                                    "کد تایید ارسال شد. "
                                            + "شناسه گوشی به‌صورت خودکار ثبت می‌شود."
                            );
                        })
        );
    }

    private void verifyOtp() {
        String p = phone.getText().toString().trim();
        String code = otp.getText().toString().trim();

        if (p.isEmpty() || code.isEmpty()) {
            Toast.makeText(
                    this,
                    "شماره موبایل و کد تایید را وارد کنید",
                    Toast.LENGTH_LONG
            ).show();
            return;
        }

        verify.setEnabled(false);
        status.setText("در حال تایید کد و ثبت گوشی...");

        AuthManager.verifyOtp(
                this,
                p,
                code,
                (result, error) ->
                        runOnUiThread(() -> {
                            verify.setEnabled(true);

                            if (error != null || result == null) {
                                status.setText(
                                        "ارتباط با سرور انجام نشد."
                                );
                                return;
                            }

                            if (!result.optBoolean("ok")) {
                                status.setText(
                                        result.optString(
                                                "message",
                                                "کد تایید صحیح نیست."
                                        )
                                );
                                return;
                            }

                            refresh.setVisibility(View.VISIBLE);

                            if (result.optBoolean("active")) {
                                status.setText(
                                        result.optString(
                                                "message",
                                                "حساب فعال است."
                                        )
                                );
                                openMain();
                                return;
                            }

                            showPending(
                                    result.optString(
                                            "message",
                                            "ثبت گوشی انجام شد؛ "
                                                    + "منتظر فعال‌سازی مدیر باشید."
                                    )
                            );

                            startPolling();
                        })
        );
    }

    private void showPending(String message) {
        if (status != null) {
            status.setText(message);
        }
    }

    private void checkActivation(boolean auto) {
        if (!AuthManager.hasToken(this)) {
            if (!auto) {
                status.setText(
                        "ابتدا شماره موبایل را با کد پیامکی تایید کنید."
                );
            }
            return;
        }

        AuthManager.checkStatus(
                this,
                (result, error) ->
                        runOnUiThread(() -> {
                            if (error != null || result == null) {
                                if (!auto) {
                                    status.setText(
                                            "سرور در دسترس نیست."
                                    );
                                }
                                return;
                            }

                            if (!result.optBoolean("ok")) {
                                status.setText(
                                        result.optString(
                                                "message",
                                                "حساب معتبر نیست."
                                        )
                                );
                                return;
                            }

                            if (result.optBoolean("active")) {
                                int days = result.optInt(
                                        "remaining_days",
                                        AuthManager.remainingDays(this)
                                );

                                status.setText(
                                        "حساب فعال است • "
                                                + days
                                                + " روز از اعتبار تست باقی مانده"
                                );

                                openMain();
                                return;
                            }

                            String message = result.optString(
                                    "message",
                                    result.optBoolean("expired")
                                            ? "اعتبار ۳۰ روزه تمام شده است. "
                                                + "مدیر باید حساب را تمدید کند."
                                            : "حساب هنوز توسط مدیر فعال نشده است."
                            );

                            status.setText(message);

                            // چه در انتظار تایید و چه منقضی،
                            // با فعال/تمدید مدیر خودکار وارد برنامه می‌شود.
                            startPolling();
                        })
        );
    }

    private void startPolling() {
        if (polling) return;

        polling = true;

        handler.postDelayed(
                new Runnable() {
                    @Override
                    public void run() {
                        if (isFinishing()) return;

                        AuthManager.checkStatus(
                                RegistrationActivity.this,
                                (result, error) ->
                                        runOnUiThread(() -> {
                                            if (isFinishing()) return;

                                            if (result != null
                                                    && result.optBoolean("ok")
                                                    && result.optBoolean("active")) {

                                                status.setText(
                                                        result.optString(
                                                                "message",
                                                                "حساب فعال شد."
                                                        )
                                                );
                                                openMain();
                                                return;
                                            }

                                            if (result != null
                                                    && result.optBoolean("ok")) {
                                                status.setText(
                                                        result.optString(
                                                                "message",
                                                                "منتظر فعال‌سازی مدیر..."
                                                        )
                                                );
                                            }

                                            handler.postDelayed(
                                                    this,
                                                    8000L
                                            );
                                        })
                        );
                    }
                },
                8000L
        );
    }

    private void openMain() {
        polling = false;
        handler.removeCallbacksAndMessages(null);

        Intent i = new Intent(
                this,
                MainActivity.class
        );
        i.addFlags(
                Intent.FLAG_ACTIVITY_CLEAR_TOP
                        | Intent.FLAG_ACTIVITY_NEW_TASK
        );

        startActivity(i);
        finish();
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }

    private int dp(int v) {
        return (int)(
                v
                        * getResources()
                        .getDisplayMetrics()
                        .density
                        + 0.5f
        );
    }
}
