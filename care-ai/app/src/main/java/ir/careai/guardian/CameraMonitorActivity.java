package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.ContentUris;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.SurfaceTexture;
import android.media.AudioAttributes;
import android.media.MediaPlayer;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.speech.tts.TextToSpeech;
import android.telecom.TelecomManager;
import android.view.Gravity;
import android.view.Surface;
import android.view.TextureView;
import android.view.View;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import android.widget.VideoView;

import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.hardware.camera2.CameraCaptureSession;
import android.hardware.camera2.CameraCharacteristics;
import android.hardware.camera2.CameraDevice;
import android.hardware.camera2.CameraManager;
import android.hardware.camera2.CaptureRequest;

import com.google.mediapipe.framework.image.BitmapImageBuilder;
import com.google.mediapipe.framework.image.MPImage;
import com.google.mediapipe.tasks.components.containers.NormalizedLandmark;
import com.google.mediapipe.tasks.core.BaseOptions;
import com.google.mediapipe.tasks.vision.core.RunningMode;
import com.google.mediapipe.tasks.vision.facelandmarker.FaceLandmarker;
import com.google.mediapipe.tasks.vision.facelandmarker.FaceLandmarkerResult;

import java.util.ArrayList;
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

    private static final long BLINK_MIN_MS = 90L;
    private static final long BLINK_MAX_MS = 750L;
    private static final long DOUBLE_BLINK_WINDOW_MS = 1800L;
    private static final long SLEEP_HOLD_MS = 6000L;
    private static final long WAKE_OPEN_MS = 1800L;

    private static final int ACTION_SPEAK = 0;
    private static final int ACTION_TALK_MODE = 1;
    private static final int ACTION_EMERGENCY = 2;
    private static final int ACTION_BACK = 3;
    private static final int ACTION_CALL = 4;
    private static final int ACTION_VIDEO = 5;
    private static final int ACTION_AUDIO = 6;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private final ExecutorService visionExecutor = Executors.newSingleThreadExecutor();

    private TextureView texture;
    private FrameLayout root;
    private LinearLayout panel;
    private VideoView videoView;

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

    private SharedPreferences prefs;

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
    private long eyeClosedStartedAt = 0L;
    private int blinkCount = 0;
    private long firstBlinkAt = 0L;
    private long rightGazeStartedAt = 0L;
    private long lastActionAt = 0L;

    private boolean sleepMode = false;
    private long wakeOpenStartedAt = 0L;

    private boolean mediaMode = false;
    private MediaPlayer audioPlayer;
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
            if (calibrationStage == 2 && !mediaMode && !sleepMode) {
                faceState.setText("پاسخی ثبت نشد • سؤال بعدی");
                advancePrompt(900L);
            }
        }
    };

    private final Runnable countdown = new Runnable() {
        @Override
        public void run() {
            if (calibrationStage != 2 || mediaMode || sleepMode || promptDeadline <= 0L) return;

            long left = Math.max(0L, promptDeadline - System.currentTimeMillis());
            long seconds = (left + 999L) / 1000L;

            instruction.setText(
                    "دو پلک پشت سر هم = تأیید  •  نگاه راست = رد  •  "
                            + seconds + " ثانیه"
            );

            if (left > 0L) handler.postDelayed(this, 1000L);
        }
    };

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);

        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);

        prefs = getSharedPreferences("careai", MODE_PRIVATE);
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

            faceLandmarker = FaceLandmarker.createFromOptions(this, options);
            startCalibration();

        } catch (Exception e) {
            faceStateSafe("راه‌اندازی تشخیص چشم ناموفق بود");
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
        root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);

        texture = new TextureView(this);
        root.addView(texture, new FrameLayout.LayoutParams(-1, -1));

        videoView = new VideoView(this);
        videoView.setVisibility(View.GONE);
        root.addView(videoView, new FrameLayout.LayoutParams(-1, -1));

        panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(14), dp(12), dp(14), dp(14));
        panel.setBackgroundColor(0xE8111822);

        modeState = new TextView(this);
        modeState.setTextColor(0xFF9CCBFF);
        modeState.setTextSize(15);
        modeState.setGravity(Gravity.CENTER);
        modeState.setPadding(0, 0, 0, dp(6));

        promptText = new TextView(this);
        promptText.setTextColor(Color.WHITE);
        promptText.setTextSize(30);
        promptText.setGravity(Gravity.CENTER);
        promptText.setBackgroundColor(0xFF1A5688);
        promptText.setPadding(dp(10), dp(16), dp(10), dp(16));

        instruction = new TextView(this);
        instruction.setTextColor(0xFFE9F0F7);
        instruction.setTextSize(15);
        instruction.setGravity(Gravity.CENTER);
        instruction.setPadding(dp(4), dp(8), dp(4), 0);

        panel.addView(modeState);
        panel.addView(promptText, new LinearLayout.LayoutParams(-1, dp(105)));
        panel.addView(instruction);

        FrameLayout.LayoutParams pp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.TOP
                );
        root.addView(panel, pp);

        faceState = new TextView(this);
        faceState.setText("در حال آماده‌سازی...");
        faceState.setTextColor(Color.WHITE);
        faceState.setTextSize(15);
        faceState.setGravity(Gravity.CENTER);
        faceState.setBackgroundColor(0xAA101820);
        faceState.setPadding(dp(8), dp(8), dp(8), dp(8));

        FrameLayout.LayoutParams fp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.BOTTOM
                );
        root.addView(faceState, fp);

        setContentView(root);

        texture.setSurfaceTextureListener(
                new TextureView.SurfaceTextureListener() {
                    @Override
                    public void onSurfaceTextureAvailable(
                            SurfaceTexture s, int w, int h) {
                        openFrontCamera();
                    }

                    @Override public void onSurfaceTextureSizeChanged(
                            SurfaceTexture s, int w, int h) {}

                    @Override
                    public boolean onSurfaceTextureDestroyed(
                            SurfaceTexture s) {
                        return true;
                    }

                    @Override public void onSurfaceTextureUpdated(
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

        modeState.setText("کالیبراسیون چشم — مرحله ۱ از ۲");
        promptText.setText("مستقیم به صفحه نگاه کن");
        instruction.setText("حدود ۶ ثانیه مستقیم نگاه کن.");
        speak("لطفاً چند ثانیه مستقیم به صفحه نگاه کنید.");
    }

    private void beginRightCalibration() {
        calibrationStage = 1;
        calibrationStageStartedAt = System.currentTimeMillis();
        rightGazeSum = 0.0;
        rightGazeCount = 0;

        modeState.setText("کالیبراسیون چشم — مرحله ۲ از ۲");
        promptText.setText("فقط چشم‌ها را به سمت راست ببر");
        instruction.setText("سر ثابت؛ فقط چشم‌ها به راست. حدود ۷ ثانیه.");
        speak("حالا فقط چشم‌ها را به سمت راست ببرید و نگه دارید.");
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
            retryCalibration("حرکت نگاه راست واضح نبود");
            return;
        }

        neutralGaze = center;
        rightDirectionSign = diff >= 0f ? 1 : -1;
        rightThreshold =
                Math.max(0.018f, Math.min(0.075f, Math.abs(diff) * 0.45f));

        calibrationStage = 2;

        speak(
                "کالیبراسیون انجام شد. دو پلک پشت سر هم یعنی تأیید. "
                        + "نگاه به راست یعنی رد. بسته ماندن چشم‌ها یعنی خواب."
        );

        handler.postDelayed(() -> showPrompt(0), 2600L);
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
        instruction.setText("چند ثانیه مستقیم نگاه کن.");
        speak(reason + ". دوباره مستقیم به صفحه نگاه کنید.");
    }

    private List<Prompt> currentPrompts() {
        ArrayList<Prompt> out = new ArrayList<>();

        // اکشن‌های واقعی همیشه اول چرخه هستند تا در هر حالت قابل دسترسی باشند.
        for (int i = 1; i <= 3; i++) {
            String number = prefs.getString("trusted" + i, "").trim();
            if (!number.isEmpty()) {
                String name = prefs.getString("trusted_name" + i, "").trim();
                if (name.isEmpty()) name = "همراه " + i;
                out.add(new Prompt(
                        "می‌خواهی به " + name + " زنگ بزنم؟",
                        name,
                        ACTION_CALL,
                        number
                ));
            }
        }

        out.add(new Prompt(
                "می‌خواهی برات ویدیو پخش کنم؟",
                "ویدیو",
                ACTION_VIDEO,
                ""
        ));

        out.add(new Prompt(
                "می‌خواهی برات آهنگ پخش کنم؟",
                "آهنگ",
                ACTION_AUDIO,
                ""
        ));

        // ارتباط و نیازهای روزمره
        out.add(new Prompt("می‌خواهی صحبت کنی؟", "", ACTION_TALK_MODE, ""));
        out.add(new Prompt("بله؟", "بله", ACTION_SPEAK, ""));
        out.add(new Prompt("خیر؟", "خیر", ACTION_SPEAK, ""));
        out.add(new Prompt("آب می‌خواهی؟", "آب می‌خواهم", ACTION_SPEAK, ""));
        out.add(new Prompt("غذا می‌خواهی؟", "غذا می‌خواهم", ACTION_SPEAK, ""));
        out.add(new Prompt("درد داری؟", "درد دارم", ACTION_SPEAK, ""));
        out.add(new Prompt("دستشویی می‌خواهی؟", "دستشویی می‌خواهم", ACTION_SPEAK, ""));
        out.add(new Prompt("سردت است؟", "سردم است", ACTION_SPEAK, ""));
        out.add(new Prompt("گرمت است؟", "گرمم است", ACTION_SPEAK, ""));
        out.add(new Prompt("کمک فوری می‌خواهی؟", "", ACTION_EMERGENCY, ""));

        if (talkMode) {
            out.add(new Prompt("از حالت صحبت خارج شویم؟", "", ACTION_BACK, ""));
        }

        return out;
    }

    private void showPrompt(int index) {
        if (calibrationStage != 2 || mediaMode || sleepMode) return;

        List<Prompt> prompts = currentPrompts();
        if (prompts.isEmpty()) return;

        promptIndex = (index + prompts.size()) % prompts.size();

        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        promptShownAt = System.currentTimeMillis();
        promptDeadline = promptShownAt + PROMPT_DURATION_MS;
        rightGazeStartedAt = 0L;
        blinkCount = 0;
        firstBlinkAt = 0L;

        Prompt p = prompts.get(promptIndex);

        panel.setVisibility(View.VISIBLE);
        setPanelFull();
        promptText.setBackgroundColor(0xFF1A5688);
        String category;
        if (p.action == ACTION_CALL) category = "تماس تلفنی";
        else if (p.action == ACTION_VIDEO) category = "پخش ویدیو";
        else if (p.action == ACTION_AUDIO) category = "پخش موسیقی";
        else if (p.action == ACTION_EMERGENCY) category = "کمک اضطراری";
        else category = talkMode ? "ارتباط با بیمار" : "Care Mode";

        modeState.setText(category + " • دو پلک = اجرا");
        promptText.setText(p.question);
        faceState.setText("در حال انتظار برای تصمیم بیمار");

        speak(p.question);

        handler.postDelayed(promptTimeout, PROMPT_DURATION_MS);
        handler.post(countdown);
    }

    private void advancePrompt(long delayMs) {
        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);
        final int next = promptIndex + 1;
        handler.postDelayed(() -> showPrompt(next), delayMs);
    }

    private void rejectCurrentByGaze() {
        long now = System.currentTimeMillis();
        if (now - lastActionAt < 1200L || mediaMode || sleepMode) return;
        lastActionAt = now;

        faceState.setText("نگاه راست تشخیص داده شد • رد شد");
        speak("باشه. سؤال بعدی.");
        advancePrompt(900L);
    }

    private void confirmCurrentByDoubleBlink() {
        long now = System.currentTimeMillis();

        if (mediaMode) {
            stopMediaAndReturn("پخش متوقف شد.");
            return;
        }

        if (sleepMode || now - lastActionAt < 1200L) return;
        lastActionAt = now;

        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        List<Prompt> prompts = currentPrompts();
        if (promptIndex < 0 || promptIndex >= prompts.size()) return;

        Prompt p = prompts.get(promptIndex);

        promptText.setBackgroundColor(0xFF1E7A46);
        promptText.setText("✓ فرمان دریافت شد");
        faceState.setText("دو پلک تأیید شد • در حال اجرای فرمان واقعی");

        if (p.action == ACTION_TALK_MODE) {
            talkMode = true;
            speak("تأیید شد. حالت صحبت را فعال می‌کنم.");
            handler.postDelayed(() -> showPrompt(0), 3800L);
            return;
        }

        if (p.action == ACTION_BACK) {
            talkMode = false;
            speak("تأیید شد. به منوی اصلی برمی‌گردیم.");
            handler.postDelayed(() -> showPrompt(0), 3800L);
            return;
        }

        if (p.action == ACTION_EMERGENCY) {
            promptText.setBackgroundColor(0xFF9B1C31);
            promptText.setText("✓ کمک فوری ارسال شد");
            speak("تأیید شد. درخواست کمک فوری را ارسال می‌کنم.");
            EmergencyManager.sendEmergency(
                    this,
                    "درخواست کمک با دو پلک بیمار",
                    true
            );
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 5000L);
            return;
        }

        if (p.action == ACTION_CALL) {
            promptText.setText("☎ در حال تماس با " + p.output);
            faceState.setText("در حال ارسال فرمان تماس به سیستم تلفن");
            speak("تأیید شد. الان به " + p.output + " زنگ می‌زنم و تماس را روی بلندگو می‌گذارم.");
            handler.postDelayed(() -> placeSpeakerCall(p.data), 700L);
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 6500L);
            return;
        }

        if (p.action == ACTION_VIDEO) {
            promptText.setText("▶ در حال باز کردن ویدیو");
            faceState.setText("فرمان پخش ویدیو اجرا شد");
            speak("تأیید شد. الان ویدیو را پخش می‌کنم.");
            handler.postDelayed(this::playLatestVideo, 1100L);
            return;
        }

        if (p.action == ACTION_AUDIO) {
            promptText.setText("♫ در حال پخش آهنگ");
            faceState.setText("فرمان پخش موسیقی اجرا شد");
            speak("تأیید شد. الان آهنگ را پخش می‌کنم.");
            handler.postDelayed(this::playLatestAudio, 1100L);
            return;
        }

        promptText.setText("✓ " + p.output);
        speak("تأیید شد. " + p.output);
        handler.postDelayed(() -> showPrompt(promptIndex + 1), 4500L);
    }

    private void placeSpeakerCall(String number) {
        if (number == null || number.trim().isEmpty()) {
            speak("شماره تماس ثبت نشده است.");
            return;
        }

        if (checkSelfPermission(Manifest.permission.CALL_PHONE)
                != PackageManager.PERMISSION_GRANTED) {
            speak("مجوز تماس تلفنی داده نشده است.");
            return;
        }

        try {
            TelecomManager telecom =
                    (TelecomManager) getSystemService(TELECOM_SERVICE);

            Bundle extras = new Bundle();
            extras.putBoolean(
                    TelecomManager.EXTRA_START_CALL_WITH_SPEAKERPHONE,
                    true
            );

            telecom.placeCall(
                    Uri.fromParts("tel", number, null),
                    extras
            );
        } catch (Exception e) {
            speak("تماس برقرار نشد.");
        }
    }

    private boolean hasAudioPermission() {
        if (Build.VERSION.SDK_INT >= 33) {
            return checkSelfPermission(Manifest.permission.READ_MEDIA_AUDIO)
                    == PackageManager.PERMISSION_GRANTED;
        }
        return checkSelfPermission(Manifest.permission.READ_EXTERNAL_STORAGE)
                == PackageManager.PERMISSION_GRANTED;
    }

    private boolean hasVideoPermission() {
        if (Build.VERSION.SDK_INT >= 33) {
            return checkSelfPermission(Manifest.permission.READ_MEDIA_VIDEO)
                    == PackageManager.PERMISSION_GRANTED;
        }
        return checkSelfPermission(Manifest.permission.READ_EXTERNAL_STORAGE)
                == PackageManager.PERMISSION_GRANTED;
    }

    private Uri latestMediaUri(boolean video) {
        Uri collection = video
                ? MediaStore.Video.Media.EXTERNAL_CONTENT_URI
                : MediaStore.Audio.Media.EXTERNAL_CONTENT_URI;

        String[] projection = new String[] {
                MediaStore.MediaColumns._ID
        };

        Cursor cursor = null;
        try {
            cursor = getContentResolver().query(
                    collection,
                    projection,
                    null,
                    null,
                    MediaStore.MediaColumns.DATE_ADDED + " DESC"
            );

            if (cursor != null && cursor.moveToFirst()) {
                long id = cursor.getLong(0);
                return ContentUris.withAppendedId(collection, id);
            }
        } catch (Exception ignored) {
        } finally {
            if (cursor != null) cursor.close();
        }

        return null;
    }

    private void playLatestVideo() {
        if (!hasVideoPermission()) {
            speak("مجوز دسترسی به ویدیوهای گوشی داده نشده است.");
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 3000L);
            return;
        }

        Uri uri = latestMediaUri(true);
        if (uri == null) {
            speak("ویدیویی در گوشی پیدا نکردم.");
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 3000L);
            return;
        }

        stopAudioOnly();
        mediaMode = true;

        videoView.setVisibility(View.VISIBLE);
        videoView.setVideoURI(uri);
        videoView.setOnPreparedListener(mp -> {
            mp.setLooping(false);
            videoView.start();
        });
        videoView.setOnCompletionListener(mp ->
                stopMediaAndReturn("ویدیو تمام شد.")
        );
        videoView.setOnErrorListener((mp, what, extra) -> {
            stopMediaAndReturn("پخش ویدیو با خطا متوقف شد.");
            return true;
        });

        setPanelCompact("Care AI • رصد فعال", "دو پلک = توقف و بازگشت");
        faceState.setText("ویدیو در حال پخش • رصد چشم ادامه دارد");
    }

    private void playLatestAudio() {
        if (!hasAudioPermission()) {
            speak("مجوز دسترسی به آهنگ‌های گوشی داده نشده است.");
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 3000L);
            return;
        }

        Uri uri = latestMediaUri(false);
        if (uri == null) {
            speak("آهنگی در گوشی پیدا نکردم.");
            handler.postDelayed(() -> showPrompt(promptIndex + 1), 3000L);
            return;
        }

        stopMediaSilently();
        mediaMode = true;

        try {
            audioPlayer = new MediaPlayer();
            audioPlayer.setAudioAttributes(
                    new AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_MEDIA)
                            .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                            .build()
            );
            audioPlayer.setDataSource(this, uri);
            audioPlayer.setOnPreparedListener(mp -> mp.start());
            audioPlayer.setOnCompletionListener(mp ->
                    stopMediaAndReturn("آهنگ تمام شد.")
            );
            audioPlayer.prepareAsync();

            setPanelCompact("Care AI • موسیقی", "دو پلک = توقف و بازگشت");
            faceState.setText("آهنگ در حال پخش • رصد چشم ادامه دارد");

        } catch (Exception e) {
            stopMediaAndReturn("پخش آهنگ انجام نشد.");
        }
    }

    private void setPanelCompact(String title, String sub) {
        modeState.setText(title);
        promptText.setText("رصد فعال");
        promptText.setTextSize(18);
        instruction.setText(sub);

        FrameLayout.LayoutParams lp =
                new FrameLayout.LayoutParams(
                        dp(190),
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.TOP | Gravity.END
                );
        lp.setMargins(dp(8), dp(10), dp(8), 0);
        panel.setLayoutParams(lp);
    }

    private void setPanelFull() {
        promptText.setTextSize(30);

        FrameLayout.LayoutParams lp =
                new FrameLayout.LayoutParams(
                        -1,
                        FrameLayout.LayoutParams.WRAP_CONTENT,
                        Gravity.TOP
                );
        panel.setLayoutParams(lp);
    }

    private void stopAudioOnly() {
        if (audioPlayer != null) {
            try { audioPlayer.stop(); } catch (Exception ignored) {}
            try { audioPlayer.release(); } catch (Exception ignored) {}
            audioPlayer = null;
        }
    }

    private void stopMediaSilently() {
        if (videoView != null) {
            try { videoView.stopPlayback(); } catch (Exception ignored) {}
            videoView.setVisibility(View.GONE);
        }
        stopAudioOnly();
        mediaMode = false;
    }

    private void stopMediaAndReturn(String reason) {
        stopMediaSilently();
        setPanelFull();
        panel.setVisibility(View.VISIBLE);
        promptText.setBackgroundColor(0xFF1A5688);
        modeState.setText("Care Mode");
        promptText.setText("بازگشت به مراقبت");
        instruction.setText("دو پلک = تأیید • نگاه راست = رد");
        faceState.setText(reason);
        speak(reason);
        handler.postDelayed(() -> showPrompt(promptIndex + 1), 2200L);
    }

    private void enterSleepMode() {
        if (sleepMode) return;

        sleepMode = true;
        blinkCount = 0;
        firstBlinkAt = 0L;
        rightGazeStartedAt = 0L;

        handler.removeCallbacks(promptTimeout);
        handler.removeCallbacks(countdown);

        stopMediaSilently();

        setPanelFull();
        panel.setVisibility(View.VISIBLE);
        promptText.setBackgroundColor(0xFF263238);
        modeState.setText("حالت خواب");
        promptText.setText("خواب تشخیص داده شد");
        instruction.setText("رصد ادامه دارد • با بازشدن چشم‌ها سیستم برمی‌گردد");
        faceState.setText("چشم‌ها طولانی بسته مانده‌اند • حالت خواب فعال شد");

        // عمداً صوت پخش نمی‌شود تا بیمار بیدار نشود.
    }

    private void exitSleepMode() {
        sleepMode = false;
        wakeOpenStartedAt = 0L;
        promptText.setBackgroundColor(0xFF1A5688);
        faceState.setText("بیداری تشخیص داده شد");
        speak("بیداری تشخیص داده شد. مراقبت ادامه دارد.");
        handler.postDelayed(() -> showPrompt(0), 1800L);
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
                MPImage image = new BitmapImageBuilder(bitmap).build();
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
                    if (!bitmap.isRecycled()) bitmap.recycle();
                }
            });
        });
    }

    private void processFaceResult(FaceLandmarkerResult result) {
        long now = System.currentTimeMillis();

        if (result == null
                || result.faceLandmarks() == null
                || result.faceLandmarks().isEmpty()) {
            faceState.setText("چهره دیده نمی‌شود • گوشی روبه‌روی بیمار باشد");
            rightGazeStartedAt = 0L;
            return;
        }

        List<NormalizedLandmark> lm = result.faceLandmarks().get(0);

        if (lm == null || lm.size() < 478) {
            faceState.setText("نقاط چشم کامل دریافت نشد");
            return;
        }

        float ear = eyeAspectRatio(lm);
        float gaze = gazeRatio(lm);

        if (Float.isNaN(ear) || Float.isNaN(gaze)) return;

        if (ear > openEarBaseline * 0.70f) {
            openEarBaseline = openEarBaseline * 0.97f + ear * 0.03f;
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
                            + ((left + 999L) / 1000L) + " ثانیه"
            );

            if (elapsed >= 6000L) beginRightCalibration();
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
                            + ((left + 999L) / 1000L) + " ثانیه"
            );

            if (elapsed >= 7000L) finishCalibration();
            return;
        }

        if (closed) {
            wakeOpenStartedAt = 0L;
            rightGazeStartedAt = 0L;

            if (!eyesClosed) {
                eyesClosed = true;
                eyeClosedStartedAt = now;
            }

            long held = now - eyeClosedStartedAt;

            if (!sleepMode && held >= SLEEP_HOLD_MS) {
                enterSleepMode();
            } else if (!sleepMode && !mediaMode) {
                faceState.setText(
                        held < 1000L
                                ? "چشم بسته • اگر کوتاه باشد پلک محسوب می‌شود"
                                : "چشم بسته مانده • اگر ادامه پیدا کند خواب محسوب می‌شود"
                );
            }

            return;
        }

        if (eyesClosed && open) {
            long duration = now - eyeClosedStartedAt;
            eyesClosed = false;
            eyeClosedStartedAt = 0L;

            if (!sleepMode
                    && duration >= BLINK_MIN_MS
                    && duration <= BLINK_MAX_MS
                    && now >= promptShownAt + READING_LOCK_MS) {

                if (blinkCount == 1
                        && now - firstBlinkAt <= DOUBLE_BLINK_WINDOW_MS) {
                    blinkCount = 0;
                    firstBlinkAt = 0L;
                    faceState.setText("✓ دو پلک پشت سر هم تشخیص داده شد");
                    confirmCurrentByDoubleBlink();
                    return;
                }

                blinkCount = 1;
                firstBlinkAt = now;
                faceState.setText("پلک اول ثبت شد • یک پلک دیگر");
                return;
            }
        }

        if (blinkCount == 1
                && now - firstBlinkAt > DOUBLE_BLINK_WINDOW_MS) {
            blinkCount = 0;
            firstBlinkAt = 0L;
        }

        if (sleepMode) {
            if (open) {
                if (wakeOpenStartedAt == 0L) wakeOpenStartedAt = now;

                if (now - wakeOpenStartedAt >= WAKE_OPEN_MS) {
                    exitSleepMode();
                } else {
                    faceState.setText("چشم‌ها باز شد • در حال بررسی بیداری");
                }
            } else {
                wakeOpenStartedAt = 0L;
            }
            return;
        }

        if (mediaMode) {
            faceState.setText(
                    "رصد فعال • دو پلک = توقف پخش • چشم بسته طولانی = خواب"
            );
            return;
        }

        if (!open) return;

        if (now - promptShownAt < READING_LOCK_MS) {
            rightGazeStartedAt = 0L;
            faceState.setText("زمان خواندن سؤال");
            return;
        }

        float rightScore = (gaze - neutralGaze) * rightDirectionSign;

        if (rightScore > rightThreshold) {
            if (rightGazeStartedAt == 0L) rightGazeStartedAt = now;

            long held = now - rightGazeStartedAt;
            faceState.setText(
                    "نگاه راست • "
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
                    "دو پلک = تأیید • نگاه راست = رد • چشم بسته طولانی = خواب"
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
        float rightIrisX = averageX(lm, 468, 469, 470, 471, 472);
        float leftIrisX = averageX(lm, 473, 474, 475, 476, 477);

        float rightMin = Math.min(lm.get(33).x(), lm.get(133).x());
        float rightMax = Math.max(lm.get(33).x(), lm.get(133).x());

        float leftMin = Math.min(lm.get(362).x(), lm.get(263).x());
        float leftMax = Math.max(lm.get(362).x(), lm.get(263).x());

        float rightWidth = rightMax - rightMin;
        float leftWidth = leftMax - leftMin;

        if (rightWidth < 0.0001f || leftWidth < 0.0001f) {
            return Float.NaN;
        }

        float r = (rightIrisX - rightMin) / rightWidth;
        float l = (leftIrisX - leftMin) / leftWidth;

        return (r + l) / 2f;
    }

    private float averageX(List<NormalizedLandmark> lm, int... indexes) {
        float sum = 0f;
        for (int index : indexes) sum += lm.get(index).x();
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

        double magnitude = Math.sqrt(x * x + y * y + z * z);
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
                "care-ai-v5"
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

        tts.setSpeechRate(0.82f);
        ttsReady = true;

        if (pendingSpeech != null) {
            String p = pendingSpeech;
            pendingSpeech = null;
            speak(p);
        }
    }

    private void faceStateSafe(String text) {
        if (faceState != null) faceState.setText(text);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);

        stopMediaSilently();

        if (session != null) session.close();
        if (camera != null) camera.close();

        if (faceLandmarker != null) faceLandmarker.close();

        visionExecutor.shutdownNow();

        if (sensorManager != null) sensorManager.unregisterListener(this);

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

    private static final class Prompt {
        final String question;
        final String output;
        final int action;
        final String data;

        Prompt(String question, String output, int action, String data) {
            this.question = question;
            this.output = output;
            this.action = action;
            this.data = data;
        }
    }
}
