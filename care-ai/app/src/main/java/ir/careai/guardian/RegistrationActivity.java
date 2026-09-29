package ir.careai.guardian;

import android.app.Activity;
import android.content.Intent;
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

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class RegistrationActivity extends Activity {

    private final Handler handler =
            new Handler(Looper.getMainLooper());

    private EditText phone;
    private EditText otp;
    private TextView status;
    private TextView deviceIdView;
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
            t.setTypeface(
                    t.getTypeface(),
                    android.graphics.Typeface.BOLD
            );
        }

        return t;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setTextSize(16);
        e.setSingleLine(true);
        e.setPadding(dp(14), dp(10), dp(14), dp(10));
        e.setBackground(bg(Color.WHITE, 16));

        LinearLayout.LayoutParams p =
                new LinearLayout.LayoutParams(-1, dp(58));

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
                new LinearLayout.LayoutParams(-1, dp(58));

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
        root.setPadding(
                dp(18),
                dp(30),
                dp(18),
                dp(30)
        );

        TextView title = text(
                "فعال‌سازی Care AI",
                28,
                Color.rgb(22,62,101),
                true
        );

        title.setGravity(Gravity.CENTER);
        root.addView(title);

        TextView desc = text(
                "فعال‌سازی به‌صورت خودکار با شناسه واقعی همین گوشی انجام می‌شود. "
                        + "کاربر فقط شماره موبایل و کد پیامکی را وارد می‌کند. "
                        + "پس از تأیید پیامکی، مدیر حساب را برای یک ماه فعال می‌کند.",
                14,
                Color.rgb(91,111,134),
                false
        );

        desc.setGravity(Gravity.CENTER);
        desc.setPadding(
                dp(8),
                dp(10),
                dp(8),
                dp(18)
        );

        root.addView(desc);

        LinearLayout deviceCard = new LinearLayout(this);
        deviceCard.setOrientation(LinearLayout.VERTICAL);
        deviceCard.setPadding(
                dp(14),
                dp(12),
                dp(14),
                dp(12)
        );
        deviceCard.setBackground(
                bg(Color.rgb(233,246,255),16)
        );

        TextView deviceLabel = text(
                "شناسه این گوشی",
                13,
                Color.rgb(71,104,132),
                true
        );

        deviceIdView = text(
                AuthManager.deviceId(this),
                17,
                Color.rgb(22,62,101),
                true
        );

        deviceIdView.setTextDirection(
                View.TEXT_DIRECTION_LTR
        );

        deviceCard.addView(deviceLabel);
        deviceCard.addView(deviceIdView);

        LinearLayout.LayoutParams deviceParams =
                new LinearLayout.LayoutParams(-1,-2);

        deviceParams.setMargins(
                0,
                0,
                0,
                dp(12)
        );

        root.addView(deviceCard,deviceParams);

        phone = input("شماره موبایل");
        otp = input("کد تایید ۶ رقمی");
        otp.setVisibility(View.GONE);

        root.addView(phone);
        root.addView(otp);

        Button send = button(
                "ارسال کد تایید",
                Color.rgb(18,185,170)
        );

        verify = button(
                "ثبت کد و ارسال برای فعال‌سازی",
                Color.rgb(29,120,220)
        );

        verify.setVisibility(View.GONE);

        root.addView(send);
        root.addView(verify);

        status = text(
                "",
                14,
                Color.rgb(52,101,128),
                false
        );

        status.setGravity(Gravity.CENTER);
        status.setPadding(
                dp(10),
                dp(14),
                dp(10),
                dp(14)
        );

        root.addView(status);

        refresh = button(
                "بررسی وضعیت فعال‌سازی",
                Color.rgb(116,83,207)
        );

        refresh.setVisibility(
                AuthManager.hasToken(this)
                        ? View.VISIBLE
                        : View.GONE
        );

        root.addView(refresh);

        send.setOnClickListener(v -> {
            String p =
                    phone.getText()
                            .toString()
                            .trim();

            if (p.isEmpty()) {
                Toast.makeText(
                        this,
                        "شماره موبایل را وارد کنید",
                        Toast.LENGTH_LONG
                ).show();
                return;
            }

            String deviceId =
                    AuthManager.deviceId(this);

            if ("unknown-device".equals(deviceId)) {
                status.setText(
                        "شناسه گوشی قابل خواندن نیست. "
                                + "گوشی را یک‌بار راه‌اندازی مجدد کنید."
                );
                return;
            }

            status.setText(
                    "در حال ارسال کد..."
            );

            AuthManager.requestOtp(
                    this,
                    p,
                    (result,error) ->
                            runOnUiThread(() -> {
                                if (error != null
                                        || result == null) {

                                    status.setText(
                                            "ارتباط با سرور انجام نشد."
                                    );
                                    return;
                                }

                                if (!result.optBoolean("ok")) {
                                    status.setText(
                                            result.optString(
                                                    "message",
                                                    "ثبت این حساب ممکن نیست: "
                                                            + result.optString("code")
                                            )
                                    );
                                    return;
                                }

                                otp.setVisibility(View.VISIBLE);
                                verify.setVisibility(View.VISIBLE);

                                status.setText(
                                        "کد تایید ارسال شد. "
                                                + "شناسه دستگاه به‌صورت خودکار ثبت می‌شود."
                                );
                            })
            );
        });

        verify.setOnClickListener(v -> {
            String p =
                    phone.getText()
                            .toString()
                            .trim();

            String code =
                    otp.getText()
                            .toString()
                            .trim();

            if (p.isEmpty() || code.isEmpty()) {
                Toast.makeText(
                        this,
                        "شماره و کد تایید را وارد کنید",
                        Toast.LENGTH_LONG
                ).show();
                return;
            }

            status.setText(
                    "در حال تایید..."
            );

            AuthManager.verifyOtp(
                    this,
                    p,
                    code,
                    (result,error) ->
                            runOnUiThread(() -> {
                                if (error != null
                                        || result == null) {

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
                                    showActivationSuccess(result);
                                    handler.postDelayed(
                                            this::openMain,
                                            900L
                                    );
                                    return;
                                }

                                showPending(
                                        result.optString(
                                                "message",
                                                "ثبت‌نام انجام شد؛ "
                                                        + "منتظر فعال‌سازی مدیر باشید."
                                        )
                                );

                                if (!"ACTIVATION_EXPIRED".equals(
                                        result.optString(
                                                "activation_code",
                                                ""
                                        ))) {
                                    startPolling();
                                }
                            })
            );
        });

        refresh.setOnClickListener(
                v -> checkActivation(false)
        );

        sc.addView(root);
        setContentView(sc);
    }

    private void showActivationSuccess(
            JSONObject result) {

        String mode =
                result.optString(
                        "activation_mode",
                        "active"
                );

        long expiresAt =
                result.optLong(
                        "expires_at",
                        0L
                );

        String modeFa =
                "test".equals(mode)
                        ? "حالت تست"
                        : "فعال";

        String expiry =
                formatExpiry(expiresAt);

        status.setText(
                "حساب " + modeFa
                        + " شد"
                        + (expiry.isEmpty()
                        ? ""
                        : " • اعتبار تا " + expiry)
        );
    }

    private String formatExpiry(long seconds) {
        if (seconds <= 0L) return "";

        try {
            return new SimpleDateFormat(
                    "yyyy/MM/dd HH:mm",
                    Locale.US
            ).format(
                    new Date(seconds * 1000L)
            );
        } catch (Exception e) {
            return "";
        }
    }

    private void showPending(String message) {
        if (status != null) {
            status.setText(message);
        }
    }

    private void checkActivation(boolean auto) {
        AuthManager.checkStatus(
                this,
                (result,error) ->
                        runOnUiThread(() -> {
                            if (error != null
                                    || result == null) {

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
                                showActivationSuccess(result);

                                handler.postDelayed(
                                        this::openMain,
                                        650L
                                );
                                return;
                            }

                            String activationCode =
                                    result.optString(
                                            "activation_code",
                                            ""
                                    );

                            status.setText(
                                    result.optString(
                                            "message",
                                            "حساب هنوز توسط مدیر فعال نشده است."
                                    )
                            );

                            if (!"ACTIVATION_EXPIRED".equals(
                                    activationCode
                            )) {
                                startPolling();
                            }
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
                                (result,error) ->
                                        runOnUiThread(() -> {
                                            if (result != null
                                                    && result.optBoolean("ok")) {

                                                if (result.optBoolean("active")) {
                                                    polling = false;
                                                    showActivationSuccess(result);
                                                    handler.postDelayed(
                                                            RegistrationActivity.this::openMain,
                                                            500L
                                                    );
                                                    return;
                                                }

                                                String code =
                                                        result.optString(
                                                                "activation_code",
                                                                ""
                                                        );

                                                status.setText(
                                                        result.optString(
                                                                "message",
                                                                "منتظر فعال‌سازی مدیر باشید."
                                                        )
                                                );

                                                if ("ACTIVATION_EXPIRED".equals(code)) {
                                                    polling = false;
                                                    return;
                                                }
                                            }

                                            handler.postDelayed(
                                                    this,
                                                    10000L
                                            );
                                        })
                        );
                    }
                },
                10000L
        );
    }

    private void openMain() {
        polling = false;

        Intent i =
                new Intent(
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
