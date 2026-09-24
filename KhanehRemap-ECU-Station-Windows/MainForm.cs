using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Linq;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace KhanehRemapEcuStation;

public sealed class MainForm : Form
{
    static readonly Color Bg = Color.FromArgb(10, 15, 23);
    static readonly Color PanelBg = Color.FromArgb(17, 24, 39);
    static readonly Color Panel2 = Color.FromArgb(24, 34, 50);
    static readonly Color Border = Color.FromArgb(45, 58, 78);
    static readonly Color TextMain = Color.FromArgb(238, 243, 248);
    static readonly Color TextMuted = Color.FromArgb(151, 166, 184);
    static readonly Color Cyan = Color.FromArgb(24, 202, 231);
    static readonly Color Green = Color.FromArgb(46, 204, 113);
    static readonly Color Amber = Color.FromArgb(255, 183, 77);
    static readonly Color Red = Color.FromArgb(255, 82, 82);
    static readonly Color Purple = Color.FromArgb(142, 100, 255);

    readonly System.Windows.Forms.Timer demoTimer = new() { Interval = 120 };
    readonly Dictionary<string, Control> pages = new();
    readonly Dictionary<string, NavButton> navButtons = new();

    Panel contentHost = null!;
    Label connectionLabel = null!;
    Label demoBadge = null!;
    Label ecuLabel = null!;
    MetricCard rpmCard = null!;
    MetricCard tempCard = null!;
    MetricCard tpsCard = null!;
    MetricCard mapCard = null!;
    MetricCard voltCard = null!;
    MetricCard currentCard = null!;

    MiniGaugeCard dashRpm = null!;
    MiniGaugeCard dashWater = null!;
    MiniGaugeCard dashIntake = null!;
    MiniGaugeCard dashBattery = null!;
    MiniGaugeCard dashOxygen = null!;
    MiniGaugeCard dashThrottle = null!;
    MiniGaugeCard dashMap = null!;
    RichTextBox dashboardLog = null!;
    Label dashPowerB = null!;
    Label dashPowerIgn = null!;
    Label dashPower5 = null!;
    Label dashPowerGnd = null!;
    Label dashPowerRpm = null!;
    Label dashPowerBus = null!;
    ScopeControl scope = null!;
    LedLamp[] injectorLeds = Array.Empty<LedLamp>();
    LedLamp[] coilLeds = Array.Empty<LedLamp>();
    LedLamp fuelPumpLed = null!;
    LedLamp fanLowLed = null!;
    LedLamp fanHighLed = null!;
    LedLamp milLed = null!;
    LedLamp immoLed = null!;
    LedLamp oxygenLed = null!;
    LedLamp ckpLed = null!;
    LedLamp cmpLed = null!;
    TrackBar rpmSlider = null!;
    TrackBar throttleSlider = null!;
    TrackBar tempSlider = null!;
    DataGridView liveGrid = null!;
    ListView dtcList = null!;
    ProgressBar autoProgress = null!;
    Label autoStatus = null!;
    ListView autoList = null!;
    ProgressBar progProgress = null!;
    Label progStatus = null!;

    bool demoMode;
    double phase;
    readonly Random rnd = new();

    public MainForm()
    {
        Text = "نرم افزار تست ECU | خانه ریمپ";
        BackColor = Bg;
        ForeColor = TextMain;
        Font = new Font("Segoe UI", 10f);
        MinimumSize = new Size(1240, 760);
        WindowState = FormWindowState.Maximized;
        StartPosition = FormStartPosition.CenterScreen;
        RightToLeft = RightToLeft.Yes;
        RightToLeftLayout = false;
        DoubleBuffered = true;

        BuildShell();
        BuildPages();

        // نسخه نمایشی از ابتدا با داده‌های شبیه‌سازی‌شده فعال است.
        demoMode = true;
        connectionLabel.Text = "● ارتباط با ECU برقرار است  |  DEMO";
        connectionLabel.ForeColor = Green;
        ecuLabel.Text = "ECU: M7.4.4 / ME7.4.4 — DEMO";
        demoBadge.Text = "● حالت دمو فعال";
        demoBadge.ForeColor = Cyan;
        ShowPage("داشبورد");

        demoTimer.Tick += (_, _) => TickDemo();
        demoTimer.Start();
        FormClosing += (_, _) => demoTimer.Stop();
    }

    void BuildShell()
    {
        var root = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Bg,
            Padding = Padding.Empty,
            Margin = Padding.Empty
        };
        Controls.Add(root);

        var topBar = new RoundedPanel
        {
            Dock = DockStyle.Top,
            Height = 88,
            BackColor = Color.FromArgb(5, 18, 30),
            BorderColor = Color.FromArgb(13, 75, 105),
            Radius = 12,
            Padding = new Padding(8),
            Margin = Padding.Empty
        };
        root.Controls.Add(topBar);

        var topLayout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 3,
            RowCount = 1,
            BackColor = Color.Transparent,
            Margin = Padding.Empty,
            Padding = Padding.Empty,
            RightToLeft = RightToLeft.No
        };
        topLayout.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 365));
        topLayout.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        topLayout.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 250));
        topLayout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        topBar.Controls.Add(topLayout);

        var brand = new BrandControl
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(4, 2, 8, 2),
            Title = "نرم افزار تست ECU",
            Subtitle = "عیب‌یابی و تست کامل واحد کنترل موتور"
        };
        topLayout.Controls.Add(brand, 0, 0);

        var nav = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = false,
            AutoScroll = true,
            BackColor = Color.Transparent,
            Padding = new Padding(4, 3, 4, 3),
            Margin = Padding.Empty,
            RightToLeft = RightToLeft.No
        };
        topLayout.Controls.Add(nav, 1, 0);

        AddNav(nav, "داشبورد", "⌂", "داشبورد");
        AddNav(nav, "تست خودکار", "⌁", "تست عملکردها");
        AddNav(nav, "داده زنده", "◌", "نمایش سنسورها");
        AddNav(nav, "پروگرامر", "⚙", "تنظیمات و ابزارها");
        AddNav(nav, "دیاگ", "▤", "گزارش و لاگ");
        AddNav(nav, "شروع", "?", "راهنما");

        var connectionBox = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(8, 8, 6, 8),
            BackColor = Color.FromArgb(5, 28, 35),
            BorderColor = Color.FromArgb(13, 84, 95),
            Radius = 13,
            Padding = new Padding(12)
        };
        topLayout.Controls.Add(connectionBox, 2, 0);

        var connectionDot = new Panel
        {
            Size = new Size(36, 36),
            Location = new Point(18, 16),
            BackColor = Green
        };
        connectionDot.Paint += (_, e) =>
        {
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            e.Graphics.Clear(connectionBox.BackColor);
            using var glow = new SolidBrush(Color.FromArgb(60, Green));
            using var core = new SolidBrush(Green);
            e.Graphics.FillEllipse(glow, 0, 0, 36, 36);
            e.Graphics.FillEllipse(core, 8, 8, 20, 20);
        };
        connectionBox.Controls.Add(connectionDot);

        connectionLabel = new Label
        {
            Text = "● ارتباط با ECU برقرار است",
            ForeColor = Green,
            Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
            AutoSize = false,
            Location = new Point(60, 12),
            Size = new Size(168, 28),
            TextAlign = ContentAlignment.MiddleRight
        };
        connectionBox.Controls.Add(connectionLabel);

        ecuLabel = new Label
        {
            Text = "ECU: DEMO",
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 8.5f, FontStyle.Bold),
            AutoSize = false,
            Location = new Point(60, 40),
            Size = new Size(168, 24),
            TextAlign = ContentAlignment.MiddleRight
        };
        connectionBox.Controls.Add(ecuLabel);

        demoBadge = new Label
        {
            Visible = false,
            Text = "● حالت دمو فعال",
            ForeColor = Cyan
        };
        connectionBox.Controls.Add(demoBadge);

        contentHost = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Bg,
            Padding = new Padding(8, 8, 8, 8),
            RightToLeft = RightToLeft.Yes
        };
        root.Controls.Add(contentHost);
        contentHost.BringToFront();
    }

    void AddNav(FlowLayoutPanel host, string key, string icon, string text)
    {
        var b = new NavButton
        {
            Width = 138,
            Height = 72,
            Margin = new Padding(3, 0, 3, 0),
            Text = $"{icon}\n{text}",
            Tag = key,
            TextAlign = ContentAlignment.MiddleCenter,
            Padding = Padding.Empty,
            RightToLeft = RightToLeft.Yes,
            Font = new Font("Segoe UI", 9.2f, FontStyle.Bold)
        };
        b.Click += (_, _) => ShowPage((string)b.Tag!);
        host.Controls.Add(b);
        navButtons[key] = b;
    }

    void BuildPages()
    {
        pages["شروع"] = CreateStartPage();
        pages["داشبورد"] = CreateDashboardPage();
        pages["تست خودکار"] = CreateAutoTestPage();
        pages["داده زنده"] = CreateLiveDataPage();
        pages["اسیلوسکوپ"] = CreateScopePage();
        pages["دیاگ"] = CreateDiagPage();
        pages["پروگرامر"] = CreateProgrammerPage();
        pages["تنظیمات"] = CreateSettingsPage();

        foreach (var p in pages.Values)
        {
            p.Dock = DockStyle.Fill;
            p.Visible = false;
            contentHost.Controls.Add(p);
        }
    }

    Control CreateStartPage()
    {
        var page = NewPage();

        var hero = new RoundedPanel
        {
            Width = 870,
            Height = 520,
            Anchor = AnchorStyles.None,
            BackColor = PanelBg,
            BorderColor = Border,
            Radius = 26
        };
        page.Controls.Add(hero);
        page.Resize += (_, _) => hero.Location = new Point(Math.Max(20, (page.ClientSize.Width - hero.Width) / 2), Math.Max(20, (page.ClientSize.Height - hero.Height) / 2));
        hero.Location = new Point(100, 60);

        var icon = new Label { Text = "⚡", Font = new Font("Segoe UI Emoji", 48), ForeColor = Cyan, AutoSize = false, Size = new Size(100, 85), Location = new Point(385, 35), TextAlign = ContentAlignment.MiddleCenter };
        hero.Controls.Add(icon);

        var title = new Label
        {
            Text = "ایستگاه حرفه‌ای تست و پروگرام ECU",
            Font = new Font("Segoe UI", 25, FontStyle.Bold),
            ForeColor = TextMain,
            AutoSize = false,
            Size = new Size(800, 55),
            Location = new Point(35, 120),
            TextAlign = ContentAlignment.MiddleCenter
        };
        hero.Controls.Add(title);

        var sub = new Label
        {
            Text = "خانه ریمپ  •  تستر ۱۰۰ کاناله  •  دیاگ  •  اسیلوسکوپ  •  پروگرامر",
            Font = new Font("Segoe UI", 11),
            ForeColor = TextMuted,
            AutoSize = false,
            Size = new Size(800, 34),
            Location = new Point(35, 180),
            TextAlign = ContentAlignment.MiddleCenter
        };
        hero.Controls.Add(sub);

        var demo = ActionButton("🧪  ورود به حالت دمو", Cyan, 330, 66);
        demo.Location = new Point(455, 265);
        demo.Click += (_, _) => StartDemo();
        hero.Controls.Add(demo);

        var connect = ActionButton("🔌  اتصال به دستگاه واقعی", Purple, 330, 66);
        connect.Location = new Point(85, 265);
        connect.Click += (_, _) => MessageBox.Show("بخش ارتباط USB برای سخت‌افزار STM32H743 در نسخه اتصال دستگاه فعال می‌شود.\nفعلاً برای بررسی کامل رابط کاربری، حالت دمو را اجرا کنید.", "اتصال سخت‌افزار", MessageBoxButtons.OK, MessageBoxIcon.Information);
        hero.Controls.Add(connect);

        var note = new Label
        {
            Text = "در حالت دمو هیچ ECU واقعی متصل نیست و تمام داده‌ها شبیه‌سازی می‌شوند.\nمی‌توانید قبل از ساخت دستگاه، تمام صفحات و روند تست را بررسی کنید.",
            Font = new Font("Segoe UI", 10),
            ForeColor = TextMuted,
            AutoSize = false,
            Size = new Size(700, 70),
            Location = new Point(85, 375),
            TextAlign = ContentAlignment.MiddleCenter
        };
        hero.Controls.Add(note);

        return page;
    }

    Control CreateDashboardPage()
    {
        var page = NewPage();

        var top = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            Height = 178,
            ColumnCount = 6,
            RowCount = 1,
            Padding = new Padding(0, 0, 0, 10),
            RightToLeft = RightToLeft.No
        };
        for (int i = 0; i < 6; i++) top.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 16.666f));
        page.Controls.Add(top);

        rpmCard = AddMetric(top, "دور موتور", "0", "RPM", Cyan, 5);
        tempCard = AddMetric(top, "دمای آب", "0", "°C", Amber, 4);
        tpsCard = AddMetric(top, "دریچه گاز", "0", "%", Green, 3);
        mapCard = AddMetric(top, "فشار MAP", "0", "kPa", Purple, 2);
        voltCard = AddMetric(top, "ولتاژ ECU", "0", "V", Cyan, 1);
        currentCard = AddMetric(top, "جریان ECU", "0", "A", Amber, 0);

        var center = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 1,
            RightToLeft = RightToLeft.No,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        center.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 34));
        center.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 66));
        page.Controls.Add(center);
        center.BringToFront();

        // Simulator controls stay inside their own fixed table cell.
        // No child button is anchored to an un-laid-out right edge anymore.
        var controlsCard = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(0, 6, 8, 0),
            BackColor = PanelBg,
            BorderColor = Border,
            Radius = 20,
            Padding = new Padding(18),
            RightToLeft = RightToLeft.Yes
        };
        center.Controls.Add(controlsCard, 0, 0);
        controlsCard.Controls.Add(SectionTitle("کنترل شبیه‌ساز"));

        rpmSlider = AddSlider(controlsCard, "دور موتور هدف", 0, 8000, 850, 80);
        throttleSlider = AddSlider(controlsCard, "دریچه گاز", 0, 100, 18, 168);
        tempSlider = AddSlider(controlsCard, "دمای آب", -20, 125, 88, 256);

        var actions = new TableLayoutPanel
        {
            Dock = DockStyle.Bottom,
            Height = 132,
            ColumnCount = 1,
            RowCount = 2,
            Padding = new Padding(8, 6, 8, 6),
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };
        actions.RowStyles.Add(new RowStyle(SizeType.Percent, 50));
        actions.RowStyles.Add(new RowStyle(SizeType.Percent, 50));
        controlsCard.Controls.Add(actions);

        var quick = ActionButton("▶  اجرای تست سریع", Green, 100, 52);
        quick.Dock = DockStyle.Fill;
        quick.Margin = new Padding(4);
        quick.Click += (_, _) => { ShowPage("تست خودکار"); _ = RunAutoTest(); };
        actions.Controls.Add(quick, 0, 0);

        var fault = ActionButton("⚠  تزریق خطای آزمایشی", Red, 100, 52);
        fault.Dock = DockStyle.Fill;
        fault.Margin = new Padding(4);
        fault.Click += (_, _) => AddDemoDtc();
        actions.Controls.Add(fault, 0, 1);

        // Live ECU outputs.
        var signalCard = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(8, 6, 0, 0),
            BackColor = PanelBg,
            BorderColor = Border,
            Radius = 20,
            Padding = new Padding(16),
            RightToLeft = RightToLeft.Yes
        };
        center.Controls.Add(signalCard, 1, 0);
        signalCard.Controls.Add(SectionTitle("نمایش زنده خروجی‌ها و سنسورهای ECU"));

        injectorLeds = Enumerable.Range(1, 6)
            .Select(i => new LedLamp($"انژکتور {i}", Green, "INJ", "پالس", "خاموش"))
            .ToArray();

        coilLeds = Enumerable.Range(1, 6)
            .Select(i => new LedLamp($"کوئل {i}", Cyan, "⚡", "جرقه", "خاموش"))
            .ToArray();

        fuelPumpLed = new LedLamp("پمپ بنزین", Amber, "fuelpump", "فعال", "خاموش");
        fanLowLed = new LedLamp("فن کند", Cyan, "fan", "فعال", "خاموش");
        fanHighLed = new LedLamp("فن تند", Red, "fanfast", "فعال", "خاموش");
        milLed = new LedLamp("چراغ چک", Amber, "mil", "روشن", "خاموش");
        immoLed = new LedLamp("چراغ ایمو", Red, "immo", "قفل", "آزاد");
        oxygenLed = new LedLamp("سنسور اکسیژن", Purple, "oxygen", "فعال", "سرد");
        ckpLed = new LedLamp("سنسور دور", Cyan, "ckp", "سیگنال", "قطع");
        cmpLed = new LedLamp("میل‌سوپاپ", Green, "cmp", "سیگنال", "قطع");

        var statusGrid = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            RowCount = 3,
            ColumnCount = 1,
            Padding = new Padding(0, 4, 0, 0),
            Margin = Padding.Empty,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };
        statusGrid.RowStyles.Add(new RowStyle(SizeType.Percent, 33.333f));
        statusGrid.RowStyles.Add(new RowStyle(SizeType.Percent, 33.333f));
        statusGrid.RowStyles.Add(new RowStyle(SizeType.Percent, 33.334f));
        signalCard.Controls.Add(statusGrid);
        statusGrid.BringToFront();

        statusGrid.Controls.Add(MakeLampGroup("انژکتورها", injectorLeds), 0, 0);
        statusGrid.Controls.Add(MakeLampGroup("کویل‌ها", coilLeds), 0, 1);
        statusGrid.Controls.Add(MakeLampGroup("شبکه سنسورها و عملگرها",
            new[] { fuelPumpLed, fanLowLed, fanHighLed, milLed, immoLed, oxygenLed, ckpLed, cmpLed }), 0, 2);

        return page;
    }

    Control MakeLampGroup(string title, IEnumerable<LedLamp> lamps)
    {
        var group = new Panel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(2, 3, 2, 3),
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };

        var groupTitle = new Label
        {
            Text = title,
            Dock = DockStyle.Top,
            Height = 28,
            ForeColor = TextMuted,
            Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleRight
        };
        group.Controls.Add(groupTitle);

        var flow = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.RightToLeft,
            WrapContents = false,
            AutoScroll = true,
            Padding = new Padding(4, 2, 4, 2),
            Margin = Padding.Empty,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };
        group.Controls.Add(flow);
        flow.BringToFront();

        foreach (var lamp in lamps) flow.Controls.Add(lamp);
        return group;
    }

    MetricCard AddMetric(TableLayoutPanel top, string title, string value, string unit, Color accent, int column)
    {
        var c = new MetricCard(title, value, unit, accent) { Dock = DockStyle.Fill, Margin = new Padding(5) };
        top.Controls.Add(c, column, 0);
        return c;
    }

    TrackBar AddSlider(Control host, string title, int min, int max, int value, int y)
    {
        var label = new Label
        {
            Text = $"{title}: {value}",
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            AutoSize = false,
            Height = 28,
            Location = new Point(25, y),
            TextAlign = ContentAlignment.MiddleRight
        };

        var tr = new TrackBar
        {
            Minimum = min,
            Maximum = max,
            Value = value,
            TickStyle = TickStyle.None,
            Height = 44,
            Location = new Point(22, y + 32),
            RightToLeft = RightToLeft.No
        };

        void Fit()
        {
            int w = Math.Max(150, host.ClientSize.Width - 50);
            label.Width = w;
            tr.Width = w;
        }

        host.Controls.Add(label);
        host.Controls.Add(tr);
        host.Resize += (_, _) => Fit();
        tr.ValueChanged += (_, _) => label.Text = $"{title}: {tr.Value}";
        Fit();
        return tr;
    }

    Control CreateAutoTestPage()
    {
        var page = NewPage();
        var card = FullCard(page, "تست خودکار ECU", "اجرای مرحله‌ای پاور، ارتباط، ورودی‌ها و خروجی‌ها");

        autoList = new ListView
        {
            View = View.Details,
            FullRowSelect = true,
            BorderStyle = BorderStyle.None,
            BackColor = Panel2,
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 10),
            Location = new Point(30, 105),
            Size = new Size(760, 450),
            Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right,
            RightToLeft = RightToLeft.Yes
        };
        autoList.Columns.Add("مرحله", 420);
        autoList.Columns.Add("نتیجه", 160);
        autoList.Columns.Add("مقدار", 160);
        card.Controls.Add(autoList);

        autoStatus = new Label { Text = "آماده شروع", ForeColor = TextMuted, Font = new Font("Segoe UI", 11, FontStyle.Bold), Location = new Point(820, 125), Size = new Size(260, 40), TextAlign = ContentAlignment.MiddleCenter, Anchor = AnchorStyles.Top | AnchorStyles.Right };
        card.Controls.Add(autoStatus);

        autoProgress = new ProgressBar { Minimum = 0, Maximum = 100, Value = 0, Location = new Point(820, 180), Size = new Size(260, 28), Anchor = AnchorStyles.Top | AnchorStyles.Right };
        card.Controls.Add(autoProgress);

        var run = ActionButton("▶  شروع AUTO TEST", Green, 260, 58);
        run.Location = new Point(820, 235);
        run.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        run.Click += async (_, _) => await RunAutoTest();
        card.Controls.Add(run);

        var clear = ActionButton("↻  پاک کردن نتیجه", Panel2, 260, 48);
        clear.Location = new Point(820, 305);
        clear.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        clear.Click += (_, _) => { autoList.Items.Clear(); autoProgress.Value = 0; autoStatus.Text = "آماده شروع"; autoStatus.ForeColor = TextMuted; };
        card.Controls.Add(clear);

        return page;
    }

    async Task RunAutoTest()
    {
        if (!demoMode)
        {
            MessageBox.Show("برای اجرای بدون سخت‌افزار ابتدا از صفحه شروع وارد حالت دمو شوید.", "حالت دمو", MessageBoxButtons.OK, MessageBoxIcon.Information);
            return;
        }

        autoList.Items.Clear();
        autoProgress.Value = 0;
        autoStatus.ForeColor = Cyan;
        string[] stages =
        {
            "بررسی اتصال زمین و ایمنی",
            "فعال‌سازی BAT و کنترل جریان",
            "فعال‌سازی IGN",
            "بررسی رفرنس 5V",
            "بررسی ارتباط CAN",
            "بررسی ارتباط K-Line",
            "تولید CKP / CMP",
            "بررسی انژکتور 1",
            "بررسی انژکتور 2",
            "بررسی انژکتور 3",
            "بررسی انژکتور 4",
            "بررسی کوئل 1",
            "بررسی کوئل 2",
            "بررسی کوئل 3",
            "بررسی کوئل 4",
            "رله پمپ بنزین",
            "فن دور کند",
            "فن دور تند"
        };

        for (int i = 0; i < stages.Length; i++)
        {
            autoStatus.Text = $"در حال تست: {stages[i]}";
            await Task.Delay(170);
            string val = stages[i].Contains("5V") ? "5.01 V" :
                         stages[i].Contains("BAT") ? "13.82 V / 0.74 A" :
                         stages[i].Contains("انژکتور") ? $"{2.8 + rnd.NextDouble() * 0.7:0.00} ms" :
                         stages[i].Contains("کوئل") ? $"{2.4 + rnd.NextDouble() * 0.5:0.00} ms" : "OK";
            var item = new ListViewItem(stages[i]);
            item.SubItems.Add("✓ PASS");
            item.SubItems.Add(val);
            item.ForeColor = Green;
            autoList.Items.Add(item);
            autoList.EnsureVisible(autoList.Items.Count - 1);
            autoProgress.Value = (i + 1) * 100 / stages.Length;
        }
        autoStatus.Text = "✓ تمام تست‌های دمو با موفقیت انجام شد";
        autoStatus.ForeColor = Green;
    }

    Control CreateLiveDataPage()
    {
        var page = NewPage();
        var card = FullCard(page, "داده‌های زنده", "پارامترهای لحظه‌ای ECU و شبیه‌ساز");

        liveGrid = new DataGridView
        {
            Location = new Point(28, 105),
            Size = new Size(1040, 500),
            Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right,
            BackgroundColor = Panel2,
            BorderStyle = BorderStyle.None,
            EnableHeadersVisualStyles = false,
            ColumnHeadersDefaultCellStyle = new DataGridViewCellStyle { BackColor = Color.FromArgb(31, 43, 61), ForeColor = TextMain, Font = new Font("Segoe UI", 10, FontStyle.Bold), Alignment = DataGridViewContentAlignment.MiddleCenter },
            DefaultCellStyle = new DataGridViewCellStyle { BackColor = Panel2, ForeColor = TextMain, SelectionBackColor = Color.FromArgb(36, 78, 96), SelectionForeColor = Color.White, Font = new Font("Segoe UI", 10), Alignment = DataGridViewContentAlignment.MiddleCenter },
            RowHeadersVisible = false,
            AllowUserToAddRows = false,
            AllowUserToDeleteRows = false,
            ReadOnly = true,
            AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill,
            RightToLeft = RightToLeft.Yes
        };
        liveGrid.Columns.Add("name", "پارامتر");
        liveGrid.Columns.Add("value", "مقدار");
        liveGrid.Columns.Add("unit", "واحد");
        liveGrid.Columns.Add("state", "وضعیت");
        foreach (var row in new[]
        {
            new[]{"دور موتور","0","RPM","—"},
            new[]{"دمای آب","0","°C","—"},
            new[]{"دریچه گاز","0","%","—"},
            new[]{"MAP","0","kPa","—"},
            new[]{"ولتاژ باتری","0","V","—"},
            new[]{"جریان ECU","0","A","—"},
            new[]{"عرض پالس انژکتور","0","ms","—"},
            new[]{"Dwell کوئل","0","ms","—"},
            new[]{"فرکانس CKP","0","Hz","—"}
        }) liveGrid.Rows.Add(row);
        card.Controls.Add(liveGrid);
        return page;
    }

    Control CreateScopePage()
    {
        var page = NewPage();
        var card = FullCard(page, "اسیلوسکوپ نرم‌افزاری", "CKP • CMP • Injector • Ignition");
        scope = new ScopeControl { Location = new Point(28, 105), Size = new Size(1040, 485), Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right };
        card.Controls.Add(scope);

        var channels = new Label { Text = "● CKP     ● CMP     ● INJECTOR     ● IGNITION", ForeColor = TextMuted, AutoSize = false, Location = new Point(35, 66), Size = new Size(600, 28), TextAlign = ContentAlignment.MiddleLeft };
        card.Controls.Add(channels);
        return page;
    }

    Control CreateDiagPage()
    {
        var page = NewPage();
        var card = FullCard(page, "دیاگ و خطاها", "در حالت دمو خطاهای آزمایشی ایجاد و پاک می‌شوند");

        dtcList = new ListView
        {
            View = View.Details, FullRowSelect = true, BorderStyle = BorderStyle.None,
            BackColor = Panel2, ForeColor = TextMain, Font = new Font("Segoe UI", 10),
            Location = new Point(28, 105), Size = new Size(760, 470),
            Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right,
            RightToLeft = RightToLeft.Yes
        };
        dtcList.Columns.Add("کد خطا", 150);
        dtcList.Columns.Add("شرح", 430);
        dtcList.Columns.Add("وضعیت", 150);
        card.Controls.Add(dtcList);

        var add = ActionButton("⚠ ایجاد خطای دمو", Red, 260, 52);
        add.Location = new Point(820, 125); add.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        add.Click += (_, _) => AddDemoDtc(); card.Controls.Add(add);

        var clear = ActionButton("✓ پاک کردن خطاها", Green, 260, 52);
        clear.Location = new Point(820, 190); clear.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        clear.Click += (_, _) => { dtcList.Items.Clear(); milLed?.SetState(false); };
        card.Controls.Add(clear);

        var scan = ActionButton("◉ شناسایی خودکار ECU", Cyan, 260, 52);
        scan.Location = new Point(820, 255); scan.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        scan.Click += async (_, _) =>
        {
            scan.Enabled = false; scan.Text = "در حال شناسایی...";
            await Task.Delay(900);
            ecuLabel.Text = "ECU: Bosch ME7.4.4 (DEMO)";
            scan.Text = "✓ شناسایی شد"; scan.Enabled = true;
        };
        card.Controls.Add(scan);
        return page;
    }

    void AddDemoDtc()
    {
        if (!demoMode) { MessageBox.Show("ابتدا حالت دمو را فعال کنید."); return; }
        string[][] faults =
        {
            new[]{"P0335","مدار سنسور موقعیت میل‌لنگ","فعال"},
            new[]{"P0115","مدار سنسور دمای آب موتور","ذخیره‌شده"},
            new[]{"P0202","مدار انژکتور سیلندر 2","فعال"},
            new[]{"P0351","مدار اولیه/ثانویه کوئل 1","ذخیره‌شده"}
        };
        var f = faults[rnd.Next(faults.Length)];
        if (!dtcList.Items.Cast<ListViewItem>().Any(x => x.Text == f[0]))
        {
            var i = new ListViewItem(f[0]); i.SubItems.Add(f[1]); i.SubItems.Add(f[2]); i.ForeColor = Red; dtcList.Items.Add(i);
        }
        milLed?.SetState(true);
        ShowPage("دیاگ");
    }

    Control CreateProgrammerPage()
    {
        var page = NewPage();
        var card = FullCard(
            page,
            "محیط پروگرامر ECU",
            "محیط کاری فارسی برای شناسایی، خواندن، بکاپ، نوشتن و وریفای — طراحی مستقل با الهام از گردش‌کار پروگرامرهای حرفه‌ای");

        var warning = new RoundedPanel
        {
            BackColor = Color.FromArgb(45, 35, 20),
            BorderColor = Amber,
            Radius = 16,
            Location = new Point(28, 94),
            Size = new Size(1040, 58),
            Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right
        };
        warning.Controls.Add(new Label
        {
            Text = "⚠ حالت دمو فعال است؛ هیچ فرمان واقعی به ECU ارسال نمی‌شود.",
            Dock = DockStyle.Fill,
            ForeColor = Amber,
            Font = new Font("Segoe UI", 10.5f, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleCenter
        });
        card.Controls.Add(warning);

        var work = new TableLayoutPanel
        {
            Location = new Point(28, 166),
            Size = new Size(1040, 420),
            Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right,
            ColumnCount = 3,
            RowCount = 1,
            RightToLeft = RightToLeft.No,
            Margin = Padding.Empty,
            Padding = Padding.Empty,
            BackColor = Color.Transparent
        };
        work.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 24));
        work.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 48));
        work.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 28));
        card.Controls.Add(work);

        // Left: operations.
        var operations = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(0, 0, 7, 0),
            BackColor = Panel2,
            BorderColor = Border,
            Radius = 16,
            Padding = new Padding(12),
            RightToLeft = RightToLeft.Yes
        };
        work.Controls.Add(operations, 0, 0);
        operations.Controls.Add(new Label
        {
            Text = "عملیات",
            Dock = DockStyle.Top,
            Height = 34,
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 12, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleRight
        });

        var opFlow = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoScroll = true,
            Padding = new Padding(0, 8, 0, 0),
            RightToLeft = RightToLeft.Yes
        };
        operations.Controls.Add(opFlow);
        opFlow.BringToFront();

        Button Op(string text, Color color, string operation)
        {
            var b = ActionButton(text, color, 210, 44);
            b.Margin = new Padding(3, 4, 3, 4);
            b.Click += async (_, _) => await RunProgrammerDemo(operation);
            return b;
        }

        opFlow.Controls.Add(Op("🔎 شناسایی ECU", Cyan, "IDENTIFY"));
        opFlow.Controls.Add(Op("⬇ خواندن حافظه", Cyan, "READ"));
        opFlow.Controls.Add(Op("▣ تهیه نسخه پشتیبان", Purple, "BACKUP"));

        var openFile = ActionButton("📂 باز کردن فایل", PanelBg, 210, 44);
        openFile.ForeColor = TextMain;
        openFile.Margin = new Padding(3, 4, 3, 4);
        openFile.Click += (_, _) => MessageBox.Show(
            "در نسخه دمو فایل واقعی روی ECU نوشته نمی‌شود.\nاین دکمه در نسخه سخت‌افزاری برای انتخاب فایل Flash/EEPROM استفاده خواهد شد.",
            "باز کردن فایل",
            MessageBoxButtons.OK,
            MessageBoxIcon.Information);
        opFlow.Controls.Add(openFile);

        opFlow.Controls.Add(Op("⬆ نوشتن برنامه", Amber, "WRITE"));
        opFlow.Controls.Add(Op("✓ وریفای / مقایسه", Green, "VERIFY"));
        opFlow.Controls.Add(Op("↻ پاک کردن بافر", Color.FromArgb(72, 89, 111), "CLEAR"));
        opFlow.Controls.Add(Op("■ قطع اضطراری", Red, "EMERGENCY OFF"));

        // Center: buffer / status.
        var centerPane = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(7, 0, 7, 0),
            BackColor = Panel2,
            BorderColor = Border,
            Radius = 16,
            Padding = new Padding(12),
            RightToLeft = RightToLeft.Yes
        };
        work.Controls.Add(centerPane, 1, 0);

        var centerHeader = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            Height = 74,
            ColumnCount = 4,
            RightToLeft = RightToLeft.No,
            Margin = Padding.Empty
        };
        for (int i = 0; i < 4; i++) centerHeader.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 25));
        centerPane.Controls.Add(centerHeader);

        centerHeader.Controls.Add(ProgrammerInfoTile("ارتباط", "Bench / K-Line", Cyan), 0, 0);
        centerHeader.Controls.Add(ProgrammerInfoTile("ولتاژ ECU", "13.50 V", Green), 1, 0);
        centerHeader.Controls.Add(ProgrammerInfoTile("جریان", "0.74 A", Amber), 2, 0);
        centerHeader.Controls.Add(ProgrammerInfoTile("حافظه", "FLASH + EEPROM", Purple), 3, 0);

        var tabs = new TabControl
        {
            Dock = DockStyle.Fill,
            Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
            RightToLeft = RightToLeft.Yes,
            RightToLeftLayout = true
        };
        centerPane.Controls.Add(tabs);
        tabs.BringToFront();

        var bufferTab = new TabPage("بافر برنامه") { BackColor = Color.FromArgb(11, 18, 28), ForeColor = TextMain };
        var logTab = new TabPage("گزارش عملیات") { BackColor = Color.FromArgb(11, 18, 28), ForeColor = TextMain };
        var wiringTab = new TabPage("راهنمای اتصال") { BackColor = Color.FromArgb(11, 18, 28), ForeColor = TextMain };
        tabs.TabPages.AddRange(new[] { bufferTab, logTab, wiringTab });

        var hexBox = new RichTextBox
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(7, 13, 21),
            ForeColor = Color.FromArgb(164, 225, 238),
            BorderStyle = BorderStyle.None,
            ReadOnly = true,
            Font = new Font("Consolas", 10),
            RightToLeft = RightToLeft.No,
            Text =
@"00000000  2E 7A 91 00 10 4F 20 11  7C A0 55 18 02 00 80 FF
00000010  10 21 83 44 00 00 5A C1  19 A4 7E 2B 10 30 90 0D
00000020  42 4F 53 43 48 20 4D 45  37 2E 34 2E 34 00 00 00
00000030  31 30 33 37 33 39 38 31  30 30 00 00 20 26 09 24
00000040  FF FF 00 11 36 90 41 22  18 02 C0 7A 61 09 30 5F
00000050  01 04 0A 10 12 1E 2A 30  40 50 60 70 80 90 A0 B0

                   نمایش دمو — داده بالا نمونه ساختگی است"
        };
        bufferTab.Controls.Add(hexBox);

        var log = new RichTextBox
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(7, 13, 21),
            ForeColor = TextMuted,
            BorderStyle = BorderStyle.None,
            ReadOnly = true,
            Font = new Font("Segoe UI", 9.5f),
            RightToLeft = RightToLeft.Yes,
            Text =
@"[آماده] سخت‌افزار در حالت دمو
[ایمنی] خروجی‌های واقعی غیرفعال هستند
[ECU] Bosch ME7.4.4 - Demo Profile
[ارتباط] Bench / K-Line
[حافظه] Flash + EEPROM
[وضعیت] آماده اجرای عملیات شبیه‌سازی"
        };
        logTab.Controls.Add(log);

        wiringTab.Controls.Add(new Label
        {
            Dock = DockStyle.Fill,
            Text =
@"راهنمای اتصال پروفایل انتخابی

• برق اصلی ECU:  +12V کنترل‌شده
• زمین ECU:      GND
• روش ارتباط:    K-Line / Bench
• مسیر پروگرام:  بر اساس پروفایل ECU
• حفاظت:         کنترل جریان و قطع اضطراری

در نسخه واقعی، تصویر سوکت ECU و پین‌های موردنیاز همین‌جا نمایش داده می‌شوند.",
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 10.5f),
            Padding = new Padding(18),
            TextAlign = ContentAlignment.TopRight
        });

        // Right: ECU/profile selector.
        var selector = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(7, 0, 0, 0),
            BackColor = Panel2,
            BorderColor = Border,
            Radius = 16,
            Padding = new Padding(12),
            RightToLeft = RightToLeft.Yes
        };
        work.Controls.Add(selector, 2, 0);
        selector.Controls.Add(new Label
        {
            Text = "انتخاب ECU / پروفایل",
            Dock = DockStyle.Top,
            Height = 34,
            ForeColor = TextMain,
            Font = new Font("Segoe UI", 12, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleRight
        });

        var search = new TextBox
        {
            Dock = DockStyle.Top,
            Height = 34,
            BackColor = Color.FromArgb(11, 18, 28),
            ForeColor = TextMain,
            BorderStyle = BorderStyle.FixedSingle,
            Font = new Font("Segoe UI", 10),
            PlaceholderText = "جستجوی ECU، خودرو یا سازنده...",
            RightToLeft = RightToLeft.Yes
        };
        selector.Controls.Add(search);
        search.BringToFront();

        var tree = new TreeView
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(11, 18, 28),
            ForeColor = TextMain,
            BorderStyle = BorderStyle.None,
            Font = new Font("Segoe UI", 9.5f),
            HideSelection = false,
            FullRowSelect = true,
            RightToLeft = RightToLeft.Yes,
            RightToLeftLayout = true,
            ItemHeight = 28
        };
        selector.Controls.Add(tree);
        tree.BringToFront();

        var bosch = tree.Nodes.Add("Bosch");
        bosch.Nodes.Add("ME7.4.4 — پژو 206");
        bosch.Nodes.Add("ME7.4.5");
        bosch.Nodes.Add("ME7.4.9");
        bosch.Nodes.Add("ME17 — Bench/GPT");

        var valeo = tree.Nodes.Add("Valeo / Sagem");
        valeo.Nodes.Add("J34P");
        valeo.Nodes.Add("S2000");
        valeo.Nodes.Add("4PL");

        var siemens = tree.Nodes.Add("Siemens / Continental");
        siemens.Nodes.Add("SIM2K");
        siemens.Nodes.Add("CIM");
        siemens.Nodes.Add("Continental قدیمی");

        var other = tree.Nodes.Add("سایر");
        other.Nodes.Add("Delphi");
        other.Nodes.Add("Denso");
        other.Nodes.Add("Marelli");
        bosch.Expand();

        tree.AfterSelect += (_, e) =>
        {
            if (e.Node.Nodes.Count == 0)
            {
                ecuLabel.Text = $"ECU: {e.Node.Text} (DEMO)";
                progStatus.Text = $"پروفایل انتخاب شد: {e.Node.Text}";
                progStatus.ForeColor = Cyan;
            }
        };

        search.TextChanged += (_, _) =>
        {
            string q = search.Text.Trim();
            if (string.IsNullOrWhiteSpace(q)) return;

            foreach (TreeNode root in tree.Nodes)
            foreach (TreeNode child in root.Nodes)
                if (child.Text.Contains(q, StringComparison.OrdinalIgnoreCase))
                {
                    tree.SelectedNode = child;
                    child.EnsureVisible();
                    return;
                }
        };

        // Bottom status / progress.
        progStatus = new Label
        {
            Text = "آماده — یک ECU را انتخاب کنید یا شناسایی خودکار را بزنید",
            ForeColor = TextMuted,
            Font = new Font("Segoe UI", 10.5f, FontStyle.Bold),
            Location = new Point(30, 596),
            Size = new Size(1035, 30),
            TextAlign = ContentAlignment.MiddleCenter,
            Anchor = AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right
        };
        card.Controls.Add(progStatus);

        progProgress = new ProgressBar
        {
            Minimum = 0,
            Maximum = 100,
            Value = 0,
            Location = new Point(30, 630),
            Size = new Size(1035, 22),
            Anchor = AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right
        };
        card.Controls.Add(progProgress);

        return page;
    }

    Control ProgrammerInfoTile(string title, string value, Color accent)
    {
        var tile = new RoundedPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(3),
            BackColor = Color.FromArgb(18, 29, 43),
            BorderColor = Color.FromArgb(44, 61, 80),
            Radius = 12
        };

        tile.Controls.Add(new Label
        {
            Text = title,
            Dock = DockStyle.Top,
            Height = 26,
            ForeColor = TextMuted,
            Font = new Font("Segoe UI", 8.5f),
            TextAlign = ContentAlignment.BottomCenter
        });

        tile.Controls.Add(new Label
        {
            Text = value,
            Dock = DockStyle.Fill,
            ForeColor = accent,
            Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleCenter
        });

        return tile;
    }

    async Task RunProgrammerDemo(string op)
    {
        if (!demoMode) { MessageBox.Show("ابتدا حالت دمو را فعال کنید."); return; }
        progProgress.Value = 0;
        for (int i = 0; i <= 100; i += 4)
        {
            string fa = op switch
            {
                "IDENTIFY" => "شناسایی ECU",
                "READ" => "خواندن حافظه",
                "BACKUP" => "تهیه نسخه پشتیبان",
                "WRITE" => "نوشتن برنامه",
                "VERIFY" => "وریفای / مقایسه",
                "CLEAR" => "پاک کردن بافر",
                "EMERGENCY OFF" => "قطع اضطراری",
                _ => op
            };
            progStatus.Text = $"{fa} — {i}%  |  حالت دمو";
            progProgress.Value = i;
            await Task.Delay(45);
        }
        string done = op switch
        {
            "IDENTIFY" => "شناسایی ECU",
            "READ" => "خواندن حافظه",
            "BACKUP" => "تهیه نسخه پشتیبان",
            "WRITE" => "نوشتن برنامه",
            "VERIFY" => "وریفای / مقایسه",
            "CLEAR" => "پاک کردن بافر",
            "EMERGENCY OFF" => "قطع اضطراری",
            _ => op
        };
        progStatus.Text = $"✓ {done} در حالت دمو با موفقیت پایان یافت";
        progStatus.ForeColor = Green;
    }

    Control CreateSettingsPage()
    {
        var page = NewPage();
        var card = FullCard(page, "تنظیمات", "تنظیمات رابط کاربری و رفتار حالت دمو");

        var demoInfo = new Label
        {
            Text = "نسخه: Demo Preview 0.1\nرابط: فارسی / راست‌چین\nپلتفرم هدف: Windows x64\nسخت‌افزار هدف: STM32H743 + Real-Time I/O\n\nحالت دمو برای بررسی کامل رابط کاربری قبل از ساخت برد فعال است.",
            ForeColor = TextMain, Font = new Font("Segoe UI", 11), Location = new Point(50, 120), Size = new Size(800, 230), TextAlign = ContentAlignment.TopRight
        };
        card.Controls.Add(demoInfo);

        var exitDemo = ActionButton("خروج از حالت دمو", Red, 260, 52);
        exitDemo.Location = new Point(50, 390);
        exitDemo.Click += (_, _) => { demoMode = false; demoTimer.Stop(); demoBadge.Text = "● آماده"; demoBadge.ForeColor = TextMuted; connectionLabel.Text = "○ دستگاه متصل نیست"; connectionLabel.ForeColor = Red; ShowPage("شروع"); };
        card.Controls.Add(exitDemo);
        return page;
    }

    RoundedPanel FullCard(Control page, string title, string subtitle)
    {
        var card = new RoundedPanel { Dock = DockStyle.Fill, BackColor = PanelBg, BorderColor = Border, Radius = 22, Padding = new Padding(20) };
        page.Controls.Add(card);
        card.Controls.Add(new Label { Text = title, ForeColor = TextMain, Font = new Font("Segoe UI", 18, FontStyle.Bold), Location = new Point(28, 18), Size = new Size(600, 36), TextAlign = ContentAlignment.MiddleRight });
        card.Controls.Add(new Label { Text = subtitle, ForeColor = TextMuted, Font = new Font("Segoe UI", 10), Location = new Point(28, 55), Size = new Size(700, 28), TextAlign = ContentAlignment.MiddleRight });
        return card;
    }

    Label SectionTitle(string text) => new() { Text = text, Dock = DockStyle.Top, Height = 42, ForeColor = TextMain, Font = new Font("Segoe UI", 13, FontStyle.Bold), TextAlign = ContentAlignment.MiddleRight };

    Panel NewPage() => new() { BackColor = Bg, Padding = new Padding(4) };

    Button ActionButton(string text, Color color, int width, int height)
    {
        var b = new Button
        {
            Text = text,
            Size = new Size(width, height),
            BackColor = color,
            ForeColor = color == Panel2 ? TextMain : Color.FromArgb(7, 16, 22),
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 11, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        b.FlatAppearance.BorderSize = 0;
        return b;
    }

    void StartDemo()
    {
        demoMode = true;
        demoBadge.Text = "● حالت دمو فعال";
        demoBadge.ForeColor = Cyan;
        connectionLabel.Text = "● DEMO — ECU واقعی متصل نیست";
        connectionLabel.ForeColor = Amber;
        ecuLabel.Text = "ECU: Bosch ME7.4.4 (DEMO)";
        demoTimer.Start();
        ShowPage("داشبورد");
    }

    void ShowPage(string key)
    {
        foreach (var kv in pages) kv.Value.Visible = kv.Key == key;
        foreach (var kv in navButtons) kv.Value.Active = kv.Key == key;
        if (pages.TryGetValue(key, out var page)) page.BringToFront();
    }

    void TickDemo()
    {
        if (!demoMode) return;
        phase += 0.16;

        double rpm = Math.Max(0, rpmSlider.Value + Math.Sin(phase) * Math.Min(90, rpmSlider.Value * .04 + 8));
        double temp = tempSlider.Value + Math.Sin(phase * .18) * .8;
        double tps = Math.Clamp(throttleSlider.Value + Math.Sin(phase * .4) * 1.2, 0, 100);
        double map = 30 + tps * .58 + Math.Sin(phase * .7) * 2.5;
        double volt = 13.78 + Math.Sin(phase * .24) * .07;
        double current = .55 + rpm / 8000.0 * .55 + (tps / 100.0) * .18 + Math.Sin(phase) * .03;

        rpmCard.SetValue($"{rpm:0}", "RPM");
        tempCard.SetValue($"{temp:0.0}", "°C");
        tpsCard.SetValue($"{tps:0.0}", "%");
        mapCard.SetValue($"{map:0.0}", "kPa");
        voltCard.SetValue($"{volt:0.00}", "V");
        currentCard.SetValue($"{current:0.00}", "A");

        double pulse = 2.1 + tps * .035 + rpm / 4000.0 * .35;
        double dwell = 2.6 + Math.Sin(phase * .25) * .15;

        for (int i = 0; i < injectorLeds.Length; i++)
            injectorLeds[i].SetState(((int)(phase * 4 + i) % 6) < 2 && rpm > 100);
        for (int i = 0; i < coilLeds.Length; i++)
            coilLeds[i].SetState(((int)(phase * 3 + i * 2) % 8) < 2 && rpm > 100);

        fuelPumpLed.SetState(true);
        fanLowLed.SetState(temp >= 92);
        fanHighLed.SetState(temp >= 103);
        immoLed.SetState(rpm < 100);           // red only when immobilizer is blocking start
        oxygenLed.SetState(rpm > 650 && temp > 55);
        ckpLed.SetState(rpm > 100);
        cmpLed.SetState(rpm > 100);

        scope.Rpm = rpm;
        scope.Phase = phase;
        scope.Invalidate();

        if (liveGrid != null && liveGrid.Rows.Count >= 9)
        {
            SetGrid(0, $"{rpm:0}", rpm > 0 ? "فعال" : "خاموش");
            SetGrid(1, $"{temp:0.0}", "نرمال");
            SetGrid(2, $"{tps:0.0}", "نرمال");
            SetGrid(3, $"{map:0.0}", "نرمال");
            SetGrid(4, $"{volt:0.00}", "نرمال");
            SetGrid(5, $"{current:0.00}", "نرمال");
            SetGrid(6, $"{pulse:0.00}", "فعال");
            SetGrid(7, $"{dwell:0.00}", "فعال");
            SetGrid(8, $"{rpm / 60.0 * 58:0}", "فعال");
        }
    }

    void SetGrid(int row, string value, string state)
    {
        liveGrid.Rows[row].Cells[1].Value = value;
        liveGrid.Rows[row].Cells[3].Value = state;
        liveGrid.Rows[row].Cells[3].Style.ForeColor = state == "نرمال" || state == "فعال" ? Green : Amber;
    }
}

public sealed class NavButton : Button
{
    bool active;
    public bool Active { get => active; set { active = value; Invalidate(); } }

    public NavButton()
    {
        FlatStyle = FlatStyle.Flat;
        FlatAppearance.BorderSize = 0;
        BackColor = Color.Transparent;
        ForeColor = Color.FromArgb(178, 191, 207);
        Font = new Font("Segoe UI", 10.5f, FontStyle.Bold);
        TextAlign = ContentAlignment.MiddleRight;
        Cursor = Cursors.Hand;
        Padding = new Padding(12, 0, 14, 0);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        if (Active)
        {
            using var b = new SolidBrush(Color.FromArgb(27, 55, 72));
            using var p = Rounded(ClientRectangle, 12);
            e.Graphics.FillPath(b, p);
        }
        base.OnPaint(e);
    }

    static GraphicsPath Rounded(Rectangle r, int radius)
    {
        var p = new GraphicsPath();
        int d = radius * 2;
        p.AddArc(r.X, r.Y, d, d, 180, 90);
        p.AddArc(r.Right - d, r.Y, d, d, 270, 90);
        p.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        p.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
        p.CloseFigure();
        return p;
    }
}

public class RoundedPanel : Panel
{
    public int Radius { get; set; } = 18;
    public Color BorderColor { get; set; } = Color.FromArgb(45, 58, 78);

    public RoundedPanel()
    {
        DoubleBuffered = true;
        ResizeRedraw = true;
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        var r = new Rectangle(0, 0, Width - 1, Height - 1);
        using var path = MakePath(r, Radius);
        using var pen = new Pen(BorderColor, 1);
        e.Graphics.DrawPath(pen, path);
    }

    static GraphicsPath MakePath(Rectangle r, int radius)
    {
        var p = new GraphicsPath();
        int d = Math.Min(radius * 2, Math.Min(r.Width, r.Height));
        p.AddArc(r.X, r.Y, d, d, 180, 90);
        p.AddArc(r.Right - d, r.Y, d, d, 270, 90);
        p.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        p.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
        p.CloseFigure();
        return p;
    }
}

public sealed class MetricCard : RoundedPanel
{
    readonly Label valueLabel;
    readonly Label unitLabel;

    public MetricCard(string title, string value, string unit, Color accent)
    {
        BackColor = Color.FromArgb(17, 24, 39);
        BorderColor = Color.FromArgb(45, 58, 78);
        Radius = 18;
        Controls.Add(new Label { Text = title, Dock = DockStyle.Top, Height = 39, ForeColor = Color.FromArgb(151, 166, 184), Font = new Font("Segoe UI", 9.5f, FontStyle.Bold), TextAlign = ContentAlignment.BottomCenter });
        valueLabel = new Label { Text = value, Dock = DockStyle.Fill, ForeColor = accent, Font = new Font("Segoe UI", 25, FontStyle.Bold), TextAlign = ContentAlignment.MiddleCenter };
        Controls.Add(valueLabel);
        unitLabel = new Label { Text = unit, Dock = DockStyle.Bottom, Height = 34, ForeColor = Color.FromArgb(151, 166, 184), Font = new Font("Segoe UI", 9), TextAlign = ContentAlignment.TopCenter };
        Controls.Add(unitLabel);
        valueLabel.BringToFront();
    }

    public void SetValue(string value, string unit)
    {
        valueLabel.Text = value;
        unitLabel.Text = unit;
    }
}

public sealed class LedLamp : UserControl
{
    readonly string caption;
    readonly Color onColor;
    readonly string iconType;
    readonly string onText;
    readonly string offText;
    bool state;

    public LedLamp(string caption, Color onColor, string iconType = "dot", string onText = "فعال", string offText = "خاموش")
    {
        this.caption = caption;
        this.onColor = onColor;
        this.iconType = iconType;
        this.onText = onText;
        this.offText = offText;

        Size = new Size(108, 98);
        MinimumSize = new Size(100, 94);
        Margin = new Padding(5);
        DoubleBuffered = true;
        BackColor = Color.Transparent;
        Cursor = Cursors.Default;
    }

    public void SetState(bool on)
    {
        if (state == on) return;
        state = on;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;

        var card = new Rectangle(1, 1, Width - 3, Height - 3);
        using var cardPath = Rounded(card, 13);
        using var cardBrush = new SolidBrush(state ? Color.FromArgb(24, onColor) : Color.FromArgb(22, 31, 45));
        using var borderPen = new Pen(state ? Color.FromArgb(125, onColor) : Color.FromArgb(50, 66, 86), 1.2f);
        e.Graphics.FillPath(cardBrush, cardPath);
        e.Graphics.DrawPath(borderPen, cardPath);

        int cx = Width / 2;
        int cy = 27;

        if (state)
        {
            using var glow = new SolidBrush(Color.FromArgb(35, onColor));
            e.Graphics.FillEllipse(glow, cx - 25, cy - 24, 50, 50);
        }

        using (var iconBg = new SolidBrush(state ? Color.FromArgb(215, onColor) : Color.FromArgb(48, 63, 82)))
            e.Graphics.FillEllipse(iconBg, cx - 19, cy - 19, 38, 38);

        DrawPictogram(e.Graphics, new Rectangle(cx - 15, cy - 15, 30, 30),
            state ? Color.FromArgb(5, 15, 22) : Color.FromArgb(195, 208, 220));

        TextRenderer.DrawText(
            e.Graphics,
            caption,
            new Font("Segoe UI", 8.4f, FontStyle.Bold),
            new Rectangle(4, 52, Width - 8, 22),
            state ? Color.White : Color.FromArgb(182, 194, 208),
            TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);

        TextRenderer.DrawText(
            e.Graphics,
            state ? onText : offText,
            new Font("Segoe UI", 7.4f),
            new Rectangle(4, 75, Width - 8, 17),
            state ? onColor : Color.FromArgb(117, 132, 151),
            TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }

    void DrawPictogram(Graphics g, Rectangle r, Color c)
    {
        using var p = new Pen(c, 2.1f) { StartCap = LineCap.Round, EndCap = LineCap.Round, LineJoin = LineJoin.Round };
        using var b = new SolidBrush(c);

        float x = r.X, y = r.Y, w = r.Width, h = r.Height;

        switch (iconType)
        {
            case "injector":
                // Injector body + nozzle + electrical connector.
                g.DrawRectangle(p, x + w * .25f, y + h * .20f, w * .42f, h * .36f);
                g.DrawLine(p, x + w * .34f, y + h * .56f, x + w * .34f, y + h * .73f);
                g.DrawLine(p, x + w * .58f, y + h * .56f, x + w * .58f, y + h * .73f);
                g.DrawLine(p, x + w * .34f, y + h * .73f, x + w * .46f, y + h * .88f);
                g.DrawLine(p, x + w * .58f, y + h * .73f, x + w * .46f, y + h * .88f);
                g.DrawLine(p, x + w * .67f, y + h * .30f, x + w * .83f, y + h * .30f);
                g.DrawLine(p, x + w * .83f, y + h * .30f, x + w * .83f, y + h * .47f);
                g.FillEllipse(b, x + w * .42f, y + h * .89f, 3.2f, 3.2f);
                break;

            case "sparkplug":
                // Spark plug porcelain + hex + electrode/spark.
                g.DrawRectangle(p, x + w * .38f, y + h * .12f, w * .24f, h * .34f);
                g.DrawLine(p, x + w * .32f, y + h * .46f, x + w * .68f, y + h * .46f);
                g.DrawLine(p, x + w * .28f, y + h * .54f, x + w * .72f, y + h * .54f);
                g.DrawLine(p, x + w * .39f, y + h * .54f, x + w * .39f, y + h * .78f);
                g.DrawLine(p, x + w * .61f, y + h * .54f, x + w * .61f, y + h * .78f);
                g.DrawLine(p, x + w * .39f, y + h * .78f, x + w * .55f, y + h * .78f);
                g.DrawLine(p, x + w * .66f, y + h * .72f, x + w * .82f, y + h * .63f);
                g.DrawLine(p, x + w * .82f, y + h * .63f, x + w * .74f, y + h * .82f);
                break;

            case "oxygen":
                // O2 sensor body, threaded section and cable.
                g.DrawEllipse(p, x + w * .28f, y + h * .18f, w * .34f, h * .34f);
                g.DrawLine(p, x + w * .45f, y + h * .52f, x + w * .45f, y + h * .74f);
                g.DrawLine(p, x + w * .35f, y + h * .61f, x + w * .55f, y + h * .61f);
                g.DrawLine(p, x + w * .35f, y + h * .68f, x + w * .55f, y + h * .68f);
                g.DrawLine(p, x + w * .45f, y + h * .18f, x + w * .70f, y + h * .08f);
                g.DrawBezier(p, x + w * .70f, y + h * .08f, x + w * .90f, y + h * .08f, x + w * .82f, y + h * .32f, x + w * .94f, y + h * .36f);
                g.DrawString("O₂", new Font("Segoe UI", 6.5f, FontStyle.Bold), b, x + w * .12f, y + h * .72f);
                break;

            case "fuelpump":
                g.DrawRectangle(p, x + w * .24f, y + h * .26f, w * .36f, h * .48f);
                g.DrawLine(p, x + w * .31f, y + h * .36f, x + w * .53f, y + h * .36f);
                g.DrawArc(p, x + w * .52f, y + h * .30f, w * .25f, h * .30f, 270, 180);
                g.DrawLine(p, x + w * .75f, y + h * .44f, x + w * .75f, y + h * .70f);
                break;

            case "fan":
            case "fanfast":
                g.DrawEllipse(p, x + w * .21f, y + h * .18f, w * .58f, h * .58f);
                g.FillEllipse(b, x + w * .46f, y + h * .43f, w * .08f, h * .08f);
                for (int i = 0; i < 4; i++)
                {
                    double a = i * Math.PI / 2;
                    float sx = x + w * .50f + (float)Math.Cos(a) * w * .08f;
                    float sy = y + h * .47f + (float)Math.Sin(a) * h * .08f;
                    float ex = x + w * .50f + (float)Math.Cos(a + .50) * w * .24f;
                    float ey = y + h * .47f + (float)Math.Sin(a + .50) * h * .24f;
                    g.DrawLine(p, sx, sy, ex, ey);
                }
                if (iconType == "fanfast")
                {
                    g.DrawLine(p, x + w * .78f, y + h * .24f, x + w * .94f, y + h * .24f);
                    g.DrawLine(p, x + w * .80f, y + h * .36f, x + w * .96f, y + h * .36f);
                }
                break;

            case "mil":
                // Engine silhouette.
                var ep = new GraphicsPath();
                ep.AddLines(new[]
                {
                    new PointF(x+w*.18f,y+h*.42f), new PointF(x+w*.30f,y+h*.42f),
                    new PointF(x+w*.36f,y+h*.28f), new PointF(x+w*.64f,y+h*.28f),
                    new PointF(x+w*.70f,y+h*.38f), new PointF(x+w*.83f,y+h*.38f),
                    new PointF(x+w*.83f,y+h*.66f), new PointF(x+w*.70f,y+h*.66f),
                    new PointF(x+w*.62f,y+h*.78f), new PointF(x+w*.32f,y+h*.78f),
                    new PointF(x+w*.24f,y+h*.68f), new PointF(x+w*.18f,y+h*.68f)
                });
                ep.CloseFigure();
                g.DrawPath(p, ep);
                g.DrawLine(p, x+w*.40f, y+h*.28f, x+w*.40f, y+h*.17f);
                g.DrawLine(p, x+w*.56f, y+h*.28f, x+w*.56f, y+h*.17f);
                break;

            case "immo":
                // Key + lock/coil hint.
                g.DrawEllipse(p, x + w * .16f, y + h * .28f, w * .28f, h * .28f);
                g.DrawLine(p, x + w * .42f, y + h * .42f, x + w * .78f, y + h * .42f);
                g.DrawLine(p, x + w * .66f, y + h * .42f, x + w * .66f, y + h * .58f);
                g.DrawLine(p, x + w * .78f, y + h * .42f, x + w * .78f, y + h * .54f);
                g.DrawArc(p, x + w * .50f, y + h * .12f, w * .30f, h * .24f, 180, 180);
                break;

            case "ckp":
                // Crank sensor facing toothed wheel.
                g.DrawRectangle(p, x + w * .12f, y + h * .28f, w * .25f, h * .36f);
                g.DrawLine(p, x + w * .37f, y + h * .46f, x + w * .51f, y + h * .46f);
                g.DrawArc(p, x + w * .48f, y + h * .20f, w * .35f, h * .55f, 65, 230);
                for (int i=0;i<4;i++)
                {
                    float yy=y+h*(.28f+i*.12f);
                    g.DrawLine(p,x+w*.74f,yy,x+w*.88f,yy);
                }
                break;

            case "cmp":
                // Cam sensor + cam lobe.
                g.DrawRectangle(p, x + w * .12f, y + h * .28f, w * .24f, h * .36f);
                g.DrawLine(p, x + w * .36f, y + h * .46f, x + w * .50f, y + h * .46f);
                g.DrawEllipse(p, x + w * .52f, y + h * .29f, w * .28f, h * .34f);
                g.DrawEllipse(p, x + w * .64f, y + h * .16f, w * .16f, h * .20f);
                break;

            default:
                g.FillEllipse(b, x + w * .39f, y + h * .39f, w * .22f, h * .22f);
                break;
        }
    }

    static GraphicsPath Rounded(Rectangle r, int radius)
    {
        var p = new GraphicsPath();
        int d = radius * 2;
        p.AddArc(r.X, r.Y, d, d, 180, 90);
        p.AddArc(r.Right - d, r.Y, d, d, 270, 90);
        p.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        p.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
        p.CloseFigure();
        return p;
    }
}

public sealed class ScopeControl : Control
{
    public double Rpm { get; set; }
    public double Phase { get; set; }

    public ScopeControl()
    {
        DoubleBuffered = true;
        BackColor = Color.FromArgb(6, 12, 19);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        var g = e.Graphics;

        using var gridPen = new Pen(Color.FromArgb(28, 47, 61), 1);
        for (int x = 0; x < Width; x += 40) g.DrawLine(gridPen, x, 0, x, Height);
        for (int y = 0; y < Height; y += 40) g.DrawLine(gridPen, 0, y, Width, y);

        DrawSquare(g, Height * .19f, Color.FromArgb(24, 202, 231), 60, 2, missingTooth: true);
        DrawSquare(g, Height * .40f, Color.FromArgb(142, 100, 255), 180, 1, missingTooth: false);
        DrawPulseTrain(g, Height * .62f, Color.FromArgb(46, 204, 113), 130, 18);
        DrawPulseTrain(g, Height * .82f, Color.FromArgb(255, 183, 77), 190, 25);

        TextRenderer.DrawText(g, $"RPM {Rpm:0}", new Font("Segoe UI", 9, FontStyle.Bold), new Point(12, 10), Color.FromArgb(180, 200, 215));
    }

    void DrawSquare(Graphics g, float y, Color color, int period, int highWidth, bool missingTooth)
    {
        using var p = new Pen(color, 2);
        float amp = 28;
        float offset = (float)(Phase * 7 % period);
        bool high = false;
        var points = new List<PointF> { new(-period + offset, y) };
        for (float x = -period + offset; x < Width + period; x += period / 2f)
        {
            bool skip = missingTooth && ((int)((x + period * 10) / period) % 15 == 0);
            float nextY = skip ? y : (high ? y : y - amp);
            points.Add(new PointF(x, points[^1].Y));
            points.Add(new PointF(x, nextY));
            high = !high;
        }
        if (points.Count > 1) g.DrawLines(p, points.ToArray());
    }

    void DrawPulseTrain(Graphics g, float y, Color color, int period, int width)
    {
        using var p = new Pen(color, 2);
        float amp = 32;
        float offset = (float)(Phase * 10 % period);
        for (float x = -period + offset; x < Width; x += period)
        {
            g.DrawLine(p, x, y, x, y - amp);
            g.DrawLine(p, x, y - amp, x + width, y - amp);
            g.DrawLine(p, x + width, y - amp, x + width, y);
        }
    }
}
