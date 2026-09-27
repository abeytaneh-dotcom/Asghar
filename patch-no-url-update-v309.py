from pathlib import Path
import sys, re

root=Path(sys.argv[1])
p=root/'app/src/main/java/ir/khanehremap/diag/MainActivity.java'
s=p.read_text()

# Native JS dialogs with no origin/URL ever shown.
old='        web.setWebChromeClient(new WebChromeClient());'
new=r'''        web.setWebChromeClient(new WebChromeClient(){
            @Override public boolean onJsAlert(WebView view,String url,String message,JsResult result){
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("خانه ریمپ")
                    .setMessage(message==null?"":message)
                    .setPositiveButton("باشه",(d,w)->result.confirm())
                    .setOnCancelListener(d->result.cancel())
                    .show();
                return true;
            }
            @Override public boolean onJsConfirm(WebView view,String url,String message,JsResult result){
                final boolean update = message!=null && (message.contains("نسخه جدید") || message.contains("آپدیت"));
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle(update?"آپدیت برنامه":"خانه ریمپ")
                    .setMessage(message==null?"":message)
                    .setPositiveButton(update?"دانلود و نصب":"تأیید",(d,w)->result.confirm())
                    .setNegativeButton(update?"بعداً":"لغو",(d,w)->result.cancel())
                    .setOnCancelListener(d->result.cancel())
                    .show();
                return true;
            }
            @Override public boolean onJsPrompt(WebView view,String url,String message,String defaultValue,JsPromptResult result){
                final EditText input=new EditText(MainActivity.this);
                input.setSingleLine(true);
                input.setText(defaultValue==null?"":defaultValue);
                input.setSelectAllOnFocus(true);
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("خانه ریمپ")
                    .setMessage(message==null?"":message)
                    .setView(input)
                    .setPositiveButton("تأیید",(d,w)->result.confirm(input.getText().toString()))
                    .setNegativeButton("لغو",(d,w)->result.cancel())
                    .setOnCancelListener(d->result.cancel())
                    .show();
                return true;
            }
        });'''
if old not in s:
    raise SystemExit('WebChromeClient patch point not found')
s=s.replace(old,new)

# Ensure EditText import.
s=s.replace('import android.widget.Toast;','import android.widget.Toast;\nimport android.widget.EditText;')

# Compare server update against the actually installed APK version, not a hardcoded baseline.
old_cmp='                if(compareVersion(ver,"3.0.0")<=0){toastUi("برنامه به‌روز است");return;}'
new_cmp='                String current=currentVersionName(); if(compareVersion(ver,current)<=0){toastUi("برنامه به‌روز است");return;}'
if old_cmp not in s:
    raise SystemExit('version compare patch point not found')
s=s.replace(old_cmp,new_cmp)

marker='    private static int compareVersion(String a,String b){'
helper='''    private String currentVersionName(){
        try{
            android.content.pm.PackageInfo p=getPackageManager().getPackageInfo(getPackageName(),0);
            return p.versionName==null?"0.0.0":p.versionName;
        }catch(Exception e){return "0.0.0";}
    }

'''
if helper.strip() not in s:
    s=s.replace(marker,helper+marker)

p.write_text(s)

# v3.0.9 metadata
p=root/'app/build.gradle'
b=p.read_text()
b=re.sub(r'versionCode\s+\d+','versionCode 309',b)
b=re.sub(r"versionName\s+'[^']+'","versionName '3.0.9'",b)
p.write_text(b)
