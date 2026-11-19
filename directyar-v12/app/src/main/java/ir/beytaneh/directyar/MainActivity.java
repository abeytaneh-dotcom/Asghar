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
import android.graphics.drawable.Drawable;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.WebResourceError;
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
    private static final String VERSION = "۱.۲";
    private static final String INSTA = "https://www.instagram.com/";
    private static final String INBOX = "https://www.instagram.com/direct/inbox/";
    private static final String META = "https://business.facebook.com/latest/inbox/";
    private static final String META_CONTENT = "https://business.facebook.com/latest/content/";
    private final List<String> defaults = new ArrayList<>();
    private SharedPreferences prefs;
    private LinearLayout root;
    private WebView web;
    private ValueCallback<Uri[]> uploadCallback;
    private String currentUrl = INSTA;
    private String currentTitle = "اینستاگرام";
    private TextView browserTitle;
    private TextView browserAccount;
    private boolean showingBrowser = false;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        prefs = getSharedPreferences("directyar_local", MODE_PRIVATE);
        defaults.add("سلام، وقت شما بخیر 🌷 لطفاً بفرمایید چه کمکی از دست ما برمی‌آید؟");
        defaults.add("لطفاً مدل دستگاه یا ECU، سال ساخت و شرح دقیق ایراد را ارسال کنید.");
        defaults.add("برای بررسی دقیق‌تر، لطفاً یک عکس واضح از قطعه یا خطای دستگاه بفرستید.");
        defaults.add("پیامتان دریافت شد. پس از بررسی، پاسخ دقیق را برایتان ارسال می‌کنیم.");
        defaults.add("برای اعلام هزینه، لطفاً عکس برد، شماره قطعه یا مدل دقیق را بفرستید.");
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
        showingBrowser = false;
        browserTitle = null;
        browserAccount = null;
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

    private void addOutlineNote(LinearLayout parent, String message) {
        TextView note = text(message, 12, MUTED, false);
        padding(note, 13, 12);
        note.setBackground(bordered(Color.rgb(19, 25, 40), 12, Color.rgb(51, 60, 83)));
        parent.addView(note, lp(-1, -2));
    }

    private void quickTile(LinearLayout row, String icon, String name, String caption, Runnable action) {
        LinearLayout tile = column();
        padding(tile, 14, 15);
        tile.setBackground(bordered(PANEL, 18, Color.rgb(47, 54, 80)));
        TextView glyph = text(icon, 23, Color.rgb(200, 176, 255), true);
        tile.addView(glyph, lp(-1, 32));
        gap(tile, 10);
        tile.addView(text(name, 15, WHITE, true), lp(-1, 24));
        gap(tile, 4);
        tile.addView(text(caption, 11, MUTED, false), lp(-1, -2));
        tile.setOnClickListener(v -> action.run());
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, dp(126), 1);
        params.leftMargin = dp(6);
        params.rightMargin = dp(6);
        row.addView(tile, params);
    }

    private void renderHome() {
        setRoot();
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));
        LinearLayout page = column();
        padding(page, 15, 20);
        scroll.addView(page);

        LinearLayout wordmark = row();
        TextView brand = text("◉  دایرکت‌یار", 19, WHITE, true);
        wordmark.addView(brand, new LinearLayout.LayoutParams(0, dp(34), 1));
        TextView ver = pill("نسخه " + VERSION, Color.rgb(49, 39, 79));
        ver.setTextColor(Color.rgb(224, 209, 255));
        wordmark.addView(ver);
        page.addView(wordmark);
        gap(page, 17);
        headline(page, "مدیریت اینستاگرام", "سریع، فارسی و بدون نیاز به دریافت رمز عبور");
        gap(page, 16);

        LinearLayout account = column();
        padding(account, 17, 19);
        GradientDrawable gradient = new GradientDrawable(GradientDrawable.Orientation.TL_BR,
                new int[]{Color.rgb(70, 41, 119), Color.rgb(36, 53, 96)});
        gradient.setCornerRadius(dp(20));
        account.setBackground(gradient);
        account.addView(text("میان‌بر پروفایل", 12, Color.rgb(224, 215, 249), false));
        gap(account, 7);
        account.addView(text("@" + username(), 23, WHITE, true));
        gap(account, 4);
        account.addView(text("حساب واقعی واردشده را اینستاگرام تعیین می‌کند.", 12, WHITE, false));
        gap(account, 17);
        LinearLayout actions = row();
        TextView primary = pill("بازکردن دایرکت  ←", PINK);
        primary.setOnClickListener(v -> openInstagram(INBOX, "دایرکت"));
        actions.addView(primary);
        TextView other = pill("مدیریت حساب‌ها", Color.rgb(33, 41, 65));
        LinearLayout.LayoutParams otherParams = lp(-2, -2);
        otherParams.rightMargin = dp(10);
        other.setOnClickListener(v -> showAccounts());
        actions.addView(other, otherParams);
        account.addView(actions);
        page.addView(account, lp(-1, -2));

        section(page, "دسترسی سریع");
        LinearLayout tiles1 = row();
        quickTile(tiles1, "✉", "صندوق دایرکت", "Primary / General / Requests", () -> openInstagram(INBOX, "دایرکت"));
        quickTile(tiles1, "⚡", "پاسخ آماده", "ویرایش، حذف و کپی سریع", this::showTemplates);
        page.addView(tiles1, lp(-1, -2));
        gap(page, 12);
        LinearLayout tiles2 = row();
        quickTile(tiles2, "⊞", "انتشار محتوا", "آماده‌سازی کپشن و انتشار دستی", this::showPublishing);
        quickTile(tiles2, "♙", "کامنت‌ها", "دسترسی به صندوق نظرات Meta", this::showComments);
        page.addView(tiles2, lp(-1, -2));

        section(page, "پاسخ به مشتری");
        actionCard(page, "✦", "پیشنهاد پاسخ با تأیید", "پیام مشتری را وارد کن؛ پیشنهاد را بازبینی، ویرایش و کپی کن", this::showSmartReply);
        section(page, "مدیریت و تنظیمات");
        actionCard(page, "▣", "صندوق Meta Business Suite", "ورود مستقیم به ابزار رسمی مدیریت صفحه", () -> openInstagram(META, "صندوق Meta"));
        actionCard(page, "⇄", "مدیریت چند حساب", "میان‌بر و انتخاب پروفایل؛ بدون ذخیره گذرواژه", this::showAccounts);
        actionCard(page, "⚙", "تنظیمات و حریم خصوصی", "مرورگر پیش‌فرض، پاک‌کردن کوکی و اطلاعات برنامه", this::showSettings);
        gap(page, 6);
        addOutlineNote(page, "برای امنیت حساب، دایرکت‌یار پیام‌ها را از سایت اصلی نمایش می‌دهد و ارسال خودکار انجام نمی‌دهد.");
        gap(page, 18);
        homeBar();
    }

    private void homeBar() {
        LinearLayout nav = row();
        nav.setBackgroundColor(Color.rgb(22, 27, 42));
        padding(nav, 8, 9);
        navButton(nav, "⌂", "خانه", this::renderHome);
        navButton(nav, "✉", "دایرکت", () -> openInstagram(INBOX, "دایرکت"));
        navButton(nav, "⚡", "پاسخ‌ها", this::showTemplates);
        navButton(nav, "✦", "پیشنهاد", this::showSmartReply);
        root.addView(nav, lp(-1, -2));
    }

    private void navButton(LinearLayout nav, String glyph, String caption, Runnable action) {
        LinearLayout item = column();
        item.setGravity(Gravity.CENTER);
        item.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        TextView icon = text(glyph, 20, Color.rgb(219, 218, 239), true);
        icon.setGravity(Gravity.CENTER);
        item.addView(icon, lp(-1, 25));
        TextView label = text(caption, 11, WHITE, false);
        label.setGravity(Gravity.CENTER);
        item.addView(label, lp(-1, 17));
        item.setContentDescription(caption);
        item.setOnClickListener(v -> action.run());
        nav.addView(item, new LinearLayout.LayoutParams(0, dp(52), 1));
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
        if (!scheme.equalsIgnoreCase("https")) return false;
        return host.equals("instagram.com") || host.endsWith(".instagram.com")
                || host.equals("facebook.com") || host.endsWith(".facebook.com")
                || host.equals("meta.com") || host.endsWith(".meta.com")
                || host.equals("fbcdn.net") || host.endsWith(".fbcdn.net");
    }
    private String contextualTitle(String url) {
        if (url == null) return currentTitle;
        Uri u = Uri.parse(url);
        String host = u.getHost() == null ? "" : u.getHost();
        String path = u.getPath() == null ? "" : u.getPath().toLowerCase(Locale.ROOT);
        if (host.contains("instagram.com")) {
            if (path.startsWith("/direct")) return "دایرکت • " + username();
            if (path.equals("/" + username().toLowerCase(Locale.ROOT) + "/")) return "پروفایل • " + username();
            return "اینستاگرام";
        }
        if (host.endsWith("facebook.com") || host.endsWith("meta.com")) return "Meta Business Suite";
        return currentTitle;
    }

    private void updateBrowserTitle(String url) {
        if (!showingBrowser || browserTitle == null) return;
        currentUrl = url == null ? currentUrl : url;
        browserTitle.setText(contextualTitle(currentUrl));
        if (browserAccount != null) browserAccount.setText("@" + username());
    }

    private void openInternal(String url, String title) {
        currentUrl = url; currentTitle = title;
        setRoot();
        showingBrowser = true;
        LinearLayout toolbar = row();
        padding(toolbar, 11, 10);
        toolbar.setBackgroundColor(BG);
        TextView goHome = pill("⌂", Color.rgb(46, 55, 82));
        goHome.setContentDescription("صفحه اصلی دایرکت‌یار");
        goHome.setOnClickListener(v -> renderHome());
        toolbar.addView(goHome, lp(49, 45));
        LinearLayout center = column();
        browserTitle = text(contextualTitle(url), 15, WHITE, true);
        browserTitle.setGravity(Gravity.CENTER);
        center.addView(browserTitle, lp(-1, 25));
        browserAccount = text("@" + username(), 10, MUTED, false);
        browserAccount.setGravity(Gravity.CENTER);
        center.addView(browserAccount, lp(-1, 18));
        toolbar.addView(center, new LinearLayout.LayoutParams(0, dp(45), 1));
        TextView chrome = pill("Chrome ↗", PURPLE);
        chrome.setContentDescription("بازکردن در مرورگر Chrome");
        chrome.setOnClickListener(v -> openChrome(web != null && web.getUrl() != null ? web.getUrl() : url));
        toolbar.addView(chrome, lp(-2, 45));
        TextView refresh = pill("↻", Color.rgb(46, 55, 82));
        refresh.setContentDescription("تازه‌سازی صفحه");
        refresh.setOnClickListener(v -> { if (web != null) web.reload(); });
        toolbar.addView(refresh, lp(43, 45));
        root.addView(toolbar, lp(-1, -2));
        ProgressBar progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setMax(100);
        root.addView(progress, lp(-1, 3));
        web = new WebView(this);
        WebSettings settings = web.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        settings.setSupportMultipleWindows(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setBuiltInZoomControls(true);
        settings.setDisplayZoomControls(false);
        settings.setMediaPlaybackRequiresUserGesture(true);
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, true);
        web.setBackgroundColor(Color.WHITE);
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (!request.isForMainFrame()) return false;
                Uri link = request.getUrl();
                if (allowedHost(link)) return false;
                String scheme = link.getScheme();
                if ("http".equalsIgnoreCase(scheme) || "https".equalsIgnoreCase(scheme)) openChrome(link.toString());
                return true;
            }
            @Override public void onPageStarted(WebView view, String url, android.graphics.Bitmap icon) {
                updateBrowserTitle(url);
                progress.setVisibility(View.VISIBLE);
            }
            @Override public void onPageFinished(WebView view, String url) {
                updateBrowserTitle(url);
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.cancel();
                Toast.makeText(MainActivity.this, "خطای امنیتی اتصال؛ صفحه باز نشد", Toast.LENGTH_LONG).show();
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    Toast.makeText(MainActivity.this, "ارتباط برقرار نشد؛ اینترنت را بررسی کنید", Toast.LENGTH_LONG).show();
                }
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView view, int percent) {
                progress.setProgress(percent);
                progress.setVisibility(percent >= 100 ? View.GONE : View.VISIBLE);
            }
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (uploadCallback != null) uploadCallback.onReceiveValue(null);
                uploadCallback = callback;
                try { startActivityForResult(params.createIntent(), REQUEST_UPLOAD); return true; }
                catch (Exception e) {
                    uploadCallback.onReceiveValue(null);
                    uploadCallback = null;
                    Toast.makeText(MainActivity.this, "انتخاب فایل در دسترس نیست", Toast.LENGTH_SHORT).show();
                    return false;
                }
            }
        });
        web.setDownloadListener((link, userAgent, contentDisposition, mimeType, length) -> openChrome(link));
        root.addView(web, new LinearLayout.LayoutParams(-1, 0, 1));
        LinearLayout nav = row();
        nav.setBackgroundColor(Color.rgb(22, 27, 42));
        padding(nav, 5, 8);
        navButton(nav, "←", "عقب", () -> { if (web != null && web.canGoBack()) web.goBack(); else renderHome(); });
        navButton(nav, "✦", "پیشنهاد", this::showSmartReply);
        navButton(nav, "✉", "دایرکت", () -> { if (web != null) web.loadUrl(INBOX); });
        navButton(nav, "⚡", "پاسخ‌ها", this::showTemplates);
        navButton(nav, "⌂", "خانه", this::renderHome);
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


    // A deliberately local, deterministic reply assistant: no message scraping or automated sending.
    private String suggestReply(String incoming) {
        String x = incoming.toLowerCase(Locale.ROOT).replace('ي', 'ی').replace('ك', 'ک');
        String prefix = "سلام، وقت شما بخیر. ";
        if (x.contains("قیمت") || x.contains("هزینه") || x.contains("چند درمیاد") || x.contains("اجرت")) {
            return prefix + "برای اعلام هزینه دقیق، لطفاً مدل دستگاه یا ECU، شرح ایراد و در صورت امکان عکس واضح قطعه را ارسال کنید. پس از بررسی شرایط و قطعات موردنیاز، مبلغ اعلام می‌شود.";
        }
        if (x.contains("ریمپ") || x.contains("تیون") || x.contains("دیاگ")) {
            return prefix + "برای راهنمایی درباره ریمپ یا عیب‌یابی، لطفاً مدل خودرو، سال ساخت، نوع ECU و هدف یا مشکل فعلی را بفرمایید تا امکان بررسی دقیق مشخص شود.";
        }
        if (x.contains("ایسیو") || x.contains("ecu") || x.contains("برد") || x.contains("سوخت") || x.contains("لحیم")) {
            return prefix + "لطفاً شماره دقیق ECU یا برد، علائم خرابی و یک عکس واضح از قسمت آسیب‌دیده را ارسال کنید. تشخیص نهایی پس از بررسی و تست امکان‌پذیر است.";
        }
        if (x.contains("روشن") || x.contains("استارت") || x.contains("خطا") || x.contains("چراغ چک")) {
            return prefix + "لطفاً مدل خودرو یا دستگاه، زمان بروز مشکل و کد خطا یا علائم مشاهده‌شده را ارسال کنید. برای جلوگیری از تعویض بی‌دلیل قطعه، ابتدا عیب‌یابی دقیق انجام می‌شود.";
        }
        if (x.contains("آموزش") || x.contains("دوره") || x.contains("کلاس")) {
            return prefix + "ممنون از علاقه شما. لطفاً موضوع آموزشی موردنظر و سطح آشنایی خودتان را بفرمایید تا اطلاعات مرتبط را در اختیارتان بگذاریم.";
        }
        if (x.contains("سلام") || x.contains("درود") || x.contains("وقت بخیر")) {
            return prefix + "خوشحالیم پیام دادید. لطفاً بفرمایید در چه زمینه‌ای می‌توانیم راهنمایی‌تان کنیم؟";
        }
        return prefix + "پیامتان را دریافت کردیم. لطفاً جزئیات بیشتری درباره درخواستتان بفرمایید تا بتوانیم دقیق‌تر راهنمایی کنیم.";
    }

    private void copyText(String label, String value) {
        ClipboardManager cm = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        cm.setPrimaryClip(ClipData.newPlainText(label, value));
        Toast.makeText(this, "متن کپی شد؛ پس از بررسی در گفتگوی دلخواه جای‌گذاری و ارسال کنید.", Toast.LENGTH_LONG).show();
    }

    private void showSmartReply() {
        LinearLayout form = column();
        padding(form, 18, 12);
        form.addView(text("متن پیام مشتری را اینجا وارد یا جای‌گذاری کن:", 14, Color.DKGRAY, true), lp(-1, -2));
        gap(form, 8);
        EditText received = new EditText(this);
        received.setHint("مثلاً: سلام هزینه تعمیر ایسیو چقدره؟");
        received.setTextDirection(View.TEXT_DIRECTION_RTL);
        received.setTextSize(15);
        received.setMinLines(3);
        received.setMaxLines(6);
        received.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
        form.addView(received, lp(-1, -2));
        gap(form, 7);
        TextView note = text("پیام به سرور ارسال نمی‌شود؛ این پیشنهاد بر اساس قواعد داخلی ساخته می‌شود، نه هوش مصنوعی آنلاین.", 12, Color.DKGRAY, false);
        form.addView(note, lp(-1, -2));
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("پیشنهاد پاسخ • با تأیید شما")
                .setView(form)
                .setPositiveButton("تهیه پیشنهاد", null)
                .setNeutralButton("پاسخ‌های آماده", (d, w) -> showTemplates())
                .setNegativeButton("انصراف", null).create();
        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
            String message = received.getText().toString().trim();
            if (message.isEmpty()) {
                received.setError("متن پیام را وارد کن");
                return;
            }
            if (message.length() > 5000) {
                received.setError("متن پیام خیلی طولانی است");
                return;
            }
            dialog.dismiss();
            showReplyReview(suggestReply(message));
        }));
        dialog.show();
    }

    private void showReplyReview(String suggestion) {
        LinearLayout form = column();
        padding(form, 18, 8);
        form.addView(text("پیش از استفاده، متن را ویرایش و تأیید کن:", 13, Color.DKGRAY, false), lp(-1, -2));
        EditText draft = new EditText(this);
        draft.setText(suggestion);
        draft.setTextDirection(View.TEXT_DIRECTION_RTL);
        draft.setTextSize(15);
        draft.setMinLines(5);
        draft.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
        form.addView(draft, lp(-1, -2));
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("پیش‌نمایش پاسخ")
                .setView(form)
                .setPositiveButton("تأیید و کپی", null)
                .setNeutralButton("ذخیره به‌عنوان پاسخ آماده", null)
                .setNegativeButton("انصراف", null).create();
        dialog.setOnShowListener(d -> {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
                String t = draft.getText().toString().trim();
                if (t.isEmpty()) { draft.setError("پاسخ خالی است"); return; }
                copyText("پاسخ تأییدشده", t);
                dialog.dismiss();
            });
            dialog.getButton(AlertDialog.BUTTON_NEUTRAL).setOnClickListener(v -> {
                String t = draft.getText().toString().trim();
                if (t.isEmpty()) { draft.setError("پاسخ خالی است"); return; }
                JSONArray a = templateArray();
                a.put(t);
                saveTemplates(a);
                Toast.makeText(this, "پاسخ آماده ذخیره شد", Toast.LENGTH_SHORT).show();
                dialog.dismiss();
            });
        });
        dialog.show();
    }

    private void showComments() {
        new AlertDialog.Builder(this)
                .setTitle("مدیریت کامنت‌های اینستاگرام")
                .setMessage("صندوق رسمی Meta را باز کن و سربرگ Instagram comments را انتخاب کن. پاسخ و حذف کامنت فقط با اقدام خودت در صفحه رسمی انجام می‌شود.")
                .setPositiveButton("بازکردن صندوق Meta", (d,w) -> openInstagram(META, "نظرات اینستاگرام"))
                .setNeutralButton("نمایش پروفایل", (d,w) -> openInstagram(profileUrl(), "پروفایل"))
                .setNegativeButton("انصراف", null).show();
    }

    private void showPublishing() {
        LinearLayout form = column();
        padding(form, 18, 8);
        form.addView(text("پیش‌نویس کپشن (فقط روی همین گوشی ذخیره می‌شود):", 13, Color.DKGRAY, false), lp(-1,-2));
        EditText draft = new EditText(this);
        draft.setText(prefs.getString("captionDraft", ""));
        draft.setHint("متن پست یا ریلز را بنویس...");
        draft.setTextDirection(View.TEXT_DIRECTION_RTL);
        draft.setMinLines(5);
        draft.setMaxLines(9);
        draft.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
        form.addView(draft, lp(-1,-2));
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("انتشار دستی پست، ریلز و استوری")
                .setView(form)
                .setPositiveButton("کپی و بازکردن Meta", null)
                .setNeutralButton("ذخیره پیش‌نویس", null)
                .setNegativeButton("انصراف", null).create();
        dialog.setOnShowListener(d -> {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
                String caption = draft.getText().toString().trim();
                prefs.edit().putString("captionDraft", caption).apply();
                if (!caption.isEmpty()) copyText("کپشن", caption);
                dialog.dismiss();
                openInstagram(META_CONTENT, "محتوای Meta");
                Toast.makeText(this,"در Meta از بخش Content / Create post استفاده کن؛ ارسال خودکار نیست.",Toast.LENGTH_LONG).show();
            });
            dialog.getButton(AlertDialog.BUTTON_NEUTRAL).setOnClickListener(v -> {
                prefs.edit().putString("captionDraft", draft.getText().toString()).apply();
                Toast.makeText(this, "پیش‌نویس ذخیره شد", Toast.LENGTH_SHORT).show();
                dialog.dismiss();
            });
        });
        dialog.show();
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
                .setTitle("پاسخ‌های آماده • لمس برای کپی")
                .setItems(items, (d, which) -> {
                    if (which == a.length()) editTemplate(-1);
                    else new AlertDialog.Builder(this).setTitle("پاسخ آماده")
                            .setMessage(a.optString(which))
                            .setPositiveButton("کپی متن", (d2, w) -> {
                                ClipboardManager c = (ClipboardManager)getSystemService(CLIPBOARD_SERVICE);
                                c.setPrimaryClip(ClipData.newPlainText("پاسخ آماده", a.optString(which)));
                                Toast.makeText(this, "کپی شد؛ در گفتگوی دلخواه جای‌گذاری کن", Toast.LENGTH_LONG).show();
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
        new AlertDialog.Builder(this).setTitle("حساب‌ها • فقط میان‌بر")
                .setItems(entries, (d, item) -> {
                    if (item == a.length()) addAccount();
                    else {
                        String selected=a.optString(item);
                        new AlertDialog.Builder(this).setTitle("@" + selected)
                            .setMessage("این گزینه فقط میان‌بر پروفایل است. تغییر حساب واردشده باید از داخل اینستاگرام انجام شود.")
                            .setPositiveButton("انتخاب میان‌بر", (d2,w) -> {
                                prefs.edit().putString("username", selected).apply();
                                renderHome();
                            })
                            .setNeutralButton("نمایش پروفایل", (d2,w) -> openInstagram(INSTA + selected + "/", "پروفایل"))
                            .setNegativeButton(a.length() > 1 ? "حذف از فهرست" : "انصراف", (d2,w) -> { if(a.length() > 1) confirmRemoveAccount(selected); })
                            .show();
                    }
                }).setNegativeButton("بستن", null).show();
    }
    private void confirmRemoveAccount(String name) {
        new AlertDialog.Builder(this).setTitle("حذف میان‌بر حساب")
                .setMessage("@" + name + " فقط از فهرست محلی دایرکت‌یار حذف شود؟")
                .setPositiveButton("حذف", (d,w) -> {
                    JSONArray original=accountsArray(), result=new JSONArray();
                    for(int i=0;i<original.length();i++) if(!name.equals(original.optString(i))) result.put(original.optString(i));
                    if(result.length() == 0) result.put("beytaneh1");
                    SharedPreferences.Editor editor=prefs.edit().putString("accounts", result.toString());
                    if (name.equals(username())) editor.putString("username", result.optString(0));
                    editor.apply();
                    renderHome();
                }).setNegativeButton("انصراف", null).show();
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
                                .setMessage( "دایرکت‌یار نسخه ۱.۲: بدون API، بدون سرور و بدون دسترسی به رمز حساب. پیشنهاد پاسخ با قواعد محلی و متن واردشده توسط شما ساخته می‌شود؛ متن پیام‌ها به صورت خودکار خوانده نمی‌شود. ارسال پیام، ثبت کامنت و انتشار محتوا صرفاً پس از اقدام دستی شما در اینستاگرام یا Meta انجام می‌شود. کوکی‌های ورود مرورگر داخلی در Android WebView نگهداری می‌شوند.")
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
