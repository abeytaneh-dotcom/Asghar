from pathlib import Path
import sys, re

root=Path(sys.argv[1])
p=root/'app/src/main/java/ir/khanehremap/diag/MainActivity.java'
s=p.read_text()

s=s.replace('import android.content.*;','import android.content.*;\nimport android.media.AudioAttributes;\nimport android.media.AudioManager;\nimport android.media.MediaPlayer;\nimport android.media.ToneGenerator;')

old='        @JavascriptInterface public void disconnect(){ runOnUiThread(()->{if(gatt!=null){try{gatt.disconnect();gatt.close();}catch(Exception ignored){}gatt=null;chars.clear();}); }'
new='''        @JavascriptInterface public void disconnect(){
            runOnUiThread(()->{
                if(gatt!=null){
                    try{gatt.disconnect();gatt.close();}catch(Exception ignored){}
                    gatt=null;
                }
                chars.clear();
            });
        }'''
if old in s: s=s.replace(old,new)

s=s.replace('private static final String APP_URL = "https://achinu.ir/diag/";','private static final String APP_URL = "https://achinu.ir/amper/";')
s=s.replace('private static final String APP_ORIGIN = "https://achinu.ir";\n','')
s=s.replace('        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);','        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);\n        s.setMediaPlaybackRequiresUserGesture(false);')
s=s.replace('if(url.startsWith(APP_URL) && !u.getPath().contains("/manage-")) return false;','if(url.startsWith(APP_URL) && !u.getPath().contains("/panel-")) return false;')
s=s.replace('                injectNativeUpdateHook();\n','')
s=re.sub(r'\n    private void injectNativeUpdateHook\(\)\{.*?\n    \}\n','\n',s,flags=re.S)

if 'private MediaPlayer alertPlayer;' not in s:
    s=s.replace('    private SharedPreferences prefs;','    private SharedPreferences prefs;\n    private MediaPlayer alertPlayer;\n    private static final String ALERT_AUDIO_URL = "https://achinu.ir/amper/assets/cooler_warning.wav";')

if 'prefetchAlertAudio();' not in s:
    s=s.replace('        ensureBluetoothPermissions();','        ensureBluetoothPermissions();\n        prefetchAlertAudio();')

s=s.replace('@JavascriptInterface public void checkForAppUpdate(String token){checkUpdate(token);}',
'''@JavascriptInterface public void checkForAppUpdate(String token){checkUpdate(token);}
        @JavascriptInterface public void playCoolantWarning(){playNativeCoolantWarning();}
        @JavascriptInterface public void stopCoolantWarning(){stopNativeCoolantWarning();}
        @JavascriptInterface public void testCoolantWarning(){playNativeCoolantWarning();}''')

helpers=r'''    private File alertAudioFile(){ return new File(getFilesDir(),"cooler_warning.wav"); }

    private boolean downloadAlertAudio(){
        try{
            File dst=alertAudioFile();
            if(dst.exists() && dst.length()>10000) return true;
            File tmp=new File(getFilesDir(),"cooler_warning.tmp");
            HttpURLConnection c=(HttpURLConnection)new URL(ALERT_AUDIO_URL+"?v=3").openConnection();
            c.setConnectTimeout(8000);
            c.setReadTimeout(15000);
            c.setUseCaches(false);
            c.connect();
            if(c.getResponseCode()!=200) return false;
            try(InputStream in=c.getInputStream(); FileOutputStream out=new FileOutputStream(tmp)){
                byte[] b=new byte[16384];
                int n;
                long total=0;
                while((n=in.read(b))>0){ out.write(b,0,n); total+=n; }
                if(total<10000){ tmp.delete(); return false; }
            }
            if(dst.exists()) dst.delete();
            return tmp.renameTo(dst);
        }catch(Exception e){ return false; }
    }

    private void prefetchAlertAudio(){
        new Thread(()->downloadAlertAudio()).start();
    }

    private void stopNativeCoolantWarning(){
        runOnUiThread(()->{
            try{
                if(alertPlayer!=null){
                    try{ if(alertPlayer.isPlaying()) alertPlayer.stop(); }catch(Exception ignored){}
                    try{ alertPlayer.release(); }catch(Exception ignored){}
                    alertPlayer=null;
                }
            }catch(Exception ignored){}
        });
    }

    private void playNativeFromFile(){
        runOnUiThread(()->{
            try{
                if(alertPlayer!=null){
                    try{ alertPlayer.release(); }catch(Exception ignored){}
                    alertPlayer=null;
                }
                File f=alertAudioFile();
                if(!f.exists() || f.length()<10000){ nativeFallbackTone(); return; }
                MediaPlayer mp=new MediaPlayer();
                mp.setAudioAttributes(new AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_ALARM)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build());
                mp.setDataSource(f.getAbsolutePath());
                mp.setVolume(1.0f,1.0f);
                mp.setLooping(false);
                mp.setOnPreparedListener(MediaPlayer::start);
                mp.setOnCompletionListener(x->{
                    try{ x.release(); }catch(Exception ignored){}
                    if(alertPlayer==x) alertPlayer=null;
                });
                mp.setOnErrorListener((x,what,extra)->{
                    try{ x.release(); }catch(Exception ignored){}
                    if(alertPlayer==x) alertPlayer=null;
                    nativeFallbackTone();
                    return true;
                });
                alertPlayer=mp;
                mp.prepareAsync();
            }catch(Exception e){ nativeFallbackTone(); }
        });
    }

    private void playNativeCoolantWarning(){
        File f=alertAudioFile();
        if(f.exists() && f.length()>10000){ playNativeFromFile(); return; }
        new Thread(()->{
            boolean ok=downloadAlertAudio();
            if(ok) playNativeFromFile();
            else runOnUiThread(this::nativeFallbackTone);
        }).start();
    }

    private void nativeFallbackTone(){
        try{
            final ToneGenerator tg=new ToneGenerator(AudioManager.STREAM_ALARM,100);
            tg.startTone(ToneGenerator.TONE_CDMA_ALERT_CALL_GUARD,900);
            handler.postDelayed(()->{ try{tg.release();}catch(Exception ignored){} },1000);
        }catch(Exception ignored){}
    }

'''
marker='    private void checkUpdate(String token){'
if 'private void playNativeCoolantWarning()' not in s:
    s=s.replace(marker,helpers+marker)

p.write_text(s)

p=root/'app/build.gradle'
s=p.read_text()
s=re.sub(r"applicationId\s+'[^']+'","applicationId 'com.krdiag.kilometer.n202609252354'",s)
s=re.sub(r"minSdk\s+\d+","minSdk 23",s)
s=re.sub(r"targetSdk\s+\d+","targetSdk 34",s)
s=re.sub(r"versionCode\s+\d+","versionCode 308",s)
s=re.sub(r"versionName\s+'[^']+'","versionName '3.0.8'",s)
s=s.replace('minifyEnabled true','minifyEnabled false').replace('shrinkResources true','shrinkResources false')
p.write_text(s)

p=root/'app/src/main/AndroidManifest.xml'
s=p.read_text()
if 'android.permission.VIBRATE' not in s:
    s=s.replace('<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>','<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>\n    <uses-permission android:name="android.permission.VIBRATE"/>')
if 'android:icon=' not in s:
    s=s.replace('android:allowBackup="false"','android:allowBackup="false"\n        android:icon="@drawable/kr_launcher"\n        android:roundIcon="@drawable/kr_launcher"')
else:
    s=re.sub(r'android:icon="[^"]+"','android:icon="@drawable/kr_launcher"',s)
    if 'android:roundIcon=' in s: s=re.sub(r'android:roundIcon="[^"]+"','android:roundIcon="@drawable/kr_launcher"',s)
s=s.replace('android:label="خانه ریمپ"','android:label="@string/app_name"')
p.write_text(s)

(root/'app/src/main/res/values/strings.xml').write_text('<resources>\n  <string name="app_name">خانه ریمپ کیلومتر</string>\n</resources>\n')
