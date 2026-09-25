using System.Security.Cryptography;

namespace KhanehRemapStudio;

public sealed class BinaryDocument
{
    public string Path { get; private set; } = "";
    public byte[] Original { get; private set; } = Array.Empty<byte>();
    public byte[] Working { get; private set; } = Array.Empty<byte>();
    public string Sha256 { get; private set; } = "";

    public bool HasFile => Working.Length > 0;
    public int Length => Working.Length;

    public void Load(string path)
    {
        var bytes=File.ReadAllBytes(path);
        if(bytes.Length==0) throw new InvalidDataException("فایل خالی است.");
        Path=path;
        Original=bytes;
        Working=bytes.ToArray();
        Sha256=Util.Sha256(bytes);
    }

    public void Reset() => Working=Original.ToArray();

    public byte[] GetBytes(int address,int length)
    {
        if(address<0 || length<0 || address+length>Working.Length) throw new ArgumentOutOfRangeException();
        return Working.AsSpan(address,length).ToArray();
    }

    public void PutBytes(int address,byte[] bytes)
    {
        if(address<0 || address+bytes.Length>Working.Length) throw new ArgumentOutOfRangeException();
        Buffer.BlockCopy(bytes,0,Working,address,bytes.Length);
    }

    public long ReadRaw(int address,string type,string endian)
    {
        int size=Util.TypeSize(type);
        if(address<0 || address+size>Working.Length) throw new ArgumentOutOfRangeException();
        bool be=endian.Equals("big",StringComparison.OrdinalIgnoreCase);
        return type.ToLowerInvariant() switch
        {
            "uint8"=>Working[address],
            "int8"=>unchecked((sbyte)Working[address]),
            "uint16"=>be ? (ushort)((Working[address]<<8)|Working[address+1]) : BitConverter.ToUInt16(Working,address),
            "int16"=>be ? unchecked((short)((Working[address]<<8)|Working[address+1])) : BitConverter.ToInt16(Working,address),
            "uint32"=>be ? ((long)Working[address]<<24)|((long)Working[address+1]<<16)|((long)Working[address+2]<<8)|Working[address+3] : BitConverter.ToUInt32(Working,address),
            "int32"=>be ? unchecked((int)(((uint)Working[address]<<24)|((uint)Working[address+1]<<16)|((uint)Working[address+2]<<8)|Working[address+3])) : BitConverter.ToInt32(Working,address),
            _=>throw new InvalidOperationException(type)
        };
    }

    public double ReadScaled(int address,MapDefinition map) => ReadRaw(address,map.DataType,map.Endian)*map.Factor+map.Offset;

    public byte[] WriteScaled(int address,MapDefinition map,double scaled)
    {
        if(Math.Abs(map.Factor)<1e-15) throw new InvalidOperationException("Factor صفر است.");
        long raw=checked((long)Math.Round((scaled-map.Offset)/map.Factor,MidpointRounding.AwayFromZero));
        return WriteRaw(address,map.DataType,map.Endian,raw);
    }

    public byte[] WriteRaw(int address,string type,string endian,long value)
    {
        int size=Util.TypeSize(type);
        var old=GetBytes(address,size);
        byte[] b=type.ToLowerInvariant() switch
        {
            "uint8"=>new[]{checked((byte)value)},
            "int8"=>new[]{unchecked((byte)checked((sbyte)value))},
            "uint16"=>BitConverter.GetBytes(checked((ushort)value)),
            "int16"=>BitConverter.GetBytes(checked((short)value)),
            "uint32"=>BitConverter.GetBytes(checked((uint)value)),
            "int32"=>BitConverter.GetBytes(checked((int)value)),
            _=>throw new InvalidOperationException(type)
        };
        if(endian.Equals("big",StringComparison.OrdinalIgnoreCase) && b.Length>1) Array.Reverse(b);
        PutBytes(address,b);
        return old;
    }

    public int ChangedByteCount()
    {
        int n=Math.Min(Original.Length,Working.Length), c=0;
        for(int i=0;i<n;i++) if(Original[i]!=Working[i]) c++;
        return c+Math.Abs(Original.Length-Working.Length);
    }

    public List<(int Start,int End)> ChangedRanges(int max=200)
    {
        var result=new List<(int,int)>();
        int i=0,n=Math.Min(Original.Length,Working.Length);
        while(i<n && result.Count<max)
        {
            while(i<n && Original[i]==Working[i]) i++;
            if(i>=n) break;
            int s=i;
            while(i+1<n && Original[i+1]!=Working[i+1]) i++;
            result.Add((s,i)); i++;
        }
        return result;
    }

    public bool IsChanged(int address,int length)
    {
        if(address<0 || address+length>Working.Length) return false;
        for(int i=0;i<length;i++) if(Original[address+i]!=Working[address+i]) return true;
        return false;
    }

    public string SaveWithBackup(string path)
    {
        if(!HasFile) throw new InvalidOperationException("فایلی باز نشده است.");
        if(File.Exists(path))
        {
            string backup=path+".bak-"+DateTime.Now.ToString("yyyyMMdd-HHmmss");
            File.Copy(path,backup,true);
        }
        File.WriteAllBytes(path,Working);
        return Util.Sha256(Working);
    }
}

public sealed class DumpCatalogService
{
    private readonly DumpCatalog _catalog;

    public int Count => _catalog.Items.Count;
    public IReadOnlyDictionary<string,int> Vendors => _catalog.Vendors;

    public DumpCatalogService()
    {
        _catalog=LoadCatalog();
    }

    public IdentificationResult Identify(byte[] data,string sha256)
    {
        var exact=_catalog.Items.FirstOrDefault(x =>
            x.Size==data.LongLength && x.Sha256.Equals(sha256,StringComparison.OrdinalIgnoreCase));
        if(exact!=null) return new IdentificationResult { Exact=exact, Similarity=1.0 };

        var fp=Util.BlockFingerprints(data,16);
        DumpCatalogItem? best=null;
        double score=0;
        foreach(var item in _catalog.Items)
        {
            if(item.Size!=data.LongLength || item.Blocks.Count==0) continue;
            double s=Util.FingerprintSimilarity(fp,item.Blocks);
            if(s>score){score=s;best=item;}
        }
        return new IdentificationResult { BestCandidate=best, Similarity=score };
    }

    private static DumpCatalog LoadCatalog()
    {
        string json=EmbeddedData.ReadTextBySuffix("Data.DumpCatalog.json");
        return System.Text.Json.JsonSerializer.Deserialize<DumpCatalog>(json,
            new System.Text.Json.JsonSerializerOptions{PropertyNameCaseInsensitive=true})
            ?? new DumpCatalog();
    }
}