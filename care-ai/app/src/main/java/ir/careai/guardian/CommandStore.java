package ir.careai.guardian;

import android.content.Context;
import android.content.SharedPreferences;

import java.io.File;

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
    public static final String ACTION_OPEN_APP = "OPEN_APP";
    public static final String ACTION_OPEN_URL = "OPEN_URL";
    public static final String ACTION_SMS = "SMS";

    private static final String KEY = "commands_json";

    private CommandStore() {}

    public static final class Command {
        public String id;
        public String question;
        public String output;
        public String action;
        public boolean enabled;
        public boolean notifyContacts;
        public String target;
        public String voiceFile;

        public Command(String id, String question, String output, String action, boolean enabled) {
            this(id, question, output, action, enabled, defaultNotify(action), "", "");
        }

        public Command(String id, String question, String output, String action, boolean enabled, boolean notifyContacts) {
            this(id, question, output, action, enabled, notifyContacts, "", "");
        }

        public Command(String id, String question, String output, String action, boolean enabled, boolean notifyContacts, String target) {
            this(id, question, output, action, enabled, notifyContacts, target, "");
        }

        public Command(String id, String question, String output, String action, boolean enabled, boolean notifyContacts, String target, String voiceFile) {
            this.id = id;
            this.question = question;
            this.output = output;
            this.action = action;
            this.enabled = enabled;
            this.notifyContacts = notifyContacts;
            this.target = target == null ? "" : target;
            this.voiceFile = voiceFile == null ? "" : voiceFile;
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
                                : defaultNotify(o.optString("action", ACTION_SPEAK)),
                        o.optString("target", ""),
                        o.optString("voiceFile", "")
                ));
            }
        } catch (Exception ignored) {}

        if (out.isEmpty()) {
            out = new ArrayList<>(defaults(c));
            save(c, out);
            p.edit().putBoolean("sms_command_added_v16", true).apply();
            return out;
        }

        // یک بار بعد از ارتقا، فرمان پیامک چشمی را به حساب‌های قدیمی اضافه می‌کنیم.
        if (!p.getBoolean("sms_command_added_v16", false)) {
            boolean hasSms = false;
            for (Command cmd : out) {
                if (ACTION_SMS.equals(cmd.action)) {
                    hasSms = true;
                    break;
                }
            }
            if (!hasSms) {
                out.add(new Command(
                        "eye_sms",
                        "می‌خواهی پیام بفرستی؟",
                        "صفحه نوشتن پیام را باز می‌کنم",
                        ACTION_SMS,
                        true,
                        false
                ));
                save(c, out);
            }
            p.edit().putBoolean("sms_command_added_v16", true).apply();
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
                o.put("target", cmd.target == null ? "" : cmd.target);
                o.put("voiceFile", cmd.voiceFile == null ? "" : cmd.voiceFile);
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

        out.add(new Command(
                "eye_sms",
                "می‌خواهی پیام بفرستی؟",
                "صفحه نوشتن پیام را باز می‌کنم",
                ACTION_SMS,
                true,
                false
        ));
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

    public static File voiceDirectory(Context c) {
        File dir = new File(c.getFilesDir(), "command_voice");
        if (!dir.exists()) dir.mkdirs();
        return dir;
    }

    public static File voiceFile(Context c, Command cmd) {
        if (cmd == null || cmd.voiceFile == null || cmd.voiceFile.trim().isEmpty()) {
            return null;
        }
        return new File(voiceDirectory(c), cmd.voiceFile);
    }

    public static File newVoiceFile(Context c, Command cmd) {
        String safeId = cmd == null || cmd.id == null
                ? String.valueOf(System.currentTimeMillis())
                : cmd.id.replaceAll("[^A-Za-z0-9_-]", "_");
        return new File(
                voiceDirectory(c),
                "voice_" + safeId + ".m4a"
        );
    }

    public static boolean hasVoice(Context c, Command cmd) {
        File f = voiceFile(c, cmd);
        return f != null && f.isFile() && f.length() > 0;
    }

    public static void deleteVoice(Context c, Command cmd) {
        File f = voiceFile(c, cmd);
        if (f != null && f.exists()) {
            try { f.delete(); } catch (Exception ignored) {}
        }
        if (cmd != null) cmd.voiceFile = "";
    }

    public static void deleteAllVoiceFiles(Context c) {
        File dir = voiceDirectory(c);
        File[] files = dir.listFiles();
        if (files == null) return;
        for (File f : files) {
            try { f.delete(); } catch (Exception ignored) {}
        }
    }

    public static boolean defaultNotify(String action) {
        return ACTION_SPEAK.equals(action) || ACTION_EMERGENCY.equals(action);
    }

    public static String categoryLabel(String action) {
        if (ACTION_VIDEO.equals(action) || ACTION_AUDIO.equals(action)) return "رسانه";
        if (ACTION_CALL_1.equals(action) || ACTION_CALL_2.equals(action) || ACTION_CALL_3.equals(action)) return "تماس";
        if (ACTION_EMERGENCY.equals(action)) return "اضطراری";
        if (ACTION_SMS.equals(action)) return "پیامک چشمی";
        if (ACTION_OPEN_APP.equals(action) || ACTION_OPEN_URL.equals(action)) return "اجرایی گوشی";
        return "اطلاع‌رسانی";
    }

    public static String actionLabel(String action) {
        if (ACTION_CALL_1.equals(action)) return "تماس با همراه ۱";
        if (ACTION_CALL_2.equals(action)) return "تماس با همراه ۲";
        if (ACTION_CALL_3.equals(action)) return "تماس با همراه ۳";
        if (ACTION_VIDEO.equals(action)) return "پخش ویدیو";
        if (ACTION_AUDIO.equals(action)) return "پخش آهنگ";
        if (ACTION_EMERGENCY.equals(action)) return "کمک فوری";
        if (ACTION_OPEN_APP.equals(action)) return "باز کردن اپلیکیشن";
        if (ACTION_OPEN_URL.equals(action)) return "باز کردن لینک / سایت";
        if (ACTION_SMS.equals(action)) return "نوشتن و ارسال پیامک با چشم";
        return "اعلام صوتی + پیامک";
    }
}
