using System;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Linq;
using System.Net.NetworkInformation;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Windows.Forms;
using System.Collections.Generic;

namespace WebsazStore
{
  static class DpiBootstrap
  {
    [DllImport("user32.dll", SetLastError=true)] static extern bool SetProcessDPIAware();
    [DllImport("user32.dll", SetLastError=true)] static extern bool SetProcessDpiAwarenessContext(IntPtr value);
    public static void Enable()
    {
      try { if(SetProcessDpiAwarenessContext(new IntPtr(-4))) return; } catch {}
      try { SetProcessDPIAware(); } catch {}
    }
  }

  static class ProgramModern
  {
    [STAThread]
    static void Main()
    {
      DpiBootstrap.Enable();
      System.Net.ServicePointManager.SecurityProtocol=System.Net.SecurityProtocolType.Tls12;
      Application.EnableVisualStyles();
      Application.SetCompatibleTextRenderingDefault(false);
      Application.Run(new ModernMainForm());
    }
  }

  sealed class ModernMainForm : MainForm
  {
    readonly ModernDashboardView view;
    public ModernMainForm() : base()
    {
      Text="وب ساز - مدیریت کامل فروشگاه";
      AutoScaleMode=AutoScaleMode.Dpi;
      view=new ModernDashboardView(this){Dock=DockStyle.Fill};
      Controls.Add(view);
      view.BringToFront();
      Resize+=(s,e)=>view.Invalidate();
      Activated+=(s,e)=>view.RefreshStatus();
    }

    public void InvokeCore(string method)
    {
      try
      {
        var m=typeof(MainForm).GetMethod(method,BindingFlags.Instance|BindingFlags.NonPublic);
        if(m!=null)m.Invoke(this,null);
      }
      catch(Exception ex)
      {
        var real=ex is TargetInvocationException && ex.InnerException!=null?ex.InnerException:ex;
        MessageBox.Show(this,"عملیات انجام نشد.\n"+real.Message,"وب ساز",MessageBoxButtons.OK,MessageBoxIcon.Warning);
      }
    }

    public bool SiteConfigured()
    {
      try
      {
        var m=typeof(MainForm).GetMethod("HasSiteSettings",BindingFlags.Instance|BindingFlags.NonPublic);
        return m!=null && (bool)m.Invoke(this,null);
      }
      catch{return false;}
    }
  }

  sealed class DashHit
  {
    public string Id,Title;
    public RectangleF Rect;
    public Action Action;
  }

  sealed class ModernDashboardView : Control
  {
    const float VW=1440f,VH=900f;
    readonly ModernMainForm owner;
    readonly List<DashHit> hits=new List<DashHit>();
    string hover="",pressed="";
    readonly Timer timer=new Timer();
    bool internet,site,secondScreen;
    int productCount,saleCount,localCount;

    readonly Color bg=Color.FromArgb(243,247,252);
    readonly Color surface=Color.White;
    readonly Color nav=Color.FromArgb(250,252,255);
    readonly Color primary=Color.FromArgb(30,103,219);
    readonly Color primaryDark=Color.FromArgb(18,79,182);
    readonly Color ink=Color.FromArgb(26,42,68);
    readonly Color muted=Color.FromArgb(104,117,137);
    readonly Color line=Color.FromArgb(226,233,243);
    readonly Color success=Color.FromArgb(33,155,102);
    readonly Color warning=Color.FromArgb(222,155,39);
    readonly Color softBlue=Color.FromArgb(235,243,255);
    readonly Color softGreen=Color.FromArgb(232,248,239);
    readonly Color softOrange=Color.FromArgb(255,246,226);
    readonly Color softPurple=Color.FromArgb(242,237,255);

    public ModernDashboardView(ModernMainForm f)
    {
      owner=f;
      DoubleBuffered=true;
      SetStyle(ControlStyles.AllPaintingInWmPaint|ControlStyles.UserPaint|ControlStyles.OptimizedDoubleBuffer|ControlStyles.ResizeRedraw,true);
      BackColor=bg;
      TabStop=true;
      timer.Interval=3500;
      timer.Tick+=(s,e)=>RefreshStatus();
      timer.Start();
      MouseMove+=Move;
      MouseLeave+=(s,e)=>{hover="";Cursor=Cursors.Default;Invalidate();};
      MouseDown+=Down;
      MouseUp+=Up;
      RefreshStatus();
    }

    public void RefreshStatus()
    {
      try{internet=NetworkInterface.GetIsNetworkAvailable();}catch{internet=false;}
      try{site=owner.SiteConfigured();}catch{site=false;}
      try{secondScreen=Screen.AllScreens.Length>1;}catch{secondScreen=false;}
      try{productCount=owner.LoadProducts().Count;}catch{productCount=0;}
      try{saleCount=owner.LoadType("sale").Count;}catch{saleCount=0;}
      try{localCount=owner.Load().Count;}catch{localCount=0;}
      Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
      base.OnPaint(e);
      var g=e.Graphics;
      g.SmoothingMode=SmoothingMode.AntiAlias;
      g.TextRenderingHint=System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
      g.Clear(bg);
      hits.Clear();
      float s=Math.Min(ClientSize.Width/VW,ClientSize.Height/VH);
      float ox=(ClientSize.Width-VW*s)/2f, oy=(ClientSize.Height-VH*s)/2f;
      g.TranslateTransform(ox,oy);g.ScaleTransform(s,s);

      DrawTopBar(g);
      DrawSidebar(g);
      DrawHero(g);
      DrawStats(g);
      DrawDevices(g);
      DrawQuickActions(g);
      DrawFooter(g);
    }

    RectangleF R(float x,float y,float w,float h)=>new RectangleF(x,y,w,h);
    GraphicsPath Round(RectangleF r,float radius)
    {
      float d=radius*2;var p=new GraphicsPath();
      p.AddArc(r.X,r.Y,d,d,180,90);p.AddArc(r.Right-d,r.Y,d,d,270,90);
      p.AddArc(r.Right-d,r.Bottom-d,d,d,0,90);p.AddArc(r.X,r.Bottom-d,d,d,90,90);p.CloseFigure();return p;
    }
    void FillRound(Graphics g,RectangleF r,float radius,Color c)
    {
      using(var p=Round(r,radius))using(var b=new SolidBrush(c))g.FillPath(b,p);
    }
    void Shadow(Graphics g,RectangleF r,float radius,int alpha=18,float dy=7)
    {
      var q=new RectangleF(r.X+2,r.Y+dy,r.Width,r.Height);
      using(var p=Round(q,radius))using(var b=new SolidBrush(Color.FromArgb(alpha,30,59,110)))g.FillPath(b,p);
    }
    Font F(float size,bool bold=false)=>new Font("Tahoma",size,bold?FontStyle.Bold:FontStyle.Regular,GraphicsUnit.Pixel);
    void Text(Graphics g,string text,RectangleF r,float size,Color color,bool bold=false,StringAlignment align=StringAlignment.Near)
    {
      using(var f=F(size,bold))using(var b=new SolidBrush(color))using(var sf=new StringFormat{Alignment=align,LineAlignment=StringAlignment.Center,FormatFlags=StringFormatFlags.DirectionRightToLeft})
        g.DrawString(text,f,b,r,sf);
    }
    void TextLtr(Graphics g,string text,RectangleF r,float size,Color color,bool bold=false,StringAlignment align=StringAlignment.Near)
    {
      using(var f=F(size,bold))using(var b=new SolidBrush(color))using(var sf=new StringFormat{Alignment=align,LineAlignment=StringAlignment.Center})g.DrawString(text,f,b,r,sf);
    }
    void Hit(string id,string title,RectangleF r,Action action){hits.Add(new DashHit{Id=id,Title=title,Rect=r,Action=action});}
    bool H(string id)=>hover==id;bool P(string id)=>pressed==id;

    void DrawTopBar(Graphics g)
    {
      Shadow(g,R(28,24,1135,88),20,10,5);FillRound(g,R(28,24,1135,88),20,surface);
      DrawStoreBadge(g,R(54,43,210,49));
      var search=R(355,43,500,49);FillRound(g,search,14,Color.FromArgb(248,250,253));using(var pen=new Pen(line,1.2f))using(var p=Round(search,14))g.DrawPath(pen,p);
      DrawSearchIcon(g,new PointF(818,67),muted);Text(g,"جستجوی کالا، مشتری، فاکتور و ...",R(378,43,405,49),14,muted,false,StringAlignment.Far);
      DrawBell(g,new PointF(905,66),muted);
      Text(g,DateTime.Now.ToString("HH:mm"),R(950,45,78,45),14,ink,true,StringAlignment.Center);
      Text(g,"فروشگاه آماده کار",R(1030,45,105,45),11,success,true,StringAlignment.Center);
    }

    void DrawStoreBadge(Graphics g,RectangleF r)
    {
      FillRound(g,r,15,Color.FromArgb(248,250,253));
      FillRound(g,R(r.X+12,r.Y+8,34,34),10,softBlue);DrawShopIcon(g,new PointF(r.X+29,r.Y+25),primary);
      Text(g,"فروشگاه نمونه",R(r.X+55,r.Y,r.Width-68,r.Height),14,ink,true,StringAlignment.Far);
    }

    void DrawSidebar(Graphics g)
    {
      Shadow(g,R(1185,24,225,852),22,12,5);FillRound(g,R(1185,24,225,852),22,nav);
      FillRound(g,R(1211,48,48,48),14,primary);DrawShopIcon(g,new PointF(1235,72),Color.White);
      Text(g,"وب ساز",R(1268,44,115,35),24,primary,true,StringAlignment.Far);
      Text(g,"مدیریت کامل فروشگاه",R(1266,78,118,25),10,muted,false,StringAlignment.Far);

      float y=132;
      Side(g,"dash","داشبورد",y,"home",()=>Invalidate(),true);y+=57;
      Side(g,"migrate","مهاجرت اختیاری",y,"db",()=>owner.InvokeCore("StartMigration"));y+=57;
      Side(g,"sale","فروش",y,"cart",()=>owner.InvokeCore("OpenSale"));y+=57;
      Side(g,"products","کالاها",y,"box",()=>new ProductsForm(owner).ShowDialog(owner));y+=57;
      Side(g,"stock","انبار",y,"warehouse",()=>new InventoryForm(owner).ShowDialog(owner));y+=57;
      Side(g,"account","حسابداری ساده",y,"wallet",()=>new AccountsHubForm(owner).ShowDialog(owner));y+=57;
      Side(g,"report","گزارش‌ها",y,"chart",()=>new ReportsForm(owner).ShowDialog(owner));y+=57;
      Side(g,"devices","دستگاه‌ها",y,"printer",()=>MessageBox.Show(owner,"بارکدخوان، نمایشگر مشتری، پرینتر، ترازو و کارتخوان از این بخش قابل اتصال هستند.\nنمایشگر دوم در صورت اتصال، خودکار شناسایی می‌شود.","دستگاه‌ها"));y+=57;
      Side(g,"site","اتصال سایت",y,"globe",()=>owner.InvokeCore("SiteDialog"));y+=57;
      Side(g,"settings","تنظیمات",y,"gear",()=>System.Diagnostics.Process.Start("explorer.exe",System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),"WebsazStore")));

      FillRound(g,R(1204,760,186,88),16,Color.White);
      DrawShield(g,new PointF(1364,785),success);
      Text(g,"همیشه در کنار شما",R(1220,770,126,25),11,ink,true,StringAlignment.Far);
      Text(g,"آفلاین  •  امن  •  آماده",R(1220,799,126,22),10,success,true,StringAlignment.Far);
      Text(g,"همگام‌سازی بدون توقف فروش",R(1214,822,158,18),9,muted,false,StringAlignment.Far);
    }

    void Side(Graphics g,string id,string title,float y,string icon,Action action,bool active=false)
    {
      var r=R(1204,y,186,47);bool hot=H(id),down=P(id);
      if(active||hot||down)FillRound(g,r,13,down?Color.FromArgb(214,229,253):(active?softBlue:Color.FromArgb(241,246,255)));
      DrawIcon(g,icon,new PointF(1365,y+23.5f),active?primary:(hot?primary:Color.FromArgb(92,107,130)));
      Text(g,title,R(1222,y,124,47),12,active?primary:ink,active||hot,StringAlignment.Far);
      Hit(id,title,r,action);
    }

    void DrawHero(Graphics g)
    {
      var hero=R(28,132,1135,286);Shadow(g,hero,24,14,7);
      using(var p=Round(hero,24))using(var lg=new LinearGradientBrush(hero,Color.White,Color.FromArgb(236,245,255),0f))g.FillPath(lg,p);
      FillRound(g,R(72,166,58,58),18,softBlue);DrawShield(g,new PointF(101,195),primary);
      Text(g,"شروع مستقل و کامل",R(720,158,395,44),27,ink,true,StringAlignment.Far);
      Text(g,"بدون نیاز به اطلاعات هیچ نرم‌افزار دیگری",R(670,203,445,32),13,success,true,StringAlignment.Far);
      Text(g,"فروشگاه از اولین اجرا با دیتابیس محلی خودش آماده است؛ کالا، موجودی، خرید، فروش، حساب‌ها و گزارش‌ها بدون اینترنت هم کار می‌کنند.",R(340,247,775,52),12,muted,false,StringAlignment.Far);

      DrawBullet(g,success,"دیتابیس محلی آماده و مستقل",R(790,311,325,26));
      DrawBullet(g,success,"مهاجرت فقط در صورت انتخاب شما",R(790,341,325,26));
      DrawBullet(g,success,"همگام‌سازی دوطرفه با سایت",R(790,371,325,26));

      PrimaryButton(g,"startSale","شروع فروش",R(72,325,215,56),()=>owner.InvokeCore("OpenSale"));
      SecondaryButton(g,"syncSite",site?"همگام‌سازی سایت":"اتصال به سایت",R(303,325,215,56),()=>owner.InvokeCore("SiteDialog"));
      Text(g,"اگر اطلاعات سیستم دیگری موجود باشد، «مهاجرت اختیاری» آن را بدون ایجاد رکورد تکراری وارد می‌کند.",R(72,268,500,42),10,muted,false,StringAlignment.Far);
    }

    void DrawStats(Graphics g)
    {
      Stat(g,R(28,438,360,102),"کالاهای ثبت‌شده",productCount.ToString("N0"),"box",softBlue,primary);
      Stat(g,R(402,438,360,102),"فاکتورهای فروش",saleCount.ToString("N0"),"cart",softGreen,success);
      Stat(g,R(776,438,387,102),"رکوردهای محلی",localCount.ToString("N0"),"db",softPurple,Color.FromArgb(117,80,196));
    }

    void Stat(Graphics g,RectangleF r,string title,string value,string icon,Color soft,Color accent)
    {
      Shadow(g,r,18,8,4);FillRound(g,r,18,surface);FillRound(g,R(r.Right-72,r.Y+18,48,48),14,soft);DrawIcon(g,icon,new PointF(r.Right-48,r.Y+42),accent);
      Text(g,title,R(r.X+22,r.Y+17,r.Width-112,30),11,muted,false,StringAlignment.Far);
      TextLtr(g,value,R(r.X+22,r.Y+46,r.Width-112,39),24,ink,true,StringAlignment.Far);
    }

    void DrawDevices(Graphics g)
    {
      Text(g,"اتصال خودکار تجهیزات",R(865,558,298,34),18,ink,true,StringAlignment.Far);
      Text(g,"وضعیت تجهیزات و ارتباطات فروشگاه",R(785,590,378,24),10,muted,false,StringAlignment.Far);
      Device(g,R(28,626,178,100),"اینترنت",internet?"پایدار":"قطع",internet,"wifi");
      Device(g,R(220,626,178,100),"کارتخوان / پوز","قابل اتصال",false,"pos",true);
      Device(g,R(412,626,178,100),"ترازو","قابل اتصال",false,"scale",true);
      Device(g,R(604,626,178,100),"فیش پرینتر","قابل اتصال",false,"printer",true);
      Device(g,R(796,626,178,100),"بارکدخوان","آماده",true,"barcode");
      Device(g,R(988,626,175,100),"وب‌سایت",site?"متصل":"تنظیم نشده",site,"globe");
    }

    void Device(Graphics g,RectangleF r,string title,string state,bool ok,string icon,bool neutral=false)
    {
      Shadow(g,r,17,7,4);FillRound(g,r,17,surface);
      Color c=neutral?warning:(ok?success:Color.FromArgb(198,82,82));Color soft=neutral?softOrange:(ok?softGreen:Color.FromArgb(255,237,237));
      FillRound(g,R(r.X+14,r.Y+16,40,40),12,soft);DrawIcon(g,icon,new PointF(r.X+34,r.Y+36),c);
      Text(g,title,R(r.X+64,r.Y+15,r.Width-78,28),10,ink,true,StringAlignment.Far);
      Text(g,state,R(r.X+64,r.Y+47,r.Width-78,22),9,c,true,StringAlignment.Far);
      using(var b=new SolidBrush(c))g.FillEllipse(b,r.X+r.Width-30,r.Y+76,8,8);
    }

    void DrawQuickActions(Graphics g)
    {
      Text(g,"دسترسی سریع",R(915,746,248,42),21,ink,true,StringAlignment.Far);
      Text(g,"کارهای روزمره فروشگاه را مستقیم باز کنید",R(725,783,438,22),10,muted,false,StringAlignment.Far);
      Quick(g,"qreport","گزارش‌ها","فروش و مالی","chart",R(28,813,175,72),softBlue,primary,()=>new ReportsForm(owner).ShowDialog(owner));
      Quick(g,"qaccount","حساب‌ها","مشتری و مالی","wallet",R(217,813,175,72),Color.FromArgb(255,238,241),Color.FromArgb(205,74,98),()=>new AccountsHubForm(owner).ShowDialog(owner));
      Quick(g,"qbuy","خرید و ورود کالا","ورود به انبار","basket",R(406,813,175,72),softPurple,Color.FromArgb(119,82,196),()=>new PurchaseForm(owner).ShowDialog(owner));
      Quick(g,"qstock","موجودی","کنترل انبار","warehouse",R(595,813,175,72),softOrange,warning,()=>new InventoryForm(owner).ShowDialog(owner));
      Quick(g,"qproducts","کالاها","تعریف و ویرایش","box",R(784,813,175,72),softBlue,primary,()=>new ProductsForm(owner).ShowDialog(owner));
      Quick(g,"qsale","فروش جدید","ثبت فاکتور","cart",R(973,813,190,72),softGreen,success,()=>owner.InvokeCore("OpenSale"));
    }

    void Quick(Graphics g,string id,string title,string sub,string icon,RectangleF r,Color soft,Color accent,Action action)
    {
      bool hot=H(id),down=P(id);if(down)r.Offset(0,2);
      Shadow(g,r,17,hot?15:8,hot?6:3);FillRound(g,r,17,hot?Color.FromArgb(252,253,255):surface);
      if(hot){using(var p=Round(r,17))using(var pen=new Pen(Color.FromArgb(115,accent),1.7f))g.DrawPath(pen,p);}
      FillRound(g,R(r.Right-54,r.Y+15,36,36),11,soft);DrawIcon(g,icon,new PointF(r.Right-36,r.Y+33),accent);
      Text(g,title,R(r.X+12,r.Y+10,r.Width-75,28),11,ink,true,StringAlignment.Far);Text(g,sub,R(r.X+12,r.Y+39,r.Width-75,22),9,muted,false,StringAlignment.Far);
      Hit(id,title,r,action);
    }

    void DrawFooter(Graphics g)
    {
      // status is intentionally compact; all main content stays above the Windows taskbar.
    }

    void PrimaryButton(Graphics g,string id,string title,RectangleF r,Action action)
    {
      bool hot=H(id),down=P(id);if(down)r.Offset(0,2);Color c=down?primaryDark:(hot?Color.FromArgb(22,91,204):primary);
      Shadow(g,r,14,hot?20:10,4);FillRound(g,r,14,c);Text(g,title,r,12,Color.White,true,StringAlignment.Center);Hit(id,title,r,action);
    }
    void SecondaryButton(Graphics g,string id,string title,RectangleF r,Action action)
    {
      bool hot=H(id),down=P(id);if(down)r.Offset(0,2);FillRound(g,r,14,hot?Color.FromArgb(239,245,255):Color.White);using(var p=Round(r,14))using(var pen=new Pen(hot?primary:Color.FromArgb(200,215,236),1.5f))g.DrawPath(pen,p);Text(g,title,r,12,primary,true,StringAlignment.Center);Hit(id,title,r,action);
    }
    void DrawBullet(Graphics g,Color c,string text,RectangleF r){using(var b=new SolidBrush(c))g.FillEllipse(b,r.Right-12,r.Y+9,8,8);Text(g,text,new RectangleF(r.X,r.Y,r.Width-22,r.Height),10,ink,true,StringAlignment.Far);}

    PointF ToLogical(Point p)
    {
      float s=Math.Min(ClientSize.Width/VW,ClientSize.Height/VH);float ox=(ClientSize.Width-VW*s)/2f,oy=(ClientSize.Height-VH*s)/2f;
      return new PointF((p.X-ox)/s,(p.Y-oy)/s);
    }
    DashHit Find(Point p){var q=ToLogical(p);return hits.LastOrDefault(h=>h.Rect.Contains(q));}
    void Move(object s,MouseEventArgs e){var h=Find(e.Location);string id=h==null?"":h.Id;if(id!=hover){hover=id;Cursor=h==null?Cursors.Default:Cursors.Hand;Invalidate();}}
    void Down(object s,MouseEventArgs e){if(e.Button!=MouseButtons.Left)return;var h=Find(e.Location);pressed=h==null?"":h.Id;Invalidate();}
    void Up(object s,MouseEventArgs e){var h=Find(e.Location);string id=pressed;pressed="";Invalidate();if(h!=null&&h.Id==id&&h.Action!=null){h.Action();RefreshStatus();}}

    void DrawIcon(Graphics g,string kind,PointF c,Color color)
    {
      using(var p=new Pen(color,2.2f){StartCap=LineCap.Round,EndCap=LineCap.Round})
      {
        switch(kind)
        {
          case "home": g.DrawRectangle(p,c.X-9,c.Y-5,18,14);g.DrawLines(p,new[]{new PointF(c.X-11,c.Y-4),new PointF(c.X,c.Y-13),new PointF(c.X+11,c.Y-4)});break;
          case "db": for(int i=0;i<3;i++){g.DrawEllipse(p,c.X-9,c.Y-11+i*7,18,7);if(i<2){g.DrawLine(p,c.X-9,c.Y-8+i*7,c.X-9,c.Y-1+i*7);g.DrawLine(p,c.X+9,c.Y-8+i*7,c.X+9,c.Y-1+i*7);}}break;
          case "cart": g.DrawLine(p,c.X-11,c.Y-10,c.X-7,c.Y-10);g.DrawLines(p,new[]{new PointF(c.X-7,c.Y-10),new PointF(c.X-3,c.Y+4),new PointF(c.X+9,c.Y+4),new PointF(c.X+12,c.Y-6),new PointF(c.X-5,c.Y-6)});g.DrawEllipse(p,c.X-3,c.Y+7,3,3);g.DrawEllipse(p,c.X+8,c.Y+7,3,3);break;
          case "box": g.DrawRectangle(p,c.X-9,c.Y-7,18,15);g.DrawLine(p,c.X-9,c.Y-2,c.X,c.Y+3);g.DrawLine(p,c.X+9,c.Y-2,c.X,c.Y+3);g.DrawLine(p,c.X,c.Y+3,c.X,c.Y+8);break;
          case "warehouse": g.DrawLines(p,new[]{new PointF(c.X-11,c.Y-5),new PointF(c.X,c.Y-12),new PointF(c.X+11,c.Y-5)});g.DrawRectangle(p,c.X-10,c.Y-5,20,14);g.DrawRectangle(p,c.X-4,c.Y+1,8,8);break;
          case "wallet": g.DrawRectangle(p,c.X-11,c.Y-7,22,15);g.DrawRectangle(p,c.X+2,c.Y-3,9,7);break;
          case "chart": g.DrawLine(p,c.X-11,c.Y+9,c.X-11,c.Y-9);g.DrawLine(p,c.X-11,c.Y+9,c.X+11,c.Y+9);g.DrawLine(p,c.X-7,c.Y+4,c.X-2,c.Y-3);g.DrawLine(p,c.X-2,c.Y-3,c.X+3,c.Y+1);g.DrawLine(p,c.X+3,c.Y+1,c.X+9,c.Y-8);break;
          case "printer": g.DrawRectangle(p,c.X-9,c.Y-9,18,8);g.DrawRectangle(p,c.X-11,c.Y-1,22,10);g.DrawRectangle(p,c.X-7,c.Y+5,14,8);break;
          case "globe": g.DrawEllipse(p,c.X-10,c.Y-10,20,20);g.DrawLine(p,c.X-10,c.Y,c.X+10,c.Y);g.DrawArc(p,c.X-5,c.Y-10,10,20,-90,180);g.DrawArc(p,c.X-5,c.Y-10,10,20,90,180);break;
          case "gear": g.DrawEllipse(p,c.X-7,c.Y-7,14,14);g.DrawEllipse(p,c.X-2,c.Y-2,4,4);for(int i=0;i<8;i++){double a=i*Math.PI/4;g.DrawLine(p,c.X+(float)Math.Cos(a)*8,c.Y+(float)Math.Sin(a)*8,c.X+(float)Math.Cos(a)*12,c.Y+(float)Math.Sin(a)*12);}break;
          case "wifi": g.DrawArc(p,c.X-11,c.Y-6,22,16,200,140);g.DrawArc(p,c.X-7,c.Y-2,14,10,205,130);g.FillEllipse(new SolidBrush(color),c.X-2,c.Y+5,4,4);break;
          case "pos": g.DrawRectangle(p,c.X-8,c.Y-12,16,24);g.DrawRectangle(p,c.X-5,c.Y-8,10,7);g.DrawLine(p,c.X-5,c.Y+1,c.X+5,c.Y+1);g.DrawLine(p,c.X-5,c.Y+5,c.X+5,c.Y+5);break;
          case "scale": g.DrawRectangle(p,c.X-10,c.Y+4,20,7);g.DrawEllipse(p,c.X-6,c.Y-9,12,12);g.DrawLine(p,c.X,c.Y-3,c.X+4,c.Y-7);break;
          case "barcode": for(int i=-9;i<=9;i+=3)g.DrawLine(p,c.X+i,c.Y-10,c.X+i,c.Y+10);break;
          case "basket": g.DrawRectangle(p,c.X-10,c.Y-4,20,13);g.DrawArc(p,c.X-7,c.Y-12,14,16,180,180);break;
          default: g.DrawEllipse(p,c.X-8,c.Y-8,16,16);break;
        }
      }
    }
    void DrawSearchIcon(Graphics g,PointF c,Color col){using(var p=new Pen(col,2)){g.DrawEllipse(p,c.X-8,c.Y-8,12,12);g.DrawLine(p,c.X+2,c.Y+2,c.X+8,c.Y+8);}}
    void DrawBell(Graphics g,PointF c,Color col){using(var p=new Pen(col,2)){g.DrawArc(p,c.X-7,c.Y-8,14,16,190,160);g.DrawLine(p,c.X-8,c.Y+5,c.X+8,c.Y+5);g.DrawArc(p,c.X-3,c.Y+4,6,6,0,180);}}
    void DrawShield(Graphics g,PointF c,Color col){using(var p=new Pen(col,2)){g.DrawLines(p,new[]{new PointF(c.X,c.Y-12),new PointF(c.X+10,c.Y-7),new PointF(c.X+8,c.Y+5),new PointF(c.X,c.Y+12),new PointF(c.X-8,c.Y+5),new PointF(c.X-10,c.Y-7),new PointF(c.X,c.Y-12)});g.DrawLines(p,new[]{new PointF(c.X-4,c.Y),new PointF(c.X-1,c.Y+4),new PointF(c.X+6,c.Y-4)});}}
    void DrawShopIcon(Graphics g,PointF c,Color col){using(var p=new Pen(col,2)){g.DrawRectangle(p,c.X-10,c.Y-3,20,13);g.DrawLines(p,new[]{new PointF(c.X-11,c.Y-3),new PointF(c.X-8,c.Y-11),new PointF(c.X+8,c.Y-11),new PointF(c.X+11,c.Y-3)});g.DrawRectangle(p,c.X-3,c.Y+2,6,8);}}
  }
}
