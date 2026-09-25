package ir.khanehremap.diag;

import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.Gravity;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class SplashActivity extends Activity {
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private LinearLayout root;

    @Override protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(Color.rgb(2, 9, 20));
        getWindow().setNavigationBarColor(Color.rgb(2, 9, 20));
        buildLoading();
        runPreflight();
    }

    private void buildLoading() {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.CENTER);
        root.setPadding(48, 48, 48, 48);
        root.setBackgroundColor(Color.rgb(2, 9, 20));

        TextView title = label("خانه ریمپ", 34, true, Color.WHITE);
        TextView sub = label("KHANEH REMAP", 14, true, Color.rgb(255, 122, 24));
        ProgressBar p = new ProgressBar(this);
        root.addView(title);
        root.addView(sub);
        root.addView(p);
        setContentView(root);
    }

    private TextView label(String text, int sp, boolean bold, int color) {
        TextView v = new TextView(this);
        v.setText(text);
        v.setTextSize(sp);
        v.setTextColor(color);
        v.setGravity(Gravity.CENTER);
        if (bold) v.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        v.setPadding(10, 10, 10, 18);
        return v;
    }

    private void runPreflight() {
        executor.execute(() -> {
            if (!chromeInstalled()) {
                runOnUiThread(() -> showError(2));
                return;
            }
            int result = Preflight.check();
            runOnUiThread(() -> {
                if (result == 0) launch();
                else showError(result);
            });
        });
    }

    private boolean chromeInstalled() {
        try {
            getPackageManager().getPackageInfo("com.android.chrome", 0);
            return true;
        } catch (PackageManager.NameNotFoundException e) {
            return false;
        }
    }

    private void launch() {
        startActivity(new Intent(this, TwaActivity.class));
        finish();
    }

    private void showError(int type) {
        root.removeAllViews();
        root.addView(label("خانه ریمپ", 30, true, Color.WHITE));
        TextView msg = label(
                type == 1 ? getString(R.string.error_network) : getString(R.string.error_setup),
                18, false, Color.rgb(185, 211, 230));
        msg.setMaxWidth(760);
        root.addView(msg);

        Button retry = new Button(this);
        retry.setText(R.string.retry);
        retry.setTextSize(17);
        retry.setTextColor(Color.WHITE);
        retry.setBackgroundColor(Color.rgb(11, 124, 193));
        retry.setPadding(40, 16, 40, 16);
        retry.setOnClickListener(v -> {
            buildLoading();
            runPreflight();
        });
        root.addView(retry);
    }

    @Override protected void onDestroy() {
        executor.shutdownNow();
        super.onDestroy();
    }
}
