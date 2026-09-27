package ir.careai.guardian;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.provider.MediaStore;
import android.provider.Settings;

public final class DeviceActionEngine {

    private DeviceActionEngine() {}

    public static boolean isExecutableCommand(CommandStore.Command cmd) {
        if (cmd == null) return false;

        if (CommandStore.ACTION_OPEN_APP.equals(cmd.action)
                || CommandStore.ACTION_OPEN_URL.equals(cmd.action)) {
            return true;
        }

        if (!CommandStore.ACTION_SPEAK.equals(cmd.action)) {
            return false;
        }

        return !inferAppTarget(cmd).isEmpty()
                || looksLikeUrl(cmd.target)
                || looksLikeUrl(cmd.output);
    }

    public static boolean execute(Activity activity, CommandStore.Command cmd) {
        if (cmd == null) return false;

        if (CommandStore.ACTION_OPEN_URL.equals(cmd.action)) {
            String target = firstNonEmpty(cmd.target, cmd.output);
            return openUrl(activity, target);
        }

        if (CommandStore.ACTION_OPEN_APP.equals(cmd.action)) {
            String target = firstNonEmpty(cmd.target, cmd.output, cmd.question);
            return openApp(activity, target);
        }

        // سازگاری با دستورهای V9 که نوع عمل آنها هنوز SPEAK بوده ولی
        // متنشان یک فرمان اجرایی مثل «اینستاگرام باز بشه» است.
        if (CommandStore.ACTION_SPEAK.equals(cmd.action)) {
            String inferred = inferAppTarget(cmd);
            if (!inferred.isEmpty()) {
                return openApp(activity, inferred);
            }

            String possibleUrl = firstNonEmpty(cmd.target, cmd.output);
            if (looksLikeUrl(possibleUrl)) {
                return openUrl(activity, possibleUrl);
            }
        }

        return false;
    }

    private static boolean openApp(Activity activity, String target) {
        String raw = target == null ? "" : target.trim();
        if (raw.isEmpty()) return false;

        String normalized = normalize(raw);

        // فرمان‌های سیستمی ساده
        if (containsAny(normalized, "تنظیمات", "settings")) {
            return start(activity, new Intent(Settings.ACTION_SETTINGS));
        }

        if (containsAny(normalized, "دوربین", "camera")) {
            return start(
                    activity,
                    new Intent(MediaStore.INTENT_ACTION_STILL_IMAGE_CAMERA)
            );
        }

        if (containsAny(normalized, "گالری", "gallery", "عکسها", "عکس ها")) {
            Intent i = new Intent(
                    Intent.ACTION_VIEW,
                    MediaStore.Images.Media.EXTERNAL_CONTENT_URI
            );
            return start(activity, i);
        }

        AppTarget known = knownTarget(normalized);
        if (known != null) {
            if (launchPackage(activity, known.packageName)) {
                return true;
            }

            if (known.deepLink != null && !known.deepLink.isEmpty()) {
                Intent deep = new Intent(Intent.ACTION_VIEW, Uri.parse(known.deepLink));
                if (start(activity, deep)) return true;
            }

            if (known.fallbackUrl != null && !known.fallbackUrl.isEmpty()) {
                return openUrl(activity, known.fallbackUrl);
            }

            return false;
        }

        // اگر کاربر package name داده باشد، مستقیم همان اپ اجرا می‌شود.
        if (raw.contains(".") && !raw.contains(" ")) {
            if (launchPackage(activity, raw)) return true;
        }

        // اگر خود target یک URL باشد، به عنوان لینک باز شود.
        if (looksLikeUrl(raw)) {
            return openUrl(activity, raw);
        }

        return false;
    }

    private static boolean launchPackage(Activity activity, String packageName) {
        try {
            Intent launch =
                    activity.getPackageManager().getLaunchIntentForPackage(packageName);

            if (launch == null) return false;

            launch.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            activity.startActivity(launch);
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    private static boolean openUrl(Activity activity, String target) {
        if (target == null) return false;

        String value = target.trim();
        if (value.isEmpty()) return false;

        if (!value.contains("://")) {
            value = "https://" + value;
        }

        try {
            Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse(value));
            activity.startActivity(i);
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    private static boolean start(Activity activity, Intent intent) {
        try {
            activity.startActivity(intent);
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    public static String inferAppTarget(CommandStore.Command cmd) {
        if (cmd == null) return "";

        String all = normalize(
                firstNonEmpty(cmd.target, "")
                        + " "
                        + firstNonEmpty(cmd.output, "")
                        + " "
                        + firstNonEmpty(cmd.question, "")
        );

        if (containsAny(all, "اینستاگرام", "instagram", "insta")) {
            return "Instagram";
        }
        if (containsAny(all, "واتساپ", "واتس اپ", "whatsapp")) {
            return "WhatsApp";
        }
        if (containsAny(all, "تلگرام", "telegram")) {
            return "Telegram";
        }
        if (containsAny(all, "یوتیوب", "youtube")) {
            return "YouTube";
        }
        if (containsAny(all, "اسپاتیفای", "spotify")) {
            return "Spotify";
        }
        if (containsAny(all, "کروم", "chrome")) {
            return "Chrome";
        }
        if (containsAny(all, "گوگل مپ", "گوگل مپس", "google maps", "maps")) {
            return "Maps";
        }
        if (containsAny(all, "دوربین", "camera")) {
            return "Camera";
        }
        if (containsAny(all, "گالری", "gallery")) {
            return "Gallery";
        }
        if (containsAny(all, "تنظیمات", "settings")) {
            return "Settings";
        }

        return "";
    }

    private static AppTarget knownTarget(String normalized) {
        if (containsAny(normalized, "اینستاگرام", "instagram", "insta")) {
            return new AppTarget(
                    "com.instagram.android",
                    "instagram://app",
                    "https://www.instagram.com/"
            );
        }

        if (containsAny(normalized, "واتساپ", "واتس اپ", "whatsapp")) {
            return new AppTarget(
                    "com.whatsapp",
                    "whatsapp://send",
                    "https://www.whatsapp.com/"
            );
        }

        if (containsAny(normalized, "تلگرام", "telegram")) {
            return new AppTarget(
                    "org.telegram.messenger",
                    "tg://resolve?domain=telegram",
                    "https://t.me/"
            );
        }

        if (containsAny(normalized, "یوتیوب", "youtube")) {
            return new AppTarget(
                    "com.google.android.youtube",
                    "vnd.youtube://",
                    "https://www.youtube.com/"
            );
        }

        if (containsAny(normalized, "اسپاتیفای", "spotify")) {
            return new AppTarget(
                    "com.spotify.music",
                    "spotify://",
                    "https://open.spotify.com/"
            );
        }

        if (containsAny(normalized, "کروم", "chrome")) {
            return new AppTarget(
                    "com.android.chrome",
                    "",
                    "https://www.google.com/"
            );
        }

        if (containsAny(normalized, "گوگل مپ", "گوگل مپس", "google maps", "maps")) {
            return new AppTarget(
                    "com.google.android.apps.maps",
                    "geo:0,0?q=",
                    "https://maps.google.com/"
            );
        }

        return null;
    }

    private static boolean looksLikeUrl(String value) {
        if (value == null) return false;

        String s = value.trim().toLowerCase();
        return s.startsWith("http://")
                || s.startsWith("https://")
                || s.startsWith("www.")
                || s.contains(".com")
                || s.contains(".ir")
                || s.contains(".net")
                || s.contains(".org");
    }

    private static String normalize(String value) {
        if (value == null) return "";

        return value
                .trim()
                .toLowerCase()
                .replace('ي', 'ی')
                .replace('ك', 'ک')
                .replace("\u200c", " ");
    }

    private static boolean containsAny(String text, String... values) {
        for (String value : values) {
            if (text.contains(value)) return true;
        }
        return false;
    }

    private static String firstNonEmpty(String... values) {
        for (String v : values) {
            if (v != null && !v.trim().isEmpty()) return v.trim();
        }
        return "";
    }

    private static final class AppTarget {
        final String packageName;
        final String deepLink;
        final String fallbackUrl;

        AppTarget(String packageName, String deepLink, String fallbackUrl) {
            this.packageName = packageName;
            this.deepLink = deepLink;
            this.fallbackUrl = fallbackUrl;
        }
    }
}
