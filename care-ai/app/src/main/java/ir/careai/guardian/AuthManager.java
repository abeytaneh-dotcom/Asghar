package ir.careai.guardian;

import android.content.Context;
import android.content.SharedPreferences;
import android.provider.Settings;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public final class AuthManager {

    public static final String API_URL = "https://achinu.ir/careai/api.php";

    public interface Callback {
        void done(JSONObject result, Exception error);
    }

    private AuthManager() {}

    public static String deviceId(Context c) {
        String id = Settings.Secure.getString(
                c.getContentResolver(),
                Settings.Secure.ANDROID_ID
        );
        return id == null ? "unknown-device" : id;
    }

    public static boolean hasToken(Context c) {
        return !prefs(c).getString("auth_token", "").trim().isEmpty();
    }

    public static String token(Context c) {
        return prefs(c).getString("auth_token", "");
    }

    public static String patientId(Context c) {
        return prefs(c).getString("auth_patient_id", "");
    }

    public static void requestOtp(
            Context c,
            String phone,
            Callback cb) {

        JSONObject p = new JSONObject();
        try {
            p.put("action", "request_otp");
            p.put("phone", phone);
            p.put("device_id", deviceId(c));
        } catch (Exception ignored) {}

        post(p, cb);
    }

    public static void verifyOtp(
            Context c,
            String phone,
            String code,
            Callback cb) {

        JSONObject p = new JSONObject();
        try {
            p.put("action", "verify_otp");
            p.put("phone", phone);
            p.put("device_id", deviceId(c));
            p.put("code", code);
        } catch (Exception ignored) {}

        post(p, (result, error) -> {
            if (error == null && result != null && result.optBoolean("ok")) {
                long expiresAt = result.optLong("expires_at", 0L);

                SharedPreferences.Editor e = prefs(c).edit()
                        .putString("auth_token", result.optString("token", ""))
                        .putString("auth_phone", phone)
                        .putString(
                                "auth_patient_id",
                                result.optString("patient_id", "")
                        )
                        .putString(
                                "auth_device_id",
                                result.optString("device_id", deviceId(c))
                        )
                        .putString("video_key", result.optString("video_key", ""))
                        .putString(
                                "auth_activation_mode",
                                result.optString("activation_mode", "trial")
                        )
                        .putLong("auth_expires_at", expiresAt)
                        .putBoolean(
                                "auth_active_cache",
                                result.optBoolean("active", false)
                        )
                        .putLong(
                                "auth_checked_at",
                                System.currentTimeMillis()
                        );

                e.apply();
            }

            cb.done(result, error);
        });
    }

    public static void checkStatus(Context c, Callback cb) {
        JSONObject p = new JSONObject();
        try {
            p.put("action", "status");
            p.put("token", token(c));
            p.put("device_id", deviceId(c));
        } catch (Exception ignored) {}

        post(p, (result, error) -> {
            if (error == null && result != null && result.optBoolean("ok")) {
                prefs(c).edit()
                        .putBoolean(
                                "auth_active_cache",
                                result.optBoolean("active", false)
                        )
                        .putString(
                                "auth_patient_id",
                                result.optString("patient_id", patientId(c))
                        )
                        .putString(
                                "auth_device_id",
                                result.optString("device_id", deviceId(c))
                        )
                        .putString(
                                "auth_activation_mode",
                                result.optString("activation_mode", "trial")
                        )
                        .putLong(
                                "auth_expires_at",
                                result.optLong("expires_at", 0L)
                        )
                        .putString(
                                "video_key",
                                result.optString(
                                        "video_key",
                                        prefs(c).getString("video_key","")
                                )
                        )
                        .putLong(
                                "auth_checked_at",
                                System.currentTimeMillis()
                        )
                        .apply();
            }
            cb.done(result, error);
        });
    }

    public static boolean cachedActive(Context c) {
        SharedPreferences p = prefs(c);

        if (!p.getBoolean("auth_active_cache", false)) {
            return false;
        }

        long expiresAtSeconds = p.getLong("auth_expires_at", 0L);

        // حساب‌های جدید همیشه expiry دارند. برای حساب قدیمی بدون expiry
        // فقط تا اولین checkStatus آنلاین اجازه عبور می‌دهیم.
        if (expiresAtSeconds <= 0L) {
            long checkedAt = p.getLong("auth_checked_at", 0L);
            return checkedAt > 0L
                    && System.currentTimeMillis() - checkedAt
                    < 6L * 60L * 60L * 1000L;
        }

        return System.currentTimeMillis()
                < expiresAtSeconds * 1000L;
    }

    public static long expiresAt(Context c) {
        return prefs(c).getLong("auth_expires_at", 0L);
    }

    public static int remainingDays(Context c) {
        long expires = expiresAt(c);
        if (expires <= 0L) return 0;

        long diff = expires * 1000L - System.currentTimeMillis();
        if (diff <= 0L) return 0;

        return (int)Math.ceil(
                diff / (24d * 60d * 60d * 1000d)
        );
    }

    public static void clear(Context c) {
        prefs(c).edit()
                .remove("auth_token")
                .remove("auth_phone")
                .remove("auth_patient_id")
                .remove("auth_device_id")
                .remove("auth_activation_mode")
                .remove("auth_expires_at")
                .remove("auth_active_cache")
                .remove("video_key")
                .remove("auth_checked_at")
                .apply();
    }

    private static SharedPreferences prefs(Context c) {
        return c.getSharedPreferences("careai", Context.MODE_PRIVATE);
    }

    private static void post(JSONObject payload, Callback cb) {
        new Thread(() -> {
            HttpURLConnection con = null;
            try {
                con = (HttpURLConnection) new URL(API_URL).openConnection();
                con.setConnectTimeout(9000);
                con.setReadTimeout(10000);
                con.setRequestMethod("POST");
                con.setDoOutput(true);
                con.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                con.setRequestProperty("Accept", "application/json");

                byte[] body = payload.toString().getBytes(StandardCharsets.UTF_8);
                try (OutputStream os = con.getOutputStream()) {
                    os.write(body);
                }

                int status = con.getResponseCode();
                BufferedReader br = new BufferedReader(new InputStreamReader(
                        status >= 200 && status < 400
                                ? con.getInputStream()
                                : con.getErrorStream(),
                        StandardCharsets.UTF_8
                ));

                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = br.readLine()) != null) sb.append(line);
                br.close();

                JSONObject result = new JSONObject(sb.length() == 0 ? "{}" : sb.toString());
                cb.done(result, null);

            } catch (Exception e) {
                cb.done(null, e);
            } finally {
                if (con != null) con.disconnect();
            }
        }).start();
    }
}
