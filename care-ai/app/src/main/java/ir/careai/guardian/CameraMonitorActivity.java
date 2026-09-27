package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.SurfaceTexture;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.hardware.camera2.CameraCaptureSession;
import android.hardware.camera2.CameraCharacteristics;
import android.hardware.camera2.CameraDevice;
import android.hardware.camera2.CameraManager;
import android.hardware.camera2.CaptureRequest;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.speech.tts.TextToSpeech;
import android.view.Gravity;
import android.view.Surface;
import android.view.TextureView;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import java.util.Arrays;
import java.util.Locale;

public class CameraMonitorActivity extends Activity
        implements TextToSpeech.OnInitListener, SensorEventListener {

    private TextureView texture;
    private CameraDevice camera;
    private CameraCaptureSession session;
    private final Handler handler = new Handler(Looper.getMainLooper());

    private TextToSpeech tts;
    private TextView status;
    private boolean awaiting = false;
    private long lastMotionPrompt = 0L;

    private SensorManager sensorManager;

    private final Runnable periodic = new Runnable() {
        @Override
        public void run() {
            startCheckIn("بررسی دوره‌ای");
            handler.postDelayed(this, 5 * 60 * 1000L);
        }
    };

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);

        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        tts = new TextToSpeech(this, this);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);

        texture = new TextureView(this);
        root.addView(texture, new FrameLayout.LayoutParams(-1, -1));

        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(14), dp(14), dp(14), dp(18));
        panel.setBackgroundColor(0xCC101722);

        status = new TextView(this);
        status.setText("Guardian فعال • دوربین جلو");
        status.setTextColor(Color.WHITE);
        status.setTextSize(19);
        status.setGravity(Gravity.CENTER);
        status.setPadding(0, 0, 0, dp(8));

        Button confirm = new Button(this);
        confirm.setText("من خوبم");
        confirm.setTextSize(20);
        confirm.setMinHeight(dp(58));

        Button sos = new Button(this);
        sos.setText("SOS — کمک فوری");
        sos.setTextSize(20);

        Button close = new Button(this);
        close.setText("پایان مراقبت");

        panel.addView(status);
        panel.addView(confirm, new LinearLayout.LayoutParams(-1, dp(60)));
        panel.addView(sos, new LinearLayout.LayoutParams(-1, dp(60)));
        panel.addView(close, new LinearLayout.LayoutParams(-1, dp(54)));

        FrameLayout.LayoutParams pp =
                new FrameLayout.LayoutParams(-1, FrameLayout.LayoutParams.WRAP_CONTENT, Gravity.BOTTOM);
        root.addView(panel, pp);

        setContentView(root);

        confirm.setOnClickListener(v -> {
            awaiting = false;
            status.setText("پاسخ بیمار ثبت شد • وضعیت تأیید شد");
            speak("پاسخ شما ثبت شد.");
        });

        sos.setOnClickListener(v ->
                EmergencyManager.sendEmergency(
                        this,
                        "درخواست مستقیم بیمار در Care Mode",
                        true));

        close.setOnClickListener(v -> finish());

        texture.setSurfaceTextureListener(new TextureView.SurfaceTextureListener() {
            @Override
            public void onSurfaceTextureAvailable(SurfaceTexture s, int w, int h) {
                openFrontCamera();
            }

            @Override
            public void onSurfaceTextureSizeChanged(SurfaceTexture s, int w, int h) {}

            @Override
            public boolean onSurfaceTextureDestroyed(SurfaceTexture s) {
                return true;
            }

            @Override
            public void onSurfaceTextureUpdated(SurfaceTexture s) {}
        });

        sensorManager = (SensorManager) getSystemService(SENSOR_SERVICE);
        Sensor acc = sensorManager.getDefaultSensor(Sensor.TYPE_ACCELEROMETER);
        if (acc != null) {
            sensorManager.registerListener(
                    this,
                    acc,
                    SensorManager.SENSOR_DELAY_NORMAL
            );
        }

        handler.postDelayed(periodic, 5 * 60 * 1000L);
    }

    private void startCheckIn(String reason) {
        if (awaiting) return;

        awaiting = true;
        status.setText(reason + " • لطفاً تا ۶۰ ثانیه «من خوبم» را بزنید");
        speak("لطفاً اگر حالتان خوب است گزینه من خوبم را انتخاب کنید.");

        handler.postDelayed(() -> {
            if (awaiting) {
                awaiting = false;
                status.setText("پاسخی دریافت نشد • هشدار ارسال شد");

                EmergencyManager.sendEmergency(
                        this,
                        "عدم پاسخ بیمار به بررسی Care AI",
                        true
                );
            }
        }, 60_000L);
    }

    private void openFrontCamera() {
        if (checkSelfPermission(Manifest.permission.CAMERA)
                != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(
                    this,
                    "مجوز دوربین لازم است",
                    Toast.LENGTH_LONG
            ).show();
            finish();
            return;
        }

        try {
            CameraManager cm =
                    (CameraManager) getSystemService(CAMERA_SERVICE);

            String selected = null;

            for (String id : cm.getCameraIdList()) {
                Integer facing =
                        cm.getCameraCharacteristics(id)
                                .get(CameraCharacteristics.LENS_FACING);

                if (facing != null
                        && facing == CameraCharacteristics.LENS_FACING_FRONT) {
                    selected = id;
                    break;
                }
            }

            if (selected == null && cm.getCameraIdList().length > 0) {
                selected = cm.getCameraIdList()[0];
            }

            if (selected == null) return;

            cm.openCamera(
                    selected,
                    new CameraDevice.StateCallback() {
                        @Override
                        public void onOpened(CameraDevice c) {
                            camera = c;
                            startPreview();
                        }

                        @Override
                        public void onDisconnected(CameraDevice c) {
                            c.close();
                        }

                        @Override
                        public void onError(CameraDevice c, int e) {
                            c.close();
                        }
                    },
                    handler
            );

        } catch (Exception e) {
            Toast.makeText(
                    this,
                    "خطای دوربین: " + e.getMessage(),
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private void startPreview() {
        try {
            SurfaceTexture st = texture.getSurfaceTexture();
            if (st == null || camera == null) return;

            st.setDefaultBufferSize(1280, 720);

            Surface surface = new Surface(st);

            CaptureRequest.Builder b =
                    camera.createCaptureRequest(CameraDevice.TEMPLATE_PREVIEW);

            b.addTarget(surface);

            camera.createCaptureSession(
                    Arrays.asList(surface),
                    new CameraCaptureSession.StateCallback() {
                        @Override
                        public void onConfigured(CameraCaptureSession s) {
                            session = s;

                            try {
                                b.set(
                                        CaptureRequest.CONTROL_AF_MODE,
                                        CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE
                                );

                                s.setRepeatingRequest(
                                        b.build(),
                                        null,
                                        handler
                                );

                            } catch (Exception ignored) {}
                        }

                        @Override
                        public void onConfigureFailed(CameraCaptureSession s) {}
                    },
                    handler
            );

        } catch (Exception e) {
            Toast.makeText(
                    this,
                    "راه‌اندازی پیش‌نمایش ناموفق بود",
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    @Override
    public void onSensorChanged(SensorEvent e) {
        float x = e.values[0];
        float y = e.values[1];
        float z = e.values[2];

        double m = Math.sqrt(x * x + y * y + z * z);
        long now = System.currentTimeMillis();

        if (m > 28.0 && now - lastMotionPrompt > 10_000L) {
            lastMotionPrompt = now;
            startCheckIn("حرکت شدید گوشی یا بیمار تشخیص داده شد");
        }
    }

    @Override
    public void onAccuracyChanged(Sensor s, int a) {}

    private void speak(String s) {
        if (tts != null) {
            tts.speak(
                    s,
                    TextToSpeech.QUEUE_FLUSH,
                    null,
                    "guardian"
            );
        }
    }

    @Override
    public void onInit(int st) {
        if (st == TextToSpeech.SUCCESS) {
            tts.setLanguage(new Locale("fa", "IR"));
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);

        if (session != null) session.close();
        if (camera != null) camera.close();

        if (sensorManager != null) {
            sensorManager.unregisterListener(this);
        }

        if (tts != null) {
            tts.stop();
            tts.shutdown();
        }

        super.onDestroy();
    }

    private int dp(int v) {
        return (int) (
                v * getResources().getDisplayMetrics().density + 0.5f
        );
    }
}
