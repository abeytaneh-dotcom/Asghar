using System.Globalization;
using System.IO.Compression;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace KhanehRemapStudio;

public sealed class AxisDefinition
{
    public string Name { get; set; } = "";
    public long Address { get; set; } = -1;
    public int Count { get; set; }
    public string DataType { get; set; } = "uint8";
    public string Endian { get; set; } = "little";
    public double Factor { get; set; } = 1;
    public double Offset { get; set; }
    public string Unit { get; set; } = "";
    public double[]? StaticValues { get; set; }
}

public sealed class MapDefinition
{
    public string Id { get; set; } = Guid.NewGuid().ToString("N");
    public string NameFa { get; set; } = "بدون نام";
    public string NameEn { get; set; } = "";
    public string Category { get; set; } = "سایر";
    public long Address { get; set; }
    public int Rows { get; set; } = 1;
    public int Cols { get; set; } = 1;
    public string DataType { get; set; } = "uint8";
    public string Endian { get; set; } = "little";
    public double Factor { get; set; } = 1;
    public double Offset { get; set; }
    public string Unit { get; set; } = "";
    public string Source { get; set; } = "";
    public string Confidence { get; set; } = "unknown";
    public string Notes { get; set; } = "";
    public bool ReadOnly { get; set; }
    public AxisDefinition? XAxis { get; set; }
    public AxisDefinition? YAxis { get; set; }
    public string Display => $"{NameFa}  •  {Category}  •  0x{Address:X}";
}

public sealed class CalibrationProfile
{
    public string ProfileName { get; set; } = "";
    public string Vehicle { get; set; } = "";
    public string Fuel { get; set; } = "";
    public string EcuVendor { get; set; } = "";
    public string EcuFamily { get; set; } = "";
    public string Hardware { get; set; } = "";
    public string Software { get; set; } = "";
    public string CalibrationId { get; set; } = "";
    public string Sha256 { get; set; } = "";
    public long FileSize { get; set; }
    public string Source { get; set; } = "";
    public List<MapDefinition> Maps { get; set; } = new();
}

public sealed class DumpCatalog
{
    public int SchemaVersion { get; set; }
    public string Source { get; set; } = "";
    public int Count { get; set; }
    public Dictionary<string,int> Vendors { get; set; } = new(StringComparer.OrdinalIgnoreCase);
    public List<DumpCatalogItem> Items { get; set; } = new();
}

public sealed class DumpCatalogItem
{
    public string Name { get; set; } = "";
    public string Path { get; set; } = "";
    public string Vendor { get; set; } = "";
    public long Size { get; set; }
    public string Sha256 { get; set; } = "";
    public List<string> Blocks { get; set; } = new();
    public List<string> AsciiIds { get; set; } = new();
    // Physical source, if available. Metadata-only built-ins leave these empty.
    public string SourceKind { get; set; } = ""; // file | archive | metadata
    public string SourceContainer { get; set; } = ""; // absolute file/archive path
    public string EntryPath { get; set; } = ""; // archive entry path
    public bool HasPhysicalSource =>
        SourceKind.Equals("file",StringComparison.OrdinalIgnoreCase) && File.Exists(SourceContainer) ||
        SourceKind.Equals("archive",StringComparison.OrdinalIgnoreCase) && File.Exists(SourceContainer) && !string.IsNullOrWhiteSpace(EntryPath);

    public string FamilyHint
    {
        get
        {
            string n = Name.ToUpperInvariant();
            string[] keys = {
                "ME17.9.7","M7.9.7.1","M7.9.7","M7.4.11","ME7.4.9","ME7.4.5","ME-7-4-4","ME7.4.4","M7.4.4",
                "S2000","PL4","J34","J35","J34P","K6","SIRIUS","SIM2K","SIMK","CIM","CBM","CIX","SSAT","EASYU","EZU"
            };
            return keys.FirstOrDefault(k => n.Contains(k, StringComparison.OrdinalIgnoreCase)) ?? "";
        }
    }
}

public sealed class IdentificationResult
{
    public DumpCatalogItem? Exact { get; set; }
    public DumpCatalogItem? BestCandidate { get; set; }
    public double Similarity { get; set; }
    public string DisplayName => Exact?.Name ?? BestCandidate?.Name ?? "ناشناخته";
    public string Vendor => Exact?.Vendor ?? BestCandidate?.Vendor ?? "";
    public bool IsExact => Exact != null;
    public string FamilyHint => Exact?.FamilyHint ?? BestCandidate?.FamilyHint ?? "";
}

public sealed class ByteChange
{
    public int Address { get; set; }
    public byte[] OldBytes { get; set; } = Array.Empty<byte>();
    public byte[] NewBytes { get; set; } = Array.Empty<byte>();
}

public sealed class ChecksumReport
{
    public string PluginId { get; set; } = "";
    public string Family { get; set; } = "";
    public bool Supported { get; set; }
    public bool Valid { get; set; }
    public bool Repairable { get; set; }
    public int RegionCount { get; set; }
    public string Message { get; set; } = "";
}

public static class Util
{
    public static string Sha256(byte[] data) => Convert.ToHexString(SHA256.HashData(data)).ToLowerInvariant();

    public static List<string> BlockFingerprints(byte[] data, int blocks = 16)
    {
        var list = new List<string>(blocks);
        for (int i=0;i<blocks;i++)
        {
            int a=(int)((long)data.Length*i/blocks);
            int b=(int)((long)data.Length*(i+1)/blocks);
            var h=SHA256.HashData(data.AsSpan(a,b-a));
            list.Add(Convert.ToHexString(h.AsSpan(0,8)).ToLowerInvariant());
        }
        return list;
    }

    public static double FingerprintSimilarity(IReadOnlyList<string> a, IReadOnlyList<string> b)
    {
        int n=Math.Min(a.Count,b.Count);
        if(n==0) return 0;
        int hit=0;
        for(int i=0;i<n;i++)
            if(string.Equals(a[i],b[i],StringComparison.OrdinalIgnoreCase)) hit++;
        return hit/(double)n;
    }

    public static int TypeSize(string type) => type.ToLowerInvariant() switch
    {
        "uint8" or "int8" => 1,
        "uint16" or "int16" => 2,
        "uint32" or "int32" => 4,
        _ => throw new InvalidOperationException($"نوع داده پشتیبانی نمی‌شود: {type}")
    };

    public static string Format(double v) =>
        Math.Abs(v-Math.Round(v))<1e-9 ? Math.Round(v).ToString(CultureInfo.InvariantCulture) : v.ToString("0.######",CultureInfo.InvariantCulture);

    public static string FormatSize(long n)
    {
        string[] u={"B","KB","MB","GB"};
        double x=n; int i=0;
        while(x>=1024 && i<u.Length-1){x/=1024;i++;}
        return $"{x:0.##} {u[i]}";
    }
}

public static class EmbeddedData
{
    public static string ReadTextBySuffix(string suffix)
    {
        var asm=Assembly.GetExecutingAssembly();
        var name=asm.GetManifestResourceNames().FirstOrDefault(x =>
            x.EndsWith(suffix.Replace('/','.').Replace('\\','.'),StringComparison.OrdinalIgnoreCase) ||
            x.EndsWith(suffix,StringComparison.OrdinalIgnoreCase));
        if(name==null) throw new FileNotFoundException(suffix);
        using var s=asm.GetManifestResourceStream(name)!;
        using var r=new StreamReader(s,Encoding.UTF8,true);
        return r.ReadToEnd();
    }

    public static IReadOnlyList<string> ResourceNames() => Assembly.GetExecutingAssembly().GetManifestResourceNames();
}