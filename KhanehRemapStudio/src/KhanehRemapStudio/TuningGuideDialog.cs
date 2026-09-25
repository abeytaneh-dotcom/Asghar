namespace KhanehRemapStudio;

public sealed class TuningGuideDialog : Form
{
    private readonly IReadOnlyList<MapDefinition> _maps;
    private readonly TreeView _tree=new();
    private readonly Label _title=new(),_purpose=new(),_direction=new(),_range=new(),_monitor=new(),_stop=new(),_warning=new(),_modeState=new();
    private readonly NumericUpDown _value=new();
    private readonly ComboBox _mode=new(),_scope=new();
    private readonly ModernButton _apply=new(),_auto=new();
    private MapDefinition? _selectedMap;
    private TuningGuideRule _rule=TuningGuide.For(null);

    public MapDefinition? SelectedMap => _selectedMap;
    public string ApplyMode => _mode.SelectedIndex==1?"value":"percent";
    public double ApplyValue => (double)_value.Value;
    public bool WholeMap => _scope.SelectedIndex==1;
    public bool AutoRequested { get; private set; }
    public bool ApplyRequested { get; private set; }

    public TuningGuideDialog(IReadOnlyList<MapDefinition> maps,MapDefinition? selected)
    {
        _maps=maps;
        Text="راهنمای هوشمند ریمپ • Khaneh Remap";
        Width=1120;Height=720;MinimumSize=new Size(900,600);StartPosition=FormStartPosition.CenterParent;
        BackColor=Color.FromArgb(8,14,22);ForeColor=Color.White;Font=new Font("Segoe UI",10f);
        RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;

        var head=new Panel{Dock=DockStyle.Top,Height=78,BackColor=Color.FromArgb(12,22,34),Padding=new Padding(16,10,16,8)};
        var h1=new Label{Dock=DockStyle.Top,Height=34,Text="؟  راهنمای جداول و ویرایش کنترل‌شده",ForeColor=Color.FromArgb(47,226,185),Font=new Font("Segoe UI",16f,FontStyle.Bold),TextAlign=ContentAlignment.MiddleRight};
        var sub=new Label{Dock=DockStyle.Top,Height=25,Text="جدول را انتخاب کنید؛ وظیفه، جهت تغییر، دامنه شروع تست و پارامترهای لازم برای کنترل نمایش داده می‌شود.",ForeColor=Color.FromArgb(151,172,194),TextAlign=ContentAlignment.MiddleRight};
        head.Controls.Add(sub);head.Controls.Add(h1);Controls.Add(head);

        var split=new SplitContainer{Dock=DockStyle.Fill,SplitterWidth=6,BackColor=Color.FromArgb(5,10,16),RightToLeft=RightToLeft.No};
        split.Size=new Size(1000,600);split.Panel1MinSize=280;split.Panel2MinSize=520;split.SplitterDistance=330;

        var left=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(15,25,37),Padding=new Padding(10)};
        var search=new TextBox{Dock=DockStyle.Top,Height=34,BackColor=Color.FromArgb(24,38,54),ForeColor=Color.White,BorderStyle=BorderStyle.FixedSingle,PlaceholderText="جستجوی جدول / دسته..."};
        _tree.Dock=DockStyle.Fill;_tree.BackColor=Color.FromArgb(10,18,28);_tree.ForeColor=Color.FromArgb(232,240,248);_tree.BorderStyle=BorderStyle.None;_tree.ItemHeight=29;_tree.Font=new Font("Segoe UI",9.3f);_tree.FullRowSelect=true;_tree.HideSelection=false;
        var il=new ImageList{ImageSize=new Size(20,20),ColorDepth=ColorDepth.Depth32Bit};
        il.Images.Add("folder",IconFactory.Create("folder",20));il.Images.Add("map",IconFactory.Create("map",20));
        _tree.ImageList=il;
        left.Controls.Add(_tree);left.Controls.Add(search);split.Panel1.Controls.Add(left);

        var rightScroll=new Panel{Dock=DockStyle.Fill,AutoScroll=true,BackColor=Color.FromArgb(10,18,28),Padding=new Padding(16)};
        var content=new TableLayoutPanel{Dock=DockStyle.Top,AutoSize=true,ColumnCount=1,RowCount=10,BackColor=Color.Transparent};
        content.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,100));
        StyleBig(_title,Color.White,14f,true);content.Controls.Add(_title);
        AddSection(content,"این جدول برای چیست؟",_purpose,Color.FromArgb(86,166,255));
        AddSection(content,"جهت تغییر",_direction,Color.FromArgb(47,226,185));
        AddSection(content,"دامنه پیشنهادی شروع تست",_range,Color.FromArgb(255,191,87));
        AddSection(content,"چه چیزهایی را دیتالاگ کنم؟",_monitor,Color.FromArgb(86,166,255));
        AddSection(content,"چه زمانی تغییر را متوقف/برگردانم؟",_stop,Color.FromArgb(255,119,119));
        AddSection(content,"هشدار",_warning,Color.FromArgb(255,119,119));

        var editCard=new AccentPanel{Dock=DockStyle.Top,Height=164,BackColor=Color.FromArgb(16,28,41),EdgeColor=Color.FromArgb(47,226,185),Padding=new Padding(12),Margin=new Padding(0,12,0,0)};
        var editGrid=new TableLayoutPanel{Dock=DockStyle.Fill,ColumnCount=4,RowCount=3};
        editGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,21));editGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,29));editGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,21));editGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,29));
        editGrid.RowStyles.Add(new RowStyle(SizeType.Absolute,38));editGrid.RowStyles.Add(new RowStyle(SizeType.Absolute,44));editGrid.RowStyles.Add(new RowStyle(SizeType.Percent,100));

        editGrid.Controls.Add(MiniLabel("روش تغییر"),0,0);
        _mode.Dock=DockStyle.Fill;_mode.DropDownStyle=ComboBoxStyle.DropDownList;_mode.Items.AddRange(new object[]{"درصدی (%)","مقداری"});_mode.SelectedIndex=0;editGrid.Controls.Add(_mode,1,0);
        editGrid.Controls.Add(MiniLabel("ناحیه اعمال"),2,0);
        _scope.Dock=DockStyle.Fill;_scope.DropDownStyle=ComboBoxStyle.DropDownList;_scope.Items.AddRange(new object[]{"سلول‌های انتخابی","کل جدول"});_scope.SelectedIndex=0;editGrid.Controls.Add(_scope,3,0);
        editGrid.Controls.Add(MiniLabel("مقدار (+ / -)"),0,1);
        _value.Dock=DockStyle.Fill;_value.DecimalPlaces=3;_value.Minimum=-100000;_value.Maximum=100000;_value.Increment=.25M;editGrid.Controls.Add(_value,1,1);
        _modeState.Dock=DockStyle.Fill;_modeState.ForeColor=Color.FromArgb(158,177,197);_modeState.TextAlign=ContentAlignment.MiddleRight;editGrid.SetColumnSpan(_modeState,2);editGrid.Controls.Add(_modeState,2,1);

        _apply.Text="اعمال کنترل‌شده";_apply.AccentMode=true;_apply.Image=IconFactory.Create("edit",20);_apply.Dock=DockStyle.Fill;_apply.Margin=new Padding(5);_apply.Click+=(_,_)=>{ApplyRequested=true;AutoRequested=false;DialogResult=DialogResult.OK;Close();};
        _auto.Text="AUTO پیشنهادی";_auto.AccentMode=false;_auto.Image=IconFactory.Create("graph",20);_auto.Dock=DockStyle.Fill;_auto.Margin=new Padding(5);_auto.Click+=(_,_)=>{ApplyRequested=true;AutoRequested=true;DialogResult=DialogResult.OK;Close();};
        editGrid.Controls.Add(_apply,0,2);editGrid.SetColumnSpan(_apply,2);editGrid.Controls.Add(_auto,2,2);editGrid.SetColumnSpan(_auto,2);
        editCard.Controls.Add(editGrid);content.Controls.Add(editCard);

        var note=new Label{Dock=DockStyle.Top,AutoSize=true,MaximumSize=new Size(720,0),Padding=new Padding(4,10,4,4),
            Text="AUTO فقط برای Ruleهایی فعال می‌شود که تعریف دقیق، Scale/Unit معتبر و قانون عددی تأییدشده داشته باشند. جداول حفاظتی و ناشناخته خودکار تغییر داده نمی‌شوند.",
            ForeColor=Color.FromArgb(147,167,187),Font=new Font("Segoe UI",8.8f),TextAlign=ContentAlignment.TopRight};
        content.Controls.Add(note);

        rightScroll.Controls.Add(content);split.Panel2.Controls.Add(rightScroll);Controls.Add(split);

        BuildTree("");
        search.TextChanged+=(_,_)=>BuildTree(search.Text);
        _tree.AfterSelect+=(_,e)=>{if(e.Node?.Tag is MapDefinition m)SelectRule(m);};

        var initial=selected??maps.FirstOrDefault();
        if(initial!=null)SelectTreeNode(initial.Id);
    }

    private void BuildTree(string q)
    {
        q=(q??"").Trim().ToLowerInvariant();
        _tree.BeginUpdate();_tree.Nodes.Clear();
        foreach(var g in _maps.Where(m=>q.Length==0 || (m.NameFa+" "+m.NameEn+" "+m.Category).ToLowerInvariant().Contains(q)).GroupBy(m=>m.Category).OrderBy(g=>g.Key))
        {
            var cat=new TreeNode($"{g.Key}  ({g.Count()})"){ImageKey="folder",SelectedImageKey="folder",ForeColor=Color.FromArgb(117,184,244)};
            foreach(var m in g.OrderBy(x=>x.NameFa))
                cat.Nodes.Add(new TreeNode($"{m.NameFa}   0x{m.Address:X}"){Tag=m,Name=m.Id,ImageKey="map",SelectedImageKey="map"});
            _tree.Nodes.Add(cat);
        }
        if(q.Length>0)_tree.ExpandAll();_tree.EndUpdate();
    }

    private void SelectTreeNode(string id)
    {
        foreach(TreeNode cat in _tree.Nodes)
            foreach(TreeNode n in cat.Nodes)
                if(n.Name==id){cat.Expand();_tree.SelectedNode=n;n.EnsureVisible();return;}
    }

    private void SelectRule(MapDefinition map)
    {
        _selectedMap=map;_rule=TuningGuide.For(map);
        _title.Text=$"{_rule.Title}    •    {map.NameFa}    •    0x{map.Address:X}";
        _purpose.Text=_rule.Purpose;_direction.Text=_rule.Direction;_range.Text=_rule.SuggestedRange;
        _monitor.Text=_rule.Monitor;_stop.Text=_rule.StopCondition;_warning.Text=_rule.Warning;

        _mode.SelectedIndex=_rule.EditMode=="value"?1:0;
        _value.Value=(decimal)Math.Clamp(_rule.DefaultValue,(double)_value.Minimum,(double)_value.Maximum);
        bool auto=TuningGuide.CanAuto(map,_rule);
        _auto.Enabled=auto;
        _auto.OpacityForEnabled(auto);
        _apply.Enabled=!map.ReadOnly && _rule.EditMode!="none";
        _apply.OpacityForEnabled(_apply.Enabled);
        _modeState.Text=map.ReadOnly
            ?"این جدول Read-only است؛ تعریف دقیق لازم است."
            :auto
                ?"AUTO برای این جدول تأیید شده است."
                :"ویرایش دستی کنترل‌شده فعال؛ AUTO برای این جدول قفل است.";
    }

    private static void StyleBig(Label l,Color c,float size,bool bold=false)
    {
        l.Dock=DockStyle.Top;l.AutoSize=true;l.Padding=new Padding(4,8,4,8);l.ForeColor=c;l.Font=new Font("Segoe UI",size,bold?FontStyle.Bold:FontStyle.Regular);l.TextAlign=ContentAlignment.MiddleRight;
    }

    private static void AddSection(TableLayoutPanel p,string caption,Label value,Color c)
    {
        var box=new Panel{Dock=DockStyle.Top,Height=86,BackColor=Color.FromArgb(14,25,38),Padding=new Padding(10),Margin=new Padding(0,4,0,4)};
        var cap=new Label{Dock=DockStyle.Top,Height=25,Text=caption,ForeColor=c,Font=new Font("Segoe UI",9f,FontStyle.Bold),TextAlign=ContentAlignment.MiddleRight};
        value.Dock=DockStyle.Fill;value.ForeColor=Color.FromArgb(220,231,241);value.Font=new Font("Segoe UI",9.4f);value.TextAlign=ContentAlignment.TopRight;
        box.Controls.Add(value);box.Controls.Add(cap);p.Controls.Add(box);
    }

    private static Label MiniLabel(string text)=>new(){Text=text,Dock=DockStyle.Fill,ForeColor=Color.FromArgb(153,174,195),TextAlign=ContentAlignment.MiddleRight,Font=new Font("Segoe UI",8.7f,FontStyle.Bold)};
}

internal static class GuideUiExtensions
{
    public static void OpacityForEnabled(this Control c,bool enabled)
    {
        c.ForeColor=enabled?Color.White:Color.FromArgb(105,119,134);
    }
}