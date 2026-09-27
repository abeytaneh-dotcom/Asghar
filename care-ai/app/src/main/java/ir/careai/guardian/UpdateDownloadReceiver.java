package ir.careai.guardian;

import android.app.DownloadManager;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;

public class UpdateDownloadReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (!DownloadManager.ACTION_DOWNLOAD_COMPLETE.equals(intent.getAction())) {
            return;
        }

        long completedId =
                intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1L);

        SharedPreferences p =
                context.getSharedPreferences("careai", Context.MODE_PRIVATE);

        long expectedId = p.getLong("update_download_id", -2L);
        if (completedId != expectedId) return;

        DownloadManager dm =
                (DownloadManager) context.getSystemService(Context.DOWNLOAD_SERVICE);

        DownloadManager.Query query =
                new DownloadManager.Query().setFilterById(completedId);

        try (android.database.Cursor cursor = dm.query(query)) {
            if (cursor == null || !cursor.moveToFirst()) return;

            int status = cursor.getInt(
                    cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS)
            );

            if (status != DownloadManager.STATUS_SUCCESSFUL) return;
        }

        int version = p.getInt("update_download_version", 0);
        p.edit()
                .putInt("downloaded_update_version", version)
                .apply();

        try {
            Uri uri = dm.getUriForDownloadedFile(completedId);
            if (uri == null) return;

            Intent install = new Intent(Intent.ACTION_VIEW);
            install.setDataAndType(
                    uri,
                    "application/vnd.android.package-archive"
            );
            install.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            install.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            context.startActivity(install);
        } catch (Exception ignored) {}
    }
}
