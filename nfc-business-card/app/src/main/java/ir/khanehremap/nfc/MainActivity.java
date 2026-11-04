package ir.khanehremap.nfc;
import android.app.*;
import android.os.*;
import android.content.*;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.nfc.*;
import android.nfc.cardemulation.*;
import android.net.Uri;
import android.provider.Settings;
import android.view.*;
import android.widget.*;
import java.util.*;
import java.nio.charset.StandardCharsets;

public class MainActivity extends Activity {
 static final String SITE="https://htaelec.ir/";
 static final String CONTACT="BEGIN:VCARD\r\nVERSION:3.0\r\nFN:اصغر بیتانه - خانه ریمپ\r\nN:بیتانه;اصغر;;;\r\nORG:خانه ریمپ\r\nTITLE:مدیر مجموعه\r\nURL:https://htaelec.ir/\r\nADR:;;اصفهان;;;;ایران\r\nNOTE:مرکز تخصصی تعمیر ECU، ریمپ، دیاگ، برق و الکترونیک خودرو، آموزش و تجهیزات تخصصی.\r\nEND:VCARD\r\n";
 TextView info;
 boolean modeUrl(){return getSharedPreferences("card",0).getBoolean("url",false);}
 void setMode(boolean b){getSharedPreferences("card",0).edit().putBoolean("url",b).apply();refresh();}
 void refresh(){
   if(info==null)return;
   NfcAdapter n=NfcAdapter.getDefaultAdapter(this);
   String state=n==null?"NFC موجود نیست":!n.isEnabled()?"NFC خاموش است (برای تنظیمات لمس کنید)":"NFC روشن — گوشی آماده است";
   info.setText(state+"\n"+(modeUrl()?"حالت: ارسال لینک وب‌سایت":"حالت: ارسال فایل مخاطب vCard"));
 }
 TextView text(String value,int size,int color){TextView v=new TextView(this);v.setText(value);v.setTextSize(size);v.setTextColor(color);v.setPadding(12,14,12,14);return v;}
 Button button(String title,Runnable onClick){
   Button v=new Button(this);v.setText(title);v.setTextColor(Color.WHITE);
   v.setAllCaps(false);
   GradientDrawable bg=new GradientDrawable();bg.setColor(Color.rgb(196,32,46));bg.setCornerRadius(22);v.setBackground(bg);
   v.setOnClickListener(x->onClick.run());return v;
 }
 public void onCreate(Bundle b){
   super.onCreate(b); getWindow().setStatusBarColor(Color.BLACK);
   ScrollView sc=new ScrollView(this);sc.setBackgroundColor(Color.rgb(19,20,24));
   LinearLayout l=new LinearLayout(this);l.setOrientation(1);l.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);l.setPadding(30,35,30,35);
   l.addView(text("خانه ریمپ",34,Color.WHITE));
   l.addView(text("اصغر بیتانه  •  مدیر مجموعه",20,Color.WHITE));
   l.addView(text("تعمیر ECU، ریمپ، دیاگ، برق و الکترونیک خودرو، آموزش و تجهیزات تخصصی",17,Color.LTGRAY));
   l.addView(text("🌐  htaelec.ir      📍 اصفهان",17,Color.WHITE));
   info=text("",16,Color.rgb(95,225,145));l.addView(info);
   info.setOnClickListener(v->{NfcAdapter n=NfcAdapter.getDefaultAdapter(this);if(n!=null&&!n.isEnabled())startActivity(new Intent(Settings.ACTION_NFC_SETTINGS));});
   l.addView(button("۱ — ارسال کارت مخاطب از طریق NFC",()->setMode(false)));
   l.addView(button("۲ — ارسال لینک سایت از طریق NFC",()->setMode(true)));
   l.addView(button("اشتراک‌گذاری کارت از طریق پیام‌رسان",()->{
     Intent i=new Intent(Intent.ACTION_SEND);i.setType("text/plain");i.putExtra(Intent.EXTRA_TEXT,
       "خانه ریمپ — اصغر بیتانه\n"+SITE+"\n"+CONTACT);
     startActivity(Intent.createChooser(i,"ارسال کارت ویزیت"));
   }));
   l.addView(text("تست: NFC هر دو گوشی روشن، هر دو قفل باز، پشت گوشی‌ها را نزدیک کنید. بازشدن خودکار کارت در گوشی مقصد تضمین نیست؛ برای آزمایش می‌توان از برنامه NFC Reader استفاده کرد.",15,Color.LTGRAY));
   sc.addView(l);setContentView(sc);refresh();
 }
 protected void onResume(){super.onResume();NfcAdapter n=NfcAdapter.getDefaultAdapter(this);
   if(n!=null&&n.isEnabled()){try{CardEmulation.getInstance(n).setPreferredService(this,new ComponentName(this,TagService.class));}catch(Exception ignored){}}
   refresh();
 }
 protected void onPause(){NfcAdapter n=NfcAdapter.getDefaultAdapter(this);
   if(n!=null){try{CardEmulation.getInstance(n).unsetPreferredService(this);}catch(Exception ignored){}}
   super.onPause();
 }
 public static class TagService extends HostApduService {
   static final byte[] OK={(byte)0x90,0x00}, FAIL={(byte)0x6A,(byte)0x82},BAD={(byte)0x6D,0};
   static final byte[] AID={(byte)0xD2,0x76,0,0,(byte)0x85,1,1};
   static final byte[] CC={0,15,0x20,0,(byte)0xF6,0,(byte)0xF6,4,6,(byte)0xE1,4,0x10,0,0,(byte)0xFF};
   int selected=0;
   public byte[] processCommandApdu(byte[] a,Bundle extras){
     if(a==null||a.length<5)return BAD;
     if((a[0]&255)!=0)return BAD;
     int ins=a[1]&255;
     if(ins==0xA4){
       int p1=a[2]&255, lc=a[4]&255;
       if(a.length<5+lc)return FAIL;
       if(p1==4&&lc==7){byte[] found=Arrays.copyOfRange(a,5,12);if(Arrays.equals(found,AID)){selected=0;return OK;}}
       if((p1==0||p1==2)&&lc==2&&(a[5]&255)==0xE1){
         if((a[6]&255)==3){selected=1;return OK;}
         if((a[6]&255)==4){selected=2;return OK;}
       }
       return FAIL;
     }
     if(ins==0xB0){
       if(selected==0)return FAIL;
       byte[] data=CC;
       if(selected==2){
         boolean url=getSharedPreferences("card",0).getBoolean("url",false);
         NdefRecord rec=url?NdefRecord.createUri(SITE):NdefRecord.createMime("text/x-vcard",CONTACT.getBytes(StandardCharsets.UTF_8));
         byte[] ndef=new NdefMessage(rec).toByteArray();
         data=new byte[ndef.length+2];data[0]=(byte)(ndef.length>>8);data[1]=(byte)(ndef.length&255);
         System.arraycopy(ndef,0,data,2,ndef.length);
       }
       int offset=((a[2]&255)<<8)|(a[3]&255),len=a[4]&255;
       if(len==0)len=256;
       if(offset>data.length)return FAIL;
       byte[] out=Arrays.copyOfRange(data,offset,Math.min(data.length,offset+len));
       byte[] resp=Arrays.copyOf(out,out.length+2);resp[out.length]=(byte)0x90;resp[out.length+1]=0;
       return resp;
     }
     return BAD;
   }
   public void onDeactivated(int reason){selected=0;}
 }
}
