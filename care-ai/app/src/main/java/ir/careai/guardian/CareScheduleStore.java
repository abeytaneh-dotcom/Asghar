package ir.careai.guardian;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public final class CareScheduleStore {
    public static final String KIND_MEDICINE = "medicine";
    public static final String KIND_CARE = "care";

    public static final String REPEAT_ONCE = "once";
    public static final String REPEAT_DAILY = "daily";
    public static final String REPEAT_WEEKLY = "weekly";

    private static final String KEY = "care_schedules_json";

    private CareScheduleStore() {}

    public static final class Item {
        public String id;
        public String kind;
        public String title;
        public String detail;
        public String repeat;
        public String date;
        public int dayOfWeek;
        public int hour;
        public int minute;
        public int nurseSlot;
        public boolean sendSms;
        public boolean enabled;

        public Item() {
            id = UUID.randomUUID().toString();
            kind = KIND_MEDICINE;
            title = "";
            detail = "";
            repeat = REPEAT_DAILY;
            date = "";
            dayOfWeek = java.util.Calendar.SATURDAY;
            hour = 8;
            minute = 0;
            nurseSlot = 1;
            sendSms = true;
            enabled = true;
        }
    }

    public static List<Item> load(Context c) {
        SharedPreferences p = c.getSharedPreferences("careai", Context.MODE_PRIVATE);
        String raw = p.getString(KEY, "");
        ArrayList<Item> out = new ArrayList<>();
        if (raw == null || raw.trim().isEmpty()) return out;

        try {
            JSONArray arr = new JSONArray(raw);
            for (int i = 0; i < arr.length(); i++) {
                JSONObject o = arr.getJSONObject(i);
                Item x = new Item();
                x.id = o.optString("id", UUID.randomUUID().toString());
                x.kind = o.optString("kind", KIND_MEDICINE);
                x.title = o.optString("title", "");
                x.detail = o.optString("detail", "");
                x.repeat = o.optString("repeat", REPEAT_DAILY);
                x.date = o.optString("date", "");
                x.dayOfWeek = o.optInt("dayOfWeek", java.util.Calendar.SATURDAY);
                x.hour = o.optInt("hour", 8);
                x.minute = o.optInt("minute", 0);
                x.nurseSlot = Math.max(1, Math.min(3, o.optInt("nurseSlot", 1)));
                x.sendSms = o.optBoolean("sendSms", true);
                x.enabled = o.optBoolean("enabled", true);
                out.add(x);
            }
        } catch (Exception ignored) {}
        return out;
    }

    public static void save(Context c, List<Item> items) {
        JSONArray arr = new JSONArray();
        try {
            for (Item x : items) {
                JSONObject o = new JSONObject();
                o.put("id", x.id);
                o.put("kind", x.kind);
                o.put("title", x.title);
                o.put("detail", x.detail);
                o.put("repeat", x.repeat);
                o.put("date", x.date);
                o.put("dayOfWeek", x.dayOfWeek);
                o.put("hour", x.hour);
                o.put("minute", x.minute);
                o.put("nurseSlot", x.nurseSlot);
                o.put("sendSms", x.sendSms);
                o.put("enabled", x.enabled);
                arr.put(o);
            }
        } catch (Exception ignored) {}

        c.getSharedPreferences("careai", Context.MODE_PRIVATE)
                .edit()
                .putString(KEY, arr.toString())
                .apply();
    }

    public static Item find(Context c, String id) {
        for (Item x : load(c)) {
            if (x.id.equals(id)) return x;
        }
        return null;
    }

    public static void upsert(Context c, Item item) {
        List<Item> items = load(c);
        boolean replaced = false;
        for (int i = 0; i < items.size(); i++) {
            if (items.get(i).id.equals(item.id)) {
                items.set(i, item);
                replaced = true;
                break;
            }
        }
        if (!replaced) items.add(item);
        save(c, items);
    }

    public static void delete(Context c, String id) {
        List<Item> items = load(c);
        items.removeIf(x -> x.id.equals(id));
        save(c, items);
    }
}
