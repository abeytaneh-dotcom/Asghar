package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.view.View;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

public class VideoCallActivity extends Activity {

    private WebView web;
    private String room;
    private String role;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        room=getIntent().getStringExtra("room");
        role=getIntent().getStringExtra("role");
        if(role==null)role="patient";

        if(checkSelfPermission(Manifest.permission.CAMERA)!=PackageManager.PERMISSION_GRANTED
                || checkSelfPermission(Manifest.permission.RECORD_AUDIO)!=PackageManager.PERMISSION_GRANTED){
            requestPermissions(
                    new String[]{Manifest.permission.CAMERA,Manifest.permission.RECORD_AUDIO},
                    801
            );
        }else{
            startWebCall();
        }
    }

    private void startWebCall() {
        web=new WebView(this);
        setContentView(web);

        WebSettings s=web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);

        web.setWebViewClient(new WebViewClient());
        web.setWebChromeClient(new WebChromeClient(){
            @Override
            public void onPermissionRequest(PermissionRequest request){
                runOnUiThread(()->{
                    String origin=request.getOrigin()==null?"":request.getOrigin().toString();
                    if(origin.startsWith("https://achinu.ir/")){
                        request.grant(request.getResources());
                    }else{
                        request.deny();
                    }
                });
            }
        });

        String token=AuthManager.token(this);
        String url="https://achinu.ir/careai/video.php?role="
                +android.net.Uri.encode(role)
                +"&room="+android.net.Uri.encode(room==null?"":room)
                +"&token="+android.net.Uri.encode(token);

        web.loadUrl(url);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode,String[] permissions,int[] grantResults){
        super.onRequestPermissionsResult(requestCode,permissions,grantResults);
        if(requestCode==801){
            boolean ok=checkSelfPermission(Manifest.permission.CAMERA)==PackageManager.PERMISSION_GRANTED
                    &&checkSelfPermission(Manifest.permission.RECORD_AUDIO)==PackageManager.PERMISSION_GRANTED;
            if(ok)startWebCall();
            else{
                Toast.makeText(this,"برای تماس تصویری مجوز دوربین و میکروفن لازم است",Toast.LENGTH_LONG).show();
                finish();
            }
        }
    }

    @Override
    protected void onDestroy(){
        if(web!=null){
            try{web.loadUrl("about:blank");}catch(Exception ignored){}
            try{web.destroy();}catch(Exception ignored){}
        }
        super.onDestroy();
    }
}
