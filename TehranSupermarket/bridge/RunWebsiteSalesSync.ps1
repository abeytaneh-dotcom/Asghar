# Website order -> Pyramid sale worker. Shares mutex with catalog to prevent stock resurrection.
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$dir='D:\TehranBridge\logs'
if(!(Test-Path $dir)){[void](New-Item -Path $dir -ItemType Directory -Force)}
$log=Join-Path $dir ('website-sales-'+(Get-Date).ToString('yyyy-MM-dd')+'.log')
$mutex=$null
try{
    try{
        $mutex=[IO.File]::Open('D:\TehranBridge\pyramid-jobs.lock',[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)
    }catch [IO.IOException]{
        Write-Output 'SALES_CYCLE=SKIPPED_MUTEX_BUSY'
        exit 0
    }
    $lines=@(& 'D:\TehranBridge\PyramidBridge.orders.ps1' -Mode OrderSyncOnce -ConfigPath 'D:\TehranBridge\bridge-config-orders-live.json')
    foreach($line in $lines){
        if($line -match '^(WEBSITE_STATUS|ORDER_ACK_OK|ORDER_HELD|ORDER_AWAITING_PAYMENT|WEBSITE_SALES_POSTING_CYCLE|ORDERS_PREVIEWED)'){
            Add-Content -LiteralPath $log -Value ((Get-Date).ToString('s')+' '+$line) -Encoding UTF8
        }
    }
    if(-not (@($lines)|Where-Object{$_ -like 'WEBSITE_SALES_POSTING_CYCLE=PASS*'})){
        throw 'Pyramid sale cycle lacks verified successful completion marker'
    }
    Write-Output 'WEBSITE_SALES_POSTING_CYCLE=PASS'
}catch{
    Add-Content -LiteralPath $log -Value ((Get-Date).ToString('s')+' WEBSITE_SALES_POSTING_CYCLE=FAIL CLASS='+$_.Exception.GetType().Name) -Encoding UTF8
    Write-Output ('WEBSITE_SALES_POSTING_CYCLE=FAIL CLASS='+$_.Exception.GetType().Name)
    exit 1
}finally{
    if($null -ne $mutex){$mutex.Dispose()}
}
