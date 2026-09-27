package ir.careai.guardian;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.media.AudioAttributes;
import android.media.MediaPlayer;
import android.media.MediaRecorder;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import java.io.File;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class CommandEditorActivity extends Activity {

    private static final int C_BG = Color.rgb(244, 249, 253);
    private static final int C_TEXT = Color.rgb(19, 49, 83);
    private static final int C_MUTED = Color.rgb(91, 111, 134);
    private static final int C_BLUE = Color.rgb(29, 120, 220);
    private static final int C_TEAL = Color.rgb(18, 185, 170);
    private static final int C_RED = Color.rgb(237, 76, 91);
    private static final int C_PURPLE = Color.rgb(116, 83, 207);

    private static final String[] ACTION_VALUES = new String[] {
            CommandStore.ACTION_SPEAK,
            CommandStore.ACTION_CALL_1,
            CommandStore.ACTION_CALL_2,
            CommandStore.ACTION_CALL_3,
            CommandStore.ACTION_VIDEO,
            CommandStore.ACTION_AUDIO,
            CommandStore.ACTION_OPEN_APP,
            CommandStore.ACTION_OPEN_URL,
            CommandStore.ACTION_EMERGENCY
    };

    private static final String[] ACTION_LABELS = new String[] {
            "اعلام صوتی",
            "تماس با همراه ۱",
            "تماس با همراه ۲",
            "تماس با همراه ۳",
            "پخش ویدیو",
            "پخش آهنگ",
            "باز کردن اپلیکیشن",
            "باز کردن لینک / سایت",
            "کمک فوری"
    };

    private LinearLayout list;
    private List<CommandStore.Command> commands;

    private MediaRecorder recorder;
    private MediaPlayer previewPlayer;
    private CommandStore.Command recordingCommand;
    private Button recordingButton;
    private TextView recordingStatus;

    private CommandStore.Command pendingRecordCommand;
    private Button pendingRecordButton;
    private TextView pendingRecordStatus;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        getWindow().setStatusBarColor(C_BG);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR);

        commands = new ArrayList<>(CommandStore.load(this));
        render();
    }

    private GradientDrawable bg(int color, int radius) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(radius));
        return g;
    }

    private GradientDrawable strokeBg(int color, int radius, int strokeColor) {
        GradientDrawable g = bg(color, radius);
        g.setStroke(dp(1), strokeColor);
        return g;
    }

    private void render() {
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(C_BG);

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(14), dp(16), dp(14), dp(30));

        LinearLayout hero = new LinearLayout(this);
        hero.setOrientation(LinearLayout.VERTICAL);
        hero.setPadding(dp(16), dp(16), dp(16), dp(16));
        hero.setBackground(bg(Color.WHITE, 24));

        TextView title = text("مدیریت دستورات", 25, C_TEXT, true);
        TextView sub = text(
                "فرمان‌های بیمار را اضافه، ویرایش، حذف و دسته‌بندی کنید",
                13.5f,
                C_MUTED,
                false
        );
        sub.setPadding(0, dp(4), 0, 0);
        hero.addView(title);
        hero.addView(sub);
        root.addView(hero);

        TextView info = text(
                "برای هر دستور می‌توانی صدای واقعی ضبط کنی. هنگام تأیید بیمار، صدای ضبط‌شده پخش می‌شود؛ اگر ضبطی وجود نداشته باشد Care AI متن نتیجه را با صدای مصنوعی می‌خواند.",
                13,
                Color.rgb(52, 101, 128),
                false
        );
        info.setPadding(dp(14), dp(12), dp(14), dp(12));
        info.setBackground(bg(Color.rgb(231, 247, 247), 18));
        LinearLayout.LayoutParams infop = new LinearLayout.LayoutParams(-1, -2);
        infop.setMargins(0, dp(12), 0, dp(12));
        root.addView(info, infop);

        Button add = button("＋ افزودن دستور جدید", C_TEAL);
        root.addView(add);
        add.setOnClickListener(v -> {
            commands.add(0, new CommandStore.Command(
                    UUID.randomUUID().toString(),
                    "سؤال جدید؟",
                    "پاسخ یا نتیجه دستور",
                    CommandStore.ACTION_SPEAK,
                    true,
                    true
            ));
            CommandStore.save(this, commands);
            render();
        });

        TextView count = text(
                "دستورات فعال و ذخیره‌شده  •  " + commands.size() + " مورد",
                16,
                C_TEXT,
                true
        );
        count.setPadding(dp(2), dp(18), dp(2), dp(5));
        root.addView(count);

        list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        root.addView(list);

        for (int i = 0; i < commands.size(); i++) {
            addCommandCard(i, commands.get(i));
        }

        Button saveAll = button("ذخیره همه تغییرات", C_BLUE);
        root.addView(saveAll);
        saveAll.setOnClickListener(v -> {
            CommandStore.save(this, commands);
            Toast.makeText(this, "همه دستورات ذخیره شدند", Toast.LENGTH_SHORT).show();
        });

        Button reset = button("بازگردانی دستورات پیش‌فرض", C_PURPLE);
        root.addView(reset);
        reset.setOnClickListener(v -> {
            stopRecordingIfNeeded(false);
            stopPreview();
            CommandStore.deleteAllVoiceFiles(this);
            commands = new ArrayList<>(CommandStore.defaults(this));
            CommandStore.save(this, commands);
            render();
        });

        Button back = button("بازگشت", Color.rgb(104, 120, 137));
        root.addView(back);
        back.setOnClickListener(v -> finish());

        scroll.addView(root);
        setContentView(scroll);
    }

    private void addCommandCard(int index, CommandStore.Command cmd) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(13), dp(13), dp(13), dp(13));
        card.setBackground(strokeBg(
                Color.WHITE,
                21,
                Color.rgb(224, 233, 242)
        ));

        LinearLayout.LayoutParams cp =
                new LinearLayout.LayoutParams(-1, LinearLayout.LayoutParams.WRAP_CONTENT);
        cp.setMargins(0, dp(7), 0, dp(7));
        card.setLayoutParams(cp);

        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.HORIZONTAL);
        header.setGravity(Gravity.CENTER_VERTICAL);

        TextView badge = badgeFor(cmd.action);
        header.addView(badge);

        LinearLayout titles = new LinearLayout(this);
        titles.setOrientation(LinearLayout.VERTICAL);
        titles.setPadding(dp(10), 0, dp(10), 0);
        TextView h = text(
                cmd.question == null || cmd.question.isEmpty()
                        ? "دستور " + (index + 1)
                        : cmd.question,
                17,
                C_TEXT,
                true
        );
        TextView type = text(
                CommandStore.actionLabel(cmd.action),
                12.5f,
                C_MUTED,
                false
        );
        titles.addView(h);
        titles.addView(type);
        header.addView(titles, new LinearLayout.LayoutParams(0, -2, 1f));

        CheckBox enabledTop = new CheckBox(this);
        enabledTop.setChecked(cmd.enabled);
        enabledTop.setText("");
        header.addView(enabledTop);

        card.addView(header);

        TextView qLabel = label("متن سؤال برای بیمار");
        card.addView(qLabel);
        EditText q = input("مثلاً: آب می‌خواهی؟");
        q.setText(cmd.question);
        card.addView(q);

        TextView outLabel = label("متن پاسخ / نتیجه");
        card.addView(outLabel);
        EditText out = input("مثلاً: آب می‌خواهم");
        out.setText(cmd.output);
        card.addView(out);

        TextView voiceLabel = label("صدای اختصاصی این دستور");
        card.addView(voiceLabel);

        TextView voiceStatus = text(
                CommandStore.hasVoice(this, cmd)
                        ? "● صدای ضبط‌شده آماده است"
                        : "برای این دستور هنوز صدایی ضبط نشده است",
                12.5f,
                CommandStore.hasVoice(this, cmd) ? C_TEAL : C_MUTED,
                true
        );
        voiceStatus.setPadding(dp(3), dp(2), dp(3), dp(7));
        card.addView(voiceStatus);

        LinearLayout voiceActions = new LinearLayout(this);
        voiceActions.setOrientation(LinearLayout.HORIZONTAL);

        Button recordVoice = smallButton(
                CommandStore.hasVoice(this, cmd)
                        ? "● ضبط مجدد"
                        : "● ضبط صدا",
                C_RED
        );
        Button playVoice = smallButton("▶ پخش نمونه", C_TEAL);
        Button deleteVoice = smallButton("حذف صدا", Color.rgb(104, 120, 137));

        LinearLayout.LayoutParams vp1 = new LinearLayout.LayoutParams(0, dp(48), 1f);
        vp1.setMargins(0, 0, dp(4), 0);
        LinearLayout.LayoutParams vp2 = new LinearLayout.LayoutParams(0, dp(48), 1f);
        vp2.setMargins(dp(4), 0, dp(4), 0);
        LinearLayout.LayoutParams vp3 = new LinearLayout.LayoutParams(0, dp(48), 1f);
        vp3.setMargins(dp(4), 0, 0, 0);

        voiceActions.addView(recordVoice, vp1);
        voiceActions.addView(playVoice, vp2);
        voiceActions.addView(deleteVoice, vp3);
        card.addView(voiceActions);

        recordVoice.setOnClickListener(v -> {
            if (recordingCommand == cmd) {
                stopRecordingIfNeeded(true);
            } else {
                beginRecording(cmd, recordVoice, voiceStatus);
            }
        });

        playVoice.setOnClickListener(v -> {
            stopRecordingIfNeeded(true);
            playRecordedVoice(cmd, voiceStatus);
        });

        deleteVoice.setOnClickListener(v -> {
            if (recordingCommand == cmd) {
                stopRecordingIfNeeded(false);
            }
            stopPreview();
            CommandStore.deleteVoice(this, cmd);
            CommandStore.save(this, commands);
            voiceStatus.setText("صدای ضبط‌شده حذف شد");
            voiceStatus.setTextColor(C_MUTED);
            recordVoice.setText("● ضبط صدا");
        });

        TextView targetLabel = label("هدف اجرا (برای دستورهای اجرایی)");
        card.addView(targetLabel);
        EditText target = input("مثلاً Instagram یا com.instagram.android یا https://...");
        target.setText(cmd.target == null ? "" : cmd.target);
        card.addView(target);

        TextView targetHint = text(
                "برای «باز کردن اپلیکیشن» می‌توانی Instagram، WhatsApp، Telegram، YouTube یا package name برنامه را وارد کنی.",
                12,
                C_MUTED,
                false
        );
        targetHint.setPadding(dp(3), dp(3), dp(3), dp(7));
        card.addView(targetHint);

        TextView actionLabel = label("نوع عملکرد");
        card.addView(actionLabel);

        Spinner spinner = new Spinner(this);
        ArrayAdapter<String> adapter = new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_dropdown_item,
                ACTION_LABELS
        );
        spinner.setAdapter(adapter);
        spinner.setSelection(actionIndex(cmd.action));
        spinner.setBackground(strokeBg(
                Color.rgb(248, 251, 253),
                14,
                Color.rgb(218, 229, 239)
        ));
        card.addView(spinner, new LinearLayout.LayoutParams(-1, dp(52)));

        CheckBox notify = new CheckBox(this);
        notify.setText("ارسال متن نتیجه با SMS به شماره‌های اضطراری");
        notify.setTextColor(C_TEXT);
        notify.setTextSize(13.5f);
        notify.setChecked(cmd.notifyContacts);
        card.addView(notify);

        TextView hint = text(
                notifyHint(cmd.action, cmd.notifyContacts),
                12.5f,
                C_MUTED,
                false
        );
        hint.setPadding(dp(4), dp(2), dp(4), dp(8));
        card.addView(hint);

        spinner.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            boolean first = true;

            @Override
            public void onItemSelected(
                    AdapterView<?> parent,
                    View view,
                    int position,
                    long id) {

                String action = ACTION_VALUES[position];
                if (!first) {
                    notify.setChecked(CommandStore.defaultNotify(action));
                }
                first = false;

                badge.setText(CommandStore.categoryLabel(action));
                badge.setTextColor(accentFor(action));
                badge.setBackground(bg(
                        Color.argb(
                                26,
                                Color.red(accentFor(action)),
                                Color.green(accentFor(action)),
                                Color.blue(accentFor(action))
                        ),
                        12
                ));

                hint.setText(notifyHint(action, notify.isChecked()));
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });

        notify.setOnCheckedChangeListener((buttonView, isChecked) ->
                hint.setText(
                        notifyHint(
                                ACTION_VALUES[spinner.getSelectedItemPosition()],
                                isChecked
                        )
                )
        );

        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);

        Button save = smallButton("ذخیره", C_BLUE);
        Button delete = smallButton("حذف", C_RED);

        LinearLayout.LayoutParams sp = new LinearLayout.LayoutParams(0, dp(50), 1f);
        sp.setMargins(0, 0, dp(5), 0);
        LinearLayout.LayoutParams dp2 = new LinearLayout.LayoutParams(0, dp(50), 1f);
        dp2.setMargins(dp(5), 0, 0, 0);

        actions.addView(save, sp);
        actions.addView(delete, dp2);
        card.addView(actions);

        save.setOnClickListener(v -> {
            cmd.question = q.getText().toString().trim();
            cmd.output = out.getText().toString().trim();
            cmd.action = ACTION_VALUES[spinner.getSelectedItemPosition()];
            cmd.enabled = enabledTop.isChecked();
            cmd.notifyContacts = notify.isChecked();
            cmd.target = target.getText().toString().trim();

            if (cmd.question.isEmpty()) {
                Toast.makeText(this, "متن سؤال خالی است", Toast.LENGTH_SHORT).show();
                return;
            }

            if (cmd.output.isEmpty()) {
                Toast.makeText(this, "متن نتیجه خالی است", Toast.LENGTH_SHORT).show();
                return;
            }

            CommandStore.save(this, commands);
            Toast.makeText(this, "دستور ذخیره شد", Toast.LENGTH_SHORT).show();
            render();
        });

        delete.setOnClickListener(v -> {
            if (recordingCommand == cmd) {
                stopRecordingIfNeeded(false);
            }
            stopPreview();
            CommandStore.deleteVoice(this, cmd);
            commands.remove(cmd);
            CommandStore.save(this, commands);
            render();
        });

        list.addView(card);
    }

    private void beginRecording(
            CommandStore.Command cmd,
            Button button,
            TextView status) {

        stopPreview();

        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            pendingRecordCommand = cmd;
            pendingRecordButton = button;
            pendingRecordStatus = status;
            requestPermissions(
                    new String[]{Manifest.permission.RECORD_AUDIO},
                    410
            );
            return;
        }

        if (recordingCommand != null) {
            stopRecordingIfNeeded(true);
        }

        File file = CommandStore.newVoiceFile(this, cmd);

        try {
            if (file.exists()) file.delete();

            recorder = Build.VERSION.SDK_INT >= 31
                    ? new MediaRecorder(this)
                    : new MediaRecorder();

            recorder.setAudioSource(MediaRecorder.AudioSource.MIC);
            recorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4);
            recorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC);
            recorder.setAudioSamplingRate(44100);
            recorder.setAudioEncodingBitRate(96000);
            recorder.setOutputFile(file.getAbsolutePath());
            recorder.prepare();
            recorder.start();

            recordingCommand = cmd;
            recordingButton = button;
            recordingStatus = status;

            button.setText("■ توقف و ذخیره");
            status.setText("● در حال ضبط... جمله را واضح بگو");
            status.setTextColor(C_RED);

        } catch (Exception e) {
            releaseRecorder();
            recordingCommand = null;
            recordingButton = null;
            recordingStatus = null;
            Toast.makeText(
                    this,
                    "شروع ضبط صدا انجام نشد",
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    private void stopRecordingIfNeeded(boolean save) {
        if (recorder == null || recordingCommand == null) return;

        CommandStore.Command cmd = recordingCommand;
        Button button = recordingButton;
        TextView status = recordingStatus;

        File file = CommandStore.newVoiceFile(this, cmd);
        boolean stopped = false;

        try {
            recorder.stop();
            stopped = true;
        } catch (Exception ignored) {}

        releaseRecorder();

        if (save && stopped && file.isFile() && file.length() > 0) {
            cmd.voiceFile = file.getName();
            CommandStore.save(this, commands);

            if (status != null) {
                status.setText("● صدا ذخیره شد و هنگام اجرای دستور پخش می‌شود");
                status.setTextColor(C_TEAL);
            }
            if (button != null) button.setText("● ضبط مجدد");

            Toast.makeText(
                    this,
                    "صدای دستور ذخیره شد",
                    Toast.LENGTH_SHORT
            ).show();
        } else {
            try { if (file.exists()) file.delete(); } catch (Exception ignored) {}
            if (status != null) {
                status.setText(
                        CommandStore.hasVoice(this, cmd)
                                ? "● صدای قبلی حفظ شد"
                                : "ضبط ذخیره نشد"
                );
                status.setTextColor(
                        CommandStore.hasVoice(this, cmd) ? C_TEAL : C_MUTED
                );
            }
            if (button != null) {
                button.setText(
                        CommandStore.hasVoice(this, cmd)
                                ? "● ضبط مجدد"
                                : "● ضبط صدا"
                );
            }
        }

        recordingCommand = null;
        recordingButton = null;
        recordingStatus = null;
    }

    private void releaseRecorder() {
        if (recorder != null) {
            try { recorder.reset(); } catch (Exception ignored) {}
            try { recorder.release(); } catch (Exception ignored) {}
            recorder = null;
        }
    }

    private void playRecordedVoice(
            CommandStore.Command cmd,
            TextView status) {

        stopPreview();

        File file = CommandStore.voiceFile(this, cmd);
        if (file == null || !file.isFile() || file.length() <= 0) {
            status.setText("برای این دستور هنوز صدایی ضبط نشده است");
            status.setTextColor(C_MUTED);
            Toast.makeText(
                    this,
                    "اول صدای دستور را ضبط کنید",
                    Toast.LENGTH_SHORT
            ).show();
            return;
        }

        try {
            previewPlayer = new MediaPlayer();
            previewPlayer.setAudioAttributes(
                    new AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_ASSISTANCE_ACCESSIBILITY)
                            .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                            .build()
            );
            previewPlayer.setDataSource(file.getAbsolutePath());
            previewPlayer.setOnPreparedListener(mp -> {
                status.setText("▶ در حال پخش نمونه ضبط‌شده");
                status.setTextColor(C_TEAL);
                mp.start();
            });
            previewPlayer.setOnCompletionListener(mp -> {
                stopPreview();
                status.setText("● صدای ضبط‌شده آماده است");
                status.setTextColor(C_TEAL);
            });
            previewPlayer.setOnErrorListener((mp, what, extra) -> {
                stopPreview();
                status.setText("پخش صدای ضبط‌شده ناموفق بود");
                status.setTextColor(C_RED);
                return true;
            });
            previewPlayer.prepareAsync();

        } catch (Exception e) {
            stopPreview();
            status.setText("پخش صدای ضبط‌شده ناموفق بود");
            status.setTextColor(C_RED);
        }
    }

    private void stopPreview() {
        if (previewPlayer != null) {
            try { previewPlayer.stop(); } catch (Exception ignored) {}
            try { previewPlayer.release(); } catch (Exception ignored) {}
            previewPlayer = null;
        }
    }

    @Override
    public void onRequestPermissionsResult(
            int requestCode,
            String[] permissions,
            int[] grantResults) {
        super.onRequestPermissionsResult(
                requestCode,
                permissions,
                grantResults
        );

        if (requestCode != 410) return;

        if (grantResults.length > 0
                && grantResults[0] == PackageManager.PERMISSION_GRANTED
                && pendingRecordCommand != null) {

            CommandStore.Command cmd = pendingRecordCommand;
            Button button = pendingRecordButton;
            TextView status = pendingRecordStatus;

            pendingRecordCommand = null;
            pendingRecordButton = null;
            pendingRecordStatus = null;

            beginRecording(cmd, button, status);

        } else {
            pendingRecordCommand = null;
            pendingRecordButton = null;
            pendingRecordStatus = null;

            Toast.makeText(
                    this,
                    "برای ضبط صدای دستور، مجوز میکروفن لازم است",
                    Toast.LENGTH_LONG
            ).show();
        }
    }

    @Override
    protected void onPause() {
        if (recordingCommand != null) {
            stopRecordingIfNeeded(true);
        }
        stopPreview();
        super.onPause();
    }

    @Override
    protected void onDestroy() {
        stopRecordingIfNeeded(true);
        stopPreview();
        super.onDestroy();
    }

    private TextView badgeFor(String action) {
        int accent = accentFor(action);
        TextView badge = text(
                CommandStore.categoryLabel(action),
                12,
                accent,
                true
        );
        badge.setGravity(Gravity.CENTER);
        badge.setPadding(dp(9), dp(5), dp(9), dp(5));
        badge.setBackground(bg(
                Color.argb(
                        26,
                        Color.red(accent),
                        Color.green(accent),
                        Color.blue(accent)
                ),
                12
        ));
        return badge;
    }

    private int accentFor(String action) {
        if (CommandStore.ACTION_VIDEO.equals(action)
                || CommandStore.ACTION_AUDIO.equals(action)) return C_TEAL;

        if (CommandStore.ACTION_OPEN_APP.equals(action)
                || CommandStore.ACTION_OPEN_URL.equals(action)) return Color.rgb(29, 145, 165);

        if (CommandStore.ACTION_CALL_1.equals(action)
                || CommandStore.ACTION_CALL_2.equals(action)
                || CommandStore.ACTION_CALL_3.equals(action)) return C_PURPLE;

        if (CommandStore.ACTION_EMERGENCY.equals(action)) return C_RED;
        return C_BLUE;
    }

    private String notifyHint(String action, boolean checked) {
        String cat = CommandStore.categoryLabel(action);

        if (!checked) {
            return "دسته: " + cat + " • این دستور فقط اجرا/پخش می‌شود و SMS ارسال نمی‌کند.";
        }

        return "دسته: " + cat + " • متن نتیجه برای تمام شماره‌های اضطراری نیز SMS می‌شود.";
    }

    private TextView label(String value) {
        TextView t = text(value, 12.5f, C_MUTED, true);
        t.setPadding(dp(3), dp(9), dp(3), dp(4));
        return t;
    }

    private int actionIndex(String action) {
        for (int i = 0; i < ACTION_VALUES.length; i++) {
            if (ACTION_VALUES[i].equals(action)) return i;
        }
        return 0;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setHintTextColor(Color.rgb(143, 157, 173));
        e.setTextColor(C_TEXT);
        e.setTextSize(15);
        e.setSingleLine(false);
        e.setMinHeight(dp(54));
        e.setPadding(dp(12), dp(8), dp(12), dp(8));
        e.setBackground(strokeBg(
                Color.rgb(249, 251, 253),
                14,
                Color.rgb(218, 229, 239)
        ));
        return e;
    }

    private TextView text(String value, float size, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(value);
        t.setTextSize(size);
        t.setTextColor(color);
        if (bold) t.setTypeface(t.getTypeface(), android.graphics.Typeface.BOLD);
        return t;
    }

    private Button button(String value, int color) {
        Button b = new Button(this);
        b.setText(value);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(16);
        b.setBackground(bg(color, 18));
        LinearLayout.LayoutParams p =
                new LinearLayout.LayoutParams(-1, dp(56));
        p.setMargins(0, dp(5), 0, dp(5));
        b.setLayoutParams(p);
        return b;
    }

    private Button smallButton(String value, int color) {
        Button b = new Button(this);
        b.setText(value);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(14);
        b.setBackground(bg(color, 15));
        return b;
    }

    private int dp(int v) {
        return (int)(v * getResources().getDisplayMetrics().density + 0.5f);
    }
}
