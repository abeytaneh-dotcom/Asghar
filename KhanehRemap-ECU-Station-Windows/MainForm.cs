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
    ScopeControl scope = null!;
    LedLamp[] injectorLeds = Array.Empty<LedLamp>();
    LedLamp[] coilLeds = Array.Empty<LedLamp>();
    LedLamp fuelPumpLed = null!;
    LedLamp fanLowLed = null!;
    LedLamp fanHighLed = null!;
    LedLamp milLed = null!;
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
        Text = "خانه ریمپ | ECU Station";
        BackColor = Bg;
        ForeColor = TextMain;
        Font = new Font("Segoe UI", 10f);
        MinimumSize = new Size(1180, 760);
        WindowState = FormWindowState.Maximized;
        StartPosition = FormStartPosition.CenterScreen;
        RightToLeft = RightToLeft.Yes;
        RightToLeftLayout = true;
        DoubleBuffered = true;

        BuildShell();
        BuildPages();
        ShowPage("شروع");

        demoTimer.Tick += (_, _) => TickDemo();
        FormClosing += (_, _) => demoTimer.Stop();
    }

    void BuildShell()
    {
        var sidebar = new Panel { Dock = DockStyle.Right, Width = 235, BackColor = Color.FromArgb(12, 18, 28), Padding = new Padding(12) };
        Controls.Add(sidebar);

        var brand = new Panel { Dock = DockStyle.Top, Height = 116, BackColor = Color.Transparent };
        sidebar.Controls.Add(brand);

        var logo = new Label
        {
            Text = "⚡",
            Font = new Font("Segoe UI Emoji", 27, FontStyle.Bold),
            ForeColor = Cyan,
            AutoSize = false,
            Size = new Size(54, 54),
            Location = new Point(157, 14),
            TextAlign = ContentAlignment.MiddleCenter
        };
        brand.Controls.Add(logo);

        var title = new Label
        {
            Text = "خانه ریمپ",
            Font = new Font("Segoe UI", 16, FontStyle.Bold),
            ForeColor = TextMain,
            AutoSize = false,
            Size = new Size(145, 34),
            Location = new Point(12, 15),
            TextAlign = ContentAlignment.MiddleRight
        };
        brand.Controls.Add(title);

        var sub = new Label
        {
            Text = "ECU STATION",
            Font = new Font("Segoe UI", 9, FontStyle.Bold),
            ForeColor = Cyan,
            AutoSize = false,
            Size = new Size(145, 25),
            Location = new Point(12, 49),
            TextAlign = ContentAlignment.MiddleRight
        };
        brand.Controls.Add(sub);

        demoBadge = new Label
        {
            Text = "● آماده",
            ForeColor = TextMuted,
            BackColor = PanelBg,
            AutoSize = false,
            Size = new Size(199, 28),
            Location = new Point(12, 82),
            TextAlign = ContentAlignment.MiddleCenter
        };
        brand.Controls.Add(demoBadge);

        var nav = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoScroll = true,
            Padding = new Padding(0, 8, 0, 0)
        };
        sidebar.Controls.Add(nav);
        nav.BringToFront();

        AddNav(nav, "شروع", "⌂", "شروع");
        AddNav(nav, "داشبورد", "◉", "داشبورد");
        AddNav(nav, "تست خودکار", "✓", "تست خودکار");
        AddNav(nav, "داده زنده", "≋", "داده زنده");
        AddNav(nav, "اسیلوسکوپ", "⌁", "اسیلوسکوپ");
        AddNav(nav, "دیاگ", "⚠", "دیاگ");
        AddNav(nav, "پروگرامر", "⬢", "پروگرامر");
        AddNav(nav, "تنظیمات", "⚙", "تنظیمات");

        var main = new Panel { Dock = DockStyle.Fill, BackColor = Bg };
        Controls.Add(main);

        var header = new Panel { Dock = DockStyle.Top, Height = 74, BackColor = Color.FromArgb(14, 21, 31), Padding = new Padding(24, 12, 24, 10) };
        main.Controls.Add(header);

        ecuLabel = new Label
        {
            Text = "ECU: انتخاب نشده",
            Dock = DockStyle.Right,
            Width = 340,
            Font = new Font("Segoe UI", 12, FontStyle.Bold),
            ForeColor = TextMain,
            TextAlign = ContentAlignment.MiddleRight
        };
        header.Controls.Add(ecuLabel);

        connectionLabel = new Label
        {
            Text = "○ دستگاه متصل نیست",
            Dock = DockStyle.Left,
            Width = 250,
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            ForeColor = Red,
            TextAlign = ContentAlignment.MiddleLeft
        };
        header.Controls.Add(connectionLabel);

        contentHost = new Panel { Dock = DockStyle.Fill, BackColor = Bg, Padding = new Padding(18) };
        main.Controls.Add(contentHost);
        contentHost.BringToFront();
    }

    void AddNav(FlowLayoutPanel host, string key, string icon, string text)
    {
        var b = new NavButton
        {
            Width = 202,
            Height = 48,
            Margin = new Padding(0, 3, 0, 3),
            Text = $"  {icon}   {text}",
            Tag = key
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
        var top = new TableLayoutPanel { Dock = DockStyle.Top, Height = 178, ColumnCount = 6, RowCount = 1, Padding = new Padding(0, 0, 0, 10) };
        for (int i = 0; i < 6; i++) top.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 16.666f));
        page.Controls.Add(top);

        rpmCard = AddMetric(top, "دور موتور", "0", "RPM", Cyan, 0);
        tempCard = AddMetric(top, "دمای آب", "0", "°C", Amber, 1);
        tpsCard = AddMetric(top, "دریچه گاز", "0", "%", Green, 2);
        mapCard = AddMetric(top, "فشار MAP", "0", "kPa", Purple, 3);
        voltCard = AddMetric(top, "ولتاژ ECU", "0", "V", Cyan, 4);
        currentCard = AddMetric(top, "جریان ECU", "0", "A", Amber, 5);

        var center = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 1 };
        center.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 66));
        center.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 34));
        page.Controls.Add(center);
        center.BringToFront();

        var signalCard = new RoundedPanel { Dock = DockStyle.Fill, Margin = new Padding(0, 6, 8, 0), BackColor = PanelBg, BorderColor = Border, Radius = 20, Padding = new Padding(18) };
        center.Controls.Add(signalCard, 0, 0);

        var signalTitle = SectionTitle("نمایش زنده خروجی‌های ECU");
        signalCard.Controls.Add(signalTitle);

        var lamps = new FlowLayoutPanel { Dock = DockStyle.Top, Height = 190, FlowDirection = FlowDirection.RightToLeft, WrapContents = true, Padding = new Padding(8, 20, 8, 0) };
        signalCard.Controls.Add(lamps);
        lamps.BringToFront();

        injectorLeds = Enumerable.Range(1, 6).Select(i => new LedLamp($"انژکتور {i}", Green)).ToArray();
        coilLeds = Enumerable.Range(1, 6).Select(i => new LedLamp($"کوئل {i}", Cyan)).ToArray();
        foreach (var l in injectorLeds.Concat(coilLeds)) lamps.Controls.Add(l);

        var relays = new FlowLayoutPanel { Dock = DockStyle.Bottom, Height = 105, FlowDirection = FlowDirection.RightToLeft, WrapContents = false, Padding = new Padding(8) };
        signalCard.Controls.Add(relays);
        fuelPumpLed = new LedLamp("پمپ بنزین", Amber);
        fanLowLed = new LedLamp("فن کند", Cyan);
        fanHighLed = new LedLamp("فن تند", Red);
        milLed = new LedLamp("چراغ چک", Amber);
        relays.Controls.AddRange(new Control[] { fuelPumpLed, fanLowLed, fanHighLed, milLed });

        var controlsCard = new RoundedPanel { Dock = DockStyle.Fill, Margin = new Padding(8, 6, 0, 0), BackColor = PanelBg, BorderColor = Border, Radius = 20, Padding = new Padding(18) };
        center.Controls.Add(controlsCard, 1, 0);
        controlsCard.Controls.Add(SectionTitle("کنترل شبیه‌ساز"));

        rpmSlider = AddSlider(controlsCard, "دور موتور هدف", 0, 8000, 850, 80);
        throttleSlider = AddSlider(controlsCard, "دریچه گاز", 0, 100, 18, 168);
        tempSlider = AddSlider(controlsCard, "دمای آب", -20, 125, 88, 256);

        var quick = ActionButton("▶  اجرای تست سریع", Green, 290, 52);
        quick.Location = new Point(28, 360);
        quick.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        quick.Click += (_, _) => { ShowPage("تست خودکار"); _ = RunAutoTest(); };
        controlsCard.Controls.Add(quick);

        var fault = ActionButton("⚠  تزریق خطای آزمایشی", Red, 290, 48);
        fault.Location = new Point(28, 424);
        fault.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        fault.Click += (_, _) => AddDemoDtc();
        controlsCard.Controls.Add(fault);

        return page;
    }

    MetricCard AddMetric(TableLayoutPanel top, string title, string value, string unit, Color accent, int column)
    {
        var c = new MetricCard(title, value, unit, accent) { Dock = DockStyle.Fill, Margin = new Padding(5) };
        top.Controls.Add(c, column, 0);
        return c;
    }

    TrackBar AddSlider(Control host, string title, int min, int max, int value, int y)
    {
        var label = new Label { Text = $"{title}: {value}", ForeColor = TextMain, Font = new Font("Segoe UI", 10, FontStyle.Bold), AutoSize = false, Size = new Size(300, 28), Location = new Point(25, y), TextAlign = ContentAlignment.MiddleRight };
        host.Controls.Add(label);
        var tr = new TrackBar { Minimum = min, Maximum = max, Value = value, TickStyle = TickStyle.None, Size = new Size(300, 44), Location = new Point(22, y + 32), RightToLeft = RightToLeft.No };
        tr.ValueChanged += (_, _) => label.Text = $"{title}: {tr.Value}";
        host.Controls.Add(tr);
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
        var card = FullCard(page, "پروگرامر ECU", "Bench / CAN / K-Line / JTAG / SWD / GPT — شبیه‌سازی رابط کاربری");

        var warning = new RoundedPanel { BackColor = Color.FromArgb(45, 35, 20), BorderColor = Amber, Radius = 16, Location = new Point(28, 100), Size = new Size(1040, 78), Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right };
        warning.Controls.Add(new Label { Text = "⚠ حالت دمو: هیچ عملیات خواندن/نوشتن واقعی روی ECU انجام نمی‌شود.", Dock = DockStyle.Fill, ForeColor = Amber, Font = new Font("Segoe UI", 11, FontStyle.Bold), TextAlign = ContentAlignment.MiddleCenter });
        card.Controls.Add(warning);

        string[] labels = { "ECU انتخابی", "روش اتصال", "ولتاژ پروگرام", "حافظه هدف" };
        string[] vals = { "Bosch ME7.4.4 (Demo)", "Bench / K-Line", "13.50 V", "FLASH + EEPROM" };
        for (int i = 0; i < labels.Length; i++)
        {
            var p = new RoundedPanel { BackColor = Panel2, BorderColor = Border, Radius = 14, Size = new Size(245, 90), Location = new Point(28 + i * 260, 205) };
            p.Controls.Add(new Label { Text = labels[i], ForeColor = TextMuted, Font = new Font("Segoe UI", 9), Dock = DockStyle.Top, Height = 34, TextAlign = ContentAlignment.BottomCenter });
            p.Controls.Add(new Label { Text = vals[i], ForeColor = TextMain, Font = new Font("Segoe UI", 11, FontStyle.Bold), Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleCenter });
            card.Controls.Add(p);
        }

        progStatus = new Label { Text = "آماده", ForeColor = TextMuted, Font = new Font("Segoe UI", 11, FontStyle.Bold), Location = new Point(30, 345), Size = new Size(1035, 35), TextAlign = ContentAlignment.MiddleCenter, Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right };
        card.Controls.Add(progStatus);
        progProgress = new ProgressBar { Minimum = 0, Maximum = 100, Location = new Point(30, 390), Size = new Size(1035, 30), Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right };
        card.Controls.Add(progProgress);

        var read = ActionButton("⬇ شبیه‌سازی READ", Cyan, 245, 56); read.Location = new Point(28, 455); read.Click += async (_, _) => await RunProgrammerDemo("READ"); card.Controls.Add(read);
        var backup = ActionButton("▣ BACKUP", Purple, 245, 56); backup.Location = new Point(288, 455); backup.Click += async (_, _) => await RunProgrammerDemo("BACKUP"); card.Controls.Add(backup);
        var write = ActionButton("⬆ شبیه‌سازی WRITE", Amber, 245, 56); write.Location = new Point(548, 455); write.Click += async (_, _) => await RunProgrammerDemo("WRITE"); card.Controls.Add(write);
        var verify = ActionButton("✓ VERIFY", Green, 245, 56); verify.Location = new Point(808, 455); verify.Click += async (_, _) => await RunProgrammerDemo("VERIFY"); card.Controls.Add(verify);

        return page;
    }

    async Task RunProgrammerDemo(string op)
    {
        if (!demoMode) { MessageBox.Show("ابتدا حالت دمو را فعال کنید."); return; }
        progProgress.Value = 0;
        for (int i = 0; i <= 100; i += 4)
        {
            progStatus.Text = $"{op} — {i}%  |  DEMO";
            progProgress.Value = i;
            await Task.Delay(45);
        }
        progStatus.Text = $"✓ {op} شبیه‌سازی‌شده با موفقیت پایان یافت";
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
    bool state;

    public LedLamp(string caption, Color onColor)
    {
        this.caption = caption;
        this.onColor = onColor;
        Size = new Size(128, 78);
        Margin = new Padding(7);
        DoubleBuffered = true;
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
        var c = new Point(Width / 2, 24);
        var lamp = new Rectangle(c.X - 11, c.Y - 11, 22, 22);
        if (state)
        {
            using var glow = new SolidBrush(Color.FromArgb(55, onColor));
            e.Graphics.FillEllipse(glow, c.X - 21, c.Y - 21, 42, 42);
        }
        using var b = new SolidBrush(state ? onColor : Color.FromArgb(68, 78, 92));
        e.Graphics.FillEllipse(b, lamp);
        using var pen = new Pen(state ? ControlPaint.Light(onColor) : Color.FromArgb(100, 110, 125), 2);
        e.Graphics.DrawEllipse(pen, lamp);
        TextRenderer.DrawText(e.Graphics, caption, new Font("Segoe UI", 9, FontStyle.Bold), new Rectangle(2, 48, Width - 4, 24), state ? Color.White : Color.FromArgb(151, 166, 184), TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
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
