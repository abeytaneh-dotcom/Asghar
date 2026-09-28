package ir.careai.guardian;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import android.telephony.SmsManager;

public class CareAlarmReceiver extends BroadcastReceiver {
    private static final String CHANNEL = "care_schedule";

    @Override
    public void onReceive(Context c, Intent intent) {
        String id = intent.getStringExtra("schedule_id");
        if (id == null) return;

        CareScheduleStore.Item x = CareScheduleStore.find(c, id);
        if (x == null || !x.enabled) return;

        String kind = CareScheduleStore.KIND_MEDICINE.equals(x.kind)
                ? "یادآوری دارو"
                : "یادآوری مراقبت";

        String body = x.title;
        if (x.detail != null && !x.detail.trim().isEmpty()) {
            body += " — " + x.detail.trim();
        }

        showNotification(c, kind, body);

        if (x.sendSms
                && c.checkSelfPermission(Manifest.permission.SEND_SMS)
                == PackageManager.PERMISSION_GRANTED) {
            SharedPreferences p = c.getSharedPreferences("careai", Context.MODE_PRIVATE);
            String number = p.getString("trusted" + x.nurseSlot, "").trim();
            String patient = p.getString("patient_name", "بیمار");
            if (!number.isEmpty()) {
                try {
                    SmsManager.getDefault().sendTextMessage(
                            number,
                            null,
                            "Care AI - " + patient + "\n" + kind + ": " + body,
                            null,
                            null
                    );
                } catch (Exception ignored) {}
            }
        }

        if (CareScheduleStore.REPEAT_ONCE.equals(x.repeat)) {
            x.enabled = false;
            CareScheduleStore.upsert(c, x);
        } else {
            CareAlarmScheduler.schedule(c, x);
        }
    }

    private void showNotification(Context c, String title, String body) {
        NotificationManager nm =
                (NotificationManager)c.getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm == null) return;

        if (Build.VERSION.SDK_INT >= 26) {
            nm.createNotificationChannel(new NotificationChannel(
                    CHANNEL,
                    "یادآوری‌های Care AI",
                    NotificationManager.IMPORTANCE_HIGH
            ));
        }

        Intent open = new Intent(c, MainActivity.class);
        PendingIntent pi = PendingIntent.getActivity(
                c,
                0,
                open,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        android.app.Notification.Builder b =
                Build.VERSION.SDK_INT >= 26
                        ? new android.app.Notification.Builder(c, CHANNEL)
                        : new android.app.Notification.Builder(c);

        b.setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new android.app.Notification.BigTextStyle().bigText(body))
                .setAutoCancel(true)
                .setContentIntent(pi)
                .setPriority(android.app.Notification.PRIORITY_HIGH);

        nm.notify((title + body).hashCode(), b.build());
    }
}
