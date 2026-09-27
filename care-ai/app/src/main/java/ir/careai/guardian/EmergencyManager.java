package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.telephony.SmsManager;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.List;

public final class EmergencyManager {
    private EmergencyManager() {}

    public static List<String> trustedNumbers(Context c) {
        SharedPreferences p = c.getSharedPreferences("careai", Context.MODE_PRIVATE);
        List<String> out = new ArrayList<>();
        for (int i = 1; i <= 3; i++) {
            String n = p.getString("trusted" + i, "").trim();
            if (!n.isEmpty()) out.add(n);
        }
        return out;
    }

    public static boolean notifyTrusted(Activity a, String message) {
        List<String> nums = trustedNumbers(a);
        if (nums.isEmpty()) {
            Toast.makeText(a, "شماره اضطراری ثبت نشده است", Toast.LENGTH_LONG).show();
            return false;
        }

        if (a.checkSelfPermission(Manifest.permission.SEND_SMS)
                != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(a, "مجوز پیامک داده نشده است", Toast.LENGTH_LONG).show();
            return false;
        }

        String patient = a.getSharedPreferences("careai", Context.MODE_PRIVATE)
                .getString("patient_name", "بیمار");

        String msg = patient + ": " + message;

        try {
            SmsManager sms = SmsManager.getDefault();
            for (String n : nums) {
                sms.sendTextMessage(n, null, msg, null, null);
            }
            return true;
        } catch (Exception e) {
            Toast.makeText(a, "ارسال پیامک ناموفق بود", Toast.LENGTH_LONG).show();
            return false;
        }
    }

    public static void sendEmergency(Activity a, String reason, boolean callPrimary) {
        List<String> nums = trustedNumbers(a);
        if (nums.isEmpty()) {
            Toast.makeText(a, "شماره اضطراری ثبت نشده است", Toast.LENGTH_LONG).show();
            return;
        }

        String patient = a.getSharedPreferences("careai", Context.MODE_PRIVATE)
                .getString("patient_name", "بیمار");
        String msg = "هشدار Care AI: وضعیت " + patient + " نیاز به بررسی دارد. "
                + "علت: " + reason + ". لطفاً سریعاً وضعیت بیمار را بررسی کنید.";

        if (a.checkSelfPermission(Manifest.permission.SEND_SMS) == PackageManager.PERMISSION_GRANTED) {
            try {
                SmsManager sms = SmsManager.getDefault();
                for (String n : nums) sms.sendTextMessage(n, null, msg, null, null);
                Toast.makeText(a, "هشدار برای شماره‌های مجاز ارسال شد", Toast.LENGTH_LONG).show();
            } catch (Exception e) {
                Toast.makeText(a, "ارسال پیامک ناموفق بود: " + e.getMessage(), Toast.LENGTH_LONG).show();
            }
        } else {
            Toast.makeText(a, "مجوز پیامک داده نشده است", Toast.LENGTH_LONG).show();
        }

        if (callPrimary) {
            String n = nums.get(0);
            Intent i;
            if (a.checkSelfPermission(Manifest.permission.CALL_PHONE) == PackageManager.PERMISSION_GRANTED) {
                i = new Intent(Intent.ACTION_CALL, Uri.parse("tel:" + Uri.encode(n)));
            } else {
                i = new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + Uri.encode(n)));
            }
            try {
                a.startActivity(i);
            } catch (Exception ignored) {}
        }
    }
}
