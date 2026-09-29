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

        if (id == null || id.trim().isEmpty()) {
            return "unknown-device";
        }

        return id.trim();
    }

    public static boolean hasToken(Context c) {
        return !prefs(c).getString("auth_token", "").trim().isEmpty();
    }

    public static String token(Context c) {
        return prefs(c).getString("auth_token", "");
    }

    // شناسه داخلی برای قابلیت‌هایی مثل تماس تصویری.
    // در حساب‌های جدید برابر شناسه واقعی دستگاه است.
    public static String patientId(Context c) {
        return prefs(c).getString("auth_patient_id", deviceId(c));
    }

    public static String activationMode(Context c) {
        return prefs(c).getString("auth_activation_mode", "inactive");
    }

    public static long activationExpiresAt(Context c) {
        return prefs(c).getLong("auth_expires_at", 0L);
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
                saveAuthState(c, result, phone);
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
                saveStatusState(c, result);
            }
            cb.done(result, error);
        });
    }

    private static void saveAuthState(
            Context c,
            JSONObject result,
            String phone) {

        long expiresAt =
                result.optLong("expires_at", 0L);

        prefs(c).edit()
                .putString("auth_token", result.optString("token", ""))
                .putString("auth_phone", phone)
                .putString(
                        "auth_patient_id",
                        result.optString("patient_id", deviceId(c))
                )
                .putString(
                        "video_key",
                        result.optString("video_key", "")
                )
                .putBoolean(
                        "auth_active_cache",
                        result.optBoolean("active", false)
                )
                .putString(
                        "auth_activation_mode",
                        result.optString("activation_mode", "inactive")
                )
                .putLong("auth_expires_at", expiresAt)
                .putLong("auth_checked_at", System.currentTimeMillis())
                .apply();
    }

    private static void saveStatusState(
            Context c,
            JSONObject result) {

        SharedPreferences p = prefs(c);

        p.edit()
                .putBoolean(
                        "auth_active_cache",
                        result.optBoolean("active", false)
                )
                .putString(
                        "auth_patient_id",
                        result.optString(
                                "patient_id",
                                p.getString("auth_patient_id", deviceId(c))
                        )
                )
                .putString(
                        "video_key",
                        result.optString(
                                "video_key",
                                p.getString("video_key", "")
                        )
                )
                .putString(
                        "auth_activation_mode",
                        result.optString("activation_mode", "inactive")
                )
                .putLong(
                        "auth_expires_at",
                        result.optLong("expires_at", 0L)
                )
                .putLong("auth_checked_at", System.currentTimeMillis())
                .apply();
    }

    public static boolean cachedActive(Context c) {
        SharedPreferences p = prefs(c);

        if (!p.getBoolean("auth_active_cache", false)) {
            return false;
        }

        long expiresAt = p.getLong("auth_expires_at", 0L);

        // حساب بدون تاریخ اعتبار، فعال تلقی نمی‌شود.
        if (expiresAt <= 0L) {
            return false;
        }

        long nowSeconds = System.currentTimeMillis() / 1000L;

        if (nowSeconds >= expiresAt) {
            p.edit()
                    .putBoolean("auth_active_cache", false)
                    .putString("auth_activation_mode", "expired")
                    .apply();
            return false;
        }

        return true;
    }

    public static void clear(Context c) {
        prefs(c).edit()
                .remove("auth_token")
                .remove("auth_phone")
                .remove("auth_patient_id")
                .remove("auth_active_cache")
                .remove("auth_activation_mode")
                .remove("auth_expires_at")
                .remove("video_key")
                .remove("auth_checked_at")
                .apply();
    }

    private static SharedPreferences prefs(Context c) {
        return c.getSharedPreferences("careai", Context.MODE_PRIVATE);
    }

    private static void post(
            JSONObject payload,
            Callback cb) {

        new Thread(() -> {
            HttpURLConnection con = null;

            try {
                con = (HttpURLConnection)
                        new URL(API_URL).openConnection();

                con.setConnectTimeout(9000);
                con.setReadTimeout(10000);
                con.setRequestMethod("POST");
                con.setDoOutput(true);
                con.setRequestProperty(
                        "Content-Type",
                        "application/json; charset=utf-8"
                );
                con.setRequestProperty(
                        "Accept",
                        "application/json"
                );

                byte[] body =
                        payload.toString()
                                .getBytes(StandardCharsets.UTF_8);

                try (OutputStream os = con.getOutputStream()) {
                    os.write(body);
                }

                int status = con.getResponseCode();

                BufferedReader br =
                        new BufferedReader(
                                new InputStreamReader(
                                        status >= 200 && status < 400
                                                ? con.getInputStream()
                                                : con.getErrorStream(),
                                        StandardCharsets.UTF_8
                                )
                        );

                StringBuilder sb = new StringBuilder();
                String line;

                while ((line = br.readLine()) != null) {
                    sb.append(line);
                }

                br.close();

                JSONObject result =
                        new JSONObject(
                                sb.length() == 0
                                        ? "{}"
                                        : sb.toString()
                        );

                cb.done(result, null);

            } catch (Exception e) {
                cb.done(null, e);

            } finally {
                if (con != null) {
                    con.disconnect();
                }
            }
        }).start();
    }
}
