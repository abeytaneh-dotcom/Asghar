using System;
using System.IO;
using System.Text;
using System.Linq;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Windows.Forms;
using System.Collections.Generic;
using System.Data;
using System.Data.SqlClient;
using System.Net;
using Microsoft.Win32;
using System.Security.Cryptography;
using System.Threading;
using System.Text.RegularExpressions;

namespace WebsazStore
{
  class Rec { public string T="", K="", H="", P=""; public long U; }
  class Product
  {
    public string Key="", Name="", Barcode="";
    public long SalePrice, BuyPrice;
    public decimal Stock;
    public override string ToString() => Name;
  }
  class Customer { public string Key="", Name="", Mobile=""; public long Balance; }
  class SaleLine { public Product Product; public decimal Qty=1; public long Total => Product==null?0:(long)Math.Round(Product.SalePrice*Qty); }
  class UiHotspot { public Rectangle R; public Action Click; public string Name; public UiHotspot(string n, Rectangle r, Action a){Name=n;R=r;Click=a;} }

  static class Fmt
  {
    public static string Money(long n) => n.ToString("N0")+" تومان";
    public static long Num(string s)
    {
      var b=new StringBuilder();
      foreach(char c in s??""){
        if(c>='0'&&c<='9') b.Append(c);
        else if(c>='۰'&&c<='۹') b.Append((char)('0'+c-'۰'));
        else if(c>='٠'&&c<='٩') b.Append((char)('0'+c-'٠'));
        else if(c=='-') b.Append(c);
      }
      long x; return long.TryParse(b.ToString(), out x)?x:0;
    }
    public static decimal Dec(string s)
    {
      s=(s??"").Replace('٫','.').Replace('،','.');
      var b=new StringBuilder();
      foreach(char c in s){
        if((c>='0'&&c<='9')||c=='.'||c=='-') b.Append(c);
        else if(c>='۰'&&c<='۹') b.Append((char)('0'+c-'۰'));
        else if(c>='٠'&&c<='٩') b.Append((char)('0'+c-'٠'));
      }
      decimal x; return decimal.TryParse(b.ToString(), System.Globalization.NumberStyles.Any,
        System.Globalization.CultureInfo.InvariantCulture, out x)?x:0;
    }
    public static string J(string s)=>(s??"").Replace("\\","\\\\").Replace("\"","\\\"").Replace("\r"," ").Replace("\n"," ");
    public static string UnJ(string s)=>(s??"").Replace("\\\"","\"").Replace("\\\\","\\");
    public static string Field(string json,string name)
    {
      var m=Regex.Match(json??"", "\""+Regex.Escape(name)+"\"\\s*:\\s*\"((?:\\\\.|[^\"])*)\"", RegexOptions.IgnoreCase);
      return m.Success?UnJ(m.Groups[1].Value):"";
    }
  }

  class BusyForm:Form
  {
    public BusyForm(string text)
    {
      Text="وب ساز"; FormBorderStyle=FormBorderStyle.None; StartPosition=FormStartPosition.CenterParent;
      Width=450; Height=165; BackColor=Color.White; TopMost=true; ShowInTaskbar=false;
      var p=new Panel{Dock=DockStyle.Fill,Padding=new Padding(24),BackColor=Color.White};
      var l=new Label{Dock=DockStyle.Top,Height=70,Text=text,TextAlign=ContentAlignment.MiddleCenter,
        Font=new Font("Tahoma",11,FontStyle.Bold),ForeColor=Color.FromArgb(28,61,110),RightToLeft=RightToLeft.Yes};
      var b=new ProgressBar{Dock=DockStyle.Bottom,Height=22,Style=ProgressBarStyle.Marquee,MarqueeAnimationSpeed=25};
      p.Controls.Add(l); p.Controls.Add(b); Controls.Add(p);
    }
  }

  class CustomerDisplayForm:Form
  {
    readonly DataGridView grid=new DataGridView();
    readonly Label total=new Label(),discount=new Label(),payable=new Label(),lastItem=new Label(),state=new Label();
    public CustomerDisplayForm(Screen screen)
    {
      Text="نمایشگر مشتری"; FormBorderStyle=FormBorderStyle.None; StartPosition=FormStartPosition.Manual; Bounds=screen.Bounds;
      BackColor=Color.FromArgb(244,248,255); ShowInTaskbar=false; TopMost=true; RightToLeft=RightToLeft.Yes; RightToLeftLayout=true;
      var head=new Panel{Dock=DockStyle.Top,Height=128,BackColor=Color.White,Padding=new Padding(30,18,30,12)};
      var brand=new Label{Text="سوپرمارکت طهران",Dock=DockStyle.Top,Height=45,Font=new Font("Tahoma",23,FontStyle.Bold),
        ForeColor=Color.FromArgb(20,86,190),TextAlign=ContentAlignment.MiddleCenter};
      state.Text="آماده ثبت خرید شما"; state.Dock=DockStyle.Fill; state.Font=new Font("Tahoma",13);
      state.ForeColor=Color.FromArgb(70,80,98); state.TextAlign=ContentAlignment.MiddleCenter;
      head.Controls.Add(state); head.Controls.Add(brand);
      lastItem.Dock=DockStyle.Top; lastItem.Height=70; lastItem.BackColor=Color.FromArgb(231,247,239);
      lastItem.ForeColor=Color.FromArgb(21,121,75); lastItem.Font=new Font("Tahoma",14,FontStyle.Bold);
      lastItem.TextAlign=ContentAlignment.MiddleCenter; lastItem.Text="سبد خرید خالی است";
      grid.Dock=DockStyle.Fill; grid.ReadOnly=true; grid.AllowUserToAddRows=false; grid.AllowUserToDeleteRows=false;
      grid.AllowUserToResizeRows=false; grid.RowHeadersVisible=false; grid.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;
      grid.BackgroundColor=Color.White; grid.BorderStyle=BorderStyle.None; grid.DefaultCellStyle.Font=new Font("Tahoma",12);
      grid.ColumnHeadersDefaultCellStyle.Font=new Font("Tahoma",11,FontStyle.Bold); grid.ColumnHeadersHeight=48; grid.RowTemplate.Height=48;
      grid.Columns.Add("name","کالا"); grid.Columns.Add("qty","تعداد"); grid.Columns.Add("price","قیمت واحد"); grid.Columns.Add("sum","جمع");
      var foot=new TableLayoutPanel{Dock=DockStyle.Bottom,Height=160,ColumnCount=3,RowCount=1,
        BackColor=Color.FromArgb(16,54,109),Padding=new Padding(20)};
      foot.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,33.33f)); foot.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,33.33f));
      foot.ColumnStyles.Add(new ColumnStyle(SizeType.Percent,33.34f));
      Setup(total); Setup(discount); Setup(payable); foot.Controls.Add(total,2,0); foot.Controls.Add(discount,1,0); foot.Controls.Add(payable,0,0);
      Controls.Add(grid); Controls.Add(lastItem); Controls.Add(head); Controls.Add(foot);
      UpdateSale(new List<SaleLine>(),0);
    }
    void Setup(Label l){l.Dock=DockStyle.Fill;l.TextAlign=ContentAlignment.MiddleCenter;l.Font=new Font("Tahoma",15,FontStyle.Bold);l.ForeColor=Color.White;}
    public void UpdateSale(IList<SaleLine> lines,long disc)
    {
      if(IsDisposed)return;
      if(InvokeRequired){BeginInvoke(new Action(()=>UpdateSale(lines,disc)));return;}
      grid.Rows.Clear(); long sub=0;
      foreach(var x in lines){sub+=x.Total;grid.Rows.Add(x.Product.Name,x.Qty.ToString("0.###"),Fmt.Money(x.Product.SalePrice),Fmt.Money(x.Total));}
      long pay=Math.Max(0,sub-disc);
      total.Text="جمع کالاها\n"+Fmt.Money(sub); discount.Text="تخفیف\n"+Fmt.Money(disc); payable.Text="قابل پرداخت\n"+Fmt.Money(pay);
      if(lines.Count>0){var x=lines[lines.Count-1];lastItem.Text="آخرین کالا: "+x.Product.Name+" | "+Fmt.Money(x.Product.SalePrice);state.Text="اقلام خرید شما";}
      else{lastItem.Text="سبد خرید خالی است";state.Text="آماده ثبت خرید شما";}
    }
  }

  class ProductDialog:Form
  {
    readonly TextBox name=new TextBox(),barcode=new TextBox(),sale=new TextBox(),buy=new TextBox(),stock=new TextBox();
    public Product Result;
    public ProductDialog(Product p=null)
    {
      Text=p==null?"ثبت کالا":"ویرایش کالا"; Width=540; Height=480; StartPosition=FormStartPosition.CenterParent;
      RightToLeft=RightToLeft.Yes; RightToLeftLayout=true; FormBorderStyle=FormBorderStyle.FixedDialog; MaximizeBox=false; MinimizeBox=false;
      AddField("نام کالا",name,25); AddField("بارکد / کد",barcode,95); AddField("قیمت فروش",sale,165);
      AddField("قیمت خرید",buy,235); AddField("موجودی",stock,305);
      if(p!=null){name.Text=p.Name;barcode.Text=p.Barcode;sale.Text=p.SalePrice.ToString();buy.Text=p.BuyPrice.ToString();stock.Text=p.Stock.ToString("0.###");}
      var ok=new Button{Text="ذخیره",Left=310,Top=385,Width=180,Height=42,BackColor=Color.FromArgb(22,105,220),ForeColor=Color.White,FlatStyle=FlatStyle.Flat};
      ok.FlatAppearance.BorderSize=0; ok.Click+=(s,e)=>{
        if(string.IsNullOrWhiteSpace(name.Text)){MessageBox.Show(this,"نام کالا را وارد کنید.");return;}
        Result=new Product{Name=name.Text.Trim(),Barcode=barcode.Text.Trim(),SalePrice=Fmt.Num(sale.Text),BuyPrice=Fmt.Num(buy.Text),Stock=Fmt.Dec(stock.Text)};
        DialogResult=DialogResult.OK;Close();
      }; Controls.Add(ok);
    }
    void AddField(string label,TextBox box,int top)
    {
      Controls.Add(new Label{Text=label,Left=350,Top=top,Width=140,Height=25,TextAlign=ContentAlignment.MiddleRight});
      box.Left=40;box.Top=top+27;box.Width=450;box.Height=30;Controls.Add(box);
    }
  }

  class ProductsForm:Form
  {
    readonly MainForm owner; readonly DataGridView grid=new DataGridView();
    public ProductsForm(MainForm o)
    {
      owner=o; Text="کالاها - وب ساز"; Width=1100; Height=720; StartPosition=FormStartPosition.CenterParent;
      RightToLeft=RightToLeft.Yes; RightToLeftLayout=true;
      var bar=new FlowLayoutPanel{Dock=DockStyle.Top,Height=68,Padding=new Padding(12),FlowDirection=FlowDirection.RightToLeft};
      bar.Controls.Add(Btn("کالای جدید",()=>Add()));
      bar.Controls.Add(Btn("ویرایش",()=>Edit()));
      bar.Controls.Add(Btn("حذف",()=>Delete(),true));
      bar.Controls.Add(Btn("بروزرسانی",()=>Reload()));
      grid.Dock=DockStyle.Fill; grid.ReadOnly=true; grid.AllowUserToAddRows=false; grid.RowHeadersVisible=false;
      grid.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill; grid.SelectionMode=DataGridViewSelectionMode.FullRowSelect;
      grid.MultiSelect=false; grid.Columns.Add("name","نام کالا");grid.Columns.Add("barcode","بارکد");grid.Columns.Add("sale","فروش");
      grid.Columns.Add("buy","خرید");grid.Columns.Add("stock","موجودی");
      Controls.Add(grid);Controls.Add(bar);Shown+=(s,e)=>Reload();grid.CellDoubleClick+=(s,e)=>Edit();
    }
    Button Btn(string t,Action a,bool danger=false){var b=new Button{Text=t,Width=130,Height=38,BackColor=danger?Color.MistyRose:Color.FromArgb(232,241,255),ForeColor=danger?Color.Firebrick:Color.FromArgb(20,86,190),FlatStyle=FlatStyle.Flat};b.FlatAppearance.BorderSize=0;b.Click+=(s,e)=>a();return b;}
    Product Selected(){if(grid.SelectedRows.Count==0)return null;return grid.SelectedRows[0].Tag as Product;}
    void Reload(){grid.Rows.Clear();foreach(var p in owner.LoadProducts().OrderBy(x=>x.Name)){int i=grid.Rows.Add(p.Name,p.Barcode,Fmt.Money(p.SalePrice),Fmt.Money(p.BuyPrice),p.Stock.ToString("0.###"));grid.Rows[i].Tag=p;}}
    void Add(){using(var d=new ProductDialog()){if(d.ShowDialog(this)==DialogResult.OK){owner.SaveProduct(d.Result);Reload();}}}
    void Edit(){var p=Selected();if(p==null)return;using(var d=new ProductDialog(p)){if(d.ShowDialog(this)==DialogResult.OK){d.Result.Key=p.Key;owner.SaveProduct(d.Result);Reload();}}}
    void Delete(){var p=Selected();if(p==null)return;if(MessageBox.Show(this,"این کالا حذف شود؟","وب ساز",MessageBoxButtons.YesNo)!=DialogResult.Yes)return;owner.DeleteEntity("product",p.Key);Reload();}
  }

  class InventoryForm:Form
  {
    readonly MainForm owner; readonly DataGridView grid=new DataGridView();
    public InventoryForm(MainForm o)
    {
      owner=o;Text="انبار و موجودی";Width=1050;Height=700;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      var bar=new FlowLayoutPanel{Dock=DockStyle.Top,Height=68,Padding=new Padding(12),FlowDirection=FlowDirection.RightToLeft};
      var adj=new Button{Text="اصلاح موجودی",Width=150,Height=38,BackColor=Color.FromArgb(232,241,255),FlatStyle=FlatStyle.Flat};adj.Click+=(s,e)=>Adjust();bar.Controls.Add(adj);
      grid.Dock=DockStyle.Fill;grid.ReadOnly=true;grid.AllowUserToAddRows=false;grid.RowHeadersVisible=false;grid.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;
      grid.SelectionMode=DataGridViewSelectionMode.FullRowSelect;grid.MultiSelect=false;grid.Columns.Add("name","کالا");grid.Columns.Add("barcode","بارکد");grid.Columns.Add("stock","موجودی");grid.Columns.Add("value","ارزش خرید");
      Controls.Add(grid);Controls.Add(bar);Shown+=(s,e)=>Reload();
    }
    void Reload(){grid.Rows.Clear();foreach(var p in owner.LoadProducts().OrderBy(x=>x.Name)){int i=grid.Rows.Add(p.Name,p.Barcode,p.Stock.ToString("0.###"),Fmt.Money((long)Math.Round(p.Stock*p.BuyPrice)));grid.Rows[i].Tag=p;}}
    void Adjust(){if(grid.SelectedRows.Count==0)return;var p=grid.SelectedRows[0].Tag as Product;if(p==null)return;using(var f=new Form{Text="اصلاح موجودی",Width=420,Height=220,StartPosition=FormStartPosition.CenterParent,RightToLeft=RightToLeft.Yes,RightToLeftLayout=true}){var t=new TextBox{Left=35,Top=70,Width=330,Text=p.Stock.ToString("0.###")};f.Controls.Add(new Label{Text="موجودی واقعی",Left=240,Top=35,Width=125});f.Controls.Add(t);var b=new Button{Text="ذخیره",Left=215,Top=120,Width=150,Height=38};b.Click+=(s,e)=>{p.Stock=Fmt.Dec(t.Text);owner.SaveProduct(p);f.DialogResult=DialogResult.OK;f.Close();};f.Controls.Add(b);if(f.ShowDialog(this)==DialogResult.OK)Reload();}}
  }

  class PurchaseForm:Form
  {
    readonly MainForm owner; readonly ComboBox products=new ComboBox(); readonly TextBox qty=new TextBox(),price=new TextBox(),supplier=new TextBox();
    public PurchaseForm(MainForm o)
    {
      owner=o;Text="خرید و ورود کالا";Width=600;Height=430;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      AddLabel("کالا",40);products.Left=40;products.Top=72;products.Width=500;products.DropDownStyle=ComboBoxStyle.DropDownList;Controls.Add(products);
      AddLabel("تعداد",120);qty.Left=40;qty.Top=152;qty.Width=500;qty.Text="1";Controls.Add(qty);
      AddLabel("قیمت خرید واحد",200);price.Left=40;price.Top=232;price.Width=500;Controls.Add(price);
      AddLabel("تأمین‌کننده / توضیح",280);supplier.Left=40;supplier.Top=312;supplier.Width=500;Controls.Add(supplier);
      var b=new Button{Text="ثبت ورود به انبار",Left=330,Top=355,Width=210,Height=40,BackColor=Color.FromArgb(22,105,220),ForeColor=Color.White,FlatStyle=FlatStyle.Flat};b.FlatAppearance.BorderSize=0;
      b.Click+=(s,e)=>Save();Controls.Add(b);Shown+=(s,e)=>{products.Items.Clear();foreach(var p in owner.LoadProducts().OrderBy(x=>x.Name))products.Items.Add(p);if(products.Items.Count>0)products.SelectedIndex=0;};
      products.SelectedIndexChanged+=(s,e)=>{var p=products.SelectedItem as Product;if(p!=null&&p.BuyPrice>0)price.Text=p.BuyPrice.ToString();};
    }
    void AddLabel(string t,int y){Controls.Add(new Label{Text=t,Left=360,Top=y,Width=180,Height=25,TextAlign=ContentAlignment.MiddleRight});}
    void Save(){var p=products.SelectedItem as Product;if(p==null){MessageBox.Show(this,"ابتدا یک کالا تعریف کنید.");return;}decimal q=Fmt.Dec(qty.Text);long pr=Fmt.Num(price.Text);if(q<=0){MessageBox.Show(this,"تعداد معتبر نیست.");return;}owner.CommitPurchase(p,q,pr,supplier.Text.Trim());MessageBox.Show(this,"ورود کالا ثبت شد.");DialogResult=DialogResult.OK;Close();}
  }

  class FinanceDialog:Form
  {
    readonly ComboBox type=new ComboBox(),account=new ComboBox(); readonly TextBox amount=new TextBox(),desc=new TextBox(); public string Json;
    public FinanceDialog()
    {
      Text="ثبت دریافت / پرداخت";Width=520;Height=430;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      type.Items.AddRange(new object[]{"درآمد","هزینه"});type.SelectedIndex=0;account.Items.AddRange(new object[]{"نقد","کارت","بانک","سایر"});account.SelectedIndex=0;
      Add("نوع",type,35);Add("حساب",account,105);Add("مبلغ",amount,175);Add("شرح",desc,245);
      var b=new Button{Text="ثبت",Left=300,Top=330,Width=170,Height=42};b.Click+=(s,e)=>{long a=Fmt.Num(amount.Text);if(a<=0){MessageBox.Show(this,"مبلغ معتبر نیست.");return;}Json="{\"kind\":\""+Fmt.J(type.Text)+"\",\"account\":\""+Fmt.J(account.Text)+"\",\"amount\":\""+a+"\",\"desc\":\""+Fmt.J(desc.Text)+"\",\"date\":\""+DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")+"\"}";DialogResult=DialogResult.OK;Close();};Controls.Add(b);
    }
    void Add(string l,Control c,int y){Controls.Add(new Label{Text=l,Left=350,Top=y,Width=120,Height=24,TextAlign=ContentAlignment.MiddleRight});c.Left=40;c.Top=y+25;c.Width=430;Controls.Add(c);}
  }

  class CustomerDialog:Form
  {
    readonly TextBox name=new TextBox(),mobile=new TextBox(),balance=new TextBox(); public Customer Result;
    public CustomerDialog(Customer c=null)
    {
      Text=c==null?"مشتری جدید":"ویرایش مشتری";Width=500;Height=360;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      Add("نام",name,35);Add("موبایل",mobile,115);Add("مانده حساب",balance,195);
      if(c!=null){name.Text=c.Name;mobile.Text=c.Mobile;balance.Text=c.Balance.ToString();}
      var b=new Button{Text="ذخیره",Left=285,Top=270,Width=170,Height=40};b.Click+=(s,e)=>{if(string.IsNullOrWhiteSpace(name.Text)){MessageBox.Show(this,"نام را وارد کنید.");return;}Result=new Customer{Name=name.Text.Trim(),Mobile=mobile.Text.Trim(),Balance=Fmt.Num(balance.Text)};DialogResult=DialogResult.OK;Close();};Controls.Add(b);
    }
    void Add(string l,TextBox t,int y){Controls.Add(new Label{Text=l,Left=335,Top=y,Width=120,Height=24,TextAlign=ContentAlignment.MiddleRight});t.Left=40;t.Top=y+25;t.Width=415;Controls.Add(t);}
  }

  class AccountsHubForm:Form
  {
    readonly MainForm owner; readonly TabControl tabs=new TabControl(); readonly DataGridView finance=new DataGridView(), customers=new DataGridView();
    public AccountsHubForm(MainForm o)
    {
      owner=o;Text="حسابداری ساده و مشتریان";Width=1100;Height=720;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      tabs.Dock=DockStyle.Fill;
      var t1=new TabPage("دریافت و پرداخت");var t2=new TabPage("مشتریان");
      finance.Dock=DockStyle.Fill;finance.ReadOnly=true;finance.AllowUserToAddRows=false;finance.RowHeadersVisible=false;finance.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;
      finance.Columns.Add("date","تاریخ");finance.Columns.Add("kind","نوع");finance.Columns.Add("account","حساب");finance.Columns.Add("amount","مبلغ");finance.Columns.Add("desc","شرح");
      var b1=new Button{Text="ثبت دریافت / پرداخت",Dock=DockStyle.Top,Height=46};b1.Click+=(s,e)=>{using(var d=new FinanceDialog()){if(d.ShowDialog(this)==DialogResult.OK){owner.SaveEntity("finance","tx:"+Guid.NewGuid().ToString("N"),d.Json);ReloadFinance();}}};
      t1.Controls.Add(finance);t1.Controls.Add(b1);
      customers.Dock=DockStyle.Fill;customers.ReadOnly=true;customers.AllowUserToAddRows=false;customers.RowHeadersVisible=false;customers.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;customers.SelectionMode=DataGridViewSelectionMode.FullRowSelect;customers.MultiSelect=false;
      customers.Columns.Add("name","نام");customers.Columns.Add("mobile","موبایل");customers.Columns.Add("balance","مانده");
      var pnl=new FlowLayoutPanel{Dock=DockStyle.Top,Height=52,FlowDirection=FlowDirection.RightToLeft};
      var add=new Button{Text="مشتری جدید",Width=120,Height=38};add.Click+=(s,e)=>AddCustomer();var edit=new Button{Text="ویرایش",Width=100,Height=38};edit.Click+=(s,e)=>EditCustomer();pnl.Controls.Add(add);pnl.Controls.Add(edit);
      t2.Controls.Add(customers);t2.Controls.Add(pnl);
      tabs.TabPages.Add(t1);tabs.TabPages.Add(t2);Controls.Add(tabs);Shown+=(s,e)=>{ReloadFinance();ReloadCustomers();};
    }
    void ReloadFinance(){finance.Rows.Clear();foreach(var r in owner.LoadType("finance").OrderByDescending(x=>x.U)){string j=owner.Payload(r);finance.Rows.Add(Fmt.Field(j,"date"),Fmt.Field(j,"kind"),Fmt.Field(j,"account"),Fmt.Money(Fmt.Num(Fmt.Field(j,"amount"))),Fmt.Field(j,"desc"));}}
    void ReloadCustomers(){customers.Rows.Clear();foreach(var c in owner.LoadCustomers().OrderBy(x=>x.Name)){int i=customers.Rows.Add(c.Name,c.Mobile,Fmt.Money(c.Balance));customers.Rows[i].Tag=c;}}
    Customer Sel(){return customers.SelectedRows.Count==0?null:customers.SelectedRows[0].Tag as Customer;}
    void AddCustomer(){using(var d=new CustomerDialog()){if(d.ShowDialog(this)==DialogResult.OK){owner.SaveCustomer(d.Result);ReloadCustomers();}}}
    void EditCustomer(){var c=Sel();if(c==null)return;using(var d=new CustomerDialog(c)){if(d.ShowDialog(this)==DialogResult.OK){d.Result.Key=c.Key;owner.SaveCustomer(d.Result);ReloadCustomers();}}}
  }

  class ReportsForm:Form
  {
    readonly MainForm owner; readonly Label summary=new Label(); readonly DataGridView sales=new DataGridView();
    public ReportsForm(MainForm o)
    {
      owner=o;Text="گزارش‌ها";Width=1150;Height=750;StartPosition=FormStartPosition.CenterParent;RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;
      summary.Dock=DockStyle.Top;summary.Height=120;summary.BackColor=Color.FromArgb(235,245,255);summary.Font=new Font("Tahoma",12,FontStyle.Bold);summary.TextAlign=ContentAlignment.MiddleCenter;
      sales.Dock=DockStyle.Fill;sales.ReadOnly=true;sales.AllowUserToAddRows=false;sales.RowHeadersVisible=false;sales.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;
      sales.Columns.Add("date","تاریخ");sales.Columns.Add("count","تعداد قلم");sales.Columns.Add("subtotal","جمع");sales.Columns.Add("discount","تخفیف");sales.Columns.Add("payable","پرداختی");sales.Columns.Add("pay","روش پرداخت");
      Controls.Add(sales);Controls.Add(summary);Shown+=(s,e)=>Reload();
    }
    void Reload()
    {
      sales.Rows.Clear();long saleTotal=0,purchaseTotal=0,income=0,expense=0;int saleCount=0;
      foreach(var r in owner.LoadType("sale").OrderByDescending(x=>x.U)){var j=owner.Payload(r);long pay=Fmt.Num(Fmt.Field(j,"payable"));saleTotal+=pay;saleCount++;sales.Rows.Add(Fmt.Field(j,"date"),Fmt.Field(j,"items"),Fmt.Money(Fmt.Num(Fmt.Field(j,"subtotal"))),Fmt.Money(Fmt.Num(Fmt.Field(j,"discount"))),Fmt.Money(pay),Fmt.Field(j,"payment"));}
      foreach(var r in owner.LoadType("purchase"))purchaseTotal+=Fmt.Num(Fmt.Field(owner.Payload(r),"total"));
      foreach(var r in owner.LoadType("finance")){var j=owner.Payload(r);long a=Fmt.Num(Fmt.Field(j,"amount"));if(Fmt.Field(j,"kind")=="درآمد")income+=a;else expense+=a;}
      var products=owner.LoadProducts();decimal stockQty=products.Sum(x=>x.Stock);long stockValue=(long)Math.Round(products.Sum(x=>x.Stock*x.BuyPrice));
      summary.Text="فروش ثبت‌شده: "+saleCount.ToString("N0")+" فاکتور  |  مبلغ فروش: "+Fmt.Money(saleTotal)+"\n"+
        "خرید کالا: "+Fmt.Money(purchaseTotal)+"  |  درآمد متفرقه: "+Fmt.Money(income)+"  |  هزینه: "+Fmt.Money(expense)+"\n"+
        "کالاها: "+products.Count.ToString("N0")+"  |  موجودی کل: "+stockQty.ToString("0.###")+"  |  ارزش موجودی: "+Fmt.Money(stockValue);
    }
  }

  class SaleForm:Form
  {
    readonly MainForm owner; readonly List<Product> catalog; readonly CustomerDisplayForm customer;
    readonly List<SaleLine> lines=new List<SaleLine>(); readonly DataGridView grid=new DataGridView(); readonly TextBox scan=new TextBox();
    readonly Label subtotal=new Label(),discountLabel=new Label(),payable=new Label(),screenState=new Label(); long discount;
    public SaleForm(MainForm o,List<Product> products,CustomerDisplayForm c)
    {
      owner=o;catalog=products??new List<Product>();customer=c;Text="فروش جدید - وب ساز";WindowState=FormWindowState.Maximized;
      BackColor=Color.FromArgb(242,247,255);RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;KeyPreview=true;
      var head=new Panel{Dock=DockStyle.Top,Height=110,BackColor=Color.White,Padding=new Padding(22)};
      var title=new Label{Text="فروش جدید",Dock=DockStyle.Right,Width=180,Font=new Font("Tahoma",18,FontStyle.Bold),TextAlign=ContentAlignment.MiddleCenter,ForeColor=Color.FromArgb(20,64,126)};
      scan.Width=380;scan.Height=38;scan.Font=new Font("Tahoma",14);scan.Tag="بارکد";scan.KeyDown+=(s,e)=>{if(e.KeyCode==Keys.Enter){e.SuppressKeyPress=true;AddByCode(scan.Text.Trim());scan.Clear();}};
      var add=Button("ثبت دستی کالا",()=>AddManual(""));add.Left=22;add.Top=32;add.Width=145;
      screenState.Text=customer==null?"نمایشگر مشتری پیدا نشد":"نمایشگر مشتری متصل";screenState.AutoSize=true;screenState.Left=180;screenState.Top=43;screenState.ForeColor=customer==null?Color.DarkOrange:Color.SeaGreen;
      head.Controls.Add(add);head.Controls.Add(screenState);head.Controls.Add(scan);head.Controls.Add(title);head.Resize+=(s,e)=>{scan.Left=Math.Max(360,(head.Width-scan.Width)/2);scan.Top=34;};
      grid.Dock=DockStyle.Fill;grid.ReadOnly=true;grid.AllowUserToAddRows=false;grid.RowHeadersVisible=false;grid.AutoSizeColumnsMode=DataGridViewAutoSizeColumnsMode.Fill;grid.BackgroundColor=Color.White;grid.SelectionMode=DataGridViewSelectionMode.FullRowSelect;
      grid.Columns.Add("name","کالا");grid.Columns.Add("barcode","بارکد");grid.Columns.Add("qty","تعداد");grid.Columns.Add("price","فی");grid.Columns.Add("sum","جمع");
      var tools=new FlowLayoutPanel{Dock=DockStyle.Right,Width=190,FlowDirection=FlowDirection.TopDown,Padding=new Padding(14),BackColor=Color.White};
      tools.Controls.Add(Button("حذف ردیف",RemoveSelected,true));tools.Controls.Add(Button("تعداد +",()=>ChangeQty(1)));tools.Controls.Add(Button("تعداد -",()=>ChangeQty(-1)));
      tools.Controls.Add(Button("تخفیف",SetDiscount));tools.Controls.Add(Button("ثبت و تسویه",FinalizeSale,false,true));tools.Controls.Add(Button("فاکتور جدید",NewSale));
      var foot=new Panel{Dock=DockStyle.Bottom,Height=115,BackColor=Color.FromArgb(16,54,109)};SetupTotal(subtotal,"جمع");SetupTotal(discountLabel,"تخفیف");SetupTotal(payable,"قابل پرداخت");foot.Controls.Add(subtotal);foot.Controls.Add(discountLabel);foot.Controls.Add(payable);
      foot.Resize+=(s,e)=>{payable.Left=25;discountLabel.Left=Math.Max(310,foot.Width/3);subtotal.Left=Math.Max(620,foot.Width*2/3-120);};
      Controls.Add(grid);Controls.Add(tools);Controls.Add(foot);Controls.Add(head);Shown+=(s,e)=>{scan.Focus();RefreshSale();};
      FormClosed+=(s,e)=>{if(customer!=null)customer.UpdateSale(new List<SaleLine>(),0);};
    }
    Button Button(string text,Action a,bool danger=false,bool ok=false){var b=new Button{Text=text,Width=150,Height=46,Margin=new Padding(5,8,5,0),BackColor=ok?Color.FromArgb(220,246,231):(danger?Color.MistyRose:Color.FromArgb(232,241,255)),ForeColor=ok?Color.SeaGreen:(danger?Color.Firebrick:Color.FromArgb(20,86,190)),FlatStyle=FlatStyle.Flat};b.FlatAppearance.BorderSize=0;b.Click+=(s,e)=>a();return b;}
    void SetupTotal(Label l,string cap){l.Top=18;l.Width=290;l.Height=78;l.ForeColor=Color.White;l.TextAlign=ContentAlignment.MiddleCenter;l.Font=new Font("Tahoma",13,FontStyle.Bold);l.Tag=cap;}
    void AddByCode(string code){if(string.IsNullOrWhiteSpace(code))return;var p=catalog.FirstOrDefault(x=>string.Equals(x.Barcode,code,StringComparison.OrdinalIgnoreCase));if(p==null){AddManual(code);return;}AddProduct(p);}
    void AddManual(string code){using(var d=new ProductDialog(new Product{Barcode=code,Stock=0})){if(d.ShowDialog(this)==DialogResult.OK&&d.Result!=null){owner.SaveProduct(d.Result);catalog.Add(owner.LoadProducts().FirstOrDefault(x=>x.Barcode==d.Result.Barcode&&x.Name==d.Result.Name)??d.Result);AddProduct(catalog.Last());}}scan.Focus();}
    void AddProduct(Product p){var l=lines.FirstOrDefault(x=>(x.Product.Key!=""&&x.Product.Key==p.Key)||(!string.IsNullOrWhiteSpace(p.Barcode)&&x.Product.Barcode==p.Barcode));if(l!=null)l.Qty++;else lines.Add(new SaleLine{Product=p,Qty=1});RefreshSale();}
    void RemoveSelected(){if(grid.SelectedRows.Count==0)return;int i=grid.SelectedRows[0].Index;if(i>=0&&i<lines.Count)lines.RemoveAt(i);RefreshSale();}
    void ChangeQty(int d){if(grid.SelectedRows.Count==0)return;int i=grid.SelectedRows[0].Index;if(i<0||i>=lines.Count)return;lines[i].Qty+=d;if(lines[i].Qty<=0)lines.RemoveAt(i);RefreshSale();}
    void SetDiscount(){using(var f=new Form{Text="تخفیف فاکتور",Width=430,Height=210,StartPosition=FormStartPosition.CenterParent,RightToLeft=RightToLeft.Yes,RightToLeftLayout=true}){var t=new TextBox{Left=35,Top=65,Width=340,Text=discount.ToString()};f.Controls.Add(new Label{Text="مبلغ تخفیف (تومان)",Left=210,Top=30,Width=165,TextAlign=ContentAlignment.MiddleRight});f.Controls.Add(t);var b=new Button{Text="اعمال",Left=225,Top=115,Width=150,Height=38};b.Click+=(s,e)=>{discount=Math.Max(0,Fmt.Num(t.Text));f.DialogResult=DialogResult.OK;f.Close();};f.Controls.Add(b);if(f.ShowDialog(this)==DialogResult.OK)RefreshSale();}}
    void NewSale(){if(lines.Count>0&&MessageBox.Show(this,"فاکتور فعلی پاک شود؟","وب ساز",MessageBoxButtons.YesNo)!=DialogResult.Yes)return;lines.Clear();discount=0;RefreshSale();}
    void FinalizeSale()
    {
      if(lines.Count==0){MessageBox.Show(this,"فاکتور خالی است.");return;}
      var pay=MessageBox.Show(this,"پرداخت کارت است؟\nYes = کارت / No = نقد","روش پرداخت",MessageBoxButtons.YesNoCancel);
      if(pay==DialogResult.Cancel)return;string method=pay==DialogResult.Yes?"کارت":"نقد";
      owner.CommitSale(lines,discount,method);MessageBox.Show(this,"فروش ثبت شد و موجودی به‌روزرسانی شد.","وب ساز");
      lines.Clear();discount=0;RefreshSale();
    }
    void RefreshSale(){grid.Rows.Clear();long sub=0;foreach(var x in lines){sub+=x.Total;grid.Rows.Add(x.Product.Name,x.Product.Barcode,x.Qty.ToString("0.###"),Fmt.Money(x.Product.SalePrice),Fmt.Money(x.Total));}long pay=Math.Max(0,sub-discount);subtotal.Text="جمع\n"+Fmt.Money(sub);discountLabel.Text="تخفیف\n"+Fmt.Money(discount);payable.Text="قابل پرداخت\n"+Fmt.Money(pay);if(customer!=null)customer.UpdateSale(lines.ToList(),discount);}
  }

  class MainForm:Form
  {
    const int BW=1492,BH=1054; Image bg; string root; int syncing; readonly object dataLock=new object();
    readonly List<UiHotspot> hot=new List<UiHotspot>(); UiHotspot hover,pressed; CustomerDisplayForm customerDisplay;
    string DataFile=>Path.Combine(root,"records.sync"); string Ini=>Path.Combine(root,"settings.txt"); string DeviceFile=>Path.Combine(root,"device.id");

    public MainForm()
    {
      root=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),"WebsazStore");
      Directory.CreateDirectory(root);
      if(!File.Exists(DataFile))File.WriteAllText(DataFile,"",new UTF8Encoding(false));
      Text="وب ساز - مدیریت کامل فروشگاه";StartPosition=FormStartPosition.CenterScreen;WindowState=FormWindowState.Maximized;DoubleBuffered=true;RightToLeft=RightToLeft.Yes;Icon=SystemIcons.Application;
      SetStyle(ControlStyles.AllPaintingInWmPaint|ControlStyles.OptimizedDoubleBuffer|ControlStyles.UserPaint,true);
      bg=LoadOverlayImage();BuildHotspots();Paint+=DrawUI;Resize+=(s,e)=>Invalidate();MouseMove+=MoveUI;MouseLeave+=(s,e)=>{hover=null;Cursor=Cursors.Default;Invalidate();};MouseDown+=DownUI;MouseUp+=UpUI;
      var tm=new System.Windows.Forms.Timer();tm.Interval=60000;tm.Tick+=(s,e)=>{if(HasSiteSettings())ThreadPool.QueueUserWorkItem(_=>SyncCore(false));};tm.Start();
      Shown+=(s,e)=>{EnsureCustomerDisplay();if(HasSiteSettings())ThreadPool.QueueUserWorkItem(_=>{Thread.Sleep(1800);SyncCore(false);});};
    }

    void BuildHotspots()
    {
      hot.Add(new UiHotspot("انتقال اختیاری اطلاعات",new Rectangle(895,430,265,52),()=>StartMigration()));
      hot.Add(new UiHotspot("بررسی اطلاعات",new Rectangle(615,430,260,52),()=>CheckData()));
      hot.Add(new UiHotspot("مهاجرت اختیاری",new Rectangle(1210,210,235,58),()=>StartMigration()));
      hot.Add(new UiHotspot("فروش",new Rectangle(1210,270,235,58),()=>OpenSale()));
      hot.Add(new UiHotspot("کالاها",new Rectangle(1210,330,235,58),()=>new ProductsForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("انبار",new Rectangle(1210,390,235,58),()=>new InventoryForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("حسابداری",new Rectangle(1210,450,235,58),()=>new AccountsHubForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("گزارش‌ها",new Rectangle(1210,510,235,58),()=>new ReportsForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("اتصال سایت",new Rectangle(1210,625,235,58),()=>SiteDialog()));
      hot.Add(new UiHotspot("تنظیمات",new Rectangle(1210,685,235,58),()=>System.Diagnostics.Process.Start("explorer.exe",root)));
      hot.Add(new UiHotspot("وبسایت",new Rectangle(985,655,175,110),()=>SiteDialog()));
      hot.Add(new UiHotspot("فروش جدید",new Rectangle(980,850,180,135),()=>OpenSale()));
      hot.Add(new UiHotspot("کالاها",new Rectangle(800,850,170,135),()=>new ProductsForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("موجودی",new Rectangle(620,850,170,135),()=>new InventoryForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("خرید و ورود کالا",new Rectangle(435,850,175,135),()=>new PurchaseForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("حساب‌ها",new Rectangle(255,850,175,135),()=>new AccountsHubForm(this).ShowDialog(this)));
      hot.Add(new UiHotspot("گزارش‌ها",new Rectangle(70,850,175,135),()=>new ReportsForm(this).ShowDialog(this)));
    }

    Image LoadOverlayImage()
    {
      try{
        var exe=Application.ExecutablePath;using(var f=File.OpenRead(exe)){if(f.Length<16)return null;f.Seek(-16,SeekOrigin.End);
          var m=new byte[8];f.Read(m,0,8);var lenb=new byte[8];f.Read(lenb,0,8);if(Encoding.ASCII.GetString(m)!="WSUIJPG1")return null;
          long len=BitConverter.ToInt64(lenb,0);if(len<=0||len>f.Length)return null;f.Seek(-16-len,SeekOrigin.End);var b=new byte[len];f.Read(b,0,b.Length);using(var ms=new MemoryStream(b))return new Bitmap(ms);}
      }catch{return null;}
    }
    Rectangle UiRect(){double s=Math.Min((double)ClientSize.Width/BW,(double)ClientSize.Height/BH);int w=(int)(BW*s),h=(int)(BH*s);return new Rectangle((ClientSize.Width-w)/2,(ClientSize.Height-h)/2,w,h);}
    Point ToUi(Point p){var r=UiRect();double s=(double)r.Width/BW;return new Point((int)((p.X-r.X)/s),(int)((p.Y-r.Y)/s));}
    Rectangle FromUi(Rectangle x){var r=UiRect();double s=(double)r.Width/BW;return new Rectangle(r.X+(int)(x.X*s),r.Y+(int)(x.Y*s),(int)(x.Width*s),(int)(x.Height*s));}
    UiHotspot FindHot(Point p){var u=ToUi(p);return hot.FirstOrDefault(x=>x.R.Contains(u));}
    GraphicsPath Round(Rectangle r,int radius){var p=new GraphicsPath();int d=radius*2;p.AddArc(r.X,r.Y,d,d,180,90);p.AddArc(r.Right-d,r.Y,d,d,270,90);p.AddArc(r.Right-d,r.Bottom-d,d,d,0,90);p.AddArc(r.X,r.Bottom-d,d,d,90,90);p.CloseFigure();return p;}
    void DrawUI(object s,PaintEventArgs e)
    {
      e.Graphics.Clear(Color.FromArgb(239,246,255));if(bg!=null)e.Graphics.DrawImage(bg,UiRect());e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
      var status=FromUi(new Rectangle(295,176,840,55));using(var p=Round(status,12))using(var b=new SolidBrush(Color.FromArgb(235,255,255,255)))e.Graphics.FillPath(b,p);
      string site=HasSiteSettings()?"سایت متصل/قابل همگام‌سازی":"اتصال سایت تنظیم نشده";string txt="شروع مستقل فعال است  •  دیتابیس محلی آماده  •  "+site+"  •  مهاجرت از نرم‌افزارهای دیگر اختیاری است";
      using(var f=new Font("Tahoma",9,FontStyle.Bold))using(var br=new SolidBrush(Color.FromArgb(30,74,128)))e.Graphics.DrawString(txt,f,br,status,new StringFormat{Alignment=StringAlignment.Center,LineAlignment=StringAlignment.Center,FormatFlags=StringFormatFlags.DirectionRightToLeft});
      if(hover!=null){var r=FromUi(hover.R);if(pressed==hover)r.Offset(0,2);using(var sh=Round(new Rectangle(r.X+3,r.Y+5,r.Width,r.Height),12))using(var b=new SolidBrush(Color.FromArgb(28,16,64,120)))e.Graphics.FillPath(b,sh);using(var p=Round(r,12))using(var b=new SolidBrush(Color.FromArgb(pressed==hover?52:24,21,105,220)))using(var pen=new Pen(Color.FromArgb(pressed==hover?230:175,21,105,220),pressed==hover?3:2)){e.Graphics.FillPath(b,p);e.Graphics.DrawPath(pen,p);}}
    }
    void MoveUI(object s,MouseEventArgs e){var h=FindHot(e.Location);if(h!=hover){hover=h;Cursor=h==null?Cursors.Default:Cursors.Hand;Invalidate();}}
    void DownUI(object s,MouseEventArgs e){if(e.Button!=MouseButtons.Left)return;pressed=FindHot(e.Location);Invalidate();}
    void UpUI(object s,MouseEventArgs e){var p=pressed;pressed=null;Invalidate();if(p!=null&&p==FindHot(e.Location)&&p.Click!=null)p.Click();}

    void EnsureCustomerDisplay(){if(customerDisplay!=null&&!customerDisplay.IsDisposed)return;var seller=Screen.FromControl(this);var target=Screen.AllScreens.FirstOrDefault(x=>x.DeviceName!=seller.DeviceName);if(target==null)return;customerDisplay=new CustomerDisplayForm(target);customerDisplay.Show();customerDisplay.Bounds=target.Bounds;}
    void OpenSale(){EnsureCustomerDisplay();new SaleForm(this,LoadProducts(),customerDisplay).Show(this);}
    void CheckData(){var by=Load().GroupBy(x=>x.T).ToDictionary(g=>g.Key,g=>g.Count(),StringComparer.OrdinalIgnoreCase);MessageBox.Show(this,"نرم‌افزار مستقل آماده کار است.\n\nرکوردهای محلی: "+Load().Count.ToString("N0")+"\nکالاها: "+Count(by,"product")+"\nفروش‌ها: "+Count(by,"sale")+"\nخریدها: "+Count(by,"purchase")+"\n\nوجود نرم‌افزار قبلی الزامی نیست. مهاجرت فقط در صورت انتخاب شما انجام می‌شود.","وب ساز");}
    int Count(Dictionary<string,int>d,string k){return d.ContainsKey(k)?d[k]:0;}

    string Device(){if(File.Exists(DeviceFile))return File.ReadAllText(DeviceFile).Trim();var x=Guid.NewGuid().ToString("N");File.WriteAllText(DeviceFile,x);return x;}
    Dictionary<string,string> Settings(){var d=new Dictionary<string,string>(StringComparer.OrdinalIgnoreCase);if(File.Exists(Ini))foreach(var l in File.ReadAllLines(Ini)){int i=l.IndexOf('=');if(i>0)d[l.Substring(0,i)]=l.Substring(i+1);}return d;}
    bool HasSiteSettings(){var s=Settings();return s.ContainsKey("url")&&!string.IsNullOrWhiteSpace(s["url"])&&s.ContainsKey("key")&&!string.IsNullOrWhiteSpace(s["key"]);}
    void SaveSettings(string url,string key,string store){File.WriteAllLines(Ini,new[]{"url="+url,"key="+key,"store="+store});Invalidate();}
    string B64(string s)=>Convert.ToBase64String(Encoding.UTF8.GetBytes(s??""));
    string UB64(string s){try{return Encoding.UTF8.GetString(Convert.FromBase64String(s));}catch{return "";}}
    string Hash(string s){using(var h=SHA256.Create())return BitConverter.ToString(h.ComputeHash(Encoding.UTF8.GetBytes(s??""))).Replace("-","").ToLowerInvariant();}
    string Norm(object x){var s=(x??"").ToString().Trim().ToLowerInvariant();return new string(s.Where(c=>char.IsLetterOrDigit(c)||c>127).ToArray());}

    public List<Rec> Load()
    {
      lock(dataLock){
        var a=new List<Rec>();if(!File.Exists(DataFile))return a;
        foreach(var l in File.ReadLines(DataFile)){var p=l.Split(new[]{'\t'},5);if(p.Length<5)continue;long u;long.TryParse(p[2],out u);a.Add(new Rec{T=UB64(p[0]),K=UB64(p[1]),U=u,H=UB64(p[3]),P=p[4]});}
        return a;
      }
    }
    public string Payload(Rec r)=>UB64(r.P);
    public List<Rec> LoadType(string type)=>Load().Where(x=>string.Equals(x.T,type,StringComparison.OrdinalIgnoreCase)&&Fmt.Field(Payload(x),"deleted")!="1").ToList();
    void Merge(IEnumerable<Rec> inc)
    {
      lock(dataLock){
        var current=Load();var m=current.ToDictionary(x=>x.T+"|"+x.K,x=>x,StringComparer.OrdinalIgnoreCase);
        foreach(var r in inc){var k=r.T+"|"+r.K;Rec old;if(!m.TryGetValue(k,out old)||r.U>old.U||(r.U==old.U&&r.H!=old.H))m[k]=r;}
        using(var w=new StreamWriter(DataFile,false,new UTF8Encoding(false)))foreach(var r in m.Values.OrderBy(x=>x.T).ThenBy(x=>x.K))w.WriteLine(B64(r.T)+"\t"+B64(r.K)+"\t"+r.U+"\t"+B64(r.H)+"\t"+r.P);
      }
    }
    public void SaveEntity(string type,string key,string json){Merge(new[]{new Rec{T=type,K=key,U=DateTimeOffset.UtcNow.ToUnixTimeSeconds(),H=Hash(json),P=B64(json)}});}
    public void DeleteEntity(string type,string key){SaveEntity(type,key,"{\"deleted\":\"1\"}");}

    public List<Product> LoadProducts()
    {
      var list=new List<Product>();
      foreach(var r in LoadType("product")){
        string j=Payload(r);string barcode=Fmt.Field(j,"barcode"),name=Fmt.Field(j,"name");
        if(string.IsNullOrWhiteSpace(name))name=Fmt.Field(j,"title"); if(string.IsNullOrWhiteSpace(name))name="کالا";
        list.Add(new Product{Key=r.K,Name=name,Barcode=barcode,SalePrice=Fmt.Num(Fmt.Field(j,"salePrice")!=""?Fmt.Field(j,"salePrice"):Fmt.Field(j,"price")),BuyPrice=Fmt.Num(Fmt.Field(j,"buyPrice")),Stock=Fmt.Dec(Fmt.Field(j,"stock"))});
      }
      return list.GroupBy(x=>x.Key,StringComparer.OrdinalIgnoreCase).Select(g=>g.First()).ToList();
    }
    public void SaveProduct(Product p)
    {
      if(p==null)return;if(string.IsNullOrWhiteSpace(p.Key))p.Key=!string.IsNullOrWhiteSpace(p.Barcode)?"barcode:"+Norm(p.Barcode):"product:"+Guid.NewGuid().ToString("N");
      string j="{\"name\":\""+Fmt.J(p.Name)+"\",\"barcode\":\""+Fmt.J(p.Barcode)+"\",\"salePrice\":\""+p.SalePrice+"\",\"buyPrice\":\""+p.BuyPrice+"\",\"stock\":\""+p.Stock.ToString(System.Globalization.CultureInfo.InvariantCulture)+"\"}";
      SaveEntity("product",p.Key,j);
    }
    public List<Customer> LoadCustomers(){return LoadType("customer").Select(r=>{var j=Payload(r);return new Customer{Key=r.K,Name=Fmt.Field(j,"name"),Mobile=Fmt.Field(j,"mobile"),Balance=Fmt.Num(Fmt.Field(j,"balance"))};}).ToList();}
    public void SaveCustomer(Customer c){if(string.IsNullOrWhiteSpace(c.Key))c.Key=!string.IsNullOrWhiteSpace(c.Mobile)?"phone:"+Norm(c.Mobile):"customer:"+Guid.NewGuid().ToString("N");SaveEntity("customer",c.Key,"{\"name\":\""+Fmt.J(c.Name)+"\",\"mobile\":\""+Fmt.J(c.Mobile)+"\",\"balance\":\""+c.Balance+"\"}");}

    public void CommitPurchase(Product p,decimal qty,long buyPrice,string supplier)
    {
      p.BuyPrice=buyPrice;p.Stock+=qty;SaveProduct(p);long total=(long)Math.Round(qty*buyPrice);
      SaveEntity("purchase","purchase:"+Guid.NewGuid().ToString("N"),"{\"productKey\":\""+Fmt.J(p.Key)+"\",\"product\":\""+Fmt.J(p.Name)+"\",\"qty\":\""+qty.ToString(System.Globalization.CultureInfo.InvariantCulture)+"\",\"unitPrice\":\""+buyPrice+"\",\"total\":\""+total+"\",\"supplier\":\""+Fmt.J(supplier)+"\",\"date\":\""+DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")+"\"}");
    }
    public void CommitSale(IList<SaleLine> lines,long discount,string payment)
    {
      long sub=lines.Sum(x=>x.Total),pay=Math.Max(0,sub-discount);int items=0;
      foreach(var l in lines){items++;var p=LoadProducts().FirstOrDefault(x=>x.Key==l.Product.Key)??l.Product;p.Stock-=l.Qty;SaveProduct(p);}
      string id="sale:"+Guid.NewGuid().ToString("N");
      SaveEntity("sale",id,"{\"date\":\""+DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")+"\",\"items\":\""+items+"\",\"subtotal\":\""+sub+"\",\"discount\":\""+discount+"\",\"payable\":\""+pay+"\",\"payment\":\""+Fmt.J(payment)+"\"}");
      SaveEntity("finance","sale-fin:"+id,"{\"kind\":\"درآمد\",\"account\":\""+Fmt.J(payment)+"\",\"amount\":\""+pay+"\",\"desc\":\"فروش فروشگاه\",\"date\":\""+DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")+"\"}");
      if(HasSiteSettings())ThreadPool.QueueUserWorkItem(_=>SyncCore(false));
    }

    string Pick(DataColumnCollection c,params string[] names){foreach(DataColumn x in c)foreach(var n in names)if(x.ColumnName.IndexOf(n,StringComparison.OrdinalIgnoreCase)>=0)return x.ColumnName;return "";}
    bool Any(string s,params string[] q){return q.Any(x=>s.IndexOf(x,StringComparison.OrdinalIgnoreCase)>=0);}
    void ImportTable(SqlConnection c,string schema,string table,List<Rec> all,ref int tables,ref int rows)
    {
      string t=table.ToLowerInvariant(),type=null;if(Any(t,"kala","item","product","goods","کالا"))type="product";else if(Any(t,"customer","moshtari","person","party","client","مشتری"))type="customer";else if(Any(t,"factor","invoice","sale","forosh","فاکتور","فروش"))type="sale";else if(Any(t,"anbar","stock","inventory","warehouse","انبار"))type="stock";else if(Any(t,"hesab","account","ledger","حساب"))type="account";if(type==null)return;
      var dt=new DataTable();try{new SqlDataAdapter("SELECT TOP 50000 * FROM ["+schema.Replace("]","]]" )+ "].["+table.Replace("]","]]" )+"]",c).Fill(dt);}catch{return;}if(dt.Rows.Count==0)return;
      string barcode=Pick(dt.Columns,"barcode","bar_code","ean","gtin","upc","بارکد"),code=Pick(dt.Columns,"u_code1","kalacode","itemcode","code","id","کد"),name=Pick(dt.Columns,"kalaname","itemname","productname","name","title","نام"),phone=Pick(dt.Columns,"mobile","phone","tel","موبایل","تلفن"),no=Pick(dt.Columns,"factorno","invoiceno","number","no","شماره");
      int n=0;foreach(DataRow r in dt.Rows){string key;if(type=="product")key=!string.IsNullOrEmpty(barcode)?"barcode:"+Norm(r[barcode]):!string.IsNullOrEmpty(code)?"code:"+Norm(r[code]):"name:"+Norm(string.IsNullOrEmpty(name)?"":r[name]);else if(type=="customer")key=!string.IsNullOrEmpty(phone)?"phone:"+Norm(r[phone]):!string.IsNullOrEmpty(code)?"code:"+Norm(r[code]):"name:"+Norm(string.IsNullOrEmpty(name)?"":r[name]);else if(type=="sale")key="legacy-sale:"+Norm(string.IsNullOrEmpty(no)?"":r[no])+":"+Guid.NewGuid().ToString("N").Substring(0,8);else key="legacy:"+Norm(table)+":"+n;
        string nm=!string.IsNullOrEmpty(name)?Convert.ToString(r[name]):"";string bc=!string.IsNullOrEmpty(barcode)?Convert.ToString(r[barcode]):"";string payload=type=="product"?"{\"name\":\""+Fmt.J(nm)+"\",\"barcode\":\""+Fmt.J(bc)+"\",\"salePrice\":\"0\",\"buyPrice\":\"0\",\"stock\":\"0\",\"source\":\"migration\"}":"{\"source\":\"migration\",\"table\":\""+Fmt.J(table)+"\",\"name\":\""+Fmt.J(nm)+"\"}";
        all.Add(new Rec{T=type,K=key,U=DateTimeOffset.UtcNow.ToUnixTimeSeconds(),H=Hash(payload),P=B64(payload)});n++;}
      if(n>0){tables++;rows+=n;}
    }
    IEnumerable<string> Servers(){var s=new HashSet<string>(StringComparer.OrdinalIgnoreCase){"localhost",@".\SQLEXPRESS"};foreach(var path in new[]{@"SOFTWARE\Microsoft\Microsoft SQL Server\Instance Names\SQL",@"SOFTWARE\WOW6432Node\Microsoft\Microsoft SQL Server\Instance Names\SQL"})try{using(var k=Registry.LocalMachine.OpenSubKey(path))if(k!=null)foreach(var n in k.GetValueNames())s.Add(n.Equals("MSSQLSERVER",StringComparison.OrdinalIgnoreCase)?"localhost":@".\"+n);}catch{}return s;}
    void StartMigration(){RunBusy("در حال جستجوی اختیاری اطلاعات نرم‌افزارهای دیگر...",()=>MigrateCore(),true);}
    string MigrateCore()
    {
      int dbs=0,tables=0,rows=0;var all=new List<Rec>();
      foreach(var server in Servers())try{using(var master=new SqlConnection("Data Source="+server+";Initial Catalog=master;Integrated Security=True;Connect Timeout=2;TrustServerCertificate=True")){master.Open();var names=new List<string>();using(var q=new SqlCommand("SELECT name FROM sys.databases WHERE database_id>4 AND state=0",master))using(var rd=q.ExecuteReader())while(rd.Read())names.Add(rd.GetString(0));foreach(var db in names)try{using(var c=new SqlConnection("Data Source="+server+";Initial Catalog="+db+";Integrated Security=True;Connect Timeout=3;TrustServerCertificate=True")){c.Open();dbs++;using(var q=new SqlCommand("SELECT TABLE_SCHEMA,TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'",c))using(var rd=q.ExecuteReader()){var ts=new List<string[]>();while(rd.Read())ts.Add(new[]{rd.GetString(0),rd.GetString(1)});rd.Close();foreach(var z in ts)ImportTable(c,z[0],z[1],all,ref tables,ref rows);}}}catch{}}}catch{}
      Merge(all);return dbs==0?"هیچ دیتابیس قابل خواندن پیدا نشد؛ مشکلی نیست. نرم‌افزار مستقل است و با دیتابیس محلی خودش ادامه می‌دهد.":"مهاجرت اختیاری انجام شد.\nدیتابیس‌های بررسی‌شده: "+dbs+"\nجدول‌های شناسایی‌شده: "+tables+"\nرکوردهای واردشده/مچ‌شده: "+rows+"\nرکورد تکراری ساخته نمی‌شود.";
    }

    void SiteDialog()
    {
      var s=Settings();var f=new Form{Text="اتصال به سایت",Width=620,Height=455,StartPosition=FormStartPosition.CenterParent,RightToLeft=RightToLeft.Yes,RightToLeftLayout=true};
      var u=new TextBox{Left=40,Top=90,Width=520,Text=s.ContainsKey("url")?s["url"]:"https://websaza.ir/tehran"};
      var k=new TextBox{Left=40,Top=175,Width=520,UseSystemPasswordChar=true,Text=s.ContainsKey("key")?s["key"]:""};
      var st=new TextBox{Left=40,Top=260,Width=520,Text=s.ContainsKey("store")?s["store"]:"tehran"};
      f.Controls.AddRange(new Control[]{new Label{Text="آدرس سایت",Left=410,Top=60,Width=150,TextAlign=ContentAlignment.MiddleRight},u,new Label{Text="کد اتصال",Left=410,Top=145,Width=150,TextAlign=ContentAlignment.MiddleRight},k,new Label{Text="شناسه فروشگاه",Left=380,Top=230,Width=180,TextAlign=ContentAlignment.MiddleRight},st});
      var b=new Button{Text="ذخیره و همگام‌سازی دوطرفه",Left=300,Top=325,Width=260,Height=45,BackColor=Color.FromArgb(22,105,220),ForeColor=Color.White,FlatStyle=FlatStyle.Flat};b.FlatAppearance.BorderSize=0;
      b.Click+=(x,y)=>{SaveSettings(u.Text.Trim(),k.Text.Trim(),st.Text.Trim());var busy=new BusyForm("در حال خواندن اطلاعات سایت و مچ با اطلاعات این سیستم...");b.Enabled=false;busy.Show(f);ThreadPool.QueueUserWorkItem(_=>{var msg=SyncCore(true);try{f.BeginInvoke(new Action(()=>{busy.Close();b.Enabled=true;MessageBox.Show(f,msg,"وب ساز");}));}catch{}});};
      f.Controls.Add(b);f.Controls.Add(new Label{Text="همگام‌سازی دوطرفه است: اطلاعاتی که فقط روی سایت یا فقط روی این سیستم باشد، بدون ایجاد رکورد تکراری به سمت دیگر اضافه می‌شود.",Left=40,Top=382,Width=520,Height=45,TextAlign=ContentAlignment.MiddleCenter,ForeColor=Color.DimGray});
      f.ShowDialog(this);
    }
    string Endpoint(string u){u=(u??"").Trim().TrimEnd('/');return u.EndsWith(".php",StringComparison.OrdinalIgnoreCase)?u:u+"/websaz-sync.php";}
    string Body(){var a=Load();var s=Settings();var sb=new StringBuilder("WEBSAZSYNC1\n").Append(B64(Device())).Append('\n').Append(B64(s.ContainsKey("store")?s["store"]:"default")).Append('\n').Append(a.Count).Append('\n');foreach(var r in a)sb.Append(B64(r.T)).Append('\t').Append(B64(r.K)).Append('\t').Append(r.U).Append('\t').Append(B64(r.H)).Append('\t').Append(r.P).Append('\n');return sb.ToString();}
    List<Rec> ParseBody(string s){var a=new List<Rec>();var lines=(s??"").Split(new[]{'\r','\n'},StringSplitOptions.RemoveEmptyEntries);if(lines.Length<4||lines[0]!="WEBSAZSYNC1")return a;for(int i=4;i<lines.Length;i++){var p=lines[i].Split(new[]{'\t'},5);if(p.Length<5)continue;long u;long.TryParse(p[2],out u);a.Add(new Rec{T=UB64(p[0]),K=UB64(p[1]),U=u,H=UB64(p[3]),P=p[4]});}return a;}
    string SyncCore(bool user)
    {
      if(Interlocked.Exchange(ref syncing,1)==1)return user?"همگام‌سازی دیگری در حال اجراست.":"";
      try{
        var s=Settings();if(!HasSiteSettings())return user?"ابتدا آدرس سایت و کد اتصال را وارد کنید.":"";
        var req=(HttpWebRequest)WebRequest.Create(Endpoint(s["url"]));req.Method="POST";req.ContentType="application/octet-stream";req.Timeout=15000;req.ReadWriteTimeout=15000;req.Headers["Authorization"]="Bearer "+s["key"];
        var data=Encoding.UTF8.GetBytes(Body());req.ContentLength=data.Length;using(var st=req.GetRequestStream())st.Write(data,0,data.Length);
        string resp;using(var r=(HttpWebResponse)req.GetResponse())using(var sr=new StreamReader(r.GetResponseStream(),Encoding.UTF8))resp=sr.ReadToEnd();
        var remote=ParseBody(resp);Merge(remote);
        return "همگام‌سازی انجام شد.\nاطلاعات سایت خوانده شد و با دیتابیس محلی مچ شد.\nرکوردهای دریافت‌شده/تطبیق‌شده: "+remote.Count.ToString("N0")+"\nرکورد تکراری ساخته نمی‌شود.";
      }catch(Exception ex){return user?"اتصال به سایت انجام نشد.\n"+ex.Message:"";}
      finally{Interlocked.Exchange(ref syncing,0);}
    }
    void RunBusy(string text,Func<string> work,bool showMessage){var b=new BusyForm(text);Enabled=false;b.Show(this);ThreadPool.QueueUserWorkItem(_=>{string msg="";try{msg=work();}catch(Exception ex){msg="عملیات انجام نشد.\n"+ex.Message;}try{BeginInvoke(new Action(()=>{Enabled=true;b.Close();Activate();if(showMessage&&!string.IsNullOrEmpty(msg))MessageBox.Show(this,msg,"وب ساز");}));}catch{}});}
  }

  static class Program
  {
    [STAThread] static void Main()
    {
      ServicePointManager.SecurityProtocol=SecurityProtocolType.Tls12;
      Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);
      Application.Run(new MainForm());
    }
  }
}
