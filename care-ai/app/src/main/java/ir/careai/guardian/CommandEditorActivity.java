package ir.careai.guardian;

import android.app.Activity;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
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
            CommandStore.ACTION_EMERGENCY
    };

    private static final String[] ACTION_LABELS = new String[] {
            "اعلام صوتی",
            "تماس با همراه ۱",
            "تماس با همراه ۲",
            "تماس با همراه ۳",
            "پخش ویدیو",
            "پخش آهنگ",
            "کمک فوری"
    };

    private LinearLayout list;
    private List<CommandStore.Command> commands;

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
                "دستورهای تماس، موسیقی و ویدیو به‌صورت پیش‌فرض فقط اجرا می‌شوند. برای دستورهای نیاز بیمار یا اضطراری می‌توانید ارسال SMS را فعال کنید.",
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
            commands.remove(cmd);
            CommandStore.save(this, commands);
            render();
        });

        list.addView(card);
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
