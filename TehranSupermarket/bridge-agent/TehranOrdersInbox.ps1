# Tehran Market printable order inbox. Reads orders only; never writes Pyramid SQL.
[CmdletBinding()]
param(
  [string]$ApiUrl='https://tehranmarket-baharestan.ir/pyramid_api.php?action=orders&limit=100',
  [string]$SecretFile='D:\TehranBridge\site_sync_key.txt',
  [string]$WorkDir='D:\TehranBridge',
  [string]$FixtureFile=''
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
function E([object]$v) { return [System.Net.WebUtility]::HtmlEncode([string]$v) }
if (-not (Test-Path $WorkDir -PathType Container)) { throw 'Bridge work directory missing' }
if ($FixtureFile -ne '') {
  if (-not (Test-Path $FixtureFile)) { throw 'Fixture missing' }
  $reply=Get-Content -LiteralPath $FixtureFile -Encoding UTF8 -Raw | ConvertFrom-Json
  Write-Output 'ORDER_SOURCE=LOCAL_TEST_FIXTURE'
} else {
  if ($ApiUrl -notmatch '^https://tehranmarket-baharestan\.ir/pyramid_api\.php\?action=orders(&limit=[0-9]{1,3})?$') { throw 'Unapproved host or API action' }
  if (-not (Test-Path $SecretFile -PathType Leaf)) {
    Write-Output 'ORDER_PULL=DISABLED_NO_SITE_SECRET'
    return
  }
  $cutoffPath=Join-Path $WorkDir 'site_orders_start_after_id.txt'
  if (-not (Test-Path $cutoffPath)) {
    Write-Output 'ORDER_PULL=DISABLED_START_WATERMARK_NOT_SET'
    return
  }
  $cutoffText=(Get-Content -LiteralPath $cutoffPath -Raw).Trim()
  if ($cutoffText -notmatch '^[0-9]{1,13}$') { throw 'Invalid order sync watermark' }
  $cutoff=[long]$cutoffText
  $secret=(Get-Content -LiteralPath $SecretFile -Raw).Trim()
  if ($secret.Length -lt 32) { throw 'Site key is invalid' }
  $headers=@{'X-Sync-Key'=$secret;'X-Device-ID'='EXSOM-J3-TEHRAN-BRIDGE'}
  $reply=Invoke-RestMethod -Uri $ApiUrl -Headers $headers -Method Get -TimeoutSec 25 -ErrorAction Stop
  Write-Output 'ORDER_SOURCE=AUTHENTICATED_SITE'
}
if ($null -eq $reply -or $reply.ok -ne $true -or $null -eq $reply.orders) { throw 'Unexpected site order response' }
$dir=Join-Path $WorkDir 'orders-pending'
if (-not (Test-Path $dir)) { New-Item -Path $dir -ItemType Directory -Force | Out-Null }
$created=0
$existing=0
$rejected=0
$utf8=New-Object System.Text.UTF8Encoding($false)
foreach($order in @($reply.orders)) {
  $idText=[string]$order.id
  if ($idText -notmatch '^[1-9][0-9]{0,12}$' -or $null -eq $order.items -or @($order.items).Count -eq 0) { $rejected++; continue }
  $id=[long]$idText
  if ($FixtureFile -eq '' -and $id -le $cutoff) { $existing++; continue }
  $items=@($order.items)
  $rows=New-Object System.Text.StringBuilder
  foreach($it in $items) {
    $barcode=E $it.barcode
    $name=E $it.product_name
    $qty=E $it.quantity
    $price=E $it.unit_price
    [void]$rows.Append("<tr><td>$name<br><small>$barcode</small></td><td>$qty</td><td>$price</td></tr>")
  }
  $customer=E $order.customer_name
  $phone=E $order.phone
  $address=E $order.address
  $status=E $order.payment_status
  $method=E $order.payment_method
  $total=E $order.total
  $date=E $order.created_at
  $html=@"
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8">
<title>سفارش سایت شماره $id</title>
<style>
@page {size:80mm auto;margin:3mm}
body {font:13px Tahoma,Arial,sans-serif;max-width:74mm;margin:auto;color:#111}
h2,p {margin:5px 0}table {width:100%;border-collapse:collapse}
td,th {border-bottom:1px dashed #777;padding:5px 2px;text-align:right}
small {font-size:9px;color:#444}.notice {border:2px solid #222;padding:5px}
@media print {.toolbar {display:none}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">چاپ سفارش</button></div>
<h2>طهران مارکت - سفارش اینترنتی</h2><p>شماره: $id</p><p>تاریخ: $date</p>
<p class="notice">پیش‌فاکتور آماده‌سازی — ثبت قطعی در پیرامید تأیید نشده</p>
<p>مشتری: $customer</p><p>تلفن: $phone</p><p>نشانی: $address</p>
<p>وضعیت پرداخت: $status | روش: $method</p>
<table><thead><tr><th>کالا / بارکد</th><th>تعداد</th><th>قیمت واحد</th></tr></thead><tbody>
$($rows.ToString())
</tbody></table><h3>مبلغ سفارش: $total تومان</h3>
<p>کالای تحویل‌شده را با بارکد و مقدار فاکتور مطابقت دهید.</p>
</body></html>
"@
  $bytes=[System.Text.Encoding]::UTF8.GetBytes($html)
  $sha=[System.Security.Cryptography.SHA256]::Create()
  try { $hash=[BitConverter]::ToString($sha.ComputeHash($bytes)).Replace('-','').Substring(0,12) }
  finally { $sha.Dispose() }
  $dest=Join-Path $dir ("order-" + $id + "-" + $hash + ".html")
  if (Test-Path $dest) { $existing++; continue }
  $temp=Join-Path $dir ([guid]::NewGuid().ToString('N')+'.tmp')
  try {
    [IO.File]::WriteAllText($temp,$html,$utf8)
    [IO.File]::Move($temp,$dest)
    $created++
  } finally { if (Test-Path $temp) { Remove-Item $temp -Force } }
}
Write-Output ('ORDERS_NEW_PRINTABLE=' + $created)
Write-Output ('ORDERS_ALREADY_QUEUED=' + $existing)
Write-Output ('ORDERS_REJECTED=' + $rejected)
Write-Output 'POS_INVOICE_CREATED=NO'
Write-Output 'SITE_ORDER_ACK_SENT=NO'
