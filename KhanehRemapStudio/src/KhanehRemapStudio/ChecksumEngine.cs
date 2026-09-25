namespace KhanehRemapStudio;

public interface IChecksumPlugin
{
    string Id { get; }
    string DisplayName { get; }
    bool CanHandle(IdentificationResult id, byte[] data);
    ChecksumReport Verify(byte[] data);
    ChecksumReport Repair(byte[] data);
}

public sealed class ChecksumManager
{
    private readonly List<IChecksumPlugin> _plugins = new()
    {
        new SagemS2000Checksum(),
        new ValeoPl4Checksum(),
        new BoschMe744Checksum(),
        new BoschMe745Checksum(),
        new BoschMe749Checksum(),
        new BoschM7971Checksum(),
        new BoschM7411Checksum(),
        new BoschM797Checksum(),
        new BoschM744CrcChecksum()
    };

    public ChecksumReport Verify(IdentificationResult id, byte[] data)
    {
        var p=_plugins.FirstOrDefault(x=>x.CanHandle(id,data));
        return p?.Verify(data) ?? new ChecksumReport
        {
            Supported=false,Valid=false,Repairable=false,Family=id.FamilyHint,
            Message="برای این خانواده پلاگین چکسام تأییدشده موجود نیست؛ فایل بدون تغییر چکسام ذخیره می‌شود."
        };
    }

    public ChecksumReport Repair(IdentificationResult id, byte[] data)
    {
        var p=_plugins.FirstOrDefault(x=>x.CanHandle(id,data));
        return p?.Repair(data) ?? new ChecksumReport
        {
            Supported=false,Valid=false,Repairable=false,Family=id.FamilyHint,
            Message="چکسام این خانواده پشتیبانی نمی‌شود."
        };
    }
}

internal static class ChecksumMath
{
    public static uint SumLe16(byte[] d,int start,int end)
    {
        ulong sum=0;
        int i=start;
        for(;i+1<end;i+=2) sum+=(uint)(d[i]|(d[i+1]<<8));
        if(i<end) sum+=d[i];
        return unchecked((uint)sum);
    }

    public static ushort Sum8(byte[] d,int start,int end)
    {
        uint sum=0;
        for(int i=start;i<end;i++) sum+=d[i];
        return unchecked((ushort)sum);
    }

    public static uint ReadU32LE(byte[] d,int p) => BitConverter.ToUInt32(d,p);
    public static void WriteU32LE(byte[] d,int p,uint v)
    {
        var b=BitConverter.GetBytes(v);
        Buffer.BlockCopy(b,0,d,p,4);
    }
    public static ushort ReadU16LE(byte[] d,int p) => BitConverter.ToUInt16(d,p);
    public static void WriteU16LE(byte[] d,int p,ushort v)
    {
        var b=BitConverter.GetBytes(v);
        Buffer.BlockCopy(b,0,d,p,2);
    }

    public static bool NameHas(IdentificationResult id,params string[] tokens)
    {
        string n=((id.Exact?.Name ?? id.BestCandidate?.Name) ?? "").ToUpperInvariant();
        return tokens.Any(t=>n.Contains(t.ToUpperInvariant()));
    }

    public static uint Crc32(byte[] d,int start,int end)
    {
        uint crc=0xFFFFFFFFu;
        for(int i=start;i<end;i++)
        {
            crc^=d[i];
            for(int b=0;b<8;b++)
                crc=(crc&1)!=0 ? (crc>>1)^0xEDB88320u : crc>>1;
        }
        return ~crc;
    }

    public static ChecksumReport Report(string id,string family,bool valid,bool repairable,int count,string message) => new()
    {
        PluginId=id,Family=family,Supported=true,Valid=valid,Repairable=repairable,RegionCount=count,Message=message
    };
}

public abstract class WordSumBlockChecksum : IChecksumPlugin
{
    public abstract string Id { get; }
    public abstract string DisplayName { get; }
    protected abstract (int Start,int End)[] Ranges { get; }
    protected abstract int[] StorageOffsets { get; }
    public abstract bool CanHandle(IdentificationResult id,byte[] data);

    public ChecksumReport Verify(byte[] data)
    {
        if(!Ranges.All(r=>r.Start>=0 && r.End<=data.Length) || StorageOffsets.Any(p=>p<0 || p+4>data.Length))
            return ChecksumMath.Report(Id,DisplayName,false,false,0,"اندازه فایل با این پلاگین سازگار نیست.");

        bool ok=true;
        for(int i=0;i<Ranges.Length;i++)
        {
            var r=Ranges[i];
            uint calc=ChecksumMath.SumLe16(data,r.Start,r.End);
            uint stored=ChecksumMath.ReadU32LE(data,StorageOffsets[i]);
            if(calc!=stored) ok=false;
        }
        return ChecksumMath.Report(Id,DisplayName,ok,true,Ranges.Length,
            ok ? $"چکسام {Ranges.Length} بلوک صحیح است." : $"چکسام یک یا چند بلوک نادرست است؛ قابلیت اصلاح خودکار فعال است.");
    }

    public ChecksumReport Repair(byte[] data)
    {
        if(!Ranges.All(r=>r.Start>=0 && r.End<=data.Length) || StorageOffsets.Any(p=>p<0 || p+4>data.Length))
            return ChecksumMath.Report(Id,DisplayName,false,false,0,"اندازه فایل با این پلاگین سازگار نیست.");

        for(int i=0;i<Ranges.Length;i++)
        {
            var r=Ranges[i];
            ChecksumMath.WriteU32LE(data,StorageOffsets[i],ChecksumMath.SumLe16(data,r.Start,r.End));
        }
        var v=Verify(data);
        v.Message=v.Valid ? $"چکسام {Ranges.Length} بلوک محاسبه و در فایل نوشته شد." : "اصلاح چکسام ناموفق بود.";
        return v;
    }
}

public sealed class BoschMe744Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-me744-wordsum-v1";
    public override string DisplayName=>"Bosch ME7.4.4";
    protected override (int,int)[] Ranges=>new[]
    {
        (65536,81920),(81920,98152),(98304,114688),(114688,131052),(131072,147456),(147456,163840),
        (163840,180224),(180224,196608),(196608,212992),(212992,229376),(229376,245760),(245760,262144)
    };
    protected override int[] StorageOffsets=>Enumerable.Range(0,12).Select(i=>98408+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=262144 && ChecksumMath.NameHas(id,"ME-7-4-4","ME7.4.4") && !ChecksumMath.NameHas(id,"M7.4.4");
}

public sealed class BoschMe745Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-me745-wordsum-v1";
    public override string DisplayName=>"Bosch ME7.4.5";
    protected override (int,int)[] Ranges=>new[]
    {
        (532480,540672),(540672,557056),(557056,573440),(573440,589824),(589824,606208),(606208,622592),
        (622592,638976),(638976,655360),(655360,671744),(671744,688128),(688372,704512),(704512,720896),
        (720896,737280),(737280,753664),(753664,770048),(770048,786388)
    };
    protected override int[] StorageOffsets=>Enumerable.Range(0,16).Select(i=>786140+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=786432 && ChecksumMath.NameHas(id,"ME7.4.5","ME-7-4-5");
}

public sealed class BoschMe749Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-me749-wordsum-v1";
    public override string DisplayName=>"Bosch ME7.4.9";
    protected override (int,int)[] Ranges=>new[]
    {
        (524288,540672),(540672,557056),(557056,573440),(573440,589824),(589824,606208),(606208,622592),
        (622592,638976),(638976,655360),(655360,671744),(671744,688128),(688128,704512),(704512,719084),
        (720896,737280),(737280,753664),(753664,770048),(770048,786432)
    };
    protected override int[] StorageOffsets=>Enumerable.Range(0,16).Select(i=>720358+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=786432 && ChecksumMath.NameHas(id,"ME7.4.9","ME-7-4-9");
}

public sealed class BoschM7971Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-m7971-wordsum-v1";
    public override string DisplayName=>"Bosch M7.9.7.1";
    protected override (int,int)[] Ranges=>new[]{(655360,671744),(671744,687936),(688128,704512),(704512,720896)};
    protected override int[] StorageOffsets=>Enumerable.Range(0,4).Select(i=>686728+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=720896 && ChecksumMath.NameHas(id,"M7.9.7.1");
}

public sealed class BoschM7411Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-m7411-wordsum-v1";
    public override string DisplayName=>"Bosch M7.4.11";
    protected override (int,int)[] Ranges=>new[]{(622592,638976),(638976,655360),(655360,671744),(671744,688128)};
    protected override int[] StorageOffsets=>Enumerable.Range(0,4).Select(i=>720454+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=720896 && ChecksumMath.NameHas(id,"M7.4.11");
}

public sealed class BoschM797Checksum : WordSumBlockChecksum
{
    public override string Id=>"bosch-m797-wordsum-v1";
    public override string DisplayName=>"Bosch M7.9.7";
    protected override (int,int)[] Ranges=>new[]{(655360,671744),(671744,687372),(688128,704512),(704512,720896)};
    protected override int[] StorageOffsets=>Enumerable.Range(0,4).Select(i=>686226+i*16).ToArray();
    public override bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length==786432 && ChecksumMath.NameHas(id,"M7.9.7") && !ChecksumMath.NameHas(id,"M7.9.7.1","ME17.9.7");
}

public sealed class SagemS2000Checksum : IChecksumPlugin
{
    public string Id=>"sagem-s2000-add16-v1";
    public string DisplayName=>"Sagem S2000";
    public bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=261570 && (id.Vendor.Equals("sagem",StringComparison.OrdinalIgnoreCase) || ChecksumMath.NameHas(id,"S2000"));

    public ChecksumReport Verify(byte[] d)
    {
        ushort c1=ChecksumMath.Sum8(d,32896,65472), s1=ChecksumMath.ReadU16LE(d,65472);
        ushort c2=ChecksumMath.Sum8(d,65536,261568), s2=ChecksumMath.ReadU16LE(d,261568);
        bool ok=c1==s1 && c2==s2;
        return ChecksumMath.Report(Id,DisplayName,ok,true,2,ok?"هر دو چکسام صحیح است.":"چکسام نیاز به اصلاح دارد.");
    }
    public ChecksumReport Repair(byte[] d)
    {
        ChecksumMath.WriteU16LE(d,65472,ChecksumMath.Sum8(d,32896,65472));
        ChecksumMath.WriteU16LE(d,261568,ChecksumMath.Sum8(d,65536,261568));
        var r=Verify(d); r.Message=r.Valid?"دو چکسام S2000 اصلاح شد.":"اصلاح ناموفق بود."; return r;
    }
}

public sealed class ValeoPl4Checksum : IChecksumPlugin
{
    public string Id=>"valeo-pl4-add16-v1";
    public string DisplayName=>"Valeo PL4 / J34";
    public bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=524288 && (ChecksumMath.NameHas(id,"PL4","J34") || (id.Vendor.Equals("valeo",StringComparison.OrdinalIgnoreCase) && data.Length==524288));

    public ChecksumReport Verify(byte[] d)
    {
        ushort c=ChecksumMath.Sum8(d,468998,524288), s=ChecksumMath.ReadU16LE(d,468996);
        bool ok=c==s;
        return ChecksumMath.Report(Id,DisplayName,ok,true,1,ok?"چکسام صحیح است.":"چکسام نیاز به اصلاح دارد.");
    }
    public ChecksumReport Repair(byte[] d)
    {
        ChecksumMath.WriteU16LE(d,468996,ChecksumMath.Sum8(d,468998,524288));
        var r=Verify(d); r.Message=r.Valid?"چکسام Valeo اصلاح شد.":"اصلاح ناموفق بود."; return r;
    }
}

public sealed class BoschM744CrcChecksum : IChecksumPlugin
{
    public string Id=>"bosch-m744-crc32-v1";
    public string DisplayName=>"Bosch M7.4.4 CRC32";
    public bool CanHandle(IdentificationResult id,byte[] data) =>
        data.Length>=131070 && ChecksumMath.NameHas(id,"M7.4.4") && !ChecksumMath.NameHas(id,"ME7.4.4","ME-7-4-4");

    public ChecksumReport Verify(byte[] d)
    {
        uint calc=ChecksumMath.Crc32(d,65536,131054);
        uint stored=ChecksumMath.ReadU32LE(d,131066);
        bool ok=calc==stored;
        return ChecksumMath.Report(Id,DisplayName,ok,true,1,ok?"CRC32 صحیح است.":"CRC32 نیاز به اصلاح دارد.");
    }
    public ChecksumReport Repair(byte[] d)
    {
        uint calc=ChecksumMath.Crc32(d,65536,131054);
        ChecksumMath.WriteU32LE(d,131066,calc);
        var r=Verify(d); r.Message=r.Valid?"CRC32 اصلاح شد.":"اصلاح CRC32 ناموفق بود."; return r;
    }
}