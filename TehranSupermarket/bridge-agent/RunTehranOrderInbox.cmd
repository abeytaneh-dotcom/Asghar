@echo off
setlocal
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "D:\TehranBridge\TehranOrdersInbox.ps1" 1>"D:\TehranBridge\last-order-inbox-run.log" 2>&1
exit /b %ERRORLEVEL%
