using System.Globalization;
using System.Text;

namespace KhanehRemapStudio;

public sealed class MainForm : Form
{
    private readonly BinaryDocument _doc=new();
    private readonly DumpCatalogService _catalog=new();
    private readonly ChecksumManager _checksum=new();
    private readonly ProfileStore _profileStore=new();
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
    private readonly TextBox _search=new(),_info=new(),_hex=new();
    private readonly ComboBox _category=new();
    private readonly ListBox _mapList=new();
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
        BuildUi();HookEvents();SetStatus("آماده — فایل ECU را باز کنید.");
        _bank.Text=$"بانک دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";
    }

    private void BuildUi()
    {
        var tool=new ToolStrip{Dock=DockStyle.Top,Height=48,AutoSize=false,BackColor=Color.FromArgb(7,12,19),ForeColor=Fg,
            GripStyle=ToolStripGripStyle.Hidden,Padding=new Padding(10,7,10,7),RightToLeft=RightToLeft.Yes,
            Renderer=new ToolStripProfessionalRenderer(new DarkColorTable())};
        AddTool(tool,"باز کردن دامپ",(_,_)=>OpenDump(),true);
        AddTool(tool,"ذخیره MOD",(_,_)=>SaveMod(),true);
        tool.Items.Add(new ToolStripSeparator());
        AddTool(tool,"چکسام",(_,_)=>VerifyChecksum(),false);
        AddTool(tool,"اصلاح چکسام",(_,_)=>RepairChecksum(),false);
        tool.Items.Add(new ToolStripSeparator());
        AddTool(tool,"ورود XDF/A2L",(_,_)=>ImportDefinition(),false);
        AddTool(tool,"ایندکس بانک دامپ",(_,_)=>IndexDumpFolder(),false);
        AddTool(tool,"ایندکس RAR/ZIP/7z",(_,_)=>IndexDumpArchive(),false);
        tool.Items.Add(new ToolStripSeparator());
        AddTool(tool,"ویرایش گروهی",(_,_)=>BatchEdit(),false);
        AddTool(tool,"مقایسه",(_,_)=>Compare(),false);
        AddTool(tool,"Undo",(_,_)=>Undo(),false);
        AddTool(tool,"Redo",(_,_)=>Redo(),false);
        Controls.Add(tool);

        var header=new Panel{Dock=DockStyle.Top,Height=112,BackColor=Color.FromArgb(13,22,34),Padding=new Padding(12)};
        var brand=new Label{Dock=DockStyle.Left,Width=270,Text="KHANEH REMAP\nSTUDIO PRO",Font=new Font("Segoe UI",17f,FontStyle.Bold),
            ForeColor=Accent,TextAlign=ContentAlignment.MiddleLeft,RightToLeft=RightToLeft.No};

        var cards=new TableLayoutPanel{Dock=DockStyle.Fill,ColumnCount=2,RowCount=3,Padding=new Padding(8,0,4,0)};
        cards.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,50));cards.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,50));
        for(int i=0;i<3;i++)cards.RowStyles.Add(new RowStyle(SizeType.Percent,33.333f));
        StyleHeader(_file,Fg);StyleHeader(_identify,Blue);StyleHeader(_hash,Muted);StyleHeader(_checksumLabel,Muted);StyleHeader(_changes,Muted);
        var engine=new Label{Text="ENGINE: Binary / Maps / Checksum / Compare",Dock=DockStyle.Fill,AutoEllipsis=true,ForeColor=Color.FromArgb(111,178,235),
            TextAlign=ContentAlignment.MiddleRight,RightToLeft=RightToLeft.No,Font=new Font("Segoe UI",8.5f,FontStyle.Bold)};
        cards.Controls.Add(_file,0,0);cards.Controls.Add(_identify,1,0);cards.Controls.Add(_hash,0,1);cards.Controls.Add(_checksumLabel,1,1);cards.Controls.Add(_changes,0,2);cards.Controls.Add(engine,1,2);
        header.Controls.Add(cards);header.Controls.Add(brand);Controls.Add(header);

        var workspace=new Panel{Dock=DockStyle.Fill,BackColor=Bg,Padding=new Padding(8)};
        var split=new SplitContainer{Dock=DockStyle.Fill,SplitterDistance=360,SplitterWidth=6,Panel1MinSize=300,Panel2MinSize=650,BackColor=Color.FromArgb(6,11,18),RightToLeft=RightToLeft.No};

        var leftOuter=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(33,50,68),Padding=new Padding(1)};
        var left=new Panel{Dock=DockStyle.Fill,BackColor=Panel,Padding=new Padding(12)};
        var title=new Label{Text="نقشه‌ها و پارامترها",Dock=DockStyle.Top,Height=34,Font=new Font("Segoe UI",11.5f,FontStyle.Bold),ForeColor=Fg,TextAlign=ContentAlignment.MiddleRight};
        var filters=new TableLayoutPanel{Dock=DockStyle.Top,Height=82,RowCount=2,ColumnCount=1,Padding=new Padding(0,2,0,7),BackColor=Panel};
        _search.Dock=DockStyle.Fill;_search.BackColor=Panel2;_search.ForeColor=Fg;_search.BorderStyle=BorderStyle.FixedSingle;_search.PlaceholderText="جستجو: جرقه، سوخت، 0x...";_search.Margin=new Padding(0,2,0,5);
        _category.Dock=DockStyle.Fill;_category.DropDownStyle=ComboBoxStyle.DropDownList;_category.BackColor=Panel2;_category.ForeColor=Fg;_category.FlatStyle=FlatStyle.Flat;
        filters.Controls.Add(_search,0,0);filters.Controls.Add(_category,0,1);

        _mapList.Dock=DockStyle.Fill;_mapList.BackColor=Color.FromArgb(12,21,32);_mapList.ForeColor=Fg;_mapList.BorderStyle=BorderStyle.None;
        _mapList.DrawMode=DrawMode.OwnerDrawFixed;_mapList.ItemHeight=35;_mapList.IntegralHeight=false;_mapList.Font=new Font("Segoe UI",9.7f);
        _mapList.DrawItem+=DrawMapItem;
        left.Controls.Add(_mapList);left.Controls.Add(filters);left.Controls.Add(title);leftOuter.Controls.Add(left);split.Panel1.Controls.Add(leftOuter);

        var editorOuter=new Panel{Dock=DockStyle.Fill,BackColor=Color.FromArgb(33,50,68),Padding=new Padding(1)};
        _tabs.Dock=DockStyle.Fill;_tabs.RightToLeft=RightToLeft.Yes;_tabs.RightToLeftLayout=true;_tabs.DrawMode=TabDrawMode.OwnerDrawFixed;_tabs.SizeMode=TabSizeMode.Fixed;_tabs.ItemSize=new Size(126,36);_tabs.DrawItem+=DrawTab;

        var tableTab=new TabPage("جدول"){BackColor=Panel,Padding=new Padding(8)};SetupGrid();tableTab.Controls.Add(_grid);
        var g2tab=new TabPage("2D"){BackColor=Panel,Padding=new Padding(8)};_g2.Dock=DockStyle.Fill;_g2.BackColor=Color.FromArgb(9,16,25);g2tab.Controls.Add(_g2);
        var g3tab=new TabPage("3D"){BackColor=Panel,Padding=new Padding(8)};_g3.Dock=DockStyle.Fill;_g3.BackColor=Color.FromArgb(9,16,25);g3tab.Controls.Add(_g3);
        var hexTab=new TabPage("HEX"){BackColor=Panel,Padding=new Padding(8)};_hex.Dock=DockStyle.Fill;_hex.Multiline=true;_hex.ReadOnly=true;_hex.WordWrap=false;_hex.ScrollBars=ScrollBars.Both;_hex.BackColor=Color.FromArgb(7,13,21);_hex.ForeColor=Color.FromArgb(204,220,237);_hex.Font=new Font("Consolas",10f);_hex.RightToLeft=RightToLeft.No;_hex.BorderStyle=BorderStyle.None;hexTab.Controls.Add(_hex);
        var infoTab=new TabPage("اطلاعات"){BackColor=Panel,Padding=new Padding(12)};_info.Dock=DockStyle.Fill;_info.Multiline=true;_info.ReadOnly=true;_info.ScrollBars=ScrollBars.Vertical;_info.BackColor=Color.FromArgb(15,25,38);_info.ForeColor=Fg;_info.BorderStyle=BorderStyle.None;_info.Font=new Font("Segoe UI",10.5f);infoTab.Controls.Add(_info);
        _tabs.TabPages.AddRange(new[]{tableTab,g2tab,g3tab,hexTab,infoTab});editorOuter.Controls.Add(_tabs);split.Panel2.Controls.Add(editorOuter);
        workspace.Controls.Add(split);Controls.Add(workspace);

        var status=new StatusStrip{Dock=DockStyle.Bottom,Height=28,BackColor=Color.FromArgb(7,12,19),ForeColor=Fg,SizingGrip=false};
        _status.Spring=true;_status.TextAlign=ContentAlignment.MiddleRight;_bank.ForeColor=Blue;status.Items.Add(_status);status.Items.Add(_bank);Controls.Add(status);
        tool.BringToFront();header.BringToFront();status.BringToFront();
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
        _mapList.SelectedIndexChanged+=(_,_)=>{if(_mapList.SelectedItem is MapDefinition m)SelectMap(m);};
        _grid.CellEndEdit+=GridCellEndEdit;
        DragEnter+=(_,e)=>{if(e.Data?.GetDataPresent(DataFormats.FileDrop)==true)e.Effect=DragDropEffects.Copy;};
        DragDrop+=(_,e)=>{if(e.Data?.GetData(DataFormats.FileDrop) is string[] a&&a.Length>0)LoadDump(a[0]);};
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
                    _bank.Text=$"بانک دامپ: {_catalog.Count:N0} • پروفایل دقیق: {_profileStore.CountProfiles():N0}";
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
        try{Cursor=Cursors.WaitCursor;int n=_catalog.IndexFolder(d.SelectedPath,true);_bank.Text=$"بانک دامپ: {_catalog.Count:N0}";SetStatus($"بانک دامپ به‌روزرسانی شد: {n:N0} فایل جدید.");if(_doc.HasFile){_id=_catalog.Identify(_doc.Working,_doc.Sha256);_identify.Text=_id.IsExact?$"تشخیص دقیق: {_id.DisplayName}":$"نزدیک‌ترین: {_id.DisplayName} • {_id.Similarity:P0}";UpdateChecksumLabel();}}
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

    private void RefreshCategories()
    {
        string old=Convert.ToString(_category.SelectedItem)??"همه";var c=_maps.Select(x=>x.Category).Where(x=>!string.IsNullOrWhiteSpace(x)).Distinct().OrderBy(x=>x).ToList();c.Insert(0,"همه");
        _category.Items.Clear();_category.Items.AddRange(c.Cast<object>().ToArray());_category.SelectedItem=c.Contains(old)?old:"همه";if(_category.SelectedIndex<0)_category.SelectedIndex=0;
    }

    private void RefreshMapList()
    {
        string q=_search.Text.Trim().ToLowerInvariant(),cat=Convert.ToString(_category.SelectedItem)??"همه";string? id=_selected?.Id;
        var list=_maps.Where(m=>(cat=="همه"||m.Category==cat)&&(q.Length==0||m.NameFa.ToLowerInvariant().Contains(q)||m.NameEn.ToLowerInvariant().Contains(q)||m.Category.ToLowerInvariant().Contains(q)||$"0x{m.Address:X}".ToLowerInvariant().Contains(q))).OrderBy(x=>x.Category).ThenBy(x=>x.Address).ToList();
        _mapList.DataSource=null;_mapList.DataSource=list;_mapList.DisplayMember=nameof(MapDefinition.Display);
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
