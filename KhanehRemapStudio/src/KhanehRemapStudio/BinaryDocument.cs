using SharpCompress.Archives;

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
    private readonly DumpCatalog _catalog = new();
    private readonly string _localDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"KhanehRemapStudio");
    private string LocalCatalogPath => Path.Combine(_localDir,"dump_catalog.psv");

    public int Count => _catalog.Items.Count;
    public IReadOnlyDictionary<string,int> Vendors => _catalog.Vendors;

    public DumpCatalogService()
    {
        Directory.CreateDirectory(_localDir);
        LoadBuiltIns();
        LoadPsvIfExists(LocalCatalogPath);

        string besideExe=Path.Combine(AppContext.BaseDirectory,"Data","dump_catalog.psv");
        LoadPsvIfExists(besideExe);

        string autoFolder=Path.Combine(AppContext.BaseDirectory,"Domp");
        if(Directory.Exists(autoFolder) && !_catalog.Items.Any(x=>x.SourceTag()=="auto-folder"))
        {
            try { IndexFolder(autoFolder,false); } catch { }
        }
        RefreshVendorCounts();
    }

    public IdentificationResult Identify(byte[] data,string sha256)
    {
        var exact=_catalog.Items.FirstOrDefault(x=>x.Size==data.LongLength && x.Sha256.Equals(sha256,StringComparison.OrdinalIgnoreCase));
        if(exact!=null) return new IdentificationResult{Exact=exact,Similarity=1};

        var fp=Util.BlockFingerprints(data,16);
        DumpCatalogItem? best=null; double score=0;
        foreach(var item in _catalog.Items)
        {
            if(item.Size!=data.LongLength || item.Blocks.Count!=16) continue;
            double s=Util.FingerprintSimilarity(fp,item.Blocks);
            if(s>score){score=s;best=item;}
        }
        return new IdentificationResult{BestCandidate=best,Similarity=score};
    }

    public int IndexArchive(string archivePath,bool persist=true)
    {
        if(!File.Exists(archivePath)) throw new FileNotFoundException(archivePath);
        var exts=new HashSet<string>(StringComparer.OrdinalIgnoreCase){".bin",".ori",".mod",".rom",".dump"};
        int added=0;

        using var archive=ArchiveFactory.Open(archivePath);
        foreach(var entry in archive.Entries.Where(e=>!e.IsDirectory))
        {
            string key=(entry.Key ?? "").Replace('\\','/');
            if(!exts.Contains(Path.GetExtension(key))) continue;

            byte[] data;
            try
            {
                using var input=entry.OpenEntryStream();
                using var ms=new MemoryStream();
                input.CopyTo(ms);
                data=ms.ToArray();
            }
            catch { continue; }

            if(data.Length==0) continue;
            string sha=Util.Sha256(data);
            if(_catalog.Items.Any(x=>x.Sha256.Equals(sha,StringComparison.OrdinalIgnoreCase))) continue;

            string[] seg=key.Split('/',StringSplitOptions.RemoveEmptyEntries);
            string vendor=seg.Length>1 ? seg[^2] : "unknown";
            if(key.Contains("/Domp/",StringComparison.OrdinalIgnoreCase) || key.StartsWith("Domp/",StringComparison.OrdinalIgnoreCase))
            {
                int di=Array.FindIndex(seg,x=>x.Equals("Domp",StringComparison.OrdinalIgnoreCase));
                if(di>=0 && di+1<seg.Length) vendor=seg[di+1];
            }

            _catalog.Items.Add(new DumpCatalogItem
            {
                Name=Path.GetFileName(key),Path=key,Vendor=vendor,Size=data.LongLength,Sha256=sha,
                Blocks=Util.BlockFingerprints(data,16)
            });
            added++;
        }

        RefreshVendorCounts();
        if(persist) SaveLocalCatalog();
        return added;
    }

    public int IndexFolder(string folder,bool persist=true)
    {
        if(!Directory.Exists(folder)) throw new DirectoryNotFoundException(folder);
        var exts=new HashSet<string>(StringComparer.OrdinalIgnoreCase){".bin",".ori",".mod",".rom",".dump"};
        int added=0;
        foreach(var file in Directory.EnumerateFiles(folder,"*.*",SearchOption.AllDirectories))
        {
            if(!exts.Contains(Path.GetExtension(file))) continue;
            byte[] data;
            try { data=File.ReadAllBytes(file); } catch { continue; }
            if(data.Length==0) continue;
            string sha=Util.Sha256(data);
            if(_catalog.Items.Any(x=>x.Sha256.Equals(sha,StringComparison.OrdinalIgnoreCase))) continue;
            string rel=Path.GetRelativePath(folder,file).Replace('\\','/');
            string vendor=rel.Split('/').FirstOrDefault() ?? "unknown";
            _catalog.Items.Add(new DumpCatalogItem
            {
                Name=Path.GetFileName(file),Path=rel,Vendor=vendor,Size=data.LongLength,Sha256=sha,
                Blocks=Util.BlockFingerprints(data,16)
            });
            added++;
        }
        RefreshVendorCounts();
        if(persist) SaveLocalCatalog();
        return added;
    }

    private void SaveLocalCatalog()
    {
        var sb=new System.Text.StringBuilder();
        foreach(var x in _catalog.Items)
        {
            string blocks=x.Blocks.Count==16?string.Join(',',x.Blocks):"";
            sb.Append(x.Sha256).Append('|').Append(x.Size).Append('|').Append(x.Vendor.Replace("|","_")).Append('|')
              .Append(x.Path.Replace("|","_")).Append('|').Append(blocks).AppendLine();
        }
        File.WriteAllText(LocalCatalogPath,sb.ToString(),System.Text.Encoding.UTF8);
    }

    private void LoadPsvIfExists(string path)
    {
        if(!File.Exists(path)) return;
        foreach(var line in File.ReadLines(path))
        {
            if(string.IsNullOrWhiteSpace(line) || line.StartsWith("#")) continue;
            var p=line.Split('|'); if(p.Length<4) continue;
            if(!long.TryParse(p[1],out long size)) continue;
            string sha=p[0].Trim(); if(sha.Length!=64) continue;
            if(_catalog.Items.Any(x=>x.Sha256.Equals(sha,StringComparison.OrdinalIgnoreCase))) continue;
            _catalog.Items.Add(new DumpCatalogItem
            {
                Sha256=sha,Size=size,Vendor=p[2],Path=p[3],Name=Path.GetFileName(p[3]),
                Blocks=p.Length>4 && p[4].Length>0 ? p[4].Split(',',StringSplitOptions.RemoveEmptyEntries).ToList() : new()
            });
        }
    }

    private void LoadBuiltIns()
    {
        void Add(string sha,long size,string vendor,string path)
            => _catalog.Items.Add(new DumpCatalogItem{Sha256=sha,Size=size,Vendor=vendor,Path=path,Name=Path.GetFileName(path)});
        Add("2590c8c0351bc53ab870499d3c696bfd4ee34df618c184158254ff5e619175b1",524288,"siemens","siemens/Pride_Bifuel(CB7).bin");
        Add("207891c86bdb5529a85ba7e5805b9ea8870379e9dbc4fe21862675c31452882d",524288,"siemens","siemens/Pride(Immo)-Bifuel.bin");
        Add("2502c3f384c8389eb147684c9d8eabc5747bbfa3fad9741304816ff61b006d31",524288,"siemens","siemens/Pride_Bifuel(XC80MP02)(CA5).bin");
        Add("cdbbf725920a26937a647fc8cc7dfc7bb2cdd54ef9048f8ee88e00520ed94a0f",524288,"siemens","siemens/Pride_Bifuel(CB7)(SC800PM1).bin");
        Add("e7db6cfb7bb501d1e6a5ca8681bb95c56aa4e6461181e70f0b6f63197d02604e",524288,"siemens","siemens/Pride_Bifuel_ICU2(CA2).bin");
    }

    private void RefreshVendorCounts()
    {
        _catalog.Count=_catalog.Items.Count;
        _catalog.Vendors=_catalog.Items.GroupBy(x=>x.Vendor,StringComparer.OrdinalIgnoreCase).ToDictionary(g=>g.Key,g=>g.Count(),StringComparer.OrdinalIgnoreCase);
    }
}

internal static class DumpItemExtensions
{
    public static string SourceTag(this DumpCatalogItem item) => "";
}
