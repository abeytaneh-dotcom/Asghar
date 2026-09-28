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
            String patientId,
            Callback cb) {

        JSONObject p = new JSONObject();
        try {
            p.put("action", "request_otp");
            p.put("phone", phone);
            p.put("patient_id", patientId);
            p.put("device_id", deviceId(c));
        } catch (Exception ignored) {}
        post(p, cb);
    }

    public static void verifyOtp(
            Context c,
            String phone,
            String patientId,
            String code,
            Callback cb) {

        JSONObject p = new JSONObject();
        try {
            p.put("action", "verify_otp");
            p.put("phone", phone);
            p.put("patient_id", patientId);
            p.put("device_id", deviceId(c));
            p.put("code", code);
        } catch (Exception ignored) {}

        post(p, (result, error) -> {
            if (error == null && result != null && result.optBoolean("ok")) {
                SharedPreferences.Editor e = prefs(c).edit()
                        .putString("auth_token", result.optString("token", ""))
                        .putString("auth_phone", phone)
                        .putString("auth_patient_id", result.optString("patient_id", patientId))
                        .putString("video_key", result.optString("video_key", ""))
                        .putBoolean("auth_active_cache", result.optBoolean("active", false))
                        .putLong("auth_checked_at", System.currentTimeMillis());
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
                        .putBoolean("auth_active_cache", result.optBoolean("active", false))
                        .putString("auth_patient_id", result.optString("patient_id", patientId(c)))
                        .putString("video_key", result.optString("video_key", prefs(c).getString("video_key","")))
                        .putLong("auth_checked_at", System.currentTimeMillis())
                        .apply();
            }
            cb.done(result, error);
        });
    }

    public static boolean cachedActive(Context c) {
        return prefs(c).getBoolean("auth_active_cache", false);
    }

    public static void clear(Context c) {
        prefs(c).edit()
                .remove("auth_token")
                .remove("auth_phone")
                .remove("auth_patient_id")
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
