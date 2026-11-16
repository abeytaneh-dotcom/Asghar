# Tehran/Pyramid read-only snapshot agent. No outbound requests and no database writes.
# Run from a limited Windows account with read access to SQL Server 2008 R2.
[CmdletBinding()]
param(
  [string]$OutputDir = 'D:\TehranBridge',
  [string]$Database = '1405',
  [string]$Warehouse = '103'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($Database -notmatch '^[0-9A-Za-z_]{1,64}$' -or $Warehouse -notmatch '^[0-9A-Za-z_-]{1,12}$') { throw 'Invalid SQL parameters' }
if ($Database -ne '1405') { throw 'Only verified active Pyramid database 1405 is allowed at this stage' }
if (-not (Test-Path $OutputDir -PathType Container)) { New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null }
$conn = New-Object System.Data.SqlClient.SqlConnection
$conn.ConnectionString = "Data Source=localhost;Initial Catalog=$Database;Integrated Security=SSPI;Connect Timeout=10;Application Name=TehranPyramidReadOnly"
$records = New-Object 'System.Collections.Generic.List[object]'
$seenCodes = @{}
$seenBarcodes = @{}
$anomalies = New-Object 'System.Collections.Generic.List[object]'
$today = [DateTime]::UtcNow.ToString('o')
try {
  $conn.Open()
  $command = $conn.CreateCommand()
  $command.CommandTimeout = 40
  $command.CommandText = @'
SELECT k.K_Code, k.Barcode, k.Name1, k.R_Unit1_2,
       a.A_Code, a.Mojodi, ISNULL(a.Reserve,0) AS Reserve,
       a.Price_Forosh, a.Price_Internet
FROM dbo.KalaId AS k
INNER JOIN dbo.Anbar AS a ON a.K_Code = k.K_Code
WHERE a.A_Code = @wh
ORDER BY k.K_Code
'@
  $param = $command.Parameters.Add('@wh',[System.Data.SqlDbType]::VarChar,12)
  $param.Value = $Warehouse
  $reader = $command.ExecuteReader([System.Data.CommandBehavior]::SequentialAccess)
  try {
    while ($reader.Read()) {
      $kcode = [string]$reader['K_Code']
      $barcode = ([string]$reader['Barcode']).Trim()
      $name = [string]$reader['Name1']
      $quantity = [decimal]$reader['Mojodi']
      $reserved = [decimal]$reader['Reserve']
      $unitRatio = if ($reader['R_Unit1_2'] -is [DBNull]) { [decimal]0 } else { [decimal]$reader['R_Unit1_2'] }
      $salePrice = [decimal]$reader['Price_Forosh']
      $internetPrice = [decimal]$reader['Price_Internet']
      $safe = $true
      $reasons = New-Object 'System.Collections.Generic.List[string]'
      if ([string]::IsNullOrWhiteSpace($barcode)) { $safe = $false; $reasons.Add('missing_barcode') }
      if ($barcode -ne '' -and $seenBarcodes.ContainsKey($barcode)) { $safe = $false; $reasons.Add('duplicate_barcode') }
      if ($seenCodes.ContainsKey($kcode)) { $safe = $false; $reasons.Add('duplicate_kcode') }
      if ($unitRatio -ne 0 -and $unitRatio -ne 1) { $safe = $false; $reasons.Add('multiple_units_needs_review') }
      if ($quantity -ne [decimal]::Truncate($quantity) -or $reserved -ne [decimal]::Truncate($reserved)) { $safe = $false; $reasons.Add('fractional_stock_needs_review') }
      if ($salePrice -le 0) { $safe = $false; $reasons.Add('missing_sale_price') }
      if ($quantity -lt 0) { $safe = $false; $reasons.Add('negative_physical_stock') }
      if ($reserved -lt 0) { $safe = $false; $reasons.Add('negative_reserved_stock') }
      if ($barcode -ne '') { $seenBarcodes[$barcode] = $true }
      $seenCodes[$kcode] = $true
      $available = [decimal]::Max([decimal]0, $quantity - $reserved)
      $entry = [pscustomobject]@{
        k_code = $kcode
        barcode = $barcode
        name = $name
        warehouse = [string]$reader['A_Code']
        stock_physical = $quantity
        stock_reserved = $reserved
        stock_available = [decimal]::Truncate($available)
        price_forosh_raw = $salePrice
        price_internet_raw = $internetPrice
        price_unit = 'UNVERIFIED'
        unit_ratio = $unitRatio
        sync_eligible = $safe
        review_reasons = @($reasons.ToArray())
      }
      $records.Add($entry)
      if (-not $safe) {
        $anomalies.Add([pscustomobject]@{ k_code=$kcode; reasons=@($reasons.ToArray()) })
      }
    }
  } finally { $reader.Close() }
} finally {
  $conn.Close()
  $conn.Dispose()
}
# Do not send catalogue/credentials to any service until a verified mapping and price unit exist.
$summary = [ordered]@{
  status = 'READ_ONLY_DRY_RUN'
  database = $Database
  warehouse = $Warehouse
  generated_at_utc = $today
  products_seen = $records.Count
  eligible_for_future_exact_match = @($records | Where-Object {$_.sync_eligible}).Count
  requiring_review = $anomalies.Count
  positive_available_stock = @($records | Where-Object {$_.stock_available -gt 0}).Count
  site_push_enabled = $false
  pyramid_invoice_write_enabled = $false
  price_unit_confirmed = $false
}
$payload = [pscustomobject]@{ summary=$summary; products=$records.ToArray() }
$utf8 = New-Object System.Text.UTF8Encoding($false)
$latest = Join-Path $OutputDir 'pyramid-products-readonly.json'
$tmp = Join-Path $OutputDir ('pyramid-products-' + [guid]::NewGuid().ToString('N') + '.tmp')
try {
  [System.IO.File]::WriteAllText($tmp, (ConvertTo-Json -InputObject $payload -Depth 7 -Compress), $utf8)
  if (Test-Path $latest) { [System.IO.File]::Replace($tmp,$latest,$null) }
  else { [System.IO.File]::Move($tmp,$latest) }
} finally { if (Test-Path $tmp) { Remove-Item -LiteralPath $tmp -Force } }
Write-Output ('BRIDGE_STATUS=' + $summary.status)
Write-Output ('DB=' + $summary.database)
Write-Output ('WAREHOUSE=' + $summary.warehouse)
Write-Output ('ROWS=' + $summary.products_seen)
Write-Output ('ELIGIBLE_FOR_REVIEW=' + $summary.eligible_for_future_exact_match)
Write-Output ('QUARANTINED=' + $summary.requiring_review)
Write-Output ('AVAILABLE_POSITIVE=' + $summary.positive_available_stock)
Write-Output 'SITE_WRITES=DISABLED'
Write-Output 'POS_WRITES=DISABLED'
Write-Output ('SNAPSHOT_FILE=' + $latest)
