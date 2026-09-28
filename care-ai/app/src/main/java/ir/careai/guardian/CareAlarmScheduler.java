package ir.careai.guardian;

import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;

import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Calendar;

public final class CareAlarmScheduler {
    private CareAlarmScheduler() {}

    public static void schedule(Context c, CareScheduleStore.Item item) {
        if (item == null || !item.enabled) return;

        long when = nextTrigger(item);
        if (when <= System.currentTimeMillis()) return;

        AlarmManager am = (AlarmManager)c.getSystemService(Context.ALARM_SERVICE);
        if (am == null) return;

        Intent i = new Intent(c, CareAlarmReceiver.class);
        i.putExtra("schedule_id", item.id);

        PendingIntent pi = PendingIntent.getBroadcast(
                c,
                item.id.hashCode(),
                i,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        try {
            if (Build.VERSION.SDK_INT >= 31 && !am.canScheduleExactAlarms()) {
                am.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, when, pi);
            } else {
                am.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, when, pi);
            }
        } catch (Exception e) {
            am.set(AlarmManager.RTC_WAKEUP, when, pi);
        }
    }

    public static void cancel(Context c, String id) {
        AlarmManager am = (AlarmManager)c.getSystemService(Context.ALARM_SERVICE);
        if (am == null) return;
        Intent i = new Intent(c, CareAlarmReceiver.class);
        PendingIntent pi = PendingIntent.getBroadcast(
                c,
                id.hashCode(),
                i,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        am.cancel(pi);
    }

    public static void scheduleAll(Context c) {
        for (CareScheduleStore.Item x : CareScheduleStore.load(c)) {
            if (x.enabled) schedule(c, x);
        }
    }

    private static long nextTrigger(CareScheduleStore.Item x) {
        Calendar now = Calendar.getInstance();
        Calendar target = Calendar.getInstance();
        target.set(Calendar.SECOND, 0);
        target.set(Calendar.MILLISECOND, 0);
        target.set(Calendar.HOUR_OF_DAY, x.hour);
        target.set(Calendar.MINUTE, x.minute);

        if (CareScheduleStore.REPEAT_DAILY.equals(x.repeat)) {
            if (target.getTimeInMillis() <= now.getTimeInMillis()) {
                target.add(Calendar.DAY_OF_YEAR, 1);
            }
            return target.getTimeInMillis();
        }

        if (CareScheduleStore.REPEAT_WEEKLY.equals(x.repeat)) {
            int current = now.get(Calendar.DAY_OF_WEEK);
            int delta = (x.dayOfWeek - current + 7) % 7;
            if (delta == 0 && target.getTimeInMillis() <= now.getTimeInMillis()) delta = 7;
            target.add(Calendar.DAY_OF_YEAR, delta);
            return target.getTimeInMillis();
        }

        try {
            LocalDate d = LocalDate.parse(x.date);
            return d.atTime(x.hour, x.minute)
                    .atZone(ZoneId.systemDefault())
                    .toInstant()
                    .toEpochMilli();
        } catch (Exception e) {
            return -1L;
        }
    }
}
