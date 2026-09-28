package ir.careai.guardian;

import android.app.Activity;
import android.app.DatePickerDialog;
import android.app.TimePickerDialog;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
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

import java.time.LocalDate;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;

public class CarePlanActivity extends Activity {

    private static final int BLUE = Color.rgb(29,120,220);
    private static final int TEAL = Color.rgb(18,185,170);
    private static final int TEXT = Color.rgb(19,49,83);
    private static final int MUTED = Color.rgb(91,111,134);
    private static final int BG = Color.rgb(244,249,253);
    private static final int RED = Color.rgb(220,70,78);

    private String kind;
    private LinearLayout list;
    private EditText title;
    private EditText detail;
    private Spinner repeat;
    private Spinner nurse;
    private CheckBox sms;
    private Button dateButton;
    private Button timeButton;

    private String selectedDate = LocalDate.now().toString();
    private int selectedHour = 8;
    private int selectedMinute = 0;
    private CareScheduleStore.Item editing = null;

    private final String[] repeatLabels = new String[]{
            "یکبار در تاریخ مشخص",
            "هر روز",
            "هر شنبه",
            "هر یکشنبه",
            "هر دوشنبه",
            "هر سه‌شنبه",
            "هر چهارشنبه",
            "هر پنجشنبه",
            "هر جمعه"
    };

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        getWindow().getDecorView().setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        kind = getIntent().getStringExtra("kind");
        if (!CareScheduleStore.KIND_CARE.equals(kind)) {
            kind = CareScheduleStore.KIND_MEDICINE;
        }
        render();
    }

    private GradientDrawable bg(int color, int radius) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(color);
        g.setCornerRadius(dp(radius));
        return g;
    }

    private TextView text(String s, float size, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(s);
        t.setTextSize(size);
        t.setTextColor(color);
        if (bold) t.setTypeface(t.getTypeface(), android.graphics.Typeface.BOLD);
        return t;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setTextSize(16);
        e.setTextColor(TEXT);
        e.setHintTextColor(Color.rgb(145,157,171));
        e.setBackground(bg(Color.WHITE, 15));
        e.setPadding(dp(13),dp(9),dp(13),dp(9));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1,dp(58));
        p.setMargins(0,dp(5),0,dp(5));
        e.setLayoutParams(p);
        return e;
    }

    private Button button(String s, int color) {
        Button b = new Button(this);
        b.setText(s);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(15.5f);
        b.setBackground(bg(color, 16));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-1,dp(54));
        p.setMargins(0,dp(5),0,dp(5));
        b.setLayoutParams(p);
        return b;
    }

    private void render() {
        ScrollView sc = new ScrollView(this);
        sc.setFillViewport(true);
        sc.setBackgroundColor(BG);

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(16),dp(20),dp(16),dp(30));

        String pageTitle = CareScheduleStore.KIND_MEDICINE.equals(kind)
                ? "داروها و زمان مصرف"
                : "مراقبت‌ها و نوبت‌ها";

        root.addView(text(pageTitle,26,TEXT,true));
        TextView note = text(
                CareScheduleStore.KIND_MEDICINE.equals(kind)
                        ? "نام دارو، مقدار، روز و ساعت را ثبت کن. در زمان مقرر برای پرستار منتخب پیامک ارسال می‌شود."
                        : "نوبت پزشک، فیزیوتراپی، جابه‌جایی بیمار یا هر مراقبت دیگری را ثبت کن و برای پرستار منتخب یادآوری بفرست.",
                13.5f,MUTED,false
        );
        note.setPadding(0,dp(5),0,dp(14));
        root.addView(note);

        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        form.setPadding(dp(13),dp(13),dp(13),dp(13));
        form.setBackground(bg(Color.WHITE,22));

        title = input(CareScheduleStore.KIND_MEDICINE.equals(kind)
                ? "نام دارو"
                : "عنوان مراقبت یا نوبت");
        detail = input(CareScheduleStore.KIND_MEDICINE.equals(kind)
                ? "مقدار / دوز، مثلاً ۱ قرص ۵ میلی‌گرم"
                : "توضیحات، مثلاً دکتر قلب - درمانگاه");
        form.addView(title);
        form.addView(detail);

        repeat = new Spinner(this);
        repeat.setAdapter(new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_dropdown_item,
                repeatLabels
        ));
        form.addView(repeat,new LinearLayout.LayoutParams(-1,dp(54)));

        dateButton = button("تاریخ: " + selectedDate, BLUE);
        dateButton.setOnClickListener(v -> pickDate());
        form.addView(dateButton);

        timeButton = button("ساعت: " + timeText(), TEAL);
        timeButton.setOnClickListener(v -> pickTime());
        form.addView(timeButton);

        String[] nurses = new String[3];
        for (int i=1;i<=3;i++) {
            String name = getSharedPreferences("careai",MODE_PRIVATE)
                    .getString("trusted_name"+i,"").trim();
            String num = getSharedPreferences("careai",MODE_PRIVATE)
                    .getString("trusted"+i,"").trim();
            nurses[i-1] = (name.isEmpty() ? "پرستار/همراه "+i : name)
                    + (num.isEmpty() ? "" : " — " + num);
        }

        nurse = new Spinner(this);
        nurse.setAdapter(new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_dropdown_item,
                nurses
        ));
        form.addView(nurse,new LinearLayout.LayoutParams(-1,dp(54)));

        sms = new CheckBox(this);
        sms.setText("در زمان مقرر برای پرستار منتخب SMS ارسال شود");
        sms.setTextColor(TEXT);
        sms.setChecked(true);
        form.addView(sms);

        Button save = button("ذخیره یادآوری", BLUE);
        save.setOnClickListener(v -> saveItem());
        form.addView(save);

        Button cancelEdit = button("لغو و ساخت مورد جدید", Color.rgb(106,122,138));
        cancelEdit.setOnClickListener(v -> clearForm());
        form.addView(cancelEdit);

        root.addView(form);

        TextView listTitle = text("موارد ثبت‌شده",20,TEXT,true);
        listTitle.setPadding(0,dp(18),0,dp(8));
        root.addView(listTitle);

        list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        root.addView(list);

        refreshList();

        Button back = button("بازگشت", Color.rgb(106,122,138));
        back.setOnClickListener(v -> finish());
        root.addView(back);

        sc.addView(root);
        setContentView(sc);
    }

    private void pickDate() {
        LocalDate d;
        try { d = LocalDate.parse(selectedDate); }
        catch (Exception e) { d = LocalDate.now(); }

        new DatePickerDialog(
                this,
                (view, year, month, day) -> {
                    selectedDate = String.format(
                            java.util.Locale.US,
                            "%04d-%02d-%02d",
                            year,month+1,day
                    );
                    dateButton.setText("تاریخ: " + selectedDate);
                },
                d.getYear(),
                d.getMonthValue()-1,
                d.getDayOfMonth()
        ).show();
    }

    private void pickTime() {
        new TimePickerDialog(
                this,
                (view,h,m) -> {
                    selectedHour=h;
                    selectedMinute=m;
                    timeButton.setText("ساعت: " + timeText());
                },
                selectedHour,
                selectedMinute,
                true
        ).show();
    }

    private String timeText() {
        return String.format(java.util.Locale.US,"%02d:%02d",selectedHour,selectedMinute);
    }

    private void saveItem() {
        String t = title.getText().toString().trim();
        if (t.isEmpty()) {
            Toast.makeText(this,"عنوان را وارد کنید",Toast.LENGTH_SHORT).show();
            return;
        }

        CareScheduleStore.Item x = editing == null
                ? new CareScheduleStore.Item()
                : editing;

        x.kind = kind;
        x.title = t;
        x.detail = detail.getText().toString().trim();
        x.date = selectedDate;
        x.hour = selectedHour;
        x.minute = selectedMinute;
        x.nurseSlot = nurse.getSelectedItemPosition()+1;
        x.sendSms = sms.isChecked();
        x.enabled = true;

        int r = repeat.getSelectedItemPosition();
        if (r == 0) {
            x.repeat = CareScheduleStore.REPEAT_ONCE;
        } else if (r == 1) {
            x.repeat = CareScheduleStore.REPEAT_DAILY;
        } else {
            x.repeat = CareScheduleStore.REPEAT_WEEKLY;
            x.dayOfWeek = calendarDayForPosition(r);
        }

        CareScheduleStore.upsert(this,x);
        CareAlarmScheduler.cancel(this,x.id);
        CareAlarmScheduler.schedule(this,x);

        Toast.makeText(this,"یادآوری ذخیره شد",Toast.LENGTH_SHORT).show();
        clearForm();
        refreshList();
    }

    private int calendarDayForPosition(int p) {
        switch (p) {
            case 2: return Calendar.SATURDAY;
            case 3: return Calendar.SUNDAY;
            case 4: return Calendar.MONDAY;
            case 5: return Calendar.TUESDAY;
            case 6: return Calendar.WEDNESDAY;
            case 7: return Calendar.THURSDAY;
            case 8: return Calendar.FRIDAY;
            default: return Calendar.SATURDAY;
        }
    }

    private int positionForItem(CareScheduleStore.Item x) {
        if (CareScheduleStore.REPEAT_ONCE.equals(x.repeat)) return 0;
        if (CareScheduleStore.REPEAT_DAILY.equals(x.repeat)) return 1;
        switch (x.dayOfWeek) {
            case Calendar.SATURDAY: return 2;
            case Calendar.SUNDAY: return 3;
            case Calendar.MONDAY: return 4;
            case Calendar.TUESDAY: return 5;
            case Calendar.WEDNESDAY: return 6;
            case Calendar.THURSDAY: return 7;
            case Calendar.FRIDAY: return 8;
            default: return 2;
        }
    }

    private void editItem(CareScheduleStore.Item x) {
        editing = x;
        title.setText(x.title);
        detail.setText(x.detail);
        selectedDate = x.date == null || x.date.isEmpty()
                ? LocalDate.now().toString()
                : x.date;
        selectedHour = x.hour;
        selectedMinute = x.minute;
        dateButton.setText("تاریخ: " + selectedDate);
        timeButton.setText("ساعت: " + timeText());
        repeat.setSelection(positionForItem(x));
        nurse.setSelection(Math.max(0,x.nurseSlot-1));
        sms.setChecked(x.sendSms);
    }

    private void clearForm() {
        editing = null;
        title.setText("");
        detail.setText("");
        repeat.setSelection(1);
        selectedDate = LocalDate.now().toString();
        selectedHour = 8;
        selectedMinute = 0;
        dateButton.setText("تاریخ: " + selectedDate);
        timeButton.setText("ساعت: " + timeText());
        sms.setChecked(true);
    }

    private void refreshList() {
        if (list == null) return;
        list.removeAllViews();

        List<CareScheduleStore.Item> all = CareScheduleStore.load(this);
        ArrayList<CareScheduleStore.Item> items = new ArrayList<>();
        for (CareScheduleStore.Item x : all) if (kind.equals(x.kind)) items.add(x);

        if (items.isEmpty()) {
            TextView empty = text("هنوز موردی ثبت نشده است.",14,MUTED,false);
            empty.setGravity(Gravity.CENTER);
            empty.setPadding(0,dp(16),0,dp(16));
            list.addView(empty);
            return;
        }

        for (CareScheduleStore.Item x : items) {
            LinearLayout card = new LinearLayout(this);
            card.setOrientation(LinearLayout.VERTICAL);
            card.setPadding(dp(12),dp(12),dp(12),dp(12));
            card.setBackground(bg(Color.WHITE,18));

            String timing = repeatLabels[positionForItem(x)] + " • "
                    + String.format(java.util.Locale.US,"%02d:%02d",x.hour,x.minute);
            if (CareScheduleStore.REPEAT_ONCE.equals(x.repeat)) {
                timing = x.date + " • " + timing.substring(timing.indexOf("•")+2);
            }

            card.addView(text(x.title,17,TEXT,true));
            if (x.detail != null && !x.detail.isEmpty()) {
                card.addView(text(x.detail,13.5f,MUTED,false));
            }
            card.addView(text(timing,13,TEAL,true));

            LinearLayout row = new LinearLayout(this);
            row.setOrientation(LinearLayout.HORIZONTAL);

            Button edit = button("ویرایش",BLUE);
            Button toggle = button(x.enabled ? "غیرفعال" : "فعال",Color.rgb(116,83,207));
            Button del = button("حذف",RED);

            LinearLayout.LayoutParams bp = new LinearLayout.LayoutParams(0,dp(48),1f);
            bp.setMargins(dp(3),dp(6),dp(3),0);
            row.addView(edit,bp);
            row.addView(toggle,new LinearLayout.LayoutParams(bp));
            row.addView(del,new LinearLayout.LayoutParams(bp));
            card.addView(row);

            edit.setOnClickListener(v -> editItem(x));
            toggle.setOnClickListener(v -> {
                x.enabled = !x.enabled;
                CareScheduleStore.upsert(this,x);
                CareAlarmScheduler.cancel(this,x.id);
                if (x.enabled) CareAlarmScheduler.schedule(this,x);
                refreshList();
            });
            del.setOnClickListener(v -> {
                CareAlarmScheduler.cancel(this,x.id);
                CareScheduleStore.delete(this,x.id);
                refreshList();
            });

            LinearLayout.LayoutParams cp = new LinearLayout.LayoutParams(-1,-2);
            cp.setMargins(0,0,0,dp(8));
            list.addView(card,cp);
        }
    }

    private int dp(int v) {
        return (int)(v*getResources().getDisplayMetrics().density+0.5f);
    }
}
