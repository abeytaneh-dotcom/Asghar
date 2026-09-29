package ir.careai.guardian;

import android.Manifest;
import android.content.Context;
import android.content.pm.PackageManager;
import android.telephony.SmsManager;

import java.util.ArrayList;

public final class EyeSmsSender {

    private EyeSmsSender() {}

    public static boolean canSend(Context context) {
        return context.checkSelfPermission(Manifest.permission.SEND_SMS)
                == PackageManager.PERMISSION_GRANTED;
    }

    public static boolean send(Context context, String number, String text) {
        if (!canSend(context)) return false;
        if (number == null || number.trim().isEmpty()) return false;
        if (text == null || text.trim().isEmpty()) return false;

        try {
            SmsManager manager = SmsManager.getDefault();
            ArrayList<String> parts = manager.divideMessage(text);

            if (parts.size() <= 1) {
                manager.sendTextMessage(
                        number.trim(),
                        null,
                        text,
                        null,
                        null
                );
            } else {
                manager.sendMultipartTextMessage(
                        number.trim(),
                        null,
                        parts,
                        null,
                        null
                );
            }

            return true;
        } catch (Exception e) {
            return false;
        }
    }
}
