package ir.careai.guardian;

import android.app.Activity;
import android.graphics.Color;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
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
            "اعلام صوتی + پیامک",
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

        commands = new ArrayList<>(CommandStore.load(this));
        render();
    }

    private void render() {
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(Color.rgb(244, 247, 251));

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(14), dp(18), dp(14), dp(28));

        TextView title = text("مدیریت دستورات Care AI", 24);
        title.setGravity(Gravity.CENTER);
        title.setPadding(0, 0, 0, dp(8));
        root.addView(title);

        TextView note = text(
                "هر دستور شامل سؤال، متن نتیجه و عمل واقعی است. بعد از تأیید بیمار، متن نتیجه هم با صدا پخش می‌شود و هم برای شماره‌های اضطراری پیامک می‌رود؛ سپس عمل انتخاب‌شده اجرا می‌شود.",
                14
        );
        note.setGravity(Gravity.CENTER);
        note.setPadding(dp(4), 0, dp(4), dp(14));
        root.addView(note);

        Button add = button("＋ افزودن دستور جدید");
        root.addView(add);
        add.setOnClickListener(v -> {
            commands.add(new CommandStore.Command(
                    UUID.randomUUID().toString(),
                    "سؤال جدید؟",
                    "متن نتیجه",
                    CommandStore.ACTION_SPEAK,
                    true
            ));
            CommandStore.save(this, commands);
            render();
        });

        list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        root.addView(list);

        for (int i = 0; i < commands.size(); i++) {
            addCommandCard(i, commands.get(i));
        }

        Button saveAll = button("ذخیره همه دستورات");
        root.addView(saveAll);
        saveAll.setOnClickListener(v -> {
            CommandStore.save(this, commands);
            Toast.makeText(this, "دستورات ذخیره شد", Toast.LENGTH_SHORT).show();
        });

        Button reset = button("بازگردانی دستورات پیش‌فرض");
        root.addView(reset);
        reset.setOnClickListener(v -> {
            commands = new ArrayList<>(CommandStore.defaults(this));
            CommandStore.save(this, commands);
            render();
        });

        Button back = button("بازگشت");
        root.addView(back);
        back.setOnClickListener(v -> finish());

        scroll.addView(root);
        setContentView(scroll);
    }

    private void addCommandCard(int index, CommandStore.Command cmd) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(12), dp(12), dp(12), dp(12));
        card.setBackgroundColor(Color.WHITE);

        LinearLayout.LayoutParams cp =
                new LinearLayout.LayoutParams(-1, LinearLayout.LayoutParams.WRAP_CONTENT);
        cp.setMargins(0, dp(8), 0, dp(8));
        card.setLayoutParams(cp);

        TextView header = text("دستور " + (index + 1), 18);
        card.addView(header);

        EditText q = input("سؤال برای بیمار");
        q.setText(cmd.question);
        card.addView(q);

        EditText out = input("متن خروجی صوتی و پیامک");
        out.setText(cmd.output);
        card.addView(out);

        Spinner spinner = new Spinner(this);
        ArrayAdapter<String> adapter = new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_dropdown_item,
                ACTION_LABELS
        );
        spinner.setAdapter(adapter);
        spinner.setSelection(actionIndex(cmd.action));
        card.addView(spinner);

        CheckBox enabled = new CheckBox(this);
        enabled.setText("فعال باشد");
        enabled.setChecked(cmd.enabled);
        card.addView(enabled);

        CheckBox notify = new CheckBox(this);
        notify.setText("ارسال پیامک نتیجه به شماره‌های اضطراری");
        notify.setChecked(cmd.notifyContacts);
        card.addView(notify);

        TextView classify = text(
                "دسته: " + CommandStore.categoryLabel(cmd.action),
                14
        );
        classify.setTextColor(Color.rgb(55, 115, 160));
        card.addView(classify);

        spinner.setOnItemSelectedListener(new android.widget.AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(android.widget.AdapterView<?> parent, View view, int position, long id) {
                String action = ACTION_VALUES[position];
                classify.setText("دسته: " + CommandStore.categoryLabel(action));

                // انتخاب نوع عمل، رفتار پیش‌فرض پیامک را هوشمند می‌کند.
                if (CommandStore.ACTION_VIDEO.equals(action)
                        || CommandStore.ACTION_AUDIO.equals(action)
                        || CommandStore.ACTION_CALL_1.equals(action)
                        || CommandStore.ACTION_CALL_2.equals(action)
                        || CommandStore.ACTION_CALL_3.equals(action)) {
                    notify.setChecked(false);
                } else if (CommandStore.ACTION_EMERGENCY.equals(action)
                        || CommandStore.ACTION_SPEAK.equals(action)) {
                    notify.setChecked(true);
                }
            }

            @Override
            public void onNothingSelected(android.widget.AdapterView<?> parent) {}
        });

        Button save = button("ذخیره این دستور");
        Button delete = button("حذف این دستور");
        card.addView(save);
        card.addView(delete);

        save.setOnClickListener(v -> {
            cmd.question = q.getText().toString().trim();
            cmd.output = out.getText().toString().trim();
            cmd.action = ACTION_VALUES[spinner.getSelectedItemPosition()];
            cmd.enabled = enabled.isChecked();
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
        });

        delete.setOnClickListener(v -> {
            commands.remove(cmd);
            CommandStore.save(this, commands);
            render();
        });

        list.addView(card);
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
        e.setTextSize(16);
        e.setSingleLine(false);
        e.setMinHeight(dp(54));
        return e;
    }

    private TextView text(String value, int size) {
        TextView t = new TextView(this);
        t.setText(value);
        t.setTextSize(size);
        t.setTextColor(Color.rgb(28, 37, 50));
        return t;
    }

    private Button button(String text) {
        Button b = new Button(this);
        b.setText(text);
        b.setAllCaps(false);
        b.setTextSize(16);
        LinearLayout.LayoutParams p =
                new LinearLayout.LayoutParams(-1, dp(56));
        p.setMargins(0, dp(5), 0, dp(5));
        b.setLayoutParams(p);
        return b;
    }

    private int dp(int v) {
        return (int)(v * getResources().getDisplayMetrics().density + 0.5f);
    }
}
