# Tehran Market ↔ Pyramid bridge (Persian operations report)

Report: 2026-11-17. Branch: feature/pyramid-bridge-v1. POS: EXSOM-J3, Windows 10, D:\Kara\Pyramid\pyramid.exe.

## Completed and tested
- Live read-only SQL 2008 R2 connection to database 1405 / warehouse 103, using local integrated authentication only.
- Product snapshot: 9938 warehouse rows; 9695 rows potentially eligible for exact-match review; 243 quarantined; 729 with positive inventory. The 9695 figure is NOT the matched-to-site count.
- D:\TehranBridge\TehranPyramidReadOnly.ps1 writes an atomic local JSON snapshot. Scheduled task TehranPyramid-ReadOnly-Snapshot runs every two minutes. Scheduler error was fixed and success code 0 observed.
- D:\TehranBridge\TehranOrdersInbox.ps1 creates UTF-8 printable 80 mm order receipts in orders-pending. One synthetic order was printed to HTML; repeating it produced no duplicate.
- Scheduled task TehranPyramid-NewOrders-PrintableInbox runs every minute, but does not fetch orders until the existing site sync key is explicitly installed locally and a safe watermark is stored.
- Current site HTTPS endpoint /pyramid_api.php?action=status returns HTTP 401 without a key. No client secret has been copied to the POS.
- A fresh COPY_ONLY backup of active database 1405 was verified and restored to isolated TehranPyramidIntegrationLab.
- The EShop_FactorInsert, EShop_FactorDetailInsert and Sodor_Factor stored procedures were tested ONLY in the lab copy. A synthetic sale temporarily reduced one product from 21 to 20; outer rollback restored 21. No live invoice was posted.
- Bridge directory file ACL grants access only to local operator Exsom, Administrators, SYSTEM.

## Remaining mandatory safety gates
1. The owner must install the pre-existing site synchronization key directly on EXSOM-J3, never in chat or GitHub. The interactive setup script D:\TehranBridge\SetupSiteKey.ps1 verifies it against the site's authenticated API and initializes the starting order watermark. The key lives in protected cPanel storage/pyramid_sync_key.php.
2. Verify whether Pyramid Price_Forosh is stored in rial or toman against official vendor documents or an authorized shop operator. price_unit currently remains UNVERIFIED. No price or inventory is uploaded.
3. Produce exact-match barcode / product code comparison with the site's ~9849 records. Preserve leading zeros. Block ambiguous, missing, weighted and multiple-unit products.
4. Current site pyramid_api.php write actions are deliberately disabled. Require fresh live-site backup, staging migration, complete payment/order tests, and audited safe synchronization before enabling them.
5. Before any real POS invoice, confirm vendor-supported EShop API contract, tax/payment behavior, cancellation and refund handling, duplicate prevention and exact customer/order mapping.
6. No live SQL writes and no site production updates have been performed. Do not report integration as production complete.

## Safe disable
PowerShell commands:
Disable-ScheduledTask -TaskName TehranPyramid-ReadOnly-Snapshot
Disable-ScheduledTask -TaskName TehranPyramid-NewOrders-PrintableInbox

## Local files
D:\TehranBridge\TehranPyramidReadOnly.ps1
D:\TehranBridge\TehranOrdersInbox.ps1
D:\TehranBridge\SetupSiteKey.ps1
D:\TehranBridge\pyramid-products-readonly.json
D:\TehranBridge\PyramidEShopLabRollback.sql
D:\TehranBridge\PyramidInvoiceLabRollback.sql
D:\TehranBridge\pyramid_1405_lab_copy.bak
D:\TehranBridge\orders-pending\
