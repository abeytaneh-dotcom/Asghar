package ir.careai.guardian;

public final class EyeSmsSession {
    public static final int STAGE_TEXT = 0;
    public static final int STAGE_NUMBER = 1;

    public static final String[] LETTERS = {
            "ا","آ","ب","پ","ت","ث","ج","چ","ح","خ",
            "د","ذ","ر","ز","ژ","س","ش","ص","ض","ط",
            "ظ","ع","غ","ف","ق","ک","گ","ل","م","ن",
            "و","ه","ی","فاصله"
    };

    public static final String[] DIGITS = {
            "0","1","2","3","4","5","6","7","8","9"
    };

    public final StringBuilder text = new StringBuilder();
    public final StringBuilder number = new StringBuilder();

    public int stage = STAGE_TEXT;
    public int index = 0;
    public String fixedNumber = "";

    public String[] symbols() {
        return stage == STAGE_TEXT ? LETTERS : DIGITS;
    }

    public String current() {
        String[] s = symbols();
        return s[index % s.length];
    }

    public void next() {
        String[] s = symbols();
        index = (index + 1) % s.length;
    }

    public void selectCurrent() {
        String symbol = current();
        if (stage == STAGE_TEXT) {
            text.append("فاصله".equals(symbol) ? " " : symbol);
        } else {
            number.append(symbol);
        }
        next();
    }

    public String recipient() {
        return fixedNumber == null || fixedNumber.trim().isEmpty()
                ? number.toString()
                : fixedNumber.trim();
    }
}
