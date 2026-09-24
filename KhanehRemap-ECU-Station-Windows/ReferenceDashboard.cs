using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Linq;
using System.Windows.Forms;

namespace KhanehRemapEcuStation;

public sealed class ReferenceDashboard : UserControl
{
    static readonly Color Bg = Color.FromArgb(3, 13, 23);
    static readonly Color Panel = Color.FromArgb(5, 24, 38);
    static readonly Color Card = Color.FromArgb(8, 31, 48);
    static readonly Color Border = Color.FromArgb(18, 83, 112);
    static readonly Color Text = Color.FromArgb(229, 240, 248);
    static readonly Color Muted = Color.FromArgb(150, 177, 198);
    static readonly Color Cyan = Color.FromArgb(0, 212, 255);
    static readonly Color Blue = Color.FromArgb(0, 130, 255);
    static readonly Color Green = Color.FromArgb(0, 235, 122);
    static readonly Color Red = Color.FromArgb(255, 68, 85);
    static readonly Color Amber = Color.FromArgb(255, 181, 45);
    static readonly Color Purple = Color.FromArgb(180, 83, 255);

    readonly System.Windows.Forms.Timer timer = new() { Interval = 140 };
    double phase;

    RefGauge rpmGauge = null!;
    RefGauge waterGauge = null!;
    RefGauge intakeGauge = null!;
    RefGauge batteryGauge = null!;
    RefGauge oxygenGauge = null!;
    RefGauge throttleGauge = null!;
    RefGauge mapGauge = null!;
    RefChannel[] injectorChannels = Array.Empty<RefChannel>();
    RefChannel[] coilChannels = Array.Empty<RefChannel>();
    RichTextBox logBox = null!;
    Label bVolt = null!;
    Label ignVolt = null!;
    Label o2State = null!;

    public ReferenceDashboard()
    {
        Dock = DockStyle.Fill;
        BackColor = Bg;
        RightToLeft = RightToLeft.Yes;
        DoubleBuffered = true;
        Padding = new Padding(2);

        Build();
        timer.Tick += (_, _) => TickDemo();
        timer.Start();
        Disposed += (_, _) => timer.Stop();
    }

    void Build()
    {
        var root = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 3,
            RowCount = 3,
            BackColor = Bg,
            Padding = new Padding(1),
            Margin = Padding.Empty,
            RightToLeft = RightToLeft.No
        };
        root.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 20));
        root.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 58));
        root.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 22));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 56));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 23));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 21));
        Controls.Add(root);

        var info = BuildInfoPanel();
        root.Controls.Add(info, 0, 0);
        root.SetRowSpan(info, 3);

        var actions = BuildActionsPanel();
        root.Controls.Add(actions, 1, 0);
        root.SetColumnSpan(actions, 2);

        root.Controls.Add(BuildGaugePanel(), 1, 1);
        root.Controls.Add(BuildPowerPanel(), 2, 1);
        root.Controls.Add(BuildLogPanel(), 1, 2);
        root.Controls.Add(BuildHealthPanel(), 2, 2);
    }

    Control BuildInfoPanel()
    {
        var p = NewPanel();
        p.Margin = new Padding(1, 1, 5, 1);

        var table = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 8,
            BackColor = Color.Transparent,
            Padding = new Padding(8),
            Margin = Padding.Empty
        };
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 42));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 248));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 78));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 66));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 66));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 66));
        table.RowStyles.Add(new RowStyle(SizeType.Absolute, 66));
        table.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        p.Controls.Add(table);

        table.Controls.Add(Title("▣  اطلاعات ECU", 13), 0, 0);

        var info = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 8,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        info.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 44));
        info.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 56));
        for (int i = 0; i < 8; i++) info.RowStyles.Add(new RowStyle(SizeType.Percent, 12.5f));

        string[,] rows =
        {
            { "نوع ECU :", "M7.4.4 - ME7.4.4" },
            { "شماره قطعه :", "IKCO 9649048180" },
            { "نسخه سخت‌افزار :", "HW Ver. 1.2" },
            { "نسخه نرم‌افزار :", "SW Ver. 2.3.1" },
            { "کالیبراسیون :", "Calib. 9928" },
            { "وضعیت ارتباط :", "● متصل" },
            { "حالت تست :", "تست رومیزی" },
            { "وضعیت :", "آماده" }
        };

        for (int i = 0; i < 8; i++)
        {
            info.Controls.Add(new Label
            {
                Text = rows[i,0],
                Dock = DockStyle.Fill,
                ForeColor = Muted,
                Font = new Font("Segoe UI", 9),
                TextAlign = ContentAlignment.MiddleRight
            }, 0, i);
            info.Controls.Add(new Label
            {
                Text = rows[i,1],
                Dock = DockStyle.Fill,
                ForeColor = i == 5 ? Green : Text,
                Font = new Font("Segoe UI", 9.2f, i == 5 ? FontStyle.Bold : FontStyle.Regular),
                TextAlign = ContentAlignment.MiddleLeft
            }, 1, i);
        }
        table.Controls.Add(info, 0, 1);

        var ready = NewPanel();
        ready.Dock = DockStyle.Fill;
        ready.Margin = new Padding(3, 5, 3, 6);
        ready.BackColor = Color.FromArgb(4, 39, 42);
        ready.BorderColor = Color.FromArgb(0, 105, 100);
        ready.Controls.Add(new Label
        {
            Text = "▣   لاگ آماده است\nتمام سیستم‌ها سالم هستند.",
            Dock = DockStyle.Fill,
            ForeColor = Green,
            Font = new Font("Segoe UI", 9.6f, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleCenter
        });
        table.Controls.Add(ready, 0, 2);

        table.Controls.Add(BigAction("▶", "حالت تست دستی", "فعال‌سازی و تست هر بخش", Cyan), 0, 3);
        table.Controls.Add(BigAction("↻", "حالت تست خودکار", "اجرای تست کامل ECU", Color.FromArgb(85,155,230)), 0, 4);
        table.Controls.Add(BigAction("▤", "خواندن اطلاعات ECU", "دریافت پارامترها و کدها", Color.FromArgb(125,172,220)), 0, 5);
        table.Controls.Add(BigAction("▰", "پاک کردن خطاها", "حذف خطاهای ذخیره‌شده", Red), 0, 6);

        return p;
    }

    Button BigAction(string icon, string title, string sub, Color accent)
    {
        var b = new Button
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(3,5,3,5),
            FlatStyle = FlatStyle.Flat,
            BackColor = Color.FromArgb(8, 32, 50),
            ForeColor = Text,
            Text = $"{icon}    {title}\n       {sub}",
            TextAlign = ContentAlignment.MiddleRight,
            Font = new Font("Segoe UI", 9.2f, FontStyle.Bold),
            Padding = new Padding(8,0,8,0),
            Cursor = Cursors.Hand,
            RightToLeft = RightToLeft.Yes
        };
        b.FlatAppearance.BorderColor = accent;
        b.FlatAppearance.BorderSize = 1;
        b.FlatAppearance.MouseOverBackColor = Color.FromArgb(13, 49, 69);
        b.Click += (_, _) => AppendLog($"{title} در حالت دمو اجرا شد.", accent);
        return b;
    }

    Control BuildActionsPanel()
    {
        var outer = NewPanel();
        outer.Margin = new Padding(5,1,1,4);
        outer.Padding = new Padding(7);
        outer.Controls.Add(Title("⚡  تست عملگرها و خروجی‌های ECU", 13));

        var grid = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 5,
            RowCount = 2,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.No,
            Margin = Padding.Empty,
            Padding = new Padding(0, 39, 0, 0)
        };
        for (int i = 0; i < 5; i++) grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 20));
        grid.RowStyles.Add(new RowStyle(SizeType.Percent, 50));
        grid.RowStyles.Add(new RowStyle(SizeType.Percent, 50));
        outer.Controls.Add(grid);
        grid.BringToFront();

        // Injector card
        var inj = ActionCard("تست انژکتورها", "injector", Red, out var injBody);
        injectorChannels = Enumerable.Range(1, 4).Select(i => new RefChannel(i.ToString(), Green)).ToArray();
        var ch1 = ChannelRow(injectorChannels);
        injBody.Controls.Add(ch1);
        injBody.Controls.Add(NumericRow("مدت پالس (ms)", 5, 1, 20));
        grid.Controls.Add(inj, 0, 0);

        // Coil card
        var coil = ActionCard("تست کوئل / جرقه", "sparkplug", Amber, out var coilBody);
        coilChannels = Enumerable.Range(1, 4).Select(i => new RefChannel(i.ToString(), Cyan)).ToArray();
        coilBody.Controls.Add(ChannelRow(coilChannels));
        coilBody.Controls.Add(NumericRow("فرکانس (Hz)", 10, 1, 100));
        grid.Controls.Add(coil, 1, 0);

        // Fuel pump
        var pump = ActionCard("پمپ بنزین", "fuelpump", Green, out var pumpBody);
        pumpBody.Controls.Add(IconState("fuelpump", Green, "پمپ آماده"));
        pumpBody.Controls.Add(NumericRow("مدت زمان (ثانیه)", 10, 1, 60));
        grid.Controls.Add(pump, 2, 0);

        // Fan
        var fan = ActionCard("فن رادیاتور", "fan", Cyan, out var fanBody);
        fanBody.Controls.Add(IconState("fan", Cyan, "کنترل دور"));
        fanBody.Controls.Add(TwoButtons("دور تند", "دور کند", Green));
        grid.Controls.Add(fan, 3, 0);

        // Throttle
        var throttle = ActionCard("دریچه گاز / موتور آرام", "throttle", Color.FromArgb(190,210,225), out var throttleBody);
        throttleBody.Controls.Add(ButtonStack(new[] { "باز کردن", "بستن", "پله‌ای" }));
        grid.Controls.Add(throttle, 4, 0);

        // Relays
        var relay = ActionCard("تست رله‌ها", "relay", Amber, out var relayBody);
        relayBody.Controls.Add(ButtonStack(new[] { "رله اصلی", "رله فن", "رله پمپ بنزین" }));
        grid.Controls.Add(relay, 0, 1);

        // Warning lamps
        var warning = ActionCard("چراغ هشدارها", "warning", Red, out var warnBody);
        warnBody.Controls.Add(WarningList());
        grid.Controls.Add(warning, 1, 1);

        // Sensor simulator
        var sensors = ActionCard("شبیه‌ساز سنسورها", "sensor", Purple, out var sensorBody);
        sensorBody.Controls.Add(CompactNumericList(new[]
        {
            ("سنسور اکسیژن (mV)", 700m, 0m, 1000m),
            ("دمای آب (°C)", 90m, -20m, 130m),
            ("دمای هوا (°C)", 30m, -20m, 100m),
            ("دریچه گاز (%)", 15m, 0m, 100m)
        }));
        grid.Controls.Add(sensors, 2, 1);

        // Commands
        var cmd = ActionCard("فرمان‌ها و عملگرها", "actuator", Cyan, out var cmdBody);
        cmdBody.Controls.Add(ButtonStack(new[] { "شیر برقی کنیستر", "عملگر EGR", "شیر برقی VVT", "سایر عملگرها" }));
        grid.Controls.Add(cmd, 3, 1);

        // Other
        var other = ActionCard("سایر تست‌ها", "gear", Color.FromArgb(195,215,230), out var otherBody);
        otherBody.Controls.Add(ButtonStack(new[] { "تست استپر موتور", "تست سنسور کیلومتر", "تست بوق و صدا", "تست چراغ‌ها" }));
        grid.Controls.Add(other, 4, 1);

        return outer;
    }

    RefPanel ActionCard(string title, string iconType, Color accent, out Panel body)
    {
        var card = new RefPanel
        {
            Dock = DockStyle.Fill,
            Margin = new Padding(4),
            BackColor = Card,
            BorderColor = Color.FromArgb(17, 74, 100),
            Radius = 10,
            Padding = new Padding(6)
        };

        var header = new Panel { Dock = DockStyle.Top, Height = 46, BackColor = Color.Transparent };
        card.Controls.Add(header);

        header.Controls.Add(new RefIcon
        {
            IconType = iconType,
            Accent = accent,
            Size = new Size(42,42),
            Location = new Point(3,2)
        });

        header.Controls.Add(new Label
        {
            Text = title,
            ForeColor = Text,
            Font = new Font("Segoe UI", 9.1f, FontStyle.Bold),
            Location = new Point(48,4),
            Size = new Size(118,34),
            TextAlign = ContentAlignment.MiddleRight,
            Anchor = AnchorStyles.Left | AnchorStyles.Right | AnchorStyles.Top
        });

        var sw = new CheckBox
        {
            Appearance = Appearance.Button,
            Checked = true,
            AutoSize = false,
            Text = "●",
            Size = new Size(42,22),
            Location = new Point(170,10),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            FlatStyle = FlatStyle.Flat,
            BackColor = Blue,
            ForeColor = Color.White,
            TextAlign = ContentAlignment.MiddleCenter,
            Cursor = Cursors.Hand
        };
        sw.FlatAppearance.BorderSize = 0;
        sw.CheckedChanged += (_, _) =>
        {
            sw.BackColor = sw.Checked ? Blue : Color.FromArgb(48,64,82);
            sw.Text = sw.Checked ? "●" : "○";
        };
        header.Controls.Add(sw);

        body = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes,
            Padding = new Padding(2)
        };
        card.Controls.Add(body);
        body.BringToFront();
        return card;
    }

    Control ChannelRow(IEnumerable<RefChannel> channels)
    {
        var f = new FlowLayoutPanel
        {
            Dock = DockStyle.Top,
            Height = 66,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = false,
            BackColor = Color.Transparent,
            Padding = new Padding(4,0,4,0)
        };
        foreach (var c in channels)
        {
            c.Size = new Size(42,58);
            c.Margin = new Padding(2);
            f.Controls.Add(c);
        }
        return f;
    }

    Control NumericRow(string text, decimal value, decimal min, decimal max)
    {
        var p = new Panel { Dock = DockStyle.Bottom, Height = 35, BackColor = Color.Transparent };
        p.Controls.Add(new Label
        {
            Text = text,
            Dock = DockStyle.Right,
            Width = 118,
            ForeColor = Muted,
            Font = new Font("Segoe UI", 7.8f),
            TextAlign = ContentAlignment.MiddleRight
        });
        p.Controls.Add(new NumericUpDown
        {
            Minimum = min,
            Maximum = max,
            Value = value,
            Dock = DockStyle.Left,
            Width = 80,
            BackColor = Color.FromArgb(13,36,53),
            ForeColor = Text,
            BorderStyle = BorderStyle.FixedSingle,
            Font = new Font("Segoe UI",8f),
            TextAlign = HorizontalAlignment.Center
        });
        return p;
    }

    Control IconState(string icon, Color accent, string text)
    {
        var p = new Panel { Dock = DockStyle.Top, Height = 80, BackColor = Color.Transparent };
        p.Controls.Add(new RefIcon { IconType = icon, Accent = accent, Size = new Size(58,58), Location = new Point(10,10) });
        p.Controls.Add(new Label
        {
            Text = text,
            ForeColor = accent,
            Font = new Font("Segoe UI",8.5f,FontStyle.Bold),
            Location = new Point(74,18),
            Size = new Size(110,34),
            TextAlign = ContentAlignment.MiddleCenter
        });
        return p;
    }

    Control TwoButtons(string a, string b, Color accent)
    {
        var t = new TableLayoutPanel { Dock = DockStyle.Bottom, Height = 38, ColumnCount = 2, BackColor = Color.Transparent };
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,50));
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,50));
        t.Controls.Add(SmallButton(a, accent),0,0);
        t.Controls.Add(SmallButton(b, Color.FromArgb(70,105,135)),1,0);
        return t;
    }

    Control ButtonStack(string[] labels)
    {
        var f = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            BackColor = Color.Transparent,
            Padding = new Padding(3)
        };
        foreach (var s in labels)
        {
            var b = SmallButton(s, Color.FromArgb(70,105,135));
            b.Width = 172;
            b.Height = 28;
            b.Margin = new Padding(2);
            b.Click += (_, _) => AppendLog($"فرمان دمو: {s}", Cyan);
            f.Controls.Add(b);
        }
        return f;
    }

    Button SmallButton(string text, Color accent)
    {
        var b = new Button
        {
            Text = text,
            Dock = DockStyle.Fill,
            Margin = new Padding(3),
            FlatStyle = FlatStyle.Flat,
            BackColor = Color.FromArgb(12,38,55),
            ForeColor = Text,
            Font = new Font("Segoe UI",8.1f,FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        b.FlatAppearance.BorderColor = accent;
        b.FlatAppearance.BorderSize = 1;
        b.FlatAppearance.MouseOverBackColor = Color.FromArgb(16,55,74);
        return b;
    }

    Control WarningList()
    {
        var t = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 4,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute,44));
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,100));
        string[] names = { "چراغ چک موتور", "چراغ ایموبلایزر", "چراغ روغن", "چراغ باتری" };
        string[] icons = { "mil", "immo", "oil", "battery" };
        Color[] colors = { Amber, Red, Red, Red };
        for(int i=0;i<4;i++)
        {
            t.RowStyles.Add(new RowStyle(SizeType.Percent,25));
            t.Controls.Add(new RefIcon { IconType=icons[i], Accent=colors[i], Dock=DockStyle.Fill, Margin=new Padding(4) },0,i);
            t.Controls.Add(new Label { Text=names[i], Dock=DockStyle.Fill, ForeColor=Text, Font=new Font("Segoe UI",8f), TextAlign=ContentAlignment.MiddleRight },1,i);
        }
        return t;
    }

    Control CompactNumericList((string label, decimal value, decimal min, decimal max)[] rows)
    {
        var t = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = rows.Length,
            BackColor = Color.Transparent,
            RightToLeft = RightToLeft.Yes
        };
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,62));
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,38));
        for(int i=0;i<rows.Length;i++)
        {
            t.RowStyles.Add(new RowStyle(SizeType.Percent,100f/rows.Length));
            t.Controls.Add(new Label { Text=rows[i].label, Dock=DockStyle.Fill, ForeColor=Muted, Font=new Font("Segoe UI",7.6f), TextAlign=ContentAlignment.MiddleRight },0,i);
            t.Controls.Add(new NumericUpDown
            {
                Minimum=rows[i].min, Maximum=rows[i].max, Value=rows[i].value,
                Dock=DockStyle.Fill, Margin=new Padding(2,3,2,3),
                BackColor=Color.FromArgb(13,36,53), ForeColor=Text,
                BorderStyle=BorderStyle.FixedSingle, Font=new Font("Segoe UI",7.8f),
                TextAlign=HorizontalAlignment.Center
            },1,i);
        }
        return t;
    }

    Control BuildGaugePanel()
    {
        var p = NewPanel();
        p.Margin = new Padding(5,3,3,3);
        p.Controls.Add(Title("⌁  نمایش زنده پارامترها و وضعیت سنسورها", 10.8f));

        var t = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 7,
            RowCount = 1,
            BackColor = Color.Transparent,
            Padding = new Padding(0,33,0,0),
            RightToLeft = RightToLeft.No
        };
        for(int i=0;i<7;i++) t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,14.2857f));
        p.Controls.Add(t);
        t.BringToFront();

        rpmGauge = new RefGauge("RPM","rpm",Cyan,"rpm");
        waterGauge = new RefGauge("دمای آب","°C",Cyan,"temp");
        intakeGauge = new RefGauge("دمای هوای ورودی","°C",Color.FromArgb(195,215,230),"temp");
        batteryGauge = new RefGauge("ولتاژ باتری","V",Green,"battery");
        oxygenGauge = new RefGauge("سنسور اکسیژن","mV",Cyan,"oxygen");
        throttleGauge = new RefGauge("موقعیت دریچه گاز","%",Cyan,"throttle");
        mapGauge = new RefGauge("فشار منیفولد (MAP)","kPa",Color.FromArgb(195,215,230),"rpm");

        var cards = new[]{rpmGauge,waterGauge,intakeGauge,batteryGauge,oxygenGauge,throttleGauge,mapGauge};
        for(int i=0;i<cards.Length;i++){ cards[i].Dock=DockStyle.Fill; cards[i].Margin=new Padding(3); t.Controls.Add(cards[i],i,0); }
        return p;
    }

    Control BuildPowerPanel()
    {
        var p = NewPanel();
        p.Margin = new Padding(3,3,1,3);
        p.Controls.Add(Title("⌁  وضعیت تغذیه و سیگنال‌ها", 10.7f));

        var f = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            BackColor = Color.Transparent,
            Padding = new Padding(4,34,4,0),
            RightToLeft = RightToLeft.Yes
        };
        p.Controls.Add(f);
        f.BringToFront();

        f.Controls.Add(StatusLine("+12 ولت دائم (B+)","12.4 V",out bVolt));
        f.Controls.Add(StatusLine("+12 ولت بعد از سوئیچ (IGN)","12.3 V",out ignVolt));
        f.Controls.Add(StatusLine("5 ولت سنسورها (5V)","5.0 V",out _));
        f.Controls.Add(StatusLine("زمین (GND)","مناسب",out _));
        f.Controls.Add(StatusLine("سیگنال دور موتور (RPM)","موجود",out _));
        f.Controls.Add(StatusLine("CAN / K-Line","فعال",out _));
        return p;
    }

    Control StatusLine(string name,string value,out Label valueLabel)
    {
        var r = new Panel { Width=310, Height=25, BackColor=Color.Transparent, Margin=new Padding(1) };
        r.Controls.Add(new Label { Text="●", ForeColor=Green, Location=new Point(1,1), Size=new Size(22,22), Font=new Font("Segoe UI",11,FontStyle.Bold), TextAlign=ContentAlignment.MiddleCenter });
        valueLabel = new Label { Text=value, ForeColor=value.Contains("V")?Cyan:Green, Location=new Point(24,1), Size=new Size(72,22), Font=new Font("Segoe UI",8.2f,FontStyle.Bold), TextAlign=ContentAlignment.MiddleLeft };
        r.Controls.Add(valueLabel);
        r.Controls.Add(new Label { Text=name, ForeColor=Text, Location=new Point(96,1), Size=new Size(205,22), Font=new Font("Segoe UI",8f), TextAlign=ContentAlignment.MiddleRight });
        return r;
    }

    Control BuildLogPanel()
    {
        var p = NewPanel();
        p.Margin = new Padding(5,3,3,1);

        var head = new Panel { Dock=DockStyle.Top, Height=34, BackColor=Color.Transparent };
        p.Controls.Add(head);
        head.Controls.Add(new Label { Text="▤  گزارش تست و لاگ عملکرد", Dock=DockStyle.Right, Width=280, ForeColor=Color.FromArgb(178,222,246), Font=new Font("Segoe UI",10.8f,FontStyle.Bold), TextAlign=ContentAlignment.MiddleRight });
        var save = SmallButton("▣ ذخیره گزارش",Color.FromArgb(90,125,160)); save.Dock=DockStyle.Left; save.Width=125; head.Controls.Add(save);
        var clear = SmallButton("▰ پاک کردن لاگ",Color.FromArgb(90,125,160)); clear.Dock=DockStyle.Left; clear.Width=125; head.Controls.Add(clear);

        logBox = new RichTextBox
        {
            Dock=DockStyle.Fill, BackColor=Color.FromArgb(3,16,26), ForeColor=Text,
            BorderStyle=BorderStyle.None, ReadOnly=true, RightToLeft=RightToLeft.Yes,
            Font=new Font("Segoe UI",8.3f), ScrollBars=RichTextBoxScrollBars.Vertical,
            Text=
"14:22:10    ●  ارتباط با ECU برقرار شد.\n"+
"14:22:15    ●  تست انژکتور شماره 1 با موفقیت انجام شد.\n"+
"14:22:17    ●  تست کوئل شماره 1 در حال اجرا...\n"+
"14:22:25    ●  فن رادیاتور دور کند فعال شد.\n"+
"14:22:31    ●  سنسور اکسیژن 682 mV — در محدوده نرمال."
        };
        clear.Click += (_,_) => logBox.Clear();
        p.Controls.Add(logBox);
        logBox.BringToFront();
        return p;
    }

    Control BuildHealthPanel()
    {
        var p = NewPanel();
        p.Margin = new Padding(3,3,1,1);
        p.Controls.Add(Title("●  وضعیت کلی سیستم",10.3f,Green));

        var t = new TableLayoutPanel
        {
            Dock=DockStyle.Fill, ColumnCount=2, RowCount=1, BackColor=Color.Transparent,
            Padding=new Padding(0,30,0,0), RightToLeft=RightToLeft.No
        };
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,58));
        t.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,42));
        p.Controls.Add(t);
        t.BringToFront();

        t.Controls.Add(new RefVehicleHealth { Dock=DockStyle.Fill, Margin=new Padding(2) },0,0);
        var f = new FlowLayoutPanel { Dock=DockStyle.Fill, FlowDirection=FlowDirection.TopDown, WrapContents=false, BackColor=Color.Transparent, RightToLeft=RightToLeft.Yes, Padding=new Padding(2) };
        foreach(var s in new[]{"تغذیه ECU","سنسورها","خروجی‌ها و عملگرها","ارتباط و دیاگ"})
            f.Controls.Add(new Label { Text=$"●  {s}", ForeColor=Green, Font=new Font("Segoe UI",8f,FontStyle.Bold), Width=140, Height=21, TextAlign=ContentAlignment.MiddleRight });
        f.Controls.Add(new Label { Text="✓  همه سیستم‌ها سالم است", BackColor=Color.FromArgb(0,90,55), ForeColor=Color.FromArgb(130,255,185), Font=new Font("Segoe UI",8.2f,FontStyle.Bold), Width=150, Height=29, TextAlign=ContentAlignment.MiddleCenter, Margin=new Padding(2,7,2,2) });
        t.Controls.Add(f,1,0);
        return p;
    }

    RefPanel NewPanel() => new()
    {
        Dock=DockStyle.Fill,
        BackColor=Panel,
        BorderColor=Border,
        Radius=12,
        Padding=new Padding(6)
    };

    Label Title(string text,float size,Color? color=null) => new()
    {
        Text=text,
        Dock=DockStyle.Top,
        Height=36,
        ForeColor=color??Color.FromArgb(178,222,246),
        Font=new Font("Segoe UI",size,FontStyle.Bold),
        TextAlign=ContentAlignment.MiddleRight
    };

    void AppendLog(string text,Color color)
    {
        if(logBox==null)return;
        logBox.SelectionStart=logBox.TextLength;
        logBox.SelectionColor=color;
        logBox.AppendText($"{DateTime.Now:HH:mm:ss}    ●  {text}\n");
        logBox.SelectionColor=Text;
        logBox.ScrollToCaret();
    }

    void TickDemo()
    {
        phase += .15;
        double rpm=850+Math.Sin(phase)*22;
        double water=88+Math.Sin(phase*.12)*.8;
        double intake=28+Math.Sin(phase*.19)*.6;
        double batt=12.4+Math.Sin(phase*.20)*.05;
        double o2=682+Math.Sin(phase*.55)*35;
        double tps=14+Math.Sin(phase*.31)*1.0;
        double map=33+Math.Sin(phase*.27)*1.4;

        rpmGauge.SetValue($"{rpm:0}");
        waterGauge.SetValue($"{water:0}");
        intakeGauge.SetValue($"{intake:0}");
        batteryGauge.SetValue($"{batt:0.0}");
        oxygenGauge.SetValue($"{o2:0}");
        throttleGauge.SetValue($"{tps:0}");
        mapGauge.SetValue($"{map:0}");
        bVolt.Text=$"{batt:0.0} V";
        ignVolt.Text=$"{batt-.1:0.0} V";

        for(int i=0;i<injectorChannels.Length;i++) injectorChannels[i].Active=((int)(phase*5+i*2)%8)<4;
        for(int i=0;i<coilChannels.Length;i++) coilChannels[i].Active=((int)(phase*3+i*2)%10)<3;
    }
}

public sealed class RefPanel : Panel
{
    public int Radius { get; set; } = 12;
    public Color BorderColor { get; set; } = Color.FromArgb(18,83,112);
    public RefPanel(){DoubleBuffered=true;ResizeRedraw=true;}
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        var r=new Rectangle(0,0,Width-1,Height-1);
        using var path=Round(r,Radius);
        using var pen=new Pen(BorderColor,1);
        e.Graphics.DrawPath(pen,path);
    }
    static GraphicsPath Round(Rectangle r,int rad)
    {
        var p=new GraphicsPath(); int d=Math.Min(rad*2,Math.Min(r.Width,r.Height));
        p.AddArc(r.X,r.Y,d,d,180,90); p.AddArc(r.Right-d,r.Y,d,d,270,90);
        p.AddArc(r.Right-d,r.Bottom-d,d,d,0,90); p.AddArc(r.X,r.Bottom-d,d,d,90,90); p.CloseFigure(); return p;
    }
}

public sealed class RefChannel : Control
{
    public string Caption { get; }
    public Color Accent { get; }
    bool active;
    public bool Active { get=>active; set{active=value;Invalidate();} }
    public RefChannel(string caption,Color accent){Caption=caption;Accent=accent;DoubleBuffered=true;BackColor=Color.Transparent;}
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        var r=new Rectangle(5,3,Width-10,30);
        using var path=Round(r,8);
        using var bg=new SolidBrush(active?Color.FromArgb(18,70,90):Color.FromArgb(17,43,58));
        using var pen=new Pen(active?Accent:Color.FromArgb(60,85,100),1.2f);
        e.Graphics.FillPath(bg,path); e.Graphics.DrawPath(pen,path);
        TextRenderer.DrawText(e.Graphics,Caption,new Font("Segoe UI",8.5f,FontStyle.Bold),r,active?Color.White:Color.FromArgb(170,190,205),TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);
        using var dot=new SolidBrush(active?Accent:Color.FromArgb(50,70,82));
        e.Graphics.FillEllipse(dot,Width/2-5,39,10,10);
    }
    static GraphicsPath Round(Rectangle r,int rad){var p=new GraphicsPath();int d=rad*2;p.AddArc(r.X,r.Y,d,d,180,90);p.AddArc(r.Right-d,r.Y,d,d,270,90);p.AddArc(r.Right-d,r.Bottom-d,d,d,0,90);p.AddArc(r.X,r.Bottom-d,d,d,90,90);p.CloseFigure();return p;}
}

public sealed class RefGauge : Control
{
    readonly string title,unit,kind;
    readonly Color accent;
    string value="0";
    public RefGauge(string title,string unit,Color accent,string kind){this.title=title;this.unit=unit;this.accent=accent;this.kind=kind;DoubleBuffered=true;BackColor=Color.FromArgb(7,28,43);}
    public void SetValue(string v){value=v;Invalidate();}
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        e.Graphics.Clear(BackColor);
        using var border=new Pen(Color.FromArgb(25,79,103),1); e.Graphics.DrawRectangle(border,0,0,Width-1,Height-1);
        TextRenderer.DrawText(e.Graphics,title,new Font("Segoe UI",7.7f,FontStyle.Bold),new Rectangle(3,5,Width-6,25),Color.FromArgb(180,203,220),TextFormatFlags.HorizontalCenter|TextFormatFlags.EndEllipsis);
        DrawIcon(e.Graphics,new Rectangle(Width/2-22,30,44,38));
        TextRenderer.DrawText(e.Graphics,value,new Font("Segoe UI",18,FontStyle.Bold),new Rectangle(2,70,Width-4,32),accent,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);
        TextRenderer.DrawText(e.Graphics,unit,new Font("Segoe UI",7.5f),new Rectangle(2,101,Width-4,19),Color.FromArgb(170,190,205),TextFormatFlags.HorizontalCenter);
    }
    void DrawIcon(Graphics g,Rectangle r)
    {
        using var p=new Pen(accent,2); using var b=new SolidBrush(accent);
        if(kind=="battery"){g.DrawRectangle(p,r.X+8,r.Y+8,r.Width-18,r.Height-16);g.FillRectangle(b,r.Right-10,r.Y+15,5,10);g.DrawLine(p,r.X+15,r.Y+18,r.X+22,r.Y+18);g.DrawLine(p,r.X+18,r.Y+15,r.X+18,r.Y+22);g.DrawLine(p,r.X+28,r.Y+18,r.X+34,r.Y+18);}
        else if(kind=="oxygen"){g.DrawEllipse(p,r.X+8,r.Y+4,28,28);TextRenderer.DrawText(g,"O₂",new Font("Segoe UI",8,FontStyle.Bold),new Rectangle(r.X+9,r.Y+8,27,20),accent,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);}
        else if(kind=="temp"){g.DrawEllipse(p,r.X+13,r.Bottom-13,14,14);g.DrawRectangle(p,r.X+18,r.Y+4,4,24);g.DrawLine(p,r.X+20,r.Y+9,r.X+20,r.Bottom-8);}
        else if(kind=="throttle"){g.DrawRectangle(p,r.X+14,r.Y+6,16,26);g.DrawLine(p,r.X+18,r.Y+9,r.X+27,r.Y+28);g.DrawLine(p,r.X+14,r.Y+32,r.X+30,r.Y+32);}
        else {g.DrawArc(p,r.X+4,r.Y+6,r.Width-8,r.Height-10,200,140);g.DrawLine(p,r.X+r.Width/2,r.Y+r.Height/2,r.Right-10,r.Y+10);}
    }
}

public sealed class RefVehicleHealth : Control
{
    public RefVehicleHealth(){DoubleBuffered=true;BackColor=Color.Transparent;}
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        using var glow=new SolidBrush(Color.FromArgb(45,0,235,122));
        e.Graphics.FillEllipse(glow,Width*.18f,Height*.40f,Width*.30f,Height*.34f);
        using var carPen=new Pen(Color.FromArgb(65,140,210),1.6f);
        var body=new RectangleF(Width*.08f,Height*.28f,Width*.82f,Height*.48f);
        e.Graphics.DrawArc(carPen,body,190,160);
        e.Graphics.DrawLine(carPen,Width*.18f,Height*.55f,Width*.82f,Height*.55f);
        e.Graphics.DrawLine(carPen,Width*.32f,Height*.30f,Width*.45f,Height*.16f);
        e.Graphics.DrawLine(carPen,Width*.45f,Height*.16f,Width*.67f,Height*.21f);
        e.Graphics.DrawEllipse(carPen,Width*.20f,Height*.63f,Width*.17f,Height*.20f);
        e.Graphics.DrawEllipse(carPen,Width*.68f,Height*.63f,Width*.17f,Height*.20f);
        using var eng=new Pen(Color.FromArgb(0,235,122),2.2f);
        e.Graphics.DrawRectangle(eng,Width*.21f,Height*.43f,Width*.22f,Height*.18f);
        e.Graphics.DrawLine(eng,Width*.26f,Height*.43f,Width*.26f,Height*.36f);
    }
}

public sealed class RefIcon : Control
{
    public string IconType { get; set; }="gear";
    public Color Accent { get; set; }=Color.Cyan;
    public RefIcon(){DoubleBuffered=true;BackColor=Color.Transparent;}
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        using var glow=new SolidBrush(Color.FromArgb(30,Accent));
        e.Graphics.FillEllipse(glow,1,1,Width-2,Height-2);
        using var p=new Pen(Accent,2.2f){StartCap=LineCap.Round,EndCap=LineCap.Round,LineJoin=LineJoin.Round};
        using var b=new SolidBrush(Accent);
        float x=Width*.15f,y=Height*.14f,w=Width*.70f,h=Height*.70f;
        switch(IconType)
        {
            case "injector":
                e.Graphics.DrawRectangle(p,x+w*.18f,y+h*.10f,w*.44f,h*.36f);
                e.Graphics.DrawLine(p,x+w*.28f,y+h*.46f,x+w*.28f,y+h*.68f);
                e.Graphics.DrawLine(p,x+w*.52f,y+h*.46f,x+w*.52f,y+h*.68f);
                e.Graphics.DrawLine(p,x+w*.28f,y+h*.68f,x+w*.40f,y+h*.86f);
                e.Graphics.DrawLine(p,x+w*.52f,y+h*.68f,x+w*.40f,y+h*.86f);
                e.Graphics.DrawLine(p,x+w*.62f,y+h*.20f,x+w*.82f,y+h*.20f);
                break;
            case "sparkplug":
                e.Graphics.DrawRectangle(p,x+w*.35f,y+h*.03f,w*.25f,h*.36f);
                e.Graphics.DrawLine(p,x+w*.28f,y+h*.43f,x+w*.68f,y+h*.43f);
                e.Graphics.DrawLine(p,x+w*.24f,y+h*.52f,x+w*.72f,y+h*.52f);
                e.Graphics.DrawLine(p,x+w*.38f,y+h*.52f,x+w*.38f,y+h*.80f);
                e.Graphics.DrawLine(p,x+w*.58f,y+h*.52f,x+w*.58f,y+h*.80f);
                e.Graphics.DrawLine(p,x+w*.69f,y+h*.70f,x+w*.84f,y+h*.61f);
                break;
            case "fuelpump":
                e.Graphics.DrawRectangle(p,x+w*.10f,y+h*.18f,w*.44f,h*.58f);
                e.Graphics.DrawLine(p,x+w*.20f,y+h*.30f,x+w*.44f,y+h*.30f);
                e.Graphics.DrawArc(p,x+w*.44f,y+h*.20f,w*.28f,h*.36f,270,180);
                e.Graphics.DrawLine(p,x+w*.70f,y+h*.39f,x+w*.70f,y+h*.75f);
                break;
            case "fan":
                e.Graphics.DrawEllipse(p,x+w*.10f,y+h*.08f,w*.72f,h*.72f);
                e.Graphics.FillEllipse(b,x+w*.43f,y+h*.40f,w*.08f,h*.08f);
                for(int i=0;i<4;i++){double a=i*Math.PI/2;float sx=x+w*.47f+(float)Math.Cos(a)*w*.08f;float sy=y+h*.44f+(float)Math.Sin(a)*h*.08f;float ex=x+w*.47f+(float)Math.Cos(a+.55)*w*.28f;float ey=y+h*.44f+(float)Math.Sin(a+.55)*h*.28f;e.Graphics.DrawLine(p,sx,sy,ex,ey);}
                break;
            case "throttle":
                e.Graphics.DrawRectangle(p,x+w*.14f,y+h*.15f,w*.55f,h*.58f);
                e.Graphics.DrawLine(p,x+w*.24f,y+h*.20f,x+w*.59f,y+h*.68f);
                e.Graphics.DrawLine(p,x+w*.69f,y+h*.24f,x+w*.84f,y+h*.24f);
                break;
            case "relay":
                e.Graphics.DrawRectangle(p,x+w*.12f,y+h*.18f,w*.60f,h*.56f);
                e.Graphics.DrawLine(p,x+w*.23f,y+h*.28f,x+w*.23f,y+h*.63f);
                e.Graphics.DrawLine(p,x+w*.35f,y+h*.28f,x+w*.35f,y+h*.63f);
                e.Graphics.DrawLine(p,x+w*.47f,y+h*.28f,x+w*.47f,y+h*.63f);
                break;
            case "warning":
                e.Graphics.DrawLine(p,x+w*.45f,y+h*.07f,x+w*.10f,y+h*.73f);
                e.Graphics.DrawLine(p,x+w*.45f,y+h*.07f,x+w*.82f,y+h*.73f);
                e.Graphics.DrawLine(p,x+w*.10f,y+h*.73f,x+w*.82f,y+h*.73f);
                e.Graphics.DrawLine(p,x+w*.45f,y+h*.30f,x+w*.45f,y+h*.52f);
                e.Graphics.FillEllipse(b,x+w*.42f,y+h*.60f,4,4);
                break;
            case "sensor":
                e.Graphics.DrawRectangle(p,x+w*.18f,y+h*.18f,w*.48f,h*.48f);
                e.Graphics.DrawLine(p,x+w*.18f,y+h*.33f,x+w*.05f,y+h*.33f);
                e.Graphics.DrawLine(p,x+w*.18f,y+h*.50f,x+w*.05f,y+h*.50f);
                e.Graphics.DrawLine(p,x+w*.66f,y+h*.33f,x+w*.82f,y+h*.33f);
                e.Graphics.DrawLine(p,x+w*.66f,y+h*.50f,x+w*.82f,y+h*.50f);
                break;
            case "actuator":
                e.Graphics.DrawRectangle(p,x+w*.14f,y+h*.20f,w*.54f,h*.48f);
                e.Graphics.DrawLine(p,x+w*.27f,y+h*.20f,x+w*.27f,y+h*.06f);
                e.Graphics.DrawLine(p,x+w*.55f,y+h*.20f,x+w*.55f,y+h*.06f);
                e.Graphics.DrawLine(p,x+w*.68f,y+h*.36f,x+w*.84f,y+h*.36f);
                break;
            case "mil":
                e.Graphics.DrawRectangle(p,x+w*.13f,y+h*.27f,w*.61f,h*.40f);
                e.Graphics.DrawLine(p,x+w*.24f,y+h*.27f,x+w*.30f,y+h*.13f);
                e.Graphics.DrawLine(p,x+w*.55f,y+h*.27f,x+w*.55f,y+h*.14f);
                break;
            case "immo":
                e.Graphics.DrawEllipse(p,x+w*.08f,y+h*.26f,w*.28f,h*.28f);
                e.Graphics.DrawLine(p,x+w*.35f,y+h*.40f,x+w*.80f,y+h*.40f);
                e.Graphics.DrawLine(p,x+w*.66f,y+h*.40f,x+w*.66f,y+h*.58f);
                break;
            case "oil":
                e.Graphics.DrawRectangle(p,x+w*.18f,y+h*.42f,w*.42f,h*.20f);
                e.Graphics.DrawLine(p,x+w*.60f,y+h*.46f,x+w*.78f,y+h*.34f);
                e.Graphics.FillEllipse(b,x+w*.76f,y+h*.60f,5,5);
                break;
            case "battery":
                e.Graphics.DrawRectangle(p,x+w*.12f,y+h*.28f,w*.62f,h*.42f);
                e.Graphics.DrawLine(p,x+w*.25f,y+h*.28f,x+w*.25f,y+h*.18f);
                e.Graphics.DrawLine(p,x+w*.58f,y+h*.28f,x+w*.58f,y+h*.18f);
                e.Graphics.DrawLine(p,x+w*.22f,y+h*.47f,x+w*.34f,y+h*.47f);
                e.Graphics.DrawLine(p,x+w*.49f,y+h*.47f,x+w*.63f,y+h*.47f);
                e.Graphics.DrawLine(p,x+w*.56f,y+h*.40f,x+w*.56f,y+h*.54f);
                break;
            default:
                e.Graphics.DrawEllipse(p,x+w*.12f,y+h*.12f,w*.62f,h*.62f);
                e.Graphics.DrawLine(p,x+w*.43f,y+h*.05f,x+w*.43f,y+h*.22f);
                e.Graphics.DrawLine(p,x+w*.43f,y+h*.65f,x+w*.43f,y+h*.82f);
                e.Graphics.DrawLine(p,x+w*.05f,y+h*.43f,x+w*.22f,y+h*.43f);
                e.Graphics.DrawLine(p,x+w*.65f,y+h*.43f,x+w*.82f,y+h*.43f);
                break;
        }
    }
}


public sealed class BrandControl : Control
{
    public string Title { get; set; } = "نرم افزار تست ECU";
    public string Subtitle { get; set; } = "عیب‌یابی و تست کامل واحد کنترل موتور";

    public BrandControl()
    {
        DoubleBuffered = true;
        BackColor = Color.Transparent;
        RightToLeft = RightToLeft.Yes;
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;

        Color cyan = Color.FromArgb(0, 212, 255);
        Color text = Color.FromArgb(229, 240, 248);
        Color muted = Color.FromArgb(145, 174, 195);

        var chip = new Rectangle(12, 15, 58, 52);
        using var glow = new SolidBrush(Color.FromArgb(28, cyan));
        using var pen = new Pen(cyan, 2.2f);
        e.Graphics.FillEllipse(glow, 4, 7, 74, 68);
        e.Graphics.DrawRectangle(pen, chip);

        for (int i = 0; i < 4; i++)
        {
            int yy = chip.Y + 8 + i * 11;
            e.Graphics.DrawLine(pen, chip.X - 8, yy, chip.X, yy);
            e.Graphics.DrawLine(pen, chip.Right, yy, chip.Right + 8, yy);
        }
        for (int i = 0; i < 3; i++)
        {
            int xx = chip.X + 12 + i * 14;
            e.Graphics.DrawLine(pen, xx, chip.Y - 7, xx, chip.Y);
            e.Graphics.DrawLine(pen, xx, chip.Bottom, xx, chip.Bottom + 7);
        }

        // Simple car silhouette inside the ECU-chip icon.
        e.Graphics.DrawArc(pen, chip.X + 11, chip.Y + 17, 34, 19, 200, 140);
        e.Graphics.DrawLine(pen, chip.X + 12, chip.Y + 31, chip.X + 45, chip.Y + 31);
        e.Graphics.DrawEllipse(pen, chip.X + 16, chip.Y + 28, 7, 7);
        e.Graphics.DrawEllipse(pen, chip.X + 35, chip.Y + 28, 7, 7);

        TextRenderer.DrawText(
            e.Graphics,
            Title,
            new Font("Segoe UI", 15.5f, FontStyle.Bold),
            new Rectangle(86, 14, Width - 94, 34),
            text,
            TextFormatFlags.Right | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);

        TextRenderer.DrawText(
            e.Graphics,
            Subtitle,
            new Font("Segoe UI", 8.6f, FontStyle.Regular),
            new Rectangle(86, 46, Width - 94, 25),
            muted,
            TextFormatFlags.Right | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }
}
