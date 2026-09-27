from pathlib import Path
import sys, re

root=Path(sys.argv[1])
p=root/'app/src/main/java/ir/khanehremap/diag/MainActivity.java'
s=p.read_text()

# Imports for native DownloadManager + progress UI.
s=s.replace('import android.app.AlertDialog;','import android.app.AlertDialog;\nimport android.app.DownloadManager;')
s=s.replace('import android.content.pm.PackageManager;','import android.content.pm.PackageManager;\nimport android.database.Cursor;')
s=s.replace('import android.widget.Toast;','import android.widget.Toast;\nimport android.widget.ProgressBar;\nimport android.widget.LinearLayout;\nimport android.widget.TextView;')

# Replace native update checker with a clean in-app report and direct download button.
rx=r'    private void checkUpdate\(String token\)\{.*?\n    \}\n\n    private void downloadAndInstall\(String url,String sha,String ver\)throws Exception\{.*?\n    \}\n\n    private void installApk'
new=r'''    private void checkUpdate(String token){
        new Thread(()->{
            try{
                URL u=new URL(APP_URL+"api/app_manifest.php");
                HttpURLConnection c=(HttpURLConnection)u.openConnection();
                c.setConnectTimeout(8000);
                c.setReadTimeout(10000);
                c.setRequestMethod("POST");
                c.setDoOutput(true);
                c.setRequestProperty("Content-Type","application/json");
                if(token!=null&&!token.isEmpty()) c.setRequestProperty("Authorization","Bearer "+token);
                try(OutputStream os=c.getOutputStream()){os.write("{}".getBytes());}
                if(c.getResponseCode()!=200) return;

                String body=readAll(c.getInputStream());
                JSONObject j=new JSONObject(body);
                if(!j.optBoolean("ok",false)) return;

                final String ver=j.optString("version","").replaceFirst("^[vV]","");
                final String dlUrl=j.optString("url","");
                final String sha=j.optString("sha256","");
                final String notes=j.optString("notes","").trim();
                if(ver.isEmpty()||dlUrl.isEmpty()) return;

                String current=currentVersionName();
                if(compareVersion(ver,current)<=0) return;

                runOnUiThread(()->{
                    String message=notes.isEmpty()
                        ? ("نسخه "+ver+" آماده نصب است.")
                        : ("نسخه "+ver+"\n\n"+notes);
                    new AlertDialog.Builder(MainActivity.this)
                        .setTitle("آپدیت جدید")
                        .setMessage(message)
                        .setPositiveButton("دانلود و نصب",(d,w)->startNativeDownload(dlUrl,sha,ver))
                        .setNegativeButton("بعداً",null)
                        .show();
                });
            }catch(Exception ignored){
                // Never expose host, URL, SSL or internal server errors to the user.
            }
        }).start();
    }

    private void startNativeDownload(String url,String sha,String ver){
        try{
            DownloadManager dm=(DownloadManager)getSystemService(DOWNLOAD_SERVICE);
            if(dm==null){showUpdateError("دریافت بروزرسانی انجام نشد.");return;}

            String fileName="KhanehRemap-"+ver+".apk";
            File dir=getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
            if(dir==null){showUpdateError("فایل بروزرسانی آماده نیست.");return;}
            File apk=new File(dir,fileName);
            if(apk.exists()) apk.delete();

            DownloadManager.Request req=new DownloadManager.Request(Uri.parse(url));
            req.setTitle("بروزرسانی خانه ریمپ");
            req.setDescription("در حال دریافت نسخه "+ver);
            req.setAllowedOverMetered(true);
            req.setAllowedOverRoaming(false);
            req.setNotificationVisibility(DownloadManager.Request.VISIBILITY_HIDDEN);
            req.setDestinationInExternalFilesDir(this,Environment.DIRECTORY_DOWNLOADS,fileName);

            final long id=dm.enqueue(req);
            showDownloadProgress(dm,id,apk,sha,ver);
        }catch(Exception e){
            showUpdateError("دریافت بروزرسانی انجام نشد.");
        }
    }

    private void showDownloadProgress(DownloadManager dm,long id,File apk,String sha,String ver){
        LinearLayout box=new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        int pad=(int)(24*getResources().getDisplayMetrics().density);
        box.setPadding(pad,pad,pad,pad);

        TextView status=new TextView(this);
        status.setText("در حال دانلود... 0%");
        status.setTextSize(18);

        ProgressBar bar=new ProgressBar(this,null,android.R.attr.progressBarStyleHorizontal);
        bar.setMax(100);
        bar.setProgress(0);

        box.addView(status,new LinearLayout.LayoutParams(-1,-2));
        LinearLayout.LayoutParams bp=new LinearLayout.LayoutParams(-1,-2);
        bp.topMargin=(int)(16*getResources().getDisplayMetrics().density);
        box.addView(bar,bp);

        final AlertDialog dlg=new AlertDialog.Builder(this)
            .setTitle("بروزرسانی")
            .setView(box)
            .setNegativeButton("لغو",(d,w)->{
                try{dm.remove(id);}catch(Exception ignored){}
            })
            .setCancelable(false)
            .create();
        dlg.show();

        final Runnable poll=new Runnable(){
            @Override public void run(){
                Cursor cur=null;
                try{
                    DownloadManager.Query q=new DownloadManager.Query().setFilterById(id);
                    cur=dm.query(q);
                    if(cur==null||!cur.moveToFirst()){
                        if(dlg.isShowing()) dlg.dismiss();
                        showUpdateError("دریافت بروزرسانی انجام نشد.");
                        return;
                    }

                    int st=cur.getInt(cur.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS));
                    long done=cur.getLong(cur.getColumnIndexOrThrow(DownloadManager.COLUMN_BYTES_DOWNLOADED_SO_FAR));
                    long total=cur.getLong(cur.getColumnIndexOrThrow(DownloadManager.COLUMN_TOTAL_SIZE_BYTES));
                    int pct=(total>0)?(int)Math.min(100,(done*100L)/total):0;
                    bar.setProgress(pct);
                    status.setText("در حال دانلود... "+pct+"%");

                    if(st==DownloadManager.STATUS_SUCCESSFUL){
                        if(dlg.isShowing()) dlg.dismiss();
                        if(!apk.exists()||apk.length()<10000){
                            showUpdateError("فایل بروزرسانی آماده نیست.");
                            return;
                        }
                        new Thread(()->{
                            try{
                                if(sha!=null&&!sha.isEmpty()){
                                    String got=sha256File(apk);
                                    if(!sha.equalsIgnoreCase(got)){
                                        apk.delete();
                                        showUpdateError("فایل بروزرسانی معتبر نیست.");
                                        return;
                                    }
                                }
                                runOnUiThread(()->installApk(apk));
                            }catch(Exception e){
                                showUpdateError("آماده‌سازی نصب انجام نشد.");
                            }
                        }).start();
                        return;
                    }

                    if(st==DownloadManager.STATUS_FAILED){
                        if(dlg.isShowing()) dlg.dismiss();
                        showUpdateError("دریافت بروزرسانی انجام نشد.");
                        return;
                    }

                    handler.postDelayed(this,350);
                }catch(Exception e){
                    if(dlg.isShowing()) dlg.dismiss();
                    showUpdateError("دریافت بروزرسانی انجام نشد.");
                }finally{
                    if(cur!=null) cur.close();
                }
            }
        };
        handler.post(poll);
    }

    private String sha256File(File f)throws Exception{
        MessageDigest md=MessageDigest.getInstance("SHA-256");
        try(InputStream in=new FileInputStream(f)){
            byte[] b=new byte[32768];int n;
            while((n=in.read(b))>0) md.update(b,0,n);
        }
        return hex(md.digest());
    }

    private void showUpdateError(String message){
        runOnUiThread(()->new AlertDialog.Builder(MainActivity.this)
            .setTitle("بروزرسانی")
            .setMessage(message)
            .setPositiveButton("باشه",null)
            .show());
    }

    private void installApk'''
s2=re.sub(rx,new,s,flags=re.S)
if s2==s:
    raise SystemExit('updater patch point not found')
s=s2
p.write_text(s)

# v3.0.10 metadata.
p=root/'app/build.gradle'
b=p.read_text()
b=re.sub(r'versionCode\s+\d+','versionCode 310',b)
b=re.sub(r"versionName\s+'[^']+'","versionName '3.0.10'",b)
p.write_text(b)
