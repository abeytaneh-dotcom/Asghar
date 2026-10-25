using System;
using System.IO;
using System.Text;
using System.Linq;
using System.Drawing;
using System.Drawing.Imaging;
using System.Windows.Forms;
using System.Collections.Generic;
using System.Data;
using System.Data.SqlClient;
using System.Net;
using Microsoft.Win32;
using System.Security.Cryptography;
using System.Threading;

namespace WebsazStore {
  class Rec { public string T,K,H,P; public long U; }
  class MainForm:Form {
    const int BW=1492,BH=1054; Image bg; string root; bool syncing;
    string DataFile=>Path.Combine(root,"records.sync"), Ini=>Path.Combine(root,"settings.txt"), DeviceFile=>Path.Combine(root,"device.id");
    public MainForm(){
      root=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),"WebsazStore");Directory.CreateDirectory(root);
      Text="وب ساز - مدیریت ساده فروشگاه";StartPosition=FormStartPosition.CenterScreen;WindowState=FormWindowState.Maximized;DoubleBuffered=true;RightToLeft=RightToLeft.Yes;Icon=SystemIcons.Application;
      bg=LoadOverlayImage();MouseUp+=ClickUI;Paint+=DrawUI;Resize+=(s,e)=>Invalidate();
      var tm=new System.Windows.Forms.Timer();tm.Interval=60000;tm.Tick+=(s,e)=>{if(!syncing)new Thread(()=>Sync(false)){IsBackground=true}.Start();};tm.Start();
      Shown+=(s,e)=>{new Thread(()=>{Thread.Sleep(8000);Sync(false);}){IsBackground=true}.Start();};
    }
    Image LoadOverlayImage(){try{var exe=Application.ExecutablePath;using(var f=File.OpenRead(exe)){if(f.Length<16)return null;f.Seek(-16,SeekOrigin.End);var m=new byte[8];f.Read(m,0,8);var lenb=new byte[8];f.Read(lenb,0,8);if(Encoding.ASCII.GetString(m)!="WSUIJPG1")return null;long len=BitConverter.ToInt64(lenb,0);if(len<=0||len>f.Length)return null;f.Seek(-16-len,SeekOrigin.End);var b=new byte[len];f.Read(b,0,b.Length);using(var ms=new MemoryStream(b))return new Bitmap(ms);}}catch{return null;}}
    Rectangle UiRect(){double s=Math.Min((double)ClientSize.Width/BW,(double)ClientSize.Height/BH);int w=(int)(BW*s),h=(int)(BH*s);return new Rectangle((ClientSize.Width-w)/2,(ClientSize.Height-h)/2,w,h);}
    Point ToUi(Point p){var r=UiRect();double s=(double)r.Width/BW;return new Point((int)((p.X-r.X)/s),(int)((p.Y-r.Y)/s));}
    bool Hit(Point p,int l,int t,int r,int b){var u=ToUi(p);return u.X>=l&&u.X<=r&&u.Y>=t&&u.Y<=b;}
    void DrawUI(object s,PaintEventArgs e){e.Graphics.Clear(Color.FromArgb(239,246,255));if(bg!=null)e.Graphics.DrawImage(bg,UiRect());}
    void ClickUI(object s,MouseEventArgs e){
      if(Hit(e.Location,870,420,1135,475)||Hit(e.Location,1200,205,1475,265)) Migrate();
      else if(Hit(e.Location,1200,625,1475,690)||Hit(e.Location,955,655,1135,760)) SiteDialog();
      else if(Hit(e.Location,595,420,860,475)){var a=Load();MessageBox.Show("بررسی انجام شد.\nرکوردهای فعلی: "+a.Count+"\nرکورد تکراری بر اساس کلید تطبیق ساخته نمی‌شود.","بررسی قبل از انتقال");}
      else if(Hit(e.Location,1200,690,1475,755)) System.Diagnostics.Process.Start("explorer.exe",root);
    }
    string Device(){if(File.Exists(DeviceFile))return File.ReadAllText(DeviceFile).Trim();var x=Guid.NewGuid().ToString("N");File.WriteAllText(DeviceFile,x);return x;}
    Dictionary<string,string> Settings(){var d=new Dictionary<string,string>(StringComparer.OrdinalIgnoreCase);if(File.Exists(Ini))foreach(var l in File.ReadAllLines(Ini)){int i=l.IndexOf('=');if(i>0)d[l.Substring(0,i)]=l.Substring(i+1);}return d;}
    void SaveSettings(string url,string key,string store){File.WriteAllLines(Ini,new[]{"url="+url,"key="+key,"store="+store});}
    string B64(string s)=>Convert.ToBase64String(Encoding.UTF8.GetBytes(s??"")); string UB64(string s){try{return Encoding.UTF8.GetString(Convert.FromBase64String(s));}catch{return "";}}
    string Hash(string s){using(var h=SHA256.Create())return BitConverter.ToString(h.ComputeHash(Encoding.UTF8.GetBytes(s))).Replace("-","").ToLowerInvariant();}
    List<Rec> Load(){var a=new List<Rec>();if(!File.Exists(DataFile))return a;foreach(var l in File.ReadLines(DataFile)){var p=l.Split(new[]{'\t'},5);if(p.Length<5)continue;long u;long.TryParse(p[2],out u);a.Add(new Rec{T=UB64(p[0]),K=UB64(p[1]),U=u,H=UB64(p[3]),P=p[4]});}return a;}
    void Merge(IEnumerable<Rec> inc){lock(this){var m=Load().ToDictionary(x=>x.T+"|"+x.K,x=>x,StringComparer.OrdinalIgnoreCase);foreach(var r in inc){var k=r.T+"|"+r.K;Rec old;if(!m.TryGetValue(k,out old)|| (r.U>old.U&&r.H!=old.H))m[k]=r;}using(var w=new StreamWriter(DataFile,false,new UTF8Encoding(false)))foreach(var r in m.Values)w.WriteLine(B64(r.T)+"\t"+B64(r.K)+"\t"+r.U+"\t"+B64(r.H)+"\t"+r.P);}}
    string Norm(object x){var s=(x??"").ToString().Trim().ToLowerInvariant();return new string(s.Where(c=>char.IsLetterOrDigit(c)||c>127).ToArray());}
    string J(object x){return (x??"").ToString().Replace("\\","\\\\").Replace("\"","\\\"").Replace("\r"," ").Replace("\n"," ");}
    string Pick(DataColumnCollection c,params string[] names){foreach(DataColumn x in c)foreach(var n in names)if(x.ColumnName.IndexOf(n,StringComparison.OrdinalIgnoreCase)>=0)return x.ColumnName;return "";}
    bool Any(string s,params string[] q){return q.Any(x=>s.IndexOf(x,StringComparison.OrdinalIgnoreCase)>=0);}
    void ImportTable(SqlConnection c,string schema,string table,List<Rec> all,ref int tables,ref int rows){
      string t=table.ToLowerInvariant(),type=null;if(Any(t,"kala","item","product","goods","کالا"))type="product";else if(Any(t,"customer","moshtari","person","party","client","مشتری"))type="customer";else if(Any(t,"factor","invoice","sale","forosh","فاکتور","فروش"))type="invoice";else if(Any(t,"anbar","stock","inventory","warehouse","انبار"))type="stock";else if(Any(t,"hesab","account","ledger","حساب"))type="account";if(type==null)return;
      var dt=new DataTable();try{new SqlDataAdapter("SELECT TOP 50000 * FROM ["+schema.Replace("]","]] ")+"] .["+table.Replace("]","]] ")+"]",c).Fill(dt);}catch{return;} if(dt.Rows.Count==0)return;
      string barcode=Pick(dt.Columns,"barcode","bar_code","ean","gtin","upc","بارکد"),code=Pick(dt.Columns,"u_code1","kalacode","itemcode","code","id","کد"),name=Pick(dt.Columns,"kalaname","itemname","productname","name","title","نام"),phone=Pick(dt.Columns,"mobile","phone","tel","موبایل","تلفن"),no=Pick(dt.Columns,"factorno","invoiceno","number","no","شماره"),date=Pick(dt.Columns,"date","tarikh","تاریخ"),total=Pick(dt.Columns,"total","mablagh","amount","جمع","مبلغ");int n=0;
      foreach(DataRow r in dt.Rows){var sb=new StringBuilder("{");for(int i=0;i<dt.Columns.Count;i++){if(i>0)sb.Append(',');sb.Append('"').Append(J(dt.Columns[i].ColumnName)).Append("\":\"").Append(J(r[i])).Append('"');}sb.Append('}');string key;if(type=="product")key=!string.IsNullOrEmpty(barcode)?"barcode:"+Norm(r[barcode]):!string.IsNullOrEmpty(code)?"code:"+Norm(r[code]):"name:"+Norm(string.IsNullOrEmpty(name)?"":r[name]);else if(type=="customer")key=!string.IsNullOrEmpty(phone)?"phone:"+Norm(r[phone]):!string.IsNullOrEmpty(code)?"code:"+Norm(r[code]):"name:"+Norm(string.IsNullOrEmpty(name)?"":r[name]);else if(type=="invoice")key="invoice:"+Norm(string.IsNullOrEmpty(no)?"":r[no])+":"+Norm(string.IsNullOrEmpty(date)?"":r[date])+":"+Norm(string.IsNullOrEmpty(total)?"":r[total]);else key=!string.IsNullOrEmpty(code)?"code:"+Norm(r[code]):"row:"+Norm(table)+":"+n;if(key.Length<5)key="row:"+Norm(table)+":"+n;string payload=sb.ToString();all.Add(new Rec{T=type,K=key,U=DateTimeOffset.UtcNow.ToUnixTimeSeconds(),H=Hash(payload),P=B64(payload)});n++;}if(n>0){tables++;rows+=n;}
    }
    IEnumerable<string> Servers(){var s=new HashSet<string>(StringComparer.OrdinalIgnoreCase){"localhost",@".\SQLEXPRESS"};foreach(var path in new[]{@"SOFTWARE\Microsoft\Microsoft SQL Server\Instance Names\SQL",@"SOFTWARE\WOW6432Node\Microsoft\Microsoft SQL Server\Instance Names\SQL"})try{using(var k=Registry.LocalMachine.OpenSubKey(path))if(k!=null)foreach(var n in k.GetValueNames())s.Add(n.Equals("MSSQLSERVER",StringComparison.OrdinalIgnoreCase)?"localhost":@".\"+n);}catch{}return s;}
    void Migrate(){Cursor=Cursors.WaitCursor;int dbs=0,tables=0,rows=0;var all=new List<Rec>();foreach(var server in Servers())try{using(var master=new SqlConnection("Data Source="+server+";Initial Catalog=master;Integrated Security=True;Connect Timeout=2;TrustServerCertificate=True")){master.Open();var names=new List<string>();using(var q=new SqlCommand("SELECT name FROM sys.databases WHERE database_id>4 AND state=0",master))using(var rd=q.ExecuteReader())while(rd.Read())names.Add(rd.GetString(0));foreach(var db in names)try{using(var c=new SqlConnection("Data Source="+server+";Initial Catalog="+db+";Integrated Security=True;Connect Timeout=3;TrustServerCertificate=True")){c.Open();dbs++;using(var q=new SqlCommand("SELECT TABLE_SCHEMA,TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'",c))using(var rd=q.ExecuteReader()){var ts=new List<string[]>();while(rd.Read())ts.Add(new[]{rd.GetString(0),rd.GetString(1)});rd.Close();foreach(var z in ts)ImportTable(c,z[0],z[1],all,ref tables,ref rows);}}}catch{}}}catch{}Merge(all);Cursor=Cursors.Default;MessageBox.Show(dbs==0?"دیتابیس قابل دسترس با ورود ویندوز پیدا نشد. هیچ نام کاربری یا رمزی درخواست نمی‌شود.":"مهاجرت انجام شد.\nدیتابیس‌ها: "+dbs+"\nجدول‌های شناسایی‌شده: "+tables+"\nرکوردهای مچ/واردشده: "+rows+"\n\nموارد تکراری ادغام می‌شوند.","مهاجرت از نرم‌افزارهای دیگر");}
    void SiteDialog(){var s=Settings();var f=new Form{Text="اتصال به سایت",Width=600,Height=430,StartPosition=FormStartPosition.CenterParent,RightToLeft=RightToLeft.Yes,RightToLeftLayout=true};var u=new TextBox{Left=40,Top=90,Width=500,Text=s.ContainsKey("url")?s["url"]:""};var k=new TextBox{Left=40,Top=175,Width=500,UseSystemPasswordChar=true,Text=s.ContainsKey("key")?s["key"]:""};var st=new TextBox{Left=40,Top=260,Width=500,Text=s.ContainsKey("store")?s["store"]:"default"};f.Controls.AddRange(new Control[]{new Label{Text="آدرس سایت",Left=390,Top=60,Width=150,TextAlign=ContentAlignment.MiddleRight},u,new Label{Text="کد اتصال",Left=390,Top=145,Width=150,TextAlign=ContentAlignment.MiddleRight},k,new Label{Text="شناسه فروشگاه",Left=360,Top=230,Width=180,TextAlign=ContentAlignment.MiddleRight},st});var b=new Button{Text="ذخیره و همگام‌سازی",Left=310,Top=320,Width=230,Height=45};b.Click+=(x,y)=>{SaveSettings(u.Text.Trim(),k.Text.Trim(),st.Text.Trim());Sync(true);};f.Controls.Add(b);f.ShowDialog(this);}
    string Endpoint(string u){u=(u??"").Trim().TrimEnd('/');return u.EndsWith(".php",StringComparison.OrdinalIgnoreCase)?u:u+"/websaz-sync.php";}
    string Body(){var a=Load();var s=Settings();var sb=new StringBuilder("WEBSAZSYNC1\n").Append(B64(Device())).Append('\n').Append(B64(s.ContainsKey("store")?s["store"]:"default")).Append('\n').Append(a.Count).Append('\n');foreach(var r in a)sb.Append(B64(r.T)).Append('\t').Append(B64(r.K)).Append('\t').Append(r.U).Append('\t').Append(B64(r.H)).Append('\t').Append(r.P).Append('\n');return sb.ToString();}
    List<Rec> Parse(string x){var a=new List<Rec>();var l=x.Replace("\r","").Split('\n');if(l.Length<4||l[0]!="WEBSAZSYNC1")return a;for(int i=4;i<l.Length;i++){var p=l[i].Split(new[]{'\t'},5);if(p.Length<5)continue;long u;long.TryParse(p[2],out u);a.Add(new Rec{T=UB64(p[0]),K=UB64(p[1]),U=u,H=UB64(p[3]),P=p[4]});}return a;}
    void Sync(bool show){if(syncing)return;syncing=true;try{var s=Settings();if(!s.ContainsKey("url")||!s.ContainsKey("key")||s["url"]==""||s["key"]==""){if(show)MessageBox.Show("ابتدا آدرس سایت و کد اتصال را وارد کنید.");return;}var req=(HttpWebRequest)WebRequest.Create(Endpoint(s["url"]));req.Method="POST";req.ContentType="application/octet-stream";req.Headers[HttpRequestHeader.Authorization]="Bearer "+s["key"];req.Timeout=30000;var b=Encoding.UTF8.GetBytes(Body());req.ContentLength=b.Length;using(var q=req.GetRequestStream())q.Write(b,0,b.Length);using(var resp=(HttpWebResponse)req.GetResponse())using(var rd=new StreamReader(resp.GetResponseStream()))Merge(Parse(rd.ReadToEnd()));File.WriteAllText(Path.Combine(root,"last_sync.txt"),DateTime.Now.ToString("s"));if(show)MessageBox.Show("اطلاعات همه سیستم‌ها با سایت مچ شد.","وب ساز");}catch(Exception ex){if(show)MessageBox.Show("اتصال سایت انجام نشد.\n"+ex.Message,"وب ساز");}finally{syncing=false;}}
  }
  static class Program{[STAThread]static void Main(){ServicePointManager.SecurityProtocol=SecurityProtocolType.Tls12;Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);Application.Run(new MainForm());}}
}
