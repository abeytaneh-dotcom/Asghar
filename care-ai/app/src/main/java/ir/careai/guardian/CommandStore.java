package ir.careai.guardian;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;

public final class CommandStore {
    public static final String ACTION_SPEAK = "SPEAK";
    public static final String ACTION_CALL_1 = "CALL_1";
    public static final String ACTION_CALL_2 = "CALL_2";
    public static final String ACTION_CALL_3 = "CALL_3";
    public static final String ACTION_VIDEO = "VIDEO";
    public static final String ACTION_AUDIO = "AUDIO";
    public static final String ACTION_EMERGENCY = "EMERGENCY";

    private static final String KEY = "commands_json";

    private CommandStore() {}

    public static final class Command {
        public String id;
        public String question;
        public String output;
        public String action;
        public boolean enabled;
        public boolean notifyContacts;

        public Command(String id, String question, String output, String action, boolean enabled) {
            this(id, question, output, action, enabled, defaultNotify(action));
        }

        public Command(String id, String question, String output, String action, boolean enabled, boolean notifyContacts) {
            this.id = id;
            this.question = question;
            this.output = output;
            this.action = action;
            this.enabled = enabled;
            this.notifyContacts = notifyContacts;
        }
    }

    public static List<Command> load(Context c) {
        SharedPreferences p = c.getSharedPreferences("careai", Context.MODE_PRIVATE);
        String raw = p.getString(KEY, "");
        if (raw == null || raw.trim().isEmpty()) {
            List<Command> defaults = defaults(c);
            save(c, defaults);
            return defaults;
        }

        ArrayList<Command> out = new ArrayList<>();
        try {
            JSONArray arr = new JSONArray(raw);
            for (int i = 0; i < arr.length(); i++) {
                JSONObject o = arr.getJSONObject(i);
                out.add(new Command(
                        o.optString("id", "cmd_" + i),
                        o.optString("question", ""),
                        o.optString("output", ""),
                        o.optString("action", ACTION_SPEAK),
                        o.optBoolean("enabled", true),
                        o.has("notifyContacts")
                                ? o.optBoolean("notifyContacts", true)
                                : defaultNotify(o.optString("action", ACTION_SPEAK))
                ));
            }
        } catch (Exception ignored) {}

        if (out.isEmpty()) {
            out = new ArrayList<>(defaults(c));
            save(c, out);
        }
        return out;
    }

    public static void save(Context c, List<Command> commands) {
        JSONArray arr = new JSONArray();
        try {
            for (Command cmd : commands) {
                JSONObject o = new JSONObject();
                o.put("id", cmd.id);
                o.put("question", cmd.question);
                o.put("output", cmd.output);
                o.put("action", cmd.action);
                o.put("enabled", cmd.enabled);
                o.put("notifyContacts", cmd.notifyContacts);
                arr.put(o);
            }
        } catch (Exception ignored) {}

        c.getSharedPreferences("careai", Context.MODE_PRIVATE)
                .edit()
                .putString(KEY, arr.toString())
                .apply();
    }

    public static List<Command> defaults(Context c) {
        SharedPreferences p = c.getSharedPreferences("careai", Context.MODE_PRIVATE);
        ArrayList<Command> out = new ArrayList<>();

        for (int i = 1; i <= 3; i++) {
            String number = p.getString("trusted" + i, "").trim();
            if (!number.isEmpty()) {
                String name = p.getString("trusted_name" + i, "").trim();
                if (name.isEmpty()) name = "همراه " + i;
                out.add(new Command(
                        "call_" + i,
                        "می‌خواهی به " + name + " زنگ بزنم؟",
                        "می‌خواهم با " + name + " تماس بگیرم",
                        i == 1 ? ACTION_CALL_1 : (i == 2 ? ACTION_CALL_2 : ACTION_CALL_3),
                        true,
                        false
                ));
            }
        }

        out.add(new Command("video", "می‌خواهی برات ویدیو پخش کنم؟", "ویدیو پخش می‌کنم", ACTION_VIDEO, true, false));
        out.add(new Command("audio", "می‌خواهی برات آهنگ پخش کنم؟", "آهنگ پخش می‌کنم", ACTION_AUDIO, true, false));
        out.add(new Command("water", "آب می‌خواهی؟", "آب می‌خواهم", ACTION_SPEAK, true));
        out.add(new Command("food", "غذا می‌خواهی؟", "غذا می‌خواهم", ACTION_SPEAK, true));
        out.add(new Command("pain", "درد داری؟", "درد دارم", ACTION_SPEAK, true));
        out.add(new Command("toilet", "دستشویی می‌خواهی؟", "دستشویی می‌خواهم", ACTION_SPEAK, true));
        out.add(new Command("cold", "سردت است؟", "سردم است", ACTION_SPEAK, true));
        out.add(new Command("hot", "گرمت است؟", "گرمم است", ACTION_SPEAK, true));
        out.add(new Command("help", "کمک فوری می‌خواهی؟", "کمک فوری می‌خواهم", ACTION_EMERGENCY, true, true));
        return out;
    }

    public static boolean defaultNotify(String action) {
        return ACTION_SPEAK.equals(action) || ACTION_EMERGENCY.equals(action);
    }

    public static String categoryLabel(String action) {
        if (ACTION_VIDEO.equals(action) || ACTION_AUDIO.equals(action)) return "رسانه";
        if (ACTION_CALL_1.equals(action) || ACTION_CALL_2.equals(action) || ACTION_CALL_3.equals(action)) return "تماس";
        if (ACTION_EMERGENCY.equals(action)) return "اضطراری";
        return "اطلاع‌رسانی";
    }

    public static String actionLabel(String action) {
        if (ACTION_CALL_1.equals(action)) return "تماس با همراه ۱";
        if (ACTION_CALL_2.equals(action)) return "تماس با همراه ۲";
        if (ACTION_CALL_3.equals(action)) return "تماس با همراه ۳";
        if (ACTION_VIDEO.equals(action)) return "پخش ویدیو";
        if (ACTION_AUDIO.equals(action)) return "پخش آهنگ";
        if (ACTION_EMERGENCY.equals(action)) return "کمک فوری";
        return "اعلام صوتی + پیامک";
    }
}
