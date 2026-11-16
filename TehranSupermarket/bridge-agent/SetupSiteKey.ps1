# Run this script interactively on the Pyramid PC. Never paste the key in chat.
[CmdletBinding()]
param([string]$WorkDir='D:\TehranBridge')
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
$keyPath=Join-Path $WorkDir 'site_sync_key.txt'
$cutoffPath=Join-Path $WorkDir 'site_orders_start_after_id.txt'
if (-not (Test-Path $WorkDir -PathType Container)) { throw 'Bridge directory is missing' }
if (Test-Path $keyPath) { Write-Output 'KEY_ALREADY_CONFIGURED=YES'; return }
Write-Host 'Enter the site sync key stored in protected cPanel storage. Characters are hidden.'
$secure=Read-Host 'Site Sync Key' -AsSecureString
$ptr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try { $key=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
if ([string]::IsNullOrWhiteSpace($key) -or $key.Trim().Length -lt 32) { throw 'Site sync key too short' }
$key=$key.Trim()
$header=@{'X-Sync-Key'=$key;'X-Device-ID'='EXSOM-J3-TEHRAN-BRIDGE'}
$base='https://tehranmarket-baharestan.ir/pyramid_api.php'
$status=Invoke-RestMethod -Method Get -Uri ($base+'?action=status') -TimeoutSec 25 -Headers $header
if ($status.ok -ne $true -or $status.api_version -ne 2) { throw 'Site API authentication or compatibility check failed' }
$pending=Invoke-RestMethod -Method Get -Uri ($base+'?action=orders&limit=500') -TimeoutSec 25 -Headers $header
if ($pending.ok -ne $true) { throw 'Site orders query authentication failed' }
$max=0L
foreach($order in @($pending.orders)) {
  if ([string]$order.id -match '^[0-9]{1,13}$' -and [long]$order.id -gt $max) { $max=[long]$order.id }
}
$utf8=New-Object System.Text.UTF8Encoding($false)
# This path's ACL allows only the operator, Administrators and SYSTEM.
[System.IO.File]::WriteAllText($keyPath,$key,$utf8)
[System.IO.File]::WriteAllText($cutoffPath,[string]$max,$utf8)
Write-Output 'SITE_KEY_VERIFIED=YES'
Write-Output ('EXISTING_UNACKNOWLEDGED_SITE_ORDERS=' + @($pending.orders).Count)
Write-Output 'OLD_SITE_ORDERS_SKIPPED=YES'
Write-Output 'ORDER_INBOX_READY_FOR_NEW_ORDERS=YES'
