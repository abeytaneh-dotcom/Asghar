# Compare exact identifiers in live site catalogue against local Pyramid snapshot.
# NEVER writes site data, Pyramid SQL, or product mappings.
[CmdletBinding()]
param(
  [string]$WorkDir='D:\TehranBridge',
  [string]$SiteKeyFile='D:\TehranBridge\site_sync_key.txt',
  [string]$FixtureFile=''
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
$snapFile=Join-Path $WorkDir 'pyramid-products-readonly.json'
if (-not (Test-Path $snapFile)) {throw 'POS snapshot is missing'}
$local=Get-Content $snapFile -Raw -Encoding UTF8 | ConvertFrom-Json
if($local.summary.status -ne 'READ_ONLY_DRY_RUN') {throw 'POS snapshot invalid'}
if($FixtureFile -ne '') {
  $site=Get-Content $FixtureFile -Raw -Encoding UTF8 | ConvertFrom-Json
  Write-Output 'SITE_SOURCE=LOCAL_TEST_FIXTURE'
} else {
  if(-not (Test-Path $SiteKeyFile)) {Write-Output 'SITE_MAPPING=DISABLED_NO_SITE_SECRET'; return}
  $key=(Get-Content $SiteKeyFile -Raw).Trim()
  if($key.Length -lt 32) {throw 'Site sync key invalid'}
  $site=Invoke-RestMethod -Method Get -Uri 'https://tehranmarket-baharestan.ir/pyramid_api.php?action=products' -TimeoutSec 45 -Headers @{'X-Sync-Key'=$key;'X-Device-ID'='EXSOM-J3-TEHRAN-BRIDGE'}
  Write-Output 'SITE_SOURCE=AUTHENTICATED_LIVE_API'
}
if($null -eq $site -or $site.ok -ne $true -or $null -eq $site.products) {throw 'Invalid site product catalogue'}
$barcodeIndex=@{}
$codeIndex=@{}
$siteIds=@{}
foreach($pos in @($local.products)) {
  if($pos.sync_eligible -ne $true) {continue}
  $barcode=([string]$pos.barcode).Trim()
  $code=([string]$pos.k_code).Trim()
  if($barcode -ne '') {
    if($barcodeIndex.ContainsKey($barcode)) {$barcodeIndex[$barcode]=$null} else {$barcodeIndex[$barcode]=$pos}
  }
  if($code -ne '') {
    if($codeIndex.ContainsKey($code)) {$codeIndex[$code]=$null} else {$codeIndex[$code]=$pos}
  }
}
$matched=0; $missing=0; $conflict=0
$siteDemoStock10=0; $siteStockVsPosDifferent=0
$siteEqualsRaw=0; $siteTimesTenEqualsRaw=0; $pricesOther=0
$reportRows=New-Object 'System.Collections.Generic.List[object]'
foreach($p in @($site.products)){
  $id=[string]$p.id; $barcode=([string]$p.barcode).Trim(); $code=([string]$p.pyramid_code2).Trim()
  if($siteIds.ContainsKey($id)) {$conflict++;continue}
  $siteIds[$id]=$true
  if([decimal]$p.stock -eq 10) {$siteDemoStock10++}
  $byBar=$null; $byCode=$null
  if($barcode -ne '' -and $barcodeIndex.ContainsKey($barcode)) {$byBar=$barcodeIndex[$barcode]}
  if($code -ne '' -and $codeIndex.ContainsKey($code)) {$byCode=$codeIndex[$code]}
  if($null -eq $byBar -and $null -eq $byCode) {$missing++;continue}
  if(($null -ne $byBar -and $null -ne $byCode -and $byBar.k_code -ne $byCode.k_code) -or
     ($null -ne $byBar -and $barcode -ne $byBar.barcode)) {$conflict++;continue}
  $pos=if($null -ne $byBar){$byBar}else{$byCode}
  if($null -eq $pos) {$conflict++;continue}
  $matched++
  $sitePrice=[decimal]$p.price
  $posPrice=[decimal]$pos.price_forosh_raw
  if($sitePrice -gt 0 -and $posPrice -gt 0){
    if($sitePrice -eq $posPrice){$siteEqualsRaw++}
    elseif(($sitePrice * 10) -eq $posPrice){$siteTimesTenEqualsRaw++}
    else{$pricesOther++}
  }
  if([decimal]$p.stock -ne [decimal]$pos.stock_available) {$siteStockVsPosDifferent++}
  $reportRows.Add([pscustomobject]@{
    site_id=$id
    match_method=if($null -ne $byBar){'barcode_exact'}else{'code2_exact'}
    pyramid_code=$pos.k_code
    site_price_toman=$sitePrice
    pyramid_price_raw=$posPrice
    site_stock=$p.stock
    pyramid_available=$pos.stock_available
  })
}
$summary=[ordered]@{
  status='PREVIEW_NO_WRITES'
  site_products=@($site.products).Count
  pyramid_warehouse_rows=@($local.products).Count
  matched_exact=$matched
  site_unmatched=$missing
  site_ambiguous=$conflict
  site_stock_equal_10=$siteDemoStock10
  site_stock_differs_from_pyramid=$siteStockVsPosDifferent
  price_same_as_pyramid_raw=$siteEqualsRaw
  price_site_toman_times_10_equals_pyramid_raw=$siteTimesTenEqualsRaw
  price_disagreements_other=$pricesOther
  price_unit_confirmed=$false
  site_written=$false
  pyramid_written=$false
}
$outFile=Join-Path $WorkDir 'site-product-match-preview.json'
$tmp=Join-Path $WorkDir ('match-'+[guid]::NewGuid().ToString('N')+'.tmp')
$utf8=New-Object System.Text.UTF8Encoding($false)
try {
  [IO.File]::WriteAllText($tmp,(ConvertTo-Json -InputObject ([pscustomobject]@{summary=$summary;matches=$reportRows.ToArray()}) -Depth 5 -Compress),$utf8)
  if(Test-Path $outFile){$bak=Join-Path $WorkDir ('match-backup-'+[guid]::NewGuid().ToString('N')+'.bak');[IO.File]::Replace($tmp,$outFile,$bak);Remove-Item $bak -Force}
  else{[IO.File]::Move($tmp,$outFile)}
} finally {if(Test-Path $tmp){Remove-Item $tmp -Force}}
Write-Output ('SITE_PRODUCT_COUNT='+$summary.site_products)
Write-Output ('EXACT_MATCHED='+$matched)
Write-Output ('UNMATCHED='+$missing)
Write-Output ('AMBIGUOUS='+$conflict)
Write-Output ('STOCK_DIFFERS='+$siteStockVsPosDifferent)
Write-Output ('RAW_PRICE_EQUALS_SITE_PRICE='+$siteEqualsRaw)
Write-Output ('RAW_PRICE_EQUALS_TEN_TIMES_SITE='+$siteTimesTenEqualsRaw)
Write-Output 'CATALOGUE_UPDATE=NO'
Write-Output ('PREVIEW_FILE='+$outFile)
