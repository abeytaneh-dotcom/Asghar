package ir.careai.guardian;

import android.accessibilityservice.AccessibilityService;
import android.accessibilityservice.AccessibilityServiceInfo;
import android.accessibilityservice.GestureDescription;
import android.content.Context;
import android.content.Intent;
import android.graphics.Path;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.accessibility.AccessibilityEvent;
import android.view.accessibility.AccessibilityManager;
import android.view.accessibility.AccessibilityNodeInfo;

import java.util.List;

public class CareAccessibilityService extends AccessibilityService {

    private static CareAccessibilityService instance;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private String currentPackage = "";

    @Override
    protected void onServiceConnected() {
        super.onServiceConnected();
        instance = this;
    }

    @Override
    public void onAccessibilityEvent(AccessibilityEvent event) {
        if (event != null && event.getPackageName() != null) {
            currentPackage = event.getPackageName().toString();
        }
    }

    @Override
    public void onInterrupt() {}

    @Override
    public void onDestroy() {
        if (instance == this) instance = null;
        super.onDestroy();
    }

    public static boolean isEnabled(Context context) {
        AccessibilityManager am =
                (AccessibilityManager) context.getSystemService(Context.ACCESSIBILITY_SERVICE);
        if (am == null || !am.isEnabled()) return false;

        List<android.accessibilityservice.AccessibilityServiceInfo> list =
                am.getEnabledAccessibilityServiceList(
                        AccessibilityServiceInfo.FEEDBACK_ALL_MASK
                );

        String expected = context.getPackageName() + "/" + CareAccessibilityService.class.getName();
        for (android.accessibilityservice.AccessibilityServiceInfo info : list) {
            if (info.getResolveInfo() == null
                    || info.getResolveInfo().serviceInfo == null) continue;

            String id = info.getResolveInfo().serviceInfo.packageName
                    + "/" + info.getResolveInfo().serviceInfo.name;
            if (expected.equals(id)
                    || info.getResolveInfo().serviceInfo.name.endsWith(".CareAccessibilityService")) {
                return true;
            }
        }
        return instance != null;
    }

    public static void openSettings(Context context) {
        Intent i = new Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        context.startActivity(i);
    }

    public static boolean isInstagramActive() {
        return instance != null
                && "com.instagram.android".equals(instance.currentPackage);
    }

    public static void openInstagramReelsSoon() {
        if (instance == null) return;

        // بعضی نسخه‌های Instagram بعد از Intent مستقیم، Home را نشان می‌دهند.
        // چند بار روی نود Reels تلاش می‌کنیم و بین تلاش‌ها دوباره Deep Link می‌فرستیم.
        instance.handler.postDelayed(() -> instance.tryOpenReels(0), 650L);
    }

    private void tryOpenReels(int attempt) {
        if (clickReelsNode()) return;

        if (attempt == 0 || attempt == 2) {
            try {
                Intent i = new Intent(
                        Intent.ACTION_VIEW,
                        android.net.Uri.parse("https://www.instagram.com/reels/")
                );
                i.setPackage("com.instagram.android");
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK
                        | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                startActivity(i);
            } catch (Exception ignored) {}
        }

        if (attempt < 5) {
            handler.postDelayed(
                    () -> tryOpenReels(attempt + 1),
                    850L
            );
        }
    }

    private boolean clickReelsNode() {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) return false;

        AccessibilityNodeInfo node = findReels(root);
        if (node != null) {
            AccessibilityNodeInfo clickable = node;
            while (clickable != null && !clickable.isClickable()) {
                clickable = clickable.getParent();
            }
            if (clickable != null) {
                return clickable.performAction(AccessibilityNodeInfo.ACTION_CLICK);
            }
        }
        return false;
    }

    private AccessibilityNodeInfo findReels(AccessibilityNodeInfo node) {
        if (node == null) return null;

        CharSequence text = node.getText();
        CharSequence desc = node.getContentDescription();

        String s = ((text == null ? "" : text.toString()) + " "
                + (desc == null ? "" : desc.toString())).toLowerCase();

        if (s.contains("reels")
                || s.contains("reel")
                || s.contains("ریلز")
                || s.contains("کلیپ")) {
            return node;
        }

        for (int i = 0; i < node.getChildCount(); i++) {
            AccessibilityNodeInfo found = findReels(node.getChild(i));
            if (found != null) return found;
        }

        return null;
    }

    public static void nextInstagramReel() {
        if (instance == null) return;
        instance.swipeUp();
    }

    private void swipeUp() {
        android.util.DisplayMetrics dm = getResources().getDisplayMetrics();

        float x = dm.widthPixels * 0.52f;
        float y1 = dm.heightPixels * 0.78f;
        float y2 = dm.heightPixels * 0.25f;

        Path path = new Path();
        path.moveTo(x, y1);
        path.lineTo(x, y2);

        GestureDescription.StrokeDescription stroke =
                new GestureDescription.StrokeDescription(path, 0, 280);

        GestureDescription gesture =
                new GestureDescription.Builder()
                        .addStroke(stroke)
                        .build();

        dispatchGesture(gesture, null, null);
    }
}
