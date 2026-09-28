package ir.careai.guardian;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public final class VideoCallManager {

    public interface Callback {
        void done(JSONObject result, Exception error);
    }

    private VideoCallManager() {}

    public static void checkIncoming(Context c, Callback cb) {
        JSONObject p = new JSONObject();
        try {
            p.put("action","video_incoming");
            p.put("token",AuthManager.token(c));
            p.put("device_id",AuthManager.deviceId(c));
        } catch(Exception ignored) {}
        post(p,cb);
    }

    public static String caregiverLink(Context c) {
        SharedPreferences p=c.getSharedPreferences("careai",Context.MODE_PRIVATE);
        String patientId=AuthManager.patientId(c);
        String key=p.getString("video_key","");
        if(patientId.isEmpty()||key.isEmpty()) return "";
        return "https://achinu.ir/careai/video.php?role=caller&patient_id="
                + Uri.encode(patientId)
                + "&key=" + Uri.encode(key);
    }

    public static void shareCaregiverLink(Activity a, int slot) {
        String link=caregiverLink(a);
        if(link.isEmpty()){
            android.widget.Toast.makeText(
                    a,
                    "لینک تماس هنوز از سرور دریافت نشده است.",
                    android.widget.Toast.LENGTH_LONG
            ).show();
            return;
        }

        SharedPreferences p=a.getSharedPreferences("careai",Context.MODE_PRIVATE);
        String number=p.getString("trusted"+slot,"").trim();
        String patient=p.getString("patient_name","بیمار");

        Intent i=new Intent(Intent.ACTION_SENDTO);
        i.setData(Uri.parse("smsto:"+Uri.encode(number)));
        i.putExtra("sms_body",
                "لینک تماس تصویری Care AI برای "+patient+":\n"+link
                        +"\nبا باز کردن این لینک، در صورت فعال بودن پاسخ خودکار، تماس روی گوشی بیمار برقرار می‌شود.");
        a.startActivity(i);
    }

    private static void post(JSONObject payload, Callback cb) {
        new Thread(()->{
            HttpURLConnection con=null;
            try{
                con=(HttpURLConnection)new URL(AuthManager.API_URL).openConnection();
                con.setConnectTimeout(7000);
                con.setReadTimeout(8000);
                con.setRequestMethod("POST");
                con.setDoOutput(true);
                con.setRequestProperty("Content-Type","application/json; charset=utf-8");
                byte[] body=payload.toString().getBytes(StandardCharsets.UTF_8);
                try(OutputStream os=con.getOutputStream()){ os.write(body); }

                int status=con.getResponseCode();
                BufferedReader br=new BufferedReader(new InputStreamReader(
                        status>=200&&status<400?con.getInputStream():con.getErrorStream(),
                        StandardCharsets.UTF_8
                ));
                StringBuilder sb=new StringBuilder();
                String line;
                while((line=br.readLine())!=null)sb.append(line);
                br.close();

                cb.done(new JSONObject(sb.length()==0?"{}":sb.toString()),null);
            }catch(Exception e){
                cb.done(null,e);
            }finally{
                if(con!=null)con.disconnect();
            }
        }).start();
    }
}
