package ir.beytaneh.directyar;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.net.http.SslError;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONException;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class MainActivity extends Activity {
    private static final int BG = Color.rgb(12, 15, 26);
    private static final int PANEL = Color.rgb(26, 31, 47);
    private static final int WHITE = Color.rgb(247, 248, 255);
    private static final int MUTED = Color.rgb(164, 173, 195);
    private static final int PINK = Color.rgb(242, 77, 145);
    private static final int PURPLE = Color.rgb(130, 92, 244);
    private static final int REQUEST_UPLOAD = 721;
    private static final String INSTA = "https://www.instagram.com/";
    private static final String INBOX = "https://www.instagram.com/direct/inbox/";
    private static final String META = "https://business.facebook.com/latest/inbox/";
    private final List<String> defaults = new ArrayList<>();
    private SharedPreferences prefs;
    private LinearLayout root;
    private WebView web;
    private ValueCallback<Uri[]> uploadCallback;
    private String currentUrl = INSTA;
    private String currentTitle = "اینستاگرام";

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        prefs = getSharedPreferences("directyar_local", MODE_PRIVATE);
        defaults.add("سلام، وقت شما بخیر 🌷 لطفاً بفرمایید چه کمکی از دست ما برمی‌آید؟");
        defaults.add("لطفاً مدل دستگاه یا ECU، سال ساخت و شرح دقیق ایراد را ارسال کنید.");
        defaults.add("برای بررسی دقیق‌تر، لطفاً یک عکس واضح از قطعه یا خطای دستگاه بفرستید.");
        defaults.add("پیامتان دریافت شد. پس از بررسی، پاسخ دقیق را برایتان ارسال می‌کنیم.");
        getWindow().setStatusBarColor(BG);
        getWindow().setNavigationBarColor(BG);
        getWindow().getDecorView().setSystemUiVisibility(0);
        renderHome();
    }

    private int dp(float x) { return (int)(getResources().getDisplayMetrics().density * x + 0.5f); }
    private GradientDrawable bg(int color, int radius) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(color);
        d.setCornerRadius(dp(radius));
        return d;
    }
    private GradientDrawable bordered(int color, int radius, int border) {
        GradientDrawable d = bg(color, radius);
        d.setStroke(dp(1), border);
        return d;
    }
    private TextView text(String content, float size, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(content);
        t.setTextColor(color);
        t.setTextSize(size);
        if (bold) t.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        t.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        t.setTextDirection(View.TEXT_DIRECTION_RTL);
        return t;
    }
    private LinearLayout column() {
        LinearLayout c = new LinearLayout(this);
        c.setOrientation(LinearLayout.VERTICAL);
        c.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        return c;
    }
    private LinearLayout row() {
        LinearLayout r = new LinearLayout(this);
        r.setOrientation(LinearLayout.HORIZONTAL);
        r.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        r.setGravity(Gravity.CENTER_VERTICAL);
        return r;
    }
    private LinearLayout.LayoutParams lp(int w, int h) {
        return new LinearLayout.LayoutParams(w == -1 || w == -2 ? w : dp(w), h == -1 || h == -2 ? h : dp(h));
    }
    private void padding(View v, int h, int vertical) { v.setPadding(dp(h), dp(vertical), dp(h), dp(vertical)); }
    private void gap(LinearLayout c, int amount) { c.addView(new View(this), lp(1, amount)); }
    private void setRoot() {
        if (web != null) { web.stopLoading(); web.destroy(); web = null; }
        root = column();
        root.setBackgroundColor(BG);
        setContentView(root);
    }
    private void headline(LinearLayout target, String title, String subtitle) {
        TextView t = text(title, 26, WHITE, true);
        target.addView(t, lp(-1, -2));
        if (subtitle != null) {
            gap(target, 6);
            target.addView(text(subtitle, 13, MUTED, false), lp(-1, -2));
        }
    }
    private void section(LinearLayout parent, String label) {
        gap(parent, 22);
        parent.addView(text(label, 17, WHITE, true), lp(-1, 32));
        gap(parent, 8);
    }
    private void actionCard(LinearLayout parent, String emoji, String label, String desc, Runnable onClick) {
        LinearLayout item = column();
        padding(item, 16, 14);
        item.setBackground(bordered(PANEL, 17, Color.rgb(46, 52, 73)));
        TextView h = text(emoji + "   " + label + "   ←", 17, WHITE, true);
        item.addView(h, lp(-1, -2));
        gap(item, 5);
        item.addView(text(desc, 12, MUTED, false), lp(-1, -2));
        item.setOnClickListener(v -> onClick.run());
        LinearLayout.LayoutParams p = lp(-1, -2);
        p.bottomMargin = dp(10);
        parent.addView(item, p);
    }
    private LinearLayout header(String title, Runnable back) {
        LinearLayout h = row();
        padding(h, 16, 13);
        h.setBackgroundColor(BG);
        TextView t = text("◀  " + title, 17, WHITE, true);
        h.addView(t, new LinearLayout.LayoutParams(0, dp(38), 1));
        if (back != null) {
            t.setOnClickListener(v -> back.run());
        }
        return h;
    }
    private TextView pill(String label, int color) {
        TextView v = text(label, 13, WHITE, true);
        padding(v, 12, 9);
        v.setGravity(Gravity.CENTER);
        v.setBackground(bg(color, 12));
        return v;
    }
    private String username() { return prefs.getString("username", "beytaneh1"); }
    private String profileUrl() { return INSTA + username() + "/"; }

    private void renderHome() {
        setRoot();
        ScrollView sc = new ScrollView(this);
        sc.setFillViewport(true);
        root.addView(sc, new LinearLayout.LayoutParams(-1, 0, 1));
        LinearLayout page = column();
        padding(page, 20, 19);
        sc.addView(page);
        TextView branding = text("◉  دایرکت‌یار", 16, PINK, true);
        page.addView(branding, lp(-1, -2));
        gap(page, 20);
        headline(page, "مدیریت اینستاگرام", "پنل اختصاصی فارسی • بدون ذخیره رمز عبور");
        gap(page, 20);

        LinearLayout account = column();
        padding(account, 19, 18);
        GradientDrawable grad = new GradientDrawable(GradientDrawable.Orientation.TL_BR,
                new int[]{Color.rgb(77, 44, 121), Color.rgb(39, 56, 106)});
        grad.setCornerRadius(dp(20));
        account.setBackground(grad);
        account.addView(text("حساب انتخاب‌شده", 12, Color.rgb(224, 215, 249), false));
        gap(account, 8);
        account.addView(text("@" + username(), 22, WHITE, true));
        gap(account, 8);
        account.addView(text("برای ورود امن از Chrome استفاده کن؛ مرورگر داخلی هم در دسترس است.", 12, WHITE, false));
        gap(account, 14);
        LinearLayout buttons = row();
        TextView start = pill("ورود به اینستاگرام", PINK);
        start.setOnClickListener(v -> openInstagram(INSTA, "اینستاگرام"));
        buttons.addView(start);
        TextView chrome = pill("Chrome  ↗", Color.rgb(34, 42, 65));
        LinearLayout.LayoutParams c = lp(-2, -2); c.leftMargin = dp(10);
        chrome.setOnClickListener(v -> openChrome(INSTA));
        buttons.addView(chrome, c);
        account.addView(buttons);
        page.addView(account, lp(-1, -2));

        section(page, "دسترسی سریع");
        actionCard(page, "✉", "دایرکت", "نمایش و پاسخ‌دادن به پیام‌ها در سایت اینستاگرام", () -> openInstagram(INBOX, "دایرکت"));
        actionCard(page, "◈", "پست، ریلز و استوری", "انتشار از محیط رسمی اینستاگرام؛ بسته به امکانات نسخه وب", () -> openInstagram(INSTA, "انتشار محتوا"));
        actionCard(page, "▣", "نمایه و کامنت‌ها", "پروفایل، پست‌ها و دیدگاه‌های حساب", () -> openInstagram(profileUrl(), "پروفایل"));
        actionCard(page, "⚡", "پاسخ‌های آماده", "متن‌ها را ویرایش و کپی کن و در دایرکت قرار بده", this::showTemplates);
        actionCard(page, "⇄", "چند حساب", "میان‌بر حساب‌ها؛ جابه‌جایی واقعی از طریق خود اینستاگرام", this::showAccounts);
        actionCard(page, "▤", "صندوق Meta", "مدیریت Business Suite در صورت نیاز", () -> openInstagram(META, "Meta Business Suite"));

        section(page, "ابزارها");
        actionCard(page, "⚙", "تنظیمات و حریم خصوصی", "مرورگر پیش‌فرض، حذف داده‌های داخلی و اطلاعات نسخه", this::showSettings);
        bottomBar();
    }
    private void bottomBar() {
        LinearLayout bar = row();
        bar.setBackgroundColor(Color.rgb(22, 27, 42));
        TextView home = pill("⌂  خانه", Color.rgb(48, 56, 79));
        TextView inbox = pill("✉  دایرکت", Color.rgb(48, 56, 79));
        TextView responses = pill("⚡  پاسخ آماده", Color.rgb(48, 56, 79));
        home.setOnClickListener(v -> renderHome());
        inbox.setOnClickListener(v -> openInstagram(INBOX, "دایرکت"));
        responses.setOnClickListener(v -> showTemplates());
        bar.addView(home, new LinearLayout.LayoutParams(0, dp(42), 1));
        bar.addView(inbox, new LinearLayout.LayoutParams(0, dp(42), 1));
        bar.addView(responses, new LinearLayout.LayoutParams(0, dp(42), 1));
        padding(bar, 8, 7);
        root.addView(bar, lp(-1, -2));
    }

    private void openInstagram(String url, String title) {
        if (prefs.getBoolean("preferChrome", false)) openChrome(url);
        else openInternal(url, title);
    }
    private void openChrome(String url) {
        Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
        i.addCategory(Intent.CATEGORY_BROWSABLE);
        i.setPackage("com.android.chrome");
        try { startActivity(i); }
        catch (ActivityNotFoundException e) {
            i.setPackage(null);
            try { startActivity(i); }
            catch (ActivityNotFoundException e2) { Toast.makeText(this, "مرورگر نصب‌شده‌ای پیدا نشد", Toast.LENGTH_LONG).show(); }
        }
    }
    private boolean allowedHost(Uri u) {
        String scheme = u.getScheme(), host = u.getHost();
        if (scheme == null || host == null || !(scheme.equals("https") || scheme.equals("http"))) return false;
        host = host.toLowerCase(Locale.ROOT);
        return host.equals("instagram.com") || host.endsWith(".instagram.com")
                || host.equals("facebook.com") || host.endsWith(".facebook.com")
                || host.equals("meta.com") || host.endsWith(".meta.com")
                || host.equals("fbcdn.net") || host.endsWith(".fbcdn.net");
    }
    private void openInternal(String url, String title) {
        currentUrl = url; currentTitle = title;
        setRoot();
        LinearLayout h = row();
        padding(h, 10, 9);
        TextView back = pill("⌂", Color.rgb(42, 50, 75));
        back.setOnClickListener(v -> renderHome());
        h.addView(back, lp(48, 40));
        TextView t = text(title, 16, WHITE, true);
        t.setGravity(Gravity.CENTER);
        h.addView(t, new LinearLayout.LayoutParams(0, dp(40), 1));
        TextView browser = pill("Chrome ↗", PURPLE);
        browser.setOnClickListener(v -> openChrome(web != null ? web.getUrl() == null ? url : web.getUrl() : url));
        h.addView(browser);
        root.addView(h);
        ProgressBar progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setMax(100);
        root.addView(progress, lp(-1, 3));
        web = new WebView(this);
        WebSettings st = web.getSettings();
        st.setJavaScriptEnabled(true);
        st.setDomStorageEnabled(true);
        st.setAllowFileAccess(false);
        st.setAllowContentAccess(true);
        st.setJavaScriptCanOpenWindowsAutomatically(false);
        st.setSupportMultipleWindows(false);
        st.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        st.setBuiltInZoomControls(true);
        st.setDisplayZoomControls(false);
        st.setMediaPlaybackRequiresUserGesture(true);
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, true);
        web.setBackgroundColor(Color.WHITE);
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) {
                if (!req.isForMainFrame()) return false;
                Uri u = req.getUrl();
                if (allowedHost(u)) return false;
                if ("http".equals(u.getScheme()) || "https".equals(u.getScheme())) openChrome(u.toString());
                return true;
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.cancel();
                Toast.makeText(MainActivity.this, "خطای امنیتی اتصال؛ صفحه باز نشد", Toast.LENGTH_LONG).show();
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView view, int newProgress) {
                progress.setProgress(newProgress);
                progress.setVisibility(newProgress >= 100 ? View.GONE : View.VISIBLE);
            }
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (uploadCallback != null) uploadCallback.onReceiveValue(null);
                uploadCallback = callback;
                try { startActivityForResult(params.createIntent(), REQUEST_UPLOAD); return true; }
                catch (Exception e) { uploadCallback = null; Toast.makeText(MainActivity.this, "انتخاب فایل در دسترس نیست", Toast.LENGTH_SHORT).show(); return false; }
            }
        });
        web.setDownloadListener((downloadUrl, userAgent, contentDisposition, mimeType, length) -> openChrome(downloadUrl));
        root.addView(web, new LinearLayout.LayoutParams(-1, 0, 1));
        LinearLayout nav = row();
        padding(nav, 7, 8);
        String[] names = {"→ عقب", "↻ تازه‌سازی", "⌂ خانه", "✉ دایرکت"};
        Runnable[] ops = {() -> { if (web != null && web.canGoBack()) web.goBack(); else renderHome(); },
                () -> {if (web != null) web.reload();}, this::renderHome,
                () -> {if (web != null) web.loadUrl(INBOX);} };
        for (int k=0; k<names.length; k++) {
            TextView b = pill(names[k], Color.rgb(43, 49, 68));
            final int ix = k;
            b.setOnClickListener(v -> ops[ix].run());
            nav.addView(b, new LinearLayout.LayoutParams(0, dp(45), 1));
        }
        root.addView(nav, lp(-1, -2));
        web.loadUrl(url);
    }
    @Override protected void onActivityResult(int request, int result, Intent data) {
        super.onActivityResult(request, result, data);
        if (request == REQUEST_UPLOAD && uploadCallback != null) {
            uploadCallback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result, data));
            uploadCallback = null;
        }
    }

    private JSONArray templateArray() {
        try {
            String s = prefs.getString("templates", null);
            if (s != null) return new JSONArray(s);
        } catch (JSONException ignored) { }
        JSONArray a = new JSONArray();
        for (String x : defaults) a.put(x);
        return a;
    }
    private void saveTemplates(JSONArray a) { prefs.edit().putString("templates", a.toString()).apply(); }
    private void showTemplates() {
        JSONArray a = templateArray();
        String[] items = new String[a.length() + 1];
        for (int i=0; i<a.length(); i++) {
            String s = a.optString(i);
            items[i] = s.length() > 80 ? s.substring(0, 80) + "…" : s;
        }
        items[items.length - 1] = "＋ افزودن پاسخ جدید";
        new AlertDialog.Builder(this)
                .setTitle("پاسخ‌های آماده")
                .setItems(items, (d, which) -> {
                    if (which == a.length()) editTemplate(-1);
                    else new AlertDialog.Builder(this).setTitle("پاسخ آماده")
                            .setMessage(a.optString(which))
                            .setPositiveButton("کپی متن", (d2, w) -> {
                                ClipboardManager c = (ClipboardManager)getSystemService(CLIPBOARD_SERVICE);
                                c.setPrimaryClip(ClipData.newPlainText("پاسخ آماده", a.optString(which)));
                                Toast.makeText(this, "متن کپی شد؛ در دایرکت جای‌گذاری کن", Toast.LENGTH_SHORT).show();
                            })
                            .setNeutralButton("ویرایش", (d2, w) -> editTemplate(which))
                            .setNegativeButton("حذف", (d2, w) -> confirmDeleteTemplate(which))
                            .show();
                }).setNegativeButton("بستن", null).show();
    }
    private void confirmDeleteTemplate(int index) {
        new AlertDialog.Builder(this).setTitle("حذف پاسخ آماده")
                .setMessage("این متن از فهرست محلی حذف شود؟")
                .setPositiveButton("حذف", (d, w) -> {
                    JSONArray a=templateArray(), b=new JSONArray();
                    for(int i=0;i<a.length();i++) if(i!=index)b.put(a.optString(i));
                    saveTemplates(b); showTemplates();
                }).setNegativeButton("انصراف", null).show();
    }
    private void editTemplate(int index) {
        JSONArray a = templateArray();
        EditText field = new EditText(this);
        field.setText(index >= 0 ? a.optString(index) : "");
        field.setMinLines(3);
        field.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
        new AlertDialog.Builder(this).setTitle(index < 0 ? "پاسخ جدید" : "ویرایش پاسخ")
                .setView(field).setPositiveButton("ذخیره", (d, w) -> {
                    String s = field.getText().toString().trim();
                    if (s.isEmpty()) return;
                    JSONArray output = new JSONArray();
                    for (int i=0;i<a.length();i++) output.put(index==i ? s : a.optString(i));
                    if (index < 0) output.put(s);
                    saveTemplates(output); showTemplates();
                }).setNegativeButton("انصراف", null).show();
    }

    private JSONArray accountsArray() {
        try { return new JSONArray(prefs.getString("accounts", "[\"beytaneh1\"]")); }
        catch (JSONException e) { return new JSONArray().put("beytaneh1"); }
    }
    private void showAccounts() {
        JSONArray a = accountsArray();
        String[] entries = new String[a.length()+1];
        for (int i=0;i<a.length();i++) entries[i]="@" + a.optString(i) + (a.optString(i).equals(username()) ? "  ✓" : "");
        entries[a.length()] = "＋ افزودن نام حساب";
        new AlertDialog.Builder(this).setTitle("میان‌بر حساب‌ها")
                .setMessage("این فهرست فقط میان‌بر محلی است. ورود و جابه‌جایی حساب از سایت رسمی اینستاگرام انجام می‌شود.")
                .setItems(entries, (d, item) -> {
                    if (item == a.length()) addAccount();
                    else {
                        String selected=a.optString(item);
                        prefs.edit().putString("username", selected).apply();
                        renderHome();
                        openInstagram("https://www.instagram.com/" + selected + "/", "پروفایل");
                    }
                }).setNegativeButton("بستن", null).show();
    }
    private void addAccount() {
        EditText f = new EditText(this);
        f.setSingleLine(true);
        f.setHint("username");
        new AlertDialog.Builder(this).setTitle("افزودن نام حساب")
                .setView(f).setPositiveButton("افزودن", (d,w) -> {
                    String name=f.getText().toString().trim().replaceFirst("^@", "").toLowerCase(Locale.ROOT);
                    if (!name.matches("[a-z0-9._]{1,30}")) { Toast.makeText(this,"نام کاربری نامعتبر است",Toast.LENGTH_SHORT).show(); return; }
                    JSONArray a=accountsArray();
                    for(int i=0;i<a.length();i++) if(name.equals(a.optString(i))) { showAccounts();return; }
                    a.put(name);
                    prefs.edit().putString("accounts", a.toString()).apply();
                    showAccounts();
                }).setNegativeButton("انصراف", null).show();
    }
    private void showSettings() {
        String[] options = {"باز کردن لینک‌ها با Chrome: " + (prefs.getBoolean("preferChrome", false) ? "فعال" : "غیرفعال"),
                "ویرایش نام حساب فعال", "پاک‌کردن کوکی‌های مرورگر داخلی", "حریم خصوصی و محدودیت‌ها"};
        new AlertDialog.Builder(this).setTitle("تنظیمات دایرکت‌یار")
                .setItems(options, (d, option) -> {
                    switch(option) {
                        case 0:
                            boolean next=!prefs.getBoolean("preferChrome", false);
                            prefs.edit().putBoolean("preferChrome", next).apply();
                            Toast.makeText(this,next ? "اکنون Chrome پیش‌فرض است" : "اکنون مرورگر داخلی پیش‌فرض است",Toast.LENGTH_SHORT).show();
                            showSettings(); break;
                        case 1: editUsername(); break;
                        case 2: confirmClearCookies(); break;
                        case 3: new AlertDialog.Builder(this).setTitle("حریم خصوصی")
                                .setMessage("دایرکت‌یار نسخه ۱.۰: بدون API، بدون سرور، بدون دریافت یا ذخیره رمز. در حالت مرورگر داخلی، کوکی‌های ورود فقط در Android WebView نگهداری می‌شوند. ارسال خودکار پیام و انتشار خودکار در این نسخه وجود ندارد. امکان ورود یا انتشار در نسخه وب تابع محدودیت‌های رسمی اینستاگرام است.")
                                .setPositiveButton("متوجه شدم", null).show(); break;
                    }
                }).setNegativeButton("بستن", null).show();
    }
    private void editUsername() {
        EditText f = new EditText(this);
        f.setSingleLine(true);
        f.setText(username());
        new AlertDialog.Builder(this).setTitle("نام کاربری اصلی")
                .setView(f).setPositiveButton("ذخیره", (d,w) -> {
                    String name=f.getText().toString().trim().replaceFirst("^@", "").toLowerCase(Locale.ROOT);
                    if(name.matches("[a-z0-9._]{1,30}")) { prefs.edit().putString("username", name).apply(); renderHome(); }
                    else Toast.makeText(this,"نام کاربری نامعتبر است",Toast.LENGTH_SHORT).show();
                }).setNegativeButton("انصراف", null).show();
    }
    private void confirmClearCookies() {
        new AlertDialog.Builder(this).setTitle("پاک‌کردن داده‌های ورود داخلی")
                .setMessage("با این کار کوکی‌های همه سایت‌های بازشده در مرورگر داخلی اپ حذف می‌شوند. ورود به Chrome و پاسخ‌های آماده حذف نمی‌شوند.")
                .setPositiveButton("پاک شود", (d,w) -> {
                    CookieManager.getInstance().removeAllCookies(value -> {
                        CookieManager.getInstance().flush();
                        Toast.makeText(this,"کوکی‌های داخلی حذف شدند",Toast.LENGTH_SHORT).show();
                    });
                }).setNegativeButton("انصراف", null).show();
    }
    @Override public void onBackPressed() {
        if (web != null && web.canGoBack()) web.goBack();
        else if (web != null) renderHome();
        else super.onBackPressed();
    }
    @Override protected void onDestroy() {
        if (web != null) { web.stopLoading(); web.destroy(); web = null; }
        super.onDestroy();
    }
}