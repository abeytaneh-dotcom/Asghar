package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.ImageFormat;
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
import android.media.Image;
import android.media.ImageReader;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.speech.tts.TextToSpeech;
import android.util.SparseIntArray;
import android.view.Gravity;
import android.view.Surface;
import android.view.TextureView;
import android.view.View;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.google.mlkit.vision.common.InputImage;
import com.google.mlkit.vision.face.Face;
import com.google.mlkit.vision.face.FaceDetection;
import com.google.mlkit.vision.face.FaceDetector;
import com.google.mlkit.vision.face.FaceDetectorOptions;

import java.util.Arrays;
import java.util.List;
import java.util.Locale;

public class CameraMonitorActivity extends Activity
        implements TextToSpeech.OnInitListener, SensorEventListener {

    private static final SparseIntArray ORIENTATIONS = new SparseIntArray();
    static {
        ORIENTATIONS.append(Surface.ROTATION_0, 0);
        ORIENTATIONS.append(Surface.ROTATION_90, 90);
        ORIENTATIONS.append(Surface.ROTATION_180, 180);
        ORIENTATIONS.append(Surface.ROTATION_270, 270);
    }

    private final Handler handler = new Handler(Looper.getMainLooper());

    private final String[] mainItems = new String[] {
            "صحبت با من",
            "آب می‌خواهم",
            "درد دارم",
            "دستشویی",
            "سردم",
            "گرمم",
            "من خوبم",
            "کمک فوری"
    };

    private final String[] talkItems = new String[] {
            "بله",
            "خیر",
            "آب می‌خواهم",
            "غذا می‌خواهم",
            "درد دارم",
            "سرم درد می‌کند",
            "قفسه سینه‌ام درد می‌کند",
            "دستشویی می‌خواهم",
            "سردم",
            "گرمم",
            "خانواده‌ام را می‌خواهم",
            "بازگشت"
    };

    private TextureView texture;
    private CameraDevice camera;
    private CameraCaptureSession session;
    private ImageReader imageReader;
    private int imageRotation = 0;

    private FaceDetector faceDetector;
    private boolean processingFrame = false;

    private TextToSpeech tts;
    private boolean ttsReady = false;
    private String pendingSpeech = null;

    private TextView faceState;
    private TextView modeState;
    private TextView scanLabel;
    private TextView instruction;

    private boolean talkMode = false;
    private int scanIndex = -1;

    private boolean eyesClosed = false;
    private long blinkStartedAt = 0L;
    private long lastSelectionAt = 0L;
    private long lastFaceSeenAt = 0L;
    private long lastNoFaceSpeechAt = 0L;

    private boolean awaiting = false;
    private long lastMotionPrompt = 0L;

    private SensorManager sensorManager;

    private final Runnable scanner = new Runnable() {
        @Override
        public void run() {
            String[] items = currentItems();
            if (items.length == 0) return;

            scanIndex = (scanIndex + 1) % items.length;
            updateScannerText();

            handler.postDelayed(this, 1800L);
        }
    };

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

        talkMode = getIntent().getBooleanExtra("talk_mode", false);

        initTts();
        initFaceDetector();
        buildUi();
        initMotionSensor();

        handler.post(scanner);
        handler.postDelayed(periodic, 5 * 60 * 1000L);
    }

    private void initTts() {
        tts = new TextToSpeech(this, this);
    }

    private void initFaceDetector() {
        FaceDetectorOptions options =
                new FaceDetectorOptions.Builder()
                        .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
                        .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                        .setMinFaceSize(0.15f)
                        .enableTracking()
                        .build();

        faceDetector = FaceDetection.getClient(options);
    }

    private void buildUi() {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);

        texture = new TextureView(this);
        root.addView(texture, new FrameLayout.LayoutParams(-1, -1));

        faceState = new TextView(this);
        faceState.setText("در حال پیدا کردن چهره...");
        faceState.setTextColor(Color.WHITE);
        faceState.setTextSize(17);
        faceState.setGravity(Gravity.CENTER);
        faceState.setBackgroundColor(0xAA111821);
        faceState.setPadding(dp(10), dp(10), dp(10), dp(10));

        FrameLayout.LayoutParams fp =
                new FrameLayout.LayoutParams(-1, FrameLayout.LayoutParams.WRAP_CONTENT, Gravity.TOP);
        root.addView(faceState, fp);

        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(16), dp(14), dp(16), dp(18));
        panel.setBackgroundColor(0xE6111822);

        modeState = new TextView(this);
        modeState.setTextColor(0xFF9AC8FF);
        modeState.setTextSize(16);
        modeState.setGravity(Gravity.CENTER);
        modeState.setPadding(0, 0, 0, dp(6));

        scanLabel = new TextView(this);
        scanLabel.setTextColor(Color.WHITE);
        scanLabel.setTextSize(30);
        scanLabel.setGravity(Gravity.CENTER);
        scanLabel.setBackgroundColor(0xFF1D5B8F);
        scanLabel.setPadding(dp(12), dp(14), dp(12), dp(14));

        instruction = new TextView(this);
        instruction.setTextColor(0xFFE7EEF7);
        instruction.setTextSize(15);
        instruction.setGravity(Gravity.CENTER);
        instruction.setPadding(dp(6), dp(12), dp(6), 0);
        instruction.setText(
                "گزینه‌ها خودکار عوض می‌شوند. برای انتخاب، هر دو چشم را حدود ۰٫۶ تا ۱٫۶ ثانیه ببندید."
        );

        panel.addView(modeState);
        panel.addView(scanLabel, new LinearLayout.LayoutParams(-1, dp(92)));
        panel.addView(instruction);

        FrameLayout.LayoutParams pp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.BOTTOM
                );

        root.addView(panel, pp);
        setContentView(root);

        updateScannerText();

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
    }

    private void initMotionSensor() {
        sensorManager = (SensorManager) getSystemService(SENSOR_SERVICE);
        Sensor acc = sensorManager.getDefaultSensor(Sensor.TYPE_ACCELEROMETER);

        if (acc != null) {
            sensorManager.registerListener(
                    this,
                    acc,
                    SensorManager.SENSOR_DELAY_NORMAL
            );
        }
    }

    private String[] currentItems() {
        return talkMode ? talkItems : mainItems;
    }

    private void updateScannerText() {
        String[] items = currentItems();

        if (scanIndex < 0 || scanIndex >= items.length) {
            scanIndex = 0;
        }

        modeState.setText(
                talkMode
                        ? "حالت صحبت • انتخاب فقط با چشم"
                        : "Care Mode • انتخاب فقط با چشم"
        );

        scanLabel.setText(items[scanIndex]);
    }

    private void selectCurrent() {
        long now = System.currentTimeMillis();
        if (now - lastSelectionAt < 1200L) return;
        lastSelectionAt = now;

        String[] items = currentItems();
        if (scanIndex < 0 || scanIndex >= items.length) return;

        String selected = items[scanIndex];

        faceState.setText("انتخاب شد: " + selected);

        if ("صحبت با من".equals(selected)) {
            talkMode = true;
            scanIndex = -1;
            speak("حالت صحبت فعال شد. گزینه مورد نظر را با بستن چشم‌ها انتخاب کنید.");
            updateScannerText();
            return;
        }

        if ("بازگشت".equals(selected)) {
            talkMode = false;
            scanIndex = -1;
            speak("به منوی اصلی برگشتیم.");
            updateScannerText();
            return;
        }

        if ("کمک فوری".equals(selected)) {
            speak("درخواست کمک ارسال شد.");
            EmergencyManager.sendEmergency(
                    this,
                    "درخواست کمک با انتخاب چشمی بیمار",
                    true
            );
            return;
        }

        if ("من خوبم".equals(selected)) {
            awaiting = false;
            speak("حالم خوب است.");
            faceState.setText("بیمار با چشم تأیید کرد که حالش خوب است");
            return;
        }

        speak(selected);
    }

    private void startCheckIn(String reason) {
        if (awaiting) return;

        awaiting = true;
        talkMode = false;
        scanIndex = 5;
        updateScannerText();

        faceState.setText(reason + " • منتظر پاسخ چشمی بیمار");
        speak("اگر حالتان خوب است، گزینه من خوبم را با بستن چشم‌ها انتخاب کنید.");

        handler.postDelayed(() -> {
            if (awaiting) {
                awaiting = false;
                faceState.setText("پاسخی دریافت نشد • هشدار ارسال شد");

                EmergencyManager.sendEmergency(
                        this,
                        "عدم پاسخ چشمی بیمار به بررسی Care AI",
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
            CameraCharacteristics selectedCharacteristics = null;

            for (String id : cm.getCameraIdList()) {
                CameraCharacteristics cc = cm.getCameraCharacteristics(id);
                Integer facing = cc.get(CameraCharacteristics.LENS_FACING);

                if (facing != null
                        && facing == CameraCharacteristics.LENS_FACING_FRONT) {
                    selected = id;
                    selectedCharacteristics = cc;
                    break;
                }
            }

            if (selected == null && cm.getCameraIdList().length > 0) {
                selected = cm.getCameraIdList()[0];
                selectedCharacteristics = cm.getCameraCharacteristics(selected);
            }

            if (selected == null || selectedCharacteristics == null) return;

            calculateImageRotation(selectedCharacteristics);

            cm.openCamera(
                    selected,
                    new CameraDevice.StateCallback() {
                        @Override
                        public void onOpened(CameraDevice c) {
                            camera = c;
                            startPreviewAndAnalysis();
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

    private void calculateImageRotation(CameraCharacteristics cc) {
        Integer sensorOrientation =
                cc.get(CameraCharacteristics.SENSOR_ORIENTATION);
        Integer lensFacing =
                cc.get(CameraCharacteristics.LENS_FACING);

        if (sensorOrientation == null) sensorOrientation = 0;

        int deviceRotation = getWindowManager().getDefaultDisplay().getRotation();
        int compensation = ORIENTATIONS.get(deviceRotation);

        if (lensFacing != null
                && lensFacing == CameraCharacteristics.LENS_FACING_FRONT) {
            imageRotation = (sensorOrientation + compensation) % 360;
        } else {
            imageRotation =
                    (sensorOrientation - compensation + 360) % 360;
        }
    }

    private void startPreviewAndAnalysis() {
        try {
            SurfaceTexture st = texture.getSurfaceTexture();
            if (st == null || camera == null) return;

            st.setDefaultBufferSize(640, 480);
            Surface previewSurface = new Surface(st);

            imageReader = ImageReader.newInstance(
                    640,
                    480,
                    ImageFormat.YUV_420_888,
                    2
            );

            imageReader.setOnImageAvailableListener(
                    this::analyzeFrame,
                    handler
            );

            Surface analysisSurface = imageReader.getSurface();

            CaptureRequest.Builder builder =
                    camera.createCaptureRequest(
                            CameraDevice.TEMPLATE_PREVIEW
                    );

            builder.addTarget(previewSurface);
            builder.addTarget(analysisSurface);

            camera.createCaptureSession(
                    Arrays.asList(previewSurface, analysisSurface),
                    new CameraCaptureSession.StateCallback() {
                        @Override
                        public void onConfigured(CameraCaptureSession s) {
                            session = s;

                            try {
                                builder.set(
                                        CaptureRequest.CONTROL_AF_MODE,
                                        CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE
                                );

                                s.setRepeatingRequest(
                                        builder.build(),
                                        null,
                                        handler
                                );
                            } catch (Exception e) {
                                faceState.setText("خطا در شروع تحلیل دوربین");
                            }
                        }

                        @Override
                        public void onConfigureFailed(CameraCaptureSession s) {
                            faceState.setText("تنظیم دوربین ناموفق بود");
                        }
                    },
                    handler
            );

        } catch (Exception e) {
            Toast.makeText(
                    this,
                    "راه‌اندازی پایش چشمی ناموفق بود: " + e.getMessage(),
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private void analyzeFrame(ImageReader reader) {
        Image image = reader.acquireLatestImage();
        if (image == null) return;

        if (processingFrame) {
            image.close();
            return;
        }

        processingFrame = true;

        InputImage input =
                InputImage.fromMediaImage(image, imageRotation);

        faceDetector
                .process(input)
                .addOnSuccessListener(this::processFaces)
                .addOnFailureListener(e ->
                        faceState.setText("تحلیل چهره موقتاً ناموفق بود"))
                .addOnCompleteListener(task -> {
                    processingFrame = false;
                    image.close();
                });
    }

    private void processFaces(List<Face> faces) {
        long now = System.currentTimeMillis();

        if (faces == null || faces.isEmpty()) {
            faceState.setText("چهره دیده نمی‌شود • دوربین را روبه‌روی بیمار قرار دهید");

            if (lastFaceSeenAt > 0
                    && now - lastFaceSeenAt > 12000L
                    && now - lastNoFaceSpeechAt > 30000L) {
                lastNoFaceSpeechAt = now;
                speak("چهره دیده نمی‌شود. لطفاً دوربین را روبه روی بیمار قرار دهید.");
            }

            eyesClosed = false;
            return;
        }

        lastFaceSeenAt = now;

        Face face = faces.get(0);
        Float left = face.getLeftEyeOpenProbability();
        Float right = face.getRightEyeOpenProbability();

        if (left == null || right == null) {
            faceState.setText("چهره پیدا شد • در حال خواندن وضعیت چشم‌ها");
            return;
        }

        boolean closed = left < 0.28f && right < 0.28f;
        boolean open = left > 0.62f && right > 0.62f;

        if (closed && !eyesClosed) {
            eyesClosed = true;
            blinkStartedAt = now;
            faceState.setText("چشم‌ها بسته شد • برای انتخاب کمی نگه دارید");
            return;
        }

        if (eyesClosed && open) {
            long duration = now - blinkStartedAt;
            eyesClosed = false;

            if (duration >= 550L && duration <= 1800L) {
                faceState.setText("پلک ارادی تشخیص داده شد");
                selectCurrent();
            } else if (duration < 550L) {
                faceState.setText("پلک طبیعی • انتخابی انجام نشد");
            } else {
                faceState.setText("چشم‌ها باز شد • دوباره انتخاب کنید");
            }

            return;
        }

        if (!eyesClosed) {
            faceState.setText(
                    "چهره و چشم‌ها فعال • گزینه مناسب را با پلک بلند انتخاب کنید"
            );
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

    private void speak(String text) {
        if (text == null || text.trim().isEmpty()) return;

        if (!ttsReady || tts == null) {
            pendingSpeech = text;
            return;
        }

        tts.speak(
                text,
                TextToSpeech.QUEUE_FLUSH,
                null,
                "careai-eye"
        );
    }

    @Override
    public void onInit(int status) {
        if (status != TextToSpeech.SUCCESS || tts == null) {
            faceStateSafe("موتور صوتی گوشی آماده نیست");
            return;
        }

        int result = tts.setLanguage(new Locale("fa", "IR"));

        if (result == TextToSpeech.LANG_MISSING_DATA
                || result == TextToSpeech.LANG_NOT_SUPPORTED) {
            tts.setLanguage(Locale.getDefault());
        }

        tts.setSpeechRate(0.90f);
        ttsReady = true;

        if (pendingSpeech != null) {
            String p = pendingSpeech;
            pendingSpeech = null;
            speak(p);
        } else {
            speak(
                    talkMode
                            ? "حالت صحبت با چشم فعال شد."
                            : "کنترل چشمی فعال شد. برای انتخاب، چشم‌ها را کمی نگه دارید."
            );
        }
    }

    private void faceStateSafe(String text) {
        if (faceState != null) faceState.setText(text);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);

        if (session != null) session.close();
        if (camera != null) camera.close();
        if (imageReader != null) imageReader.close();

        if (faceDetector != null) {
            faceDetector.close();
        }

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
