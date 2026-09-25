using System.Text.Json;

namespace KhanehRemapStudio;

public sealed class ProfileStore
{
    private readonly string _dir=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"KhanehRemapStudio","Profiles");
    private readonly JsonSerializerOptions _json=new(){WriteIndented=true,PropertyNameCaseInsensitive=true};

    public ProfileStore(){Directory.CreateDirectory(_dir);}

    public CalibrationProfile? FindExact(string sha256)
    {
        string path=Path.Combine(_dir,SafeHash(sha256)+".json");
        if(!File.Exists(path)) return null;
        try{return JsonSerializer.Deserialize<CalibrationProfile>(File.ReadAllText(path),_json);}catch{return null;}
    }

    public void SaveForDump(CalibrationProfile profile,string sha256,long size,string? source=null)
    {
        profile.Sha256=sha256;
        profile.FileSize=size;
        if(!string.IsNullOrWhiteSpace(source)) profile.Source=source;
        if(string.IsNullOrWhiteSpace(profile.ProfileName)) profile.ProfileName="Local "+sha256[..Math.Min(10,sha256.Length)];
        string path=Path.Combine(_dir,SafeHash(sha256)+".json");
        File.WriteAllText(path,JsonSerializer.Serialize(profile,_json));
    }

    public int CountProfiles() => Directory.Exists(_dir) ? Directory.EnumerateFiles(_dir,"*.json").Count() : 0;

    private static string SafeHash(string s)=>new string((s??"").Where(Uri.IsHexDigit).ToArray()).ToLowerInvariant();
}