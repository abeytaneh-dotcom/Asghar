# Protected catalog worker. Shared filesystem mutex prevents overlap with POS sale processing.
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$dir='D:\TehranBridge\logs'
if(!(Test-Path $dir)){[void](New-Item -ItemType Directory -Path $dir -Force)}
$log=Join-Path $dir ('catalog-'+(Get-Date).ToString('yyyy-MM-dd')+'.log')
$lockPath='D:\TehranBridge\pyramid-jobs.lock'
$started=[DateTime]::UtcNow
$mutex=$null
try{
    try{
        $mutex=[IO.File]::Open($lockPath,[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)
    }catch [IO.IOException] {
        Write-Output 'CATALOG_CYCLE=SKIPPED_MUTEX_BUSY'
        exit 0
    }
    $result=@(& 'D:\TehranBridge\PyramidBridge.ps1' -Mode CatalogSyncOnce -ConfigPath 'D:\TehranBridge\bridge-config-catalog-live.json')
    if(-not (@($result) | Where-Object {$_ -eq 'CATALOG_SYNC_COMPLETED_WITHOUT_POS_SALES=YES'})){
        throw 'Missing successful atomic catalog completion marker'
    }
    foreach($line in $result){
        if($line -match '^(AUDIT_DB|WEBSITE_STATUS|ATOMIC_CATALOG_FINALIZED|CATALOG_SYNC_COMPLETED)'){
            Add-Content -LiteralPath $log -Value ((Get-Date).ToString('s')+' '+$line) -Encoding UTF8
        }
    }
    Write-Output ('CATALOG_SYNC_STATUS=PASS DURATION_SECONDS='+[int]([DateTime]::UtcNow-$started).TotalSeconds)
}catch{
    Add-Content -LiteralPath $log -Value ((Get-Date).ToString('s')+' CATALOG_SYNC_STATUS=FAIL CLASS='+$_.Exception.GetType().Name) -Encoding UTF8
    Write-Output ('CATALOG_SYNC_STATUS=FAIL CLASS='+$_.Exception.GetType().Name)
    exit 1
}finally{
    if($null -ne $mutex){$mutex.Dispose()}
}
