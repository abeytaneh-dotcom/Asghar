package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
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
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.google.mediapipe.framework.image.BitmapImageBuilder;
import com.google.mediapipe.framework.image.MPImage;
import com.google.mediapipe.tasks.components.containers.NormalizedLandmark;
import com.google.mediapipe.tasks.core.BaseOptions;
import com.google.mediapipe.tasks.vision.core.RunningMode;
import com.google.mediapipe.tasks.vision.facelandmarker.FaceLandmarker;
import com.google.mediapipe.tasks.vision.facelandmarker.FaceLandmarkerResult;

import java.util.Arrays;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class CameraMonitorActivity extends Activity
        implements TextToSpeech.OnInitListener, SensorEventListener {

    private static final long FRAME_INTERVAL_MS = 250L;
    private static final long PROMPT_DURATION_MS = 20000L;
    private static final long READING_LOCK_MS = 4000L;
    private static final long RIGHT_GAZE_HOLD_MS = 1000L;
    private static final long BLINK_MIN_MS = 700L;
    private static final long BLINK_MAX_MS = 3000L;

    private static final int ACTION_SPEAK = 0;
    private static final int ACTION_TALK_MODE = 1;
    private static final int ACTION_EMERGENCY = 2;
    private static final int ACTION_BACK = 3;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private final ExecutorService visionExecutor = Executors.newSingleThreadExecutor();

    private final Prompt[] mainPrompts = new Prompt[] {
            new Prompt("می‌خواهی صحبت کنی؟", "", ACTION_TALK_MODE),
            new Prompt("آب می‌خواهی؟", "آب می‌خواهم", ACTION_SPEAK),
            new Prompt("درد داری؟", "درد دارم", ACTION_SPEAK),
            new Prompt("دستشویی می‌خواهی؟", "دستشویی می‌خواهم", ACTION_SPEAK),
            new Prompt("سردت است؟", "سردم است", ACTION_SPEAK),
            new Prompt("گرمت است؟", "گرمم است", ACTION_SPEAK),
            new Prompt("کسی از خانواده را می‌خواهی؟", "خانواده‌ام را می‌خواهم", ACTION_SPEAK),
            new Prompt("کمک فوری می‌خواهی؟", "", ACTION_EMERGENCY)
    };

    private final Prompt[] talkPrompts = new Prompt[] {
            new Prompt("بله؟", "بله", ACTION_SPEAK),
            new Prompt("خیر؟", "خیر", ACTION_SPEAK),
            new Prompt("آب می‌خواهی؟", "آب می‌خواهم", ACTION_SPEAK),
            new Prompt("غذا می‌خواهی؟", "غذا می‌خواهم", ACTION_SPEAK),
            new Prompt("درد داری؟", "درد دارم", ACTION_SPEAK),
            new Prompt("سرت درد می‌کند؟", "سرم درد می‌کند", ACTION_SPEAK),
            new Prompt("قفسه سینه‌ات درد می‌کند؟", "قفسه سینه‌ام درد می‌کند", ACTION_SPEAK),
            new Prompt("دستشویی می‌خواهی؟", "دستشویی می‌خواهم", ACTION_SPEAK),
            new Prompt("سردت است؟", "سردم است", ACTION_SPEAK),
            new Prompt("گرمت است؟", "گرمم است", ACTION_SPEAK),
            new Prompt("خانواده‌ات را می‌خواهی؟", "خانواده‌ام را می‌خواهم", ACTION_SPEAK),
            new Prompt("برگردیم به منوی اصلی؟", "", ACTION_BACK)
    };

    private TextureView texture;
    private CameraDevice camera;
    private CameraCaptureSession session;

    private FaceLandmarker faceLandmarker;
    private boolean processingFrame = false;

    private TextToSpeech tts;
    private boolean ttsReady = false;
    private String pendingSpeech = null;

    private TextView faceState;
    private TextView modeState;
    private TextView promptText;
    private TextView instruction;

    private boolean talkMode = false;
    private int promptIndex = 0;
    private long promptShownAt = 0L;
    private long promptDeadline = 0L;

    private int calibrationStage = 0;
    private long calibrationStageStartedAt = 0L;
    private double centerGazeSum = 0.0;
    private int centerGazeCount = 0;
    private double rightGazeSum = 0.0;
    private int rightGazeCount = 0;
    private float neutralGaze = 0.5f;
    private int rightDirectionSign = 1;
    private float rightThreshold = 0.04f;
    private int calibrationRetry = 0;

    private float openEarBaseline = 0.24f;
    private boolean eyesClosed = false;
    private boolean blinkActionFired = false;
    private long blinkStartedAt = 0L;
    private long rightGazeStartedAt = 0L;
    private long lastActionAt = 0L;
    private long lastFaceSeenAt = 0L;

    private SensorManager sensorManager;
    private long lastMotionPrompt = 0L;

    private final Runnable frameLoop = new Runnable() {
        @Override
        public void run() {
            analyzeTextureFrame();
            handler.postDelayed(this, FRAME_INTERVAL_MS);
        }
    };

    private final Runnable promptTimeout = new Runnable() {
        @Override
        public void run() {
            if (calibrationStage == 2) {
                faceState.setText("پاسخی ثبت نشد • می‌رویم سراغ سؤال بعدی");
                advancePrompt(900L);
            }
        }
    };

    private final Runnable countdown = new Runnable() {
        @Override
        public void run() {
            if (calibrationStage != 2 || promptDeadline <= 0L) return;

            long left = Math.max(0L, promptDeadline - System.currentTimeMillis());
            long seconds = (left + 999L) / 1000L;

            instruction.setText(
                    "چشم‌ها را ۰٫۷ ثانیه ببند = انتخاب  •  نگاه راست = رد  •  زمان باقی‌مانده: "
                            + seconds + " ثانیه"
            );

            if (left > 0L) {
                handler.postDelayed(this, 1000L);
            }
        }
    };

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);

        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        talkMode = getIntent().getBooleanExtra("talk_mode", false);

        initTts();
        buildUi();
        initMotionSensor();
        initFaceLandmarker();

        handler.postDelayed(frameLoop, 500L);
    }

    private void initFaceLandmarker() {
        try {
            BaseOptions baseOptions =
                    BaseOptions.builder()
                            .setModelAssetPath("face_landmarker.task")
                            .build();

            FaceLandmarker.FaceLandmarkerOptions options =
                    FaceLandmarker.FaceLandmarkerOptions.builder()
                            .setBaseOptions(baseOptions)
                            .setMinFaceDetectionConfidence(0.5f)
                            .setMinFacePresenceConfidence(0.5f)
                            .setMinTrackingConfidence(0.5f)
                            .setNumFaces(1)
                            .setRunningMode(RunningMode.IMAGE)
                            .build();

            faceLandmarker =
                    FaceLandmarker.createFromOptions(this, options);

            startCalibration();

        } catch (Exception e) {
            faceStateSafe("راه‌اندازی تشخیص دقیق چشم ناموفق بود");
            Toast.makeText(
                    this,
                    "مدل تشخیص چشم اجرا نشد: " + e.getMessage(),
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private void initTts() {
        tts = new TextToSpeech(this, this);
    }

    private void buildUi() {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);

        texture = new TextureView(this);
        root.addView(texture, new FrameLayout.LayoutParams(-1, -1));

        faceState = new TextView(this);
        faceState.setText("در حال آماده‌سازی تشخیص چشم...");
        faceState.setTextColor(Color.WHITE);
        faceState.setTextSize(16);
        faceState.setGravity(Gravity.CENTER);
        faceState.setBackgroundColor(0xAA101820);
        faceState.setPadding(dp(10), dp(10), dp(10), dp(10));

        FrameLayout.LayoutParams fp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.BOTTOM
                );
        root.addView(faceState, fp);

        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(16), dp(14), dp(16), dp(18));
        panel.setBackgroundColor(0xEE101820);

        modeState = new TextView(this);
        modeState.setTextColor(0xFF9CCBFF);
        modeState.setTextSize(16);
        modeState.setGravity(Gravity.CENTER);
        modeState.setPadding(0, 0, 0, dp(8));

        promptText = new TextView(this);
        promptText.setTextColor(Color.WHITE);
        promptText.setTextSize(32);
        promptText.setGravity(Gravity.CENTER);
        promptText.setBackgroundColor(0xFF1A5688);
        promptText.setPadding(dp(12), dp(18), dp(12), dp(18));

        instruction = new TextView(this);
        instruction.setTextColor(0xFFE9F0F7);
        instruction.setTextSize(16);
        instruction.setGravity(Gravity.CENTER);
        instruction.setPadding(dp(6), dp(12), dp(6), 0);

        panel.addView(modeState);
        panel.addView(
                promptText,
                new LinearLayout.LayoutParams(-1, dp(115))
        );
        panel.addView(instruction);

        FrameLayout.LayoutParams pp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.TOP
                );
        root.addView(panel, pp);

        setContentView(root);

        texture.setSurfaceTextureListener(
                new TextureView.SurfaceTextureListener() {
                    @Override
                    public void onSurfaceTextureAvailable(
                            SurfaceTexture s,
                            int w,
                            int h) {
                        openFrontCamera();
                    }

                    @Override
                    public void onSurfaceTextureSizeChanged(
                            SurfaceTexture s,
                            int w,
                            int h) {}

                    @Override
                    public boolean onSurfaceTextureDestroyed(
                            SurfaceTexture s) {
                        return true;
                    }

                    @Override
                    public void onSurfaceTextureUpdated(
                            SurfaceTexture s) {}
                }
        );
    }

    private void startCalibration() {
        calibrationStage = 0;
        calibrationStageStartedAt = System.currentTimeMillis();
        centerGazeSum = 0.0;
        centerGazeCount = 0;
        rightGazeSum = 0.0;
        rightGazeCount = 0;
        eyesClosed = false;

        modeState.setText("کالیبراسیون چشم — مرحله ۱ از ۲");
        promptText.setText("مستقیم به صفحه نگاه کن");
        instruction.setText(
                "فقط راحت و مستقیم نگاه کن. حدود ۶ ثانیه زمان داری."
        );

        speak("لطفاً چند ثانیه مستقیم به صفحه نگاه کنید.");
    }

    private void beginRightCalibration() {
        calibrationStage = 1;
        calibrationStageStartedAt = System.currentTimeMillis();
        rightGazeSum = 0.0;
        rightGazeCount = 0;

        modeState.setText("کالیبراسیون چشم — مرحله ۲ از ۲");
        promptText.setText("فقط چشم‌ها را به سمت راست ببر");
        instruction.setText(
                "سر را تا جای ممکن ثابت نگه دار و فقط چشم‌ها را به راست حرکت بده. حدود ۷ ثانیه."
        );

        speak("حالا فقط چشم‌ها را به سمت راست ببرید و چند ثانیه نگه دارید.");
    }

    private void finishCalibration() {
        if (centerGazeCount < 4 || rightGazeCount < 4) {
            retryCalibration("نمونه کافی از چشم‌ها گرفته نشد");
            return;
        }

        float center = (float) (centerGazeSum / centerGazeCount);
        float right = (float) (rightGazeSum / rightGazeCount);
        float diff = right - center;

        if (Math.abs(diff) < 0.018f && calibrationRetry < 1) {
            calibrationRetry++;
            retryCalibration("حرکت نگاه راست واضح نبود، یک بار دیگر");
            return;
        }

        neutralGaze = center;
        rightDirectionSign = diff >= 0f ? 1 : -1;
        rightThreshold =
                Math.max(
                        0.018f,
                        Math.min(0.075f, Math.abs(diff) * 0.45f)
                );

        calibrationStage = 2;
        modeState.setText(
                talkMode
                        ? "حالت صحبت با چشم"
                        : "Care Mode — کنترل فقط با چشم"
        );

        faceState.setText("کالیبراسیون انجام شد • کنترل چشمی فعال است");

        speak(
                "کالیبراسیون انجام شد. هر سؤال مدتی روی صفحه می‌ماند. "
                        + "بستن چشم‌ها یعنی بله و انتخاب. نگاه به راست یعنی خیر و رفتن به سؤال بعدی."
        );

        handler.postDelayed(() -> showPrompt(0), 2500L);
    }

    private void retryCalibration(String reason) {
        faceState.setText(reason);
        calibrationStage = 0;
        calibrationStageStartedAt = System.currentTimeMillis();
        centerGazeSum = 0.0;
        centerGazeCount = 0;
        rightGazeSum = 0.0;
        rightGazeCount = 0;

        modeState.setText("کالیبراسیون دوباره");
        promptText.setText("مستقیم به صفحه نگاه کن");
        instruction.setText("چند ثانیه فقط مستقیم نگاه کن.");

        speak(reason + ". لطفاً دوباره مستقیم به صفحه نگاه کنید.");
    }

    private void showPrompt(int index) {
        if (calibrationStage != 2) return;

        Prompt[] prompts = currentPrompts();
        if (prompts.length == 0) return;

        promptIndex = (index + prompts.length) % prompts.length;

        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        promptShownAt = System.currentTimeMillis();
        promptDeadline = promptShownAt + PROMPT_DURATION_MS;
        rightGazeStartedAt = 0L;
        if (!eyesClosed) {
            blinkActionFired = false;
        }
        promptText.setBackgroundColor(0xFF1A5688);

        Prompt p = prompts[promptIndex];

        modeState.setText(
                talkMode
                        ? "حالت صحبت • ۲۰ ثانیه برای تصمیم"
                        : "Care Mode • ۲۰ ثانیه برای تصمیم"
        );

        promptText.setText(p.question);
        faceState.setText("در حال انتظار برای تصمیم بیمار");

        speak(p.question);

        handler.postDelayed(promptTimeout, PROMPT_DURATION_MS);
        handler.post(countdown);
    }

    private Prompt[] currentPrompts() {
        return talkMode ? talkPrompts : mainPrompts;
    }

    private void advancePrompt(long delayMs) {
        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        int next = promptIndex + 1;

        handler.postDelayed(
                () -> showPrompt(next),
                delayMs
        );
    }

    private void rejectCurrentByGaze() {
        long now = System.currentTimeMillis();
        if (now - lastActionAt < 1200L) return;
        lastActionAt = now;

        faceState.setText("نگاه راست تشخیص داده شد • گزینه رد شد");
        speak("باشه. سؤال بعدی.");
        advancePrompt(850L);
    }

    private void confirmCurrentByBlink() {
        long now = System.currentTimeMillis();
        if (now - lastActionAt < 1200L) return;
        lastActionAt = now;

        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        Prompt[] prompts = currentPrompts();
        if (promptIndex < 0 || promptIndex >= prompts.length) return;

        Prompt p = prompts[promptIndex];
        faceState.setText("✓ انتخاب شد: " + p.question);
        promptText.setBackgroundColor(0xFF1E7A46);

        if (p.action == ACTION_TALK_MODE) {
            talkMode = true;
            modeState.setText("حالت صحبت با چشم");
            promptText.setText("✓ حالت صحبت فعال شد");
            speak("حالت صحبت فعال شد.");
            handler.postDelayed(() -> showPrompt(0), 3500L);
            return;
        }

        if (p.action == ACTION_BACK) {
            talkMode = false;
            modeState.setText("Care Mode");
            promptText.setText("✓ بازگشت به منوی اصلی");
            speak("به منوی اصلی برگشتیم.");
            handler.postDelayed(() -> showPrompt(0), 3500L);
            return;
        }

        if (p.action == ACTION_EMERGENCY) {
            promptText.setBackgroundColor(0xFF9B1C31);
            promptText.setText("✓ درخواست کمک فوری ارسال شد");
            speak("درخواست کمک فوری ارسال شد.");
            EmergencyManager.sendEmergency(
                    this,
                    "درخواست کمک با تأیید چشمی بیمار",
                    true
            );
            handler.postDelayed(
                    () -> showPrompt(promptIndex + 1),
                    4500L
            );
            return;
        }

        promptText.setText("✓ " + p.output);
        speak(p.output);
        handler.postDelayed(
                () -> showPrompt(promptIndex + 1),
                4500L
        );
    }

    private void analyzeTextureFrame() {
        if (faceLandmarker == null
                || processingFrame
                || texture == null
                || !texture.isAvailable()) {
            return;
        }

        Bitmap bitmap = texture.getBitmap(360, 480);
        if (bitmap == null) return;

        processingFrame = true;

        visionExecutor.execute(() -> {
            FaceLandmarkerResult result = null;
            Exception error = null;

            try {
                MPImage image =
                        new BitmapImageBuilder(bitmap).build();
                result = faceLandmarker.detect(image);
            } catch (Exception e) {
                error = e;
            }

            FaceLandmarkerResult finalResult = result;
            Exception finalError = error;

            handler.post(() -> {
                try {
                    if (finalError != null) {
                        faceStateSafe("تحلیل چشم موقتاً ناموفق بود");
                    } else {
                        processFaceResult(finalResult);
                    }
                } finally {
                    processingFrame = false;
                    if (!bitmap.isRecycled()) {
                        bitmap.recycle();
                    }
                }
            });
        });
    }

    private void processFaceResult(FaceLandmarkerResult result) {
        long now = System.currentTimeMillis();

        if (result == null
                || result.faceLandmarks() == null
                || result.faceLandmarks().isEmpty()) {
            faceState.setText(
                    "چهره دیده نمی‌شود • گوشی را روبه‌روی صورت بیمار قرار بده"
            );
            eyesClosed = false;
            rightGazeStartedAt = 0L;
            return;
        }

        lastFaceSeenAt = now;

        List<NormalizedLandmark> lm =
                result.faceLandmarks().get(0);

        if (lm == null || lm.size() < 478) {
            faceState.setText("نقاط چشم کامل دریافت نشد");
            return;
        }

        float ear = eyeAspectRatio(lm);
        float gaze = gazeRatio(lm);

        if (Float.isNaN(ear) || Float.isNaN(gaze)) {
            return;
        }

        if (ear > openEarBaseline * 0.70f) {
            openEarBaseline =
                    openEarBaseline * 0.97f + ear * 0.03f;
        }

        boolean closed =
                ear < Math.max(0.035f, openEarBaseline * 0.48f);
        boolean open =
                ear > Math.max(0.060f, openEarBaseline * 0.70f);

        if (calibrationStage == 0) {
            if (open) {
                centerGazeSum += gaze;
                centerGazeCount++;
            }

            long elapsed = now - calibrationStageStartedAt;
            long left = Math.max(0L, 6000L - elapsed);

            instruction.setText(
                    "مستقیم نگاه کن • "
                            + ((left + 999L) / 1000L)
                            + " ثانیه"
            );

            if (elapsed >= 6000L) {
                beginRightCalibration();
            }

            return;
        }

        if (calibrationStage == 1) {
            if (open) {
                rightGazeSum += gaze;
                rightGazeCount++;
            }

            long elapsed = now - calibrationStageStartedAt;
            long left = Math.max(0L, 7000L - elapsed);

            instruction.setText(
                    "فقط چشم‌ها به راست • "
                            + ((left + 999L) / 1000L)
                            + " ثانیه"
            );

            if (elapsed >= 7000L) {
                finishCalibration();
            }

            return;
        }

        if (closed) {
            rightGazeStartedAt = 0L;

            if (!eyesClosed) {
                eyesClosed = true;
                blinkActionFired = false;
                blinkStartedAt = now;
                faceState.setText("چشم‌ها بسته شد • کمی نگه دار برای انتخاب");
                return;
            }

            long duration = now - blinkStartedAt;
            boolean readingFinished =
                    blinkStartedAt >= promptShownAt + READING_LOCK_MS;

            if (!blinkActionFired
                    && readingFinished
                    && duration >= BLINK_MIN_MS
                    && duration <= BLINK_MAX_MS) {
                blinkActionFired = true;
                faceState.setText("✓ انتخاب با چشم ثبت شد • دستور در حال اجرا");
                confirmCurrentByBlink();
            } else if (!blinkActionFired && readingFinished) {
                long percent =
                        Math.min(100L, duration * 100L / BLINK_MIN_MS);
                faceState.setText(
                        "چشم بسته • نگه دار برای انتخاب: " + percent + "%"
                );
            }

            return;
        }

        if (eyesClosed && open) {
            long duration = now - blinkStartedAt;
            eyesClosed = false;
            blinkStartedAt = 0L;

            if (!blinkActionFired && duration < BLINK_MIN_MS) {
                faceState.setText("پلک طبیعی بود • انتخابی انجام نشد");
            }

            blinkActionFired = false;
            return;
        }

        if (!open) {
            return;
        }

        if (now - promptShownAt < READING_LOCK_MS) {
            rightGazeStartedAt = 0L;
            faceState.setText("زمان خواندن سؤال");
            return;
        }

        float rightScore =
                (gaze - neutralGaze) * rightDirectionSign;

        if (rightScore > rightThreshold) {
            if (rightGazeStartedAt == 0L) {
                rightGazeStartedAt = now;
            }

            long held = now - rightGazeStartedAt;
            faceState.setText(
                    "نگاه راست دیده شد • "
                            + Math.min(100L, held * 100L / RIGHT_GAZE_HOLD_MS)
                            + "%"
            );

            if (held >= RIGHT_GAZE_HOLD_MS) {
                rightGazeStartedAt = 0L;
                rejectCurrentByGaze();
            }
        } else {
            rightGazeStartedAt = 0L;
            faceState.setText(
                    "در حال انتظار • چشم‌ها را ببند = انتخاب • نگاه راست = رد"
            );
        }
    }

    private float eyeAspectRatio(List<NormalizedLandmark> lm) {
        float rightVertical = distance(lm.get(159), lm.get(145));
        float rightHorizontal = distance(lm.get(33), lm.get(133));

        float leftVertical = distance(lm.get(386), lm.get(374));
        float leftHorizontal = distance(lm.get(362), lm.get(263));

        if (rightHorizontal < 0.0001f
                || leftHorizontal < 0.0001f) {
            return Float.NaN;
        }

        return (
                rightVertical / rightHorizontal
                        + leftVertical / leftHorizontal
        ) / 2f;
    }

    private float gazeRatio(List<NormalizedLandmark> lm) {
        float rightIrisX =
                averageX(lm, 468, 469, 470, 471, 472);
        float leftIrisX =
                averageX(lm, 473, 474, 475, 476, 477);

        float rightMin =
                Math.min(lm.get(33).x(), lm.get(133).x());
        float rightMax =
                Math.max(lm.get(33).x(), lm.get(133).x());

        float leftMin =
                Math.min(lm.get(362).x(), lm.get(263).x());
        float leftMax =
                Math.max(lm.get(362).x(), lm.get(263).x());

        float rightWidth = rightMax - rightMin;
        float leftWidth = leftMax - leftMin;

        if (rightWidth < 0.0001f
                || leftWidth < 0.0001f) {
            return Float.NaN;
        }

        float r = (rightIrisX - rightMin) / rightWidth;
        float l = (leftIrisX - leftMin) / leftWidth;

        return (r + l) / 2f;
    }

    private float averageX(
            List<NormalizedLandmark> lm,
            int... indexes) {
        float sum = 0f;

        for (int index : indexes) {
            sum += lm.get(index).x();
        }

        return sum / indexes.length;
    }

    private float distance(
            NormalizedLandmark a,
            NormalizedLandmark b) {
        float dx = a.x() - b.x();
        float dy = a.y() - b.y();
        return (float) Math.sqrt(dx * dx + dy * dy);
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

            if (selected == null
                    && cm.getCameraIdList().length > 0) {
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
                            faceState.setText("دوربین در دسترس نیست");
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

            st.setDefaultBufferSize(640, 480);

            Surface previewSurface = new Surface(st);

            CaptureRequest.Builder builder =
                    camera.createCaptureRequest(
                            CameraDevice.TEMPLATE_PREVIEW
                    );

            builder.addTarget(previewSurface);

            camera.createCaptureSession(
                    Arrays.asList(previewSurface),
                    new CameraCaptureSession.StateCallback() {
                        @Override
                        public void onConfigured(
                                CameraCaptureSession s) {
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
                                faceState.setText("خطا در پیش‌نمایش دوربین");
                            }
                        }

                        @Override
                        public void onConfigureFailed(
                                CameraCaptureSession s) {
                            faceState.setText("تنظیم دوربین ناموفق بود");
                        }
                    },
                    handler
            );

        } catch (Exception e) {
            Toast.makeText(
                    this,
                    "راه‌اندازی دوربین ناموفق بود",
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private void initMotionSensor() {
        sensorManager =
                (SensorManager) getSystemService(SENSOR_SERVICE);

        Sensor acc =
                sensorManager.getDefaultSensor(
                        Sensor.TYPE_ACCELEROMETER
                );

        if (acc != null) {
            sensorManager.registerListener(
                    this,
                    acc,
                    SensorManager.SENSOR_DELAY_NORMAL
            );
        }
    }

    @Override
    public void onSensorChanged(SensorEvent e) {
        float x = e.values[0];
        float y = e.values[1];
        float z = e.values[2];

        double magnitude =
                Math.sqrt(x * x + y * y + z * z);

        long now = System.currentTimeMillis();

        if (magnitude > 31.0
                && now - lastMotionPrompt > 15000L) {
            lastMotionPrompt = now;
            faceState.setText(
                    "حرکت شدید دستگاه ثبت شد • مراقب وضعیت بیمار باشید"
            );
        }
    }

    @Override
    public void onAccuracyChanged(Sensor sensor, int accuracy) {}

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
                "care-ai-gaze"
        );
    }

    @Override
    public void onInit(int status) {
        if (status != TextToSpeech.SUCCESS || tts == null) {
            faceStateSafe("موتور صوتی گوشی آماده نیست");
            return;
        }

        int result =
                tts.setLanguage(new Locale("fa", "IR"));

        if (result == TextToSpeech.LANG_MISSING_DATA
                || result == TextToSpeech.LANG_NOT_SUPPORTED) {
            tts.setLanguage(Locale.getDefault());
        }

        tts.setSpeechRate(0.82f);
        ttsReady = true;

        if (pendingSpeech != null) {
            String p = pendingSpeech;
            pendingSpeech = null;
            speak(p);
        }
    }

    private void faceStateSafe(String text) {
        if (faceState != null) {
            faceState.setText(text);
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);

        if (session != null) session.close();
        if (camera != null) camera.close();

        if (faceLandmarker != null) {
            faceLandmarker.close();
        }

        visionExecutor.shutdownNow();

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
                v * getResources().getDisplayMetrics().density
                        + 0.5f
        );
    }

    private static final class Prompt {
        final String question;
        final String output;
        final int action;

        Prompt(String question, String output, int action) {
            this.question = question;
            this.output = output;
            this.action = action;
        }
    }
}
