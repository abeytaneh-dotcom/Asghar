using System.Globalization;
using System.Text;

namespace KhanehRemapStudio;

public sealed class EcuProfile
{
    public string Id { get; set; }="";
    public string Vendor { get; set; }="";
    public string Family { get; set; }="";
    public List<string> Variants { get; set; }=new();
    public List<string> VehicleMakers { get; set; }=new();
    public List<string> Vehicles { get; set; }=new();
    public List<string> Buses { get; set; }=new();
    public List<string> ManualPages { get; set; }=new();
    public string Confidence { get; set; }="";
    public List<string> ProgrammingMethods { get; set; }=new();
    public bool ObdProgramming { get; set; }
    public bool AutoIdentification { get; set; }
    public List<string> Aliases { get; set; }=new();

    public string Display => $"{Vendor} • {Family}";
    public string SearchText => string.Join(" ", new[]{
        Id,Vendor,Family,string.Join(" ",Variants),string.Join(" ",VehicleMakers),string.Join(" ",Vehicles),
        string.Join(" ",Buses),string.Join(" ",ProgrammingMethods),string.Join(" ",Aliases)
    }).ToLowerInvariant();
}

public static class EcuProfileCatalog
{
    public static IReadOnlyList<EcuProfile> Load()
    {
        string csv=EmbeddedData.ReadTextBySuffix("Data.tnm_ecu_profiles.csv");
        var lines=csv.Replace("\r","").Split('\n',StringSplitOptions.RemoveEmptyEntries);
        if(lines.Length<2) return Array.Empty<EcuProfile>();

        var result=new List<EcuProfile>();
        for(int i=1;i<lines.Length;i++)
        {
            var c=SplitCsv(lines[i]);
            if(c.Count<14) continue;

            result.Add(new EcuProfile
            {
                Id=Get(c,1),
                Vendor=Get(c,2),
                Family=Get(c,3),
                Variants=List(Get(c,4)),
                VehicleMakers=List(Get(c,5)),
                Vehicles=List(Get(c,6)),
                Buses=List(Get(c,7)),
                ManualPages=List(Get(c,8)),
                Confidence=Get(c,9),
                ProgrammingMethods=List(Get(c,10)),
                ObdProgramming=Bool(Get(c,11)),
                AutoIdentification=Bool(Get(c,12)),
                Aliases=List(Get(c,13))
            });
        }
        return result;
    }

    private static string Get(List<string> row,int i)=>i<row.Count?row[i].Trim():"";
    private static bool Bool(string s)=>s.Equals("true",StringComparison.OrdinalIgnoreCase)||s=="1"||s.Equals("yes",StringComparison.OrdinalIgnoreCase);
    private static List<string> List(string s)=>s.Split(';',StringSplitOptions.RemoveEmptyEntries|StringSplitOptions.TrimEntries).Where(x=>x.Length>0).Distinct(StringComparer.OrdinalIgnoreCase).ToList();

    private static List<string> SplitCsv(string line)
    {
        var r=new List<string>();var sb=new StringBuilder();bool q=false;
        for(int i=0;i<line.Length;i++)
        {
            char ch=line[i];
            if(ch=='"')
            {
                if(q&&i+1<line.Length&&line[i+1]=='"'){sb.Append('"');i++;}
                else q=!q;
            }
            else if(ch==','&&!q){r.Add(sb.ToString());sb.Clear();}
            else sb.Append(ch);
        }
        r.Add(sb.ToString());
        return r;
    }
}