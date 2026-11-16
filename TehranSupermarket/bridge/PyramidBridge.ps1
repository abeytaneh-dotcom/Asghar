# Tehran Market <-> Pyramid SQL Server 2008 R2 bridge
# Default is read-only audit. POS posting and website pushes are opt-in only.
[CmdletBinding()]
param(
  [ValidateSet('Audit','OrderPreview','SyncOnce','SandboxTest')][string]$Mode='Audit',
  [string]$ConfigPath='D:\TehranBridge\bridge-config.json'
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Read-Config {
  if (!(Test-Path $ConfigPath)) { throw "Missing bridge config; run Audit only with a local config." }
  $c=Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
  if (($c.database -ne '1405' -and $c.database -ne 'TehranBridgeSandbox1405') -or $c.warehouse -ne '103') { throw 'Unexpected production database or warehouse: fail closed' }
  if ($c.website -ne 'https://tehranmarket-baharestan.ir') { throw 'Unexpected website origin: fail closed' }
  return $c
}
function Open-Sql([string]$database) {
  $cs='Data Source=localhost;Initial Catalog='+$database+';Integrated Security=SSPI;Application Name=TehranMarketBridge;Connect Timeout=8'
  $db=New-Object System.Data.SqlClient.SqlConnection($cs); $db.Open();return $db
}
function Command($db,$tx,[string]$sql,[bool]$stored=$false) {
  $x=$db.CreateCommand();$x.CommandText=$sql;$x.CommandTimeout=30
  if ($null -ne $tx) { $x.Transaction=$tx }
  if ($stored) { $x.CommandType=[Data.CommandType]::StoredProcedure }
  return $x
}
function Parameter($cmd,[string]$name,[string]$type,$value,[string]$direction='Input') {
  $t=[System.Data.SqlDbType]::$type
  $p=$cmd.Parameters.Add($name,$t)
  if ($type -eq 'Decimal') {$p.Precision=18;$p.Scale=0}
  if ($type -in @('NVarChar','VarChar','NText')) {
    $p.Size= if ($type -eq 'NText') {-1} elseif ($null -eq $value) {200} else {[Math]::Max(50,([string]$value).Length)}
  }
  $p.Direction=[System.Data.ParameterDirection]::$direction
  if ($direction -eq 'Input') { $p.Value=if($null -eq $value){[DBNull]::Value}else{$value} }
  return $p
}
function Rows($cmd) {
  $a=New-Object System.Data.SqlClient.SqlDataAdapter($cmd)
  $table=New-Object System.Data.DataTable
  [void]$a.Fill($table)
  return ,$table
}
function Catalog($db,[string]$warehouse) {
  $q=Command $db $null @'
SELECT k.K_Code AS code, k.Barcode AS barcode, a.Id AS warehouse_id,
       a.Mojodi AS quantity, a.Reserve AS reserved,
       a.Price_Forosh AS sale_price, a.Price_Internet AS internet_price
FROM dbo.Anbar a
JOIN dbo.KalaId k ON k.K_Code=a.K_Code
WHERE a.A_Code=@warehouse ORDER BY k.K_Code
'@
  [void](Parameter $q '@warehouse' 'VarChar' $warehouse)
  return [pscustomobject]@{Table=(Rows $q)}
}
function To-Price($v,[string]$unit) {
  $n=[decimal]$v
  if($n -lt 0){throw 'Negative price'}
  if($unit -eq 'rial'){ return [long][math]::Floor(($n/10)+0.5) }
  if($unit -eq 'toman'){return [long][math]::Floor($n+0.5)}
  throw 'Unsupported price unit'
}
function Catalog-Items($table,$config) {
  $seen=@{};$items=New-Object System.Collections.Generic.List[object]
  $invalid=0;$frac=0
  foreach($r in $table.Table.Rows) {
    $code=[string]$r['code'];$barcode=([string]$r['barcode']).Trim()
    if([string]::IsNullOrWhiteSpace($barcode) -or $seen.ContainsKey($barcode)){ $invalid++;continue }
    $seen[$barcode]=$true
    $quantity=if($r['quantity'] -is [DBNull]){[decimal]0}else{[decimal]$r['quantity']}
    $reserved=if($r['reserved'] -is [DBNull]){[decimal]0}else{[decimal]$r['reserved']}
    if($quantity -ne [math]::Truncate($quantity)){$frac++;continue}
    if($quantity -lt 0){$quantity=0}
    $qty=[long][math]::Floor([math]::Max(0,($quantity - [math]::Max(0,$reserved))))
    $raw=$r['sale_price'];if($raw -is [DBNull] -or [decimal]$raw -le 0){$invalid++;continue}
    $price=To-Price $raw $config.source_price_unit
    if($price -le 0){$invalid++;continue}
    $items.Add([pscustomobject]@{barcode=$barcode;code2=$code;stock=$qty;price=$price;price_unit='toman'})
  }
  return [pscustomobject]@{Items=$items;Invalid=$invalid;Fractional=$frac}
}
function Website($cfg,[string]$action,$payload=$null,[string]$requestId='') {
  $keyFile=[string]$cfg.sync_key_file
  if(!(Test-Path -LiteralPath $keyFile)){throw 'Sync key missing: nothing uploaded'}
  $key=(Get-Content -LiteralPath $keyFile -Raw -Encoding ASCII).Trim()
  if($key.Length -lt 32){throw 'Sync key too short'}
  $url=$cfg.website.TrimEnd('/')+'/pyramid_api.php?action='+[Uri]::EscapeDataString($action)
  $heads=@{'X-Sync-Key'=$key;'X-Device-ID'='EXSOM-J3'}
  if($requestId -ne ''){$heads['X-Request-ID']=$requestId}
  $args=@{Uri=$url;Headers=$heads;TimeoutSec=35;ErrorAction='Stop'}
  if($null -ne $payload){$args.Method='POST';$args.Body=($payload|ConvertTo-Json -Depth 12 -Compress);$args.ContentType='application/json; charset=utf-8'}
  else{$args.Method='GET'}
  $r=Invoke-RestMethod @args
  if($null -eq $r -or !$r.ok){throw ('Remote API rejected '+$action)}
  return $r
}
function Id-Guid([long]$id,[string]$kind) {
  $data=[Text.Encoding]::UTF8.GetBytes('tehranmarket-baharestan.ir|'+$kind+'|'+$id)
  $sha=[Security.Cryptography.SHA256]::Create()
  try{$hash=$sha.ComputeHash($data)}finally{$sha.Dispose()}
  $bytes=New-Object byte[] 16;[Array]::Copy($hash,$bytes,16)
  return (New-Object Guid (,$bytes))
}
function Sql-Value($db,$tx,[string]$query,[string]$pName,$value,[string]$pType) {
  $cmd=Command $db $tx $query
  if($pName){[void](Parameter $cmd $pName $pType $value)}
  return $cmd.ExecuteScalar()
}
function Call-Proc($db,$tx,[string]$name,[object[]]$parameters) {
  $cmd=Command $db $tx $name $true
  $result=Parameter $cmd '@RETURN_VALUE' 'Int' $null 'ReturnValue'
  foreach($p in $parameters){[void](Parameter $cmd $p[0] $p[1] $p[2])}
  [void]$cmd.ExecuteNonQuery()
  return [int]$result.Value
}
function Sql-StockMatch($db,$tx,[string]$barcode,[string]$warehouse) {
  $q=Command $db $tx @'
SELECT TOP (2) a.Id warehouse_id, a.Price_Forosh price_rial, a.Mojodi quantity
FROM dbo.KalaId k
JOIN dbo.Anbar a ON a.K_Code=k.K_Code
WHERE k.Barcode=@barcode AND a.A_Code=@warehouse
'@
  [void](Parameter $q '@barcode' 'VarChar' $barcode)
  [void](Parameter $q '@warehouse' 'VarChar' $warehouse)
  return [pscustomobject]@{Table=(Rows $q)}
}
function Post-Order($db,$config,$o,[bool]$dryRun) {
  $orderId=[long]$o.id
  if($orderId -lt 1 -or $orderId -gt 9999999){throw 'Order ID outside safe 8-digit invoice range'}
  if($o.status -eq 'cancelled'){throw 'Cancelled order skipped'}
  if(($o.payment_method -ne 'cod') -and ($o.payment_status -ne 'paid')){throw 'Unpaid online order skipped'}
  if([decimal]$o.delivery_fee -ne 0 -or [decimal]$o.total -ne [decimal]$o.subtotal){throw 'Shipping/discount accounting mapping pending verification'}
  $number=[int](70000000+$orderId)
  $tracking=Id-Guid $orderId 'tracking'
  $factor=Id-Guid $orderId 'factor'
  $tx=$db.BeginTransaction([Data.IsolationLevel]::Serializable)
  try {
    $lock=Command $db $tx "DECLARE @ret int;EXEC @ret=sp_getapplock @Resource=@r,@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=8000;SELECT @ret"
    [void](Parameter $lock '@r' 'NVarChar' ('tehranmarket_order_'+$orderId))
    if([int]$lock.ExecuteScalar() -lt 0){throw 'Unable to acquire order lock'}
    $previous=Sql-Value $db $tx 'SELECT COUNT(*) FROM dbo.TrackingHeader WITH(UPDLOCK,HOLDLOCK) WHERE Id=@x' '@x' $tracking 'UniqueIdentifier'
    $complete=Sql-Value $db $tx 'SELECT COUNT(*) FROM dbo.headH WITH(UPDLOCK,HOLDLOCK) WHERE Nu_Havaleh=@x AND Kind_Flag=''7''' '@x' ([string]$number) 'VarChar'
    if([int]$previous -gt 0) {
      if([int]$complete -ne 1){throw 'Previous attempt is pending/ambiguous: manual review required'}
      $id=Sql-Value $db $tx 'SELECT TOP 1 Id_Havaleh FROM dbo.headH WHERE Nu_Havaleh=@x AND Kind_Flag=''7''' '@x' ([string]$number) 'VarChar'
      $tx.Rollback()
      return [pscustomobject]@{state='already_posted';invoice=[string]$id}
    }
    if([int]$complete -gt 0){throw 'Invoice number collision: manual review required'}
    $tempExisting=Sql-Value $db $tx 'SELECT COUNT(*) FROM dbo.TmpHeadH WHERE Nu_Havaleh=@x' '@x' ([string]$number) 'VarChar'
    if([int]$tempExisting -gt 0){throw 'Draft invoice number collision'}
    $lines=@($o.items)
    if($lines.Count -eq 0 -or $lines.Count -gt 300){throw 'Invalid line count'}
    $prepared=New-Object System.Collections.Generic.List[object]
    $sum=[decimal]0
    foreach($line in $lines){
      $barcode=([string]$line.barcode).Trim()
      $qty=[int]$line.quantity
      if(!$barcode -or $qty -lt 1 -or $qty -gt 1000){throw 'Missing barcode or invalid quantity'}
      $matches=Sql-StockMatch $db $tx $barcode ([string]$config.warehouse)
      if($matches.Table.Rows.Count -ne 1){throw 'Ambiguous or missing Pyramid barcode'}
      $rec=$matches.Table.Rows[0]
      if([decimal]$rec['quantity'] -lt $qty){throw 'Pyramid stock is not sufficient'}
      $unit=[decimal]$line.unit_price
      if($unit -le 0){throw 'Invalid unit price'}
      $sum+=$unit*$qty
      $prepared.Add([pscustomobject]@{Warehouse=[Guid]$rec['warehouse_id'];Qty=$qty;UnitRial=($unit*10)})
    }
    if($sum -ne [decimal]$o.subtotal){throw 'Website order total does not match items'}
    if($dryRun){$tx.Rollback();return [pscustomobject]@{state='validated';lines=$prepared.Count}}
    $first=$prepared[0]
    $date=[DateTime]::Today
    $customer=[string]$o.customer_name
    $mobile=[string]$o.phone
    if($mobile -notmatch '^09[0-9]{9}$'){throw 'Invalid buyer mobile'}
    $address=[string]$o.address
    $r=Call-Proc $db $tx 'dbo.EShop_FactorInsert' @(
      @('@Number','Decimal',0),@('@CustomerId','UniqueIdentifier',[Guid]::Empty),
      @('@WearhouseGoodsId','UniqueIdentifier',$first.Warehouse),
      @('@TotalFactorPrice','Money',([decimal]$o.total*10)),
      @('@Note','NText',('TehranMarket order '+$orderId)),
      @('@GUID','UniqueIdentifier',$factor),@('@Date','Date',$date),
      @('@NumberOrder','Int',$number),@('@Name','NVarChar',$customer),
      @('@Mobile','VarChar',$mobile),@('@Addr','NVarChar',$address),
      @('@Email','VarChar',''),@('@PostalCode','VarChar',''),
      @('@Position','VarChar',''),@('@OrderId','UniqueIdentifier',$tracking),
      @('@DeliveryFamily','NVarChar',$customer),@('@OrderDate','DateTime',$date),
      @('@DeliveryMoBile','VarChar',$mobile),@('@DeliVeryAddr','NVarChar',$address),
      @('@Description','NVarChar',([string]$o.delivery_slot)),
      @('@DelivaryDate','VarChar',''),@('@FactorType','TinyInt',1)
    )
    if($r -le 0){throw 'Pyramid order header failed'}
    $rowNumber=0
    foreach($l in $prepared) {
      $rowNumber++
      $dr=Call-Proc $db $tx 'dbo.EShop_FactorDetailInsert' @(
        @('@Number','Decimal',$r),@('@CustomerId','UniqueIdentifier',[Guid]::Empty),
        @('@WarehouseGoodsId','UniqueIdentifier',$l.Warehouse),
        @('@RowNumber','SmallInt',$rowNumber),@('@Qty','Int',$l.Qty),
        @('@SalesPrice','Money',$l.UnitRial),@('@Mobile','VarChar',$mobile)
      )
      if($dr -ne 0){throw 'Pyramid order detail failed'}
    }
    $fin=Command $db $tx 'dbo.Sodor_Factor' $true
    $rc=Parameter $fin '@RETURN_VALUE' 'Int' $null 'ReturnValue'
    $output=Parameter $fin '@msg_num' 'Decimal' $null 'Output'
    [void](Parameter $fin '@TmpId' 'Decimal' $r)
    [void]$fin.ExecuteNonQuery()
    $invoice=[long]$output.Value
    if($invoice -le 0 -or [int]$rc.Value -le 0){throw 'Pyramid invoice finalization failed'}
    $byId=Sql-Value $db $tx 'SELECT COUNT(*) FROM dbo.headH WHERE Id_Havaleh=@x AND Kind_Flag=''7''' '@x' ([decimal]$invoice) 'Decimal'
    $byNumber=Sql-Value $db $tx 'SELECT COUNT(*) FROM dbo.headH WHERE Nu_Havaleh=@n AND Kind_Flag=''7''' '@n' ([string]$number) 'VarChar'
    if([int]$byId -ne 1 -or [int]$byNumber -ne 1){throw 'Final invoice identity not confirmed'}
    $tx.Commit()
    return [pscustomobject]@{state='posted';invoice=[string]$invoice}
  } catch {
    if($tx.Connection){try{$tx.Rollback()}catch{}}
    throw
  }
}
function Save-ReceiptHtml($o,[string]$invoice) {
  $dir='D:\TehranBridge\receipts'
  if(!(Test-Path $dir)){[void](New-Item -ItemType Directory -Path $dir)}
  $escape={param($v) [System.Net.WebUtility]::HtmlEncode([string]$v)}
  $id=[long]$o.id
  $name=(& $escape $o.customer_name);$phone=(& $escape $o.phone);$addr=(& $escape $o.address)
  $lines=New-Object System.Collections.Generic.List[string]
  foreach($line in @($o.items)){
    $p=(& $escape $line.product_name)
    $amount=[long]$line.quantity*[long]$line.unit_price
    $lines.Add('<tr><td>'+$p+'</td><td>'+[int]$line.quantity+'</td><td>'+$amount.ToString('N0')+'</td></tr>')
  }
  $html='<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>فاکتور طهران مارکت</title><style>@page{size:80mm auto;margin:3mm}body{font:13px Tahoma,sans-serif;max-width:74mm;margin:auto;color:#111}h1{text-align:center;font-size:17px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px dashed #999;padding:5px 2px;text-align:right}h2{font-size:14px;text-align:center}.sum{font-size:16px;font-weight:bold}small{display:block;margin:5px 0}button{margin:12px 0}@media print{button{display:none}}</style></head><body><h1>طهران مارکت</h1><h2>فاکتور فروش اینترنتی #'+(& $escape $invoice)+'</h2><small>شماره سفارش سایت: '+$id+'</small><small>مشتری: '+$name+'</small><small>تلفن: '+$phone+'</small><small>نشانی: '+$addr+'</small><table><tr><th>کالا</th><th>تعداد</th><th>تومان</th></tr>'+($lines -join '')+'</table><p class="sum">مبلغ کل: '+([long]$o.total).ToString('N0')+' تومان</p><small>روش پرداخت: '+(& $escape $o.payment_method)+'</small><button onclick="window.print()">چاپ فاکتور</button></body></html>'
  $file=Join-Path $dir ('order-'+$id+'.html')
  [IO.File]::WriteAllText($file,$html,(New-Object System.Text.UTF8Encoding($false)))
  return $file
}

function Main {
  $cfg=Read-Config
  $db=Open-Sql ([string]$cfg.database)
  try {
    $rows=Catalog $db ([string]$cfg.warehouse)
    $cat=Catalog-Items $rows $cfg
    Write-Output ('AUDIT_DB='+$cfg.database+' WAREHOUSE='+$cfg.warehouse+' TOTAL='+$rows.Table.Rows.Count+' VALID='+$cat.Items.Count+' UNMAPPED='+$cat.Invalid+' FRACTIONAL='+$cat.Fractional)
    if($Mode -eq 'Audit'){return}
    if($Mode -eq 'SandboxTest'){
      if($cfg.database -ne 'TehranBridgeSandbox1405'){throw 'SandboxTest requires the isolated restored database'}
      $testRow=$null
      foreach($p in $rows.Table.Rows){if([decimal]$p['quantity'] -gt 2 -and [decimal]$p['sale_price'] -gt 0 -and ![string]::IsNullOrWhiteSpace([string]$p['barcode'])){$testRow=$p;break}}
      if($null -eq $testRow){throw 'No safe sandbox SKU'}
      $unit=To-Price $testRow['sale_price'] $cfg.source_price_unit
      $testOrder=[pscustomobject]@{id=900001;status='new';payment_method='cod';payment_status='unpaid';delivery_fee=0;subtotal=$unit;total=$unit;customer_name='Sandbox';phone='09999999999';address='SANDBOX';delivery_slot='sandbox';items=@([pscustomobject]@{barcode=[string]$testRow['barcode'];product_name='Sandbox Test Item';unit_price=$unit;quantity=1})}
      $result=Post-Order $db $cfg $testOrder $false
      Write-Output ('SANDBOX_POS_ORDER='+$result.state+' INVOICE='+$result.invoice)
      $receipt=Save-ReceiptHtml $testOrder ([string]$result.invoice)
      Write-Output ('SANDBOX_RECEIPT_READY='+[bool](Test-Path $receipt))
      return
    }

    if(-not $cfg.enabled){throw 'Synchronization disabled in local config'}
    $status=Website $cfg 'status'
    Write-Output ('WEBSITE_STATUS=PASS PENDING_ORDERS='+$status.pending_orders)
    if($Mode -eq 'SyncOnce' -and ((-not $status.write_permissions.catalog) -or (-not $status.write_permissions.order_ack))){throw 'Remote catalog and invoice acknowledgement permissions are not verified; no POS sales posted'}
    $ordersResult=Website $cfg 'orders'
    $orders=@($ordersResult.orders)
    $ready=0;$held=0
    foreach($o in $orders) {
      try {
        if($Mode -eq 'OrderPreview'){
          $result=Post-Order $db $cfg $o $true
          $ready++
        } else {
          if(-not $cfg.allow_live_pos_writes){throw 'Live invoice posting is not enabled'}
          $result=Post-Order $db $cfg $o $false
          $ack=Website $cfg 'order_ack' @{order_id=[long]$o.id;invoice_no=[string]$result.invoice} ('ack-pos-'+[long]$o.id)
          $receipt=Save-ReceiptHtml $o ([string]$result.invoice)
          Write-Output ('ORDER_ACK_OK='+[long]$o.id+' POS_INVOICE='+$result.invoice+' PRINT_READY='+[bool](Test-Path $receipt))
        }
      } catch { $held++;Write-Output ('ORDER_HELD='+[long]$o.id+' REASON='+$_.Exception.Message)}
    }
    if($Mode -eq 'SyncOnce') {
      if(-not $cfg.allow_live_catalog_writes){throw 'Catalog update disabled: no data transmitted'}
      # Re-read POS stock after each order is finalized/acknowledged.
      # Uploading the pre-sale snapshot would restore stock just sold in POS.
      $freshRows=Catalog $db ([string]$cfg.warehouse)
      $cat=Catalog-Items $freshRows $cfg
      Write-Output ('FRESH_POST_SALES_CATALOG='+$cat.Items.Count)
      $batchSize=150
      for($i=0;$i -lt $cat.Items.Count;$i+=$batchSize){
        $last=[Math]::Min($i+$batchSize,$cat.Items.Count)
        $items=@($cat.Items.GetRange($i,$last-$i))
        $id='pyr-catalog-'+[DateTime]::UtcNow.ToString('yyyyMMddHHmm')+'-'+[int]($i/$batchSize)
        $resp=Website $cfg 'products_batch' @{price_unit='toman';products=$items} $id
        Write-Output ('CATALOG_SENT='+$items.Count+' UPDATED='+$resp.updated)
      }
    }
    Write-Output ('ORDERS_PREVIEWED='+$ready+' HELD='+$held)
  } finally { $db.Dispose() }
}
Main
