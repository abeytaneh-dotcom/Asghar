using System.Globalization;
using System.Text;

namespace KhanehRemapStudio;

public sealed class MainForm : Form
{
    private readonly BinaryDocument _doc=new();
    private readonly DumpCatalogService _catalog=new();
    private readonly ChecksumManager _checksum=new();
    private readonly ProfileStore _profileStore=new();
    private readonly IReadOnlyList<EcuProfile> _ecuProfiles=EcuProfileCatalog.Load();
    private IdentificationResult _id=new();
    private readonly List<MapDefinition> _maps=new();
    private readonly Stack<List<ByteChange>> _undo=new();
    private readonly Stack<List<ByteChange>> _redo=new();
    private MapDefinition? _selected;
    private bool _filling;

    private readonly Color Bg=Color.FromArgb(10,16,25), Panel=Color.FromArgb(16,25,37), Panel2=Color.FromArgb(24,37,53),
        Fg=Color.FromArgb(238,244,250), Muted=Color.FromArgb(142,160,181), Accent=Color.FromArgb(39,215,174),
        Blue=Color.FromArgb(78,156,244), Warn=Color.FromArgb(255,190,92), Bad=Color.FromArgb(255,105,105);

    private readonly Label _file=new(),_identify=new(),_hash=new(),_checksumLabel=new(),_changes=new();
    private readonly TextBox _search=new(),_info=new(),_hex=new(),_librarySearch=new();
    private readonly ComboBox _category=new();
    private readonly ListBox _mapList=new();
    private readonly TreeView _ecuTree=new(),_fileTree=new();
    private readonly Label _ecuDetails=new(),_libraryStats=new(),_mapStats=new();
    private readonly TabControl _libraryTabs=new();
    private readonly DataGridView _grid=new();
    private readonly LineGraphPanel _g2=new();
    private readonly SurfaceGraphPanel _g3=new();
    private readonly TabControl _tabs=new();
    private readonly ToolStripStatusLabel _status=new(),_bank=new();

    public MainForm()
    {
        Text="Khaneh Remap Studio Pro • خانه ریمپ";
        Width=1560;Height=930;MinimumSize=new Size(1160,740);StartPosition=FormStartPosition.CenterScreen;
        BackColor=Bg;ForeColor=Fg;Font=new Font("Segoe UI",10f);RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;AllowDrop=true;
        BuildUi();HookEvents();BuildEcuLibraryTree();BuildFileTree();SetStatus("آماده — فایل ECU را باز کنید.");
        _bank.Text=$"ECU: {_ecuProfiles.Count:N0} • دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";
        UpdateLibraryStats();
    }

    private void BuildUi()
    {
        SuspendLayout();

        var topBar=new Panel
        {
            Dock=DockStyle.Top,
            Height=74,
            BackColor=Color.FromArgb(7,13,21),
            Padding=new Padding(16,8,16,8)
        };

        var brandBlock=new Panel{Dock=DockStyle.Left,Width=300,BackColor=Color.Transparent};
        var brand=new Label
        {
            Dock=DockStyle.Top,Height=36,Text="KHANEH REMAP",
            Font=new Font("Segoe UI",20f,FontStyle.Bold),
            ForeColor=Accent,TextAlign=ContentAlignment.MiddleLeft,RightToLeft=RightToLeft.No
        };
        var brandSub=new Label
        {
            Dock=DockStyle.Top,Height=24,Text="ECU CALIBRATION STUDIO • PRO",
            Font=new Font("Segoe UI",8.3f,FontStyle.Bold),
            ForeColor=Blue,TextAlign=ContentAlignment.MiddleLeft,RightToLeft=RightToLeft.No
        };
        brandBlock.Controls.Add(brandSub);brandBlock.Controls.Add(brand);

        var quick=new FlowLayoutPanel
        {
            Dock=DockStyle.Fill,FlowDirection=FlowDirection.RightToLeft,
            WrapContents=false,Padding=new Padding(4,7,4,0),BackColor=Color.Transparent
        };
        quick.Controls.Add(ActionButton("باز کردن دامپ",OpenDump,true,136));
        quick.Controls.Add(ActionButton("ذخیره MOD",SaveMod,true,126));
        quick.Controls.Add(ActionButton("Undo",Undo,false,78));
        quick.Controls.Add(ActionButton("Redo",Redo,false,78));
        quick.Controls.Add(ActionButton("ورود XDF/A2L",ImportDefinition,false,128));
        quick.Controls.Add(ActionButton("تعریف‌های آزاد",ShowPublicDefinitions,false,122));
        quick.Controls.Add(ActionButton("؟ راهنمای ریمپ",OpenTuningGuide,true,132));

        topBar.Controls.Add(quick);topBar.Controls.Add(brandBlock);
        Controls.Add(topBar);

        var stageBar=new Panel
        {
            Dock=DockStyle.Top,Height=60,BackColor=Color.FromArgb(11,19,30),
            Padding=new Padding(10,7,10,7)
        };
        var stages=new TableLayoutPanel
        {
            Dock=DockStyle.Fill,ColumnCount=7,RowCount=1,BackColor=Color.Transparent
        };
        for(int i=0;i<7;i++)stages.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,100f/7f));
        stages.Controls.Add(StageButton("01  بانک خودرو",()=>{_libraryTabs.SelectedIndex=0;_librarySearch.Focus();}),0,0);
        stages.Controls.Add(StageButton("02  شناسایی دامپ",OpenDump),1,0);
        stages.Controls.Add(StageButton("03  نقشه‌ها",()=>{_search.Focus();}),2,0);
        stages.Controls.Add(StageButton("04  ویرایش",()=>{if(_tabs.TabPages.Count>0)_tabs.SelectedIndex=0;}),3,0);
        stages.Controls.Add(StageButton("05  مقایسه",Compare),4,0);
        stages.Controls.Add(StageButton("06  چکسام",VerifyChecksum),5,0);
        stages.Controls.Add(StageButton("07  خروجی",SaveMod),6,0);
        stageBar.Controls.Add(stages);
        Controls.Add(stageBar);

        var header=new Panel
        {
            Dock=DockStyle.Top,Height=92,BackColor=Color.FromArgb(13,22,34),
            Padding=new Padding(10,9,10,9)
        };
        var cards=new TableLayoutPanel
        {
            Dock=DockStyle.Fill,ColumnCount=5,RowCount=1,BackColor=Color.Transparent
        };
        for(int i=0;i<5;i++)cards.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,20));

        cards.Controls.Add(InfoCard("فایل فعال",_file,Fg),0,0);
        cards.Controls.Add(InfoCard("شناسایی ECU",_identify,Blue),1,0);
        cards.Controls.Add(InfoCard("Checksum",_checksumLabel,Accent),2,0);
        cards.Controls.Add(InfoCard("تغییرات",_changes,Warn),3,0);
        cards.Controls.Add(InfoCard("SHA-256",_hash,Muted),4,0);
        header.Controls.Add(cards);
        Controls.Add(header);

        var workspace=new Panel{Dock=DockStyle.Fill,BackColor=Bg,Padding=new Padding(8)};

        var outer=new SplitContainer
        {
            Dock=DockStyle.Fill,SplitterWidth=6,BackColor=Color.FromArgb(5,10,16),
            RightToLeft=RightToLeft.No
        };
        outer.Size=new Size(1400,700);
        outer.Panel1MinSize=300;outer.Panel2MinSize=760;outer.SplitterDistance=330;

        var libraryShell=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(30,47,64),Padding=new Padding(1)};
        var library=new Panel{Dock=DockStyle.Fill,BackColor=Panel,Padding=new Padding(10)};

        var libTitle=new TableLayoutPanel{Dock=DockStyle.Top,Height=58,ColumnCount=2,BackColor=Panel};
        libTitle.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,65));libTitle.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,35));
        var libName=new Label{Text="بانک تخصصی ECU و فایل",Dock=DockStyle.Fill,ForeColor=Fg,Font=new Font("Segoe UI",12f,FontStyle.Bold),TextAlign=ContentAlignment.MiddleRight};
        _libraryStats.Dock=DockStyle.Fill;_libraryStats.ForeColor=Muted;_libraryStats.Font=new Font("Segoe UI",8.8f);_libraryStats.TextAlign=ContentAlignment.MiddleLeft;_libraryStats.RightToLeft=RightToLeft.No;
        libTitle.Controls.Add(libName,0,0);libTitle.Controls.Add(_libraryStats,1,0);

        _librarySearch.Dock=DockStyle.Top;_librarySearch.Height=34;_librarySearch.BackColor=Panel2;_librarySearch.ForeColor=Fg;_librarySearch.BorderStyle=BorderStyle.FixedSingle;
        _librarySearch.PlaceholderText="جستجو: خودرو، ECU، سازنده، پروتکل...";

        _libraryTabs.Dock=DockStyle.Fill;_libraryTabs.RightToLeft=RightToLeft.Yes;_libraryTabs.RightToLeftLayout=true;
        _libraryTabs.DrawMode=TabDrawMode.OwnerDrawFixed;_libraryTabs.SizeMode=TabSizeMode.Fixed;_libraryTabs.ItemSize=new Size(138,34);
        _libraryTabs.DrawItem+=DrawLibraryTab;

        var ecuPage=new TabPage("بانک ECU"){BackColor=Color.FromArgb(12,21,32),Padding=new Padding(5)};
        SetupTree(_ecuTree);ecuPage.Controls.Add(_ecuTree);
        var filePage=new TabPage("بانک فایل"){BackColor=Color.FromArgb(12,21,32),Padding=new Padding(5)};
        SetupTree(_fileTree);filePage.Controls.Add(_fileTree);
        _libraryTabs.TabPages.AddRange(new[]{ecuPage,filePage});

        _ecuDetails.Dock=DockStyle.Bottom;_ecuDetails.Height=165;_ecuDetails.BackColor=Color.FromArgb(11,19,29);_ecuDetails.ForeColor=Color.FromArgb(191,207,223);
        _ecuDetails.Padding=new Padding(10);_ecuDetails.Font=new Font("Segoe UI",9f);_ecuDetails.TextAlign=ContentAlignment.TopRight;
        _ecuDetails.Text="یک ECU یا فایل را از بانک انتخاب کنید.";

        var libActions=new FlowLayoutPanel{Dock=DockStyle.Bottom,Height=46,FlowDirection=FlowDirection.RightToLeft,WrapContents=false,Padding=new Padding(0,6,0,4)};
        libActions.Controls.Add(SmallButton("ایندکس پوشه",IndexDumpFolder,112));
        libActions.Controls.Add(SmallButton("RAR / ZIP / 7z",IndexDumpArchive,120));

        library.Controls.Add(_libraryTabs);library.Controls.Add(_ecuDetails);library.Controls.Add(libActions);library.Controls.Add(_librarySearch);library.Controls.Add(libTitle);
        libraryShell.Controls.Add(library);outer.Panel1.Controls.Add(libraryShell);

        var workSplit=new SplitContainer
        {
            Dock=DockStyle.Fill,SplitterWidth=6,BackColor=Color.FromArgb(5,10,16),
            RightToLeft=RightToLeft.No
        };
        workSplit.Size=new Size(1000,700);workSplit.Panel1MinSize=270;workSplit.Panel2MinSize=520;workSplit.SplitterDistance=300;

        var mapShell=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(30,47,64),Padding=new Padding(1)};
        var mapPanel=new Panel{Dock=DockStyle.Fill,BackColor=Panel,Padding=new Padding(10)};
        var mapTitle=new TableLayoutPanel{Dock=DockStyle.Top,Height=54,ColumnCount=2};
        mapTitle.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,68));mapTitle.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,32));
        var mapName=new Label{Text="جداول کالیبراسیون",Dock=DockStyle.Fill,ForeColor=Fg,Font=new Font("Segoe UI",11.5f,FontStyle.Bold),TextAlign=ContentAlignment.MiddleRight};
        _mapStats.Dock=DockStyle.Fill;_mapStats.ForeColor=Muted;_mapStats.TextAlign=ContentAlignment.MiddleLeft;_mapStats.RightToLeft=RightToLeft.No;
        mapTitle.Controls.Add(mapName,0,0);mapTitle.Controls.Add(_mapStats,1,0);

        var filters=new TableLayoutPanel{Dock=DockStyle.Top,Height=78,ColumnCount=1,RowCount=2,Padding=new Padding(0,0,0,6)};
        _search.Dock=DockStyle.Fill;_search.BackColor=Panel2;_search.ForeColor=Fg;_search.BorderStyle=BorderStyle.FixedSingle;_search.PlaceholderText="جستجوی مپ / آدرس / دسته";
        _search.Margin=new Padding(0,2,0,4);
        _category.Dock=DockStyle.Fill;_category.DropDownStyle=ComboBoxStyle.DropDownList;_category.BackColor=Panel2;_category.ForeColor=Fg;_category.FlatStyle=FlatStyle.Flat;_category.Margin=new Padding(0,2,0,0);
        filters.Controls.Add(_search,0,0);filters.Controls.Add(_category,0,1);

        _mapList.Dock=DockStyle.Fill;_mapList.BackColor=Color.FromArgb(11,19,29);_mapList.ForeColor=Fg;_mapList.BorderStyle=BorderStyle.None;
        _mapList.DrawMode=DrawMode.OwnerDrawFixed;_mapList.ItemHeight=38;_mapList.IntegralHeight=false;_mapList.Font=new Font("Segoe UI",9.5f);
        _mapList.DrawItem+=DrawMapItem;

        var mapActions=new FlowLayoutPanel{Dock=DockStyle.Bottom,Height=48,FlowDirection=FlowDirection.RightToLeft,WrapContents=false,Padding=new Padding(0,6,0,4)};
        mapActions.Controls.Add(SmallButton("ویرایش گروهی",BatchEdit,112));
        mapActions.Controls.Add(SmallButton("مقایسه",Compare,88));

        mapPanel.Controls.Add(_mapList);mapPanel.Controls.Add(mapActions);mapPanel.Controls.Add(filters);mapPanel.Controls.Add(mapTitle);
        mapShell.Controls.Add(mapPanel);workSplit.Panel1.Controls.Add(mapShell);

        var editorShell=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(30,47,64),Padding=new Padding(1)};
        var editor=new Panel{Dock=DockStyle.Fill,BackColor=Panel,Padding=new Padding(8)};

        _tabs.Dock=DockStyle.Fill;_tabs.RightToLeft=RightToLeft.Yes;_tabs.RightToLeftLayout=true;_tabs.DrawMode=TabDrawMode.OwnerDrawFixed;
        _tabs.SizeMode=TabSizeMode.Fixed;_tabs.ItemSize=new Size(125,36);_tabs.DrawItem+=DrawTab;
        var tableTab=new TabPage("جدول"){BackColor=Panel,Padding=new Padding(6)};SetupGrid();tableTab.Controls.Add(_grid);
        var g2tab=new TabPage("2D"){BackColor=Panel,Padding=new Padding(6)};_g2.Dock=DockStyle.Fill;_g2.BackColor=Color.FromArgb(8,15,24);g2tab.Controls.Add(_g2);
        var g3tab=new TabPage("3D"){BackColor=Panel,Padding=new Padding(6)};_g3.Dock=DockStyle.Fill;_g3.BackColor=Color.FromArgb(8,15,24);g3tab.Controls.Add(_g3);
        var hexTab=new TabPage("HEX"){BackColor=Panel,Padding=new Padding(6)};_hex.Dock=DockStyle.Fill;_hex.Multiline=true;_hex.ReadOnly=true;_hex.WordWrap=false;_hex.ScrollBars=ScrollBars.Both;_hex.BackColor=Color.FromArgb(6,12,20);_hex.ForeColor=Color.FromArgb(205,221,237);_hex.Font=new Font("Consolas",10f);_hex.RightToLeft=RightToLeft.No;_hex.BorderStyle=BorderStyle.None;hexTab.Controls.Add(_hex);
        var infoTab=new TabPage("تعریف"){BackColor=Panel,Padding=new Padding(10)};_info.Dock=DockStyle.Fill;_info.Multiline=true;_info.ReadOnly=true;_info.ScrollBars=ScrollBars.Vertical;_info.BackColor=Color.FromArgb(12,21,32);_info.ForeColor=Fg;_info.BorderStyle=BorderStyle.None;_info.Font=new Font("Segoe UI",10f);infoTab.Controls.Add(_info);
        _tabs.TabPages.AddRange(new[]{tableTab,g2tab,g3tab,hexTab,infoTab});

        var editorStatus=new Panel{Dock=DockStyle.Bottom,Height=42,BackColor=Color.FromArgb(10,18,28),Padding=new Padding(8,6,8,5)};
        var checkBtn=SmallButton("بررسی چکسام",VerifyChecksum,118);checkBtn.Dock=DockStyle.Right;
        var fixBtn=SmallButton("اصلاح چکسام",RepairChecksum,118);fixBtn.Dock=DockStyle.Right;
        editorStatus.Controls.Add(fixBtn);editorStatus.Controls.Add(checkBtn);

        editor.Controls.Add(_tabs);editor.Controls.Add(editorStatus);
        editorShell.Controls.Add(editor);workSplit.Panel2.Controls.Add(editorShell);

        outer.Panel2.Controls.Add(workSplit);workspace.Controls.Add(outer);Controls.Add(workspace);

        var status=new StatusStrip{Dock=DockStyle.Bottom,Height=29,BackColor=Color.FromArgb(7,12,19),ForeColor=Fg,SizingGrip=false};
        _status.Spring=true;_status.TextAlign=ContentAlignment.MiddleRight;_status.ForeColor=Color.FromArgb(202,214,227);
        _bank.ForeColor=Blue;status.Items.Add(_status);status.Items.Add(_bank);Controls.Add(status);

        topBar.BringToFront();stageBar.BringToFront();header.BringToFront();status.BringToFront();
        ResumeLayout(true);
    }

    private Button ActionButton(string text,Action action,bool accent,int width)
    {
        var b=new ModernButton
        {
            Text=text,Width=width,Height=40,AccentMode=accent,AccentColor=Accent,
            ForeColor=Color.White,Margin=new Padding(4,0,4,0),
            Font=new Font("Segoe UI",9.2f,accent?FontStyle.Bold:FontStyle.Regular),
            Image=IconFactory.Create(IconKey(text),21)
        };
        b.Click+=(_,_)=>action();return b;
    }

    private Button StageButton(string text,Action action)
    {
        var b=new ModernButton
        {
            Dock=DockStyle.Fill,Text=text,AccentMode=false,AccentColor=Accent,
            ForeColor=Color.FromArgb(220,231,241),Margin=new Padding(4,1,4,1),
            Font=new Font("Segoe UI",9.2f,FontStyle.Bold),
            Image=IconFactory.Create(IconKey(text),20)
        };
        b.Click+=(_,_)=>action();return b;
    }

    private Button SmallButton(string text,Action action,int width)
    {
        var b=new ModernButton
        {
            Text=text,Width=width,Height=33,AccentMode=false,AccentColor=Accent,
            ForeColor=Fg,Margin=new Padding(3,0,3,0),
            Font=new Font("Segoe UI",8.7f,FontStyle.Bold),
            Image=IconFactory.Create(IconKey(text),18)
        };
        b.Click+=(_,_)=>action();return b;
    }

    private static string IconKey(string text)
    {
        string t=(text??"").ToLowerInvariant();
        if(t.Contains("راهنما")||t.Contains("?")||t.Contains("؟"))return "help";
        if(t.Contains("باز")||t.Contains("بانک خودرو"))return "open";
        if(t.Contains("ذخیره")||t.Contains("خروجی"))return "save";
        if(t.Contains("چکسام"))return "checksum";
        if(t.Contains("مقایسه"))return "compare";
        if(t.Contains("ویرایش"))return "edit";
        if(t.Contains("نقشه")||t.Contains("xdf")||t.Contains("تعریف"))return "map";
        if(t.Contains("rar")||t.Contains("zip")||t.Contains("7z"))return "archive";
        if(t.Contains("ecu")||t.Contains("شناسایی"))return "ecu";
        if(t.Contains("undo"))return "undo";
        if(t.Contains("redo"))return "redo";
        return "file";
    }

    private Control InfoCard(string caption,Label value,Color valueColor)
    {
        var p=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(18,30,44),Margin=new Padding(4),Padding=new Padding(10,5,10,5)};
        var cap=new Label{Dock=DockStyle.Top,Height=22,Text=caption,ForeColor=Muted,Font=new Font("Segoe UI",8.2f,FontStyle.Bold),TextAlign=ContentAlignment.MiddleRight};
        value.Dock=DockStyle.Fill;value.AutoEllipsis=true;value.ForeColor=valueColor;value.Font=new Font("Segoe UI",9f,FontStyle.Bold);value.TextAlign=ContentAlignment.MiddleRight;
        value.Text=caption+" : —";p.Controls.Add(value);p.Controls.Add(cap);return p;
    }

    private void SetupTree(TreeView tree)
    {
        tree.Dock=DockStyle.Fill;tree.BackColor=Color.FromArgb(11,19,29);tree.ForeColor=Fg;tree.BorderStyle=BorderStyle.None;
        tree.Font=new Font("Segoe UI",9.2f);tree.ItemHeight=30;tree.HideSelection=false;tree.ShowLines=false;tree.ShowPlusMinus=true;tree.FullRowSelect=true;
        var il=new ImageList{ImageSize=new Size(20,20),ColorDepth=ColorDepth.Depth32Bit};
        il.Images.Add("folder",IconFactory.Create("folder",20));
        il.Images.Add("car",IconFactory.Create("car",20));
        il.Images.Add("ecu",IconFactory.Create("ecu",20));
        il.Images.Add("file",IconFactory.Create("file",20));
        il.Images.Add("archive",IconFactory.Create("archive",20));
        il.Images.Add("map",IconFactory.Create("map",20));
        tree.ImageList=il;
    }

    private void StyleHeader(Label l,Color c){l.Dock=DockStyle.Fill;l.AutoEllipsis=true;l.ForeColor=c;l.TextAlign=ContentAlignment.MiddleRight;l.Font=new Font("Segoe UI",9.1f);}
    private void AddTool(ToolStrip t,string text,EventHandler h,bool accent){var b=new ToolStripButton(text){DisplayStyle=ToolStripItemDisplayStyle.Text,ForeColor=accent?Accent:Fg,Font=new Font("Segoe UI",10f,accent?FontStyle.Bold:FontStyle.Regular),Margin=new Padding(4,0,4,0)};b.Click+=h;t.Items.Add(b);}

    private void SetupGrid()
    {
        _grid.Dock=DockStyle.Fill;_grid.BackgroundColor=Color.FromArgb(10,18,28);_grid.BorderStyle=BorderStyle.None;_grid.AllowUserToAddRows=false;_grid.AllowUserToDeleteRows=false;_grid.AllowUserToResizeRows=false;
        _grid.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;_grid.EnableHeadersVisualStyles=false;_grid.ColumnHeadersHeight=38;_grid.RowTemplate.Height=32;_grid.CellBorderStyle=DataGridViewCellBorderStyle.SingleHorizontal;
        _grid.ColumnHeadersDefaultCellStyle.BackColor=Color.FromArgb(27,45,61);_grid.ColumnHeadersDefaultCellStyle.ForeColor=Fg;_grid.ColumnHeadersDefaultCellStyle.Alignment=DataGridViewContentAlignment.MiddleCenter;_grid.ColumnHeadersDefaultCellStyle.Font=new Font("Segoe UI",9.3f,FontStyle.Bold);
        _grid.RowHeadersDefaultCellStyle.BackColor=Color.FromArgb(20,34,48);_grid.RowHeadersDefaultCellStyle.ForeColor=Color.FromArgb(150,181,208);
        _grid.DefaultCellStyle.BackColor=Color.FromArgb(16,27,40);_grid.AlternatingRowsDefaultCellStyle.BackColor=Color.FromArgb(19,31,45);_grid.DefaultCellStyle.ForeColor=Fg;_grid.DefaultCellStyle.SelectionBackColor=Color.FromArgb(25,112,132);_grid.DefaultCellStyle.SelectionForeColor=Color.White;
        _grid.GridColor=Color.FromArgb(36,54,72);_grid.RowHeadersWidth=82;_grid.RightToLeft=RightToLeft.No;_grid.MultiSelect=true;_grid.SelectionMode=DataGridViewSelectionMode.CellSelect;
    }

    private void HookEvents()
    {
        _search.TextChanged+=(_,_)=>RefreshMapList();_category.SelectedIndexChanged+=(_,_)=>RefreshMapList();
        _librarySearch.TextChanged+=(_,_)=>{BuildEcuLibraryTree();BuildFileTree();};
        _ecuTree.AfterSelect+=(_,e)=>ShowEcuProfileDetails(e.Node?.Tag as EcuProfile);
        _ecuTree.NodeMouseDoubleClick+=(_,e)=>{if(e.Node?.Tag is EcuProfile p)FilterFilesForEcu(p);};
        _fileTree.AfterSelect+=(_,e)=>ShowDumpDetails(e.Node?.Tag as DumpCatalogItem);
        _fileTree.NodeMouseDoubleClick+=(_,e)=>{if(e.Node?.Tag is DumpCatalogItem x)OpenCatalogDump(x);};
        _mapList.SelectedIndexChanged+=(_,_)=>{if(_mapList.SelectedItem is MapDefinition m)SelectMap(m);};
        _grid.CellEndEdit+=GridCellEndEdit;
        DragEnter+=(_,e)=>{if(e.Data?.GetDataPresent(DataFormats.FileDrop)==true)e.Effect=DragDropEffects.Copy;};
        DragDrop+=(_,e)=>{if(e.Data?.GetData(DataFormats.FileDrop) is string[] a&&a.Length>0)LoadDump(a[0]);};
    }

    private void OpenCatalogDump(DumpCatalogItem item)
    {
        try
        {
            Cursor=Cursors.WaitCursor;
            string path=_catalog.Materialize(item);
            LoadDump(path);
            _libraryTabs.SelectedIndex=1;
            SetStatus($"فایل واقعی از بانک باز شد: {item.Name}");
        }
        catch(Exception ex)
        {
            MessageBox.Show(this,ex.Message,"باز کردن فایل بانک",MessageBoxButtons.OK,MessageBoxIcon.Error);
        }
        finally{Cursor=Cursors.Default;}
    }

    private void FilterFilesForEcu(EcuProfile p)
    {
        _libraryTabs.SelectedIndex=1;
        _librarySearch.Text=p.Family;
        SetStatus($"بانک فایل برای {p.Vendor} {p.Family} فیلتر شد.");
    }

    private void OpenTuningGuide()
    {
        if(!_doc.HasFile)
        {
            Info("ابتدا یک دامپ ECU را باز کنید.");
            return;
        }
        if(_maps.Count==0)
        {
            Info("برای این فایل هنوز جدول کالیبراسیون دقیق یا تعریف واردشده وجود ندارد.");
            return;
        }

        using var d=new TuningGuideDialog(_maps,_selected);
        if(d.ShowDialog(this)!=DialogResult.OK || !d.ApplyRequested || d.SelectedMap==null)return;

        var map=d.SelectedMap;
        SelectMap(map);
        var rule=TuningGuide.For(map);

        string mode=d.AutoRequested?rule.EditMode:d.ApplyMode;
        double value=d.AutoRequested?rule.DefaultValue:d.ApplyValue;

        if(d.AutoRequested && !TuningGuide.CanAuto(map,rule))
        {
            MessageBox.Show(this,"AUTO برای این جدول فعال نیست؛ تعریف/Scale یا Rule عددی دقیق باید تأیید شده باشد.",
                "راهنمای ریمپ",MessageBoxButtons.OK,MessageBoxIcon.Warning);
            return;
        }

        ApplyGuideEdit(map,mode,value,d.WholeMap,d.AutoRequested,rule);
    }

    private void ApplyGuideEdit(MapDefinition map,string mode,double value,bool wholeMap,bool auto,TuningGuideRule rule)
    {
        if(map.ReadOnly)
        {
            Info("این جدول Read-only است و تا زمان تعریف دقیق قابل تغییر نیست.");
            return;
        }

        var cells=new List<(int r,int c)>();
        if(!wholeMap && _grid.SelectedCells.Count>0)
        {
            foreach(DataGridViewCell cell in _grid.SelectedCells)
                cells.Add((cell.RowIndex,cell.ColumnIndex));
        }
        else
        {
            for(int r=0;r<map.Rows;r++)
                for(int col=0;col<map.Cols;col++)cells.Add((r,col));
        }

        cells=cells.Distinct().ToList();
        if(cells.Count==0){Info("سلولی برای ویرایش انتخاب نشده است.");return;}

        string op=mode=="percent"?$"{value:+0.###;-0.###;0}%":$"{value:+0.###;-0.###;0} {map.Unit}".Trim();
        var confirm=MessageBox.Show(this,
            $"{map.NameFa}\n\nعملیات: {op}\nناحیه: {(wholeMap?"کل جدول":cells.Count+" سلول انتخابی")}\n\n{rule.SuggestedRange}\n\nبعد از تغییر، Checksum و دیتالاگ را بررسی کنید.\n\nاعمال شود؟",
            auto?"AUTO پیشنهادی":"ویرایش راهنما",MessageBoxButtons.YesNo,MessageBoxIcon.Question);
        if(confirm!=DialogResult.Yes)return;

        var changes=new List<ByteChange>();
        int size=Util.TypeSize(map.DataType);
        foreach(var (r,col) in cells)
        {
            int addr=checked((int)map.Address+(r*map.Cols+col)*size);
            double oldV=_doc.ReadScaled(addr,map);
            double newV=mode=="percent"?oldV*(1+value/100d):oldV+value;
            var old=_doc.GetBytes(addr,size);
            try
            {
                _doc.WriteScaled(addr,map,newV);
                var now=_doc.GetBytes(addr,size);
                if(!old.SequenceEqual(now))
                    changes.Add(new ByteChange{Address=addr,OldBytes=old,NewBytes=now});
            }
            catch
            {
                _doc.PutBytes(addr,old);
            }
        }

        if(changes.Count>0)
        {
            _undo.Push(changes);_redo.Clear();
            AfterEdit($"{(auto?"AUTO":"راهنما")}: {changes.Count} سلول تغییر کرد • {op}");
        }
        else
        {
            SetStatus("هیچ تغییر معتبری اعمال نشد.");
        }
    }

    private void OpenDump(){using var d=new OpenFileDialog{Filter="ECU files|*.bin;*.ori;*.mod;*.rom;*.dump|All files|*.*",Title="باز کردن دامپ ECU"};if(d.ShowDialog(this)==DialogResult.OK)LoadDump(d.FileName);}
    private void LoadDump(string path)
    {
        try
        {
            Cursor=Cursors.WaitCursor;_doc.Load(path);_undo.Clear();_redo.Clear();_maps.Clear();_selected=null;
            _id=_catalog.Identify(_doc.Working,_doc.Sha256);
            _file.Text=$"فایل: {Path.GetFileName(path)} • {Util.FormatSize(_doc.Length)}";
            _hash.Text=$"SHA-256: {_doc.Sha256}";
            _identify.Text=_id.IsExact ? $"تشخیص دقیق: {_id.DisplayName}" :
                _id.BestCandidate!=null && _id.Similarity>=0.5 ? $"نزدیک‌ترین دامپ: {_id.DisplayName} • {_id.Similarity:P0}" : "تشخیص دقیق: پیدا نشد";
            LoadMaps();
            UpdateChecksumLabel();UpdateChanges();SetStatus(_id.IsExact?"دامپ با بانک مرجع تطبیق دقیق دارد.":"فایل باز شد؛ نتیجه‌های غیرقطعی با برچسب مناسب نمایش داده می‌شوند.");
        }
        catch(Exception ex){MessageBox.Show(this,ex.Message,"خطا",MessageBoxButtons.OK,MessageBoxIcon.Error);}
        finally{Cursor=Cursors.Default;}
    }

    private void LoadMaps()
    {
        _maps.Clear();
        var profile=_profileStore.FindExact(_doc.Sha256) ?? BuiltInProfiles.Find(_doc.Sha256);
        if(profile!=null)
        {
            _maps.AddRange(profile.Maps);
            SetStatus($"پروفایل دقیق بارگذاری شد: {profile.ProfileName} • {profile.Maps.Count} تعریف");
        }
        else if(_id.Vendor.Equals("siemens",StringComparison.OrdinalIgnoreCase) || _id.DisplayName.Contains("Siemens",StringComparison.OrdinalIgnoreCase))
        {
            bool bi=_id.DisplayName.Contains("Bifuel",StringComparison.OrdinalIgnoreCase)||_id.DisplayName.Contains("CNG",StringComparison.OrdinalIgnoreCase);
            _maps.AddRange(SiemensMapScanner.Scan(_doc.Working,bi));
        }
        RefreshCategories();RefreshMapList();ClearMapView();
    }

    private void ImportDefinition()
    {
        if(!_doc.HasFile){Info("ابتدا دامپ را باز کنید.");return;}
        using var d=new OpenFileDialog{Filter="Definitions|*.xdf;*.a2l;*.json;*.csv|TunerPro XDF|*.xdf|ASAM A2L|*.a2l|JSON|*.json|CSV|*.csv"};
        if(d.ShowDialog(this)!=DialogResult.OK)return;
        try
        {
            var p=DefinitionParsers.Parse(d.FileName);int ok=0,reject=0;
            var valid=new List<MapDefinition>();
            foreach(var m in p.Maps)
            {
                if(Inside(m)){_maps.Add(m);valid.Add(m);ok++;}
                else reject++;
            }
            RefreshCategories();RefreshMapList();
            SetStatus($"تعریف وارد شد: {ok} مورد" +(reject>0?$" • {reject} خارج از محدوده":""));

            if(valid.Count>0)
            {
                var bind=MessageBox.Show(this,
                    "این تعریف به SHA-256 همین دامپ متصل و در بانک تعریف محلی ذخیره شود؟\n\nدر دفعات بعد همین فایل به‌صورت خودکار با همین جداول باز می‌شود.",
                    "ثبت پروفایل دقیق",MessageBoxButtons.YesNo,MessageBoxIcon.Question);
                if(bind==DialogResult.Yes)
                {
                    p.Maps=valid;
                    if(string.IsNullOrWhiteSpace(p.ProfileName)) p.ProfileName=Path.GetFileNameWithoutExtension(d.FileName);
                    if(string.IsNullOrWhiteSpace(p.EcuVendor)) p.EcuVendor=_id.Vendor;
                    if(string.IsNullOrWhiteSpace(p.EcuFamily)) p.EcuFamily=_id.FamilyHint;
                    _profileStore.SaveForDump(p,_doc.Sha256,_doc.Length,$"{p.Source} • bound to exact dump");
                    _bank.Text=$"ECU: {_ecuProfiles.Count:N0} • دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";BuildFileTree();
                    SetStatus($"پروفایل دقیق ذخیره شد: {p.ProfileName} • {valid.Count} جدول/پارامتر.");
                }
            }
        }catch(Exception ex){MessageBox.Show(this,ex.Message,"خطای تعریف",MessageBoxButtons.OK,MessageBoxIcon.Error);}
    }


    private void ShowPublicDefinitions()
    {
        using var d=new PublicDefinitionsDialog();
        if(d.ShowDialog(this)!=DialogResult.OK || string.IsNullOrWhiteSpace(d.ResourceSuffix)) return;
        if(!_doc.HasFile){Info("ابتدا دامپ ECU را باز کنید، سپس تعریف را اعمال کنید.");return;}
        try
        {
            string text=EmbeddedData.ReadTextBySuffix(d.ResourceSuffix);
            string temp=Path.Combine(Path.GetTempPath(),Guid.NewGuid().ToString("N")+".xdf");
            File.WriteAllText(temp,text,System.Text.Encoding.UTF8);
            var p=DefinitionParsers.Parse(temp);
            File.Delete(temp);
            int ok=0;
            foreach(var m in p.Maps) if(Inside(m)){_maps.Add(m);ok++;}
            RefreshCategories();RefreshMapList();SetStatus($"تعریف آزاد {d.SelectedTitle} وارد شد: {ok} جدول/پارامتر.");
        }
        catch(Exception ex){MessageBox.Show(this,ex.Message,"تعریف رایگان",MessageBoxButtons.OK,MessageBoxIcon.Error);}
    }

    private void IndexDumpFolder()
    {
        using var d=new FolderBrowserDialog{Description="پوشه بانک دامپ‌ها را انتخاب کنید. برنامه SHA-256 و اثرانگشت بلوکی فایل‌ها را ایندکس می‌کند.",UseDescriptionForTitle=true};
        if(d.ShowDialog(this)!=DialogResult.OK)return;
        try{Cursor=Cursors.WaitCursor;int n=_catalog.IndexFolder(d.SelectedPath,true);_bank.Text=$"ECU: {_ecuProfiles.Count:N0} • دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";BuildFileTree();SetStatus($"بانک دامپ به‌روزرسانی شد: {n:N0} فایل جدید.");if(_doc.HasFile){_id=_catalog.Identify(_doc.Working,_doc.Sha256);_identify.Text=_id.IsExact?$"تشخیص دقیق: {_id.DisplayName}":$"نزدیک‌ترین: {_id.DisplayName} • {_id.Similarity:P0}";UpdateChecksumLabel();}}
        catch(Exception ex){MessageBox.Show(this,ex.Message,"خطای ایندکس",MessageBoxButtons.OK,MessageBoxIcon.Error);}finally{Cursor=Cursors.Default;}
    }

    private void IndexDumpArchive()
    {
        using var d=new OpenFileDialog
        {
            Title="انتخاب آرشیو بانک دامپ",
            Filter="Archives|*.rar;*.zip;*.7z;*.001|RAR|*.rar|ZIP|*.zip|7-Zip|*.7z;*.001|All files|*.*"
        };
        if(d.ShowDialog(this)!=DialogResult.OK)return;

        try
        {
            Cursor=Cursors.WaitCursor;
            SetStatus("در حال ایندکس آرشیو دامپ‌ها...");
            int n=_catalog.IndexArchive(d.FileName,true);
            _bank.Text=$"بانک دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";

            if(_doc.HasFile)
            {
                _id=_catalog.Identify(_doc.Working,_doc.Sha256);
                _identify.Text=_id.IsExact
                    ? $"تشخیص دقیق: {_id.DisplayName}"
                    : _id.BestCandidate!=null
                        ? $"نزدیک‌ترین: {_id.DisplayName} • {_id.Similarity:P0}"
                        : "تشخیص دقیق: پیدا نشد";
                UpdateChecksumLabel();
            }

            MessageBox.Show(this,
                $"ایندکس آرشیو تمام شد.\n\nفایل جدید: {n:N0}\nکل بانک: {_catalog.Count:N0}",
                "بانک دامپ",MessageBoxButtons.OK,MessageBoxIcon.Information);
            SetStatus($"آرشیو ایندکس شد: {n:N0} دامپ جدید.");
        }
        catch(Exception ex)
        {
            MessageBox.Show(this,ex.Message,"خطای ایندکس آرشیو",MessageBoxButtons.OK,MessageBoxIcon.Error);
        }
        finally{Cursor=Cursors.Default;}
    }

    private void BuildEcuLibraryTree()
    {
        string q=_librarySearch.Text.Trim().ToLowerInvariant();
        var profiles=_ecuProfiles.Where(p=>q.Length==0 || p.SearchText.Contains(q)).ToList();

        _ecuTree.BeginUpdate();
        _ecuTree.Nodes.Clear();
        _ecuTree.Sorted=true;

        foreach(var p in profiles)
        {
            var makers=p.VehicleMakers.Count>0?p.VehicleMakers:new List<string>{"سایر"};
            var vehicles=p.Vehicles.Count>0?p.Vehicles:new List<string>{"خودرو نامشخص"};

            foreach(string maker in makers)
            {
                var makerNode=GetOrAdd(_ecuTree.Nodes,maker);
                foreach(string vehicle in vehicles)
                {
                    var vehicleNode=GetOrAdd(makerNode.Nodes,vehicle);
                    var vendorNode=GetOrAdd(vehicleNode.Nodes,p.Vendor);
                    string bus=p.Buses.Count>0?string.Join("/",p.Buses):"—";
                    string suffix=p.AutoIdentification?"  AUTO-ID":"";
                    var leaf=new TreeNode($"{p.Family}   [{bus}]{suffix}"){Tag=p,ForeColor=p.AutoIdentification?Accent:Fg};
                    vendorNode.Nodes.Add(leaf);
                }
            }
        }

        AddCounts(_ecuTree.Nodes);
        if(q.Length>0)_ecuTree.ExpandAll();
        else
        {
            foreach(TreeNode n in _ecuTree.Nodes)n.Collapse();
        }
        _ecuTree.EndUpdate();
        UpdateLibraryStats();
    }

    private void BuildFileTree()
    {
        string q=_librarySearch.Text.Trim().ToLowerInvariant();
        var items=_catalog.Items.Where(x =>
            q.Length==0 ||
            (x.Name??"").ToLowerInvariant().Contains(q) ||
            (x.Path??"").ToLowerInvariant().Contains(q) ||
            (x.Vendor??"").ToLowerInvariant().Contains(q) ||
            (x.FamilyHint??"").ToLowerInvariant().Contains(q)).ToList();

        _fileTree.BeginUpdate();
        _fileTree.Nodes.Clear();
        _fileTree.Sorted=true;

        foreach(var x in items)
        {
            string vendor=string.IsNullOrWhiteSpace(x.Vendor)?"unknown":x.Vendor;
            string family=string.IsNullOrWhiteSpace(x.FamilyHint)?"سایر / نامشخص":x.FamilyHint;
            var vendorNode=GetOrAdd(_fileTree.Nodes,vendor);
            var familyNode=GetOrAdd(vendorNode.Nodes,family);
            var leaf=new TreeNode($"{x.Name}   •   {Util.FormatSize(x.Size)}"){Tag=x,ForeColor=Color.FromArgb(207,221,235)};
            familyNode.Nodes.Add(leaf);
        }

        AddCounts(_fileTree.Nodes);
        if(q.Length>0)_fileTree.ExpandAll();
        _fileTree.EndUpdate();
        UpdateLibraryStats();
    }

    private static TreeNode GetOrAdd(TreeNodeCollection nodes,string text)
    {
        foreach(TreeNode n in nodes)
            if(string.Equals(n.Name,text,StringComparison.OrdinalIgnoreCase))return n;
        var node=new TreeNode(text){Name=text};
        nodes.Add(node);
        return node;
    }

    private static void AddCounts(TreeNodeCollection nodes)
    {
        foreach(TreeNode n in nodes)
        {
            if(n.Nodes.Count>0)
            {
                AddCounts(n.Nodes);
                int leaves=CountLeaves(n);
                string baseText=n.Name.Length>0?n.Name:n.Text;
                n.Text=$"{baseText}   ({leaves})";
            }
        }
    }

    private static int CountLeaves(TreeNode n)
    {
        if(n.Nodes.Count==0)return 1;
        int c=0;foreach(TreeNode ch in n.Nodes)c+=CountLeaves(ch);return c;
    }

    private void ShowEcuProfileDetails(EcuProfile? p)
    {
        if(p==null)return;
        string yes="✓",no="—";
        _ecuDetails.Text=
            $"ECU: {p.Vendor} / {p.Family}\n"+
            $"Variant: {(p.Variants.Count>0?string.Join(" • ",p.Variants):"—")}\n"+
            $"خودرو: {(p.Vehicles.Count>0?string.Join(" • ",p.Vehicles):"—")}\n"+
            $"پروتکل: {(p.Buses.Count>0?string.Join(" / ",p.Buses):"—")}\n"+
            $"روش پروگرام: {(p.ProgrammingMethods.Count>0?string.Join(" / ",p.ProgrammingMethods):"—")}\n"+
            $"OBD: {(p.ObdProgramming?yes:no)}   Auto ID: {(p.AutoIdentification?yes:no)}   Confidence: {p.Confidence}";
    }

    private void ShowDumpDetails(DumpCatalogItem? x)
    {
        if(x==null)return;
        _ecuDetails.Text=
            $"فایل: {x.Name}\n"+
            $"ECU Vendor: {x.Vendor}\n"+
            $"Family Hint: {(string.IsNullOrWhiteSpace(x.FamilyHint)?"—":x.FamilyHint)}\n"+
            $"Size: {Util.FormatSize(x.Size)}\n"+
            $"SHA-256: {x.Sha256}\n"+
            $"Path: {x.Path}";
    }

    private void UpdateLibraryStats()
    {
        int makers=_ecuProfiles.SelectMany(x=>x.VehicleMakers).Distinct(StringComparer.OrdinalIgnoreCase).Count();
        int vendors=_ecuProfiles.Select(x=>x.Vendor).Distinct(StringComparer.OrdinalIgnoreCase).Count();
        _libraryStats.Text=$"{_ecuProfiles.Count} ECU • {makers} MAKERS • {vendors} VENDORS";
    }

    private void DrawLibraryTab(object? sender,DrawItemEventArgs e)
    {
        if(e.Index<0)return;
        bool selected=e.Index==_libraryTabs.SelectedIndex;
        using var bg=new SolidBrush(selected?Color.FromArgb(25,57,72):Color.FromArgb(16,25,37));
        e.Graphics.FillRectangle(bg,e.Bounds);
        if(selected)
        {
            using var p=new Pen(Accent,3f);
            e.Graphics.DrawLine(p,e.Bounds.Left+10,e.Bounds.Bottom-2,e.Bounds.Right-10,e.Bounds.Bottom-2);
        }
        TextRenderer.DrawText(e.Graphics,_libraryTabs.TabPages[e.Index].Text,
            new Font("Segoe UI",9.2f,selected?FontStyle.Bold:FontStyle.Regular),
            e.Bounds,selected?Color.White:Muted,
            TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter|TextFormatFlags.NoPrefix);
    }

    private void RefreshCategories()
    {
        string old=Convert.ToString(_category.SelectedItem)??"همه";var c=_maps.Select(x=>x.Category).Where(x=>!string.IsNullOrWhiteSpace(x)).Distinct().OrderBy(x=>x).ToList();c.Insert(0,"همه");
        _category.Items.Clear();_category.Items.AddRange(c.Cast<object>().ToArray());_category.SelectedItem=c.Contains(old)?old:"همه";if(_category.SelectedIndex<0)_category.SelectedIndex=0;
    }

    private void RefreshMapList()
    {
        string q=_search.Text.Trim().ToLowerInvariant(),cat=Convert.ToString(_category.SelectedItem)??"همه";string? id=_selected?.Id;
        var list=_maps.Where(m=>(cat=="همه"||m.Category==cat)&&(q.Length==0||m.NameFa.ToLowerInvariant().Contains(q)||m.NameEn.ToLowerInvariant().Contains(q)||m.Category.ToLowerInvariant().Contains(q)||$"0x{m.Address:X}".ToLowerInvariant().Contains(q))).OrderBy(x=>x.Category).ThenBy(x=>x.Address).ToList();
        _mapList.DataSource=null;_mapList.DataSource=list;_mapList.DisplayMember=nameof(MapDefinition.Display);_mapStats.Text=$"{list.Count}/{_maps.Count} MAPS";
        if(id!=null){int i=list.FindIndex(x=>x.Id==id);if(i>=0)_mapList.SelectedIndex=i;}
    }

    private void SelectMap(MapDefinition m)
    {
        _selected=m;FillGrid(m);RenderHex(m);RenderGraphs(m);
        _info.Text=$"نام: {m.NameFa}\r\nName: {m.NameEn}\r\nدسته: {m.Category}\r\nآدرس: 0x{m.Address:X}\r\nابعاد: {m.Rows} × {m.Cols}\r\nنوع: {m.DataType} / {m.Endian}\r\nFactor: {m.Factor}\r\nOffset: {m.Offset}\r\nUnit: {m.Unit}\r\nSource: {m.Source}\r\nConfidence: {m.Confidence}\r\nMode: {(m.ReadOnly?"Read-only":"Editable")}\r\n\r\n{m.Notes}";
    }

    private void FillGrid(MapDefinition m)
    {
        _filling=true;try
        {
            _grid.Columns.Clear();_grid.Rows.Clear();if(!Inside(m))return;int size=Util.TypeSize(m.DataType);
            var xv=AxisValues(m.XAxis,m.Cols);for(int c=0;c<m.Cols;c++)_grid.Columns.Add($"c{c}",xv!=null&&c<xv.Length?Util.Format(xv[c]):$"C{c+1}");
            var yv=AxisValues(m.YAxis,m.Rows);
            for(int r=0;r<m.Rows;r++)
            {
                var row=new object[m.Cols];for(int c=0;c<m.Cols;c++){int a=checked((int)m.Address+(r*m.Cols+c)*size);row[c]=Util.Format(_doc.ReadScaled(a,m));}
                int ri=_grid.Rows.Add(row);_grid.Rows[ri].HeaderCell.Value=yv!=null&&r<yv.Length?Util.Format(yv[r]):$"R{r+1}";
            }
            _grid.ReadOnly=m.ReadOnly;
            for(int r=0;r<m.Rows;r++)for(int c=0;c<m.Cols;c++){int a=checked((int)m.Address+(r*m.Cols+c)*size);if(_doc.IsChanged(a,size))_grid.Rows[r].Cells[c].Style.BackColor=Color.FromArgb(91,65,28);}
        }finally{_filling=false;}
    }

    private double[]? AxisValues(AxisDefinition? a,int count)
    {
        if(a==null)return null;if(a.StaticValues is {Length:>0})return a.StaticValues;if(a.Address<0||a.Count<=0)return null;
        try{var v=new double[a.Count];int size=Util.TypeSize(a.DataType);var tmp=new MapDefinition{DataType=a.DataType,Endian=a.Endian,Factor=a.Factor,Offset=a.Offset};for(int i=0;i<a.Count;i++)v[i]=_doc.ReadScaled(checked((int)a.Address+i*size),tmp);return v;}catch{return null;}
    }

    private void GridCellEndEdit(object? s,DataGridViewCellEventArgs e)
    {
        if(_filling||_selected==null||_selected.ReadOnly||e.RowIndex<0||e.ColumnIndex<0)return;
        if(!double.TryParse(Convert.ToString(_grid.Rows[e.RowIndex].Cells[e.ColumnIndex].Value)?.Replace(',','.'),NumberStyles.Float,CultureInfo.InvariantCulture,out var value)){FillGrid(_selected);return;}
        int size=Util.TypeSize(_selected.DataType),addr=checked((int)_selected.Address+(e.RowIndex*_selected.Cols+e.ColumnIndex)*size);var old=_doc.GetBytes(addr,size);
        try{_doc.WriteScaled(addr,_selected,value);var now=_doc.GetBytes(addr,size);if(!old.SequenceEqual(now)){_undo.Push(new(){new ByteChange{Address=addr,OldBytes=old,NewBytes=now}});_redo.Clear();}AfterEdit("تغییر ثبت شد.");}catch(Exception ex){_doc.PutBytes(addr,old);FillGrid(_selected);MessageBox.Show(this,ex.Message,"مقدار نامعتبر");}
    }

    private void BatchEdit()
    {
        if(_selected==null||_selected.ReadOnly||_grid.SelectedCells.Count==0){Info("چند سلول از یک جدول قابل‌ویرایش را انتخاب کنید.");return;}
        using var d=new BatchEditDialog();if(d.ShowDialog(this)!=DialogResult.OK)return;
        var changes=new List<ByteChange>();int size=Util.TypeSize(_selected.DataType);
        foreach(DataGridViewCell cell in _grid.SelectedCells)
        {
            int addr=checked((int)_selected.Address+(cell.RowIndex*_selected.Cols+cell.ColumnIndex)*size);double oldV=_doc.ReadScaled(addr,_selected),newV=d.Apply(oldV);var old=_doc.GetBytes(addr,size);
            try{_doc.WriteScaled(addr,_selected,newV);var now=_doc.GetBytes(addr,size);if(!old.SequenceEqual(now))changes.Add(new ByteChange{Address=addr,OldBytes=old,NewBytes=now});}catch{_doc.PutBytes(addr,old);}
        }
        if(changes.Count>0){_undo.Push(changes);_redo.Clear();}AfterEdit($"ویرایش گروهی روی {changes.Count} سلول اعمال شد.");
    }

    private void Undo(){if(_undo.Count==0)return;var tx=_undo.Pop();foreach(var c in tx)_doc.PutBytes(c.Address,c.OldBytes);_redo.Push(tx);AfterEdit("Undo انجام شد.");}
    private void Redo(){if(_redo.Count==0)return;var tx=_redo.Pop();foreach(var c in tx)_doc.PutBytes(c.Address,c.NewBytes);_undo.Push(tx);AfterEdit("Redo انجام شد.");}
    private void AfterEdit(string msg){if(_selected!=null){FillGrid(_selected);RenderHex(_selected);RenderGraphs(_selected);}UpdateChanges();UpdateChecksumLabel();SetStatus(msg);}

    private void VerifyChecksum(){if(!_doc.HasFile){Info("ابتدا فایل را باز کنید.");return;}var r=_checksum.Verify(_id,_doc.Working);_checksumLabel.Text=$"Checksum: {(r.Supported?(r.Valid?"OK":"NEEDS FIX"):"Unsupported")} • {r.Family}";_checksumLabel.ForeColor=!r.Supported?Muted:r.Valid?Accent:Warn;MessageBox.Show(this,r.Message,"Checksum",MessageBoxButtons.OK,r.Valid?MessageBoxIcon.Information:MessageBoxIcon.Warning);}
    private void RepairChecksum(){if(!_doc.HasFile){Info("ابتدا فایل را باز کنید.");return;}var before=_doc.Working.ToArray();var r=_checksum.Repair(_id,_doc.Working);if(!r.Supported||!r.Repairable){MessageBox.Show(this,r.Message,"Checksum",MessageBoxButtons.OK,MessageBoxIcon.Warning);return;}var tx=DiffTransaction(before,_doc.Working);if(tx.Count>0){_undo.Push(tx);_redo.Clear();}UpdateChecksumLabel();UpdateChanges();SetStatus(r.Message);}
    private void UpdateChecksumLabel(){if(!_doc.HasFile){_checksumLabel.Text="Checksum: —";return;}var r=_checksum.Verify(_id,_doc.Working);_checksumLabel.Text=r.Supported?$"Checksum: {(r.Valid?"OK":"Needs Fix")} • {r.Family}":"Checksum: Unsupported";_checksumLabel.ForeColor=!r.Supported?Muted:r.Valid?Accent:Warn;}

    private void SaveMod()
    {
        if(!_doc.HasFile){Info("فایلی باز نشده است.");return;}
        var report=_checksum.Verify(_id,_doc.Working);
        if(report.Supported&&!report.Valid&&report.Repairable)
        {
            var ans=MessageBox.Show(this,$"{report.Message}\n\nقبل از ذخیره چکسام اصلاح شود؟","Checksum",MessageBoxButtons.YesNoCancel,MessageBoxIcon.Warning);
            if(ans==DialogResult.Cancel)return;if(ans==DialogResult.Yes)_checksum.Repair(_id,_doc.Working);
        }
        else if(!report.Supported)
        {
            var ans=MessageBox.Show(this,"چکسام این خانواده در نسخه فعلی پشتیبانی نمی‌شود. ذخیره فایل ممکن است برای پروگرام واقعی مناسب نباشد.\n\nبا این حال ذخیره شود؟","هشدار چکسام",MessageBoxButtons.YesNo,MessageBoxIcon.Warning);
            if(ans!=DialogResult.Yes)return;
        }
        using var d=new SaveFileDialog{Filter="Binary|*.bin|All files|*.*",FileName=$"{Path.GetFileNameWithoutExtension(_doc.Path)}_MOD.bin"};
        if(d.ShowDialog(this)!=DialogResult.OK)return;
        try{string sha=_doc.SaveWithBackup(d.FileName);MessageBox.Show(this,$"فایل ذخیره شد.\n\n{d.FileName}\n\nSHA-256:\n{sha}","ذخیره موفق",MessageBoxButtons.OK,MessageBoxIcon.Information);}
        catch(Exception ex){MessageBox.Show(this,ex.Message,"خطای ذخیره",MessageBoxButtons.OK,MessageBoxIcon.Error);}
    }

    private void Compare()
    {
        if(!_doc.HasFile)return;var ranges=_doc.ChangedRanges(100);var sb=new StringBuilder();sb.AppendLine($"Changed bytes: {_doc.ChangedByteCount():N0}");sb.AppendLine();foreach(var x in ranges)sb.AppendLine($"0x{x.Start:X8} - 0x{x.End:X8}  ({x.End-x.Start+1} bytes)");
        using var f=new TextDialog("Original / Modified",sb.ToString());f.ShowDialog(this);
    }

    private void RenderHex(MapDefinition m)
    {
        int center=(int)Math.Clamp(m.Address,0,_doc.Length-1),start=Math.Max(0,center-128),end=Math.Min(_doc.Length,center+1536);var sb=new StringBuilder();
        for(int i=start;i<end;i+=16){sb.Append(i.ToString("X8")).Append("  ");for(int j=0;j<16;j++)sb.Append(i+j<end?_doc.Working[i+j].ToString("X2")+" ":"   ");sb.AppendLine();}
        _hex.Text=sb.ToString();
    }

    private void RenderGraphs(MapDefinition m)
    {
        try{int size=Util.TypeSize(m.DataType);var matrix=new double[m.Rows,m.Cols];var flat=new List<double>();for(int r=0;r<m.Rows;r++)for(int c=0;c<m.Cols;c++){double v=_doc.ReadScaled(checked((int)m.Address+(r*m.Cols+c)*size),m);matrix[r,c]=v;flat.Add(v);}string t=m.NameFa+(string.IsNullOrWhiteSpace(m.Unit)?"":$" [{m.Unit}]");_g2.SetData(flat,t);_g3.SetData(matrix,t);}catch{_g2.SetData(Array.Empty<double>(),"خطا");_g3.SetData(null,"خطا");}
    }

    private bool Inside(MapDefinition m){try{return m.Address>=0&&m.Address+(long)m.Rows*m.Cols*Util.TypeSize(m.DataType)<=_doc.Length;}catch{return false;}}
    private void ClearMapView(){_grid.Columns.Clear();_grid.Rows.Clear();_hex.Clear();_info.Clear();_g2.SetData(Array.Empty<double>(),"بدون داده");_g3.SetData(null,"بدون داده");}
    private void UpdateChanges(){_changes.Text=$"تغییرات: {_doc.ChangedByteCount():N0} بایت";_changes.ForeColor=_doc.ChangedByteCount()==0?Muted:Warn;}
    private List<ByteChange> DiffTransaction(byte[] before,byte[] after){var l=new List<ByteChange>();int i=0;while(i<Math.Min(before.Length,after.Length)){if(before[i]==after[i]){i++;continue;}int s=i;while(i<before.Length&&i<after.Length&&before[i]!=after[i])i++;l.Add(new ByteChange{Address=s,OldBytes=before[s..i],NewBytes=after[s..i]});}return l;}
    private void SetStatus(string s)=>_status.Text=s;
    private void Info(string s)=>MessageBox.Show(this,s,"Khaneh Remap Studio",MessageBoxButtons.OK,MessageBoxIcon.Information);

    private void DrawMapItem(object? s,DrawItemEventArgs e)
    {
        if(e.Index<0)return;bool sel=(e.State&DrawItemState.Selected)!=0;using var bg=new SolidBrush(sel?Color.FromArgb(28,106,127):_mapList.BackColor);using var stripe=new SolidBrush(sel?Accent:Color.FromArgb(49,72,95));e.Graphics.FillRectangle(bg,e.Bounds);e.Graphics.FillRectangle(stripe,e.Bounds.Right-4,e.Bounds.Top+4,4,e.Bounds.Height-8);
        string text=_mapList.Items[e.Index] is MapDefinition m?m.Display:_mapList.Items[e.Index]?.ToString()??"";TextRenderer.DrawText(e.Graphics,text,_mapList.Font,new Rectangle(e.Bounds.X+8,e.Bounds.Y,e.Bounds.Width-18,e.Bounds.Height),sel?Color.White:Fg,TextFormatFlags.Right|TextFormatFlags.VerticalCenter|TextFormatFlags.EndEllipsis|TextFormatFlags.NoPrefix);
    }
    private void DrawTab(object? s,DrawItemEventArgs e){bool sel=e.Index==_tabs.SelectedIndex;using var bg=new SolidBrush(sel?Color.FromArgb(26,57,72):Color.FromArgb(16,25,37));e.Graphics.FillRectangle(bg,e.Bounds);if(sel){using var p=new Pen(Accent,3);e.Graphics.DrawLine(p,e.Bounds.Left+10,e.Bounds.Bottom-2,e.Bounds.Right-10,e.Bounds.Bottom-2);}TextRenderer.DrawText(e.Graphics,_tabs.TabPages[e.Index].Text,new Font("Segoe UI",9.5f,sel?FontStyle.Bold:FontStyle.Regular),e.Bounds,sel?Color.White:Muted,TextFormatFlags.HorizontalCenter|TextFormatFlags.VerticalCenter);}
}

public sealed class BatchEditDialog : Form
{
    private readonly ComboBox _op=new();private readonly NumericUpDown _value=new();
    public BatchEditDialog()
    {
        Text="ویرایش گروهی";Width=410;Height=210;StartPosition=FormStartPosition.CenterParent;BackColor=Color.FromArgb(18,27,40);ForeColor=Color.White;Font=new Font("Segoe UI",10f);RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
        var p=new TableLayoutPanel{Dock=DockStyle.Fill,Padding=new Padding(20),RowCount=3,ColumnCount=2};p.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,35));p.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,65));
        p.Controls.Add(new Label{Text="عملیات",Dock=DockStyle.Fill,TextAlign=ContentAlignment.MiddleRight},0,0);_op.Dock=DockStyle.Fill;_op.DropDownStyle=ComboBoxStyle.DropDownList;_op.Items.AddRange(new object[]{"افزایش درصدی","کاهش درصدی","جمع مقدار","کم کردن مقدار","ضرب","تنظیم مقدار"});_op.SelectedIndex=0;p.Controls.Add(_op,1,0);
        p.Controls.Add(new Label{Text="مقدار",Dock=DockStyle.Fill,TextAlign=ContentAlignment.MiddleRight},0,1);_value.Dock=DockStyle.Fill;_value.DecimalPlaces=3;_value.Minimum=-1000000;_value.Maximum=1000000;_value.Value=5;p.Controls.Add(_value,1,1);
        var ok=new Button{Text="اعمال",DialogResult=DialogResult.OK,Dock=DockStyle.Fill};var cancel=new Button{Text="لغو",DialogResult=DialogResult.Cancel,Dock=DockStyle.Fill};p.Controls.Add(ok,1,2);p.Controls.Add(cancel,0,2);Controls.Add(p);AcceptButton=ok;CancelButton=cancel;
    }
    public double Apply(double old){double v=(double)_value.Value;return _op.SelectedIndex switch{0=>old*(1+v/100),1=>old*(1-v/100),2=>old+v,3=>old-v,4=>old*v,5=>v,_=>old};}
}

public sealed class TextDialog : Form
{
    public TextDialog(string title,string text){Text=title;Width=850;Height=620;StartPosition=FormStartPosition.CenterParent;BackColor=Color.FromArgb(16,25,37);ForeColor=Color.White;var b=new TextBox{Dock=DockStyle.Fill,Multiline=true,ReadOnly=true,ScrollBars=ScrollBars.Both,WordWrap=false,Text=text,BackColor=Color.FromArgb(8,14,22),ForeColor=ForeColor,BorderStyle=BorderStyle.None,Font=new Font("Consolas",10f),RightToLeft=RightToLeft.No};Controls.Add(b);}
}

public sealed class PublicDefinitionsDialog : Form
{
    private readonly ListBox _list=new();
    private readonly Label _desc=new();
    private readonly (string title,string suffix,string desc)[] _items=
    {
        ("OpenGK Siemens 662007 2.0L","Data.PublicDefinitions.OpenGK_ca662007_2000.xdf","TunerPro XDF آزاد OpenGK • Apache-2.0"),
        ("OpenGK Siemens 662008 2.0L","Data.PublicDefinitions.OpenGK_ca662008_2000.xdf","TunerPro XDF آزاد OpenGK • Apache-2.0"),
        ("OpenGK Siemens 654019 2.7L","Data.PublicDefinitions.OpenGK_ca654019_2700.xdf","TunerPro XDF آزاد OpenGK • Apache-2.0")
    };
    public string ResourceSuffix{get;private set;}="";
    public string SelectedTitle{get;private set;}="";
    public PublicDefinitionsDialog()
    {
        Text="تعریف‌های رایگان";Width=700;Height=430;StartPosition=FormStartPosition.CenterParent;BackColor=Color.FromArgb(16,25,37);ForeColor=Color.White;Font=new Font("Segoe UI",10f);RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
        var title=new Label{Text="بانک تعریف‌های آزاد و دارای مجوز",Dock=DockStyle.Top,Height=55,Font=new Font("Segoe UI",15f,FontStyle.Bold),ForeColor=Color.FromArgb(39,215,174),TextAlign=ContentAlignment.MiddleCenter};
        _list.Dock=DockStyle.Fill;_list.BackColor=Color.FromArgb(22,35,50);_list.ForeColor=ForeColor;_list.BorderStyle=BorderStyle.None;_list.DataSource=_items.Select(x=>x.title).ToList();
        _desc.Dock=DockStyle.Bottom;_desc.Height=70;_desc.Padding=new Padding(10);_desc.ForeColor=Color.FromArgb(150,170,192);_desc.TextAlign=ContentAlignment.MiddleRight;
        var bottom=new FlowLayoutPanel{Dock=DockStyle.Bottom,Height=55,FlowDirection=FlowDirection.RightToLeft,Padding=new Padding(8)};
        var apply=new Button{Text="اعمال",Width=110,Height=34};var license=new Button{Text="مجوز",Width=100,Height=34};var close=new Button{Text="بستن",Width=100,Height=34};
        apply.Click+=(_,_)=>{if(_list.SelectedIndex<0)return;var x=_items[_list.SelectedIndex];ResourceSuffix=x.suffix;SelectedTitle=x.title;DialogResult=DialogResult.OK;Close();};
        license.Click+=(_,_)=>{try{using var t=new TextDialog("OpenGK Apache-2.0",EmbeddedData.ReadTextBySuffix("Data.PublicDefinitions.OpenGK_LICENSE.txt"));t.ShowDialog(this);}catch{}};
        close.Click+=(_,_)=>Close();bottom.Controls.AddRange(new Control[]{apply,license,close});
        _list.SelectedIndexChanged+=(_,_)=>{if(_list.SelectedIndex>=0)_desc.Text=_items[_list.SelectedIndex].desc+"\nفقط روی کالیبراسیون سازگار خودش استفاده شود." ;};
        Controls.Add(_list);Controls.Add(_desc);Controls.Add(bottom);Controls.Add(title);if(_list.Items.Count>0)_list.SelectedIndex=0;
    }
}
