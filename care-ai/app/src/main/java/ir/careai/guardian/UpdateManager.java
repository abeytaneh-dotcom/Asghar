package ir.careai.guardian;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Environment;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public final class UpdateManager {
    public static final String UPDATE_URL =
            "https://raw.githubusercontent.com/abeytaneh-dotcom/Asghar/care-ai-eye-m2/dist/update.json";

    private UpdateManager() {}

    public static void check(Activity activity, boolean manual) {
        new Thread(() -> {
            try {
                HttpURLConnection con =
                        (HttpURLConnection) new URL(UPDATE_URL).openConnection();
                con.setConnectTimeout(7000);
                con.setReadTimeout(7000);
                con.setRequestProperty("Cache-Control", "no-cache");

                int code = con.getResponseCode();
                if (code < 200 || code >= 300) {
                    throw new Exception("HTTP " + code);
                }

                BufferedReader br =
                        new BufferedReader(new InputStreamReader(con.getInputStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = br.readLine()) != null) sb.append(line);
                br.close();
                con.disconnect();

                JSONObject o = new JSONObject(sb.toString());
                int latest = o.optInt("version_code", 0);
                String versionName = o.optString("version_name", "");
                String apkUrl = o.optString("apk_url", "");
                String notes = o.optString("notes", "نسخه جدید Care AI آماده است.");

                SharedPreferences p =
                        activity.getSharedPreferences("careai", Context.MODE_PRIVATE);
                int downloaded = p.getInt("downloaded_update_version", 0);

                activity.runOnUiThread(() -> {
                    if (latest > BuildConfig.VERSION_CODE
                            && latest > downloaded
                            && !apkUrl.isEmpty()) {
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

            } catch (Exception e) {
                if (manual) {
                    activity.runOnUiThread(() ->
                            Toast.makeText(
                                    activity,
                                    "بررسی بروزرسانی انجام نشد. اینترنت را بررسی کنید.",
                                    Toast.LENGTH_LONG
                            ).show()
                    );
                }
            }
        }).start();
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
                                + "\n\nبا دانلود بروزرسانی، اطلاعات و دستورات ذخیره‌شده شما حذف نمی‌شوند."
                )
                .setNegativeButton("بعداً", null)
                .setPositiveButton("دانلود مستقیم", (d, w) ->
                        startDownload(activity, versionCode, versionName, apkUrl)
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
