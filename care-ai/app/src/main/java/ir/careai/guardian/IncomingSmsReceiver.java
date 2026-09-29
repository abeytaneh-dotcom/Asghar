package ir.careai.guardian;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.provider.Telephony;
import android.telephony.SmsMessage;

public class IncomingSmsReceiver extends BroadcastReceiver {

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null
                || !Telephony.Sms.Intents.SMS_RECEIVED_ACTION.equals(intent.getAction())) {
            return;
        }

        SmsMessage[] messages = Telephony.Sms.Intents.getMessagesFromIntent(intent);
        if (messages == null || messages.length == 0) return;

        String number = messages[0].getDisplayOriginatingAddress();
        StringBuilder body = new StringBuilder();

        for (SmsMessage message : messages) {
            if (message != null && message.getMessageBody() != null) {
                body.append(message.getMessageBody());
            }
        }

        if (number == null) number = "";

        SharedPreferences p = context.getSharedPreferences(
                "careai",
                Context.MODE_PRIVATE
        );

        p.edit()
                .putBoolean("incoming_sms_pending", true)
                .putString("incoming_sms_number", number)
                .putString("incoming_sms_body", body.toString())
                .putLong("incoming_sms_at", System.currentTimeMillis())
                .apply();
    }
}
