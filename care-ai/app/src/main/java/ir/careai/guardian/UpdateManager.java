package ir.careai.guardian;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Environment;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public final class UpdateManager {

    private static final String FALLBACK_UPDATE_URL =
            "https://raw.githubusercontent.com/abeytaneh-dotcom/Asghar/care-ai-eye-m2/dist/update.json";

    private UpdateManager() {}

    public static void check(Activity activity, boolean manual) {
        new Thread(() -> {
            JSONObject info = fetchFromAdminServer();

            if (info == null || info.optInt("version_code", 0) <= 0) {
                info = fetchFallback();
            }

            JSONObject finalInfo = info;

            activity.runOnUiThread(() -> {
                if (finalInfo == null) {
                    if (manual) {
                        Toast.makeText(
                                activity,
                                "بررسی بروزرسانی انجام نشد.",
                                Toast.LENGTH_LONG
                        ).show();
                    }
                    return;
                }

                int latest = finalInfo.optInt("version_code", 0);
                String versionName = finalInfo.optString("version_name", "");
                String apkUrl = finalInfo.optString("apk_url", "");
                String notes = finalInfo.optString(
                        "notes",
                        "نسخه جدید Care AI آماده است."
                );

                SharedPreferences p =
                        activity.getSharedPreferences("careai", Context.MODE_PRIVATE);

                int downloaded = p.getInt("downloaded_update_version", 0);

                if (latest > BuildConfig.VERSION_CODE
                        && latest > downloaded
                        && !apkUrl.trim().isEmpty()) {

                    showUpdateDialog(
                            activity,
                            latest,
                            versionName,
                            apkUrl,
                            notes
                    );

                } else if (manual) {
                    Toast.makeText(
                            activity,
                            "آخرین نسخه روی گوشی نصب است.",
                            Toast.LENGTH_SHORT
                    ).show();
                }
            });
        }).start();
    }

    private static JSONObject fetchFromAdminServer() {
        HttpURLConnection con = null;

        try {
            con = (HttpURLConnection) new URL(AuthManager.API_URL).openConnection();
            con.setConnectTimeout(7000);
            con.setReadTimeout(7000);
            con.setRequestMethod("POST");
            con.setDoOutput(true);
            con.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            con.setRequestProperty("Accept", "application/json");

            JSONObject req = new JSONObject();
            req.put("action", "update_check");

            byte[] body = req.toString().getBytes(StandardCharsets.UTF_8);
            try (OutputStream os = con.getOutputStream()) {
                os.write(body);
            }

            int status = con.getResponseCode();
            if (status < 200 || status >= 300) return null;

            BufferedReader br =
                    new BufferedReader(new InputStreamReader(
                            con.getInputStream(),
                            StandardCharsets.UTF_8
                    ));

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) sb.append(line);
            br.close();

            JSONObject result = new JSONObject(sb.toString());
            if (!result.optBoolean("ok", false)) return null;
            return result;

        } catch (Exception e) {
            return null;
        } finally {
            if (con != null) con.disconnect();
        }
    }

    private static JSONObject fetchFallback() {
        HttpURLConnection con = null;

        try {
            con = (HttpURLConnection) new URL(FALLBACK_UPDATE_URL).openConnection();
            con.setConnectTimeout(7000);
            con.setReadTimeout(7000);
            con.setRequestProperty("Cache-Control", "no-cache");

            int code = con.getResponseCode();
            if (code < 200 || code >= 300) return null;

            BufferedReader br =
                    new BufferedReader(new InputStreamReader(
                            con.getInputStream(),
                            StandardCharsets.UTF_8
                    ));

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) sb.append(line);
            br.close();

            return new JSONObject(sb.toString());

        } catch (Exception e) {
            return null;
        } finally {
            if (con != null) con.disconnect();
        }
    }

    private static void showUpdateDialog(
            Activity activity,
            int versionCode,
            String versionName,
            String apkUrl,
            String notes) {

        new AlertDialog.Builder(activity)
                .setTitle("بروزرسانی جدید Care AI")
                .setMessage(
                        (versionName.isEmpty()
                                ? ""
                                : "نسخه " + versionName + "\n\n")
                                + notes
                                + "\n\nاطلاعات بیمار، دستورات و تنظیمات قبلی حفظ می‌شوند."
                )
                .setNegativeButton("بعداً", null)
                .setPositiveButton(
                        "دانلود مستقیم",
                        (d, w) -> startDownload(
                                activity,
                                versionCode,
                                versionName,
                                apkUrl
                        )
                )
                .show();
    }

    private static void startDownload(
            Activity activity,
            int versionCode,
            String versionName,
            String apkUrl) {

        try {
            DownloadManager dm =
                    (DownloadManager) activity.getSystemService(Context.DOWNLOAD_SERVICE);

            String safeVersion =
                    versionName == null || versionName.trim().isEmpty()
                            ? String.valueOf(versionCode)
                            : versionName.replaceAll("[^A-Za-z0-9._-]", "_");

            DownloadManager.Request request =
                    new DownloadManager.Request(Uri.parse(apkUrl))
                            .setTitle("Care AI " + safeVersion)
                            .setDescription("در حال دانلود بروزرسانی")
                            .setMimeType("application/vnd.android.package-archive")
                            .setNotificationVisibility(
                                    DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED
                            )
                            .setDestinationInExternalFilesDir(
                                    activity,
                                    Environment.DIRECTORY_DOWNLOADS,
                                    "CareAI-" + safeVersion + ".apk"
                            );

            long id = dm.enqueue(request);

            activity.getSharedPreferences("careai", Context.MODE_PRIVATE)
                    .edit()
                    .putLong("update_download_id", id)
                    .putInt("update_download_version", versionCode)
                    .apply();

            Toast.makeText(
                    activity,
                    "دانلود بروزرسانی شروع شد.",
                    Toast.LENGTH_LONG
            ).show();

        } catch (Exception e) {
            Toast.makeText(
                    activity,
                    "شروع دانلود ناموفق بود.",
                    Toast.LENGTH_LONG
            ).show();
        }
    }
}
