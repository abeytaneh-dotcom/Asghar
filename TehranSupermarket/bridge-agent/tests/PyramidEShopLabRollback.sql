-- This test runs exclusively in a separate restored copy of database 1405.
-- Every synthetic insert is wrapped in an outer transaction and rolled back.
USE [TehranPyramidIntegrationLab];
SET NOCOUNT ON;
SET XACT_ABORT ON;
DECLARE @beforeHead bigint, @beforeTracking bigint, @beforeStock money;
DECLARE @w uniqueidentifier, @g uniqueidentifier, @order uniqueidentifier, @c uniqueidentifier;
DECLARE @amount money, @number numeric(18,0), @ret int, @detailResult int;
SELECT TOP (1) @w=Id, @amount=Price_Forosh, @beforeStock=Mojodi FROM dbo.Anbar WHERE Mojodi>=10 AND Price_Forosh>0 AND Id IS NOT NULL ORDER BY K_Code;
IF @w IS NULL RAISERROR('No suitable synthetic test warehouse row exists',16,1);
SET @g=NEWID(); SET @order=NEWID(); SET @c=NEWID();
SELECT @beforeHead=COUNT_BIG(*) FROM dbo.TmpHeadH;
SELECT @beforeTracking=COUNT_BIG(*) FROM dbo.TrackingHeader;
BEGIN TRY
    BEGIN TRANSACTION;
    EXEC @ret = dbo.EShop_FactorInsert
      @Number=@number OUTPUT,
      @CustomerId=@c,
      @WearhouseGoodsId=@w,
      @TotalFactorPrice=@amount,
      @Note=N'LOCAL ISOLATED DRY RUN',
      @GUID=@g, @Date='2026-11-17', @NumberOrder=98765123,
      @Name=N'TEST ONLY', @Mobile='09000000000', @Addr=N'TEST',
      @Email='', @PostalCode='', @Position='', @OrderId=@order,
      @DeliveryFamily=N'TEST ONLY', @OrderDate='2026-11-17T00:00:00',
      @DeliveryMoBile='09000000000', @DeliVeryAddr=N'TEST',
      @Description=N'ISOLATED DRY RUN', @DelivaryDate='', @FactorType=1;
    PRINT 'HEADER_PROC_RETURN=' + ISNULL(CAST(@ret AS VARCHAR(25)),'NULL');
    PRINT 'HEADER_OUTPUT_NUMBER=' + ISNULL(CAST(@number AS VARCHAR(25)),'NULL');
    SELECT 'TMP_HEADER_DELTA',COUNT_BIG(*)-@beforeHead FROM dbo.TmpHeadH;
    SELECT 'TRACKING_HEADER_DELTA',COUNT_BIG(*)-@beforeTracking FROM dbo.TrackingHeader;
    SELECT 'TRACKING_ORDER_COUNT',COUNT_BIG(*) FROM dbo.TrackingHeader WHERE Id=@order;
    IF @ret>0
    BEGIN
      EXEC @detailResult = dbo.EShop_FactorDetailInsert
        @Number=@ret,@CustomerId=@c,@WarehouseGoodsId=@w,
        @RowNumber=1,@Qty=1,@SalesPrice=@amount,@Mobile='09000000000';
      PRINT 'DETAIL_PROC_RETURN=' + ISNULL(CAST(@detailResult AS VARCHAR(25)),'NULL');
      SELECT 'TMP_DETAIL_COUNT',COUNT_BIG(*) FROM dbo.TmpHavaleh WHERE Id_Havaleh=@ret;
    END
    SELECT 'PHYSICAL_STOCK_DURING_TEST',Mojodi FROM dbo.Anbar WHERE Id=@w;
    ROLLBACK TRANSACTION;
    PRINT 'OUTER_TRANSACTION_ROLLED_BACK=YES';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT>0 ROLLBACK TRANSACTION;
    PRINT 'TEST_ERROR_NUMBER=' + CAST(ERROR_NUMBER() AS VARCHAR(20));
    PRINT 'TEST_ERROR_MESSAGE=' + ERROR_MESSAGE();
    PRINT 'OUTER_TRANSACTION_ROLLED_BACK=YES';
END CATCH;
SELECT 'TMP_HEADER_AFTER',COUNT_BIG(*)-@beforeHead FROM dbo.TmpHeadH;
SELECT 'TRACKING_HEADER_AFTER',COUNT_BIG(*)-@beforeTracking FROM dbo.TrackingHeader;
SELECT 'STOCK_AFTER_MINUS_BEFORE',Mojodi-@beforeStock FROM dbo.Anbar WHERE Id=@w;
