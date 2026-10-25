package ir.websaz.keepawake;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.provider.Settings;
import android.view.Gravity;
import android.view.View;
import android.widget.CompoundButton;
import android.widget.LinearLayout;
import android.widget.Switch;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {
    private static final String PREFS = "keep_awake_prefs";
    private static final String KEY_ENABLED = "enabled";
    private static final String KEY_OLD_TIMEOUT = "old_timeout";
    private static final String KEY_PENDING_ENABLE = "pending_enable";
    private static final int PRACTICALLY_NEVER_MS = Integer.MAX_VALUE;

    private SharedPreferences prefs;
    private Switch keepAwakeSwitch;
    private TextView statusText;
    private boolean suppressListener = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        buildUi();
        syncUi();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (prefs.getBoolean(KEY_PENDING_ENABLE, false) && Settings.System.canWrite(this)) {
            prefs.edit().putBoolean(KEY_PENDING_ENABLE, false).apply();
            enableKeepAwake();
        }
        syncUi();
    }

    private void buildUi() {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.CENTER_HORIZONTAL);
        root.setPadding(dp(24), dp(56), dp(24), dp(32));
        root.setBackgroundColor(Color.rgb(15, 18, 24));
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        TextView icon = new TextView(this);
        icon.setText("☀");
        icon.setTextSize(58);
        icon.setTextColor(Color.WHITE);
        icon.setGravity(Gravity.CENTER);
        root.addView(icon, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(90)));

        TextView title = new TextView(this);
        title.setText("صفحه همیشه روشن");
        title.setTextSize(27);
        title.setTextColor(Color.WHITE);
        title.setGravity(Gravity.CENTER);
        title.setTypeface(null, android.graphics.Typeface.BOLD);
        root.addView(title, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));

        TextView subtitle = new TextView(this);
        subtitle.setText("با روشن کردن کلید، صفحه گوشی به‌خاطر تایم‌اوت خاموش و قفل نمی‌شود.");
        subtitle.setTextSize(16);
        subtitle.setTextColor(Color.rgb(190, 195, 205));
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setPadding(0, dp(14), 0, dp(34));
        root.addView(subtitle, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(22), dp(22), dp(22), dp(22));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.rgb(29, 34, 44));
        bg.setCornerRadius(dp(22));
        card.setBackground(bg);

        keepAwakeSwitch = new Switch(this);
        keepAwakeSwitch.setText("جلوگیری از خاموش شدن صفحه");
        keepAwakeSwitch.setTextSize(18);
        keepAwakeSwitch.setTextColor(Color.WHITE);
        keepAwakeSwitch.setGravity(Gravity.CENTER_VERTICAL);
        keepAwakeSwitch.setPadding(0, dp(6), 0, dp(6));
        card.addView(keepAwakeSwitch, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(64)));

        statusText = new TextView(this);
        statusText.setTextSize(15);
        statusText.setGravity(Gravity.CENTER);
        statusText.setPadding(0, dp(16), 0, dp(4));
        card.addView(statusText, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));

        LinearLayout.LayoutParams cardParams = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT);
        cardParams.setMargins(0, 0, 0, dp(24));
        root.addView(card, cardParams);

        TextView note = new TextView(this);
        note.setText("بار اول اندروید اجازه «تغییر تنظیمات سیستم» می‌خواهد؛ فقط همان یک‌بار اجازه بده.\n\nدکمه پاور همچنان می‌تواند گوشی را دستی قفل کند.");
        note.setTextSize(14);
        note.setTextColor(Color.rgb(145, 153, 168));
        note.setGravity(Gravity.CENTER);
        root.addView(note, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));

        keepAwakeSwitch.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
            @Override
            public void onCheckedChanged(CompoundButton buttonView, boolean isChecked) {
                if (suppressListener) return;
                if (isChecked) {
                    requestOrEnable();
                } else {
                    disableKeepAwake();
                }
            }
        });

        setContentView(root);
    }

    private void requestOrEnable() {
        if (!Settings.System.canWrite(this)) {
            prefs.edit().putBoolean(KEY_PENDING_ENABLE, true).apply();
            setSwitchChecked(false);
            Intent intent = new Intent(Settings.ACTION_MANAGE_WRITE_SETTINGS);
            intent.setData(Uri.parse("package:" + getPackageName()));
            startActivity(intent);
            Toast.makeText(this, "اجازه تغییر تنظیمات سیستم را فعال کن", Toast.LENGTH_LONG).show();
            return;
        }
        enableKeepAwake();
    }

    private void enableKeepAwake() {
        try {
            if (!prefs.getBoolean(KEY_ENABLED, false)) {
                int oldTimeout = Settings.System.getInt(
                        getContentResolver(), Settings.System.SCREEN_OFF_TIMEOUT, 30000);
                prefs.edit().putInt(KEY_OLD_TIMEOUT, oldTimeout).apply();
            }
            boolean ok = Settings.System.putInt(
                    getContentResolver(), Settings.System.SCREEN_OFF_TIMEOUT, PRACTICALLY_NEVER_MS);
            if (ok) {
                prefs.edit().putBoolean(KEY_ENABLED, true).apply();
                Toast.makeText(this, "صفحه دیگر با تایم‌اوت خاموش نمی‌شود", Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "تغییر تنظیمات انجام نشد", Toast.LENGTH_LONG).show();
            }
        } catch (Exception e) {
            Toast.makeText(this, "خطا در تغییر تنظیمات صفحه", Toast.LENGTH_LONG).show();
        }
        syncUi();
    }

    private void disableKeepAwake() {
        if (!prefs.getBoolean(KEY_ENABLED, false)) {
            syncUi();
            return;
        }
        if (!Settings.System.canWrite(this)) {
            prefs.edit().putBoolean(KEY_PENDING_ENABLE, false).apply();
            setSwitchChecked(true);
            Intent intent = new Intent(Settings.ACTION_MANAGE_WRITE_SETTINGS);
            intent.setData(Uri.parse("package:" + getPackageName()));
            startActivity(intent);
            Toast.makeText(this, "برای برگرداندن زمان قبلی، اجازه تغییر تنظیمات را فعال کن", Toast.LENGTH_LONG).show();
            return;
        }
        int oldTimeout = prefs.getInt(KEY_OLD_TIMEOUT, 120000);
        try {
            Settings.System.putInt(
                    getContentResolver(), Settings.System.SCREEN_OFF_TIMEOUT, oldTimeout);
            prefs.edit().putBoolean(KEY_ENABLED, false).apply();
            Toast.makeText(this, "زمان خاموش شدن قبلی برگشت", Toast.LENGTH_SHORT).show();
        } catch (Exception e) {
            Toast.makeText(this, "خطا در برگرداندن تنظیم قبلی", Toast.LENGTH_LONG).show();
        }
        syncUi();
    }

    private void syncUi() {
        if (keepAwakeSwitch == null || statusText == null) return;
        boolean enabled = prefs.getBoolean(KEY_ENABLED, false);
        setSwitchChecked(enabled);
        if (enabled) {
            statusText.setText("● فعال — صفحه روشن می‌ماند");
            statusText.setTextColor(Color.rgb(80, 220, 140));
        } else {
            statusText.setText("● غیرفعال — تنظیم عادی گوشی");
            statusText.setTextColor(Color.rgb(190, 195, 205));
        }
    }

    private void setSwitchChecked(boolean checked) {
        suppressListener = true;
        keepAwakeSwitch.setChecked(checked);
        suppressListener = false;
    }

    private int dp(int value) {
        float density = getResources().getDisplayMetrics().density;
        return Math.round(value * density);
    }
}
