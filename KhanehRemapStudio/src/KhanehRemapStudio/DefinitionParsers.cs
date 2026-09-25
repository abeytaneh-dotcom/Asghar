using System.Globalization;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Xml.Linq;

namespace KhanehRemapStudio;

public static class DefinitionParsers
{
    public static CalibrationProfile Parse(string path)
    {
        string ext=Path.GetExtension(path).ToLowerInvariant();
        string text=File.ReadAllText(path);
        string name=Path.GetFileNameWithoutExtension(path);
        return ext switch
        {
            ".json"=>JsonSerializer.Deserialize<CalibrationProfile>(text,new JsonSerializerOptions{PropertyNameCaseInsensitive=true}) ?? throw new InvalidDataException("JSON نامعتبر است."),
            ".csv"=>ParseCsv(text,name),
            ".xdf"=>ParseXdf(text,name),
            ".a2l"=>ParseA2l(text,name),
            _=>throw new InvalidDataException("فرمت پشتیبانی نمی‌شود: JSON / CSV / XDF / A2L")
        };
    }

    private static CalibrationProfile ParseCsv(string text,string name)
    {
        var p=new CalibrationProfile{ProfileName=name,Source="CSV"};
        var lines=text.Replace("\r","").Split('\n',StringSplitOptions.RemoveEmptyEntries);
        if(lines.Length<2) throw new InvalidDataException("CSV خالی است.");
        var h=SplitCsv(lines[0]).Select(x=>x.Trim().ToLowerInvariant()).ToArray();
        int Idx(params string[] n)=>Array.FindIndex(h,x=>n.Contains(x));
        int ia=Idx("address","addr","offset");
        if(ia<0) throw new InvalidDataException("ستون address وجود ندارد.");
        int iname=Idx("name","nameen","name_en"),ifa=Idx("namefa","name_fa"),icat=Idx("category","cat"),ir=Idx("rows"),ic=Idx("cols","columns"),
            it=Idx("datatype","type"),ie=Idx("endian"),ifac=Idx("factor","scale"),io=Idx("valueoffset","value_offset"),iu=Idx("unit");
        for(int line=1;line<lines.Length;line++)
        {
            var c=SplitCsv(lines[line]); string G(int i)=>i>=0&&i<c.Count?c[i].Trim():"";
            long a=Addr(G(ia),-1); if(a<0) continue;
            string en=G(iname),fa=G(ifa); if(string.IsNullOrWhiteSpace(fa)) fa=string.IsNullOrWhiteSpace(en)?$"Map 0x{a:X}":en;
            p.Maps.Add(new MapDefinition{NameFa=fa,NameEn=en,Category=string.IsNullOrWhiteSpace(G(icat))?"CSV":G(icat),Address=a,
                Rows=Int(G(ir),1),Cols=Int(G(ic),1),DataType=Type(G(it)),Endian=G(ie).StartsWith("b",StringComparison.OrdinalIgnoreCase)?"big":"little",
                Factor=Double(G(ifac),1),Offset=Double(G(io),0),Unit=G(iu),Source="CSV",Confidence="imported"});
        }
        return p;
    }

    private static CalibrationProfile ParseXdf(string text,string name)
    {
        var doc=XDocument.Parse(text,LoadOptions.PreserveWhitespace);
        var p=new CalibrationProfile{ProfileName=name,Source="TunerPro XDF"};
        long baseOffset=0; bool subtract=false;
        var baseNode=doc.Descendants().FirstOrDefault(x=>L(x)=="BASEOFFSET");
        if(baseNode!=null){baseOffset=Addr(A(baseNode,"offset"),0);subtract=A(baseNode,"subtract")=="1";}
        long Translate(long a)=>subtract?a-baseOffset:a+baseOffset;

        foreach(var table in doc.Descendants().Where(x=>L(x)=="XDFTABLE"))
        {
            string title=Child(table,"title"); if(string.IsNullOrWhiteSpace(title)) title="XDF Table";
            var axes=table.Elements().Where(x=>L(x)=="XDFAXIS").ToList();
            var z=axes.FirstOrDefault(x=>A(x,"id").Equals("z",StringComparison.OrdinalIgnoreCase)); if(z==null) continue;
            var ed=z.Descendants().FirstOrDefault(x=>L(x)=="EMBEDDEDDATA"); if(ed==null) continue;
            long addr=Addr(A(ed,"mmedaddress"),-1); if(addr<0) continue; addr=Translate(addr);
            var (type,endian)=DataType(ed);
            int xCount=AxisCount(axes,"x"),yCount=AxisCount(axes,"y");
            int zr=Int(A(ed,"mmedrowcount"),0),zc=Int(A(ed,"mmedcolcount"),0);
            int cols=zc>0?zc:Math.Max(1,xCount), rows=zr>0?zr:Math.Max(1,yCount);
            var math=Affine(z.Descendants().FirstOrDefault(x=>L(x)=="MATH")?.Attribute("equation")?.Value??"X");
            var map=new MapDefinition{NameFa=title,NameEn=title,Category="XDF",Address=addr,Rows=rows,Cols=cols,DataType=type,Endian=endian,
                Factor=math.ok?math.f:1,Offset=math.ok?math.o:0,Unit=Child(z,"units"),Source=$"XDF: {name}",Confidence="imported",
                ReadOnly=!math.ok,Notes=math.ok?"واردشده از XDF":"معادله Z پیچیده است؛ برای ایمنی فقط نمایش."};
            map.XAxis=ParseAxis(axes,"x",Translate);
            map.YAxis=ParseAxis(axes,"y",Translate);
            p.Maps.Add(map);
        }
        if(p.Maps.Count==0) throw new InvalidDataException("جدول قابل استفاده‌ای در XDF پیدا نشد.");
        return p;
    }

    private static AxisDefinition? ParseAxis(List<XElement> axes,string id,Func<long,long> translate)
    {
        var a=axes.FirstOrDefault(x=>A(x,"id").Equals(id,StringComparison.OrdinalIgnoreCase)); if(a==null) return null;
        int count=AxisCount(axes,id); if(count<=0) return null;
        var values=a.Descendants().Where(x=>L(x)=="VALUE").Select(x=>Double(x.Value,double.NaN)).Where(x=>!double.IsNaN(x)).ToArray();
        if(values.Length==count) return new AxisDefinition{Name=id.ToUpperInvariant(),Count=count,StaticValues=values,Unit=Child(a,"units")};
        var ed=a.Descendants().FirstOrDefault(x=>L(x)=="EMBEDDEDDATA"); if(ed==null) return new AxisDefinition{Name=id.ToUpperInvariant(),Count=count,Unit=Child(a,"units")};
        long addr=Addr(A(ed,"mmedaddress"),-1); var dt=DataType(ed); var math=Affine(a.Descendants().FirstOrDefault(x=>L(x)=="MATH")?.Attribute("equation")?.Value??"X");
        return new AxisDefinition{Name=id.ToUpperInvariant(),Count=count,Address=addr<0?-1:translate(addr),DataType=dt.type,Endian=dt.endian,
            Factor=math.ok?math.f:1,Offset=math.ok?math.o:0,Unit=Child(a,"units")};
    }

    private static CalibrationProfile ParseA2l(string text,string name)
    {
        var p=new CalibrationProfile{ProfileName=name,Source="ASAM A2L"};
        var rx=new Regex("/begin\\s+CHARACTERISTIC\\s+(?<name>[^\\s]+)\\s+\"(?<desc>[^\"]*)\"\\s+(?<type>[^\\s]+)\\s+(?<addr>0x[0-9A-Fa-f]+)",RegexOptions.IgnoreCase|RegexOptions.Singleline);
        foreach(Match m in rx.Matches(text))
        {
            long a=Addr(m.Groups["addr"].Value,-1); if(a<0) continue;
            string n=m.Groups["name"].Value;
            p.Maps.Add(new MapDefinition{NameFa=n,NameEn=n,Category="A2L",Address=a,Rows=1,Cols=1,DataType="uint8",
                Source=$"A2L: {name}",Confidence="a2l-address",ReadOnly=true,
                Notes="آدرس CHARACTERISTIC استخراج شده؛ Record Layout/Conversion کامل تفسیر نشده و ویرایش قفل است."});
        }
        if(p.Maps.Count==0) throw new InvalidDataException("CHARACTERISTIC پیدا نشد.");
        return p;
    }

    private static (string type,string endian) DataType(XElement ed)
    {
        int bits=Int(A(ed,"mmedelementsizebits"),8); if(bits is not(8 or 16 or 32)) bits=8;
        int flags=Int(A(ed,"mmedtypeflags"),0); bool little=(flags&1)!=0,signed=(flags&2)!=0;
        return ((signed?"int":"uint")+bits,bits==8||little?"little":"big");
    }

    private static (bool ok,double f,double o) Affine(string eq)
    {
        string e=(eq??"X").Replace(" ","").Replace("(","").Replace(")","").ToUpperInvariant();
        if(e=="X") return(true,1,0);
        var m=Regex.Match(e,@"^X\*(?<f>[+-]?[0-9.]+(?:E[+-]?[0-9]+)?)(?<o>[+-][0-9.]+(?:E[+-]?[0-9]+)?)?$");
        if(m.Success&&TryD(m.Groups["f"].Value,out var f)) return(true,f,m.Groups["o"].Success&&TryD(m.Groups["o"].Value,out var o)?o:0);
        m=Regex.Match(e,@"^(?<f>[+-]?[0-9.]+(?:E[+-]?[0-9]+)?)\*X(?<o>[+-][0-9.]+(?:E[+-]?[0-9]+)?)?$");
        if(m.Success&&TryD(m.Groups["f"].Value,out f)) return(true,f,m.Groups["o"].Success&&TryD(m.Groups["o"].Value,out var o2)?o2:0);
        m=Regex.Match(e,@"^X/(?<d>[+-]?[0-9.]+)(?<o>[+-][0-9.]+)?$");
        if(m.Success&&TryD(m.Groups["d"].Value,out var d)&&Math.Abs(d)>1e-15) return(true,1/d,m.Groups["o"].Success&&TryD(m.Groups["o"].Value,out var o3)?o3:0);
        return(false,1,0);
    }

    private static int AxisCount(List<XElement> a,string id){var x=a.FirstOrDefault(v=>A(v,"id").Equals(id,StringComparison.OrdinalIgnoreCase));return x==null?1:Int(x.Elements().FirstOrDefault(v=>L(v)=="INDEXCOUNT")?.Value??"",1);}
    private static string L(XElement e)=>e.Name.LocalName.ToUpperInvariant();
    private static string A(XElement e,string n)=>e.Attributes().FirstOrDefault(a=>a.Name.LocalName.Equals(n,StringComparison.OrdinalIgnoreCase))?.Value??"";
    private static string Child(XElement e,string n)=>e.Elements().FirstOrDefault(x=>x.Name.LocalName.Equals(n,StringComparison.OrdinalIgnoreCase))?.Value??"";
    private static long Addr(string s,long fb){if(string.IsNullOrWhiteSpace(s))return fb;s=s.Trim();try{if(s.StartsWith("0x",StringComparison.OrdinalIgnoreCase))return Convert.ToInt64(s[2..],16);if(long.TryParse(s,NumberStyles.Integer,CultureInfo.InvariantCulture,out var d))return d;if(long.TryParse(s,NumberStyles.HexNumber,CultureInfo.InvariantCulture,out var h))return h;}catch{}return fb;}
    private static int Int(string s,int fb)=>int.TryParse(s,NumberStyles.Integer,CultureInfo.InvariantCulture,out var v)?v:fb;
    private static double Double(string s,double fb)=>double.TryParse((s??"").Replace(',','.'),NumberStyles.Float,CultureInfo.InvariantCulture,out var v)?v:fb;
    private static bool TryD(string s,out double v)=>double.TryParse(s,NumberStyles.Float,CultureInfo.InvariantCulture,out v);
    private static string Type(string s)=>s.Trim().ToLowerInvariant() switch{"u8" or "uint8" or "byte"=>"uint8","s8" or "int8"=>"int8","u16" or "uint16" or "word"=>"uint16","s16" or "int16"=>"int16","u32" or "uint32" or "dword"=>"uint32","s32" or "int32"=>"int32",_=>"uint8"};
    private static List<string> SplitCsv(string line){var r=new List<string>();var sb=new System.Text.StringBuilder();bool q=false;for(int i=0;i<line.Length;i++){char ch=line[i];if(ch=='"'){if(q&&i+1<line.Length&&line[i+1]=='"'){sb.Append('"');i++;}else q=!q;}else if(ch==','&&!q){r.Add(sb.ToString());sb.Clear();}else sb.Append(ch);}r.Add(sb.ToString());return r;}
}