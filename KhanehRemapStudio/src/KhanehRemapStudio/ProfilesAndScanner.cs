namespace KhanehRemapStudio;

public static class BuiltInProfiles
{
    public static CalibrationProfile? Find(string sha256)
    {
        if(sha256.Equals("2590c8c0351bc53ab870499d3c696bfd4ee34df618c184158254ff5e619175b1",StringComparison.OrdinalIgnoreCase))
            return PrideCb7();
        return null;
    }

    private static CalibrationProfile PrideCb7()
    {
        var maps=new List<MapDefinition>();
        void Add(string fa,string en,string cat,long addr,int rows,int cols,string type="uint8",double factor=1,double offset=0,string unit="")
            => maps.Add(new MapDefinition
            {
                NameFa=fa,NameEn=en,Category=cat,Address=addr,Rows=rows,Cols=cols,DataType=type,
                Endian="little",Factor=factor,Offset=offset,Unit=unit,Source="Built-in verified profile",
                Confidence="exact-hash+signature",ReadOnly=false,
                Notes="آدرس با دامپ مرجع دقیق و امضای خانواده تطبیق داده شده است. Scale مهندسی فقط در مواردی که مشخص است اعمال شده."
            });

        Add("جرقه ۱","Spark Advance 1","جرقه",108770,16,12);
        Add("جرقه ۲","Spark Advance 2","جرقه",108962,16,12);
        Add("پاشش ۱","Injection 1","سوخت",118048,16,12);
        Add("پاشش ۲","Injection 2","سوخت",118240,16,12);
        Add("پاشش ۳","Injection 3","سوخت",118432,8,8);
        Add("پاشش ۴","Injection 4","سوخت",118496,8,8);
        Add("سرعت دنده","Gear Speed","محدودکننده",100526,1,5);
        Add("لامبدا","Lambda","سوخت",107844,8,8);
        Add("دمای فن","Fan Temperature","دما",101313,1,2,"uint8",0.75,-48,"°C");
        Add("کات‌آف ۱","Cutoff 1","محدودکننده",74731,1,1,"uint8",32,0,"rpm");
        Add("کات‌آف ۲","Cutoff 2","محدودکننده",74734,1,1,"uint8",32,0,"rpm");
        Add("کات‌آف ۳","Cutoff 3","محدودکننده",74742,1,1,"uint8",32,0,"rpm");
        Add("کات‌آف ۴","Cutoff 4","محدودکننده",74750,1,1,"uint8",32,0,"rpm");
        Add("قطع سوخت رها کردن گاز ۱","Overrun Fuel Cutoff 1","سوخت",113166,6,6);
        Add("قطع سوخت رها کردن گاز ۲","Overrun Fuel Cutoff 2","سوخت",113284,6,6);
        Add("گشتاور ۱","Torque 1","گشتاور",104530,8,16,"uint16");
        Add("گشتاور ۲","Torque 2","گشتاور",104882,8,16,"uint16");
        Add("هوای ورودی ۱","Air Input 1","هوا",105138,8,8,"uint16");
        Add("هوای ورودی ۲","Air Input 2","هوا",105266,8,8,"uint16");
        Add("دریچه گاز خطی","Throttle Linear","دریچه گاز",98656,1,16,"uint16");
        Add("زمان شارژ کویل ۱","Coil Charge 1","جرقه",106164,6,6,"uint16");
        Add("حجم موتور ۱","Motor Volume 1","مدل موتور",106352,12,8,"uint16");
        Add("حجم موتور ۲","Motor Volume 2","مدل موتور",106556,12,8,"uint16");
        Add("پاشش 16بیت ۱","Injection 16-bit 1","سوخت",106392,8,8,"uint16");
        Add("پاشش 16بیت ۲","Injection 16-bit 2","سوخت",106604,8,8,"uint16");
        Add("تشخیص میس‌فایر ۱","Misfire 1","احتراق",117496,10,6,"uint16");
        Add("تشخیص میس‌فایر ۲","Misfire 2","احتراق",117616,10,6,"uint16");
        Add("زمان شارژ کویل ۲","Coil Charge 2","جرقه",116802,6,8,"uint16");
        Add("استارت سرد","Cold Start","استارت سرد",118592,5,12,"uint16");

        return new CalibrationProfile
        {
            ProfileName="Pride Siemens Bifuel CB7",
            Vehicle="Saipa Pride",Fuel="Bifuel",EcuVendor="Siemens",
            EcuFamily="CB7 / Iranian Siemens",FileSize=524288,
            Sha256="2590c8c0351bc53ab870499d3c696bfd4ee34df618c184158254ff5e619175b1",
            Source="User reference dump + signature validation",Maps=maps
        };
    }
}

public static class SiemensMapScanner
{
    public static List<MapDefinition> Scan(byte[] d,bool bifuelHint)
    {
        var maps=new List<MapDefinition>();
        FindSpark(d,maps,bifuelHint);
        FindInjection(d,maps,bifuelHint);
        FindLambda(d,maps);
        FindFan(d,maps);
        FindCutoff(d,maps);
        FindOverrun(d,maps);
        FindTorque(d,maps);
        FindThrottle(d,maps);
        FindCoil(d,maps);
        FindColdStart(d,maps);

        foreach(var m in maps)
        {
            m.ReadOnly=true;
            m.Source="Siemens family signature";
            m.Confidence="family-signature";
            m.Notes="از امضای خانواده پیدا شده است؛ تا زمان تطبیق با تعریف دقیق برای ایمنی فقط نمایش داده می‌شود.";
        }
        return maps.Where(x=>Inside(d.Length,x)).GroupBy(x=>$"{x.Address}:{x.Rows}:{x.Cols}:{x.NameEn}").Select(g=>g.First()).OrderBy(x=>x.Address).ToList();
    }

    private static void FindSpark(byte[] d,List<MapDefinition> m,bool bi)
    {
        var defs=new[]{(H("62 62 62 62 00 00 00 00 00 00 00 00"),12),(H("62 62 62 62 00 4D 80 80 00 00 00 00"),12),(H("62 FF FF FF FF 00 00 00 00"),9),(H("CD FF FF 00 00 00 00"),7)};
        foreach(var x in defs)
        {
            int s=Find(d,x.Item1); if(s<0) continue;
            Add(m,"جرقه ۱","Spark Advance 1","جرقه",s+x.Item2,16,12);
            if(bi) Add(m,"جرقه ۲","Spark Advance 2","جرقه",s+x.Item2+192,16,12);
            break;
        }
    }

    private static void FindInjection(byte[] d,List<MapDefinition> m,bool bi)
    {
        byte[][] p={H("CD A7 8D 80"),H("C7 AA 8E 80"),H("DC B0 95 80"),H("BE A3 8E 80"),H("CB B4 9A 80"),H("FF CE 9A 80"),H("FF C4 98 80"),H("C7 A1 88 80"),H("E4 AC 91 80"),H("D3 AC 98 80"),H("C0 AA 99 80")};
        int s=p.Select(x=>Find(d,x)).FirstOrDefault(x=>x>=0,-1); if(s<0) return;
        Add(m,"پاشش ۱","Injection 1","سوخت",s+4,16,12);
        if(bi){Add(m,"پاشش ۲","Injection 2","سوخت",s+196,16,12);Add(m,"پاشش ۳","Injection 3","سوخت",s+388,8,8);Add(m,"پاشش ۴","Injection 4","سوخت",s+452,8,8);}
    }

    private static void FindLambda(byte[] d,List<MapDefinition> m)
    {
        int s=Find(d,H("50 50 4B 38 25 1B 10 13")); if(s>=64) Add(m,"لامبدا","Lambda","سوخت",s-64,8,8);
    }

    private static void FindFan(byte[] d,List<MapDefinition> m)
    {
        int s=Find(d,H("90 B8 04 18")); if(s<0||s+7>d.Length) return;
        double t0=Math.Floor(d[s+4]*0.75-48),t1=Math.Floor(d[s+5]*0.75-48);
        if(t0>80) Add(m,"دمای فن","Fan Temperature","دما",s+4,1,3,"uint8",0.75,-48,"°C");
        else if(t1>80) Add(m,"دمای فن","Fan Temperature","دما",s+5,1,2,"uint8",0.75,-48,"°C");
    }

    private static void FindCutoff(byte[] d,List<MapDefinition> m)
    {
        int s=Find(d,H("1C 0A 1C 02")); if(s<0) return;
        int[] o={6,9,17,25}; for(int i=0;i<o.Length;i++) Add(m,$"کات‌آف {i+1}",$"Cutoff {i+1}","محدودکننده",s+o[i],1,1,"uint8",32,0,"rpm");
    }

    private static void FindOverrun(byte[] d,List<MapDefinition> m)
    {
        int s=Find(d,H("E6 E6 FF FF FF FF FF FF")); if(s>=0) Add(m,"قطع سوخت رها کردن گاز ۱","Overrun Fuel Cutoff 1","سوخت",s+8,6,6);
        foreach(var x in new[]{(H("1B 1A 19 16 16 16 16 16"),8),(H("00 FF FF 7D 7D"),3),(H("00 00 00 7D 7D"),3)})
        {s=Find(d,x.Item1);if(s>=0){Add(m,"قطع سوخت رها کردن گاز ۲","Overrun Fuel Cutoff 2","سوخت",s+x.Item2,6,6);break;}}
    }

    private static void FindTorque(byte[] d,List<MapDefinition> m)
    {
        var w=Words(d);
        int a=Find(w,new ushort[]{0x0A2D,0x07E0,0x0189}); if(a<0) a=Find(w,new ushort[]{0x32E1,0x2B50,0x2661});
        if(a>=0) Add(m,"گشتاور ۱","Torque 1","گشتاور",(a+3)*2L,8,16,"uint16");
        int b=Find(w,new ushort[]{0x04B0,0x04B0,0x0444,0x030C}); if(b<0) return;
        Add(m,"گشتاور ۲","Torque 2","گشتاور",(b+4)*2L,8,16,"uint16");
        Add(m,"هوای ورودی ۱","Air Input 1","هوا",(b+132)*2L,8,8,"uint16");
        Add(m,"هوای ورودی ۲","Air Input 2","هوا",(b+196)*2L,8,8,"uint16");
    }

    private static void FindThrottle(byte[] d,List<MapDefinition> m)
    {
        var w=Words(d); int s=Find(w,new ushort[]{0x0009,0,0,0,0,0x0010});
        if(s>=0) Add(m,"دریچه گاز خطی","Throttle Linear","دریچه گاز",(s+6)*2L,1,16,"uint16");
    }

    private static void FindCoil(byte[] d,List<MapDefinition> m)
    {
        var w=Words(d);
        foreach(var x in new[]{(new ushort[]{0,0,0x02D5,0x02D5,0x02D5},38),(new ushort[]{0x0320,0x02EE,0x02EE,0x02EE,0x02EE},13),(new ushort[]{0x033E,0x02EE,0x02EE,0x02EE},7)})
        {int s=Find(w,x.Item1);if(s>=0){Add(m,"زمان شارژ کویل ۱","Coil Charge 1","جرقه",(s+x.Item2)*2L,6,6,"uint16");break;}}
    }

    private static void FindColdStart(byte[] d,List<MapDefinition> m)
    {
        var w=Words(d); ushort[][] pats={new ushort[]{0x1212,0x0A0C,0x0808,0x0808},new ushort[]{0x0C0C,0x0C0C,0x100E,0x1212},new ushort[]{0xFFF1,0xFFEC,0xFFE2,0xFFD7}};
        foreach(var p in pats){int s=Find(w,p);if(s>=0){Add(m,"استارت سرد","Cold Start","استارت سرد",(s+4)*2L,5,12,"uint16");break;}}
    }

    private static void Add(List<MapDefinition> m,string fa,string en,string cat,long addr,int rows,int cols,string type="uint8",double factor=1,double offset=0,string unit="")
        =>m.Add(new MapDefinition{NameFa=fa,NameEn=en,Category=cat,Address=addr,Rows=rows,Cols=cols,DataType=type,Endian="little",Factor=factor,Offset=offset,Unit=unit});

    private static bool Inside(int len,MapDefinition m){try{return m.Address>=0 && m.Address+(long)m.Rows*m.Cols*Util.TypeSize(m.DataType)<=len;}catch{return false;}}
    private static byte[] H(string s)=>s.Split(' ',StringSplitOptions.RemoveEmptyEntries).Select(x=>Convert.ToByte(x,16)).ToArray();
    private static int Find(byte[] d,byte[] p){for(int i=0;i<=d.Length-p.Length;i++){int j=0;for(;j<p.Length&&d[i+j]==p[j];j++);if(j==p.Length)return i;}return -1;}
    private static ushort[] Words(byte[] d){var w=new ushort[d.Length/2];for(int i=0;i<w.Length;i++)w[i]=(ushort)(d[i*2]|(d[i*2+1]<<8));return w;}
    private static int Find(ushort[] d,ushort[] p){for(int i=0;i<=d.Length-p.Length;i++){int j=0;for(;j<p.Length&&d[i+j]==p[j];j++);if(j==p.Length)return i;}return -1;}
}